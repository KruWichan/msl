<?php
// get_address_data.php
require 'includes/db.php'; // เชื่อมต่อฐานข้อมูล

header('Content-Type: application/json'); // บอกเบราว์เซอร์ว่า response เป็น JSON

$action = $_GET['action'] ?? '';
$id = $_GET['id'] ?? 0;

$data = [];

try {
    switch ($action) {
        case 'provinces':
            // ดึงข้อมูลจังหวัดทั้งหมด
            // ใช้ province_id เป็น id และ name_th เป็น name_th
            $stmt = $pdo->query("SELECT province_id AS id, name_th FROM province ORDER BY name_th ASC"); 
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;
        case 'districts': 
            // ดึงข้อมูลอำเภอ/เขตตาม province_id
            if ($id > 0) {
                // ใช้ district_id เป็น id และ name_th เป็น name_th
                $stmt = $pdo->prepare("SELECT district_id AS id, name_th FROM district WHERE province_id = :id ORDER BY name_th ASC"); 
                $stmt->bindParam(':id', $id, PDO::PARAM_INT);
                $stmt->execute();
                $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            break;
        case 'subdistricts': 
            // ดึงข้อมูลตำบล/แขวงและรหัสไปรษณีย์ตาม district_id
            if ($id > 0) {
                // ใช้ subdistrict_id เป็น id, name_th เป็น name_th, และ zipcode เป็น zip_code
                $stmt = $pdo->prepare("SELECT subdistrict_id AS id, name_th, zipcode AS zip_code FROM subdistrict WHERE district_id = :id ORDER BY name_th ASC"); 
                $stmt->bindParam(':id', $id, PDO::PARAM_INT);
                $stmt->execute();
                $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            break;
        case 'zip_code':
            // ดึงรหัสไปรษณีย์ตาม subdistrict_id
            if ($id > 0) {
                // ดึง zipcode โดยตรงจาก subdistrict
                $stmt = $pdo->prepare("SELECT zipcode FROM subdistrict WHERE subdistrict_id = :id LIMIT 1"); 
                $stmt->bindParam(':id', $id, PDO::PARAM_INT);
                $stmt->execute();
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $data = $result ? $result['zipcode'] : ''; // ใช้ 'zipcode' ตรงๆ
            }
            break;
        default:
            $data = ['error' => 'Invalid action'];
            break;
    }
} catch (PDOException $e) {
    error_log("Error in get_address_data.php: " . $e->getMessage());
    $data = ['error' => 'Database error: ' . $e->getMessage()];
}

echo json_encode($data);
?>