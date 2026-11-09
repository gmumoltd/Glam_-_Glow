<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_role(['owner']);

$id = (int) ($_GET['id'] ?? 0);
$stmt = db()->prepare('SELECT p.*, c.name AS category_name FROM products p JOIN categories c ON c.id = p.category_id WHERE p.id = ?');
$stmt->execute([$id]);
$product = $stmt->fetch();

if (!$product) {
    header('Location: ' . BASE_URL . '/inventory.php');
    exit;
}

$imgStmt = db()->prepare('SELECT * FROM product_images WHERE product_id = ? ORDER BY sort_order');
$imgStmt->execute([$id]);
$images = $imgStmt->fetchAll();
$variants = get_variants_for_product($id);
$totalStock = array_sum(array_column($variants, 'stock'));

$pageTitle = $product['name'];
$activeNav = 'inventory';
require __DIR__ . '/includes/layout-header.php';
?>

<div class="flex items-center gap-2">
  <a href="<?= BASE_URL ?>/inventory.php" class="text-slate-gray material-symbols-outlined">arrow_back</a>
  <h2 class="font-headline-lg text-headline-lg text-primary"><?= htmlspecialchars($product['name']) ?></h2>
  <?php if ($product['on_sale']): ?>
    <span class="bg-danger-red text-white text-[11px] font-bold px-2 py-0.5 rounded-full">-<?= htmlspecialchars($product['discount_percent']) ?>% ON SALE</span>
  <?php endif; ?>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-gutter">
  <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-card-padding">
    <?php if (empty($images)): ?>
      <div class="w-full h-80 bg-surface-container-high rounded-lg flex items-center justify-center text-slate-gray">
        <span class="material-symbols-outlined text-[48px]">inventory_2</span>
      </div>
    <?php else: ?>
      <div class="relative">
        <div id="carouselTrack" class="overflow-hidden rounded-lg">
          <div id="carouselSlides" class="flex transition-transform duration-300">
            <?php foreach ($images as $img): ?>
              <img src="<?= BASE_URL ?>/uploads/products/<?= htmlspecialchars($img['image_path']) ?>" class="w-full flex-shrink-0 h-80 object-cover">
            <?php endforeach; ?>
          </div>
        </div>
        <?php if (count($images) > 1): ?>
          <button onclick="carouselMove(-1)" class="absolute left-2 top-1/2 -translate-y-1/2 bg-white/80 hover:bg-white rounded-full w-9 h-9 flex items-center justify-center shadow">
            <span class="material-symbols-outlined">chevron_left</span>
          </button>
          <button onclick="carouselMove(1)" class="absolute right-2 top-1/2 -translate-y-1/2 bg-white/80 hover:bg-white rounded-full w-9 h-9 flex items-center justify-center shadow">
            <span class="material-symbols-outlined">chevron_right</span>
          </button>
          <div class="flex justify-center gap-1.5 mt-3" id="carouselDots">
            <?php foreach ($images as $i => $img): ?>
              <button onclick="carouselGoTo(<?= $i ?>)" class="carousel-dot w-2 h-2 rounded-full <?= $i === 0 ? 'bg-primary' : 'bg-outline-variant' ?>" data-index="<?= $i ?>"></button>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>
    <a href="<?= BASE_URL ?>/product-form.php?id=<?= $id ?>" class="mt-4 block text-center w-full px-4 py-2 bg-primary text-on-primary rounded-lg font-label-caps text-label-caps">Edit Product</a>
  </div>

  <div class="space-y-stack-lg">
    <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-card-padding">
      <p class="font-label-caps text-label-caps text-slate-gray mb-1"><?= htmlspecialchars($product['category_name']) ?><?= $product['collection'] ? ' · ' . htmlspecialchars($product['collection']) : '' ?></p>
      <?php if ($product['on_sale'] && $product['discount_percent']): ?>
        <p class="text-lg"><span class="line-through text-slate-gray mr-2"><?= money($product['price']) ?></span><span class="font-bold text-danger-red"><?= money($product['price'] * (1 - $product['discount_percent'] / 100)) ?></span></p>
      <?php else: ?>
        <p class="font-headline-md text-headline-md font-bold text-primary"><?= money($product['price']) ?></p>
      <?php endif; ?>
      <p class="font-label-numeric text-label-numeric text-slate-gray mt-1">SKU: <?= htmlspecialchars($product['sku']) ?></p>
      <p class="font-body-sm mt-3"><?= nl2br(htmlspecialchars($product['description'])) ?></p>
    </div>

    <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-card-padding">
      <h4 class="font-headline-md text-[16px] font-bold text-primary mb-3">Variants (<?= $totalStock ?> total units)</h4>
      <table class="w-full">
        <thead><tr class="text-left border-b border-outline-variant"><th class="py-2 font-label-caps text-label-caps text-slate-gray">Color</th><th class="py-2 font-label-caps text-label-caps text-slate-gray">Size</th><th class="py-2 font-label-caps text-label-caps text-slate-gray text-right">Stock</th></tr></thead>
        <tbody class="divide-y divide-outline-variant">
          <?php foreach ($variants as $v): ?>
            <tr>
              <td class="py-2 font-body-sm"><?= htmlspecialchars($v['color']) ?></td>
              <td class="py-2 font-body-sm"><?= htmlspecialchars($v['size']) ?></td>
              <td class="py-2 font-label-numeric text-label-numeric text-right <?= $v['stock'] <= $product['reorder_level'] ? 'text-danger-red font-bold' : '' ?>"><?= $v['stock'] ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
let carouselIndex = 0;
const slideCount = <?= count($images) ?>;
function carouselRender() {
    document.getElementById('carouselSlides').style.transform = `translateX(-${carouselIndex * 100}%)`;
    document.querySelectorAll('.carousel-dot').forEach((d, i) => {
        d.classList.toggle('bg-primary', i === carouselIndex);
        d.classList.toggle('bg-outline-variant', i !== carouselIndex);
    });
}
function carouselMove(dir) {
    carouselIndex = (carouselIndex + dir + slideCount) % slideCount;
    carouselRender();
}
function carouselGoTo(i) {
    carouselIndex = i;
    carouselRender();
}
</script>

<?php require __DIR__ . '/includes/layout-footer.php'; ?>
