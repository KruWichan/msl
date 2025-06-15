<?php
// register.php
session_start();
require 'includes/db.php';
$settings = $pdo->query("SELECT * FROM site_settings LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$font = in_array($settings['font_family'], ['Sarabun','Kanit','Prompt','Mitr','Noto Sans Thai']) ? $settings['font_family'] : 'Sarabun';
$color = $settings['primary_color'] ?? '#2563eb';
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <title>สมัครสมาชิก - <?= htmlspecialchars($settings['site_name']) ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=<?= str_replace(' ', '+', $font) ?>&display=swap" rel="stylesheet">
  <style>
    body { font-family: '<?= $font ?>', sans-serif; }
    .primary-bg { background-color: <?= $color ?>; }
    .primary-text { color: <?= $color ?>; }
  </style>
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen">
<div class="bg-white p-8 rounded-lg shadow-md w-full max-w-md">
  <div class="text-center mb-6">
    <?php if (!empty($settings['logo'])): ?>
      <img src="<?= $settings['logo'] ?>" alt="Logo" class="h-12 mx-auto mb-2">
    <?php endif; ?>
    <h1 class="text-2xl font-bold primary-text">สมัครสมาชิก</h1>
    <p class="text-sm text-gray-500"><?= htmlspecialchars($settings['site_name']) ?></p>
  </div>

  <?php if (isset($_SESSION['login_error'])): ?>
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-2 rounded mb-4">
      <?= $_SESSION['login_error']; unset($_SESSION['login_error']); ?>
    </div>
  <?php endif; ?>

  <form action="process_register.php" method="POST" class="space-y-4">
    <div>
      <label class="block text-sm font-medium text-gray-700">ชื่อผู้ใช้</label>
      <input type="text" name="username" required class="mt-1 w-full px-3 py-2 border border-gray-300 rounded">
    </div>
    <div>
      <label class="block text-sm font-medium text-gray-700">อีเมล</label>
      <input type="email" name="email" required class="mt-1 w-full px-3 py-2 border border-gray-300 rounded">
    </div>
    <div>
      <label class="block text-sm font-medium text-gray-700">รหัสผ่าน</label>
      <input type="password" name="password" required class="mt-1 w-full px-3 py-2 border border-gray-300 rounded">
    </div>
    <button type="submit" class="w-full py-2 rounded primary-bg text-white font-semibold hover:opacity-90 transition">สมัครสมาชิก</button>
  </form>

  <p class="mt-6 text-center text-sm text-gray-600">
    มีบัญชีแล้ว? <a href="login.php" class="text-blue-600 hover:underline">เข้าสู่ระบบ</a>
  </p>
</div>
</body>
</html>
