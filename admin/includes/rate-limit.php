<?php
declare(strict_types=1);

function pkks_admin_get_client_ip(): string { $ip = $_SERVER['REMOTE_ADDR'] ?? ''; return is_string($ip) && $ip !== '' ? $ip : '0.0.0.0'; }
function pkks_admin_login_attempt_key(string $login): string { return hash('sha256', strtolower(trim($login)) . '|' . pkks_admin_get_client_ip()); }
function pkks_admin_is_login_blocked(string $login, array $config = []): bool { $statement = pkks_admin_auth_pdo()->prepare('SELECT blocked_until FROM auth_attempts WHERE key_hash = :key'); $statement->execute(['key' => pkks_admin_login_attempt_key($login)]); return (int)$statement->fetchColumn() > time(); }
function pkks_admin_record_login_attempt(string $login, bool $success, array $config = []): void
{
    $pdo = pkks_admin_auth_pdo(); pkks_admin_auth_begin_immediate($pdo);
    try {
        $key = pkks_admin_login_attempt_key($login); if ($success) { $pdo->prepare('DELETE FROM auth_attempts WHERE key_hash = :key')->execute(['key' => $key]); pkks_admin_auth_commit($pdo); return; }
        $now = time(); $statement = $pdo->prepare('SELECT * FROM auth_attempts WHERE key_hash = :key'); $statement->execute(['key' => $key]); $entry = $statement->fetch(); $fresh = is_array($entry) && (int)$entry['window_started_at'] + 900 >= $now; $attempts = $fresh ? (int)$entry['attempts'] + 1 : 1; $started = $fresh ? (int)$entry['window_started_at'] : $now;
        $pdo->prepare('INSERT INTO auth_attempts(key_hash, attempts, window_started_at, blocked_until, updated_at) VALUES(:key, :attempts, :started, :blocked, :now) ON CONFLICT(key_hash) DO UPDATE SET attempts=excluded.attempts, window_started_at=excluded.window_started_at, blocked_until=excluded.blocked_until, updated_at=excluded.updated_at')->execute(['key' => $key, 'attempts' => $attempts, 'started' => $started, 'blocked' => $attempts >= 5 ? $now + 900 : 0, 'now' => $now]); pkks_admin_auth_commit($pdo);
    } catch (Throwable $exception) { pkks_admin_auth_rollback($pdo); throw $exception; }
}
