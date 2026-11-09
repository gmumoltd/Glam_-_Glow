<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_role(['owner']);

$pageTitle = 'Business Calculators';
$activeNav = 'calculators';
require __DIR__ . '/includes/layout-header.php';
?>

<h2 class="font-headline-lg text-headline-lg text-primary">AI Business Calculators</h2>
<p class="font-body-sm text-slate-gray -mt-4">Quick tools to help you make smarter pricing decisions.</p>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-gutter">

  <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-card-padding">
    <h4 class="font-headline-md text-[16px] font-bold text-primary mb-3 flex items-center gap-2"><span class="material-symbols-outlined text-secondary">payments</span>Profit Calculator</h4>
    <label class="font-label-caps text-label-caps text-slate-gray">Cost Price (KES)</label>
    <input id="p_cost" type="number" value="1500" class="w-full mb-2 px-3 py-2 border border-outline-variant rounded-lg" oninput="calcProfit()">
    <label class="font-label-caps text-label-caps text-slate-gray">Selling Price (KES)</label>
    <input id="p_sell" type="number" value="2500" class="w-full mb-2 px-3 py-2 border border-outline-variant rounded-lg" oninput="calcProfit()">
    <label class="font-label-caps text-label-caps text-slate-gray">Quantity</label>
    <input id="p_qty" type="number" value="1" class="w-full mb-3 px-3 py-2 border border-outline-variant rounded-lg" oninput="calcProfit()">
    <div class="bg-secondary-container/20 rounded-lg p-3 flex justify-between">
      <span class="font-bold">Profit</span>
      <span id="p_result" class="font-label-numeric font-bold text-secondary">KES 1,000</span>
    </div>
  </div>

  <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-card-padding">
    <h4 class="font-headline-md text-[16px] font-bold text-primary mb-3 flex items-center gap-2"><span class="material-symbols-outlined text-ai-intelligence">percent</span>Margin Calculator</h4>
    <label class="font-label-caps text-label-caps text-slate-gray">Cost Price (KES)</label>
    <input id="m_cost" type="number" value="1500" class="w-full mb-2 px-3 py-2 border border-outline-variant rounded-lg" oninput="calcMargin()">
    <label class="font-label-caps text-label-caps text-slate-gray">Selling Price (KES)</label>
    <input id="m_sell" type="number" value="2500" class="w-full mb-3 px-3 py-2 border border-outline-variant rounded-lg" oninput="calcMargin()">
    <div class="bg-ai-intelligence/10 rounded-lg p-3 flex justify-between">
      <span class="font-bold">Margin</span>
      <span id="m_result" class="font-label-numeric font-bold text-ai-intelligence">40%</span>
    </div>
  </div>

  <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-card-padding">
    <h4 class="font-headline-md text-[16px] font-bold text-primary mb-3 flex items-center gap-2"><span class="material-symbols-outlined text-primary">trending_up</span>Markup Calculator</h4>
    <label class="font-label-caps text-label-caps text-slate-gray">Cost Price (KES)</label>
    <input id="mk_cost" type="number" value="1500" class="w-full mb-2 px-3 py-2 border border-outline-variant rounded-lg" oninput="calcMarkup()">
    <label class="font-label-caps text-label-caps text-slate-gray">Markup %</label>
    <input id="mk_pct" type="number" value="50" class="w-full mb-3 px-3 py-2 border border-outline-variant rounded-lg" oninput="calcMarkup()">
    <div class="bg-primary-fixed rounded-lg p-3 flex justify-between">
      <span class="font-bold">Selling Price</span>
      <span id="mk_result" class="font-label-numeric font-bold text-primary">KES 2,250</span>
    </div>
  </div>

  <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-card-padding">
    <h4 class="font-headline-md text-[16px] font-bold text-primary mb-3 flex items-center gap-2"><span class="material-symbols-outlined text-warning-amber">sell</span>Discount Calculator</h4>
    <label class="font-label-caps text-label-caps text-slate-gray">Original Price (KES)</label>
    <input id="d_price" type="number" value="2500" class="w-full mb-2 px-3 py-2 border border-outline-variant rounded-lg" oninput="calcDiscount()">
    <label class="font-label-caps text-label-caps text-slate-gray">Discount %</label>
    <input id="d_pct" type="number" value="15" class="w-full mb-3 px-3 py-2 border border-outline-variant rounded-lg" oninput="calcDiscount()">
    <div class="bg-warning-amber/10 rounded-lg p-3 flex justify-between mb-2">
      <span class="font-bold">Discount Amount</span>
      <span id="d_amount" class="font-label-numeric font-bold text-warning-amber">KES 375</span>
    </div>
    <div class="bg-surface-container-high rounded-lg p-3 flex justify-between">
      <span class="font-bold">Final Price</span>
      <span id="d_result" class="font-label-numeric font-bold text-primary">KES 2,125</span>
    </div>
  </div>

  <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-card-padding">
    <h4 class="font-headline-md text-[16px] font-bold text-primary mb-3 flex items-center gap-2"><span class="material-symbols-outlined text-secondary">inventory</span>Bulk Pricing Calculator</h4>
    <label class="font-label-caps text-label-caps text-slate-gray">Selling Price / Item (KES)</label>
    <input id="b_price" type="number" value="2100" class="w-full mb-2 px-3 py-2 border border-outline-variant rounded-lg" oninput="calcBulk()">
    <label class="font-label-caps text-label-caps text-slate-gray">Markup %</label>
    <input id="b_markup" type="number" value="40" class="w-full mb-2 px-3 py-2 border border-outline-variant rounded-lg" oninput="calcBulk()">
    <label class="font-label-caps text-label-caps text-slate-gray">Quantity</label>
    <input id="b_qty" type="number" value="10" class="w-full mb-3 px-3 py-2 border border-outline-variant rounded-lg" oninput="calcBulk()">
    <div class="bg-secondary-container/20 rounded-lg p-3 flex justify-between">
      <span class="font-bold">Total Price</span>
      <span id="b_result" class="font-label-numeric font-bold text-secondary">KES 21,000</span>
    </div>
  </div>

  <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-card-padding">
    <h4 class="font-headline-md text-[16px] font-bold text-primary mb-3 flex items-center gap-2"><span class="material-symbols-outlined text-ai-intelligence">balance</span>Break-even Calculator</h4>
    <label class="font-label-caps text-label-caps text-slate-gray">Fixed Costs (KES)</label>
    <input id="be_fixed" type="number" value="25000" class="w-full mb-2 px-3 py-2 border border-outline-variant rounded-lg" oninput="calcBreakeven()">
    <label class="font-label-caps text-label-caps text-slate-gray">Selling Price / Item (KES)</label>
    <input id="be_price" type="number" value="2500" class="w-full mb-2 px-3 py-2 border border-outline-variant rounded-lg" oninput="calcBreakeven()">
    <label class="font-label-caps text-label-caps text-slate-gray">Variable Cost / Item (KES)</label>
    <input id="be_var" type="number" value="1500" class="w-full mb-3 px-3 py-2 border border-outline-variant rounded-lg" oninput="calcBreakeven()">
    <div class="bg-ai-intelligence/10 rounded-lg p-3 flex justify-between mb-2">
      <span class="font-bold">Break-even Qty</span>
      <span id="be_qty_result" class="font-label-numeric font-bold text-ai-intelligence">25 Items</span>
    </div>
    <div class="bg-surface-container-high rounded-lg p-3 flex justify-between">
      <span class="font-bold">Break-even Sales</span>
      <span id="be_sales_result" class="font-label-numeric font-bold text-primary">KES 62,500</span>
    </div>
  </div>

</div>

<script>
const money = n => 'KES ' + Math.round(n).toLocaleString();

function calcProfit() {
    const cost = parseFloat(document.getElementById('p_cost').value) || 0;
    const sell = parseFloat(document.getElementById('p_sell').value) || 0;
    const qty = parseFloat(document.getElementById('p_qty').value) || 0;
    document.getElementById('p_result').textContent = money((sell - cost) * qty);
}
function calcMargin() {
    const cost = parseFloat(document.getElementById('m_cost').value) || 0;
    const sell = parseFloat(document.getElementById('m_sell').value) || 0;
    const margin = sell > 0 ? ((sell - cost) / sell) * 100 : 0;
    document.getElementById('m_result').textContent = margin.toFixed(1) + '%';
}
function calcMarkup() {
    const cost = parseFloat(document.getElementById('mk_cost').value) || 0;
    const pct = parseFloat(document.getElementById('mk_pct').value) || 0;
    document.getElementById('mk_result').textContent = money(cost * (1 + pct / 100));
}
function calcDiscount() {
    const price = parseFloat(document.getElementById('d_price').value) || 0;
    const pct = parseFloat(document.getElementById('d_pct').value) || 0;
    const amount = price * (pct / 100);
    document.getElementById('d_amount').textContent = money(amount);
    document.getElementById('d_result').textContent = money(price - amount);
}
function calcBulk() {
    const price = parseFloat(document.getElementById('b_price').value) || 0;
    const qty = parseFloat(document.getElementById('b_qty').value) || 0;
    document.getElementById('b_result').textContent = money(price * qty);
}
function calcBreakeven() {
    const fixed = parseFloat(document.getElementById('be_fixed').value) || 0;
    const price = parseFloat(document.getElementById('be_price').value) || 0;
    const varCost = parseFloat(document.getElementById('be_var').value) || 0;
    const contribution = price - varCost;
    const qty = contribution > 0 ? Math.ceil(fixed / contribution) : 0;
    document.getElementById('be_qty_result').textContent = qty + ' Items';
    document.getElementById('be_sales_result').textContent = money(qty * price);
}

calcProfit(); calcMargin(); calcMarkup(); calcDiscount(); calcBulk(); calcBreakeven();
</script>

<?php require __DIR__ . '/includes/layout-footer.php'; ?>
