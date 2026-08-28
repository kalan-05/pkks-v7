<?php
declare(strict_types=1);

function r119_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$runtime = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pkks-r119-' . bin2hex(random_bytes(6));
if (!mkdir($runtime, 0700, true) && !is_dir($runtime)) {
    throw new RuntimeException('Не удалось подготовить изолированное окружение.');
}
$runtimeConfig = $runtime . DIRECTORY_SEPARATOR . 'runtime-config.php';
$runtimeValues = [
    'PKKS_ADMIN_AUTH_DB_PATH' => $runtime . DIRECTORY_SEPARATOR . 'accounts.sqlite',
    'PKKS_ADMIN_OUTBOX_PATH' => $runtime . DIRECTORY_SEPARATOR . 'outbox',
    'PKKS_ADMIN_BASE_URL' => 'https://example.invalid',
];
file_put_contents($runtimeConfig, "<?php\nreturn " . var_export($runtimeValues, true) . ";\n", LOCK_EX);
@chmod($runtimeConfig, 0600);
putenv('PKKS_ADMIN_RUNTIME_CONFIG=' . $runtimeConfig);
putenv('PKKS_ADMIN_MAIL_TRANSPORT=local');

require_once dirname(__DIR__) . '/admin/includes/auth.php';

$password = 'R119-' . bin2hex(random_bytes(16));
r119_assert(pkks_admin_auth_db_path() === $runtimeValues['PKKS_ADMIN_AUTH_DB_PATH'], 'external-runtime-config');
pkks_admin_auth_migrate();
$primary = pkks_admin_auth_bootstrap_primary('primary@example.invalid', $password);

$first = pkks_admin_auth_create_token((int)$primary['id'], 'reset');
pkks_admin_auth_deliver_mail('primary@example.invalid', 'Восстановление доступа', '/admin/reset-password.php', $first);
r119_assert(pkks_admin_auth_token_state($first, 'reset')['state'] === 'active', 'local-delivery-active');
$outboxFiles = glob($runtime . DIRECTORY_SEPARATOR . 'outbox' . DIRECTORY_SEPARATOR . '*.eml') ?: [];
r119_assert(count($outboxFiles) === 1, 'local-outbox');
$message = (string)file_get_contents($outboxFiles[0]);
r119_assert(!str_contains($message, $password), 'password-not-in-message');

$pending = pkks_admin_auth_create_token((int)$primary['id'], 'reset');
putenv('PKKS_ADMIN_MAIL_TRANSPORT=unexpected');
try {
    pkks_admin_auth_deliver_mail('primary@example.invalid', 'Восстановление доступа', '/admin/reset-password.php', $pending);
    throw new RuntimeException('unknown-transport-not-rejected');
} catch (RuntimeException $exception) {
    r119_assert(!str_contains($exception->getMessage(), $pending), 'token-not-in-exception');
}
r119_assert(pkks_admin_auth_token_state($first, 'reset')['state'] === 'active', 'prior-token-kept-on-failure');
r119_assert(pkks_admin_auth_token_state($pending, 'reset')['state'] === 'revoked', 'failed-token-revoked');

$replacement = pkks_admin_auth_create_token((int)$primary['id'], 'reset');
putenv('PKKS_ADMIN_MAIL_TRANSPORT=local');
pkks_admin_auth_deliver_mail('primary@example.invalid', 'Восстановление доступа', '/admin/reset-password.php', $replacement);
r119_assert(pkks_admin_auth_token_state($first, 'reset')['state'] === 'revoked', 'prior-token-revoked-after-delivery');
r119_assert(pkks_admin_auth_token_state($replacement, 'reset')['state'] === 'active', 'replacement-token-active');

$invalidRecipient = pkks_admin_auth_create_token((int)$primary['id'], 'invite');
try {
    pkks_admin_auth_deliver_mail('primary@example.test', 'Приглашение', '/admin/accept-invite.php', $invalidRecipient);
    throw new RuntimeException('invalid-recipient-not-rejected');
} catch (RuntimeException) {
    r119_assert(pkks_admin_auth_token_state($invalidRecipient, 'invite')['state'] === 'revoked', 'invalid-recipient-token-revoked');
}

$smtpCandidate = pkks_admin_auth_create_token((int)$primary['id'], 'invite');
putenv('PKKS_ADMIN_MAIL_TRANSPORT=smtp');
putenv('PKKS_ADMIN_SMTP_HOST=smtp.example.invalid');
putenv('PKKS_ADMIN_SMTP_PORT=587');
putenv('PKKS_ADMIN_SMTP_ENCRYPTION=starttls');
putenv('PKKS_ADMIN_SMTP_AUTH=0');
putenv('PKKS_ADMIN_SMTP_FROM_ADDRESS=no-reply@example.invalid');
putenv('PKKS_ADMIN_SMTP_FROM_NAME=Правовая контора К. Сопрачева');
putenv('PKKS_ADMIN_SMTP_TIMEOUT=15');
$smtpInvalidRecipient = pkks_admin_auth_create_token((int)$primary['id'], 'invite');
try {
    pkks_admin_auth_deliver_mail('primary@example.invalid', 'Приглашение', '/admin/accept-invite.php', $smtpInvalidRecipient);
    throw new RuntimeException('smtp-invalid-recipient-not-rejected');
} catch (RuntimeException) {
    r119_assert(pkks_admin_auth_token_state($smtpInvalidRecipient, 'invite')['state'] === 'revoked', 'smtp-invalid-recipient-token-revoked');
}

$smtpMissingSetting = pkks_admin_auth_create_token((int)$primary['id'], 'invite');
putenv('PKKS_ADMIN_SMTP_HOST=');
try {
    pkks_admin_auth_deliver_mail('primary@example.test', 'Приглашение', '/admin/accept-invite.php', $smtpMissingSetting);
    throw new RuntimeException('smtp-missing-setting-not-rejected');
} catch (RuntimeException) {
    r119_assert(pkks_admin_auth_token_state($smtpMissingSetting, 'invite')['state'] === 'revoked', 'smtp-missing-setting-token-revoked');
}
putenv('PKKS_ADMIN_SMTP_HOST=smtp.example.invalid');

try {
    pkks_admin_auth_deliver_mail('primary@example.test', "Приглашение\r\nBcc: test@example.invalid", '/admin/accept-invite.php', $smtpCandidate);
    throw new RuntimeException('header-injection-not-rejected');
} catch (RuntimeException $exception) {
    r119_assert(!str_contains($exception->getMessage(), $smtpCandidate), 'smtp-token-not-in-exception');
}
r119_assert(pkks_admin_auth_token_state($smtpCandidate, 'invite')['state'] === 'revoked', 'smtp-failure-token-revoked');

$localInjection = pkks_admin_auth_create_token((int)$primary['id'], 'invite');
putenv('PKKS_ADMIN_MAIL_TRANSPORT=local');
try {
    pkks_admin_auth_deliver_mail('primary@example.invalid', "Приглашение\r\nBcc: test@example.invalid", '/admin/accept-invite.php', $localInjection);
    throw new RuntimeException('local-header-injection-not-rejected');
} catch (RuntimeException) {
    r119_assert(pkks_admin_auth_token_state($localInjection, 'invite')['state'] === 'revoked', 'local-header-injection-token-revoked');
}

$transportSource = (string)file_get_contents(dirname(__DIR__) . '/admin/includes/mail-transport.php');
r119_assert(str_contains($transportSource, 'SMTP::DEBUG_OFF'), 'smtp-debug-disabled');
r119_assert(!str_contains($transportSource, 'SMTPOptions'), 'tls-verification-not-overridden');

fwrite(STDOUT, "R119_SMTP_TEST_PASS\n");
