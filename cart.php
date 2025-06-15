<?php
// ใน cart.php

// เริ่ม session อย่างปลอดภัย
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// **ปิดการแสดงข้อผิดพลาด PHP สำหรับ Production**
// ถ้าแท็กแสดงแล้ว ให้เปลี่ยน display_errors เป็น 0
ini_set('display_errors', 1); // เปิดไว้เพื่อดูข้อผิดพลาดที่อาจเกิดขึ้น
error_reporting(E_ALL); 

// Path เชื่อมต่อฐานข้อมูล: db.php อยู่ใน public_html/home/includes/
require 'includes/db.php'; 

// **สำคัญ:** ตรวจสอบให้แน่ใจว่าไฟล์ helpers.php มีอยู่จริงและ Path ถูกต้อง
// ถ้า helpers.php อยู่ในโฟลเดอร์ includes เดียวกันกับ db.php
if (file_exists('includes/helpers.php')) {
    require_once 'includes/helpers.php';
} else {
    // ถ้า helpers.php ไม่พบ ให้แจ้งเตือนและสร้างฟังก์ชัน fallback
    // คุณควรตรวจสอบ Path ของ includes/helpers.php บนเซิร์ฟเวอร์ของคุณ
    echo "<div style='color: red; font-weight: bold;'>ERROR: helpers.php not found. Please check 'includes/helpers.php' path.</div>";
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
    error_log("Database error fetching product tags in cart.php: " . $e->getMessage());
    // คุณอาจจะแสดงข้อความ error ตรงนี้ในโหมด debug
    // echo "<p style='color: red;'>Database error loading tag colors: " . htmlspecialchars($e->getMessage()) . "</p>";
}


$total_cart_amount = 0; // ราคารวมทั้งหมดในตะกร้า
$total_cart_items = 0; // จำนวนสินค้ารวมทั้งหมดในตะกร้า (นับเป็นชิ้น ไม่ใช่ประเภทสินค้า)

$cart_items = $_SESSION['cart'] ?? []; // ดึงตะกร้าสินค้าจาก Session

// *** DEBUGGING: แสดง Content ของ $_SESSION['cart'] ทั้งหมด (ยังคงแสดงไว้เพื่อยืนยัน) ***
// echo "<h2 style='color: blue; font-weight: bold;'>DEBUG: Full Content of \$_SESSION['cart'] (for tags check):</h2>";
// echo "<pre style='color: blue; font-weight: bold; background-color: #f0f8ff; border: 1px solid blue; padding: 10px;'>";
// var_dump($_SESSION['cart']);
// echo "</pre>";
// ************************************************************************************

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ตะกร้าสินค้า - ชื่อเว็บไซต์ของคุณ</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .cart-item-image {
            width: 80px; 
            height: 80px;
            object-fit: cover;
            border-radius: 0.25rem;
        }
    </style>
</head>
<body class="bg-gray-100 font-sans leading-normal tracking-normal">

    <?php include 'header.php'; ?>

    <div class="container mx-auto px-4 py-8 mt-12">
        <h1 class="text-4xl font-extrabold text-gray-800 mb-8 text-center">ตะกร้าสินค้าของคุณ</h1>

        <?php
        // แสดงข้อความแจ้งเตือน (จาก add_to_cart.php)
        if (isset($_SESSION['message'])) {
            $msg_type = $_SESSION['message']['type'];
            $msg_text = $_SESSION['message']['text'];
            echo "<div class='p-4 mb-4 rounded-lg " . ($msg_type == 'success' ? 'bg-green-100 text-green-800' : ($msg_type == 'error' ? 'bg-red-100 text-red-800' : 'bg-blue-100 text-blue-800')) . "'>" . htmlspecialchars($msg_text) . "</div>";
            unset($_SESSION['message']); // ลบข้อความหลังแสดงแล้ว
        }
        ?>

        <?php if (!empty($cart_items)): ?>
            <div class="bg-white rounded-lg shadow-xl p-6 md:p-8">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">สินค้า</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ราคา</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">จำนวน</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">รวม</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"></th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($cart_items as $item_id => $item):
                                $item_total = $item['price'] * $item['quantity'];
                                $total_cart_amount += $item_total;
                                $total_cart_items += $item['quantity'];

                                $image_full_path_from_session = $item['image'] ?? ''; 
                                
                                $html_image_src = '';
                                if (!empty($image_full_path_from_session) && is_string($image_full_path_from_session)) {
                                    $html_image_src = htmlspecialchars($image_full_path_from_session); 
                                } else {
                                    $html_image_src = 'https://via.placeholder.com/80x80?text=No+Image'; 
                                }
                                
                                $absolute_image_path_for_check = '';
                                if (!empty($image_full_path_from_session) && is_string($image_full_path_from_session)) {
                                    $absolute_image_path_for_check = getcwd() . DIRECTORY_SEPARATOR . $image_full_path_from_session; 
                                }
                            ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0">
                                            <?php
                                            if (!empty($image_full_path_from_session) && file_exists($absolute_image_path_for_check)):
                                            ?>
                                                <img class="cart-item-image" src="<?= $html_image_src ?>" alt="<?= htmlspecialchars($item['name']) ?>">
                                            <?php else: ?>
                                                <div class="cart-item-image bg-gray-200 flex items-center justify-center text-gray-500 text-xs text-center p-1">ไม่พบรูปภาพ</div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($item['name']) ?></div>
                                            <div class="text-xs text-gray-500">
                                                <?php
                                                if (isset($item['description']) && !empty($item['description'])) {
                                                    echo mb_substr(htmlspecialchars($item['description']), 0, 50, 'UTF-8') . (mb_strlen($item['description'], 'UTF-8') > 50 ? '...' : ''); 
                                                } else {
                                                    echo "ไม่มีคำอธิบายสินค้า"; 
                                                }
                                                ?>
                                            </div>
                                            
                                            <?php 
                                            // **โค้ดแสดงแท็กสินค้า**
                                            // ตรวจสอบว่ามีแท็กใน Session และเป็น string ที่ไม่ว่างเปล่า
                                            // และตรวจสอบว่าฟังก์ชัน explodeTags() มีอยู่จริง (เพื่อความปลอดภัย)
                                            if (isset($item['tags']) && !empty($item['tags']) && is_string($item['tags']) && function_exists('explodeTags')): 
                                                $tags_array = explodeTags($item['tags']); 
                                            ?>
                                            <div class="mt-2 flex flex-wrap gap-1">
                                                <?php foreach ($tags_array as $tag_name): 
                                                    $trimmed_tag = trim($tag_name); 
                                                    if (!empty($trimmed_tag)):
                                                        // ใช้สีจาก $tagColors ที่โหลดมาจากฐานข้อมูล
                                                        $bgColor = $tagColors[$trimmed_tag] ?? '#999999'; // สีเทาเริ่มต้นถ้าไม่พบสีใน DB
                                                ?>
                                                    <span style="background-color: <?= htmlspecialchars($bgColor) ?>;"
                                                        class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium text-white">
                                                        <?= htmlspecialchars($trimmed_tag) ?>
                                                    </span>
                                                <?php 
                                                    endif;
                                                endforeach; 
                                                ?>
                                            </div>
                                            <?php endif; ?>

                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">฿<?= number_format($item['price'], 2) ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <form action="add_to_cart.php" method="POST" class="flex items-center">
                                        <input type="hidden" name="product_id" value="<?= htmlspecialchars($item['product_id']) ?>">
                                        <input type="hidden" name="action" value="update">
                                        <input type="number" name="quantity" value="<?= htmlspecialchars($item['quantity']) ?>" 
                                            min="1" class="w-20 p-2 border border-gray-300 rounded-md text-center text-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                        <button type="submit" class="ml-2 bg-blue-500 text-white px-3 py-2 rounded-md text-sm hover:bg-blue-600 transition duration-200">
                                            อัปเดต
                                        </button>
                                    </form>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">฿<?= number_format($item_total, 2) ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <form action="add_to_cart.php" method="POST">
                                        <input type="hidden" name="product_id" value="<?= htmlspecialchars($item['product_id']) ?>">
                                        <input type="hidden" name="action" value="remove">
                                        <button type="submit" class="text-red-600 hover:text-red-900 transition duration-200">
                                            ลบ
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="mt-8 flex justify-end items-center border-t pt-4">
                    <div class="text-right">
                        <p class="text-xl font-semibold text-gray-800">
                            จำนวนสินค้าทั้งหมด: <span class="text-blue-600"><?= $total_cart_items ?> ชิ้น</span>
                        </p>
                        <p class="text-3xl font-bold text-gray-900 mt-2">
                            รวมทั้งหมด: <span class="text-blue-700">฿<?= number_format($total_cart_amount, 2) ?></span>
                        </p>
                        <div class="mt-6 flex justify-end space-x-3">
                            <a href="all_products.php" class="inline-flex items-center px-6 py-3 border border-gray-300 shadow-sm text-base font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                เลือกซื้อสินค้าต่อ
                            </a>
                            <form action="add_to_cart.php" method="POST" onsubmit="return confirm('คุณแน่ใจหรือไม่ที่ต้องการล้างตะกร้าทั้งหมด?');">
                                <input type="hidden" name="action" value="clear_all">
                                <button type="submit" class="inline-flex items-center px-6 py-3 border border-transparent shadow-sm text-base font-medium rounded-md text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                                    ล้างตะกร้า
                                </button>
                            </form>
                            <a href="checkout.php" class="inline-flex items-center px-6 py-3 border border-transparent shadow-sm text-base font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                ดำเนินการสั่งซื้อ
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="bg-white rounded-lg shadow-xl p-8 text-center">
                <h2 class="text-2xl font-bold text-gray-800 mb-4">ตะกร้าสินค้าของคุณว่างเปล่า</h2>
                <p class="text-gray-600 mb-6">เริ่มเลือกซื้อสินค้าที่คุณสนใจได้เลย!</p>
                <div class="flex flex-col sm:flex-row justify-center gap-3">
                    <a href="all_products.php" class="inline-flex items-center px-6 py-3 border border-transparent shadow-sm text-base font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        ไปที่หน้ารายการสินค้า
                    </a>
                    <a href="my_orders.php" class="inline-flex items-center px-6 py-3 border border-transparent shadow-sm text-base font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                        ดูคำสั่งซื้อที่ผ่านมาของคุณ
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <?php include 'footer.php'; ?>

</body>
</html>