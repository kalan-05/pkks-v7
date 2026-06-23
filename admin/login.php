<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/admin-layout.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    http_response_code(405);
    pkks_admin_render_header('Вход в админ-панель');
    echo '    <p>Вход в админ-панель пока не подключён.</p>' . PHP_EOL;
    pkks_admin_render_footer();
    exit;
}

pkks_admin_render_header('Вход в админ-панель');

if (!pkks_admin_has_config()) {
    echo '    <p>Админ-доступ ещё не настроен.</p>' . PHP_EOL;
} else {
    echo '    <p>Форма входа будет подключена на следующем этапе.</p>' . PHP_EOL;
}

pkks_admin_render_footer();
