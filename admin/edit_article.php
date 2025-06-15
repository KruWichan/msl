<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}
require '../includes/db.php';

$id = $_GET['id'] ?? null;
if (!$id || !is_numeric($id)) {
    die("ไม่พบ ID ของบทความที่ต้องการแก้ไข");
}

// โหลดหมวดหมู่บทความ
$stmt = $pdo->query("SELECT id, name FROM article_categories ORDER BY name");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

$error = '';
$success = '';

// โหลดข้อมูลบทความเดิม
$stmt = $pdo->prepare("SELECT * FROM articles WHERE id = :id");
$stmt->execute([':id' => $id]);
$article = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$article) {
    die("ไม่พบบทความนี้");
}

$title = $_POST['title'] ?? $article['title'];
$content = $_POST['content'] ?? $article['content'];
$category_id = $_POST['category_id'] ?? $article['category_id'];
$published_at = $_POST['published_at'] ?? date('Y-m-d\TH:i', strtotime($article['published_at']));
$imagePath = $article['image'] ?? null;

function isValidImage($file) {
    $allowed = ['image/jpeg', 'image/png', 'image/gif'];
    return in_array(mime_content_type($file['tmp_name']), $allowed);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $content = $_POST['content'] ?? '';
    $category_id = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
    $published_at = !empty($_POST['published_at']) ? $_POST['published_at'] : date('Y-m-d H:i:s');

    if (empty($title)) {
        $error = "กรุณากรอกชื่อบทความด้วยครับ";
    } elseif (empty($content)) {
        $error = "กรุณากรอกเนื้อหาบทความด้วยครับ";
    }

    if (!$error && isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        if (isValidImage($_FILES['image'])) {
            $uploadDir = dirname(__DIR__) . '/uploads/articles/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

            $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $newName = uniqid('art_') . '.' . strtolower($ext);
            $destPath = $uploadDir . $newName;

            if (move_uploaded_file($_FILES['image']['tmp_name'], $destPath)) {
                if ($imagePath && file_exists(dirname(__DIR__) . '/' . $imagePath)) {
                    unlink(dirname(__DIR__) . '/' . $imagePath);
                }
                $imagePath = '../uploads/articles/' . $newName;
            } else {
                $error = "เกิดข้อผิดพลาดในการอัปโหลดภาพ";
            }
        } else {
            $error = "ชนิดไฟล์ภาพไม่ถูกต้อง (อนุญาตเฉพาะ jpg, png, gif)";
        }
    }

    if (!$error) {
        $sql = "UPDATE articles SET 
                    title = :title,
                    content = :content,
                    category_id = :category_id,
                    published_at = :published_at,
                    updated_at = NOW(),
                    image = :image
                WHERE id = :id";

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':title', $title);
        $stmt->bindValue(':content', $content);
        $stmt->bindValue(':category_id', $category_id, PDO::PARAM_INT);
        $stmt->bindValue(':published_at', $published_at);
        $stmt->bindValue(':image', $imagePath);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);

        if ($stmt->execute()) {
            $success = "แก้ไขบทความเรียบร้อยแล้ว!";
            $article = array_merge($article, [
                'title' => $title,
                'content' => $content,
                'category_id' => $category_id,
                'published_at' => $published_at,
                'image' => $imagePath
            ]);
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
    <title>แก้ไขบทความ</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@3.3.2/dist/tailwind.min.css" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Kanit&family=Sarabun&display=swap" rel="stylesheet" />
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

    /* ใช้ฟอนต์ Sarabun กับพื้นที่เนื้อหาใน Quill */
    .ql-editor {
        font-family: 'Sarabun', sans-serif;
        font-size: 16px;
        line-height: 1.6;
    }
    </style>
</head>
<body class="bg-gray-100 text-gray-800">
<?php require 'header.php'; ?>
<div class="max-w-4xl mx-auto bg-white p-6 rounded shadow mt-8">
    <h1 class="text-3xl font-bold mb-6">แก้ไขบทความ</h1>

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
            <div id="editor" style="height: 300px; background: white; border: 1px solid #ccc;"></div>
            <textarea name="content" id="content" hidden><?= htmlspecialchars($content ?? '') ?></textarea>
        </div>

        <div>
            <label for="image" class="block mb-1 font-semibold">ภาพประกอบบทความ</label>
            <?php if ($imagePath): ?>
                <img src="<?= htmlspecialchars($imagePath) ?>" alt="ภาพประกอบ" class="mb-2 max-h-48" />
            <?php endif; ?>
            <input type="file" id="image" name="image" accept="image/jpeg,image/png,image/gif" class="w-full" />
            <small class="text-gray-500">ถ้าไม่เลือกภาพใหม่ จะเก็บภาพเดิมไว้</small>
        </div>

        <button type="submit"
                class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700 transition duration-200">
            บันทึกการแก้ไข
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
                <title>Preview - ${title}</title>
                <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600&display=swap" rel="stylesheet">
                <style>
                    body { font-family: 'Sarabun', sans-serif; padding: 40px; line-height: 1.7; font-size: 16px; }
                    h1 { font-size: 28px; font-weight: bold; margin-bottom: 20px; }
                </style>
            </head>
            <body>
                <h1>${title}</h1>
                ${content}
            </body>
            </html>
        `);
        previewWindow.document.close();
    }
</script>
</body>
</html>
