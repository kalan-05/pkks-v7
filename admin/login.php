<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/audit-log.php';
require_once __DIR__ . '/includes/rate-limit.php';
require_once __DIR__ . '/includes/admin-layout.php';

$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if (!in_array($requestMethod, ['GET', 'HEAD', 'POST'], true)) {
    http_response_code(405);
    exit;
}

$hasConfig = pkks_admin_has_config();
$config = null;
$loginError = null;
$submittedLogin = '';

if ($hasConfig) {
    try {
        $config = pkks_admin_load_config();
    } catch (RuntimeException) {
        $hasConfig = false;
    }
}

if ($hasConfig && $config !== null) {
    pkks_admin_start_session($config);

    if (in_array($requestMethod, ['GET', 'HEAD'], true) && pkks_admin_is_authenticated($config)) {
        header('Location: /admin/index.php', true, 302);
        exit;
    }
}

if ($requestMethod === 'POST' && $hasConfig && $config !== null) {
    $submittedLogin = trim((string)($_POST['login'] ?? ''));

    pkks_admin_require_csrf(is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : null);

    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    $isBlocked = pkks_admin_is_login_blocked($submittedLogin, $config);
    $isVerified = !$isBlocked && pkks_admin_verify_credentials($submittedLogin, $password, $config);

    if ($isVerified) {
        pkks_admin_mark_authenticated($submittedLogin, $config);
        pkks_admin_record_login_attempt($submittedLogin, true, $config);
        pkks_admin_write_audit_event('login_success', ['login' => $submittedLogin]);

        header('Location: /admin/index.php', true, 302);
        exit;
    }

    if (!$isBlocked) {
        pkks_admin_record_login_attempt($submittedLogin, false, $config);
    }

    pkks_admin_write_audit_event('login_failed', ['login' => $submittedLogin]);
    $loginError = 'Неверный логин или пароль.';
}

pkks_admin_render_header('Вход в админ-панель', ['body_class' => 'pkks-admin-login-page']);
?>
    <section class="pkks-admin-auth-card" aria-labelledby="pkks-admin-login-title">
        <p class="pkks-admin-brand">Правовая контора К. Сопрачева</p>
        <h1 id="pkks-admin-login-title">Вход в админ-панель</h1>
        <p class="pkks-admin-lead">Управление сотрудниками, услугами и стоимостью</p>

        <?php if (!$hasConfig): ?>
            <?php pkks_admin_render_notice(
                'Админ-доступ ещё не настроен.',
                'Для включения входа создайте файл config/admin-auth.php по примеру config/admin-auth.php.example.'
            ); ?>
        <?php elseif ($loginError !== null): ?>
            <?php pkks_admin_render_notice('Ошибка входа', $loginError); ?>
        <?php else: ?>
            <?php pkks_admin_render_notice(
                'Конфигурация найдена.',
                'Введите логин и пароль администратора.'
            ); ?>
        <?php endif; ?>

        <form class="pkks-admin-login-form" action="/admin/login.php" method="post" <?php echo !$hasConfig ? 'aria-disabled="true"' : ''; ?>>
            <?php echo $hasConfig ? '            ' . pkks_admin_csrf_field() . PHP_EOL : ''; ?>
            <label for="pkks-admin-login">Логин</label>
            <input id="pkks-admin-login" name="login" type="text" placeholder="Логин" value="<?php echo pkks_admin_escape($submittedLogin); ?>" autocomplete="username" <?php echo !$hasConfig ? 'disabled' : 'required'; ?>>

            <label for="pkks-admin-password">Пароль</label>
            <div class="pkks-admin-password-field">
                <input id="pkks-admin-password" name="password" type="password" placeholder="Пароль" autocomplete="current-password" <?php echo !$hasConfig ? 'disabled' : 'required'; ?>>
                <button class="pkks-admin-password-toggle" type="button" aria-label="Показать пароль" aria-pressed="false" aria-controls="pkks-admin-password" data-password-toggle <?php echo !$hasConfig ? 'disabled' : ''; ?>>
                    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <path d="M2.8 12s3.3-5.5 9.2-5.5S21.2 12 21.2 12s-3.3 5.5-9.2 5.5S2.8 12 2.8 12Z"></path>
                        <circle cx="12" cy="12" r="2.8"></circle>
                        <path class="pkks-admin-password-toggle__slash" d="M5 19 19 5"></path>
                    </svg>
                </button>
            </div>

            <button type="submit" <?php echo !$hasConfig ? 'disabled' : ''; ?>>Войти</button>
        </form>
        <script>
            (function () {
                var toggle = document.querySelector('[data-password-toggle]');
                var input = document.getElementById('pkks-admin-password');

                if (!toggle || !input) {
                    return;
                }

                toggle.addEventListener('click', function () {
                    var isVisible = input.type === 'text';

                    input.type = isVisible ? 'password' : 'text';
                    toggle.setAttribute('aria-pressed', isVisible ? 'false' : 'true');
                    toggle.setAttribute('aria-label', isVisible ? 'Показать пароль' : 'Скрыть пароль');
                });
            }());
        </script>

        <p class="pkks-admin-footnote">Пароль проверяется только по password_hash из локального конфигурационного файла.</p>

        <section class="pkks-admin-recovery" id="forgot-password" aria-labelledby="pkks-admin-recovery-title">
            <h2 id="pkks-admin-recovery-title">Забыли пароль?</h2>
            <p>В MVP восстановление выполняется технически: нужно сгенерировать новый password_hash и заменить его в config/admin-auth.php на хостинге. Пароль не хранится в открытом виде и не восстанавливается по e-mail.</p>
        </section>
    </section>
<?php

pkks_admin_render_footer([
    ['href' => '/', 'label' => 'Вернуться на сайт'],
]);
