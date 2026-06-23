<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

pkks_admin_logout();

header('Location: /admin/login.php', true, 302);
exit;
