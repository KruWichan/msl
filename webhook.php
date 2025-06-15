<?php

// เชื่อมต่อฐานข้อมูล
require_once __DIR__ . '/includes/db.php'; // ตรวจสอบเส้นทางให้ถูกต้อง หากไฟล์ db.php อยู่ในโฟลเดอร์ includes

// ดึง Channel Access Token และ Channel Secret จากฐานข้อมูล
try {
    $stmt_settings = $pdo->query("SELECT line_channel_access_token, line_channel_secret FROM site_settings WHERE id = 2");
    $settings = $stmt_settings->fetch(PDO::FETCH_ASSOC);
    $accessToken = $settings['line_channel_access_token'] ?? null;
    $channelSecret = $settings['line_channel_secret'] ?? null;

    if (!$accessToken || !$channelSecret) {
        // หากไม่พบ Channel Access Token หรือ Channel Secret ในฐานข้อมูล ให้บันทึกข้อผิดพลาดและยุติการทำงาน
        error_log('LINE Webhook: Missing access token or channel secret');
        http_response_code(500); // Internal Server Error
        echo "Error: Missing LINE credentials.";
        exit();
    }
} catch (PDOException $e) {
    error_log('LINE Webhook DB Error: ' . $e->getMessage());
    http_response_code(500); // Internal Server Error
    echo "Error: Database connection failed.";
    exit();
}

// รับข้อมูล JSON ที่ LINE ส่งมา
$content = file_get_contents('php://input');

// ตรวจสอบ LINE signature
$signature = $_SERVER['HTTP_X_LINE_SIGNATURE'] ?? '';
$hash = base64_encode(hash_hmac('sha256', $content, $channelSecret, true));
if (!hash_equals($hash, $signature)) {
    error_log('LINE Webhook: Invalid signature');
    http_response_code(400);
    exit('Invalid signature');
}

$events = json_decode($content, true);

// ตรวจสอบว่ามีข้อมูล event หรือไม่
if (empty($events['events'])) {
    echo "OK"; // ไม่มี event ให้ประมวลผล
    exit();
}

foreach ($events['events'] as $event) {
    // ดึง replyToken และ userId สำหรับทุก event ที่มี source type เป็น user
    $replyToken = $event['replyToken'] ?? null;
    $userId = $event['source']['userId'] ?? null;

    // Log เพื่อดีบั๊ก (สามารถเปิด/ปิดได้ตามต้องการ)
    // file_put_contents('line_webhook_debug.log', date('Y-m-d H:i:s') . " - Event Type: " . $event['type'] . ", User ID: " . $userId . ", Message: " . ($event['message']['text'] ?? 'N/A') . "\n", FILE_APPEND);

    // --- ส่วนจัดการ Event ประเภทต่างๆ ---

    // 1. เหตุการณ์เมื่อผู้ใช้เพิ่มเพื่อน (Follow Event)
    if ($event['type'] == 'follow' && $replyToken && $userId) {
        $welcomeMessage = "ขอบคุณที่เพิ่มเพื่อนกับ " . SITE_NAME . " ครับ! 👋\n\n";
        $welcomeMessage .= "LINE User ID ของคุณคือ:\n`" . $userId . "`\n\n";
        $welcomeMessage .= "คุณสามารถนำ User ID นี้ไปใช้สำหรับการตั้งค่าแจ้งเตือนส่วนตัวได้เลยครับ หรือติดต่อสอบถามข้อมูลเพิ่มเติมได้เลย 😊";
        
        $replyData = [
            'replyToken' => $replyToken,
            'messages' => [
                ['type' => 'text', 'text' => $welcomeMessage]
            ]
        ];
        sendLineReply($replyData, $accessToken);
    }
    // 2. เหตุการณ์เมื่อผู้ใช้ส่งข้อความ (Message Event - Text)
    else if ($event['type'] == 'message' && $event['message']['type'] == 'text') {
        $userMessage = trim(strtolower($event['message']['text'])); // แปลงเป็นตัวพิมพ์เล็กและตัดช่องว่าง
        
        if ($replyToken && $userId) {
            $replyText = '';
            
            switch ($userMessage) {
                case 'myid':
                case 'ไอดีฉัน':
                case 'userid':
                    $replyText = "LINE User ID ของคุณคือ:\n`" . $userId . "`\n\nคุณสามารถนำไปตั้งค่าการแจ้งเตือนได้ครับ";
                    break;
                case 'สวัสดี':
                case 'hello':
                    $replyText = "สวัสดีครับ! " . SITE_NAME . " ยินดีต้อนรับครับ มีอะไรให้ช่วยไหมครับ?";
                    break;
                // เพิ่มเงื่อนไขสำหรับการตอบกลับข้อความอื่นๆ ตามที่คุณต้องการ
                default:
                    $replyText = 'คุณพิมพ์: "' . htmlspecialchars($event['message']['text']) . '"\n';
                    $replyText .= 'เรากำลังปรับปรุงการตอบกลับอัตโนมัติ กรุณาติดต่อแอดมินโดยตรงหากมีข้อสงสัย';
                    break;
            }

            $replyData = [
                'replyToken' => $replyToken,
                'messages' => [
                    ['type' => 'text', 'text' => $replyText]
                ]
            ];
            sendLineReply($replyData, $accessToken);
        }
    }
    // 3. สามารถเพิ่มการจัดการ Event ประเภทอื่น ๆ ได้ที่นี่
    // เช่น 'join', 'leave', 'postback' (สำหรับ Quick Reply/Template Messages)
}

// ทุกครั้งที่ LINE ส่ง Webhook มา PHP ควรจะคืนค่า "OK" กลับไปให้ LINE เพื่อยืนยันว่าได้รับข้อมูลแล้ว
echo "OK";


/**
 * ฟังก์ชันสำหรับส่งข้อความตอบกลับไปยัง LINE
 * @param array $data ข้อมูลที่จะส่งในรูปแบบ JSON (replyToken, messages)
 * @param string $accessToken Channel Access Token
 * @return bool true หากส่งสำเร็จ, false หากเกิดข้อผิดพลาด
 */
function sendLineReply($data, $accessToken) {
    $ch = curl_init('https://api.line.me/v2/bot/message/reply');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $accessToken
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // ตรวจสอบผลลัพธ์การส่ง (สำหรับการดีบั๊ก)
    if ($httpCode != 200) {
        error_log("LINE Reply API Error (HTTP $httpCode): " . $response);
        return false;
    }
    return true;
}

// ตรวจสอบและกำหนดค่า SITE_NAME ถ้ายังไม่ได้กำหนด (จาก order_confirmation.php)
// หรือคุณอาจจะย้ายไปไว้ใน includes/helpers.php หรือไฟล์ config กลาง
if (!defined('SITE_NAME')) {
    global $pdo; // เรียกใช้ตัวแปร $pdo ที่ประกาศไว้แล้ว
    if (isset($pdo)) {
        try {
            $stmt_site_name = $pdo->query("SELECT site_name FROM site_settings WHERE id = 2");
            $site_settings = $stmt_site_name->fetch(PDO::FETCH_ASSOC);
            define('SITE_NAME', $site_settings['site_name'] ?? 'ร้านค้าออนไลน์');
        } catch (PDOException $e) {
            error_log('LINE Webhook Error: Failed to load SITE_NAME: ' . $e->getMessage());
            define('SITE_NAME', 'ร้านค้าออนไลน์'); // fallback
        }
    } else {
        define('SITE_NAME', 'ร้านค้าออนไลน์'); // fallback if $pdo is not available
    }
}

?>