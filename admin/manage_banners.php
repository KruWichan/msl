<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}
require '../includes/db.php';

// Helper function สำหรับแสดงข้อความแจ้งเตือน
function displayMessage($type) {
    if (isset($_GET[$type])) {
        $messages = [
            'added' => ['class' => 'bg-green-100 text-green-800', 'text' => '✅ เพิ่มแบนเนอร์เรียบร้อยแล้ว'],
            'updated' => ['class' => 'bg-green-100 text-green-800', 'text' => '✅ อัปเดตแบนเนอร์เรียบร้อยแล้ว'],
            'deleted' => ['class' => 'bg-red-100 text-red-800', 'text' => '🗑️ ลบแบนเนอร์แล้ว'],
            'error' => [
                'class' => 'bg-yellow-100 text-yellow-800',
                'map' => [
                    'invalid_input' => '❗ ข้อมูลที่ป้อนไม่ถูกต้อง กรุณาตรวจสอบอีกครั้ง',
                    'db_error' => '❗ เกิดข้อผิดพลาดในการบันทึกข้อมูล',
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

// จัดการการลบแบนเนอร์
if (isset($_GET['delete'])) {
    $banner_id = filter_input(INPUT_GET, 'delete', FILTER_VALIDATE_INT);
    if ($banner_id) {
        try {
            // ดึงชื่อไฟล์รูปภาพก่อนลบ
            $stmt_img = $pdo->prepare("SELECT image FROM banners WHERE id = ?");
            $stmt_img->execute([$banner_id]);
            $banner_to_delete = $stmt_img->fetch(PDO::FETCH_ASSOC);

            // ลบแบนเนอร์ออกจากตาราง banners (และจะลบใน banner_grid_map ด้วย CASCADE DELETE)
            $stmt_delete = $pdo->prepare("DELETE FROM banners WHERE id = ?");
            $stmt_delete->execute([$banner_id]);

            if ($stmt_delete->rowCount() > 0) {
                // ลบไฟล์รูปภาพออกจากเซิร์ฟเวอร์
                if ($banner_to_delete && $banner_to_delete['image'] && file_exists('../' . $banner_to_delete['image'])) {
                    unlink('../' . $banner_to_delete['image']);
                }
                header("Location: manage_banners.php?deleted=1");
                exit;
            } else {
                header("Location: manage_banners.php?error=not_found");
                exit;
            }
        } catch (PDOException $e) {
            error_log("Database error deleting banner: " . $e->getMessage());
            header("Location: manage_banners.php?error=db_error");
            exit;
        }
    } else {
        header("Location: manage_banners.php?error=invalid_input");
        exit;
    }
}

// จัดการการอัปเดตสถานะแบนเนอร์ (ใช้ AJAX หรือ form submit แยก)
// เพื่อความง่าย ผมจะรวมใน form ของแต่ละแถว
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $banner_id = filter_input(INPUT_POST, 'banner_id', FILTER_VALIDATE_INT);
    $status = $_POST['status'] ?? 'inactive';

    if (!$banner_id || !in_array($status, ['active', 'inactive'])) {
        header("Location: manage_banners.php?error=invalid_input");
        exit;
    }

    try {
        $stmt = $pdo->prepare("UPDATE banners SET status = ? WHERE id = ?");
        $stmt->execute([$status, $banner_id]);
        header("Location: manage_banners.php?updated=1");
        exit;
    } catch (PDOException $e) {
        error_log("Database error updating banner status: " . $e->getMessage());
        header("Location: manage_banners.php?error=db_error");
        exit;
    }
}


// ดึงข้อมูลแบนเนอร์ทั้งหมด
$banners = $pdo->query("SELECT id, name, image, link, status FROM banners ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>จัดการแบนเนอร์ทั้งหมด</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .preview-img {
            max-width: 80px;
            height: auto;
            display: block;
            margin: auto;
            border-radius: 4px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            border: 1px solid #e2e8f0;
            padding: 0.75rem;
            text-align: left;
        }
        th {
            background-color: #f8fafc;
            font-weight: 600;
        }
    </style>
</head>
<body class="bg-gray-100">
<?php require 'header.php'; ?>

<div class="max-w-7xl mx-auto mt-8 p-6 bg-white rounded shadow">
    <h1 class="text-2xl font-bold mb-4">📊 จัดการแบนเนอร์ทั้งหมด</h1>
    <p class="text-gray-600 mb-4">หน้านี้แสดงรายการแบนเนอร์ทั้งหมด คุณสามารถแก้ไขข้อมูลพื้นฐาน, ลบแบนเนอร์, หรือจัดการการแสดงผลใน Grid ต่างๆ ได้ที่นี่</p>

    <div class="mb-6">
        <a href="add_banner.php" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
            ➕ เพิ่มแบนเนอร์ใหม่
        </a>
    </div>

    <?php 
    displayMessage('added');
    displayMessage('updated');
    displayMessage('deleted');
    displayMessage('error');
    ?>

    <?php if (empty($banners)): ?>
        <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 p-3 rounded">
            ยังไม่มีแบนเนอร์ในระบบ. กรุณาเพิ่มแบนเนอร์ใหม่.
        </div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="min-w-full bg-white border">
                <thead>
                    <tr class="bg-gray-200">
                        <th class="p-2 border">ID</th>
                        <th class="p-2 border">รูปภาพ</th>
                        <th class="p-2 border">ชื่อแบนเนอร์</th>
                        <th class="p-2 border">ลิงก์</th>
                        <th class="p-2 border text-center">สถานะ</th>
                        <th class="p-2 border text-center">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($banners as $banner): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="border p-2 text-center"><?= htmlspecialchars($banner['id']) ?></td>
                            <td class="border p-2 text-center">
                                <?php if ($banner['image'] && file_exists("../" . $banner['image'])): ?>
                                    <img src="../<?= htmlspecialchars($banner['image']) ?>" alt="<?= htmlspecialchars($banner['name'] ?? 'Banner Image') ?>" class="preview-img">
                                <?php else: ?>
                                    ไม่มีรูป
                                <?php endif; ?>
                            </td>
                            <td class="border p-2"><?= htmlspecialchars($banner['name']) ?></td>
                            <td class="border p-2 break-all">
                                <?= $banner['link'] ? '<a href="' . htmlspecialchars($banner['link']) . '" class="text-blue-500 hover:underline" target="_blank">' . htmlspecialchars($banner['link']) . '</a>' : '—' ?>
                            </td>
                            <td class="border p-2 text-center">
                                <form action="manage_banners.php" method="POST" class="inline-block">
                                    <input type="hidden" name="banner_id" value="<?= htmlspecialchars($banner['id']) ?>">
                                    <select name="status" onchange="this.form.submit()" class="border rounded px-2 py-1 text-sm">
                                        <option value="active" <?= $banner['status'] == 'active' ? 'selected' : '' ?>>Active ✅</option>
                                        <option value="inactive" <?= $banner['status'] == 'inactive' ? 'selected' : '' ?>>Inactive ❌</option>
                                    </select>
                                    <input type="hidden" name="update_status" value="1">
                                </form>
                            </td>
                            <td class="border p-2 text-center">
                                <div class="flex flex-col space-y-2">
                                    <a href="edit_banner.php?id=<?= htmlspecialchars($banner['id']) ?>" class="bg-yellow-500 text-white px-3 py-1 rounded hover:bg-yellow-600 text-sm">
                                        ✏️ แก้ไขข้อมูล
                                    </a>
                                    <a href="edit_banner_grid_items.php?id=<?= htmlspecialchars($banner['id']) ?>" class="bg-purple-500 text-white px-3 py-1 rounded hover:bg-purple-600 text-sm">
                                        🔗 จัดการใน Grid
                                    </a>
                                    <a href="manage_banners.php?delete=<?= htmlspecialchars($banner['id']) ?>" 
                                       class="bg-red-500 text-white px-3 py-1 rounded hover:bg-red-600 text-sm" 
                                       onclick="return confirm('ยืนยันการลบแบนเนอร์นี้หรือไม่? (การลบจะนำแบนเนอร์ออกจาก Grid ทั้งหมดด้วย)')">
                                        🗑️ ลบ
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
</body>
</html>