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

function pkks_admin_session_cookie_options(): array
{
    $isSecure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

    return [
        'lifetime' => 0,
        'path' => '/admin',
        'secure' => $isSecure,
        'httponly' => true,
        'samesite' => 'Lax',
    ];
}

function pkks_admin_start_session(array $config = []): void
{
    $isActive = session_status() === PHP_SESSION_ACTIVE;

    if (!$isActive) {
        session_name('pkks_admin_session');

        if (!headers_sent()) {
            session_set_cookie_params(pkks_admin_session_cookie_options());
        }

        session_start();
    }

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
        && isset($adminState['created_at'])
        && (int)$adminState['created_at'] + $absoluteTimeout < $now
    ) {
        pkks_admin_logout();
        return;
    }

    if (isset($_SESSION['pkks_admin'])) {
        $_SESSION['pkks_admin']['last_activity'] = $now;
    }
}

function pkks_admin_is_authenticated(array $config = []): bool
{
    pkks_admin_start_session($config);

    $adminState = $_SESSION['pkks_admin'] ?? null;

    if (!is_array($adminState)) {
        return false;
    }

    $login = $adminState['login'] ?? null;

    if (!is_string($login) || trim($login) === '') {
        return false;
    }

    if (($adminState['authenticated'] ?? false) !== true) {
        return false;
    }

    return isset($adminState['created_at'], $adminState['last_activity'])
        && is_int($adminState['created_at'])
        && is_int($adminState['last_activity']);
}

function pkks_admin_require_auth(): void
{
    $config = pkks_admin_has_config() ? pkks_admin_load_config() : [];

    pkks_admin_start_session($config);

    if (pkks_admin_is_authenticated($config)) {
        return;
    }

    header('Location: /admin/login.php', true, 302);
    exit;
}

function pkks_admin_mark_authenticated(string $login, array $config = []): void
{
    pkks_admin_start_session($config);
    session_regenerate_id(true);

    $now = time();

    $_SESSION['pkks_admin'] = [
        'authenticated' => true,
        'login' => $login,
        'created_at' => $now,
        'last_activity' => $now,
    ];
}

function pkks_admin_verify_credentials(string $login, string $password, array $config): bool
{
    $expectedLogin = $config['admin_login'] ?? null;
    $passwordHash = $config['admin_password_hash'] ?? null;

    if (!is_string($expectedLogin) || !is_string($passwordHash) || $passwordHash === '') {
        return false;
    }

    $loginMatches = hash_equals($expectedLogin, $login);
    $passwordMatches = password_verify($password, $passwordHash);

    return $loginMatches && $passwordMatches;
}

function pkks_admin_logout(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_name('pkks_admin_session');

        if (!headers_sent()) {
            session_set_cookie_params(pkks_admin_session_cookie_options());
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
