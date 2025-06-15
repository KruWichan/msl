<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/helpers.php';

// ดึงค่าการตั้งค่าเว็บไซต์
$stmt_settings = $pdo->query("SELECT * FROM site_settings WHERE id = 2");
$settings = $stmt_settings->fetch(PDO::FETCH_ASSOC) ?: [
    'site_name' => 'MorsengLove',
    'font_family' => 'Sarabun',
    'primary_color' => '#2563eb',
    'secondary_color' => '#f0f0f0',
    'logo' => 'uploads/default_logo.png'
];

$site_name = $settings['site_name'];
$font = in_array($settings['font_family'], ['Sarabun', 'Kanit', 'Prompt', 'Mitr', 'Noto Sans Thai']) ? $settings['font_family'] : 'Sarabun';
$primary_color = $settings['primary_color'];
$secondary_color = $settings['secondary_color'];

// ดึงบัญชีธนาคารทั้งหมด
$stmt_banks = $pdo->query("SELECT * FROM bank_accounts WHERE is_active = 1 ORDER BY id ASC");
$bank_accounts = $stmt_banks->fetchAll(PDO::FETCH_ASSOC);

// ช่องทางการชำระเงิน
$bank_available = count($bank_accounts) > 0;
$promptpay_available = false;
$qr_available = false;
foreach ($bank_accounts as $b) {
    if (!empty($b['promptpay_number'])) $promptpay_available = true;
    if (!empty($b['promptpay_qr'])) $qr_available = true;
}
$payment_channels = [];
if ($bank_available) $payment_channels['bank'] = 'โอนเข้าบัญชีธนาคาร';
if ($promptpay_available) $payment_channels['promptpay'] = 'พร้อมเพย์';
if ($qr_available) $payment_channels['qr'] = 'QR Code';

// ดึง order_id สำหรับลูกค้า (เฉพาะ order ที่ยังไม่แจ้งชำระเงินและยัง pending)
$user_id = $_SESSION['user_id'] ?? null;
$line_user_id = $_SESSION['line_user_id'] ?? null;
$telegram_user_id = $_SESSION['telegram_user_id'] ?? null;
$user_email = $_SESSION['user']['email'] ?? null;
$order_list = [];
$order_id_prefill = $_GET['order_id'] ?? '';
if ($user_id) {
    $stmt = $pdo->prepare("
        SELECT o.id
        FROM orders o
        LEFT JOIN payment_notifications p ON o.id = p.order_id AND p.status IN ('pending','success')
        WHERE o.user_id = ? 
        AND o.status = 'pending'
        AND p.id IS NULL
        ORDER BY o.created_at DESC
    ");
    $stmt->execute([$user_id]);
    $order_list = $stmt->fetchAll(PDO::FETCH_COLUMN);
} elseif ($line_user_id) {
    $stmt = $pdo->prepare("
        SELECT o.id
        FROM orders o
        LEFT JOIN payment_notifications p ON o.id = p.order_id AND p.status IN ('pending','success')
        WHERE o.line_user_id = ? 
        AND o.status = 'pending'
        AND p.id IS NULL
        ORDER BY o.created_at DESC
    ");
    $stmt->execute([$line_user_id]);
    $order_list = $stmt->fetchAll(PDO::FETCH_COLUMN);
} elseif ($telegram_user_id) {
    $stmt = $pdo->prepare("
        SELECT o.id
        FROM orders o
        LEFT JOIN payment_notifications p ON o.id = p.order_id AND p.status IN ('pending','success')
        WHERE o.telegram_user_id = ? 
        AND o.status = 'pending'
        AND p.id IS NULL
        ORDER BY o.created_at DESC
    ");
    $stmt->execute([$telegram_user_id]);
    $order_list = $stmt->fetchAll(PDO::FETCH_COLUMN);
} elseif ($user_email) {
    $stmt = $pdo->prepare("
        SELECT o.id, o.total_amount
        FROM orders o
        LEFT JOIN payment_notifications p ON o.id = p.order_id AND p.status IN ('pending','success','approved')
        WHERE o.email = ?
        AND o.status = 'pending'
        AND p.id IS NULL
        ORDER BY o.created_at DESC
    ");
    $stmt->execute([$user_email]);
    $order_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
if (count($order_list) === 1) {
    $order_id_prefill = $order_list[0];
}

// --- ฟอร์ม POST ---
$message = '';
$message_type = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $order_id = trim($_POST['order_id'] ?? '');
    $customer_name = trim($_POST['customer_name'] ?? '');
    $payment_channel = $_POST['payment_channel'] ?? '';
    $bank_name = trim($_POST['bank_name'] ?? '');
    $transfer_amount = trim($_POST['transfer_amount'] ?? '');
    $transfer_date = trim($_POST['transfer_date'] ?? '');
    $transfer_time = trim($_POST['transfer_time'] ?? '');

    $errors = [];
    if (empty($order_id)) $errors[] = "กรุณาระบุรหัสคำสั่งซื้อ.";
    if (empty($customer_name)) $errors[] = "กรุณาระบุชื่อผู้โอน.";
    if ($payment_channel === 'bank' && empty($bank_name)) $errors[] = "กรุณาเลือกธนาคารที่โอนเข้า.";
    if (empty($transfer_amount) || !is_numeric($transfer_amount) || $transfer_amount <= 0) $errors[] = "กรุณาระบุยอดเงินที่โอนให้ถูกต้อง.";
    if (empty($transfer_date)) $errors[] = "กรุณาระบุวันที่โอน.";
    if (empty($transfer_time)) $errors[] = "กรุณาระบุเวลาที่โอน.";

    // ตรวจสอบ order_id ว่ามีอยู่จริงและยังไม่ถูกแจ้งชำระเงิน
    if (!empty($order_id)) {
        // ตรวจสอบ order ว่ายัง pending และยังไม่มีแจ้งชำระเงินที่รออนุมัติ
        $stmt_check_order = $pdo->prepare("
            SELECT o.id
            FROM orders o
            LEFT JOIN payment_notifications p ON o.id = p.order_id AND p.status IN ('pending','success','approved')
            WHERE o.id = ? AND o.status = 'pending' AND p.id IS NULL
        ");
        $stmt_check_order->execute([$order_id]);
        if (!$stmt_check_order->fetch()) {
            $errors[] = "ไม่พบรหัสคำสั่งซื้อที่คุณระบุ หรือคำสั่งซื้อนี้แจ้งชำระเงินไปแล้ว";
        }

        $stmt_check_payment = $pdo->prepare("SELECT id FROM payment_notifications WHERE order_id = ? AND status IN ('pending','success','approved')");
        $stmt_check_payment->execute([$order_id]);
        if ($stmt_check_payment->fetch()) {
            $errors[] = "คำสั่งซื้อนี้แจ้งชำระเงินไปแล้ว กรุณารอการตรวจสอบ";
        }
    }

    // อัปโหลดไฟล์สลิป
    $proof_of_payment = null;
    if (isset($_FILES['proof_of_payment']) && $_FILES['proof_of_payment']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/payments/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
        $file_ext = pathinfo($_FILES['proof_of_payment']['name'], PATHINFO_EXTENSION);
        $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'pdf'];
        if (!in_array(strtolower($file_ext), $allowed_ext)) {
            $errors[] = "ประเภทไฟล์หลักฐานการโอนไม่ถูกต้อง. อนุญาตเฉพาะ JPG, PNG, GIF, PDF";
        } else {
            $new_file_name = 'slip_' . uniqid() . '.' . $file_ext;
            $dest_path = $upload_dir . $new_file_name;
            if (!move_uploaded_file($_FILES['proof_of_payment']['tmp_name'], $dest_path)) {
                $errors[] = "เกิดข้อผิดพลาดในการอัปโหลดไฟล์สลิป.";
            } else {
                $proof_of_payment = 'uploads/payments/' . $new_file_name;
            }
        }
    }
    if ($_FILES['proof_of_payment']['size'] > 5 * 1024 * 1024) {
        $errors[] = "ขนาดไฟล์สลิปต้องไม่เกิน 5MB";
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO payment_notifications (order_id, customer_name, bank_name, transfer_amount, transfer_date, transfer_time, proof_of_payment, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')");
            $stmt->execute([$order_id, $customer_name, $bank_name, $transfer_amount, $transfer_date, $transfer_time, $proof_of_payment]);
            // อัปเดตสถานะ order เป็น paid
            $stmt_update_order = $pdo->prepare("UPDATE orders SET status = 'paid' WHERE id = :order_id");
            $stmt_update_order->execute(['order_id' => $order_id]);
            $message = "แจ้งชำระเงินเรียบร้อยแล้ว! เราจะตรวจสอบและอัปเดตสถานะคำสั่งซื้อของคุณโดยเร็วที่สุด ขอบคุณครับ/ค่ะ";
            $message_type = 'success';
            // ลบ session เพื่อไม่ให้เติม order_id เดิมอีก
            unset($_SESSION['last_order_id']);
            // ล้างข้อมูลฟอร์ม
            $order_id_prefill = $customer_name = $bank_name = $transfer_amount = $transfer_date = $transfer_time = '';

            // แจ้งเตือนไปยังแอดมิน (LINE/Telegram)
            require_once __DIR__ . '/includes/notify_helper.php';
            require_once __DIR__ . '/includes/line_messaging_api.php';

            // ดึงข้อมูล order เพิ่มเติม
            $stmtOrder = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
            $stmtOrder->execute([$order_id]);
            $order = $stmtOrder->fetch(PDO::FETCH_ASSOC);

            $msg = "💸 แจ้งชำระเงินใหม่\n"
                 . "Order: #{$order_id}\n"
                 . "ชื่อผู้โอน: {$customer_name}\n"
                 . "ยอดเงิน: " . number_format((float)$transfer_amount, 2) . " บาท\n"
                 . "วันที่: {$transfer_date} {$transfer_time}\n"
                 . "ธนาคาร: {$bank_name}\n"
                 . "อีเมลลูกค้า: " . ($order['email'] ?? '-') . "\n"
                 . "เบอร์โทร: " . ($order['phone'] ?? '-') . "\n"
                 . "ยอดรวมออเดอร์: " . number_format($order['total_amount'], 2) . " บาท";

            // ส่งผ่าน LINE Messaging API
            $stmtAdmin = $pdo->query("SELECT admin_line_user_id FROM site_settings WHERE id = 2");
            $admin_line_user_id = $stmtAdmin->fetchColumn();
            $channelAccessToken = $settings['line_channel_access_token'] ?? '';
            if (!empty($admin_line_user_id) && !empty($channelAccessToken)) {
                sendLinePushMessage($channelAccessToken, $admin_line_user_id, $msg);
            }

            // ส่ง Telegram (admin)
            $enableTelegram = !empty($settings['enable_telegram_notify']);
            if ($enableTelegram) {
                sendTelegramNotify($msg);
            }
            // DEBUG: แสดงผลลัพธ์การแจ้งเตือน (เฉพาะ dev)
            // error_log("Line notify enabled: " . var_export($enableLine, true));
            // error_log("Telegram notify enabled: " . var_export($enableTelegram, true));
            // หากต้องการแจ้งเตือนเฉพาะเมื่อเปิดใช้งานใน site_settings ให้ตรวจสอบ enable_line_notify/enable_telegram_notify ด้วย

        } catch (PDOException $e) {
            $message = "เกิดข้อผิดพลาดในการบันทึกข้อมูล: " . $e->getMessage();
            $message_type = 'error';
        }
    } else {
        $message = implode('<br>', $errors);
        $message_type = 'error';
    }
}
if ($message_type === 'success') {
    unset($_SESSION['last_order_id']);
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แจ้งชำระเงิน - <?= htmlspecialchars($site_name) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=<?= str_replace(' ', '+', $font) ?>&display=swap" rel="stylesheet">
    <style>
        body { font-family: '<?= $font ?>', sans-serif; }
        .primary-bg { background-color: <?= $primary_color ?>; }
        .primary-text { color: <?= $primary_color ?>; }
        .primary-button { background-color: <?= $primary_color ?>; }
        .primary-button:hover { background-color: <?php echo adjustBrightness($primary_color, -20); ?>; }
    </style>
</head>
<body class="bg-gray-100 flex flex-col min-h-screen">
    <?php require 'header.php'; ?>
    <main class="flex-grow container mx-auto px-4 py-8">
        <div class="max-w-xl mx-auto bg-white p-8 rounded-lg shadow-xl">
            <h1 class="text-3xl font-bold mb-6 text-center primary-text">แจ้งชำระเงิน</h1>
            <p class="text-gray-600 text-center mb-6">กรุณากรอกข้อมูลการโอนเงินและแนบหลักฐานเพื่อยืนยันคำสั่งซื้อของคุณ</p>
            <?php if ($message): ?>
                <div class="p-4 mb-6 rounded-md <?= $message_type === 'success' ? 'bg-green-100 border border-green-400 text-green-700' : 'bg-red-100 border border-red-400 text-red-700'; ?>" role="alert">
                    <p class="font-bold text-center"><?= $message_type === 'success' ? 'สำเร็จ!' : 'ผิดพลาด!' ?></p>
                    <p class="text-center"><?= $message ?></p>
                </div>
            <?php endif; ?>
            <form action="payment_notification.php" method="POST" enctype="multipart/form-data" class="space-y-6">
                <!-- ช่องทางการชำระเงิน -->
                <?php if (count($payment_channels) > 1): ?>
                    <div>
                        <label for="payment_channel" class="block text-sm font-medium text-gray-700 mb-1">ช่องทางที่โอนเข้า <span class="text-red-500">*</span></label>
                        <select id="payment_channel" name="payment_channel" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm p-2">
                            <option value="">-- เลือกช่องทาง --</option>
                            <?php foreach ($payment_channels as $key => $label): ?>
                                <option value="<?= $key ?>" <?= (isset($payment_channel) && $payment_channel == $key) ? 'selected' : '' ?>><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php elseif (count($payment_channels) === 1): ?>
                    <?php $only_channel = array_key_first($payment_channels); ?>
                    <input type="hidden" name="payment_channel" value="<?= $only_channel ?>">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">ช่องทางที่โอนเข้า</label>
                        <div class="p-2 bg-gray-50 rounded border border-gray-200"><?= $payment_channels[$only_channel] ?></div>
                    </div>
                <?php endif; ?>

                <!-- เฉพาะกรณีโอนเข้าธนาคาร -->
                <div id="bank_fields" style="display:none;">
                    <label for="bank_name" class="block text-sm font-medium text-gray-700 mb-1">ธนาคารที่โอนเข้า (ของร้าน) <span class="text-red-500">*</span></label>
                    <select id="bank_name" name="bank_name" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm p-2">
                        <option value="">-- เลือกธนาคาร --</option>
                        <?php foreach ($bank_accounts as $b): ?>
                            <option value="<?= htmlspecialchars($b['bank_name'] . '|' . $b['account_number']) ?>"
                                <?= (isset($bank_name) && $bank_name == ($b['bank_name'] . '|' . $b['account_number'])) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($b['bank_name']) ?> - <?= htmlspecialchars($b['account_number']) ?> (<?= htmlspecialchars($b['account_name']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- เฉพาะกรณีพร้อมเพย์ -->
                <div id="promptpay_fields" style="display:none;">
                    <label class="block text-sm font-medium text-gray-700 mb-1">พร้อมเพย์ที่โอนเข้า</label>
                    <?php foreach ($bank_accounts as $b): ?>
                        <?php if (!empty($b['promptpay_number'])): ?>
                            <div class="p-2 bg-gray-50 rounded border border-gray-200 mb-2">
                                <?= htmlspecialchars($b['promptpay_number']) ?>
                                <?php if (!empty($b['account_name'])): ?>
                                    <span class="text-xs text-gray-500"> (<?= htmlspecialchars($b['account_name']) ?>)</span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>

                <!-- เฉพาะกรณี QR Code -->
                <div id="qr_fields" style="display:none;">
                    <label class="block text-sm font-medium text-gray-700 mb-1">QR Code ที่โอนเข้า</label>
                    <div class="flex flex-wrap gap-4">
                        <?php foreach ($bank_accounts as $b): ?>
                            <?php if (!empty($b['promptpay_qr'])): ?>
                                <div class="flex flex-col items-center">
                                    <img src="<?= htmlspecialchars($b['promptpay_qr']) ?>" alt="QR Code" class="max-h-32 rounded border border-gray-300 shadow my-2">
                                    <?php if (!empty($b['account_name'])): ?>
                                        <span class="text-xs text-gray-500"><?= htmlspecialchars($b['account_name']) ?></span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Order ID -->
                <div>
                    <label for="order_id" class="block text-sm font-medium text-gray-700 mb-1">รหัสคำสั่งซื้อ (Order ID) <span class="text-red-500">*</span></label>
                    <?php if (count($order_list) > 1): ?>
                        <select name="order_id" id="order_id" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm p-2">
                            <option value="">-- เลือกคำสั่งซื้อ --</option>
                            <?php foreach ($order_list as $order): ?>
                                <option value="<?= htmlspecialchars($order['id']) ?>"
                                    data-amount="<?= htmlspecialchars($order['total_amount']) ?>"
                                    <?= ($order['id'] == ($order_id_prefill ?? '')) ? 'selected' : '' ?>>
                                    #<?= htmlspecialchars($order['id']) ?> - <?= number_format($order['total_amount'], 2) ?> บาท
                                </option>
                            <?php endforeach; ?>
                        </select>
                    <?php elseif (count($order_list) === 1): ?>
                        <input type="text" name="order_id" id="order_id" value="<?= htmlspecialchars($order_id_prefill) ?>" readonly class="mt-1 block w-full rounded-md border-gray-300 shadow-sm bg-gray-100 cursor-not-allowed p-2">
                    <?php else: ?>
                        <input type="text" name="order_id" id="order_id" value="" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2" placeholder="กรุณากรอกรหัสคำสั่งซื้อ">
                        <div class="text-xs text-red-500 mt-1">* ไม่พบคำสั่งซื้อในระบบ กรุณากรอกเองหรือเช็คอีเมล/LINE</div>
                    <?php endif; ?>
                </div>

                <div>
                    <label for="customer_name" class="block text-sm font-medium text-gray-700 mb-1">ชื่อผู้โอน <span class="text-red-500">*</span></label>
                    <input type="text" id="customer_name" name="customer_name"
                           value="<?= htmlspecialchars($customer_name ?? '') ?>"
                           required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm p-2">
                </div>

                <div>
                    <label for="transfer_amount" class="block text-sm font-medium text-gray-700 mb-1">ยอดเงินที่โอน (บาท) <span class="text-red-500">*</span></label>
                    <input type="number" step="0.01" id="transfer_amount" name="transfer_amount"
                           value="<?= htmlspecialchars($transfer_amount ?? '') ?>"
                           required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm p-2"
                           placeholder="เช่น 1500.00">
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="transfer_date" class="block text-sm font-medium text-gray-700 mb-1">วันที่โอน <span class="text-red-500">*</span></label>
                        <input type="date" id="transfer_date" name="transfer_date"
                               value="<?= htmlspecialchars($transfer_date ?? date('Y-m-d')) ?>"
                               required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm p-2">
                    </div>
                    <div>
                        <label for="transfer_time" class="block text-sm font-medium text-gray-700 mb-1">เวลาที่โอน <span class="text-red-500">*</span></label>
                        <input type="time" id="transfer_time" name="transfer_time"
                               value="<?= isset($transfer_time) ? htmlspecialchars($transfer_time) : '' ?>"
                               required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm p-2">
                    </div>
                </div>

                <div>
                    <label for="proof_of_payment" class="block text-sm font-medium text-gray-700 mb-1">หลักฐานการโอน (สลิป/รูปภาพ) <span class="text-gray-500">(ถ้ามี)</span></label>
                    <input type="file" id="proof_of_payment" name="proof_of_payment" accept="image/*,.pdf"
                           class="mt-1 block w-full text-sm text-gray-500
                                  file:mr-4 file:py-2 file:px-4
                                  file:rounded-md file:border-0
                                  file:text-sm file:font-semibold
                                  file:bg-blue-50 file:text-blue-700
                                  hover:file:bg-blue-100">
                    <p class="mt-1 text-xs text-gray-500">รองรับไฟล์: JPG, PNG, GIF, PDF (ขนาดไม่เกิน 5MB)</p>
                </div>

                <button type="submit" class="w-full py-3 px-4 border border-transparent rounded-md shadow-sm text-base font-medium text-white primary-button hover:primary-button-darken focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    ยืนยันการแจ้งชำระเงิน
                </button>
            </form>
        </div>
    </main>
    <?php require 'footer.php'; ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            function toggleFields() {
                var channel = document.getElementById('payment_channel') ? document.getElementById('payment_channel').value : '<?= $only_channel ?? '' ?>';
                document.getElementById('bank_fields').style.display = (channel === 'bank') ? '' : 'none';
                document.getElementById('promptpay_fields').style.display = (channel === 'promptpay') ? '' : 'none';
                document.getElementById('qr_fields').style.display = (channel === 'qr') ? '' : 'none';
            }
            var paymentChannel = document.getElementById('payment_channel');
            if (paymentChannel) {
                paymentChannel.addEventListener('change', toggleFields);
                toggleFields();
            } else {
                toggleFields();
            }
            // ตั้งค่าเวลาเริ่มต้นเป็นเวลาปัจจุบัน (ถ้ายังไม่มีค่า)
            var timeInput = document.getElementById('transfer_time');
            if (timeInput && !timeInput.value) {
                var now = new Date();
                var hh = String(now.getHours()).padStart(2, '0');
                var mm = String(now.getMinutes()).padStart(2, '0');
                timeInput.value = hh + ':' + mm;
            }
        });
    </script>
    <script>
document.addEventListener('DOMContentLoaded', function() {
    var orderSelect = document.getElementById('order_id');
    var amountInput = document.getElementById('transfer_amount');
    if(orderSelect && amountInput) {
        orderSelect.addEventListener('change', function() {
            var selected = orderSelect.options[orderSelect.selectedIndex];
            var amount = selected.getAttribute('data-amount');
            if(amount) {
                amountInput.value = amount;
            } else {
                amountInput.value = '';
            }
        });
        // trigger change event on load if already selected
        if(orderSelect.value) {
            var selected = orderSelect.options[orderSelect.selectedIndex];
            var amount = selected.getAttribute('data-amount');
            if(amount) amountInput.value = amount;
        }
    }
});
</script>
</body>
</html>
