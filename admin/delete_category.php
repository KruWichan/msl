<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}
require '../includes/db.php';

$id = intval($_GET['id'] ?? 0);
if ($id) {
    $stmt = $pdo->prepare("UPDATE product_categories SET is_deleted = 1, deleted_at = NOW() WHERE id = ?");
    $stmt->execute([$id]);
}
header("Location: manage_categories.php");
exit;
?>
