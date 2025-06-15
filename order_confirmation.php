<?php

session_start();
require_once 'includes/db.php';
require_once 'includes/helpers.php'; // ตรวจสอบว่ามีฟังก์ชัน getStatusColor, getPaymentStatusText, getPaymentMethodText อยู่ใน helpers.php

$order_id = $_SESSION['last_order_id'] ?? null;
if (!$order_id) {
    header('Location: index.php');
    exit();
}

$user_id = $_SESSION['user']['id'] ?? null;

// ดึงข้อมูลคำสั่งซื้อ
$stmt = $pdo->prepare("
    SELECT o.*
    FROM orders o
    WHERE o.id = ?
");
$stmt->execute([$order_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

// ถ้าไม่พบคำสั่งซื้อหรือไม่ใช่คำสั่งซื้อของผู้ใช้นี้
if (!$order) {
    header("Location: index.php");
    exit;
}

// ดึงรายการสินค้าในคำสั่งซื้อ
$stmt = $pdo->prepare("
    SELECT oi.*, p.name as product_name, p.image as product_image
    FROM order_items oi
    LEFT JOIN products p ON oi.product_id = p.id
    WHERE oi.order_id = ?
");
$stmt->execute([$order_id]);
$order_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ดึงข้อมูลการชำระเงิน
$stmt = $pdo->prepare("
    SELECT *
    FROM payment_notifications
    WHERE order_id = ?
    ORDER BY created_at DESC
");
$stmt->execute([$order_id]);
$payment = $stmt->fetch(PDO::FETCH_ASSOC);

// ดึงข้อมูลการจัดส่ง
$stmt = $pdo->prepare("
    SELECT *
    FROM shippings
    WHERE order_id = ?
    ORDER BY created_at DESC
");
$stmt->execute([$order_id]);
$shipping = $stmt->fetch(PDO::FETCH_ASSOC);

// แปลงสถานะคำสั่งซื้อเป็นภาษาไทย
$order_status_text = [
    'pending' => 'รอการชำระเงิน',
    'paid' => 'ชำระเงินแล้ว',
    'processing' => 'กำลังจัดเตรียมสินค้า',
    'shipped' => 'จัดส่งแล้ว',
    'delivered' => 'ได้รับสินค้าแล้ว',
    'cancelled' => 'ยกเลิก'
];

// ส่งข้อความแจ้งเตือนผ่าน LINE (ถ้ามีการเชื่อมต่อกับ LINE)
if (file_exists('includes/line_messaging_api.php') && file_exists('includes/order_notification.php')) {
    try {
        require_once 'includes/line_messaging_api.php';
        require_once 'includes/order_notification.php';
        
        // ส่งข้อความแจ้งเตือนเมื่อมีคำสั่งซื้อใหม่ (เฉพาะกรณีที่เพิ่งสร้างคำสั่งซื้อ)
        if (isset($_GET['new_order']) && $_GET['new_order'] == 1) {
            if (file_exists('includes/line_messaging_api.php') && file_exists('includes/order_notification.php')) {
                try {
                    require_once 'includes/line_messaging_api.php';
                    require_once 'includes/order_notification.php';
                    sendNewOrderNotification($order_id);
                } catch (Exception $e) {
                    error_log('LINE Notification Error: ' . $e->getMessage());
                }
            }
            require_once __DIR__ . '/includes/notify_helper.php';
            $msg = "📦 มีออเดอร์ใหม่ #{$order_id}\n"
                 . "ชื่อ: " . ($order['customer_name'] ?? '-') . "\n"
                 . "ยอดรวม: " . number_format($order['total_amount'], 2) . " บาท\n"
                 . "วันที่: " . date('d/m/Y H:i', strtotime($order['created_at']));
            sendLineNotify($msg, true);
            sendTelegramNotify($msg);
            // ลบ session เพื่อป้องกันแจ้งเตือนซ้ำ
            unset($_SESSION['last_order_id']);
        }
    } catch (Exception $e) {
        // ไม่ต้องทำอะไรถ้าเกิดข้อผิดพลาด เพื่อไม่ให้กระทบกับการแสดงผลหน้าเว็บ
        error_log('LINE Notification Error: ' . $e->getMessage());
    }
}

// แจ้งเตือนออเดอร์ใหม่ไปที่แอดมินทั้ง LINE และ Telegram
if (!empty($order_id)) {
    require_once __DIR__ . '/includes/notify_helper.php';
    // เตรียมข้อความแจ้งเตือน
    $msg = "📦 มีออเดอร์ใหม่ #{$order_id}\n"
         . "ชื่อ: " . ($order['customer_name'] ?? '-') . "\n"
         . "ยอดรวม: " . number_format($order['total_amount'], 2) . " บาท\n"
         . "วันที่: " . date('d/m/Y H:i', strtotime($order['created_at'])) . "\n"
         . "อีเมล: " . ($order['email'] ?? '-') . "\n"
         . "เบอร์โทร: " . ($order['phone'] ?? '-') . "\n"
         . "ที่อยู่: " . ($order['shipping_address'] ?? '') . " " . ($order['shipping_postcode'] ?? '');
    sendLineNotify($msg, true);
    sendTelegramNotify($msg);

    // ===== แจ้งเตือนแอดมินผ่าน LINE Messaging API (admin_line_user_id) =====
    require_once __DIR__ . '/includes/line_messaging_api.php';
    $stmtAdmin = $pdo->query("SELECT admin_line_user_id FROM site_settings WHERE id = 2");
    $admin_line_user_id = $stmtAdmin->fetchColumn();
    $channelAccessToken = $settings['line_channel_access_token'] ?? '';
    if (!empty($admin_line_user_id) && !empty($channelAccessToken)) {
        sendLinePushMessage($channelAccessToken, $admin_line_user_id, $msg);
    }
}

// ฟังก์ชันสำหรับแปลงสถานะการชำระเงินเป็นภาษาไทย
function getPaymentStatusText($status) {
    $status_text = [
        'pending' => 'รอการตรวจสอบ',
        'verified' => 'ตรวจสอบแล้ว',
        'failed' => 'ไม่ถูกต้อง'
    ];
    
    return $status_text[$status] ?? $status;
}

// ฟังก์ชันสำหรับแปลงวิธีการชำระเงินเป็นภาษาไทย
function getPaymentMethodText($method) {
    $method_text = [
        'bank_transfer' => 'โอนเงินผ่านธนาคาร',
        'credit_card' => 'บัตรเครดิต/เดบิต',
        'promptpay' => 'พร้อมเพย์',
        'cod' => 'เก็บเงินปลายทาง'
    ];
    
    return $method_text[$method] ?? $method;
}

// ดึงข้อมูลคำสั่งซื้อ (อันนี้ซ้ำกับข้างบน แต่ทิ้งไว้เผื่อคุณต้องการใช้ตัวแปร $order ที่ดึงมาใหม่)
$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
$stmt->execute([$order_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

// ===== แจ้งเตือนลูกค้าทาง LINE OA (ถ้ามี line_user_id) =====
if (!empty($order['line_user_id'])) {
    require_once __DIR__ . '/includes/order_notification.php';
    // sendOrderConfirmationToCustomer($order, $order_items); // suppress error for debugging
    try {
        sendOrderConfirmationToCustomer($order, $order_items);
    } catch (Throwable $ex) {
        error_log('LINE OA customer notify error: ' . $ex->getMessage());
    }
}

// ===== แจ้งเตือนแอดมินผ่าน LINE Messaging API (admin_line_user_id) =====
require_once __DIR__ . '/includes/line_messaging_api.php';
$stmtAdmin = $pdo->query("SELECT admin_line_user_id FROM site_settings WHERE id = 2");
$admin_line_user_id = $stmtAdmin ? $stmtAdmin->fetchColumn() : '';
$channelAccessToken = $settings['line_channel_access_token'] ?? '';
if (!empty($admin_line_user_id) && !empty($channelAccessToken)) {
    $msg = "📦 มีออเดอร์ใหม่ #{$order_id}\n"
         . "ชื่อ: " . ($order['customer_name'] ?? '-') . "\n"
         . "อีเมล: " . ($order['email'] ?? '-') . "\n"
         . "เบอร์โทร: " . ($order['phone'] ?? '-') . "\n"
         . "ที่อยู่: " . ($order['shipping_address'] ?? '') . "\n"
         . ($order['shipping_province'] ?? '') . " " . ($order['shipping_postcode'] ?? '') . "\n"
         . "ยอดรวม: " . number_format($order['total_amount'], 2) . " บาท\n"
         . "วันที่: " . date('d/m/Y H:i', strtotime($order['created_at']));
    try {
        sendLinePushMessage($channelAccessToken, $admin_line_user_id, $msg);
    } catch (Throwable $ex) {
        error_log('LINE OA admin notify error: ' . $ex->getMessage());
    }
}

// ฟังก์ชันสำหรับกำหนดสีของสถานะคำสั่งซื้อ
if (!function_exists('getStatusColor')) {
    function getStatusColor($status) {
        $colors = [
            'pending' => 'warning',
            'paid' => 'info',
            'processing' => 'primary',
            'shipped' => 'info', // สามารถเปลี่ยนเป็น success หรือ primary ได้ถ้าต้องการเน้น
            'delivered' => 'success',
            'cancelled' => 'danger'
        ];
        
        return $colors[$status] ?? 'secondary';
    }
}

// ฟังก์ชันสำหรับกำหนดสีของสถานะการชำระเงิน
if (!function_exists('getPaymentStatusColor')) {
    function getPaymentStatusColor($status) {
        $colors = [
            'pending' => 'warning',
            'verified' => 'success',
            'failed' => 'danger'
        ];
        
        return $colors[$status] ?? 'secondary';
    }
}

// ตรวจสอบและกำหนดค่า SITE_NAME ถ้ายังไม่ได้กำหนด
if (!defined('SITE_NAME')) {
    try {
        $stmt = $pdo->query("SELECT site_name FROM site_settings WHERE id = 2");
        $site_settings = $stmt->fetch(PDO::FETCH_ASSOC);
        define('SITE_NAME', $site_settings['site_name'] ?? 'ร้านค้าออนไลน์');
    } catch (PDOException $e) {
        error_log('Error fetching SITE_NAME: ' . $e->getMessage());
        define('SITE_NAME', 'ร้านค้าออนไลน์'); // fallback value
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ยืนยันคำสั่งซื้อ #<?= $order_id ?> - <?= SITE_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        /* Custom styles for a 'wow' effect */
        body {
            background-color: #f8f9fa; /* Light gray background */
        }
        .card-header-gradient {
            background: linear-gradient(45deg, #28a745, #218838); /* Green gradient */
            color: white;
            border-bottom: none;
            padding: 2.5rem 1.5rem;
            border-radius: 0.5rem 0.5rem 0 0;
            position: relative;
            overflow: hidden;
        }
        .card-header-gradient h2 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            position: relative;
            z-index: 1;
        }
        .card-header-gradient p {
            font-size: 1.25rem;
            opacity: 0.9;
            position: relative;
            z-index: 1;
        }
        .card-header-gradient::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: rgba(255, 255, 255, 0.1);
            transform: rotate(30deg);
            z-index: 0;
        }
        .order-id-highlight {
            background-color: #e9f7ef; /* Light green background */
            padding: 1.5rem;
            border-radius: 12px;
            margin-bottom: 3rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            border: 1px solid #c3e6cb; /* Light green border */
        }
        .order-id-highlight h3 {
            font-size: 2.2rem;
            font-weight: 800;
            color: #28a745;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .order-id-highlight h3 .text-dark {
            margin-left: 0.75rem;
            letter-spacing: 1px;
            font-size: 2.5rem; /* Larger font for ID */
        }
        .order-id-highlight i {
            font-size: 2rem;
            color: #28a745;
            margin-right: 10px;
        }
        .card-info {
            border: 1px solid #0dcaf0; /* Info color border */
            box-shadow: 0 2px 10px rgba(13, 202, 240, 0.1);
            border-radius: 0.75rem;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        .card-primary-info {
            border: 1px solid #0d6efd; /* Primary color border */
            box-shadow: 0 2px 10px rgba(13, 110, 253, 0.1);
            border-radius: 0.75rem;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        .card-info:hover, .card-primary-info:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.15);
        }
        .card-info .card-header, .card-primary-info .card-header {
            background-color: #0dcaf0; /* Info color */
            color: white;
            font-weight: bold;
            border-bottom: none;
            padding: 1rem 1.5rem;
        }
        .card-primary-info .card-header {
             background-color: #0d6efd; /* Primary color */
        }
        .card-info .card-body p, .card-primary-info .card-body p {
            margin-bottom: 0.75rem;
            line-height: 1.6;
        }
        .card-info .card-body p:last-child, .card-primary-info .card-body p:last-child {
            margin-bottom: 0;
        }
        .table th, .table td {
            vertical-align: middle;
            padding: 1rem;
        }
        .table thead th {
            background-color: #f0f2f5; /* Light grey for table header */
            color: #343a40;
            font-weight: 600;
        }
        .table tbody tr:hover {
            background-color: #f2f2f2;
        }
        .table tfoot tr.table-success td {
            background-color: #d1e7dd !important;
            border-top: 2px solid #28a745;
            font-weight: bold;
            font-size: 1.15rem;
        }
        .table tfoot td {
            padding: 1rem;
        }
        .badge {
            font-size: 0.85em;
            padding: 0.5em 0.8em;
            border-radius: 0.5rem;
        }
        .payment-card {
            border-color: #ffc107; /* Warning color border */
            box-shadow: 0 2px 10px rgba(255, 193, 7, 0.1);
            border-radius: 0.75rem;
        }
        .payment-card .card-header {
            background-color: #ffc107; /* Warning color */
            color: #343a40;
            font-weight: bold;
            border-bottom: none;
        }
        .payment-card.verified-payment {
            border-color: #198754; /* Success color border */
            box-shadow: 0 2px 10px rgba(25, 135, 84, 0.1);
        }
        .payment-card.verified-payment .card-header {
            background-color: #198754; /* Success color */
            color: white;
        }
        .line-cta {
            background: linear-gradient(90deg, #1abc9c, #1ddb62); /* LINE green gradient */
            color: white;
            padding: 3rem;
            border-radius: 1rem;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
            text-align: center;
            margin-top: 4rem;
            margin-bottom: 4rem;
            position: relative;
            overflow: hidden;
        }
        .line-cta::before {
            content: '\f099'; /* Font Awesome Twitter icon, or any other icon */
            font-family: "Font Awesome 5 Brands";
            position: absolute;
            top: -20px;
            right: -20px;
            font-size: 150px;
            color: rgba(255, 255, 255, 0.1);
            transform: rotate(-15deg);
        }
        .line-cta h4 {
            font-size: 2.2rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
        }
        .line-cta p {
            font-size: 1.1rem;
            margin-bottom: 2rem;
            opacity: 0.9;
        }
        .line-cta .btn-line {
            background-color: #00b900; /* Darker LINE green */
            border-color: #00b900;
            font-size: 1.25rem;
            padding: 0.75rem 2rem;
            border-radius: 50px;
            transition: all 0.3s ease;
            font-weight: bold;
        }
        .line-cta .btn-line:hover {
            background-color: #008f00;
            border-color: #008f00;
            transform: translateY(-3px);
        }
        .btn-lg {
            padding: 0.75rem 2rem;
            font-size: 1.1rem;
            border-radius: 0.5rem;
            transition: all 0.3s ease;
        }
        .btn-outline-primary:hover, .btn-outline-secondary:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="container my-5">
        <div class="row justify-content-center">
            <div class="col-md-10 col-lg-9">
                <div class="card shadow-lg border-0 rounded-lg">
                    <div class="card-header-gradient text-center">
                        <h2 class="mb-0"><i class="fas fa-check-circle me-2"></i> คำสั่งซื้อของคุณได้รับการยืนยันแล้ว!</h2>
                        <p class="lead mb-0">ขอบคุณสำหรับการสั่งซื้อสินค้าจาก <?= SITE_NAME ?></p>
                    </div>
                    <div class="card-body p-5">
                        <div class="text-center order-id-highlight">
                            <h3 class="mb-0">
                                <i class="fas fa-barcode"></i>
                                รหัสคำสั่งซื้อ: <strong class="text-dark">#<?= htmlspecialchars($order_id) ?></strong>
                            </h3>
                            <p class="text-muted mt-2">โปรดเก็บรหัสนี้ไว้เพื่อติดตามสถานะและอ้างอิง</p>
                        </div>
                        
                        <div class="row g-4 mb-5">
                            <div class="col-md-6">
                                <div class="card h-100 card-info">
                                    <div class="card-header">
                                        <h5 class="mb-0"><i class="fas fa-truck me-2"></i> ข้อมูลการจัดส่ง</h5>
                                    </div>
                                    <div class="card-body">
                                        <p><strong>👤 ชื่อผู้รับ:</strong> <?= htmlspecialchars($order['customer_name']) ?></p>
                                        <p><strong>📧 อีเมล:</strong> <?= htmlspecialchars($order['email'] ?? '-') ?></p>
                                        <p><strong>📞 เบอร์โทรศัพท์:</strong> <?= htmlspecialchars($order['phone'] ?? '-') ?></p>
                                        <p class="mb-0"><strong>🏠 ที่อยู่จัดส่ง:</strong><br>
                                            <?= htmlspecialchars($order['shipping_address']) ?><br>
                                            <?= htmlspecialchars($order['shipping_province']) ?> <?= htmlspecialchars($order['shipping_postcode']) ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card h-100 card-primary-info">
                                    <div class="card-header">
                                        <h5 class="mb-0"><i class="fas fa-clipboard-list me-2"></i> รายละเอียดคำสั่งซื้อ</h5>
                                    </div>
                                    <div class="card-body">
                                        <p><strong>🆔 รหัสคำสั่งซื้อ:</strong> #<?= $order_id ?></p>
                                        <p><strong>📅 วันที่สั่งซื้อ:</strong> <?= date('d/m/Y H:i', strtotime($order['created_at'])) ?></p>
                                        <p><strong>📦 สถานะ:</strong> 
                                            <span class="badge bg-<?= getStatusColor($order['status']) ?> text-white py-2 px-3 rounded-pill">
                                                <?= $order_status_text[$order['status']] ?? $order['status'] ?>
                                            </span>
                                        </p>
                                        <p><strong>💳 วิธีการชำระเงิน:</strong> <?= getPaymentMethodText($order['payment_method']) ?></p>
                                        <?php if ($shipping): ?>
                                            <p><strong>🔢 หมายเลขพัสดุ:</strong> <span class="badge bg-secondary text-white"><?= htmlspecialchars($shipping['tracking_number']) ?></span></p>
                                            <p class="mb-0"><strong>🚚 บริษัทขนส่ง:</strong> <?= htmlspecialchars($shipping['shipping_company']) ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <h4 class="mb-4 text-primary text-center"><i class="fas fa-box-open me-2"></i> รายการสินค้า</h4>
                        <div class="table-responsive mb-5">
                            <table class="table table-hover border rounded overflow-hidden">
                                <thead class="bg-light">
                                    <tr>
                                        <th scope="col">สินค้า</th>
                                        <th scope="col" class="text-center">ราคา</th>
                                        <th scope="col" class="text-center">จำนวน</th>
                                        <th scope="col" class="text-end">รวม</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($order_items as $item): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <?php if ($item['product_image']): ?>
                                                        <img src="uploads/<?= htmlspecialchars($item['product_image']) ?>" alt="<?= htmlspecialchars($item['product_name']) ?>" class="img-thumbnail me-3" style="width: 60px; height: 60px; object-fit: cover; border-radius: 8px;">
                                                    <?php endif; ?>
                                                    <div class="fw-bold"><?= htmlspecialchars($item['product_name']) ?></div>
                                                </div>
                                            </td>
                                            <td class="text-center"><?= number_format($item['price'], 2) ?> บาท</td>
                                            <td class="text-center"><?= $item['quantity'] ?></td>
                                            <td class="text-end"><?= number_format($item['price'] * $item['quantity'], 2) ?> บาท</td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot class="table-group-divider">
                                    <tr>
                                        <td colspan="3" class="text-end fw-bold">รวมค่าสินค้า:</td>
                                        <td class="text-end fw-bold"><?= number_format($order['subtotal'] ?? 0, 2) ?> บาท</td>
                                    </tr>
                                    <tr>
                                        <td colspan="3" class="text-end fw-bold">ค่าจัดส่ง:</td>
                                        <td class="text-end fw-bold"><?= number_format($order['shipping_cost'], 2) ?> บาท</td>
                                    </tr>
                                    <?php if (!empty($order['discount']) && $order['discount'] > 0): ?>
                                        <tr>
                                            <td colspan="3" class="text-end fw-bold">ส่วนลด:</td>
                                            <td class="text-end fw-bold text-danger">-<?= number_format($order['discount'] ?? 0, 2) ?> บาท</td>
                                        </tr>
                                    <?php endif; ?>
                                    <tr class="table-success">
                                        <td colspan="3" class="text-end fs-5 fw-bold">ยอดรวมทั้งสิ้น:</td>
                                        <td class="text-end fs-5 fw-bold"><?= number_format($order['total_amount'], 2) ?> บาท</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        
                        <?php if ($order['status'] === 'pending'): ?>
                            <div class="card mb-4 payment-card">
                                <div class="card-header">
                                    <h5 class="mb-0"><i class="fas fa-wallet me-2"></i> การชำระเงิน</h5>
                                </div>
                                <div class="card-body">
                                    <p class="lead">กรุณาชำระเงินภายใน 24 ชั่วโมงเพื่อยืนยันคำสั่งซื้อของคุณ</p>
                                    <a href="payment_notification.php?order_id=<?= $order_id ?>" class="btn btn-warning btn-lg text-dark">
                                        <i class="fas fa-receipt me-2"></i> แจ้งชำระเงิน
                                    </a>
                                </div>
                            </div>
                        <?php elseif ($payment): ?>
                            <div class="card mb-4 payment-card <?= $payment['status'] === 'verified' ? 'verified-payment' : '' ?>">
                                <div class="card-header">
                                    <h5 class="mb-0"><i class="fas fa-money-check-alt me-2"></i> ข้อมูลการแจ้งชำระเงิน</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row align-items-center">
                                        <div class="col-md-7">
                                            <p><strong>วันที่โอน:</strong> <?= date('d/m/Y', strtotime($payment['transfer_date'])) ?> <?= $payment['transfer_time'] ?></p>
                                            <p><strong>จำนวนเงิน:</strong> <span class="text-success fw-bold"><?= number_format($payment['transfer_amount'], 2) ?> บาท</span></p>
                                            <p><strong>ธนาคาร:</strong> <?= htmlspecialchars($payment['bank_name']) ?></p>
                                            <p class="mb-0"><strong>สถานะ:</strong> <span class="badge bg-<?= getPaymentStatusColor($payment['status']) ?> text-white py-1 px-2 rounded-pill"><?= htmlspecialchars(getPaymentStatusText($payment['status'])) ?></span></p>
                                        </div>
                                        <div class="col-md-5 text-center mt-3 mt-md-0">
                                            <?php if ($payment['proof_of_payment']): ?>
                                                <p class="mb-2 text-muted"><strong>หลักฐานการโอน:</strong></p>
                                                <a href="<?= htmlspecialchars($payment['proof_of_payment']) ?>" target="_blank" class="d-inline-block">
                                                    <img src="<?= htmlspecialchars($payment['proof_of_payment']) ?>" alt="หลักฐานการโอน" class="img-fluid rounded shadow-sm" style="max-width: 180px; height: auto; border: 1px solid #ddd;">
                                                </a>
                                            <?php else: ?>
                                                <p class="text-muted">ไม่มีหลักฐานการโอน</p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <div class="line-cta">
                            <h4 class="mb-3">📲 เพิ่มเพื่อนกับร้านค้าทาง LINE เพื่อรับการแจ้งเตือนสถานะคำสั่งซื้อ!</h4>
                            <p>กดเพิ่มเพื่อนหรือเข้าสู่ระบบด้วย LINE เพื่อรับข่าวสาร โปรโมชั่น และติดตามสถานะออเดอร์ของคุณได้ทันที</p>
                            <?php if ($line_oa_id): ?>
                                <a href="https://line.me/R/ti/p/@<?= htmlspecialchars($line_oa_id) ?>" target="_blank" class="btn btn-line text-white">
                                    <i class="fab fa-line me-2"></i> เพิ่มเพื่อน LINE
                                </a>
                            <?php endif; ?>
                        </div>
                        
                        <div class="text-center mt-5">
                            <a href="index.php" class="btn btn-outline-primary btn-lg me-3">
                                <i class="fas fa-home me-2"></i> กลับไปหน้าหลัก
                            </a>
                            <a href="my_orders.php" class="btn btn-outline-secondary btn-lg">
                                <i class="fas fa-list me-2"></i> ดูคำสั่งซื้อทั้งหมด
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php include 'footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script src="assets/js/jquery-3.6.0.min.js"></script>
</body>
</html>

<?php
// ฟังก์ชันสำหรับกำหนดสีของสถานะคำสั่งซื้อ
if (!function_exists('getStatusColor')) {
    function getStatusColor($status) {
        $colors = [
            'pending' => 'warning',
            'paid' => 'info',
            'processing' => 'primary',
            'shipped' => 'info', // สามารถเปลี่ยนเป็น success หรือ primary ได้ถ้าต้องการเน้น
            'delivered' => 'success',
            'cancelled' => 'danger'
        ];
        
        return $colors[$status] ?? 'secondary';
    }
}

// ฟังก์ชันสำหรับกำหนดสีของสถานะการชำระเงิน
if (!function_exists('getPaymentStatusColor')) {
    function getPaymentStatusColor($status) {
        $colors = [
            'pending' => 'warning',
            'verified' => 'success',
            'failed' => 'danger'
        ];
        
        return $colors[$status] ?? 'secondary';
    }
}

// เปิด error display เพื่อ debug (ลบออกเมื่อขึ้น production จริง)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Debugging: Log และแสดงค่าตัวแปรสำคัญ
error_log("Debugging order_confirmation.php: Order ID = " . ($order_id ?? 'NULL'));
if (!$order) {
    error_log("Order not found for Order ID: " . ($order_id ?? 'NULL'));
    echo "<div style='color:red; text-align:center; margin-top:20px;'>ไม่พบคำสั่งซื้อที่ระบุ (Order ID: " . htmlspecialchars($order_id) . ")</div>";
    exit;
}

if (empty($order_items)) {
    error_log("No order items found for Order ID: " . $order_id);
    echo "<div style='color:red; text-align:center; margin-top:20px;'>ไม่พบรายการสินค้าในคำสั่งซื้อ (Order ID: " . htmlspecialchars($order_id) . ")</div>";
    exit;
}

if (!$payment) {
    error_log("No payment information found for Order ID: " . $order_id);
}

if (!$shipping) {
    error_log("No shipping information found for Order ID: " . $order_id);
}

// Debugging: แสดงข้อมูลคำสั่งซื้อและรายการสินค้า
echo "<pre style='background-color: #f8f9fa; padding: 10px; border: 1px solid #ccc;'>";
echo "Order Details:\n";
print_r($order);
echo "\nOrder Items:\n";
print_r($order_items);
echo "\nPayment Details:\n";
print_r($payment);
echo "\nShipping Details:\n";
print_r($shipping);
echo "</pre>";
?>