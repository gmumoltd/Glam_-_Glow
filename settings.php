<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_role(['owner']);

$settings = get_store_settings();
$errors = [];
$offerProducts = db()->query("SELECT id, name FROM products WHERE status = 'active' ORDER BY name")->fetchAll();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_featured'])) {
    if (!hash_equals($_SESSION['featured_csrf'] ?? '', (string) ($_POST['featured_csrf'] ?? '')) || empty($_SESSION['featured_csrf'])) {
        http_response_code(403);
        exit('Refresh Settings and try again.');
    }
    $featuredId = (int) ($_POST['featured_product_id'] ?? 0);
    if ($featuredId && !in_array($featuredId, array_map('intval', array_column($offerProducts, 'id')), true)) {
        http_response_code(400);
        exit('Select an active product.');
    }
    db()->prepare('UPDATE store_settings SET featured_product_id = ? WHERE id = 1')->execute([$featuredId ?: null]);
    header('Location: ' . BASE_URL . '/settings.php?featured_saved=1#featured');
    exit;
}
$_SESSION['featured_csrf'] ??= bin2hex(random_bytes(32));
$saved = false;
$socialSaved = false;
$categoryError = null;
$categorySaved = false;

if (isset($_GET['delete_category'])) {
    try {
        db()->prepare('DELETE FROM categories WHERE id = ?')->execute([(int) $_GET['delete_category']]);
        header('Location: ' . BASE_URL . '/settings.php?category_deleted=1');
    } catch (PDOException $e) {
        header('Location: ' . BASE_URL . '/settings.php?category_error=in_use');
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_category'])) {
    $categoryName = trim($_POST['category_name'] ?? '');
    if ($categoryName === '') {
        $categoryError = 'Category name is required.';
    } else {
        $slug = slugify($categoryName);
        $baseSlug = $slug;
        $suffix = 2;
        $slugCheck = db()->prepare('SELECT id FROM categories WHERE slug = ?');
        while (true) {
            $slugCheck->execute([$slug]);
            if (!$slugCheck->fetch()) break;
            $slug = $baseSlug . '-' . $suffix++;
        }
        $nextOrder = (int) db()->query('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM categories')->fetchColumn();
        db()->prepare('INSERT INTO categories (name, slug, sort_order) VALUES (?,?,?)')->execute([$categoryName, $slug, $nextOrder]);
        $categorySaved = true;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_social_links'])) {
    $platforms = $_POST['social_platform'] ?? [];
    $urls = $_POST['social_url'] ?? [];
    $pdo = db();
    $pdo->beginTransaction();
    $pdo->exec('DELETE FROM social_links');
    $stmt = $pdo->prepare('INSERT INTO social_links (platform, url, sort_order) VALUES (?,?,?)');
    $order = 0;
    foreach ($platforms as $i => $platform) {
        $platform = trim($platform);
        $url = trim($urls[$i] ?? '');
        if ($url !== '' && !preg_match('#^https?://#i', $url)) {
            $url = 'https://' . ltrim($url, '/');
        }
        if ($platform !== '' && $url !== '') {
            $stmt->execute([$platform, $url, $order++]);
        }
    }
    $pdo->commit();
    $socialSaved = true;
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $businessName = trim($_POST['business_name'] ?? '');
    $whatsappNumber = trim($_POST['whatsapp_number'] ?? '');

    if ($businessName === '') $errors[] = 'Business name is required.';

    if (empty($errors)) {
        $logoPath = $settings['logo_path'];

        if (!empty($_FILES['logo']['name']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'svg'], true)) {
                $newName = 'logo-' . bin2hex(random_bytes(4)) . '.' . $ext;
                move_uploaded_file($_FILES['logo']['tmp_name'], __DIR__ . '/uploads/logo/' . $newName);
                if ($logoPath && is_file(__DIR__ . '/uploads/logo/' . $logoPath)) {
                    @unlink(__DIR__ . '/uploads/logo/' . $logoPath);
                }
                $logoPath = $newName;
            } else {
                $errors[] = 'Logo must be jpg, png, webp, or svg.';
            }
        }

        if (empty($errors)) {
            $stmt = db()->prepare('UPDATE store_settings SET business_name = ?, logo_path = ?, whatsapp_number = ? WHERE id = 1');
            $stmt->execute([$businessName, $logoPath, $whatsappNumber]);
            $settings = get_store_settings();
            $saved = true;
        }
    }
}

$socialLinks = get_social_links();
$categories = get_categories();

$pageTitle = 'Settings';
$activeNav = 'settings';
require __DIR__ . '/includes/layout-header.php';
?>

<h2 class="font-headline-lg text-headline-lg text-primary">Business Settings</h2>
<form id="featured" method="post" class="bg-white rounded-xl p-4 space-y-3 max-w-xl">
  <input type="hidden" name="featured_csrf" value="<?= htmlspecialchars($_SESSION['featured_csrf']) ?>">
  <label for="featured_product_id" class="block font-bold">Storefront featured offer</label>
  <p>Choose the product that fills the opening banner. Customers tap the offer to see details and choose a size. It appears while stock is available.</p>
  <select name="featured_product_id" id="featured_product_id" class="w-full rounded-lg border p-3">
    <option value="0">Show collection introduction</option>
    <?php foreach ($offerProducts as $offer): ?>
      <option value="<?= (int) $offer['id'] ?>" <?= (int) ($settings['featured_product_id'] ?? 0) === (int) $offer['id'] ? 'selected' : '' ?>><?= htmlspecialchars($offer['name']) ?></option>
    <?php endforeach; ?>
  </select>
  <button name="save_featured" value="1" class="glam-button">Save storefront offer</button>
  <a href="<?= BASE_URL ?>/shop.php" class="underline ml-2">Preview storefront</a>
  <details>
    <summary class="cursor-pointer font-bold">Preview the saved storefront at phone width</summary>
    <iframe title="Phone preview of the storefront" src="<?= BASE_URL ?>/shop.php" loading="lazy" class="block w-full max-w-[390px] h-[640px] border rounded-2xl mt-3"></iframe>
  </details>
  <?php if (isset($_GET['featured_saved'])): ?><p role="status">Featured offer saved.</p><?php endif; ?>
</form>

<?php if ($saved): ?>
  <div class="bg-secondary-container/30 border border-secondary/30 rounded-xl p-4 flex items-center gap-3">
    <span class="material-symbols-outlined text-secondary">check_circle</span>
    <p class="font-body-md font-bold text-on-secondary-container">Settings updated.</p>
  </div>
<?php endif; ?>
<?php foreach ($errors as $err): ?>
  <div class="bg-error-container text-on-error-container p-3 rounded-lg font-body-sm"><?= htmlspecialchars($err) ?></div>
<?php endforeach; ?>

<form method="post" enctype="multipart/form-data" class="max-w-xl bg-surface-container-lowest border border-outline-variant rounded-xl p-card-padding space-y-4">
  <div>
    <label class="font-label-caps text-label-caps text-slate-gray block mb-1">Business Name</label>
    <input name="business_name" value="<?= htmlspecialchars($settings['business_name']) ?>" class="w-full px-4 py-2 border border-outline-variant rounded-lg focus:ring-2 focus:ring-primary" required>
  </div>
  <div>
    <label class="font-label-caps text-label-caps text-slate-gray block mb-1">WhatsApp Number (international format, no +)</label>
    <input name="whatsapp_number" value="<?= htmlspecialchars($settings['whatsapp_number']) ?>" placeholder="254712345678" class="w-full px-4 py-2 border border-outline-variant rounded-lg focus:ring-2 focus:ring-primary">
    <p class="text-[11px] text-slate-gray mt-1">Used for the "Buy on WhatsApp" button on the public shop page.</p>
  </div>
  <div>
    <label class="font-label-caps text-label-caps text-slate-gray block mb-1">Logo</label>
    <?php if (!empty($settings['logo_path'])): ?>
      <img src="<?= BASE_URL ?>/uploads/logo/<?= htmlspecialchars($settings['logo_path']) ?>" class="w-16 h-16 object-cover rounded-lg border border-outline-variant mb-2">
    <?php endif; ?>
    <input type="file" name="logo" accept="image/*" class="w-full text-body-sm">
  </div>
  <button type="submit" class="px-6 py-3 bg-primary text-on-primary rounded-lg font-label-caps text-label-caps hover:opacity-90">Save Settings</button>
</form>

<div class="max-w-xl bg-surface-container-lowest border border-outline-variant rounded-xl p-card-padding mt-6">
  <h4 class="font-headline-md text-[16px] font-bold text-primary mb-1">Product Categories</h4>
  <p class="text-[11px] text-slate-gray mb-3">Used to organize Inventory and the category filter on the public shop.</p>

  <?php if ($categorySaved): ?>
    <div class="bg-secondary-container/30 border border-secondary/30 rounded-xl p-3 flex items-center gap-2 mb-3">
      <span class="material-symbols-outlined text-secondary text-[18px]">check_circle</span>
      <p class="font-body-sm font-bold text-on-secondary-container">Category added.</p>
    </div>
  <?php endif; ?>
  <?php if (isset($_GET['category_deleted'])): ?>
    <div class="bg-secondary-container/30 border border-secondary/30 rounded-xl p-3 flex items-center gap-2 mb-3">
      <span class="material-symbols-outlined text-secondary text-[18px]">check_circle</span>
      <p class="font-body-sm font-bold text-on-secondary-container">Category deleted.</p>
    </div>
  <?php endif; ?>
  <?php if ($categoryError || (isset($_GET['category_error']) && $_GET['category_error'] === 'in_use')): ?>
    <div class="bg-error-container text-on-error-container p-3 rounded-lg font-body-sm mb-3">
      <?= htmlspecialchars($categoryError ?: 'Can\'t delete this category — it still has products assigned to it. Move or delete those products first.') ?>
    </div>
  <?php endif; ?>

  <ul class="divide-y divide-outline-variant mb-4">
    <?php foreach ($categories as $cat): ?>
      <li class="flex items-center justify-between py-2">
        <span class="font-body-sm text-primary"><?= htmlspecialchars($cat['name']) ?></span>
        <a href="<?= BASE_URL ?>/settings.php?delete_category=<?= $cat['id'] ?>" onclick="return confirm('Delete category &quot;<?= htmlspecialchars(addslashes($cat['name'])) ?>&quot;?')" class="p-1 text-slate-gray hover:text-danger-red transition-colors">
          <span class="material-symbols-outlined text-[18px]">delete</span>
        </a>
      </li>
    <?php endforeach; ?>
    <?php if (empty($categories)): ?>
      <li class="py-2 text-body-sm text-slate-gray">No categories yet.</li>
    <?php endif; ?>
  </ul>

  <form method="post" class="flex gap-2">
    <input type="hidden" name="add_category" value="1">
    <input name="category_name" placeholder="e.g. Sportswear" class="flex-1 px-4 py-2 border border-outline-variant rounded-lg focus:ring-2 focus:ring-primary" required>
    <button type="submit" class="px-4 py-2 bg-primary text-on-primary rounded-lg font-label-caps text-label-caps hover:opacity-90 flex items-center gap-1 whitespace-nowrap">
      <span class="material-symbols-outlined text-[18px]">add</span> Add
    </button>
  </form>
</div>

<div class="max-w-xl bg-surface-container-lowest border border-outline-variant rounded-xl p-card-padding mt-6">
  <h4 class="font-headline-md text-[16px] font-bold text-primary mb-1">Social &amp; Marketplace Links</h4>
  <p class="text-[11px] text-slate-gray mb-3">Shown as quick links at the top and bottom of your public shop page — e.g. TikTok Shop, Instagram, Facebook, WhatsApp chat.</p>

  <?php if ($socialSaved): ?>
    <div class="bg-secondary-container/30 border border-secondary/30 rounded-xl p-3 flex items-center gap-2 mb-3">
      <span class="material-symbols-outlined text-secondary text-[18px]">check_circle</span>
      <p class="font-body-sm font-bold text-on-secondary-container">Links updated.</p>
    </div>
  <?php endif; ?>

  <form method="post">
    <input type="hidden" name="save_social_links" value="1">
    <table class="w-full mb-3" id="socialTable">
      <thead><tr class="text-left"><th class="font-label-caps text-label-caps text-slate-gray pb-2">Platform</th><th class="font-label-caps text-label-caps text-slate-gray pb-2">Link (URL)</th><th></th></tr></thead>
      <tbody>
        <?php if ($socialLinks): foreach ($socialLinks as $link): ?>
          <tr>
            <td class="pr-2 pb-2"><input name="social_platform[]" value="<?= htmlspecialchars($link['platform']) ?>" placeholder="Instagram" class="w-full px-3 py-1.5 border border-outline-variant rounded-lg"></td>
            <td class="pr-2 pb-2"><input name="social_url[]" value="<?= htmlspecialchars($link['url']) ?>" placeholder="https://instagram.com/yourshop" class="w-full px-3 py-1.5 border border-outline-variant rounded-lg"></td>
            <td class="pb-2"><button type="button" onclick="this.closest('tr').remove()" class="text-danger-red material-symbols-outlined">close</button></td>
          </tr>
        <?php endforeach; else: ?>
          <tr>
            <td class="pr-2 pb-2"><input name="social_platform[]" placeholder="Instagram" class="w-full px-3 py-1.5 border border-outline-variant rounded-lg"></td>
            <td class="pr-2 pb-2"><input name="social_url[]" placeholder="https://instagram.com/yourshop" class="w-full px-3 py-1.5 border border-outline-variant rounded-lg"></td>
            <td class="pb-2"><button type="button" onclick="this.closest('tr').remove()" class="text-danger-red material-symbols-outlined">close</button></td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
    <button type="button" onclick="addSocialRow()" class="mb-3 font-label-caps text-label-caps text-primary flex items-center gap-1">
      <span class="material-symbols-outlined text-[18px]">add</span> Add Platform
    </button>
    <button type="submit" class="block px-6 py-3 bg-primary text-on-primary rounded-lg font-label-caps text-label-caps hover:opacity-90">Save Links</button>
  </form>
</div>

<script>
function addSocialRow() {
    const tbody = document.querySelector('#socialTable tbody');
    const row = document.createElement('tr');
    row.innerHTML = `
        <td class="pr-2 pb-2"><input name="social_platform[]" placeholder="Instagram" class="w-full px-3 py-1.5 border border-outline-variant rounded-lg"></td>
        <td class="pr-2 pb-2"><input name="social_url[]" placeholder="https://instagram.com/yourshop" class="w-full px-3 py-1.5 border border-outline-variant rounded-lg"></td>
        <td class="pb-2"><button type="button" onclick="this.closest('tr').remove()" class="text-danger-red material-symbols-outlined">close</button></td>
    `;
    tbody.appendChild(row);
}
</script>

<?php require __DIR__ . '/includes/layout-footer.php'; ?>
