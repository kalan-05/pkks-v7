<?php
declare(strict_types=1);

function pkks_admin_team_data_path(): string
{
    return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'team.json';
}

function pkks_admin_team_backup_dir(): string
{
    return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'backups' . DIRECTORY_SEPARATOR . 'team';
}

function pkks_admin_load_team_data(): array
{
    $path = pkks_admin_team_data_path();

    if (!is_file($path)) {
        throw new RuntimeException('Team JSON file not found.');
    }

    $contents = file_get_contents($path);

    if ($contents === false) {
        throw new RuntimeException('Team JSON file reading failed.');
    }

    try {
        $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $exception) {
        throw new RuntimeException('Team JSON file is invalid.', 0, $exception);
    }

    if (!is_array($decoded)) {
        throw new RuntimeException('Team JSON root must be an object.');
    }

    return $decoded;
}

function pkks_admin_validate_team_payload(array $post, array $currentData): array
{
    $errors = [];
    $formData = [];
    $nextData = $currentData;
    $currentEmployees = isset($currentData['employees']) && is_array($currentData['employees'])
        ? $currentData['employees']
        : [];
    $submittedEmployees = isset($post['employees']) && is_array($post['employees'])
        ? $post['employees']
        : [];
    $knownIds = [];

    if ($currentEmployees === []) {
        $errors[] = 'В data/team.json нет сотрудников для редактирования.';
    }

    foreach ($currentEmployees as $employee) {
        if (!is_array($employee)) {
            $errors[] = 'В data/team.json найден некорректный сотрудник.';
            continue;
        }

        $employeeId = isset($employee['id']) && is_scalar($employee['id'])
            ? trim((string)$employee['id'])
            : '';

        if ($employeeId === '') {
            $errors[] = 'В data/team.json найден сотрудник без id.';
            continue;
        }

        if (isset($knownIds[$employeeId])) {
            $errors[] = 'В data/team.json найден повторяющийся id сотрудника: ' . $employeeId . '.';
            continue;
        }

        $knownIds[$employeeId] = true;
    }

    foreach ($submittedEmployees as $submittedId => $submittedEmployee) {
        $submittedId = (string)$submittedId;

        if (!isset($knownIds[$submittedId])) {
            $errors[] = 'Нельзя добавить нового сотрудника через форму: ' . $submittedId . '.';
        }

        if (!is_array($submittedEmployee)) {
            $errors[] = 'Некорректные данные сотрудника: ' . $submittedId . '.';
        }
    }

    $nextEmployees = [];

    foreach ($currentEmployees as $employee) {
        if (!is_array($employee)) {
            continue;
        }

        $employeeId = isset($employee['id']) && is_scalar($employee['id'])
            ? trim((string)$employee['id'])
            : '';

        if ($employeeId === '') {
            continue;
        }

        if (!array_key_exists($employeeId, $submittedEmployees) || !is_array($submittedEmployees[$employeeId])) {
            $errors[] = 'Данные сотрудника "' . $employeeId . '" отсутствуют в форме.';
            $formData[$employeeId] = pkks_admin_current_employee_form_data($employee);
            $nextEmployees[] = $employee;
            continue;
        }

        $submitted = $submittedEmployees[$employeeId];
        $visible = array_key_exists('visible', $submitted);
        $sortOrderRaw = pkks_admin_scalar_to_string($submitted['sortOrder'] ?? '');
        $fullNameRaw = pkks_admin_scalar_to_string($submitted['fullName'] ?? '');
        $positionRaw = pkks_admin_scalar_to_string($submitted['position'] ?? '');
        $educationRaw = pkks_admin_scalar_to_string($submitted['education'] ?? '');

        $formData[$employeeId] = [
            'visible' => $visible,
            'sortOrder' => $sortOrderRaw,
            'fullName' => $fullNameRaw,
            'position' => $positionRaw,
            'education' => $educationRaw,
        ];

        $sortOrder = filter_var($sortOrderRaw, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 999],
        ]);

        if ($sortOrder === false) {
            $errors[] = 'Порядок сортировки сотрудника "' . $employeeId . '" должен быть числом от 1 до 999.';
            $sortOrder = (int)($employee['sortOrder'] ?? 0);
        }

        $fullName = pkks_admin_validate_plain_text($fullNameRaw, 'ФИО сотрудника "' . $employeeId . '"', 120, true, $errors);
        $position = pkks_admin_validate_plain_text($positionRaw, 'Должность сотрудника "' . $employeeId . '"', 160, true, $errors);
        $educationItems = pkks_admin_split_lines($educationRaw);

        foreach ($educationItems as $educationItem) {
            pkks_admin_validate_plain_text($educationItem, 'Образование сотрудника "' . $employeeId . '"', 300, true, $errors);
        }

        if ($visible && $educationItems === []) {
            $errors[] = 'Для видимого сотрудника "' . $employeeId . '" нужен хотя бы один пункт образования.';
        }

        $nextEmployee = $employee;
        $nextEmployee['visible'] = $visible;
        $nextEmployee['sortOrder'] = $sortOrder;
        $nextEmployee['fullName'] = $fullName;
        $nextEmployee['position'] = $position;
        $nextEmployee['education'] = $educationItems;
        $nextEmployees[] = $nextEmployee;
    }

    $nextData['employees'] = $nextEmployees;

    return [
        'teamData' => $nextData,
        'errors' => array_values(array_unique($errors)),
        'formData' => $formData,
    ];
}

function pkks_admin_backup_team_data(): string
{
    $dataPath = pkks_admin_team_data_path();
    $backupDir = pkks_admin_team_backup_dir();

    if (!is_file($dataPath)) {
        throw new RuntimeException('Team JSON file not found for backup.');
    }

    if (!is_dir($backupDir) && !mkdir($backupDir, 0775, true)) {
        throw new RuntimeException('Team backup directory creation failed.');
    }

    $backupPath = null;

    for ($attempt = 0; $attempt < 20; $attempt++) {
        $candidate = $backupDir . DIRECTORY_SEPARATOR . 'team-' . date('Ymd-His') . '.json';

        if (!file_exists($candidate)) {
            $backupPath = $candidate;
            break;
        }

        usleep(100000);
    }

    if ($backupPath === null || !copy($dataPath, $backupPath) || !is_file($backupPath)) {
        throw new RuntimeException('Team backup creation failed.');
    }

    return $backupPath;
}

function pkks_admin_write_team_data(array $teamData): void
{
    $dataPath = pkks_admin_team_data_path();
    $dataDir = dirname($dataPath);
    $tempPath = $dataDir . DIRECTORY_SEPARATOR . 'team.json.tmp.' . getmypid() . '.' . bin2hex(random_bytes(6));

    try {
        $json = json_encode(
            $teamData,
            JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );

        if (file_put_contents($tempPath, $json . PHP_EOL, LOCK_EX) === false) {
            throw new RuntimeException('Temporary team JSON writing failed.');
        }

        if (!rename($tempPath, $dataPath)) {
            throw new RuntimeException('Atomic team JSON rename failed.');
        }
    } catch (Throwable $exception) {
        if (is_file($tempPath)) {
            unlink($tempPath);
        }

        throw $exception;
    }
}

function pkks_admin_split_lines(string $value): array
{
    $lines = preg_split('/\R/u', $value);

    if (!is_array($lines)) {
        $lines = explode("\n", $value);
    }

    $result = [];

    foreach ($lines as $line) {
        $line = trim((string)$line);

        if ($line !== '') {
            $result[] = $line;
        }
    }

    return $result;
}

function pkks_admin_current_employee_form_data(array $employee): array
{
    $education = isset($employee['education']) && is_array($employee['education'])
        ? implode(PHP_EOL, array_map(static fn (mixed $item): string => pkks_admin_scalar_to_string($item), $employee['education']))
        : '';

    return [
        'visible' => ($employee['visible'] ?? false) === true,
        'sortOrder' => pkks_admin_scalar_to_string($employee['sortOrder'] ?? ''),
        'fullName' => pkks_admin_scalar_to_string($employee['fullName'] ?? ''),
        'position' => pkks_admin_scalar_to_string($employee['position'] ?? ''),
        'education' => $education,
    ];
}

function pkks_admin_scalar_to_string(mixed $value): string
{
    return is_scalar($value) ? trim((string)$value) : '';
}

function pkks_admin_validate_plain_text(
    string $value,
    string $fieldLabel,
    int $maxLength,
    bool $required,
    array &$errors
): string {
    $value = trim($value);

    if ($required && $value === '') {
        $errors[] = $fieldLabel . ' обязательно для заполнения.';
        return $value;
    }

    if ($value === '') {
        return $value;
    }

    if (str_contains($value, '<') || str_contains($value, '>')) {
        $errors[] = $fieldLabel . ' не должно содержать HTML-теги.';
    }

    if (preg_match('/\bon[a-z]+\s*=/iu', $value) === 1) {
        $errors[] = $fieldLabel . ' не должно содержать HTML-атрибуты событий.';
    }

    if (pkks_admin_utf8_length($value) > $maxLength) {
        $errors[] = $fieldLabel . ' не должно быть длиннее ' . $maxLength . ' символов.';
    }

    return $value;
}

function pkks_admin_utf8_length(string $value): int
{
    if (function_exists('mb_strlen')) {
        return mb_strlen($value, 'UTF-8');
    }

    $length = preg_match_all('/./us', $value, $matches);

    return $length === false ? strlen($value) : $length;
}
