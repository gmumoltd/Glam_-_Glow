<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_role(['owner']);

$products = db()->query('SELECT id, name, sku, price FROM products WHERE status = "active" ORDER BY name')->fetchAll();

$pageTitle = 'Barcode Labels';
$activeNav = 'inventory';
require __DIR__ . '/includes/layout-header.php';
?>

<h2 class="font-headline-lg text-headline-lg text-primary">Barcode Labels</h2>
<p class="text-body-sm text-slate-gray -mt-2">Pick products, generate labels, then print. Each label's barcode encodes the product SKU — scan it at the POS "Scan or type SKU" box to add it to a sale instantly.</p>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-gutter">
  <div class="lg:col-span-1 bg-surface-container-lowest border border-outline-variant rounded-xl p-card-padding no-print">
    <div class="flex justify-between items-center mb-3">
      <h4 class="font-headline-md text-[16px] font-bold text-primary">Select Products</h4>
      <button type="button" onclick="toggleAll(true)" class="text-[11px] font-label-caps text-primary">All</button>
    </div>
    <div id="productChecklist" class="space-y-2 max-h-[500px] overflow-y-auto no-scrollbar">
      <?php foreach ($products as $p): ?>
        <label class="flex items-center gap-2 text-sm">
          <input type="checkbox" class="product-check w-4 h-4" value="<?= $p['id'] ?>" checked>
          <span class="flex-1"><?= htmlspecialchars($p['name']) ?></span>
          <span class="font-label-numeric text-[11px] text-slate-gray"><?= htmlspecialchars($p['sku']) ?></span>
        </label>
      <?php endforeach; ?>
      <?php if (empty($products)): ?>
        <p class="text-slate-gray font-body-sm">No products yet.</p>
      <?php endif; ?>
    </div>
    <div class="flex items-center gap-2 mt-4">
      <label class="text-[11px] text-slate-gray">Copies per label</label>
      <input type="number" id="copiesInput" value="1" min="1" max="20" class="w-16 px-2 py-1 border border-outline-variant rounded-lg text-sm">
    </div>
    <button type="button" onclick="generateLabels()" class="w-full mt-4 px-4 py-3 bg-primary text-on-primary rounded-lg font-label-caps text-label-caps hover:opacity-90">Generate Labels</button>
    <button type="button" onclick="window.print()" class="w-full mt-2 px-4 py-3 border border-outline-variant rounded-lg font-label-caps text-label-caps text-slate-gray">Print</button>
  </div>

  <div class="lg:col-span-2 bg-surface-container-lowest border border-outline-variant rounded-xl p-card-padding">
    <div id="labelSheet" class="grid grid-cols-2 sm:grid-cols-3 gap-3"></div>
    <p id="labelEmptyState" class="text-slate-gray font-body-sm">Select products on the left and click "Generate Labels".</p>
  </div>
</div>

<style>
@media print {
  .no-print, aside, main > header { display: none !important; }
  main { margin-left: 0 !important; }
  #labelSheet { grid-template-columns: repeat(3, 1fr) !important; }
}
.barcode-label { border: 1px dashed #94A3B8; border-radius: 6px; padding: 8px; text-align: center; break-inside: avoid; }
</style>

<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
<script>
const products = <?= json_encode($products) ?>;
const currency = <?= json_encode(CURRENCY) ?>;

function toggleAll(checked) {
    document.querySelectorAll('.product-check').forEach(cb => cb.checked = checked);
}

function generateLabels() {
    const selectedIds = Array.from(document.querySelectorAll('.product-check:checked')).map(cb => parseInt(cb.value, 10));
    const copies = Math.max(1, parseInt(document.getElementById('copiesInput').value, 10) || 1);
    const sheet = document.getElementById('labelSheet');
    const emptyState = document.getElementById('labelEmptyState');
    sheet.innerHTML = '';

    const selected = products.filter(p => selectedIds.includes(p.id));
    if (selected.length === 0) {
        emptyState.style.display = 'block';
        return;
    }
    emptyState.style.display = 'none';

    selected.forEach(p => {
        for (let i = 0; i < copies; i++) {
            const label = document.createElement('div');
            label.className = 'barcode-label';
            const svgId = 'bc-' + p.id + '-' + i;
            label.innerHTML = `
                <p style="font-size:11px;font-weight:bold;margin-bottom:2px;">${p.name}</p>
                <svg id="${svgId}"></svg>
                <p style="font-size:11px;font-weight:bold;margin-top:2px;">${currency} ${Math.round(p.price).toLocaleString()}</p>
            `;
            sheet.appendChild(label);
            JsBarcode('#' + svgId, p.sku, { format: 'CODE128', height: 40, width: 1.5, fontSize: 11, margin: 4 });
        }
    });
}

generateLabels();
</script>

<?php require __DIR__ . '/includes/layout-footer.php'; ?>
