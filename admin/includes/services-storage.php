<?php
declare(strict_types=1);

function pkks_admin_services_data_path(): string
{
    return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'services.json';
}

function pkks_admin_services_backup_dir(): string
{
    return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'backups' . DIRECTORY_SEPARATOR . 'services';
}

function pkks_admin_load_services_data(): array
{
    $path = pkks_admin_services_data_path();

    if (!is_file($path)) {
        throw new RuntimeException('Services JSON file not found.');
    }

    $contents = file_get_contents($path);

    if ($contents === false) {
        throw new RuntimeException('Services JSON file reading failed.');
    }

    try {
        $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $exception) {
        throw new RuntimeException('Services JSON file is invalid.', 0, $exception);
    }

    if (!is_array($decoded)) {
        throw new RuntimeException('Services JSON root must be an object.');
    }

    pkks_admin_assert_services_structure($decoded);

    return $decoded;
}

function pkks_admin_validate_services_payload(array $post, array $currentData): array
{
    pkks_admin_assert_services_structure($currentData);

    $errors = [];
    $formData = [];
    $nextData = $currentData;
    $currentGroups = $currentData['serviceGroups'];
    $submittedGroups = isset($post['serviceGroups']) && is_array($post['serviceGroups'])
        ? $post['serviceGroups']
        : [];

    pkks_admin_validate_services_index_set(
        $submittedGroups,
        count($currentGroups),
        'Количество групп услуг в форме не совпадает с data/services.json.',
        'Нельзя изменить структуру групп услуг через форму.',
        $errors
    );

    $nextGroups = [];

    foreach ($currentGroups as $groupIndex => $group) {
        $groupId = pkks_admin_services_required_id($group['id'] ?? null, 'группы услуг #' . ($groupIndex + 1));
        $submittedGroup = pkks_admin_services_index_array($submittedGroups, (int)$groupIndex);

        if ($submittedGroup === null) {
            $errors[] = 'Данные группы услуг "' . $groupId . '" отсутствуют в форме.';
            $formData[$groupIndex] = pkks_admin_current_service_group_form_data($group);
            $nextGroups[] = $group;
            continue;
        }

        $visible = array_key_exists('visible', $submittedGroup);
        $sortOrderRaw = pkks_admin_services_scalar_to_string($submittedGroup['sortOrder'] ?? '');
        $titleRaw = pkks_admin_services_scalar_to_string($submittedGroup['title'] ?? '');

        $formData[$groupIndex] = [
            'visible' => $visible,
            'sortOrder' => $sortOrderRaw,
            'title' => $titleRaw,
            'cards' => [],
        ];

        $sortOrder = pkks_admin_services_validate_sort_order(
            $sortOrderRaw,
            'Порядок сортировки группы услуг "' . $groupId . '"',
            (int)($group['sortOrder'] ?? 0),
            $errors
        );
        $title = pkks_admin_services_validate_plain_text(
            $titleRaw,
            'Заголовок группы услуг "' . $groupId . '"',
            500,
            true,
            $errors
        );

        $currentCards = $group['cards'];
        $submittedCards = isset($submittedGroup['cards']) && is_array($submittedGroup['cards'])
            ? $submittedGroup['cards']
            : [];

        pkks_admin_validate_services_index_set(
            $submittedCards,
            count($currentCards),
            'Количество карточек группы услуг "' . $groupId . '" не совпадает с data/services.json.',
            'Нельзя изменить структуру карточек группы услуг "' . $groupId . '" через форму.',
            $errors
        );

        $nextCards = [];

        foreach ($currentCards as $cardIndex => $card) {
            $cardId = pkks_admin_services_required_id($card['id'] ?? null, 'карточки услуг #' . ($cardIndex + 1));
            $submittedCard = pkks_admin_services_index_array($submittedCards, (int)$cardIndex);

            if ($submittedCard === null) {
                $errors[] = 'Данные карточки услуг "' . $cardId . '" отсутствуют в форме.';
                $formData[$groupIndex]['cards'][$cardIndex] = pkks_admin_current_service_card_form_data($card);
                $nextCards[] = $card;
                continue;
            }

            $cardVisible = array_key_exists('visible', $submittedCard);
            $cardSortOrderRaw = pkks_admin_services_scalar_to_string($submittedCard['sortOrder'] ?? '');
            $cardTitleRaw = pkks_admin_services_scalar_to_string($submittedCard['title'] ?? '');
            $itemsRaw = pkks_admin_services_scalar_to_string($submittedCard['items'] ?? '');

            $formData[$groupIndex]['cards'][$cardIndex] = [
                'visible' => $cardVisible,
                'sortOrder' => $cardSortOrderRaw,
                'title' => $cardTitleRaw,
                'items' => $itemsRaw,
            ];

            $cardSortOrder = pkks_admin_services_validate_sort_order(
                $cardSortOrderRaw,
                'Порядок сортировки карточки услуг "' . $cardId . '"',
                (int)($card['sortOrder'] ?? 0),
                $errors
            );
            $cardTitle = pkks_admin_services_validate_plain_text(
                $cardTitleRaw,
                'Заголовок карточки услуг "' . $cardId . '"',
                300,
                true,
                $errors
            );
            $items = pkks_admin_services_split_lines($itemsRaw);

            foreach ($items as $item) {
                pkks_admin_services_validate_plain_text(
                    $item,
                    'Пункт карточки услуг "' . $cardId . '"',
                    500,
                    true,
                    $errors
                );
            }

            $nextCard = $card;
            $nextCard['visible'] = $cardVisible;
            $nextCard['sortOrder'] = $cardSortOrder;
            $nextCard['title'] = $cardTitle;
            $nextCard['items'] = $items;
            $nextCards[] = $nextCard;
        }

        $nextGroup = $group;
        $nextGroup['visible'] = $visible;
        $nextGroup['sortOrder'] = $sortOrder;
        $nextGroup['title'] = $title;
        $nextGroup['cards'] = $nextCards;
        $nextGroups[] = $nextGroup;
    }

    $nextData['serviceGroups'] = $nextGroups;

    if (array_key_exists('updatedAt', $nextData)) {
        $nextData['updatedAt'] = date(DATE_ATOM);
    }

    return [
        'servicesData' => $nextData,
        'errors' => array_values(array_unique($errors)),
        'formData' => $formData,
    ];
}

function pkks_admin_backup_services_data(): string
{
    $dataPath = pkks_admin_services_data_path();
    $backupDir = pkks_admin_services_backup_dir();

    if (!is_file($dataPath)) {
        throw new RuntimeException('Services JSON file not found for backup.');
    }

    if (!is_dir($backupDir) && !mkdir($backupDir, 0775, true)) {
        throw new RuntimeException('Services backup directory creation failed.');
    }

    $backupPath = null;

    for ($attempt = 0; $attempt < 20; $attempt++) {
        $candidate = $backupDir . DIRECTORY_SEPARATOR . 'services-' . date('Ymd-His') . '.json';

        if (!file_exists($candidate)) {
            $backupPath = $candidate;
            break;
        }

        usleep(100000);
    }

    if ($backupPath === null || !copy($dataPath, $backupPath) || !is_file($backupPath)) {
        throw new RuntimeException('Services backup creation failed.');
    }

    return $backupPath;
}

function pkks_admin_write_services_data(array $servicesData): void
{
    pkks_admin_assert_services_structure($servicesData);

    $dataPath = pkks_admin_services_data_path();
    $dataDir = dirname($dataPath);
    $tempPath = $dataDir . DIRECTORY_SEPARATOR . 'services.json.tmp.' . getmypid() . '.' . bin2hex(random_bytes(6));

    try {
        $json = json_encode(
            $servicesData,
            JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );

        if (file_put_contents($tempPath, $json . PHP_EOL, LOCK_EX) === false) {
            throw new RuntimeException('Temporary services JSON writing failed.');
        }

        if (!rename($tempPath, $dataPath)) {
            throw new RuntimeException('Atomic services JSON rename failed.');
        }
    } catch (Throwable $exception) {
        if (is_file($tempPath)) {
            unlink($tempPath);
        }

        throw $exception;
    }
}

function pkks_admin_services_count_groups(array $servicesData): int
{
    return isset($servicesData['serviceGroups']) && is_array($servicesData['serviceGroups'])
        ? count($servicesData['serviceGroups'])
        : 0;
}

function pkks_admin_services_count_cards(array $servicesData): int
{
    $count = 0;
    $groups = isset($servicesData['serviceGroups']) && is_array($servicesData['serviceGroups'])
        ? $servicesData['serviceGroups']
        : [];

    foreach ($groups as $group) {
        if (is_array($group) && isset($group['cards']) && is_array($group['cards'])) {
            $count += count($group['cards']);
        }
    }

    return $count;
}

function pkks_admin_services_count_items(array $servicesData): int
{
    $count = 0;
    $groups = isset($servicesData['serviceGroups']) && is_array($servicesData['serviceGroups'])
        ? $servicesData['serviceGroups']
        : [];

    foreach ($groups as $group) {
        if (!is_array($group) || !isset($group['cards']) || !is_array($group['cards'])) {
            continue;
        }

        foreach ($group['cards'] as $card) {
            if (is_array($card) && isset($card['items']) && is_array($card['items'])) {
                $count += count($card['items']);
            }
        }
    }

    return $count;
}

function pkks_admin_assert_services_structure(array $servicesData): void
{
    if (!array_key_exists('schemaVersion', $servicesData) || !is_int($servicesData['schemaVersion'])) {
        throw new RuntimeException('Services JSON schemaVersion must be integer.');
    }

    if (!isset($servicesData['updatedAt']) || !is_scalar($servicesData['updatedAt']) || trim((string)$servicesData['updatedAt']) === '') {
        throw new RuntimeException('Services JSON updatedAt must be a non-empty string.');
    }

    if (!isset($servicesData['serviceGroups']) || !is_array($servicesData['serviceGroups'])) {
        throw new RuntimeException('Services JSON must contain serviceGroups array.');
    }

    if ($servicesData['serviceGroups'] === []) {
        throw new RuntimeException('Services JSON must contain at least one service group.');
    }

    $groupIds = [];

    foreach ($servicesData['serviceGroups'] as $groupIndex => $group) {
        if (!is_array($group)) {
            throw new RuntimeException('Services JSON group must be an object.');
        }

        $groupId = pkks_admin_services_required_id($group['id'] ?? null, 'группы услуг #' . ($groupIndex + 1));

        if (isset($groupIds[$groupId])) {
            throw new RuntimeException('Services JSON contains duplicate group id: ' . $groupId . '.');
        }

        $groupIds[$groupId] = true;

        if (!array_key_exists('visible', $group) || !is_bool($group['visible'])) {
            throw new RuntimeException('Services JSON group visible must be boolean: ' . $groupId . '.');
        }

        if (!array_key_exists('sortOrder', $group) || !is_int($group['sortOrder'])) {
            throw new RuntimeException('Services JSON group sortOrder must be integer: ' . $groupId . '.');
        }

        if (!isset($group['title']) || !is_scalar($group['title']) || trim((string)$group['title']) === '') {
            throw new RuntimeException('Services JSON group title must be a non-empty string: ' . $groupId . '.');
        }

        if (!isset($group['cards']) || !is_array($group['cards'])) {
            throw new RuntimeException('Services JSON group cards must be an array: ' . $groupId . '.');
        }

        $cardIds = [];

        foreach ($group['cards'] as $cardIndex => $card) {
            if (!is_array($card)) {
                throw new RuntimeException('Services JSON card must be an object.');
            }

            $cardId = pkks_admin_services_required_id($card['id'] ?? null, 'карточки услуг #' . ($cardIndex + 1));

            if (isset($cardIds[$cardId])) {
                throw new RuntimeException('Services JSON contains duplicate card id: ' . $cardId . '.');
            }

            $cardIds[$cardId] = true;

            if (!array_key_exists('visible', $card) || !is_bool($card['visible'])) {
                throw new RuntimeException('Services JSON card visible must be boolean: ' . $cardId . '.');
            }

            if (!array_key_exists('sortOrder', $card) || !is_int($card['sortOrder'])) {
                throw new RuntimeException('Services JSON card sortOrder must be integer: ' . $cardId . '.');
            }

            if (!isset($card['title']) || !is_scalar($card['title']) || trim((string)$card['title']) === '') {
                throw new RuntimeException('Services JSON card title must be a non-empty string: ' . $cardId . '.');
            }

            if (!isset($card['items']) || !is_array($card['items'])) {
                throw new RuntimeException('Services JSON card items must be an array: ' . $cardId . '.');
            }

            foreach ($card['items'] as $item) {
                if (!is_scalar($item)) {
                    throw new RuntimeException('Services JSON card item must be a string: ' . $cardId . '.');
                }
            }
        }
    }
}

function pkks_admin_validate_services_index_set(
    array $submitted,
    int $expectedCount,
    string $countMessage,
    string $structureMessage,
    array &$errors
): void {
    if (count($submitted) !== $expectedCount) {
        $errors[] = $countMessage;
    }

    foreach (array_keys($submitted) as $submittedKey) {
        $key = (string)$submittedKey;

        if ($key === '' || preg_match('/^\d+$/', $key) !== 1 || (int)$key < 0 || (int)$key >= $expectedCount) {
            $errors[] = $structureMessage;
            return;
        }
    }
}

function pkks_admin_services_index_array(array $items, int $index): ?array
{
    if (!array_key_exists($index, $items) || !is_array($items[$index])) {
        return null;
    }

    return $items[$index];
}

function pkks_admin_services_validate_sort_order(
    string $value,
    string $fieldLabel,
    int $fallback,
    array &$errors
): int {
    $sortOrder = filter_var($value, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 0, 'max_range' => 9999],
    ]);

    if ($sortOrder === false) {
        $errors[] = $fieldLabel . ' должен быть целым числом от 0 до 9999.';
        return $fallback;
    }

    return $sortOrder;
}

function pkks_admin_services_split_lines(string $value): array
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

function pkks_admin_current_service_group_form_data(array $group): array
{
    return [
        'visible' => ($group['visible'] ?? false) === true,
        'sortOrder' => pkks_admin_services_scalar_to_string($group['sortOrder'] ?? ''),
        'title' => pkks_admin_services_scalar_to_string($group['title'] ?? ''),
        'cards' => [],
    ];
}

function pkks_admin_current_service_card_form_data(array $card): array
{
    $items = isset($card['items']) && is_array($card['items'])
        ? implode(PHP_EOL, array_map(static fn (mixed $item): string => pkks_admin_services_scalar_to_string($item), $card['items']))
        : '';

    return [
        'visible' => ($card['visible'] ?? false) === true,
        'sortOrder' => pkks_admin_services_scalar_to_string($card['sortOrder'] ?? ''),
        'title' => pkks_admin_services_scalar_to_string($card['title'] ?? ''),
        'items' => $items,
    ];
}

function pkks_admin_services_scalar_to_string(mixed $value): string
{
    return is_scalar($value) ? trim((string)$value) : '';
}

function pkks_admin_services_required_id(mixed $value, string $label): string
{
    $id = pkks_admin_services_scalar_to_string($value);

    if ($id === '') {
        throw new RuntimeException('Services JSON contains item without id: ' . $label . '.');
    }

    return $id;
}

function pkks_admin_services_validate_plain_text(
    string $value,
    string $fieldLabel,
    int $maxLength,
    bool $required,
    array &$errors
): string {
    $value = trim($value);

    if ($required && $value === '') {
        $errors[] = $fieldLabel . ' обязателен для заполнения.';
        return $value;
    }

    if ($value === '') {
        return $value;
    }

    if (str_contains($value, '<') || str_contains($value, '>')) {
        $errors[] = $fieldLabel . ' не должен содержать HTML-теги.';
    }

    if (preg_match('/\bon[a-z]+\s*=/iu', $value) === 1) {
        $errors[] = $fieldLabel . ' не должен содержать HTML-атрибуты событий.';
    }

    if (pkks_admin_services_utf8_length($value) > $maxLength) {
        $errors[] = $fieldLabel . ' не должен быть длиннее ' . $maxLength . ' символов.';
    }

    return $value;
}

function pkks_admin_services_utf8_length(string $value): int
{
    if (function_exists('mb_strlen')) {
        return mb_strlen($value, 'UTF-8');
    }

    $length = preg_match_all('/./us', $value, $matches);

    return $length === false ? strlen($value) : $length;
}
