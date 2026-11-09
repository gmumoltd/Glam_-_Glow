<?php
require_once __DIR__ . '/../config/database.php';

$pdo = db();

$users = [
    ['name' => 'Owner', 'email' => 'owner@valmier.co.ke', 'password' => 'Owner@2026', 'role' => 'owner'],
    ['name' => 'Cashier', 'email' => 'cashier@valmier.co.ke', 'password' => 'Cashier@2026', 'role' => 'salesperson'],
];

foreach ($users as $u) {
    $exists = $pdo->prepare('SELECT id FROM users WHERE email = ?');
    $exists->execute([$u['email']]);
    if ($exists->fetch()) {
        echo "Skipped (exists): {$u['email']}\n";
        continue;
    }
    $stmt = $pdo->prepare('INSERT INTO users (name, email, password_hash, role) VALUES (?,?,?,?)');
    $stmt->execute([$u['name'], $u['email'], password_hash($u['password'], PASSWORD_DEFAULT), $u['role']]);
    echo "Created: {$u['email']} / {$u['password']} ({$u['role']})\n";
}
