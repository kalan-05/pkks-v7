<?php
declare(strict_types=1);

function pkks_admin_csrf_start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    if (function_exists('pkks_admin_start_session')) {
        pkks_admin_start_session();
        return;
    }

    session_name('pkks_admin_session');
    session_start();
}

function pkks_admin_csrf_token(): string
{
    pkks_admin_csrf_start_session();

    if (empty($_SESSION['pkks_admin_csrf_token']) || !is_string($_SESSION['pkks_admin_csrf_token'])) {
        $_SESSION['pkks_admin_csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['pkks_admin_csrf_token'];
}

function pkks_admin_csrf_field(): string
{
    $token = htmlspecialchars(pkks_admin_csrf_token(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    return '<input type="hidden" name="csrf_token" value="' . $token . '">';
}

function pkks_admin_verify_csrf_token(?string $token): bool
{
    if ($token === null || $token === '') {
        return false;
    }

    return hash_equals(pkks_admin_csrf_token(), $token);
}

function pkks_admin_require_csrf(?string $token): void
{
    if (pkks_admin_verify_csrf_token($token)) {
        return;
    }

    http_response_code(403);
    echo 'Доступ запрещён.';
    exit;
}
