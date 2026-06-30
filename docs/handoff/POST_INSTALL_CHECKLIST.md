# Post-install checklist

## Публичная часть

- Публичная главная открывается.
- `/index.php` открывается.
- CSS/JS/assets загружаются.
- GSAP ScrollSmoother работает.
- Hero overlap работает на desktop.
- Mobile menu работает.
- Anchors работают.
- Open Graph image отдаёт 200.
- Console errors нет.
- Horizontal overflow нет.

## Админ-панель

- Admin login открывается.
- Admin protected pages не открываются без входа.
- Noindex admin работает через `robots.txt`, meta robots или X-Robots-Tag.
- Customer-friendly статусы и тексты админки не заменены техническими кодами.

## Безопасность и окружение

- `/debug.log` не отдаётся.
- `.env`, logs, dumps, backups и temp-файлы не отдаются публично.
- SSL включён.
- Финальный домен проверен.
- Права записи настроены только для нужных runtime-папок.

## Завершающие действия

- Проверить canonical, sitemap и metadata после подтверждения финального домена.
- Выполнить authenticated admin smoke только после разрешения владельца и получения временных credentials.
- Зафиксировать результат проверки в отдельном отчёте передачи.
