#!/usr/bin/env bash
# Автоматичні перевірки ЛР 9 за таблицею «Перевірка та очікуваний результат». Запуск із каталогу lab-09:
#   bash tools/checks.sh
# Сервери (php -S, GD вмикається параметром -d extension=gd, php.ini не змінюється):
#   8090 — основний: корінь, у якому lab-09 — цей каталог
#   8091 — тестовий: копія tests/work/www/lab-09 (негативні перевірки файлів лише на копії)
#   8092 — та сама копія, але LAB09_FONT вказує на відсутній шрифт
cd "$(dirname "$0")/.." || exit 1
PHP=${PHP:-php}; command -v "$PHP" >/dev/null || PHP=/c/xampp/php/php.exe
B=http://127.0.0.1:8090/lab-09/src
T=http://127.0.0.1:8091/lab-09/src
NF=http://127.0.0.1:8092/lab-09/src
COPY=tests/work/www/lab-09/src
FX=tests/fixtures
OUT=$(mktemp); H=$(mktemp)

get()   { curl -s -o "$OUT" -D "$H" -w '%{http_code}' "$@"; }
ctype() { grep -i '^content-type:' "$H" | tr -d '\r' | sed 's/^[^:]*: //'; }
hdr()   { grep -i "^$1:" "$H" | tr -d '\r' | sed 's/^[^:]*: //'; }
kind()  { file -b "$OUT" | cut -d, -f1-2 | sed 's/ image data//'; }
body()  { tr -d '\r' < "$OUT" | head -c 200; }
warn()  { grep -aqiE 'warning|notice|deprecated|fatal|TypeError|Stack trace' "$OUT" && echo " [!] службове повідомлення PHP"; }
show()  { local code; code=$(get "$1"); case "$(ctype)" in
              image/png*) echo "$code $(ctype), $(kind), $(stat -c%s "$OUT") байт$(warn)";;
              image/svg*) echo "$code $(ctype), SVG $(grep -o '<svg[^>]*' "$OUT" | grep -o 'width="[0-9]*" height="[0-9]*"'), $(stat -c%s "$OUT") байт$(warn)";;
              *)          echo "$code $(ctype): $(body)$(warn)";; esac; }
sha()   { sha256sum "$1" | cut -c1-16; }

echo "== 1. Середовище"
echo "PHP CLI: $("$PHP" -r 'echo PHP_VERSION;'); php.ini: $("$PHP" --ini | grep Loaded | sed 's/.*: *//')"
echo "GD у CLI без параметрів: $("$PHP" -r 'echo extension_loaded("gd") ? "так" : "ні";') (рядок у php.ini: $(grep -n '^;\?extension=gd' "$(dirname "$(command -v "$PHP" 2>/dev/null || echo /c/xampp/php/php.exe)")/php.ini" 2>/dev/null || grep -n '^;\?extension=gd' /c/xampp/php/php.ini))"
echo "GD у CLI з -d extension=gd: $("$PHP" -d extension=gd -r '$i = gd_info(); echo $i["GD Version"], "; PNG ", $i["PNG Support"] ? "так" : "ні", "; FreeType ", $i["FreeType Support"] ? "так" : "ні";')"
# Тимчасова діагностична сторінка phpinfo() — лише на час перевірки, потім видаляється.
printf '%s\n' '<?php phpinfo(INFO_GENERAL | INFO_MODULES);' > src/_phpinfo_tmp.php
get "$B/_phpinfo_tmp.php" >/dev/null
echo "phpinfo() вебсервера: $(tr -d '\n' < "$OUT" | grep -o 'Loaded Configuration File </td><td class="v">[^<]*' | sed 's/.*"v">/Loaded Configuration File: /')"
echo "   $(tr -d '\n' < "$OUT" | grep -o 'GD Support </td><td class="v">[^<]*\|GD Version </td><td class="v">[^<]*\|FreeType Support </td><td class="v">[^<]*\|PNG Support </td><td class="v">[^<]*' | sed 's| </td><td class="v">|: |' | paste -sd ';')"
rm -f src/_phpinfo_tmp.php
echo "   діагностичну сторінку видалено: $([ -e src/_phpinfo_tmp.php ] && echo НІ || echo так)"
echo "шрифт: $("$PHP" -r 'require "src/config.php"; echo ttfFont() ?? "немає";')"

echo; echo "== 2. Кругова діаграма"
echo "diagram_png_1.php: $(show "$B/diagram_png_1.php")"
python -I - "$OUT" <<'EOF'
import sys
from PIL import Image
im = Image.open(sys.argv[1]).convert('RGB'); w, h = im.size; cx, cy, r = w // 2, h // 2, (min(w, h) - 100) // 2
import math
cols = {(37, 99, 235): 'синій', (22, 163, 74): 'зелений', (234, 88, 12): 'помаранчевий'}
count = {}
for deg in range(360):
    a = math.radians(deg + 0.5); p = im.getpixel((int(cx + r * 0.6 * math.cos(a)), int(cy + r * 0.6 * math.sin(a))))
    count[cols.get(p, 'інше')] = count.get(cols.get(p, 'інше'), 0) + 1
print('   частки за кольором пікселів на колі:', ', '.join(f'{k} {v / 3.6:.0f}%' for k, v in count.items()))
EOF
for v in "50,25,15,10" "1,1" "0,0,5"; do echo "pie_png.php?values=$v: $(show "$B/pie_png.php?values=$v")"; done
for v in "0,0" "5" "1,2,3,4,5,6,7" "1001,1" "a,b" "-1,2" "1.5,2"; do echo "pie_png.php?values=$v: $(show "$B/pie_png.php?values=$v")"; done
echo "pie_png.php?values[]=1: $(show "$B/pie_png.php?values[]=1")"

echo; echo "== 3. Розміри полотна (готові й власні діаграми)"
for p in diagram_png_2 diagram_svg bus_png bus_svg; do
    for s in "" "?width=400&height=300" "?width=640&height=400" "?width=800&height=500" "?width=1200&height=800"; do
        echo "$p.php$s: $(show "$B/$p.php$s")"; done; done

echo; echo "== 4. Неприпустимі параметри розміру (HTTP 400)"
for q in width=399 width=1201 height=299 height=801 'width[]=640' width=400.5 width=abc width= height=-300 'width=0640' 'width=640%20'; do
    echo "bus_png.php?$q: $(show "$B/bus_png.php?$q") | diagram_svg.php: $(get "$B/diagram_svg.php?$q")"; done

echo; echo "== 5. Масштаб: висоти стовпців у SVG (D = 80 найвищий, C = 30 → 3/8 висоти D)"
for p in diagram_svg bus_svg; do
    get "$B/$p.php" >/dev/null
    python -I - "$OUT" "$p" <<'EOF'
import re, sys
svg = open(sys.argv[1], encoding='utf-8').read()
rects = [(int(h)) for h in re.findall(r'<rect x="\d+" y="\d+" width="\d+" height="(\d+)" fill="#2563eb"', svg)]
if sys.argv[2] == 'diagram_svg':
    print(f'   {sys.argv[2]}: висоти {rects}; C/D = {rects[2]}/{rects[3]} = {rects[2] / rects[3]:.4f} (3/8 = 0.375)')
else:
    vals = [42, 35, 28, 21, 14, 4, 9]
    print(f'   {sys.argv[2]}: висоти {rects}; відношення до найвищого: ' + ', '.join(f'{h / rects[0]:.3f}≈{v / 42:.3f}' for h, v in zip(rects, vals)))
EOF
done
get "$B/bus_png.php" >/dev/null; cp "$OUT" tests/work/bus-640.png
python -I - tests/work/bus-640.png <<'EOF'
import sys
from PIL import Image
im = Image.open(sys.argv[1]).convert('RGB')
heights = []
for x in range(64, 620):
    col = [im.getpixel((x, y)) == (37, 99, 235) for y in range(im.height)]
    heights.append(sum(col))
bars, cur = [], 0
for h in heights + [0]:
    if h: cur = max(cur, h)
    elif cur: bars.append(cur); cur = 0
print(f'   bus_png (PNG, пікселі кольору стовпця): висоти {bars} — збігаються з SVG')
EOF

echo; echo "== 6. Дані з файла: копія src на тестовому сервері"
mkdir -p tests/work
rm -rf tests/work/www && mkdir -p tests/work/www/lab-09 && cp -r src index.php tests/work/www/lab-09/ && rm -f $COPY/watermark-output.png $COPY/watermark-bus-output.png
orig_bus=$(sha src/bus-data.json); orig_chart=$(sha src/chart-data.json)
for f in one zeros empty broken negative string float eleven longlabel list markup; do
    cp "$FX/data/bus-$f.json" "$COPY/bus-data.json"
    echo "bus-$f.json → bus_png: $(show "$T/bus_png.php") | bus_svg: $(get "$T/bus_svg.php") $( [ "$(ctype | cut -c1-5)" = image ] && echo "стовпців $(grep -o 'fill="#2563eb"' "$OUT" | wc -l)") | index.php: $(get "$T/../index.php") $(grep -o 'class="error">[^<]*' "$OUT" | sed 's/class="error">//')"
done
cp "$FX/data/bus-markup.json" "$COPY/bus-data.json"; get "$T/bus_svg.php" >/dev/null; cp "$OUT" tests/work/markup.svg
python -I - tests/work/markup.svg <<'EOF'
import sys, xml.etree.ElementTree as ET
ns = '{http://www.w3.org/2000/svg}'
root = ET.parse(sys.argv[1]).getroot()
texts = [t.text for t in root.iter(ns + 'text')]
titles = [t.text for t in root.iter(ns + 'title')]
print('   XML-розбір SVG із мітками &, <, лапками й <script>: успішно; корінь', root.tag.replace(ns, 'svg:'))
print('   елементів script:', len(list(root.iter(ns + 'script'))), '| атрибутів on*:', sum(1 for e in root.iter() for a in e.attrib if a.startswith('on')))
print('   підписи стовпців (title):', '; '.join(titles[1:]))
EOF
echo "   сирий фрагмент: $(grep -o '<title>&lt;script[^<]*</title>' tests/work/markup.svg | head -1)"
for f in one zeros empty broken negative string; do
    cp "$FX/data/chart-$f.json" "$COPY/chart-data.json"
    echo "chart-$f.json → diagram_png_2: $(show "$T/diagram_png_2.php") | diagram_svg: $(get "$T/diagram_svg.php")"
done
cp src/chart-data.json src/bus-data.json "$COPY/"
echo "справжні дані не змінено: bus-data.json $([ "$(sha src/bus-data.json)" = "$orig_bus" ] && echo так || echo НІ), chart-data.json $([ "$(sha src/chart-data.json)" = "$orig_chart" ] && echo так || echo НІ)"
echo "журнал PHP тестового сервера (причини 500):"
grep -a -o 'PHP Message: .*\|Пошкоджений JSON[^"]*\|items має бути[^"]*\|Категорія №[^"]*\|Очікується[^"]*' tests/work/server-8091.log 2>/dev/null | sort -u | head -12 | sed 's/^/   /'

echo; echo "== 7. Водяний знак: вибір файла"
for q in image=sample image=bus 'image=../../README.md' 'image=..%2F..%2FREADME.md' 'image[]=sample' image=BUS 'image=http://example.com/a.png' 'image=C:/Windows/win.ini' 'image=bus&download=2' 'image=bus&download[]=1'; do
    echo "watermark_bus.php?$q: $(show "$B/watermark_bus.php?$q")"; done
for q in 'image=../../README.md' 'image[]=sample' 'image=unknown' 'image=sample' 'image=bus'; do echo "watermark.php?$q (готовий + ключ bus): $(show "$B/watermark.php?$q")"; done

echo; echo "== 8. Водяний знак: непридатні файли (підставляються замість bus.png у копії)"
for f in jpeg-renamed text-renamed truncated corrupted small-300x150 wide-2001x300 pixels-1500x1500 heavy-noise full; do
    cp "$FX/images/$f.png" "$COPY/images/bus.png"
    echo "$f.png ($(stat -c%s "$FX/images/$f.png") байт): $(show "$T/watermark_bus.php?image=bus")"
done
cp src/images/bus.png "$COPY/images/bus.png"
echo "журнал PHP тестового сервера (причини):"
grep -a -o 'Файл не є PNG[^"]*\|Неприпустимі габарити[^"]*\|Файл зображення більший[^"]*\|imagecreatefrompng[^"]*\|Не вдалося декодувати[^"]*' tests/work/server-8091.log 2>/dev/null | sort -u | sed 's/^/   /'

echo; echo "== 9. Шрифт відсутній (сервер 8092, LAB09_FONT=відсутній файл)"
echo "bus_png.php: $(show "$NF/bus_png.php")"
echo "watermark_bus.php: $(show "$NF/watermark_bus.php?image=bus")"
echo "bus_svg.php (GD і шрифт не потрібні): $(show "$NF/bus_svg.php")"
echo "watermark.php (готовий): $(show "$NF/watermark.php?image=sample") — у Windows підставляє arial.ttf"
echo "журнал: $(grep -a -o 'Не знайдено TrueType-шрифт[^"]*' tests/work/server-8092.log | head -1)"

echo; echo "== 10. Завантаження (download=1)"
for p in "watermark.php?image=sample&download=1" "watermark.php?image=bus&download=1" "watermark_bus.php?image=bus&download=1"; do
    get "$B/$p" >/dev/null; echo "$p: Content-Type $(ctype); Content-Disposition: $(hdr content-disposition); $(kind)"; done
get "$B/watermark_bus.php?image=bus" >/dev/null; echo "без download: Content-Disposition: $(hdr content-disposition || true)— (показ у браузері)"

echo; echo "== 11. Збереження на сервері з термінала (CLI)"
before_sample=$(sha src/images/sample.png); before_bus=$(sha src/images/bus.png)
echo "php watermark.php без GD: $(cd src && "$PHP" watermark.php 2>&1 | paste -sd ' '; echo " → код ${PIPESTATUS[0]}")"
echo "php -d extension=gd watermark.php: $(cd src && "$PHP" -d extension=gd watermark.php)"
echo "php -d extension=gd watermark_bus.php bus: $(cd src && "$PHP" -d extension=gd watermark_bus.php bus)"
echo "php -d extension=gd watermark_bus.php ../secret: $(cd src && "$PHP" -d extension=gd watermark_bus.php ../secret; echo " → код $?")"
for f in src/watermark-output.png src/watermark-bus-output.png; do echo "$f: $(file -b "$f" | cut -d, -f1-2), декодується GD: $("$PHP" -d extension=gd -r 'echo imagecreatefrompng($argv[1]) instanceof GdImage ? "так" : "ні";' "$f")"; done
echo "sample.png не змінено: $([ "$(sha src/images/sample.png)" = "$before_sample" ] && echo так || echo НІ); bus.png не змінено: $([ "$(sha src/images/bus.png)" = "$before_bus" ] && echo так || echo НІ)"
echo "GET не записує файлів: $(n1=$(stat -c%Y src/watermark-bus-output.png); get "$B/watermark_bus.php?image=bus" >/dev/null; [ "$(stat -c%Y src/watermark-bus-output.png)" = "$n1" ] && echo так || echo НІ)"
echo "prepare_sample.php через HTTP: $(show "$B/prepare_sample.php"); make_bus_image.php через HTTP: $(get "$B/make_bus_image.php")"

echo; echo "== 12. HTML-сторінка index.php"
echo "index.php: $(get "http://127.0.0.1:8090/lab-09/index.php") $(ctype); зображень $(tr -d '
' < "$OUT" | grep -o '<img ' | wc -l), з непорожнім alt: $(tr -d '
' < "$OUT" | grep -o '<img [^>]*alt="[^"][^"]*"' | wc -l)$(warn)"
rm -f "$OUT" "$H"
