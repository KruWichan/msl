<?php
// เริ่ม session อย่างปลอดภัย เพื่อป้องกัน Notice "session already active"
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

ini_set('display_errors', 1);
error_reporting(E_ALL);

// Path เชื่อมต่อฐานข้อมูล: db.php อยู่ใน home/includes/
require 'includes/db.php'; 

$product = null; // กำหนดค่าเริ่มต้นเป็น null

// ตรวจสอบว่ามีการส่งค่า ID สินค้ามาหรือไม่
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $product_id = (int)$_GET['id'];

    try {
        // ดึงข้อมูลสินค้าจากฐานข้อมูล พร้อมชื่อหมวดหมู่
        // เพิ่ม sale_price เข้ามาใน query ด้วย
        $stmt = $pdo->prepare("
            SELECT 
                p.id, 
                p.name, 
                p.slug, 
                p.description, 
                p.price, 
                p.sale_price,  -- <<< มีอยู่แล้ว เยี่ยมเลย!
                p.image, 
                p.category_id,
                pc.name AS category_name
            FROM 
                products p
            LEFT JOIN 
                product_categories pc ON p.category_id = pc.id
            WHERE 
                p.id = :id
            LIMIT 1
        ");
        $stmt->bindParam(':id', $product_id, PDO::PARAM_INT);
        $stmt->execute();
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        error_log("Database error fetching product detail: " . $e->getMessage());
        // สามารถแสดงข้อความ Error บนหน้าเว็บได้ ถ้าต้องการ
        // echo "<p class='text-center p-4 bg-red-100 text-red-800 rounded'>เกิดข้อผิดพลาดในการดึงข้อมูลสินค้า กรุณาลองใหม่อีกครั้ง</p>";
    }
}

// ดึงข้อความแจ้งเตือนจาก session (ถ้ามี)
$message = null;
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']); // ลบข้อความหลังจากดึงมาแล้ว
}

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $product ? htmlspecialchars($product['name']) . ' - ชื่อเว็บไซต์ของคุณ' : 'ไม่พบสินค้า - ชื่อเว็บไซต์ของคุณ' ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* CSS เพิ่มเติม */
        .product-image-container {
            width: 100%;
            padding-top: 75%; /* 4:3 Aspect Ratio */
            position: relative;
            overflow: hidden;
            border-radius: 0.5rem; /* rounded-lg */
        }
        .product-image {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: contain; /* ใช้ contain เพื่อให้รูปภาพแสดงเต็ม ไม่ถูก crop */
            background-color: #f0f0f0; /* สีพื้นหลังเมื่อรูปภาพมีสัดส่วนไม่ตรง */
        }
        .description-content img {
            max-width: 100%; /* ทำให้รูปภาพใน description ไม่ล้นกรอบ */
            height: auto;
            display: block;
            margin-top: 1rem;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body class="bg-gray-100 font-sans leading-normal tracking-normal">

    <?php // include 'includes/header.php'; ?> 

    <div class="container mx-auto px-4 py-8 mt-12">
        <nav class="mb-6 text-sm text-gray-600">
            <a href="all_products.php" class="hover:underline text-blue-600">‹ กลับไปหน้าสินค้าทั้งหมด</a>
        </nav>

        <?php if ($message): // แสดงข้อความแจ้งเตือน (Success/Error/Info) ?>
            <div class='p-4 mb-4 rounded-lg <?= ($message['type'] == 'success' ? 'bg-green-100 text-green-800' : ($message['type'] == 'error' ? 'bg-red-100 text-red-800' : 'bg-blue-100 text-blue-800')) ?>'>
                <?= htmlspecialchars($message['text']) ?>
            </div>
        <?php endif; ?>

        <?php if ($product): ?>
            <div class="bg-white rounded-lg shadow-xl p-6 md:p-8 flex flex-col md:flex-row gap-8">
                <div class="md:w-1/2 flex justify-center items-center">
                    <div class="product-image-container">
                        <?php 
                        // Path รูปภาพสำหรับแสดงผล (src) และตรวจสอบ file_exists:
                        $html_image_src = 'uploads/' . $product['image'];
                        $absolute_image_path_for_check = getcwd() . '/' . $html_image_src; 

                        if (!empty($product['image']) && file_exists($absolute_image_path_for_check)): 
                        ?>
                            <img src="<?= htmlspecialchars($html_image_src) ?>" 
                                 alt="<?= htmlspecialchars($product['name']) ?>" 
                                 class="product-image">
                        <?php else: ?>
                            <div class="product-image bg-gray-200 flex items-center justify-center text-gray-500 text-lg font-semibold">
                                ไม่มีรูปภาพ
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="md:w-1/2 flex flex-col">
                    <h1 class="text-4xl font-bold text-gray-900 mb-4"><?= htmlspecialchars($product['name']) ?></h1>
                    
                    <?php if (!empty($product['category_name'])): ?>
                        <p class="text-sm text-gray-600 mb-2">
                            หมวดหมู่: 
                            <a href="products_by_category.php?id=<?= htmlspecialchars($product['category_id'] ?? '') ?>" class="text-blue-500 hover:underline">
                                <?= htmlspecialchars($product['category_name'] ?? 'ไม่ระบุ') ?>
                            </a>
                        </p>
                    <?php endif; ?>

                    <?php 
                    // ตรวจสอบราคาที่จะแสดง: ใช้ sale_price ถ้ามีและน้อยกว่าราคาปกติ
                    $display_price = $product['price'];
                    $has_sale_price = false;
                    if ($product['sale_price'] > 0 && $product['sale_price'] < $product['price']) {
                        $display_price = $product['sale_price'];
                        $has_sale_price = true;
                    }
                    ?>

                    <?php if ($has_sale_price): ?>
                        <p class="text-gray-500 line-through text-lg mb-1">ราคาปกติ: ฿<?= number_format($product['price'], 2) ?></p>
                        <p class="text-blue-700 font-extrabold text-4xl mb-4">ราคาพิเศษ: ฿<?= number_format($display_price, 2) ?></p>
                    <?php else: ?>
                        <p class="text-blue-700 font-extrabold text-4xl mb-4">ราคา: ฿<?= number_format($display_price, 2) ?></p>
                    <?php endif; ?>

                    <div class="text-gray-700 leading-relaxed description-content mt-4">
                        <h2 class="text-2xl font-semibold mb-2">รายละเอียดสินค้า</h2>
                        <?= $product['description'] ? $product['description'] : '<p class="text-gray-500">ไม่มีรายละเอียดเพิ่มเติมสำหรับสินค้านี้.</p>' ?>
                    </div>

                    <div class="mt-8">
                        <form action="add_to_cart.php" method="POST" class="flex items-center space-x-4">
                            <input type="hidden" name="product_id" value="<?= htmlspecialchars($product['id']) ?>">
                            <input type="hidden" name="action" value="add">
                            
                            <label for="quantity" class="text-lg font-semibold text-gray-700">จำนวน:</label>
                            <input type="number" name="quantity" id="quantity" value="1" min="1" 
                                   class="w-24 p-3 border border-gray-300 rounded-lg text-center text-lg focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                            
                            <button type="submit" class="bg-green-600 text-white px-6 py-3 rounded-lg text-lg font-semibold hover:bg-green-700 transition duration-200 shadow-md">
                                เพิ่มลงตะกร้า
                            </button>
                        </form>
                    </div>
                </div>
            </div>

        <?php else: ?>
            <div class="bg-white rounded-lg shadow-xl p-8 text-center">
                <h2 class="text-3xl font-bold text-gray-800 mb-4">ขออภัย, ไม่พบสินค้าที่คุณกำลังมองหา</h2>
                <p class="text-gray-600">สินค้านี้อาจถูกลบไปแล้ว หรือรหัสสินค้าไม่ถูกต้อง</p>
                <a href="all_products.php" class="inline-block mt-6 bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition duration-200">
                    กลับไปหน้าสินค้าทั้งหมด
                </a>
            </div>
        <?php endif; ?>
    </div>

    <?php // include 'includes/footer.php'; ?>

</body>
</html>