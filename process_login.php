<?php
session_start();
require 'includes/db.php';

$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';
$remember = isset($_POST['remember']);

$stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch();

if ($user && password_verify($password, $user['password'])) {
    $_SESSION['user'] = [
        'id' => $user['id'],
        'username' => $user['username'],
        'email' => $user['email'],
        'role' => $user['role']
    ];

    // จำการเข้าสู่ระบบ
    if ($remember) {
        $token = bin2hex(random_bytes(32));
        setcookie("remember_token", $token, time() + (86400 * 30), "/");
        $pdo->prepare("UPDATE users SET remember_token = ? WHERE id = ?")->execute([$token, $user['id']]);
    }

    // ไม่ต้องแยกปลายทางแล้ว
    header("Location: index.php");
    exit;
} else {
    $_SESSION['login_error'] = 'อีเมลหรือรหัสผ่านไม่ถูกต้อง';
    header("Location: login.php");
    exit;
}
