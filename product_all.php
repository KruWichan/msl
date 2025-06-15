<?php
// บรรทัดเหล่านี้ (session_start, error_reporting, require_once 'includes/db.php', require_once 'includes/helpers.php')
// ควรจะอยู่ในไฟล์หลัก (เช่น index.php หรือ all_products.php ถ้าไฟล์นี้คือไฟล์หลัก)
// ที่เรียก include 'product_all.php' เพื่อหลีกเลี่ยงการเรียกซ้ำและปัญหา session
/*
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
ini_set('display_errors', 1); // เปลี่ยนเป็น 0 ใน Production
error_reporting(E_ALL);
require_once 'includes/db.php'; // ตรวจสอบ Path ให้ถูกต้อง
require_once 'includes/helpers.php'; // ตรวจสอบ Path ให้ถูกต้อง
*/

// สมมติว่า $pdo และฟังก์ชัน explodeTags() พร้อมใช้งานจากไฟล์หลักที่ include นี้

// โหลด tag สี
$tagColors = [];
try {
    // ต้องแน่ใจว่า $pdo เป็น global หรือถูกส่งมาใน scope นี้
    global $pdo; 
    $tags = $pdo->query("SELECT id, name, slug, color FROM product_tags")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($tags as $tag) {
        $tagColors[$tag['name']] = $tag['color'];
    }
} catch (PDOException $e) {
    error_log("Database error fetching product tags in product_all.php: " . $e->getMessage());
    // ไม่ต้องแสดง error หน้าเว็บใน production
}

// ดึงข้อความแจ้งเตือนจาก session (ถ้ามี)
// เนื่องจาก product_all.php อาจจะถูก include ในหน้าอื่น
// การแสดงข้อความแจ้งเตือนควรจะทำในไฟล์หลัก หรือใน header.php ที่ถูก include ทุกหน้า
// แต่เพื่อความสมบูรณ์ในการสาธิต ผมจะใส่ไว้ใน section นี้ก่อน
$message = null;
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']); // ลบข้อความหลังจากดึงมาแล้ว
}

// *** ส่วนนี้เดิมในโค้ดของคุณดูเหมือนจะดึงสินค้าที่ "featured"
// *** ซึ่งอาจจะซ้ำซ้อนกับการดึง "สินค้าทั้งหมด" ($all_products)
// *** ถ้า product_all.php ควรจะแสดง "สินค้าทั้งหมด" จริงๆ ควรจะดึงแบบนี้:
// *** (คุณต้องนำโค้ดการดึง $all_products จากไฟล์หลักมาใส่ หรือให้ไฟล์หลักดึงแล้วส่งมาที่นี่)
/*
$all_products = [];
try {
    $stmt_all_products = $pdo->prepare("
        SELECT p.id, p.name, p.description, p.price, p.sale_price, p.image, 
               GROUP_CONCAT(t.name SEPARATOR ',') AS tag_names
        FROM products p
        LEFT JOIN product_tag_map ptm ON p.id = ptm.product_id
        LEFT JOIN product_tags t ON ptm.tag_id = t.id
        GROUP BY p.id
        ORDER BY p.created_at DESC
    ");
    $stmt_all_products->execute();
    $all_products = $stmt_all_products->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Database error fetching all products in product_all.php: " . $e->getMessage());
}
*/
// ผมจะใช้ $all_products ตามที่คุณใช้ใน foreach loop ด้านล่าง
// และจะละเว้นโค้ดที่ดึง $featured_tag_ids และ $tagged_products ออกไป
// เนื่องจากในส่วน "สินค้าทั้งหมด" ไม่น่าจะใช้ logic ของ featured tags

?>

<section class="py-12 bg-gray-100">
    <div class="container mx-auto px-4">
        <h2 class="text-2xl font-bold mb-8 primary-text text-center">สินค้าทั้งหมด</h2>

        <?php if ($message): // แสดงข้อความแจ้งเตือน (Success/Error/Info) ถ้ามี?>
            <div class='p-4 mb-4 rounded-lg <?= ($message['type'] == 'success' ? 'bg-green-100 text-green-800' : ($message['type'] == 'error' ? 'bg-red-100 text-red-800' : 'bg-blue-100 text-blue-800')) ?>'>
                <?= htmlspecialchars($message['text']) ?>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">

            <?php 
            // ตรวจสอบว่า $all_products มีข้อมูลหรือไม่
            if (empty($all_products)): ?>
                <div class="col-span-full text-center py-8 text-gray-600">
                    ไม่พบสินค้าในระบบ
                </div>
            <?php else: ?>
                <?php foreach ($all_products as $product): 
                    // กำหนดราคาที่ใช้แสดงผล
                    $display_price = $product['price'];
                    $has_sale_price = false;
                    if (isset($product['sale_price']) && $product['sale_price'] > 0 && $product['sale_price'] < $product['price']) {
                        $display_price = $product['sale_price'];
                        $has_sale_price = true;
                    }
                ?>
                    <div class="relative group bg-white rounded-2xl shadow hover:shadow-xl transition duration-300 overflow-hidden flex flex-col">
                        <div class="absolute top-2 left-2 flex flex-col gap-1 z-10">
                            <?php
                            // ตรวจสอบว่า tag_names มีอยู่และไม่ว่างเปล่า
                            if (isset($product['tag_names']) && !empty($product['tag_names'])) {
                                // ตรวจสอบว่าฟังก์ชัน explodeTags() มีอยู่จริง
                                if (function_exists('explodeTags')) {
                                    $tagsForProduct = explodeTags($product['tag_names']);
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
                        $html_image_src = 'uploads/' . ($product['image'] ?? '');
                        $absolute_image_path_for_check = getcwd() . '/' . $html_image_src; 
                        ?>
                        <img src="<?= (!empty($product['image']) && file_exists($absolute_image_path_for_check)) ? htmlspecialchars($html_image_src) : 'https://via.placeholder.com/400x300?text=No+Image' ?>" 
                             class="w-full h-48 object-cover group-hover:scale-105 transition-transform duration-300" 
                             alt="<?= htmlspecialchars($product['name'] ?? 'สินค้า') ?>">
                        
                        <div class="p-4 flex-grow"> <h3 class="text-md font-semibold mb-1"><?= htmlspecialchars($product['name'] ?? 'ชื่อสินค้า') ?></h3>
                            <div class="mt-1 mb-2 text-sm text-gray-600 line-clamp-2"><?= htmlspecialchars($product['description'] ?? '') ?></div>
                            
                            <?php if ($has_sale_price): ?>
                                <div class="text-red-500 font-bold text-lg"><?= number_format($display_price, 2) ?> บาท</div>
                                <div class="text-sm line-through text-gray-400"><?= number_format($product['price'], 2) ?> บาท</div>
                            <?php else: ?>
                                <div class="text-gray-800 font-bold text-lg"><?= number_format($display_price, 2) ?> บาท</div>
                            <?php endif; ?>
                        </div>

                        <div class="p-4 pt-0"> <form action="add_to_cart.php" method="POST" class="flex items-center space-x-2 mb-2">
                                <input type="hidden" name="product_id" value="<?= htmlspecialchars($product['id'] ?? '') ?>">
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
                            <a href="product_detail.php?id=<?= htmlspecialchars($product['id'] ?? '') ?>" class="block w-full text-center bg-blue-500 hover:bg-blue-600 text-white text-sm py-2 rounded-lg transition">
                                ดูรายละเอียดสินค้า
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

        </div>
    </div>
</section>