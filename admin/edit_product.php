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

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) {
    echo "ไม่พบสินค้าที่ต้องการแก้ไข";
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
$stmt->execute([$id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$product) {
    echo "ไม่พบสินค้าที่ต้องการแก้ไข";
    exit;
}

// ดึงหมวดหมู่
$categories = $pdo->query("SELECT id, name FROM product_categories ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// ดึงแท็ก
$stmt = $pdo->query("SELECT id, name FROM product_tags ORDER BY name");
$allTags = $stmt->fetchAll(PDO::FETCH_ASSOC);

// โหลด tag_ids ที่เคยเลือกไว้
$stmt = $pdo->prepare("SELECT tag_id FROM product_tag_map WHERE product_id = ?");
$stmt->execute([$id]);
$selectedTagIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

// ดึงรูปภาพทั้งหมดของสินค้า
$stmt = $pdo->prepare("SELECT * FROM product_images WHERE product_id = ?");
$stmt->execute([$id]);
$productImages = $stmt->fetchAll(PDO::FETCH_ASSOC);

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = floatval($_POST['price'] ?? 0);
    $sale_price = $_POST['sale_price'] !== '' ? floatval($_POST['sale_price']) : null;
    $category_id = intval($_POST['category_id'] ?? 0);
    $stock = intval($_POST['stock'] ?? 0);
    $tag_ids = $_POST['tags'] ?? [];

    if ($name === '') $errors[] = 'กรุณากรอกชื่อสินค้า';
    if ($price <= 0) $errors[] = 'กรุณาระบุราคาสินค้าให้ถูกต้อง';

    // อัปโหลดรูปภาพใหม่ (หลายไฟล์)
    $imageFiles = $_FILES['images'] ?? null;
    $newImageNames = [];
    if ($imageFiles && $imageFiles['error'][0] !== UPLOAD_ERR_NO_FILE) {
        foreach ($imageFiles['tmp_name'] as $idx => $tmp) {
            if ($imageFiles['error'][$idx] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($imageFiles['name'][$idx], PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'webp'];
                if (in_array($ext, $allowed)) {
                    $imgName = uniqid('img_', true) . '.' . $ext;
                    if (move_uploaded_file($tmp, '../uploads/products/' . $imgName)) {
                        $newImageNames[] = $imgName;
                    }
                } else {
                    $errors[] = 'อนุญาตเฉพาะไฟล์ jpg, png, webp';
                }
            }
        }
    }

    if (!$errors) {
        // อัปเดตข้อมูลสินค้า
        $updateStmt = $pdo->prepare("
          UPDATE products SET 
            name = :name,
            description = :description,
            price = :price,
            sale_price = :sale_price,
            stock = :stock,
            category_id = :category_id,
            updated_at = NOW()
          WHERE id = :id
        ");

        $updateStmt->execute([
            ':name' => $name,
            ':description' => $description,
            ':price' => $price,
            ':sale_price' => $sale_price,
            ':stock' => $stock,
            ':category_id' => $category_id ?: null,
            ':id' => $id,
        ]);

        // ลบแท็กเดิม
        $pdo->prepare("DELETE FROM product_tag_map WHERE product_id = ?")->execute([$id]);
        // เพิ่มแท็กใหม่
        if (!empty($tag_ids)) {
            $insertTagStmt = $pdo->prepare("INSERT INTO product_tag_map (product_id, tag_id) VALUES (?, ?)");
            foreach ($tag_ids as $tag_id) {
                $insertTagStmt->execute([$id, $tag_id]);
            }
        }

        // เพิ่มรูปใหม่
        foreach ($newImageNames as $img) {
            $stmt = $pdo->prepare("INSERT INTO product_images (product_id, filename) VALUES (?, ?)");
            $stmt->execute([$id, $img]);
        }

        $success = true;

        // อัปเดตข้อมูลในตัวแปร $product เพื่อแสดงผลหน้า form ใหม่
        $product = array_merge($product, [
            'name' => $name,
            'description' => $description,
            'price' => $price,
            'sale_price' => $sale_price,
            'stock' => $stock,
            'category_id' => $category_id
        ]);
        $selectedTagIds = $tag_ids;

        // โหลดรูปใหม่
        $stmt = $pdo->prepare("SELECT * FROM product_images WHERE product_id = ?");
        $stmt->execute([$id]);
        $productImages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8" />
    <title>แก้ไขสินค้า</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs" defer></script>
</head>
<body class="bg-gray-100 text-gray-800">
<?php require 'header.php'; ?>
<div class="max-w-3xl mx-auto bg-white p-6 rounded shadow">
    <h1 class="text-2xl font-bold mb-6">แก้ไขสินค้า</h1>

    <?php if ($success): ?>
        <div class="bg-green-100 text-green-800 p-4 rounded mb-4">✅ แก้ไขสินค้าเรียบร้อยแล้ว!</div>
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
            <input type="text" name="name" value="<?= htmlspecialchars($product['name']) ?>" class="border rounded w-full px-3 py-2" required />
        </div>

        <div>
            <label class="block font-medium">รายละเอียด</label>
            <textarea name="description" rows="4" class="border rounded w-full px-3 py-2"><?= htmlspecialchars($product['description']) ?></textarea>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block font-medium">ราคาปกติ *</label>
                <input type="number" step="0.01" name="price" value="<?= htmlspecialchars($product['price']) ?>" class="border rounded w-full px-3 py-2" required />
            </div>
            <div>
                <label class="block font-medium">ราคาพิเศษ</label>
                <input type="number" step="0.01" name="sale_price" value="<?= htmlspecialchars($product['sale_price']) ?>" class="border rounded w-full px-3 py-2" />
            </div>
        </div>

        <div>
            <label class="block font-medium">จำนวนคงเหลือ (Stock)</label>
            <input type="number" name="stock" min="0" value="<?= htmlspecialchars($product['stock']) ?>" class="border rounded w-full px-3 py-2" required />
        </div>

        <div>
            <label class="block font-medium">หมวดหมู่สินค้า</label>
            <select name="category_id" class="border rounded w-full px-3 py-2">
                <option value="">-- เลือกหมวดหมู่ --</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['id'] ?>" <?= ($cat['id'] == $product['category_id']) ? 'selected' : '' ?>>
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
                            <?= in_array($tag['id'], $_POST['tags'] ?? $selectedTagIds) ? 'checked' : '' ?>>
                        <span><?= htmlspecialchars($tag['name']) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div>
            <label class="block font-medium">รูปภาพสินค้า (อัปโหลดเพิ่มได้หลายไฟล์)</label>
            <div class="flex flex-wrap gap-2 mb-2">
                <?php foreach ($productImages as $img): ?>
                    <div class="relative">
                        <img src="../uploads/products/<?= htmlspecialchars($img['filename']) ?>" class="h-24 w-24 object-cover rounded border" />
                        <a href="delete_product_image.php?id=<?= $img['id'] ?>&product_id=<?= $id ?>" class="absolute top-0 right-0 bg-red-600 text-white rounded-full px-2 py-0.5 text-xs" onclick="return confirm('ลบรูปนี้?')">ลบ</a>
                    </div>
                <?php endforeach; ?>
            </div>
            <input type="file" name="images[]" accept=".jpg,.jpeg,.png,.webp" class="block mt-1" multiple />
        </div>

        <div class="flex justify-between mt-6">
            <a href="manage_products.php" class="bg-gray-400 hover:bg-gray-500 text-white px-4 py-2 rounded">ย้อนกลับ</a>
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded">บันทึก</button>
        </div>
    </form>
</div>
</body>
</html>
