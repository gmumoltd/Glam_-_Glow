<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_role(['owner', 'salesperson']);

$orderNumber = trim($_GET['order'] ?? '');
if ($orderNumber === '') {
    header('Location: ' . BASE_URL . '/pos.php');
    exit;
}

$stmt = db()->prepare('SELECT o.*, u.name AS cashier_name FROM orders o LEFT JOIN users u ON u.id = o.processed_by WHERE o.order_number = ?');
$stmt->execute([$orderNumber]);
$order = $stmt->fetch();

if (!$order) {
    header('Location: ' . BASE_URL . '/pos.php');
    exit;
}

// Salespeople can only reprint their own sales; owners can reprint any.
$authUser = current_user();
if ($authUser['role'] === 'salesperson' && (int) $order['processed_by'] !== (int) $authUser['id']) {
    header('Location: ' . BASE_URL . '/pos.php');
    exit;
}

$itemsStmt = db()->prepare('SELECT * FROM order_items WHERE order_id = ?');
$itemsStmt->execute([$order['id']]);
$items = $itemsStmt->fetchAll();

$settings = get_store_settings();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Receipt <?= htmlspecialchars($order['order_number']) ?></title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700&family=JetBrains+Mono:wght@400;700&family=Manrope:wght@700;800&family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
<style>
  .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }
  @media print {
    .no-print { display: none !important; }
    body { background: white !important; }
    #receipt { box-shadow: none !important; border: none !important; }
  }
</style>
</head>
<body class="bg-[#F1F5F9] font-['Inter'] min-h-screen py-8">

<div class="max-w-sm mx-auto flex justify-end gap-2 mb-3 no-print">
  <button onclick="window.print()" class="px-4 py-2 bg-[#0F172A] text-white rounded-lg text-sm font-bold flex items-center gap-1.5">
    <span class="material-symbols-outlined text-[18px]">print</span> Print / Save as PDF
  </button>
</div>

<div id="receipt" class="max-w-sm mx-auto bg-white border border-[#E2E8F0] rounded-xl p-6 font-['JetBrains_Mono']">
  <?php if ($order['status'] === 'pending'): ?>
    <div class="text-center mb-3 py-1.5 bg-[#FEE2E2] text-[#991B1B] font-bold text-xs rounded-lg tracking-wide">UNPAID — BALANCE DUE</div>
  <?php endif; ?>
  <div class="text-center mb-4">
    <?php if (!empty($settings['logo_path'])): ?>
      <img src="<?= BASE_URL ?>/uploads/logo/<?= htmlspecialchars($settings['logo_path']) ?>" class="w-12 h-12 object-cover rounded-lg mx-auto mb-2 border border-[#E2E8F0]">
    <?php endif; ?>
    <h1 class="font-['Manrope'] font-extrabold text-lg text-[#0F172A]"><?= htmlspecialchars($settings['business_name']) ?></h1>
    <?php if (!empty($settings['whatsapp_number'])): ?>
      <p class="text-[11px] text-[#64748B]">WhatsApp: <?= htmlspecialchars($settings['whatsapp_number']) ?></p>
    <?php endif; ?>
  </div>

  <div class="border-t border-b border-dashed border-[#CBD5E1] py-2 my-3 text-[11px] text-[#334155] space-y-0.5">
    <div class="flex justify-between"><span>Receipt #</span><span class="font-bold"><?= htmlspecialchars($order['order_number']) ?></span></div>
    <div class="flex justify-between"><span>Date</span><span><?= date('d M Y, H:i', strtotime($order['created_at'])) ?></span></div>
    <div class="flex justify-between"><span>Channel</span><span class="capitalize"><?= htmlspecialchars($order['channel']) ?></span></div>
    <?php if ($order['cashier_name']): ?>
      <div class="flex justify-between"><span>Served by</span><span><?= htmlspecialchars($order['cashier_name']) ?></span></div>
    <?php endif; ?>
    <?php if ($order['customer_name'] && $order['customer_name'] !== 'Walk-in Customer'): ?>
      <div class="flex justify-between"><span>Customer</span><span><?= htmlspecialchars($order['customer_name']) ?></span></div>
    <?php endif; ?>
  </div>

  <table class="w-full text-[11px] mb-3">
    <thead>
      <tr class="text-left border-b border-[#E2E8F0]">
        <th class="pb-1 font-bold">Item</th>
        <th class="pb-1 font-bold text-center">Qty</th>
        <th class="pb-1 font-bold text-right">Price</th>
        <th class="pb-1 font-bold text-right">Total</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($items as $item): ?>
        <tr class="align-top">
          <td class="py-1 pr-1">
            <?= htmlspecialchars($item['product_name']) ?>
            <?php if ($item['color'] !== 'Standard' || $item['size'] !== 'One Size'): ?>
              <div class="text-[#94A3B8]"><?= htmlspecialchars($item['color']) ?> / <?= htmlspecialchars($item['size']) ?></div>
            <?php endif; ?>
          </td>
          <td class="py-1 text-center"><?= (int) $item['qty'] ?></td>
          <td class="py-1 text-right"><?= number_format($item['unit_price'], 0) ?></td>
          <td class="py-1 text-right font-bold"><?= number_format($item['unit_price'] * $item['qty'], 0) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <div class="border-t border-dashed border-[#CBD5E1] pt-2 text-[12px] space-y-1">
    <div class="flex justify-between"><span>Subtotal</span><span><?= money($order['subtotal']) ?></span></div>
    <?php if ($order['discount'] > 0): ?>
      <div class="flex justify-between text-[#EF4444]"><span>Discount</span><span>-<?= money($order['discount']) ?></span></div>
    <?php endif; ?>
    <div class="flex justify-between font-bold text-base text-[#0F172A] border-t border-[#E2E8F0] pt-1 mt-1"><span>Total</span><span><?= money($order['total']) ?></span></div>
  </div>

  <div class="text-center mt-5 pt-3 border-t border-dashed border-[#CBD5E1]">
    <p class="font-['Manrope'] font-bold text-sm text-[#0F172A]">Thank you for shopping with us!</p>
    <p class="text-[10px] text-[#94A3B8] mt-1">We'd love to see you again.</p>
  </div>
</div>

</body>
</html>
