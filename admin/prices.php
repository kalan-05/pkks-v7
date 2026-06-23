<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/admin-layout.php';
require_once __DIR__ . '/includes/prices-storage.php';

pkks_admin_require_auth();

$currentLogin = pkks_admin_current_login() ?? 'администратор';
$flash = pkks_admin_prices_take_flash();
$formData = isset($flash['formData']) && is_array($flash['formData']) ? $flash['formData'] : [];
$priceFormData = isset($formData['prices']) && is_array($formData['prices']) ? $formData['prices'] : [];
$noteFormData = isset($formData['notes']) && is_array($formData['notes']) ? $formData['notes'] : [];
$loadError = false;

try {
    $pricesData = pkks_admin_load_prices_data();
} catch (RuntimeException) {
    $pricesData = ['prices' => [], 'notes' => []];
    $loadError = true;
}

$prices = isset($pricesData['prices']) && is_array($pricesData['prices']) ? $pricesData['prices'] : [];
$notes = isset($pricesData['notes']) && is_array($pricesData['notes']) ? $pricesData['notes'] : [];

if ($flash === null && ($_GET['status'] ?? '') === 'saved') {
    $flash = [
        'type' => 'success',
        'title' => 'Изменения сохранены.',
        'messages' => [],
    ];
}

if ($flash === null && ($_GET['status'] ?? '') === 'error') {
    $flash = [
        'type' => 'error',
        'title' => 'Изменения не сохранены.',
        'messages' => ['Проверьте данные и повторите попытку.'],
    ];
}

pkks_admin_render_header('Цены', ['body_class' => 'pkks-admin-prices-page']);
pkks_admin_render_topbar('Цены', 'Вход выполнен: ' . $currentLogin);
?>
    <section class="pkks-admin-dashboard-intro pkks-admin-prices-intro">
        <div class="pkks-admin-dashboard-intro__copy">
            <p class="pkks-admin-eyebrow">Редактор контента</p>
            <h2>Цены</h2>
            <p>Редактирование видимости, порядка, тарифов и существующих примечаний</p>
        </div>
        <div class="pkks-admin-dashboard-actions" aria-label="Навигация редактора цен">
            <a class="pkks-admin-button pkks-admin-button--secondary" href="/admin/index.php">Назад в админ-панель</a>
            <a class="pkks-admin-button pkks-admin-button--primary" href="/admin/logout.php">Выйти</a>
        </div>
    </section>

    <?php pkks_admin_prices_render_flash($flash); ?>

<?php if ($loadError): ?>
    <?php pkks_admin_render_notice(
        'Данные цен недоступны.',
        'Проверьте data/prices.json и повторите попытку.'
    ); ?>
<?php else: ?>
    <form class="pkks-admin-team-form pkks-admin-prices-form" action="/admin/api/save-prices.php" method="post">
        <?php echo pkks_admin_csrf_field() . PHP_EOL; ?>

<?php foreach ($prices as $priceIndex => $price): ?>
    <?php if (!is_array($price)) {
        continue;
    } ?>
    <?php
    $priceId = pkks_admin_prices_string($price['id'] ?? '');
    $priceFieldId = pkks_admin_prices_field_id('price-' . $priceId);
    $submittedPrice = pkks_admin_prices_form_index_array($priceFormData, (int)$priceIndex);
    $priceValues = pkks_admin_prices_price_form_values($price, $submittedPrice);
    ?>
        <article class="pkks-admin-team-card pkks-admin-services-card" aria-labelledby="pkks-admin-prices-<?php echo pkks_admin_escape($priceFieldId); ?>-title">
            <header class="pkks-admin-services-card__header">
                <div>
                    <p class="pkks-admin-team-card__meta">ID тарифа: <?php echo pkks_admin_escape($priceId); ?></p>
                    <h2 id="pkks-admin-prices-<?php echo pkks_admin_escape($priceFieldId); ?>-title"><?php echo pkks_admin_escape($priceValues['title']); ?></h2>
                </div>
                <label class="pkks-admin-team-visible" for="pkks-admin-prices-<?php echo pkks_admin_escape($priceFieldId); ?>-visible">
                    <input
                        id="pkks-admin-prices-<?php echo pkks_admin_escape($priceFieldId); ?>-visible"
                        type="checkbox"
                        name="prices[<?php echo (int)$priceIndex; ?>][visible]"
                        value="1"
                        <?php echo $priceValues['visible'] ? 'checked' : ''; ?>
                    >
                    Показывать тариф
                </label>
            </header>

            <div class="pkks-admin-team-grid pkks-admin-services-card-grid">
                <label class="pkks-admin-team-field" for="pkks-admin-prices-<?php echo pkks_admin_escape($priceFieldId); ?>-sort">
                    <span>Порядок</span>
                    <input
                        id="pkks-admin-prices-<?php echo pkks_admin_escape($priceFieldId); ?>-sort"
                        type="number"
                        name="prices[<?php echo (int)$priceIndex; ?>][sortOrder]"
                        min="0"
                        max="9999"
                        step="1"
                        value="<?php echo pkks_admin_escape($priceValues['sortOrder']); ?>"
                        required
                    >
                </label>

                <label class="pkks-admin-team-field pkks-admin-team-field--wide" for="pkks-admin-prices-<?php echo pkks_admin_escape($priceFieldId); ?>-title-field">
                    <span>Название</span>
                    <input
                        id="pkks-admin-prices-<?php echo pkks_admin_escape($priceFieldId); ?>-title-field"
                        type="text"
                        name="prices[<?php echo (int)$priceIndex; ?>][title]"
                        maxlength="240"
                        value="<?php echo pkks_admin_escape($priceValues['title']); ?>"
                        required
                    >
                </label>

                <label class="pkks-admin-team-field" for="pkks-admin-prices-<?php echo pkks_admin_escape($priceFieldId); ?>-price">
                    <span>Цена</span>
                    <input
                        id="pkks-admin-prices-<?php echo pkks_admin_escape($priceFieldId); ?>-price"
                        type="text"
                        name="prices[<?php echo (int)$priceIndex; ?>][price]"
                        maxlength="120"
                        value="<?php echo pkks_admin_escape($priceValues['price']); ?>"
                        required
                    >
                </label>

                <label class="pkks-admin-team-field" for="pkks-admin-prices-<?php echo pkks_admin_escape($priceFieldId); ?>-unit">
                    <span>Единица</span>
                    <input
                        id="pkks-admin-prices-<?php echo pkks_admin_escape($priceFieldId); ?>-unit"
                        type="text"
                        name="prices[<?php echo (int)$priceIndex; ?>][unit]"
                        maxlength="80"
                        value="<?php echo pkks_admin_escape($priceValues['unit']); ?>"
                        required
                    >
                </label>
            </div>
        </article>

<?php endforeach; ?>

        <article class="pkks-admin-team-card pkks-admin-services-group" aria-labelledby="pkks-admin-prices-notes-title">
            <header class="pkks-admin-team-card__header">
                <div>
                    <p class="pkks-admin-team-card__meta">Примечания по индексу</p>
                    <h2 id="pkks-admin-prices-notes-title">Примечания</h2>
                </div>
            </header>

            <div class="pkks-admin-services-card-list">
<?php foreach ($notes as $noteIndex => $note): ?>
    <?php
    $noteValue = pkks_admin_prices_note_form_value($note, $noteFormData, (int)$noteIndex);
    ?>
                <label class="pkks-admin-team-field pkks-admin-team-field--full" for="pkks-admin-prices-note-<?php echo (int)$noteIndex; ?>">
                    <span>Примечание <?php echo (int)$noteIndex + 1; ?></span>
                    <textarea
                        id="pkks-admin-prices-note-<?php echo (int)$noteIndex; ?>"
                        name="notes[<?php echo (int)$noteIndex; ?>]"
                        rows="4"
                        required
                    ><?php echo pkks_admin_escape($noteValue); ?></textarea>
                </label>

<?php endforeach; ?>
            </div>
        </article>

        <div class="pkks-admin-team-actions">
            <button class="pkks-admin-button pkks-admin-button--primary pkks-admin-team-submit" type="submit">Сохранить изменения</button>
            <a class="pkks-admin-button pkks-admin-button--secondary" href="/admin/index.php">Назад в админ-панель</a>
        </div>
    </form>
<?php endif; ?>
<?php
pkks_admin_render_footer([
    ['href' => '/admin/index.php', 'label' => 'Назад в админ-панель'],
    ['href' => '/', 'label' => 'Вернуться на сайт'],
]);

function pkks_admin_prices_take_flash(): ?array
{
    $flash = $_SESSION['admin_flash'] ?? null;
    unset($_SESSION['admin_flash']);

    return is_array($flash) ? $flash : null;
}

function pkks_admin_prices_render_flash(?array $flash): void
{
    if ($flash === null) {
        return;
    }

    $type = ($flash['type'] ?? '') === 'success' ? 'success' : 'error';
    $title = is_string($flash['title'] ?? null) ? $flash['title'] : 'Сообщение';
    $messages = isset($flash['messages']) && is_array($flash['messages']) ? $flash['messages'] : [];

    echo '    <section class="pkks-admin-flash pkks-admin-flash--' . pkks_admin_escape($type) . '" role="alert">' . PHP_EOL;
    echo '        <h2>' . pkks_admin_escape($title) . '</h2>' . PHP_EOL;

    if ($messages !== []) {
        echo '        <ul>' . PHP_EOL;

        foreach ($messages as $message) {
            if (is_scalar($message)) {
                echo '            <li>' . pkks_admin_escape((string)$message) . '</li>' . PHP_EOL;
            }
        }

        echo '        </ul>' . PHP_EOL;
    }

    echo '    </section>' . PHP_EOL;
}

function pkks_admin_prices_price_form_values(array $price, mixed $submitted): array
{
    if (is_array($submitted)) {
        return [
            'visible' => ($submitted['visible'] ?? false) === true,
            'sortOrder' => pkks_admin_prices_string($submitted['sortOrder'] ?? ''),
            'title' => pkks_admin_prices_string($submitted['title'] ?? ''),
            'price' => pkks_admin_prices_string($submitted['price'] ?? ''),
            'unit' => pkks_admin_prices_string($submitted['unit'] ?? ''),
        ];
    }

    return [
        'visible' => ($price['visible'] ?? false) === true,
        'sortOrder' => pkks_admin_prices_string($price['sortOrder'] ?? ''),
        'title' => pkks_admin_prices_string($price['title'] ?? ''),
        'price' => pkks_admin_prices_string($price['price'] ?? ''),
        'unit' => pkks_admin_prices_string($price['unit'] ?? ''),
    ];
}

function pkks_admin_prices_note_form_value(mixed $note, array $submittedNotes, int $index): string
{
    if (array_key_exists($index, $submittedNotes) && is_scalar($submittedNotes[$index])) {
        return pkks_admin_prices_string($submittedNotes[$index]);
    }

    return pkks_admin_prices_string($note);
}

function pkks_admin_prices_form_index_array(array $items, int $index): ?array
{
    if (!array_key_exists($index, $items) || !is_array($items[$index])) {
        return null;
    }

    return $items[$index];
}

function pkks_admin_prices_string(mixed $value): string
{
    return is_scalar($value) ? trim((string)$value) : '';
}

function pkks_admin_prices_field_id(string $value): string
{
    $fieldId = preg_replace('/[^a-zA-Z0-9_-]+/', '-', $value);

    return is_string($fieldId) && $fieldId !== '' ? $fieldId : 'price';
}
