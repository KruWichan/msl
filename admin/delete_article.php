<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}
require '../includes/db.php';

// ตรวจสอบว่ามี ID ที่จะลบหรือไม่
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("ไม่พบรหัสบทความที่ต้องการลบ");
}

$id = (int) $_GET['id'];

// ดึงชื่อไฟล์ภาพก่อนลบ (ถ้ามี)
$stmt = $pdo->prepare("SELECT image FROM articles WHERE id = :id");
$stmt->execute([':id' => $id]);
$article = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$article) {
    die("ไม่พบบทความนี้ในระบบ");
}

// ถ้ามีภาพประกอบ ลบไฟล์ภาพออกจากเซิร์ฟเวอร์
if (!empty($article['image']) && file_exists(__DIR__ . '/' . $article['image'])) {
    unlink(__DIR__ . '/' . $article['image']);
}

// ลบบทความจากฐานข้อมูล
$stmt = $pdo->prepare("DELETE FROM articles WHERE id = :id");
$stmt->execute([':id' => $id]);

// กลับไปยังหน้าจัดการบทความ
header("Location: manage_articles.php?deleted=1");
exit;
