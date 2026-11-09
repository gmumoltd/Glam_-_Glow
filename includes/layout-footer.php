  </div>
</main>
<?php if (current_user()['role'] === 'owner'): ?>
<nav class="glam-dock" aria-label="Staff shortcuts">
  <a href="<?= BASE_URL ?>/dashboard.php" <?= basename($_SERVER['SCRIPT_NAME']) === 'dashboard.php' ? 'aria-current="page"' : '' ?>><span class="material-symbols-outlined">home</span>Home</a>
  <a href="<?= BASE_URL ?>/inventory.php" <?= basename($_SERVER['SCRIPT_NAME']) === 'inventory.php' ? 'aria-current="page"' : '' ?>><span class="material-symbols-outlined">inventory_2</span>Stock</a>
  <a class="glam-dock-add" href="<?= BASE_URL ?>/product-form.php" <?= basename($_SERVER['SCRIPT_NAME']) === 'product-form.php' ? 'aria-current="page"' : '' ?>><span class="material-symbols-outlined">add_circle</span>Add product</a>
  <a href="<?= BASE_URL ?>/sales-feed.php" <?= basename($_SERVER['SCRIPT_NAME']) === 'sales-feed.php' ? 'aria-current="page"' : '' ?>><span class="material-symbols-outlined">receipt_long</span>Sales</a>
  <a href="<?= BASE_URL ?>/settings.php" <?= basename($_SERVER['SCRIPT_NAME']) === 'settings.php' ? 'aria-current="page"' : '' ?>><span class="material-symbols-outlined">settings</span>Settings</a>
</nav>
<?php endif; ?>

<script>
(function () {
    var sidebar = document.getElementById('sidebar');
    var backdrop = document.getElementById('sidebar-backdrop');
    var toggle = document.getElementById('sidebar-toggle');

    if (!sidebar || !backdrop || !toggle) {
        return;
    }

    function openSidebar() {
        sidebar.classList.remove('-translate-x-full');
        backdrop.classList.remove('hidden');
    }
    function closeSidebar() {
        sidebar.classList.add('-translate-x-full');
        backdrop.classList.add('hidden');
    }

    toggle.addEventListener('click', openSidebar);
    backdrop.addEventListener('click', closeSidebar);
    sidebar.querySelectorAll('a').forEach(function (link) {
        link.addEventListener('click', closeSidebar);
    });
})();
</script>
</body>
</html>
