<?php
// includes/order_notification.php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/notify_helper.php'; // สำหรับ sendLineNotify (ถ้ายังใช้) และ Telegram Notify
require_once __DIR__ . '/line_messaging_api.php'; // สำหรับ sendLinePushMessage, sendLineMessage

/**
 * Function: sendNewOrderNotification
 * Description: ส่งข้อความแจ้งเตือนเมื่อมีคำสั่งซื้อใหม่ (ถึงแอดมินโดยใช้ LINE Messaging API)
 * @param int $orderId - รหัสคำสั่งซื้อ
 * @return bool - true ถ้าส่งสำเร็จ, false ถ้าส่งไม่สำเร็จ
 */
function sendNewOrderNotification($order_id) {
    global $pdo;

    error_log("Starting sendNewOrderNotification for order: " . $order_id);

    // ดึง token และ userId แอดมิน
    $stmt = $pdo->query("SELECT line_channel_access_token, admin_line_user_id, enable_line_notify_new_order, line_template_new_order FROM site_settings WHERE id = 2");
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);

    // ใช้ line_channel_access_token เป็น Messaging API Token ของคุณ
    $accessToken = $settings['line_channel_access_token'] ?? '';
    $adminLineUserId = $settings['admin_line_user_id'] ?? '';
    $enableNewOrderNotify = !empty($settings['enable_line_notify_new_order']);
    $template = $settings['line_template_new_order'] ?? "มีคำสั่งซื้อใหม่ #%order_id% มูลค่า %total_amount% บาท จาก %customer_name% (%customer_email%)\nวันที่: %order_date%";

    if (empty($accessToken)) {
        error_log('[LINE Messaging API] sendNewOrderNotification: Missing LINE Channel Access Token in site_settings.');
        return false;
    }
    if (empty($adminLineUserId)) {
        error_log('[LINE Messaging API] sendNewOrderNotification: Missing Admin LINE User ID in site_settings.');
        return false;
    }
    if (!$enableNewOrderNotify) {
        error_log('[LINE Messaging API] sendNewOrderNotification: New order notifications are disabled in settings.');
        return false;
    }

    $stmt_order = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
    $stmt_order->execute([$order_id]);
    $order = $stmt_order->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        error_log('[LINE Messaging API] sendNewOrderNotification: Order not found for ID: ' . $order_id);
        return false;
    }

    $message = str_replace(
        ['%order_id%', '%total_amount%', '%customer_name%', '%customer_email%', '%order_date%'],
        [
            $order['id'],
            number_format($order['total_amount'], 2),
            $order['customer_name'],
            $order['email'] ?? '-',
            date('d/m/Y H:i', strtotime($order['created_at']))
        ],
        $template
    );

    // ส่ง LINE Messaging API
    $result = sendLinePushMessage($accessToken, $adminLineUserId, $message);

    // ส่ง Telegram ด้วยรายละเอียดเดียวกัน
    require_once __DIR__ . '/notify_helper.php';
    sendTelegramNotify($message);

    if (!$result) {
        error_log('[LINE Messaging API] sendNewOrderNotification: Failed to send LINE message to admin. OrderID: ' . $order_id);
        return false;
    }
    error_log('[LINE Messaging API] sendNewOrderNotification: Successfully sent to admin. OrderID: ' . $order_id);
    return true;
}

// **ปรับปรุงฟังก์ชันแจ้งเตือนลูกค้า (notifyCustomerOrder) และฟังก์ชันอื่นๆ ที่เหลือ**

/**
 * ฟังก์ชันส่งแจ้งเตือนสถานะคำสั่งซื้อไปหาลูกค้า
 * @param array $order ข้อมูลคำสั่งซื้อ
 * @param string $statusText ข้อความสถานะ
 */
function notifyCustomerOrder($order, $statusText) {
    global $pdo;

    // ดึง token และสถานะเปิดใช้งานแจ้งเตือนลูกค้า
    $stmt = $pdo->query("SELECT line_channel_access_token, enable_line_notify_customer_status FROM site_settings WHERE id = 2");
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
    $accessToken = $settings['line_channel_access_token'] ?? ''; // <--- ใช้ token เดิม
    $enableCustomerNotify = !empty($settings['enable_line_notify_customer_status']);

    // ต้องมั่นใจว่า $order มี 'line_user_id' ที่บันทึกไว้สำหรับลูกค้า
    $customerLineId = $order['line_user_id'] ?? null; 

    if (empty($accessToken) || empty($customerLineId) || !$enableCustomerNotify) {
        error_log("[LINE Messaging API] notifyCustomerOrder: Missing token, customer LINE ID, or disabled. Token: " . (empty($accessToken) ? "empty" : "ok") . ", Customer ID: " . (empty($customerLineId) ? "empty" : "ok") . ", Enabled: " . ($enableCustomerNotify ? "true" : "false"));
        return false;
    }

    $message = "เรียนคุณ {$order['customer_name']},\n\nสถานะคำสั่งซื้อ #{$order['id']} ของคุณได้เปลี่ยนเป็น: \"{$statusText}\" แล้ว\n\nขอบคุณที่ใช้บริการครับ";

    // แนบรูป slip ถ้ามี (เฉพาะกรณีแจ้งชำระเงิน)
    $stmtSlip = $pdo->prepare("SELECT proof_of_payment FROM payment_notifications WHERE order_id = ? AND proof_of_payment IS NOT NULL ORDER BY created_at DESC LIMIT 1");
    $stmtSlip->execute([$order['id']]);
    $slip = $stmtSlip->fetchColumn();

    if ($slip && preg_match('/\.(jpg|jpeg|png|gif)$/i', $slip)) {
        // เตรียมข้อความและรูปภาพ (LINE Messaging API รองรับ image message)
        $messages = [
            ['type' => 'text', 'text' => $message],
            [
                'type' => 'image',
                'originalContentUrl' => (strpos($slip, 'http') === 0 ? $slip : (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . '/' . ltrim($slip, '/')),
                'previewImageUrl' => (strpos($slip, 'http') === 0 ? $slip : (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . '/' . ltrim($slip, '/'))
            ]
        ];
        $result = sendLinePushMessage($accessToken, $customerLineId, $messages);
    } else {
        $result = sendLinePushMessage($accessToken, $customerLineId, $message);
    }

    // ส่ง Telegram ถึงลูกค้าด้วย (ถ้ามี telegram_user_id)
    if (!empty($order['telegram_user_id'])) {
        require_once __DIR__ . '/notify_helper.php';
        sendTelegramNotify($message);
    }

    if (!$result) {
        error_log("[LINE Messaging API] notifyCustomerOrder: Failed to send message to customer.");
        return false;
    }
    return true;
}

/**
 * ส่งข้อความยืนยันคำสั่งซื้อ (Order Confirmation) ไปยังลูกค้า LINE OA
 * @param array $order
 * @param array $order_items
 */
function sendOrderConfirmationToCustomer($order, $order_items) {
    global $pdo;

    // ดึง token และเปิดใช้งาน
    $stmt = $pdo->query("SELECT line_channel_access_token, enable_line_notify_customer_status FROM site_settings WHERE id = 2");
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
    $accessToken = $settings['line_channel_access_token'] ?? '';
    $enableCustomerNotify = !empty($settings['enable_line_notify_customer_status']);
    $customerLineId = $order['line_user_id'] ?? null;

    if (empty($accessToken) || empty($customerLineId) || !$enableCustomerNotify) {
        error_log("[LINE Messaging API] sendOrderConfirmationToCustomer: Missing token, customer LINE ID, or disabled.");
        return false;
    }

    // สร้างข้อความ
    $msg = "คำสั่งซื้อของคุณได้รับการยืนยันแล้ว!\n";
    $msg .= "ขอบคุณสำหรับการสั่งซื้อสินค้าจาก หมอเส็งเลิฟดอทคอม - Morsenglove.com\n\n";
    $msg .= "รหัสคำสั่งซื้อ: #" . $order['id'] . "\n";
    $msg .= "โปรดเก็บรหัสนี้ไว้เพื่อติดตามสถานะและอ้างอิง\n\n";
    $msg .= "ข้อมูลการจัดส่ง\n";
    $msg .= "👤 ชื่อผู้รับ: " . ($order['customer_name'] ?? '-') . "\n";
    $msg .= "📧 อีเมล: " . ($order['email'] ?? '-') . "\n";
    $msg .= "📞 เบอร์โทรศัพท์: " . ($order['phone'] ?? '-') . "\n";
    $msg .= "🏠 ที่อยู่จัดส่ง:\n" . ($order['shipping_address'] ?? '') . "\n";
    $msg .= ($order['shipping_province'] ?? '') . " " . ($order['shipping_postcode'] ?? '') . "\n\n";
    $msg .= "รายละเอียดคำสั่งซื้อ\n";
    $msg .= "🆔 รหัสคำสั่งซื้อ: #" . $order['id'] . "\n";
    $msg .= "📅 วันที่สั่งซื้อ: " . date('d/m/Y H:i', strtotime($order['created_at'])) . "\n";
    $msg .= "📦 สถานะ: " . getOrderStatusThai($order['status']) . "\n";
    $msg .= "💳 วิธีการชำระเงิน: " . getPaymentMethodText($order['payment_method'] ?? '') . "\n\n";
    $msg .= "รายการสินค้า\n";
    foreach ($order_items as $item) {
        $msg .= $item['product_name'] . " " . number_format($item['price'], 2) . " บาท x " . $item['quantity'] . " = " . number_format($item['price'] * $item['quantity'], 2) . " บาท\n";
    }
    $msg .= "รวมค่าสินค้า: " . number_format($order['subtotal'] ?? 0, 2) . " บาท\n";
    $msg .= "ค่าจัดส่ง: " . number_format($order['shipping_cost'] ?? 0, 2) . " บาท\n";
    if (!empty($order['discount']) && $order['discount'] > 0) {
        $msg .= "ส่วนลด: -" . number_format($order['discount'], 2) . " บาท\n";
    }
    $msg .= "ยอดรวมทั้งสิ้น: " . number_format($order['total_amount'], 2) . " บาท\n";

    return sendLinePushMessage($accessToken, $customerLineId, $msg);
}

// **ทำแบบเดียวกันกับ notifyCustomerPayment, notifyCustomerCancel, ฯลฯ **
// ในแต่ละฟังก์ชัน ให้:
// 1. ดึง $accessToken จาก line_channel_access_token
// 2. ดึง $customerLineId จาก $order['line_user_id'] (ต้องมั่นใจว่ามีข้อมูลนี้)
// 3. เรียกใช้ sendLinePushMessage($accessToken, $customerLineId, $message);
// หรือถ้าเป็นกรณี reply (จาก webhook) ใช้ sendLineReplyMessage
?>
