<?php
declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

/* SMTP-параметры читаются только из закрытого runtime-конфига или окружения. */
function pkks_admin_auth_mail_setting(string $name, bool $required = true): ?string
{
    return pkks_admin_auth_setting('PKKS_ADMIN_' . $name, $required);
}

function pkks_admin_auth_mail_header(string $value, string $label): string
{
    $value = trim($value);

    if ($value === '' || preg_match('/[\r\n]/', $value) === 1) {
        throw new InvalidArgumentException('Параметр почтового сообщения недействителен: ' . $label . '.');
    }

    return $value;
}

function pkks_admin_auth_mail_url(string $path, string $token): string
{
    if ($path === '' || $path[0] !== '/' || preg_match('/[\r\n]/', $path) === 1) {
        throw new InvalidArgumentException('Путь уведомления недействителен.');
    }

    return pkks_admin_auth_base_url() . $path . '?token=' . rawurlencode($token);
}

function pkks_admin_auth_smtp_settings(): array
{
    $host = pkks_admin_auth_mail_header((string)pkks_admin_auth_mail_setting('SMTP_HOST'), 'host');
    $port = filter_var(pkks_admin_auth_mail_setting('SMTP_PORT'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]);
    $encryption = strtolower((string)pkks_admin_auth_mail_setting('SMTP_ENCRYPTION'));
    $timeout = filter_var(pkks_admin_auth_mail_setting('SMTP_TIMEOUT'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 120]]);
    $fromAddress = pkks_admin_auth_mail_header((string)pkks_admin_auth_mail_setting('SMTP_FROM_ADDRESS'), 'from_address');
    $fromName = pkks_admin_auth_mail_header((string)pkks_admin_auth_mail_setting('SMTP_FROM_NAME'), 'from_name');
    $auth = filter_var(pkks_admin_auth_mail_setting('SMTP_AUTH', false) ?? '0', FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

    if (filter_var($host, FILTER_VALIDATE_IP) === false && filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false) {
        throw new InvalidArgumentException('SMTP host недействителен.');
    }
    if ($port === false || $timeout === false || filter_var($fromAddress, FILTER_VALIDATE_EMAIL) === false || $auth === null) {
        throw new InvalidArgumentException('SMTP-конфигурация недействительна.');
    }
    if (($encryption === 'smtps' && $port !== 465) || ($encryption === 'starttls' && $port !== 587)) {
        throw new InvalidArgumentException('SMTP encryption и port должны образовывать подтверждённую пару.');
    }
    if (!in_array($encryption, ['smtps', 'starttls'], true)) {
        throw new InvalidArgumentException('SMTP encryption не поддерживается.');
    }

    $username = pkks_admin_auth_mail_setting('SMTP_USERNAME', $auth);
    $password = pkks_admin_auth_mail_setting('SMTP_PASSWORD', $auth);
    if ($auth && ($username === null || $password === null || preg_match('/[\r\n]/', $username . $password) === 1)) {
        throw new InvalidArgumentException('SMTP-аутентификация не настроена.');
    }
    if (!$auth && (($username !== null && $username !== '') || ($password !== null && $password !== ''))) {
        throw new InvalidArgumentException('SMTP-учётные данные требуют явной аутентификации.');
    }

    return compact('host', 'port', 'encryption', 'timeout', 'fromAddress', 'fromName', 'auth', 'username', 'password');
}

function pkks_admin_auth_deliver_smtp(string $email, string $subject, string $path, string $token): void
{
    $email = pkks_admin_auth_mail_header($email, 'recipient');
    $subject = pkks_admin_auth_mail_header($subject, 'subject');
    if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        throw new InvalidArgumentException('Получатель уведомления недействителен.');
    }
    if (str_ends_with(strtolower($email), '.invalid')) {
        throw new InvalidArgumentException('Тестовый получатель допустим только для local transport.');
    }

    $autoload = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
    if (!is_file($autoload)) {
        throw new RuntimeException('SMTP-зависимость не собрана.');
    }
    require_once $autoload;

    $settings = pkks_admin_auth_smtp_settings();
    $url = pkks_admin_auth_mail_url($path, $token);

    try {
        $mail = new PHPMailer(true);
        $mail->CharSet = PHPMailer::CHARSET_UTF8;
        $mail->Encoding = PHPMailer::ENCODING_8BIT;
        $mail->isSMTP();
        $mail->Host = $settings['host'];
        $mail->Port = $settings['port'];
        $mail->SMTPAuth = $settings['auth'];
        $mail->SMTPDebug = SMTP::DEBUG_OFF;
        $mail->Timeout = $settings['timeout'];
        $mail->Timelimit = $settings['timeout'];
        $mail->SMTPAutoTLS = false;
        $mail->SMTPSecure = $settings['encryption'] === 'smtps' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        if ($settings['auth']) {
            $mail->Username = $settings['username'];
            $mail->Password = $settings['password'];
        }
        $mail->setFrom($settings['fromAddress'], $settings['fromName']);
        $mail->addAddress($email);
        $mail->Subject = $subject;
        $mail->isHTML(true);
        $mail->Body = '<p>Для продолжения откройте <a href="' . htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">защищённую ссылку</a>.</p>';
        $mail->AltBody = "Для продолжения откройте защищённую ссылку:\n" . $url;
        $mail->send();
    } catch (Throwable) {
        /* Внешние SMTP-ошибки и служебные параметры никогда не выдаются пользователю. */
        throw new RuntimeException('Не удалось доставить защищённое уведомление.');
    }
}
