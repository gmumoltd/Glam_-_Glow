<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (current_user()) {
    header('Location: ' . (current_user()['role'] === 'owner' ? BASE_URL . '/dashboard.php' : BASE_URL . '/pos.php'));
    exit;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (attempt_login($email, $password)) {
        header('Location: ' . (current_user()['role'] === 'owner' ? BASE_URL . '/dashboard.php' : BASE_URL . '/pos.php'));
        exit;
    }
    $error = 'Invalid email or password.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login — StoreMind</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700&family=Manrope:wght@700;800&family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
<style>.material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }</style>
</head>
<body class="bg-[#F8FAFC] min-h-screen flex items-center justify-center font-['Inter']">
  <div class="w-full max-w-sm bg-white border border-[#E2E8F0] rounded-xl p-8 shadow-sm">
    <div class="flex items-center gap-3 mb-6">
      <div class="w-10 h-10 bg-[#0F172A] rounded-lg flex items-center justify-center">
        <span class="material-symbols-outlined text-white">monitoring</span>
      </div>
      <div>
        <h1 class="font-['Manrope'] font-bold text-lg text-[#0F172A]">StoreMind</h1>
        <p class="text-[10px] text-[#64748B] tracking-widest font-bold">RETAIL INTELLIGENCE</p>
      </div>
    </div>

    <?php if ($error): ?>
      <div class="bg-red-50 text-red-700 text-sm rounded-lg px-3 py-2 mb-4"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post" class="space-y-4">
      <div>
        <label class="text-xs font-bold text-[#64748B] block mb-1">Email</label>
        <input type="email" name="email" required class="w-full px-3 py-2 border border-[#E2E8F0] rounded-lg focus:ring-2 focus:ring-[#0F172A] outline-none" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
      </div>
      <div>
        <label class="text-xs font-bold text-[#64748B] block mb-1">Password</label>
        <input type="password" name="password" required class="w-full px-3 py-2 border border-[#E2E8F0] rounded-lg focus:ring-2 focus:ring-[#0F172A] outline-none">
      </div>
      <button type="submit" class="w-full py-2.5 bg-[#0F172A] text-white rounded-lg font-bold hover:opacity-90">Sign In</button>
    </form>

    <a href="<?= BASE_URL ?>/shop.php" class="mt-6 flex items-center justify-center gap-1 text-sm font-bold text-[#64748B] hover:text-[#0F172A]">
      <span class="material-symbols-outlined text-[18px]">arrow_back</span> Back to Shopping
    </a>
  </div>
</body>
</html>
