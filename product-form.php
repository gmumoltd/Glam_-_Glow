<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_role(['owner']);

$id = isset($_GET['id']) ? (int) $_GET['id'] : null;
$product = null;
$variants = [];
$images = [];

if ($id) {
    $stmt = db()->prepare('SELECT * FROM products WHERE id = ?');
    $stmt->execute([$id]);
    $product = $stmt->fetch();
    if (!$product) {
        header('Location: ' . BASE_URL . '/inventory.php');
        exit;
    }

    // Quick image-order actions (cover / move) — handled as simple GET redirects
    // so they don't get tangled up with the main form's POST save logic.
    if (isset($_GET['set_cover'])) {
        $coverStmt = db()->prepare('SELECT sort_order FROM product_images WHERE id = ? AND product_id = ?');
        $coverStmt->execute([(int) $_GET['set_cover'], $id]);
        $coverOrder = $coverStmt->fetchColumn();
        if ($coverOrder !== false && (int) $coverOrder !== 0) {
            $pdo = db();
            $pdo->beginTransaction();
            $pdo->prepare('UPDATE product_images SET sort_order = sort_order + 1 WHERE product_id = ? AND sort_order < ?')->execute([$id, $coverOrder]);
            $pdo->prepare('UPDATE product_images SET sort_order = 0 WHERE id = ?')->execute([(int) $_GET['set_cover']]);
            $pdo->commit();
        }
        header('Location: ' . BASE_URL . '/product-form.php?id=' . $id);
        exit;
    }
    if (isset($_GET['move_image'], $_GET['direction'])) {
        $moveId = (int) $_GET['move_image'];
        $dir = $_GET['direction'] === 'left' ? -1 : 1;
        $allImages = db()->prepare('SELECT id, sort_order FROM product_images WHERE product_id = ? ORDER BY sort_order');
        $allImages->execute([$id]);
        $ordered = $allImages->fetchAll();
        $pos = array_search($moveId, array_column($ordered, 'id'));
        $swapPos = $pos !== false ? $pos + $dir : null;
        if ($pos !== false && $swapPos !== null && isset($ordered[$swapPos])) {
            $pdo = db();
            $pdo->prepare('UPDATE product_images SET sort_order = ? WHERE id = ?')->execute([$ordered[$swapPos]['sort_order'], $ordered[$pos]['id']]);
            $pdo->prepare('UPDATE product_images SET sort_order = ? WHERE id = ?')->execute([$ordered[$pos]['sort_order'], $ordered[$swapPos]['id']]);
        }
        header('Location: ' . BASE_URL . '/product-form.php?id=' . $id);
        exit;
    }

    $variants = get_variants_for_product($id);
    $imgStmt = db()->prepare('SELECT * FROM product_images WHERE product_id = ? ORDER BY sort_order');
    $imgStmt->execute([$id]);
    $images = $imgStmt->fetchAll();
}

$categories = get_categories();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $collection = trim($_POST['collection'] ?? '');
    $categoryId = (int) ($_POST['category_id'] ?? 0);
    $sku = trim($_POST['sku'] ?? '');
    $price = (float) ($_POST['price'] ?? 0);
    $costPrice = (float) ($_POST['cost_price'] ?? 0);
    $reorderLevel = (int) ($_POST['reorder_level'] ?? 10);
    $description = trim($_POST['description'] ?? '');
    $onSale = isset($_POST['on_sale']) ? 1 : 0;
    $discountPercent = $_POST['discount_percent'] !== '' ? (float) $_POST['discount_percent'] : null;
    $variantIdsIn = $_POST['variant_id'] ?? [];
    $colorsIn = $_POST['variant_color'] ?? [];
    $sizesIn = $_POST['variant_size'] ?? [];
    $stocksIn = $_POST['variant_stock'] ?? [];
    $deleteImageIds = array_map('intval', $_POST['delete_image'] ?? []);
    $skippedDeletes = [];

    if ($name === '') $errors[] = 'Product name is required.';
    if (!$categoryId) $errors[] = 'Category is required.';
    if ($sku === '') $errors[] = 'SKU is required.';
    if ($price <= 0) $errors[] = 'Price must be greater than 0.';
    if ($onSale && $discountPercent === null) $errors[] = 'Set a discount % when marking a product on sale.';

    if (empty($errors)) {
        $slug = slugify($name) . '-' . substr(md5($sku), 0, 5);
        $pdo = db();
        $pdo->beginTransaction();
        try {
            if ($id) {
                $stmt = $pdo->prepare('UPDATE products SET category_id=?, name=?, collection=?, sku=?, description=?, price=?, cost_price=?, reorder_level=?, on_sale=?, discount_percent=? WHERE id=?');
                $stmt->execute([$categoryId, $name, $collection, $sku, $description, $price, $costPrice, $reorderLevel, $onSale, $discountPercent, $id]);
                $productId = $id;
            } else {
                $stmt = $pdo->prepare('INSERT INTO products (category_id, name, collection, slug, sku, description, price, cost_price, reorder_level, on_sale, discount_percent) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
                $stmt->execute([$categoryId, $name, $collection, $slug, $sku, $description, $price, $costPrice, $reorderLevel, $onSale, $discountPercent]);
                $productId = $pdo->lastInsertId();
            }

            // Upsert variants: update rows that carried a variant_id, insert the rest.
            // We never blind-delete-then-recreate, since a variant may already be
            // referenced by order_items once it's been sold through the POS.
            $keptVariantIds = [];
            $updateVarStmt = $pdo->prepare('UPDATE product_variants SET color=?, size=?, stock=? WHERE id=? AND product_id=?');
            $insertVarStmt = $pdo->prepare('INSERT INTO product_variants (product_id, color, size, stock) VALUES (?,?,?,?)');
            foreach ($colorsIn as $i => $color) {
                $color = trim($color);
                $size = trim($sizesIn[$i] ?? '');
                $stock = (int) ($stocksIn[$i] ?? 0);
                $existingVariantId = (int) ($variantIdsIn[$i] ?? 0);
                if ($color === '' || $size === '') {
                    continue;
                }
                if ($existingVariantId) {
                    $updateVarStmt->execute([$color, $size, $stock, $existingVariantId, $productId]);
                    $keptVariantIds[] = $existingVariantId;
                } else {
                    $insertVarStmt->execute([$productId, $color, $size, $stock]);
                    $keptVariantIds[] = (int) $pdo->lastInsertId();
                }
            }

            // Any existing variant not resubmitted was removed in the UI — delete it,
            // unless it already has sales history (FK constraint), in which case skip it.
            if ($id) {
                $existingIds = array_column(get_variants_for_product($productId), 'id');
                $toRemove = array_diff($existingIds, $keptVariantIds);
                foreach ($toRemove as $variantId) {
                    try {
                        $pdo->prepare('DELETE FROM product_variants WHERE id = ?')->execute([$variantId]);
                    } catch (PDOException $e) {
                        $skippedDeletes[] = $variantId;
                    }
                }
            }

            if (!empty($deleteImageIds)) {
                $imgToDeleteStmt = $pdo->prepare('SELECT image_path FROM product_images WHERE id = ? AND product_id = ?');
                $delStmt = $pdo->prepare('DELETE FROM product_images WHERE id = ? AND product_id = ?');
                foreach ($deleteImageIds as $imgId) {
                    $imgToDeleteStmt->execute([$imgId, $productId]);
                    $path = $imgToDeleteStmt->fetchColumn();
                    $delStmt->execute([$imgId, $productId]);
                    if ($path && is_file(__DIR__ . '/uploads/products/' . $path)) {
                        @unlink(__DIR__ . '/uploads/products/' . $path);
                    }
                }
            }

            if (!empty($_FILES['images']['name'][0])) {
                $maxSort = (int) $pdo->query('SELECT COALESCE(MAX(sort_order), -1) FROM product_images WHERE product_id = ' . (int) $productId)->fetchColumn();
                $imgStmt = $pdo->prepare('INSERT INTO product_images (product_id, image_path, sort_order) VALUES (?,?,?)');
                foreach ($_FILES['images']['name'] as $i => $filename) {
                    if ($_FILES['images']['error'][$i] !== UPLOAD_ERR_OK) continue;
                    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) continue;
                    $newName = 'p' . $productId . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
                    move_uploaded_file($_FILES['images']['tmp_name'][$i], __DIR__ . '/uploads/products/' . $newName);
                    $imgStmt->execute([$productId, $newName, ++$maxSort]);
                }
            }

            if (isset($_POST['remove_video']) && !empty($product['video_path']) && is_file(__DIR__ . '/uploads/videos/' . $product['video_path'])) {
                @unlink(__DIR__ . '/uploads/videos/' . $product['video_path']);
                $pdo->prepare('UPDATE products SET video_path = NULL WHERE id = ?')->execute([$productId]);
            }

            if (!empty($_FILES['video']['name']) && $_FILES['video']['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['video']['name'], PATHINFO_EXTENSION));
                if (in_array($ext, ['mp4', 'webm', 'mov'], true)) {
                    if (!empty($product['video_path']) && is_file(__DIR__ . '/uploads/videos/' . $product['video_path'])) {
                        @unlink(__DIR__ . '/uploads/videos/' . $product['video_path']);
                    }
                    $videoName = 'p' . $productId . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
                    move_uploaded_file($_FILES['video']['tmp_name'], __DIR__ . '/uploads/videos/' . $videoName);
                    $pdo->prepare('UPDATE products SET video_path = ? WHERE id = ?')->execute([$videoName, $productId]);
                } else {
                    $errors[] = 'Video must be mp4, webm, or mov.';
                }
            }

            $pdo->commit();
            header('Location: ' . BASE_URL . '/inventory.php');
            exit;
        } catch (Throwable $e) {
            $pdo->rollBack();
            $errors[] = 'Save failed: ' . $e->getMessage();
        }
    }
}

$pageTitle = $id ? 'Edit Product' : 'Add Product';
$activeNav = 'inventory';
require __DIR__ . '/includes/layout-header.php';
?>

<h2 class="font-headline-lg text-headline-lg text-primary"><?= $pageTitle ?></h2>

<?php foreach ($errors as $err): ?>
  <div class="bg-error-container text-on-error-container p-3 rounded-lg font-body-sm"><?= htmlspecialchars($err) ?></div>
<?php endforeach; ?>

<form method="post" enctype="multipart/form-data" class="grid grid-cols-1 lg:grid-cols-3 gap-gutter">
  <div class="lg:col-span-2 space-y-stack-lg">
    <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-card-padding space-y-4">
      <div>
        <label class="font-label-caps text-label-caps text-slate-gray block mb-1">Product Name</label>
        <input name="name" value="<?= htmlspecialchars($product['name'] ?? '') ?>" class="w-full px-4 py-2 border border-outline-variant rounded-lg focus:ring-2 focus:ring-primary" required>
      </div>
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="font-label-caps text-label-caps text-slate-gray block mb-1">Collection (optional)</label>
          <input name="collection" value="<?= htmlspecialchars($product['collection'] ?? '') ?>" class="w-full px-4 py-2 border border-outline-variant rounded-lg focus:ring-2 focus:ring-primary" placeholder="e.g. Streetwear Series">
        </div>
        <div>
          <label class="font-label-caps text-label-caps text-slate-gray block mb-1">Category</label>
          <select name="category_id" class="w-full px-4 py-2 border border-outline-variant rounded-lg focus:ring-2 focus:ring-primary" required>
            <option value="">-- Select --</option>
            <?php foreach ($categories as $cat): ?>
              <option value="<?= $cat['id'] ?>" <?= (($product['category_id'] ?? null) == $cat['id']) ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="grid grid-cols-3 gap-4">
        <div>
          <label class="font-label-caps text-label-caps text-slate-gray block mb-1">SKU</label>
          <input name="sku" value="<?= htmlspecialchars($product['sku'] ?? '') ?>" class="w-full px-4 py-2 border border-outline-variant rounded-lg font-label-numeric focus:ring-2 focus:ring-primary" required>
        </div>
        <div>
          <label class="font-label-caps text-label-caps text-slate-gray block mb-1">Selling Price (KES)</label>
          <input type="number" step="0.01" name="price" value="<?= htmlspecialchars($product['price'] ?? '') ?>" class="w-full px-4 py-2 border border-outline-variant rounded-lg focus:ring-2 focus:ring-primary" required>
        </div>
        <div>
          <label class="font-label-caps text-label-caps text-slate-gray block mb-1">Cost Price (KES)</label>
          <input type="number" step="0.01" name="cost_price" value="<?= htmlspecialchars($product['cost_price'] ?? '') ?>" class="w-full px-4 py-2 border border-outline-variant rounded-lg focus:ring-2 focus:ring-primary">
        </div>
      </div>
      <div>
        <label class="font-label-caps text-label-caps text-slate-gray block mb-1">Reorder Level (low-stock threshold)</label>
        <input type="number" name="reorder_level" value="<?= htmlspecialchars($product['reorder_level'] ?? 10) ?>" class="w-32 px-4 py-2 border border-outline-variant rounded-lg focus:ring-2 focus:ring-primary">
      </div>
      <div>
        <label class="font-label-caps text-label-caps text-slate-gray block mb-1">Description</label>
        <textarea name="description" rows="3" class="w-full px-4 py-2 border border-outline-variant rounded-lg focus:ring-2 focus:ring-primary"><?= htmlspecialchars($product['description'] ?? '') ?></textarea>
      </div>
    </div>

    <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-card-padding">
      <h4 class="font-headline-md text-[16px] font-bold text-primary mb-3 flex items-center gap-2"><span class="material-symbols-outlined text-danger-red">sell</span>Promotion</h4>
      <label class="flex items-center gap-2 mb-3">
        <input type="checkbox" name="on_sale" value="1" <?= !empty($product['on_sale']) ? 'checked' : '' ?> class="w-4 h-4">
        <span class="font-body-sm">Show "On Sale" sticker to customers</span>
      </label>
      <label class="font-label-caps text-label-caps text-slate-gray block mb-1">Discount %</label>
      <input type="number" step="0.1" name="discount_percent" value="<?= htmlspecialchars($product['discount_percent'] ?? '') ?>" class="w-32 px-4 py-2 border border-outline-variant rounded-lg focus:ring-2 focus:ring-primary" placeholder="e.g. 20">
    </div>

    <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-card-padding">
      <h4 class="font-headline-md text-[16px] font-bold text-primary mb-3">Variants (Color / Size / Stock)</h4>
      <table class="w-full" id="variantTable">
        <thead><tr class="text-left"><th class="font-label-caps text-label-caps text-slate-gray pb-2">Color</th><th class="font-label-caps text-label-caps text-slate-gray pb-2">Size</th><th class="font-label-caps text-label-caps text-slate-gray pb-2">Stock</th><th></th></tr></thead>
        <tbody>
          <?php if ($variants): foreach ($variants as $v): ?>
            <tr>
              <td class="pr-2 pb-2">
                <input type="hidden" name="variant_id[]" value="<?= $v['id'] ?>">
                <input name="variant_color[]" value="<?= htmlspecialchars($v['color']) ?>" class="w-full px-3 py-1.5 border border-outline-variant rounded-lg">
              </td>
              <td class="pr-2 pb-2"><input name="variant_size[]" value="<?= htmlspecialchars($v['size']) ?>" class="w-full px-3 py-1.5 border border-outline-variant rounded-lg"></td>
              <td class="pr-2 pb-2"><input type="number" name="variant_stock[]" value="<?= $v['stock'] ?>" class="w-full px-3 py-1.5 border border-outline-variant rounded-lg"></td>
              <td class="pb-2"><button type="button" onclick="this.closest('tr').remove()" class="text-danger-red material-symbols-outlined" title="Remove (only deletes if it has no sales history)">close</button></td>
            </tr>
          <?php endforeach; else: ?>
            <tr>
              <td class="pr-2 pb-2">
                <input type="hidden" name="variant_id[]" value="">
                <input name="variant_color[]" placeholder="Black" class="w-full px-3 py-1.5 border border-outline-variant rounded-lg">
              </td>
              <td class="pr-2 pb-2"><input name="variant_size[]" placeholder="32" class="w-full px-3 py-1.5 border border-outline-variant rounded-lg"></td>
              <td class="pr-2 pb-2"><input type="number" name="variant_stock[]" placeholder="10" class="w-full px-3 py-1.5 border border-outline-variant rounded-lg"></td>
              <td class="pb-2"><button type="button" onclick="this.closest('tr').remove()" class="text-danger-red material-symbols-outlined">close</button></td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
      <button type="button" onclick="addVariantRow()" class="mt-2 font-label-caps text-label-caps text-primary flex items-center gap-1">
        <span class="material-symbols-outlined text-[18px]">add</span> Add Variant
      </button>
    </div>
  </div>

  <div class="space-y-stack-lg">
    <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-card-padding">
      <div class="flex justify-between items-center mb-3">
        <h4 class="font-headline-md text-[16px] font-bold text-primary">Product Images</h4>
        <?php if ($id && count($images) > 1): ?>
          <a href="<?= BASE_URL ?>/product-view.php?id=<?= $id ?>" class="font-label-caps text-[11px] text-primary">View Carousel</a>
        <?php endif; ?>
      </div>
      <?php if ($images): ?>
        <div class="grid grid-cols-3 gap-2 mb-3">
          <?php foreach ($images as $i => $img): ?>
            <div class="relative">
              <?php if ($i === 0): ?>
                <span class="absolute top-1 left-1 bg-primary text-white text-[9px] font-bold px-1.5 py-0.5 rounded-full z-10">COVER</span>
              <?php endif; ?>
              <img src="<?= BASE_URL ?>/uploads/products/<?= htmlspecialchars($img['image_path']) ?>" class="w-full h-20 object-cover rounded-lg border border-outline-variant">
              <label class="absolute top-1 right-1 bg-white/90 rounded-full p-1 cursor-pointer" title="Remove">
                <input type="checkbox" name="delete_image[]" value="<?= $img['id'] ?>" class="align-middle">
              </label>
              <div class="absolute bottom-1 inset-x-1 flex justify-between gap-1">
                <?php if ($i > 0): ?>
                  <a href="<?= BASE_URL ?>/product-form.php?id=<?= $id ?>&move_image=<?= $img['id'] ?>&direction=left" class="bg-white/90 rounded-full w-5 h-5 flex items-center justify-center" title="Move left"><span class="material-symbols-outlined text-[14px]">chevron_left</span></a>
                <?php else: ?><span></span><?php endif; ?>
                <?php if ($i !== 0): ?>
                  <a href="<?= BASE_URL ?>/product-form.php?id=<?= $id ?>&set_cover=<?= $img['id'] ?>" class="bg-white/90 rounded-full w-5 h-5 flex items-center justify-center" title="Set as cover"><span class="material-symbols-outlined text-[14px]">star</span></a>
                <?php endif; ?>
                <?php if ($i < count($images) - 1): ?>
                  <a href="<?= BASE_URL ?>/product-form.php?id=<?= $id ?>&move_image=<?= $img['id'] ?>&direction=right" class="bg-white/90 rounded-full w-5 h-5 flex items-center justify-center" title="Move right"><span class="material-symbols-outlined text-[14px]">chevron_right</span></a>
                <?php else: ?><span></span><?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
        <p class="text-[11px] text-slate-gray mb-3">The cover image is what customers see first. Use the arrows to reorder, the star to set a new cover, or tick an image and save to remove it.</p>
      <?php endif; ?>
      <label class="font-label-caps text-label-caps text-slate-gray block mb-1">Add Images</label>
      <input type="file" name="images[]" accept="image/*" multiple class="w-full text-body-sm">
    </div>

    <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-card-padding">
      <h4 class="font-headline-md text-[16px] font-bold text-primary mb-3">Product Video (optional)</h4>
      <?php if (!empty($product['video_path'])): ?>
        <video src="<?= BASE_URL ?>/uploads/videos/<?= htmlspecialchars($product['video_path']) ?>" controls class="w-full rounded-lg mb-2"></video>
        <label class="flex items-center gap-2 mb-3 text-sm">
          <input type="checkbox" name="remove_video" value="1"> Remove current video
        </label>
      <?php endif; ?>
      <label class="font-label-caps text-label-caps text-slate-gray block mb-1">Upload / Replace Video</label>
      <input type="file" name="video" accept="video/mp4,video/webm,video/quicktime" class="w-full text-body-sm">
      <p class="text-[11px] text-slate-gray mt-1">Shown alongside photos in the product gallery. MP4, WebM, or MOV.</p>
    </div>

    <button type="submit" class="w-full px-4 py-3 bg-primary text-on-primary rounded-lg font-label-caps text-label-caps hover:opacity-90">Save Product</button>
    <a href="<?= BASE_URL ?>/inventory.php" class="block text-center w-full px-4 py-3 border border-outline-variant rounded-lg font-label-caps text-label-caps text-slate-gray">Cancel</a>
  </div>
</form>

<script>
function addVariantRow() {
    const tbody = document.querySelector('#variantTable tbody');
    const row = document.createElement('tr');
    row.innerHTML = `
        <td class="pr-2 pb-2">
            <input type="hidden" name="variant_id[]" value="">
            <input name="variant_color[]" placeholder="Black" class="w-full px-3 py-1.5 border border-outline-variant rounded-lg">
        </td>
        <td class="pr-2 pb-2"><input name="variant_size[]" placeholder="32" class="w-full px-3 py-1.5 border border-outline-variant rounded-lg"></td>
        <td class="pr-2 pb-2"><input type="number" name="variant_stock[]" placeholder="10" class="w-full px-3 py-1.5 border border-outline-variant rounded-lg"></td>
        <td class="pb-2"><button type="button" onclick="this.closest('tr').remove()" class="text-danger-red material-symbols-outlined">close</button></td>
    `;
    tbody.appendChild(row);
}
</script>

<?php require __DIR__ . '/includes/layout-footer.php'; ?>
