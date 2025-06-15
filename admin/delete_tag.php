<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

require '../includes/db.php';

// ตรวจสอบว่ามีการส่ง ID มาหรือไม่
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $id = $_GET['id'];

    // เตรียมคำสั่ง SQL เพื่อลบข้อมูล
    $stmt = $pdo->prepare("DELETE FROM product_tags WHERE id = ?");
    $stmt->execute([$id]);

    // ส่งกลับไปหน้าเดิมพร้อมข้อความ (ถ้าต้องการสามารถใช้ session flash ได้)
    header("Location: manage_tags.php");
    exit;
} else {
    // ถ้าไม่มี ID หรือ ID ไม่ถูกต้อง ส่งกลับไปหน้าเดิม
    header("Location: manage_tags.php");
    exit;
}
