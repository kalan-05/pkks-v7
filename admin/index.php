<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/admin-layout.php';

if (!pkks_admin_has_config()) {
    pkks_admin_render_header('Админ-панель', ['body_class' => 'pkks-admin-dashboard-page']);
    pkks_admin_render_topbar('Админ-панель', 'Доступ ещё не настроен');
    pkks_admin_render_notice(
        'Админ-доступ ещё не настроен.',
        'Создайте config/admin-auth.php по примеру config/admin-auth.php.example.'
    );
    pkks_admin_render_footer([
        ['href' => '/admin/login.php', 'label' => 'К экрану входа'],
        ['href' => '/', 'label' => 'Вернуться на сайт'],
    ]);
    exit;
}

pkks_admin_require_auth();
$currentLogin = pkks_admin_current_login() ?? 'администратор';

pkks_admin_render_header('Админ-панель', ['body_class' => 'pkks-admin-dashboard-page']);
pkks_admin_render_topbar('Админ-панель', 'Вход выполнен: ' . $currentLogin);
?>
    <section class="pkks-admin-dashboard-intro">
        <div class="pkks-admin-dashboard-intro__copy">
            <p class="pkks-admin-eyebrow">Админ-панель</p>
            <h2>Личный кабинет для управления контентом сайта</h2>
            <p>Вы вошли как <?php echo pkks_admin_escape($currentLogin); ?>. Разделы «Сотрудники» и «Услуги» подключены, остальные разделы пока недоступны.</p>
        </div>
        <div class="pkks-admin-dashboard-actions" aria-label="Навигация админ-панели">
            <a class="pkks-admin-button pkks-admin-button--primary" href="/admin/logout.php">Выйти</a>
            <a class="pkks-admin-button pkks-admin-button--secondary" href="/">Вернуться на сайт</a>
        </div>
    </section>

    <?php pkks_admin_render_notice(
        'Авторизация активна.',
        'Доступ к этой странице и редакторам контента разрешён только после успешного входа.'
    ); ?>

    <section class="pkks-admin-section-grid" aria-label="Разделы админ-панели">
        <?php
        pkks_admin_render_panel_card('Сотрудники', 'Редактирование ФИО, должности и образования.', [
            'href' => '/admin/team.php',
            'label' => 'Открыть редактор',
            'disabled' => false,
        ]);
        pkks_admin_render_panel_card('Услуги', 'Редактирование групп, карточек и пунктов услуг.', [
            'href' => '/admin/services.php',
            'label' => 'Открыть редактор',
            'disabled' => false,
        ]);
        pkks_admin_render_panel_card('Стоимость', 'Будущая настройка цен и примечаний.');
        pkks_admin_render_panel_card('Безопасность', 'Будущая настройка доступа и журнала действий.');
        ?>
    </section>
<?php
pkks_admin_render_footer([
    ['href' => '/', 'label' => 'Вернуться на сайт'],
    ['href' => '/admin/logout.php', 'label' => 'Выйти'],
]);
