<?php
require_once 'includes/db.php';
require_once 'includes/helpers.php'; // ตรวจสอบว่า helper function เช่น date_format หรืออื่นๆ ที่ใช้ในหน้ามีอยู่ในไฟล์นี้

// ดึงบทความล่าสุด (รวมถึงคอลัมน์ image)
$articles = $pdo->query("
    SELECT id, title, slug, excerpt, published_at, image -- เลือกคอลัมน์ที่จำเป็น รวมถึง image
    FROM articles
    WHERE status = 'published'
    ORDER BY published_at DESC
    LIMIT 8
")->fetchAll(PDO::FETCH_ASSOC);
?>

<section class="py-12 bg-gray-100">
    <div class="container mx-auto px-4">
    <h2 class="text-2xl font-bold mb-4 text-gray-800">บทความล่าสุด</h2>
    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
        <?php foreach ($articles as $a): ?>
            <a href="view_article.php?slug=<?= urlencode($a['slug']) ?>"
               class="block bg-white rounded-xl shadow-md overflow-hidden hover:shadow-lg transition-all duration-200 group focus:outline-none focus:ring-2 focus:ring-blue-400">
                <?php if (!empty($a['image'])): ?>
                    <img src="uploads/<?= htmlspecialchars($a['image']) ?>" alt="<?= htmlspecialchars($a['title']) ?>" class="w-full h-40 object-cover">
                <?php else: ?>
                    <img src="path/to/default-article-image.jpg" alt="No image available" class="w-full h-40 object-cover">
                <?php endif; ?>
                <div class="p-4">
                    <h3 class="text-lg font-semibold text-gray-800 line-clamp-2 group-hover:text-blue-700"><?= htmlspecialchars($a['title']) ?></h3>
                    <p class="text-sm text-gray-500 mb-2"><?= date("d M Y", strtotime($a['published_at'])) ?></p>
                    <p class="text-sm text-gray-600 line-clamp-3">
                        <?php
                        $excerpt = $a['excerpt'];
                        if (!$excerpt) {
                            // ดึง content จากฐานข้อมูล (ถ้ายังไม่ได้ join มา ให้ query เพิ่ม)
                            $stmt = $pdo->prepare("SELECT content FROM articles WHERE id = ?");
                            $stmt->execute([$a['id']]);
                            $row = $stmt->fetch(PDO::FETCH_ASSOC);
                            $content = html_entity_decode((string)($row['content'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                            $content = preg_replace('/<pre.*?<\/pre>/is', '', $content);
                            $content = preg_replace('/<code.*?<\/code>/is', '', $content);
                            $content = strip_tags($content);
                            $content = preg_replace('/(&nbsp;|&#160;|&#xA0;|\xC2\xA0|\xA0)+/iu', '', $content);
                            $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                            $content = preg_replace('/[ \t\r\n\0\x0B\xA0]+/u', ' ', $content);
                            $content = trim($content);
                            $content = preg_replace('/([ก-๙])\s*หรือ\s*([ก-๙])/u', '$1หรือ$2', $content);
                            $content = preg_replace('/\s+/u', ' ', $content);
                            $content = preg_replace('/\s*([.,;:!?])\s*/u', '$1', $content);
                            $excerpt = mb_substr($content, 0, 300, 'UTF-8');
                            if (mb_strlen($content, 'UTF-8') > 300) {
                                $excerpt .= '...';
                            }
                        }
                        echo htmlspecialchars($excerpt);
                        ?>
                    </p>
                    <span class="inline-block mt-4 px-4 py-2 rounded-lg bg-blue-600 text-white font-semibold shadow hover:bg-blue-700 hover:shadow-md transition duration-200 text-sm">
                        อ่านต่อ
                        <svg class="inline w-4 h-4 ml-1 -mt-1" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
                        </svg>
                    </span>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
    </div>
</section>