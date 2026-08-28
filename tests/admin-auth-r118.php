<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/admin/includes/auth.php';
require_once dirname(__DIR__) . '/admin/includes/rate-limit.php';

function r118_assert(bool $condition, string $message): void
{
    if (!$condition) { throw new RuntimeException($message); }
}

pkks_admin_auth_migrate();
pkks_admin_auth_migrate();
$password = bin2hex(random_bytes(16));
$primary = pkks_admin_auth_bootstrap_primary('primary@example.invalid', $password);
r118_assert(pkks_admin_auth_verify('primary@example.invalid', $password) !== null, 'primary-login');
r118_assert(pkks_admin_auth_verify('primary@example.invalid', 'incorrect-password') === null, 'wrong-password');
for ($attempt = 0; $attempt < 5; $attempt++) { pkks_admin_record_login_attempt('limited@example.invalid', false); }
r118_assert(pkks_admin_is_login_blocked('limited@example.invalid'), 'rate-limit');
pkks_admin_record_login_attempt('limited@example.invalid', true);
r118_assert(!pkks_admin_is_login_blocked('limited@example.invalid'), 'rate-limit-reset');

$technical = pkks_admin_auth_create_technical('technical@example.invalid');
$invite = pkks_admin_auth_create_token((int)$technical['id'], 'invite');
r118_assert(pkks_admin_auth_token_state($invite, 'invite')['state'] === 'active', 'invite-active');
pkks_admin_auth_write_outbox('technical@example.invalid', 'Тестовое приглашение', '/admin/accept-invite.php', $invite);
r118_assert(pkks_admin_auth_consume_token_and_set_password($invite, 'invite', $password), 'invite-consume');
r118_assert(!pkks_admin_auth_consume_token_and_set_password($invite, 'invite', $password), 'invite-one-time');
r118_assert(pkks_admin_auth_verify('technical@example.invalid', $password) !== null, 'technical-login');

$revokedInvite = pkks_admin_auth_create_token((int)$technical['id'], 'invite');
$nextInvite = pkks_admin_auth_create_token((int)$technical['id'], 'invite');
r118_assert(pkks_admin_auth_token_state($revokedInvite, 'invite')['state'] === 'revoked', 'invite-revoked');
$pdo = pkks_admin_auth_pdo(); $pdo->prepare('UPDATE action_tokens SET expires_at = :expired WHERE token_hash = :hash')->execute(['expired' => time() - 1, 'hash' => hash('sha256', $nextInvite)]);
r118_assert(pkks_admin_auth_token_state($nextInvite, 'invite')['state'] === 'expired', 'invite-expired');

$reset = pkks_admin_auth_create_token((int)$technical['id'], 'reset');
pkks_admin_auth_write_outbox('technical@example.invalid', 'Тестовое восстановление', '/admin/reset-password.php', $reset);
r118_assert(pkks_admin_auth_consume_token_and_set_password($reset, 'reset', $password), 'reset-consume');
r118_assert(!pkks_admin_auth_consume_token_and_set_password($reset, 'reset', $password), 'reset-one-time');
$revokedReset = pkks_admin_auth_create_token((int)$technical['id'], 'reset');
$nextReset = pkks_admin_auth_create_token((int)$technical['id'], 'reset');
r118_assert(pkks_admin_auth_token_state($revokedReset, 'reset')['state'] === 'revoked', 'reset-revoked');
$pdo->prepare('UPDATE action_tokens SET expires_at = :expired WHERE token_hash = :hash')->execute(['expired' => time() - 1, 'hash' => hash('sha256', $nextReset)]);
r118_assert(pkks_admin_auth_token_state($nextReset, 'reset')['state'] === 'expired', 'reset-expired');

pkks_admin_start_session(); $beforeSessionId = session_id(); pkks_admin_mark_authenticated($technical);
r118_assert($beforeSessionId !== session_id(), 'session-regeneration');
pkks_admin_auth_set_technical_active((int)$technical['id'], false);
r118_assert(!pkks_admin_is_authenticated(), 'disabled-session');
r118_assert(pkks_admin_auth_verify('technical@example.invalid', $password) === null, 'disabled-login');

$backup = pkks_admin_auth_backup(dirname(pkks_admin_auth_db_path()) . DIRECTORY_SEPARATOR . 'auth-backups');
$third = pkks_admin_auth_create_technical('restore-check@example.invalid');
r118_assert($third['role'] === 'technical_admin', 'pre-restore');
pkks_admin_auth_restore($backup);
$fresh = new PDO('sqlite:' . pkks_admin_auth_db_path());
r118_assert((int)$fresh->query("SELECT COUNT(*) FROM users WHERE email = 'restore-check@example.invalid'")->fetchColumn() === 0, 'restore');

pkks_admin_mark_authenticated($primary);
r118_assert(pkks_admin_current_role() === 'primary_admin', 'primary-role');
$outbox = pkks_admin_auth_outbox_path();
r118_assert(count(glob(rtrim($outbox, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*.eml') ?: []) >= 2, 'outbox');
fwrite(STDOUT, "R118_AUTH_TEST_PASS\n");
