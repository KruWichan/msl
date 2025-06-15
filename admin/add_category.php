<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}
require '../includes/db.php';

// เมื่อส่งฟอร์ม
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);

    if (!empty($name)) {
        $stmt = $pdo->prepare("INSERT INTO product_categories (name, created_at) VALUES (?, NOW())");
        $stmt->execute([$name]);

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
    <title>เพิ่มหมวดหมู่</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
     <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs" defer></script>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@3.3.2/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 text-gray-800">
<?php require 'header.php'; ?>

<div class="max-w-xl mx-auto bg-white p-6 rounded shadow">
    <h1 class="text-2xl font-bold mb-6">เพิ่มหมวดหมู่ใหม่</h1>

    <?php if (!empty($error)): ?>
        <div class="bg-red-100 text-red-700 p-3 rounded mb-4">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form action="add_category.php" method="POST" class="space-y-4">
        <div>
            <label for="name" class="block mb-1 font-semibold">ชื่อหมวดหมู่</label>
            <input type="text" id="name" name="name" required class="w-full border border-gray-300 rounded px-3 py-2">
        </div>

        <div class="flex justify-end">
            <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded">
                บันทึก
            </button>
        </div>
    </form>
</div>

</body>
</html>
