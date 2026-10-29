# Лабораторна робота №7. Робота з Cookies та механізмом сесій

**Крайняк Михайло Юрійович, 451 група.** Звіт: [report.pdf](report.pdf).

«Панель студентського клубу»: тема оформлення й історія відвідувань у cookies, список обраних заходів у сесії.

## Склад

| Шлях | Що це |
| --- | --- |
| `src/bootstrap.php` | Рядки 1–52 — готова заготовка з методички без змін; нижче позначки «Власні доповнення» — власний код (перевірка теми, POST-поля, одноразові повідомлення, шаблон сторінки) |
| `src/index.php` | Головна: поточна тема, кількість обраних, «Завершити сеанс» |
| `src/preferences.php` | Тема `light`/`dark` у cookie `club_theme` на 30 днів; «Скинути налаштування» |
| `src/visits.php` | Лічильник `club_visits` (0–999999) і час `club_last_visit` на 7 днів |
| `src/favorites.php`, `src/events.php` | Каталог заходів і `$_SESSION['favorites']`: `add`, `remove`, `clear` |
| `src/end_session.php` | POST-обробник «Завершити сеанс» |
| `src/style.css` | Теми `theme-light` / `theme-dark` |
| `given/src/` | Початкові демонстрації з методички (лише для порівняння) |
| `tools/checks.sh` → `tools/checks-output.txt` | Перевірки curl за таблицею завдання |
| `tools/browser_check.mjs` → `results/browser-output.txt`, `results/browser-log.json` | Ті самі сценарії в Microsoft Edge (DevTools Protocol): Set-Cookie/Cookie, сховище cookies, `document.cookie`, вкладки, приватний контекст, перезапуск, блокування cookies |
| `results/w3c-validation.txt` | W3C Nu HTML Checker: 0 повідомлень для 10 станів сторінок |

## Запуск

Каталог `src` має відкриватися за адресою `/lab-07/` (шлях cookies — `/lab-07/`):

- **XAMPP / OSPanel:** скопіюйте вміст `src` у `htdocs/lab-07` і відкрийте `http://localhost/lab-07/`.
- **Вбудований сервер PHP:** створіть каталог, у якому `lab-07` — копія або посилання на `src`, і з нього виконайте:

```bash
php -S 127.0.0.1:8070
```

Відкривайте застосунок завжди через один хост: `localhost` і `127.0.0.1` мають різні cookies.

## Перевірки

```bash
bash tools/checks.sh http://127.0.0.1:8070/lab-07
```

```bash
node tools/browser_check.mjs
```

Ідентифікатори сесій і CSRF-токени у виводі приховано («…»).
