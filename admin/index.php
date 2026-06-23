<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/admin-layout.php';

$hasConfig = pkks_admin_has_config();
$statusText = $hasConfig ? 'Конфигурация найдена, вход ещё не подключён' : 'Доступ ещё не настроен';

pkks_admin_render_header('Админ-панель', ['body_class' => 'pkks-admin-dashboard-page']);
pkks_admin_render_topbar('Админ-панель', $statusText);
?>
    <section class="pkks-admin-dashboard-intro">
        <div class="pkks-admin-dashboard-intro__copy">
            <p class="pkks-admin-eyebrow">Админ-панель</p>
            <h2>Личный кабинет для управления контентом сайта</h2>
            <p>Редактирование сотрудников, услуг и стоимости будет доступно после настройки входа. Сейчас все разделы показаны как макет без рабочих ссылок редактора и без сохранения данных.</p>
        </div>
        <div class="pkks-admin-dashboard-actions" aria-label="Навигация админ-панели">
            <a class="pkks-admin-button pkks-admin-button--primary" href="/admin/login.php">К экрану входа</a>
            <a class="pkks-admin-button pkks-admin-button--secondary" href="/">Вернуться на сайт</a>
        </div>
    </section>

    <?php if (!$hasConfig): ?>
        <?php pkks_admin_render_notice(
            'Админ-доступ ещё не настроен.',
            'Создайте config/admin-auth.php на хостинге по примеру config/admin-auth.php.example.'
        ); ?>
    <?php else: ?>
        <?php pkks_admin_render_notice(
            'Вход пока в режиме макета.',
            'Файл конфигурации найден, но рабочая авторизация и редактор будут подключены отдельным этапом.'
        ); ?>
    <?php endif; ?>

    <section class="pkks-admin-section-grid" aria-label="Будущие разделы админ-панели">
        <?php
        pkks_admin_render_panel_card('Сотрудники', 'Будущая настройка ФИО, должности и образования.');
        pkks_admin_render_panel_card('Услуги', 'Будущая настройка списка услуг.');
        pkks_admin_render_panel_card('Стоимость', 'Будущая настройка цен и примечаний.');
        pkks_admin_render_panel_card('Безопасность', 'Будущая настройка доступа и журнала действий.');
        ?>
    </section>
<?php
pkks_admin_render_footer([
    ['href' => '/', 'label' => 'Вернуться на сайт'],
    ['href' => '/admin/login.php', 'label' => 'К экрану входа'],
]);
