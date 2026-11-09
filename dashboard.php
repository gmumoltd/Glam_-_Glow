<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_role(['owner']);

$pdo = db();

$todaySales = (float) $pdo->query("SELECT COALESCE(SUM(total),0) FROM orders WHERE DATE(created_at)=CURDATE() AND status = 'paid'")->fetchColumn();

$todayProfit = (float) $pdo->query("SELECT COALESCE(SUM((oi.unit_price - p.cost_price) * oi.qty),0)
    FROM order_items oi
    JOIN orders o ON o.id = oi.order_id
    JOIN products p ON p.id = oi.product_id
    WHERE DATE(o.created_at) = CURDATE() AND o.status = 'paid'")->fetchColumn();

$totalOrders = (int) $pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn();

$outstanding = $pdo->query("SELECT COUNT(*) AS c, COALESCE(SUM(total),0) AS amount FROM orders WHERE status = 'pending'")->fetch();

$lowStockCount = (int) $pdo->query('SELECT COUNT(*) FROM (
    SELECT p.id FROM products p
    JOIN product_variants pv ON pv.product_id = p.id
    GROUP BY p.id, p.reorder_level
    HAVING COALESCE(SUM(pv.stock),0) <= p.reorder_level
) t')->fetchColumn();

$healthScore = max(0, min(100, 100 - ($lowStockCount * 6)));
$healthLabel = $healthScore >= 80 ? 'Excellent Performance' : ($healthScore >= 60 ? 'Good Performance' : 'Needs Attention');

$channelCounts = $pdo->query("SELECT channel, COUNT(*) AS c, COALESCE(SUM(total),0) AS revenue
    FROM orders WHERE status = 'paid' GROUP BY channel")->fetchAll();
$channelTotal = array_sum(array_column($channelCounts, 'c'));

$topProducts = $pdo->query("SELECT oi.product_name, SUM(oi.qty) AS items_sold, SUM(oi.qty*oi.unit_price) AS revenue
    FROM order_items oi JOIN orders o ON o.id = oi.order_id
    WHERE o.status = 'paid'
    GROUP BY oi.product_name ORDER BY items_sold DESC LIMIT 5")->fetchAll();

$recentOrders = $pdo->query('SELECT * FROM orders ORDER BY created_at DESC LIMIT 6')->fetchAll();

$allLowStockItems = get_low_stock_items();
$lowStockItems = array_slice($allLowStockItems, 0, 5);
$lowStockDigestLink = low_stock_whatsapp_link($allLowStockItems);

$gaugeCircumference = 201; // 2*pi*32
$gaugeOffset = (int) round($gaugeCircumference * (1 - $healthScore / 100));

$pageTitle = 'Command Center';
$activeNav = 'dashboard';
require __DIR__ . '/includes/layout-header.php';
?>

<div class="glam-command flex flex-wrap justify-between items-end gap-3">
  <div>
    <p class="font-label-caps text-label-caps text-slate-gray mb-1 uppercase">Glam &amp; Glow · Owner overview</p>
    <h2 class="font-headline-lg text-headline-lg text-primary">Command Center</h2>
  </div>
  <div class="flex gap-stack-sm">
    <span class="glam-command-date flex items-center gap-2">
      <span class="material-symbols-outlined text-[18px]">calendar_today</span> Today
    </span>
    <a href="<?= BASE_URL ?>/pos.php" class="px-4 py-2 bg-primary text-on-primary rounded-lg font-label-caps text-label-caps flex items-center gap-2 hover:opacity-90 transition-opacity">
      <span class="material-symbols-outlined text-[18px]">add</span> New Sale
    </a>
  </div>
</div>

<div class="glam-metrics grid gap-gutter">
  <div class="bg-surface-container-lowest p-card-padding border border-outline-variant rounded-xl hover:shadow-lg transition-all duration-300">
    <div class="flex justify-between items-start mb-4">
      <div class="w-10 h-10 bg-primary-fixed rounded-lg flex items-center justify-center text-primary">
        <span class="material-symbols-outlined">payments</span>
      </div>
    </div>
    <p class="font-label-caps text-label-caps text-slate-gray mb-1">Total Sales (Today)</p>
    <h3 class="font-headline-md text-headline-md text-primary font-bold"><?= money($todaySales) ?></h3>
  </div>

  <div class="bg-surface-container-lowest p-card-padding border border-outline-variant rounded-xl hover:shadow-lg transition-all duration-300">
    <div class="flex justify-between items-start mb-4">
      <div class="w-10 h-10 bg-secondary-fixed rounded-lg flex items-center justify-center text-secondary">
        <span class="material-symbols-outlined">account_balance_wallet</span>
      </div>
    </div>
    <p class="font-label-caps text-label-caps text-slate-gray mb-1">Today's Profit</p>
    <h3 class="font-headline-md text-headline-md text-primary font-bold"><?= money($todayProfit) ?></h3>
  </div>

  <a href="<?= BASE_URL ?>/sales-feed.php?status=pending" class="bg-surface-container-lowest p-card-padding border border-outline-variant rounded-xl hover:shadow-lg transition-all duration-300 block">
    <div class="flex justify-between items-start mb-4">
      <div class="w-10 h-10 bg-error-container rounded-lg flex items-center justify-center text-on-error-container">
        <span class="material-symbols-outlined">receipt_long</span>
      </div>
    </div>
    <p class="font-label-caps text-label-caps text-slate-gray mb-1">Outstanding Payments</p>
    <h3 class="font-headline-md text-headline-md text-primary font-bold"><?= money($outstanding['amount']) ?></h3>
    <p class="font-body-sm text-body-sm text-danger-red mt-1"><?= (int) $outstanding['c'] ?> unpaid order<?= (int) $outstanding['c'] === 1 ? '' : 's' ?></p>
  </a>

  <div class="bg-surface-container-lowest p-card-padding border border-outline-variant rounded-xl flex items-center justify-between hover:shadow-lg transition-all duration-300">
    <div>
      <p class="font-label-caps text-label-caps text-slate-gray mb-1">Business Health Score</p>
      <h3 class="font-headline-md text-headline-md text-primary font-bold"><?= $healthScore ?>/100</h3>
      <p class="font-body-sm text-body-sm text-secondary mt-1"><?= $healthLabel ?></p>
    </div>
    <div class="relative w-20 h-20">
      <svg viewBox="0 0 80 80" aria-hidden="true" class="w-full h-full transform -rotate-90">
        <circle class="text-surface-container-high" cx="40" cy="40" fill="transparent" r="32" stroke="currentColor" stroke-width="8"></circle>
        <circle class="text-secondary" cx="40" cy="40" fill="transparent" r="32" stroke="currentColor" stroke-dasharray="<?= $gaugeCircumference ?>" stroke-dashoffset="<?= $gaugeOffset ?>" stroke-width="8"></circle>
      </svg>
      <div class="absolute inset-0 flex items-center justify-center">
        <span class="material-symbols-outlined text-secondary">favorite</span>
      </div>
    </div>
  </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-gutter">
  <div class="bg-surface-container-lowest p-card-padding border border-outline-variant rounded-xl">
    <div class="flex justify-between items-center mb-stack-lg">
      <h4 class="font-headline-md text-headline-md font-bold text-primary">Sales by Channel</h4>
    </div>
    <?php if ($channelTotal === 0): ?>
      <p class="text-slate-gray font-body-sm">No sales recorded yet.</p>
    <?php else: ?>
      <div class="space-y-3">
        <?php $colors = ['walk-in'=>'primary','website'=>'secondary','whatsapp'=>'ai-intelligence','tiktok'=>'warning-amber'];
        foreach ($channelCounts as $ch):
          $pct = round($ch['c'] / $channelTotal * 100);
        ?>
          <div>
            <div class="flex justify-between text-body-sm mb-1">
              <span class="capitalize font-bold"><?= htmlspecialchars($ch['channel']) ?></span>
              <span class="font-label-numeric text-label-numeric"><?= $pct ?>% &middot; <?= money($ch['revenue']) ?></span>
            </div>
            <div class="w-full bg-surface-container-high h-2 rounded-full">
              <div class="h-2 rounded-full bg-<?= $colors[$ch['channel']] ?? 'primary' ?>" style="width: <?= $pct ?>%"></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <div class="bg-surface-container-lowest p-card-padding border border-outline-variant rounded-xl">
    <h4 class="font-headline-md text-headline-md font-bold text-primary mb-stack-md">Top Selling Products</h4>
    <div class="glam-dashboard-table" tabindex="0" role="region" aria-label="Scrollable sales table"><table class="w-full">
      <thead>
        <tr class="text-left border-b border-outline-variant">
          <th class="py-2 font-label-caps text-label-caps text-slate-gray">Product</th>
          <th class="py-2 font-label-caps text-label-caps text-slate-gray text-right">Sold</th>
          <th class="py-2 font-label-caps text-label-caps text-slate-gray text-right">Revenue</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-outline-variant">
        <?php foreach ($topProducts as $tp): ?>
          <tr>
            <td class="py-2 font-body-sm"><?= htmlspecialchars($tp['product_name']) ?></td>
            <td class="py-2 font-label-numeric text-label-numeric text-right"><?= (int) $tp['items_sold'] ?></td>
            <td class="py-2 font-label-numeric text-label-numeric text-right"><?= money($tp['revenue']) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (empty($topProducts)): ?>
          <tr><td colspan="3" class="py-4 text-center text-slate-gray font-body-sm">No sales yet.</td></tr>
        <?php endif; ?>
      </tbody>
    </table></div>
  </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-gutter">
  <div class="bg-surface-container-lowest p-card-padding border border-outline-variant rounded-xl">
    <div class="flex justify-between items-center mb-stack-md">
      <h4 class="font-headline-md text-headline-md font-bold text-primary">Recent Orders</h4>
      <a href="<?= BASE_URL ?>/sales-feed.php" class="font-label-caps text-[11px] text-primary">View all</a>
    </div>
    <div class="glam-dashboard-table" tabindex="0" role="region" aria-label="Scrollable sales table"><table class="w-full">
      <thead>
        <tr class="text-left border-b border-outline-variant">
          <th class="py-2 font-label-caps text-label-caps text-slate-gray">Order</th>
          <th class="py-2 font-label-caps text-label-caps text-slate-gray">Customer</th>
          <th class="py-2 font-label-caps text-label-caps text-slate-gray text-right">Total</th>
          <th class="py-2 font-label-caps text-label-caps text-slate-gray text-right">Status</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-outline-variant">
        <?php foreach ($recentOrders as $o): ?>
          <tr>
            <td class="py-2 font-label-numeric text-label-numeric"><?= htmlspecialchars($o['order_number']) ?></td>
            <td class="py-2 font-body-sm"><?= htmlspecialchars($o['customer_name']) ?></td>
            <td class="py-2 font-label-numeric text-label-numeric text-right"><?= money($o['total']) ?></td>
            <td class="py-2 text-right">
              <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-bold bg-secondary-container/30 text-on-secondary-container capitalize"><?= htmlspecialchars($o['status']) ?></span>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (empty($recentOrders)): ?>
          <tr><td colspan="4" class="py-4 text-center text-slate-gray font-body-sm">No orders yet.</td></tr>
        <?php endif; ?>
      </tbody>
    </table></div>
  </div>

  <div class="bg-surface-container-lowest p-card-padding border border-outline-variant rounded-xl">
    <div class="flex justify-between items-center mb-stack-md">
      <h4 class="font-headline-md text-headline-md font-bold text-primary">Stock Alerts</h4>
      <?php if ($lowStockDigestLink): ?>
        <a href="<?= htmlspecialchars($lowStockDigestLink) ?>" target="_blank" class="font-label-caps text-[11px] text-secondary flex items-center gap-1">
          <span class="material-symbols-outlined text-[16px]">chat</span> Send via WhatsApp
        </a>
      <?php endif; ?>
    </div>
    <?php foreach ($lowStockItems as $ls): ?>
      <div class="flex justify-between items-center py-2 border-b border-outline-variant last:border-0">
        <span class="font-body-sm"><?= htmlspecialchars($ls['name']) ?></span>
        <span class="font-label-numeric text-[12px] font-bold text-danger-red"><?= $ls['stock'] ?> left</span>
      </div>
    <?php endforeach; ?>
    <?php if (empty($lowStockItems)): ?>
      <p class="text-slate-gray font-body-sm">All stock levels healthy.</p>
    <?php endif; ?>
  </div>
</div>

<?php require __DIR__ . '/includes/layout-footer.php'; ?>
