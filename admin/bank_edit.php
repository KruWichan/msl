<?php
// filepath: c:\AppServ\www\home\admin\bank_edit.php
require_once '../includes/db.php';

$id = $_GET['id'] ?? null;
if (!$id) {
    echo "ไม่พบข้อมูลบัญชีธนาคาร"; exit;
}

// ดึงข้อมูลบัญชีเดิม
$stmt = $pdo->prepare("SELECT * FROM bank_accounts WHERE id = ?");
$stmt->execute([$id]);
$bank = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$bank) {
    echo "ไม่พบข้อมูลบัญชีธนาคาร"; exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $account_name = trim($_POST['account_name'] ?? '');
    $account_number = trim($_POST['account_number'] ?? '');
    $bank_name = trim($_POST['bank_name'] ?? '');
    $promptpay_number = trim($_POST['promptpay_number'] ?? '');
    $promptpay_qr = $bank['promptpay_qr'];

    // อัปโหลด QR ใหม่ถ้ามี
    if (isset($_FILES['promptpay_qr']) && $_FILES['promptpay_qr']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
        $file_ext = pathinfo($_FILES['promptpay_qr']['name'], PATHINFO_EXTENSION);
        $new_file_name = uniqid('promptpay_') . '.' . $file_ext;
        $dest_path = $upload_dir . $new_file_name;
        if (move_uploaded_file($_FILES['promptpay_qr']['tmp_name'], $dest_path)) {
            $promptpay_qr = 'uploads/' . $new_file_name;
        }
    }

    $stmt = $pdo->prepare("UPDATE bank_accounts SET account_name=?, account_number=?, bank_name=?, promptpay_number=?, promptpay_qr=? WHERE id=?");
    $stmt->execute([$account_name, $account_number, $bank_name, $promptpay_number, $promptpay_qr, $id]);
    header("Location: setting.php?tab=bank");
    exit;
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>แก้ไขบัญชีธนาคาร</title>
    <link rel="stylesheet" href="https://cdn.tailwindcss.com">
</head>
<body class="bg-gray-100 py-8">
    <div class="max-w-lg mx-auto bg-white p-8 rounded shadow">
        <h2 class="text-2xl font-bold mb-6">แก้ไขบัญชีธนาคาร</h2>
        <form method="post" enctype="multipart/form-data" class="space-y-4">
            <div>
                <label class="block mb-1">ชื่อบัญชี</label>
                <input type="text" name="account_name" value="<?= htmlspecialchars($bank['account_name']) ?>" required class="w-full border rounded p-2">
            </div>
            <div>
                <label class="block mb-1">เลขที่บัญชี</label>
                <input type="text" name="account_number" value="<?= htmlspecialchars($bank['account_number']) ?>" required class="w-full border rounded p-2">
            </div>
            <div>
                <label class="block mb-1">ธนาคาร</label>
                <input type="text" name="bank_name" value="<?= htmlspecialchars($bank['bank_name']) ?>" required class="w-full border rounded p-2">
            </div>
            <div>
                <label class="block mb-1">พร้อมเพย์ (ถ้ามี)</label>
                <input type="text" name="promptpay_number" value="<?= htmlspecialchars($bank['promptpay_number']) ?>" class="w-full border rounded p-2">
            </div>
            <div>
                <label class="block mb-1">QR พร้อมเพย์ (ถ้ามี)</label>
                <?php if ($bank['promptpay_qr']): ?>
                    <div class="mb-2">
                        <img src="../<?= htmlspecialchars($bank['promptpay_qr']) ?>" alt="QR" class="max-h-32 rounded border">
                    </div>
                <?php endif; ?>
                <input type="file" name="promptpay_qr" accept="image/*" class="w-full">
            </div>
            <div class="flex gap-2">
                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded">บันทึก</button>
                <a href="setting.php?tab=bank" class="px-4 py-2 rounded border">ยกเลิก</a>
            </div>
        </form>
    </div>
</body>
</html>