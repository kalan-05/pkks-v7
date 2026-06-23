<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/admin-layout.php';

if (!pkks_admin_has_config()) {
    pkks_admin_render_header('Админ-доступ');
    echo '    <p>Для входа нужно настроить файл config/admin-auth.php.</p>' . PHP_EOL;
    pkks_admin_render_footer();
    exit;
}

pkks_admin_require_auth();

pkks_admin_render_header('Админ-панель');
echo '    <p>Админ-панель будет добавлена в следующем этапе.</p>' . PHP_EOL;
pkks_admin_render_footer();
