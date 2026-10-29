#!/usr/bin/env bash
# Автоматичні перевірки ЛР 5 (curl) за таблицею «Перевірка результату». Запуск із каталогу lab-05:
#   bash tools/checks.sh
# Сервери (каталог src доступний як /lab-05/; сервер запущено з display_errors=1, щоб попередження були видимі):
#   8050 — типові ліміти XAMPP (upload_max_filesize = post_max_size = 40M)
#   8051 — -d post_max_size=2M -d upload_max_filesize=2M   (перевищення post_max_size)
#   8052 — -d upload_max_filesize=512K -d post_max_size=8M  (UPLOAD_ERR_INI_SIZE)
# Паролі — вигадані навчальні значення; у вивід вони не потрапляють, перевіряється лише їх відсутність у відповідях.
B=http://127.0.0.1:8050/lab-05
OUT=$(mktemp); H=$(mktemp); TMP=$(mktemp -d)
PHP=${PHP:-php}; command -v "$PHP" >/dev/null || PHP=/c/xampp/php/php.exe

req()    { curl -s -o "$OUT" -D "$H" -w '%{http_code}' "$@"; }
status() { tr -d '\n' < "$OUT" | grep -o 'role="status">[^<]*' | head -1 | sed 's/role="status">/ /'; }
errs()   { tr -d '\n' < "$OUT" | grep -o '<li>[^<]*' | sed 's/<li>//' | paste -sd '|' | sed 's/^/ /'; }
ferr()   { tr -d '\n' < "$OUT" | grep -o 'class="field-error" id="[a-z]*-error">[^<]*' | sed 's/class="field-error" id="//;s/-error">/: /' | paste -sd '|' | sed 's/^/ /'; }
alert()  { tr -d '\n' < "$OUT" | grep -o 'role="alert">[^<]*' | head -1 | sed 's/role="alert">/ /'; }
warn()   { grep -qiE 'warning|notice|deprecated|fatal|TypeError|Stack trace' "$OUT" && echo " [!] службове повідомлення PHP"; }
val()    { tr -d '\n' < "$OUT" | grep -o "id=\"$1\" name=\"$1\"[^>]*value=\"[^\"]*\"" | sed 's/.*value="//;s/"$//'; }
secret() { local n=0; for s in "$@"; do grep -qF -- "$s" "$OUT" && n=$((n+1)); done; grep -q '\$2y\$' "$OUT" && n=$((n+1)); [ $n -eq 0 ] && echo "пароль/хеш у відповіді: ні" || echo "ПАРОЛЬ/ХЕШ У ВІДПОВІДІ"; }
login()  { req "$B/index.php" --data-urlencode "login=$1" --data-urlencode "password=$2"; }
rep()    { python -I -c "import sys; print(sys.argv[1] * int(sys.argv[2]), end='')" "$1" "$2"; }
fb()     { req "$B/feedback.php" "$@"; }
mkfile() { local f; f=$(cygpath -m "$TMP/$1" 2>/dev/null || echo "$TMP/$1"); python -I -c "import sys; open(sys.argv[1], 'wb').write(b'x' * int(sys.argv[2]))" "$f" "$2"; echo "$f"; }
# Тіло multipart як у браузера, коли файл не вибрано: частина attachment з filename="" і без вмісту.
nofile() { local f; f=$(cygpath -m "$TMP/nofile.bin" 2>/dev/null || echo "$TMP/nofile.bin"); python -I tools/multipart_body.py nofile "$f" "$MSG"; echo "$f"; }

echo "== 1. Середовище"
echo "PHP вебсервера: $(curl -s -D - -o /dev/null "$B/index.php" | grep -i '^x-powered-by' | tr -d '\r' | sed 's/.*: //')"
echo "PHP CLI: $("$PHP" -r 'echo PHP_VERSION, "; mbstring: ", extension_loaded("mbstring") ? "так" : "ні", "; file_uploads=", ini_get("file_uploads"), "; upload_max_filesize=", ini_get("upload_max_filesize"), "; post_max_size=", ini_get("post_max_size"), "; max_file_uploads=", ini_get("max_file_uploads");')"

echo; echo "== 2. Крок 2: форма й окремий обробник login.php"
echo "GET stage2/index.php: $(req "$B/stage2/index.php") / форма action=$(grep -o 'action="[^"]*" method="[^"]*"' "$OUT")"
echo "GET stage2/login.php: $(req "$B/stage2/login.php") / $(tr -d '\n' < "$OUT" | grep -o 'Цю сторінку[^<]*')$(warn)"
echo "POST коректні дані: $(req "$B/stage2/login.php" --data-urlencode 'login= student_1 ' --data-urlencode 'password=Kalyna#2026')$(status)$(tr -d '\n' < "$OUT" | grep -o '<strong>[^<]*' | sed 's/<strong>/ /'); $(secret 'Kalyna#2026')"
echo "POST login[]=x: $(req "$B/stage2/login.php" --data 'login[]=x&password=abc')$(errs)$(warn)"
echo "POST без password: $(req "$B/stage2/login.php" --data 'login=student')$(errs)$(warn)"
echo "POST login з пробілів: $(req "$B/stage2/login.php" --data-urlencode 'login=   ' --data 'password=x')$(errs)"
echo "POST login=<b>x</b>: $(req "$B/stage2/login.php" --data-urlencode 'login=<b>x</b>' --data 'password=x') / у відповіді: $(grep -o '&lt;b&gt;x&lt;/b&gt;' "$OUT" | head -1)"
echo "GET-форма query: адреса запиту $(curl -s -o "$OUT" -w '%{url_effective}' -G "$B/stage2/query.php" --data-urlencode 'query=розклад автобусів')$(status)"

echo; echo "== 3. Вхід (index.php): перше відкриття і три користувачі"
echo "GET index.php: $(req "$B/index.php") / помилок: $(grep -c 'role="alert"' "$OUT"), успіху: $(grep -c 'role="status"' "$OUT"), значення login: «$(val login)»$(warn)"
for c in "olena_k|Kalyna#2026" "taras_m|Kobzar 1814" "iryna_99|Сонях2026"; do
    l=${c%%|*}; p=${c#*|}; echo "$l: $(login "$l" "$p")$(status); $(secret "$p")"; done
echo "olena_k з пробілом наприкінці пароля: $(login olena_k 'Kalyna#2026 ')$(errs) — пароль не обрізається"
echo "\" olena_k \" (логін з пробілами): $(login ' olena_k ' 'Kalyna#2026')$(status) — логін нормалізовано trim()"

echo; echo "== 4. Невдалий вхід: однакове повідомлення"
echo "невідомий логін: $(login nobody_here 'Kalyna#2026')$(errs); login збережено: «$(val login)»; поле password порожнє: $(tr -d '\n' < "$OUT" | grep -o 'id="password"[^>]*' | grep -c value= | sed 's/^0$/так/;s/^[1-9].*/НІ/')"
echo "неправильний пароль: $(login olena_k 'Kalyna#2025')$(errs); $(secret 'Kalyna#2025')"
echo "ім’я в іншому регістрі (OLENA_K): $(login OLENA_K 'Kalyna#2026')$(errs)"

echo; echo "== 5. Відсутнє поле, порожнє значення, масив"
echo "без login: $(req "$B/index.php" --data 'password=Kalyna2026')$(errs)$(warn)"
echo "без password: $(req "$B/index.php" --data 'login=olena_k')$(errs)$(warn)"
echo "без полів (тіло x=1): $(req "$B/index.php" --data 'x=1')$(errs)$(warn)"
echo "порожній login: $(req "$B/index.php" --data 'login=&password=Kalyna2026')$(errs)"
echo "порожній password: $(req "$B/index.php" --data 'login=olena_k&password=')$(errs)"
echo "login з пробілів: $(req "$B/index.php" --data-urlencode 'login=   ' --data 'password=Kalyna2026')$(errs)"
echo "login[]=olena_k: $(req "$B/index.php" --data 'login[]=olena_k&password=Kalyna2026')$(errs)$(warn)"
echo "password[]=x: $(req "$B/index.php" --data 'login=olena_k&password[]=x')$(errs)$(warn)"
echo "login[a][b]=1: $(req "$B/index.php" --data 'login[a][b]=1&password=x')$(errs)$(warn)"
echo "login=0 (рядок «0» не порожній): $(req "$B/index.php" --data 'login=0&password=Kalyna2026')$(errs)"
echo "PUT index.php: $(req -X PUT "$B/index.php") $(cat "$OUT")"

echo; echo "== 6. Межі довжини"
for n in 2 3 30 31; do echo "логін $n симв.: $(login "$(rep a "$n")" 'Kalyna#2026')$(errs)"; done
for n in 7 8 72 73; do echo "пароль $n байт: $(login olena_k "$(rep p "$n")")$(errs)"; done
echo "пароль «Кіт12» (5 симв., 8 байт): $(login olena_k 'Кіт12')$(errs)"
echo "пароль «Кіт1» (4 симв., 7 байт): $(login olena_k 'Кіт1')$(errs)"
echo "пароль 36 укр. літер (72 байти): $(login olena_k "$(rep ж 36)")$(errs)"
echo "пароль 37 укр. літер (74 байти, 37 симв.): $(login olena_k "$(rep ж 37)")$(errs)"
echo "логін кирилицею «олена»: $(login 'олена' 'Kalyna#2026')$(errs)"
echo "логін «olena-k»: $(login 'olena-k' 'Kalyna#2026')$(errs)"
echo "логін з переносом рядка «olena_k\\n»: $(req "$B/index.php" --data 'login=olena_k%0A&password=Kalyna%232026')$(status)$(errs) (trim прибирає \\n)"
echo "логін «ol\\nena»: $(req "$B/index.php" --data 'login=ol%0Aena&password=Kalyna%232026')$(errs)"

echo; echo "== 7. Зворотний зв’язок: GET і коректні дані"
echo "GET feedback.php: $(fb) / помилок: $(grep -c 'role="alert"' "$OUT"), успіху: $(grep -c 'role="status"' "$OUT"), enctype: $(grep -o 'enctype="[^"]*"' "$OUT")$(warn)"
MSG='Добрий день! Чи можна записатися на екскурсію до Канева?'
echo "коректна форма без вкладення (файл не вибрано): $(fb -H 'Content-Type: multipart/form-data; boundary=----lab05boundary' --data-binary "@$(nofile)")$(status) | $(tr -d '\n' < "$OUT" | grep -o 'Вкладення</th><td>[^<]*' | sed 's/.*<td>//;s/  */ /g')"
echo "   ім’я у зведенні: $(tr -d '\n' < "$OUT" | grep -o 'Ім’я</th><td>[^<]*' | sed 's/.*<td>//'); довжина повідомлення: $(tr -d '\n' < "$OUT" | grep -o 'Повідомлення ([0-9]* симв.)') (mb_strlen = $(python -I -c 'import sys; print(len(sys.argv[1]))' "$MSG"), strlen = $(printf '%s' "$MSG" | wc -c) байт)"
echo "коректна форма без поля attachment (urlencoded): $(fb --data-urlencode 'name=Петро' --data-urlencode 'email=p@example.com' --data-urlencode "message=$MSG")$(status)"

echo; echo "== 8. Помилки полів і збереження введення"
echo "неправильна пошта: $(fb --data-urlencode 'name=Ґанна Їжак' --data-urlencode 'email=ganna@@example' --data-urlencode "message=$MSG")$(ferr); ім’я збережено: «$(val name)», пошта збережена: «$(val email)»"
echo "повідомлення з пробілів: $(fb --data-urlencode 'name=Ґанна' --data-urlencode 'email=g@example.com' --data-urlencode 'message=              ')$(ferr)"
echo "порожнє ім’я (пробіли): $(fb --data-urlencode 'name=   ' --data-urlencode 'email=g@example.com' --data-urlencode "message=$MSG")$(ferr)"
echo "ім’я 100 симв.: $(fb --data-urlencode "name=$(rep Я 100)" --data-urlencode 'email=g@example.com' --data-urlencode "message=$MSG")$(status)$(ferr)"
echo "ім’я 101 симв.: $(fb --data-urlencode "name=$(rep Я 101)" --data-urlencode 'email=g@example.com' --data-urlencode "message=$MSG")$(ferr)"
E254="$(rep a 64)@$(rep b 63).$(rep c 63).$(rep d 58).ua"; E255="$(rep a 64)@$(rep b 63).$(rep c 63).$(rep d 59).ua"
echo "пошта ${#E254} байт: $(fb --data-urlencode 'name=Ґанна' --data-urlencode "email=$E254" --data-urlencode "message=$MSG")$(status)$(ferr)"
echo "пошта ${#E255} байт: $(fb --data-urlencode 'name=Ґанна' --data-urlencode "email=$E255" --data-urlencode "message=$MSG")$(ferr)"
echo "усі поля порожні: $(fb --data 'name=&email=&message=')$(ferr)"
echo "без полів (лише x=1): $(fb --data 'x=1')$(ferr)$(warn)"
echo "name[]=x: $(fb --data 'name[]=x&email=g@example.com' --data-urlencode "message=$MSG")$(ferr)$(warn)"
echo "message[a]=x, email[]=y: $(fb --data 'name=Ганна&email[]=y&message[a]=x')$(ferr)$(warn)"
echo "некоректний UTF-8 у name: $(fb --data 'name=%C3%28&email=g@example.com' --data-urlencode "message=$MSG")$(ferr)$(warn)"

echo; echo "== 9. Довжина повідомлення (українські літери «ї»)"
for n in 9 10 1000 1001; do echo "$n симв. ($(( n * 2 )) байт): $(fb --data-urlencode 'name=Ґанна' --data-urlencode 'email=g@example.com' --data-urlencode "message=$(rep ї "$n")")$(status)$(ferr)"; done
echo "10 симв. з пробілами по краях (14 до trim): $(fb --data-urlencode 'name=Ґанна' --data-urlencode 'email=g@example.com' --data-urlencode "message=  $(rep ї 10)  ")$(status)$(ferr)"

echo; echo "== 10. Розмітка в повідомленні"
fb --data-urlencode 'name=<i>Ганна</i>' --data-urlencode 'email=g@example.com' --data-urlencode 'message=<b>Перевірка</b> & <script>alert(1)</script>' >/dev/null
echo "<b>Перевірка</b>: тег <b> у відповіді: $(grep -c '<b>Перевірка' "$OUT"), екрановано: $(tr -d '\n' < "$OUT" | grep -o '&lt;b&gt;Перевірка&lt;/b&gt; &amp; &lt;script&gt;alert(1)&lt;/script&gt;' | head -1); <script> у відповіді: $(grep -c '<script>' "$OUT"); ім’я: $(tr -d '\n' < "$OUT" | grep -o 'Ім’я</th><td>[^<]*' | sed 's/.*<td>//')"

echo; echo "== 11. Вкладення: 1 байт, 1 MiB, 1 MiB + 1"
F=(-F 'name=Ґанна' -F 'email=g@example.com' -F "message=$MSG")
for size in 1 1048576 1048577; do
    f=$(mkfile "file-$size.txt" "$size")
    echo "$size байт: $(fb "${F[@]}" -F "attachment=@$f;type=text/plain")$(status)$(ferr) | $(tr -d '\n' < "$OUT" | grep -o 'Назва:[^<]*<br>[^<]*<br>[^<]*' | sed 's/<br>/; /g;s/  */ /g')"
done
f=$(mkfile photo.jpg 2048)
echo "MIME-тип від клієнта (text .jpg як image/jpeg): $(fb "${F[@]}" -F "attachment=@$f;type=image/jpeg")$(status) | $(tr -d '\n' < "$OUT" | grep -o 'MIME-тип:[^<]*' | sed 's/  */ /g')"
f=$(mkfile 'markup.txt' 5)
echo "назва файла з розміткою: $(fb "${F[@]}" -F "attachment=@$f;filename=<img src=x onerror=alert(1)>.txt")$(status) | $(tr -d '\n' < "$OUT" | grep -o 'Назва: [^<]*' | sed 's/  */ /g'); tmp_name у відповіді: $(grep -c -i 'tmp\\php\|php[A-Za-z0-9]*\.tmp' "$OUT")"

echo; echo "== 12. Порожній файл і коди помилок завантаження"
f=$(mkfile empty.txt 0)
echo "файл 0 байт: $(fb "${F[@]}" -F "attachment=@$f")$(ferr)$(warn); успіху: $(grep -c 'role="status"' "$OUT")"
f1=$(mkfile a1.txt 10); f2=$(mkfile a2.txt 10)
echo "attachment[] (два файли): $(fb "${F[@]}" -F "attachment[]=@$f1" -F "attachment[]=@$f2")$(ferr)$(warn)"
f=$(mkfile big600k.bin 614400)
echo "600 КіБ при upload_max_filesize=512K (8052): $(curl -s -o "$OUT" -w '%{http_code}' "${B/8050/8052}/feedback.php" "${F[@]}" -F "attachment=@$f")$(ferr)$(warn); успіху: $(grep -c 'role="status"' "$OUT")"
# UPLOAD_ERR_PARTIAL: тіло multipart обривається посеред файла (немає завершальної межі).
python -I tools/multipart_body.py partial "$(cygpath -m "$TMP/partial.bin")" "$MSG"
echo "обірване тіло multipart: $(fb -H 'Content-Type: multipart/form-data; boundary=----lab05boundary' --data-binary "@$(cygpath -m "$TMP/partial.bin")")$(ferr)$(warn)"

echo; echo "== 13. POST понад post_max_size"
f=$(mkfile big3m.bin 3145728)
echo "3 МіБ при post_max_size=2M (8051), feedback.php: $(curl -s -o "$OUT" -w '%{http_code}' "${B/8050/8051}/feedback.php" "${F[@]}" -F "attachment=@$f")$(alert)"
echo "   попередження ядра PHP до запуску сценарію (видно лише через display_errors=1): $(tr -d '
' < "$OUT" | grep -o 'POST Content-Length[^<]*')"
' < "$OUT" | grep -o 'Warning</b>:  PHP Request Startup: [^<]*' | sed 's/<\/b>//')"
echo "3 МіБ при post_max_size=2M (8051), index.php: $(curl -s -o "$OUT" -w '%{http_code}' "${B/8050/8051}/index.php" -F 'login=olena_k' -F 'password=x' -F "attachment=@$f")$(errs)"
echo "та сама форма без файла (8051): $(curl -s -o "$OUT" -w '%{http_code}' "${B/8050/8051}/feedback.php" "${F[@]}")$(status)"

rm -rf "$TMP"; rm -f "$OUT" "$H"
