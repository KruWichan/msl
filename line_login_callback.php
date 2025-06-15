<?php
// filepath: line_login_callback.php

// ตั้งค่าตาม site_settings
require_once 'includes/db.php';

// ดึงค่า LINE Login จากฐานข้อมูล
$stmt = $pdo->query("SELECT line_channel_id, line_channel_secret FROM site_settings WHERE id = 2");
$settings = $stmt->fetch(PDO::FETCH_ASSOC);

$client_id = $settings['line_channel_id']; // Channel ID
$client_secret = $settings['line_channel_secret'];
$redirect_uri = 'https://' . $_SERVER['HTTP_HOST'] . '/line_login_callback.php'; // หรือ URL ที่ตั้งไว้ใน LINE Developer Console

if (!isset($_GET['code'])) {
    die('No code parameter');
}

$code = $_GET['code'];

// ขอ access token จาก LINE
$token_url = 'https://api.line.me/oauth2/v2.1/token';
$data = [
    'grant_type' => 'authorization_code',
    'code' => $code,
    'redirect_uri' => $redirect_uri,
    'client_id' => $client_id,
    'client_secret' => $client_secret
];

$options = [
    'http' => [
        'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
        'method'  => 'POST',
        'content' => http_build_query($data),
    ]
];
$context  = stream_context_create($options);
$result = file_get_contents($token_url, false, $context);
if ($result === FALSE) {
    die('Error requesting access token');
}
$response = json_decode($result, true);

if (!isset($response['access_token'])) {
    die('Failed to get access token: ' . htmlspecialchars($result));
}

if (isset($response['error'])) {
    die('LINE error: ' . htmlspecialchars($response['error_description'] ?? $response['error']));
}

// ดึงข้อมูลโปรไฟล์ผู้ใช้
$access_token = $response['access_token'];
$user_info_url = 'https://api.line.me/v2/profile';
$opts = [
    'http' => [
        'header' => "Authorization: Bearer $access_token\r\n"
    ]
];
$context = stream_context_create($opts);
$user_info = file_get_contents($user_info_url, false, $context);
$user = json_decode($user_info, true);

// ตัวอย่าง: แสดงข้อมูลผู้ใช้
echo "<h2>LINE Login Success</h2>";
echo "<pre>";
print_r($user);
echo "</pre>";

// TODO: นำข้อมูล user ไปใช้งานต่อ เช่น login, สมัครสมาชิก, สร้าง session ฯลฯ