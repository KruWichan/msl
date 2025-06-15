<?php
session_start();
header('Content-Type: application/json');
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}
require '../includes/db.php';

// ดึงข้อมูลจากฐานข้อมูล
$stmt = $pdo->query("SELECT * FROM site_settings WHERE id = 2");
$settings = $stmt->fetch(PDO::FETCH_ASSOC);

$token = $settings['line_channel_access_token'];
$userId = $settings['admin_line_user_id'];
$message = $_POST['message'] ?? '';

if (!$userId) {
    echo json_encode(['success' => false, 'error' => 'ยังไม่มี LINE User ID ของแอดมิน']);
    exit;
}

$data = [
    'to' => $userId,
    'messages' => [
        ['type' => 'text', 'text' => $message]
    ]
];

$ch = curl_init('https://api.line.me/v2/bot/message/push');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $token
]);
curl_setopt($ch, CURLOPT_CAINFO, "C:\\AppServ\\cacert.pem"); // เพิ่มบรรทัดนี้ถ้าจำเป็น
$response = curl_exec($ch);
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpcode == 200) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode([
        'success' => false,
        'error' => 'ส่งข้อความไม่สำเร็จ',
        'httpcode' => $httpcode,
        'response' => $response
    ]);
}
?>