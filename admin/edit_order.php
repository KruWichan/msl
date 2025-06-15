<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}
require '../includes/db.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die('รหัสคำสั่งซื้อไม่ถูกต้อง');
}

$order_id = (int)$_GET['id'];

// หากส่งฟอร์มมาแล้ว
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customer_name = $_POST['customer_name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $status = $_POST['status'];

    $sql = "UPDATE orders 
            SET customer_name = :customer_name, email = :email, phone = :phone, status = :status 
            WHERE id = :id";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'customer_name' => $customer_name,
        'email' => $email,
        'phone' => $phone,
        'status' => $status,
        'id' => $order_id
    ]);

    header("Location: manage_orders.php");
    exit;
}

// ดึงข้อมูลคำสั่งซื้อเดิมมาแสดงในฟอร์ม
$sql = "SELECT * FROM orders WHERE id = :id";
$stmt = $pdo->prepare($sql);
$stmt->execute(['id' => $order_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    die('ไม่พบคำสั่งซื้อนี้');
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
     <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>แก้ไขคำสั่งซื้อ #<?= $order_id ?></title>
    <script src="https://unpkg.com/alpinejs" defer></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Sarabun', sans-serif; }
    </style>
</head>
<body class="bg-gray-100 text-gray-800">
<?php require 'header.php'; ?>

<div class="max-w-xl mx-auto bg-white p-6 rounded shadow">
    <h1 class="text-2xl font-bold mb-4">แก้ไขคำสั่งซื้อ #<?= htmlspecialchars($order_id) ?></h1>

    <form action="" method="post" class="space-y-4">
        <div>
            <label class="block font-semibold mb-1">ชื่อลูกค้า</label>
            <input type="text" name="customer_name" value="<?= htmlspecialchars($order['customer_name']) ?>"
                   class="w-full border rounded px-3 py-2" required>
        </div>

        <div>
            <label class="block font-semibold mb-1">อีเมล</label>
            <input type="email" name="email" value="<?= htmlspecialchars($order['email']) ?>"
                   class="w-full border rounded px-3 py-2" required>
        </div>

        <div>
            <label class="block font-semibold mb-1">เบอร์โทรศัพท์</label>
            <input type="text" name="phone" value="<?= htmlspecialchars($order['phone']) ?>"
                   class="w-full border rounded px-3 py-2" required>
        </div>

        <div>
            <label class="block font-semibold mb-1">สถานะคำสั่งซื้อ</label>
            <select name="status" class="w-full border rounded px-3 py-2">
                <option value="pending" <?= $order['status'] === 'pending' ? 'selected' : '' ?>>รอดำเนินการ</option>
                <option value="paid" <?= $order['status'] === 'paid' ? 'selected' : '' ?>>ชำระเงินแล้ว</option>
                <option value="shipped" <?= $order['status'] === 'shipped' ? 'selected' : '' ?>>จัดส่งแล้ว</option>
                <option value="cancelled" <?= $order['status'] === 'cancelled' ? 'selected' : '' ?>>ยกเลิก</option>
            </select>
        </div>

        <div class="flex justify-between mt-6">
            <a href="manage_orders.php" class="text-blue-600 hover:underline">← ย้อนกลับ</a>
            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">บันทึก</button>
        </div>
    </form>
</div>

</body>
</html>
