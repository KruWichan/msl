<?php
// ใน all_products.php

// เริ่ม session อย่างปลอดภัย
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// เปิดการแสดงข้อผิดพลาด PHP สำหรับการพัฒนา (ควรปิดในการใช้งานจริงบน Production Server)
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Path เชื่อมต่อฐานข้อมูล: ไฟล์ db.php อยู่ใน public_html/home/includes/
// ถ้า all_products.php อยู่ใน public_html/home/ ก็ใช้ includes/db.php ได้เลย
require 'includes/db.php'; 

// ต้องมี function explodeTags() หากใช้ (จาก helpers.php)
// ตรวจสอบ Path ให้ถูกต้อง
if (file_exists('includes/helpers.php')) {
    require_once 'includes/helpers.php';
} else {
    // หากไม่พบ helpers.php ให้สร้างฟังก์ชันพื้นฐานเพื่อป้องกัน Fatal Error
    if (!function_exists('explodeTags')) {
        function explodeTags($tagString) {
            return array_filter(array_map('trim', explode(',', $tagString)));
        }
    }
}

// โหลด tag สีจากฐานข้อมูล
$tagColors = [];
try {
    $tags = $pdo->query("SELECT id, name, slug, color FROM product_tags")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($tags as $tag) {
        $tagColors[$tag['name']] = $tag['color'];
    }
} catch (PDOException $e) {
    error_log("Database error fetching product tags: " . $e->getMessage());
    // ไม่ต้องแสดง error หน้าเว็บใน production
}

// --- การแบ่งหน้า (Pagination) ---
$products_per_page = 12; // กำหนดจำนวนสินค้าที่จะแสดงต่อหน้า
$current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $products_per_page; // คำนวณค่า offset สำหรับ SQL LIMIT

// 1. นับจำนวนสินค้าทั้งหมดในฐานข้อมูล
$total_products = 0; 
try {
    $stmt_count = $pdo->prepare("SELECT COUNT(*) FROM products");
    $stmt_count->execute();
    $total_products = $stmt_count->fetchColumn();
} catch (PDOException $e) {
    error_log("Database error counting products: " . $e->getMessage());
    echo "<div class='p-4 mb-4 rounded-lg bg-red-100 text-red-800'>
            **Database Error (Count Products):** " . htmlspecialchars($e->getMessage()) . "
          </div>";
    $total_products = 0; 
}

// 2. คำนวณจำนวนหน้ารวมทั้งหมด
$total_pages = ceil($total_products / $products_per_page);
if ($total_pages == 0) { 
    $total_pages = 1;
}

// 3. ดึงข้อมูลสินค้าสำหรับหน้าปัจจุบันจากฐานข้อมูล พร้อมแท็ก
$products = []; 
try {
    $stmt_products = $pdo->prepare("
        SELECT 
            p.id, 
            p.name, 
            p.price, 
            p.sale_price, 
            p.image, 
            p.description, 
            p.category_id, 
            pc.name AS category_name,
            GROUP_CONCAT(t.name SEPARATOR ',') AS tag_names
        FROM 
            products p
        LEFT JOIN 
            product_categories pc ON p.category_id = pc.id
        LEFT JOIN 
            product_tag_map ptm ON p.id = ptm.product_id
        LEFT JOIN 
            product_tags t ON ptm.tag_id = t.id
        GROUP BY 
            p.id
        ORDER BY 
            p.created_at DESC, p.id DESC
        LIMIT :limit OFFSET :offset
    ");
    $stmt_products->bindParam(':limit', $products_per_page, PDO::PARAM_INT);
    $stmt_products->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt_products->execute();
    $products = $stmt_products->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Database error fetching products: " . $e->getMessage());
    echo "<div class='p-4 mb-4 rounded-lg bg-red-100 text-red-800'>
            **Database Error (Fetch Products):** " . htmlspecialchars($e->getMessage()) . "
          </div>";
}

// ดึงข้อความแจ้งเตือนจาก session (ถ้ามี)
$message = null;
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']); 
}

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>สินค้าทั้งหมด - ชื่อเว็บไซต์ของคุณ</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* CSS เพิ่มเติมจาก product_best.php */
        /* อาจจะต้องปรับ margin-top ของ body ถ้ามี fixed header */
        body {
            /* margin-top: 64px; */ /* สมมติว่า header สูง 64px (h-16) */
        }
        .product-image-container {
            width: 100%;
            /* padding-top: 75%; /* 4:3 Aspect Ratio */
            /* position: relative; */
            /* overflow: hidden; */
            height: 192px; /* Fixed height for image (48*4) to match product_best.php */
        }
        .product-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }
        /* Tailwind's line-clamp utility might need a plugin if not working */
        /* If line-clamp-2 / line-clamp-3 doesn't work, you might need to add this to your tailwind.config.js plugins: */
        /* plugins: [require('@tailwindcss/line-clamp')], */
        /* Or use custom CSS for line-clamp: */
        .line-clamp-2 {
            overflow: hidden;
            display: -webkit-box;
            -webkit-box-orient: vertical;
            -webkit-line-clamp: 2;
        }
        .line-clamp-3 {
            overflow: hidden;
            display: -webkit-box;
            -webkit-box-orient: vertical;
            -webkit-line-clamp: 3;
        }
    </style>
</head>
<body class="bg-gray-100 font-sans leading-normal tracking-normal">

    <?php include 'header.php'; ?> 

    <div class="container mx-auto px-4 py-8 mt-12">
        <h1 class="text-4xl font-extrabold text-gray-800 mb-8 text-center">สินค้าทั้งหมด</h1>

        <?php if ($message): // แสดงข้อความแจ้งเตือน ?>
            <div class='p-4 mb-4 rounded-lg <?= ($message['type'] == 'success' ? 'bg-green-100 text-green-800' : ($message['type'] == 'error' ? 'bg-red-100 text-red-800' : 'bg-blue-100 text-blue-800')) ?>'>
                <?= htmlspecialchars($message['text']) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($products)): ?>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <?php foreach ($products as $p): 
                    // กำหนดราคาที่ใช้แสดงผล
                    $display_price = $p['price'];
                    $has_sale_price = false;
                    if (isset($p['sale_price']) && $p['sale_price'] > 0 && $p['sale_price'] < $p['price']) {
                        $display_price = $p['sale_price'];
                        $has_sale_price = true;
                    }
                ?>
                    <div class="relative bg-white rounded-2xl shadow-md hover:shadow-xl transition duration-300 overflow-hidden group flex flex-col">
                        <a href="product_detail.php?id=<?= htmlspecialchars($p['id']) ?>" class="block">
                            <div class="absolute top-0 left-0 p-2 z-10 flex flex-col items-start">
                                <?php
                                // แสดง Tag สินค้า (ถ้ามี)
                                if (isset($p['tag_names']) && !empty($p['tag_names'])) {
                                    if (function_exists('explodeTags')) {
                                        $tagsForProduct = explodeTags($p['tag_names']);
                                        foreach ($tagsForProduct as $tagName):
                                            $bgColor = $tagColors[$tagName] ?? '#999999'; 
                                ?>
                                            <span style="background-color: <?= htmlspecialchars($bgColor) ?>;"
                                                  class="text-white text-xs px-2 py-1 rounded mb-1 inline-block">
                                                <?= htmlspecialchars($tagName) ?>
                                            </span>
                                <?php
                                        endforeach;
                                    }
                                }
                                ?>
                            </div>
                            <?php
                            // Path รูปภาพสำหรับแสดงผล (src) และตรวจสอบ file_exists:
                            $html_image_src = 'uploads/' . ($p['image'] ?? 'placeholder.jpg'); // ใช้ placeholder หากไม่มีรูปภาพ
                            $absolute_image_path_for_check = getcwd() . '/' . $html_image_src; 
                            ?>
                            <div class="product-image-container">
                                <img src="<?= file_exists($absolute_image_path_for_check) && !empty($p['image']) ? htmlspecialchars($html_image_src) : 'https://via.placeholder.com/400x300?text=No+Image' ?>" 
                                     alt="<?= htmlspecialchars($p['name']) ?>" 
                                     class="product-image">
                            </div>
                            <div class="p-4 flex-grow"> 
                                <h3 class="text-lg font-semibold line-clamp-2"><?= htmlspecialchars($p['name']) ?></h3>
                                <div class="mt-1 mb-2 text-sm text-gray-600 line-clamp-2"><?= htmlspecialchars($p['description'] ?? 'ไม่มีคำอธิบายสำหรับสินค้านี้') ?></div>
                                <div class="mb-2 flex items-baseline">
                                    <?php if ($has_sale_price): ?>
                                        <span class="text-red-500 font-bold mr-2 text-lg"><?= number_format($display_price, 2) ?> บาท</span>
                                        <span class="line-through text-gray-400 text-sm"><?= number_format($p['price'], 2) ?> บาท</span>
                                    <?php else: ?>
                                        <span class="text-gray-800 font-bold text-lg"><?= number_format($display_price, 2) ?> บาท</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </a>
                        <div class="p-4 pt-0"> 
                            <form action="add_to_cart.php" method="POST" class="flex items-center space-x-2">
                                <input type="hidden" name="product_id" value="<?= htmlspecialchars($p['id']) ?>">
                                <input type="hidden" name="action" value="add">
                                
                                <input type="number" name="quantity" value="1" min="1" 
                                       class="w-20 p-2 border border-gray-300 rounded-md text-center text-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                
                                <button type="submit" class="flex-grow bg-green-600 text-white px-3 py-2 rounded-lg text-sm hover:bg-green-700 transition duration-200 flex items-center justify-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 inline-block -mt-0.5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                                    </svg>
                                    หยิบใส่ตะกร้า
                                </button>
                            </form>
                            <a href="product_detail.php?id=<?= htmlspecialchars($p['id']) ?>" class="block w-full text-center bg-blue-500 hover:bg-blue-600 text-white text-sm py-2 rounded-lg transition mt-2">
                                ดูรายละเอียด
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="flex justify-center items-center space-x-2 mt-10">
                <?php if ($current_page > 1): ?>
                    <a href="?page=<?= $current_page - 1 ?>" class="px-4 py-2 rounded-lg bg-gray-300 text-gray-700 hover:bg-gray-400 transition duration-200">
                        ก่อนหน้า
                    </a>
                <?php endif; ?>

                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="?page=<?= $i ?>" 
                       class="px-4 py-2 rounded-lg <?= ($i === $current_page) ? 'bg-blue-600 text-white' : 'bg-gray-300 text-gray-700 hover:bg-gray-400' ?> transition duration-200">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>

                <?php if ($current_page < $total_pages): ?>
                    <a href="?page=<?= $current_page + 1 ?>" class="px-4 py-2 rounded-lg bg-gray-300 text-gray-700 hover:bg-gray-400 transition duration-200">
                        ถัดไป
                    </a>
                <?php endif; ?>
            </div>

        <?php else: ?>
            <div class="text-center p-8 text-gray-500 text-lg">
                <p>ยังไม่มีสินค้าที่แสดงผลในขณะนี้.</p>
            </div>
        <?php endif; ?>
    </div>

    <?php include 'footer.php'; ?>

</body>
</html>