<?php
declare(strict_types=1);

require_once __DIR__ . '/auth-store.php';

function pkks_admin_has_config(): bool { try { pkks_admin_auth_db_path(); return true; } catch (Throwable) { return false; } }
function pkks_admin_session_cookie_options(): array { return ['lifetime' => 0, 'path' => '/admin', 'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', 'httponly' => true, 'samesite' => 'Lax']; }
function pkks_admin_start_session(array $config = []): void { if (session_status() !== PHP_SESSION_ACTIVE) { session_name('pkks_admin_session'); if (!headers_sent()) { session_set_cookie_params(pkks_admin_session_cookie_options()); } session_start(); } if (isset($_SESSION['pkks_admin'])) { $_SESSION['pkks_admin']['last_activity'] = time(); } }
function pkks_admin_is_authenticated(array $config = []): bool
{
    pkks_admin_start_session($config); $state = $_SESSION['pkks_admin'] ?? null;
    if (!is_array($state) || ($state['authenticated'] ?? false) !== true || !isset($state['user_id'], $state['session_version'], $state['created_at'], $state['last_activity'])) { return false; }
    try { $user = pkks_admin_auth_user_by_id((int)$state['user_id']); $valid = $user !== null && (int)$user['active'] === 1 && (int)$user['session_version'] === (int)$state['session_version'] && in_array($user['role'], ['primary_admin', 'technical_admin'], true); } catch (Throwable) { $valid = false; }
    if (!$valid) { pkks_admin_logout(); return false; }
    $_SESSION['pkks_admin']['role'] = $user['role']; $_SESSION['pkks_admin']['login'] = $user['email']; return true;
}
function pkks_admin_require_auth(): void { if (pkks_admin_is_authenticated()) { return; } header('Location: /admin/login.php', true, 302); exit; }
function pkks_admin_current_role(): ?string { return pkks_admin_is_authenticated() ? ($_SESSION['pkks_admin']['role'] ?? null) : null; }
function pkks_admin_require_role(string $role): void { pkks_admin_require_auth(); if (pkks_admin_current_role() === $role) { return; } http_response_code(403); echo 'Доступ запрещён.'; exit; }
function pkks_admin_mark_authenticated(array $user, array $config = []): void { pkks_admin_start_session($config); session_regenerate_id(true); $now = time(); $_SESSION['pkks_admin'] = ['authenticated' => true, 'user_id' => (int)$user['id'], 'session_version' => (int)$user['session_version'], 'role' => (string)$user['role'], 'login' => (string)$user['email'], 'created_at' => $now, 'last_activity' => $now]; }
function pkks_admin_verify_credentials(string $email, string $password, array $config = []): ?array { try { return pkks_admin_auth_verify($email, $password); } catch (Throwable) { return null; } }
function pkks_admin_logout(): void { if (session_status() !== PHP_SESSION_ACTIVE) { pkks_admin_start_session(); } $_SESSION = []; if (!headers_sent()) { $options = pkks_admin_session_cookie_options(); unset($options['lifetime']); $options['expires'] = time() - 3600; setcookie(session_name(), '', $options); } session_destroy(); }
function pkks_admin_current_login(): ?string { return pkks_admin_is_authenticated() ? ($_SESSION['pkks_admin']['login'] ?? null) : null; }
