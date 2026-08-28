<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/audit-log.php';
require_once __DIR__ . '/../includes/team-storage.php';

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
    $result = pkks_admin_with_data_lock(pkks_admin_team_data_path(), static function () use ($submittedRevision): array {
        pkks_admin_assert_current_revision(pkks_admin_team_data_path(), $submittedRevision);
        $currentData = pkks_admin_load_team_data();
        $validation = pkks_admin_validate_team_payload($_POST, $currentData);
        $errors = isset($validation['errors']) && is_array($validation['errors']) ? $validation['errors'] : [];

        if ($errors !== []) {
            return ['validation' => $validation];
        }

        $teamData = isset($validation['teamData']) && is_array($validation['teamData']) ? $validation['teamData'] : $currentData;
        $backupPath = pkks_admin_backup_team_data();
        pkks_admin_write_team_data($teamData);

        return ['teamData' => $teamData, 'backupPath' => $backupPath];
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

        header('Location: /admin/team.php', true, 302);
        exit;
    }

    $teamData = $result['teamData'];
    $backupPath = $result['backupPath'];

    $employees = isset($teamData['employees']) && is_array($teamData['employees']) ? $teamData['employees'] : [];
    $visibleCount = count(array_filter(
        $employees,
        static fn (mixed $employee): bool => is_array($employee) && ($employee['visible'] ?? false) === true
    ));

    pkks_admin_write_audit_event('team_update', [
        'login' => $currentLogin,
        'employee_count' => count($employees),
        'visible_count' => $visibleCount,
        'backup_file' => basename($backupPath),
    ]);

    $_SESSION['admin_flash'] = [
        'type' => 'success',
        'title' => '✓ Изменения сохранены',
        'messages' => [],
    ];

    header('Location: /admin/team.php?status=saved', true, 302);
    exit;
} catch (PkksAdminStaleDataException) {
    $_SESSION['admin_flash'] = [
        'type' => 'error',
        'title' => 'Данные изменены в другой вкладке.',
        'messages' => ['Обновите страницу и повторите сохранение. Ваши изменения не были записаны.'],
    ];
    header('Location: /admin/team.php?status=conflict', true, 302);
    exit;
} catch (Throwable) {
    try {
        pkks_admin_write_audit_event('team_update_failed', ['login' => $currentLogin]);
    } catch (Throwable) {
    }

    http_response_code(500);
    echo 'Не удалось сохранить изменения.';
    exit;
}
