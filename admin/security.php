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

    echo '        <article class="pkks-admin-security-card pkks-admin-security-card--' . pkks_admin_escape($statusClass) . '">' . PHP_EOL;
    echo '            <header class="pkks-admin-security-card__header">' . PHP_EOL;
    echo '                <h2>' . pkks_admin_escape($title) . '</h2>' . PHP_EOL;
    echo '                <span class="pkks-admin-security-badge pkks-admin-security-badge--' . pkks_admin_escape($statusClass) . '">' . pkks_admin_escape($status) . '</span>' . PHP_EOL;
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
    'title' => 'Авторизация',
    'status' => 'PASS',
    'description' => 'Доступ к админ-панели закрыт авторизацией.',
    'details' => ['Страница доступна только после активной admin-сессии.'],
];

$statusCards[] = [
    'title' => 'CSRF',
    'status' => 'PASS',
    'description' => 'Save/upload endpoints используют CSRF-проверку.',
    'details' => ['Статус статический: POST-проверки с этой страницы не выполняются.'],
];

$statusCards[] = [
    'title' => 'Save/upload endpoints',
    'status' => 'PASS',
    'description' => 'Endpoints работают через POST и требуют авторизацию.',
    'details' => [
        '/admin/api/save-team.php',
        '/admin/api/upload-team-photo.php',
        '/admin/api/save-services.php',
        '/admin/api/save-prices.php',
    ],
];

$rateLimitExists = is_file(pkks_admin_security_project_path('admin/includes/rate-limit.php'));
$statusCards[] = [
    'title' => 'Rate-limit входа',
    'status' => $rateLimitExists ? 'PASS' : 'WARNING',
    'description' => $rateLimitExists
        ? 'Файл ограничения попыток входа подключён в MVP.'
        : 'Файл ограничения попыток входа не найден.',
    'details' => ['admin/includes/rate-limit.php: ' . pkks_admin_security_file_label('admin/includes/rate-limit.php')],
];

$auditLogsDir = pkks_admin_security_project_path('storage/logs');
$auditLogsExists = is_dir($auditLogsDir);
$auditLogsWritable = $auditLogsExists && is_writable($auditLogsDir);
$auditLogStatus = !$auditLogsExists ? 'ACTION_REQUIRED' : ($auditLogsWritable ? 'PASS' : 'WARNING');
$statusCards[] = [
    'title' => 'Audit log',
    'status' => $auditLogStatus,
    'description' => 'Директория журнала проверена без чтения записей.',
    'details' => ['storage/logs/: ' . pkks_admin_security_access_label($auditLogsExists, $auditLogsWritable)],
];

$backupDirs = [
    'data/backups/team/',
    'data/backups/services/',
    'data/backups/prices/',
];
$missingBackupDirs = [];
$blockedBackupDirs = [];
$backupDetails = [];

foreach ($backupDirs as $backupDir) {
    $backupPath = pkks_admin_security_project_path($backupDir);
    $backupExists = is_dir($backupPath);
    $backupWritable = $backupExists && is_writable($backupPath);

    if (!$backupExists) {
        $missingBackupDirs[] = $backupDir;
    } elseif (!$backupWritable) {
        $blockedBackupDirs[] = $backupDir;
    }

    $backupDetails[] = $backupDir . ': ' . pkks_admin_security_access_label($backupExists, $backupWritable);
}

$backupStatus = $missingBackupDirs !== [] ? 'ACTION_REQUIRED' : ($blockedBackupDirs !== [] ? 'WARNING' : 'PASS');
$statusCards[] = [
    'title' => 'JSON backups',
    'status' => $backupStatus,
    'description' => 'Проверены только директории резервных копий, без списка backup-файлов.',
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
    'title' => 'Upload фото',
    'status' => $uploadStatus,
    'description' => 'Проверены директория фото и доступные PHP-проверки изображения без реальной загрузки.',
    'details' => [
        'img/team/: ' . pkks_admin_security_access_label($uploadDirExists, $uploadDirWritable),
        'img/team/.htaccess: ' . ($uploadHtaccessExists ? 'найден' : 'отсутствует'),
        'getimagesize: ' . ($getimagesizeAvailable ? 'доступен' : 'недоступен'),
        'fileinfo/finfo_open: ' . ($fileinfoAvailable ? 'доступен' : 'недоступен'),
    ],
];

$gitignoreExists = is_file(pkks_admin_security_project_path('.gitignore'));
$statusCards[] = [
    'title' => 'Runtime/gitignore policy',
    'status' => 'PASS',
    'description' => 'Runtime-файлы исключены из Git через .gitignore: config, logs, backups, uploaded photos.',
    'details' => ['.gitignore: ' . ($gitignoreExists ? 'найден' : 'не найден локально')],
];

$hostingProtectionFiles = [
    'config/.htaccess',
    'storage/.htaccess',
    'data/backups/.htaccess',
    'includes/.htaccess',
    'img/team/.htaccess',
];
$missingHostingProtection = [];
$hostingProtectionDetails = [];

foreach ($hostingProtectionFiles as $protectionFile) {
    $exists = is_file(pkks_admin_security_project_path($protectionFile));

    if (!$exists) {
        $missingHostingProtection[] = $protectionFile;
    }

    $hostingProtectionDetails[] = $protectionFile . ': ' . ($exists ? 'найден' : 'отсутствует');
}

$statusCards[] = [
    'title' => 'Hosting protection',
    'status' => $missingHostingProtection === [] ? 'WARNING' : 'ACTION_REQUIRED',
    'description' => $missingHostingProtection === []
        ? '.htaccess-файлы найдены, но требуют проверки на целевом хостинге.'
        : 'Один или несколько защитных .htaccess отсутствуют.',
    'details' => $hostingProtectionDetails,
];

$statusCards[] = [
    'title' => 'Production dry-run',
    'status' => 'ACTION_REQUIRED',
    'description' => 'Локальный MVP проверен. Целевой хостинг ещё требует dry-run: права записи, .htaccess, upload, backup, audit.',
    'details' => ['Production-проверки с этой страницы не запускаются.'],
];

pkks_admin_render_header('Безопасность', ['body_class' => 'pkks-admin-security-page']);
pkks_admin_render_topbar('Безопасность', 'Вход выполнен: ' . $currentLogin);
?>
    <section class="pkks-admin-dashboard-intro pkks-admin-security-intro">
        <div class="pkks-admin-dashboard-intro__copy">
            <p class="pkks-admin-eyebrow">Только просмотр</p>
            <h2>Безопасность</h2>
            <p>Панель показывает состояние защитных механизмов MVP без изменения настроек, чтения секретов, логов и runtime-файлов.</p>
        </div>
        <div class="pkks-admin-dashboard-actions" aria-label="Навигация статуса безопасности">
            <a class="pkks-admin-button pkks-admin-button--secondary" href="/admin/index.php">Назад в админ-панель</a>
            <a class="pkks-admin-button pkks-admin-button--primary" href="/">Вернуться на сайт</a>
        </div>
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
