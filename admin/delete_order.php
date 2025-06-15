<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}
require '../includes/db.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die('รหัสคำสั่งซื้อไม่ถูกต้อง');
}

$order_id = (int)$_GET['id'];

try {
    $pdo->beginTransaction();

    // ลบรายการสินค้าในคำสั่งซื้อนี้ก่อน (ถ้ามีความสัมพันธ์ foreign key)
    $stmt_items = $pdo->prepare("DELETE FROM order_items WHERE order_id = :order_id");
    $stmt_items->execute(['order_id' => $order_id]);

    // ลบคำสั่งซื้อ
    $stmt_order = $pdo->prepare("DELETE FROM orders WHERE id = :id");
    $stmt_order->execute(['id' => $order_id]);

    $pdo->commit();

    header("Location: manage_orders.php");
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    echo "เกิดข้อผิดพลาดในการลบ: " . $e->getMessage();
}
