<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

$host = "localhost"; // ตรวจสอบอีกครั้งว่าโฮสติ้งของคุณใช้ localhost หรือชื่ออื่น
$dbname = "morseng2_home";
$username = "morseng2_morseng2";
$password = "p4j#$6t9*cKy"; // ตรวจสอบว่ารหัสผ่านถูกต้องแน่นอน

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "<h1>เชื่อมต่อฐานข้อมูลสำเร็จ!</h1>";
    echo "<p>เวอร์ชัน MySQL: " . $pdo->query('select version()')->fetchColumn() . "</p>";

    // ลอง Query ข้อมูลเล็กๆ น้อยๆ เพื่อให้แน่ใจว่าทำงานได้จริง
    $stmt = $pdo->query("SELECT COUNT(*) FROM province");
    $provinceCount = $stmt->fetchColumn();
    echo "<p>จำนวนจังหวัดในตาราง 'province': " . $provinceCount . "</p>";

} catch (PDOException $e) {
    die("<h1>ไม่สามารถเชื่อมต่อฐานข้อมูลได้!</h1><p><b>ข้อผิดพลาด:</b> " . $e->getMessage() . "</p>");
}
?>