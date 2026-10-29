# Лабораторна робота №5. Передача даних між HTML-формою та PHP-сценарієм

**Крайняк Михайло Юрійович, 451 група.** Звіт: [report.pdf](report.pdf).

## Склад

| Шлях | Що це |
| --- | --- |
| `src/stage2/index.php`, `src/stage2/login.php` | Крок 2: форма з методички й окремий обробник (метод, типи, непорожність) |
| `src/stage2/query.php` | Тимчасова GET-форма з полем `query` для порівняння з POST |
| `src/index.php` | Вхід: перевірка формату (логін 3–30 `[A-Za-z0-9_]`, пароль 8–72 байти), `password_verify()` |
| `src/login-form.php`, `src/users.php` | Шаблон форми входу; масив трьох вигаданих користувачів із bcrypt-хешами |
| `src/feedback.php`, `src/feedback-form.php` | Самостійне завдання: зворотний зв’язок (ім’я, пошта, повідомлення, вкладення до 1 МіБ) |
| `src/helpers.php`, `src/style.css` | Спільні функції та стилі |
| `given/src/index001.php` | Початкова заготовка з методички |
| `tools/make_hash.php` | Одноразове створення bcrypt-хешу (пароль зі стандартного введення) |
| `tools/checks.sh` → `tools/checks-output.txt` | Перевірки curl за таблицею завдання; `tools/multipart_body.py` — сирі тіла multipart |
| `results/w3c-validation.txt` | W3C Nu HTML Checker: 0 повідомлень для 13 станів сторінок |

## Запуск

Скопіюйте вміст `src` у `htdocs/lab-05` і відкрийте `http://localhost/lab-05/`. Інший варіант — вбудований сервер PHP з каталогу, у якому `lab-05` вказує на `src`:

```bash
php -S 127.0.0.1:8050
```

Навчальні облікові записи (вигадані): `olena_k` / `Kalyna#2026`, `taras_m` / `Kobzar 1814`, `iryna_99` / `Сонях2026`.

## Перевірки

Потрібні три сервери (8050 — типові ліміти; 8051 — `-d post_max_size=2M -d upload_max_filesize=2M`; 8052 — `-d upload_max_filesize=512K -d post_max_size=8M`):

```bash
bash tools/checks.sh
```
