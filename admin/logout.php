<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/audit-log.php';

pkks_admin_start_session();

$currentLogin = pkks_admin_current_login();

if ($currentLogin !== null) {
    pkks_admin_write_audit_event('logout', ['login' => $currentLogin]);
}

pkks_admin_logout();

header('Location: /admin/login.php', true, 302);
exit;
