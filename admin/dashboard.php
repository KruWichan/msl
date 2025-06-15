<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

require_once '../includes/db.php';

// ตัวแปรสำหรับเก็บข้อมูลที่จะแสดงผล
$totalProducts = 0;
$totalArticles = 0;
$totalOrders = 0;
$totalUsers = 0;
$totalBanners = 0;
$totalGrids = 0;
$totalSales = 0.00;

// ดึงข้อมูลสถิติพื้นฐาน
try {
    $stmtProducts = $pdo->query("SELECT COUNT(*) AS total_products FROM products");
    $resultProducts = $stmtProducts->fetch(PDO::FETCH_ASSOC);
    $totalProducts = $resultProducts['total_products'];

    $stmtArticles = $pdo->query("SELECT COUNT(*) AS total_articles FROM articles");
    $resultArticles = $stmtArticles->fetch(PDO::FETCH_ASSOC);
    $totalArticles = $resultArticles['total_articles'];

    $stmtOrders = $pdo->query("SELECT COUNT(*) AS total_orders FROM orders");
    $resultOrders = $stmtOrders->fetch(PDO::FETCH_ASSOC);
    $totalOrders = $resultOrders['total_orders'];

    $stmtUsers = $pdo->query("SELECT COUNT(*) AS total_users FROM users");
    $resultUsers = $stmtUsers->fetch(PDO::FETCH_ASSOC);
    $totalUsers = $resultUsers['total_users'];

    $stmtBanners = $pdo->query("SELECT COUNT(*) AS total_banners FROM banners");
    $resultBanners = $stmtBanners->fetch(PDO::FETCH_ASSOC);
    $totalBanners = $resultBanners['total_banners'];

    $stmtGrids = $pdo->query("SELECT COUNT(*) AS total_grids FROM banner_grids");
    $resultGrids = $stmtGrids->fetch(PDO::FETCH_ASSOC);
    $totalGrids = $resultGrids['total_grids'];

    // Dashboard: สถิติยอดขายรวม
    $stmtSales = $pdo->query("SELECT SUM(total_amount) AS total_sales FROM orders WHERE status IN ('completed','shipped')");
    $resultSales = $stmtSales->fetch(PDO::FETCH_ASSOC);
    $totalSales = $resultSales['total_sales'] ?: 0.00;

} catch (PDOException $e) {
    echo "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative' role='alert'>
                <strong class='font-bold'>โอ้!</strong>
                <span class='block sm:inline'>เกิดข้อผิดพลาดในการดึงข้อมูล: " . $e->getMessage() . "</span>
              </div>";
}

// ดึงข้อมูลคำสั่งซื้อล่าสุด
$latestOrders = [];
try {
    $stmtLatestOrders = $pdo->query("
        SELECT id, customer_name, total_amount, status, created_at 
        FROM orders 
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    $latestOrders = $stmtLatestOrders->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Database error fetching latest orders: " . $e->getMessage());
    echo "<p class='text-red-500 mt-3'>ไม่สามารถดึงข้อมูลคำสั่งซื้อล่าสุดได้: " . $e->getMessage() . "</p>";
}

// ดึงข้อมูลผู้ใช้งานล่าสุด (ลบ last_login ออกจาก SELECT)
$latestUsers = [];
try {
    $stmtUsers = $pdo->query("SELECT id, username, email, created_at FROM users ORDER BY created_at DESC LIMIT 5"); // <<< แก้ไขตรงนี้: ลบ last_login ออก
    $latestUsers = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Database error fetching latest users: " . $e->getMessage());
    echo "<p class='text-red-500 mt-3'>ไม่สามารถดึงข้อมูลผู้ใช้งานล่าสุดได้: " . $e->getMessage() . "</p>";
}

// Dashboard: สินค้าขายดี 5 อันดับ
$topProducts = [];
try {
    $stmtTopProducts = $pdo->query("
        SELECT p.id, p.name, SUM(oi.quantity) AS total_sold
        FROM order_items oi
        JOIN products p ON oi.product_id = p.id
        JOIN orders o ON oi.order_id = o.id
        WHERE o.status IN ('completed','shipped')
        GROUP BY p.id, p.name
        ORDER BY total_sold DESC
        LIMIT 5
    ");
    $topProducts = $stmtTopProducts->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $topProducts = [];
}

// Dashboard: บทความยอดนิยม 5 อันดับ (ตัวอย่าง: นับจำนวนบทความที่มีการเผยแพร่ล่าสุด)
$popularArticles = [];
try {
    $stmtPopularArticles = $pdo->query("
        SELECT id, title, created_at
        FROM articles
        WHERE status = 'published'
        ORDER BY created_at DESC
        LIMIT 5
    ");
    $popularArticles = $stmtPopularArticles->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $popularArticles = [];
}

function getOrderStatusText($status) {
    $map = [
        'pending' => 'รอดำเนินการ',
        'processing' => 'กำลังดำเนินการ',
        'completed' => 'สำเร็จ',
        'cancelled' => 'ยกเลิก',
        'shipped' => 'จัดส่งแล้ว'
    ];
    return $map[$status] ?? $status;
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แดชบอร์ด - morsenglove.com</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="//unpkg.com/alpinejs" defer></script>
</head>
<body>
    <div class="min-h-screen bg-gray-100">
        <?php require_once 'header.php'; ?>
        
        <main class="p-6">
            <h2 class="text-3xl font-bold text-gray-800 mb-6">แดชบอร์ดภาพรวม</h2>

            <!-- Dashboard Stat Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
                <div class="card-item bg-white rounded-lg shadow-md hover:shadow-xl transition duration-300">
                    <a href="manage_products.php" class="block p-6 text-center">
                        <div class="text-green-500 text-5xl mb-3">📦</div>
                        <div class="label text-gray-600 text-lg mb-2">สินค้าทั้งหมด</div>
                        <div class="value text-4xl font-bold"><?php echo number_format($totalProducts); ?></div>
                    </a>
                </div>

                <div class="card-item bg-white rounded-lg shadow-md hover:shadow-xl transition duration-300">
                    <a href="manage_articles.php" class="block p-6 text-center">
                        <div class="text-blue-500 text-5xl mb-3">📝</div>
                        <div class="label text-gray-600 text-lg mb-2">บทความทั้งหมด</div>
                        <div class="value text-4xl font-bold"><?php echo number_format($totalArticles); ?></div>
                    </a>
                </div>

                <div class="card-item bg-white rounded-lg shadow-md hover:shadow-xl transition duration-300">
                    <a href="manage_orders.php" class="block p-6 text-center">
                        <div class="text-yellow-500 text-5xl mb-3">🛒</div>
                        <div class="label text-gray-600 text-lg mb-2">ออเดอร์ทั้งหมด</div>
                        <div class="value text-4xl font-bold"><?php echo number_format($totalOrders); ?></div>
                    </a>
                </div>

                <div class="card-item bg-white rounded-lg shadow-md hover:shadow-xl transition duration-300">
                    <a href="manage_users.php" class="block p-6 text-center">
                        <div class="text-purple-500 text-5xl mb-3">👤</div>
                        <div class="label text-gray-600 text-lg mb-2">ผู้ใช้งานทั้งหมด</div>
                        <div class="value text-4xl font-bold"><?php echo number_format($totalUsers); ?></div>
                    </a>
                </div>

                <div class="card-item bg-white rounded-lg shadow-md hover:shadow-xl transition duration-300">
                    <a href="manage_banners.php" class="block p-6 text-center">
                        <div class="text-indigo-500 text-5xl mb-3">🖼️</div>
                        <div class="label text-gray-600 text-lg mb-2">แบนเนอร์ทั้งหมด</div>
                        <div class="value text-4xl font-bold"><?php echo number_format($totalBanners); ?></div>
                    </a>
                </div>

                <div class="card-item bg-white rounded-lg shadow-md hover:shadow-xl transition duration-300">
                    <a href="manage_banner_grids.php" class="block p-6 text-center">
                        <div class="text-red-500 text-5xl mb-3">🧱</div>
                        <div class="label text-gray-600 text-lg mb-2">Grid ทั้งหมด</div>
                        <div class="value text-4xl font-bold"><?php echo number_format($totalGrids); ?></div>
                    </a>
                </div>

                <div class="card-item bg-white rounded-lg shadow-md hover:shadow-xl transition duration-300">
                    <div class="block p-6 text-center">
                        <div class="text-orange-500 text-5xl mb-3">💰</div>
                        <div class="label text-gray-600 text-lg mb-2">ยอดขายรวม (บาท)</div>
                        <div class="value text-4xl font-bold"><?= number_format($totalSales, 2) ?></div>
                    </div>
                </div>
            </div>

            <!-- Top Products -->
            <div class="bg-white p-6 rounded-lg shadow-md mb-8">
                <h3 class="text-xl font-semibold text-gray-800 mb-4">สินค้าขายดี 5 อันดับ</h3>
                <?php if (!empty($topProducts)): ?>
                    <ol class="list-decimal pl-6">
                        <?php foreach ($topProducts as $prod): ?>
                            <li class="mb-2">
                                <span class="font-semibold"><?= htmlspecialchars($prod['name']) ?></span>
                                <span class="text-gray-500">ขายได้ <?= number_format($prod['total_sold']) ?> ชิ้น</span>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                <?php else: ?>
                    <div class="text-gray-500">ยังไม่มีข้อมูลสินค้าขายดี</div>
                <?php endif; ?>
            </div>

            <!-- Popular Articles -->
            <div class="bg-white p-6 rounded-lg shadow-md mb-8">
                <h3 class="text-xl font-semibold text-gray-800 mb-4">บทความเผยแพร่ล่าสุด 5 อันดับ</h3>
                <?php if (!empty($popularArticles)): ?>
                    <ol class="list-decimal pl-6">
                        <?php foreach ($popularArticles as $art): ?>
                            <li class="mb-2">
                                <span class="font-semibold"><?= htmlspecialchars($art['title']) ?></span>
                                <span class="text-gray-500">(<?= date('d/m/Y', strtotime($art['created_at'])) ?>)</span>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                <?php else: ?>
                    <div class="text-gray-500">ยังไม่มีบทความเผยแพร่</div>
                <?php endif; ?>
            </div>

            <div class="bg-white p-6 rounded-lg shadow-md mb-8">
                <h3 class="text-xl font-semibold text-gray-800 mb-4">ข้อมูลเชิงลึกเพิ่มเติม</h3>
                
                <h4 class="text-lg font-medium text-gray-700 mb-3 mt-4">คำสั่งซื้อล่าสุด 5 รายการ</h4>
                <?php if (!empty($latestOrders)): ?>
                    <div class="overflow-x-auto mb-8">
                        <table class="min-w-full bg-white border border-gray-200 rounded-lg shadow-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID ออเดอร์</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ลูกค้า</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ยอดรวม</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">สถานะ</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">วันที่สั่ง</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">จัดการ</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php foreach ($latestOrders as $order): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">#<?php echo htmlspecialchars($order['id']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?php echo htmlspecialchars($order['customer_name'] ?? 'N/A'); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo number_format($order['total_amount'], 2); ?> บาท</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                            <?php
                                            $statusClass = '';
                                            switch ($order['status']) {
                                                case 'pending':
                                                    $statusClass = 'bg-yellow-100 text-yellow-800';
                                                    break;
                                                case 'processing':
                                                    $statusClass = 'bg-blue-100 text-blue-800';
                                                    break;
                                                case 'completed':
                                                    $statusClass = 'bg-green-100 text-green-800';
                                                    break;
                                                case 'cancelled':
                                                    $statusClass = 'bg-red-100 text-red-800';
                                                    break;
                                                case 'shipped':
                                                    $statusClass = 'bg-purple-100 text-purple-800';
                                                    break;
                                                default:
                                                    $statusClass = 'bg-gray-100 text-gray-800';
                                                    break;
                                            }
                                            ?>
                                            <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full <?= $statusClass ?>">
                                                <?php echo htmlspecialchars(ucfirst($order['status'])); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($order['created_at']))); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-left text-sm font-medium">
                                            <a href="manage_orders.php?id=<?php echo $order['id']; ?>" class="text-indigo-600 hover:text-indigo-900">ดูรายละเอียด</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-8 mb-8">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path vector-effect="non-scaling-stroke" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 13h6m-3-3v6m-9 1V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z" />
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-gray-900">ยังไม่มีคำสั่งซื้อ</h3>
                        <p class="mt-1 text-sm text-gray-500">
                            เมื่อมีคำสั่งซื้อใหม่ จะแสดงที่นี่
                        </p>
                        <div class="mt-6">
                            <a href="manage_orders.php" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                <svg class="-ml-1 mr-2 h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" />
                                </svg>
                                ดูออเดอร์ทั้งหมด
                            </a>
                        </div>
                    </div>
                <?php endif; ?>

                <h4 class="text-lg font-medium text-gray-700 mb-3 mt-8">ผู้ใช้งานล่าสุด 5 คน</h4>
                <?php if (!empty($latestUsers)): ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full bg-white border border-gray-200 rounded-lg shadow-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">#</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ชื่อผู้ใช้งาน</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">อีเมล</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">วันที่สมัคร</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">จัดการ</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php foreach ($latestUsers as $index => $user): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo $index + 1; ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($user['username']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($user['email']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($user['created_at']))); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-left text-sm font-medium">
                                            <a href="manage_users.php?action=edit&id=<?php echo $user['id']; ?>" class="text-blue-600 hover:text-blue-900">แก้ไข</a>
                                            <a href="manage_users.php?action=delete&id=<?php echo $user['id']; ?>" onclick="return confirm('คุณแน่ใจหรือไม่ที่จะลบผู้ใช้งานนี้?')" class="ml-2 text-red-600 hover:text-red-900">ลบ</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-8">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path vector-effect="non-scaling-stroke" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-gray-900">ยังไม่มีผู้ใช้งาน</h3>
                        <p class="mt-1 text-sm text-gray-500">
                            เมื่อมีผู้ใช้งานสมัครสมาชิก จะแสดงที่นี่
                        </p>
                        <div class="mt-6">
                            <a href="manage_users.php?action=add" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                <svg class="-ml-1 mr-2 h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" />
                                </svg>
                                เพิ่มผู้ใช้งาน
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Dashboard: สถิติยอดขายรวม -->
            <!-- Dashboard: สินค้าขายดี 5 อันดับ -->
            <!-- Dashboard: บทความยอดนิยม 5 อันดับ -->
            <!-- Dashboard: รายการล่าสุด (orders, users) -->

            <!-- ระบบแจ้งเตือน (Notification System) -->
            <!-- 
                TODO:
                - Email Notification (แจ้งเตือนลูกค้า/แอดมิน)
                - Web Notification/Popup (แจ้งเตือนในเว็บ)
                - ระบบ opt-out สำหรับลูกค้า
                - ตัวอย่าง: 
                <div class="mt-8 text-xs text-blue-500">ระบบแจ้งเตือน: Email, LINE, Web Notification (กำลังพัฒนา)</div>
            -->

            <!-- ระบบความปลอดภัย (Security) -->
            <!-- 
                TODO:
                - ตรวจสอบ CSRF/XSS/SQL Injection (ใช้ prepared statement แล้ว)
                - ตรวจสอบ session และสิทธิ์ทุกหน้า (มีแล้ว)
                - ตัวอย่าง: 
                <div class="mt-2 text-xs text-green-500">Security: CSRF/XSS/SQL Injection/Session/Role (ตรวจสอบแล้ว)</div>
            -->

        </main>
    </div>
</body>
</html>