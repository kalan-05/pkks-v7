<?php
declare(strict_types=1);

function pkks_load_json(string $path): array
{
    if (!file_exists($path)) {
        throw new RuntimeException('JSON file not found: ' . $path);
    }

    $contents = file_get_contents($path);

    if ($contents === false) {
        throw new RuntimeException('Unable to read JSON file: ' . $path);
    }

    try {
        $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $exception) {
        throw new RuntimeException('Invalid JSON file: ' . $path, 0, $exception);
    }

    if (!is_array($decoded)) {
        throw new RuntimeException('JSON root must be an array: ' . $path);
    }

    return $decoded;
}

function pkks_escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function pkks_string_value(mixed $value): string
{
    return is_scalar($value) ? (string) $value : '';
}

function pkks_visible_sorted(array $items): array
{
    $visibleItems = array_values(array_filter(
        $items,
        static fn (mixed $item): bool => is_array($item) && ($item['visible'] ?? false) === true
    ));

    usort(
        $visibleItems,
        static fn (array $left, array $right): int => ((int) ($left['sortOrder'] ?? 0)) <=> ((int) ($right['sortOrder'] ?? 0))
    );

    return $visibleItems;
}
