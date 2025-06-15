<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

require '../includes/db.php';
require_once '../includes/line_messaging_api.php';
require_once '../includes/notify_helper.php'; // << เพิ่มบรรทัดนี้

// ดึงข้อมูลการสั่งซื้อทั้งหมด
$orders = $pdo->query("SELECT o.*, c.name AS customer_name, c.email, c.phone
FROM orders o
JOIN customers c ON o.customer_id = c.id
ORDER BY o.created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

// ดึงข้อมูลแจ้งชำระเงินทั้งหมด (แทนที่การดึง orders)
$payment_notifications = $pdo->query("
    SELECT pn.*, o.customer_name, o.total_amount, o.status AS order_status
    FROM payment_notifications pn
    JOIN orders o ON pn.order_id = o.id
    ORDER BY pn.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

$success = false;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['proof_of_payment'])) {
    $order_id = intval($_POST['order_id']);
    $customer_name = trim($_POST['customer_name']);
    $bank_name = trim($_POST['bank_name']);
    $transfer_amount = floatval($_POST['transfer_amount']);
    $transfer_date = $_POST['transfer_date'];
    $transfer_time = $_POST['transfer_time'];
    $proof = null;

    // Validate ข้อมูล
    if (!$order_id) $errors[] = "กรุณาเลือกคำสั่งซื้อ";
    if (!$customer_name) $errors[] = "กรุณาระบุชื่อผู้โอน";
    if (!$bank_name) $errors[] = "กรุณาระบุธนาคาร";
    if ($transfer_amount <= 0) $errors[] = "กรุณาระบุจำนวนเงินที่ถูกต้อง";
    if (!$transfer_date) $errors[] = "กรุณาระบุวันที่โอน";
    if (!$transfer_time) $errors[] = "กรุณาระบุเวลาโอน";

    // ตรวจสอบไฟล์อัปโหลด
    if ($_FILES['proof_of_payment']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['proof_of_payment']['name'], PATHINFO_EXTENSION));
        $allowed_ext = ['jpg', 'jpeg', 'png', 'gif'];
        if (!in_array($ext, $allowed_ext)) {
            $errors[] = "อนุญาตเฉพาะไฟล์รูปภาพเท่านั้น";
        }
        if ($_FILES['proof_of_payment']['size'] > 5 * 1024 * 1024) {
            $errors[] = "ขนาดไฟล์ต้องไม่เกิน 5MB";
        }
        if (empty($errors)) {
            $upload_dir = '../uploads/slips/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            $proof = uniqid('slip_') . '.' . $ext;
            move_uploaded_file($_FILES['proof_of_payment']['tmp_name'], $upload_dir . $proof);
        }
    } else {
        $errors[] = "กรุณาอัปโหลดไฟล์สลิป";
    }

    // ถ้าไม่มี error ให้บันทึกข้อมูลและแจ้งเตือน
    if (empty($errors)) {
        $stmt = $pdo->prepare("INSERT INTO payment_notifications (order_id, customer_name, bank_name, transfer_amount, transfer_date, transfer_time, proof_of_payment) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$order_id, $customer_name, $bank_name, $transfer_amount, $transfer_date, $transfer_time, $proof]);

        // ===== แจ้งเตือน LINE Messaging API =====
        $setting = $pdo->query("SELECT line_channel_access_token, admin_line_user_id FROM site_settings WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
        $line_token = $setting['line_channel_access_token'];
        $line_user_id = $setting['admin_line_user_id'];

        $notify_msg = "แจ้งชำระเงิน Order #{$order_id}\nชื่อ: {$customer_name}\nจำนวนเงิน: " . number_format($transfer_amount, 2) . " บาท\nธนาคาร: {$bank_name}\nวันที่: {$transfer_date} {$transfer_time}";

        if ($line_token && $line_user_id) {
            sendLinePushMessage($line_token, $line_user_id, $notify_msg);
        }

        // ===== แจ้งเตือน Telegram (ถ้ามีและเปิดใช้งาน) =====
        sendTelegramNotify($notify_msg);

        $success = true;
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>แจ้งชำระเงิน</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs" defer></script>
</head>
<body class="bg-gray-100 text-gray-800">
<?php require 'header.php'; ?>
<div class="max-w-3xl mx-auto bg-white p-6 rounded shadow">
    <h2 class="text-xl font-bold mb-4">แจ้งชำระเงิน</h2>
    <?php if ($success): ?>
        <div class="bg-green-100 text-green-700 p-3 rounded mb-4">บันทึกข้อมูลสำเร็จ</div>
    <?php elseif (!empty($errors)): ?>
        <div class="bg-red-100 text-red-700 p-3 rounded mb-4">
            <?php foreach ($errors as $err) echo htmlspecialchars($err) . "<br>"; ?>
        </div>
    <?php endif; ?>
    <form method="post" enctype="multipart/form-data" class="space-y-4">
        <div>
            <label class="block font-medium">เลือกคำสั่งซื้อ *</label>
            <select name="order_id" class="border rounded w-full px-3 py-2" required>
                <option value="">-- เลือก --</option>
                <?php foreach ($orders as $order): ?>
                    <option value="<?= $order['id'] ?>"><?= "Order #{$order['id']} - {$order['customer_name']}" ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label class="block font-medium">ชื่อผู้โอน *</label>
            <input type="text" name="customer_name" class="border rounded w-full px-3 py-2" required />
        </div>

        <div>
            <label class="block font-medium">ชื่อธนาคาร *</label>
            <input type="text" name="bank_name" class="border rounded w-full px-3 py-2" required />
        </div>

        <div>
            <label class="block font-medium">จำนวนเงินที่โอน *</label>
            <input type="number" step="0.01" name="transfer_amount" class="border rounded w-full px-3 py-2" required />
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block font-medium">วันที่โอน *</label>
                <input type="date" name="transfer_date" class="border rounded w-full px-3 py-2" required />
            </div>
            <div>
                <label class="block font-medium">เวลาที่โอน *</label>
                <input type="time" name="transfer_time" class="border rounded w-full px-3 py-2" required />
            </div>
        </div>

        <div>
            <label class="block font-medium">อัปโหลดสลิปการโอนเงิน *</label>
            <input type="file" name="proof_of_payment" accept="image/*" class="border rounded w-full px-3 py-2" required />
        </div>

        <div class="flex justify-between mt-6">
            <a href="manage_orders.php" class="bg-gray-400 hover:bg-gray-500 text-white px-4 py-2 rounded">ย้อนกลับ</a>
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded">บันทึก</button>
        </div>
    </form>

    <h2 class="text-xl font-semibold mt-8 mb-4">รายการแจ้งชำระเงินทั้งหมด</h2>
    <table class="min-w-full bg-white border border-gray-200 rounded-lg overflow-hidden">
        <thead class="bg-gray-100">
            <tr>
                <th class="py-3 px-4 text-left text-gray-600">#</th>
                <th class="py-3 px-4 text-left text-gray-600">หมายเลขคำสั่งซื้อ</th>
                <th class="py-3 px-4 text-left text-gray-600">ชื่อผู้ชำระเงิน</th>
                <th class="py-3 px-4 text-left text-gray-600">ธนาคาร</th>
                <th class="py-3 px-4 text-left text-gray-600">จำนวนเงิน</th>
                <th class="py-3 px-4 text-left text-gray-600">วันที่โอน</th>
                <th class="py-3 px-4 text-left text-gray-600">เวลาที่โอน</th>
                <th class="py-3 px-4 text-left text-gray-600">สถานะ</th>
                <th class="py-3 px-4 text-left text-gray-600">หลักฐาน</th>
                <th class="py-3 px-4 text-left text-gray-600">จัดการ</th>
            </tr>
        </thead>
        <tbody class="text-gray-700">
            <?php foreach ($payment_notifications as $pn): ?>
                <tr>
                    <td class="py-3 px-4 border-b"><?= $pn['id'] ?></td>
                    <td class="py-3 px-4 border-b">#<?= htmlspecialchars($pn['order_id']) ?></td>
                    <td class="py-3 px-4 border-b"><?= htmlspecialchars($pn['customer_name']) ?></td>
                    <td class="py-3 px-4 border-b"><?= htmlspecialchars($pn['bank_name']) ?></td>
                    <td class="py-3 px-4 border-b"><?= number_format($pn['transfer_amount'], 2) ?> บาท</td>
                    <td class="py-3 px-4 border-b"><?= date('d/m/Y', strtotime($pn['transfer_date'])) ?></td>
                    <td class="py-3 px-4 border-b"><?= htmlspecialchars($pn['transfer_time']) ?></td>
                    <td class="py-3 px-4 border-b">
                        <?php
                        if ($pn['status'] === 'pending') {
                            echo '<span class="text-yellow-600">รอการตรวจสอบ</span>';
                        } elseif ($pn['status'] === 'approved') {
                            echo '<span class="text-green-600">อนุมัติแล้ว</span>';
                        } else {
                            echo '<span class="text-red-600">ไม่อนุมัติ</span>';
                        }
                        ?>
                    </td>
                    <td class="py-3 px-4 border-b">
                        <?php if ($pn['proof_of_payment']): ?>
                            <a href="../<?= htmlspecialchars($pn['proof_of_payment']) ?>" target="_blank" class="text-blue-600 hover:underline">ดูสลิป</a>
                        <?php else: ?>
                            ไม่มี
                        <?php endif; ?>
                    </td>
                    <td class="py-3 px-4 border-b">
                        <a href="view_payment_notification.php?id=<?= $pn['id'] ?>" class="text-blue-600 hover:underline">ดูรายละเอียด</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
</body>
</html>