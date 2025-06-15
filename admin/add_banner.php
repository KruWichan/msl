<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}
require '../includes/db.php';

// Helper function สำหรับแสดงข้อความแจ้งเตือน (ปรับปรุงให้รองรับ success/error)
function displayMessage($type) {
    if (isset($_GET[$type])) {
        $messages = [
            'added' => ['class' => 'bg-green-100 text-green-800', 'text' => '✅ เพิ่มแบนเนอร์เรียบร้อยแล้ว'],
            'error' => [
                'class' => 'bg-red-100 text-red-800',
                'map' => [
                    'invalid_input' => '❗ ข้อมูลที่ป้อนไม่ถูกต้อง กรุณาตรวจสอบอีกครั้ง',
                    'invalid_file_type' => '❗ ประเภทไฟล์รูปภาพไม่ถูกต้อง อนุญาตเฉพาะ JPEG, PNG, GIF, WebP เท่านั้น',
                    'file_too_large' => '❗ ขนาดไฟล์รูปภาพใหญ่เกินไป (สูงสุด 5MB)',
                    'upload_failed' => '❗ ไม่สามารถอัปโหลดไฟล์รูปภาพได้',
                    'db_error' => '❗ เกิดข้อผิดพลาดในการบันทึกข้อมูลลงฐานข้อมูล',
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

// จัดการการเพิ่มแบนเนอร์
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_banner'])) {
    $name = trim($_POST['name'] ?? '');
    $link = trim($_POST['link'] ?? '');
    $status = $_POST['status'] ?? 'inactive'; // Default to inactive if not set
    $image_path = null;
    $error_code = null;

    // Validate inputs
    if (empty($name)) {
        $error_code = 'invalid_input';
    }

    if (!$error_code && isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/banners/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $max_file_size = 5 * 1024 * 1024; // 5MB

        if (!in_array($_FILES['image']['type'], $allowed_types)) {
            $error_code = 'invalid_file_type';
        } elseif ($_FILES['image']['size'] > $max_file_size) {
            $error_code = 'file_too_large';
        } else {
            $file_ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $new_file_name = uniqid('banner_') . '.' . $file_ext;
            $destination = $upload_dir . $new_file_name;

            if (move_uploaded_file($_FILES['image']['tmp_name'], $destination)) {
                $image_path = 'uploads/banners/' . $new_file_name;
            } else {
                $error_code = 'upload_failed';
            }
        }
    }

    if ($error_code) {
        header("Location: add_banner.php?error=$error_code");
        exit;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO banners (name, image, link, status) VALUES (?, ?, ?, ?)");
        $stmt->execute([$name, $image_path, $link, $status]);
        header("Location: manage_banners.php?added=1"); // Redirect to manage_banners after adding
        exit;
    } catch (PDOException $e) {
        error_log("Database error adding banner: " . $e->getMessage());
        header("Location: add_banner.php?error=db_error");
        exit;
    }
}

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>เพิ่มแบนเนอร์ใหม่</title>
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
    <h1 class="text-2xl font-bold mb-4">🆕 เพิ่มแบนเนอร์ใหม่</h1>
    
    <?php displayMessage('added'); ?>
    <?php displayMessage('error'); ?>

    <form action="add_banner.php" method="POST" enctype="multipart/form-data" class="space-y-4">
        <div>
            <label for="name" class="block text-gray-700 text-sm font-bold mb-1">ชื่อแบนเนอร์:</label>
            <input type="text" name="name" id="name" required class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
        </div>
        <div>
            <label for="image" class="block text-gray-700 text-sm font-bold mb-1">รูปภาพแบนเนอร์:</label>
            <input type="file" name="image" id="image" accept="image/jpeg, image/png, image/gif, image/webp" class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
            <p class="text-xs text-gray-500 mt-1">ไฟล์: JPEG, PNG, GIF, WebP (สูงสุด 5MB)</p>
            <img id="image_preview" src="#" alt="Image Preview" class="preview-img hidden">
        </div>
        <div>
            <label for="link" class="block text-gray-700 text-sm font-bold mb-1">ลิงก์ (URL):</label>
            <input type="url" name="link" id="link" placeholder="https://example.com/your-product" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
            <p class="text-xs text-gray-500 mt-1">ตัวอย่าง: https://example.com/your-product</p>
        </div>
        <div>
            <label for="status" class="block text-gray-700 text-sm font-bold mb-1">สถานะ:</label>
            <select name="status" id="status" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
            </select>
        </div>
        
        <button type="submit" name="add_banner" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
            💾 บันทึกแบนเนอร์
        </button>
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