<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

const PASSWORD_BCRYPT_COST = 12;

function session_start_safe(): void {
    if (session_status() === PHP_SESSION_NONE) {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
            || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => $https,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

function is_logged_in(): bool {
    session_start_safe();
    return current_user() !== null;
}

function current_user(): ?array {
    static $cached_user = null;
    if ($cached_user !== null) {
        return $cached_user;
    }
    session_start_safe();
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    $stmt = db()->prepare('SELECT * FROM users WHERE id = ? AND active = 1');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    if (!$user) {
        logout();
        return null;
    }
    $cached_user = $user;
    return $cached_user;
}

function require_login(): void {
    if (!is_logged_in()) {
        redirect('/?page=login&redirect=' . urlencode($_SERVER['REQUEST_URI']));
    }
}

function require_role(string ...$roles): void {
    require_login();
    $user = current_user();
    if (!$user || !in_array($user['role'], $roles, true)) {
        redirect('/?page=403');
    }
}

function login(string $username, string $password): bool {
    session_start_safe();
    $stmt = db()->prepare('SELECT * FROM users WHERE (username = ? OR email = ?) AND active = 1');
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch();
    if (!$user || !password_verify($password, $user['password_hash'])) {
        return false;
    }
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['role']    = $user['role'];
    return true;
}

function logout(): void {
    session_start_safe();
    $_SESSION = [];
    session_destroy();
}

function redirect(string $url): never {
    header('Location: ' . $url);
    exit;
}
