<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/admin-layout.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET'; $token = is_string($_REQUEST['token'] ?? null) ? $_REQUEST['token'] : ''; $error = null; $done = false;
if ($method === 'POST') {
    pkks_admin_start_session(); pkks_admin_require_csrf(is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : null);
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : ''; $confirmation = is_string($_POST['password_confirmation'] ?? null) ? $_POST['password_confirmation'] : '';
    if ($password !== $confirmation || !pkks_admin_auth_password_is_valid($password)) { $error = 'Укажите одинаковый пароль длиной от 15 до 64 символов.'; }
    elseif (!pkks_admin_auth_consume_token_and_set_password($token, 'reset', $password)) { $error = 'Ссылка недействительна или уже использована.'; } else { $done = true; }
}
pkks_admin_render_header('Новый пароль', ['body_class' => 'pkks-admin-login-page']);
?>
    <section class="pkks-admin-auth-card" aria-labelledby="pkks-admin-new-password-title"><p class="pkks-admin-brand">Правовая контора К. Сопрачева</p><h1 id="pkks-admin-new-password-title">Новый пароль</h1>
        <?php if ($done) { pkks_admin_render_notice('Пароль изменён', 'Теперь можно войти с новым паролем.'); ?><p class="pkks-admin-footnote"><a href="/admin/login.php">Перейти ко входу</a></p>
        <?php } else { if ($error !== null) { pkks_admin_render_notice('Не удалось изменить пароль', $error); } ?><form class="pkks-admin-login-form" action="/admin/reset-password.php" method="post"><?php echo pkks_admin_csrf_field(); ?><input type="hidden" name="token" value="<?php echo pkks_admin_escape($token); ?>"><label for="pkks-admin-password">Новый пароль</label><input id="pkks-admin-password" name="password" type="password" autocomplete="new-password" required><label for="pkks-admin-password-confirmation">Повторите пароль</label><input id="pkks-admin-password-confirmation" name="password_confirmation" type="password" autocomplete="new-password" required><button type="submit">Сохранить пароль</button></form><?php } ?>
    </section>
<?php pkks_admin_render_footer([['href' => '/admin/login.php', 'label' => 'К экрану входа']]);
