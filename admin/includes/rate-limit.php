<?php
declare(strict_types=1);

function pkks_admin_login_attempts_path(): string
{
    return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'login-attempts.json';
}

function pkks_admin_get_client_ip(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';

    return is_string($ip) && $ip !== '' ? $ip : '0.0.0.0';
}

function pkks_admin_login_attempt_key(string $login): string
{
    return hash('sha256', strtolower($login) . '|' . pkks_admin_get_client_ip());
}

function pkks_admin_read_login_attempts(): array
{
    $path = pkks_admin_login_attempts_path();

    if (!is_file($path)) {
        return [];
    }

    $raw = file_get_contents($path);

    if ($raw === false || $raw === '') {
        return [];
    }

    $decoded = json_decode($raw, true);

    return is_array($decoded) ? $decoded : [];
}

function pkks_admin_write_login_attempts(array $attempts): void
{
    $path = pkks_admin_login_attempts_path();
    $storageDir = dirname($path);

    if (!is_dir($storageDir)) {
        mkdir($storageDir, 0775, true);
    }

    $json = json_encode($attempts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    if ($json === false) {
        throw new RuntimeException('Admin login attempts encoding failed.');
    }

    if (file_put_contents($path, $json . PHP_EOL, LOCK_EX) === false) {
        throw new RuntimeException('Admin login attempts writing failed.');
    }
}

function pkks_admin_is_login_blocked(string $login, array $config): bool
{
    $attempts = pkks_admin_read_login_attempts();
    $key = pkks_admin_login_attempt_key($login);
    $entry = $attempts[$key] ?? null;

    if (!is_array($entry)) {
        return false;
    }

    $now = time();
    $maxAttempts = (int)($config['max_login_attempts'] ?? 5);
    $windowSeconds = (int)($config['login_attempt_window_seconds'] ?? 900);
    $blockSeconds = (int)($config['login_block_seconds'] ?? 900);
    $failures = $entry['failures'] ?? [];

    if (!is_array($failures)) {
        return false;
    }

    $recentFailures = array_filter(
        $failures,
        static fn ($timestamp): bool => is_int($timestamp) && $timestamp + $windowSeconds >= $now
    );

    if (count($recentFailures) < $maxAttempts) {
        return false;
    }

    $lastFailure = max($recentFailures);

    return $lastFailure + $blockSeconds >= $now;
}

function pkks_admin_record_login_attempt(string $login, bool $success, array $config): void
{
    $path = pkks_admin_login_attempts_path();
    $attempts = pkks_admin_read_login_attempts();
    $key = pkks_admin_login_attempt_key($login);

    if ($success) {
        if (!isset($attempts[$key]) && !is_file($path)) {
            return;
        }

        unset($attempts[$key]);
        pkks_admin_write_login_attempts($attempts);
        return;
    }

    $now = time();
    $windowSeconds = (int)($config['login_attempt_window_seconds'] ?? 900);
    $entry = $attempts[$key] ?? ['failures' => []];
    $failures = is_array($entry['failures'] ?? null) ? $entry['failures'] : [];

    $failures = array_values(array_filter(
        $failures,
        static fn ($timestamp): bool => is_int($timestamp) && $timestamp + $windowSeconds >= $now
    ));

    $failures[] = $now;
    $attempts[$key] = ['failures' => $failures];

    pkks_admin_write_login_attempts($attempts);
}
