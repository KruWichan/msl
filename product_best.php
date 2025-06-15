<?php
// บรรทัดเหล่านี้ควรจะอยู่ในไฟล์หลัก (เช่น index.php) ที่เรียก include 'product_best.php'
// เพื่อหลีกเลี่ยงการเรียกซ้ำและปัญหา session
/*
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
ini_set('display_errors', 1); // เปลี่ยนเป็น 0 ใน Production
error_reporting(E_ALL);
require 'includes/db.php'; // ตรวจสอบ Path ให้ถูกต้อง
*/

// หากไฟล์นี้ถูก include ในไฟล์หลักที่กำหนดค่าเหล่านี้ไว้แล้ว
// ก็ไม่จำเป็นต้องใส่ session_start, ini_set, error_reporting, require 'includes/db.php' ซ้ำ
// แต่ต้องแน่ใจว่าตัวแปร $pdo ถูกส่งมาหรือเป็น global
// คุณใช้ global $pdo; ไว้แล้ว ซึ่งถูกต้องครับ

global $pdo; // เพื่อให้แน่ใจว่า $pdo สามารถใช้งานได้ใน scope นี้

// ดึงข้อความแจ้งเตือนจาก session (ถ้ามี)
// เนื่องจาก product_best.php อาจจะถูก include ในหน้าอื่น
// การแสดงข้อความแจ้งเตือนควรจะทำในไฟล์หลัก หรือใน header.php ที่ถูก include ทุกหน้า
// แต่เพื่อความสมบูรณ์ในการสาธิต ผมจะใส่ไว้ใน section นี้ก่อน
$message = null;
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']); // ลบข้อความหลังจากดึงมาแล้ว
}

// ต้องมี function explodeTags() หากใช้
// ถ้าไม่มีไฟล์นี้ ให้ลบบรรทัดนี้ หรือสร้างไฟล์ helpers.php
require_once 'includes/helpers.php'; // ตรวจสอบ Path ให้ถูกต้อง

// โหลด tag สี
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


// ดึงแท็กสินค้าที่ตั้งไว้ใน site_settings
$settings = [];
try {
    $settings = $pdo->query("SELECT * FROM site_settings LIMIT 1")->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Database error fetching site settings: " . $e->getMessage());
}
$featured_tag_ids = $settings['featured_tag_ids'] ?? '';

$tagged_products = [];

if (!empty($featured_tag_ids)) {
    $tagIds = array_filter(array_map('intval', explode(',', $featured_tag_ids)));
    if (count($tagIds) > 0) {
        $placeholders = implode(',', array_fill(0, count($tagIds), '?'));
        try {
            $stmt = $pdo->prepare("
                SELECT p.id, p.name, p.description, p.price, p.sale_price, p.image, 
                       GROUP_CONCAT(t.name SEPARATOR ',') AS tag_names
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
        } catch (PDOException $e) {
            error_log("Database error fetching tagged products: " . $e->getMessage());
            // สามารถแสดงข้อความ Error บนหน้าเว็บได้ ถ้าต้องการ
        }
    }
}
?>
<section class="py-12 bg-gray-100">
    <div class="container mx-auto px-4">
    <h2 class="text-xl md:text-2xl font-bold mb-4 text-center text-gray-800">สินค้าแนะนำจากหมอเส็ง</h2>

    <?php if ($message): // แสดงข้อความแจ้งเตือน (Success/Error/Info) ถ้ามี?>
        <div class='p-4 mb-4 rounded-lg <?= ($message['type'] == 'success' ? 'bg-green-100 text-green-800' : ($message['type'] == 'error' ? 'bg-red-100 text-red-800' : 'bg-blue-100 text-blue-800')) ?>'>
            <?= htmlspecialchars($message['text']) ?>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-2 lg:grid-cols-4 gap-4">

        <?php foreach ($tagged_products as $p): 
            // กำหนดราคาที่ใช้แสดงผล
            $display_price = $p['price'];
            $has_sale_price = false;
            if ($p['sale_price'] > 0 && $p['sale_price'] < $p['price']) {
                $display_price = $p['sale_price'];
                $has_sale_price = true;
            }
        ?>

            <div class="relative bg-white rounded-2xl shadow-md hover:shadow-xl transition duration-300 overflow-hidden group flex flex-col">
                <a href="product_detail.php?id=<?= $p['id'] ?>" class="block">

                    <div class="absolute top-0 left-0 p-2 z-10 flex flex-col items-start">
                        <?php
                        // ตรวจสอบว่า tag_names มีอยู่และไม่ว่างเปล่า
                        if (isset($p['tag_names']) && !empty($p['tag_names'])) {
                            // ตรวจสอบว่าฟังก์ชัน explodeTags() มีอยู่จริง
                            if (function_exists('explodeTags')) {
                                $tagsForProduct = explodeTags($p['tag_names']);
                                foreach ($tagsForProduct as $tagName):
                                    $bgColor = $tagColors[$tagName] ?? '#999999'; // สีพื้นหลังจากฐานข้อมูลหรือสีเทา default
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
                    // $p['image'] เก็บแค่ชื่อไฟล์ (เช่น '68370884aeece.jpg')
                    // โฟลเดอร์รูปภาพคือ /public_html/home/uploads/
                    $html_image_src = 'uploads/' . $p['image'];
                    $absolute_image_path_for_check = getcwd() . '/' . $html_image_src; 
                    ?>
                    <img src="<?= file_exists($absolute_image_path_for_check) ? htmlspecialchars($html_image_src) : 'https://via.placeholder.com/400x300?text=No+Image' ?>" 
                         alt="<?= htmlspecialchars($p['name']) ?>" 
                         class="w-full h-48 object-cover">
                    <div class="p-4 flex-grow"> <h3 class="text-lg font-semibold line-clamp-2"><?= htmlspecialchars($p['name']) ?></h3>
                        <div class="mt-1 mb-2 text-sm text-gray-600 line-clamp-2"><?= htmlspecialchars($p['description']) ?></div>
                        <div class="mb-2">
                            <?php if ($has_sale_price): ?>
                                <span class="text-red-500 font-bold mr-2 text-lg"><?= number_format($display_price) ?> บาท</span>
                                <span class="line-through text-gray-400 text-sm"><?= number_format($p['price']) ?> บาท</span>
                            <?php else: ?>
                                <span class="text-gray-800 font-bold text-lg"><?= number_format($display_price) ?> บาท</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </a>
                <div class="p-4 pt-0"> <form action="add_to_cart.php" method="POST" class="flex items-center space-x-2">
                        <input type="hidden" name="product_id" value="<?= htmlspecialchars($p['id']) ?>">
                        <input type="hidden" name="action" value="add">
                        
                        <input type="number" name="quantity" value="1" min="1" 
                               class="w-20 p-2 border border-gray-300 rounded-md text-center text-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                        
                        <button type="submit" class="flex-grow bg-green-600 text-white px-3 py-2 rounded-lg text-sm hover:bg-green-700 transition duration-200">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 inline-block -mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
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
    </div>
</section>