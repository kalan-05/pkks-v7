<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/audit-log.php';
require_once __DIR__ . '/../includes/services-storage.php';

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
    $result = pkks_admin_with_data_lock(pkks_admin_services_data_path(), static function () use ($submittedRevision): array {
        pkks_admin_assert_current_revision(pkks_admin_services_data_path(), $submittedRevision);
        $currentData = pkks_admin_load_services_data();
        $validation = pkks_admin_validate_services_payload($_POST, $currentData);
        $errors = isset($validation['errors']) && is_array($validation['errors']) ? $validation['errors'] : [];

        if ($errors !== []) {
            return ['validation' => $validation];
        }

        $servicesData = isset($validation['servicesData']) && is_array($validation['servicesData']) ? $validation['servicesData'] : $currentData;
        $backupPath = pkks_admin_backup_services_data();
        pkks_admin_write_services_data($servicesData);

        return ['servicesData' => $servicesData, 'backupPath' => $backupPath];
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

        header('Location: /admin/services.php?status=error', true, 302);
        exit;
    }

    $servicesData = $result['servicesData'];
    $backupPath = $result['backupPath'];

    pkks_admin_write_audit_event('services_update', [
        'login' => $currentLogin,
        'group_count' => pkks_admin_services_count_groups($servicesData),
        'card_count' => pkks_admin_services_count_cards($servicesData),
        'item_count' => pkks_admin_services_count_items($servicesData),
        'backup_file' => basename($backupPath),
    ]);

    $_SESSION['admin_flash'] = [
        'type' => 'success',
        'title' => '✓ Изменения сохранены',
        'messages' => [],
    ];

    header('Location: /admin/services.php?status=saved', true, 302);
    exit;
} catch (PkksAdminStaleDataException) {
    $_SESSION['admin_flash'] = [
        'type' => 'error',
        'title' => 'Данные изменены в другой вкладке.',
        'messages' => ['Обновите страницу и повторите сохранение. Ваши изменения не были записаны.'],
    ];
    header('Location: /admin/services.php?status=conflict', true, 302);
    exit;
} catch (Throwable) {
    try {
        pkks_admin_write_audit_event('services_update_failed', ['login' => $currentLogin]);
    } catch (Throwable) {
    }

    $_SESSION['admin_flash'] = [
        'type' => 'error',
        'title' => 'Изменения не сохранены.',
        'messages' => ['Произошла внутренняя ошибка сохранения.'],
    ];

    header('Location: /admin/services.php?status=error', true, 302);
    exit;
}
