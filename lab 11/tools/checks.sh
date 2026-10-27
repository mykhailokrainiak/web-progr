#!/usr/bin/env bash
# Автоматичні перевірки ЛР 11 (curl + PHP CLI). Потрібні змінні DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASSWORD
# (користувач застосунку) і два сервери, запущені з каталогу lab-11:
#   php -S 127.0.0.1:8000 -t .                              — з правильними реквізитами
#   DB_PASSWORD=wrong php -S 127.0.0.1:8001 -t .            — з неправильним паролем
# Скрипт створює тестові записи й видаляє їх через вебформи; початкові дані не змінюються.
cd "$(dirname "$0")/.." || exit 1
B=http://127.0.0.1:8000/src
PHP=${PHP:-php}; command -v "$PHP" >/dev/null || PHP=/c/xampp/php/php.exe
OUT=$(mktemp); H=$(mktemp); J=$(mktemp)

req()   { curl -s -b "$J" -c "$J" -o "$OUT" -D "$H" -w '%{http_code}' "$@"; }
token() { curl -s -b "$J" -c "$J" "$B/product-form.php" | grep -o 'name="csrf" value="[a-f0-9]*"' | head -1 | sed 's/.*value="//;s/"//'; }
loc()   { grep -i '^location:' "$H" | tr -d '\r' | sed 's/^[Ll]ocation: //'; }
alert() { tr -d '\n' < "$OUT" | grep -o 'role="alert">[^<]*' | head -1 | sed 's/role="alert">//'; }
okmsg() { tr -d '\n' < "$OUT" | grep -o 'role="status">[^<]*' | head -1 | sed 's/role="status">//'; }
ferr()  { tr -d '\n' < "$OUT" | grep -o 'class="error" id="[a-z_]*-error">[^<]*' | sed 's/class="error" id="//;s/-error">/: /' | paste -sd ';'; }
warn()  { grep -qiE 'warning|fatal|notice|deprecated|TypeError|Uncaught|mysqli_sql|Access denied|lab11_app' "$OUT" && echo " [!] службовий текст у відповіді"; }
sql()   { "$PHP" -r 'mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $db = mysqli_connect(getenv("DB_HOST"), getenv("DB_USER"), getenv("DB_PASSWORD"), getenv("DB_NAME"), (int) getenv("DB_PORT"));
    mysqli_set_charset($db, "utf8mb4"); $r = mysqli_query($db, $argv[1]);
    while ($row = mysqli_fetch_row($r)) echo implode(" | ", $row), "\n";' "$1"; }
sum()   { sql "CHECKSUM TABLE $1" | awk -F' [|] ' '{print $2}'; }
product() { # code name desc price [додаткові аргументи curl]
    local c=$1 n=$2 d=$3 p=$4; shift 4
    req "$B/product-form.php" --data-urlencode "csrf=${CSRF_OVERRIDE-$CSRF}" --data-urlencode "product_code=$c" \
        --data-urlencode "product_name=$n" --data-urlencode "product_desc=$d" --data-urlencode "price=$p" "$@"; }
route() { # number destination time platform price [додаткові аргументи curl]
    local n=$1 d=$2 t=$3 pl=$4 p=$5; shift 5
    req "$B/route-form.php" --data-urlencode "csrf=${CSRF_OVERRIDE-$CSRF}" --data-urlencode "route_number=$n" \
        --data-urlencode "destination=$d" --data-urlencode "departure_time=$t" --data-urlencode "platform=$pl" \
        --data-urlencode "price=$p" "$@"; }
pid() { sql "SELECT id FROM products WHERE product_code = '$1'"; }
rid() { sql "SELECT id FROM bus_routes WHERE route_number = '$1' ORDER BY id DESC LIMIT 1"; }
same() { [ "$1" = "$2" ] && echo "не змінено" || echo "ЗМІНЕНО"; }

CSRF=$(token)
echo "== Початковий стан"
echo "товарів: $(sql 'SELECT COUNT(*) FROM products'), рейсів: $(sql 'SELECT COUNT(*) FROM bus_routes')"
echo "COUNT(*) і WHERE price >= 40.00: $(sql 'SELECT COUNT(*) FROM products') / $(sql 'SELECT COUNT(*) FROM products WHERE price >= 40.00')"

echo; echo "== Готова сторінка index.php"
echo "список: $(req "$B/index.php") / рядків $(grep -c '<tr><td>' "$OUT")"
echo "code=PD1002: $(req "$B/index.php?code=PD1002") / $(tr -d '\n' < "$OUT" | grep -o '<td>PD1002</td>[^/]*</td>' | sed 's/<[^>]*>/ /g' | tr -s ' ')"
echo "code=NO-SUCH-CODE: $(req "$B/index.php?code=NO-SUCH-CODE") $(grep -o 'Товарів не знайдено.' "$OUT")"
echo "code[]=PD1002: $(req "$B/index.php?code[]=PD1002") $(alert)$(warn)"
echo "code=pd1002 (малі літери): $(req "$B/index.php?code=pd1002") $(alert)"
h=$(sum products); echo "POST до index.php: $(req "$B/index.php" -X POST --data 'code=PD1002') $(alert); таблиця $(same "$h" "$(sum products)")"

echo; echo "== Товари: додавання й спецсимволи"
echo "апостроф, & і <b>, ціна 0: $(product TEST-APOS "Набір 'Код' & <b>друзі</b>" "Опис <i>курсивом</i>" 0) → $(loc)"
echo "   у БД: $(sql "SELECT product_name, product_desc, price FROM products WHERE product_code = 'TEST-APOS'")"
req "$B/products.php" >/dev/null; echo "   у HTML: $(grep -o "Набір &#039;Код&#039; &amp; &lt;b&gt;друзі&lt;/b&gt;" "$OUT" | head -1)"
for p in 25 25.5 25.50; do echo "ціна $p: $(product "TEST-P${p//./-}" "Тест ціни" "Перевірка нормалізації" "$p") → БД: $(sql "SELECT price FROM products WHERE product_code = 'TEST-P${p//./-}'")"; done
h=$(sum products)
for p in -1 25,50 1e3 25.555 100000000; do echo "ціна $p: $(product TEST-BAD "Тест" "Опис" "$p") $(alert)"; done
echo "   таблиця $(same "$h" "$(sum products)")"
echo "без product_desc: $(req "$B/product-form.php" --data-urlencode "csrf=$CSRF" --data-urlencode "product_code=TEST-X" --data-urlencode "product_name=Тест" --data-urlencode "price=1") $(alert)$(warn)"
echo "назва з пробілів: $(product TEST-X "   " "Опис" 1) $(alert)"
echo "product_name[] масивом: $(req "$B/product-form.php" --data-urlencode "csrf=$CSRF" --data-urlencode "product_code=TEST-X" --data-urlencode "product_name[]=Тест" --data-urlencode "product_desc=Опис" --data-urlencode "price=1") $(alert)$(warn)"
echo "   таблиця $(same "$h" "$(sum products)")"
echo "повторний код PD1001: $(product PD1001 "Дублікат" "Опис" 10) $(alert); значення у формі збережено: $(grep -c 'value="PD1001"' "$OUT")"
echo "   товарів із кодом PD1001: $(sql "SELECT COUNT(*) FROM products WHERE product_code = 'PD1001'"); таблиця $(same "$h" "$(sum products)")"

echo; echo "== Товари: редагування"
ID=$(pid TEST-APOS)
echo "GET форма id=$ID: $(req "$B/product-form.php?id=$ID")"
echo "UPDATE тими самими даними: $(product TEST-APOS "Набір 'Код' & <b>друзі</b>" "Опис <i>курсивом</i>" 0.00 --data-urlencode "id=$ID") → $(loc)"; req "$B/products.php" >/dev/null; echo "   $(okmsg)"
echo "UPDATE ціни на 45: $(product TEST-APOS "Набір 'Код' & <b>друзі</b>" "Опис <i>курсивом</i>" 45 --data-urlencode "id=$ID") → $(loc)"; req "$B/products.php" >/dev/null; echo "   $(okmsg); БД: $(sql "SELECT price FROM products WHERE id = $ID")"
echo "UPDATE на чужий код PD1002: $(product PD1002 "Назва" "Опис" 1 --data-urlencode "id=$ID") $(alert)"
h=$(sum products); n=$(sql 'SELECT COUNT(*) FROM products')
echo "POST невідомий id=999999: $(product TEST-NEW "Новий" "Опис" 1 --data-urlencode "id=999999") $(alert)"
echo "GET невідомий id: $(req "$B/product-form.php?id=999999") $(alert); id=abc: $(req "$B/product-form.php?id=abc") $(alert); id[]=1: $(req "$B/product-form.php?id[]=1") $(alert)$(warn)"
echo "   товарів $n → $(sql 'SELECT COUNT(*) FROM products'); таблиця $(same "$h" "$(sum products)")"

echo; echo "== CSRF (товари)"
echo "INSERT без токена: $(CSRF_OVERRIDE='' product TEST-CSRF "Тест" "Опис" 1) $(alert)"
echo "INSERT із чужим токеном: $(CSRF_OVERRIDE=deadbeef product TEST-CSRF "Тест" "Опис" 1) $(alert)"
echo "UPDATE без токена: $(CSRF_OVERRIDE='' product TEST-APOS "Змінено" "Опис" 99 --data-urlencode "id=$ID") $(alert)"
echo "DELETE без токена: $(req "$B/product-delete.php" --data-urlencode "id=$ID") $(alert)"
echo "   таблиця $(same "$h" "$(sum products)")"

echo; echo "== Оновлення сторінки після збереження"
echo "POST: $(product TEST-PRG "Тест PRG" "Опис" 5) → $(loc)"; n=$(sql 'SELECT COUNT(*) FROM products')
req "$B/products.php" >/dev/null; req "$B/products.php" >/dev/null; echo "товарів до/після двох GET: $n/$(sql 'SELECT COUNT(*) FROM products')"

echo; echo "== Видалення з підтвердженням"
for code in TEST-APOS TEST-P25 TEST-P25-5 TEST-P25-50 TEST-PRG; do
    id=$(pid $code); g=$(req "$B/product-delete.php?id=$id"); c=$(grep -c 'Так, видалити' "$OUT")
    echo "$code (id $id): GET $g, кнопка підтвердження: $c, після GET запис є: $(sql "SELECT COUNT(*) FROM products WHERE id = $id"); POST $(req "$B/product-delete.php" --data-urlencode "csrf=$CSRF" --data-urlencode "id=$id") → $(loc)"
done
echo "повторне видалення id $ID: $(req "$B/product-delete.php" --data-urlencode "csrf=$CSRF" --data-urlencode "id=$ID") $(alert)"
echo "товарів після очищення: $(sql 'SELECT COUNT(*) FROM products') (очікується 3)"

echo; echo "== Варіант 10: розклад (bus_routes)"
echo "список: $(req "$B/routes.php") / рядків $(grep -c 'route-form.php?id=' "$OUT")"
echo "точний пошук 212-А: $(req "$B/routes.php" -G --data-urlencode "number=212-А") / рядків $(grep -c 'route-form.php?id=' "$OUT"): $(grep -o 'Біла Церква' "$OUT" | head -1)"
echo "пошук 212 (не точний збіг): $(req "$B/routes.php?number=212") $(grep -o 'Рейс «212» не знайдено.' "$OUT")"
echo "number[]=x: $(req "$B/routes.php?number[]=x") $(alert)$(warn); number=a%20b: $(req "$B/routes.php?number=a%20b") $(alert)"
echo "додавання (спецсимволи): $(route T-1 "Кам'янець & <Поділля>" 05:00 1 1) → $(loc)"
RID=$(rid T-1); echo "   у БД: $(sql "SELECT route_number, destination, departure_time, platform, price FROM bus_routes WHERE id = $RID")"
req "$B/routes.php" >/dev/null; echo "   у HTML: $(grep -o "Кам&#039;янець &amp; &lt;Поділля&gt;" "$OUT" | head -1)"
echo "Межі (UPDATE того самого рейсу id $RID):"
ok() { echo "  ✓ $1: $(route "$2" "$3" "$4" "$5" "$6" --data-urlencode "id=$RID") $(ferr)"; }
bad() { echo "  ✗ $1: $(route "$2" "$3" "$4" "$5" "$6" --data-urlencode "id=$RID") $(ferr)"; }
ok  "платформа 20, ціна 5000, 23:59" T-1 "Ду" 23:59 20 5000
ok  "ціна 1.00, час 00:00" T-1 "Ду" 00:00 1 1.00
ok  "номер 10 символів, назва 100 символів" ABCDEFGH-1 "$(printf 'Д%.0s' $(seq 100))" 12:00 5 100.5
bad "платформа 0" T-1 "Ду" 12:00 0 100
bad "платформа 21" T-1 "Ду" 12:00 21 100
bad "ціна 0.99" T-1 "Ду" 12:00 5 0.99
bad "ціна 5000.01" T-1 "Ду" 12:00 5 5000.01
bad "ціна 25,50" T-1 "Ду" 12:00 5 25,50
bad "час 24:00" T-1 "Ду" 24:00 5 100
bad "назва 1 символ" T-1 "Д" 12:00 5 100
bad "назва 101 символ" T-1 "$(printf 'Д%.0s' $(seq 101))" 12:00 5 100
bad "номер 11 символів" ABCDEFGHI-1 "Ду" 12:00 5 100
bad "порожні поля" "" "" "" "" ""
echo "дублікат рейсу 105 о 07:45: $(route 105 "Житомир" 07:45 3 180) $(alert) | $(ferr)"
echo "CSRF (рейс): $(CSRF_OVERRIDE='' route T-2 "Тест" 10:00 1 10) $(alert)"
echo "редагування невідомого id: $(route T-2 "Тест" 10:00 1 10 --data-urlencode "id=999999") $(alert)"
echo "видалення тестового рейсу: GET $(req "$B/route-delete.php?id=$RID") / POST $(req "$B/route-delete.php" --data-urlencode "csrf=$CSRF" --data-urlencode "id=$RID") → $(loc)"
echo "рейсів після очищення: $(sql 'SELECT COUNT(*) FROM bus_routes') (очікується 8)"

echo; echo "== Неправильні реквізити БД (сервер :8001)"
B=http://127.0.0.1:8001/src
for p in index.php products.php routes.php; do echo "$p: $(req "$B/$p") $(alert)$(warn)"; done
echo "POST додавання: $(CSRF_OVERRIDE=$(curl -s -b "$J" -c "$J" "$B/product-form.php" | grep -o 'name="csrf" value="[a-f0-9]*"' | head -1 | sed 's/.*value="//;s/"//') product TEST-DB "Тест" "Опис" 1) $(alert)$(warn)"
rm -f "$OUT" "$H" "$J"
