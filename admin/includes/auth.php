<?php
declare(strict_types=1);

function pkks_admin_config_path(): string
{
    return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'admin-auth.php';
}

function pkks_admin_has_config(): bool
{
    return is_file(pkks_admin_config_path());
}

function pkks_admin_load_config(): array
{
    $configPath = pkks_admin_config_path();

    if (!is_file($configPath)) {
        throw new RuntimeException('Admin auth config is not configured.');
    }

    $config = require $configPath;

    if (!is_array($config)) {
        throw new RuntimeException('Admin auth config must return an array.');
    }

    return $config;
}

function pkks_admin_start_session(array $config = []): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    session_name('pkks_admin_session');

    if (!headers_sent()) {
        $isSecure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/admin',
            'secure' => $isSecure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    session_start();

    $now = time();
    $idleTimeout = (int)($config['session_idle_timeout'] ?? 0);
    $absoluteTimeout = (int)($config['session_absolute_timeout'] ?? 0);
    $adminState = $_SESSION['pkks_admin'] ?? [];

    if (
        $idleTimeout > 0
        && isset($adminState['last_activity'])
        && (int)$adminState['last_activity'] + $idleTimeout < $now
    ) {
        pkks_admin_logout();
        return;
    }

    if (
        $absoluteTimeout > 0
        && isset($adminState['authenticated_at'])
        && (int)$adminState['authenticated_at'] + $absoluteTimeout < $now
    ) {
        pkks_admin_logout();
        return;
    }

    if (isset($_SESSION['pkks_admin'])) {
        $_SESSION['pkks_admin']['last_activity'] = $now;
    }
}

function pkks_admin_is_authenticated(): bool
{
    pkks_admin_start_session();

    return (bool)($_SESSION['pkks_admin']['authenticated'] ?? false);
}

function pkks_admin_require_auth(): void
{
    $config = pkks_admin_has_config() ? pkks_admin_load_config() : [];

    pkks_admin_start_session($config);

    if (pkks_admin_is_authenticated()) {
        return;
    }

    header('Location: /admin/login.php', true, 302);
    exit;
}

function pkks_admin_mark_authenticated(string $login): void
{
    pkks_admin_start_session();
    session_regenerate_id(true);

    $now = time();

    $_SESSION['pkks_admin'] = [
        'authenticated' => true,
        'login' => $login,
        'authenticated_at' => $now,
        'last_activity' => $now,
    ];
}

function pkks_admin_logout(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_name('pkks_admin_session');

        if (!headers_sent()) {
            $isSecure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

            session_set_cookie_params([
                'lifetime' => 0,
                'path' => '/admin',
                'secure' => $isSecure,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }

        session_start();
    }

    $_SESSION = [];

    if (!headers_sent()) {
        $params = session_get_cookie_params();
        $cookieOptions = [
            'expires' => time() - 3600,
            'path' => $params['path'] ?: '/admin',
            'secure' => (bool)($params['secure'] ?? false),
            'httponly' => (bool)($params['httponly'] ?? true),
            'samesite' => $params['samesite'] ?? 'Lax',
        ];

        if (!empty($params['domain'])) {
            $cookieOptions['domain'] = $params['domain'];
        }

        setcookie(session_name(), '', $cookieOptions);
    }

    session_destroy();
}

function pkks_admin_current_login(): ?string
{
    if (!pkks_admin_is_authenticated()) {
        return null;
    }

    $login = $_SESSION['pkks_admin']['login'] ?? null;

    return is_string($login) ? $login : null;
}
