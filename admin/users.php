<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/rate-limit.php';
require_once __DIR__ . '/includes/admin-layout.php';

pkks_admin_require_role('primary_admin');
$manualDelivery = pkks_admin_auth_is_manual_delivery();
$message = null; $error = null; $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'POST') {
    pkks_admin_require_csrf(is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : null);
    $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : ''; $email = is_string($_POST['email'] ?? null) ? $_POST['email'] : ''; $id = (int)($_POST['user_id'] ?? 0);
    try {
        if ($manualDelivery && in_array($action, ['invite', 'resend'], true)) {
            throw new RuntimeException('Почтовые приглашения отключены.');
        } elseif ($action === 'invite') {
            if (pkks_admin_is_login_blocked($email)) { throw new RuntimeException('Повторите попытку позже.'); }
            $user = pkks_admin_auth_create_technical($email); $token = pkks_admin_auth_create_token((int)$user['id'], 'invite'); pkks_admin_auth_deliver_mail((string)$user['email'], 'Приглашение в кабинет', '/admin/accept-invite.php', $token); pkks_admin_record_login_attempt($email, true); $message = 'Приглашение подготовлено.';
        } elseif ($action === 'resend') {
            $user = pkks_admin_auth_user_by_id($id); if ($user === null || $user['role'] !== 'technical_admin') { throw new RuntimeException('Доступ не найден.'); } $token = pkks_admin_auth_create_token($id, 'invite'); pkks_admin_auth_deliver_mail((string)$user['email'], 'Приглашение в кабинет', '/admin/accept-invite.php', $token); $message = 'Новое приглашение подготовлено.';
        } elseif ($action === 'disable') { pkks_admin_auth_set_technical_active($id, false); $message = 'Технический доступ отключён.'; }
        elseif ($action === 'enable') { pkks_admin_auth_set_technical_active($id, true); $message = 'Технический доступ включён.'; }
        else { throw new RuntimeException('Действие не поддерживается.'); }
    } catch (Throwable $exception) { $error = 'Не удалось выполнить действие.'; }
}
$users = pkks_admin_auth_users();
pkks_admin_render_header('Пользователи и доступ', ['body_class' => 'pkks-admin-dashboard-page']);
pkks_admin_render_topbar('Пользователи и доступ', 'Основной доступ');
?>
    <section class="pkks-admin-dashboard-intro"><div><p class="pkks-admin-eyebrow">Доступ</p><h2>Пользователи и доступ</h2><p><?php echo $manualDelivery ? 'Подключение нового доступа выполняется отдельно.' : 'Здесь можно подготовить приглашение или отключить технический доступ.'; ?></p></div><div class="pkks-admin-dashboard-actions"><a class="pkks-admin-button pkks-admin-button--secondary" href="/admin/index.php">Назад</a></div></section>
    <?php if ($message !== null) { pkks_admin_render_notice('Готово', $message); } if ($error !== null) { pkks_admin_render_notice('Не удалось выполнить действие', $error); } ?>
    <?php if (!$manualDelivery): ?><section class="pkks-admin-section-card"><h2>Пригласить технического администратора</h2><p>Приглашение действует сутки. Новый запрос отменяет предыдущее приглашение.</p><form class="pkks-admin-login-form" action="/admin/users.php" method="post"><?php echo pkks_admin_csrf_field(); ?><input type="hidden" name="action" value="invite"><label for="pkks-admin-user-email">E-mail</label><input id="pkks-admin-user-email" name="email" type="email" autocomplete="email" required><button type="submit">Подготовить приглашение</button></form></section><?php endif; ?>
    <section class="pkks-admin-section-grid" aria-label="Пользователи">
    <?php foreach ($users as $user): ?>
        <article class="pkks-admin-section-card"><div><h2><?php echo pkks_admin_escape((string)$user['email']); ?></h2><p><?php echo $user['role'] === 'primary_admin' ? 'Основной администратор' : 'Технический администратор'; ?> · <?php echo (int)$user['active'] === 1 ? 'доступ активен' : 'доступ отключён'; ?></p></div>
        <?php if ($user['role'] === 'technical_admin'): ?><form class="pkks-admin-login-form" action="/admin/users.php" method="post"><?php echo pkks_admin_csrf_field(); ?><input type="hidden" name="user_id" value="<?php echo (int)$user['id']; ?>"><?php if (!$manualDelivery): ?><button name="action" value="resend" type="submit">Новое приглашение</button><?php endif; ?><button name="action" value="<?php echo (int)$user['active'] === 1 ? 'disable' : 'enable'; ?>" type="submit"><?php echo (int)$user['active'] === 1 ? 'Отключить доступ' : 'Включить доступ'; ?></button></form><?php endif; ?></article>
    <?php endforeach; ?>
    </section>
<?php pkks_admin_render_footer([['href' => '/admin/index.php', 'label' => 'Назад в админ-панель'], ['href' => '/admin/logout.php', 'label' => 'Выйти']]);
