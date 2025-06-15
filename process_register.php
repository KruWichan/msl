<?php
// process_register.php
session_start();
require 'includes/db.php';

$username = $_POST['username'] ?? '';
$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';

if ($username && $email && $password) {
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, 'user')");
    $stmt->execute([$username, $email, $hashed_password]);
    $_SESSION['login_error'] = "สมัครสมาชิกสำเร็จ กรุณาเข้าสู่ระบบ";
    header("Location: login.php");
    exit;
} else {
    $_SESSION['login_error'] = "กรุณากรอกข้อมูลให้ครบ";
    header("Location: register.php");
    exit;
}

