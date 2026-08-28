<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/rate-limit.php';
require_once __DIR__ . '/includes/admin-layout.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$sent = false;
if ($method === 'POST') {
    pkks_admin_start_session();
    pkks_admin_require_csrf(is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : null);
    $email = (string)($_POST['email'] ?? '');
    try {
        if (!pkks_admin_is_login_blocked($email)) {
            $user = pkks_admin_auth_user_by_email($email);
            if ($user !== null && (int)$user['active'] === 1) {
                $token = pkks_admin_auth_create_token((int)$user['id'], 'reset');
                pkks_admin_auth_deliver_mail((string)$user['email'], 'Восстановление доступа', '/admin/reset-password.php', $token);
                pkks_admin_record_login_attempt($email, true);
            } else {
                pkks_admin_record_login_attempt($email, false);
            }
        }
    } catch (Throwable) {
        /* Нейтральный ответ не раскрывает существование учётной записи. */
    }
    $sent = true;
}
pkks_admin_render_header('Восстановление доступа', ['body_class' => 'pkks-admin-login-page']);
?>
    <section class="pkks-admin-auth-card" aria-labelledby="pkks-admin-reset-title">
        <p class="pkks-admin-brand">Правовая контора К. Сопрачева</p>
        <h1 id="pkks-admin-reset-title">Восстановление доступа</h1>
        <p class="pkks-admin-lead">Укажите e-mail, связанный с доступом.</p>
        <?php if ($sent) { pkks_admin_render_notice('Проверьте почту', 'Если адрес подходит, инструкция уже подготовлена.'); } ?>
        <form class="pkks-admin-login-form" action="/admin/request-password-reset.php" method="post">
            <?php echo pkks_admin_csrf_field(); ?>
            <label for="pkks-admin-reset-email">E-mail</label>
            <input id="pkks-admin-reset-email" name="email" type="email" autocomplete="email" required>
            <button type="submit">Продолжить</button>
        </form>
        <p class="pkks-admin-footnote"><a href="/admin/login.php">Вернуться ко входу</a></p>
    </section>
<?php pkks_admin_render_footer([['href' => '/', 'label' => 'Вернуться на сайт']]);
