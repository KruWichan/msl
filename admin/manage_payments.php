<?php
// เปิดการแสดงผล Error สำหรับการ Debug (ควรปิดในการใช้งานจริงบน Production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
// ตรวจสอบว่าผู้ใช้ล็อกอินและเป็น admin หรือไม่
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

require '../includes/db.php'; // ตรวจสอบ Path ให้ถูกต้อง
require '../includes/helpers.php'; // ตรวจสอบ Path ให้ถูกต้อง
require_once '../includes/line_messaging_api.php'; // ต้องมีบรรทัดนี้

// ดึงค่าการตั้งค่าเว็บไซต์ (สำหรับชื่อเว็บไซต์, สี, ฟอนต์)
$stmt_settings = $pdo->query("SELECT * FROM site_settings WHERE id = 2");
$settings = $stmt_settings->fetch(PDO::FETCH_ASSOC);

if (!$settings) {
    // กำหนดค่าเริ่มต้นถ้าไม่พบข้อมูลในฐานข้อมูล
    $settings = [
        'site_name' => 'MorsengLove',
        'font_family' => 'Sarabun',
        'primary_color' => '#2563eb',
        'secondary_color' => '#f0f0f0',
        'logo' => 'uploads/default_logo.png',
        'product_display_type' => 'all',
        'featured_tag_ids' => '',
        'homepage_banner_grid_id' => null
    ];
}

$site_name = $settings['site_name'] ?? 'MorsengLove';
$font = in_array($settings['font_family'] ?? '', ['Sarabun', 'Kanit', 'Prompt', 'Mitr', 'Noto Sans Thai', 'Anuphan', 'IBM Plex Sans Thai', 'Chakra Petch']) ? $settings['font_family'] : 'Sarabun';
$primary_color = $settings['primary_color'] ?? '#2563eb';
$secondary_color = $settings['secondary_color'] ?? '#f0f0f0';

$message = '';
$message_type = ''; // 'success' or 'error'

// --- จัดการการอนุมัติ/ปฏิเสธการแจ้งชำระเงิน ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $notification_id = $_POST['notification_id'];
    $action = $_POST['action']; // 'approve' or 'reject'
    $note_admin = trim($_POST['note_admin'] ?? '');

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("SELECT order_id, customer_name, transfer_amount FROM payment_notifications WHERE id = ?");
        $stmt->execute([$notification_id]);
        $notification = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$notification) {
            throw new Exception("ไม่พบรายการแจ้งชำระเงิน.");
        }

        $order_id = $notification['order_id'];

        if ($action === 'approve') {
            // อัปเดตสถานะการแจ้งชำระเงินเป็น 'approved'
            $stmt_update_notification = $pdo->prepare("UPDATE payment_notifications SET status = 'approved', note_admin = ? WHERE id = ?");
            $stmt_update_notification->execute([$note_admin, $notification_id]);

            // อัปเดตสถานะคำสั่งซื้อเป็น 'processing'
            $stmt_update_order = $pdo->prepare("UPDATE orders SET status = 'processing', updated_at = NOW() WHERE id = ?");
            $stmt_update_order->execute([$order_id]);

            $message = "อนุมัติการแจ้งชำระเงินและอัปเดตสถานะคำสั่งซื้อ #{$order_id} เป็น 'กำลังดำเนินการ' เรียบร้อยแล้ว.";
            $message_type = 'success';

            // แจ้งเตือน LINE/Telegram
            require_once __DIR__ . '/../includes/notify_helper.php';
            $msg = "✅ อนุมัติแจ้งชำระเงิน Order #{$order_id}\n"
                 . "ชื่อผู้โอน: {$notification['customer_name']}\n"
                 . "ยอดเงิน: " . number_format($notification['transfer_amount'], 2) . " บาท\n"
                 . "วันที่: " . date('d/m/Y H:i') . "\n"
                 . "สถานะออเดอร์: processing";
            sendLineNotify($msg, true);
            sendTelegramNotify($msg);

            // หลังจากอัปเดตสถานะการชำระเงินสำเร็จ
            // ดึงข้อมูล order และ line_user_id ของลูกค้า
            $stmtOrder = $pdo->prepare("SELECT o.*, u.line_user_id FROM orders o LEFT JOIN users u ON o.user_id = u.id WHERE o.id = ?");
            $stmtOrder->execute([$order_id]);
            $order = $stmtOrder->fetch(PDO::FETCH_ASSOC);

            $customerLineId = $order['line_user_id'] ?? '';
            $channelAccessToken = $settings['line_channel_access_token'] ?? '';

            // ===== แจ้งเตือนลูกค้าผ่าน LINE Messaging API =====
            if ($customerLineId && $channelAccessToken) {
                require_once __DIR__ . '/../includes/order_notification.php';
                notifyCustomerOrder($order, 'แจ้งชำระเงินได้รับการอนุมัติแล้ว');
            }
        } elseif ($action === 'reject') {
            // อัปเดตสถานะการแจ้งชำระเงินเป็น 'rejected'
            $stmt_update_notification = $pdo->prepare("UPDATE payment_notifications SET status = 'rejected', note_admin = ? WHERE id = ?");
            $stmt_update_notification->execute([$note_admin, $notification_id]);

            $message = "ปฏิเสธการแจ้งชำระเงินสำหรับคำสั่งซื้อ #{$order_id} เรียบร้อยแล้ว.";
            $message_type = 'info';

            // แจ้งเตือน LINE/Telegram
            require_once __DIR__ . '/../includes/notify_helper.php';
            $msg = "❌ ปฏิเสธแจ้งชำระเงิน Order #{$order_id}\n"
                 . "ชื่อผู้โอน: {$notification['customer_name']}\n"
                 . "ยอดเงิน: " . number_format($notification['transfer_amount'], 2) . " บาท\n"
                 . "วันที่: " . date('d/m/Y H:i');
            sendLineNotify($msg, true);
            sendTelegramNotify($msg);

            // แจ้งเตือนลูกค้าผ่าน LINE Messaging API กรณีปฏิเสธ
            $stmtOrder = $pdo->prepare("SELECT o.*, u.line_user_id FROM orders o LEFT JOIN users u ON o.user_id = u.id WHERE o.id = ?");
            $stmtOrder->execute([$order_id]);
            $order = $stmtOrder->fetch(PDO::FETCH_ASSOC);
            $customerLineId = $order['line_user_id'] ?? '';
            $channelAccessToken = $settings['line_channel_access_token'] ?? '';
            if ($customerLineId && $channelAccessToken) {
                require_once __DIR__ . '/../includes/order_notification.php';
                notifyCustomerOrder($order, 'แจ้งชำระเงินถูกปฏิเสธ กรุณาตรวจสอบข้อมูลและแจ้งใหม่อีกครั้ง');
            }
        } else {
            throw new Exception("การกระทำไม่ถูกต้อง.");
        }

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = "เกิดข้อผิดพลาด: " . $e->getMessage();
        $message_type = 'error';
        error_log("Payment Notification Admin Error: " . $e->getMessage());
    }
}

// --- ดึงข้อมูลการแจ้งชำระเงินทั้งหมด ---
$status_filter = $_GET['status'] ?? ''; // กรองตามสถานะ
$search_query = $_GET['search'] ?? ''; // ค้นหาด้วย order_id หรือ customer_name

$sql = "
    SELECT pn.*, o.total_amount AS order_total_amount, o.status AS order_status
    FROM payment_notifications pn
    JOIN orders o ON pn.order_id = o.id
    WHERE 1=1
";
$params = [];

if ($status_filter && in_array($status_filter, ['pending', 'approved', 'rejected'])) {
    $sql .= " AND pn.status = ?";
    $params[] = $status_filter;
}

if ($search_query) {
    $sql .= " AND (pn.order_id = ? OR pn.customer_name LIKE ?)";
    $params[] = $search_query;
    $params[] = '%' . $search_query . '%';
}

$sql .= " ORDER BY pn.created_at DESC";

$stmt_payments = $pdo->prepare($sql);
$stmt_payments->execute($params);
$payment_notifications = $stmt_payments->fetchAll(PDO::FETCH_ASSOC);

// ฟังก์ชันสำหรับแสดงสถานะเป็นภาษาไทย
function getPaymentStatusThai($status) {
    switch ($status) {
        case 'pending': return '<span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">รอดำเนินการ</span>';
        case 'approved': return '<span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">อนุมัติแล้ว</span>';
        case 'rejected': return '<span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">ปฏิเสธ</span>';
        default: return $status;
    }
}

// ฟังก์ชันสำหรับแสดงสถานะคำสั่งซื้อเป็นภาษาไทย
function getOrderStatusThai($status) {
    switch ($status) {
        case 'pending': return '<span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">รอดำเนินการ</span>';
        case 'processing': return '<span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-indigo-100 text-indigo-800">กำลังดำเนินการ</span>';
        case 'shipped': return '<span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-purple-100 text-purple-800">จัดส่งแล้ว</span>';
        case 'completed': return '<span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">สำเร็จ</span>';
        case 'cancelled': return '<span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">ยกเลิกแล้ว</span>';
        default: return $status;
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการแจ้งชำระเงิน - <?= htmlspecialchars($site_name) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=<?= str_replace(' ', '+', $font) ?>&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body { font-family: '<?= $font ?>', sans-serif; }
        .primary-bg { background-color: <?= $primary_color ?>; }
        .primary-text { color: <?= $primary_color ?>; }
        .primary-border { border-color: <?= $primary_color ?>; }
        .primary-button { background-color: <?= $primary_color ?>; }
        .primary-button:hover { background-color: var(--color-darken, <?php echo adjustBrightness($primary_color, -20); ?>); }
        .secondary-bg { background-color: <?= $secondary_color ?>; }
        .secondary-text { color: <?= $secondary_color ?>; }
    </style>
</head>
<body class="bg-gray-100 flex flex-col min-h-screen">
    <?php require 'header.php'; // ใช้ header ของ Admin ?>

    <main class="flex-grow container mx-auto px-4 py-8">
        <div class="bg-white p-8 rounded-lg shadow-xl">
            <h1 class="text-3xl font-bold mb-6 text-center primary-text">จัดการแจ้งชำระเงิน <i class="fas fa-receipt"></i></h1>

            <?php if ($message): ?>
                <div class="p-4 mb-6 rounded-md 
                    <?php echo $message_type === 'success' ? 'bg-green-100 border border-green-400 text-green-700' : ($message_type === 'info' ? 'bg-blue-100 border border-blue-400 text-blue-700' : 'bg-red-100 border border-red-400 text-red-700'); ?>
                    " role="alert">
                    <p class="font-bold text-center"><?= $message_type === 'success' ? 'สำเร็จ!' : ($message_type === 'info' ? 'ข้อมูล' : 'ผิดพลาด!') ?></p>
                    <p class="text-center"><?= $message ?></p>
                </div>
            <?php endif; ?>

            <div class="mb-6 flex flex-col md:flex-row space-y-4 md:space-y-0 md:space-x-4 items-center">
                <form action="" method="GET" class="flex-grow flex flex-col md:flex-row space-y-4 md:space-y-0 md:space-x-4 w-full">
                    <div class="flex-grow">
                        <label for="search" class="sr-only">ค้นหา</label>
                        <input type="text" id="search" name="search" placeholder="ค้นหารหัสสั่งซื้อ/ชื่อผู้โอน" 
                                value="<?= htmlspecialchars($search_query) ?>"
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm p-2">
                    </div>
                    <div>
                        <label for="status" class="sr-only">สถานะ</label>
                        <select id="status" name="status" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm p-2">
                            <option value="">-- สถานะทั้งหมด --</option>
                            <option value="pending" <?= $status_filter == 'pending' ? 'selected' : '' ?>>รอดำเนินการ</option>
                            <option value="approved" <?= $status_filter == 'approved' ? 'selected' : '' ?>>อนุมัติแล้ว</option>
                            <option value="rejected" <?= $status_filter == 'rejected' ? 'selected' : '' ?>>ปฏิเสธ</option>
                        </select>
                    </div>
                    <button type="submit" class="primary-button text-white px-4 py-2 rounded-md hover:primary-button-darken focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        <i class="fas fa-filter"></i> กรอง
                    </button>
                </form>
            </div>

            <?php if (empty($payment_notifications)): ?>
                <p class="text-center text-gray-500 text-lg py-10">ยังไม่มีรายการแจ้งชำระเงิน</p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    #
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    คำสั่งซื้อ
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    ผู้โอน
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    ยอดโอน
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    วันที่/เวลาโอน
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    หลักฐาน
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    สถานะแจ้งเตือน
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    สถานะคำสั่งซื้อ
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    จัดการ
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($payment_notifications as $payment): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        <?= htmlspecialchars($payment['id']) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <a href="view_order.php?id=<?= htmlspecialchars($payment['order_id']) ?>" class="primary-text hover:underline">
                                            #<?= htmlspecialchars($payment['order_id']) ?>
                                        </a>
                                        <br>
                                        <small class="text-gray-500">ยอดรวม: <?= number_format($payment['order_total_amount'], 2) ?> ฿</small>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?= htmlspecialchars($payment['customer_name']) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?= number_format($payment['transfer_amount'], 2) ?> ฿
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?= htmlspecialchars(thai_date($payment['transfer_date'])) ?>
                                        <br>
                                        <?= htmlspecialchars($payment['transfer_time']) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php if ($payment['proof_of_payment']): ?>
                                            <a href="../<?= htmlspecialchars($payment['proof_of_payment']) ?>" target="_blank" class="primary-text hover:underline flex items-center">
                                                <i class="fas fa-file-image mr-1"></i> ดูสลิป
                                            </a>
                                        <?php else: ?>
                                            ไม่มี
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?= getPaymentStatusThai($payment['status']) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?= getOrderStatusThai($payment['order_status']) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <?php if ($payment['status'] === 'pending'): ?>
                                            <form action="manage_payments.php" method="POST" class="inline-block" onsubmit="return confirm('คุณต้องการอนุมัติการแจ้งชำระเงินนี้สำหรับคำสั่งซื้อ #<?= htmlspecialchars($payment['order_id']) ?>?');">
                                                <input type="hidden" name="notification_id" value="<?= htmlspecialchars($payment['id']) ?>">
                                                <input type="hidden" name="action" value="approve">
                                                <input type="hidden" name="note_admin" value="อนุมัติโดยผู้ดูแลระบบ">
                                                <button type="submit" class="text-green-600 hover:text-green-900 mr-3" title="อนุมัติ"><i class="fas fa-check-circle"></i> อนุมัติ</button>
                                            </form>
                                            <form action="manage_payments.php" method="POST" class="inline-block" onsubmit="return promptRejectReason(this, '<?= htmlspecialchars($payment['order_id']) ?>');">
                                                <input type="hidden" name="notification_id" value="<?= htmlspecialchars($payment['id']) ?>">
                                                <input type="hidden" name="action" value="reject">
                                                <input type="hidden" name="note_admin" id="note_admin_<?= htmlspecialchars($payment['id']) ?>" value="">
                                                <button type="submit" class="text-red-600 hover:text-red-900" title="ปฏิเสธ"><i class="fas fa-times-circle"></i> ปฏิเสธ</button>
                                            </form>
                                        <?php else: ?>
                                            <span class="text-gray-500">ดำเนินการแล้ว</span>
                                            <?php if ($payment['note_admin']): ?>
                                                <br><small class="text-gray-500" title="บันทึกผู้ดูแลระบบ"><i class="fas fa-info-circle"></i> <?= htmlspecialchars($payment['note_admin']) ?></small>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <?php require 'footer.php'; // ใช้ footer ของ Admin ?>

    <script>
        function promptRejectReason(form, orderId) {
            const reason = prompt('กรุณาระบุเหตุผลในการปฏิเสธการแจ้งชำระเงินสำหรับคำสั่งซื้อ #' + orderId + ':');
            if (reason === null) { // User clicked Cancel
                return false;
            }
            document.getElementById('note_admin_' + form.notification_id.value).value = reason;
            return true;
        }
    </script>
</body>
</html>