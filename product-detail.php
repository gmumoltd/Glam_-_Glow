<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/shop_cart.php';

$id = (int) ($_GET['id'] ?? 0);
$stmt = db()->prepare('SELECT p.*, c.name AS category_name FROM products p JOIN categories c ON c.id = p.category_id WHERE p.id = ? AND p.status = "active"');
$stmt->execute([$id]);
$product = $stmt->fetch();

if (!$product) {
    header('Location: ' . BASE_URL . '/shop.php');
    exit;
}

$added = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart_variant'])) {
    $variantId = (int) $_POST['add_to_cart_variant'];
    $qty = max(1, (int) ($_POST['qty'] ?? 1));
    $variant = get_variant($variantId);
    if ($variant && $variant['stock'] >= $qty) {
        shop_cart_add($variantId, $qty);
        $added = true;
    }
}

$settings = get_store_settings();
$socialLinks = get_social_links();

$imgStmt = db()->prepare('SELECT * FROM product_images WHERE product_id = ? ORDER BY sort_order');
$imgStmt->execute([$id]);
$images = $imgStmt->fetchAll();

$variants = get_variants_for_product($id);
$colors = [];
foreach ($variants as $v) {
    $colors[$v['color']][] = $v;
}

$onSale = $product['on_sale'] && $product['discount_percent'] !== null;
$finalPrice = $onSale ? $product['price'] * (1 - $product['discount_percent'] / 100) : $product['price'];
$totalStock = array_sum(array_column($variants, 'stock'));

function whatsapp_detail_link(array $product, float $price, string $waNumber, string $businessName): string
{
    $message = "Hello {$businessName}, I'm interested in {$product['name']} — " . money($price) . '. Is it available?';
    return 'https://wa.me/' . $waNumber . '?text=' . rawurlencode($message);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($product['name']) ?> — <?= htmlspecialchars($settings['business_name']) ?></title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700&family=Manrope:wght@700;800&family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
<style>
.material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }
#lightbox { display: none; }
#lightbox.open { display: flex; }
</style>
</head>
<body class="bg-[#F8FAFC] font-['Inter'] min-h-screen">

<header class="bg-white border-b border-[#E2E8F0] sticky top-0 z-10">
  <div class="max-w-5xl mx-auto px-4 h-16 flex items-center gap-3">
    <a href="<?= BASE_URL ?>/shop.php" class="material-symbols-outlined text-[#0F172A]">arrow_back</a>
    <h1 class="font-['Manrope'] font-bold text-lg text-[#0F172A] flex-1 truncate"><?= htmlspecialchars($settings['business_name']) ?></h1>
    <a href="<?= BASE_URL ?>/login.php" class="hidden sm:flex items-center gap-1 px-3 py-2 text-[#64748B] hover:text-[#0F172A] text-sm font-bold">
      <span class="material-symbols-outlined text-[18px]">login</span> Staff Login
    </a>
    <a href="<?= BASE_URL ?>/cart.php" class="relative flex items-center gap-1 px-3 py-2 bg-[#0F172A] text-white rounded-lg text-sm font-bold">
      <span class="material-symbols-outlined text-[18px]">shopping_cart</span> Cart
      <span class="absolute -top-2 -right-2 bg-[#EF4444] text-white text-[10px] w-5 h-5 rounded-full flex items-center justify-center"><?= shop_cart_count() ?></span>
    </a>
  </div>
  <?php if ($socialLinks): ?>
    <div class="max-w-5xl mx-auto px-4 pb-3">
      <?php render_social_bar($socialLinks, 'small'); ?>
    </div>
  <?php endif; ?>
</header>

<main class="max-w-5xl mx-auto px-4 py-6">
  <?php if ($added): ?>
    <div class="bg-white border border-[#25D366] rounded-xl p-3 mb-4 flex items-center gap-2">
      <span class="material-symbols-outlined text-[#25D366]">check_circle</span>
      <p class="text-sm font-bold text-[#0F172A]">Added to cart.</p>
      <a href="<?= BASE_URL ?>/cart.php" class="ml-auto text-sm font-bold text-[#0F172A] underline">View Cart</a>
    </div>
  <?php endif; ?>

  <nav class="text-xs text-[#64748B] mb-4">
    <a href="<?= BASE_URL ?>/shop.php" class="hover:underline">Shop</a> /
    <a href="<?= BASE_URL ?>/shop.php?category=<?= $product['category_id'] ?>" class="hover:underline"><?= htmlspecialchars($product['category_name']) ?></a> /
    <span><?= htmlspecialchars($product['name']) ?></span>
  </nav>

  <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
    <!-- Gallery -->
    <div>
      <?php
        $mainSrc = !empty($images) ? BASE_URL . '/uploads/products/' . htmlspecialchars($images[0]['image_path']) : null;
      ?>
      <div class="relative bg-white border border-[#E2E8F0] rounded-xl overflow-hidden mb-3">
        <?php if ($mainSrc): ?>
          <img id="mainImage" src="<?= $mainSrc ?>" class="w-full h-96 object-cover cursor-zoom-in" onclick="openLightbox(currentIndex)">
        <?php else: ?>
          <div class="w-full h-96 bg-[#F1F5F9] flex items-center justify-center text-[#94A3B8]">
            <span class="material-symbols-outlined text-[48px]">inventory_2</span>
          </div>
        <?php endif; ?>
        <?php if ($onSale): ?>
          <span class="absolute top-3 left-3 bg-[#EF4444] text-white text-xs font-bold px-2 py-1 rounded-full">-<?= htmlspecialchars($product['discount_percent']) ?>% OFF</span>
        <?php endif; ?>
        <?php if ($mainSrc): ?>
          <button onclick="openLightbox(currentIndex)" class="absolute bottom-3 right-3 bg-white/90 rounded-full p-2" title="Expand">
            <span class="material-symbols-outlined text-[#0F172A]">fullscreen</span>
          </button>
        <?php endif; ?>
      </div>

      <?php if (count($images) > 1 || !empty($product['video_path'])): ?>
        <div class="flex gap-2 overflow-x-auto">
          <?php foreach ($images as $i => $img): ?>
            <img src="<?= BASE_URL ?>/uploads/products/<?= htmlspecialchars($img['image_path']) ?>"
                 class="w-16 h-16 object-cover rounded-lg border-2 <?= $i === 0 ? 'border-[#0F172A]' : 'border-transparent' ?> cursor-pointer flex-shrink-0"
                 onclick="selectImage(<?= $i ?>)">
          <?php endforeach; ?>
          <?php if (!empty($product['video_path'])): ?>
            <div class="w-16 h-16 rounded-lg border-2 border-transparent cursor-pointer flex-shrink-0 bg-black flex items-center justify-center relative" onclick="showVideo()">
              <span class="material-symbols-outlined text-white">play_circle</span>
            </div>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <?php if (!empty($product['video_path'])): ?>
        <div id="videoWrap" class="hidden mt-3 bg-black rounded-xl overflow-hidden">
          <video src="<?= BASE_URL ?>/uploads/videos/<?= htmlspecialchars($product['video_path']) ?>" controls class="w-full max-h-96"></video>
        </div>
      <?php endif; ?>
    </div>

    <!-- Details -->
    <div>
      <p class="text-xs uppercase tracking-wide text-[#64748B] mb-1"><?= htmlspecialchars($product['category_name']) ?><?= $product['collection'] ? ' · ' . htmlspecialchars($product['collection']) : '' ?></p>
      <h2 class="font-['Manrope'] text-2xl font-bold text-[#0F172A] mb-2"><?= htmlspecialchars($product['name']) ?></h2>

      <?php if ($onSale): ?>
        <p class="text-2xl mb-4"><span class="line-through text-[#94A3B8] text-base mr-2"><?= money($product['price']) ?></span><span class="font-bold text-[#EF4444]"><?= money($finalPrice) ?></span></p>
      <?php else: ?>
        <p class="text-2xl font-bold text-[#0F172A] mb-4"><?= money($product['price']) ?></p>
      <?php endif; ?>

      <?php if ($totalStock > 0): ?>
        <form method="post" id="addToCartForm" class="mb-3">
          <input type="hidden" name="add_to_cart_variant" id="variantIdInput" value="">

          <div class="mb-3">
            <label class="text-xs font-bold text-[#64748B] block mb-1">Color</label>
            <div id="colorSwatches" class="flex gap-2"></div>
            <p class="text-xs text-[#64748B] mt-1" id="selectedColorLabel"></p>
          </div>

          <div class="mb-3">
            <label class="text-xs font-bold text-[#64748B] block mb-1">Size</label>
            <div id="sizeButtons" class="flex flex-wrap gap-2"></div>
          </div>

          <p class="text-xs text-[#64748B] mb-3" id="stockLabel"></p>

          <div class="flex gap-2 items-center mb-3">
            <input type="number" name="qty" value="1" min="1" class="w-20 px-3 py-2 border border-[#E2E8F0] rounded-lg text-center">
            <button type="submit" id="addToCartBtn" disabled class="flex-1 py-3 bg-[#0F172A] text-white rounded-lg font-bold disabled:opacity-40 flex items-center justify-center gap-1">
              <span class="material-symbols-outlined text-[18px]">add_shopping_cart</span> Add to Cart
            </button>
          </div>
        </form>
        <a href="#" id="whatsappBtn" target="_blank" class="flex items-center justify-center gap-1 w-full py-3 bg-[#25D366] text-white rounded-lg font-bold">
          <span class="material-symbols-outlined text-[18px]">chat</span> Buy on WhatsApp
        </a>
      <?php else: ?>
        <p class="text-[#EF4444] font-bold mb-3">Out of Stock</p>
      <?php endif; ?>

      <div class="mt-6 border-t border-[#E2E8F0] pt-4">
        <h3 class="font-bold text-sm text-[#0F172A] mb-2">Description</h3>
        <p class="text-sm text-[#334155] whitespace-pre-line"><?= htmlspecialchars($product['description']) ?></p>
      </div>
    </div>
  </div>
</main>

<?php if ($socialLinks): ?>
<div class="max-w-5xl mx-auto px-4 pb-4">
  <?php render_social_bar($socialLinks); ?>
</div>
<?php endif; ?>

<footer class="text-center text-[11px] text-[#94A3B8] py-6">
  &copy; <?= date('Y') ?> <?= htmlspecialchars($settings['business_name']) ?>. Powered by StoreMind.
</footer>

<!-- Lightbox -->
<div id="lightbox" class="fixed inset-0 bg-black/90 z-50 items-center justify-center">
  <button onclick="closeLightbox()" class="absolute top-4 right-4 text-white material-symbols-outlined text-3xl">close</button>
  <button onclick="lightboxMove(-1)" class="absolute left-4 text-white material-symbols-outlined text-3xl">chevron_left</button>
  <img id="lightboxImage" class="max-h-[90vh] max-w-[90vw] object-contain">
  <button onclick="lightboxMove(1)" class="absolute right-4 text-white material-symbols-outlined text-3xl">chevron_right</button>
</div>

<script>
const images = <?= json_encode(array_map(fn($img) => BASE_URL . '/uploads/products/' . $img['image_path'], $images)) ?>;
const colors = <?= json_encode($colors) ?>;
const waNumber = <?= json_encode($settings['whatsapp_number']) ?>;
const businessName = <?= json_encode($settings['business_name']) ?>;
const productName = <?= json_encode($product['name']) ?>;

let currentIndex = 0;
let selectedColor = Object.keys(colors)[0] || null;
let selectedVariant = null;

function selectImage(i) {
    currentIndex = i;
    document.getElementById('mainImage').src = images[i];
    document.querySelectorAll('.flex.gap-2.overflow-x-auto img').forEach((el, idx) => {
        el.classList.toggle('border-[#0F172A]', idx === i);
        el.classList.toggle('border-transparent', idx !== i);
    });
    document.getElementById('videoWrap')?.classList.add('hidden');
}

function showVideo() {
    document.getElementById('videoWrap')?.classList.remove('hidden');
}

function openLightbox(i) {
    if (!images.length) return;
    currentIndex = i;
    document.getElementById('lightboxImage').src = images[i];
    document.getElementById('lightbox').classList.add('open');
}
function closeLightbox() {
    document.getElementById('lightbox').classList.remove('open');
}
function lightboxMove(dir) {
    currentIndex = (currentIndex + dir + images.length) % images.length;
    document.getElementById('lightboxImage').src = images[currentIndex];
}

function renderColors() {
    const box = document.getElementById('colorSwatches');
    box.innerHTML = '';
    Object.keys(colors).forEach(color => {
        const el = document.createElement('button');
        el.type = 'button';
        el.className = 'w-8 h-8 rounded-full border-2 ' + (color === selectedColor ? 'border-[#0F172A]' : 'border-[#E2E8F0]');
        el.style.background = color.toLowerCase();
        el.title = color;
        el.onclick = () => selectColor(color);
        box.appendChild(el);
    });
    document.getElementById('selectedColorLabel').textContent = selectedColor || '';
}

function selectColor(color) {
    selectedColor = color;
    selectedVariant = null;
    document.getElementById('addToCartBtn').disabled = true;
    document.getElementById('stockLabel').textContent = '';
    renderColors();
    renderSizes();
    updateWhatsapp();
}

function renderSizes() {
    const box = document.getElementById('sizeButtons');
    box.innerHTML = '';
    (colors[selectedColor] || []).forEach(v => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'px-3 py-1.5 border rounded-lg text-sm ' + (v.stock <= 0 ? 'border-[#E2E8F0] text-[#94A3B8] line-through' : 'border-[#E2E8F0] text-[#0F172A]');
        btn.textContent = v.size;
        btn.disabled = v.stock <= 0;
        btn.onclick = () => selectVariant(v, btn);
        box.appendChild(btn);
    });
}

function selectVariant(v, btn) {
    selectedVariant = v;
    document.querySelectorAll('#sizeButtons button').forEach(b => b.classList.remove('bg-[#0F172A]', 'text-white'));
    btn.classList.add('bg-[#0F172A]', 'text-white');
    document.getElementById('variantIdInput').value = v.id;
    document.getElementById('addToCartBtn').disabled = false;
    document.getElementById('stockLabel').textContent = v.stock + ' in stock';
    updateWhatsapp();
}

function updateWhatsapp() {
    let msg = 'Hello ' + businessName + ', I want ' + productName;
    if (selectedColor) msg += ', Color: ' + selectedColor;
    if (selectedVariant) msg += ', Size: ' + selectedVariant.size;
    const btn = document.getElementById('whatsappBtn');
    if (btn) btn.href = 'https://wa.me/' + waNumber + '?text=' + encodeURIComponent(msg);
}

if (Object.keys(colors).length) {
    renderColors();
    renderSizes();
}
updateWhatsapp();
</script>

</body>
</html>
