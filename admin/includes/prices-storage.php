<?php
declare(strict_types=1);

function pkks_admin_prices_data_path(): string
{
    return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'prices.json';
}

function pkks_admin_prices_backup_dir(): string
{
    return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'backups' . DIRECTORY_SEPARATOR . 'prices';
}

function pkks_admin_load_prices_data(): array
{
    $path = pkks_admin_prices_data_path();

    if (!is_file($path)) {
        throw new RuntimeException('Prices JSON file not found.');
    }

    $contents = file_get_contents($path);

    if ($contents === false) {
        throw new RuntimeException('Prices JSON file reading failed.');
    }

    try {
        $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $exception) {
        throw new RuntimeException('Prices JSON file is invalid.', 0, $exception);
    }

    if (!is_array($decoded)) {
        throw new RuntimeException('Prices JSON root must be an object.');
    }

    pkks_admin_assert_prices_structure($decoded);

    return $decoded;
}

function pkks_admin_validate_prices_payload(array $post, array $currentData): array
{
    pkks_admin_assert_prices_structure($currentData);

    $errors = [];
    $formData = [
        'prices' => [],
        'notes' => [],
    ];
    $nextData = $currentData;
    $currentPrices = $currentData['prices'];
    $submittedPrices = isset($post['prices']) && is_array($post['prices'])
        ? $post['prices']
        : [];

    pkks_admin_validate_prices_index_set(
        $submittedPrices,
        count($currentPrices),
        'Количество тарифов в форме не совпадает с data/prices.json.',
        'Нельзя изменить структуру тарифов через форму.',
        $errors
    );

    $nextPrices = [];

    foreach ($currentPrices as $priceIndex => $price) {
        $priceId = pkks_admin_prices_required_id($price['id'] ?? null, 'тарифа #' . ($priceIndex + 1));
        $submittedPrice = pkks_admin_prices_index_array($submittedPrices, (int)$priceIndex);

        if ($submittedPrice === null) {
            $errors[] = 'Данные тарифа "' . $priceId . '" отсутствуют в форме.';
            $formData['prices'][$priceIndex] = pkks_admin_current_price_form_data($price);
            $nextPrices[] = $price;
            continue;
        }

        $visible = array_key_exists('visible', $submittedPrice);
        $sortOrderRaw = pkks_admin_prices_scalar_to_string($submittedPrice['sortOrder'] ?? '');
        $titleRaw = pkks_admin_prices_scalar_to_string($submittedPrice['title'] ?? '');
        $priceRaw = pkks_admin_prices_scalar_to_string($submittedPrice['price'] ?? '');
        $unitRaw = pkks_admin_prices_scalar_to_string($submittedPrice['unit'] ?? '');

        $formData['prices'][$priceIndex] = [
            'visible' => $visible,
            'sortOrder' => $sortOrderRaw,
            'title' => $titleRaw,
            'price' => $priceRaw,
            'unit' => $unitRaw,
        ];

        $sortOrder = pkks_admin_prices_validate_sort_order(
            $sortOrderRaw,
            'Порядок сортировки тарифа "' . $priceId . '"',
            (int)($price['sortOrder'] ?? 0),
            $errors
        );
        $title = pkks_admin_prices_validate_plain_text(
            $titleRaw,
            'Название тарифа "' . $priceId . '"',
            240,
            true,
            $errors
        );
        $priceValue = pkks_admin_prices_validate_plain_text(
            $priceRaw,
            'Цена тарифа "' . $priceId . '"',
            120,
            true,
            $errors
        );
        $unit = pkks_admin_prices_validate_plain_text(
            $unitRaw,
            'Единица тарифа "' . $priceId . '"',
            80,
            true,
            $errors
        );

        $nextPrice = $price;
        $nextPrice['visible'] = $visible;
        $nextPrice['sortOrder'] = $sortOrder;
        $nextPrice['title'] = $title;
        $nextPrice['price'] = $priceValue;
        $nextPrice['unit'] = $unit;
        $nextPrices[] = $nextPrice;
    }

    $currentNotes = $currentData['notes'];
    $submittedNotes = isset($post['notes']) && is_array($post['notes'])
        ? $post['notes']
        : [];

    pkks_admin_validate_prices_index_set(
        $submittedNotes,
        count($currentNotes),
        'Количество примечаний в форме не совпадает с data/prices.json.',
        'Нельзя изменить структуру примечаний через форму.',
        $errors
    );

    $nextNotes = [];

    foreach ($currentNotes as $noteIndex => $note) {
        if (!array_key_exists($noteIndex, $submittedNotes) || !is_scalar($submittedNotes[$noteIndex])) {
            $errors[] = 'Данные примечания #' . ((int)$noteIndex + 1) . ' отсутствуют в форме.';
            $formData['notes'][$noteIndex] = pkks_admin_prices_scalar_to_string($note);
            $nextNotes[] = $note;
            continue;
        }

        $noteRaw = pkks_admin_prices_scalar_to_string($submittedNotes[$noteIndex]);
        $formData['notes'][$noteIndex] = $noteRaw;
        $nextNotes[] = pkks_admin_prices_validate_plain_text(
            $noteRaw,
            'Примечание #' . ((int)$noteIndex + 1),
            900,
            true,
            $errors
        );
    }

    $nextData['prices'] = $nextPrices;
    $nextData['notes'] = $nextNotes;

    if (array_key_exists('updatedAt', $nextData)) {
        $nextData['updatedAt'] = date(DATE_ATOM);
    }

    return [
        'pricesData' => $nextData,
        'errors' => array_values(array_unique($errors)),
        'formData' => $formData,
    ];
}

function pkks_admin_backup_prices_data(): string
{
    $dataPath = pkks_admin_prices_data_path();
    $backupDir = pkks_admin_prices_backup_dir();

    if (!is_file($dataPath)) {
        throw new RuntimeException('Prices JSON file not found for backup.');
    }

    if (!is_dir($backupDir) && !mkdir($backupDir, 0775, true)) {
        throw new RuntimeException('Prices backup directory creation failed.');
    }

    $backupPath = null;

    for ($attempt = 0; $attempt < 20; $attempt++) {
        $candidate = $backupDir . DIRECTORY_SEPARATOR . 'prices-' . date('Ymd-His') . '.json';

        if (!file_exists($candidate)) {
            $backupPath = $candidate;
            break;
        }

        usleep(100000);
    }

    if ($backupPath === null || !copy($dataPath, $backupPath) || !is_file($backupPath)) {
        throw new RuntimeException('Prices backup creation failed.');
    }

    return $backupPath;
}

function pkks_admin_write_prices_data(array $pricesData): void
{
    pkks_admin_assert_prices_structure($pricesData);

    $dataPath = pkks_admin_prices_data_path();
    $dataDir = dirname($dataPath);
    $tempPath = $dataDir . DIRECTORY_SEPARATOR . 'prices.json.tmp.' . getmypid() . '.' . bin2hex(random_bytes(6));

    try {
        $json = json_encode(
            $pricesData,
            JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );

        if (file_put_contents($tempPath, $json . PHP_EOL, LOCK_EX) === false) {
            throw new RuntimeException('Temporary prices JSON writing failed.');
        }

        if (!rename($tempPath, $dataPath)) {
            throw new RuntimeException('Atomic prices JSON rename failed.');
        }
    } catch (Throwable $exception) {
        if (is_file($tempPath)) {
            unlink($tempPath);
        }

        throw $exception;
    }
}

function pkks_admin_prices_count_prices(array $pricesData): int
{
    return isset($pricesData['prices']) && is_array($pricesData['prices'])
        ? count($pricesData['prices'])
        : 0;
}

function pkks_admin_prices_count_notes(array $pricesData): int
{
    return isset($pricesData['notes']) && is_array($pricesData['notes'])
        ? count($pricesData['notes'])
        : 0;
}

function pkks_admin_assert_prices_structure(array $pricesData): void
{
    if (!array_key_exists('schemaVersion', $pricesData) || !is_int($pricesData['schemaVersion'])) {
        throw new RuntimeException('Prices JSON schemaVersion must be integer.');
    }

    if (!isset($pricesData['updatedAt']) || !is_scalar($pricesData['updatedAt']) || trim((string)$pricesData['updatedAt']) === '') {
        throw new RuntimeException('Prices JSON updatedAt must be a non-empty string.');
    }

    if (!isset($pricesData['prices']) || !is_array($pricesData['prices'])) {
        throw new RuntimeException('Prices JSON must contain prices array.');
    }

    if ($pricesData['prices'] === []) {
        throw new RuntimeException('Prices JSON must contain at least one price item.');
    }

    if (!isset($pricesData['notes']) || !is_array($pricesData['notes'])) {
        throw new RuntimeException('Prices JSON must contain notes array.');
    }

    $priceIds = [];

    foreach ($pricesData['prices'] as $priceIndex => $price) {
        if (!is_array($price)) {
            throw new RuntimeException('Prices JSON price item must be an object.');
        }

        $priceId = pkks_admin_prices_required_id($price['id'] ?? null, 'тарифа #' . ($priceIndex + 1));

        if (isset($priceIds[$priceId])) {
            throw new RuntimeException('Prices JSON contains duplicate price id: ' . $priceId . '.');
        }

        $priceIds[$priceId] = true;

        if (!array_key_exists('visible', $price) || !is_bool($price['visible'])) {
            throw new RuntimeException('Prices JSON price visible must be boolean: ' . $priceId . '.');
        }

        if (!array_key_exists('sortOrder', $price) || !is_int($price['sortOrder'])) {
            throw new RuntimeException('Prices JSON price sortOrder must be integer: ' . $priceId . '.');
        }

        if (!isset($price['title']) || !is_scalar($price['title']) || trim((string)$price['title']) === '') {
            throw new RuntimeException('Prices JSON price title must be a non-empty string: ' . $priceId . '.');
        }

        if (!isset($price['price']) || !is_scalar($price['price']) || trim((string)$price['price']) === '') {
            throw new RuntimeException('Prices JSON price value must be a non-empty string: ' . $priceId . '.');
        }

        if (!isset($price['unit']) || !is_scalar($price['unit']) || trim((string)$price['unit']) === '') {
            throw new RuntimeException('Prices JSON price unit must be a non-empty string: ' . $priceId . '.');
        }
    }

    foreach ($pricesData['notes'] as $note) {
        if (!is_scalar($note)) {
            throw new RuntimeException('Prices JSON note must be a string.');
        }
    }
}

function pkks_admin_validate_prices_index_set(
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

        if (
            $key === ''
            || preg_match('/^\d+$/', $key) !== 1
            || (string)(int)$key !== $key
            || (int)$key < 0
            || (int)$key >= $expectedCount
        ) {
            $errors[] = $structureMessage;
            return;
        }
    }
}

function pkks_admin_prices_index_array(array $items, int $index): ?array
{
    if (!array_key_exists($index, $items) || !is_array($items[$index])) {
        return null;
    }

    return $items[$index];
}

function pkks_admin_prices_validate_sort_order(
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

function pkks_admin_current_price_form_data(array $price): array
{
    return [
        'visible' => ($price['visible'] ?? false) === true,
        'sortOrder' => pkks_admin_prices_scalar_to_string($price['sortOrder'] ?? ''),
        'title' => pkks_admin_prices_scalar_to_string($price['title'] ?? ''),
        'price' => pkks_admin_prices_scalar_to_string($price['price'] ?? ''),
        'unit' => pkks_admin_prices_scalar_to_string($price['unit'] ?? ''),
    ];
}

function pkks_admin_prices_scalar_to_string(mixed $value): string
{
    return is_scalar($value) ? trim((string)$value) : '';
}

function pkks_admin_prices_required_id(mixed $value, string $label): string
{
    $id = pkks_admin_prices_scalar_to_string($value);

    if ($id === '') {
        throw new RuntimeException('Prices JSON contains item without id: ' . $label . '.');
    }

    return $id;
}

function pkks_admin_prices_validate_plain_text(
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

    if (pkks_admin_prices_utf8_length($value) > $maxLength) {
        $errors[] = $fieldLabel . ' не должен быть длиннее ' . $maxLength . ' символов.';
    }

    return $value;
}

function pkks_admin_prices_utf8_length(string $value): int
{
    if (function_exists('mb_strlen')) {
        return mb_strlen($value, 'UTF-8');
    }

    $length = preg_match_all('/./us', $value, $matches);

    return $length === false ? strlen($value) : $length;
}
