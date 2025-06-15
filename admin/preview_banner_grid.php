<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}
require '../includes/db.php';

$grid_id = $_GET['id'] ?? null;

if (!$grid_id) {
    echo "<div class='text-center p-8 text-red-500'>ไม่พบ ID ของ Grid ที่ต้องการ Preview.</div>";
    exit;
}

// ดึงข้อมูล Grid
$stmt_grid = $pdo->prepare("SELECT * FROM banner_grids WHERE id = ?");
$stmt_grid->execute([$grid_id]);
$grid_data = $stmt_grid->fetch(PDO::FETCH_ASSOC);

if (!$grid_data) {
    echo "<div class='text-center p-8 text-red-500'>ไม่พบ Grid ที่มี ID: " . htmlspecialchars($grid_id) . "</div>";
    exit;
}

// ดึงข้อมูลแบนเนอร์ทั้งหมดที่อยู่ใน Grid ที่เลือก
$stmt_grid_banners = $pdo->prepare("
    SELECT b.*, bgm.col_span, bgm.row_span, bgm.display_order
    FROM banners b
    JOIN banner_grid_map bgm ON b.id = bgm.banner_id
    WHERE bgm.grid_id = ? AND b.status = 'active'
    ORDER BY bgm.display_order ASC
");
$stmt_grid_banners->execute([$grid_id]);
$banners_in_grid = $stmt_grid_banners->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>Preview Grid: <?= htmlspecialchars($grid_data['title'] ?? $grid_data['name'] ?? 'ไม่มีชื่อ') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .grid-container {
            display: grid;
            grid-template-columns: repeat(<?= $grid_data['columns'] ?? 1 ?>, 1fr);
            gap: 1rem;
            min-height: <?= is_numeric($grid_data['height']) ? $grid_data['height'] . 'px' : ($grid_data['height'] ?? '300px') ?>;
            width: 100%;
            max-width: 1200px;
            margin: 2rem auto;
            border: 2px solid #007bff;
            padding: 1rem;
            background-color: #f0f8ff;
        }
        .grid-item {
            display: flex;
            justify-content: center;
            align-items: center;
            overflow: hidden;
            background-color: #ffffff;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            min-height: 100px;
            position: relative;
        }
        .grid-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }
        .grid-item a {
            display: block;
            width: 100%;
            height: 100%;
        }
        .grid-placeholder {
            background-color: #f0f0f0;
            color: #a0a0a0;
            display: flex;
            justify-content: center;
            align-items: center;
            text-align: center;
            font-style: italic;
        }
        .banner-info {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(0,0,0,0.7);
            color: white;
            padding: 4px;
            font-size: 12px;
            text-align: center;
        }
    </style>
</head>
<body class="bg-gray-100">
<div class="container mx-auto p-4 text-center">
    <h1 class="text-3xl font-bold mb-4">Preview Grid: "<?= htmlspecialchars($grid_data['title'] ?? $grid_data['name'] ?? 'ไม่มีชื่อ') ?>"</h1>
    <p class="text-lg text-gray-700 mb-6">
        โครงสร้าง: <?= $grid_data['columns'] ?? 1 ?> คอลัมน์ | 
        ความสูง: <?= htmlspecialchars($grid_data['height'] ?? 'auto') ?> | 
        จำนวนแบนเนอร์: <?= count($banners_in_grid) ?>
    </p>
    <a href="edit_banner_grid_items.php?id=<?= $grid_id ?>" class="text-blue-600 hover:underline mb-4 inline-block">← กลับไปหน้าจัดการแบนเนอร์ใน Grid</a>

    <?php if (!empty($banners_in_grid)): ?>
        <div class="grid-container">
            <?php foreach ($banners_in_grid as $banner): ?>
                <div class="grid-item" 
                     style="grid-column: span <?= $banner['col_span'] ?? 1 ?>;
                            grid-row: span <?= $banner['row_span'] ?? 1 ?>;">
                    <?php if (!empty($banner['link'])): ?>
                        <a href="<?= htmlspecialchars($banner['link']) ?>" target="_blank">
                    <?php endif; ?>

                    <?php if (!empty($banner['image']) && file_exists('../' . $banner['image'])): ?>
                        <img src="../<?= htmlspecialchars($banner['image']) ?>" 
                             alt="<?= htmlspecialchars($banner['name'] ?? 'แบนเนอร์ ' . $banner['id']) ?>">
                    <?php else: ?>
                        <div class="w-full h-full flex items-center justify-center bg-gray-200">
                            <p class="text-gray-500">ไม่มีรูปภาพ</p>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($banner['link'])): ?>
                        </a>
                    <?php endif; ?>
                    
                    <div class="banner-info">
                        <?= htmlspecialchars($banner['name'] ?? 'แบนเนอร์ ' . $banner['id']) ?> | 
                        Col: <?= $banner['col_span'] ?? 1 ?> | 
                        Row: <?= $banner['row_span'] ?? 1 ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="grid-container grid-placeholder">
            <p>ไม่มีแบนเนอร์ใน Grid นี้.<br>กรุณาเพิ่มแบนเนอร์ในหน้าจัดการ.</p>
        </div>
    <?php endif; ?>
</div>
</body>
</html>