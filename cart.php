<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/shop_cart.php';

$settings = get_store_settings();
$socialLinks = get_social_links();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['clear_after_checkout'])) {
        shop_cart_clear();
        header('Location: ' . BASE_URL . '/cart.php?checked_out=1');
        exit;
    }
    foreach ($_POST['qty'] ?? [] as $variantId => $qty) {
        shop_cart_update((int) $variantId, (int) $qty);
    }
    if (isset($_POST['remove'])) {
        shop_cart_remove((int) $_POST['remove']);
    }
    header('Location: ' . BASE_URL . '/cart.php');
    exit;
}

$items = shop_cart_items();
$subtotal = shop_cart_subtotal();
$checkedOut = isset($_GET['checked_out']);

$waMessage = "Hello {$settings['business_name']}, I'd like to order:\n";
foreach ($items as $item) {
    $waMessage .= "- {$item['product_name']} ({$item['color']}, {$item['size']}) x{$item['qty']} — " . money($item['line_total']) . "\n";
}
$waMessage .= 'Total: ' . money($subtotal);
$waLink = 'https://wa.me/' . $settings['whatsapp_number'] . '?text=' . rawurlencode($waMessage);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Your Cart — <?= htmlspecialchars($settings['business_name']) ?></title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700&family=Manrope:wght@700;800&family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
<style>.material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }</style>
</head>
<body class="bg-[#F8FAFC] font-['Inter'] min-h-screen">

<header class="bg-white border-b border-[#E2E8F0] sticky top-0 z-10">
  <div class="max-w-3xl mx-auto px-4 h-16 flex items-center gap-3">
    <a href="<?= BASE_URL ?>/shop.php" class="material-symbols-outlined text-[#0F172A]">arrow_back</a>
    <h1 class="font-['Manrope'] font-bold text-lg text-[#0F172A] flex-1">Your Cart</h1>
    <a href="<?= BASE_URL ?>/login.php" class="flex items-center gap-1 px-3 py-2 text-[#64748B] hover:text-[#0F172A] text-sm font-bold" title="Staff Login">
      <span class="material-symbols-outlined text-[18px]">login</span>
      <span class="hidden sm:inline">Staff Login</span>
    </a>
  </div>
  <?php if ($socialLinks): ?>
    <div class="max-w-3xl mx-auto px-4 pb-3">
      <?php render_social_bar($socialLinks, 'small'); ?>
    </div>
  <?php endif; ?>
</header>

<main class="max-w-3xl mx-auto px-4 py-8">
  <?php if ($checkedOut): ?>
    <div class="bg-white border border-[#E2E8F0] rounded-xl p-8 text-center">
      <span class="material-symbols-outlined text-[#25D366] text-[48px]">check_circle</span>
      <h2 class="font-['Manrope'] text-xl font-bold text-[#0F172A] mt-2">Thanks! Your order was sent on WhatsApp.</h2>
      <p class="text-[#64748B] mt-1">If a chat window didn't open, message us directly to confirm your order.</p>
      <a href="<?= BASE_URL ?>/shop.php" class="inline-block mt-4 px-6 py-2.5 bg-[#0F172A] text-white rounded-lg font-bold text-sm">Continue Shopping</a>
    </div>
  <?php elseif (empty($items)): ?>
    <div class="bg-white border border-[#E2E8F0] rounded-xl p-8 text-center">
      <span class="material-symbols-outlined text-[#94A3B8] text-[48px]">shopping_cart</span>
      <p class="text-[#64748B] mt-2">Your cart is empty.</p>
      <a href="<?= BASE_URL ?>/shop.php" class="inline-block mt-4 px-6 py-2.5 bg-[#0F172A] text-white rounded-lg font-bold text-sm">Continue Shopping</a>
    </div>
  <?php else: ?>
    <form method="post" class="bg-white border border-[#E2E8F0] rounded-xl overflow-hidden mb-4">
      <?php foreach ($items as $item): ?>
        <div class="flex items-center gap-3 p-4 border-b border-[#E2E8F0] last:border-0">
          <div class="flex-1">
            <p class="font-bold text-sm text-[#0F172A]"><?= htmlspecialchars($item['product_name']) ?></p>
            <p class="text-xs text-[#64748B]"><?= htmlspecialchars($item['color']) ?> · <?= htmlspecialchars($item['size']) ?> · <?= money($item['effective_price']) ?></p>
          </div>
          <input type="number" name="qty[<?= $item['id'] ?>]" value="<?= $item['qty'] ?>" min="1" max="<?= $item['stock'] ?>" class="w-16 px-2 py-1 border border-[#E2E8F0] rounded-lg text-sm text-center">
          <p class="font-bold text-sm w-24 text-right"><?= money($item['line_total']) ?></p>
          <button type="submit" name="remove" value="<?= $item['id'] ?>" class="material-symbols-outlined text-[#EF4444] text-[20px]">close</button>
        </div>
      <?php endforeach; ?>
      <div class="p-4 bg-[#F8FAFC] flex justify-between items-center">
        <span class="text-sm text-[#64748B]">Update quantities</span>
        <button type="submit" class="px-4 py-2 border border-[#E2E8F0] rounded-lg text-sm font-bold text-[#0F172A]">Update Cart</button>
      </div>
    </form>

    <div class="bg-white border border-[#E2E8F0] rounded-xl p-4">
      <div class="flex justify-between text-lg font-bold text-[#0F172A] mb-4">
        <span>Total</span>
        <span><?= money($subtotal) ?></span>
      </div>
      <div class="flex gap-3">
        <a href="<?= BASE_URL ?>/shop.php" class="flex-1 text-center px-4 py-3 border border-[#E2E8F0] rounded-lg font-bold text-sm text-[#0F172A]">Continue Shopping</a>
        <button id="checkoutBtn" class="flex-1 flex items-center justify-center gap-1 px-4 py-3 bg-[#25D366] text-white rounded-lg font-bold text-sm">
          <span class="material-symbols-outlined text-[18px]">chat</span> Checkout via WhatsApp
        </button>
      </div>
    </div>

    <form method="post" id="clearCartForm" class="hidden">
      <input type="hidden" name="clear_after_checkout" value="1">
    </form>
  <?php endif; ?>
</main>

<?php if ($socialLinks): ?>
<div class="max-w-3xl mx-auto px-4 pb-4">
  <?php render_social_bar($socialLinks); ?>
</div>
<?php endif; ?>

<footer class="text-center text-[11px] text-[#94A3B8] py-6">
  &copy; <?= date('Y') ?> <?= htmlspecialchars($settings['business_name']) ?>. Powered by StoreMind.
</footer>

<script>
const checkoutBtn = document.getElementById('checkoutBtn');
if (checkoutBtn) {
    checkoutBtn.addEventListener('click', () => {
        window.open(<?= json_encode($waLink) ?>, '_blank');
        document.getElementById('clearCartForm').requestSubmit();
    });
}
</script>

</body>
</html>
