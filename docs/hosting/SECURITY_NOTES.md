# Заметки по безопасности

Этот документ описывает безопасность текущего состояния сайта и рабочей MVP-админки.

## Текущее состояние

- Публичная часть сайта открывается через `index.php`.
- Данные публичных блоков лежат в `data/team.json`, `data/services.json`, `data/prices.json`.
- Админ-панель находится в `/admin/`.
- Вход в админку работает через `config/admin-auth.php`.
- Пароль проверяется через `password_hash` / `password_verify`.
- Формы защищены CSRF-токеном.
- Попытки входа ограничиваются rate-limit через `storage/login-attempts.json`.
- Перед сохранением создаётся JSON backup.
- Действия администратора пишутся в audit log.

## Что должно быть недоступно из web

Нужно проверить, что браузер не может скачать:

- `config/admin-auth.php`;
- `storage/`;
- `storage/login-attempts.json`;
- `storage/logs/admin-audit.log`;
- `data/backups/`;
- `data/backups/team/*.json`;
- `data/backups/services/*.json`;
- `data/backups/prices/*.json`;
- `includes/*.php`.

Если любой из этих файлов открывается напрямую, передачу сайта нельзя считать завершённой до настройки запрета доступа.

## Apache-compatible hosting

На Apache-compatible hosting файлы `.htaccess` могут закрывать часть служебных зон:

- `includes/.htaccess` закрывает прямой доступ к `includes/`;
- `storage/.htaccess` закрывает прямой доступ к `storage/`;
- корневой `.htaccess` задаёт приоритет `index.php` перед `index.html`.

Но `.htaccess` работает только там, где хостинг его поддерживает и не переопределяет правила. Отдельно нужно проверить запрет доступа к:

- `config/admin-auth.php`;
- `data/backups/`.

## Non-Apache hosting

На Nginx и других non-Apache hosting файлы `.htaccess` не работают.

Там нужно отдельно настроить запрет прямого доступа к:

- `config/admin-auth.php`;
- `storage/`;
- `data/backups/`;
- `includes/`.

Конкретные правила зависят от хостинга. Их должен настроить владелец хостинга или техническая поддержка.

## Admin config

Файл `config/admin-auth.php` должен:

- создаваться на хостинге из `config/admin-auth.php.example`;
- содержать только login и password hash, без plaintext-пароля;
- быть недоступным из web;
- не попадать в репозиторий;
- не попадать в публичные архивы, тикеты и переписку.

После передачи заказчику нужно заменить любые local/test credentials на production-логин и новый сильный пароль.

## Пароль и сброс доступа

- Не использовать простой пароль.
- Не хранить plaintext password.
- Не отправлять пароль вместе с hash в одном сообщении.
- Не загружать `config/admin-auth.php` в публичные места.
- Для сброса доступа сгенерировать новый hash и заменить `admin_password_hash` в `config/admin-auth.php` на хостинге.
- Старый пароль восстановить нельзя: он не должен храниться в открытом виде.

## Runtime-файлы

Runtime-файлы не должны коммититься и не должны скачиваться из браузера:

- `config/admin-auth.php`;
- `storage/login-attempts.json`;
- `storage/logs/*.log`;
- `data/backups/**/*.json`;

В репозитории допустимы только placeholder-файлы вроде `.gitkeep`, чтобы нужные папки существовали после загрузки.

## Audit log

Audit log нужен для технической проверки действий администратора, но он не должен быть публичным.

Не публикуйте:

- `storage/logs/admin-audit.log`;
- выгрузки audit log;
- скриншоты или фрагменты audit log с IP, user-agent или служебными деталями.

## Права на запись

PHP должен иметь минимальные права, достаточные для записи в:

- `data/team.json`;
- `data/services.json`;
- `data/prices.json`;
- `data/backups/team/`;
- `data/backups/services/`;
- `data/backups/prices/`;
- `storage/logs/`;
- `storage/login-attempts.json`.

Не используйте `777` как стандартную настройку. Если хостинг требует слишком широкие права, нужно уточнить безопасный вариант у поддержки хостинга.

## Что нельзя делать

- Не давать публичный доступ к `config/admin-auth.php`.
- Не давать публичный доступ к `storage/`.
- Не давать публичный доступ к `data/backups/`.
- Не публиковать audit log.
- Не хранить plaintext password.
- Не включать directory listing для служебных папок.
- Не считать `.htaccess` достаточной защитой на non-Apache hosting.
- Не обещать production-безопасность без проверки настроек конкретного хостинга.
