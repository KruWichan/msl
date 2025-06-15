<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}
require '../includes/db.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$product_id = isset($_GET['product_id']) ? intval($_GET['product_id']) : 0;

if ($id > 0 && $product_id > 0) {
    $stmt = $pdo->prepare("SELECT filename FROM product_images WHERE id = ? AND product_id = ?");
    $stmt->execute([$id, $product_id]);
    $img = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($img) {
        $file = '../uploads/products/' . $img['filename'];
        if (file_exists($file)) unlink($file);
        $pdo->prepare("DELETE FROM product_images WHERE id = ?")->execute([$id]);
    }
}
header("Location: edit_product.php?id=" . $product_id);
exit;

// ฟังก์ชันบันทึก log การเปลี่ยนสถานะ
function logOrderStatus($pdo, $order_id, $old_status, $new_status, $user_id, $note = null) {
    $stmt = $pdo->prepare("INSERT INTO order_status_logs (order_id, old_status, new_status, changed_by, note) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$order_id, $old_status, $new_status, $user_id, $note]);
}

// ตัวอย่างการเปลี่ยนสถานะ (ยกเลิก/คืนเงิน/อัปเดต tracking)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['cancel_order'])) {
        $old_status = $order['status'];
        $pdo->prepare("UPDATE orders SET status = 'cancelled' WHERE id = ?")->execute([$order_id]);
        logOrderStatus($pdo, $order_id, $old_status, 'cancelled', $_SESSION['user']['id'], 'Admin cancelled order');
    }
    if (isset($_POST['refund_order'])) {
        $old_status = $order['status'];
        $pdo->prepare("UPDATE orders SET status = 'refunded' WHERE id = ?")->execute([$order_id]);
        logOrderStatus($pdo, $order_id, $old_status, 'refunded', $_SESSION['user']['id'], 'Admin refunded order');
    }
    if (isset($_POST['tracking_number'])) {
        $tracking = trim($_POST['tracking_number']);
        $pdo->prepare("INSERT INTO shippings (order_id, tracking_number) VALUES (?, ?) ON DUPLICATE KEY UPDATE tracking_number = VALUES(tracking_number)")->execute([$order_id, $tracking]);
        $old_status = $order['status'];
        $pdo->prepare("UPDATE orders SET status = 'shipped' WHERE id = ?")->execute([$order_id]);
        logOrderStatus($pdo, $order_id, $old_status, 'shipped', $_SESSION['user']['id'], 'Tracking updated');
    }
}

// ดึง log สถานะ
$stmt = $pdo->prepare("SELECT l.*, u.username FROM order_status_logs l LEFT JOIN users u ON l.changed_by = u.id WHERE l.order_id = ? ORDER BY l.changed_at DESC");
$stmt->execute([$order_id]);
$statusLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- ฟอร์มอัปโหลดสลิป -->
<form method="post" enctype="multipart/form-data">
    <label>อัปโหลดสลิปโอนเงิน:</label>
    <input type="file" name="proof_of_payment" accept="image/*" required>
    <button type="submit" name="upload_slip" class="bg-blue-500 text-white px-3 py-1 rounded">อัปโหลด</button>
</form>

<!-- ฟอร์มกรอก Tracking Number -->
<form method="post" class="mt-4">
    <label>Tracking Number:</label>
    <input type="text" name="tracking_number" value="<?= htmlspecialchars($current_tracking ?? '') ?>" class="border rounded px-2 py-1">
    <button type="submit" class="bg-green-500 text-white px-3 py-1 rounded">บันทึกเลขพัสดุ</button>
</form>

<!-- ปุ่มยกเลิก/คืนเงิน -->
<form method="post" class="mt-4 flex gap-2">
    <button type="submit" name="cancel_order" class="bg-red-500 text-white px-3 py-1 rounded" onclick="return confirm('ยืนยันยกเลิกออเดอร์?')">ยกเลิกออเดอร์</button>
    <button type="submit" name="refund_order" class="bg-yellow-500 text-white px-3 py-1 rounded" onclick="return confirm('ยืนยันคืนเงิน?')">คืนเงิน</button>
</form>

<!-- แสดงประวัติการเปลี่ยนสถานะ -->
<h2 class="text-lg font-bold mt-6 mb-2">ประวัติการเปลี่ยนสถานะ</h2>
<table class="w-full table-auto border-collapse mb-4">
    <thead>
        <tr>
            <th class="border px-2 py-1">วันที่</th>
            <th class="border px-2 py-1">สถานะเดิม</th>
            <th class="border px-2 py-1">สถานะใหม่</th>
            <th class="border px-2 py-1">โดย</th>
            <th class="border px-2 py-1">หมายเหตุ</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($statusLogs as $log): ?>
        <tr>
            <td class="border px-2 py-1"><?= htmlspecialchars($log['changed_at']) ?></td>
            <td class="border px-2 py-1"><?= htmlspecialchars($log['old_status']) ?></td>
            <td class="border px-2 py-1"><?= htmlspecialchars($log['new_status']) ?></td>
            <td class="border px-2 py-1"><?= htmlspecialchars($log['username']) ?></td>
            <td class="border px-2 py-1"><?= htmlspecialchars($log['note']) ?></td>
        </tr>
        <?php endforeach ?>
    </tbody>
</table>