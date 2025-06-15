<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}
require '../includes/db.php';

$stmt = $pdo->query("SELECT * FROM users ORDER BY created_at DESC");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>จัดการผู้ใช้</title>
  <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs" defer></script>
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 text-gray-800">
<?php require 'header.php'; ?>
<div class="max-w-6xl mx-auto bg-white p-6 rounded shadow">
  <h1 class="text-2xl font-bold mb-4">รายการผู้ใช้</h1>

  <a href="add_user.php" class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600 mb-4 inline-block">+ เพิ่มผู้ใช้</a>

  <div class="overflow-x-auto">
    <table class="min-w-full bg-white border border-gray-300">
      <thead>
        <tr class="bg-gray-200 text-gray-600 uppercase text-sm leading-normal">
          <th class="py-3 px-6 text-left">#</th>
          <th class="py-3 px-6 text-left">ชื่อผู้ใช้</th>
          <th class="py-3 px-6 text-left">อีเมล</th>
          <th class="py-3 px-6 text-left">บทบาท</th>
          <th class="py-3 px-6 text-left">สร้างเมื่อ</th>
          <th class="py-3 px-6 text-center">การจัดการ</th>
        </tr>
      </thead>
      <tbody class="text-gray-700">
        <?php foreach ($users as $index => $user): ?>
          <tr class="border-b border-gray-200 hover:bg-gray-100">
            <td class="py-3 px-6 text-left"><?= $index + 1 ?></td>
            <td class="py-3 px-6 text-left"><?= htmlspecialchars($user['username']) ?></td>
            <td class="py-3 px-6 text-left"><?= htmlspecialchars($user['email']) ?></td>
            <td class="py-3 px-6 text-left"><?= htmlspecialchars($user['role'] ?? 'user') ?></td>
            <td class="py-3 px-6 text-left"><?= $user['created_at'] ?></td>
            <td class="py-3 px-6 text-center">
              <a href="edit_user.php?id=<?= $user['id'] ?>" class="text-blue-500 hover:underline mx-1">แก้ไข</a>
              <a href="delete_user.php?id=<?= $user['id'] ?>" onclick="return confirm('คุณแน่ใจหรือไม่ว่าต้องการลบผู้ใช้นี้?');" class="text-red-500 hover:underline mx-1">ลบ</a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

</body>
</html>
