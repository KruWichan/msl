<?php
// เริ่ม session อย่างปลอดภัย เพื่อป้องกัน Notice "session already active"
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

ini_set('display_errors', 1);
error_reporting(E_ALL);

// Path เชื่อมต่อฐานข้อมูล: db.php อยู่ใน home/includes/
require 'includes/db.php'; 

$article = null; // กำหนดค่าเริ่มต้นเป็น null

// ตรวจสอบว่ามีการส่งค่า ID บทความมาหรือไม่
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $article_id = (int)$_GET['id'];

    try {
        // ดึงข้อมูลบทความจากฐานข้อมูล พร้อมชื่อหมวดหมู่, slug, author_id, status
        $stmt = $pdo->prepare("
            SELECT 
                a.id, 
                a.title, 
                a.slug,
                a.content, 
                a.excerpt,
                a.image, 
                a.created_at,
                a.author_id,
                a.status,
                ac.name AS category_name,
                ac.id AS category_id
            FROM 
                articles a
            LEFT JOIN 
                article_categories ac ON a.category_id = ac.id
            WHERE 
                a.id = :id
            LIMIT 1
        ");
        $stmt->bindParam(':id', $article_id, PDO::PARAM_INT);
        $stmt->execute();
        $article = $stmt->fetch(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        error_log("Database error fetching article detail: " . $e->getMessage());
    }
}

// ดึงบทความที่เกี่ยวข้อง (หมวดเดียวกัน ไม่รวมตัวเอง)
$related = [];
if ($article) {
    try {
        $relatedStmt = $pdo->prepare("
            SELECT 
                a.id, 
                a.title 
            FROM 
                articles a
            WHERE 
                a.category_id = :category_id 
                AND a.id != :id 
            ORDER BY 
                a.created_at DESC 
            LIMIT 5
        ");
        $relatedStmt->bindParam(':category_id', $article['category_id'], PDO::PARAM_INT);
        $relatedStmt->bindParam(':id', $article_id, PDO::PARAM_INT);
        $relatedStmt->execute();
        $related = $relatedStmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        error_log("Database error fetching related articles: " . $e->getMessage());
    }
}

// ดึงบทความล่าสุด (ยกเว้นบทความปัจจุบัน)
$latest = [];
try {
    $latestStmt = $pdo->prepare("
        SELECT id, title, excerpt, content, image, created_at
        FROM articles
        WHERE id != :id
        ORDER BY created_at DESC
        LIMIT 5
    ");
    $latestStmt->bindParam(':id', $article_id, PDO::PARAM_INT);
    $latestStmt->execute();
    $latest = $latestStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Database error fetching latest articles: " . $e->getMessage());
}

// (เตรียมจุดสำหรับระบบแท็ก, คอมเมนต์, ผู้เขียน, สถานะบทความ)
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $article ? htmlspecialchars($article['title']) . ' - ชื่อเว็บไซต์ของคุณ' : 'ไม่พบบทความ - ชื่อเว็บไซต์ของคุณ' ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* CSS เพิ่มเติม */
        .article-image-container {
            width: 100%;
            padding-top: 56.25%; /* 16:9 Aspect Ratio */
            position: relative;
            overflow: hidden;
            border-radius: 0.5rem; /* rounded-lg */
        }
        .article-image {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: contain; /* ใช้ contain เพื่อให้รูปภาพแสดงเต็ม ไม่ถูก crop */
            background-color: #f0f0f0; /* สีพื้นหลังเมื่อรูปภาพมีสัดส่วนไม่ตรง */
        }
        .article-content img {
            max-width: 100%; /* ทำให้รูปภาพในเนื้อหาไม่ล้นกรอบ */
            height: auto;
            display: block;
            margin-top: 1rem;
            margin-bottom: 1rem;
        }
        .latest-article-card {
            transition: box-shadow 0.2s, transform 0.2s;
        }
        .latest-article-card:hover {
            box-shadow: 0 8px 24px 0 rgba(37,99,235,0.15), 0 1.5px 6px 0 rgba(0,0,0,0.08);
            transform: translateY(-2px) scale(1.03);
        }
        .tag-badge {
            display: inline-block;
            background: #e0e7ff;
            color: #3730a3;
            border-radius: 0.25rem;
            padding: 0.1rem 0.5rem;
            font-size: 0.85em;
            margin-right: 0.25rem;
        }
    </style>
</head>
<body class="bg-gray-100 font-sans leading-normal tracking-normal">

    <?php  include 'header.php'; ?> 

    <div class="container mx-auto px-4 py-8 mt-12 flex flex-col md:flex-row gap-8">
        <div class="flex-1 min-w-0">
            <!-- เนื้อหาบทความหลัก -->
            <?php if ($article): ?>
                <div class="bg-white rounded-lg shadow-xl p-6 md:p-8">
                    <h1 class="text-4xl font-bold text-gray-900 mb-4 text-center"><?= htmlspecialchars($article['title']) ?></h1>
                    
                    <p class="text-sm text-gray-600 mb-2 text-center">
                        <?php if (!empty($article['category_name'])): ?>
                            หมวดหมู่:
                            <a href="articles_by_category.php?id=<?= htmlspecialchars($article['category_id'] ?? '') ?>" class="text-blue-500 hover:underline">
                                <?= htmlspecialchars($article['category_name'] ?? 'ไม่ระบุ') ?>
                            </a>
                        <?php else: ?>
                            หมวดหมู่: ไม่ระบุ
                        <?php endif; ?>
                        <span class="mx-2">|</span>
                        วันที่: <?= date('d/m/Y H:i', strtotime($article['created_at'])) ?>
                        <?php if (!empty($article['status'])): ?>
                            <span class="mx-2">|</span>
                            <span class="text-xs px-2 py-1 rounded bg-gray-200"><?= htmlspecialchars($article['status']) ?></span>
                        <?php endif; ?>
                    </p>

                    <!-- (เตรียมจุดสำหรับแสดงแท็ก ถ้ามีระบบแท็ก) -->
                    <!-- <div class="mb-2">
                        <span class="tag-badge">สุขภาพ</span>
                        <span class="tag-badge">สมุนไพร</span>
                    </div> -->

                    <div class="article-image-container mb-8">
                        <?php 
                        $html_image_src = str_replace('../', '', $article['image']);
                        $absolute_image_path_for_check = getcwd() . '/' . $html_image_src; 
                        if (!empty($article['image']) && file_exists($absolute_image_path_for_check)): 
                        ?>
                            <img src="<?= htmlspecialchars($html_image_src) ?>" 
                                 alt="<?= htmlspecialchars($article['title']) ?>" 
                                 class="article-image">
                        <?php else: ?>
                            <div class="article-image bg-gray-200 flex items-center justify-center text-gray-500 text-lg font-semibold">
                                ไม่มีรูปภาพ
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="text-gray-800 leading-relaxed article-content prose max-w-none">
                        <?php 
                        // **สำคัญ:** ใช้ html_entity_decode() เพื่อแปลง HTML entities (เช่น &nbsp;)
                        // และไม่ใช้ strip_tags() เพื่อให้ HTML formatting ในเนื้อหาแสดงผลได้
                        echo html_entity_decode($article['content'] ?? '', ENT_QUOTES, 'UTF-8'); 
                        ?>
                    </div>

                    <?php if ($related): ?>
                        <div class="mt-8">
                            <h2 class="text-lg font-semibold mb-2">บทความที่เกี่ยวข้อง</h2>
                            <ul class="list-disc pl-6">
                                <?php foreach ($related as $rel): ?>
                                    <li>
                                        <a href="article_detail.php?id=<?= $rel['id'] ?>" class="text-blue-600 hover:underline"><?= htmlspecialchars($rel['title']) ?></a>
                                    </li>
                                <?php endforeach ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <!-- (เตรียมจุดสำหรับระบบคอมเมนต์) -->
                    <!-- <div class="mt-8">
                        <h2 class="text-lg font-semibold mb-2">แสดงความคิดเห็น</h2>
                        <form>...</form>
                    </div> -->
                </div>

            <?php else: ?>
                <div class="bg-white rounded-lg shadow-xl p-8 text-center">
                    <h2 class="text-3xl font-bold text-gray-800 mb-4">ขออภัย, ไม่พบบทความที่คุณกำลังมองหา</h2>
                    <p class="text-gray-600">บทความนี้อาจถูกลบไปแล้ว หรือรหัสบทความไม่ถูกต้อง</p>
                    <a href="all_articles.php" class="inline-block mt-6 bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition duration-200">
                        กลับไปหน้าบทความทั้งหมด
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Sidebar: Latest Articles -->
        <aside class="w-full md:w-80 flex-shrink-0">
            <div class="bg-white rounded-lg shadow-xl p-4 mb-8">
                <h2 class="text-xl font-bold text-blue-700 mb-4 flex items-center gap-2">
                    <svg class="w-6 h-6 text-blue-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 21l-7-5-7 5V5a2 2 0 012-2h10a2 2 0 012 2z"/></svg>
                    บทความล่าสุด
                </h2>
                <?php if ($latest): ?>
                    <ul class="space-y-4">
                        <?php foreach ($latest as $item): ?>
                            <li class="latest-article-card bg-blue-50 rounded-lg flex items-center gap-3 p-2 hover:bg-blue-100">
                                <a href="article_detail.php?id=<?= $item['id'] ?>" class="flex items-center gap-3 w-full">
                                    <?php
                                    $img_src = !empty($item['image']) ? str_replace('../', '', $item['image']) : '';
                                    $img_path = getcwd() . '/' . $img_src;
                                    ?>
                                    <?php if ($img_src && file_exists($img_path)): ?>
                                        <img src="<?= htmlspecialchars($img_src) ?>" alt="<?= htmlspecialchars($item['title']) ?>" class="w-14 h-14 object-cover rounded shadow border border-blue-200 flex-shrink-0">
                                    <?php else: ?>
                                        <div class="w-14 h-14 bg-blue-200 flex items-center justify-center rounded text-blue-600 font-bold text-lg flex-shrink-0">
                                            <svg class="w-7 h-7" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 21l-7-5-7 5V5a2 2 0 012-2h10a2 2 0 012 2z"/></svg>
                                        </div>
                                    <?php endif; ?>
                                    <div class="flex-1 min-w-0">
                                        <div class="font-semibold text-blue-900 truncate"><?= htmlspecialchars($item['title']) ?></div>
                                        <div class="text-xs text-blue-600 mb-1"><?= date('d/m/Y', strtotime($item['created_at'])) ?></div>
                                        <div class="text-xs text-gray-600 line-clamp-2">
                                            <?php
                                            $desc = $item['excerpt'] ?: strip_tags(html_entity_decode($item['content'] ?? '', ENT_QUOTES, 'UTF-8'));
                                            echo htmlspecialchars(mb_substr($desc, 0, 60, 'UTF-8')) . (mb_strlen($desc, 'UTF-8') > 60 ? '...' : '');
                                            ?>
                                        </div>
                                    </div>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <div class="text-gray-400 text-sm">ไม่มีบทความล่าสุด</div>
                <?php endif; ?>
            </div>
        </aside>
    </div>

    <?php include 'footer.php'; ?>

</body>
</html>