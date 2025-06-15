<?php
session_start();
require 'includes/db.php';

// ตรวจสอบว่าผู้ใช้ล็อกอินเป็นแอดมินหรือไม่
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

// ลบ LINE Channel Access Token และ User ID ของแอดมินออกจาก site_settings
$stmt = $pdo->prepare("UPDATE site_settings SET line_channel_access_token = '', admin_line_user_id = '' WHERE id = 2");
$stmt->execute();

// ถ้าต้องการ redirect กลับไปหน้า setting.php
header('Location: admin/setting.php?line_disconnected=1');
exit;
?>