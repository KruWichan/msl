<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}
require '../includes/db.php';

$error = '';
$success = '';

function slugify($text) {
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9ก-๙\-]+/u', '-', $text); // รองรับภาษาไทย
    $text = preg_replace('/-+/', '-', $text);
    return trim($text, '-');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $slug = trim($_POST['slug']) ?: slugify($name);
    $color = $_POST['color'] ?: 'gray-600';

    if ($name === '') {
        $error = 'กรุณากรอกชื่อแท็ก';
    } else {
        $stmt = $pdo->prepare("INSERT INTO product_tags (name, slug, color) VALUES (:name, :slug, :color)");
        $stmt->execute([
            'name' => $name,
            'slug' => $slug,
            'color' => $color
        ]);
        $success = 'เพิ่มแท็กเรียบร้อยแล้ว';
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>เพิ่มแท็กสินค้า</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 text-gray-800">
<?php require 'header.php'; ?>

<div class="max-w-xl mx-auto bg-white p-6 mt-8 rounded shadow">
    <h1 class="text-2xl font-bold mb-4">เพิ่มแท็กสินค้า</h1>

    <?php if ($error): ?>
        <div class="bg-red-100 text-red-700 px-4 py-2 rounded mb-4"><?= $error ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="bg-green-100 text-green-700 px-4 py-2 rounded mb-4"><?= $success ?></div>
    <?php endif; ?>

    <form action="" method="post" class="space-y-4">
        <div>
            <label class="block mb-1 font-medium">ชื่อแท็ก</label>
            <input type="text" name="name" class="w-full border p-2 rounded" required>
        </div>

        <div>
            <label class="block mb-1 font-medium">Slug (ถ้าไม่กรอกจะสร้างจากชื่ออัตโนมัติ)</label>
            <input type="text" name="slug" class="w-full border p-2 rounded">
        </div>

        <div>
            <label class="block mb-1 font-medium">เลือกสีแท็ก</label>
            <input type="color" name="color" class="w-16 h-10 p-0 border rounded" value="#cccccc">
            <p class="text-sm text-gray-500 mt-1">* ถ้าไม่เลือกจะใช้ค่า <code>gray-600</code></p>
        </div>

        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">เพิ่มแท็ก</button>
    </form>
</div>
</body>
</html>
