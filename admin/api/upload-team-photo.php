<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/audit-log.php';
require_once __DIR__ . '/../includes/team-storage.php';

const PKKS_ADMIN_TEAM_PHOTO_MAX_BYTES = 3145728;
const PKKS_ADMIN_TEAM_PHOTO_ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];
const PKKS_ADMIN_TEAM_PHOTO_ALLOWED_MIME = ['image/jpeg', 'image/png', 'image/webp'];

pkks_admin_require_auth();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo 'Метод не поддерживается.';
    exit;
}

$currentLogin = pkks_admin_current_login() ?? 'администратор';
$movedPhotoPath = null;

try {
    if (!pkks_admin_verify_csrf_token(pkks_admin_team_photo_post_string('csrf_token'))) {
        pkks_admin_team_photo_fail($currentLogin, ['Сессия устарела. Обновите страницу и повторите загрузку.']);
    }

    $employeeId = pkks_admin_team_photo_post_string('employee_id');

    if ($employeeId === '') {
        pkks_admin_team_photo_fail($currentLogin, ['Не выбран сотрудник для замены фото.']);
    }

    $currentData = pkks_admin_load_team_data();
    $employee = pkks_admin_find_team_employee($currentData, $employeeId);

    if ($employee === null) {
        pkks_admin_team_photo_fail($currentLogin, ['Сотрудник не найден в data/team.json.'], $employeeId);
    }

    $metadataErrors = [];
    $metadata = pkks_admin_normalize_team_photo_metadata(
        $employee,
        pkks_admin_team_photo_post_string('photoAlt'),
        pkks_admin_team_photo_post_string('photoTitle'),
        $metadataErrors
    );

    if ($metadataErrors !== []) {
        pkks_admin_team_photo_fail($currentLogin, $metadataErrors, $employeeId);
    }

    $uploadErrors = [];
    $upload = pkks_admin_validate_team_photo_upload($_FILES['photo'] ?? null, $uploadErrors);

    if ($uploadErrors !== []) {
        pkks_admin_team_photo_fail($currentLogin, $uploadErrors, $employeeId);
    }

    $targetDir = pkks_admin_team_photo_target_dir();
    $filename = pkks_admin_generate_team_photo_filename($employeeId, $upload['extension'], $targetDir);
    $targetPath = $targetDir . DIRECTORY_SEPARATOR . $filename;
    pkks_admin_assert_team_photo_target_path($targetDir, $targetPath);

    if (!move_uploaded_file($upload['tmp_name'], $targetPath)) {
        throw new RuntimeException('Team photo moving failed.');
    }

    $movedPhotoPath = $targetPath;
    $photoPath = 'img/team/' . $filename;
    $nextData = pkks_admin_update_team_employee_photo_fields(
        $currentData,
        $employeeId,
        $photoPath,
        $metadata['photoAlt'],
        $metadata['photoTitle']
    );
    $backupPath = pkks_admin_backup_team_data();

    pkks_admin_write_team_data($nextData);
    pkks_admin_write_audit_event('team_photo_update', [
        'login' => $currentLogin,
        'employee_id' => $employeeId,
        'photo' => $photoPath,
        'backup_file' => basename($backupPath),
        'mime_check_source' => $upload['mime_check_source'],
    ]);

    $_SESSION['admin_flash'] = [
        'type' => 'success',
        'title' => 'Фото сотрудника обновлено.',
        'messages' => ['Резервная копия создана: ' . basename($backupPath) . '.'],
    ];

    header('Location: /admin/team.php?status=photo-saved', true, 302);
    exit;
} catch (Throwable) {
    if ($movedPhotoPath !== null && is_file($movedPhotoPath)) {
        unlink($movedPhotoPath);
    }

    try {
        pkks_admin_write_audit_event('team_photo_update_failed', [
            'login' => $currentLogin,
            'employee_id' => pkks_admin_team_photo_post_string('employee_id'),
        ]);
    } catch (Throwable) {
    }

    $_SESSION['admin_flash'] = [
        'type' => 'error',
        'title' => 'Фото не обновлено.',
        'messages' => ['Не удалось сохранить фото. Проверьте файл и повторите попытку.'],
    ];

    header('Location: /admin/team.php?status=photo-error', true, 302);
    exit;
}

function pkks_admin_team_photo_fail(string $currentLogin, array $messages, string $employeeId = ''): void
{
    try {
        pkks_admin_write_audit_event('team_photo_update_failed', [
            'login' => $currentLogin,
            'employee_id' => $employeeId,
            'message_count' => count($messages),
        ]);
    } catch (Throwable) {
    }

    $_SESSION['admin_flash'] = [
        'type' => 'error',
        'title' => 'Фото не обновлено.',
        'messages' => $messages,
    ];

    header('Location: /admin/team.php?status=photo-error', true, 302);
    exit;
}

function pkks_admin_team_photo_post_string(string $key): string
{
    $value = $_POST[$key] ?? '';

    return is_scalar($value) ? trim((string)$value) : '';
}

function pkks_admin_validate_team_photo_upload(mixed $file, array &$errors): array
{
    if (!is_array($file)) {
        $errors[] = 'Файл фото обязателен для загрузки.';
        return [];
    }

    $errorCode = isset($file['error']) && is_numeric($file['error']) ? (int)$file['error'] : UPLOAD_ERR_NO_FILE;

    if ($errorCode !== UPLOAD_ERR_OK) {
        $errors[] = $errorCode === UPLOAD_ERR_NO_FILE
            ? 'Выберите файл фото.'
            : 'Файл не был загружен корректно.';
        return [];
    }

    $tmpName = isset($file['tmp_name']) && is_string($file['tmp_name']) ? $file['tmp_name'] : '';

    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        $errors[] = 'Файл должен быть загружен через форму.';
        return [];
    }

    $reportedSize = isset($file['size']) && is_numeric($file['size']) ? (int)$file['size'] : 0;
    $actualSize = filesize($tmpName);
    $size = max($reportedSize, $actualSize === false ? 0 : $actualSize);

    if ($size <= 0) {
        $errors[] = 'Файл фото пустой.';
    }

    if ($size > PKKS_ADMIN_TEAM_PHOTO_MAX_BYTES) {
        $errors[] = 'Файл фото должен быть не больше 3 MB.';
    }

    $originalName = isset($file['name']) && is_string($file['name']) ? $file['name'] : '';
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $blockedExtensions = ['svg', 'php', 'phtml', 'html', 'js', 'pdf', 'doc', 'docx', 'exe'];

    if (in_array($extension, $blockedExtensions, true) || !in_array($extension, PKKS_ADMIN_TEAM_PHOTO_ALLOWED_EXTENSIONS, true)) {
        $errors[] = 'Разрешены только файлы jpg, jpeg, png или webp.';
    }

    $mime = '';
    $mimeCheckSource = 'getimagesize';

    if (extension_loaded('fileinfo') && function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);

        if ($finfo === false) {
            $errors[] = 'Не удалось проверить MIME файла.';
            return [];
        }

        $detectedMime = finfo_file($finfo, $tmpName);
        finfo_close($finfo);

        $mime = is_string($detectedMime) ? $detectedMime : '';
        $mimeCheckSource = 'finfo';
    }

    if ($mimeCheckSource === 'finfo' && !in_array($mime, PKKS_ADMIN_TEAM_PHOTO_ALLOWED_MIME, true)) {
        $errors[] = 'MIME файла не соответствует разрешённым изображениям.';
    }

    $imageInfo = @getimagesize($tmpName);

    if (!is_array($imageInfo)) {
        $errors[] = 'Файл не является валидным изображением.';
    }

    $imageMime = is_array($imageInfo) && is_string($imageInfo['mime'] ?? null) ? $imageInfo['mime'] : '';

    if ($imageMime === '' || !in_array($imageMime, PKKS_ADMIN_TEAM_PHOTO_ALLOWED_MIME, true)) {
        $errors[] = 'MIME изображения не соответствует разрешённым форматам.';
    }

    if ($mimeCheckSource === 'finfo' && $mime !== '' && $imageMime !== '' && $mime !== $imageMime) {
        $errors[] = 'MIME файла не совпадает с MIME изображения.';
    }

    $extensionMimeMap = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
    ];

    $validatedMime = $mimeCheckSource === 'finfo' ? $mime : $imageMime;

    if (isset($extensionMimeMap[$extension]) && $validatedMime !== $extensionMimeMap[$extension]) {
        $errors[] = 'Расширение файла не совпадает с типом изображения.';
    }

    if ($errors !== []) {
        return [];
    }

    return [
        'tmp_name' => $tmpName,
        'extension' => $extension,
        'mime' => $validatedMime,
        'mime_check_source' => $mimeCheckSource,
    ];
}

function pkks_admin_team_photo_target_dir(): string
{
    $targetDir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'img' . DIRECTORY_SEPARATOR . 'team';

    if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true)) {
        throw new RuntimeException('Team photo directory creation failed.');
    }

    if (!is_writable($targetDir)) {
        throw new RuntimeException('Team photo directory is not writable.');
    }

    return $targetDir;
}

function pkks_admin_generate_team_photo_filename(string $employeeId, string $extension, string $targetDir): string
{
    $slug = strtolower($employeeId);
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    $slug = is_string($slug) ? trim($slug, '-') : '';
    $slug = $slug !== '' ? $slug : 'employee';

    for ($attempt = 0; $attempt < 20; $attempt++) {
        $candidate = 'employee-' . $slug . '-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.' . $extension;

        if (!file_exists($targetDir . DIRECTORY_SEPARATOR . $candidate)) {
            return $candidate;
        }
    }

    throw new RuntimeException('Unique team photo filename generation failed.');
}

function pkks_admin_assert_team_photo_target_path(string $targetDir, string $targetPath): void
{
    $realTargetDir = realpath($targetDir);
    $realTargetParent = realpath(dirname($targetPath));

    if ($realTargetDir === false || $realTargetParent === false || $realTargetDir !== $realTargetParent) {
        throw new RuntimeException('Invalid team photo target path.');
    }
}
