<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}
require '../includes/db.php';

if (!isset($_GET['id'])) {
    // ถ้าไม่มี id ส่งกลับหน้า manage_users.php
    header('Location: manage_users.php');
    exit;
}

$id = (int)$_GET['id'];

try {
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$id]);

    // ลบเสร็จแล้ว redirect กลับไปหน้า manage_users.php
    header('Location: manage_users.php?msg=deleted');
    exit;
} catch (PDOException $e) {
    // ถ้ามีข้อผิดพลาดแสดงข้อความ
    echo "เกิดข้อผิดพลาด: " . $e->getMessage();
}
?>
