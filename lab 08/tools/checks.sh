#!/usr/bin/env bash
# Перевірки з таблиці «Перевірка та очікуваний результат» для обох версій каталогу.
# Запуск із каталогу lab-08 при працюючому сервері:  php -S 127.0.0.1:8000 -t .
#   bash tools/checks.sh
# Скрипт тимчасово підміняє файли даних і відновлює початкові з data-initial/.
cd "$(dirname "$0")/.." || exit 1
B=http://127.0.0.1:8000
PHP=${PHP:-php}
command -v "$PHP" >/dev/null || PHP=/c/xampp/php/php.exe
J=$(mktemp); OUT=$(mktemp)
curl -s -c "$J" -b "$J" "$B/xml/add.php" -o "$OUT"
CSRF=$(grep -o 'name="csrf" value="[a-f0-9]*"' "$OUT" | sed 's/.*value="//;s/"//')

get()  { curl -s -b "$J" -c "$J" -o "$OUT" -w '%{http_code}' "$B/$1"; }
post() { local url=$1; shift; curl -s -b "$J" -c "$J" -o "$OUT" -w '%{http_code}' "$@" "$B/$url"; }
alert() { tr -d '\n' < "$OUT" | grep -o 'role="alert">[^<]*' | head -1 | sed 's/role="alert">//'; }
rows() { grep -c 'edit.php?id=' "$OUT"; }
warn() { grep -qiE 'warning|fatal|notice|deprecated|TypeError' "$OUT" && echo " [!] PHP-повідомлення у відповіді"; }
postf() { # url number destination departure platform price [додаткові аргументи curl]
    local url=$1 n=$2 d=$3 t=$4 p=$5 pr=$6; shift 6
    post "$url" "$@" --data-urlencode "csrf=${CSRF_OVERRIDE:-$CSRF}" --data-urlencode "number=$n" \
        --data-urlencode "destination=$d" --data-urlencode "departure=$t" --data-urlencode "platform=$p" --data-urlencode "price=$pr"; }
restore() { cp data-initial/routes.xml xml/routes.xml; cp data-initial/routes.json json/routes.json; }
sum() { md5sum "$1" | cut -c1-8; }

restore
echo "== Готові приклади"
for f in xml json; do get "src/read_persons_$f.php" >/dev/null
    echo "$f: $(tr -d '\n' < "$OUT" | grep -o '<tr><td>[^<]*' | sed 's/<tr><td>//' | paste -sd '|')"; done
"$PHP" -r 'require "src/common.php"; foreach ([-1, 121, 1.5, "20"] as $age) {
    try { validatePeople([["name" => "Тест", "age" => $age]]); echo "  вік ", var_export($age, true), ": прийнято\n"; }
    catch (RuntimeException $e) { echo "  вік ", var_export($age, true), ": відмова\n"; } }'

for V in xml json; do
    F=$V/routes.$V
    echo; echo "################ Версія $V"
    restore
    echo "== Початковий каталог: $(get $V/index.php) / рядків $(rows)"
    echo "== Спецсимволи"
    code=$(postf $V/add.php "7" "Клуб \"Код\" <b>&</b> друзі" "06:05" 1 1 -D /tmp/h)
    echo "POST $code → $(grep -i '^location' /tmp/h | tr -d '\r')"
    get $V/index.php >/dev/null
    echo "у таблиці: $(grep -o 'Клуб[^<]*друзі' "$OUT")"; echo "у файлі:   $(grep -o 'Клуб.*друзі' $F)"
    grep -o 'class="ok"[^<]*<' "$OUT" | head -1
    echo "== Два записи поспіль"
    postf $V/add.php "300" "Вінниця" "18:20" 2 260 >/dev/null
    postf $V/add.php "301" "Черкаси" "19:00" 4 300 >/dev/null
    get $V/index.php >/dev/null; echo "рядків: $(rows) (очікується 6)"
    echo "== Оновлення сторінки після додавання"
    before=$(rows); get $V/index.php >/dev/null; get $V/index.php >/dev/null; echo "рядків до/після двох GET: $before/$(rows)"
    echo "== Порожнє поле, масив замість рядка"
    h=$(sum $F)
    echo "порожній номер: $(postf $V/add.php "" "Львів" "10:00" 1 100) $(grep -o 'Заповніть поле' "$OUT" | head -1)$(warn)"
    echo "масив: $(post $V/add.php --data-urlencode "csrf=$CSRF" --data-urlencode "number[]=1" --data-urlencode "destination=Львів" --data-urlencode "departure=10:00" --data-urlencode "platform=1" --data-urlencode "price=100") $(grep -o 'Поле має бути текстовим значенням' "$OUT" | head -1)$(warn)"
    echo "== Межі значень"
    for c in "platform=0" "platform=21" "platform=1.5" "platform=08" "price=0" "price=5001" "departure=24:00" "number=12 34"; do
        k=${c%%=*}; v=${c#*=}; n=1; d=Львів; t=10:00; p=1; pr=100
        case $k in platform) p=$v;; price) pr=$v;; departure) t=$v;; number) n=$v;; esac
        echo "  $c → $(postf $V/add.php "$n" "$d" "$t" "$p" "$pr") $(tr -d '\n' < "$OUT" | grep -o 'class="error" id="[a-z]*-error">[^<]*' | sed 's/.*">//')"
    done
    echo "  файл не змінився: $([ "$h" = "$(sum $F)" ] && echo так || echo НІ)"
    echo "== CSRF"
    echo "без токена: $(post $V/add.php --data-urlencode number=1 --data-urlencode destination=Львів --data-urlencode departure=10:00 --data-urlencode platform=1 --data-urlencode price=100) $(alert)"
    echo "змінений:   $(CSRF_OVERRIDE="x$CSRF" postf $V/add.php 1 Львів 10:00 1 100) $(alert)"
    echo "файл не змінився: $([ "$h" = "$(sum $F)" ] && echo так || echo НІ)"
    echo "== Редагування"
    ID=$(get $V/index.php >/dev/null; grep -o 'edit.php?id=[a-f0-9]*' "$OUT" | head -1 | cut -d= -f2)
    echo "GET форма: $(get "$V/edit.php?id=$ID")"
    echo "POST зміна: $(postf $V/edit.php "7" "Клуб змінено" "06:10" 2 5 -D /tmp/h --data-urlencode "id=$ID") → $(grep -i '^location' /tmp/h | tr -d '\r')"
    get $V/index.php >/dev/null; echo "рядків: $(rows); $(grep -o 'Клуб змінено' "$OUT")"
    h=$(sum $F)
    echo "GET невідомий id: $(get "$V/edit.php?id=0000000000000000") $(alert)"
    echo "POST невідомий id: $(postf $V/edit.php 9 Нове 11:00 1 10 --data-urlencode "id=0000000000000000") $(alert)"
    echo "GET id=../x: $(get "$V/edit.php?id=../x") $(alert)"
    echo "файл не змінився: $([ "$h" = "$(sum $F)" ] && echo так || echo НІ)"
    echo "== Файл недоступний для запису"
    attrib +R "$(cygpath -w $F)" 2>/dev/null || chmod a-w $F
    echo "POST: $(postf $V/add.php 500 Рівне 12:00 1 100) $(alert)$(warn)"
    attrib -R "$(cygpath -w $F)" 2>/dev/null || chmod u+w $F
    echo "файл не змінився: $([ "$h" = "$(sum $F)" ] && echo так || echo НІ)"
    grep -q 'class="ok"' "$OUT" && echo " [!] показано успіх"
done

echo; echo "################ Пошкоджені та невідповідні файли"
broken() { # версія, опис, вміст ("" — видалити файл)
    local V=$1 F=$1/routes.$1; restore
    if [ "$3" = "__missing__" ]; then rm -f $F; else printf '%s' "$3" > $F; fi
    local h; h=$( [ -f $F ] && sum $F || echo none)
    local c1; c1=$(get $V/index.php); local msg; msg=$(alert); [ -z "$msg" ] && msg=$(grep -o 'Записів немає' "$OUT")
    local c2; c2=$(postf $V/add.php 9 Тест 10:00 1 10)
    local h2; h2=$( [ -f $F ] && sum $F || echo none)
    printf '%-4s %-34s GET %s: %s | POST %s, файл %s\n' "$V" "$2" "$c1" "$msg" "$c2" "$([ "$h" = "$h2" ] && echo 'не змінено' || echo ЗМІНЕНО)"
}
R='<route><id>3f9a1c2b7d4e8a01</id><number>1</number><destination>Київ</destination><departure>07:00</departure><platform>1</platform><price>100</price></route>'
broken xml "відсутній файл" "__missing__"
broken xml "порожній файл" ""
broken xml "порожній список routes" '<?xml version="1.0" encoding="UTF-8"?><routes/>'
broken xml "незакритий тег" '<routes><route><id>1</route></routes>'
broken xml "DTD із зовнішньою сутністю" '<?xml version="1.0"?><!DOCTYPE routes [<!ENTITY x SYSTEM "file:///C:/Windows/win.ini">]><routes>&x;</routes>'
broken xml "інший корінь" "<people>$R</people>"
broken xml "відсутнє поле price" "<routes>${R/<price>100<\/price>/}</routes>"
broken xml "повторне поле number" "<routes>${R/<number>1<\/number>/<number>1</number><number>2</number>}</routes>"
broken xml "platform=abc" "<routes>${R/<platform>1</<platform>abc<}</routes>"
broken xml "price=0100 (початковий нуль)" "<routes>${R/<price>100</<price>0100<}</routes>"
J1='{"id":"3f9a1c2b7d4e8a01","number":"1","destination":"Київ","departure":"07:00","platform":1,"price":100}'
broken json "відсутній файл" "__missing__"
broken json "порожній файл" ""
broken json "порожній масив []" '[]'
broken json "зайва кома" "[$J1,]"
broken json "об’єкт {}" '{}'
broken json "null" 'null'
broken json "відсутнє поле price" "[${J1/,\"price\":100/}]"
broken json "platform рядком \"1\"" "[${J1/\"platform\":1/\"platform\":\"1\"}]"
broken json "price дробове 99.5" "[${J1/\"price\":100/\"price\":99.5}]"
broken json "зайве поле" "[${J1/\}/,\"x\":1\}}]"
restore
echo; echo "(дані відновлено з data-initial/)"
rm -f "$J" "$OUT"
