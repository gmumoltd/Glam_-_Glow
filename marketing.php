<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_role(['owner']);

if (isset($_GET['delete'])) {
    db()->prepare('DELETE FROM campaigns WHERE id = ?')->execute([(int) $_GET['delete']]);
    header('Location: ' . BASE_URL . '/marketing.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $type = in_array($_POST['type'] ?? '', ['coupon', 'sms', 'whatsapp'], true) ? $_POST['type'] : 'coupon';
    $code = strtoupper(trim($_POST['code'] ?? ''));
    $discountPercent = $_POST['discount_percent'] !== '' ? (float) $_POST['discount_percent'] : null;
    $message = trim($_POST['message'] ?? '');

    if ($name !== '') {
        $stmt = db()->prepare('INSERT INTO campaigns (name, type, code, discount_percent, message, status) VALUES (?,?,?,?,?,"active")');
        $stmt->execute([$name, $type, $code ?: null, $discountPercent, $message]);
    }
    header('Location: ' . BASE_URL . '/marketing.php');
    exit;
}

$campaigns = db()->query('SELECT * FROM campaigns ORDER BY created_at DESC')->fetchAll();

$pageTitle = 'Marketing Campaign Center';
$activeNav = 'marketing';
require __DIR__ . '/includes/layout-header.php';
?>

<h2 class="font-headline-lg text-headline-lg text-primary">Marketing Campaign Center</h2>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-gutter items-start">
  <div class="lg:col-span-2 bg-surface-container-lowest border border-outline-variant rounded-xl overflow-hidden">
    <div class="p-4 border-b border-outline-variant">
      <h4 class="font-headline-md text-[16px] font-bold text-primary">Active Campaigns</h4>
    </div>
    <table class="w-full">
      <thead>
        <tr class="bg-slate-gray/5 text-left border-b border-outline-variant">
          <th class="p-4 font-label-caps text-label-caps text-slate-gray">Campaign</th>
          <th class="p-4 font-label-caps text-label-caps text-slate-gray">Type</th>
          <th class="p-4 font-label-caps text-label-caps text-slate-gray">Code</th>
          <th class="p-4 font-label-caps text-label-caps text-slate-gray">Discount</th>
          <th class="p-4 font-label-caps text-label-caps text-slate-gray text-right">Actions</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-outline-variant">
        <?php foreach ($campaigns as $c): ?>
          <tr class="hover:bg-surface-container-low">
            <td class="p-4 font-body-sm font-bold text-primary"><?= htmlspecialchars($c['name']) ?></td>
            <td class="p-4">
              <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-bold bg-tertiary-fixed text-on-tertiary-fixed-variant capitalize"><?= htmlspecialchars($c['type']) ?></span>
            </td>
            <td class="p-4 font-label-numeric text-label-numeric"><?= htmlspecialchars($c['code'] ?? '—') ?></td>
            <td class="p-4 font-label-numeric text-label-numeric"><?= $c['discount_percent'] !== null ? $c['discount_percent'] . '%' : '—' ?></td>
            <td class="p-4 text-right">
              <a href="<?= BASE_URL ?>/marketing.php?delete=<?= $c['id'] ?>" onclick="return confirm('Delete this campaign?')" class="text-slate-gray hover:text-danger-red material-symbols-outlined">delete</a>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (empty($campaigns)): ?>
          <tr><td colspan="5" class="p-8 text-center text-slate-gray font-body-sm">No campaigns yet — create one to the right.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-card-padding">
    <h4 class="font-headline-md text-[16px] font-bold text-primary mb-3">New Campaign</h4>
    <form method="post" class="space-y-3">
      <div>
        <label class="font-label-caps text-label-caps text-slate-gray block mb-1">Campaign Name</label>
        <input name="name" required class="w-full px-3 py-2 border border-outline-variant rounded-lg text-sm">
      </div>
      <div>
        <label class="font-label-caps text-label-caps text-slate-gray block mb-1">Type</label>
        <select name="type" class="w-full px-3 py-2 border border-outline-variant rounded-lg text-sm">
          <option value="coupon">Discount Coupon</option>
          <option value="sms">Bulk SMS</option>
          <option value="whatsapp">WhatsApp Broadcast</option>
        </select>
      </div>
      <div>
        <label class="font-label-caps text-label-caps text-slate-gray block mb-1">Coupon Code (optional)</label>
        <input name="code" class="w-full px-3 py-2 border border-outline-variant rounded-lg text-sm font-label-numeric" placeholder="TENIKA20">
      </div>
      <div>
        <label class="font-label-caps text-label-caps text-slate-gray block mb-1">Discount % (optional)</label>
        <input type="number" name="discount_percent" step="0.1" class="w-full px-3 py-2 border border-outline-variant rounded-lg text-sm">
      </div>
      <div>
        <label class="font-label-caps text-label-caps text-slate-gray block mb-1">Message / Notes</label>
        <textarea name="message" rows="3" class="w-full px-3 py-2 border border-outline-variant rounded-lg text-sm"></textarea>
      </div>
      <button type="submit" class="w-full px-4 py-2 bg-primary text-on-primary rounded-lg font-label-caps text-label-caps hover:opacity-90">Create Campaign</button>
    </form>
  </div>
</div>

<?php require __DIR__ . '/includes/layout-footer.php'; ?>
