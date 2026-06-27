<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/admin-layout.php';
require_once __DIR__ . '/includes/team-storage.php';

pkks_admin_require_auth();

$currentLogin = pkks_admin_current_login() ?? 'администратор';
$flash = pkks_admin_team_take_flash();
$formData = isset($flash['formData']) && is_array($flash['formData']) ? $flash['formData'] : [];
$loadError = false;

try {
    $teamData = pkks_admin_load_team_data();
} catch (RuntimeException) {
    $teamData = ['employees' => []];
    $loadError = true;
}

$employees = isset($teamData['employees']) && is_array($teamData['employees']) ? $teamData['employees'] : [];
$mainFormId = 'pkks-admin-team-main-form';

if ($flash === null) {
    $status = $_GET['status'] ?? '';

    if ($status === 'saved') {
        $flash = [
            'type' => 'success',
            'title' => 'Изменения сохранены.',
            'messages' => [],
        ];
    } elseif ($status === 'photo-saved') {
        $flash = [
            'type' => 'success',
            'title' => 'Фото сотрудника обновлено.',
            'messages' => [],
        ];
    } elseif ($status === 'photo-error') {
        $flash = [
            'type' => 'error',
            'title' => 'Фото не обновлено.',
            'messages' => ['Проверьте файл и повторите попытку.'],
        ];
    }
}

pkks_admin_render_header('Сотрудники', ['body_class' => 'pkks-admin-team-page']);
pkks_admin_render_topbar('Сотрудники', 'Вход выполнен: ' . $currentLogin);
?>
    <section class="pkks-admin-dashboard-intro pkks-admin-team-intro">
        <div class="pkks-admin-dashboard-intro__copy">
            <p class="pkks-admin-eyebrow">Редактор контента</p>
            <h2>Сотрудники</h2>
            <p>Редактирование ФИО, должности и образования</p>
        </div>
        <div class="pkks-admin-dashboard-actions" aria-label="Навигация редактора сотрудников">
            <a class="pkks-admin-button pkks-admin-button--secondary" href="/admin/index.php">Назад в админ-панель</a>
            <a class="pkks-admin-button pkks-admin-button--primary" href="/admin/logout.php">Выйти</a>
        </div>
    </section>

    <?php pkks_admin_team_render_flash($flash); ?>

<?php if ($loadError): ?>
    <?php pkks_admin_render_notice(
        'Данные сотрудников недоступны.',
        'Проверьте data/team.json и повторите попытку.'
    ); ?>
<?php else: ?>
    <form id="<?php echo pkks_admin_escape($mainFormId); ?>" action="/admin/api/save-team.php" method="post">
        <?php echo pkks_admin_csrf_field() . PHP_EOL; ?>
    </form>

    <div class="pkks-admin-team-form">

<?php foreach ($employees as $employee): ?>
    <?php if (!is_array($employee)) {
        continue;
    } ?>
    <?php
    $employeeId = pkks_admin_team_string($employee['id'] ?? '');
    $fieldId = pkks_admin_team_field_id($employeeId);
    $values = pkks_admin_team_form_values($employee, $formData[$employeeId] ?? null);
    $photoPath = pkks_admin_team_string($employee['photo'] ?? '');
    $photoAlt = pkks_admin_team_string($employee['photoAlt'] ?? '');
    $photoTitle = pkks_admin_team_string($employee['photoTitle'] ?? '');
    $photoSrc = pkks_admin_team_photo_src($photoPath);
    ?>
        <article class="pkks-admin-team-card" aria-labelledby="pkks-admin-team-<?php echo pkks_admin_escape($fieldId); ?>-title">
            <header class="pkks-admin-team-card__header">
                <div>
                    <p class="pkks-admin-team-card__meta">ID: <?php echo pkks_admin_escape($employeeId); ?></p>
                    <h2 id="pkks-admin-team-<?php echo pkks_admin_escape($fieldId); ?>-title"><?php echo pkks_admin_escape($values['fullName']); ?></h2>
                </div>
                <label class="pkks-admin-team-visible" for="pkks-admin-team-<?php echo pkks_admin_escape($fieldId); ?>-visible">
                    <input
                        id="pkks-admin-team-<?php echo pkks_admin_escape($fieldId); ?>-visible"
                        form="<?php echo pkks_admin_escape($mainFormId); ?>"
                        type="checkbox"
                        name="employees[<?php echo pkks_admin_escape($employeeId); ?>][visible]"
                        value="1"
                        <?php echo $values['visible'] ? 'checked' : ''; ?>
                    >
                    Показывать на сайте
                </label>
            </header>

            <div class="pkks-admin-team-grid">
                <label class="pkks-admin-team-field" for="pkks-admin-team-<?php echo pkks_admin_escape($fieldId); ?>-sort">
                    <span>Порядок</span>
                    <input
                        id="pkks-admin-team-<?php echo pkks_admin_escape($fieldId); ?>-sort"
                        form="<?php echo pkks_admin_escape($mainFormId); ?>"
                        type="number"
                        name="employees[<?php echo pkks_admin_escape($employeeId); ?>][sortOrder]"
                        min="1"
                        max="999"
                        step="1"
                        value="<?php echo pkks_admin_escape($values['sortOrder']); ?>"
                        required
                    >
                </label>

                <label class="pkks-admin-team-field" for="pkks-admin-team-<?php echo pkks_admin_escape($fieldId); ?>-name">
                    <span>ФИО</span>
                    <input
                        id="pkks-admin-team-<?php echo pkks_admin_escape($fieldId); ?>-name"
                        form="<?php echo pkks_admin_escape($mainFormId); ?>"
                        type="text"
                        name="employees[<?php echo pkks_admin_escape($employeeId); ?>][fullName]"
                        maxlength="120"
                        value="<?php echo pkks_admin_escape($values['fullName']); ?>"
                        required
                    >
                </label>

                <label class="pkks-admin-team-field pkks-admin-team-field--wide" for="pkks-admin-team-<?php echo pkks_admin_escape($fieldId); ?>-position">
                    <span>Должность</span>
                    <input
                        id="pkks-admin-team-<?php echo pkks_admin_escape($fieldId); ?>-position"
                        form="<?php echo pkks_admin_escape($mainFormId); ?>"
                        type="text"
                        name="employees[<?php echo pkks_admin_escape($employeeId); ?>][position]"
                        maxlength="160"
                        value="<?php echo pkks_admin_escape($values['position']); ?>"
                        required
                    >
                </label>

                <label class="pkks-admin-team-field pkks-admin-team-field--full" for="pkks-admin-team-<?php echo pkks_admin_escape($fieldId); ?>-education">
                    <span>Образование</span>
                    <textarea
                        id="pkks-admin-team-<?php echo pkks_admin_escape($fieldId); ?>-education"
                        form="<?php echo pkks_admin_escape($mainFormId); ?>"
                        name="employees[<?php echo pkks_admin_escape($employeeId); ?>][education]"
                        rows="4"
                    ><?php echo pkks_admin_escape($values['education']); ?></textarea>
                </label>
            </div>

            <section class="pkks-admin-team-photo-panel" aria-label="Замена фото сотрудника">
                <div class="pkks-admin-team-photo-preview" aria-label="Текущее фото">
<?php if ($photoSrc !== ''): ?>
                    <img src="<?php echo pkks_admin_escape($photoSrc); ?>" alt="<?php echo pkks_admin_escape($photoAlt !== '' ? $photoAlt : $values['fullName']); ?>">
<?php else: ?>
                    <span>Фото не задано</span>
<?php endif; ?>
                </div>

                <div class="pkks-admin-team-photo-current">
                    <span>Текущий путь photo</span>
                    <code><?php echo pkks_admin_escape($photoPath !== '' ? $photoPath : 'не задан'); ?></code>
                </div>

                <form class="pkks-admin-team-photo-form" action="/admin/api/upload-team-photo.php" method="post" enctype="multipart/form-data">
                    <?php echo pkks_admin_csrf_field() . PHP_EOL; ?>
                    <input type="hidden" name="employee_id" value="<?php echo pkks_admin_escape($employeeId); ?>">

                    <label class="pkks-admin-team-field pkks-admin-team-field--full" for="pkks-admin-team-<?php echo pkks_admin_escape($fieldId); ?>-photo-file">
                        <span>Новое фото</span>
                        <input
                            id="pkks-admin-team-<?php echo pkks_admin_escape($fieldId); ?>-photo-file"
                            type="file"
                            name="photo"
                            accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                            required
                        >
                    </label>

                    <label class="pkks-admin-team-field" for="pkks-admin-team-<?php echo pkks_admin_escape($fieldId); ?>-photo-alt">
                        <span>Alt фото</span>
                        <input
                            id="pkks-admin-team-<?php echo pkks_admin_escape($fieldId); ?>-photo-alt"
                            type="text"
                            name="photoAlt"
                            maxlength="180"
                            value="<?php echo pkks_admin_escape($photoAlt); ?>"
                        >
                    </label>

                    <label class="pkks-admin-team-field" for="pkks-admin-team-<?php echo pkks_admin_escape($fieldId); ?>-photo-title">
                        <span>Title фото</span>
                        <input
                            id="pkks-admin-team-<?php echo pkks_admin_escape($fieldId); ?>-photo-title"
                            type="text"
                            name="photoTitle"
                            maxlength="180"
                            value="<?php echo pkks_admin_escape($photoTitle); ?>"
                        >
                    </label>

                    <p class="pkks-admin-team-photo-hint">jpg, png, webp, до 3 MB.</p>
                    <button class="pkks-admin-button pkks-admin-button--secondary pkks-admin-team-photo-submit" type="submit">Заменить фото</button>
                </form>
            </section>
        </article>

<?php endforeach; ?>

        <div class="pkks-admin-team-actions">
            <button class="pkks-admin-button pkks-admin-button--primary pkks-admin-team-submit" type="submit" form="<?php echo pkks_admin_escape($mainFormId); ?>">Сохранить изменения</button>
            <a class="pkks-admin-button pkks-admin-button--secondary" href="/admin/index.php">Назад в админ-панель</a>
        </div>
    </div>
<?php endif; ?>
<?php
pkks_admin_render_footer([
    ['href' => '/admin/index.php', 'label' => 'Назад в админ-панель'],
    ['href' => '/', 'label' => 'Вернуться на сайт'],
]);

function pkks_admin_team_take_flash(): ?array
{
    $flash = $_SESSION['admin_flash'] ?? null;
    unset($_SESSION['admin_flash']);

    return is_array($flash) ? $flash : null;
}

function pkks_admin_team_render_flash(?array $flash): void
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

function pkks_admin_team_form_values(array $employee, mixed $submitted): array
{
    if (is_array($submitted)) {
        return [
            'visible' => ($submitted['visible'] ?? false) === true,
            'sortOrder' => pkks_admin_team_string($submitted['sortOrder'] ?? ''),
            'fullName' => pkks_admin_team_string($submitted['fullName'] ?? ''),
            'position' => pkks_admin_team_string($submitted['position'] ?? ''),
            'education' => pkks_admin_team_string($submitted['education'] ?? ''),
        ];
    }

    $education = isset($employee['education']) && is_array($employee['education'])
        ? implode(PHP_EOL, array_map(static fn (mixed $item): string => pkks_admin_team_string($item), $employee['education']))
        : '';

    return [
        'visible' => ($employee['visible'] ?? false) === true,
        'sortOrder' => pkks_admin_team_string($employee['sortOrder'] ?? ''),
        'fullName' => pkks_admin_team_string($employee['fullName'] ?? ''),
        'position' => pkks_admin_team_string($employee['position'] ?? ''),
        'education' => $education,
    ];
}

function pkks_admin_team_string(mixed $value): string
{
    return is_scalar($value) ? trim((string)$value) : '';
}

function pkks_admin_team_field_id(string $employeeId): string
{
    $fieldId = preg_replace('/[^a-zA-Z0-9_-]+/', '-', $employeeId);

    return is_string($fieldId) && $fieldId !== '' ? $fieldId : 'employee';
}

function pkks_admin_team_photo_src(string $photoPath): string
{
    $photoPath = trim(str_replace('\\', '/', $photoPath));

    if ($photoPath === '' || str_starts_with($photoPath, '//') || preg_match('/^[a-z][a-z0-9+.-]*:/i', $photoPath) === 1) {
        return '';
    }

    return '/' . ltrim($photoPath, '/');
}
