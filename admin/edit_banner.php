<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

// ตรวจสอบสิทธิ์ผู้ดูแลระบบ
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

require '../includes/db.php';

// Helper function สำหรับแสดงข้อความแจ้งเตือน (คัดลอกมาจาก add_banner.php)
function displayMessage($type) {
    if (isset($_GET[$type])) {
        $messages = [
            'updated' => ['class' => 'bg-green-100 text-green-800', 'text' => '✅ อัปเดตแบนเนอร์เรียบร้อยแล้ว'],
            'error' => [
                'class' => 'bg-red-100 text-red-800',
                'map' => [
                    'banner_not_found' => '❗ ไม่พบแบนเนอร์ที่ต้องการแก้ไข',
                    'invalid_banner_id' => '❗ ID แบนเนอร์ไม่ถูกต้อง',
                    'invalid_input' => '❗ ข้อมูลที่ป้อนไม่ถูกต้อง กรุณาตรวจสอบอีกครั้ง',
                    'invalid_file_type' => '❗ ประเภทไฟล์รูปภาพไม่ถูกต้อง อนุญาตเฉพาะ JPEG, PNG, GIF, WebP เท่านั้น',
                    'file_too_large' => '❗ ขนาดไฟล์รูปภาพใหญ่เกินไป (สูงสุด 5MB)',
                    'upload_failed' => '❗ ไม่สามารถอัปโหลดไฟล์รูปภาพได้',
                    'db_error' => '❗ เกิดข้อผิดพลาดในการบันทึกข้อมูล กรุณาลองใหม่',
                    'default' => '❗ เกิดข้อผิดพลาดที่ไม่รู้จัก'
                ]
            ]
        ];

        $msg_config = $messages[$type];
        $text = '';
        if ($type === 'error') {
            $error_code = $_GET['error'] ?? 'default';
            $text = $msg_config['map'][$error_code] ?? $msg_config['map']['default'];
        } else {
            $text = $msg_config['text'];
        }
        echo '<div class="' . $msg_config['class'] . ' p-2 rounded mb-4">' . $text . '</div>';
    }
}


$banner_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$banner = null;

// ดึงข้อมูลแบนเนอร์ที่จะแก้ไข
if ($banner_id) {
    $stmt = $pdo->prepare("SELECT id, name, image, link, status FROM banners WHERE id = ?"); // ดึงเฉพาะคอลัมน์ที่เกี่ยวข้อง
    $stmt->execute([$banner_id]);
    $banner = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$banner) {
        // หากไม่พบแบนเนอร์ที่ต้องการแก้ไข
        header("Location: manage_banners.php?error=banner_not_found"); // Redirect ไปหน้า manage_banners
        exit;
    }
} else {
    // หากไม่มีการส่ง id มาใน URL
    header("Location: manage_banners.php?error=invalid_banner_id"); // Redirect ไปหน้า manage_banners
    exit;
}

// ประมวลผลการอัปเดตข้อมูลแบนเนอร์
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_banner'])) {
    $id = filter_input(INPUT_POST, 'banner_id', FILTER_VALIDATE_INT);
    $name = trim($_POST['name'] ?? '');
    $link = trim($_POST['link'] ?? '');
    $status = $_POST['status'] ?? 'inactive'; // ตรวจสอบ status ที่ส่งมา
    
    // ตรวจสอบความถูกต้องของข้อมูลที่ส่งมา
    if (!$id || empty($name)) {
        header("Location: edit_banner.php?id=$id&error=invalid_input");
        exit;
    }
    // Validate URL only if not empty
    if (!empty($link) && !filter_var($link, FILTER_VALIDATE_URL)) {
        header("Location: edit_banner.php?id=$id&error=invalid_input"); // หรือ error code เฉพาะสำหรับ URL
        exit;
    }

    $image_path = $banner['image']; // ใช้รูปภาพเดิมเป็นค่าเริ่มต้น

    // จัดการการอัปโหลดรูปภาพใหม่
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK && !empty($_FILES['image']['name'])) {
        $target_dir = "../uploads/banners/";
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0755, true); // Changed to 0755
        }

        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $max_size = 5 * 1024 * 1024; // 5 MB

        if (!in_array($_FILES['image']['type'], $allowed_types)) {
            header("Location: edit_banner.php?id=$id&error=invalid_file_type");
            exit;
        }
        if ($_FILES['image']['size'] > $max_size) {
            header("Location: edit_banner.php?id=$id&error=file_too_large");
            exit;
        }

        $file_ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $new_file_name = uniqid('banner_') . '.' . $file_ext; // สร้างชื่อไฟล์ใหม่
        $target_file = $target_dir . $new_file_name;

        if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
            // ลบรูปภาพเก่าออกถ้ามี
            if ($banner['image'] && file_exists("../" . $banner['image'])) {
                unlink("../" . $banner['image']);
            }
            $image_path = "uploads/banners/" . $new_file_name;
        } else {
            header("Location: edit_banner.php?id=$id&error=upload_failed");
            exit;
        }
    } else if (isset($_POST['remove_image']) && $_POST['remove_image'] == '1') {
        // กรณีต้องการลบรูปภาพที่มีอยู่
        if ($banner['image'] && file_exists("../" . $banner['image'])) {
            unlink("../" . $banner['image']);
        }
        $image_path = null;
    }

    try {
        // อัปเดตเฉพาะคอลัมน์ที่อยู่ในตาราง banners
        $stmt = $pdo->prepare("UPDATE banners SET name = ?, image = ?, link = ?, status = ? WHERE id = ?");
        $stmt->execute([$name, $image_path, $link, $status, $id]);

        header("Location: manage_banners.php?updated=1"); // Redirect กลับไปหน้า manage_banners
        exit;
    } catch (PDOException $e) {
        error_log("Database error updating banner: " . $e->getMessage());
        header("Location: edit_banner.php?id=$id&error=db_error");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>แก้ไขแบนเนอร์: <?= htmlspecialchars($banner['name'] ?? 'ไม่มีชื่อ') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .preview-img {
            max-width: 100%;
            height: auto;
            border-radius: 8px;
            margin-top: 10px;
        }
    </style>
</head>
<body class="bg-gray-100">
<?php require 'header.php'; ?>

<div class="max-w-4xl mx-auto mt-8 p-6 bg-white rounded shadow">
    <h1 class="text-2xl font-bold mb-6">✏️ แก้ไขแบนเนอร์: <?= htmlspecialchars($banner['name'] ?? 'ไม่มีชื่อ') ?></h1>

    <?php displayMessage('updated'); ?>
    <?php displayMessage('error'); ?>

    <form method="post" enctype="multipart/form-data" class="space-y-4">
        <input type="hidden" name="banner_id" value="<?= htmlspecialchars($banner['id']) ?>">
        
        <div>
            <label for="name" class="block text-gray-700 text-sm font-bold mb-1">ชื่อแบนเนอร์:</label>
            <input type="text" name="name" id="name" required class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" value="<?= htmlspecialchars($banner['name'] ?? '') ?>">
        </div>
        
        <div>
            <label for="link" class="block text-gray-700 text-sm font-bold mb-1">ลิงก์ (URL):</label>
            <input type="url" name="link" id="link" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" value="<?= htmlspecialchars($banner['link'] ?? '') ?>" placeholder="https://example.com">
            <p class="text-xs text-gray-500 mt-1">ตัวอย่าง: https://example.com/your-product</p>
        </div>

        <div>
            <label for="status" class="block text-gray-700 text-sm font-bold mb-1">สถานะ:</label>
            <select name="status" id="status" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                <option value="active" <?= ($banner['status'] ?? 'inactive') == 'active' ? 'selected' : '' ?>>Active</option>
                <option value="inactive" <?= ($banner['status'] ?? 'inactive') == 'inactive' ? 'selected' : '' ?>>Inactive</option>
            </select>
        </div>

        <div>
            <label class="block text-gray-700 text-sm font-bold mb-1">รูปภาพปัจจุบัน:</label>
            <?php if ($banner['image'] && file_exists("../" . $banner['image'])): ?>
                <img src="../<?= htmlspecialchars($banner['image']) ?>" alt="Current Banner Image" class="mb-2 rounded max-h-48 object-contain border p-1">
                <label class="block mt-2 text-gray-700 text-sm">
                    <input type="checkbox" name="remove_image" value="1" class="mr-2"> ติ๊กเพื่อลบรูปภาพปัจจุบัน
                </label>
            <?php else: ?>
                <div class="mb-2 text-gray-500 text-sm">ไม่มีรูปภาพปัจจุบัน</div>
            <?php endif; ?>

            <label for="image" class="block text-gray-700 text-sm font-bold mt-4 mb-1">อัปโหลดรูปภาพใหม่ (หากต้องการเปลี่ยน):</label>
            <input type="file" name="image" id="image" accept="image/jpeg, image/png, image/gif, image/webp" class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
            <p class="text-xs text-gray-500 mt-1">ไฟล์: JPEG, PNG, GIF, WebP (สูงสุด 5MB). หากอัปโหลดรูปใหม่ รูปเดิมจะถูกแทนที่</p>
            <img id="image_preview" src="#" alt="Image Preview" class="preview-img hidden">
        </div>

        <div class="text-right flex justify-between items-center">
            <a href="manage_banners.php" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">
                ↩️ ย้อนกลับไปรายการแบนเนอร์
            </a>
            <button type="submit" name="edit_banner" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                💾 บันทึกการแก้ไข
            </button>
        </div>
    </form>
</div>

<script>
    // Image preview script
    document.getElementById('image').addEventListener('change', function(event) {
        const [file] = event.target.files;
        if (file) {
            const preview = document.getElementById('image_preview');
            preview.src = URL.createObjectURL(file);
            preview.classList.remove('hidden');
        }
    });
</script>
</body>
</html>