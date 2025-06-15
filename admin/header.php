<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}
?>
<nav class="bg-green-600 text-white p-4" x-data="{ open: false, gridBuilderOpen: false }">
    <div class="flex justify-between items-center max-w-6xl mx-auto">
      <h1 class="text-xl font-bold"><a href="../index.php" class="hover:underline">🌿 MORSENGLOVE :::.</h1></a>
      <button @click="open = !open" class="md:hidden">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2"
             viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round"
                d="M4 6h16M4 12h16M4 18h16" />
        </svg>
      </button>
      <ul class="hidden md:flex space-x-6 text-sm">
        <li><a href="dashboard.php" class="hover:underline">แดชบอร์ด</a></li>
        <li><a href="manage_products.php" class="hover:underline">สินค้า</a></li>
        <li><a href="manage_tags.php" class="hover:underline">ป้ายสินค้า</a></li>
        <li><a href="manage_categories.php" class="hover:underline">หมวดหมู่สินค้า</a></li>
        <li><a href="manage_articles.php" class="hover:underline">บทความ</a></li>
        <li><a href="manage_orders.php" class="hover:underline">ออเดอร์</a></li>
          <li><a href="manage_payments.php" class="hover:underline">แจ้งชำระเงิน</a></li>
        <li><a href="manage_users.php" class="hover:underline">ผู้ใช้งาน</a></li>
        
        <li class="relative">
            <button @click="gridBuilderOpen = !gridBuilderOpen" class="hover:underline focus:outline-none flex items-center">
                GridBuilder
                <svg :class="{'rotate-180': gridBuilderOpen}" class="ml-1 w-4 h-4 transform transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
            </button>
            <ul x-show="gridBuilderOpen" @click.away="gridBuilderOpen = false" 
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 scale-95"
                x-transition:enter-end="opacity-100 scale-100"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100 scale-100"
                x-transition:leave-end="opacity-0 scale-95"
                class="absolute left-0 mt-2 w-48 bg-green-700 rounded-md shadow-lg py-2 z-20"
            >
                <li><a href="add_banner.php" class="block px-4 py-2 hover:bg-green-800">เพิ่ม Banner</a></li>
                
                <li><a href="manage_banners.php" class="block px-4 py-2 hover:bg-green-800">จัดการ Banner</a></li>
                <li><a href="manage_banner_grids.php" class="block px-4 py-2 hover:bg-green-800">จัดการ Grid</a></li>
             
                
            </ul>
        </li>
        <li><a href="setting.php" class="hover:underline">ตั้งค่าเว็บไซต์</a></li>
        <li><a href="../logout.php" class="hover:underline text-red-200">ออกจากระบบ</a></li>
      </ul>
    </div>

    <ul x-show="open" class="mt-4 space-y-2 md:hidden">
      <li><a href="dashboard.php" class="block hover:underline">แดชบอร์ด</a></li>
      <li><a href="manage_products.php" class="block hover:underline">จัดการสินค้า</a></li>
      <li><a href="manage_tags.php" class="block hover:underline">จัดการป้ายสินค้า</a></li>
      <li><a href="manage_categories.php" class="block hover:underline">จัดการหมวดหมู่สินค้า</a></li>
      <li><a href="manage_articles.php" class="block hover:underline">จัดการบทความ</a></li>
      <li><a href="manage_orders.php" class="block hover:underline">จัดการออเดอร์</a></li>
      <li><a href="manage_payments.php" class="text-white hover:text-gray-200 px-3 py-2 rounded-md text-sm font-medium">จัดการแจ้งชำระเงิน</a>
</li>
      <li><a href="manage_users.php" class="block hover:underline">จัดการผู้ใช้งาน</a></li>
      
      <li>
        <button @click="gridBuilderOpen = !gridBuilderOpen" class="block w-full text-left hover:underline focus:outline-none flex items-center justify-between">
            GridBuilder
            <svg :class="{'rotate-180': gridBuilderOpen}" class="ml-1 w-4 h-4 transform transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
        </button>
        <ul x-show="gridBuilderOpen" class="mt-2 pl-4 space-y-2 bg-green-700 rounded-md py-2">
            <li><a href="add_banner.php" class="block hover:bg-green-800 px-2 py-1 rounded">เพิ่ม Banner</a></li>
           
            <li><a href="manage_banners.php" class="block hover:bg-green-800 px-2 py-1 rounded">จัดการ Banner</a></li>
            <li><a href="manage_banner_grids.php" class="block hover:bg-green-800 px-2 py-1 rounded">จัดการ Grid</a></li>
            
        </ul>
      </li>
      <li><a href="setting.php" class="block hover:underline">ตั้งค่าเว็บไซต์</a></li>
      <li><a href="../logout.php" class="block hover:underline text-red-200">ออกจากระบบ</a></li>
    </ul>
</nav>

<script src="//unpkg.com/alpinejs" defer></script>