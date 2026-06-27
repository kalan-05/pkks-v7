<?php
declare(strict_types=1);

function pkks_admin_escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function pkks_admin_stylesheet_href(): string
{
    $stylesheetPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'admin.css';
    $version = is_file($stylesheetPath) ? (string)filemtime($stylesheetPath) : '1';

    return '/admin/assets/admin.css?v=' . rawurlencode($version);
}

function pkks_admin_render_header(string $title, array $options = []): void
{
    $safeTitle = pkks_admin_escape($title);
    $bodyClass = trim('pkks-admin-page ' . (string)($options['body_class'] ?? ''));
    $safeBodyClass = pkks_admin_escape($bodyClass);
    $safeStylesheetHref = pkks_admin_escape(pkks_admin_stylesheet_href());

    echo '<!doctype html>' . PHP_EOL;
    echo '<html lang="ru">' . PHP_EOL;
    echo '<head>' . PHP_EOL;
    echo '    <meta charset="utf-8">' . PHP_EOL;
    echo '    <meta name="viewport" content="width=device-width, initial-scale=1">' . PHP_EOL;
    echo '    <title>' . $safeTitle . ' — Админ-панель</title>' . PHP_EOL;
    echo '    <link rel="icon" href="/img/1Logo.svg" type="image/svg+xml">' . PHP_EOL;
    echo '    <link rel="stylesheet" href="' . $safeStylesheetHref . '">' . PHP_EOL;
    echo '</head>' . PHP_EOL;
    echo '<body class="' . $safeBodyClass . '">' . PHP_EOL;
    echo '<main class="pkks-admin-shell">' . PHP_EOL;
}

function pkks_admin_render_footer(array $links = []): void
{
    if ($links !== []) {
        echo '    <footer class="pkks-admin-footer" aria-label="Навигация админ-панели">' . PHP_EOL;

        foreach ($links as $link) {
            $href = pkks_admin_escape((string)($link['href'] ?? '#'));
            $label = pkks_admin_escape((string)($link['label'] ?? 'Ссылка'));

            echo '        <a href="' . $href . '">' . $label . '</a>' . PHP_EOL;
        }

        echo '    </footer>' . PHP_EOL;
    }

    echo '</main>' . PHP_EOL;
    echo '</body>' . PHP_EOL;
    echo '</html>' . PHP_EOL;
}

function pkks_admin_render_status_badge(string $statusText): void
{
    echo '        <div class="pkks-admin-status" aria-label="Статус админ-доступа">' . pkks_admin_escape($statusText) . '</div>' . PHP_EOL;
}

function pkks_admin_render_topbar(string $title, string $statusText): void
{
    echo '    <header class="pkks-admin-topbar">' . PHP_EOL;
    echo '        <div class="pkks-admin-topbar__title">' . PHP_EOL;
    echo '            <p class="pkks-admin-brand">Правовая контора К. Сопрачева</p>' . PHP_EOL;
    echo '            <h1>' . pkks_admin_escape($title) . '</h1>' . PHP_EOL;
    echo '        </div>' . PHP_EOL;
    pkks_admin_render_status_badge($statusText);
    echo '    </header>' . PHP_EOL;
}

function pkks_admin_render_notice(string $title, string $text): void
{
    echo '        <section class="pkks-admin-notice" aria-label="' . pkks_admin_escape($title) . '">' . PHP_EOL;
    echo '            <h2>' . pkks_admin_escape($title) . '</h2>' . PHP_EOL;
    echo '            <p>' . pkks_admin_escape($text) . '</p>' . PHP_EOL;
    echo '        </section>' . PHP_EOL;
}

function pkks_admin_render_panel_card(string $title, string $description, array $options = []): void
{
    $href = is_string($options['href'] ?? null) ? $options['href'] : '';
    $label = is_string($options['label'] ?? null) ? $options['label'] : 'Открыть';
    $isDisabled = ($options['disabled'] ?? true) !== false || $href === '';
    $cardClass = 'pkks-admin-section-card' . ($isDisabled ? ' pkks-admin-section-card--disabled' : ' pkks-admin-section-card--active');
    $ariaDisabled = $isDisabled ? ' aria-disabled="true"' : '';

    echo '            <article class="' . $cardClass . '"' . $ariaDisabled . '>' . PHP_EOL;
    echo '                <div>' . PHP_EOL;
    echo '                    <h2>' . pkks_admin_escape($title) . '</h2>' . PHP_EOL;
    echo '                    <p>' . pkks_admin_escape($description) . '</p>' . PHP_EOL;
    echo '                </div>' . PHP_EOL;

    if ($isDisabled) {
        echo '                <span>Будущий раздел</span>' . PHP_EOL;
    } else {
        echo '                <a class="pkks-admin-section-card__link" href="' . pkks_admin_escape($href) . '">' . pkks_admin_escape($label) . '</a>' . PHP_EOL;
    }

    echo '            </article>' . PHP_EOL;
}
