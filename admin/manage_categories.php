<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}
require '../includes/db.php';

// ดึงจำนวนหมวดหมู่ทั้งหมด (ไม่รวม soft delete)
$totalStmt = $pdo->query("SELECT COUNT(*) FROM product_categories WHERE is_deleted = 0");
$totalCategories = $totalStmt->fetchColumn();

// ดึงข้อมูลหมวดหมู่ทั้งหมด (ไม่รวม soft delete)
$stmt = $pdo->query("SELECT * FROM product_categories WHERE is_deleted = 0 ORDER BY id DESC");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>จัดการหมวดหมู่สินค้า</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
     <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs" defer></script>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@3.3.2/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 text-gray-800">
<?php require 'header.php'; ?>

<div class="max-w-4xl mx-auto bg-white p-6 rounded shadow">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-2xl font-bold">จัดการหมวดหมู่สินค้า</h1>
            <p class="text-gray-600 mt-1">จำนวนหมวดหมู่ทั้งหมด: <span class="font-semibold"><?= $totalCategories ?></span></p>
        </div>
        <div class="flex">
            <a href="add_category.php" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded">
                + เพิ่มหมวดหมู่
            </a>
            <a href="manage_categories.php?show_deleted=1" class="bg-gray-400 hover:bg-gray-500 text-white px-4 py-2 rounded ml-2">
                ถังขยะ
            </a>
        </div>
    </div>

    <table class="w-full table-auto border-collapse">
        <thead>
            <tr class="bg-gray-100 text-left">
                <th class="border px-4 py-2">#</th>
                <th class="border px-4 py-2">ชื่อหมวดหมู่</th>
                <th class="border px-4 py-2">วันที่เพิ่ม</th>
                <th class="border px-4 py-2 text-center">การจัดการ</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($categories as $i => $cat): ?>
            <tr class="hover:bg-gray-50">
                <td class="border px-4 py-2"><?= $i + 1 ?></td>
                <td class="border px-4 py-2"><?= htmlspecialchars($cat['name']) ?></td>
                <td class="border px-4 py-2"><?= $cat['created_at'] ?></td>
                <td class="border px-4 py-2 text-center">
                    <a href="edit_category.php?id=<?= $cat['id'] ?>" class="text-blue-500 hover:underline">แก้ไข</a> |
                    <a href="delete_category.php?id=<?= $cat['id'] ?>" class="text-red-500 hover:underline" onclick="return confirm('ยืนยันการลบหมวดหมู่นี้?')">ลบ</a>
                </td>
            </tr>
            <?php endforeach ?>
        </tbody>
    </table>
</div>

</body>
</html>
