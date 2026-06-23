<?php
declare(strict_types=1);

function pkks_admin_escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function pkks_admin_render_header(string $title): void
{
    $safeTitle = pkks_admin_escape($title);

    echo '<!doctype html>' . PHP_EOL;
    echo '<html lang="ru">' . PHP_EOL;
    echo '<head>' . PHP_EOL;
    echo '    <meta charset="utf-8">' . PHP_EOL;
    echo '    <meta name="viewport" content="width=device-width, initial-scale=1">' . PHP_EOL;
    echo '    <title>' . $safeTitle . '</title>' . PHP_EOL;
    echo '</head>' . PHP_EOL;
    echo '<body>' . PHP_EOL;
    echo '<main>' . PHP_EOL;
    echo '    <h1>' . $safeTitle . '</h1>' . PHP_EOL;
}

function pkks_admin_render_footer(): void
{
    echo '</main>' . PHP_EOL;
    echo '</body>' . PHP_EOL;
    echo '</html>' . PHP_EOL;
}
