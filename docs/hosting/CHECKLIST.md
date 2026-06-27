# Чек-лист после загрузки сайта

Отмечать пункты нужно после загрузки файлов на хостинг и настройки `config/admin-auth.php`.

## URL для проверки

- [ ] `/`
- [ ] `/index.php`
- [ ] `/index.html`
- [ ] `/admin/login.php`
- [ ] `/admin/index.php`
- [ ] `/admin/team.php`
- [ ] `/admin/services.php`
- [ ] `/admin/prices.php`
- [ ] `/admin/logout.php`

## Публичная часть

- [ ] Открывается главная страница `/`.
- [ ] `/` открывает PHP-render, а не старый `index.html`.
- [ ] `/index.php` открывается.
- [ ] `/index.html` открывается как fallback.
- [ ] Блок «О нас» отображает сотрудников из `data/team.json`.
- [ ] Блок «Услуги» отображает услуги из `data/services.json`.
- [ ] Блок «Цена» отображает цены из `data/prices.json`.
- [ ] Карта открывается.
- [ ] Кнопка оплаты ведёт на прежний адрес.
- [ ] Нет битой кириллицы.
- [ ] Нет PHP warnings/notices на странице.
- [ ] На мобильном нет горизонтального скролла.

## Админ-панель

- [ ] `/admin/login.php` открывает форму входа.
- [ ] Вход в админку работает с логином и паролем из `config/admin-auth.php`.
- [ ] После входа открывается `/admin/index.php`.
- [ ] `/admin/team.php` открывает редактор сотрудников.
- [ ] `/admin/services.php` открывает редактор услуг.
- [ ] `/admin/prices.php` открывает редактор цен.
- [ ] Закрытые страницы без сессии редиректят на `/admin/login.php`.
- [ ] No-op save сотрудников создаёт backup и запись audit log.
- [ ] No-op save услуг создаёт backup и запись audit log.
- [ ] No-op save цен создаёт backup и запись audit log.
- [ ] После `/admin/logout.php` закрытые страницы снова недоступны без входа.
- [ ] Ручной сброс пароля возможен через замену `admin_password_hash` в `config/admin-auth.php`.

No-op save - это сохранение формы без смыслового изменения текста. Перед такой проверкой убедитесь, что есть backup текущего файла или что изменение допустимо для заказчика.

## Загрузка фото сотрудников

- [ ] Войти в `/admin/team.php`.
- [ ] Загрузить тестовое фото сотрудника в формате jpg, jpeg, png или webp размером до 3 MB.
- [ ] Проверить redirect на `/admin/team.php?status=photo-saved`.
- [ ] Проверить, что фото появилось в `img/team/`.
- [ ] Проверить, что путь к фото сохранился в `data/team.json`.
- [ ] Проверить, что фото отображается на публичной странице.
- [ ] Проверить, что backup `data/backups/team/team-*.json` создан.
- [ ] Проверить audit event `team_photo_update` в `storage/logs/admin-audit.log`.
- [ ] Проверить, что старое фото не удалилось автоматически.
- [ ] Проверить, что `img/team/` не показывает directory listing.

## Runtime и служебные файлы

- [ ] `/includes/bootstrap.php` не открывается напрямую.
- [ ] `/includes/json-storage.php` не открывается напрямую.
- [ ] `/config/admin-auth.php` не открывается напрямую.
- [ ] `/storage/login-attempts.json` не открывается напрямую.
- [ ] `/storage/logs/admin-audit.log` не открывается напрямую.
- [ ] JSON backup из `data/backups/team/` не скачивается напрямую.
- [ ] JSON backup из `data/backups/services/` не скачивается напрямую.
- [ ] JSON backup из `data/backups/prices/` не скачивается напрямую.
- [ ] Directory listing служебных папок выключен.
- [ ] PHP-скрипты не могут выполняться из публичной upload-папки `img/team/`.

## Как понять, что `/` открыл PHP-render

Ожидаемый признак PHP-render:

- сотрудники, услуги и цены отображаются из JSON-данных;
- `/index.php` и `/` дают одинаковую актуальную версию сайта.

Если `/index.php` показывает актуальные JSON-блоки, а `/` показывает старый вариант, проблема почти наверняка в приоритете `index.html` над `index.php`.

## Что считать ошибкой

Ошибкой считается:

- белый экран;
- `500 Internal Server Error`;
- вместо сайта выводится PHP-код;
- не отображаются сотрудники, услуги или цены;
- кириллица превратилась в кракозябры;
- `/` открывает старый `index.html` вместо `index.php`;
- закрытые страницы админки открываются без сессии;
- вход в админку не работает после настройки `config/admin-auth.php`;
- save в админке не создаёт backup;
- save в админке не пишет audit log;
- загрузка фото сотрудника не создаёт backup `data/team.json`;
- загрузка фото сотрудника не пишет audit event `team_photo_update`;
- после успешной загрузки фото нет redirect на `/admin/team.php?status=photo-saved`;
- файл фото не появляется в `img/team/` или не отображается на публичной странице;
- `includes/*.php` открываются напрямую;
- `config/admin-auth.php` открывается напрямую;
- runtime-файлы из `storage/` открываются напрямую;
- backup-файлы из `data/backups/` открываются напрямую;
- на странице видны PHP warnings/notices;
- на мобильной ширине появляется горизонтальный скролл.

## Что проверить при ошибке

- Версия PHP на хостинге.
- Включено ли JSON-расширение PHP.
- Работают ли PHP sessions.
- Создан ли `config/admin-auth.php`.
- Корректно ли вставлены `admin_login` и `admin_password_hash`.
- Загружены ли `data/team.json`, `data/services.json`, `data/prices.json`.
- Есть ли у PHP право читать и писать нужные `data/*.json`.
- Есть ли у PHP право писать в `data/backups/*/`.
- Есть ли у PHP право писать в `img/team/`.
- Есть ли у PHP право писать в `storage/logs/` и `storage/login-attempts.json`.
- Разрешает ли PHP загрузку файлов до 3 MB.
- Доступно ли расширение fileinfo; если нет, проходит ли fallback-проверка через `getimagesize`.
- Стоит ли `index.php` выше `index.html` в настройках index-файлов.
- Поддерживает ли хостинг `.htaccess`.
- Закрыт ли прямой доступ к `config/`, `storage/`, `data/backups/` и `includes/`.
- Есть ли ошибки в PHP error log хостинга.
