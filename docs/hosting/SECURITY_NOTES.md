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
- `/admin/team.php` позволяет заменить фото сотрудника, изменить Alt и Title фото, сохранить путь в `data/team.json`, создать backup и записать audit event `team_photo_update`.
- Загруженные фото сотрудников хранятся в публичной папке `img/team/`.

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

Папка `img/team/` публичная: отдельные изображения из неё должны открываться браузером, чтобы фото отображались на сайте. При этом directory listing для `img/team/` должен быть выключен, а выполнение PHP и других скриптов в upload-папке должно быть запрещено на уровне хостинга.

## Apache-compatible hosting

На Apache-compatible hosting файлы `.htaccess` могут закрывать часть служебных зон:

- `includes/.htaccess` закрывает прямой доступ к `includes/`;
- `storage/.htaccess` закрывает прямой доступ к `storage/`;
- корневой `.htaccess` задаёт приоритет `index.php` перед `index.html`.

Но `.htaccess` работает только там, где хостинг его поддерживает и не переопределяет правила. Отдельно нужно проверить запрет доступа к:

- `config/admin-auth.php`;
- `data/backups/`.
- directory listing и выполнение скриптов в `img/team/`.

## Non-Apache hosting

На Nginx и других non-Apache hosting файлы `.htaccess` не работают.

Там нужно отдельно настроить запрет прямого доступа к:

- `config/admin-auth.php`;
- `storage/`;
- `data/backups/`;
- `includes/`.
- directory listing и выполнение скриптов в `img/team/`.

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
- runtime-фото `img/team/*.jpg`, `img/team/*.jpeg`, `img/team/*.png`, `img/team/*.webp`;

В репозитории допустимы только placeholder-файлы вроде `.gitkeep`, чтобы нужные папки существовали после загрузки.

## Загрузка фото сотрудников

Для фото сотрудников разрешены только:

- jpg;
- jpeg;
- png;
- webp.

Максимальный размер файла - 3 MB.

Запрещены svg, php, phtml, html, js, pdf, doc, docx, exe и любые файлы, которые не проходят проверку изображения.

Проверка загрузки работает так:

- если на хостинге доступен PHP fileinfo, MIME проверяется через `finfo`;
- если fileinfo недоступен, используется строгая проверка через `getimagesize`;
- дополнительно проверяются расширение, MIME и то, что файл действительно является изображением.

Fallback через `getimagesize` практичен для shared hosting, но зависит от окружения хостинга. Не отключайте MIME/image validation и не добавляйте SVG в разрешённые форматы без отдельной security-задачи.

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
- `img/team/`;
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
- Не включать directory listing для `img/team/`.
- Не разрешать выполнение PHP в upload-папках.
- Не загружать SVG в качестве фото сотрудников.
- Не отключать MIME/image validation.
- Не считать `.htaccess` достаточной защитой на non-Apache hosting.
- Не коммитить runtime-фото из `img/team/`.
- Не обещать production-безопасность без проверки настроек конкретного хостинга.
