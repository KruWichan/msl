<?php
// filepath: c:\AppServ\www\home\admin\bank_add.php
require_once '../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $account_name = trim($_POST['account_name'] ?? '');
    $account_number = trim($_POST['account_number'] ?? '');
    $bank_name = trim($_POST['bank_name'] ?? '');
    $promptpay_number = trim($_POST['promptpay_number'] ?? '');
    $promptpay_qr = null;

    // อัปโหลด QR พร้อมเพย์ (ถ้ามี)
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

    $stmt = $pdo->prepare("INSERT INTO bank_accounts (account_name, account_number, bank_name, promptpay_number, promptpay_qr) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$account_name, $account_number, $bank_name, $promptpay_number, $promptpay_qr]);
    header("Location: setting.php?tab=bank");
    exit;
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>เพิ่มบัญชีธนาคาร</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 text-gray-800">
    <?php require 'header.php'; ?>
    <div class="max-w-4xl mx-auto bg-white p-6 rounded shadow mt-8">
        <h2 class="text-2xl font-bold mb-6 text-center text-blue-700">เพิ่มบัญชีธนาคาร</h2>
        <form method="post" enctype="multipart/form-data" class="space-y-5">
            <div>
                <label class="block mb-1 font-medium text-gray-700">ชื่อบัญชี <span class="text-red-500">*</span></label>
                <input type="text" name="account_name" required class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-blue-400 focus:outline-none">
            </div>
            <div>
                <label class="block mb-1 font-medium text-gray-700">เลขที่บัญชี <span class="text-red-500">*</span></label>
                <input type="text" name="account_number" required class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-blue-400 focus:outline-none">
            </div>
            <div>
                <label class="block mb-1 font-medium text-gray-700">ธนาคาร <span class="text-red-500">*</span></label>
                <input type="text" name="bank_name" required class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-blue-400 focus:outline-none">
            </div>
            <div>
                <label class="block mb-1 font-medium text-gray-700">พร้อมเพย์ (ถ้ามี)</label>
                <input type="text" name="promptpay_number" class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-blue-400 focus:outline-none">
            </div>
            <div>
                <label class="block mb-1 font-medium text-gray-700">QR พร้อมเพย์ (ถ้ามี)</label>
                <input type="file" name="promptpay_qr" accept="image/*" class="w-full border border-gray-300 rounded-lg p-2 bg-gray-50">
                <span class="text-xs text-gray-500">ไฟล์ภาพเท่านั้น</span>
            </div>
            <div class="flex gap-3 justify-center pt-2">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg font-semibold shadow">บันทึก</button>
                <a href="setting.php?tab=bank" class="px-6 py-2 rounded-lg border border-gray-300 bg-gray-50 hover:bg-gray-100 text-gray-700 font-semibold">ยกเลิก</a>
            </div>
        </form>
    </div>
</body>
</html>