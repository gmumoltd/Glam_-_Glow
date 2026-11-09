<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_role(['owner']);

if (isset($_GET['mark_paid'])) {
    db()->prepare("UPDATE orders SET status = 'paid' WHERE id = ? AND status = 'pending'")->execute([(int) $_GET['mark_paid']]);
    header('Location: ' . BASE_URL . '/sales-feed.php' . (!empty($_GET['channel']) ? '?channel=' . urlencode($_GET['channel']) : ''));
    exit;
}

$channel = $_GET['channel'] ?? '';
$statusFilter = $_GET['status'] ?? '';

$sql = 'SELECT * FROM orders';
$conditions = [];
$params = [];
if ($channel && in_array($channel, ['walk-in', 'website', 'whatsapp', 'tiktok'], true)) {
    $conditions[] = 'channel = ?';
    $params[] = $channel;
}
if ($statusFilter && in_array($statusFilter, ['paid', 'pending'], true)) {
    $conditions[] = 'status = ?';
    $params[] = $statusFilter;
}
if ($conditions) {
    $sql .= ' WHERE ' . implode(' AND ', $conditions);
}
$sql .= ' ORDER BY created_at DESC LIMIT 50';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

$channelIcons = ['walk-in' => 'storefront', 'website' => 'language', 'whatsapp' => 'chat', 'tiktok' => 'music_note'];

$pageTitle = 'Multi-Channel Sales Feed';
$activeNav = 'sales-feed';
require __DIR__ . '/includes/layout-header.php';
?>

<div class="flex justify-between items-center">
  <h2 class="font-headline-lg text-headline-lg text-primary">Multi-Channel Sales Feed</h2>
</div>

<div class="flex flex-wrap gap-2 items-center">
  <a href="<?= BASE_URL ?>/sales-feed.php" class="px-4 py-1.5 <?= !$channel ? 'bg-primary text-on-primary' : 'bg-surface-container-lowest border border-outline-variant text-slate-gray' ?> rounded-full font-label-caps text-[11px]">All Channels</a>
  <?php foreach (['walk-in' => 'Walk-in', 'website' => 'Website', 'whatsapp' => 'WhatsApp', 'tiktok' => 'TikTok'] as $key => $label): ?>
    <a href="<?= BASE_URL ?>/sales-feed.php?channel=<?= $key ?><?= $statusFilter ? '&status=' . $statusFilter : '' ?>" class="px-4 py-1.5 <?= $channel === $key ? 'bg-primary text-on-primary' : 'bg-surface-container-lowest border border-outline-variant text-slate-gray' ?> rounded-full font-label-caps text-[11px] flex items-center gap-1">
      <span class="material-symbols-outlined text-[14px]"><?= $channelIcons[$key] ?></span> <?= $label ?>
    </a>
  <?php endforeach; ?>
  <span class="h-5 w-[1px] bg-outline-variant mx-1"></span>
  <a href="<?= BASE_URL ?>/sales-feed.php?status=pending<?= $channel ? '&channel=' . $channel : '' ?>" class="px-4 py-1.5 <?= $statusFilter === 'pending' ? 'bg-danger-red text-white' : 'bg-surface-container-lowest border border-outline-variant text-slate-gray' ?> rounded-full font-label-caps text-[11px]">Unpaid Only</a>
  <?php if ($statusFilter): ?>
    <a href="<?= BASE_URL ?>/sales-feed.php<?= $channel ? '?channel=' . $channel : '' ?>" class="px-3 py-1.5 text-[11px] text-slate-gray underline">Clear</a>
  <?php endif; ?>
</div>

<div class="bg-surface-container-lowest border border-outline-variant rounded-xl overflow-hidden">
  <div class="overflow-x-auto">
  <table class="w-full border-collapse">
    <thead>
      <tr class="bg-slate-gray/5 text-left border-b border-outline-variant">
        <th class="p-4 font-label-caps text-label-caps text-slate-gray">Order</th>
        <th class="p-4 font-label-caps text-label-caps text-slate-gray">Channel</th>
        <th class="p-4 font-label-caps text-label-caps text-slate-gray">Customer</th>
        <th class="p-4 font-label-caps text-label-caps text-slate-gray text-right">Total</th>
        <th class="p-4 font-label-caps text-label-caps text-slate-gray text-right">Payment</th>
        <th class="p-4 font-label-caps text-label-caps text-slate-gray text-right">Date</th>
        <th class="p-4 font-label-caps text-label-caps text-slate-gray text-right">Receipt</th>
      </tr>
    </thead>
    <tbody class="divide-y divide-outline-variant">
      <?php foreach ($orders as $o): ?>
        <tr class="hover:bg-surface-container-low transition-colors">
          <td class="p-4 font-label-numeric text-label-numeric"><?= htmlspecialchars($o['order_number']) ?></td>
          <td class="p-4">
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-surface-container-high text-[11px] font-bold capitalize">
              <span class="material-symbols-outlined text-[14px]"><?= $channelIcons[$o['channel']] ?? 'shopping_bag' ?></span> <?= htmlspecialchars($o['channel']) ?>
            </span>
          </td>
          <td class="p-4 font-body-sm"><?= htmlspecialchars($o['customer_name']) ?></td>
          <td class="p-4 font-label-numeric text-label-numeric text-right"><?= money($o['total']) ?></td>
          <td class="p-4 text-right">
            <?php if ($o['status'] === 'pending'): ?>
              <div class="flex items-center justify-end gap-2">
                <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-bold bg-error-container text-on-error-container">Unpaid</span>
                <a href="<?= BASE_URL ?>/sales-feed.php?mark_paid=<?= $o['id'] ?><?= $channel ? '&channel=' . $channel : '' ?>" onclick="return confirm('Mark order <?= htmlspecialchars(addslashes($o['order_number'])) ?> as paid?')" class="text-[11px] font-bold text-secondary hover:underline">Mark Paid</a>
              </div>
            <?php elseif ($o['status'] === 'paid'): ?>
              <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-bold bg-secondary-container/30 text-on-secondary-container">Paid</span>
            <?php else: ?>
              <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-bold bg-surface-container-high text-slate-gray capitalize"><?= htmlspecialchars($o['status']) ?></span>
            <?php endif; ?>
          </td>
          <td class="p-4 text-right font-body-sm text-slate-gray"><?= date('d M, H:i', strtotime($o['created_at'])) ?></td>
          <td class="p-4 text-right">
            <a href="<?= BASE_URL ?>/receipt.php?order=<?= urlencode($o['order_number']) ?>" target="_blank" class="p-2 text-slate-gray hover:text-primary transition-colors inline-block" title="Print Receipt"><span class="material-symbols-outlined">print</span></a>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($orders)): ?>
        <tr><td colspan="7" class="p-8 text-center text-slate-gray font-body-sm">No orders match these filters.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
  </div>
</div>

<?php require __DIR__ . '/includes/layout-footer.php'; ?>
