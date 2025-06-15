<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}
require '../includes/db.php';

// ตรวจสอบว่ามีการส่ง id มาหรือไม่
if (!isset($_GET['id'])) {
    header("Location: manage_categories.php");
    exit;
}

$id = $_GET['id'];
$error = "";

// ดึงข้อมูลหมวดหมู่เดิม
$stmt = $pdo->prepare("SELECT * FROM product_categories WHERE id = ?");
$stmt->execute([$id]);
$category = $stmt->fetch();

if (!$category) {
    echo "ไม่พบหมวดหมู่ที่ต้องการแก้ไข";
    exit;
}

// เมื่อกดปุ่มบันทึก
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);

    if (!empty($name)) {
        $stmt = $pdo->prepare("UPDATE product_categories SET name = ? WHERE id = ?");
        $stmt->execute([$name, $id]);
        header("Location: manage_categories.php");
        exit;
    } else {
        $error = "กรุณากรอกชื่อหมวดหมู่";
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>แก้ไขหมวดหมู่</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
      <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs" defer></script>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@3.3.2/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 text-gray-800">
<?php require 'header.php'; ?>

<div class="max-w-xl mx-auto bg-white p-6 rounded shadow">
    <h1 class="text-2xl font-bold mb-6">แก้ไขหมวดหมู่</h1>

    <?php if (!empty($error)): ?>
        <div class="bg-red-100 text-red-700 p-3 rounded mb-4">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form action="edit_category.php?id=<?= htmlspecialchars($id) ?>" method="POST" class="space-y-4">
        <div>
            <label for="name" class="block mb-1 font-semibold">ชื่อหมวดหมู่</label>
            <input type="text" id="name" name="name" value="<?= htmlspecialchars($category['name']) ?>" required class="w-full border border-gray-300 rounded px-3 py-2">
        </div>

        <div class="flex justify-end space-x-2">
            <a href="manage_categories.php" class="px-4 py-2 rounded bg-gray-300 hover:bg-gray-400 text-gray-800">ยกเลิก</a>
            <button type="submit" class="px-4 py-2 rounded bg-blue-500 hover:bg-blue-600 text-white">บันทึก</button>
        </div>
    </form>
</div>

</body>
</html>
