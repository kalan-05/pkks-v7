<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/admin-layout.php';

pkks_admin_require_auth();
$currentLogin = pkks_admin_current_login() ?? 'администратор';

pkks_admin_render_header('Админ-панель', ['body_class' => 'pkks-admin-dashboard-page']);
pkks_admin_render_topbar('Админ-панель', 'Вход выполнен: ' . $currentLogin);
?>
    <section class="pkks-admin-dashboard-intro">
        <div class="pkks-admin-dashboard-intro__copy">
            <p class="pkks-admin-eyebrow">Админ-панель</p>
            <h2>Личный кабинет для управления контентом сайта</h2>
            <p>Вы вошли как <?php echo pkks_admin_escape($currentLogin); ?>. Разделы «Сотрудники», «Услуги» и «Цены» подключены, остальные разделы пока недоступны.</p>
        </div>
        <div class="pkks-admin-dashboard-actions" aria-label="Навигация админ-панели">
            <?php if (pkks_admin_current_role() === 'primary_admin'): ?>
                <a class="pkks-admin-button pkks-admin-button--secondary" href="/admin/users.php">Пользователи и доступ</a>
            <?php endif; ?>
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
        pkks_admin_render_panel_card('Цены', 'Редактирование тарифов и примечаний.', [
            'href' => '/admin/prices.php',
            'label' => 'Открыть редактор',
            'disabled' => false,
        ]);
        pkks_admin_render_panel_card('Безопасность', 'Проверка защиты админки, резервных копий и готовности к хостингу.', [
            'href' => '/admin/security.php',
            'label' => 'Открыть статус',
            'disabled' => false,
        ]);
        ?>
    </section>
<?php
pkks_admin_render_footer([
    ['href' => '/', 'label' => 'Вернуться на сайт'],
    ['href' => '/admin/logout.php', 'label' => 'Выйти'],
]);
