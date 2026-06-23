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
    $currentData = pkks_admin_load_team_data();
    $validation = pkks_admin_validate_team_payload($_POST, $currentData);
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

    $teamData = isset($validation['teamData']) && is_array($validation['teamData'])
        ? $validation['teamData']
        : $currentData;
    $backupPath = pkks_admin_backup_team_data();

    pkks_admin_write_team_data($teamData);

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
        'title' => 'Изменения сохранены.',
        'messages' => ['Резервная копия создана: ' . basename($backupPath) . '.'],
    ];

    header('Location: /admin/team.php?status=saved', true, 302);
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
