<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}
require '../includes/db.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id > 0) {
    // ลบรูปภาพไฟล์จริง
    $stmt = $pdo->prepare("SELECT filename FROM product_images WHERE product_id = ?");
    $stmt->execute([$id]);
    foreach ($stmt as $img) {
        $file = '../uploads/products/' . $img['filename'];
        if (file_exists($file)) unlink($file);
    }
    // ลบข้อมูลใน DB
    $pdo->prepare("DELETE FROM product_images WHERE product_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM product_tag_map WHERE product_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM products WHERE id = ?")->execute([$id]);
}
header("Location: manage_products.php");
exit;
