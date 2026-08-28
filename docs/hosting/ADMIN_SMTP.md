# SMTP-доставка административного кабинета

Этот документ описывает только production-почту invitation/reset. Публичный сайт, `data/*.json` и UI админки в этот контур не входят.

## Сборка пакета

Сборку выполняют из чистого опубликованного commit с `composer.lock`. В изолированной staging-директории запускают:

```text
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction --no-scripts
```

В deploy-пакет входят `composer.json`, `composer.lock`, `vendor/autoload.php`, `vendor/composer/`, `vendor/phpmailer/` и разрешённые auth-файлы. Локальный `vendor/` не коммитят.

## Закрытая runtime-конфигурация

Создайте PHP-файл вне `DOCUMENT_ROOT`, с правами `0600`, и передайте его абсолютный путь только через `PKKS_ADMIN_RUNTIME_CONFIG`. Файл возвращает массив с ключами из [`config/admin-auth-runtime.php.example`](../../config/admin-auth-runtime.php.example).

Для production обязательны абсолютный путь SQLite вне публичной зоны, trusted HTTPS URL и SMTP-параметры. Допустимы только пары `starttls`/`587` или `smtps`/`465`; TLS-проверку сертификата не отключают. Значение `PKKS_ADMIN_MAIL_TRANSPORT` должно быть `smtp`.

`local` предназначен только для изолированных тестов: он принимает исключительно адреса `.invalid` и пишет сообщения во внешний outbox. В production его не используют.

Не записывайте SMTP host, учётные данные, пароль, адреса получателей или токены в Git, deploy-manifest, логи и отчёты.
