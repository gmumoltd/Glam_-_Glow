<?php
/** Expects $pageTitle, $activeNav in scope. Requires an authenticated user. */
require_login();
$authUser = current_user();
$storeSettings = get_store_settings();

$navItems = [
    'dashboard'   => ['icon' => 'dashboard',       'label' => 'Dashboard',   'href' => BASE_URL . '/dashboard.php',    'roles' => ['owner']],
    'inventory'   => ['icon' => 'inventory_2',     'label' => 'Inventory',   'href' => BASE_URL . '/inventory.php',    'roles' => ['owner']],
    'receipt-import' => ['icon' => 'receipt_long', 'label' => 'Import Receipt', 'href' => BASE_URL . '/receipt-import.php', 'roles' => ['owner']],
    'pos'         => ['icon' => 'point_of_sale',   'label' => 'Sales & POS', 'href' => BASE_URL . '/pos.php',          'roles' => ['owner', 'salesperson']],
    'calculators' => ['icon' => 'calculate',       'label' => 'Calculators', 'href' => BASE_URL . '/calculators.php',  'roles' => ['owner']],
    'marketing'   => ['icon' => 'campaign',        'label' => 'Marketing',   'href' => BASE_URL . '/marketing.php',    'roles' => ['owner']],
    'sales-feed'  => ['icon' => 'hub',             'label' => 'Sales Feed',  'href' => BASE_URL . '/sales-feed.php',   'roles' => ['owner']],
    'settings'    => ['icon' => 'settings',        'label' => 'Settings',    'href' => BASE_URL . '/settings.php',     'roles' => ['owner']],
];
$navItems = array_filter($navItems, fn ($item) => in_array($authUser['role'], $item['roles'], true));
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title><?= htmlspecialchars($pageTitle ?? 'StoreMind') ?> — StoreMind</title>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@100..900&family=JetBrains+Mono:wght@100..900&family=Manrope:wght@100..900&family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
<script>
tailwind.config = {
    darkMode: "class",
    theme: {
        extend: {
            colors: {
                "on-tertiary": "#ffffff", "inverse-on-surface": "#f3f0f2", "secondary-container": "#6cf8bb",
                "on-primary-container": "#7c839b", "tertiary-container": "#07006c", "background-subtle": "#F8FAFC",
                "error": "#ba1a1a", "warning-amber": "#F59E0B", "on-error-container": "#93000a",
                "slate-gray": "#64748B", "outline": "#76777d", "on-secondary-fixed-variant": "#005236",
                "ai-intelligence": "#8B5CF6", "on-primary": "#ffffff", "on-secondary-container": "#00714d",
                "inverse-surface": "#303032", "surface-tint": "#565e74", "surface-container": "#f0edef",
                "surface-dim": "#dcd9db", "error-container": "#ffdad6", "on-tertiary-container": "#7073ff",
                "on-primary-fixed-variant": "#3f465c", "secondary-fixed-dim": "#4edea3", "outline-variant": "#c6c6cd",
                "inverse-primary": "#bec6e0", "secondary-fixed": "#6ffbbe", "on-surface": "#1b1b1d",
                "primary-fixed-dim": "#bec6e0", "surface-container-highest": "#e4e2e4", "on-surface-variant": "#45464d",
                "tertiary-fixed": "#e1e0ff", "primary": "#0F172A", "on-tertiary-fixed-variant": "#2f2ebe",
                "surface-container-high": "#eae7e9", "surface": "#fcf8fa", "surface-container-low": "#f6f3f5",
                "primary-container": "#131b2e", "on-background": "#1b1b1d", "on-error": "#ffffff",
                "secondary": "#10B981", "primary-fixed": "#dae2fd", "tertiary": "#000000",
                "danger-red": "#EF4444", "on-secondary": "#ffffff", "background": "#fcf8fa",
                "on-primary-fixed": "#131b2e", "tertiary-fixed-dim": "#c0c1ff", "surface-variant": "#e4e2e4",
                "surface-container-lowest": "#ffffff", "on-secondary-fixed": "#002113", "on-tertiary-fixed": "#07006c",
                "surface-bright": "#fcf8fa"
            },
            borderRadius: { DEFAULT: "0.25rem", lg: "0.5rem", xl: "0.75rem", full: "9999px" },
            spacing: { "container-margin": "24px", "card-padding": "20px", "gutter": "16px", "stack-md": "16px", "stack-sm": "8px", "stack-lg": "32px" },
            fontFamily: {
                "label-caps": ["Inter"], "headline-lg": ["Manrope"], "label-numeric": ["JetBrains Mono"],
                "body-lg": ["Inter"], "body-sm": ["Inter"], "headline-md": ["Manrope"], "display-lg": ["Manrope"], "body-md": ["Inter"]
            },
            fontSize: {
                "label-caps": ["12px", {lineHeight: "16px", letterSpacing: "0.05em", fontWeight: "700"}],
                "headline-lg": ["32px", {lineHeight: "40px", fontWeight: "700"}],
                "label-numeric": ["14px", {lineHeight: "20px", fontWeight: "500"}],
                "body-lg": ["18px", {lineHeight: "28px", fontWeight: "400"}],
                "body-sm": ["14px", {lineHeight: "20px", fontWeight: "400"}],
                "headline-md": ["24px", {lineHeight: "32px", fontWeight: "600"}],
                "display-lg": ["48px", {lineHeight: "56px", letterSpacing: "-0.02em", fontWeight: "800"}],
                "body-md": ["16px", {lineHeight: "24px", fontWeight: "400"}]
            }
        }
    }
}
</script>
<style>
    .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }
    .glass-ai { background: rgba(255,255,255,.7); backdrop-filter: blur(12px); border: 1px solid rgba(139,92,246,.2); }
    .no-scrollbar::-webkit-scrollbar { display: none; }
    .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
</style>
<link rel="stylesheet" href="<?= htmlspecialchars(BASE_URL) ?>/glam.css">
</head>
<body class="glam-admin bg-background-subtle text-on-surface font-body-md min-h-screen">

<div id="sidebar-backdrop" class="hidden md:hidden fixed inset-0 bg-black/40 z-30"></div>

<aside id="sidebar" class="flex flex-col fixed left-0 top-0 h-full p-4 gap-stack-md bg-surface border-r border-outline-variant w-[240px] z-40 -translate-x-full md:translate-x-0 transition-transform duration-200">
  <div class="flex items-center gap-3 px-2 mb-4">
    <?php if (!empty($storeSettings['logo_path'])): ?>
      <img src="<?= BASE_URL ?>/uploads/logo/<?= htmlspecialchars($storeSettings['logo_path']) ?>" class="w-10 h-10 rounded-lg object-cover border border-outline-variant">
    <?php else: ?>
      <div class="w-10 h-10 bg-primary rounded-lg flex items-center justify-center">
        <span class="material-symbols-outlined text-on-primary">monitoring</span>
      </div>
    <?php endif; ?>
    <div>
      <h1 class="font-headline-md text-headline-md font-bold text-primary" style="font-size:18px;line-height:24px;"><?= htmlspecialchars($storeSettings['business_name'] ?? 'StoreMind') ?></h1>
      <p class="font-label-caps text-[10px] text-slate-gray tracking-widest">RETAIL INTELLIGENCE</p>
    </div>
  </div>
  <nav class="flex-1 space-y-1">
    <?php foreach ($navItems as $key => $item): $isActive = ($activeNav ?? '') === $key; ?>
      <a class="flex items-center gap-3 px-4 py-3 rounded-lg font-bold transition-all duration-200 <?= $isActive ? 'bg-primary-container text-on-primary-container' : 'text-slate-gray hover:bg-surface-container-high' ?>" href="<?= $item['href'] ?>">
        <span class="material-symbols-outlined"><?= $item['icon'] ?></span>
        <span class="font-label-caps text-label-caps"><?= $item['label'] ?></span>
      </a>
    <?php endforeach; ?>
  </nav>
  <div class="mt-auto pt-4 border-t border-outline-variant space-y-1">
    <a class="flex items-center gap-3 px-4 py-3 text-slate-gray hover:bg-surface-container-high rounded-lg transition-all duration-200" href="<?= BASE_URL ?>/shop.php" target="_blank">
      <span class="material-symbols-outlined">storefront</span>
      <span class="font-label-caps text-label-caps">View Public Shop</span>
    </a>
    <a class="flex items-center gap-3 px-4 py-3 text-slate-gray hover:bg-surface-container-high rounded-lg transition-all duration-200" href="#">
      <span class="material-symbols-outlined">help</span>
      <span class="font-label-caps text-label-caps">Help Center</span>
    </a>
    <a class="flex items-center gap-3 px-4 py-3 text-danger-red hover:bg-error-container rounded-lg transition-all duration-200" href="<?= BASE_URL ?>/logout.php">
      <span class="material-symbols-outlined">logout</span>
      <span class="font-label-caps text-label-caps">Logout</span>
    </a>
  </div>
</aside>

<main class="md:ml-[240px] flex flex-col min-h-screen">
  <header class="flex justify-between items-center w-full px-container-margin h-16 sticky top-0 z-50 bg-surface-container-lowest border-b border-outline-variant">
    <div class="flex items-center gap-4 flex-1">
      <button id="sidebar-toggle" class="md:hidden w-10 h-10 flex items-center justify-center rounded-full hover:bg-surface-container transition-colors text-slate-gray" aria-label="Open menu">
        <span class="material-symbols-outlined">menu</span>
      </button>
      <div class="relative w-full max-w-md hidden sm:block">
        <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-gray">search</span>
        <input class="w-full pl-10 pr-4 py-2 bg-surface-container-low border-none rounded-lg font-body-sm text-body-sm focus:ring-2 focus:ring-primary transition-all" placeholder="Search orders, products, or customers..." type="text"/>
      </div>
    </div>
    <div class="flex items-center gap-4">
      <button class="w-10 h-10 flex items-center justify-center rounded-full hover:bg-surface-container transition-colors text-slate-gray">
        <span class="material-symbols-outlined">notifications</span>
      </button>
      <div class="h-8 w-[1px] bg-outline-variant mx-2"></div>
      <div class="flex items-center gap-3">
        <div class="text-right hidden lg:block">
          <p class="font-label-caps text-[12px] text-primary"><?= htmlspecialchars($authUser['name']) ?></p>
          <p class="font-body-sm text-[10px] text-slate-gray capitalize"><?= htmlspecialchars($authUser['role']) ?></p>
        </div>
        <div class="w-10 h-10 rounded-full border border-outline-variant bg-primary text-on-primary flex items-center justify-center font-bold">
          <?= strtoupper(substr($authUser['name'], 0, 1)) ?>
        </div>
      </div>
    </div>
  </header>
  <div class="p-container-margin flex flex-col gap-stack-lg">

