<?php
declare(strict_types=1);

function r119_manual_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$runtime = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pkks-r119-manual-' . bin2hex(random_bytes(6));
if (!mkdir($runtime, 0700, true) && !is_dir($runtime)) {
    throw new RuntimeException('Не удалось подготовить изолированное окружение.');
}
putenv('PKKS_ADMIN_AUTH_DB_PATH=' . $runtime . DIRECTORY_SEPARATOR . 'accounts.sqlite');
putenv('PKKS_ADMIN_BASE_URL=https://example.invalid');
putenv('PKKS_ADMIN_AUTH_DELIVERY_MODE=manual');
putenv('PKKS_ADMIN_MAIL_TRANSPORT=disabled');

require_once dirname(__DIR__) . '/admin/includes/auth.php';

r119_manual_assert(pkks_admin_auth_is_manual_delivery(), 'manual-mode');
$primary = pkks_admin_auth_prepare_primary('primary@example.invalid');
$technical = pkks_admin_auth_create_technical('technical@example.invalid');
$primaryToken = pkks_admin_auth_create_manual_activation_token((int)$primary['id']);
$technicalToken = pkks_admin_auth_create_manual_activation_token((int)$technical['id']);
r119_manual_assert(pkks_admin_auth_token_state($primaryToken, 'invite')['state'] === 'active', 'primary-link-active');
r119_manual_assert(pkks_admin_auth_token_state($technicalToken, 'invite')['state'] === 'active', 'technical-link-active');

$password = 'R119-manual-' . bin2hex(random_bytes(16));
r119_manual_assert(pkks_admin_auth_consume_token_and_set_password($primaryToken, 'invite', $password), 'primary-link-consume');
r119_manual_assert(!pkks_admin_auth_consume_token_and_set_password($primaryToken, 'invite', $password), 'primary-link-one-time');
r119_manual_assert(pkks_admin_auth_verify('primary@example.invalid', $password) !== null, 'primary-login');

$replacement = pkks_admin_auth_create_manual_activation_token((int)$technical['id']);
r119_manual_assert(pkks_admin_auth_token_state($technicalToken, 'invite')['state'] === 'revoked', 'previous-link-revoked');
r119_manual_assert(pkks_admin_auth_consume_token_and_set_password($replacement, 'invite', $password), 'technical-link-consume');
r119_manual_assert(pkks_admin_auth_verify('technical@example.invalid', $password) !== null, 'technical-login');

$mailSource = (string)file_get_contents(dirname(__DIR__) . '/admin/includes/auth-store.php');
r119_manual_assert(str_contains($mailSource, "if (pkks_admin_auth_is_manual_delivery())"), 'manual-mail-blocked');
fwrite(STDOUT, "R119_MANUAL_TEST_PASS\n");
