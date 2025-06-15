<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}
require '../includes/db.php';

// โหลดหมวดหมู่บทความ
$stmt = $pdo->query("SELECT id, name FROM article_categories ORDER BY name");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

$error = '';
$success = '';
$currentUserId = $_SESSION['user']['id'] ?? null; // เพิ่มส่วนนี้เพื่อกำหนด author_id

// ฟังก์ชันตรวจสอบชนิดไฟล์ภาพ
function isValidImage($file) {
    $allowed = ['image/jpeg', 'image/png', 'image/gif'];
    // ใช้ getimagesize แทน mime_content_type เพื่อความเข้ากันได้กับทุกเซิร์ฟเวอร์
    $info = @getimagesize($file['tmp_name']);
    if (!$info) return false;
    return in_array($info['mime'], $allowed);
}

function generateUniqueSlug($pdo, $baseText, $table = 'articles') {
    $slug = generateSlug($baseText);
    $uniqueSlug = $slug;
    $i = 1;

    while (true) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM {$table} WHERE slug = ?");
        $stmt->execute([$uniqueSlug]);
        $count = $stmt->fetchColumn();
        if ($count == 0) break;
        $uniqueSlug = $slug . '-' . $i;
        $i++;
    }
    return $uniqueSlug;
}

function generateSlug($text) {
    $text = trim($text);
    $text = strtolower($text);
    $text = preg_replace('/[^ก-๙a-z0-9\s\-]/u', '', $text);
    $text = preg_replace('/\s+/', '-', $text);
    $text = preg_replace('/-+/', '-', $text);
    return $text;
}
// ฟังก์ชัน createExcerpt ที่ใช้ในฟอร์มนี้จะตัด tag HTML ออกและตัดข้อความให้สั้นลง
function createExcerpt($htmlContent, $limit = 250) {
    $text = strip_tags($htmlContent); // ลบแท็ก HTML
    // ลบ &nbsp; และช่องว่างพิเศษ
    $text = preg_replace('/(&nbsp;|&#160;|&#xA0;|\xC2\xA0|\xA0|\u{00A0})+/iu', '', $text);
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace('/[\s\p{Zs}\xA0\x{00A0}]+/u', ' ', $text);
    $text = trim($text);
    if (mb_strlen($text, 'UTF-8') > $limit) {
        $text = mb_substr($text, 0, $limit, 'UTF-8') . '...';
    }
    return $text;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $content = $_POST['content'] ?? '';
    $excerpt = createExcerpt($content); // ← สร้าง excerpt จากเนื้อหา
    $category_id = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
    $published_at = !empty($_POST['published_at']) ? $_POST['published_at'] : date('Y-m-d H:i:s');
    $status = $_POST['status'] ?? 'draft';
    


    if (empty($title)) {
        $error = "กรุณากรอกชื่อบทความด้วยครับ";
    } elseif (empty($content)) {
        $error = "กรุณากรอกเนื้อหาบทความด้วยครับ";
    }

    $imagePath = null;
    if (!$error && isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        if (isValidImage($_FILES['image'])) {
            $uploadDir = __DIR__ . '/../uploads/articles/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

            $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $newName = uniqid('art_') . '.' . strtolower($ext);
            $destPath = $uploadDir . $newName;
            $slug = generateSlug($title);
            $check = $pdo->prepare("SELECT COUNT(*) FROM articles WHERE slug = ?");
            $check->execute([$slug]);
            if ($check->fetchColumn() > 0) {
            $slug .= '-' . time(); // ป้องกันซ้ำ
                }

            if (move_uploaded_file($_FILES['image']['tmp_name'], $destPath)) {
                $imagePath = '../uploads/articles/' . $newName;
            } else {
                $error = "เกิดข้อผิดพลาดในการอัปโหลดภาพ";
            }
        } else {
            $error = "ชนิดไฟล์ภาพไม่ถูกต้อง (อนุญาตเฉพาะ jpg, png, gif)";
        }
    }

    if (!$error) {
        $sql = "INSERT INTO articles (title, slug, content, excerpt, category_id, author_id, published_at, created_at, updated_at, image, status)
        VALUES (:title, :slug, :content, :excerpt, :category_id, :author_id, :published_at, NOW(), NOW(), :image, :status)";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':title', $title);
        $stmt->bindValue(':slug', $slug);
        $stmt->bindValue(':content', $content);
        $stmt->bindValue(':excerpt', $excerpt);
        $stmt->bindValue(':category_id', $category_id, PDO::PARAM_INT);
        $stmt->bindValue(':author_id', $currentUserId, PDO::PARAM_INT);
        $stmt->bindValue(':published_at', $published_at);
        $stmt->bindValue(':image', $imagePath);
        $stmt->bindValue(':status', $status);
        

        if ($stmt->execute()) {
            $success = "เพิ่มบทความเรียบร้อยแล้ว!";
            $title = $content = '';
            $category_id = null;
            $published_at = date('Y-m-d\TH:i');
            $imagePath = null;
        } else {
            $error = "เกิดข้อผิดพลาดในการบันทึกบทความ";
        }
    }
}
?>


<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>เพิ่มบทความใหม่</title>

    <!-- โหลด Google Font Sarabun -->
    <link href="https://fonts.googleapis.com/css2?family=Sarabun&display=swap" rel="stylesheet" />

    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@3.3.2/dist/tailwind.min.css" rel="stylesheet" />
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet" />
    <script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>
     <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://unpkg.com/alpinejs" defer></script>
<link href="https://fonts.googleapis.com/css2?family=Sarabun&display=swap" rel="stylesheet">
    <style>
        body, input, select, textarea {
        font-family: 'Sarabun', sans-serif;
        font-size: 16px;
        }
        /* กำหนดฟอนต์ใน editor ของ Quill */
        .ql-editor {
            font-family: 'Sarabun', sans-serif;
            font-size: 16px;
            min-height: 300px;
        }
    </style>
</head>
<body class="bg-gray-100 text-gray-800">
<?php require 'header.php'; ?>
<div class="max-w-4xl mx-auto bg-white p-6 rounded shadow mt-8">
    <h1 class="text-3xl font-bold mb-6">เพิ่มบทความใหม่</h1>

    <?php if ($error): ?>
        <div class="mb-4 p-3 bg-red-100 text-red-700 rounded"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="mb-4 p-3 bg-green-100 text-green-700 rounded"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <form action="" method="POST" enctype="multipart/form-data" class="space-y-5" onsubmit="return prepareSubmit()">

        <div>
            <label for="title" class="block mb-1 font-semibold">ชื่อบทความ</label>
            <input type="text" id="title" name="title" required
                   class="w-full border border-gray-300 rounded px-3 py-2"
                   value="<?= htmlspecialchars($title ?? '') ?>" />
        </div>

        <div>
            <label for="category_id" class="block mb-1 font-semibold">หมวดหมู่บทความ</label>
            <select id="category_id" name="category_id"
                    class="w-full border border-gray-300 rounded px-3 py-2">
                <option value="">-- เลือกหมวดหมู่ --</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['id'] ?>" <?= (isset($category_id) && $category_id == $cat['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cat['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div>
            <label for="published_at" class="block mb-1 font-semibold">วันที่เผยแพร่</label>
            <input type="datetime-local" id="published_at" name="published_at"
                   class="w-full border border-gray-300 rounded px-3 py-2"
                   value="<?= htmlspecialchars($published_at ?? date('Y-m-d\TH:i')) ?>" />
        </div>

        <div>
            <label class="block mb-1 font-semibold">เนื้อหา</label>
            <div id="editor" style="background: white; border: 1px solid #ccc;"></div>
            <textarea name="content" id="content" hidden><?= htmlspecialchars($content ?? '') ?></textarea>
        </div>

        <div>
            <label for="image" class="block mb-1 font-semibold">ภาพประกอบบทความ</label>
            <input type="file" id="image" name="image" accept="image/jpeg,image/png,image/gif" class="w-full" />
        </div>
        <div>
    <label for="status" class="block mb-1 font-semibold">สถานะบทความ</label>
    <select name="status" id="status"
            class="w-full border border-gray-300 rounded px-3 py-2">
        <option value="draft" <?= (isset($_POST['status']) && $_POST['status'] === 'draft') ? 'selected' : '' ?>>ไม่เผยแพร่</option>
        <option value="published" <?= (isset($_POST['status']) && $_POST['status'] === 'published') ? 'selected' : '' ?>>เผยแพร่</option>
    </select>
</div>
<img id="previewImage" class="mt-2 max-h-48" />
<script>
document.getElementById('image').addEventListener('change', function(e) {
    const [file] = e.target.files;
    if (file) {
        document.getElementById('previewImage').src = URL.createObjectURL(file);
    }
});
</script>
        <button type="submit"
                class="bg-green-600 text-white px-6 py-2 rounded hover:bg-green-700 transition duration-200">
            บันทึกบทความ
        </button>
        <button type="button"
        onclick="previewArticle()"
        class="bg-blue-500 text-white px-6 py-2 rounded hover:bg-blue-600 transition duration-200">
    Preview
</button>
    </form>
</div>

<script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>
<script>
    const quill = new Quill('#editor', {
        theme: 'snow',
        modules: {
            toolbar: [
                [{ 'font': [] }, { 'size': [] }],
                ['bold', 'italic', 'underline', 'strike'],
                [{ 'color': [] }, { 'background': [] }],
                [{ 'script': 'sub'}, { 'script': 'super' }],
                [{ 'header': 1 }, { 'header': 2 }, 'blockquote', 'code-block'],
                [{ 'list': 'ordered'}, { 'list': 'bullet' }, { 'indent': '-1'}, { 'indent': '+1' }],
                ['direction', { 'align': [] }],
                ['link', 'image', 'video'],
                ['clean']
            ]
        }
    });

    quill.root.innerHTML = document.getElementById('content').value;

    function prepareSubmit() {
        const content = document.getElementById('content');
        content.value = quill.root.innerHTML.trim();

        if (content.value === '' || content.value === '<p><br></p>') {
            alert('กรุณากรอกเนื้อหาบทความด้วยครับ');
            return false;
        }
        return true;
    }

    function previewArticle() {
        const title = document.getElementById('title').value.trim();
        const content = quill.root.innerHTML.trim();
        if (!title || !content || content === '<p><br></p>') {
            alert('กรุณากรอกชื่อและเนื้อหาก่อนดูตัวอย่าง');
            return;
        }

                const previewWindow = window.open('', '_blank');
        previewWindow.document.write(`
            <html>
            <head>
                <title>ตัวอย่างบทความ</title>
                <link href="https://cdn.jsdelivr.net/npm/tailwindcss@3.3.2/dist/tailwind.min.css" rel="stylesheet" />
                <link href="https://fonts.googleapis.com/css2?family=Sarabun&display=swap" rel="stylesheet">
                <style>
                    body {
                        font-family: 'Sarabun', sans-serif;
                        padding: 2rem;
                        background-color: #f9fafb;
                        color: #1f2937;
                    }
                    h1 {
                        font-size: 2rem;
                        font-weight: bold;
                        margin-bottom: 1rem;
                    }
                    .content {
                        line-height: 1.7;
                    }
                </style>
            </head>
            <body>
                <h1>${title}</h1>
                <div class="content">${content}</div>
            </body>
            </html>
        `);
        previewWindow.document.close();
    }
</script>
</body>
</html>

