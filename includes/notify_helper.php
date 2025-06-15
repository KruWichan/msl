<?php
// includes/notify_helper.php
require_once __DIR__ . '/db.php';

function getSiteSettings() {
    global $pdo;
    static $settings = null;
    if ($settings === null) {
        $stmt = $pdo->query("SELECT * FROM site_settings WHERE id = 2");
        $settings = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }
    return $settings;
}

// ฟังก์ชันนี้สำหรับ LINE NOTIFY เท่านั้น (ไม่ใช่ Messaging API)
function sendLineNotify($message, $isPayment = false) {
    $settings = getSiteSettings();
    $token = $settings['line_notify_token'] ?? '';

    if (empty($token)) {
        error_log('[LINE Notify] Token is empty');
        return false;
    }
    if (strlen($token) != 43) {
        error_log('[LINE Notify] Token format looks invalid or is not a LINE Notify token (length mismatch).');
        return false;
    }

    $enable = !empty($settings['enable_line_notify']) && (
        (!$isPayment && !empty($settings['enable_line_notify_new_order']))
        || ($isPayment && !empty($settings['enable_line_notify_new_payment']))
    );

    if (!$enable) {
        error_log('[LINE Notify] Notification disabled by settings or specific event type.');
        return false;
    }

    $url = 'https://notify-api.line.me/api/notify';
    $headers = [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/x-www-form-urlencoded'
    ];
    $data = ['message' => trim($message)];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    $result = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($result === false || $http_code != 200) {
        error_log('[LINE Notify] Failed: ' . $curl_error . ' | HTTP: ' . $http_code . ' | Response: ' . $result);
        return false;
    }
    return true;
}

// ฟังก์ชันส่ง Telegram Notify (คงเดิม)
function sendTelegramNotify($message) {
    $settings = getSiteSettings();
    $token = $settings['telegram_bot_token'] ?? '';
    $chat_id = $settings['telegram_chat_id'] ?? '';
    $enable = !empty($settings['enable_telegram_notify']);
    if (empty($token) || empty($chat_id) || !$enable) {
        error_log('[Telegram Notify] Token/ChatID empty or disabled');
        return false;
    }
    // เพิ่มรายละเอียด timestamp
    $message = $message . "\n\n[" . date('d/m/Y H:i') . "]";
    $url = "https://api.telegram.org/bot{$token}/sendMessage";
    $data = [
        'chat_id' => $chat_id,
        'text' => trim($message),
        'parse_mode' => 'HTML'
    ];
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    $result = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    if ($result === false || $http_code != 200) {
        error_log('[Telegram Notify] Failed: ' . $curl_error . ' | HTTP: ' . $http_code . ' | Response: ' . $result);
        return false;
    }
    return true;
}
?>
