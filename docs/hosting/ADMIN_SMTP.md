# Ручная активация и SMTP-доставка административного кабинета

Публичный сайт, `data/*.json` и UI админки в этот контур не входят.

## Текущий production no-mail режим

Для запуска без SMTP runtime-config вне `DOCUMENT_ROOT` содержит `PKKS_ADMIN_AUTH_DELIVERY_MODE=manual`, `PKKS_ADMIN_MAIL_TRANSPORT=disabled` и e-mail primary_admin. Почтовое восстановление и UI-приглашения скрываются; PHPMailer не вызывается.

Одноразовые ссылки создаёт только CLI-команда `manual-setup`. Она получает e-mail technical_admin из отдельного защищённого входного файла и записывает ссылки только в закрытый output-файл вне web-root. Токены не выводятся в терминал, не попадают в Git и хранятся в SQLite только в виде SHA-256-хеша.

Формат закрытого входного файла: одна строка `TECHNICAL_ADMIN_EMAIL=<адрес>`. Запуск выполняют только с абсолютными путями вне `DOCUMENT_ROOT`:

```sh
php admin/cli/auth.php manual-setup /absolute/private/technical-input.txt /absolute/private/setup-links.txt
```

Для перевыпуска одной ссылки используют `manual-link primary_admin` или `manual-link technical_admin`; предыдущая неиспользованная ссылка этого пользователя отзывается.

## Сборка пакета

Сборку выполняют из чистого опубликованного commit с `composer.lock`. В изолированной staging-директории запускают:

```text
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction --no-scripts
```

В deploy-пакет входят `composer.json`, `composer.lock`, `vendor/autoload.php`, `vendor/composer/`, `vendor/phpmailer/` и разрешённые auth-файлы. Локальный `vendor/` не коммитят.

## Закрытая runtime-конфигурация

Создайте PHP-файл вне `DOCUMENT_ROOT`, с правами `0600`, и передайте его абсолютный путь только через `PKKS_ADMIN_RUNTIME_CONFIG`. Файл возвращает массив с ключами из [`config/admin-auth-runtime.php.example`](../../config/admin-auth-runtime.php.example).

Для production обязательны абсолютный путь SQLite вне публичной зоны и trusted HTTPS URL. SMTP-параметры добавляют только при отдельном согласованном почтовом контуре. Тогда допустимы только пары `starttls`/`587` или `smtps`/`465`; TLS-проверку сертификата не отключают.

`local` предназначен только для изолированных тестов: он принимает исключительно адреса `.invalid` и пишет сообщения во внешний outbox. В production его не используют.

Не записывайте SMTP host, учётные данные, пароль, адреса получателей или токены в Git, deploy-manifest, логи и отчёты.
