<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require 'includes/db.php';

$settings = $pdo->query("SELECT * FROM site_settings LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$font = $settings['font_family'] ?? 'Sarabun';
$color = $settings['primary_color'] ?? '#2563eb';
?>

<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <title>เข้าสู่ระบบ - <?= htmlspecialchars($settings['site_name']) ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=<?= str_replace(' ', '+', $font) ?>&display=swap" rel="stylesheet">
  <style>
    body { font-family: '<?= $font ?>', sans-serif; }
    .primary-bg { background-color: <?= $color ?>; }
    .primary-text { color: <?= $color ?>; }
    .primary-border { border-color: <?= $color ?>; }
    .primary-hover:hover { background-color: <?= $color ?>; color: white; }
  </style>
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen">
<div class="bg-white p-8 rounded-lg shadow-md w-full max-w-md">
  <div class="text-center mb-6">
    <?php if (!empty($settings['logo'])): ?>
      <img src="<?= $settings['logo'] ?>" alt="Logo" class="h-12 mx-auto mb-2">
    <?php endif; ?>
    <h1 class="text-2xl font-bold primary-text">เข้าสู่ระบบ</h1>
    <p class="text-sm text-gray-500"><?= htmlspecialchars($settings['site_name']) ?></p>
  </div>

  <?php if (isset($_SESSION['login_error'])): ?>
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-2 rounded mb-4">
      <?= $_SESSION['login_error']; unset($_SESSION['login_error']); ?>
    </div>
  <?php endif; ?>

  <form action="process_login.php" method="POST" class="space-y-4">
    <div>
      <label class="block text-sm font-medium text-gray-700">อีเมล</label>
      <input type="email" name="email" required class="mt-1 w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-[<?= $color ?>]">
    </div>
    <div>
      <label class="block text-sm font-medium text-gray-700">รหัสผ่าน</label>
      <input type="password" name="password" required class="mt-1 w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-[<?= $color ?>]">
    </div>
    <div class="flex items-center justify-between text-sm">
      <label class="flex items-center space-x-2">
        <input type="checkbox" name="remember" class="form-checkbox">
        <span>จดจำฉันไว้</span>
      </label>
      <a href="user_forgot_password.php" class="text-blue-500 hover:underline">ลืมรหัสผ่าน?</a>
    </div>
    <button type="submit" class="w-full py-2 rounded primary-bg text-white font-semibold hover:opacity-90 transition">เข้าสู่ระบบ</button>
  </form>

  <div class="mt-4 text-center">
    <a href="admin/connect_line.php" class="inline-flex items-center px-4 py-2 bg-green-500 text-white rounded hover:bg-green-600">
      <img src="https://scdn.line-apps.com/n/line_regulation/files/ver2/LINE_Icon.png" alt="LINE" class="h-5 w-5 mr-2">
      เข้าสู่ระบบด้วย LINE
    </a>
  </div>

  <p class="mt-6 text-center text-sm text-gray-600">
    ยังไม่มีบัญชี? <a href="register.php" class="text-blue-600 hover:underline">สมัครสมาชิก</a>
  </p>
</div>
</body>
</html>
