<?php
// ไฟล์: /public_html/banner.php

// ตรวจสอบว่ามี $settings และ $pdo ถูกประกาศไว้แล้ว (มาจาก index.php)
// ถ้าไม่มี อาจจะต้อง include 'includes/db.php' และดึง $settings ใหม่ในไฟล์นี้
// แต่โดยปกติแล้ว index.php จะเตรียมตัวแปรเหล่านี้ให้แล้วก่อน include banner.php

// ดึง ID ของชุดแบนเนอร์ที่เลือกจาก homepage_banner_grid_id
$homepage_banner_grid_id = $settings['homepage_banner_grid_id'] ?? null;

$banners_in_grid = []; // กำหนดตัวแปรเริ่มต้นสำหรับเก็บแบนเนอร์ใน Grid
$num_columns = 1; // Default: 1 คอลัมน์
$grid_height_css_value = '300px'; // Default height value, พร้อมหน่วย

if ($homepage_banner_grid_id) {
    // ดึงข้อมูล Grid
    $stmt_grid_data = $pdo->prepare("SELECT columns, height FROM banner_grids WHERE id = ?");
    $stmt_grid_data->execute([$homepage_banner_grid_id]);
    $grid_config = $stmt_grid_data->fetch(PDO::FETCH_ASSOC);

    if ($grid_config) {
        $num_columns = (int)$grid_config['columns'];
        if ($num_columns <= 0) { // ต้องมีอย่างน้อย 1 คอลัมน์
            $num_columns = 1;
        }

        // กำหนด height style จากค่าที่ตั้งใน Grid
        $grid_height_value_from_db = $grid_config['height'];
        if (!empty($grid_height_value_from_db)) {
            // ตรวจสอบหน่วย ถ้าไม่มีหน่วย ให้ใส่ px
            if (strpos($grid_height_value_from_db, '%') !== false || strpos($grid_height_value_from_db, 'px') !== false || strpos($grid_height_value_from_db, 'vh') !== false || strpos($grid_height_value_from_db, 'em') !== false || strpos($grid_height_value_from_db, 'rem') !== false) {
                $grid_height_css_value = $grid_height_value_from_db;
            } else if (is_numeric($grid_height_value_from_db)) {
                $grid_height_css_value = $grid_height_value_from_db . 'px';
            }
        }
    } else {
        // กรณีไม่พบ grid config ใช้ค่า default
        $num_columns = 1;
        $grid_height_css_value = '300px';
    }

    // ดึงข้อมูลแบนเนอร์ทั้งหมดที่อยู่ใน Grid ที่เลือก
    $stmt_grid_banners = $pdo->prepare("
        SELECT b.id, b.name, b.image, b.link, b.status, bgm.col_span, bgm.row_span, bgm.display_order
        FROM banners b
        JOIN banner_grid_map bgm ON b.id = bgm.banner_id
        WHERE bgm.grid_id = ? AND b.status = 'active'
        ORDER BY bgm.display_order ASC
    ");
    $stmt_grid_banners->execute([$homepage_banner_grid_id]);
    $banners_in_grid = $stmt_grid_banners->fetchAll(PDO::FETCH_ASSOC);
} else {
    // ถ้า $homepage_banner_grid_id เป็น null (ไม่มีการตั้งค่า)
    $num_columns = 1;
    $grid_height_css_value = '300px';
}

// ตรวจสอบว่ามีแบนเนอร์ใน Grid หรือไม่
if (!empty($banners_in_grid)):
?>
    <div class="main-banner-grid-section container mx-auto px-4 my-8">
        <div class="grid gap-4"
             style="grid-template-columns: repeat(<?= htmlspecialchars($num_columns) ?>, 1fr);">
            <?php foreach ($banners_in_grid as $banner):
                $col_span = $banner['col_span'] ?? 1; // ค่าเริ่มต้น 1
                $row_span = $banner['row_span'] ?? 1; // ค่าเริ่มต้น 1
                ?>
                <div style="grid-column: span <?= htmlspecialchars($col_span) ?>; grid-row: span <?= htmlspecialchars($row_span) ?>; min-height: 120px;" class="flex justify-center items-center overflow-hidden rounded-lg shadow-md">
                    <?php if (!empty($banner['link'])): ?>
                        <a href="<?= htmlspecialchars($banner['link']) ?>" target="_blank" class="block w-full h-full">
                    <?php endif; ?>

                    <?php if (!empty($banner['image'])): ?>
                        <img src="<?= htmlspecialchars($banner['image']) ?>" alt="<?= htmlspecialchars($banner['name'] ?? 'Banner') ?>"
                             class="w-full h-full object-cover">
                    <?php else: ?>
                        <p class="text-center p-4 text-gray-700 text-sm font-semibold">
                            <?= htmlspecialchars($banner['name'] ?? 'ไม่มีรูปภาพ') ?>
                        </p>
                    <?php endif; ?>

                    <?php if (!empty($banner['link'])): ?>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php else: ?>
    <div class="main-banner-grid-section container mx-auto px-4 my-8">
        <p class="text-center text-gray-500 p-8 text-lg">
            ไม่มีชุดแบนเนอร์หลักที่จะแสดงผลในขณะนี้ หรือชุดแบนเนอร์ที่เลือกไม่มีแบนเนอร์อยู่. กรุณาตั้งค่าในหน้า Admin.
        </p>
    </div>
<?php endif; ?>