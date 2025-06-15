<?php
// ใน add_to_cart.php

// เริ่ม session อย่างปลอดภัย
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

ini_set('display_errors', 1);
error_reporting(E_ALL);

require 'includes/db.php'; 

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    switch ($action) {
        case 'add':
            $product_id = $_POST['product_id'] ?? null;
            $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;

            if ($product_id) {
                try {
                    // ดึงข้อมูลสินค้าจากฐานข้อมูลอีกครั้ง เพื่อความถูกต้องและปลอดภัย
                    // **ปรับปรุง Query เพื่อดึง tag_names มาด้วย**
                    $stmt = $pdo->prepare("
                        SELECT 
                            p.id, 
                            p.name, 
                            p.price, 
                            p.image, 
                            p.description,
                            GROUP_CONCAT(t.name SEPARATOR ',') AS tag_names -- <--- เพิ่มส่วนนี้
                        FROM 
                            products p
                        LEFT JOIN 
                            product_tag_map ptm ON p.id = ptm.product_id
                        LEFT JOIN 
                            product_tags t ON ptm.tag_id = t.id
                        WHERE 
                            p.id = :id
                        GROUP BY 
                            p.id
                    ");
                    $stmt->bindParam(':id', $product_id, PDO::PARAM_INT);
                    $stmt->execute();
                    $product = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($product) {
                        // *** จุดสำคัญที่ปรับปรุง: ทำให้ Path รูปภาพสมบูรณ์ก่อนเก็บใน Session ***
                        $image_filename_from_db = $product['image']; // ได้แค่ชื่อไฟล์ เช่น '683709bf1dac8.jpg'
                        $final_image_path_for_session = '';

                        if (!empty($image_filename_from_db)) {
                            // ตรวจสอบว่ามี 'uploads/' นำหน้าอยู่แล้วหรือไม่
                            // ถ้ายังไม่มี ให้เติม 'uploads/' เข้าไป
                            if (strpos($image_filename_from_db, 'uploads/') === 0) {
                                $final_image_path_for_session = $image_filename_from_db; // มีอยู่แล้ว ไม่ต้องเติม
                            } else {
                                $final_image_path_for_session = 'uploads/' . $image_filename_from_db; // เติม 'uploads/'
                            }
                        }
                        // *********************************************************

                        // ตรวจสอบว่าสินค้ามีอยู่แล้วในตะกร้าหรือไม่
                        if (isset($_SESSION['cart'][$product_id])) {
                            $_SESSION['cart'][$product_id]['quantity'] += $quantity;
                            $_SESSION['message'] = ['type' => 'info', 'text' => htmlspecialchars($product['name']) . ' เพิ่มจำนวนสินค้าแล้ว'];
                        } else {
                            // เพิ่มสินค้าใหม่เข้าไปในตะกร้า
                            $_SESSION['cart'][$product_id] = [
                                'product_id'  => $product_id,
                                'name'        => $product['name'],
                                'price'       => $product['price'],
                                'quantity'    => $quantity,
                                'image'       => $final_image_path_for_session, // ใช้ Path รูปภาพที่สมบูรณ์แล้ว
                                'description' => $product['description'] ?? 'ไม่มีคำอธิบายสินค้า', // ใช้ค่าจาก DB หรือค่าเริ่มต้น
                                'tags'        => $product['tag_names'] ?? '' // <--- **เก็บ tag_names ลงใน Session ตรงนี้**
                            ];
                            $_SESSION['message'] = ['type' => 'success', 'text' => htmlspecialchars($product['name']) . ' ถูกเพิ่มลงในตะกร้าแล้ว'];
                        }
                    } else {
                        $_SESSION['message'] = ['type' => 'error', 'text' => 'ไม่พบสินค้า'];
                    }
                } catch (PDOException $e) {
                    error_log("Error adding to cart: " . $e->getMessage());
                    $_SESSION['message'] = ['type' => 'error', 'text' => 'เกิดข้อผิดพลาดในการเพิ่มสินค้าลงตะกร้า: ' . $e->getMessage()]; // แสดงข้อความ error เพื่อ debug
                }
            } else {
                $_SESSION['message'] = ['type' => 'error', 'text' => 'ไม่ได้ระบุสินค้า'];
            }
            break;

        case 'update':
            $product_id = $_POST['product_id'] ?? null;
            $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;
            if ($product_id && isset($_SESSION['cart'][$product_id])) {
                if ($quantity > 0) {
                    $_SESSION['cart'][$product_id]['quantity'] = $quantity;
                    $_SESSION['message'] = ['type' => 'success', 'text' => 'อัปเดตจำนวนสินค้าแล้ว'];
                } else { // ถ้าจำนวนเป็น 0 หรือน้อยกว่า 0 ให้ลบออกจากตะกร้า
                    unset($_SESSION['cart'][$product_id]);
                    $_SESSION['message'] = ['type' => 'success', 'text' => 'ลบสินค้าออกจากตะกร้าแล้ว'];
                }
            } else {
                $_SESSION['message'] = ['type' => 'error', 'text' => 'ไม่สามารถอัปเดตจำนวนสินค้าได้'];
            }
            break;

        case 'remove':
            $product_id = $_POST['product_id'] ?? null;
            if ($product_id && isset($_SESSION['cart'][$product_id])) {
                unset($_SESSION['cart'][$product_id]);
                $_SESSION['message'] = ['type' => 'success', 'text' => 'ลบสินค้าออกจากตะกร้าแล้ว'];
            } else {
                $_SESSION['message'] = ['type' => 'error', 'text' => 'ไม่สามารถลบสินค้าได้'];
            }
            break;

        case 'clear_all':
            unset($_SESSION['cart']);
            $_SESSION['message'] = ['type' => 'success', 'text' => 'ล้างตะกร้าสินค้าทั้งหมดแล้ว'];
            break;

        default:
            $_SESSION['message'] = ['type' => 'error', 'text' => 'การกระทำไม่ถูกต้อง'];
            break;
    }
}

// Redirect กลับไปหน้า cart.php เสมอ
header('Location: cart.php');
exit();
?>