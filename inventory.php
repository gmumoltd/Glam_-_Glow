<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_role(['owner']);

if (isset($_GET['delete'])) {
    db()->prepare('DELETE FROM products WHERE id = ?')->execute([(int) $_GET['delete']]);
    header('Location: ' . BASE_URL . '/inventory.php');
    exit;
}

$search = trim($_GET['q'] ?? '');
$products = get_products_with_stock(['search' => $search ?: null]);

$totalInventoryValue = 0;
foreach ($products as $p) {
    $totalInventoryValue += $p['cost_price'] * $p['total_stock'];
}

$criticalCount = 0;
foreach ($products as $p) {
    if ($p['total_stock'] <= $p['reorder_level']) $criticalCount++;
}

$lowStockDigestLink = low_stock_whatsapp_link(get_low_stock_items());

$pageTitle = 'Inventory';
$activeNav = 'inventory';
require __DIR__ . '/includes/layout-header.php';
?>

<?php if ($criticalCount > 0): ?>
<div class="bg-error-container border border-error/20 rounded-xl p-4 flex items-center justify-between gap-3">
  <div class="flex items-center gap-3">
    <span class="material-symbols-outlined text-on-error-container">warning</span>
    <p class="font-body-md font-bold text-on-error-container">Low Stock Alert: <?= $criticalCount ?> item<?= $criticalCount === 1 ? '' : 's' ?> require immediate replenishment.</p>
  </div>
  <?php if ($lowStockDigestLink): ?>
    <a href="<?= htmlspecialchars($lowStockDigestLink) ?>" target="_blank" class="whitespace-nowrap px-3 py-1.5 bg-white/60 rounded-lg font-label-caps text-[11px] text-on-error-container flex items-center gap-1 hover:bg-white/90 transition-colors">
      <span class="material-symbols-outlined text-[16px]">chat</span> Send via WhatsApp
    </a>
  <?php endif; ?>
</div>
<?php endif; ?>

<div class="glam-inventory-heading flex flex-wrap justify-between items-center gap-4">
  <h2 class="font-headline-lg text-headline-lg text-primary">Inventory Management</h2>
  <div class="glam-inventory-actions flex flex-wrap gap-2">
    <form method="get" class="hidden sm:block">
      <input name="q" value="<?= htmlspecialchars($search) ?>" class="pl-4 pr-4 py-2 bg-surface-container-low border-none rounded-lg text-body-sm w-64 focus:ring-2 focus:ring-primary" placeholder="Search inventory, SKUs...">
    </form>
    <a href="<?= BASE_URL ?>/receipt-import.php" class="px-4 py-2 border border-outline-variant text-primary rounded-lg font-label-caps text-label-caps flex items-center gap-2 hover:bg-surface-container-high transition-colors">
      <span class="material-symbols-outlined text-[18px]">receipt_long</span> Import Receipt
    </a>
    <a href="<?= BASE_URL ?>/barcode-labels.php" class="px-4 py-2 border border-outline-variant text-primary rounded-lg font-label-caps text-label-caps flex items-center gap-2 hover:bg-surface-container-high transition-colors">
      <span class="material-symbols-outlined text-[18px]">barcode</span> Barcode Labels
    </a>
    <a href="<?= BASE_URL ?>/product-form.php" class="px-4 py-2 bg-primary text-on-primary rounded-lg font-label-caps text-label-caps flex items-center gap-2 hover:opacity-90 transition-opacity">
      <span class="material-symbols-outlined text-[18px]">add</span> Add Product
    </a>
  </div>
</div>

<div class="bg-surface-container-lowest border border-outline-variant rounded-xl overflow-hidden">
  <div class="flex flex-wrap items-center gap-2 p-4 border-b border-outline-variant">
    <a href="<?= BASE_URL ?>/inventory.php" class="px-4 py-1.5 <?= empty($_GET['filter']) ? 'bg-primary text-on-primary' : 'text-slate-gray hover:bg-surface-container' ?> rounded-full font-label-caps text-[11px]">All Items</a>
    <span class="px-4 py-1.5 text-slate-gray font-label-caps text-[11px]">Total Inventory Value: <span class="font-bold text-primary"><?= money($totalInventoryValue) ?></span></span>
  </div>
  <div class="overflow-x-auto">
  <table class="glam-inventory-table w-full border-collapse" aria-label="Inventory products">
    <thead>
      <tr class="bg-slate-gray/5 text-left border-b border-outline-variant">
        <th class="p-4 font-label-caps text-label-caps text-slate-gray whitespace-nowrap">Product Details</th>
        <th class="p-4 font-label-caps text-label-caps text-slate-gray whitespace-nowrap">SKU</th>
        <th class="p-4 font-label-caps text-label-caps text-slate-gray whitespace-nowrap">Category</th>
        <th class="p-4 font-label-caps text-label-caps text-slate-gray whitespace-nowrap">Variants</th>
        <th class="p-4 font-label-caps text-label-caps text-slate-gray whitespace-nowrap">Stock Level</th>
        <th class="p-4 font-label-caps text-label-caps text-slate-gray whitespace-nowrap text-right">Actions</th>
      </tr>
    </thead>
    <tbody class="divide-y divide-outline-variant">
      <?php foreach ($products as $p):
        $variants = get_variants_for_product($p['id']);
        $status = stock_status((int) $p['total_stock'], (int) $p['reorder_level']);
        $imgSrc = $p['thumbnail'] ? BASE_URL . '/uploads/products/' . htmlspecialchars($p['thumbnail']) : null;
      ?>
        <tr class="hover:bg-surface-container-low transition-colors group">
          <td class="p-4">
            <div class="flex items-center gap-3">
              <div class="relative">
                <?php if ($p['on_sale']): ?>
                  <span class="absolute -top-1 -left-1 bg-danger-red text-white text-[9px] font-bold px-1.5 py-0.5 rounded-full z-10">-<?= htmlspecialchars($p['discount_percent']) ?>%</span>
                <?php endif; ?>
                <?php if ($imgSrc): ?>
                  <img class="w-12 h-12 rounded-lg object-cover border border-outline-variant" src="<?= $imgSrc ?>">
                <?php else: ?>
                  <div class="w-12 h-12 rounded-lg bg-surface-container-high border border-outline-variant flex items-center justify-center text-slate-gray">
                    <span class="material-symbols-outlined text-[18px]">inventory_2</span>
                  </div>
                <?php endif; ?>
              </div>
              <div>
                <p class="font-body-md font-bold text-primary"><?= htmlspecialchars($p['name']) ?></p>
                <p class="font-body-sm text-slate-gray"><?= htmlspecialchars($p['collection'] ?: $p['category_name']) ?></p>
              </div>
            </div>
          </td>
          <td class="p-4 font-label-numeric text-label-numeric"><?= htmlspecialchars($p['sku']) ?></td>
          <td class="p-4 text-body-sm"><?= htmlspecialchars($p['category_name']) ?></td>
          <td class="p-4">
            <div class="flex -space-x-2">
              <?php $shown = 0; foreach ($variants as $v): if ($shown >= 3) break; $shown++; ?>
                <div class="w-7 h-7 rounded-full bg-surface-container-high border-2 border-white flex items-center justify-center text-[10px] font-bold" title="<?= htmlspecialchars($v['color']) ?>"><?= htmlspecialchars($v['size']) ?></div>
              <?php endforeach; ?>
              <?php if (count($variants) > 3): ?>
                <div class="w-7 h-7 rounded-full bg-primary text-white border-2 border-white flex items-center justify-center text-[10px] font-bold">+<?= count($variants) - 3 ?></div>
              <?php endif; ?>
            </div>
          </td>
          <td class="p-4">
            <div class="w-full bg-surface-container-high h-1.5 rounded-full mb-1">
              <div class="bg-<?= $status['color'] ?> h-1.5 rounded-full" style="width: <?= $status['pct'] ?>%"></div>
            </div>
            <span class="font-label-numeric text-[12px] text-<?= $status['color'] ?> font-bold"><?= $status['label'] ?></span>
          </td>
          <td class="p-4 text-right">
            <a href="<?= BASE_URL ?>/product-view.php?id=<?= $p['id'] ?>" class="p-2 text-slate-gray hover:text-primary transition-colors inline-block"><span class="material-symbols-outlined">visibility</span></a>
            <a href="<?= BASE_URL ?>/product-form.php?id=<?= $p['id'] ?>" class="p-2 text-slate-gray hover:text-primary transition-colors inline-block"><span class="material-symbols-outlined">edit</span></a>
            <a href="<?= BASE_URL ?>/inventory.php?delete=<?= $p['id'] ?>" onclick="return confirm('Delete this product?')" class="p-2 text-slate-gray hover:text-danger-red transition-colors inline-block"><span class="material-symbols-outlined">delete</span></a>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($products)): ?>
        <tr><td colspan="6" class="p-8 text-center text-slate-gray font-body-sm">No products yet. <a href="<?= BASE_URL ?>/product-form.php" class="text-primary font-bold">Add your first product</a>.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
  </div>
</div>

<?php require __DIR__ . '/includes/layout-footer.php'; ?>
