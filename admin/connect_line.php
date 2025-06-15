<?php
session_start();
require_once '../includes/db.php';

// ตั้งค่าจาก LINE Developers Console
$client_id = '2004169452';
$client_secret = 'f67cc201695a4bdbcb09409ed2761e65';
$redirect_uri = 'https://www.morsenglove.com/MSL/admin/connect_line.php'; // ต้องตรงกับที่ลงทะเบียนไว้ใน LINE Developers Console

// 1. ถ้ายังไม่มี code ให้ redirect ไป LINE Login
if (!isset($_GET['code'])) {
    $state = bin2hex(random_bytes(8));
    $_SESSION['line_login_state'] = $state;
    $auth_url = "https://access.line.me/oauth2/v2.1/authorize?"
        . http_build_query([
            'response_type' => 'code',
            'client_id' => $client_id,
            'redirect_uri' => $redirect_uri,
            'state' => $state,
            'scope' => 'profile openid'
        ]);
    header("Location: $auth_url");
    exit;
}

// 2. ตรวจสอบ state
if ($_GET['state'] !== ($_SESSION['line_login_state'] ?? '')) {
    echo 'Session ID: ' . session_id() . '<br>';
    echo 'GET state: ' . htmlspecialchars($_GET['state'] ?? '') . '<br>';
    echo 'SESSION state: ' . htmlspecialchars($_SESSION['line_login_state'] ?? '') . '<br>';
    error_log('LINE Connect: Invalid state. GET=' . ($_GET['state'] ?? '') . ' SESSION=' . ($_SESSION['line_login_state'] ?? ''));
    exit('Invalid state');
}
// ลบ state ออกจาก session หลังตรวจสอบเสร็จ
unset($_SESSION['line_login_state']);

// 3. ขอ access token
$code = $_GET['code'];
$token_url = "https://api.line.me/oauth2/v2.1/token";
$data = [
    'grant_type' => 'authorization_code',
    'code' => $code,
    'redirect_uri' => $redirect_uri,
    'client_id' => $client_id,
    'client_secret' => $client_secret
];
$options = [
    CURLOPT_URL => $token_url,
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POSTFIELDS => http_build_query($data),
    CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded']
];
$ch = curl_init();
curl_setopt_array($ch, $options);
// ใช้ local path ไม่ใช่ URL
curl_setopt($ch, CURLOPT_CAINFO, __DIR__ . '/../includes/cacert.pem'); // หรือใส่ path จริง เช่น '/home/youruser/public_html/MSL/includes/cacert.pem'
// หรือทดสอบแบบไม่ verify SSL (dev เท่านั้น)
// curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);
$token_data = json_decode($response, true);

if (empty($token_data['id_token'])) {
    echo "<pre>HTTP CODE: $http_code\n";
    echo "RESPONSE: " . htmlspecialchars($response) . "\n";
    echo "CURL ERROR: " . htmlspecialchars($curl_error) . "</pre>";
    exit('ไม่สามารถขอ access token ได้');
}

// 4. ถอดรหัส id_token เพื่อเอา line user id
$id_token = $token_data['id_token'];
$jwt_parts = explode('.', $id_token);
$payload = json_decode(base64_decode(strtr($jwt_parts[1], '-_', '+/')), true);
$line_user_id = $payload['sub'] ?? null;

// $line_user_id คือ LINE User ID ที่ใช้กับ Messaging API
if (!$line_user_id) {
    exit('ไม่พบ LINE User ID');
}

// 5. บันทึก line_user_id ลงใน users (ใช้ session user id)
if (!isset($_SESSION['user']['id'])) {
    exit('กรุณาเข้าสู่ระบบก่อนเชื่อมต่อ LINE');
}
$user_id = $_SESSION['user']['id'];
$stmt = $pdo->prepare("UPDATE users SET line_user_id = ?, line_connected_at = NOW() WHERE id = ?");
$stmt->execute([$line_user_id, $user_id]);

echo "<h2>เชื่อมต่อ LINE สำเร็จ!</h2>";
echo "<div>LINE User ID ของคุณคือ: <b>$line_user_id</b></div>";
echo "<a href='setting.php'>กลับไปหน้าตั้งค่า</a>";
?>