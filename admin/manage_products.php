<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}
require '../includes/db.php';

// ดึงหมวดหมู่และแท็ก
$categories = $pdo->query("SELECT * FROM product_categories WHERE is_deleted = 0")->fetchAll(PDO::FETCH_ASSOC);
$tags = $pdo->query("SELECT * FROM product_tags")->fetchAll(PDO::FETCH_ASSOC);

// Filter ตามหมวดหมู่/แท็ก
$where = [];
$params = [];
if (!empty($_GET['category_id'])) {
    $where[] = "category_id = :category_id";
    $params[':category_id'] = $_GET['category_id'];
}
if (!empty($_GET['tag_id'])) {
    $where[] = "id IN (SELECT product_id FROM product_tag_map WHERE tag_id = :tag_id)";
    $params[':tag_id'] = $_GET['tag_id'];
}
$whereSql = $where ? "WHERE " . implode(' AND ', $where) : "";

// ดึงข้อมูลสินค้า
$stmt = $pdo->prepare("SELECT * FROM products $whereSql ORDER BY id DESC");
$stmt->execute($params);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>จัดการสินค้า</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 text-gray-800">
<?php require 'header.php'; ?>

<div class="max-w-6xl mx-auto bg-white p-6 rounded shadow">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold">จัดการสินค้า</h1>
        <a href="add_product.php" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded">+ เพิ่มสินค้า</a>
    </div>

    <!-- Filter หมวดหมู่/แท็ก -->
    <form method="get" class="mb-4 flex gap-2">
        <select name="category_id" class="border rounded px-2 py-1">
            <option value="">-- เลือกหมวดหมู่ --</option>
            <?php foreach ($categories as $cat): ?>
                <option value="<?= $cat['id'] ?>" <?= @$_GET['category_id'] == $cat['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($cat['name']) ?>
                </option>
            <?php endforeach ?>
        </select>
        <select name="tag_id" class="border rounded px-2 py-1">
            <option value="">-- เลือกแท็ก --</option>
            <?php foreach ($tags as $tag): ?>
                <option value="<?= $tag['id'] ?>" <?= @$_GET['tag_id'] == $tag['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($tag['name']) ?>
                </option>
            <?php endforeach ?>
        </select>
        <button type="submit" class="bg-blue-500 text-white px-3 py-1 rounded">ค้นหา</button>
    </form>

    <table class="w-full table-auto border-collapse">
        <thead>
            <tr class="bg-gray-100 text-left">
                <th class="border px-4 py-2">#</th>
                <th class="border px-4 py-2">ชื่อสินค้า</th>
                <th class="border px-4 py-2">หมวดหมู่</th>
                <th class="border px-4 py-2">จำนวนคงเหลือ</th>
                <th class="border px-4 py-2">สถานะ</th>
                <th class="border px-4 py-2">รูปภาพ</th>
                <th class="border px-4 py-2">แท็ก</th>
                <th class="border px-4 py-2 text-center">การจัดการ</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($products as $i => $product): ?>
            <tr class="hover:bg-gray-50">
                <td class="border px-4 py-2"><?= $i + 1 ?></td>
                <td class="border px-4 py-2"><?= htmlspecialchars($product['name']) ?></td>
                <td class="border px-4 py-2">
                    <?php
                    $cat = $pdo->prepare("SELECT name FROM product_categories WHERE id = ?");
                    $cat->execute([$product['category_id']]);
                    echo htmlspecialchars($cat->fetchColumn());
                    ?>
                </td>
                <td class="border px-4 py-2"><?= $product['stock'] ?></td>
                <td class="border px-4 py-2">
                    <?php if ($product['stock'] == 0): ?>
                        <span class="bg-red-500 text-white px-2 py-1 rounded text-xs">หมด</span>
                    <?php elseif ($product['stock'] < 5): ?>
                        <span class="bg-yellow-400 text-white px-2 py-1 rounded text-xs">ใกล้หมด</span>
                    <?php else: ?>
                        <span class="bg-green-500 text-white px-2 py-1 rounded text-xs">ปกติ</span>
                    <?php endif ?>
                </td>
                <td class="border px-4 py-2">
                    <?php
                    $imgs = $pdo->prepare("SELECT * FROM product_images WHERE product_id = ?");
                    $imgs->execute([$product['id']]);
                    foreach ($imgs as $img) {
                        echo '<img src="../uploads/products/' . htmlspecialchars($img['filename']) . '" class="inline-block w-10 h-10 object-cover mr-1 rounded" />';
                    }
                    ?>
                </td>
                <td class="border px-4 py-2">
                    <?php
                    $tagStmt = $pdo->prepare("SELECT t.name FROM product_tags t INNER JOIN product_tag_map pt ON t.id = pt.tag_id WHERE pt.product_id = ?");
                    $tagStmt->execute([$product['id']]);
                    $tagNames = $tagStmt->fetchAll(PDO::FETCH_COLUMN);
                    echo implode(', ', array_map('htmlspecialchars', $tagNames));
                    ?>
                </td>
                <td class="border px-4 py-2 text-center">
                    <a href="edit_product.php?id=<?= $product['id'] ?>" class="text-blue-500 hover:underline">แก้ไข</a>
                    |
                    <a href="delete_product.php?id=<?= $product['id'] ?>" class="text-red-500 hover:underline" onclick="return confirm('ยืนยันการลบสินค้านี้?')">ลบ</a>
                </td>
            </tr>
            <?php endforeach ?>
        </tbody>
    </table>
</div>
</body>
</html>
