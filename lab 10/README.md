# Лабораторна робота №10. Використання програми Postman для емуляції дій користувача сайту

**Крайняк Михайло Юрійович, 451 група.** Звіт: [report.pdf](report.pdf).

## Склад

| Шлях | Що це |
| --- | --- |
| `src/api.php`, `src/common.php`, `src/reset.php`, `src/sample.txt` | Готовий навчальний API «Список завдань»; в `api.php` власні рядки позначено коментарем «Власне доповнення» |
| `src/search.php` | **Власне доповнення:** фільтр `q` для GET списку (підрядок title без урахування регістру, до 40 символів після `trim()`, разом із `done`, відхиляється разом з `id`) |
| `postman/lab10.postman_collection.json` | Власна колекція Postman (v2.1): 41 запит у 5 папках, у кожному Post-response тести статусу й змісту |
| `postman/lab10.postman_environment.json` | Середовище `Lab10 Local`: `base_url`, `task_id` (порожній) |
| `results/` | Результати прогону: вивід Newman (`newman-cli.txt`), JSON-журнал, HTML-звіт, самоперевірка тесту, прогін колекції викладача |
| `tools/` | Генератор колекції та сторінок для знімків звіту |

## Запуск

```bash
cd src
php reset.php
php -d display_errors=0 -d log_errors=1 -S 127.0.0.1:8080
```

API: `http://127.0.0.1:8080/api.php` (для XAMPP: `http://localhost/lab-10/src/api.php` — тоді змініть `base_url` у середовищі).

**Postman:** Import → обидва файли з `postman/`, виберіть середовище `Lab10 Local`, запустіть папки 01–04 у Collection Runner
(одна ітерація, після `php reset.php`). У папці 05 для поля `attachment` виберіть `src/sample.txt` вручну.

**Newman** (CLI-ранер Postman, `npm install -g newman`), з каталогу роботи після `php reset.php`:

```bash
newman run postman/lab10.postman_collection.json -e postman/lab10.postman_environment.json --working-dir src
```

Очікуваний результат: 41 запит, 172 перевірки, 0 невдалих.

## Пошук q (власне доповнення)

| Запит | Результат |
| --- | --- |
| `?resource=tasks&q=ПЕРЕВІРИТИ` | 200, «Перевірити GET» |
| `?resource=tasks&q=автобус` | 200, `data: []`, `meta.count: 0` |
| `?resource=tasks&q=и&done=0` | 200, лише невиконане завдання (обидва фільтри) |
| `?resource=tasks&q[]=x` | 400 «Параметр q має бути рядком.» |
| `?resource=tasks&id=1&q=x` | 400 «Для одного запису не додавайте фільтр q.» |
| `q` із 41 символу | 400; `q` з пробілів — пошуку немає (повний список) |

Усі дані вигадані; Basic Auth `student` / `demo-pass` — відкриті демонстраційні значення з коду вправи.
