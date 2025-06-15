<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}
require '../includes/db.php';  // เชื่อมต่อฐานข้อมูล

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die('คำสั่งซื้อไม่ถูกต้อง');
}

$order_id = (int)$_GET['id'];

// ดึงข้อมูลคำสั่งซื้อ
$sql_order = "SELECT * FROM orders WHERE id = :id";
$stmt_order = $pdo->prepare($sql_order);
$stmt_order->execute(['id' => $order_id]);
$order = $stmt_order->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    die('ไม่พบคำสั่งซื้อนี้');
}

// ดึงรายการสินค้าที่สั่งในคำสั่งซื้อ
$sql_items = "SELECT oi.*, p.name AS product_name 
              FROM order_items oi 
              JOIN products p ON oi.product_id = p.id
              WHERE oi.order_id = :order_id";
$stmt_items = $pdo->prepare($sql_items);
$stmt_items->execute(['order_id' => $order_id]);
$items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>รายละเอียดคำสั่งซื้อ #<?= htmlspecialchars($order_id) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs" defer></script>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun&display=swap" rel="stylesheet" />
    <style>
        body { font-family: 'Sarabun', sans-serif; }
    </style>
</head>
<body class="bg-gray-100 text-gray-800">
<?php require 'header.php'; ?>

<div class="max-w-4xl mx-auto bg-white p-6 rounded shadow">
    <h1 class="text-2xl font-bold mb-4">รายละเอียดคำสั่งซื้อ #<?= htmlspecialchars($order_id) ?></h1>

    <div class="mb-6">
        <h2 class="text-lg font-semibold mb-2">ข้อมูลลูกค้า</h2>
        <p><strong>ชื่อ:</strong> <?= htmlspecialchars($order['customer_name']) ?></p>
        <p><strong>อีเมล:</strong> <?= htmlspecialchars($order['email']) ?></p>
        <p><strong>โทรศัพท์:</strong> <?= htmlspecialchars($order['phone']) ?></p>
        <p><strong>วันที่สั่งซื้อ:</strong> <?= date('d/m/Y H:i', strtotime($order['created_at'])) ?></p>
        <p><strong>สถานะ:</strong> <?= ucfirst(htmlspecialchars($order['status'])) ?></p>
    </div>

    <div class="mb-6">
        <h2 class="text-lg font-semibold mb-2">ที่อยู่จัดส่ง</h2>
        <p>
            <?php 
                $address = [];
                if (!empty($order['shipping_name'])) $address[] = htmlspecialchars($order['shipping_name']);
                if (!empty($order['shipping_phone'])) $address[] = 'โทร: ' . htmlspecialchars($order['shipping_phone']);
                if (!empty($order['shipping_address'])) $address[] = htmlspecialchars($order['shipping_address']);
                if (!empty($order['shipping_city'])) $address[] = htmlspecialchars($order['shipping_city']);
                if (!empty($order['shipping_province'])) $address[] = htmlspecialchars($order['shipping_province']);
                if (!empty($order['shipping_postcode'])) $address[] = htmlspecialchars($order['shipping_postcode']);
                echo implode(', ', $address);
            ?>
        </p>
    </div>

    <div>
        <h2 class="text-lg font-semibold mb-2">รายการสินค้า</h2>
        <table class="min-w-full border border-gray-300">
            <thead class="bg-gray-200">
                <tr>
                    <th class="border border-gray-300 py-2 px-4 text-left">สินค้า</th>
                    <th class="border border-gray-300 py-2 px-4 text-right">ราคา/ชิ้น</th>
                    <th class="border border-gray-300 py-2 px-4 text-center">จำนวน</th>
                    <th class="border border-gray-300 py-2 px-4 text-right">ราคารวม</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                <tr>
                    <td class="border border-gray-300 py-2 px-4"><?= htmlspecialchars($item['product_name']) ?></td>
                    <td class="border border-gray-300 py-2 px-4 text-right"><?= number_format($item['price'], 2) ?> บาท</td>
                    <td class="border border-gray-300 py-2 px-4 text-center"><?= (int)$item['quantity'] ?></td>
                    <td class="border border-gray-300 py-2 px-4 text-right"><?= number_format($item['price'] * $item['quantity'], 2) ?> บาท</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr class="font-bold bg-gray-100">
                    <td colspan="3" class="border border-gray-300 py-2 px-4 text-right">ยอดรวม</td>
                    <td class="border border-gray-300 py-2 px-4 text-right"><?= number_format($order['total_amount'], 2) ?> บาท</td>
                </tr>
            </tfoot>
        </table>
    </div>

    <div class="mt-6">
        <a href="manage_orders.php" class="text-blue-600 hover:underline">&larr; กลับไปหน้าจัดการคำสั่งซื้อ</a>
    </div>
</div>

</body>
</html>
