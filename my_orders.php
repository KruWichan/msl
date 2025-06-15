<?php
// my_orders.php

ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();

if (!isset($_SESSION['user'])) {
    $_SESSION['message'] = ['type' => 'error', 'text' => 'กรุณาเข้าสู่ระบบเพื่อดูคำสั่งซื้อของคุณ'];
    header('Location: login.php');
    exit();
}

require 'includes/db.php';

$user = $_SESSION['user'];
$customer_email = $user['email']; // ใช้ email จาก session user

$orders = [];
try {
    // ดึงข้อมูลคำสั่งซื้อทั้งหมดของลูกค้าคนนี้
    $stmt = $pdo->prepare("
        SELECT o.*, 
            (SELECT pn.status FROM payment_notifications pn WHERE pn.order_id = o.id ORDER BY pn.created_at DESC LIMIT 1) AS payment_status
        FROM orders o
        WHERE o.email = :customer_email
        ORDER BY o.created_at DESC
    ");
    $stmt->execute(['customer_email' => $customer_email]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Error fetching customer orders: " . $e->getMessage());
    $_SESSION['message'] = ['type' => 'error', 'text' => 'ไม่สามารถโหลดข้อมูลคำสั่งซื้อของคุณได้: ' . $e->getMessage()];
}

// Function to translate status for display
function getThaiOrderStatus($status) {
    switch ($status) {
        case 'pending':
            return 'รอชำระเงิน';
        case 'processing':
            return 'กำลังดำเนินการ';
        case 'shipped':
            return 'จัดส่งแล้ว';
        case 'completed':
            return 'สำเร็จ';
        case 'cancelled':
            return 'ยกเลิกแล้ว';
        case 'waiting_payment_proof':
            return 'รอตรวจสอบหลักฐานการชำระเงิน';
        case 'payment_approved': // เพิ่มสถานะนี้
            return 'ชำระเงินอนุมัติแล้ว';
        default:
            return $status ? ucfirst($status) : '-'; // Avoid passing null to ucfirst
    }
}

// Function to get status color (Tailwind CSS classes)
function getStatusColorClass($status) {
    switch ($status) {
        case 'pending':
        case 'waiting_payment_proof':
            return 'bg-yellow-100 text-yellow-800';
        case 'processing':
            return 'bg-blue-100 text-blue-800';
        case 'shipped':
            return 'bg-purple-100 text-purple-800';
        case 'completed':
            return 'bg-green-100 text-green-800';
        case 'cancelled':
            return 'bg-red-100 text-red-800';
        case 'payment_approved': // สีสำหรับสถานะนี้
            return 'bg-green-200 text-green-900';
        default:
            return 'bg-gray-100 text-gray-800';
    }
}

function getThaiPaymentStatus($payment_status) {
    switch ($payment_status) {
        case 'pending': return 'ยังไม่ได้ชำระเงิน';
        case 'waiting_approve': return 'รอตรวจสอบ';
        case 'approved': return 'ชำระเงินอนุมัติแล้ว';
        case 'rejected': return 'หลักฐานไม่ถูกต้อง';
        case 'success': return 'ชำระเงินสำเร็จ';
        default: return $payment_status ? ucfirst($payment_status) : '-'; // Avoid passing null to ucfirst
    }
}
function getPaymentStatusColorClass($payment_status) {
    switch ($payment_status) {
        case 'pending': return 'bg-yellow-100 text-yellow-800';
        case 'waiting_approve': return 'bg-blue-100 text-blue-800';
        case 'approved':
        case 'success': return 'bg-green-100 text-green-800';
        case 'rejected': return 'bg-red-100 text-red-800';
        default: return 'bg-gray-100 text-gray-800';
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>คำสั่งซื้อของฉัน - ชื่อเว็บไซต์ของคุณ</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <style>
        /* Custom styles if needed */
    </style>
</head>
<body class="bg-gray-100 font-sans leading-normal tracking-normal">

    <?php include 'header.php'; ?>

    <div class="container mx-auto px-4 py-8 mt-12">
        <h1 class="text-4xl font-extrabold text-gray-800 mb-8 text-center">คำสั่งซื้อของฉัน</h1>

        <?php
        // Display messages
        if (isset($_SESSION['message'])) {
            $msg_type = $_SESSION['message']['type'];
            $msg_text = $_SESSION['message']['text'];
            echo "<div class='p-4 mb-4 rounded-lg " . ($msg_type == 'success' ? 'bg-green-100 text-green-800' : ($msg_type == 'error' ? 'bg-red-100 text-red-800' : 'bg-blue-100 text-blue-800')) . "'>" . $msg_text . "</div>";
            unset($_SESSION['message']);
        }
        ?>

        <?php if (empty($orders)): ?>
            <div class="bg-white p-6 rounded-lg shadow-xl text-center">
                <p class="text-gray-600 text-lg">คุณยังไม่มีคำสั่งซื้อใดๆ ในขณะนี้</p>
                <a href="products.php" class="mt-4 inline-block bg-blue-600 text-white px-6 py-3 rounded-md hover:bg-blue-700 transition">
                    เลือกซื้อสินค้า
                </a>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 gap-6">
                <?php foreach ($orders as $order): ?>
                    <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-blue-500">
                        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-4">
                            <h2 class="text-xl font-bold text-gray-800 mb-2 md:mb-0">
                                คำสั่งซื้อ #<?= htmlspecialchars($order['id']) ?>
                            </h2>
                            <div class="flex flex-row gap-2 md:justify-end md:items-center w-full md:w-auto mt-2 md:mt-0">
                                <span class="px-3 py-1 text-sm font-semibold rounded-full <?= getStatusColorClass($order['status']) ?>">
                                    <?= getThaiOrderStatus($order['status']) ?>
                                </span>
                                <span class="px-3 py-1 text-sm font-semibold rounded-full <?= getPaymentStatusColorClass($order['payment_status']) ?>">
                                    <?= getThaiPaymentStatus($order['payment_status']) ?>
                                </span>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 text-gray-700 text-sm mb-4">
                            <div>
                                <p><strong>วันที่สั่งซื้อ:</strong> <?= (new DateTime($order['created_at']))->format('d/m/Y H:i') ?></p>
                                <p><strong>รวมทั้งสิ้น:</strong> ฿<?= number_format($order['total_amount'], 2) ?></p>
                                <p><strong>วิธีการชำระเงิน:</strong> <?= htmlspecialchars($order['payment_method'] == 'bank_transfer' ? 'โอนเงินผ่านธนาคาร' : 'เก็บเงินปลายทาง (COD)') ?></p>
                            </div>
                            <div>
                                <p><strong>ชื่อผู้สั่งซื้อ:</strong> <?= htmlspecialchars($order['customer_name']) ?></p>
                                <p><strong>อีเมล:</strong> <?= htmlspecialchars($order['email']) ?></p>
                                <p><strong>เบอร์โทรศัพท์:</strong> <?= htmlspecialchars($order['phone']) ?></p>
                            </div>
                            <div>
                                <p><strong>ชื่อผู้รับ:</strong> <?= htmlspecialchars($order['shipping_name']) ?></p>
                                <p><strong>เบอร์โทรศัพท์ผู้รับ:</strong> <?= htmlspecialchars($order['shipping_phone']) ?></p>
                                <p><strong>ที่อยู่จัดส่ง:</strong> <?= nl2br(htmlspecialchars($order['shipping_address'] . ', ' . $order['shipping_postcode'])) ?></p>
                            </div>
                        </div>
                        <div class="flex justify-end mt-4">
                            <a href="order_detail.php?order_id=<?= htmlspecialchars($order['id']) ?>" 
                               class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition text-sm">
                                ดูรายละเอียดคำสั่งซื้อ <i class="fas fa-arrow-right ml-2"></i>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <?php include 'footer.php'; ?>

</body>
</html>