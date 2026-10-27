#!/usr/bin/env bash
# Автоматичні перевірки ЛР 12 (curl). Запуск із каталогу lab-12:
#   php -S 127.0.0.1:8000 -t src                         — основний сервер
#   LAB12_DB_PORT=3999 php -S 127.0.0.1:8001 -t src      — сервер із недоступною БД (перевірка помилок)
#   bash tools/checks.sh
# УВАГА: скрипт перестворює базу lab12_bus (php sql/setup.php).
cd "$(dirname "$0")/.." || exit 1
B=http://127.0.0.1:8000
PHP=${PHP:-php}; command -v "$PHP" >/dev/null || PHP=/c/xampp/php/php.exe
OUT=$(mktemp); H=$(mktemp)
G=$(mktemp); U=$(mktemp)   # cookie-файли гостя та користувача

req()   { local jar=$1; shift; curl -s -b "$jar" -c "$jar" -o "$OUT" -D "$H" -w '%{http_code}' "$@"; }
token() { req "$1" "$B/$2" >/dev/null; grep -o 'name="csrf" value="[a-f0-9]*"' "$OUT" | head -1 | sed 's/.*value="//;s/"//'; }
loc()   { grep -i '^location:' "$H" | tr -d '\r' | sed 's/^[Ll]ocation: //'; }
alert() { tr -d '\n' < "$OUT" | grep -o 'role="alert">[^<]*' | head -1 | sed 's/role="alert">//'; }
ferr()  { tr -d '\n' < "$OUT" | grep -o 'class="error" id="[a-z_]*-error">[^<]*' | sed 's/class="error" id="//;s/-error">/: /' | paste -sd ';'; }
rows()  { grep -c '<td><a href="route.php' "$OUT"; }
warn()  { grep -qiE 'warning|fatal|notice|deprecated|TypeError|SQLSTATE|Uncaught' "$OUT" && echo " [!] службове повідомлення у відповіді"; }
sid()   { grep PHPSESSID "$1" | awk '{print $7}'; }
sql()   { "$PHP" -r '$c = require "src/config.php"; $p = new PDO("mysql:host={$c["host"]};port={$c["port"]};dbname={$c["name"]};charset=utf8mb4", $c["user"], $c["pass"]);
                     foreach ($p->query($argv[1], PDO::FETCH_NUM) as $r) echo implode(" | ", $r), "\n";' "$1"; }
register() { # jar login full_name email password confirm [csrf]
    local t=${7-$(token "$1" register.php)}
    req "$1" "$B/register.php" --data-urlencode "csrf=$t" --data-urlencode "login=$2" --data-urlencode "full_name=$3" \
        --data-urlencode "email=$4" --data-urlencode "password=$5" --data-urlencode "password_confirm=$6"; }
login() { # jar login password [csrf]
    local t=${4-$(token "$1" login.php)}
    req "$1" "$B/login.php" --data-urlencode "csrf=$t" --data-urlencode "login=$2" --data-urlencode "password=$3"; }

echo "== Підготовка БД"; "$PHP" sql/setup.php

echo; echo "== Перегляд рейсів (index.php, route.php)"
echo "index: $(req $G "$B/index.php") / рейсів $(rows)$(warn)"
echo "пошук «Чер»: $(req $G "$B/index.php" -G --data-urlencode "q=Чер") / рейсів $(rows): $(grep -o 'Черкаси' "$OUT" | head -1)"
echo "пошук SQL-ін'єкції «' OR 1=1 -- »: $(req $G "$B/index.php" -G --data-urlencode "q=' OR 1=1 -- ") / рейсів $(rows)$(warn)"
echo "пошук «%» (екранування LIKE): $(req $G "$B/index.php" -G --data-urlencode "q=%") / рейсів $(rows)"
echo "q[]=x: $(req $G "$B/index.php?q[]=x") $(alert)"
echo "route.php?id=1: $(req $G "$B/route.php?id=1") $(tr -d '\n' < "$OUT" | grep -o '<dt>Пункт призначення</dt><dd>[^<]*' | sed 's/.*<dd>//')"
echo "route.php?id=999: $(req $G "$B/route.php?id=999") $(alert)"
echo "route.php?id=abc: $(req $G "$B/route.php?id=abc") $(alert)"
echo "route.php?id=1 OR 1=1: $(req $G "$B/route.php" -G --data-urlencode "id=1 OR 1=1") $(alert)"
echo "route.php?id[]=1: $(req $G "$B/route.php?id[]=1") $(alert)$(warn)"

echo; echo "== Меню гостя і доступ до users.php"
req $G "$B/index.php" >/dev/null; echo "меню: $(grep -o 'Увійти\|Реєстрація\|Вітаємо[^<]*\|>Вийти<' "$OUT" | paste -sd ',')"
echo "users.php гостем: $(req $G "$B/users.php") → $(loc)"

echo; echo "== Реєстрація"
echo "коректна: $(register $G iryna_k 'Коваленко Ірина Петрівна' iryna@example.com 'Kv1tka#2026' 'Kv1tka#2026') → $(loc)"
echo "той самий логін: $(register $G iryna_k 'Інша Особа' other@example.com 'Password123' 'Password123') $(ferr)"
echo "той самий email: $(register $G iryna_new 'Інша Особа' iryna@example.com 'Password123' 'Password123') $(ferr)"
echo "логін із лапкою: $(register $G "a'b" 'Інша Особа' a@example.com 'Password123' 'Password123') $(ferr)"
echo "короткий пароль: $(register $G petro_s 'Сидоренко Петро' p@example.com 'abc' 'abc') $(ferr)"
echo "паролі різні: $(register $G petro_s 'Сидоренко Петро' p@example.com 'Password123' 'Password124') $(ferr)"
echo "email некоректний: $(register $G petro_s 'Сидоренко Петро' 'not-an-email' 'Password123' 'Password123') $(ferr)"
echo "ПІБ із тегом: $(register $G petro_s '<script>alert(1)</script>' p@example.com 'Password123' 'Password123') $(ferr)"
t=$(token $G register.php)
echo "login[] масивом: $(req $G "$B/register.php" --data-urlencode "csrf=$t" --data-urlencode "login[]=x" --data-urlencode "full_name=Сидоренко Петро" --data-urlencode "email=p@example.com" --data-urlencode "password=Password123" --data-urlencode "password_confirm=Password123") $(ferr)$(warn)"
echo "без CSRF: $(register $G petro_s 'Сидоренко Петро' p@example.com 'Password123' 'Password123' '') $(alert)"
echo "ПІБ з апострофом: $(register $G taras_m "Дем'яненко Тарас Олегович" taras@example.com 'Avtobus#47' 'Avtobus#47') → $(loc)"
echo "користувачі в БД:"; sql "SELECT id, login, full_name, email, LEFT(password_hash, 7), LENGTH(password_hash) FROM users" | sed 's/^/   /'
echo "   пароль у відкритому вигляді в БД: $(sql "SELECT COUNT(*) FROM users WHERE password_hash IN ('Kv1tka#2026', 'Avtobus#47')")"

echo; echo "== Вхід"
echo "невірний пароль: $(login $U iryna_k 'wrong-pass') $(alert)"
echo "невідомий логін: $(login $U nobody 'whatever1') $(alert)"
echo "SQL-ін'єкція в логіні: $(login $U "' OR '1'='1' -- " 'x') $(alert)"
echo "без CSRF: $(login $U iryna_k 'Kv1tka#2026' '') $(alert)"
s1=$(sid $U)
echo "коректний: $(login $U iryna_k 'Kv1tka#2026') → $(loc)"
s2=$(sid $U); echo "ідентифікатор сесії змінено: $([ -n "$s1" ] && [ "$s1" != "$s2" ] && echo так || echo НІ)"
req $U "$B/index.php" >/dev/null; echo "меню: $(grep -o 'Вітаємо[^<]*\|>Вийти<\|Користувачі\|Увійти' "$OUT" | paste -sd ',') | $(grep -o 'class="ok" role="status">[^<]*' "$OUT" | sed 's/.*">//')"
echo "users.php: $(req $U "$B/users.php") / рядків $(grep -c '<td class="num">' "$OUT"); хеш на сторінці: $(grep -c '\$2y\$' "$OUT")"

echo; echo "== Вихід"
echo "GET logout.php: $(req $U "$B/logout.php") → $(loc); users.php після цього: $(req $U "$B/users.php")"
echo "POST без CSRF: $(req $U "$B/logout.php" -X POST --data "csrf=bad") $(alert); users.php: $(req $U "$B/users.php")"
t=$(token $U index.php)
echo "POST з CSRF: $(req $U "$B/logout.php" --data-urlencode "csrf=$t") → $(loc)"
req $U "$B/index.php" >/dev/null; echo "меню: $(grep -o 'Вітаємо[^<]*\|>Вийти<\|Увійти' "$OUT" | paste -sd ',') | $(grep -o 'class="ok" role="status">[^<]*' "$OUT" | sed 's/.*">//')"
echo "users.php після виходу: $(req $U "$B/users.php") → $(loc)"

echo; echo "== Недоступна БД (сервер :8001, порт MySQL 3999)"
B=http://127.0.0.1:8001
echo "index: $(req $G "$B/index.php") $(alert)$(warn)"
echo "route.php?id=1: $(req $G "$B/route.php?id=1") $(alert)$(warn)"
echo "db_check.php: $(req $G "$B/db_check.php") $(alert)$(warn)"
echo "вхід: $(login $G iryna_k 'Kv1tka#2026') $(alert)$(warn)"
rm -f "$OUT" "$H" "$G" "$U"
