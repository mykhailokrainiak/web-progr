# Лабораторна робота №8. Робота з даними XML та JSON

**Крайняк Михайло Юрійович, 451 група**
Варіант 10 — розклад маршрутів автовокзалу. Звіт: [report.pdf](report.pdf).

## Схема запису «рейс»

| Поле | Тип у JSON | Тип у XML | Обов’язкове | Допустимі значення |
| --- | --- | --- | --- | --- |
| `id` | рядок | текст | так | 16 шістнадцяткових символів, генерує сервер (`bin2hex(random_bytes(8))`), унікальний |
| `number` | рядок | текст | так | номер рейсу, 1–10 літер, цифр або дефісів (`105`, `212-А`) |
| `destination` | рядок | текст | так | пункт призначення, 2–100 символів (`mb_strlen`), без керівних символів |
| `departure` | рядок | текст | так | час відправлення `ГГ:ХХ`, 00:00–23:59 |
| `platform` | ціле число | текст із цифр | так | 1–20 |
| `price` | ціле число | текст із цифр | так | ціна квитка, грн, 1–5000 |

XML: корінь `<routes>`, записи `<route>`, поля — дочірні елементи без атрибутів.
JSON: масив об’єктів із тими самими ключами.

## Структура

```
lab 08/
├── index.php            меню роботи
├── src/                 готові приклади (persons.*) + власні dom_walk.php, add_fourth.php
├── shared/              спільний код обох версій
│   ├── routes.php       схема полів, перевірки, CSRF, блокування, flash-повідомлення
│   ├── layout.php       HTML-шаблон і форма
│   ├── catalog_page.php перегляд розкладу
│   ├── add_page.php     додавання рейсу
│   └── edit_page.php    редагування за id
├── xml/                 XML-версія: storage.php (DOM), routes.xml, index/add/edit.php
├── json/                JSON-версія: storage.php (json_*), routes.json, index/add/edit.php
├── data-initial/        початкові тестові дані (3 вигадані рейси)
├── tools/checks.sh      автоматичні перевірки (curl) і їх результат checks-output.txt
└── report.pdf           звіт
```

## Запуск

Потрібен PHP 8.1+ з розширеннями `dom`, `libxml`, `ctype`, `mbstring` (перевірка: `php -v`, `php -m`).

**OSPanel / XAMPP (Apache).** Скопіюйте вміст папки в окремий каталог, наприклад `C:\xampp\htdocs\lab-08`
(інші проєкти в `htdocs` не видаляйте), і відкрийте `http://localhost/lab-08/`
(для OSPanel — `http://web-prog.local/lab-08/`). Процес PHP потребує права запису
до `xml/routes.xml`, `json/routes.json` і самих каталогів `xml`, `json` (файл блокування `routes.lock`).

**Вбудований сервер PHP** (з цієї папки):

```bash
php -S 127.0.0.1:8000 -t .
```

У XAMPP без PHP у PATH: `C:\xampp\php\php.exe -S 127.0.0.1:8000 -t .`
Далі відкрийте `http://127.0.0.1:8000/xml/index.php` або `http://127.0.0.1:8000/json/index.php`.

Повернути початкові дані:

```bash
cp data-initial/routes.xml xml/routes.xml
cp data-initial/routes.json json/routes.json
```

Генератори прикладів запускаються лише з термінала в каталозі `src`:
`php write_persons_xml.php`, `php write_persons_json.php`, `php add_fourth.php` (працює з копіями `persons-copy.*`).

## Перевірки

При запущеному сервері: `bash tools/checks.sh` (Git Bash). Скрипт тимчасово підміняє файли даних
(пошкоджений XML/JSON, DTD, `{}`, `null`, неправильні типи, файл лише для читання тощо) і наприкінці
відновлює їх із `data-initial/`. Результат останнього запуску — [tools/checks-output.txt](tools/checks-output.txt).

Усі дані вигадані. Сесійні ідентифікатори й секрети в репозиторії не зберігаються.
