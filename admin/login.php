<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/admin-layout.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    http_response_code(405);
    pkks_admin_render_header('Вход в админ-панель', ['body_class' => 'pkks-admin-login-page']);
    echo '    <section class="pkks-admin-auth-card">' . PHP_EOL;
    echo '        <p class="pkks-admin-brand">Правовая контора К. Сопрачева</p>' . PHP_EOL;
    echo '        <h1>Вход в админ-панель</h1>' . PHP_EOL;
    echo '        <p class="pkks-admin-muted">Визуальный экран входа пока не принимает отправку формы.</p>' . PHP_EOL;
    echo '    </section>' . PHP_EOL;
    pkks_admin_render_footer([
        ['href' => '/', 'label' => 'Вернуться на сайт'],
    ]);
    exit;
}

$hasConfig = pkks_admin_has_config();

pkks_admin_render_header('Вход в админ-панель', ['body_class' => 'pkks-admin-login-page']);
?>
    <section class="pkks-admin-auth-card" aria-labelledby="pkks-admin-login-title">
        <p class="pkks-admin-brand">Правовая контора К. Сопрачева</p>
        <h1 id="pkks-admin-login-title">Вход в админ-панель</h1>
        <p class="pkks-admin-lead">Управление сотрудниками, услугами и стоимостью</p>

        <?php if (!$hasConfig): ?>
            <?php pkks_admin_render_notice(
                'Админ-доступ ещё не настроен.',
                'Для включения входа создайте файл config/admin-auth.php на хостинге по примеру config/admin-auth.php.example.'
            ); ?>
        <?php else: ?>
            <?php pkks_admin_render_notice(
                'Конфигурация найдена.',
                'Экран входа подготовлен визуально. Рабочая проверка логина и пароля будет подключена отдельным этапом.'
            ); ?>
        <?php endif; ?>

        <form class="pkks-admin-login-form" action="/admin/login.php" method="get" aria-disabled="true">
            <label for="pkks-admin-login">Логин</label>
            <input id="pkks-admin-login" type="text" placeholder="Логин" disabled>

            <label for="pkks-admin-password">Пароль</label>
            <input id="pkks-admin-password" type="password" placeholder="Пароль" disabled>

            <button type="button" disabled>Войти</button>
        </form>

        <p class="pkks-admin-footnote">Форма показана как макет: отправка данных и создание авторизованной сессии сейчас не выполняются.</p>

        <section class="pkks-admin-recovery" id="forgot-password" aria-labelledby="pkks-admin-recovery-title">
            <h2 id="pkks-admin-recovery-title">Забыли пароль?</h2>
            <p>В MVP восстановление выполняется технически: нужно сгенерировать новый password_hash и заменить его в config/admin-auth.php на хостинге. Пароль не хранится в открытом виде и не восстанавливается по e-mail.</p>
        </section>
    </section>
<?php

pkks_admin_render_footer([
    ['href' => '/', 'label' => 'Вернуться на сайт'],
]);
