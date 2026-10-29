# Лабораторна робота №9. Обробка графіки. Бібліотека GD

**Крайняк Михайло Юрійович, 451 група.** Варіант 10 — розклад маршрутів автовокзалу. Звіт: [report.pdf](report.pdf).

## Склад

| Шлях | Що це |
| --- | --- |
| `index.php` | HTML-сторінка: готові PNG і SVG з таблицею даних, власні діаграми з таблицею, водяні знаки, кругова діаграма |
| `src/common.php`, `diagram_png_1.php`, `diagram_png_2.php`, `diagram_svg.php`, `diagram_1.svg`, `chart-data.json`, `prepare_sample.php`, `images/sample.png` | Готові приклади з методички, **без змін** |
| `src/watermark.php` | Готовий приклад із двома власними змінами: ключ `bus` у списку дозволених файлів (`watermark.php?image=bus`) і підпис «ЛР 9 \| 451 група \| Тарас Маршрутенко \| дата» замість «Група DEMO» |
| `src/bus-data.json` | Власні дані: відправлення з автовокзалу за 7 напрямками (рейсів на тиждень) |
| `src/config.php`, `src/bus-common.php` | Шрифт і шляхи; перевірка JSON, спільна розкладка PNG/SVG, підбір кегля й перенесення підписів |
| `src/bus_png.php`, `src/bus_svg.php` | Самостійні завдання 1–2: та сама стовпчаста діаграма у PNG (GD, TrueType) і SVG |
| `src/watermark_bus.php`, `src/make_bus_image.php`, `src/images/bus.png` | Самостійне завдання 3: водяний знак з українським підписом; генератор власного PNG |
| `src/pie_png.php` | Кругова діаграма зі змінними значеннями `?values=50,25,15,10` |
| `src/watermark-output.png`, `src/watermark-bus-output.png` | Результати CLI-збереження |
| `results/` | Збережені PNG/SVG-відповіді, валідація W3C |
| `tools/checks.sh` → `tools/checks-output.txt` | Перевірки за таблицею завдання |
| `tools/make_fixtures.php`, `tests/fixtures/data/` | Тестові дані й зображення для негативних перевірок (зображення генеруються скриптом) |

## Запуск

Потрібне розширення `gd` з PNG і FreeType. У XAMPP розкоментуйте `extension=gd` у `php.ini` або запускайте з `-d extension=gd`. Для українських підписів потрібен TrueType-шрифт із кирилицею. Його шукають у такому порядку: `LAB09_FONT`, `src/fonts/DejaVuSans.ttf`, `C:/Windows/Fonts/DejaVuSans.ttf`, `arial.ttf`. Сам шрифт до репозиторію не включено.

З каталогу, у якому `lab-09` — цей каталог (для XAMPP — `htdocs`):

```bash
php -d extension=gd -S 127.0.0.1:8090
```

Сторінка: `http://127.0.0.1:8090/lab-09/index.php`. Збереження водяного знака на сервері з каталогу `src`:

```bash
php -d extension=gd watermark_bus.php bus
```

## Перевірки

Тестові сервери 8091 і 8092 описано на початку `tools/checks.sh`.

```bash
php -d extension=gd tools/make_fixtures.php
```

```bash
bash tools/checks.sh
```
