<?php
$host = "localhost";
$dbname = "morseng2_msl";
$username = "morseng2_msl";
$password = "Lx5FQbrZqnrMMgMyvzZh";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("ไม่สามารถเชื่อมต่อฐานข้อมูล: " . $e->getMessage());
}
?>