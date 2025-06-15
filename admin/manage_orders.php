<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}
require '../includes/db.php'; // เชื่อมต่อฐานข้อมูล

// --- 1. การจัดการการอัปเดตสถานะ (เมื่อมีการ POST จาก Dropdown) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_id']) && isset($_POST['new_status'])) {
    $orderId = $_POST['order_id'];
    $newStatus = $_POST['new_status'];

    // ตรวจสอบสถานะที่อนุญาต (จาก enum ใน DB)
    $allowedStatuses = ['pending', 'processing', 'shipped', 'completed', 'cancelled'];
    if (!in_array($newStatus, $allowedStatuses)) {
        $_SESSION['error_message'] = "สถานะไม่ถูกต้อง!";
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE orders SET status = :status, updated_at = NOW() WHERE id = :id");
            $stmt->bindParam(':status', $newStatus);
            $stmt->bindParam(':id', $orderId);
            $stmt->execute();
            $_SESSION['success_message'] = "อัปเดตสถานะคำสั่งซื้อ #{$orderId} เป็น " . getThaiStatusName($newStatus) . " เรียบร้อยแล้ว!";

            // แจ้งเตือน LINE/Telegram ทุกครั้งที่เปลี่ยนสถานะ
            require_once __DIR__ . '/../includes/notify_helper.php';
            // ดึงข้อมูลออร์เดอร์
            $stmtOrder = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
            $stmtOrder->execute([$orderId]);
            $order = $stmtOrder->fetch(PDO::FETCH_ASSOC);
            $msg = "🔔 สถานะออร์เดอร์ #{$orderId} ถูกเปลี่ยนเป็น " . getThaiStatusName($newStatus) . "\n"
                 . "ชื่อ: " . ($order['customer_name'] ?? '-') . "\n"
                 . "ยอดรวม: " . number_format($order['total_amount'], 2) . " บาท\n"
                 . "วันที่: " . date('d/m/Y H:i', strtotime($order['created_at']));
            sendLineNotify($msg, true);
            sendTelegramNotify($msg);

            // ===== แจ้งเตือนลูกค้าผ่าน LINE Messaging API =====
            require_once __DIR__ . '/../includes/order_notification.php';
            // กำหนดข้อความสถานะภาษาไทย
            $statusText = getThaiStatusName($newStatus);
            notifyCustomerOrder($order, $statusText);

        } catch (PDOException $e) {
            $_SESSION['error_message'] = "เกิดข้อผิดพลาดในการอัปเดตสถานะ: " . $e->getMessage();
        }
    }
    // Redirect กลับไปหน้าเดิมพร้อม Query parameters เพื่อรักษาสถานะการค้นหา/กรอง/แบ่งหน้า
    $queryString = http_build_query($_GET);
    header("Location: manage_orders.php?" . $queryString);
    exit;
}

// --- 2. การกำหนดค่าเริ่มต้นสำหรับการค้นหา, กรอง, เรียงลำดับ, แบ่งหน้า ---
$search = $_GET['search'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';
$sortBy = $_GET['sort_by'] ?? 'created_at'; // Default sort
$sortOrder = $_GET['sort_order'] ?? 'DESC'; // Default order
$page = (int)($_GET['page'] ?? 1);
$recordsPerPage = 20; // จำนวนรายการต่อหน้า

// ตรวจสอบและกำหนดค่าที่ปลอดภัยสำหรับการเรียงลำดับ
$allowedSortBy = ['id', 'customer_name', 'email', 'phone', 'total_amount', 'status', 'created_at'];
if (!in_array($sortBy, $allowedSortBy)) {
    $sortBy = 'created_at';
}
$allowedSortOrder = ['ASC', 'DESC'];
if (!in_array(strtoupper($sortOrder), $allowedSortOrder)) {
    $sortOrder = 'DESC';
}
$sortOrder = strtoupper($sortOrder);

// --- 3. สร้างเงื่อนไข WHERE clause สำหรับการค้นหาและกรอง ---
$whereClauses = [];
$queryParams = [];

if ($search) {
    $whereClauses[] = "(id LIKE :search OR customer_name LIKE :search OR email LIKE :search OR phone LIKE :search OR shipping_address LIKE :search OR shipping_city LIKE :search OR shipping_province LIKE :search)";
    $queryParams[':search'] = '%' . $search . '%';
}

if ($statusFilter && $statusFilter !== 'all') {
    $whereClauses[] = "status = :status_filter";
    $queryParams[':status_filter'] = $statusFilter;
}

if ($startDate) {
    $whereClauses[] = "created_at >= :start_date";
    $queryParams[':start_date'] = $startDate . ' 00:00:00';
}
if ($endDate) {
    $whereClauses[] = "created_at <= :end_date";
    $queryParams[':end_date'] = $endDate . ' 23:59:59';
}

$whereSql = '';
if (!empty($whereClauses)) {
    $whereSql = " WHERE " . implode(" AND ", $whereClauses);
}

// --- 4. ดึงข้อมูลคำสั่งซื้อสำหรับสรุป (Summary) ---
$summary = [
    'total_orders' => 0,
    'total_amount_all' => 0,
    'status_counts' => [],
];
try {
    $stmtSummary = $pdo->query("SELECT COUNT(*) AS total_orders, SUM(total_amount) AS total_amount_all FROM orders");
    $resultSummary = $stmtSummary->fetch(PDO::FETCH_ASSOC);
    $summary['total_orders'] = $resultSummary['total_orders'];
    $summary['total_amount_all'] = $resultSummary['total_amount_all'] ?? 0;

    $stmtStatusCount = $pdo->query("SELECT status, COUNT(*) AS count FROM orders GROUP BY status");
    while ($row = $stmtStatusCount->fetch(PDO::FETCH_ASSOC)) {
        $summary['status_counts'][$row['status']] = $row['count'];
    }
    // ตรวจสอบและกำหนดค่าเริ่มต้นเป็น 0 หากสถานะใดไม่มีข้อมูล
    $allPossibleStatuses = ['pending', 'processing', 'shipped', 'completed', 'cancelled'];
    foreach ($allPossibleStatuses as $s) {
        if (!isset($summary['status_counts'][$s])) {
            $summary['status_counts'][$s] = 0;
        }
    }

} catch (PDOException $e) {
    error_log("Database error fetching summary: " . $e->getMessage());
    echo "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative' role='alert'>
            <strong class='font-bold'>โอ้!</strong>
            <span class='block sm:inline'>เกิดข้อผิดพลาดในการดึงข้อมูลสรุป: " . $e->getMessage() . "</span>
          </div>";
}


// --- 5. ดึงข้อมูลคำสั่งซื้อ (สำหรับตาราง) ---
$totalRecords = 0;
$orders = [];

try {
    // นับจำนวนรวมของรายการทั้งหมดที่ตรงตามเงื่อนไข (สำหรับ Pagination)
    $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM orders" . $whereSql);
    $stmtCount->execute($queryParams);
    $totalRecords = $stmtCount->fetchColumn();

    $totalPages = ceil($totalRecords / $recordsPerPage);
    $offset = ($page - 1) * $recordsPerPage;
    if ($offset < 0) $offset = 0; // ป้องกัน offset ติดลบ
    if ($offset > 0 && $offset >= $totalRecords && $totalRecords > 0) {
        // กรณีที่หน้าปัจจุบันเกินจำนวนหน้าจริงหลังจากฟิลเตอร์/ค้นหา
        $page = 1;
        $offset = 0;
    }


    $sql = "SELECT id, customer_name, email, phone, shipping_name, shipping_phone, shipping_address, shipping_city, shipping_province, shipping_postcode, total_amount, status, created_at
            FROM orders
            " . $whereSql . "
            ORDER BY " . $sortBy . " " . $sortOrder . "
            LIMIT :limit OFFSET :offset";

    $stmt = $pdo->prepare($sql);
    foreach ($queryParams as $key => $val) {
        $stmt->bindValue($key, $val);
    }
    $stmt->bindValue(':limit', $recordsPerPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Database error fetching orders: " . $e->getMessage());
    echo "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative' role='alert'>
            <strong class='font-bold'>โอ้!</strong>
            <span class='block sm:inline'>เกิดข้อผิดพลาดในการดึงข้อมูลคำสั่งซื้อ: " . $e->getMessage() . "</span>
          </div>";
}

// ฟังก์ชันสำหรับสร้าง URL สำหรับการเรียงลำดับ
function getSortUrl($column, $currentSortBy, $currentSortOrder) {
    $newOrder = ($currentSortBy === $column && $currentSortOrder === 'ASC') ? 'DESC' : 'ASC';
    $params = $_GET;
    $params['sort_by'] = $column;
    $params['sort_order'] = $newOrder;
    $params['page'] = 1; // กลับไปหน้าแรกเมื่อเปลี่ยนการเรียงลำดับ
    return '?' . http_build_query($params);
}

// ฟังก์ชันสำหรับสร้าง URL สำหรับ Pagination
function getPaginationUrl($pageNum) {
    $params = $_GET;
    $params['page'] = $pageNum;
    return '?' . http_build_query($params);
}

// ฟังก์ชันสำหรับกำหนดคลาสสีสำหรับสถานะ
function getStatusClasses($status) {
    switch ($status) {
        case 'pending': return "bg-yellow-100 text-yellow-800";
        case 'processing': return "bg-blue-100 text-blue-800";
        case 'shipped': return "bg-purple-100 text-purple-800";
        case 'completed': return "bg-green-100 text-green-800";
        case 'cancelled': return "bg-red-100 text-red-800";
        default: return "bg-gray-100 text-gray-800";
    }
}

// ฟังก์ชันสำหรับแปลงสถานะเป็นภาษาไทย
function getThaiStatusName($status) {
    switch ($status) {
        case 'pending': return "รอดำเนินการ";
        case 'processing': return "กำลังดำเนินการ";
        case 'shipped': return "จัดส่งแล้ว";
        case 'completed': return "เสร็จสมบูรณ์";
        case 'cancelled': return "ยกเลิก";
        default: return "ไม่ระบุ";
    }
}

?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>จัดการคำสั่งซื้อ - morsenglove.com</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@3.3.2/dist/tailwind.min.css" rel="stylesheet" />
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs" defer></script>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Sarabun', sans-serif; }
        /* สไตล์สำหรับไอคอนเรียงลำดับ */
        .sort-icon {
            margin-left: 0.25rem;
            width: 0.75rem;
            height: 0.75rem;
            vertical-align: middle;
            display: inline-block;
        }
        .sort-icon.asc {
            transform: rotate(180deg);
        }
        
        /* CSS สำหรับแก้ไขปัญหา Dropdown ถูกตัดทับ */
        .table-wrapper {
            overflow-x: auto;
            /* สำคัญ: กำหนด z-index ที่นี่ เพื่อให้ Stacking Context ของตารางไม่ไปรบกวน dropdown */
            /* แต่ต้องมั่นใจว่าไม่มี element อื่น (เช่น header) มี z-index สูงกว่า */
            z-index: 0; 
        }

        /* สำหรับเซลล์ td ที่มี dropdown */
        /* เราจะไม่ได้กำหนด position: relative หรือ overflow: visible ให้ td โดยตรงแล้ว */
        /* แต่จะให้ div ภายใน td เป็นตัวจัดการแทน */
        
        /* สำหรับ container ของ dropdown ที่อยู่ภายใน td */
        .dropdown-container {
            /* สำคัญ: ต้องมี position: relative เพื่อให้ dropdown menu ที่เป็น absolute วางตำแหน่งได้ */
            position: relative;
            /* และสำคัญกว่า: กำหนด z-index ที่สูงกว่าสำหรับแต่ละ dropdown item */
            /* โดยการใช้ z-index: 10, 20, 30... ใน Alpine.js ในแต่ละแถว */
            z-index: auto; /* หรือ z-index: 1; หากไม่มีปัญหา */
            height: 100%; /* ให้เต็มความสูงของ td */
            display: flex; /* จัดให้อยู่ตรงกลางของ td */
            align-items: center; /* จัดให้อยู่ตรงกลางของ td */
            justify-content: center; /* จัดให้อยู่ตรงกลางของ td */
        }
        
        /* สำหรับตัว dropdown menu เอง */
        .dropdown-menu {
            /* กำหนด z-index ให้สูงที่สุด เพื่อให้อยู่บนสุดของทุกอย่างในหน้า */
            z-index: 1000; /* ค่าที่สูงมากพอที่จะไม่ถูกทับ */
            /* กำหนดตำแหน่ง: หากต้องการให้เปิดไปทางขวาให้ใช้ right-0, หากต้องการให้เปิดไปทางซ้ายให้ใช้ left-0 */
            /* หรือ left-1/2 -translate-x-1/2 เพื่อให้อยู่กึ่งกลาง */
        }
        /* หาก header มี position: fixed หรือ sticky และ z-index สูงมาก */
        /* อาจจะต้องปรับ z-index ของ dropdown-menu ให้สูงกว่า header */
        /* ตัวอย่าง: หาก header มี z-index: 500, dropdown-menu ควรเป็น 501+ */

    </style>
</head>
<body class="bg-gray-100 text-gray-800">
<?php require 'header.php'; // ตรวจสอบไฟล์ header.php ว่ามี position: fixed/sticky หรือ z-index สูงๆ หรือไม่ ?>

<div class="max-w-7xl mx-auto bg-white p-6 rounded-lg shadow-xl mt-6 mb-8">
    <h1 class="text-3xl font-bold mb-6 text-gray-900">จัดการคำสั่งซื้อทั้งหมด</h1>

    <?php
    // แสดงข้อความแจ้งเตือน (Success/Error)
    if (isset($_SESSION['success_message'])) {
        echo "<div class='bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4' role='alert'>
                <strong class='font-bold'>สำเร็จ!</strong>
                <span class='block sm:inline'>" . htmlspecialchars($_SESSION['success_message']) . "</span>
              </div>";
        unset($_SESSION['success_message']);
    }
    if (isset($_SESSION['error_message'])) {
        echo "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>
                <strong class='font-bold'>ผิดพลาด!</strong>
                <span class='block sm:inline'>" . htmlspecialchars($_SESSION['error_message']) . "</span>
              </div>";
        unset($_SESSION['error_message']);
    }
    ?>

    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5 xl:grid-cols-5 gap-4 mb-8">
        <div class="bg-indigo-600 text-white p-4 rounded-lg shadow-md flex flex-col items-center justify-center">
            <div class="text-2xl font-bold"><?= number_format($summary['total_orders']) ?></div>
            <div class="text-sm">คำสั่งซื้อทั้งหมด</div>
        </div>
        <div class="bg-yellow-500 text-white p-4 rounded-lg shadow-md flex flex-col items-center justify-center">
            <div class="text-2xl font-bold"><?= $summary['status_counts']['pending'] ?? 0 ?></div>
            <div class="text-sm">รอดำเนินการ</div>
        </div>
        <div class="bg-blue-500 text-white p-4 rounded-lg shadow-md flex flex-col items-center justify-center">
            <div class="text-2xl font-bold"><?= $summary['status_counts']['processing'] ?? 0 ?></div>
            <div class="text-sm">กำลังดำเนินการ</div>
        </div>
        <div class="bg-purple-500 text-white p-4 rounded-lg shadow-md flex flex-col items-center justify-center">
            <div class="text-2xl font-bold"><?= $summary['status_counts']['shipped'] ?? 0 ?></div>
            <div class="text-sm">จัดส่งแล้ว</div>
        </div>
        <div class="bg-green-500 text-white p-4 rounded-lg shadow-md flex flex-col items-center justify-center">
            <div class="text-2xl font-bold"><?= $summary['status_counts']['completed'] ?? 0 ?></div>
            <div class="text-sm">เสร็จสมบูรณ์</div>
        </div>
    </div>

    <div class="bg-gray-50 p-4 rounded-lg shadow-inner mb-6">
        <form method="GET" action="manage_orders.php" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 items-end">
            <div>
                <label for="search" class="block text-sm font-medium text-gray-700 mb-1">ค้นหา (รหัส, ชื่อลูกค้า, อีเมล, โทร, ที่อยู่)</label>
                <input type="text" name="search" id="search" value="<?= htmlspecialchars($search) ?>" placeholder="ค้นหาคำสั่งซื้อ..."
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
            </div>
            <div>
                <label for="status" class="block text-sm font-medium text-gray-700 mb-1">สถานะ</label>
                <select name="status" id="status" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    <option value="all" <?= ($statusFilter === 'all' ? 'selected' : '') ?>>ทั้งหมด</option>
                    <option value="pending" <?= ($statusFilter === 'pending' ? 'selected' : '') ?>>รอดำเนินการ</option>
                    <option value="processing" <?= ($statusFilter === 'processing' ? 'selected' : '') ?>>กำลังดำเนินการ</option>
                    <option value="shipped" <?= ($statusFilter === 'shipped' ? 'selected' : '') ?>>จัดส่งแล้ว</option>
                    <option value="completed" <?= ($statusFilter === 'completed' ? 'selected' : '') ?>>เสร็จสมบูรณ์</option>
                    <option value="cancelled" <?= ($statusFilter === 'cancelled' ? 'selected' : '') ?>>ยกเลิก</option>
                </select>
            </div>
            <div>
                <label for="start_date" class="block text-sm font-medium text-gray-700 mb-1">วันที่สร้าง (จาก)</label>
                <input type="date" name="start_date" id="start_date" value="<?= htmlspecialchars($startDate) ?>"
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
            </div>
            <div>
                <label for="end_date" class="block text-sm font-medium text-gray-700 mb-1">วันที่สร้าง (ถึง)</label>
                <input type="date" name="end_date" id="end_date" value="<?= htmlspecialchars($endDate) ?>"
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
            </div>
            <div class="col-span-1 md:col-span-2 lg:col-span-4 flex justify-end gap-2">
                <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    <svg class="-ml-1 mr-2 h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd" />
                    </svg>
                    ค้นหา/กรอง
                </button>
                <a href="manage_orders.php" class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md shadow-sm text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    <svg class="-ml-1 mr-2 h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M14.243 5.757a1 1 0 00-1.414 0L10 8.586 7.172 5.757a1 1 0 00-1.414 1.414L8.586 10l-2.829 2.828a1 1 0 101.414 1.414L10 11.414l2.828 2.829a1 1 0 001.414-1.414L11.414 10l2.829-2.828a1 1 0 000-1.414z" clip-rule="evenodd" />
                    </svg>
                    ล้างค่า
                </a>
            </div>
        </form>
    </div>

    <div class="table-wrapper rounded-lg shadow-md border border-gray-200">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-100">
                <tr>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        <a href="<?= getSortUrl('id', $sortBy, $sortOrder) ?>" class="flex items-center">
                            รหัสคำสั่งซื้อ
                            <?php if ($sortBy === 'id'): ?>
                                <svg class="sort-icon <?= ($sortOrder === 'ASC' ? 'asc' : '') ?>" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"></path></svg>
                            <?php endif; ?>
                        </a>
                    </th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        <a href="<?= getSortUrl('customer_name', $sortBy, $sortOrder) ?>" class="flex items-center">
                            ชื่อลูกค้า
                            <?php if ($sortBy === 'customer_name'): ?>
                                <svg class="sort-icon <?= ($sortOrder === 'ASC' ? 'asc' : '') ?>" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"></path></svg>
                            <?php endif; ?>
                        </a>
                    </th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">อีเมล</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">โทรศัพท์</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ที่อยู่จัดส่ง</th>
                    <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                        <a href="<?= getSortUrl('total_amount', $sortBy, $sortOrder) ?>" class="flex items-center justify-end">
                            ยอดรวม
                            <?php if ($sortBy === 'total_amount'): ?>
                                <svg class="sort-icon <?= ($sortOrder === 'ASC' ? 'asc' : '') ?>" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"></path></svg>
                            <?php endif; ?>
                        </a>
                    </th>
                    <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">สถานะ</th>
                    <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                        <a href="<?= getSortUrl('created_at', $sortBy, $sortOrder) ?>" class="flex items-center">
                            วันที่สร้าง
                            <?php if ($sortBy === 'created_at'): ?>
                                <svg class="sort-icon <?= ($sortOrder === 'ASC' ? 'asc' : '') ?>" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"></path></svg>
                            <?php endif; ?>
                        </a>
                    </th>
                    <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">จัดการ</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($orders)): ?>
                    <tr>
                        <td colspan="9" class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-500">ไม่พบคำสั่งซื้อที่ตรงกับเงื่อนไข</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($orders as $order): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">#<?= htmlspecialchars($order['id']) ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?= htmlspecialchars($order['customer_name']) ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?= htmlspecialchars($order['email']) ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?= htmlspecialchars($order['phone']) ?></td>
                            <td class="px-6 py-4 text-sm text-gray-500">
                                <?php
                                $addressParts = [];
                                if (!empty($order['shipping_name'])) $addressParts[] = htmlspecialchars($order['shipping_name']);
                                if (!empty($order['shipping_phone'])) $addressParts[] = htmlspecialchars($order['shipping_phone']);
                                if (!empty($order['shipping_address'])) $addressParts[] = htmlspecialchars($order['shipping_address']);
                                if (!empty($order['shipping_city'])) $addressParts[] = htmlspecialchars($order['shipping_city']);
                                if (!empty($order['shipping_province'])) $addressParts[] = htmlspecialchars($order['shipping_province']);
                                if (!empty($order['shipping_postcode'])) $addressParts[] = htmlspecialchars($order['shipping_postcode']);
                                
                                echo !empty($addressParts) ? nl2br(implode(', ', $addressParts)) : 'ไม่มีข้อมูลที่อยู่';
                                ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-900"><?= number_format($order['total_amount'], 2) ?> บาท</td>
                            
                            <td class="px-6 py-4 whitespace-nowrap text-center text-sm">
                                <div x-data="{ open: false, currentStatus: '<?= htmlspecialchars($order['status']) ?>', zIndexValue: 1 }" 
                                     @click.away="open = false" 
                                     class="dropdown-container" 
                                     :style="`z-index: ${zIndexValue + (open ? 100 : 0)};`">
                                     <button type="button" @click="open = !open" 
                                            class="inline-flex justify-center rounded-full py-1 px-3 text-xs font-semibold <?= getStatusClasses($order['status']) ?> capitalize" 
                                            id="menu-button-<?= $order['id'] ?>" aria-expanded="true" aria-haspopup="true">
                                        <span x-text="getThaiStatusName(currentStatus)"></span>
                                        <svg class="-mr-1 ml-1 h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                            <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 10.94l3.71-3.71a.75.75 0 111.06 1.06l-4.25 4.25a.75.75 0 01-1.06 0L5.21 8.29a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                                        </svg>
                                    </button>
                                    
                                    <div x-show="open" 
                                         x-transition:enter="transition ease-out duration-100" 
                                         x-transition:enter-start="transform opacity-0 scale-95" 
                                         x-transition:enter-end="transform opacity-100 scale-100" 
                                         x-transition:leave="transition ease-in duration-75" 
                                         x-transition:leave-start="transform opacity-100 scale-100" 
                                         x-transition:leave-end="transform opacity-0 scale-95" 
                                         class="absolute left-1/2 -translate-x-1/2 mt-2 w-40 origin-top-right rounded-md bg-white shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none dropdown-menu" 
                                         role="menu" aria-orientation="vertical" aria-labelledby="menu-button-<?= $order['id'] ?>" tabindex="-1">
                                        <div class="py-1" role="none">
                                            <?php 
                                            $statuses = ['pending', 'processing', 'shipped', 'completed', 'cancelled'];
                                            foreach ($statuses as $s): 
                                                if ($s === $order['status']) continue; // ไม่แสดงสถานะปัจจุบัน
                                            ?>
                                                <button type="button" 
                                                        onclick="confirmChangeStatus(<?= $order['id'] ?>, '<?= $s ?>', '<?= htmlspecialchars($order['customer_name']) ?>')"
                                                        class="text-gray-700 block px-4 py-2 text-sm w-full text-left hover:bg-gray-100 hover:text-gray-900" 
                                                        role="menuitem" tabindex="-1" id="menu-item-<?= $order['id'] ?>-<?= $s ?>">
                                                    <?= getThaiStatusName($s) ?>
                                                </button>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= date('d/m/Y H:i', strtotime($order['created_at'])) ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                                <a href="view_order.php?id=<?= $order['id'] ?>" class="text-blue-600 hover:underline mr-3">ดู</a>
                                <a href="edit_order.php?id=<?= $order['id'] ?>" class="text-green-600 hover:underline mr-3">แก้ไข</a>
                                <a href="delete_order.php?id=<?= $order['id'] ?>" onclick="return confirm('ต้องการลบคำสั่งซื้อ #<?= $order['id'] ?> ของ <?= htmlspecialchars($order['customer_name']) ?> จริงหรือไม่?')" class="text-red-600 hover:underline">ลบ</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="mt-6 flex justify-between items-center">
        <div class="text-sm text-gray-700">
            แสดง <?= count($orders) ?> จากทั้งหมด <?= number_format($totalRecords) ?> รายการ
            <?php if ($totalRecords > $recordsPerPage): ?>
                (หน้า <?= $page ?> จาก <?= $totalPages ?>)
            <?php endif; ?>
        </div>
        <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
            <?php if ($page > 1): ?>
                <a href="<?= getPaginationUrl($page - 1) ?>" class="relative inline-flex items-center rounded-l-md px-2 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                    <span class="sr-only">Previous</span>
                    <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M12.79 5.23a.75.75 0 01-.022 1.06L9.31 10l3.46 3.71a.75.75 0 11-1.04 1.08l-4-4a.75.75 0 010-1.08l4-4a.75.75 0 011.06.02z" clip-rule="evenodd" />
                    </svg>
                </a>
            <?php endif; ?>

            <?php
            $startPage = max(1, $page - 2);
            $endPage = min($totalPages, $page + 2);

            if ($startPage > 1) {
                echo '<a href="' . getPaginationUrl(1) . '" class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">1</a>';
                if ($startPage > 2) {
                    echo '<span class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700">...</span>';
                }
            }

            for ($i = $startPage; $i <= $endPage; $i++):
            ?>
                <a href="<?= getPaginationUrl($i) ?>" class="<?= $i === $page ? 'z-10 bg-indigo-50 border-indigo-500 text-indigo-600' : 'bg-white border-gray-300 text-gray-700 hover:bg-gray-50' ?> relative inline-flex items-center px-4 py-2 border text-sm font-medium">
                    <?= $i ?>
                </a>
            <?php endfor; ?>

            <?php if ($endPage < $totalPages): ?>
                <?php if ($endPage < $totalPages - 1): ?>
                    <span class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700">...</span>
                <?php endif; ?>
                <a href="<?= getPaginationUrl($totalPages) ?>" class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50"><?= $totalPages ?></a>
            <?php endif; ?>

            <?php if ($page < $totalPages): ?>
                <a href="<?= getPaginationUrl($page + 1) ?>" class="relative inline-flex items-center rounded-r-md px-2 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                    <span class="sr-only">Next</span>
                    <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.022-1.06L10.69 10 7.23 6.29a.75.75 0 111.04-1.08l4 4a.75.75 0 010 1.08l-4 4a.75.75 0 01-1.06-.02z" clip-rule="evenodd" />
                    </svg>
                </a>
            <?php endif; ?>
        </nav>
    </div>

</div>

<script>
    // ฟังก์ชันสำหรับแปลงสถานะภาษาอังกฤษเป็นภาษาไทย
    function getThaiStatusName(status) {
        switch (status) {
            case 'pending': return "รอดำเนินการ";
            case 'processing': return "กำลังดำเนินการ";
            case 'shipped': return "จัดส่งแล้ว";
            case 'completed': return "เสร็จสมบูรณ์";
            case 'cancelled': return "ยกเลิก";
            default: return "สั่งซื้อ";
        }
    }

    // JavaScript สำหรับยืนยันการเปลี่ยนสถานะ
    function confirmChangeStatus(orderId, newStatus, customerName) {
        const thaiStatus = getThaiStatusName(newStatus);
        if (confirm(`คุณต้องการเปลี่ยนสถานะคำสั่งซื้อ # ${orderId} ของ ${customerName} เป็น "${thaiStatus}" ใช่หรือไม่?`)) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'manage_orders.php<?= '?' . http_build_query($_GET) ?>'; 
            
            const orderIdInput = document.createElement('input');
            orderIdInput.type = 'hidden';
            orderIdInput.name = 'order_id';
            orderIdInput.value = orderId;
            form.appendChild(orderIdInput);

            const statusInput = document.createElement('input');
            statusInput.type = 'hidden';
            statusInput.name = 'new_status';
            statusInput.value = newStatus;
            form.appendChild(statusInput);

            document.body.appendChild(form);
            form.submit();
        }
    }
</script>

</body>
</html>