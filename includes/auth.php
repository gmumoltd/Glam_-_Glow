<?php

const STAFF_IDLE_TIMEOUT_SECONDS = 30 * 60;

function current_user(): ?array
{
    $user = $_SESSION['user'] ?? null;
    if ($user === null) {
        return null;
    }

    $lastActivity = $_SESSION['staff_last_activity'] ?? null;
    if (!is_int($lastActivity) || time() - $lastActivity >= STAFF_IDLE_TIMEOUT_SECONDS) {
        unset($_SESSION['user'], $_SESSION['staff_last_activity']);
        return null;
    }

    return $user;
}

function require_login(): void
{
    if (!current_user()) {
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
    $_SESSION['staff_last_activity'] = time();
}

function require_role(array $roles): void
{
    require_login();
    if (!in_array(current_user()['role'], $roles, true)) {
        header('Location: ' . BASE_URL . '/pos.php');
        exit;
    }
}

function attempt_login(string $email, string $password): bool
{
    $stmt = db()->prepare('SELECT * FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['user'] = ['id' => $user['id'], 'name' => $user['name'], 'email' => $user['email'], 'role' => $user['role']];
        $_SESSION['staff_last_activity'] = time();
        return true;
    }
    return false;
}

function get_store_settings(): array
{
    static $settings = null;
    if ($settings === null) {
        $settings = db()->query('SELECT * FROM store_settings WHERE id = 1')->fetch() ?: [
            'business_name' => BUSINESS_NAME, 'logo_path' => null, 'whatsapp_number' => '254712345678',
        ];
    }
    return $settings;
}
