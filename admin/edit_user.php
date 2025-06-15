<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}
require '../includes/db.php';

$id = $_GET['id'] ?? null;
if (!$id) {
    header("Location: manage_users.php");
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo "ไม่พบผู้ใช้ที่ระบุ";
    exit;
}

$success = '';
$error = '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST["username"]);
    $email = trim($_POST["email"]);
    $role = $_POST["role"] ?? 'user';

    if ($username && $email) {
        $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, role = ?, updated_at = NOW() WHERE id = ?");
        try {
            $stmt->execute([$username, $email, $role, $id]);
            $success = "อัปเดตข้อมูลผู้ใช้เรียบร้อยแล้ว!";
            $stmt->execute([$id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC); // ดึงข้อมูลใหม่มาแสดง
        } catch (PDOException $e) {
            $error = "เกิดข้อผิดพลาด: " . $e->getMessage();
        }
    } else {
        $error = "กรุณากรอกข้อมูลให้ครบถ้วน";
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>แก้ไขผู้ใช้</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@3.3.2/dist/tailwind.min.css" rel="stylesheet" />
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs" defer></script>
</head>
<body class="bg-gray-100 text-gray-800">
<?php require 'header.php'; ?>

<div class="max-w-4xl mx-auto bg-white p-6 rounded shadow">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold">แก้ไขข้อมูลผู้ใช้</h1>
        <a href="manage_users.php" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700">← ย้อนกลับ</a>
    </div>

    <?php if ($success): ?>
        <div class="bg-green-100 text-green-700 p-3 rounded mb-4"><?= $success ?></div>
    <?php elseif ($error): ?>
        <div class="bg-red-100 text-red-700 p-3 rounded mb-4"><?= $error ?></div>
    <?php endif; ?>

    <form method="POST" class="space-y-4">
        <div>
            <label class="block mb-1 font-semibold">ชื่อผู้ใช้</label>
            <input type="text" name="username" value="<?= htmlspecialchars($user['username']) ?>" class="w-full border border-gray-300 p-2 rounded" required>
        </div>

        <div>
            <label class="block mb-1 font-semibold">อีเมล</label>
            <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" class="w-full border border-gray-300 p-2 rounded" required>
        </div>

        <div>
            <label class="block mb-1 font-semibold">บทบาท</label>
            <select name="role" class="w-full border border-gray-300 p-2 rounded">
                <option value="user" <?= $user['role'] === 'user' ? 'selected' : '' ?>>user</option>
                <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>admin</option>
            </select>
        </div>

        <div class="flex justify-end">
            <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700">บันทึกการแก้ไข</button>
        </div>
    </form>
</div>

</body>
</html>
