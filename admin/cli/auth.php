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
    fwrite(STDOUT, "Использование: php admin/cli/auth.php migrate|bootstrap <email>|backup <каталог>|restore <файл>\n");
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
    pkks_admin_cli_usage(); exit(64);
} catch (Throwable $exception) {
    fwrite(STDERR, "Операция не выполнена.\n"); exit(1);
}
