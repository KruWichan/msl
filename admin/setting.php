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

// ดึงค่าปัจจุบัน
$stmt_settings = $pdo->query("SELECT * FROM site_settings WHERE id = 2");
$settings = $stmt_settings->fetch(PDO::FETCH_ASSOC);
if (!$settings) {
    die('ไม่พบข้อมูล site_settings ในฐานข้อมูล');
}

// ดึงรายการ Banner Grids ทั้งหมดสำหรับ dropdown
$stmt_grids = $pdo->query("SELECT id, title FROM banner_grids ORDER BY title ASC"); 
$banner_grids = $stmt_grids->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ทั่วไป
    $site_name = $_POST['site_name'];
    $primary_color = $_POST['primary_color'];
    $secondary_color = $_POST['secondary_color'];
    $font_family = $_POST['font_family'];
    $logo = $_POST['current_logo']; 
    $product_display_type = $_POST['product_display_type'];
    $featured_tag_ids = isset($_POST['featured_tag_ids']) ? implode(',', $_POST['featured_tag_ids']) : NULL;
    $homepage_banner_grid_id = !empty($_POST['homepage_banner_grid_id']) ? (int)$_POST['homepage_banner_grid_id'] : NULL;
    
    // การแจ้งเตือน LINE Official Account (Messaging API)
    $line_oa_channel_access_token = $_POST['line_oa_channel_access_token'] ?? ''; 
    $line_oa_channel_secret = $_POST['line_oa_channel_secret'] ?? '';
    $line_oa_id = $_POST['line_oa_id'] ?? '';
    $admin_line_user_id = $_POST['admin_line_user_id'] ?? '';
    $enable_line_notify = isset($_POST['enable_line_notify']) ? 1 : 0;
    $enable_line_notify_payment = isset($_POST['enable_line_notify_payment']) ? 1 : 0;
    $enable_line_notify_new_order = isset($_POST['enable_line_notify_new_order']) ? 1 : 0;
    $enable_line_notify_customer_status = isset($_POST['enable_line_notify_customer_status']) ? 1 : 0;

    // การแจ้งเตือน LINE Notify
    $line_notify_token = $_POST['line_notify_token'] ?? ''; // เพิ่มนี้

    // การแจ้งเตือน Telegram
    $telegram_bot_token = trim($_POST['telegram_bot_token'] ?? '');
    $telegram_chat_id = trim($_POST['telegram_chat_id'] ?? '');
    $enable_telegram_notify = isset($_POST['enable_telegram_notify']) ? 1 : 0;

    // Validate Telegram
    if ($enable_telegram_notify && (empty($telegram_bot_token) || empty($telegram_chat_id))) {
        echo "<div class='bg-red-100 border border-red-400 text-red-700 p-3 mb-4 rounded'>❌ กรุณากรอก Telegram Bot Token และ Chat ID ให้ครบถ้วน</div>";
        exit;
    }

    // การชำระเงิน
    $payment_methods = isset($_POST['payment_methods']) ? implode(',', $_POST['payment_methods']) : '';
    $line_channel_id = $_POST['line_channel_id'] ?? '';

    // จัดการโลโก้
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        $file_ext = pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
        $new_file_name = uniqid('logo_') . '.' . $file_ext;
        $dest_path = $upload_dir . $new_file_name;

        if (move_uploaded_file($_FILES['logo']['tmp_name'], $dest_path)) {
            if ($logo && file_exists('../' . $logo) && strpos($logo, 'default_logo.png') === false) {
                unlink('../' . $logo);
            }
            $logo = 'uploads/' . $new_file_name; 
        } else {
            echo "<div class='bg-red-100 border border-red-400 text-red-700 p-3 mb-4 rounded'>❌ ข้อผิดพลาดในการอัปโหลดโลโก้.</div>";
        }
    }

    // อัปเดต SQL
    $stmt = $pdo->prepare("UPDATE site_settings SET 
        site_name = :site_name, 
        primary_color = :primary_color, 
        secondary_color = :secondary_color, 
        font_family = :font_family, 
        logo = :logo, 
        product_display_type = :product_display_type, 
        featured_tag_ids = :featured_tag_ids, 
        homepage_banner_grid_id = :homepage_banner_grid_id,
        line_oa_channel_access_token = :line_oa_channel_access_token,
        line_oa_channel_secret = :line_oa_channel_secret,
        admin_line_user_id = :admin_line_user_id,
        enable_line_notify_new_order = :enable_line_notify_new_order,
        line_template_new_order = :line_template_new_order,
        line_notify_token = :line_notify_token,                       
        enable_line_notify_customer_status = :enable_line_notify_customer_status, 
        telegram_bot_token = :telegram_bot_token,
        telegram_chat_id = :telegram_chat_id,
        enable_telegram_notify = :enable_telegram_notify,
        payment_methods = :payment_methods,
        enable_line_notify = :enable_line_notify,
        enable_line_notify_payment = :enable_line_notify_payment,
        line_channel_id = :line_channel_id
        WHERE id = 2
    ");
    $stmt->execute([
        'site_name' => $site_name,
        'primary_color' => $primary_color,
        'secondary_color' => $secondary_color,
        'font_family' => $font_family,
        'logo' => $logo,
        'product_display_type' => $product_display_type,
        'featured_tag_ids' => $featured_tag_ids,
        'homepage_banner_grid_id' => $homepage_banner_grid_id,
        'line_oa_channel_access_token' => $line_oa_channel_access_token,
        'line_oa_channel_secret' => $line_oa_channel_secret,
        'admin_line_user_id' => $admin_line_user_id,
        'enable_line_notify_new_order' => $enable_line_notify_new_order,
        'line_template_new_order' => $line_template_new_order,
        'line_notify_token' => $line_notify_token,                       
        'enable_line_notify_customer_status' => $enable_line_notify_customer_status, 
        'telegram_bot_token' => $telegram_bot_token,
        'telegram_chat_id' => $telegram_chat_id,
        'enable_telegram_notify' => $enable_telegram_notify,
        'payment_methods' => $payment_methods,
        'enable_line_notify' => $enable_line_notify,
        'enable_line_notify_payment' => $enable_line_notify_payment,
        'line_channel_id' => $line_channel_id
    ]);
    header("Location: setting.php?success=1");
    exit;
}

// ดึงข้อมูลที่จำเป็น
$stmt_tags = $pdo->query("SELECT id, name FROM product_tags ORDER BY name ASC");
$all_tags = $stmt_tags->fetchAll(PDO::FETCH_ASSOC);
$selected_featured_tags = $settings['featured_tag_ids'] ? explode(',', $settings['featured_tag_ids']) : [];

// ตัวเลือกการชำระเงิน
$payment_options = [
    'bank_transfer' => 'โอนเงินผ่านธนาคาร',
    'paypal' => 'PayPal',
    'credit_card' => 'บัตรเครดิต'
];
$selected_payment_methods = isset($settings['payment_methods']) && $settings['payment_methods'] !== '' 
    ? explode(',', $settings['payment_methods']) 
    : [];

$stmt_banks = $pdo->query("SELECT * FROM bank_accounts ORDER BY id ASC");
$bank_accounts = $stmt_banks->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>ตั้งค่าเว็บไซต์</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <style>
        body { font-family: 'Kanit', 'Segoe UI Emoji', 'Noto Color Emoji', sans-serif; }
        .tab-content { display: none; } /* ซ่อนทุก tab content เริ่มต้น */
        .tab-content.active { display: block; } /* แสดง tab content ที่มี class active */
    </style>
</head>
<body class="bg-gray-100">
<?php require 'header.php'; ?>

<div class="max-w-4xl mx-auto mt-8 p-6 bg-white rounded shadow">
    <h1 class="text-2xl font-bold mb-4">⚙️ ตั้งค่าเว็บไซต์</h1>

    <?php if (isset($_GET['success'])): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 p-3 mb-4 rounded">✅ บันทึกการตั้งค่าเรียบร้อยแล้ว</div>
    <?php endif; ?>

    <div class="flex space-x-2 border-b mb-6">
        <button type="button" class="tab-btn px-4 py-2 border-b-2 font-semibold text-gray-600 border-transparent hover:text-blue-600 hover:border-blue-600 active" data-tab="general">ตั้งค่าพื้นฐาน</button>
        <button type="button" class="tab-btn px-4 py-2 border-b-2 font-semibold text-gray-600 border-transparent hover:text-blue-600 hover:border-blue-600" data-tab="notification">การแจ้งเตือน</button>
        <button type="button" class="tab-btn px-4 py-2 border-b-2 font-semibold text-gray-600 border-transparent hover:text-blue-600 hover:border-blue-600" data-tab="payment">การชำระเงิน</button>
    </div>

    <form method="post" enctype="multipart/form-data" class="space-y-4">
        <div id="general" class="tab-content active">
            <h2 class="text-xl font-bold mb-2">ตั้งค่าทั่วไป</h2>
            <div>
                <label class="block font-medium">ชื่อเว็บไซต์</label>
                <input type="text" name="site_name" value="<?= htmlspecialchars($settings['site_name'] ?? '') ?>" required class="w-full border rounded p-2">
            </div>

            <div>
                <label class="block font-medium">โลโก้ปัจจุบัน</label>
                <?php if (!empty($settings['logo'])): ?>
                    <img src="../<?= htmlspecialchars($settings['logo']) ?>" alt="Logo" class="max-h-20 mb-2">
                    <input type="hidden" name="current_logo" value="<?= htmlspecialchars($settings['logo']) ?>">
                <?php endif; ?>
                <input type="file" name="logo" accept="image/*" class="w-full border rounded p-2">
                <small class="text-gray-500">เลือกไฟล์ใหม่เพื่อเปลี่ยนโลโก้</small>
            </div>

            <div>
                <label class="block font-medium">สีหลัก (Primary Color)</label>
                <input type="color" name="primary_color" value="<?= htmlspecialchars($settings['primary_color'] ?? '#3490dc') ?>" class="w-20 h-10 border rounded p-1">
                <small class="text-gray-500">เลือกสีหลักของเว็บไซต์</small>
            </div>
            <div>
                <label class="block font-medium">สีรอง (Secondary Color)</label>
                <input type="color" name="secondary_color" value="<?= htmlspecialchars($settings['secondary_color'] ?? '#ffffff') ?>" class="w-20 h-10 border rounded p-1">
                <small class="text-gray-500">เลือกสีรองของเว็บไซต์ (เช่น สีพื้นหลัง, สีปุ่มรอง)</small>
            </div>

            <div>
                <label class="block font-medium">Font Family ของเว็บไซต์</label>
                <select name="font_family" class="w-full border rounded p-2">
                    <option value="sans-serif" <?= ($settings['font_family'] ?? 'sans-serif') == 'sans-serif' ? 'selected' : '' ?>>Default Sans-serif</option>
                    <option value="'Anuphan', sans-serif" <?= ($settings['font_family'] ?? '') == "'Anuphan', sans-serif" ? 'selected' : '' ?>>Anuphan</option>
                    <option value="'Kanit', sans-serif" <?= ($settings['font_family'] ?? '') == "'Kanit', sans-serif" ? 'selected' : '' ?>>Kanit</option>
                    <option value="'Prompt', sans-serif" <?= ($settings['font_family'] ?? '') == "'Prompt', sans-serif" ? 'selected' : '' ?>>Prompt</option>
                    <option value="'Sarabun', sans-serif" <?= ($settings['font_family'] ?? '') == "'Sarabun', sans-serif" ? 'selected' : '' ?>>Sarabun</option>
                    <option value="'Chakra Petch', sans-serif" <?= ($settings['font_family'] ?? '') == "'Chakra Petch', sans-serif" ? 'selected' : '' ?>>Chakra Petch</option>
                    <option value="'IBM Plex Sans Thai', sans-serif" <?= ($settings['font_family'] ?? '') == "'IBM Plex Sans Thai', sans-serif" ? 'selected' : '' ?>>IBM Plex Sans Thai</option>
                </select>
                <small class="text-gray-500">เลือก Font หลักสำหรับทั้งเว็บไซต์</small>
            </div>
            
            <div>
                <label class="block font-medium">ประเภทการแสดงสินค้าในหน้าแรก</label>
                <select name="product_display_type" class="w-full border rounded p-2">
                    <option value="all" <?= ($settings['product_display_type'] ?? 'all') == 'all' ? 'selected' : '' ?>>แสดงสินค้าทั้งหมด</option>
                    <option value="featured" <?= ($settings['product_display_type'] ?? '') == 'featured' ? 'selected' : '' ?>>แสดงเฉพาะสินค้าที่มี Tag ที่เลือก</option>
                </select>
            </div>

            <div id="featured_tags_section" class="py-2 <?= ($settings['product_display_type'] ?? 'all') == 'featured' ? '' : 'hidden' ?>">
                <label class="block font-medium">เลือก Tag สินค้าเด่น (สามารถเลือกได้หลาย Tag)</label>
                <select name="featured_tag_ids[]" id="featured_tag_ids" multiple="multiple" class="w-full border rounded p-2">
                    <?php foreach ($all_tags as $tag): ?>
                        <option value="<?= $tag['id'] ?>" <?= in_array($tag['id'], $selected_featured_tags) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($tag['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div>
                <label class="block font-medium">เลือกชุดแบนเนอร์หลักสำหรับหน้าแรก</label>
                <select name="homepage_banner_grid_id" class="w-full border rounded p-2">
                    <option value="">-- ไม่แสดงชุดแบนเนอร์ --</option>
                    <?php foreach ($banner_grids as $grid_option): ?>
                        <option value="<?= $grid_option['id'] ?>" 
                                <?= ($settings['homepage_banner_grid_id'] ?? null) == $grid_option['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($grid_option['title']) ?> (ID: <?= $grid_option['id'] ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <small class="text-gray-500">เลือก Grid ที่จะแสดงบนส่วนบนของหน้าแรก</small>
            </div>
        </div>

        <!-- Notification Tab UI (แบ่งกรอบสวยงาม ว้าว) -->
        <div id="notification" class="tab-content">
            <h2 class="text-2xl font-bold mb-4 flex items-center gap-2">
                <svg class="w-7 h-7 text-blue-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M13 16h-1v-4h-1m1-4h.01M12 20a8 8 0 100-16 8 8 0 000 16z"/></svg>
                ตั้งค่าการแจ้งเตือน
            </h2>
            <div class="grid md:grid-cols-2 gap-6">

                <!-- LINE OA (Messaging API) -->
                <div class="bg-gradient-to-br from-blue-50 to-blue-100 border border-blue-200 rounded-lg p-6 shadow">
                    <div class="flex items-center gap-2 mb-4">
                        <svg class="w-6 h-6 text-blue-600" fill="currentColor" viewBox="0 0 24 24"><path d="M19.615 3.184A11.954 11.954 0 0012 1C6.477 1 2 5.477 2 11c0 2.387.835 4.584 2.385 6.384L2 23l5.616-2.385A11.954 11.954 0 0012 21c5.523 0 10-4.477 10-10 0-2.387-.835-4.584-2.385-6.384z"/></svg>
                        <h3 class="text-lg font-semibold text-blue-700">LINE Official Account (Messaging API)</h3>
                    </div>
                    <div class="space-y-3">
                        <div>
                            <label class="block font-medium">Channel Access Token</label>
                            <input type="text" name="line_oa_channel_access_token" value="<?= htmlspecialchars($settings['line_oa_channel_access_token'] ?? '') ?>" class="w-full border rounded p-2" autocomplete="off">
                        </div>
                        <div>
                            <label class="block font-medium">Channel Secret</label>
                            <input type="text" name="line_oa_channel_secret" value="<?= htmlspecialchars($settings['line_oa_channel_secret'] ?? '') ?>" class="w-full border rounded p-2" autocomplete="off">
                        </div>
                        <div>
                            <label class="block font-medium">LINE OA ID</label>
                            <input type="text" name="line_oa_id" value="<?= htmlspecialchars($settings['line_oa_id'] ?? '') ?>" class="w-full border rounded p-2" autocomplete="off">
                        </div>
                        <div>
                            <label class="block font-medium">Admin LINE User ID</label>
                            <input type="text" name="admin_line_user_id" value="<?= htmlspecialchars($settings['admin_line_user_id'] ?? '') ?>" class="w-full border rounded p-2" autocomplete="off">
                            <small class="text-gray-500">User ID ของแอดมินที่ต้องการรับแจ้งเตือน</small>
                        </div>
                        <div class="pt-2 space-y-2">
                            <label class="inline-flex items-center">
                                <input type="checkbox" name="enable_line_notify" value="1" <?= !empty($settings['enable_line_notify']) ? 'checked' : '' ?> class="mr-2 accent-blue-600 scale-125">
                                <span class="ml-2">แจ้งเตือนแอดมินเมื่อมีออเดอร์ใหม่</span>
                            </label>
                            <label class="inline-flex items-center">
                                <input type="checkbox" name="enable_line_notify_payment" value="1" <?= !empty($settings['enable_line_notify_payment']) ? 'checked' : '' ?> class="mr-2 accent-blue-600 scale-125">
                                <span class="ml-2">แจ้งเตือนแอดมินเมื่อมีการแจ้งชำระเงิน</span>
                            </label>
                            <label class="inline-flex items-center">
                                <input type="checkbox" name="enable_line_notify_new_order" value="1" <?= !empty($settings['enable_line_notify_new_order']) ? 'checked' : '' ?> class="mr-2 accent-blue-600 scale-125">
                                <span class="ml-2">แจ้งเตือนคำสั่งซื้อใหม่ (โหมดพิเศษ)</span>
                            </label>
                            <label class="inline-flex items-center">
                                <input type="checkbox" name="enable_line_notify_customer_status" value="1" <?= !empty($settings['enable_line_notify_customer_status']) ? 'checked' : '' ?> class="mr-2 accent-blue-600 scale-125">
                                <span class="ml-2">แจ้งเตือนสถานะคำสั่งซื้อ (ถึงลูกค้า)</span>
                            </label>
                        </div>
                    </div>
                    <div class="mt-4 text-xs text-gray-500">
                        <span class="inline-block bg-blue-200 text-blue-800 px-2 py-1 rounded">LINE OA</span>
                        ใช้สำหรับแจ้งเตือนผ่านบอท LINE OA ไปยังแอดมินและลูกค้า
                    </div>
                </div>

                <!-- LINE Notify -->
                <div class="bg-gradient-to-br from-green-50 to-green-100 border border-green-200 rounded-lg p-6 shadow">
                    <div class="flex items-center gap-2 mb-4">
                        <svg class="w-6 h-6 text-green-600" fill="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M8 12l2 2 4-4" stroke="#fff" stroke-width="2" fill="none"/></svg>
                        <h3 class="text-lg font-semibold text-green-700">LINE Notify</h3>
                    </div>
                    <div class="mb-2">
                        <label class="block font-medium">LINE Notify Token</label>
                        <input type="text" name="line_notify_token" value="<?= htmlspecialchars($settings['line_notify_token'] ?? '') ?>" class="w-full border rounded p-2">
                        <small class="text-gray-500">Token จาก <a href="https://notify-bot.line.me/my/" target="_blank" class="text-blue-600 underline">notify-bot.line.me</a></small>
                    </div>
                    <div class="mt-4 text-xs text-gray-500">
                        <span class="inline-block bg-green-200 text-green-800 px-2 py-1 rounded">LINE Notify</span>
                        แจ้งเตือนเข้ากลุ่มหรือแชทส่วนตัวผ่าน LINE Notify Bot
                    </div>
                </div>

                <!-- Telegram -->
                <div class="bg-gradient-to-br from-purple-50 to-purple-100 border border-purple-200 rounded-lg p-6 shadow col-span-2">
                    <div class="flex items-center gap-2 mb-4">
                        <svg class="w-6 h-6 text-purple-600" fill="currentColor" viewBox="0 0 24 24"><path d="M21.426 2.574a2 2 0 00-2.828 0L2.574 18.598a2 2 0 002.828 2.828l16.024-16.024a2 2 0 000-2.828z"/></svg>
                        <h3 class="text-lg font-semibold text-purple-700">Telegram Notification</h3>
                    </div>
                    <div class="grid md:grid-cols-2 gap-4">
                        <div>
                            <label class="block font-medium">Telegram Bot Token</label>
                            <input type="text" name="telegram_bot_token" value="<?= htmlspecialchars($settings['telegram_bot_token'] ?? '') ?>" class="w-full border rounded p-2" autocomplete="off">
                            <small class="text-gray-500">ใส่ Token ของ Telegram Bot ที่ต้องการใช้ส่งแจ้งเตือน</small>
                        </div>
                        <div>
                            <label class="block font-medium">Telegram Chat ID</label>
                            <input type="text" name="telegram_chat_id" value="<?= htmlspecialchars($settings['telegram_chat_id'] ?? '') ?>" class="w-full border rounded p-2" autocomplete="off">
                            <small class="text-gray-500">Chat ID ของแอดมินหรือกลุ่มที่ต้องการรับแจ้งเตือน</small>
                        </div>
                    </div>
                    <div class="mt-2">
                        <label class="inline-flex items-center">
                            <input type="checkbox" name="enable_telegram_notify" value="1" <?= !empty($settings['enable_telegram_notify']) ? 'checked' : '' ?> class="mr-2 accent-purple-600 scale-125">
                            <span class="ml-2">เปิดใช้งานการแจ้งเตือนผ่าน Telegram</span>
                        </label>
                    </div>
                    <div class="mt-4 text-xs text-gray-500">
                        <span class="inline-block bg-purple-200 text-purple-800 px-2 py-1 rounded">Telegram</span>
                        แจ้งเตือนผ่าน Telegram Bot ไปยังแอดมินหรือกลุ่มที่กำหนด
                    </div>
                </div>

                <!-- LINE Login (Social Login) -->
                <div class="bg-gradient-to-br from-yellow-50 to-yellow-100 border border-yellow-200 rounded-lg p-6 shadow col-span-2">
                    <div class="flex items-center gap-2 mb-4">
                        <svg class="w-6 h-6 text-yellow-500" fill="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M9 12l2 2 4-4" stroke="#fff" stroke-width="2" fill="none"/></svg>
                        <h3 class="text-lg font-semibold text-yellow-700">LINE Login (Social Login)</h3>
                    </div>
                    <div class="grid md:grid-cols-2 gap-4">
                        <div>
                            <label class="block font-medium">LINE Login Channel Access Token</label>
                            <input type="text" name="line_channel_access_token" value="<?= htmlspecialchars($settings['line_channel_access_token'] ?? '') ?>" class="w-full border rounded p-2" autocomplete="off">
                        </div>
                        <div>
                            <label class="block font-medium">LINE Login Channel Secret</label>
                            <input type="text" name="line_channel_secret" value="<?= htmlspecialchars($settings['line_channel_secret'] ?? '') ?>" class="w-full border rounded p-2" autocomplete="off">
                        </div>
                        <div>
                            <label class="block font-medium">LINE Login Channel ID</label>
                            <input type="text" name="line_channel_id" value="<?= htmlspecialchars($settings['line_channel_id'] ?? '') ?>" class="w-full border rounded p-2" autocomplete="off">
                        </div>
                    </div>
                    <div class="mt-4 text-xs text-gray-500">
                        <span class="inline-block bg-yellow-200 text-yellow-800 px-2 py-1 rounded">LINE Login</span>
                        ใช้สำหรับ Social Login ด้วย LINE
                    </div>
                </div>
            </div>
        </div>

        <div id="payment" class="tab-content">
            <h2 class="text-xl font-bold mb-2">ตั้งค่าการชำระเงิน</h2>
            <p class="text-gray-600 mb-4">เลือกวิธีการชำระเงินที่รองรับ</p>

            <div class="space-y-2">
                <?php foreach ($payment_options as $key => $label): ?>
                    <label class="inline-flex items-center">
                        <input type="checkbox" name="payment_methods[]" value="<?= $key ?>" <?= in_array($key, $selected_payment_methods) ? 'checked' : '' ?> class="form-checkbox h-5 w-5 text-blue-600">
                        <span class="ml-2 text-gray-700"><?= $label ?></span>
                    </label>
                <?php endforeach; ?>
            </div>

            <div id="banking_info" class="mt-4">
                <h3 class="font-bold mb-2">บัญชีธนาคาร</h3>
                <?php foreach ($bank_accounts as $bank): ?>
                    <div class="border rounded p-3 mb-3 bg-gray-50 relative">
                        <div class="mb-1"><b>ชื่อบัญชี:</b> <?= htmlspecialchars($bank['account_name']) ?></div>
                        <div class="mb-1"><b>เลขที่บัญชี:</b> <?= htmlspecialchars($bank['account_number']) ?></div>
                        <div class="mb-1"><b>ธนาคาร:</b> <?= htmlspecialchars($bank['bank_name']) ?></div>
                        <?php if ($bank['promptpay_number']): ?>
                            <div class="mb-1"><b>พร้อมเพย์:</b> <?= htmlspecialchars($bank['promptpay_number']) ?></div>
                        <?php endif; ?>
                        <?php if ($bank['promptpay_qr']): ?>
                            <div class="mb-1">
                                <img src="../<?= htmlspecialchars($bank['promptpay_qr']) ?>" alt="PromptPay QR" class="max-h-24 mb-1">
                            </div>
                        <?php endif; ?>
                        <a href="bank_edit.php?id=<?= $bank['id'] ?>" class="text-blue-600 underline mr-2">แก้ไข</a>
                        <a href="bank_delete.php?id=<?= $bank['id'] ?>" class="text-red-600 underline" onclick="return confirm('ลบบัญชีนี้?')">ลบ</a>
                    </div>
                <?php endforeach; ?>
                <a href="add_bank.php" class="inline-block mt-2 px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700">+ เพิ่มบัญชีธนาคาร</a>
            </div>
        </div>

        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">บันทึกการตั้งค่า</button>
    </form>
</div>

<script>
    $(document).ready(function() {
        $('#featured_tag_ids').select2();

        $('select[name="product_display_type"]').on('change', function() {
            if (this.value === 'featured') {
                $('#featured_tags_section').removeClass('hidden');
            } else {
                $('#featured_tags_section').addClass('hidden');
            }
        });

        // Tab switching logic
        $('.tab-btn').on('click', function() {
            // Remove active class from all tab buttons
            $('.tab-btn').removeClass('active text-blue-600 border-blue-600');
            // Add active class to clicked tab
            $(this).addClass('active text-blue-600 border-blue-600');
            // Hide all tab contents
            $('.tab-content').hide();
            // Show the selected tab content
            $('#' + $(this).data('tab')).show();
        });

        // เปิด tab แรกเมื่อโหลดหน้า (หากไม่มี hash ใน URL)
        // เพื่อให้เปิดแท็บตามที่กำหนดใน data-tab="general"
        if (!window.location.hash) {
            $('.tab-content').hide();
            $('#general').show();
            $('.tab-btn[data-tab="general"]').addClass('active text-blue-600 border-blue-600');
        } else {
            // หากมี hash ใน URL (เช่น #notification) ให้เปิดแท็บนั้น
            const hash = window.location.hash.substring(1); // ลบ '#' ออก
            $('.tab-content').hide();
            $('#' + hash).show();
            $('.tab-btn[data-tab="' + hash + '"]').addClass('active text-blue-600 border-blue-600');
        }
    });
</script>
</body>
</html>
