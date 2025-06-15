<?php
require 'includes/db.php';

$slug = $_GET['slug'] ?? '';
if ($slug === '') {
    echo "ไม่พบลิงก์บทความ";
    exit;
}

$stmt = $pdo->prepare("
    SELECT a.title, a.content, a.image, a.published_at, a.category_id,
           c.name AS category_name,
           u.username AS author_name
    FROM articles a
    LEFT JOIN article_categories c ON a.category_id = c.id
    LEFT JOIN users u ON a.author_id = u.id
    WHERE a.slug = :slug AND a.status = 'published'
    LIMIT 1
");
$stmt->execute(['slug' => $slug]);
$article = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$article) {
    echo "ไม่พบบทความที่คุณต้องการ";
    exit;
}

$settings = $pdo->query("SELECT * FROM site_settings LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$site_name = $settings['site_name'] ?? 'MorsengLove';
$font = in_array($settings['font_family'], ['Sarabun', 'Kanit', 'Prompt', 'Mitr', 'Noto Sans Thai']) ? $settings['font_family'] : 'Sarabun';
$color = $settings['primary_color'] ?? '#2563eb';

$stmt_latest = $pdo->prepare("
    SELECT title, slug, image, published_at, excerpt
    FROM articles
    WHERE status = 'published' AND slug != :slug
    ORDER BY published_at DESC
    LIMIT 5
");
$stmt_latest->execute(['slug' => $slug]);
$latest_articles = $stmt_latest->fetchAll(PDO::FETCH_ASSOC);


?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($article['title']) ?> - <?= htmlspecialchars($site_name) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/line-clamp"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=<?= str_replace(' ', '+', $font) ?>&display=swap" rel="stylesheet">
    <style>
        body { font-family: '<?= $font ?>', sans-serif; }
        .primary-bg { background-color: <?= $color ?>; }
        .primary-text { color: <?= $color ?>; }
        .primary-border { border-color: <?= $color ?>; }
        .primary-hover:hover { background-color: <?= $color ?>; color: white; }
    </style>
</head>
<body class="bg-gray-50 text-gray-800">
<nav class="primary-bg text-white border-b border-gray-200 px-4 py-2.5">
  <div class="flex flex-wrap justify-between items-center mx-auto max-w-screen-xl">
    <a href="/" class="flex items-center">
      <span class="self-center text-xl font-semibold whitespace-nowrap"><?= htmlspecialchars($site_name) ?></span>
    </a>
    <button data-collapse-toggle="mobile-menu" type="button" class="inline-flex items-center p-2 ml-3 text-sm text-gray-500 rounded-lg md:hidden hover:bg-gray-100 focus:outline-none" aria-controls="mobile-menu" aria-expanded="false">
      <span class="sr-only">Open main menu</span>
      <svg class="w-6 h-6" aria-hidden="true" fill="currentColor" viewBox="0 0 20 20">
        <path fill-rule="evenodd" d="M3 5h14a1 1 0 010 2H3a1 1 0 110-2zm0 5h14a1 1 0 010 2H3a1 1 0 110-2zm0 5h14a1 1 0 010 2H3a1 1 0 110-2z" clip-rule="evenodd"></path>
      </svg>
    </button>
    <div class="hidden w-full md:block md:w-auto" id="mobile-menu">
      <ul class="flex flex-col mt-4 md:flex-row md:space-x-8 md:mt-0 md:text-sm md:font-medium">
        <li><a href="index.php" class="block py-2 pr-4 pl-3 hover:bg-gray-100">หน้าแรก</a></li>
        <li><a href="all_products.php" class="block py-2 pr-4 pl-3 hover:bg-gray-100">สินค้าทั้งหมด</a></li>
        <li><a href="all_articles.php" class="block py-2 pr-4 pl-3 hover:bg-gray-100">บทความ</a></li>
        <li><a href="payment_notify.php" class="block py-2 pr-4 pl-3 hover:bg-gray-100">แจ้งชำระเงิน</a></li>
        <li><a href="contact.php" class="block py-2 pr-4 pl-3 hover:bg-gray-100">ติดต่อเรา</a></li>
        <li><a href="cart.php" class="block py-2 pr-4 pl-3 hover:bg-gray-100">ตะกร้าสินค้า</a></li>
        <li><a href="login.php" class="block py-2 pr-4 pl-3 hover:bg-gray-100">เข้าสู่ระบบ</a></li>
      </ul>
    </div>
  </div>
</nav>
<div class="max-w-5xl mx-auto px-4 py-8">
  <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
    <div class="md:col-span-2">
      <h1 class="text-4xl font-bold mb-4"><?= htmlspecialchars($article['title']) ?></h1>
      <p class="text-sm text-gray-500 mb-2">
        โดย <span class="font-medium"><?= htmlspecialchars($article['author_name'] ?? 'ไม่ทราบผู้เขียน') ?></span> |
        หมวด <a href="category.php?id=<?= $article['category_id'] ?>" class="text-blue-600 hover:underline">
          <?= htmlspecialchars($article['category_name'] ?? '-') ?></a> |
        <?= date('d M Y', strtotime($article['published_at'])) ?>
      </p>
      <div class="flex justify-end space-x-2 mb-4">
        <button id="decrease-font" class="px-3 py-1 bg-gray-200 rounded">A-</button>
        <button id="increase-font" class="px-3 py-1 bg-gray-200 rounded">A+</button>
      </div>
      <?php if (!empty($article['image'])): ?>
        <img src="uploads/<?= htmlspecialchars($article['image']) ?>" alt="<?= htmlspecialchars($article['title']) ?>" class="mb-6 w-full rounded-lg shadow-md">
      <?php endif; ?>
      <div class="prose prose-lg max-w-none">
        <?= str_replace('<ul>', '<ul class="list-disc list-inside">', html_entity_decode($article['content'])) ?>
      </div>
      <div class="mt-6 flex gap-4">
        <a href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode('https://morsenglove.com/view_article.php?slug=' . $slug) ?>" target="_blank" class="px-4 py-2 bg-blue-600 text-white rounded">แชร์ Facebook</a>
        <a href="https://line.me/R/msg/text/?<?= urlencode($article['title'] . ' https://morsenglove.com/view_article.php?slug=' . $slug) ?>" target="_blank" class="px-4 py-2 bg-green-500 text-white rounded">แชร์ LINE</a>
      </div>
    </div>
    <aside class="md:col-span-1 mt-10 md:mt-0">
      <h2 class="text-xl font-semibold mb-2">บทความล่าสุด</h2>

    <ul class="space-y-4">
  <?php foreach ($latest_articles as $latest): ?>
    <li class="flex items-start space-x-3">
      <?php if (!empty($latest['image'])): ?>
        <img src="uploads/<?= htmlspecialchars($latest['image']) ?>" alt="<?= htmlspecialchars($latest['title']) ?>"
             class="w-16 h-16 object-cover rounded shadow-sm">
      <?php else: ?>
        <div class="w-16 h-16 bg-gray-200 rounded flex items-center justify-center text-gray-400 text-xs">ไม่มีภาพ</div>
      <?php endif; ?>
      <div class="flex-1">
        <a href="view_article.php?slug=<?= htmlspecialchars($latest['slug']) ?>"
           class="text-sm font-semibold text-blue-700 hover:underline">
          <?= htmlspecialchars($latest['title']) ?>  
        </a>
        <div class="text-xs text-gray-500"><?= date('d M Y', strtotime($latest['published_at'])) ?></div>

        <?php
          $excerpt = mb_substr(strip_tags(html_entity_decode($latest['excerpt'])), 0, 150);
          if (mb_strlen($latest['excerpt']) > 150) $excerpt .= '...';

        ?>
        <div class="text-xs text-gray-700 line-clamp-2"><?= htmlspecialchars($excerpt) ?></div>
      </div>
    </li>
  <?php endforeach; ?>
</ul>
    
    </aside>
  </div>
</div>
<script>
  document.addEventListener('DOMContentLoaded', function () {
    const toggleButton = document.querySelector('[data-collapse-toggle]');
    const menu = document.getElementById('mobile-menu');
    toggleButton.addEventListener('click', function () {
      menu.classList.toggle('hidden');
    });

    const content = document.querySelector('.prose');
    let fontSize = 16;
    document.getElementById('increase-font').addEventListener('click', function () {
      fontSize += 1;
      content.style.fontSize = fontSize + 'px';
    });
    document.getElementById('decrease-font').addEventListener('click', function () {
      if (fontSize > 10) {
        fontSize -= 1;
        content.style.fontSize = fontSize + 'px';
      }
    });
  });
</script>
</body>
</html>
