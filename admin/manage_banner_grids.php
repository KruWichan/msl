<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}
require '../includes/db.php';

// เพิ่ม banner grid ใหม่
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_grid'])) {
    $title = $_POST['title'];
    $columns = $_POST['columns'];
    $height = $_POST['height'];

    $stmt = $pdo->prepare("INSERT INTO banner_grids (title, columns, height) VALUES (?, ?, ?)");
    $stmt->execute([$title, $columns, $height]);
    header("Location: manage_banner_grids.php?success=1");
    exit;
}

// อัปเดต banner grid
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_grid'])) {
    $id = $_POST['id'];
    $title = $_POST['title'];
    $columns = $_POST['columns'];
    $height = $_POST['height'];

    $stmt = $pdo->prepare("UPDATE banner_grids SET title = ?, columns = ?, height = ? WHERE id = ?");
    $stmt->execute([$title, $columns, $height, $id]);
    header("Location: manage_banner_grids.php?updated=1");
    exit;
}

// ลบ grid
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $pdo->prepare("DELETE FROM banner_grids WHERE id = ?")->execute([$id]);
    header("Location: manage_banner_grids.php?deleted=1");
    exit;
}

// ดึงรายการ grid
$grids = $pdo->query("SELECT * FROM banner_grids ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
$edit_id = $_GET['edit'] ?? null;
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>จัดการ Banner Grids</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
<?php require 'header.php'; ?>

<div class="max-w-4xl mx-auto mt-8 p-6 bg-white rounded shadow">
    <h1 class="text-2xl font-bold mb-4">🎛️ จัดการ Banner Grids</h1>

    <?php if (isset($_GET['success'])): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 p-3 mb-4 rounded">✅ เพิ่มแม่แบบเรียบร้อยแล้ว</div>
    <?php endif; ?>
    <?php if (isset($_GET['updated'])): ?>
        <div class="bg-blue-100 border border-blue-400 text-blue-700 p-3 mb-4 rounded">✏️ แก้ไขแม่แบบเรียบร้อยแล้ว</div>
    <?php endif; ?>
    <?php if (isset($_GET['deleted'])): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 p-3 mb-4 rounded">🗑️ ลบแม่แบบเรียบร้อยแล้ว</div>
    <?php endif; ?>

    <form method="post" class="space-y-4 mb-6">
        <h2 class="text-xl font-semibold">➕ เพิ่มแม่แบบใหม่</h2>
        <div>
            <label class="block font-medium">ชื่อแม่แบบ</label>
            <input type="text" name="title" required class="w-full border rounded p-2">
        </div>
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block font-medium">จำนวนคอลัมน์</label>
                <input type="number" name="columns" required min="1" max="12" class="w-full border rounded p-2">
            </div>
            <div>
                <label class="block font-medium">ความสูง (px หรือ %)</label>
                <input type="text" name="height" required class="w-full border rounded p-2">
            </div>
        </div>
        <button type="submit" name="add_grid" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">เพิ่มแม่แบบ</button>
    </form>

    <h2 class="text-xl font-semibold mb-2">📋 รายการแม่แบบทั้งหมด</h2>
    <table class="w-full table-auto border">
        <thead>
            <tr class="bg-gray-200">
                <th class="p-2 border">ID</th>
                <th class="p-2 border">ชื่อแม่แบบ</th>
                <th class="p-2 border">คอลัมน์</th>
                <th class="p-2 border">ความสูง</th>
                <th class="p-2 border">จัดการ</th>
                <th class="p-2 border">Preview</th> <!-- เพิ่มคอลัมน์สำหรับ Preview -->
            </tr>
        </thead>
        <tbody>
        <?php foreach ($grids as $grid): ?>
            <tr>
                <td class="border p-2 text-center"><?= $grid['id'] ?></td>

                <?php if ($edit_id == $grid['id']): ?>
                    <form method="post">
                        <input type="hidden" name="id" value="<?= $grid['id'] ?>">
                        <td class="border p-2"><input name="title" class="w-full border rounded p-1" value="<?= htmlspecialchars($grid['title']) ?>"></td>
                        <td class="border p-2 text-center"><input name="columns" type="number" class="w-full border rounded p-1" value="<?= $grid['columns'] ?>"></td>
                        <td class="border p-2 text-center"><input name="height" class="w-full border rounded p-1" value="<?= htmlspecialchars($grid['height']) ?>"></td>
                        <td class="border p-2 text-center">
                            <button name="edit_grid" class="bg-green-500 text-white px-3 py-1 rounded hover:bg-green-600">💾 บันทึก</button>
                        </td>
                        <!-- เพิ่ม cell ว่างสำหรับ Preview ในโหมดแก้ไข -->
                        <td class="border p-2 text-center">-</td>
                    </form>
                <?php else: ?>
                    <td class="border p-2"><?= htmlspecialchars($grid['title']) ?></td>
                    <td class="border p-2 text-center"><?= $grid['columns'] ?></td>
                    <td class="border p-2 text-center"><?= htmlspecialchars($grid['height']) ?></td>
                    <td class="border p-2 text-center space-x-2">
                        <a href="?edit=<?= $grid['id'] ?>" class="text-blue-600 hover:underline">✏️ แก้ไข</a>
                        <a href="?delete=<?= $grid['id'] ?>" class="text-red-600 hover:underline" onclick="return confirm('ยืนยันการลบแม่แบบนี้?')">🗑️ ลบ</a>
                    </td>
                    <!-- เพิ่มลิงก์สำหรับ Preview -->
                    <td class="border p-2 text-center">
                        <a href="preview_banner_grid.php?id=<?= $grid['id'] ?>" 
                           target="_blank"
                           class="text-green-600 hover:underline font-medium">
                           👁️ ดูตัวอย่าง
                        </a>
                    </td>
                <?php endif; ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
</body>
</html>