<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/shop_cart.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart_variant'])) {
    $variantId = (int) $_POST['add_to_cart_variant'];
    $qty = max(1, (int) ($_POST['qty'] ?? 1));
    $variant = get_variant($variantId);
    if ($variant && $variant['stock'] >= $qty) {
        shop_cart_add($variantId, $qty);
    }
    header('Location: ' . BASE_URL . '/shop.php#p' . (int) ($_POST['product_id'] ?? 0));
    exit;
}

$settings = get_store_settings();
$socialLinks = get_social_links();
$categories = get_categories();

$search = trim($_GET['q'] ?? '');
$categoryId = (int) ($_GET['category'] ?? 0);
$minPrice = isset($_GET['min_price']) && $_GET['min_price'] !== '' ? (float) $_GET['min_price'] : null;
$maxPrice = isset($_GET['max_price']) && $_GET['max_price'] !== '' ? (float) $_GET['max_price'] : null;
$size = trim($_GET['size'] ?? '');

$sql = 'SELECT p.*, c.name AS category_name,
               (SELECT image_path FROM product_images pi WHERE pi.product_id = p.id ORDER BY sort_order LIMIT 1) AS thumbnail,
               (SELECT COALESCE(SUM(stock), 0) FROM product_variants pv WHERE pv.product_id = p.id) AS total_stock
        FROM products p
        JOIN categories c ON c.id = p.category_id
        WHERE p.status = "active"';
$params = [];

if ($search !== '') {
    $sql .= ' AND (p.name LIKE ? OR p.sku LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}
if ($categoryId) {
    $sql .= ' AND p.category_id = ?';
    $params[] = $categoryId;
}
if ($minPrice !== null) {
    $sql .= ' AND p.price >= ?';
    $params[] = $minPrice;
}
if ($maxPrice !== null) {
    $sql .= ' AND p.price <= ?';
    $params[] = $maxPrice;
}
if ($size !== '') {
    $sql .= ' AND EXISTS (SELECT 1 FROM product_variants pv2 WHERE pv2.product_id = p.id AND pv2.size = ?)';
    $params[] = $size;
}

$sql .= ' ORDER BY p.created_at DESC';

$stmt = db()->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

$allSizes = db()->query('SELECT DISTINCT size FROM product_variants ORDER BY size')->fetchAll(PDO::FETCH_COLUMN);

function whatsapp_buy_link(array $p, string $waNumber): string
{
    $price = $p['on_sale'] && $p['discount_percent'] !== null
        ? $p['price'] * (1 - $p['discount_percent'] / 100)
        : $p['price'];
    $message = "Hello {$GLOBALS['settings']['business_name']}, I'm interested in {$p['name']} — " . money($price) . '. Is it available?';
    return 'https://wa.me/' . $waNumber . '?text=' . rawurlencode($message);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($settings['business_name']) ?> — Shop</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700&family=Manrope:wght@700;800&family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
<style>.material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }</style>
</head>
<body class="bg-[#F8FAFC] font-['Inter'] min-h-screen">

<header class="bg-white border-b border-[#E2E8F0] sticky top-0 z-10">
  <div class="max-w-6xl mx-auto px-4 h-16 flex items-center gap-3">
    <?php if (!empty($settings['logo_path'])): ?>
      <img src="<?= BASE_URL ?>/uploads/logo/<?= htmlspecialchars($settings['logo_path']) ?>" class="w-9 h-9 rounded-lg object-cover">
    <?php else: ?>
      <div class="w-9 h-9 bg-[#0F172A] rounded-lg flex items-center justify-center">
        <span class="material-symbols-outlined text-white text-[20px]">storefront</span>
      </div>
    <?php endif; ?>
    <h1 class="font-['Manrope'] font-bold text-lg text-[#0F172A] flex-1"><?= htmlspecialchars($settings['business_name']) ?></h1>
    <a href="<?= BASE_URL ?>/login.php" class="flex items-center gap-1 px-3 py-2 text-[#64748B] hover:text-[#0F172A] text-sm font-bold" title="Staff Login">
      <span class="material-symbols-outlined text-[18px]">login</span>
      <span class="hidden sm:inline">Staff Login</span>
    </a>
    <a href="<?= BASE_URL ?>/cart.php" class="relative flex items-center gap-1 px-3 py-2 bg-[#0F172A] text-white rounded-lg text-sm font-bold">
      <span class="material-symbols-outlined text-[18px]">shopping_cart</span> Cart
      <span class="absolute -top-2 -right-2 bg-[#EF4444] text-white text-[10px] w-5 h-5 rounded-full flex items-center justify-center"><?= shop_cart_count() ?></span>
    </a>
  </div>
  <?php if ($socialLinks): ?>
    <div class="max-w-6xl mx-auto px-4 pb-3">
      <?php render_social_bar($socialLinks, 'small'); ?>
    </div>
  <?php endif; ?>
</header>

<main class="max-w-6xl mx-auto px-4 py-8">
  <h2 class="font-['Manrope'] text-2xl font-bold text-[#0F172A] mb-1">Shop Our Collection</h2>
  <p class="text-[#64748B] mb-6">Add items to your cart, or buy instantly on WhatsApp.</p>

  <button type="button" id="filterToggle" class="md:hidden w-full flex items-center justify-between px-4 py-3 mb-4 bg-white border border-[#E2E8F0] rounded-xl font-bold text-sm text-[#0F172A]">
    <span class="flex items-center gap-2"><span class="material-symbols-outlined text-[18px]">tune</span> Filters<?= ($search !== '' || $categoryId || $minPrice !== null || $maxPrice !== null || $size !== '') ? ' <span class="text-[#0F172A]">&bull;</span>' : '' ?></span>
    <span class="material-symbols-outlined text-[18px]" id="filterToggleIcon">expand_more</span>
  </button>

  <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
    <!-- Filters -->
    <form method="get" id="filterPanel" class="hidden md:block md:col-span-1 bg-white border border-[#E2E8F0] rounded-xl p-4 h-fit space-y-4">
      <div>
        <label class="text-xs font-bold text-[#64748B] block mb-1">Search</label>
        <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Product name or SKU" class="w-full px-3 py-2 border border-[#E2E8F0] rounded-lg text-sm">
      </div>
      <div>
        <label class="text-xs font-bold text-[#64748B] block mb-1">Category</label>
        <select name="category" class="w-full px-3 py-2 border border-[#E2E8F0] rounded-lg text-sm">
          <option value="">All Categories</option>
          <?php foreach ($categories as $cat): ?>
            <option value="<?= $cat['id'] ?>" <?= $categoryId === (int) $cat['id'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="text-xs font-bold text-[#64748B] block mb-1">Price Range (KES)</label>
        <div class="flex gap-2">
          <input type="number" name="min_price" value="<?= htmlspecialchars($_GET['min_price'] ?? '') ?>" placeholder="Min" class="w-1/2 px-2 py-2 border border-[#E2E8F0] rounded-lg text-sm">
          <input type="number" name="max_price" value="<?= htmlspecialchars($_GET['max_price'] ?? '') ?>" placeholder="Max" class="w-1/2 px-2 py-2 border border-[#E2E8F0] rounded-lg text-sm">
        </div>
      </div>
      <div>
        <label class="text-xs font-bold text-[#64748B] block mb-1">Size</label>
        <select name="size" class="w-full px-3 py-2 border border-[#E2E8F0] rounded-lg text-sm">
          <option value="">All Sizes</option>
          <?php foreach ($allSizes as $s): ?>
            <option value="<?= htmlspecialchars($s) ?>" <?= $size === $s ? 'selected' : '' ?>><?= htmlspecialchars($s) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <button type="submit" class="w-full py-2.5 bg-[#0F172A] text-white rounded-lg font-bold text-sm">Apply Filters</button>
      <a href="<?= BASE_URL ?>/shop.php" class="block text-center text-xs text-[#64748B] hover:underline">Clear all</a>
    </form>

    <!-- Product Grid -->
    <div class="md:col-span-3">
      <p class="text-xs text-[#64748B] mb-3"><?= count($products) ?> product<?= count($products) === 1 ? '' : 's' ?> found</p>
      <div class="grid grid-cols-2 lg:grid-cols-3 gap-4">
        <?php foreach ($products as $p): ?>
          <?php
            $onSale = $p['on_sale'] && $p['discount_percent'] !== null;
            $finalPrice = $onSale ? $p['price'] * (1 - $p['discount_percent'] / 100) : $p['price'];
            $imgSrc = $p['thumbnail'] ? BASE_URL . '/uploads/products/' . htmlspecialchars($p['thumbnail']) : null;
            $variants = get_variants_for_product($p['id']);
          ?>
          <div id="p<?= $p['id'] ?>" class="bg-white border border-[#E2E8F0] rounded-xl overflow-hidden relative hover:shadow-md transition-shadow">
            <?php if ($onSale): ?>
              <span class="absolute top-2 left-2 bg-[#EF4444] text-white text-[11px] font-bold px-2 py-1 rounded-full z-10">-<?= htmlspecialchars($p['discount_percent']) ?>% OFF</span>
            <?php endif; ?>
            <a href="<?= BASE_URL ?>/product-detail.php?id=<?= $p['id'] ?>">
              <?php if ($imgSrc): ?>
                <img src="<?= $imgSrc ?>" class="w-full h-40 object-cover">
              <?php else: ?>
                <div class="w-full h-40 bg-[#F1F5F9] flex items-center justify-center text-[#94A3B8]">
                  <span class="material-symbols-outlined text-[32px]">inventory_2</span>
                </div>
              <?php endif; ?>
            </a>
            <div class="p-3">
              <p class="text-[10px] uppercase tracking-wide text-[#64748B]"><?= htmlspecialchars($p['category_name']) ?></p>
              <a href="<?= BASE_URL ?>/product-detail.php?id=<?= $p['id'] ?>" class="font-bold text-sm text-[#0F172A] truncate block hover:underline"><?= htmlspecialchars($p['name']) ?></a>
              <?php if ($onSale): ?>
                <p class="text-sm mt-1"><span class="line-through text-[#94A3B8] text-xs mr-1"><?= money($p['price']) ?></span><span class="font-bold text-[#EF4444]"><?= money($finalPrice) ?></span></p>
              <?php else: ?>
                <p class="text-sm font-bold text-[#0F172A] mt-1"><?= money($p['price']) ?></p>
              <?php endif; ?>

              <?php if ($p['total_stock'] <= 0): ?>
                <span class="inline-block mt-2 text-[11px] font-bold text-[#94A3B8]">Out of stock</span>
              <?php else: ?>
                <form method="post" class="mt-2 space-y-1.5">
                  <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                  <select name="add_to_cart_variant" class="w-full text-xs border border-[#E2E8F0] rounded-lg px-2 py-1.5" required>
                    <?php foreach ($variants as $v): ?>
                      <option value="<?= $v['id'] ?>" <?= $v['stock'] <= 0 ? 'disabled' : '' ?>>
                        <?= htmlspecialchars($v['color']) ?> · <?= htmlspecialchars($v['size']) ?><?= $v['stock'] <= 0 ? ' (out of stock)' : '' ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                  <button type="submit" class="flex items-center justify-center gap-1 w-full py-2 bg-[#0F172A] text-white rounded-lg text-xs font-bold hover:opacity-90">
                    <span class="material-symbols-outlined text-[16px]">add_shopping_cart</span> Add to Cart
                  </button>
                </form>
                <a href="<?= whatsapp_buy_link($p, $settings['whatsapp_number']) ?>" target="_blank" class="mt-1.5 flex items-center justify-center gap-1 w-full py-2 bg-[#25D366] text-white rounded-lg text-xs font-bold hover:opacity-90">
                  <span class="material-symbols-outlined text-[16px]">chat</span> Buy on WhatsApp
                </a>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
        <?php if (empty($products)): ?>
          <p class="col-span-full text-center text-[#64748B] py-12">No products match your filters.</p>
        <?php endif; ?>
      </div>
    </div>
  </div>
</main>

<?php if ($socialLinks): ?>
<div class="max-w-6xl mx-auto px-4 pb-4">
  <?php render_social_bar($socialLinks); ?>
</div>
<?php endif; ?>

<footer class="text-center text-[11px] text-[#94A3B8] py-6">
  &copy; <?= date('Y') ?> <?= htmlspecialchars($settings['business_name']) ?>. Powered by StoreMind.
</footer>

<script>
(function () {
    var toggleBtn = document.getElementById('filterToggle');
    var panel = document.getElementById('filterPanel');
    var icon = document.getElementById('filterToggleIcon');
    if (!toggleBtn || !panel || !icon) return;

    toggleBtn.addEventListener('click', function () {
        panel.classList.toggle('hidden');
        icon.textContent = panel.classList.contains('hidden') ? 'expand_more' : 'expand_less';
    });
})();
</script>

</body>
</html>
