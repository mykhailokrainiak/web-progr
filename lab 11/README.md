# Лабораторна робота №11. Робота з СКБД MySQL та оболонкою phpMyAdmin

**Крайняк Михайло Юрійович, 451 група**
Варіант 10 (той самий, що в ЛР 8) — розклад маршрутів автовокзалу. Звіт: [report.pdf](report.pdf).

## Що зроблено

- **Готові приклади** (без змін): `src/schema.sql`, `src/common.php`, `src/index.php`, `src/crud-demo.php`.
- **Товари, крок 7** (власне): `products.php` — список, `product-form.php` — додавання й редагування за id,
  `product-delete.php` — видалення з підтвердженням; `app.php` — сесія, CSRF, шаблон.
- **Варіант 10, крок 8** (власне): таблиця `bus_routes` ([sql/routes-schema.sql](sql/routes-schema.sql), 5 рейсів),
  `routes.php` — розклад і точний пошук за номером рейсу, `route-form.php`, `route-delete.php`,
  `routes-import.php` — імпорт каталогу з ЛР 8 (`data/routes-lab08.json` / `.xml`) у транзакції.
- **Крок 4**: `fetch-demo.php` — порівняння `mysqli_fetch_assoc/row/object/array`.
- **Крок 9**: [export/lab11.sql](export/lab11.sql) — експорт phpMyAdmin; [tools/restore-check.txt](tools/restore-check.txt) —
  відновлення в іншу базу та порівняння; [sql/routes-checks.sql](sql/routes-checks.sql) — повернення CHECK-обмежень,
  яких phpMyAdmin 5.2.3 не експортує.

Усі запити з введенням — `mysqli_prepare` + `mysqli_stmt_bind_param`; дані змінює лише POST із CSRF-токеном;
після збереження — перенаправлення 303; виведення — через `e()` (`htmlspecialchars`).

## Запуск (XAMPP / OSPanel)

1. Запустіть Apache і MariaDB/MySQL, відкрийте phpMyAdmin.
2. Адміністратором виконайте [sql/app-user.sql](sql/app-user.sql), замінивши пароль на власний
   (створює базу `lab11` і користувача `lab11_app` з правами SELECT, INSERT, UPDATE, DELETE).
3. Виберіть базу `lab11` → вкладка **SQL** (або **Імпорт**): виконайте `src/schema.sql`, потім `sql/routes-schema.sql`.
4. Задайте змінні середовища PHP з [env.example](env.example): `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD`.
   Для Apache — `SetEnv` у локальній конфігурації (наприклад, `httpd-xampp.conf`), не в репозиторії.
5. Скопіюйте папку в окремий каталог, наприклад `C:\xampp\htdocs\lab-11`, і відкрийте `http://localhost/lab-11/src/routes.php`.

Вбудований сервер PHP (Git Bash, з цієї папки):

```bash
export DB_HOST=127.0.0.1 DB_PORT=3306 DB_NAME=lab11 DB_USER=lab11_app DB_PASSWORD='ваш пароль'
php -S 127.0.0.1:8000 -t .
```

Сторінки: `http://127.0.0.1:8000/src/index.php`, `.../src/products.php`, `.../src/routes.php`.

Консольні сценарії (з каталогу `src`, з тими самими змінними): `php crud-demo.php`, `php fetch-demo.php`,
`php routes-import.php json` (повторний імпорт або `xml` з тими самими рейсами відкочується повністю).

## Перевірки

`bash tools/checks.sh` — сценарії з таблиці завдання (потрібні сервери з правильним і неправильним паролем,
див. початок скрипта). Тестові записи створюються й видаляються через вебформи.
Результати: [tools/checks-output.txt](tools/checks-output.txt), [tools/cli-output.txt](tools/cli-output.txt),
[tools/restore-check.txt](tools/restore-check.txt).

Перевірено на PHP 8.2.12 (XAMPP), MariaDB 10.11.19, phpMyAdmin 5.2.3. Усі дані вигадані; реквізити підключення
в репозиторії відсутні.
