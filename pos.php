<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_role(['owner', 'salesperson']);

$successOrder = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cart_json'])) {
    $cart = json_decode($_POST['cart_json'], true) ?: [];
    $customerName = trim($_POST['customer_name'] ?? 'Walk-in Customer') ?: 'Walk-in Customer';
    $customerPhone = trim($_POST['customer_phone'] ?? '');
    $discount = (float) ($_POST['discount'] ?? 0);
    $paymentStatus = ($_POST['payment_status'] ?? 'paid') === 'pending' ? 'pending' : 'paid';

    if (!empty($cart)) {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $subtotal = 0;
            foreach ($cart as $item) {
                $variant = get_variant((int) $item['variant_id']);
                if (!$variant || $variant['stock'] < (int) $item['qty']) {
                    throw new RuntimeException('Insufficient stock for ' . htmlspecialchars($variant['product_name'] ?? 'item'));
                }
                $subtotal += $variant['effective_price'] * (int) $item['qty'];
            }
            $total = max(0, $subtotal - $discount);
            $orderNumber = generate_order_number();

            $orderStmt = $pdo->prepare('INSERT INTO orders (order_number, customer_name, customer_phone, channel, subtotal, discount, total, status, processed_by) VALUES (?,?,?,"walk-in",?,?,?,?,?)');
            $orderStmt->execute([$orderNumber, $customerName, $customerPhone, $subtotal, $discount, $total, $paymentStatus, current_user()['id']]);
            $orderId = $pdo->lastInsertId();

            $itemStmt = $pdo->prepare('INSERT INTO order_items (order_id, product_id, variant_id, product_name, color, size, qty, unit_price) VALUES (?,?,?,?,?,?,?,?)');
            $stockStmt = $pdo->prepare('UPDATE product_variants SET stock = stock - ? WHERE id = ?');

            foreach ($cart as $item) {
                $variant = get_variant((int) $item['variant_id']);
                $itemStmt->execute([$orderId, $variant['product_id'] ?? null, $variant['id'], $variant['product_name'], $variant['color'], $variant['size'], (int) $item['qty'], $variant['effective_price']]);
                $stockStmt->execute([(int) $item['qty'], $variant['id']]);
            }

            $pdo->commit();
            $successOrder = $orderNumber;
        } catch (Throwable $e) {
            $pdo->rollBack();
            $posError = $e->getMessage();
        }
    }
}

$products = get_products_with_stock();
$catalog = [];
foreach ($products as $p) {
    foreach (get_variants_for_product($p['id']) as $v) {
        $catalog[] = [
            'variant_id' => $v['id'],
            'product_name' => $p['name'],
            'sku' => $p['sku'],
            'color' => $v['color'],
            'size' => $v['size'],
            'stock' => (int) $v['stock'],
            'price' => (float) $p['price'],
            'image' => $p['thumbnail'] ? BASE_URL . '/uploads/products/' . $p['thumbnail'] : null,
            'on_sale' => (bool) $p['on_sale'],
            'discount_percent' => $p['discount_percent'] !== null ? (float) $p['discount_percent'] : null,
        ];
    }
}

$myUserId = current_user()['id'];
$pdo = db();

$todayStatsStmt = $pdo->prepare("SELECT COUNT(*) AS transactions, COALESCE(SUM(total),0) AS total_sales, COUNT(DISTINCT customer_name) AS customers
    FROM orders WHERE processed_by = ? AND DATE(created_at) = CURDATE() AND status = 'paid'");
$todayStatsStmt->execute([$myUserId]);
$todayStats = $todayStatsStmt->fetch();
$avgSale = $todayStats['transactions'] > 0 ? $todayStats['total_sales'] / $todayStats['transactions'] : 0;

$itemsSoldStmt = $pdo->prepare("SELECT COALESCE(SUM(oi.qty),0) FROM order_items oi
    JOIN orders o ON o.id = oi.order_id
    WHERE o.processed_by = ? AND DATE(o.created_at) = CURDATE() AND o.status = 'paid'");
$itemsSoldStmt->execute([$myUserId]);
$itemsSoldToday = (int) $itemsSoldStmt->fetchColumn();

$recentSalesStmt = $pdo->prepare('SELECT * FROM orders WHERE processed_by = ? ORDER BY created_at DESC LIMIT 8');
$recentSalesStmt->execute([$myUserId]);
$recentSales = $recentSalesStmt->fetchAll();

$pageTitle = 'Sales & POS';
$activeNav = 'pos';
require __DIR__ . '/includes/layout-header.php';
?>

<?php if ($successOrder): ?>
<div class="bg-secondary-container/30 border border-secondary/30 rounded-xl p-4 flex items-center justify-between gap-3">
  <div class="flex items-center gap-3">
    <span class="material-symbols-outlined text-secondary">check_circle</span>
    <p class="font-body-md font-bold text-on-secondary-container">Sale completed — Order #<?= htmlspecialchars($successOrder) ?></p>
  </div>
  <a href="<?= BASE_URL ?>/receipt.php?order=<?= urlencode($successOrder) ?>" target="_blank" class="whitespace-nowrap px-3 py-1.5 bg-white/60 rounded-lg font-label-caps text-[11px] text-on-secondary-container flex items-center gap-1 hover:bg-white/90 transition-colors">
    <span class="material-symbols-outlined text-[16px]">print</span> Print Receipt
  </a>
</div>
<?php endif; ?>
<?php if (!empty($posError)): ?>
<div class="bg-error-container border border-error/20 rounded-xl p-4 flex items-center gap-3">
  <span class="material-symbols-outlined text-on-error-container">error</span>
  <p class="font-body-md font-bold text-on-error-container"><?= htmlspecialchars($posError) ?></p>
</div>
<?php endif; ?>

<div class="grid grid-cols-2 md:grid-cols-4 gap-gutter">
  <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-4">
    <p class="font-label-caps text-label-caps text-slate-gray mb-1">Today's Sales</p>
    <p class="font-headline-md text-[20px] font-bold text-primary"><?= money($todayStats['total_sales']) ?></p>
  </div>
  <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-4">
    <p class="font-label-caps text-label-caps text-slate-gray mb-1">Transactions</p>
    <p class="font-headline-md text-[20px] font-bold text-primary"><?= (int) $todayStats['transactions'] ?></p>
  </div>
  <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-4">
    <p class="font-label-caps text-label-caps text-slate-gray mb-1">Items Sold</p>
    <p class="font-headline-md text-[20px] font-bold text-primary"><?= $itemsSoldToday ?></p>
  </div>
  <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-4">
    <p class="font-label-caps text-label-caps text-slate-gray mb-1">Average Sale</p>
    <p class="font-headline-md text-[20px] font-bold text-primary"><?= money($avgSale) ?></p>
  </div>
</div>

<div class="grid grid-cols-1 xl:grid-cols-3 gap-gutter items-start">
  <div class="xl:col-span-2 space-y-stack-md">
    <div class="relative">
      <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-gray">search</span>
      <input id="catalogSearch" class="w-full pl-10 pr-4 py-2 bg-surface-container-lowest border border-outline-variant rounded-lg focus:ring-2 focus:ring-primary text-sm" placeholder="Search products, SKU or scan barcode..." type="text" oninput="renderCatalog()">
    </div>
    <div id="catalogGrid" class="grid grid-cols-2 md:grid-cols-3 gap-4"></div>

    <div class="bg-surface-container-lowest border border-outline-variant rounded-xl overflow-hidden">
      <div class="p-4 border-b border-outline-variant">
        <h4 class="font-headline-md text-[16px] font-bold text-primary">Recent Sales</h4>
      </div>
      <table class="w-full">
        <thead>
          <tr class="text-left border-b border-outline-variant">
            <th class="p-3 font-label-caps text-label-caps text-slate-gray">Order</th>
            <th class="p-3 font-label-caps text-label-caps text-slate-gray">Customer</th>
            <th class="p-3 font-label-caps text-label-caps text-slate-gray text-right">Total</th>
            <th class="p-3 font-label-caps text-label-caps text-slate-gray text-right">Payment</th>
            <th class="p-3 font-label-caps text-label-caps text-slate-gray text-right">Time</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-outline-variant">
          <?php foreach ($recentSales as $sale): ?>
            <tr>
              <td class="p-3 font-label-numeric text-label-numeric"><?= htmlspecialchars($sale['order_number']) ?></td>
              <td class="p-3 font-body-sm"><?= htmlspecialchars($sale['customer_name']) ?></td>
              <td class="p-3 font-label-numeric text-label-numeric text-right"><?= money($sale['total']) ?></td>
              <td class="p-3 text-right">
                <?php if ($sale['status'] === 'pending'): ?>
                  <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-bold bg-error-container text-on-error-container">Unpaid</span>
                <?php elseif ($sale['status'] === 'paid'): ?>
                  <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-bold bg-secondary-container/30 text-on-secondary-container">Paid</span>
                <?php else: ?>
                  <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-bold bg-surface-container-high text-slate-gray capitalize"><?= htmlspecialchars($sale['status']) ?></span>
                <?php endif; ?>
              </td>
              <td class="p-3 text-right font-body-sm text-slate-gray"><?= date('H:i', strtotime($sale['created_at'])) ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (empty($recentSales)): ?>
            <tr><td colspan="5" class="p-6 text-center text-slate-gray font-body-sm">No sales recorded yet today.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-card-padding sticky top-20">
    <h2 class="font-headline-md text-lg font-bold text-primary mb-3">Current Sale <span id="cartCountLabel" class="text-slate-gray font-normal text-sm">(0 Items)</span></h2>
    <div id="cartList" class="space-y-3 max-h-[320px] overflow-y-auto no-scrollbar"></div>
    <div class="border-t border-outline-variant mt-4 pt-4 space-y-2">
      <input id="customerName" placeholder="Customer name (optional)" class="w-full px-3 py-2 border border-outline-variant rounded-lg text-sm">
      <input id="customerPhone" placeholder="Phone (optional)" class="w-full px-3 py-2 border border-outline-variant rounded-lg text-sm">
      <div class="flex justify-between items-center text-sm">
        <label>Discount (KES)</label>
        <input id="discountInput" type="number" value="0" min="0" class="w-24 px-2 py-1 border border-outline-variant rounded-lg text-right" oninput="renderCart()">
      </div>
      <div class="flex justify-between text-sm"><span>Subtotal</span><span id="subtotalLabel" class="font-label-numeric">KES 0</span></div>
      <div class="flex justify-between font-bold text-lg"><span>Total</span><span id="totalLabel" class="font-label-numeric text-primary">KES 0</span></div>
      <div>
        <label class="text-xs font-bold text-slate-gray block mb-1">Payment Status</label>
        <div class="grid grid-cols-2 gap-2">
          <button type="button" id="paidToggleBtn" onclick="setPaymentStatus('paid')" class="py-2 rounded-lg font-bold text-sm border-2 border-secondary bg-secondary text-on-secondary">Paid</button>
          <button type="button" id="pendingToggleBtn" onclick="setPaymentStatus('pending')" class="py-2 rounded-lg font-bold text-sm border-2 border-outline-variant text-slate-gray">Unpaid</button>
        </div>
      </div>
      <form method="post" id="checkoutForm">
        <input type="hidden" name="cart_json" id="cartJsonInput">
        <input type="hidden" name="customer_name" id="customerNameInput">
        <input type="hidden" name="customer_phone" id="customerPhoneInput">
        <input type="hidden" name="discount" id="discountHiddenInput">
        <input type="hidden" name="payment_status" id="paymentStatusInput" value="paid">
        <button type="submit" class="w-full mt-2 px-4 py-3 bg-secondary text-on-secondary rounded-lg font-bold hover:opacity-90">Complete Sale</button>
      </form>
    </div>
  </div>
</div>

<script>
const catalog = <?= json_encode($catalog) ?>;
const currency = <?= json_encode(CURRENCY) ?>;
let cart = {}; // variant_id -> qty

function money(n) {
    return currency + ' ' + Math.round(n).toLocaleString();
}

function renderCatalog() {
    const q = document.getElementById('catalogSearch').value.toLowerCase();
    const grid = document.getElementById('catalogGrid');
    grid.innerHTML = '';
    catalog.filter(c => !q || c.product_name.toLowerCase().includes(q) || c.sku.toLowerCase().includes(q))
        .forEach(c => {
            const card = document.createElement('div');
            card.className = 'bg-surface-container-lowest border border-outline-variant rounded-lg p-3 cursor-pointer hover:shadow-md transition-all relative ' + (c.stock <= 0 ? 'opacity-40 pointer-events-none' : '');
            const finalPrice = c.on_sale && c.discount_percent ? c.price * (1 - c.discount_percent / 100) : c.price;
            const priceHtml = c.on_sale && c.discount_percent
                ? `<p class="text-sm font-label-numeric mt-1"><span class="line-through text-slate-gray text-xs mr-1">${money(c.price)}</span><span class="text-danger-red font-bold">${money(finalPrice)}</span></p>`
                : `<p class="text-sm font-label-numeric mt-1">${money(c.price)}</p>`;
            const imageHtml = c.image
                ? `<img src="${c.image}" class="w-full h-36 object-cover rounded-lg mb-2 border border-outline-variant">`
                : `<div class="w-full h-36 bg-surface-container-high rounded-lg mb-2 flex items-center justify-center text-slate-gray"><span class="material-symbols-outlined text-[32px]">inventory_2</span></div>`;
            card.innerHTML = `
                ${c.on_sale ? `<span class="absolute top-1 left-1 bg-danger-red text-white text-[10px] font-bold px-2 py-0.5 rounded-full z-10">-${c.discount_percent}% </span>` : ''}
                ${imageHtml}
                <h3 class="text-sm font-bold text-primary truncate">${c.product_name}</h3>
                <p class="text-xs text-slate-gray">${c.color} &middot; ${c.size}</p>
                ${priceHtml}
                <p class="text-[11px] text-slate-gray">${c.stock} in stock</p>
            `;
            card.onclick = () => addToCart(c.variant_id);
            grid.appendChild(card);
        });
}

function addToCart(variantId) {
    const item = catalog.find(c => c.variant_id === variantId);
    if (!item || item.stock <= (cart[variantId] || 0)) return;
    cart[variantId] = (cart[variantId] || 0) + 1;
    renderCart();
}

function removeFromCart(variantId) {
    delete cart[variantId];
    renderCart();
}

function updateQty(variantId, qty) {
    qty = parseInt(qty, 10);
    if (qty <= 0) { removeFromCart(variantId); return; }
    cart[variantId] = qty;
    renderCart();
}

function renderCart() {
    const list = document.getElementById('cartList');
    list.innerHTML = '';
    let subtotal = 0;
    let count = 0;
    Object.entries(cart).forEach(([variantId, qty]) => {
        const item = catalog.find(c => c.variant_id == variantId);
        if (!item) return;
        const unitPrice = item.on_sale && item.discount_percent ? item.price * (1 - item.discount_percent / 100) : item.price;
        const lineTotal = unitPrice * qty;
        subtotal += lineTotal;
        count += qty;
        const row = document.createElement('div');
        row.className = 'flex items-center gap-2';
        row.innerHTML = `
            <div class="flex-1">
                <h4 class="text-sm font-bold text-primary truncate">${item.product_name}</h4>
                <p class="text-xs text-slate-gray">${item.color} &middot; ${item.size} &middot; ${money(unitPrice)}</p>
            </div>
            <input type="number" value="${qty}" min="1" max="${item.stock}" class="w-14 px-2 py-1 border border-outline-variant rounded-lg text-sm text-center" onchange="updateQty(${variantId}, this.value)">
            <button onclick="removeFromCart(${variantId})" class="material-symbols-outlined text-danger-red text-[18px]">close</button>
        `;
        list.appendChild(row);
    });

    const discount = parseFloat(document.getElementById('discountInput').value) || 0;
    const total = Math.max(0, subtotal - discount);

    document.getElementById('cartCountLabel').textContent = `(${count} Item${count === 1 ? '' : 's'})`;
    document.getElementById('subtotalLabel').textContent = money(subtotal);
    document.getElementById('totalLabel').textContent = money(total);
}

function setPaymentStatus(status) {
    document.getElementById('paymentStatusInput').value = status;
    const paidBtn = document.getElementById('paidToggleBtn');
    const pendingBtn = document.getElementById('pendingToggleBtn');
    if (status === 'paid') {
        paidBtn.className = 'py-2 rounded-lg font-bold text-sm border-2 border-secondary bg-secondary text-on-secondary';
        pendingBtn.className = 'py-2 rounded-lg font-bold text-sm border-2 border-outline-variant text-slate-gray';
    } else {
        pendingBtn.className = 'py-2 rounded-lg font-bold text-sm border-2 border-danger-red bg-danger-red text-white';
        paidBtn.className = 'py-2 rounded-lg font-bold text-sm border-2 border-outline-variant text-slate-gray';
    }
}

document.getElementById('checkoutForm').addEventListener('submit', (e) => {
    if (Object.keys(cart).length === 0) {
        e.preventDefault();
        alert('Cart is empty.');
        return;
    }
    const cartArray = Object.entries(cart).map(([variant_id, qty]) => ({ variant_id: parseInt(variant_id, 10), qty }));
    document.getElementById('cartJsonInput').value = JSON.stringify(cartArray);
    document.getElementById('customerNameInput').value = document.getElementById('customerName').value;
    document.getElementById('customerPhoneInput').value = document.getElementById('customerPhone').value;
    document.getElementById('discountHiddenInput').value = document.getElementById('discountInput').value || 0;
});

document.getElementById('catalogSearch').addEventListener('keydown', (e) => {
    if (e.key !== 'Enter') return;
    e.preventDefault();
    const q = e.target.value.trim().toLowerCase();
    if (!q) return;
    const exact = catalog.find(c => c.sku.toLowerCase() === q);
    if (exact) {
        addToCart(exact.variant_id);
        e.target.value = '';
        renderCatalog();
    }
});

renderCatalog();
renderCart();
</script>

<?php require __DIR__ . '/includes/layout-footer.php'; ?>
