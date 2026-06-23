<?php
declare(strict_types=1);

function pkks_admin_audit_log_path(): string
{
    return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'admin-audit.log';
}

function pkks_admin_filter_audit_context(array $context): array
{
    $blockedFragments = ['password', 'csrf', 'token', 'session'];
    $filtered = [];

    foreach ($context as $key => $value) {
        $normalizedKey = strtolower((string)$key);

        foreach ($blockedFragments as $fragment) {
            if (str_contains($normalizedKey, $fragment)) {
                continue 2;
            }
        }

        $filtered[$key] = is_scalar($value) || $value === null ? $value : '[filtered]';
    }

    return $filtered;
}

function pkks_admin_write_audit_event(string $event, array $context = []): void
{
    $logPath = pkks_admin_audit_log_path();
    $logDir = dirname($logPath);

    if (!is_dir($logDir)) {
        throw new RuntimeException('Admin audit log directory is missing.');
    }

    $entry = [
        'time' => gmdate(DATE_ATOM),
        'event' => $event,
        'context' => pkks_admin_filter_audit_context($context),
    ];

    $json = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    if ($json === false) {
        throw new RuntimeException('Admin audit event encoding failed.');
    }

    file_put_contents($logPath, $json . PHP_EOL, FILE_APPEND | LOCK_EX);
}
