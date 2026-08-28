<?php
declare(strict_types=1);

/* Хранилище авторизации намеренно настраивается только вне web-root. */
function pkks_admin_auth_setting(string $name, bool $required = true): ?string
{
    $value = getenv($name);
    if (!is_string($value) || trim($value) === '') {
        if ($required) {
            throw new RuntimeException('Защищённое хранилище доступа не настроено.');
        }
        return null;
    }
    return trim($value);
}

function pkks_admin_auth_is_absolute_path(string $path): bool
{
    return str_starts_with($path, DIRECTORY_SEPARATOR) || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1;
}

function pkks_admin_auth_path_is_inside(string $path, string $parent): bool
{
    $path = rtrim(str_replace('\\', '/', $path), '/');
    $parent = rtrim(str_replace('\\', '/', $parent), '/');
    return $path === $parent || str_starts_with($path . '/', $parent . '/');
}

function pkks_admin_auth_external_path(string $setting): string
{
    $path = pkks_admin_auth_setting($setting);
    if ($path === null || !pkks_admin_auth_is_absolute_path($path) || str_contains($path, "\0")) {
        throw new RuntimeException('Путь защищённого хранилища недействителен.');
    }
    $directory = dirname($path);
    if (!is_dir($directory) || !is_writable($directory)) {
        throw new RuntimeException('Каталог защищённого хранилища недоступен.');
    }
    $documentRoot = $_SERVER['DOCUMENT_ROOT'] ?? pkks_admin_auth_setting('PKKS_ADMIN_DOCUMENT_ROOT', false);
    $root = is_string($documentRoot) && $documentRoot !== '' ? realpath($documentRoot) : false;
    $resolvedDirectory = realpath($directory);
    if ($root !== false && $resolvedDirectory !== false && pkks_admin_auth_path_is_inside($resolvedDirectory, $root)) {
        throw new RuntimeException('Защищённое хранилище нельзя размещать в публичной зоне.');
    }
    return $path;
}

function pkks_admin_auth_db_path(): string { return pkks_admin_auth_external_path('PKKS_ADMIN_AUTH_DB_PATH'); }
function pkks_admin_auth_outbox_path(): string { return pkks_admin_auth_external_path('PKKS_ADMIN_OUTBOX_PATH'); }

function pkks_admin_auth_base_url(): string
{
    $baseUrl = pkks_admin_auth_setting('PKKS_ADMIN_BASE_URL');
    $parts = is_string($baseUrl) ? parse_url($baseUrl) : false;
    if (!is_array($parts) || ($parts['scheme'] ?? '') !== 'https' || !isset($parts['host']) || isset($parts['user'], $parts['pass'])) {
        throw new RuntimeException('Доверенный адрес не настроен.');
    }
    return rtrim($baseUrl, '/');
}

function pkks_admin_auth_pdo(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) { return $pdo; }
    $pdo = new PDO('sqlite:' . pkks_admin_auth_db_path(), null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('PRAGMA busy_timeout = 5000');
    return $pdo;
}

function pkks_admin_auth_now(): int { return time(); }

function pkks_admin_auth_normalize_email(string $email): string
{
    $email = trim($email);
    if ($email === '' || strlen($email) > 254 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        throw new InvalidArgumentException('Укажите корректный e-mail.');
    }
    return strtolower($email);
}

function pkks_admin_auth_password_is_valid(string $password): bool
{
    if (preg_match('//u', $password) !== 1) { return false; }
    $length = function_exists('mb_strlen') ? mb_strlen($password, 'UTF-8') : strlen($password);
    return $length >= 15 && $length <= 64 && !in_array(strtolower($password), ['123456789012345', 'passwordpassword', 'qwertyqwertyqwe', 'парольпарольпар'], true);
}

function pkks_admin_auth_begin_immediate(PDO $pdo): void { $pdo->exec('BEGIN IMMEDIATE'); }
function pkks_admin_auth_commit(PDO $pdo): void { $pdo->exec('COMMIT'); }
function pkks_admin_auth_rollback(PDO $pdo): void { try { $pdo->exec('ROLLBACK'); } catch (Throwable) {} }

function pkks_admin_auth_backup(string $directory): string
{
    if (!pkks_admin_auth_is_absolute_path($directory) || str_contains($directory, "\0")) { throw new RuntimeException('Путь резервной копии недействителен.'); }
    if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) { throw new RuntimeException('Не удалось создать каталог резервных копий.'); }
    $source = pkks_admin_auth_db_path();
    if (!is_file($source)) { throw new RuntimeException('Исходная база для резервной копии отсутствует.'); }
    $target = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'accounts-' . gmdate('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.sqlite';
    if (!copy($source, $target)) { throw new RuntimeException('Не удалось создать резервную копию.'); }
    @chmod($target, 0600);
    return $target;
}

function pkks_admin_auth_restore(string $backupPath): void
{
    if (!pkks_admin_auth_is_absolute_path($backupPath) || !is_file($backupPath) || !copy($backupPath, pkks_admin_auth_db_path())) { throw new RuntimeException('Не удалось восстановить резервную копию.'); }
    @chmod(pkks_admin_auth_db_path(), 0600);
}

function pkks_admin_auth_migrate(): void
{
    $pdo = pkks_admin_auth_pdo();
    $pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations (version INTEGER PRIMARY KEY, applied_at INTEGER NOT NULL)');
    $versions = array_map('intval', $pdo->query('SELECT version FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN));
    if (in_array(1, $versions, true)) { return; }
    $hadDatabase = is_file(pkks_admin_auth_db_path()) && filesize(pkks_admin_auth_db_path()) > 0;
    if ($hadDatabase) { pkks_admin_auth_backup(dirname(pkks_admin_auth_db_path()) . DIRECTORY_SEPARATOR . 'auth-backups'); }
    pkks_admin_auth_begin_immediate($pdo);
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS users (id INTEGER PRIMARY KEY, email TEXT NOT NULL UNIQUE, role TEXT NOT NULL CHECK(role IN ('primary_admin', 'technical_admin')), password_hash TEXT, active INTEGER NOT NULL DEFAULT 1 CHECK(active IN (0,1)), session_version INTEGER NOT NULL DEFAULT 1, created_at INTEGER NOT NULL, updated_at INTEGER NOT NULL)");
        $pdo->exec("CREATE TABLE IF NOT EXISTS action_tokens (id INTEGER PRIMARY KEY, user_id INTEGER NOT NULL, kind TEXT NOT NULL CHECK(kind IN ('invite', 'reset')), token_hash TEXT NOT NULL UNIQUE, expires_at INTEGER NOT NULL, used_at INTEGER, revoked_at INTEGER, created_at INTEGER NOT NULL, FOREIGN KEY(user_id) REFERENCES users(id))");
        $pdo->exec('CREATE INDEX IF NOT EXISTS action_tokens_lookup ON action_tokens(token_hash, kind)');
        $pdo->exec('CREATE TABLE IF NOT EXISTS auth_attempts (key_hash TEXT PRIMARY KEY, attempts INTEGER NOT NULL, window_started_at INTEGER NOT NULL, blocked_until INTEGER NOT NULL DEFAULT 0, updated_at INTEGER NOT NULL)');
        $pdo->exec('CREATE TABLE IF NOT EXISTS auth_events (id INTEGER PRIMARY KEY, event TEXT NOT NULL, user_id INTEGER, created_at INTEGER NOT NULL, FOREIGN KEY(user_id) REFERENCES users(id))');
        $pdo->prepare('INSERT INTO schema_migrations(version, applied_at) VALUES(1, :now)')->execute(['now' => pkks_admin_auth_now()]);
        pkks_admin_auth_commit($pdo);
    } catch (Throwable $exception) { pkks_admin_auth_rollback($pdo); throw $exception; }
}

function pkks_admin_auth_user_by_email(string $email): ?array
{
    $statement = pkks_admin_auth_pdo()->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
    $statement->execute(['email' => pkks_admin_auth_normalize_email($email)]);
    $user = $statement->fetch(); return is_array($user) ? $user : null;
}

function pkks_admin_auth_user_by_id(int $id): ?array
{
    $statement = pkks_admin_auth_pdo()->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
    $statement->execute(['id' => $id]); $user = $statement->fetch(); return is_array($user) ? $user : null;
}

function pkks_admin_auth_verify(string $email, string $password): ?array
{
    $user = pkks_admin_auth_user_by_email($email);
    if ($user === null || (int)$user['active'] !== 1 || !is_string($user['password_hash']) || !password_verify($password, $user['password_hash'])) { return null; }
    if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
        pkks_admin_auth_pdo()->prepare('UPDATE users SET password_hash = :hash, updated_at = :now WHERE id = :id')->execute(['hash' => password_hash($password, PASSWORD_DEFAULT), 'now' => pkks_admin_auth_now(), 'id' => $user['id']]);
    }
    return $user;
}

function pkks_admin_auth_bootstrap_primary(string $email, string $password): array
{
    pkks_admin_auth_migrate(); if (!pkks_admin_auth_password_is_valid($password)) { throw new InvalidArgumentException('Пароль не соответствует требованиям.'); }
    $pdo = pkks_admin_auth_pdo(); pkks_admin_auth_begin_immediate($pdo);
    try {
        if ((int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'primary_admin'")->fetchColumn() > 0) { throw new RuntimeException('Основной доступ уже создан.'); }
        $now = pkks_admin_auth_now();
        $pdo->prepare('INSERT INTO users(email, role, password_hash, active, session_version, created_at, updated_at) VALUES(:email, :role, :hash, 1, 1, :now, :now)')->execute(['email' => pkks_admin_auth_normalize_email($email), 'role' => 'primary_admin', 'hash' => password_hash($password, PASSWORD_DEFAULT), 'now' => $now]);
        $id = (int)$pdo->lastInsertId(); $pdo->prepare('INSERT INTO auth_events(event, user_id, created_at) VALUES(:event, :id, :now)')->execute(['event' => 'primary_bootstrap', 'id' => $id, 'now' => $now]); pkks_admin_auth_commit($pdo);
        return pkks_admin_auth_user_by_id($id) ?? throw new RuntimeException('Не удалось создать основной доступ.');
    } catch (Throwable $exception) { pkks_admin_auth_rollback($pdo); throw $exception; }
}

function pkks_admin_auth_create_technical(string $email): array
{
    $pdo = pkks_admin_auth_pdo(); pkks_admin_auth_begin_immediate($pdo);
    try {
        $now = pkks_admin_auth_now(); $normalized = pkks_admin_auth_normalize_email($email);
        $statement = $pdo->prepare('SELECT * FROM users WHERE email = :email LIMIT 1'); $statement->execute(['email' => $normalized]); $user = $statement->fetch();
        if (is_array($user) && $user['role'] !== 'technical_admin') { throw new RuntimeException('Этот e-mail уже занят другим доступом.'); }
        if (is_array($user)) { $pdo->prepare('UPDATE users SET active = 1, session_version = session_version + 1, updated_at = :now WHERE id = :id')->execute(['now' => $now, 'id' => $user['id']]); $id = (int)$user['id']; }
        else { $pdo->prepare('INSERT INTO users(email, role, password_hash, active, session_version, created_at, updated_at) VALUES(:email, :role, NULL, 1, 1, :now, :now)')->execute(['email' => $normalized, 'role' => 'technical_admin', 'now' => $now]); $id = (int)$pdo->lastInsertId(); }
        pkks_admin_auth_commit($pdo); return pkks_admin_auth_user_by_id($id) ?? throw new RuntimeException('Не удалось подготовить доступ.');
    } catch (Throwable $exception) { pkks_admin_auth_rollback($pdo); throw $exception; }
}

function pkks_admin_auth_create_token(int $userId, string $kind): string
{
    if (!in_array($kind, ['invite', 'reset'], true)) { throw new InvalidArgumentException('Неизвестный тип действия.'); }
    $pdo = pkks_admin_auth_pdo(); pkks_admin_auth_begin_immediate($pdo);
    try {
        $user = pkks_admin_auth_user_by_id($userId); if ($user === null || (int)$user['active'] !== 1) { throw new RuntimeException('Доступ пользователя недоступен.'); }
        $now = pkks_admin_auth_now(); $pdo->prepare('UPDATE action_tokens SET revoked_at = :now WHERE user_id = :id AND kind = :kind AND used_at IS NULL AND revoked_at IS NULL')->execute(['now' => $now, 'id' => $userId, 'kind' => $kind]);
        $token = bin2hex(random_bytes(32)); $pdo->prepare('INSERT INTO action_tokens(user_id, kind, token_hash, expires_at, created_at) VALUES(:id, :kind, :hash, :expires, :now)')->execute(['id' => $userId, 'kind' => $kind, 'hash' => hash('sha256', $token), 'expires' => $now + ($kind === 'invite' ? 86400 : 1800), 'now' => $now]); pkks_admin_auth_commit($pdo); return $token;
    } catch (Throwable $exception) { pkks_admin_auth_rollback($pdo); throw $exception; }
}

function pkks_admin_auth_token_state(string $token, string $kind): ?array
{
    $statement = pkks_admin_auth_pdo()->prepare('SELECT action_tokens.*, users.email, users.role, users.active FROM action_tokens JOIN users ON users.id = action_tokens.user_id WHERE token_hash = :hash AND kind = :kind LIMIT 1');
    $statement->execute(['hash' => hash('sha256', $token), 'kind' => $kind]); $row = $statement->fetch(); if (!is_array($row)) { return null; }
    $row['state'] = $row['revoked_at'] !== null || (int)$row['active'] !== 1 ? 'revoked' : ($row['used_at'] !== null ? 'consumed' : ((int)$row['expires_at'] < pkks_admin_auth_now() ? 'expired' : 'active'));
    return $row;
}

function pkks_admin_auth_consume_token_and_set_password(string $token, string $kind, string $password): bool
{
    if (!pkks_admin_auth_password_is_valid($password)) { return false; }
    $pdo = pkks_admin_auth_pdo(); pkks_admin_auth_begin_immediate($pdo);
    try {
        $row = pkks_admin_auth_token_state($token, $kind); if ($row === null || $row['state'] !== 'active') { pkks_admin_auth_rollback($pdo); return false; }
        $now = pkks_admin_auth_now(); $changed = $pdo->prepare('UPDATE action_tokens SET used_at = :now WHERE id = :id AND used_at IS NULL AND revoked_at IS NULL AND expires_at >= :now'); $changed->execute(['now' => $now, 'id' => $row['id']]); if ($changed->rowCount() !== 1) { pkks_admin_auth_rollback($pdo); return false; }
        $pdo->prepare('UPDATE users SET password_hash = :hash, active = 1, session_version = session_version + 1, updated_at = :now WHERE id = :id')->execute(['hash' => password_hash($password, PASSWORD_DEFAULT), 'now' => $now, 'id' => $row['user_id']]);
        $pdo->prepare('UPDATE action_tokens SET revoked_at = :now WHERE user_id = :id AND id <> :token_id AND used_at IS NULL AND revoked_at IS NULL')->execute(['now' => $now, 'id' => $row['user_id'], 'token_id' => $row['id']]);
        $pdo->prepare('INSERT INTO auth_events(event, user_id, created_at) VALUES(:event, :id, :now)')->execute(['event' => $kind . '_completed', 'id' => $row['user_id'], 'now' => $now]); pkks_admin_auth_commit($pdo); return true;
    } catch (Throwable $exception) { pkks_admin_auth_rollback($pdo); throw $exception; }
}

function pkks_admin_auth_set_technical_active(int $userId, bool $active): void
{
    $pdo = pkks_admin_auth_pdo(); pkks_admin_auth_begin_immediate($pdo);
    try {
        $user = pkks_admin_auth_user_by_id($userId); if ($user === null || $user['role'] !== 'technical_admin') { throw new RuntimeException('Изменение этого доступа запрещено.'); }
        $now = pkks_admin_auth_now(); $pdo->prepare('UPDATE users SET active = :active, session_version = session_version + 1, updated_at = :now WHERE id = :id')->execute(['active' => $active ? 1 : 0, 'now' => $now, 'id' => $userId]);
        if (!$active) { $pdo->prepare('UPDATE action_tokens SET revoked_at = :now WHERE user_id = :id AND used_at IS NULL AND revoked_at IS NULL')->execute(['now' => $now, 'id' => $userId]); } pkks_admin_auth_commit($pdo);
    } catch (Throwable $exception) { pkks_admin_auth_rollback($pdo); throw $exception; }
}

function pkks_admin_auth_users(): array { return pkks_admin_auth_pdo()->query('SELECT id, email, role, active, session_version, created_at FROM users ORDER BY role DESC, email ASC')->fetchAll(); }

function pkks_admin_auth_write_outbox(string $email, string $subject, string $path, string $token): void
{
    if (!str_ends_with(strtolower($email), '.invalid')) { throw new RuntimeException('Локальная почта разрешена только для адресов .invalid.'); }
    $outbox = pkks_admin_auth_outbox_path(); if (!is_dir($outbox) && !mkdir($outbox, 0700, true) && !is_dir($outbox)) { throw new RuntimeException('Не удалось создать локальный outbox.'); }
    $url = pkks_admin_auth_base_url() . $path . '?token=' . rawurlencode($token); $file = rtrim($outbox, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . gmdate('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.eml';
    if (file_put_contents($file, "To: {$email}\nSubject: {$subject}\n\n{$url}\n", LOCK_EX) === false) { throw new RuntimeException('Не удалось сохранить локальное уведомление.'); } @chmod($file, 0600);
}

/* Единая точка доставки: в Runner-контуре доступен только закрытый local transport. */
function pkks_admin_auth_deliver_mail(string $email, string $subject, string $path, string $token): void
{
    $transport = getenv('PKKS_ADMIN_MAIL_TRANSPORT');
    if ($transport !== false && $transport !== '' && $transport !== 'local') {
        throw new RuntimeException('Отправка уведомлений не настроена.');
    }
    pkks_admin_auth_write_outbox($email, $subject, $path, $token);
}
