<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/includes/auth-store.php';

function pkks_admin_cli_password(string $label): string
{
    fwrite(STDOUT, $label . ': ');
    $value = fgets(STDIN);
    if (!is_string($value)) {
        throw new RuntimeException('Не удалось прочитать пароль.');
    }
    return rtrim($value, "\r\n");
}

function pkks_admin_cli_usage(): void
{
    fwrite(STDOUT, "Использование: php admin/cli/auth.php migrate|bootstrap <email>|backup <каталог>|restore <файл>|manual-setup <technical-input> <links-output>|manual-link <primary_admin|technical_admin> <links-output>\n");
}

function pkks_admin_cli_external_file(string $path, bool $mustExist): string
{
    if (!pkks_admin_auth_is_absolute_path($path) || str_contains($path, "\0")) {
        throw new InvalidArgumentException('Закрытый файл недействителен.');
    }
    $directory = dirname($path); $resolvedDirectory = realpath($directory); $documentRoot = realpath(dirname(__DIR__, 2));
    if ($resolvedDirectory === false || ($documentRoot !== false && pkks_admin_auth_path_is_inside($resolvedDirectory, $documentRoot))) {
        throw new RuntimeException('Закрытый файл нельзя размещать в публичной зоне.');
    }
    if ($mustExist && (!is_file($path) || is_link($path))) { throw new RuntimeException('Закрытый входной файл недоступен.'); }
    if (!$mustExist && (file_exists($path) || !is_writable($resolvedDirectory))) { throw new RuntimeException('Закрытый выходной файл недоступен.'); }
    return $path;
}

function pkks_admin_cli_technical_email(string $inputPath): string
{
    $inputPath = pkks_admin_cli_external_file($inputPath, true); $input = file_get_contents($inputPath);
    if (!is_string($input) || preg_match('/\ATECHNICAL_ADMIN_EMAIL=([^\r\n]+)\R?\z/', $input, $matches) !== 1) {
        throw new RuntimeException('Закрытый входной файл недействителен.');
    }
    $email = pkks_admin_auth_normalize_email($matches[1]);
    if (str_ends_with($email, '.invalid')) { throw new RuntimeException('Закрытый входной файл недействителен.'); }
    return $email;
}

function pkks_admin_cli_write_links(string $outputPath, array $links): void
{
    $outputPath = pkks_admin_cli_external_file($outputPath, false); $lines = [];
    foreach ($links as $label => $token) {
        $lines[] = $label . "\n" . pkks_admin_auth_base_url() . '/admin/accept-invite.php?token=' . rawurlencode($token);
    }
    if (file_put_contents($outputPath, implode("\n\n", $lines) . "\n", LOCK_EX) === false) { throw new RuntimeException('Не удалось подготовить закрытый файл ссылок.'); }
    @chmod($outputPath, 0600);
}

try {
    $command = $argv[1] ?? '';
    if ($command === 'migrate') {
        pkks_admin_auth_migrate(); fwrite(STDOUT, "Миграции выполнены.\n"); exit(0);
    }
    if ($command === 'bootstrap') {
        $email = $argv[2] ?? '';
        $password = pkks_admin_cli_password('Введите пароль');
        $confirmation = pkks_admin_cli_password('Повторите пароль');
        if (!hash_equals($password, $confirmation)) { throw new RuntimeException('Пароли не совпадают.'); }
        pkks_admin_auth_bootstrap_primary($email, $password); fwrite(STDOUT, "Основной доступ создан.\n"); exit(0);
    }
    if ($command === 'backup') {
        $directory = $argv[2] ?? ''; fwrite(STDOUT, pkks_admin_auth_backup($directory) . PHP_EOL); exit(0);
    }
    if ($command === 'restore') {
        $backup = $argv[2] ?? ''; pkks_admin_auth_restore($backup); fwrite(STDOUT, "Восстановление выполнено.\n"); exit(0);
    }
    if ($command === 'manual-setup') {
        if (!pkks_admin_auth_is_manual_delivery()) { throw new RuntimeException('Ручная активация отключена.'); }
        $technicalEmail = pkks_admin_cli_technical_email((string)($argv[2] ?? '')); $linksOutput = pkks_admin_cli_external_file((string)($argv[3] ?? ''), false);
        $primaryEmail = pkks_admin_auth_setting('PKKS_ADMIN_PRIMARY_ADMIN_EMAIL');
        if ($primaryEmail === null || hash_equals(pkks_admin_auth_normalize_email($primaryEmail), $technicalEmail)) { throw new RuntimeException('Параметры ручной активации недействительны.'); }
        // Первый запуск выполняется на ещё не инициализированной SQLite-базе.
        pkks_admin_auth_migrate();
        if (pkks_admin_auth_user_by_email($technicalEmail) !== null) { throw new RuntimeException('Параметры ручной активации недействительны.'); }
        $primary = pkks_admin_auth_prepare_primary($primaryEmail); $technical = pkks_admin_auth_create_technical($technicalEmail);
        pkks_admin_cli_write_links($linksOutput, ['PRIMARY_ADMIN — передать владельцу' => pkks_admin_auth_create_manual_activation_token((int)$primary['id']), 'TECHNICAL_ADMIN — открыть Андрею' => pkks_admin_auth_create_manual_activation_token((int)$technical['id'])]);
        fwrite(STDOUT, "Ручная активация подготовлена.\n"); exit(0);
    }
    if ($command === 'manual-link') {
        if (!pkks_admin_auth_is_manual_delivery()) { throw new RuntimeException('Ручная активация отключена.'); }
        $role = (string)($argv[2] ?? ''); $linksOutput = pkks_admin_cli_external_file((string)($argv[3] ?? ''), false);
        if (!in_array($role, ['primary_admin', 'technical_admin'], true)) { throw new InvalidArgumentException('Роль недействительна.'); }
        $users = array_values(array_filter(pkks_admin_auth_users(), static fn(array $user): bool => $user['role'] === $role));
        if (count($users) !== 1) { throw new RuntimeException('Доступ для ручной активации недоступен.'); }
        pkks_admin_cli_write_links($linksOutput, [$role === 'primary_admin' ? 'PRIMARY_ADMIN — передать владельцу' : 'TECHNICAL_ADMIN — открыть Андрею' => pkks_admin_auth_create_manual_activation_token((int)$users[0]['id'])]);
        fwrite(STDOUT, "Новая ссылка ручной активации подготовлена.\n"); exit(0);
    }
    pkks_admin_cli_usage(); exit(64);
} catch (Throwable $exception) {
    fwrite(STDERR, "Операция не выполнена.\n"); exit(1);
}
