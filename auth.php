<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

function auth_start(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        $savePath = __DIR__ . DIRECTORY_SEPARATOR . '.sessions';
        if (!is_dir($savePath)) {
            @mkdir($savePath, 0777, true);
        }
        if (is_dir($savePath)) {
            session_save_path($savePath);
        }
        session_start();
    }
}

function auth_user(): ?array
{
    auth_start();
    $id = $_SESSION['user_id'] ?? null;
    $username = $_SESSION['username'] ?? null;
    $role = $_SESSION['role'] ?? null;

    if (!is_int($id) && !is_string($id)) {
        return null;
    }
    if (!is_string($username) || trim($username) === '') {
        return null;
    }
    if ($role === 'staff') {
        $role = 'visitante';
    }
    if (!is_string($role) || ($role !== 'admin' && $role !== 'visitante')) {
        return null;
    }

    return [
        'id' => (int)$id,
        'username' => $username,
        'role' => $role,
    ];
}

function auth_login(int $userId, string $username, string $role): void
{
    auth_start();
    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
    $_SESSION['username'] = $username;
    $_SESSION['role'] = $role;
}

function auth_logout(): void
{
    auth_start();
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            [
                'expires' => time() - 42000,
                'path' => $params['path'] ?? '/',
                'domain' => $params['domain'] ?? '',
                'secure' => (bool)($params['secure'] ?? false),
                'httponly' => (bool)($params['httponly'] ?? true),
                'samesite' => 'Lax',
            ]
        );
    }

    session_destroy();
}

function safe_redirect_path(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }
    if (preg_match('~[\r\n]~', $value)) {
        return '';
    }
    $parts = parse_url($value);
    if (is_array($parts) && (isset($parts['scheme']) || isset($parts['host']))) {
        return '';
    }
    if (!str_starts_with($value, '/')) {
        return '';
    }
    return $value;
}

function require_login(): array
{
    $user = auth_user();
    if (is_array($user)) {
        return $user;
    }

    $uri = isset($_SERVER['REQUEST_URI']) ? (string)$_SERVER['REQUEST_URI'] : '/';
    $redirect = safe_redirect_path($uri);
    $qs = $redirect !== '' ? ('?redirect=' . rawurlencode($redirect)) : '';
    header('Location: ./login.php' . $qs);
    exit;
}

function require_admin(): array
{
    $user = require_login();
    if (($user['role'] ?? '') === 'admin') {
        return $user;
    }
    http_response_code(403);
    exit('Acceso denegado');
}
