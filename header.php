<?php
//header.php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'includes/db.php';

$settings = $pdo->query("SELECT * FROM site_settings LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$site_name = $settings['site_name'] ?? 'MorsengLove';
$font = in_array($settings['font_family'] ?? '', ['Sarabun', 'Kanit', 'Prompt', 'Mitr', 'Noto Sans Thai']) ? $settings['font_family'] : 'Sarabun';
$color = $settings['primary_color'] ?? '#2563eb';
?>

<!-- Font & TailwindCSS -->
<link href="https://fonts.googleapis.com/css2?family=<?= str_replace(' ', '+', $font) ?>&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://cdn.jsdelivr.net/npm/@tailwindcss/line-clamp"></script>
<script>
    tailwind.config = {
        theme: { extend: {} },
        plugins: [tailwindcssLineClamp],
    }
</script>
<style>
    body { font-family: '<?= $font ?>', sans-serif; }
    .primary-text { color: <?= $color ?>; }
    .primary-bg { background-color: <?= $color ?>; }
</style>

<!-- Header -->
<header class="primary-bg text-white p-4">
    <div class="container mx-auto flex justify-between items-center">
        <div class="text-xl font-bold flex items-center gap-2">
            <?php if (!empty($settings['logo'])): ?>
                <img src="<?= $settings['logo'] ?>" alt="Logo" class="h-8">
            <?php endif; ?>
            <?= htmlspecialchars($site_name) ?>
        </div>

        <!-- เมนูสำหรับ Desktop -->
        <nav class="hidden md:flex space-x-4 text-sm items-center">
            <a href="index.php" class="hover:underline">หน้าแรก</a>
            <a href="all_products.php" class="hover:underline">สินค้าทั้งหมด</a>
            <a href="all_articles.php" class="hover:underline">บทความ</a>
            <a href="payment_notification.php" class="hover:underline">แจ้งชำระเงิน</a>
            <a href="contact.php" class="hover:underline">ติดต่อเรา</a>
            <a href="cart.php" class="hover:underline">ตะกร้าสินค้า</a>
            <?php if (isset($_SESSION['user'])): ?>
                <?php if ($_SESSION['user']['role'] === 'admin'): ?>
                    <a href="admin/dashboard.php" class="hover:underline text-yellow-300 font-semibold ml-2">Admin Panel</a>
                <?php endif; ?>
                <span class="ml-2">ยินดีต้อนรับ, <?= htmlspecialchars($_SESSION['user']['username']) ?></span>
                <a href="logout.php" class="hover:underline ml-2">ออกจากระบบ</a>
            <?php else: ?>
                <a href="login.php" class="hover:underline">เข้าสู่ระบบ</a>
            <?php endif; ?>
        </nav>

        <!-- Hamburger สำหรับมือถือ -->
        <div class="md:hidden">
            <button id="menuToggle" class="text-white focus:outline-none">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none"
                     viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
            </button>
        </div>
    </div>

    <!-- เมนูมือถือ -->
    <div id="mobileMenu" class="md:hidden hidden mt-2 space-y-2 px-4 text-sm">
        <a href="index.php" class="block">หน้าแรก</a>
        <a href="all_products.php" class="block">สินค้าทั้งหมด</a>
        <a href="all_articles.php" class="block">บทความ</a>
        <a href="payment_notification.php" class="block">แจ้งชำระเงิน</a>
        <a href="contact.php" class="block">ติดต่อเรา</a>
        <a href="cart.php" class="block">ตะกร้าสินค้า</a>
        <?php if (isset($_SESSION['user'])): ?>
            <?php if ($_SESSION['user']['role'] === 'admin'): ?>
                <a href="admin/dashboard.php" class="block text-yellow-500 font-semibold">Admin Panel</a>
            <?php endif; ?>
            <div class="block">ยินดีต้อนรับ, <?= htmlspecialchars($_SESSION['user']['username']) ?></div>
            <a href="logout.php" class="block">ออกจากระบบ</a>
        <?php else: ?>
            <a href="login.php" class="block">เข้าสู่ระบบ</a>
        <?php endif; ?>
    </div>
</header>

<script>
    document.getElementById('menuToggle').addEventListener('click', function () {
        const mobileMenu = document.getElementById('mobileMenu');
        mobileMenu.classList.toggle('hidden');
    });
</script>
