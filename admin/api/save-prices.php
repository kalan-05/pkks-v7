<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/audit-log.php';
require_once __DIR__ . '/../includes/prices-storage.php';

pkks_admin_require_auth();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo 'Метод не поддерживается.';
    exit;
}

$currentLogin = pkks_admin_current_login() ?? 'администратор';

pkks_admin_require_csrf(is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : null);

try {
    $submittedRevision = pkks_admin_posted_data_revision($_POST);
    $result = pkks_admin_with_data_lock(pkks_admin_prices_data_path(), static function () use ($submittedRevision): array {
        pkks_admin_assert_current_revision(pkks_admin_prices_data_path(), $submittedRevision);
        $currentData = pkks_admin_load_prices_data();
        $validation = pkks_admin_validate_prices_payload($_POST, $currentData);
        $errors = isset($validation['errors']) && is_array($validation['errors']) ? $validation['errors'] : [];

        if ($errors !== []) {
            return ['validation' => $validation];
        }

        $pricesData = isset($validation['pricesData']) && is_array($validation['pricesData']) ? $validation['pricesData'] : $currentData;
        $backupPath = pkks_admin_backup_prices_data();
        pkks_admin_write_prices_data($pricesData);

        return ['pricesData' => $pricesData, 'backupPath' => $backupPath];
    });
    $validation = $result['validation'] ?? [];
    $errors = isset($validation['errors']) && is_array($validation['errors']) ? $validation['errors'] : [];

    if ($errors !== []) {
        $_SESSION['admin_flash'] = [
            'type' => 'error',
            'title' => 'Изменения не сохранены.',
            'messages' => $errors,
            'formData' => $validation['formData'] ?? [],
        ];

        header('Location: /admin/prices.php?status=error', true, 302);
        exit;
    }

    $pricesData = $result['pricesData'];
    $backupPath = $result['backupPath'];

    pkks_admin_write_audit_event('prices_update', [
        'login' => $currentLogin,
        'price_count' => pkks_admin_prices_count_prices($pricesData),
        'note_count' => pkks_admin_prices_count_notes($pricesData),
        'backup_file' => basename($backupPath),
    ]);

    $_SESSION['admin_flash'] = [
        'type' => 'success',
        'title' => '✓ Изменения сохранены',
        'messages' => [],
    ];

    header('Location: /admin/prices.php?status=saved', true, 302);
    exit;
} catch (PkksAdminStaleDataException) {
    $_SESSION['admin_flash'] = [
        'type' => 'error',
        'title' => 'Данные изменены в другой вкладке.',
        'messages' => ['Обновите страницу и повторите сохранение. Ваши изменения не были записаны.'],
    ];
    header('Location: /admin/prices.php?status=conflict', true, 302);
    exit;
} catch (Throwable) {
    try {
        pkks_admin_write_audit_event('prices_update_failed', ['login' => $currentLogin]);
    } catch (Throwable) {
    }

    $_SESSION['admin_flash'] = [
        'type' => 'error',
        'title' => 'Изменения не сохранены.',
        'messages' => ['Произошла внутренняя ошибка сохранения.'],
    ];

    header('Location: /admin/prices.php?status=error', true, 302);
    exit;
}
