<?php
require_once __DIR__ . '/includes/bootstrap.php';

$user = current_user();

if (!$user) {
    header('Location: ' . BASE_URL . '/shop.php');
    exit;
}

header('Location: ' . ($user['role'] === 'owner' ? BASE_URL . '/dashboard.php' : BASE_URL . '/pos.php'));
exit;
