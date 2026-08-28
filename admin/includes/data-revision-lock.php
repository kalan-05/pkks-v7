<?php
declare(strict_types=1);

final class PkksAdminStaleDataException extends RuntimeException
{
}

function pkks_admin_data_revision(string $dataPath): string
{
    $revision = is_file($dataPath) ? hash_file('sha256', $dataPath) : false;

    if (!is_string($revision) || !preg_match('/^[a-f0-9]{64}$/', $revision)) {
        throw new RuntimeException('Data revision cannot be calculated.');
    }

    return $revision;
}

function pkks_admin_posted_data_revision(array $post): string
{
    $revision = $post['revision'] ?? null;

    if (!is_string($revision) || !preg_match('/^[a-f0-9]{64}$/', $revision)) {
        throw new PkksAdminStaleDataException('Missing or invalid data revision.');
    }

    return $revision;
}

function pkks_admin_with_data_lock(string $dataPath, callable $operation): mixed
{
    $lockPath = $dataPath . '.lock';
    $lockHandle = fopen($lockPath, 'c');

    if ($lockHandle === false) {
        throw new RuntimeException('Data lock cannot be opened.');
    }

    try {
        if (!flock($lockHandle, LOCK_EX)) {
            throw new RuntimeException('Data lock cannot be acquired.');
        }

        return $operation();
    } finally {
        flock($lockHandle, LOCK_UN);
        fclose($lockHandle);
    }
}

function pkks_admin_assert_current_revision(string $dataPath, string $submittedRevision): void
{
    if (!hash_equals(pkks_admin_data_revision($dataPath), $submittedRevision)) {
        throw new PkksAdminStaleDataException('Data revision is stale.');
    }
}
