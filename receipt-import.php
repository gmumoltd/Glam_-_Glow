<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_role(['owner']);

$categories = get_categories();
$errors = [];
$parsedItems = null;
$sourceLabel = '';
$created = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['parse_receipt'])) {
    if (empty($_FILES['receipt']['name']) || $_FILES['receipt']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Please choose a file to upload.';
    } else {
        $tmpPath = $_FILES['receipt']['tmp_name'];
        $ext = strtolower(pathinfo($_FILES['receipt']['name'], PATHINFO_EXTENSION));

        try {
            if ($ext === 'csv') {
                $parsedItems = parse_receipt_csv($tmpPath);
                $sourceLabel = 'CSV: ' . $_FILES['receipt']['name'];
            } elseif (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'pdf'], true)) {
                $mime = $_FILES['receipt']['type'] ?: 'image/jpeg';
                $text = ocr_extract_text($tmpPath, $mime, $ext);
                $parsedItems = parse_receipt_lines($text);
                $sourceLabel = 'Photo: ' . $_FILES['receipt']['name'];
            } else {
                $errors[] = 'Unsupported file type. Upload a JPG/PNG photo, a PDF, or a CSV.';
            }

            if ($parsedItems !== null && empty($parsedItems)) {
                $errors[] = "Couldn't find any line items in that file. You can still add rows manually below.";
                $parsedItems = [];
            }
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_import'])) {
    $names = $_POST['item_name'] ?? [];
    $qtys = $_POST['item_qty'] ?? [];
    $costs = $_POST['item_cost'] ?? [];
    $prices = $_POST['item_price'] ?? [];
    $categoryIds = $_POST['item_category'] ?? [];
    $included = $_POST['item_include'] ?? [];

    $pdo = db();
    $pdo->beginTransaction();
    $createdCount = 0;
    try {
        foreach ($names as $i => $name) {
            if (!isset($included[$i])) continue;
            $name = trim($name);
            $qty = max(0, (int) ($qtys[$i] ?? 0));
            $cost = max(0, (float) ($costs[$i] ?? 0));
            $price = (float) ($prices[$i] ?? 0);
            $categoryId = (int) ($categoryIds[$i] ?? 0);

            if ($name === '' || $price <= 0 || !$categoryId) {
                continue;
            }

            $slug = unique_product_slug($name);
            $sku = unique_product_sku($name);

            $stmt = $pdo->prepare('INSERT INTO products (category_id, name, slug, sku, price, cost_price, status) VALUES (?,?,?,?,?,?,"active")');
            $stmt->execute([$categoryId, $name, $slug, $sku, $price, $cost]);
            $productId = $pdo->lastInsertId();

            $pdo->prepare('INSERT INTO product_variants (product_id, color, size, stock) VALUES (?,?,?,?)')
                ->execute([$productId, 'Standard', 'One Size', $qty]);

            $createdCount++;
        }
        $pdo->commit();
        $created = $createdCount;
    } catch (Throwable $e) {
        $pdo->rollBack();
        $errors[] = 'Import failed: ' . $e->getMessage();
    }
}

$pageTitle = 'Import Receipt';
$activeNav = 'receipt-import';
require __DIR__ . '/includes/layout-header.php';
?>

<h2 class="font-headline-lg text-headline-lg text-primary">Import Receipt</h2>
<p class="text-body-sm text-slate-gray -mt-2">Upload a supplier receipt (photo or CSV) — stock lines get pulled out automatically. Review everything and set the selling price before it goes live on the shop.</p>

<?php foreach ($errors as $err): ?>
  <div class="bg-error-container text-on-error-container p-3 rounded-lg font-body-sm"><?= htmlspecialchars($err) ?></div>
<?php endforeach; ?>

<?php if ($created !== null): ?>
  <div class="bg-secondary-container/30 border border-secondary/30 rounded-xl p-4 flex items-center gap-3">
    <span class="material-symbols-outlined text-secondary">check_circle</span>
    <p class="font-body-md font-bold text-on-secondary-container">
      <?= $created ?> product<?= $created === 1 ? '' : 's' ?> published to the shop.
      <a href="<?= BASE_URL ?>/inventory.php" class="underline">View Inventory</a>
    </p>
  </div>
<?php endif; ?>

<?php if ($parsedItems === null): ?>

<form method="post" enctype="multipart/form-data" class="max-w-xl bg-surface-container-lowest border border-outline-variant rounded-xl p-card-padding space-y-4">
  <input type="hidden" name="parse_receipt" value="1">
  <div>
    <label class="font-label-caps text-label-caps text-slate-gray block mb-1">Receipt File</label>
    <input type="file" name="receipt" accept=".jpg,.jpeg,.png,.webp,.pdf,.csv" required class="w-full text-body-sm">
    <p class="text-[11px] text-slate-gray mt-1">Photo/scan (JPG, PNG, PDF) — text is read automatically. Or a CSV with <code>name</code>, <code>qty</code>, <code>cost</code> columns.</p>
  </div>
  <button type="submit" class="px-6 py-3 bg-primary text-on-primary rounded-lg font-label-caps text-label-caps hover:opacity-90">Read Receipt</button>
</form>

<?php else: ?>

<form method="post" class="space-y-4">
  <input type="hidden" name="confirm_import" value="1">
  <div class="bg-surface-container-lowest border border-outline-variant rounded-xl overflow-hidden">
    <div class="p-4 border-b border-outline-variant flex items-center justify-between">
      <p class="font-body-sm text-slate-gray">Source: <?= htmlspecialchars($sourceLabel) ?> — check every row, OCR can misread text.</p>
      <button type="button" onclick="addImportRow()" class="font-label-caps text-label-caps text-primary flex items-center gap-1">
        <span class="material-symbols-outlined text-[18px]">add</span> Add Row
      </button>
    </div>
    <div class="overflow-x-auto">
    <table class="w-full" id="importTable">
      <thead>
        <tr class="bg-slate-gray/5 text-left border-b border-outline-variant">
          <th class="p-3"></th>
          <th class="p-3 font-label-caps text-label-caps text-slate-gray">Product Name</th>
          <th class="p-3 font-label-caps text-label-caps text-slate-gray">Category</th>
          <th class="p-3 font-label-caps text-label-caps text-slate-gray">Qty</th>
          <th class="p-3 font-label-caps text-label-caps text-slate-gray">Cost Price</th>
          <th class="p-3 font-label-caps text-label-caps text-slate-gray">Selling Price</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-outline-variant">
        <?php foreach ($parsedItems as $item): ?>
          <tr>
            <td class="p-3"><input type="checkbox" name="item_include[]" value="1" checked class="w-4 h-4"></td>
            <td class="p-3"><input name="item_name[]" value="<?= htmlspecialchars($item['name']) ?>" class="w-full px-3 py-1.5 border border-outline-variant rounded-lg" required></td>
            <td class="p-3">
              <select name="item_category[]" class="w-full px-3 py-1.5 border border-outline-variant rounded-lg" required>
                <option value="">-- Select --</option>
                <?php foreach ($categories as $cat): ?>
                  <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </td>
            <td class="p-3"><input type="number" name="item_qty[]" value="<?= (int) $item['qty'] ?>" min="0" class="w-20 px-3 py-1.5 border border-outline-variant rounded-lg"></td>
            <td class="p-3"><input type="number" step="0.01" name="item_cost[]" value="<?= htmlspecialchars($item['cost_price']) ?>" class="w-28 px-3 py-1.5 border border-outline-variant rounded-lg"></td>
            <td class="p-3"><input type="number" step="0.01" name="item_price[]" placeholder="Set price" class="w-28 px-3 py-1.5 border border-outline-variant rounded-lg font-bold" required></td>
          </tr>
        <?php endforeach; ?>
        <?php if (empty($parsedItems)): ?>
          <tr id="importEmptyRow"><td colspan="6" class="p-4 text-center text-slate-gray font-body-sm">No rows yet — click "Add Row" to enter items by hand.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
    </div>
  </div>

  <div class="flex gap-3">
    <button type="submit" class="px-6 py-3 bg-primary text-on-primary rounded-lg font-label-caps text-label-caps hover:opacity-90">Confirm &amp; Publish to Shop</button>
    <a href="<?= BASE_URL ?>/receipt-import.php" class="px-6 py-3 border border-outline-variant rounded-lg font-label-caps text-label-caps text-slate-gray">Start Over</a>
  </div>
  <p class="text-[11px] text-slate-gray">Unchecked rows are skipped. Each item is created with a single "Standard / One Size" variant — open it from Inventory afterwards if you need real color/size variants.</p>
</form>

<script>
function addImportRow() {
    const emptyRow = document.getElementById('importEmptyRow');
    if (emptyRow) emptyRow.remove();
    const tbody = document.querySelector('#importTable tbody');
    const categoryOptions = <?= json_encode('<option value="">-- Select --</option>' . implode('', array_map(fn ($c) => '<option value="' . $c['id'] . '">' . htmlspecialchars($c['name']) . '</option>', $categories))) ?>;
    const row = document.createElement('tr');
    row.innerHTML = `
        <td class="p-3"><input type="checkbox" name="item_include[]" value="1" checked class="w-4 h-4"></td>
        <td class="p-3"><input name="item_name[]" class="w-full px-3 py-1.5 border border-outline-variant rounded-lg" required></td>
        <td class="p-3"><select name="item_category[]" class="w-full px-3 py-1.5 border border-outline-variant rounded-lg" required>${categoryOptions}</select></td>
        <td class="p-3"><input type="number" name="item_qty[]" value="1" min="0" class="w-20 px-3 py-1.5 border border-outline-variant rounded-lg"></td>
        <td class="p-3"><input type="number" step="0.01" name="item_cost[]" class="w-28 px-3 py-1.5 border border-outline-variant rounded-lg"></td>
        <td class="p-3"><input type="number" step="0.01" name="item_price[]" placeholder="Set price" class="w-28 px-3 py-1.5 border border-outline-variant rounded-lg font-bold" required></td>
    `;
    tbody.appendChild(row);
}
</script>

<?php endif; ?>

<?php require __DIR__ . '/includes/layout-footer.php'; ?>
