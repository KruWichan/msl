<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}
require '../includes/db.php';

// Helper function สำหรับแสดงข้อความแจ้งเตือน (คัดลอกมาจาก add_banner.php)
function displayMessage($type) {
    if (isset($_GET[$type])) {
        $messages = [
            'added' => ['class' => 'bg-green-100 text-green-800', 'text' => '✅ เพิ่มแบนเนอร์ลง Grid เรียบร้อยแล้ว'],
            'updated' => ['class' => 'bg-green-100 text-green-800', 'text' => '✅ อัปเดตแบนเนอร์ใน Grid เรียบร้อยแล้ว'],
            'removed' => ['class' => 'bg-red-100 text-red-800', 'text' => '🗑️ ถอดแบนเนอร์ออกจาก Grid แล้ว'],
            'error' => [
                'class' => 'bg-yellow-100 text-yellow-800',
                'map' => [
                    'invalid_input' => '❗ ข้อมูลที่ป้อนไม่ถูกต้อง กรุณาตรวจสอบอีกครั้ง',
                    'invalid_numeric_values' => '❗ ค่าตัวเลข (ลำดับ, Col Span, Row Span) ไม่ถูกต้อง',
                    'db_error' => '❗ เกิดข้อผิดพลาดในการบันทึกข้อมูล กรุณาลองใหม่',
                    'db_error_delete' => '❗ เกิดข้อผิดพลาดในการลบข้อมูล กรุณาลองใหม่',
                    'not_found' => '❗ ไม่พบข้อมูลที่ต้องการดำเนินการ',
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


$grid_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$grid_id) {
    header("Location: edit_banner_grid_items.php");
    exit;
}

// ดึงข้อมูล Grid
$stmt_grid = $pdo->prepare("SELECT * FROM banner_grids WHERE id = ?");
$stmt_grid->execute([$grid_id]);
$grid_data = $stmt_grid->fetch(PDO::FETCH_ASSOC);

if (!$grid_data) {
    echo "<div class='bg-red-100 border border-red-400 text-red-700 p-3 mb-4 rounded'>⚠️ ไม่พบข้อมูล Grid.</div>";
    exit;
}

// --- การจัดการ Form Actions ---

// 1. เพิ่มแบนเนอร์ลงใน Grid (Map existing banner to grid)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_banner_to_grid'])) {
    $banner_id = filter_input(INPUT_POST, 'banner_id', FILTER_VALIDATE_INT);
    $display_order = filter_input(INPUT_POST, 'display_order', FILTER_VALIDATE_INT);
    $col_span = filter_input(INPUT_POST, 'col_span', FILTER_VALIDATE_INT);
    $row_span = filter_input(INPUT_POST, 'row_span', FILTER_VALIDATE_INT);

    if (!$banner_id || $display_order === false || $col_span === false || $row_span === false || $display_order < 0 || $col_span <= 0 || $row_span <= 0) {
        header("Location: edit_banner_grid_items.php?id=$grid_id&error=invalid_input");
        exit;
    }

    try {
        // ตรวจสอบว่า banner_id นี้ไม่ได้อยู่ใน grid นี้แล้ว เพื่อป้องกัน duplicate
        $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM banner_grid_map WHERE grid_id = ? AND banner_id = ?");
        $stmt_check->execute([$grid_id, $banner_id]);
        if ($stmt_check->fetchColumn() > 0) {
            header("Location: edit_banner_grid_items.php?id=$grid_id&error=db_error&msg=banner_already_in_grid"); // เพิ่มข้อความเฉพาะ
            exit;
        }

        $stmt = $pdo->prepare("INSERT INTO banner_grid_map (grid_id, banner_id, display_order, col_span, row_span) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$grid_id, $banner_id, $display_order, $col_span, $row_span]);
        header("Location: edit_banner_grid_items.php?id=$grid_id&added=1");
        exit;
    } catch (PDOException $e) {
        error_log("Database error adding banner to grid: " . $e->getMessage());
        header("Location: edit_banner_grid_items.php?id=$grid_id&error=db_error");
        exit;
    }
}

// 2. อัปเดตแบนเนอร์ใน Grid (Update mapped banner properties)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_mapped_banner'])) {
    $map_id = filter_input(INPUT_POST, 'map_id', FILTER_VALIDATE_INT);
    $display_order = filter_input(INPUT_POST, 'display_order', FILTER_VALIDATE_INT);
    $col_span = filter_input(INPUT_POST, 'col_span', FILTER_VALIDATE_INT);
    $row_span = filter_input(INPUT_POST, 'row_span', FILTER_VALIDATE_INT);

    if (!$map_id || $display_order === false || $col_span === false || $row_span === false || $display_order < 0 || $col_span <= 0 || $row_span <= 0) {
        header("Location: edit_banner_grid_items.php?id=$grid_id&error=invalid_input");
        exit;
    }

    try {
        $stmt = $pdo->prepare("UPDATE banner_grid_map SET display_order = ?, col_span = ?, row_span = ? WHERE id = ? AND grid_id = ?");
        $stmt->execute([$display_order, $col_span, $row_span, $map_id, $grid_id]);
        header("Location: edit_banner_grid_items.php?id=$grid_id&updated=1");
        exit;
    } catch (PDOException $e) {
        error_log("Database error updating mapped banner: " . $e->getMessage());
        header("Location: edit_banner_grid_items.php?id=$grid_id&error=db_error");
        exit;
    }
}

// 3. ถอดแบนเนอร์ออกจาก Grid (Remove mapping, not delete banner)
if (isset($_GET['remove_map_id'])) {
    $map_id = filter_input(INPUT_GET, 'remove_map_id', FILTER_VALIDATE_INT);

    if (!$map_id) {
        header("Location: edit_banner_grid_items.php?id=$grid_id&error=invalid_input");
        exit;
    }

    try {
        $stmt = $pdo->prepare("DELETE FROM banner_grid_map WHERE id = ? AND grid_id = ?");
        $stmt->execute([$map_id, $grid_id]);
        header("Location: edit_banner_grid_items.php?id=$grid_id&removed=1");
        exit;
    } catch (PDOException $e) {
        error_log("Database error removing banner from grid: " . $e->getMessage());
        header("Location: edit_banner_grid_items.php?id=$grid_id&error=db_error_delete");
        exit;
    }
}

// ดึงแบนเนอร์ทั้งหมดที่มีอยู่ในระบบ (ที่ยังไม่ถูกผูกกับ Grid นี้)
// เราต้องการให้แสดงเฉพาะแบนเนอร์ที่ยังไม่ได้อยู่ใน Grid ปัจจุบัน
$stmt_unmapped_banners = $pdo->prepare("
    SELECT b.id, b.name, b.image 
    FROM banners b
    LEFT JOIN banner_grid_map bgm ON b.id = bgm.banner_id AND bgm.grid_id = ?
    WHERE b.status = 'active' AND bgm.banner_id IS NULL
    ORDER BY b.name ASC
");
$stmt_unmapped_banners->execute([$grid_id]);
$unmapped_banners = $stmt_unmapped_banners->fetchAll(PDO::FETCH_ASSOC);


// ดึงแบนเนอร์ที่ถูกผูกกับ Grid นี้แล้ว
$stmt_mapped_banners = $pdo->prepare("
    SELECT 
        bgm.id as map_id, 
        bgm.col_span, 
        bgm.row_span, 
        bgm.display_order,
        b.id as banner_id, 
        COALESCE(b.name, CONCAT('แบนเนอร์ ', b.id)) as banner_name, 
        b.image as banner_image,
        b.link as banner_link
    FROM banner_grid_map bgm
    JOIN banners b ON bgm.banner_id = b.id
    WHERE bgm.grid_id = ?
    ORDER BY bgm.display_order ASC
");
$stmt_mapped_banners->execute([$grid_id]);
$mapped_banners = $stmt_mapped_banners->fetchAll(PDO::FETCH_ASSOC);

// แสดงข้อมูล debug (ย้ายมาไว้ข้างล่างสุดของส่วน PHP เพื่อให้แน่ใจว่าตัวแปรพร้อมใช้งาน)
// echo "";
// echo "";
// echo "";
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>จัดการแบนเนอร์ใน Grid: <?= htmlspecialchars($grid_data['title'] ?? 'Grid') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .preview-img {
            max-width: 100px;
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

<div class="max-w-6xl mx-auto mt-8 p-6 bg-white rounded shadow">
    <h1 class="text-2xl font-bold mb-4">⚙️ จัดการแบนเนอร์ใน Grid: "<?= htmlspecialchars($grid_data['title'] ?? 'Grid') ?>" (ID: <?= $grid_id ?>)</h1>
    <p class="text-gray-600 mb-4">ในหน้านี้คุณสามารถเพิ่มแบนเนอร์ที่มีอยู่แล้วเข้าไปใน Grid นี้ และปรับแต่งตำแหน่ง, ขนาด (Col Span, Row Span) ของแบนเนอร์ใน Grid ได้</p>
    
    <?php 
    displayMessage('added');
    displayMessage('updated');
    displayMessage('removed');
    displayMessage('error');
    ?>

    <div class="bg-gray-50 p-4 rounded-lg mb-6 border border-gray-200">
        <h2 class="text-xl font-semibold mb-3">➕ เพิ่มแบนเนอร์ที่มีอยู่ลงใน Grid นี้</h2>
        <?php if (empty($unmapped_banners)): ?>
            <p class="text-gray-600">ไม่มีแบนเนอร์ที่ยังไม่ได้ถูกผูกกับ Grid นี้ในระบบ. คุณสามารถ <a href="add_banner.php" class="text-blue-500 hover:underline">เพิ่มแบนเนอร์ใหม่ได้ที่นี่</a>.</p>
        <?php else: ?>
            <form method="post" class="space-y-4">
                <div>
                    <label for="banner_id" class="block text-gray-700 text-sm font-bold mb-1">เลือกแบนเนอร์:</label>
                    <select name="banner_id" id="banner_id" required class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                        <option value="">-- เลือกแบนเนอร์ --</option>
                        <?php foreach ($unmapped_banners as $banner): ?>
                            <option value="<?= htmlspecialchars($banner['id']) ?>">
                                <?= htmlspecialchars($banner['name'] ?? 'แบนเนอร์ ID: ' . $banner['id']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label for="display_order" class="block text-gray-700 text-sm font-bold mb-1">ลำดับการแสดงผล:</label>
                        <input type="number" name="display_order" id="display_order" value="0" min="0" required class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                        <p class="text-xs text-gray-500 mt-1">ตัวเลขน้อยแสดงก่อน (0 = เริ่มต้น)</p>
                    </div>
                    <div>
                        <label for="col_span" class="block text-gray-700 text-sm font-bold mb-1">Col Span:</label>
                        <input type="number" name="col_span" id="col_span" value="1" min="1" required class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                        <p class="text-xs text-gray-500 mt-1">กินกี่คอลัมน์ใน Grid (เช่น 1, 2, 3...)</p>
                    </div>
                    <div>
                        <label for="row_span" class="block text-gray-700 text-sm font-bold mb-1">Row Span:</label>
                        <input type="number" name="row_span" id="row_span" value="1" min="1" required class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                        <p class="text-xs text-gray-500 mt-1">กินกี่แถวใน Grid (เช่น 1, 2, 3...)</p>
                    </div>
                </div>
                <button type="submit" name="add_banner_to_grid" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                    ➕ เพิ่มแบนเนอร์ลง Grid
                </button>
            </form>
        <?php endif; ?>
    </div>

    <div class="bg-white p-4 rounded-lg shadow-md">
        <h2 class="text-xl font-bold mb-3">📋 แบนเนอร์ใน Grid นี้ (<?= htmlspecialchars($grid_data['title']) ?>)</h2>
        <?php if (empty($mapped_banners)): ?>
            <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 p-3 rounded">
                ยังไม่มีแบนเนอร์ใน Grid นี้. กรุณาเพิ่มแบนเนอร์ด้านบน.
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full table-auto border mb-8">
                    <thead>
                        <tr class="bg-gray-200">
                            <th class="p-2 border">ID Map</th>
                            <th class="p-2 border">ID แบนเนอร์</th>
                            <th class="p-2 border">รูปภาพ</th>
                            <th class="p-2 border">ชื่อแบนเนอร์</th>
                            <th class="p-2 border">Col Span</th>
                            <th class="p-2 border">Row Span</th>
                            <th class="p-2 border">ลำดับ</th>
                            <th class="p-2 border">Link</th>
                            <th class="p-2 border">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($mapped_banners as $mapped_banner): ?>
                            <tr>
                                <td class="border p-2 text-center"><?= htmlspecialchars($mapped_banner['map_id']) ?></td>
                                <td class="border p-2 text-center"><?= htmlspecialchars($mapped_banner['banner_id']) ?></td>
                                <td class="border p-2 text-center">
                                    <?php if ($mapped_banner['banner_image'] && file_exists("../" . $mapped_banner['banner_image'])): ?>
                                        <img src="../<?= htmlspecialchars($mapped_banner['banner_image']) ?>" 
                                            alt="<?= !empty($mapped_banner['banner_name']) ? htmlspecialchars($mapped_banner['banner_name']) : 'แบนเนอร์ ' . htmlspecialchars($mapped_banner['banner_id']) ?>" 
                                            class="preview-img mx-auto">
                                    <?php else: ?>
                                        ไม่มีรูป
                                    <?php endif; ?>
                                </td>
                                <td class="border p-2">
                                    <?= htmlspecialchars($mapped_banner['banner_name']) ?>
                                </td>
                                <form method="post" class="contents"> <input type="hidden" name="map_id" value="<?= htmlspecialchars($mapped_banner['map_id']) ?>">
                                    <td class="border p-2">
                                        <input type="number" name="col_span" value="<?= htmlspecialchars($mapped_banner['col_span']) ?>" min="1" required class="w-full border rounded p-1 text-center">
                                    </td>
                                    <td class="border p-2">
                                        <input type="number" name="row_span" value="<?= htmlspecialchars($mapped_banner['row_span']) ?>" min="1" required class="w-full border rounded p-1 text-center">
                                    </td>
                                    <td class="border p-2">
                                        <input type="number" name="display_order" value="<?= htmlspecialchars($mapped_banner['display_order']) ?>" min="0" required class="w-full border rounded p-1 text-center">
                                    </td>
                                    <td class="border p-2">
                                        <?= $mapped_banner['banner_link'] ? '<a href="' . htmlspecialchars($mapped_banner['banner_link']) . '" class="text-blue-500 hover:underline" target="_blank">ดูลิงก์</a>' : '—' ?>
                                    </td>
                                    <td class="border p-2 text-center">
                                        <button type="submit" name="update_mapped_banner" class="bg-green-500 hover:bg-green-600 text-white px-3 py-1 rounded text-sm mb-1 w-full">
                                            ✅ อัปเดต
                                        </button>
                                        <a href="edit_banner_grid_items.php?id=<?= $grid_id ?>&remove_map_id=<?= htmlspecialchars($mapped_banner['map_id']) ?>" 
                                           onclick="return confirm('ยืนยันการถอดแบนเนอร์นี้ออกจาก Grid หรือไม่? (แบนเนอร์จะไม่ถูกลบออกจากระบบ)')" 
                                           class="bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded text-sm w-full inline-block">
                                            ✖️ ถอดออก
                                        </a>
                                    </td>
                                </form>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>