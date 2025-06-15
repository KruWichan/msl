<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}
require '../includes/db.php';

$success = '';
$error = '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST["username"]);
    $email = trim($_POST["email"]);
    $password = $_POST["password"];
    $role = $_POST["role"] ?? 'user';

    if ($username && $email && $password) {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role, created_at) VALUES (?, ?, ?, ?, NOW())");
        try {
            $stmt->execute([$username, $email, $hashedPassword, $role]);
            $success = "เพิ่มผู้ใช้เรียบร้อยแล้ว!";
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
    <title>เพิ่มผู้ใช้</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@3.3.2/dist/tailwind.min.css" rel="stylesheet" />
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs" defer></script>
</head>
<body class="bg-gray-100 text-gray-800">
<?php require 'header.php'; ?>

<div class="min-h-screen flex justify-center px-4">
  <div class="max-w-lg w-full bg-white p-6 rounded shadow">
      <h1 class="text-3xl font-bold mb-6">เพิ่มผู้ใช้ใหม่</h1>

      <?php if ($success): ?>
          <div class="bg-green-100 text-green-700 p-4 rounded mb-6"><?= htmlspecialchars($success) ?></div>
      <?php elseif ($error): ?>
          <div class="bg-red-100 text-red-700 p-4 rounded mb-6"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form method="POST" class="w-full">
          <!-- ฟอร์ม input ต่าง ๆ -->
          <div class="mb-5">
              <label class="block mb-1 font-semibold">ชื่อผู้ใช้</label>
              <input type="text" name="username" class="w-full border border-gray-300 p-3 rounded focus:outline-none focus:ring-2 focus:ring-blue-400" required>
          </div>

          <div class="mb-5">
              <label class="block mb-1 font-semibold">อีเมล</label>
              <input type="email" name="email" class="w-full border border-gray-300 p-3 rounded focus:outline-none focus:ring-2 focus:ring-blue-400" required>
          </div>

          <div class="mb-5">
              <label class="block mb-1 font-semibold">รหัสผ่าน</label>
              <input type="password" name="password" class="w-full border border-gray-300 p-3 rounded focus:outline-none focus:ring-2 focus:ring-blue-400" required>
          </div>

          <div class="mb-6">
              <label class="block mb-1 font-semibold">บทบาท</label>
              <select name="role" class="w-full border border-gray-300 p-3 rounded focus:outline-none focus:ring-2 focus:ring-blue-400">
                  <option value="user">user</option>
                  <option value="admin">admin</option>
              </select>
          </div>

          <div class="flex justify-between items-center">
              <a href="manage_users.php" class="bg-gray-500 hover:bg-gray-600 text-white font-semibold px-5 py-2 rounded transition">ย้อนกลับ</a>
              <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-5 py-2 rounded transition">บันทึก</button>
          </div>
      </form>
  </div>
</div>

</body>
</html>
