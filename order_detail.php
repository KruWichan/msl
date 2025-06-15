<?php
// Debug
//echo 'GET id: ' . ($_GET['id'] ?? 'ไม่มีค่า') . '<br>';
session_start();
require 'includes/db.php';

// ตรวจสอบว่ามีการส่ง id มาหรือไม่
$order_id = 0;
if (isset($_GET['id'])) {
    $order_id = intval($_GET['id']);
} elseif (isset($_GET['order_id'])) {
    $order_id = intval($_GET['order_id']);
}

if (!$order_id) {
    echo "ไม่พบคำสั่งซื้อ (ไม่มี order_id)";
    exit;
}

// ตรวจสอบ session
$user = $_SESSION['user'] ?? null;
if (!$user) {
    echo "กรุณาเข้าสู่ระบบ";
    exit;
}

// ตรวจสอบสิทธิ์การเข้าถึง order
if ($user['role'] === 'admin') {
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
    $stmt->execute([$order_id]);
} else {
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND email = ?");
    $stmt->execute([$order_id, $user['email']]);
}
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    echo "ไม่พบคำสั่งซื้อ (อาจไม่มีสิทธิ์เข้าถึง หรือ order_id ไม่ถูกต้อง)";
    exit;
}

// ดึงรายการสินค้าใน order นี้
$stmt = $pdo->prepare("
    SELECT oi.*, p.name AS product_name
    FROM order_items oi
    LEFT JOIN products p ON oi.product_id = p.id
    WHERE oi.order_id = ?
");
$stmt->execute([$order_id]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ฟังก์ชันแปลงสถานะ
function getStatusText($status) {
    switch ($status) {
        case 'pending': return 'รอชำระเงิน';
        case 'completed': return 'ชำระเงินแล้ว';
        case 'cancelled': return 'ยกเลิก';
        default: return $status;
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>รายละเอียดคำสั่งซื้อ #<?= htmlspecialchars($order['id']) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <div class="max-w-xl mx-auto mt-10 bg-white rounded-xl shadow-lg p-8">
        <h1 class="text-2xl font-bold text-blue-700 mb-2">รายละเอียดคำสั่งซื้อ</h1>
        <div class="flex flex-wrap gap-2 items-center mb-2">
            <span class="text-lg font-semibold text-gray-700">คำสั่งซื้อ #<?= htmlspecialchars($order['id']) ?></span>
            <span class="px-3 py-1 rounded-full text-sm font-medium
                <?= $order['status']=='completed' ? 'bg-green-100 text-green-700' : ($order['status']=='pending' ? 'bg-yellow-100 text-yellow-700' : 'bg-gray-200 text-gray-700') ?>">
                <?= getStatusText($order['status']) ?>
            </span>
        </div>
        <div class="text-gray-500 mb-4">วันที่สั่งซื้อ: <?= date('d/m/Y H:i', strtotime($order['created_at'])) ?></div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
            <div>
                <div class="mb-1 text-gray-600">รวมทั้งสิ้น</div>
                <div class="text-xl font-bold text-blue-700 mb-2">฿<?= number_format($order['total_amount'], 2) ?></div>
                <div class="mb-1 text-gray-600">วิธีการชำระเงิน</div>
                <div><?= $order['payment_method'] === 'cod' ? 'เก็บเงินปลายทาง' : 'โอนเงินผ่านธนาคาร' ?></div>
            </div>
            <div>
                <div class="mb-1 text-gray-600">ชื่อผู้สั่งซื้อ</div>
                <div><?= htmlspecialchars($order['customer_name']) ?></div>
                <div class="mb-1 text-gray-600">อีเมล</div>
                <div><?= htmlspecialchars($order['email']) ?></div>
                <div class="mb-1 text-gray-600">เบอร์โทรศัพท์</div>
                <div><?= htmlspecialchars($order['phone']) ?></div>
            </div>
        </div>
        <div class="mb-4">
            <div class="mb-1 text-gray-600">ชื่อผู้รับ</div>
            <div><?= htmlspecialchars($order['shipping_name']) ?></div>
            <div class="mb-1 text-gray-600">เบอร์โทรศัพท์ผู้รับ</div>
            <div><?= htmlspecialchars($order['shipping_phone']) ?></div>
            <div class="mb-1 text-gray-600">ที่อยู่จัดส่ง</div>
            <div><?= htmlspecialchars($order['shipping_address']) ?>, <?= htmlspecialchars($order['shipping_postcode']) ?></div>
        </div>
        <h2 class="text-lg font-semibold text-blue-700 mt-6 mb-2">รายการสินค้า</h2>
        <?php if ($items): ?>
        <div class="overflow-x-auto">
            <table class="min-w-full bg-white border border-gray-200 rounded-lg">
                <thead>
                    <tr>
                        <th class="py-2 px-3 border-b text-left bg-blue-50">ชื่อสินค้า</th>
                        <th class="py-2 px-3 border-b text-left bg-blue-50">จำนวน</th>
                        <th class="py-2 px-3 border-b text-left bg-blue-50">ราคา/ชิ้น</th>
                        <th class="py-2 px-3 border-b text-left bg-blue-50">ราคารวม</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($items as $item): ?>
                    <tr>
                        <td class="py-2 px-3 border-b"><?= htmlspecialchars($item['product_name']) ?></td>
                        <td class="py-2 px-3 border-b"><?= htmlspecialchars($item['quantity']) ?></td>
                        <td class="py-2 px-3 border-b"><?= number_format($item['price'], 2) ?></td>
                        <td class="py-2 px-3 border-b"><?= number_format($item['price'] * $item['quantity'], 2) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
            <p class="text-gray-500">ไม่มีรายการสินค้าในคำสั่งซื้อนี้</p>
        <?php endif; ?>
        <a href="my_orders.php" class="inline-block mt-6 px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition">ย้อนกลับ</a>
    </div>
</body>
</html>