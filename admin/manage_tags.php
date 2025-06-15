<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}
require '../includes/db.php';

// ดึงข้อมูลป้ายทั้งหมด
$stmt = $pdo->query("SELECT * FROM product_tags ORDER BY id DESC");
$tags = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>จัดการป้ายสินค้า</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs" defer></script>
</head>
<body class="bg-gray-100 text-gray-800">
<?php require 'header.php'; ?>

<div class="max-w-4xl mx-auto bg-white p-6 mt-6 rounded shadow">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold">จัดการป้ายสินค้า</h1>
        <a href="add_tag.php" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded shadow">
            + เพิ่มป้ายสินค้า
        </a>
    </div>

    <div class="overflow-x-auto">
        <table class="min-w-full bg-white border border-gray-200">
            <thead>
                <tr class="bg-gray-100 text-left text-sm font-semibold text-gray-700">
                    <th class="border px-4 py-2">#</th>
                    <th class="border px-4 py-2">ชื่อป้าย</th>
                    <th class="border px-4 py-2">Slug</th>
                    <th class="border px-4 py-2">สี</th>
                    <th class="border px-4 py-2 text-center">การจัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tags as $i => $tag): ?>
                <tr class="hover:bg-gray-50 text-sm">
                    <td class="border px-4 py-2"><?= $i + 1 ?></td>
                    <td class="border px-4 py-2"><?= htmlspecialchars($tag['name']) ?></td>
                    <td class="border px-4 py-2"><?= htmlspecialchars($tag['slug']) ?></td>
                    <td class="border px-4 py-2">
                        <div class="flex items-center gap-2">
                            <div class="w-5 h-5 rounded-full border border-gray-300" style="background-color: <?= htmlspecialchars($tag['color']) ?>;"></div>
                            <span class="text-gray-600"><?= htmlspecialchars($tag['color']) ?></span>
                        </div>
                    </td>
                    <td class="border px-4 py-2 text-center">
                        <a href="edit_tag.php?id=<?= $tag['id'] ?>" class="text-blue-600 hover:underline">แก้ไข</a>
                        |
                        <a href="delete_tag.php?id=<?= $tag['id'] ?>" class="text-red-600 hover:underline" onclick="return confirm('ยืนยันการลบป้ายนี้?')">ลบ</a>
                    </td>
                </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>
