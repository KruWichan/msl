<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}
require '../includes/db.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$product_id = isset($_GET['product_id']) ? intval($_GET['product_id']) : 0;

if ($id > 0 && $product_id > 0) {
    $stmt = $pdo->prepare("SELECT filename FROM product_images WHERE id = ? AND product_id = ?");
    $stmt->execute([$id, $product_id]);
    $img = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($img) {
        $file = '../uploads/products/' . $img['filename'];
        if (file_exists($file)) unlink($file);
        $pdo->prepare("DELETE FROM product_images WHERE id = ?")->execute([$id]);
    }
}
header("Location: edit_product.php?id=" . $product_id);
exit;
