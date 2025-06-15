<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}
require '../includes/db.php';

function generateUniqueSlug($pdo, $baseText, $table = 'products') {
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

$errors = [];
$success = false;

// ดึงหมวดหมู่
$stmt = $pdo->query("SELECT id, name FROM product_categories ORDER BY name");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ดึงแท็ก
$stmt = $pdo->query("SELECT id, name FROM tags ORDER BY name");
$allTags = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ตรวจสอบว่า tag_id ที่รับเข้ามาเป็นของแท้
$valid_tag_ids = array_column($allTags, 'id');
$tag_ids = $_POST['tag_ids'] ?? [];
$tag_ids = array_filter($tag_ids);
$tag_ids = array_filter($tag_ids, function($id) use ($valid_tag_ids) {
    return in_array($id, $valid_tag_ids);
});


// เมื่อมีการส่งฟอร์ม
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = floatval($_POST['price'] ?? 0);
    $sale_price = isset($_POST['sale_price']) && is_numeric($_POST['sale_price']) ? (float)$_POST['sale_price'] : null;
    $category_id = intval($_POST['category_id'] ?? 0);
    $stock = intval($_POST['stock'] ?? 0);
    $tag_ids = $_POST['tags'] ?? [];

    if ($name === '') $errors[] = 'กรุณากรอกชื่อสินค้า';
    if ($price <= 0) $errors[] = 'กรุณาระบุราคาสินค้าให้ถูกต้อง';

    // สร้าง slug
    $slug = generateUniqueSlug($pdo, $name);

    // ตรวจสอบไฟล์รูป
    $imageFiles = $_FILES['images'] ?? null;
    $imageNames = [];
    if ($imageFiles && $imageFiles['error'][0] !== UPLOAD_ERR_NO_FILE) {
        foreach ($imageFiles['tmp_name'] as $idx => $tmp) {
            if ($imageFiles['error'][$idx] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($imageFiles['name'][$idx], PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'webp'];
                if (in_array($ext, $allowed)) {
                    $imgName = uniqid('img_', true) . '.' . $ext;
                    if (move_uploaded_file($tmp, '../uploads/products/' . $imgName)) {
                        $imageNames[] = $imgName;
                    }
                } else {
                    $errors[] = 'อนุญาตเฉพาะไฟล์ jpg, png, webp';
                }
            }
        }
    }

    // บันทึกลงฐานข้อมูล
    if (!$errors) {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("INSERT INTO products 
(name, slug, description, price, sale_price, stock, category_id, created_at) 
VALUES 
(:name, :slug, :description, :price, :sale_price, :stock, :category_id, NOW())");

            $stmt->execute([
                ':name' => $name,
                ':slug' => $slug,
                ':description' => $description,
                ':price' => $price,
                ':sale_price' => $sale_price,
                ':stock' => $stock,
                ':category_id' => $category_id > 0 ? $category_id : null
            ]);

            $product_id = $pdo->lastInsertId();

            // บันทึกรูปภาพ
            foreach ($imageNames as $img) {
                $stmt = $pdo->prepare("INSERT INTO product_images (product_id, filename) VALUES (?, ?)");
                $stmt->execute([$product_id, $img]);
            }

            // บันทึก tag
            foreach ($tag_ids as $tag_id) {
                $stmt = $pdo->prepare("INSERT INTO product_tags (product_id, tag_id) VALUES (?, ?)");
                $stmt->execute([$product_id, $tag_id]);
            }

            $pdo->commit();
            $success = true;

        } catch (PDOException $e) {
            $pdo->rollBack();
            if ($e->getCode() == 23000) {
                $errors[] = "ชื่อสินค้านี้อาจทำให้ slug ซ้ำ กรุณาเปลี่ยนชื่อสินค้า";
            } else {
                $errors[] = "เกิดข้อผิดพลาด: " . $e->getMessage();
            }
        }
    }
}
?>


<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>เพิ่มสินค้า</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://unpkg.com/alpinejs" defer></script>
</head>
<body class="bg-gray-100 text-gray-800">
<?php require 'header.php'; ?>
  <div class="max-w-3xl mx-auto bg-white p-6 rounded shadow">
    <h1 class="text-2xl font-bold mb-6">เพิ่มสินค้าใหม่</h1>

    <?php if ($success): ?>
      <div class="bg-green-100 text-green-800 p-4 rounded mb-4">✅ เพิ่มสินค้าเรียบร้อยแล้ว!</div>
    <?php endif; ?>

    <?php if ($errors): ?>
      <div class="bg-red-100 text-red-800 p-4 rounded mb-4">
        <ul class="list-disc pl-6">
          <?php foreach ($errors as $e): ?>
            <li><?= htmlspecialchars($e) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" class="space-y-4">
      <div>
        <label class="block font-medium">ชื่อสินค้า *</label>
        <input type="text" name="name" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" class="border rounded w-full px-3 py-2" required />
      </div>

      <div>
        <label class="block font-medium">รายละเอียด</label>
        <textarea name="description" rows="4" class="border rounded w-full px-3 py-2"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="block font-medium">ราคาปกติ *</label>
          <input type="number" step="0.01" name="price" value="<?= htmlspecialchars($_POST['price'] ?? '') ?>" class="border rounded w-full px-3 py-2" required />
        </div>
        <div>
          <label class="block font-medium">ราคาพิเศษ</label>
          <input type="number" step="0.01" name="sale_price" value="<?= htmlspecialchars($_POST['sale_price'] ?? '') ?>" class="border rounded w-full px-3 py-2" />
        </div>
      </div>

      <div>
        <label class="block font-medium">จำนวนคงเหลือ (Stock)</label>
        <input type="number" name="stock" min="0" value="<?= htmlspecialchars($_POST['stock'] ?? '0') ?>" class="border rounded w-full px-3 py-2" required />
      </div>

      <div>
        <label class="block font-medium">หมวดหมู่สินค้า</label>
        <select name="category_id" class="border rounded w-full px-3 py-2">
          <option value="">-- เลือกหมวดหมู่ --</option>
          <?php foreach ($categories as $cat): ?>
            <option value="<?= $cat['id'] ?>" <?= ($cat['id'] == ($_POST['category_id'] ?? '')) ? 'selected' : '' ?>>
              <?= htmlspecialchars($cat['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div>
        <label class="block font-medium">แท็กสินค้า</label>
        <div class="grid grid-cols-2 gap-2">
          <?php foreach ($allTags as $tag): ?>
            <label class="flex items-center space-x-2">
              <input type="checkbox" name="tags[]" value="<?= $tag['id'] ?>"
                <?= (isset($_POST['tags']) && in_array($tag['id'], $_POST['tags'])) ? 'checked' : '' ?>>
              <span><?= htmlspecialchars($tag['name']) ?></span>
            </label>
          <?php endforeach; ?>
        </div>
      </div>

      <div>
        <label class="block font-medium">รูปภาพสินค้า (เลือกได้หลายไฟล์)</label>
        <input type="file" name="images[]" accept=".jpg,.jpeg,.png,.webp" class="block mt-1" multiple />
      </div>

      <div class="flex justify-between mt-6">
        <a href="manage_products.php" class="bg-gray-400 hover:bg-gray-500 text-white px-4 py-2 rounded">ย้อนกลับ</a>
        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded">บันทึกสินค้า</button>
      </div>
    </form>
  </div>
</body>
</html>
