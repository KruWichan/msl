<?php
// เริ่ม session อย่างปลอดภัย เพื่อป้องกัน Notice "session already active"
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

ini_set('display_errors', 1);
error_reporting(E_ALL);

// Path เชื่อมต่อฐานข้อมูล: db.php อยู่ใน home/includes/
require 'includes/db.php';

// --- การแบ่งหน้า (Pagination) ---
$articles_per_page = 10; // จำนวนบทความต่อหน้า
$current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $articles_per_page;

// --- เพิ่มส่วนรับค่าค้นหาและหมวดหมู่ ---
$search = trim($_GET['q'] ?? '');
$category_id = intval($_GET['category_id'] ?? 0);

// --- ดึงหมวดหมู่ทั้งหมด ---
$categories = $pdo->query("SELECT * FROM article_categories ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// --- ปรับ query นับจำนวนบทความทั้งหมด (ตาม filter) ---
$where = [];
$params = [];
if ($search !== '') {
    $where[] = "(a.title LIKE :search OR a.content LIKE :search)";
    $params[':search'] = "%$search%";
}
if ($category_id > 0) {
    $where[] = "a.category_id = :category_id";
    $params[':category_id'] = $category_id;
}
$whereSql = $where ? "WHERE " . implode(' AND ', $where) : "";

try {
    $stmt_count = $pdo->prepare("SELECT COUNT(*) FROM articles a $whereSql");
    $stmt_count->execute($params);
    $total_articles = $stmt_count->fetchColumn();
} catch (PDOException $e) {
    error_log("Database error counting articles: " . $e->getMessage());
    $total_articles = 0; // ตั้งเป็น 0 เพื่อป้องกันการหารด้วยศูนย์
}

// 2. คำนวณจำนวนหน้ารวม
$total_pages = ceil($total_articles / $articles_per_page);

// 3. ดึงข้อมูลบทความสำหรับหน้าปัจจุบัน
$articles = [];
try {
    $stmt_articles = $pdo->prepare("
        SELECT
            a.id,
            a.title,
            a.slug,
            a.excerpt,
            a.image,
            a.created_at,
            a.content,
            a.category_id,
            a.author_id,
            a.status,
            ac.name AS category_name
        FROM
            articles a
        LEFT JOIN
            article_categories ac ON a.category_id = ac.id
        $whereSql
        ORDER BY
            a.created_at DESC, a.id DESC
        LIMIT :limit OFFSET :offset
    ");
    foreach ($params as $k => $v) {
        $stmt_articles->bindValue($k, $v);
    }
    $stmt_articles->bindValue(':limit', $articles_per_page, PDO::PARAM_INT);
    $stmt_articles->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt_articles->execute();
    $articles = $stmt_articles->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Database error fetching articles: " . $e->getMessage());
    echo "<p class='text-center p-4 bg-red-100 text-red-800 rounded'>เกิดข้อผิดพลาดในการดึงข้อมูลบทความ กรุณาลองใหม่อีกครั้ง</p>";
}

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>บทความทั้งหมด - ชื่อเว็บไซต์ของคุณ</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* CSS เพิ่มเติมถ้าต้องการปรับแต่ง */
        .article-card {
            display: flex;
            flex-direction: column;
            border: 1px solid #e2e8f0; /* gray-200 */
            border-radius: 0.5rem; /* rounded-lg */
            overflow: hidden;
            background-color: #ffffff;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); /* shadow-md */
            transition: transform 0.2s ease-in-out;
        }
        .article-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 10px rgba(0, 0, 0, 0.15);
        }
        .article-image-container {
            width: 100%;
            padding-top: 56.25%; /* 16:9 Aspect Ratio */
            position: relative;
            overflow: hidden;
        }
        .article-image {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover; /* ใช้ cover เพื่อให้รูปภาพครอบคลุมพื้นที่ */
            display: block;
        }
    </style>
</head>
<body class="bg-gray-100 font-sans leading-normal tracking-normal">

    <?php include 'header.php'; ?>

    <div class="container mx-auto px-4 py-8 mt-12">
        <h1 class="text-4xl font-extrabold text-gray-800 mb-8 text-center">บทความทั้งหมด</h1>

        <!-- ฟอร์มค้นหาและเลือกหมวดหมู่ -->
        <form method="get" class="mb-6 flex flex-wrap gap-2 justify-center">
            <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="ค้นหาบทความ..." class="border rounded px-3 py-2 w-64">
            <select name="category_id" class="border rounded px-3 py-2">
                <option value="">-- ทุกหมวดหมู่ --</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['id'] ?>" <?= $category_id == $cat['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cat['name']) ?>
                    </option>
                <?php endforeach ?>
            </select>
            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded">ค้นหา</button>
        </form>

        <?php if (!empty($articles)): ?>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                <?php foreach ($articles as $article): ?>
                    <div class="article-card">
                        <a href="article_detail.php?slug=<?= urlencode($article['slug']) ?>" class="block">
                            <div class="article-image-container">
                                <?php
                                $html_image_src = str_replace('../', '', $article['image']);
                                $absolute_image_path_for_check = getcwd() . '/' . $html_image_src;
                                if (!empty($article['image']) && file_exists($absolute_image_path_for_check)):
                                ?>
                                    <img src="<?= htmlspecialchars($html_image_src) ?>"
                                         alt="<?= htmlspecialchars($article['title']) ?>"
                                         class="article-image">
                                <?php else: ?>
                                    <div class="article-image bg-gray-200 flex items-center justify-center text-gray-500">
                                        ไม่มีรูปภาพ
                                    </div>
                                <?php endif; ?>
                            </div>
                        </a>
                        <div class="p-4 flex-grow">
                            <h3 class="text-xl font-semibold text-gray-900 mb-2 truncate">
                                <a href="article_detail.php?slug=<?= urlencode($article['slug']) ?>" class="hover:text-blue-600">
                                    <?= htmlspecialchars($article['title']) ?>
                                </a>
                            </h3>
                            <p class="text-sm text-gray-600 mb-1">
                                <?php if (!empty($article['category_name'])): ?>
                                    หมวดหมู่:
                                    <a href="articles_by_category.php?id=<?= htmlspecialchars($article['category_id'] ?? '') ?>" class="text-blue-500 hover:underline">
                                        <?= htmlspecialchars($article['category_name'] ?? 'ไม่ระบุ') ?>
                                    </a>
                                <?php else: ?>
                                    หมวดหมู่: ไม่ระบุ
                                <?php endif; ?>
                            </p>
                            <p class="text-sm text-gray-600">
                                วันที่: <?= date('d/m/Y', strtotime($article['created_at'])) ?>
                                <?php if (!empty($article['status'])): ?>
                                    <span class="ml-2 text-xs px-2 py-1 rounded <?= $article['status'] === 'published' ? 'bg-green-200 text-green-800' : 'bg-gray-200' ?>">
                                        <?= htmlspecialchars($article['status']) ?>
                                    </span>
                                <?php endif; ?>
                            </p>
                            <!-- (เตรียมจุดสำหรับแสดงแท็ก/ผู้เขียน ถ้าต้องการ) -->
                            <!-- <div class="mb-1"><span class="tag-badge">สุขภาพ</span></div> -->
                            <div class="text-gray-700 text-base mt-2 mb-3 leading-relaxed">
    <?php
    $excerpt = $article['excerpt'];
    if (!$excerpt) {
        $content = html_entity_decode((string)($article['content'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        // ลบ <pre>, <code> และ tag HTML อื่นๆ
        $content = preg_replace('/<pre.*?<\/pre>/is', '', $content);
        $content = preg_replace('/<code.*?<\/code>/is', '', $content);
        $content = strip_tags($content);
        // ลบ &nbsp; และ unicode nbsp ทั้งหมด (รวมทั้ง entity ที่ยังเหลือ)
        $content = preg_replace('/(&nbsp;|&#160;|&#xA0;|\xC2\xA0|\xA0)+/iu', '', $content);
        // ลบ entity อื่นๆ ที่เหลือ
        $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        // ลบช่องว่างซ้ำและ trim (ลบทั้ง space, tab, \r, \n, &nbsp; และ unicode space)
        $content = preg_replace('/[ \t\r\n\0\x0B\xA0]+/u', ' ', $content);
        $content = trim($content);
        // ลบ space ที่อยู่ระหว่างตัวอักษรไทยกับ "หรือ" และ "หรือ" กับตัวอักษรไทย
        $content = preg_replace('/([ก-๙])\s*หรือ\s*([ก-๙])/u', '$1หรือ$2', $content);
        // ลบ space ซ้ำที่อาจเกิดขึ้นหลังจากข้างบน
        $content = preg_replace('/\s+/u', ' ', $content);
        // ลบ space ก่อน/หลังจุดหรือเครื่องหมายวรรคตอน
        $content = preg_replace('/\s*([.,;:!?])\s*/u', '$1', $content);
        $excerpt = mb_substr($content, 0, 300, 'UTF-8');
        if (mb_strlen($content, 'UTF-8') > 300) {
            $excerpt .= '...';
        }
    }
    echo htmlspecialchars($excerpt);
    ?>
</div>
                            <div class="mt-3">
                                <a href="article_detail.php?slug=<?= urlencode($article['slug']) ?>" class="inline-block bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition duration-200 text-sm">
                                    อ่านเพิ่มเติม
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="flex justify-center items-center space-x-2 mt-10">
                <?php if ($current_page > 1): ?>
                    <a href="?page=<?= $current_page - 1 ?>" class="px-4 py-2 rounded-lg bg-gray-300 text-gray-700 hover:bg-gray-400 transition duration-200">
                        ก่อนหน้า
                    </a>
                <?php endif; ?>

                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="?page=<?= $i ?>"
                       class="px-4 py-2 rounded-lg <?= ($i === $current_page) ? 'bg-blue-600 text-white' : 'bg-gray-300 text-gray-700 hover:bg-gray-400' ?> transition duration-200">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>

                <?php if ($current_page < $total_pages): ?>
                    <a href="?page=<?= $current_page + 1 ?>" class="px-4 py-2 rounded-lg bg-gray-300 text-gray-700 hover:bg-gray-400 transition duration-200">
                        ถัดไป
                    </a>
                <?php endif; ?>
            </div>

        <?php else: ?>
            <div class="text-center p-8 text-gray-500 text-lg">
                <p>ยังไม่มีบทความที่แสดงผลในขณะนี้.</p>
            </div>
        <?php endif; ?>
    </div>

    <?php include 'footer.php'; ?>

</body>
</html>