<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/admin-layout.php';

define('PKKS_ADMIN_SECURITY_ROOT', dirname(__DIR__));

function pkks_admin_security_project_path(string $relativePath): string
{
    return PKKS_ADMIN_SECURITY_ROOT . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
}

function pkks_admin_security_status_class(string $status): string
{
    return strtolower(str_replace('_', '-', $status));
}

function pkks_admin_security_status_label(string $status): string
{
    switch ($status) {
        case 'PASS':
            return 'Работает';
        case 'WARNING':
            return 'Требует проверки';
        case 'ACTION_REQUIRED':
            return 'Нужно выполнить';
        default:
            return 'Неизвестно';
    }
}

function pkks_admin_security_access_label(bool $exists, bool $writable): string
{
    if (!$exists) {
        return 'отсутствует';
    }

    return $writable ? 'есть, запись доступна' : 'есть, запись недоступна';
}

function pkks_admin_security_file_label(string $relativePath): string
{
    return is_file(pkks_admin_security_project_path($relativePath)) ? 'найден' : 'отсутствует';
}

function pkks_admin_security_render_card(array $card): void
{
    $title = is_string($card['title'] ?? null) ? $card['title'] : 'Статус';
    $status = is_string($card['status'] ?? null) ? $card['status'] : 'UNKNOWN';
    $description = is_string($card['description'] ?? null) ? $card['description'] : '';
    $details = isset($card['details']) && is_array($card['details']) ? $card['details'] : [];
    $statusClass = pkks_admin_security_status_class($status);
    $statusLabel = pkks_admin_security_status_label($status);

    echo '        <article class="pkks-admin-security-card pkks-admin-security-card--' . pkks_admin_escape($statusClass) . '">' . PHP_EOL;
    echo '            <header class="pkks-admin-security-card__header">' . PHP_EOL;
    echo '                <h2>' . pkks_admin_escape($title) . '</h2>' . PHP_EOL;
    echo '                <span class="pkks-admin-security-badge pkks-admin-security-badge--' . pkks_admin_escape($statusClass) . '">' . pkks_admin_escape($statusLabel) . '</span>' . PHP_EOL;
    echo '            </header>' . PHP_EOL;
    echo '            <p>' . pkks_admin_escape($description) . '</p>' . PHP_EOL;

    if ($details !== []) {
        echo '            <ul class="pkks-admin-security-details">' . PHP_EOL;

        foreach ($details as $detail) {
            if (!is_string($detail) || $detail === '') {
                continue;
            }

            echo '                <li>' . pkks_admin_escape($detail) . '</li>' . PHP_EOL;
        }

        echo '            </ul>' . PHP_EOL;
    }

    echo '        </article>' . PHP_EOL;
}

pkks_admin_require_auth();

$currentLogin = pkks_admin_current_login() ?? 'администратор';
$statusCards = [];

$statusCards[] = [
    'title' => 'Вход в админку',
    'status' => 'PASS',
    'description' => 'Админ-панель закрыта логином и паролем.',
    'details' => ['Без успешного входа страницы управления недоступны.'],
];

$statusCards[] = [
    'title' => 'Защита форм',
    'status' => 'PASS',
    'description' => 'Формы сохранения защищены от поддельной отправки.',
    'details' => ['Проверка выполняется автоматически при сохранении данных.'],
];

$statusCards[] = [
    'title' => 'Сохранение данных и загрузка файлов',
    'status' => 'PASS',
    'description' => 'Данные можно менять только через защищённые разделы админ-панели.',
    'details' => [
        'Сотрудники',
        'Фото сотрудников',
        'Услуги',
        'Цены',
    ],
];

$rateLimitExists = is_file(pkks_admin_security_project_path('admin/includes/rate-limit.php'));
$statusCards[] = [
    'title' => 'Защита от подбора пароля',
    'status' => $rateLimitExists ? 'PASS' : 'WARNING',
    'description' => $rateLimitExists
        ? 'Попытки входа ограничиваются, чтобы пароль нельзя было бесконечно подбирать.'
        : 'Ограничение попыток входа требует проверки.',
    'details' => [$rateLimitExists ? 'Ограничение попыток входа подключено.' : 'Ограничение попыток входа не найдено.'],
];

$auditLogsDir = pkks_admin_security_project_path('storage/logs');
$auditLogsExists = is_dir($auditLogsDir);
$auditLogsWritable = $auditLogsExists && is_writable($auditLogsDir);
$auditLogStatus = !$auditLogsExists ? 'ACTION_REQUIRED' : ($auditLogsWritable ? 'PASS' : 'WARNING');
$statusCards[] = [
    'title' => 'Журнал действий',
    'status' => $auditLogStatus,
    'description' => 'Важные действия в админке записываются в служебный журнал.',
    'details' => [
        'Журнал не показывается на этой странице и не раскрывает содержимое изменений.',
        'Служебный журнал: ' . pkks_admin_security_access_label($auditLogsExists, $auditLogsWritable),
    ],
];

$backupDirs = [
    'Сотрудники' => 'data/backups/team/',
    'Услуги' => 'data/backups/services/',
    'Цены' => 'data/backups/prices/',
];
$missingBackupDirs = [];
$blockedBackupDirs = [];
$backupDetails = [];

foreach ($backupDirs as $backupLabel => $backupDir) {
    $backupPath = pkks_admin_security_project_path($backupDir);
    $backupExists = is_dir($backupPath);
    $backupWritable = $backupExists && is_writable($backupPath);

    if (!$backupExists) {
        $missingBackupDirs[] = $backupDir;
    } elseif (!$backupWritable) {
        $blockedBackupDirs[] = $backupDir;
    }

    $backupDetails[] = $backupLabel . ': ' . pkks_admin_security_access_label($backupExists, $backupWritable);
}

$backupDetails[] = 'Это помогает восстановить данные, если при редактировании была допущена ошибка.';

$backupStatus = $missingBackupDirs !== [] ? 'ACTION_REQUIRED' : ($blockedBackupDirs !== [] ? 'WARNING' : 'PASS');
$statusCards[] = [
    'title' => 'Резервные копии данных',
    'status' => $backupStatus,
    'description' => 'Перед сохранением создаётся резервная копия данных.',
    'details' => $backupDetails,
];

$uploadDir = pkks_admin_security_project_path('img/team');
$uploadDirExists = is_dir($uploadDir);
$uploadDirWritable = $uploadDirExists && is_writable($uploadDir);
$getimagesizeAvailable = function_exists('getimagesize');
$fileinfoAvailable = extension_loaded('fileinfo') && function_exists('finfo_open');
$uploadHtaccessExists = is_file(pkks_admin_security_project_path('img/team/.htaccess'));

if (!$uploadDirExists || !$getimagesizeAvailable) {
    $uploadStatus = 'ACTION_REQUIRED';
} elseif (!$uploadDirWritable || !$fileinfoAvailable || !$uploadHtaccessExists) {
    $uploadStatus = 'WARNING';
} else {
    $uploadStatus = 'PASS';
}

$statusCards[] = [
    'title' => 'Проверка загружаемых фото',
    'status' => $uploadStatus,
    'description' => 'Фото сотрудников проверяются перед загрузкой.',
    'details' => [
        'Разрешены: JPG, PNG, WEBP',
        'Максимальный размер: 3 MB',
        'SVG, PHP, HTML, JS и документы запрещены',
        'Папка для фото: ' . pkks_admin_security_access_label($uploadDirExists, $uploadDirWritable),
        'Защита папки фото: ' . ($uploadHtaccessExists ? 'найдена' : 'отсутствует'),
        'Базовая проверка изображения: ' . ($getimagesizeAvailable ? 'доступна' : 'недоступна'),
        'На локальной версии используется резервная проверка изображения.',
        'На реальном хостинге нужно проверить загрузку тестового фото.',
    ],
];

$gitignoreExists = is_file(pkks_admin_security_project_path('.gitignore'));
$statusCards[] = [
    'title' => 'Служебные файлы проекта',
    'status' => 'PASS',
    'description' => 'Логи, резервные копии, локальный пароль и загруженные фото не попадают в репозиторий.',
    'details' => [
        'Это снижает риск случайно отправить служебные файлы в Git.',
        'Правило исключения служебных файлов: ' . ($gitignoreExists ? 'найдено' : 'не найдено локально'),
    ],
];

$hostingProtectionFiles = [
    'config' => 'config/.htaccess',
    'storage' => 'storage/.htaccess',
    'data/backups' => 'data/backups/.htaccess',
    'includes' => 'includes/.htaccess',
    'img/team — без просмотра списка файлов' => 'img/team/.htaccess',
];
$missingHostingProtection = [];
$hostingProtectionDetails = [];

foreach ($hostingProtectionFiles as $protectionLabel => $protectionFile) {
    $exists = is_file(pkks_admin_security_project_path($protectionFile));

    if (!$exists) {
        $missingHostingProtection[] = $protectionFile;
    }

    $hostingProtectionDetails[] = $protectionLabel . ': ' . ($exists ? 'проверено локально' : 'требует настройки');
}

$hostingProtectionDetails[] = 'На локальной версии проверена структура. На реальном хостинге нужно подтвердить, что правила защиты работают.';

$statusCards[] = [
    'title' => 'Защита папок на хостинге',
    'status' => $missingHostingProtection === [] ? 'WARNING' : 'ACTION_REQUIRED',
    'description' => $missingHostingProtection === []
        ? 'Служебные папки должны быть закрыты от прямого доступа из браузера.'
        : 'Одна или несколько служебных папок требуют настройки защиты.',
    'details' => $hostingProtectionDetails,
];

$statusCards[] = [
    'title' => 'Проверка на реальном хостинге',
    'status' => 'ACTION_REQUIRED',
    'description' => 'Локальная версия проверена. Перед передачей сайта нужно выполнить тест на хостинге.',
    'details' => [
        'Права записи',
        'Защита служебных папок',
        'Загрузка фото',
        'Создание резервных копий',
        'Запись журнала действий',
    ],
];

pkks_admin_render_header('Безопасность', ['body_class' => 'pkks-admin-security-page']);
pkks_admin_render_topbar('Безопасность', 'Вход выполнен: ' . $currentLogin);
?>
    <section class="pkks-admin-dashboard-intro pkks-admin-security-intro">
        <div class="pkks-admin-dashboard-intro__copy">
            <p class="pkks-admin-eyebrow">Только просмотр</p>
            <h2>Безопасность</h2>
            <p>Этот раздел показывает, готова ли админ-панель к безопасной работе.</p>
            <p>Здесь ничего не нужно настраивать каждый день. Основная работа с сайтом выполняется в разделах «Сотрудники», «Услуги» и «Цены».</p>
            <p>Страница нужна для технической проверки перед передачей сайта и установкой на хостинг.</p>
        </div>
        <div class="pkks-admin-dashboard-actions" aria-label="Навигация статуса безопасности">
            <a class="pkks-admin-button pkks-admin-button--secondary" href="/admin/index.php">Назад в админ-панель</a>
            <a class="pkks-admin-button pkks-admin-button--primary" href="/">Вернуться на сайт</a>
        </div>
    </section>

    <section class="pkks-admin-notice pkks-admin-security-help" aria-label="Как пользоваться разделом безопасности">
        <h2>Как пользоваться этим разделом</h2>
        <p>Если все основные пункты зелёные — админка готова к локальной работе.</p>
        <p>Жёлтые пункты означают не ошибку, а то, что настройку нужно проверить на реальном хостинге.</p>
        <p>Красный пункт означает действие, которое нужно выполнить перед передачей сайта заказчику.</p>
    </section>

    <section class="pkks-admin-security-grid" aria-label="Статусы безопасности админ-панели">
<?php foreach ($statusCards as $statusCard): ?>
<?php pkks_admin_security_render_card($statusCard); ?>
<?php endforeach; ?>
    </section>
<?php
pkks_admin_render_footer([
    ['href' => '/admin/index.php', 'label' => 'Назад в админ-панель'],
    ['href' => '/', 'label' => 'Вернуться на сайт'],
    ['href' => '/admin/logout.php', 'label' => 'Выйти'],
]);
