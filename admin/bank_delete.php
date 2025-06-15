<?php
// filepath: c:\AppServ\www\home\admin\bank_delete.php
require_once '../includes/db.php';

$id = $_GET['id'] ?? null;
if (!$id) {
    echo "ไม่พบข้อมูลบัญชีธนาคาร"; exit;
}

// ดึงข้อมูล QR เดิม (ถ้ามี)
$stmt = $pdo->prepare("SELECT promptpay_qr FROM bank_accounts WHERE id = ?");
$stmt->execute([$id]);
$bank = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$bank) {
    echo "ไม่พบข้อมูลบัญชีธนาคาร"; exit;
}

// ลบไฟล์ QR ออกจากโฟลเดอร์ (ถ้ามี)
if (!empty($bank['promptpay_qr']) && file_exists('../' . $bank['promptpay_qr'])) {
    @unlink('../' . $bank['promptpay_qr']);
}

// ลบข้อมูลในฐานข้อมูล
$stmt = $pdo->prepare("DELETE FROM bank_accounts WHERE id = ?");
$stmt->execute([$id]);

header("Location: setting.php?tab=bank");
exit;