<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
if (isset($_SESSION['user'])); // ตรวจสอบว่าผู้ใช้เข้าสู่ระบบแล้วหรือไม่

require_once 'includes/db.php';
require_once 'includes/helpers.php'; // ตรวจสอบว่าไฟล์นี้มีอยู่จริง

// ดึงค่าการตั้งค่าจากฐานข้อมูล
$stmt_settings = $pdo->query("SELECT * FROM site_settings WHERE id = 2"); // ใช้ ID 2 ตาม SQL Dump
$settings = $stmt_settings->fetch(PDO::FETCH_ASSOC);

// ตรวจสอบว่า $settings มีข้อมูลหรือไม่ หากไม่มีให้กำหนดค่าเริ่มต้น
// **แก้ไข** บรรทัดนี้: ใช้ $settings ที่ดึงมาจากฐานข้อมูล
if (!$settings) {
    // กำหนดค่าเริ่มต้นถ้าไม่พบข้อมูลในฐานข้อมูล
    $settings = [
        'site_name' => 'MorsengLove',
        'font_family' => 'Sarabun',
        'primary_color' => '#2563eb',
        'logo' => 'uploads/default_logo.png', // เพิ่มค่า logo ถ้าจะใช้งาน
        'product_display_type' => 'all',
        'featured_tag_ids' => '',
        'homepage_banner_grid_id' => null // ใช้ชื่อตัวแปรตาม settings.php
    ];
}

$site_name = $settings['site_name'] ?? 'MorsengLove';
// ตรวจสอบ font_family ให้มั่นใจว่าเป็นหนึ่งในค่าที่อนุญาต
$font = in_array($settings['font_family'] ?? '', ['Sarabun', 'Kanit', 'Prompt', 'Mitr', 'Noto Sans Thai']) ? $settings['font_family'] : 'Sarabun';
$color = $settings['primary_color'] ?? '#2563eb';

// --- แก้ไขตรงนี้: ดึง ID ของ Banner Grid ที่เลือกจาก homepage_banner_grid_id ---
$homepage_banner_grid_id = $settings['homepage_banner_grid_id'] ?? null;

// ดึงข้อมูล Banner Grid และ Banner ที่เกี่ยวข้อง
$banner_grid_to_display = null;
$banners_in_grid = [];

if ($homepage_banner_grid_id) { // ถ้ามีการเลือก Banner Grid ID ไว้
    // ดึงข้อมูล Grid
    $stmt_grid = $pdo->prepare("SELECT * FROM banner_grids WHERE id = ? LIMIT 1");
    $stmt_grid->execute([$homepage_banner_grid_id]);
    $banner_grid_to_display = $stmt_grid->fetch(PDO::FETCH_ASSOC);

    if ($banner_grid_to_display) {
        // ดึง Banner ทั้งหมดที่อยู่ใน Grid นั้น
        $stmt_banners = $pdo->prepare("SELECT b.* FROM banners b JOIN banner_grid_map bgm ON b.id = bgm.banner_id WHERE bgm.grid_id = ? ORDER BY bgm.sort_order ASC");
        $stmt_banners->execute([$homepage_banner_grid_id]);
        $banners_in_grid = $stmt_banners->fetchAll(PDO::FETCH_ASSOC);
    }
}


// ... โค้ดส่วนดึง tagColors และ tags (ไม่มีการเปลี่ยนแปลง)
$tagColors = [];
$tags = $pdo->query("SELECT id, name, slug, color FROM product_tags")->fetchAll(PDO::FETCH_ASSOC);
foreach ($tags as $tag) {
    $tagColors[$tag['name']] = $tag['color'];
}

// ... โค้ดส่วนดึง products และ featured_products (ไม่มีการเปลี่ยนแปลง)
$sql = "
    SELECT p.*, GROUP_CONCAT(t.name SEPARATOR ',') AS tag_names
    FROM products p
    LEFT JOIN product_tag_map ptm ON p.id = ptm.product_id
    LEFT JOIN product_tags t ON ptm.tag_id = t.id
    GROUP BY p.id
    ORDER BY p.created_at DESC
";
$all_products = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

$featured_tag_ids = $settings['featured_tag_ids'] ?? '';
$tagged_products = [];
if (!empty($featured_tag_ids)) {
    $tagIds = array_filter(array_map('intval', explode(',', $featured_tag_ids)));
    if (count($tagIds) > 0) {
        $placeholders = implode(',', array_fill(0, count($tagIds), '?'));
        $stmt = $pdo->prepare("
            SELECT p.*, GROUP_CONCAT(t.name SEPARATOR ',') AS tag_names
            FROM products p
            JOIN product_tag_map ptm ON p.id = ptm.product_id
            JOIN product_tags t ON ptm.tag_id = t.id
            WHERE ptm.tag_id IN ($placeholders)
            GROUP BY p.id
            ORDER BY p.created_at DESC
            LIMIT 12
        ");
        $stmt->execute($tagIds);
        $tagged_products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($site_name) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=<?= str_replace(' ', '+', $font) ?>&display=swap" rel="stylesheet">
<script>
    tailwind.config = {
        theme: {
            extend: {},
        },
        plugins: [require('@tailwindcss/line-clamp')],
    }
</script>
    <style>
        body { font-family: '<?= $font ?>', sans-serif; }
        .primary-text { color: <?= $color ?>; }
        .primary-bg { background-color: <?= $color ?>; }
    </style>
</head>
<body class="bg-gray-50">
    
<?php 
include 'header.php'; 

include 'banner.php'; 

include 'product_best.php'; 

include 'product_all.php'; 

include 'article.php'; 

include 'footer.php'; 
?>