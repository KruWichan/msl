<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}
require '../includes/db.php';

// ค้นหาชื่อบทความ
$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// ดึงหมวดหมู่ทั้งหมด
$catStmt = $pdo->query("SELECT id, name FROM article_categories ORDER BY name");
$categories = $catStmt->fetchAll(PDO::FETCH_ASSOC);

// นับจำนวนบทความที่ตรงเงื่อนไข
$countSql = "SELECT COUNT(*) FROM articles WHERE title LIKE :search";
$params = ['search' => "%$search%"];
if ($category !== '') {
    $countSql .= " AND category_id = :category";
    $params['category'] = $category;
}
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$total = $countStmt->fetchColumn();
$totalPages = ceil($total / $limit);

// ดึงบทความแบบมี filter + pagination
$sql = "SELECT a.id, a.title, a.slug, a.status, a.published_at,
               a.image, -- 👈 เพิ่มบรรทัดนี้
               c.name AS category_name,
               u.username AS author_name
        FROM articles a
        LEFT JOIN article_categories c ON a.category_id = c.id
        LEFT JOIN users u ON a.author_id = u.id
        WHERE a.title LIKE :search";

if ($category !== '') {
    $sql .= " AND a.category_id = :category";
}
$sql .= " ORDER BY a.published_at DESC LIMIT :limit OFFSET :offset";

$stmt = $pdo->prepare($sql);
$stmt->bindValue(':search', "%$search%", PDO::PARAM_STR);
if ($category !== '') {
    $stmt->bindValue(':category', $category, PDO::PARAM_INT);
}
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$articles = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>จัดการบทความ</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@3.3.2/dist/tailwind.min.css" rel="stylesheet" />
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs" defer></script>
</head>
<body class="bg-gray-100 text-gray-800">
<?php require 'header.php'; ?>
<div class="max-w-6xl mx-auto bg-white p-6 rounded shadow">

    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold">จัดการบทความ</h1>
        <a href="add_article.php" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700">เพิ่มบทความใหม่</a>
    </div>

    <form method="GET" class="mb-4 flex flex-col md:flex-row md:items-center md:space-x-4">
    <input 
        type="text" 
        name="search" 
        placeholder="ค้นหาบทความ..." 
        value="<?= htmlspecialchars($search) ?>" 
        class="border border-gray-300 rounded px-3 py-2 w-full md:w-1/3 mb-2 md:mb-0"
    />
    
    <select name="category" class="border border-gray-300 rounded px-3 py-2 w-full md:w-1/4 mb-2 md:mb-0">
        <option value="">-- ทุกหมวดหมู่ --</option>
        <?php foreach ($categories as $cat): ?>
            <option value="<?= $cat['id'] ?>" <?= $category == $cat['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($cat['name']) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">ค้นหา</button>
</form>


    <p class="mb-4 text-gray-600">พบทั้งหมด <?= count($articles) ?> รายการ</p>

    <div class="overflow-x-auto">
        <table class="min-w-full border border-gray-300 text-sm">
            <thead class="bg-gray-200 text-gray-700">
                <tr>
                    <th class="border border-gray-300 px-4 py-2 text-left">ภาพบทความ</th>
                    <th class="border border-gray-300 px-4 py-2 text-left">หัวข้อบทความ</th>
                    <th class="border border-gray-300 px-4 py-2 text-left">Slug</th> <!-- 👈 เพิ่มตรงนี้ -->
                    <th class="border border-gray-300 px-4 py-2 text-left">หมวดหมู่</th>
                    <th class="border border-gray-300 px-4 py-2 text-left">ผู้เขียน</th>
                    <th class="border border-gray-300 px-4 py-2 text-left">วันที่เผยแพร่</th>
                    <th class="border border-gray-300 px-4 py-2 text-left">สถานะ</th>
                    <th class="border border-gray-300 px-4 py-2 text-center">จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($articles) === 0): ?>
                    <tr>
                        <td colspan="6" class="text-center py-4 text-gray-500">ไม่พบบทความ</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($articles as $article): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="border border-gray-300 px-4 py-2">
                                <img src="../uploads/<?= htmlspecialchars($article['image']) ?>" alt="" class="w-16 h-16 object-cover rounded">
                    </td>
                            <td class="border border-gray-300 px-4 py-2">
                                <?= htmlspecialchars($article['title']) ?>
                                <a href="../view_article.php?slug=<?= $article['slug'] ?>" target="_blank" class="ml-2 text-sm text-gray-500 hover:underline">(ดูบทความ)</a>
                            </td>
                            <td class="border border-gray-300 px-4 py-2 text-sm text-gray-600">
                    <?= htmlspecialchars($article['slug']) ?>
                </td> <!-- 👈 เพิ่มตรงนี้ -->
                            <td class="border border-gray-300 px-4 py-2"><?= htmlspecialchars($article['category_name'] ?? '-') ?></td>
                            <td class="border border-gray-300 px-4 py-2"><?= htmlspecialchars($article['author_name'] ?? '-') ?></td>
                            <td class="border border-gray-300 px-4 py-2"><?= $article['published_at'] ? date('d/m/Y', strtotime($article['published_at'])) : '-' ?></td>
                            <td class="border border-gray-300 px-4 py-2">
                                <span class="<?= $article['status'] === 'published' ? 'text-green-600' : 'text-gray-500' ?>">
                                    <?= $article['status'] === 'published' ? 'เผยแพร่' : 'ฉบับร่าง' ?>
                                </span>
                            </td>
                            <td class="border border-gray-300 px-4 py-2 text-center space-x-2">
                                <a href="edit_article.php?id=<?= $article['id'] ?>" class="text-blue-600 hover:underline">แก้ไข</a>
                                <a href="delete_article.php?id=<?= $article['id'] ?>" onclick="return confirm('ยืนยันการลบบทความนี้?')" class="text-red-600 hover:underline">ลบ</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        <?php if ($totalPages > 1): ?>
<div class="mt-6 text-center">
    <nav class="inline-flex space-x-1">
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <a href="?search=<?= urlencode($search) ?>&category=<?= urlencode($category) ?>&page=<?= $i ?>"
               class="px-3 py-1 border rounded <?= $i == $page ? 'bg-blue-600 text-white' : 'bg-white text-blue-600 hover:bg-blue-100' ?>">
                <?= $i ?>
            </a>
        <?php endfor; ?>
    </nav>
</div>
<?php endif; ?>

    </div>
</div>

</body>
</html>
