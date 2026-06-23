<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/admin-layout.php';
require_once __DIR__ . '/includes/services-storage.php';

pkks_admin_require_auth();

$currentLogin = pkks_admin_current_login() ?? 'администратор';
$flash = pkks_admin_services_take_flash();
$formData = isset($flash['formData']) && is_array($flash['formData']) ? $flash['formData'] : [];
$loadError = false;

try {
    $servicesData = pkks_admin_load_services_data();
} catch (RuntimeException) {
    $servicesData = ['serviceGroups' => []];
    $loadError = true;
}

$serviceGroups = isset($servicesData['serviceGroups']) && is_array($servicesData['serviceGroups'])
    ? $servicesData['serviceGroups']
    : [];

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

pkks_admin_render_header('Услуги', ['body_class' => 'pkks-admin-services-page']);
pkks_admin_render_topbar('Услуги', 'Вход выполнен: ' . $currentLogin);
?>
    <section class="pkks-admin-dashboard-intro pkks-admin-services-intro">
        <div class="pkks-admin-dashboard-intro__copy">
            <p class="pkks-admin-eyebrow">Редактор контента</p>
            <h2>Услуги</h2>
            <p>Редактирование видимости, порядка, заголовков и пунктов существующих карточек услуг</p>
        </div>
        <div class="pkks-admin-dashboard-actions" aria-label="Навигация редактора услуг">
            <a class="pkks-admin-button pkks-admin-button--secondary" href="/admin/index.php">Назад в админ-панель</a>
            <a class="pkks-admin-button pkks-admin-button--primary" href="/admin/logout.php">Выйти</a>
        </div>
    </section>

    <?php pkks_admin_services_render_flash($flash); ?>

<?php if ($loadError): ?>
    <?php pkks_admin_render_notice(
        'Данные услуг недоступны.',
        'Проверьте data/services.json и повторите попытку.'
    ); ?>
<?php else: ?>
    <form class="pkks-admin-team-form pkks-admin-services-form" action="/admin/api/save-services.php" method="post">
        <?php echo pkks_admin_csrf_field() . PHP_EOL; ?>

<?php foreach ($serviceGroups as $groupIndex => $group): ?>
    <?php if (!is_array($group)) {
        continue;
    } ?>
    <?php
    $groupId = pkks_admin_services_string($group['id'] ?? '');
    $groupFieldId = pkks_admin_services_field_id('group-' . $groupId);
    $groupSubmitted = pkks_admin_services_form_index_array($formData, (int)$groupIndex);
    $groupValues = pkks_admin_services_group_form_values($group, $groupSubmitted);
    $cards = isset($group['cards']) && is_array($group['cards']) ? $group['cards'] : [];
    ?>
        <article class="pkks-admin-team-card pkks-admin-services-group" aria-labelledby="pkks-admin-services-<?php echo pkks_admin_escape($groupFieldId); ?>-title">
            <header class="pkks-admin-team-card__header">
                <div>
                    <p class="pkks-admin-team-card__meta">ID группы: <?php echo pkks_admin_escape($groupId); ?></p>
                    <h2 id="pkks-admin-services-<?php echo pkks_admin_escape($groupFieldId); ?>-title"><?php echo pkks_admin_escape(pkks_admin_services_title_preview($groupValues['title'])); ?></h2>
                </div>
                <label class="pkks-admin-team-visible" for="pkks-admin-services-<?php echo pkks_admin_escape($groupFieldId); ?>-visible">
                    <input
                        id="pkks-admin-services-<?php echo pkks_admin_escape($groupFieldId); ?>-visible"
                        type="checkbox"
                        name="serviceGroups[<?php echo (int)$groupIndex; ?>][visible]"
                        value="1"
                        <?php echo $groupValues['visible'] ? 'checked' : ''; ?>
                    >
                    Показывать группу
                </label>
            </header>

            <div class="pkks-admin-team-grid pkks-admin-services-group-grid">
                <label class="pkks-admin-team-field" for="pkks-admin-services-<?php echo pkks_admin_escape($groupFieldId); ?>-sort">
                    <span>Порядок группы</span>
                    <input
                        id="pkks-admin-services-<?php echo pkks_admin_escape($groupFieldId); ?>-sort"
                        type="number"
                        name="serviceGroups[<?php echo (int)$groupIndex; ?>][sortOrder]"
                        min="0"
                        max="9999"
                        step="1"
                        value="<?php echo pkks_admin_escape($groupValues['sortOrder']); ?>"
                        required
                    >
                </label>

                <label class="pkks-admin-team-field pkks-admin-team-field--wide" for="pkks-admin-services-<?php echo pkks_admin_escape($groupFieldId); ?>-title-field">
                    <span>Заголовок группы</span>
                    <textarea
                        id="pkks-admin-services-<?php echo pkks_admin_escape($groupFieldId); ?>-title-field"
                        name="serviceGroups[<?php echo (int)$groupIndex; ?>][title]"
                        rows="3"
                        required
                    ><?php echo pkks_admin_escape($groupValues['title']); ?></textarea>
                </label>
            </div>

            <section class="pkks-admin-services-card-list" aria-label="Карточки группы <?php echo pkks_admin_escape($groupId); ?>">
<?php foreach ($cards as $cardIndex => $card): ?>
    <?php if (!is_array($card)) {
        continue;
    } ?>
    <?php
    $cardId = pkks_admin_services_string($card['id'] ?? '');
    $cardFieldId = pkks_admin_services_field_id('card-' . $cardId);
    $cardsFormData = isset($groupSubmitted['cards']) && is_array($groupSubmitted['cards']) ? $groupSubmitted['cards'] : [];
    $cardSubmitted = pkks_admin_services_form_index_array($cardsFormData, (int)$cardIndex);
    $cardValues = pkks_admin_services_card_form_values($card, $cardSubmitted);
    ?>
                <article class="pkks-admin-services-card" aria-labelledby="pkks-admin-services-<?php echo pkks_admin_escape($cardFieldId); ?>-title">
                    <header class="pkks-admin-services-card__header">
                        <div>
                            <p class="pkks-admin-team-card__meta">ID карточки: <?php echo pkks_admin_escape($cardId); ?></p>
                            <h3 id="pkks-admin-services-<?php echo pkks_admin_escape($cardFieldId); ?>-title"><?php echo pkks_admin_escape($cardValues['title']); ?></h3>
                        </div>
                        <label class="pkks-admin-team-visible" for="pkks-admin-services-<?php echo pkks_admin_escape($cardFieldId); ?>-visible">
                            <input
                                id="pkks-admin-services-<?php echo pkks_admin_escape($cardFieldId); ?>-visible"
                                type="checkbox"
                                name="serviceGroups[<?php echo (int)$groupIndex; ?>][cards][<?php echo (int)$cardIndex; ?>][visible]"
                                value="1"
                                <?php echo $cardValues['visible'] ? 'checked' : ''; ?>
                            >
                            Показывать карточку
                        </label>
                    </header>

                    <div class="pkks-admin-team-grid pkks-admin-services-card-grid">
                        <label class="pkks-admin-team-field" for="pkks-admin-services-<?php echo pkks_admin_escape($cardFieldId); ?>-sort">
                            <span>Порядок карточки</span>
                            <input
                                id="pkks-admin-services-<?php echo pkks_admin_escape($cardFieldId); ?>-sort"
                                type="number"
                                name="serviceGroups[<?php echo (int)$groupIndex; ?>][cards][<?php echo (int)$cardIndex; ?>][sortOrder]"
                                min="0"
                                max="9999"
                                step="1"
                                value="<?php echo pkks_admin_escape($cardValues['sortOrder']); ?>"
                                required
                            >
                        </label>

                        <label class="pkks-admin-team-field pkks-admin-team-field--wide" for="pkks-admin-services-<?php echo pkks_admin_escape($cardFieldId); ?>-title-field">
                            <span>Заголовок карточки</span>
                            <input
                                id="pkks-admin-services-<?php echo pkks_admin_escape($cardFieldId); ?>-title-field"
                                type="text"
                                name="serviceGroups[<?php echo (int)$groupIndex; ?>][cards][<?php echo (int)$cardIndex; ?>][title]"
                                maxlength="300"
                                value="<?php echo pkks_admin_escape($cardValues['title']); ?>"
                                required
                            >
                        </label>

                        <label class="pkks-admin-team-field pkks-admin-team-field--full pkks-admin-services-items" for="pkks-admin-services-<?php echo pkks_admin_escape($cardFieldId); ?>-items">
                            <span>Пункты услуг</span>
                            <textarea
                                id="pkks-admin-services-<?php echo pkks_admin_escape($cardFieldId); ?>-items"
                                name="serviceGroups[<?php echo (int)$groupIndex; ?>][cards][<?php echo (int)$cardIndex; ?>][items]"
                                rows="6"
                            ><?php echo pkks_admin_escape($cardValues['items']); ?></textarea>
                        </label>
                    </div>
                </article>

<?php endforeach; ?>
            </section>
        </article>

<?php endforeach; ?>

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

function pkks_admin_services_take_flash(): ?array
{
    $flash = $_SESSION['admin_flash'] ?? null;
    unset($_SESSION['admin_flash']);

    return is_array($flash) ? $flash : null;
}

function pkks_admin_services_render_flash(?array $flash): void
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

function pkks_admin_services_group_form_values(array $group, mixed $submitted): array
{
    if (is_array($submitted)) {
        return [
            'visible' => ($submitted['visible'] ?? false) === true,
            'sortOrder' => pkks_admin_services_string($submitted['sortOrder'] ?? ''),
            'title' => pkks_admin_services_string($submitted['title'] ?? ''),
        ];
    }

    return [
        'visible' => ($group['visible'] ?? false) === true,
        'sortOrder' => pkks_admin_services_string($group['sortOrder'] ?? ''),
        'title' => pkks_admin_services_string($group['title'] ?? ''),
    ];
}

function pkks_admin_services_card_form_values(array $card, mixed $submitted): array
{
    if (is_array($submitted)) {
        return [
            'visible' => ($submitted['visible'] ?? false) === true,
            'sortOrder' => pkks_admin_services_string($submitted['sortOrder'] ?? ''),
            'title' => pkks_admin_services_string($submitted['title'] ?? ''),
            'items' => pkks_admin_services_string($submitted['items'] ?? ''),
        ];
    }

    $items = isset($card['items']) && is_array($card['items'])
        ? implode(PHP_EOL, array_map(static fn (mixed $item): string => pkks_admin_services_string($item), $card['items']))
        : '';

    return [
        'visible' => ($card['visible'] ?? false) === true,
        'sortOrder' => pkks_admin_services_string($card['sortOrder'] ?? ''),
        'title' => pkks_admin_services_string($card['title'] ?? ''),
        'items' => $items,
    ];
}

function pkks_admin_services_form_index_array(array $items, int $index): ?array
{
    if (!array_key_exists($index, $items) || !is_array($items[$index])) {
        return null;
    }

    return $items[$index];
}

function pkks_admin_services_string(mixed $value): string
{
    return is_scalar($value) ? trim((string)$value) : '';
}

function pkks_admin_services_title_preview(string $title): string
{
    $preview = preg_replace('/\s+/u', ' ', $title);

    return is_string($preview) ? trim($preview) : $title;
}

function pkks_admin_services_field_id(string $value): string
{
    $fieldId = preg_replace('/[^a-zA-Z0-9_-]+/', '-', $value);

    return is_string($fieldId) && $fieldId !== '' ? $fieldId : 'service';
}
