<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();

if (empty($_SESSION['cart'])) {
    $_SESSION['message'] = ['type' => 'info', 'text' => 'ตะกร้าสินค้าของคุณว่างเปล่า กรุณาเพิ่มสินค้าก่อนดำเนินการ'];
    header('Location: cart.php');
    exit();
}

require 'includes/db.php';
require 'includes/order_notification.php';

$cart_items = $_SESSION['cart'] ?? [];
$total_products_amount = 0;
foreach ($cart_items as $item) {
    $total_products_amount += ($item['price'] * $item['quantity']);
}
$shipping_cost = 50.00;
$final_total = $total_products_amount + $shipping_cost;

$customer_name_val = $_POST['customer_name'] ?? '';
$customer_email_val = $_POST['email'] ?? '';
$customer_phone_val = $_POST['phone'] ?? '';
$shipping_name_val = $_POST['shipping_name'] ?? $_POST['customer_name'] ?? '';
$shipping_phone_val = $_POST['shipping_phone'] ?? $_POST['phone'] ?? '';
$shipping_address_val = $_POST['shipping_address'] ?? '';
$selected_province_id = $_POST['shipping_province_id'] ?? '';
$selected_district_id = $_POST['shipping_district_id'] ?? '';
$selected_subdistrict_id = $_POST['shipping_subdistrict_id'] ?? '';
$shipping_postcode_val = $_POST['shipping_postcode'] ?? '';
$payment_method_val = $_POST['payment_method'] ?? 'bank_transfer';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customer_name = trim($customer_name_val);
    $customer_email = trim($customer_email_val);
    $customer_phone = trim($customer_phone_val);
    $shipping_name = trim($shipping_name_val);
    $shipping_phone = trim($shipping_phone_val);
    $shipping_address_detail = trim($shipping_address_val);

    $province_name = '';
    $district_name = '';
    $subdistrict_name = '';
    $shipping_postcode = '';
    $errors = [];

    try {
        if (!empty($selected_province_id)) {
            $stmt = $pdo->prepare("SELECT name_th FROM province WHERE province_id = ?");
            $stmt->execute([$selected_province_id]);
            $province_name = $stmt->fetchColumn();
            if (!$province_name) $errors[] = "จังหวัดที่เลือกไม่ถูกต้อง";
        }
        if (!empty($selected_district_id)) {
            $stmt = $pdo->prepare("SELECT name_th FROM district WHERE district_id = ?");
            $stmt->execute([$selected_district_id]);
            $district_name = $stmt->fetchColumn();
            if (!$district_name) $errors[] = "อำเภอ/เขตที่เลือกไม่ถูกต้อง";
        }
        if (!empty($selected_subdistrict_id)) {
            $stmt = $pdo->prepare("SELECT name_th, zipcode FROM subdistrict WHERE subdistrict_id = ?");
            $stmt->execute([$selected_subdistrict_id]);
            $subdistrict_data = $stmt->fetch(PDO::FETCH_ASSOC);
            $subdistrict_name = $subdistrict_data['name_th'] ?? '';
            $shipping_postcode = $subdistrict_data['zipcode'] ?? $shipping_postcode_val;
            if (!$subdistrict_name || !$shipping_postcode) $errors[] = "ตำบล/แขวงที่เลือกไม่ถูกต้อง หรือไม่พบรหัสไปรษณีย์";
        } else {
            $shipping_postcode = trim($shipping_postcode_val);
        }
    } catch (PDOException $e) {
        error_log("Error fetching address names: " . $e->getMessage());
        $errors[] = "เกิดข้อผิดพลาดในการดึงข้อมูลที่อยู่: " . $e->getMessage();
    }

    $payment_method = trim($payment_method_val);

    if (empty($customer_name)) $errors[] = "กรุณากรอกชื่อ-นามสกุลผู้สั่งซื้อ";
    if (empty($customer_email) || !filter_var($customer_email, FILTER_VALIDATE_EMAIL)) $errors[] = "กรุณากรอกอีเมลที่ถูกต้อง";
    if (empty($customer_phone)) $errors[] = "กรุณากรอกเบอร์โทรศัพท์ผู้สั่งซื้อ";
    if (empty($shipping_name)) $errors[] = "กรุณากรอกชื่อ-นามสกุลผู้รับ";
    if (empty($shipping_phone)) $errors[] = "กรุณากรอกเบอร์โทรศัพท์ผู้รับ";
    if (empty($shipping_address_detail)) $errors[] = "กรุณากรอกบ้านเลขที่, ถนน, ซอย ฯลฯ";
    if (empty($selected_province_id) || empty($province_name)) $errors[] = "กรุณาเลือกจังหวัด";
    if (empty($selected_district_id) || empty($district_name)) $errors[] = "กรุณาเลือกอำเภอ/เขต";
    if (empty($selected_subdistrict_id) || empty($subdistrict_name)) $errors[] = "กรุณาเลือกตำบล/แขวง";
    if (empty($shipping_postcode) || !preg_match('/^[0-9]{5}$/', $shipping_postcode)) $errors[] = "กรุณากรอกรหัสไปรษณีย์ 5 หลักที่ถูกต้อง";
    if (empty($payment_method)) $errors[] = "กรุณาเลือกวิธีการชำระเงิน";

    if (empty($errors)) {
        try {
            $full_shipping_address =
                $shipping_address_detail .
                " ต." . $subdistrict_name .
                " อ." . $district_name .
                " จ." . $province_name;

            $pdo->beginTransaction();

            $stmt_order = $pdo->prepare("
                INSERT INTO orders (customer_name, email, phone,
                                    shipping_name, shipping_phone, shipping_address,
                                    shipping_city, shipping_province, shipping_postcode,
                                    total_amount, shipping_cost, payment_method, status, created_at)
                VALUES (:customer_name, :email, :phone,
                        :shipping_name, :shipping_phone, :shipping_address,
                        :shipping_city, :shipping_province, :shipping_postcode,
                        :total_amount, :shipping_cost, :payment_method, :status, NOW())
            ");
            $stmt_order->execute([
                'customer_name'    => $customer_name,
                'email'            => $customer_email,
                'phone'            => $customer_phone,
                'shipping_name'    => $shipping_name,
                'shipping_phone'   => $shipping_phone,
                'shipping_address' => $full_shipping_address,
                'shipping_city'    => $district_name,
                'shipping_province'=> $province_name,
                'shipping_postcode'=> $shipping_postcode,
                'total_amount'     => $final_total,
                'shipping_cost'    => $shipping_cost,
                'payment_method'   => $payment_method,
                'status'           => 'pending'
                ]);
            $order_id = $pdo->lastInsertId();

            $stmt_order_item = $pdo->prepare("
                INSERT INTO order_items (order_id, product_id, quantity, price)
                VALUES (:order_id, :product_id, :quantity, :price)
            ");

            foreach ($cart_items as $item) {
                $stmt_order_item->execute([
                    'order_id'   => $order_id,
                    'product_id' => $item['product_id'],
                    'quantity'   => $item['quantity'],
                    'price'      => $item['price']
                ]);
            }

            $pdo->commit();

            // เคลียร์ตะกร้าหลังสั่งซื้อสำเร็จ
            unset($_SESSION['cart']);

            // แจ้งเตือนแอดมินผ่าน LINE/Telegram ทันทีหลังสั่งซื้อ
            require_once __DIR__ . '/includes/notify_helper.php';
            $msg = "🛒 มีออเดอร์ใหม่ #{$order_id}\n"
                 . "ชื่อ: {$customer_name}\n"
                 . "ยอดรวม: " . number_format($final_total, 2) . " บาท\n"
                 . "วันที่: " . date('d/m/Y H:i') . "\n"
                 . "อีเมล: {$customer_email}\n"
                 . "เบอร์โทร: {$customer_phone}\n"
                 . "ที่อยู่: {$full_shipping_address} {$shipping_postcode}";
            sendLineNotify($msg, false);
            sendTelegramNotify($msg);

            require_once __DIR__ . '/includes/line_messaging_api.php';
            $adminLineUserId = $settings['admin_line_user_id'] ?? '';
            $channelAccessToken = $settings['line_channel_access_token'] ?? '';
            if ($adminLineUserId && $channelAccessToken) {
                sendLinePushMessage($channelAccessToken, $adminLineUserId, $msg);
            }

            $_SESSION['message'] = ['type' => 'success', 'text' => 'คำสั่งซื้อของคุณสำเร็จแล้ว!'];
            $_SESSION['last_order_id'] = $order_id;
            $_SESSION['last_payment_method'] = $payment_method;

            error_log('Redirecting to order_confirmation.php');
            header('Location: order_confirmation.php');
            exit();

        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Checkout database error: " . $e->getMessage());
            $_SESSION['message'] = ['type' => 'error', 'text' => 'เกิดข้อผิดพลาดในการบันทึกคำสั่งซื้อ (Database Error): ' . $e->getMessage()];
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Checkout general error: " . $e->getMessage());
            $_SESSION['message'] = ['type' => 'error', 'text' => 'เกิดข้อผิดพลาดในการทำรายการ: ' . $e->getMessage()];
        }
    } else {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'กรุณากรอกข้อมูลให้ครบถ้วนและถูกต้อง: ' . implode('<br>', $errors)];
    }
}

$provinces = [];
try {
    $stmt = $pdo->query("SELECT province_id AS id, name_th FROM province ORDER BY name_th ASC");
    $provinces = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching provinces: " . $e->getMessage());
    $_SESSION['message'] = ['type' => 'error', 'text' => 'ไม่สามารถโหลดข้อมูลจังหวัดได้: ' . $e->getMessage()];
}

$stmt = $pdo->query("SELECT * FROM bank_accounts WHERE is_active = 1 ORDER BY id ASC");
$bank_accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt_settings = $pdo->query("SELECT * FROM site_settings WHERE id = 2");
$settings = $stmt_settings->fetch(PDO::FETCH_ASSOC) ?: [];
$line_oa_id = $settings['line_oa_id'] ?? '';
$line_oa_id = ltrim($line_oa_id, '@');
?>
<!-- ...HTML ส่วนเดิมของคุณ... -->
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ชำระเงิน - ชื่อเว็บไซต์ของคุณ</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .cart-item-image {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 0.25rem;
        }
    </style>
</head>
<body class="bg-gray-100 font-sans leading-normal tracking-normal">

    <?php include 'header.php'; ?>

    <div class="container mx-auto px-4 py-8 mt-12">
        <h1 class="text-4xl font-extrabold text-gray-800 mb-8 text-center">ดำเนินการชำระเงิน</h1>

        <?php
        // แสดงข้อความแจ้งเตือน
        if (isset($_SESSION['message'])) {
            $msg_type = $_SESSION['message']['type'];
            $msg_text = $_SESSION['message']['text'];
            // เพิ่มแสดง Order ID ถ้ามี
            if ($msg_type == 'success' && isset($_SESSION['last_order_id'])) {
                $msg_text .= "<br><span class='block mt-2 text-lg font-bold text-blue-700'>หมายเลขคำสั่งซื้อของคุณคือ <span class='text-2xl text-green-700'>#" . htmlspecialchars($_SESSION['last_order_id']) . "</span></span>";
            }
            echo "<div class='p-4 mb-4 rounded-lg " . ($msg_type == 'success' ? 'bg-green-100 text-green-800' : ($msg_type == 'error' ? 'bg-red-100 text-red-800' : 'bg-blue-100 text-blue-800')) . "'>" . $msg_text . "</div>";
            unset($_SESSION['message']);
        }
        ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <div class="lg:col-span-1 bg-white rounded-lg shadow-xl p-6">
                <h2 class="text-2xl font-bold text-gray-800 mb-4">สรุปคำสั่งซื้อ</h2>
                <?php if (!empty($cart_items)): ?>
                    <?php foreach ($cart_items as $item): ?>
                        <div class="flex items-center justify-between mb-3 pb-3 border-b border-gray-200 last:border-b-0 last:pb-0">
                            <div class="flex items-center">
                                <img src="<?= htmlspecialchars($item['image'] ?? 'https://via.placeholder.com/50x50?text=No+Image') ?>" alt="<?= htmlspecialchars($item['name']) ?>" class="cart-item-image mr-3">
                                <div>
                                    <p class="text-sm font-medium text-gray-900"><?= htmlspecialchars($item['name']) ?></p>
                                    <p class="text-xs text-gray-500">฿<?= number_format($item['price'], 2) ?> x <?= $item['quantity'] ?></p>
                                </div>
                            </div>
                            <p class="text-sm font-medium text-gray-900">฿<?= number_format($item['price'] * $item['quantity'], 2) ?></p>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-gray-500">ไม่มีสินค้าในตะกร้า</p>
                <?php endif; ?>

                <div class="mt-4 pt-4 border-t border-gray-300">
                    <div class="flex justify-between text-sm text-gray-700 mb-2">
                        <span>ราคาสินค้าทั้งหมด:</span>
                        <span>฿<?= number_format($total_products_amount, 2) ?></span>
                    </div>
                    <div class="flex justify-between text-sm text-gray-700 mb-4">
                        <span>ค่าจัดส่ง:</span>
                        <span>฿<?= number_format($shipping_cost, 2) ?></span>
                    </div>
                    <div class="flex justify-between text-xl font-bold text-gray-900">
                        <span>รวมทั้งหมด:</span>
                        <span>฿<?= number_format($final_total, 2) ?></span>
                    </div>
                </div>
            </div>

            <div class="lg:col-span-2 bg-white rounded-lg shadow-xl p-6">
                <h2 class="text-2xl font-bold text-gray-800 mb-4">ข้อมูลจัดส่งและชำระเงิน</h2>
                <form action="checkout.php" method="POST" class="space-y-6">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-700 mb-3">ข้อมูลผู้สั่งซื้อ</h3>
                        <label for="customer_name" class="block text-sm font-medium text-gray-700">ชื่อ-นามสกุล:</label>
                        <input type="text" name="customer_name" id="customer_name"
                                class="mt-1 block w-full p-3 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                value="<?= htmlspecialchars($customer_name_val) ?>" required>
                    </div>
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700">อีเมล:</label>
                        <input type="email" name="email" id="email"
                                class="mt-1 block w-full p-3 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                value="<?= htmlspecialchars($customer_email_val) ?>" required>
                    </div>
                    <div>
                        <label for="phone" class="block text-sm font-medium text-gray-700">เบอร์โทรศัพท์:</label>
                        <input type="tel" name="phone" id="phone"
                                class="mt-1 block w-full p-3 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                value="<?= htmlspecialchars($customer_phone_val) ?>" required>
                    </div>

                    <div class="pt-4 border-t border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-700 mb-3">ข้อมูลผู้รับและที่อยู่จัดส่ง</h3>
                        <label for="shipping_name" class="block text-sm font-medium text-gray-700">ชื่อ-นามสกุล ผู้รับ:</label>
                        <input type="text" name="shipping_name" id="shipping_name"
                                class="mt-1 block w-full p-3 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                value="<?= htmlspecialchars($shipping_name_val) ?>" required>
                    </div>
                    <div>
                        <label for="shipping_phone" class="block text-sm font-medium text-gray-700">เบอร์โทรศัพท์ ผู้รับ:</label>
                        <input type="tel" name="shipping_phone" id="shipping_phone"
                                class="mt-1 block w-full p-3 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                value="<?= htmlspecialchars($shipping_phone_val) ?>" required>
                    </div>
                    <div>
                        <label for="shipping_address" class="block text-sm font-medium text-gray-700">บ้านเลขที่, ถนน, ซอย ฯลฯ:</label>
                        <textarea name="shipping_address" id="shipping_address" rows="3"
                                        class="mt-1 block w-full p-3 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                        required><?= htmlspecialchars($shipping_address_val) ?></textarea>
                    </div>

                    <div>
                        <label for="shipping_province_id" class="block text-sm font-medium text-gray-700">จังหวัด:</label>
                        <select name="shipping_province_id" id="shipping_province_id"
                                class="mt-1 block w-full p-3 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500" required>
                            <option value="">-- เลือกจังหวัด --</option>
                            <?php foreach ($provinces as $province): ?>
                                <option value="<?= htmlspecialchars($province['id']) ?>"
                                        <?= ($selected_province_id == $province['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($province['name_th']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label for="shipping_district_id" class="block text-sm font-medium text-gray-700">อำเภอ/เขต:</label>
                        <select name="shipping_district_id" id="shipping_district_id"
                                class="mt-1 block w-full p-3 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500" required>
                            <option value="">-- เลือกอำเภอ/เขต --</option>
                            </select>
                    </div>

                    <div>
                        <label for="shipping_subdistrict_id" class="block text-sm font-medium text-gray-700">ตำบล/แขวง:</label>
                        <select name="shipping_subdistrict_id" id="shipping_subdistrict_id"
                                class="mt-1 block w-full p-3 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500" required>
                            <option value="">-- เลือกตำบล/แขวง --</option>
                            </select>
                    </div>

                    <div>
                        <label for="shipping_postcode" class="block text-sm font-medium text-gray-700">รหัสไปรษณีย์:</label>
                        <input type="text" name="shipping_postcode" id="shipping_postcode"
                                class="mt-1 block w-full p-3 border border-gray-300 rounded-md shadow-sm bg-gray-50 cursor-not-allowed"
                                value="<?= htmlspecialchars($shipping_postcode_val) ?>" readonly required>
                    </div>

                    <div class="pt-4 border-t border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-700 mb-3">วิธีการชำระเงิน</h3>
                        <div class="space-y-3">
                            <div>
                                <label class="inline-flex items-center">
                                    <input type="radio" name="payment_method" value="bank_transfer" class="form-radio text-blue-600"
                                            <?= ($payment_method_val === 'bank_transfer') ? 'checked' : '' ?> required>
                                    <span class="ml-2 text-gray-700">โอนเงินผ่านธนาคาร</span>
                                </label>
                                <div class="mt-2 p-4 bg-blue-50 rounded-lg text-gray-700 border border-blue-200 payment-details" id="bank_transfer_details"
     style="<?= ($payment_method_val === 'bank_transfer') ? '' : 'display: none;' ?>">
    <?php foreach ($bank_accounts as $bank): ?>
        <div class="mb-2">
            <span><b>ธนาคาร:</b> <?= htmlspecialchars($bank['bank_name']) ?></span><br>
            <span><b>ชื่อบัญชี:</b> <?= htmlspecialchars($bank['account_name']) ?></span><br>
            <span><b>เลขที่บัญชี:</b> <?= htmlspecialchars($bank['account_number']) ?></span>
            <?php if (!empty($bank['promptpay_number'])): ?>
                <br><span><b>พร้อมเพย์:</b> <?= htmlspecialchars($bank['promptpay_number']) ?></span>
            <?php endif; ?>
            <?php if (!empty($bank['promptpay_qr'])): ?>
                <br><img src="<?= htmlspecialchars($bank['promptpay_qr']) ?>" alt="PromptPay QR" class="max-h-24 rounded border border-gray-300 shadow">
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
    <div class="mt-4 bg-yellow-50 border-l-4 border-yellow-400 p-3 rounded flex items-center space-x-2">
        <svg class="w-5 h-5 text-yellow-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M13 16h-1v-4h-1m1-4h.01"></path><circle cx="12" cy="12" r="10"></circle></svg>
        <span>
            กรุณาโอนเงินภายใน <b>24 ชั่วโมง</b> และส่งหลักฐานการโอนที่
            <a href="payment_notification.php" target="_blank" class="text-red-600 underline font-bold">แจ้งการชำระเงิน</a>
            เพื่อยืนยันคำสั่งซื้อ
        </span>
    </div>
</div>
                            </div>
                            <div>
                                <label class="inline-flex items-center">
                                    <input type="radio" name="payment_method" value="cod" class="form-radio text-blue-600"
                                            <?= ($payment_method_val === 'cod') ? 'checked' : '' ?> required>
                                    <span class="ml-2 text-gray-700">เก็บเงินปลายทาง (COD)</span>
                                </label>
                                <div class="mt-2 p-3 bg-blue-50 rounded-md text-sm text-gray-700 border border-blue-200 payment-details" id="cod_details"
                                            style="<?= ($payment_method_val === 'cod') ? '' : 'display: none;' ?>">
                                    <p>ชำระเงินกับพนักงานจัดส่งเมื่อได้รับสินค้า</p>
                                    <p class="mt-2 text-blue-700">อาจมีค่าธรรมเนียมเพิ่มเติมสำหรับบริการ COD</p>
                                </div>
                            </div>
                            </div>
                    </div>

                    <?php if ($line_oa_id): ?>
                        <div class="alert alert-info text-center mt-4 mb-4 p-4 rounded bg-blue-50 border border-blue-200">
                            <div class="text-xl mb-2">📲 เพิ่มเพื่อนกับร้านค้าทาง LINE เพื่อรับการแจ้งเตือนสถานะคำสั่งซื้อ!</div>
                            <div>
                                <a href="https://line.me/R/ti/p/@<?= htmlspecialchars($line_oa_id) ?>" target="_blank" class="inline-block bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600 transition">
                                    ➕ เพิ่มเพื่อน LINE
                                </a>
                            </div>
                            <div class="mt-2 text-gray-600 text-sm">
                                กดเพิ่มเพื่อนหรือเข้าสู่ระบบด้วย LINE เพื่อรับข่าวสาร โปรโมชั่น และติดตามสถานะออเดอร์ของคุณได้ทันที
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning text-center mt-4 mb-4 p-4 rounded bg-yellow-50 border border-yellow-200">
                            <div class="text-xl mb-2">⚠️ ยังไม่ได้ตั้งค่า LINE OA ID</div>
                            <div class="mt-2 text-gray-600 text-sm">
                                กรุณาตั้งค่า LINE OA ID ในหน้าแอดมินก่อน เพื่อให้ลูกค้าสามารถเพิ่มเพื่อน LINE ได้
                            </div>
                        </div>
                    <?php endif; ?>

                    <button type="submit" class="w-full bg-green-600 text-white text-lg py-3 rounded-md hover:bg-green-700 transition duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                        ยืนยันคำสั่งซื้อ
                    </button>
                </form>
            </div>
        </div>
    </div>

    <?php include 'footer.php'; ?>

    <script>
        // JavaScript สำหรับแสดง/ซ่อนรายละเอียดวิธีการชำระเงิน
        document.querySelectorAll('input[name="payment_method"]').forEach(radio => {
            radio.addEventListener('change', function() {
                document.querySelectorAll('.payment-details').forEach(detailDiv => {
                    detailDiv.style.display = 'none';
                });
                document.getElementById(this.value + '_details').style.display = 'block';
            });
        });

        // JavaScript สำหรับคัดลอกชื่อ-เบอร์ลูกค้าไปยังผู้รับ หากยังไม่ได้กรอก
        document.addEventListener('DOMContentLoaded', function() {
            const customerNameInput = document.getElementById('customer_name');
            const customerPhoneInput = document.getElementById('phone');
            const shippingNameInput = document.getElementById('shipping_name');
            const shippingPhoneInput = document.getElementById('shipping_phone');

            customerNameInput.addEventListener('blur', function() {
                if (shippingNameInput.value.trim() === '') {
                    shippingNameInput.value = customerNameInput.value.trim();
                }
            });

            customerPhoneInput.addEventListener('blur', function() {
                if (shippingPhoneInput.value.trim() === '') {
                    shippingPhoneInput.value = customerPhoneInput.value.trim();
                }
            });

            // --- JavaScript สำหรับ Dropdown ที่อยู่ ---
            const provinceSelect = document.getElementById('shipping_province_id');
            const districtSelect = document.getElementById('shipping_district_id');
            const subdistrictSelect = document.getElementById('shipping_subdistrict_id');
            const postcodeInput = document.getElementById('shipping_postcode');

            // ฟังก์ชันสำหรับโหลดข้อมูล Dropdown
            async function loadDropdown(selectElement, action, id = 0, selectedValue = null) {
                // Clear previous options and disable
                selectElement.innerHTML = `<option value="">-- โหลดข้อมูล... --</option>`;
                selectElement.disabled = true;

                try {
                    const response = await fetch(`get_address_data.php?action=${action}&id=${id}`);
                    const data = await response.json();

                    selectElement.innerHTML = `<option value="">-- เลือก${
                        action === 'districts' ? 'อำเภอ/เขต' :
                        action === 'subdistricts' ? 'ตำบล/แขวง' : ''
                    } --</option>`;
                    selectElement.disabled = false;

                    if (data && data.length > 0) {
                        data.forEach(item => {
                            const option = document.createElement('option');
                            option.value = item.id;
                            option.textContent = item.name_th;
                            if (selectedValue && selectedValue == item.id) {
                                option.selected = true;
                            }
                            selectElement.appendChild(option);
                        });
                    } else {
                        selectElement.innerHTML = `<option value="">-- ไม่พบข้อมูล --</option>`;
                    }
                } catch (error) {
                    console.error(`Error loading ${action}:`, error);
                    selectElement.innerHTML = `<option value="">-- เกิดข้อผิดพลาดในการโหลดข้อมูล --</option>`;
                    selectElement.disabled = false;
                }
            }

            // Event Listener สำหรับ Province Dropdown
            provinceSelect.addEventListener('change', function() {
                const provinceId = this.value;
                districtSelect.innerHTML = '<option value="">-- เลือกอำเภอ/เขต --</option>';
                subdistrictSelect.innerHTML = '<option value="">-- เลือกตำบล/แขวง --</option>';
                postcodeInput.value = '';

                if (provinceId) {
                    loadDropdown(districtSelect, 'districts', provinceId, '<?= $selected_district_id ?>');
                } else {
                    districtSelect.disabled = true;
                    subdistrictSelect.disabled = true;
                }
            });

            // Event Listener สำหรับ District Dropdown
            districtSelect.addEventListener('change', function() {
                const districtId = this.value;
                subdistrictSelect.innerHTML = '<option value="">-- เลือกตำบล/แขวง --</option>';
                postcodeInput.value = '';

                if (districtId) {
                    loadDropdown(subdistrictSelect, 'subdistricts', districtId, '<?= $selected_subdistrict_id ?>');
                } else {
                    subdistrictSelect.disabled = true;
                    subdistrictSelect.innerHTML = '<option value="">-- เลือกตำบล/แขวง --</option>'; // Reset for clarity
                }
            });

            // Event Listener สำหรับ Subdistrict Dropdown
            subdistrictSelect.addEventListener('change', async function() {
                const subdistrictId = this.value;
                postcodeInput.value = '';

                if (subdistrictId) {
                    try {
                        const response = await fetch(`get_address_data.php?action=zip_code&id=${subdistrictId}`);
                        const zipCode = await response.json();
                        if (zipCode) {
                            postcodeInput.value = zipCode;
                        } else {
                            postcodeInput.value = '';
                        }
                    } catch (error) {
                        console.error('Error loading zip code:', error);
                        // Optional: Display a user-friendly error message
                    }
                }
            });

            // โหลดข้อมูล Dropdown ครั้งแรกเมื่อหน้าเว็บโหลดเสร็จ (กรณีที่เคยเลือกไว้แล้วหรือเกิด error)
            // โหลดอำเภอถ้ามีจังหวัดที่เลือกไว้
            if (provinceSelect.value) {
                loadDropdown(districtSelect, 'districts', provinceSelect.value, '<?= $selected_district_id ?>').then(() => {
                    // โหลดตำบลถ้ามีอำเภอที่เลือกไว้ และอำเภอถูกโหลดเสร็จแล้ว
                    if (districtSelect.value) {
                        loadDropdown(subdistrictSelect, 'subdistricts', districtSelect.value, '<?= $selected_subdistrict_id ?>').then(() => {
                            // โหลดรหัสไปรษณีย์ถ้ามีตำบลที่เลือกไว้
                            if (subdistrictSelect.value && postcodeInput.value === '') {
                                subdistrictSelect.dispatchEvent(new Event('change')); // Trigger change to load postcode
                            }
                        });
                    }
                });
            } else {
                districtSelect.disabled = true;
                subdistrictSelect.disabled = true;
            }
        });
    </script>

</body>
</html>