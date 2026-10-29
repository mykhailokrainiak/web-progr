#!/usr/bin/env bash
# Автоматичні перевірки ЛР 7 (curl) за таблицею «Перевірка та очікуваний результат».
# Сервер: каталог src доступний як /lab-07/ (XAMPP: htdocs/lab-07; вбудований сервер — корінь, у якому є lab-07):
#   php -d display_errors=1 -S 127.0.0.1:8070
#   bash tools/checks.sh  [базова адреса, типово http://127.0.0.1:8070/lab-07]
# Ідентифікатори сесій і CSRF-токени у виводі приховано («…»); показано лише, чи змінилися вони.
B=${1:-http://127.0.0.1:8070/lab-07}
OUT=$(mktemp); H=$(mktemp)
A=$(mktemp); P=$(mktemp)   # cookie-файли: основний браузер і приватний контекст

req()    { local jar=$1; shift; curl -s -b "$jar" -c "$jar" -o "$OUT" -D "$H" -w '%{http_code}' "$@"; }
raw()    { curl -s -o "$OUT" -D "$H" -w '%{http_code}' "$@"; }        # без cookie-файла: лише задані заголовки
token()  { req "$1" "$B/${2:-index.php}" >/dev/null; grep -o 'name="csrf" value="[a-f0-9]*"' "$OUT" | head -1 | sed 's/.*value="//;s/"//'; }
loc()    { grep -i '^location:' "$H" | tr -d '\r' | sed 's/^[Ll]ocation: //'; }
setck()  { grep -i '^set-cookie:' "$H" | tr -d '\r' | sed 's/^[Ss]et-[Cc]ookie: /      Set-Cookie: /;s/LAB07SESSID=[a-z0-9]\{8,\}/LAB07SESSID=…/'; }
theme()  { grep -o 'class="theme-[a-z]*"' "$OUT" | sed 's/class="//;s/"//'; }
alert()  { tr -d '\n' < "$OUT" | grep -o 'role="alert">[^<]*' | head -1 | sed 's/role="alert">/ /'; }
flash()  { tr -d '\n' < "$OUT" | grep -o 'role="status">[^<]*' | head -1 | sed 's/role="status">/ /'; }
favs()   { grep -o 'id="favorites-count">[0-9]*' "$OUT" | sed 's/.*>//'; }
favlist(){ tr -d '\n' < "$OUT" | grep -o '<ol id="favorites-list">.*</ol>' | grep -o '<li>[^<]*' | sed 's/<li>//' | paste -sd ';'; }
visit()  { echo "№$(grep -o 'visit-number">[0-9]*' "$OUT" | sed 's/.*>//'), попередній: $(grep -o 'previous-time">[^<]*' "$OUT" | sed 's/.*>//')$(grep -q 'Ласкаво просимо' "$OUT" && echo ', «Ласкаво просимо!»')"; }
warn()   { grep -qiE 'warning|fatal|notice|deprecated|TypeError|Uncaught' "$OUT" && echo " [!] службове повідомлення PHP у відповіді"; }
sid()    { grep LAB07SESSID "$1" | awk '{print $7}'; }
jarval() { grep -P "\t$2\t" "$1" | awk '{print $7}'; }
same()   { [ -n "$1" ] && [ "$1" = "$2" ] && echo "не змінився" || echo "змінився"; }
post()   { local jar=$1 page=$2; shift 2; req "$jar" "$B/$page" "$@"; }   # POST-поля передаються як --data-urlencode

echo "Адреса: $B"
echo "PHP вебсервера: $(curl -s -D - -o /dev/null "$B/index.php" | grep -i '^x-powered-by' | tr -d '\r' | sed 's/.*: //')"

echo; echo "== 1. Перший GET без cookies"
echo "index.php: $(req $A "$B/index.php") / тема $(theme), обраних $(favs)$(warn)"; setck
echo "visits.php: $(req $A "$B/visits.php") / $(visit)$(warn)"; setck
echo "favorites.php: $(req $A "$B/favorites.php") / обраних $(favs): $(grep -o 'Список обраного порожній' "$OUT")"

echo; echo "== 2. Збереження темної теми (POST → 303 → GET)"
t=$(token $A preferences.php)
echo "POST save dark: $(post $A preferences.php --data-urlencode "csrf=$t" --data-urlencode action=save --data-urlencode theme=dark) → $(loc)"; setck
echo "   GET $(loc): $(req $A "$B/index.php") / тема $(theme) |$(flash)"
for p in preferences visits favorites; do echo "   $p.php: тема $(req $A "$B/$p.php" >/dev/null; theme)"; done
echo "   оновлення index.php: тема $(req $A "$B/index.php" >/dev/null; theme), повідомлення:$(flash || true) (одноразове — вже немає)"
echo "   в cookie-сховищі: club_theme=$(jarval $A club_theme), прапорець HttpOnly: $(grep -c '^#HttpOnly_.*club_theme' $A)"

echo; echo "== 3. Інший хост: localhost замість 127.0.0.1"
echo "http://localhost:8070/lab-07/index.php: $(req $A "${B/127.0.0.1/localhost}/index.php") / тема $(theme) — cookies 127.0.0.1 не надіслано"

echo; echo "== 4. Повторне відкриття браузера (curl -j відкидає сесійні cookies, як закритий браузер)"
s_before=$(sid $A)
echo "visits.php до закриття: $(req $A "$B/visits.php" >/dev/null; visit)"
echo "favorites додано quiz перед закриттям: $(t=$(token $A favorites.php); post $A favorites.php --data-urlencode "csrf=$t" --data-urlencode action=add --data-urlencode id=quiz >/dev/null; req $A "$B/favorites.php" >/dev/null; favs) обраних"
s_before=$(sid $A)
echo "після «перезапуску»: index $(curl -s -j -b $A -c $A -o "$OUT" -D "$H" -w '%{http_code}' "$B/index.php") / тема $(theme), обраних $(favs)"
echo "   visits.php: $(req $A "$B/visits.php" >/dev/null; visit)"
echo "   ідентифікатор сесії: $(same "$s_before" "$(sid $A)") (сесійна cookie LAB07SESSID не зберігається після закриття)"

echo; echo "== 5. Скидання теми"
t=$(token $A preferences.php)
echo "POST reset: $(post $A preferences.php --data-urlencode "csrf=$t" --data-urlencode action=reset) → $(loc)"; setck
echo "   GET index.php: тема $(req $A "$B/index.php" >/dev/null; theme) |$(flash); club_theme у сховищі: $(grep -c club_theme $A)"

echo; echo "== 6. Другий і третій GET visits.php (нове сховище)"
V=$(mktemp)
for i in 1 2 3; do echo "GET $i: $(req $V "$B/visits.php") / $(visit)"; [ $i -lt 3 ] && sleep 1; done
setck

echo; echo "== 7. Межові значення лічильника (cookie задано вручну)"
for v in 0 999998 999999; do echo "club_visits=$v → $(raw "$B/visits.php" -H "Cookie: club_visits=$v; club_last_visit=$(( $(date +%s) - 60 ))" >/dev/null; visit) | записано: $(grep -io 'club_visits=[0-9]*' "$H")"; done

echo; echo "== 8. Некоректний лічильник"
for v in -1 1.5 1000000 007 abc ''; do echo "club_visits='$v' → $(raw "$B/visits.php" -H "Cookie: club_visits=$v") / $(visit)$(warn)"; done
echo "club_visits[x]=5 (масив) → $(raw "$B/visits.php" -H "Cookie: club_visits[x]=5") / $(visit)$(warn)"

echo; echo "== 9. Відсутній, майбутній або некоректний попередній час (лічильник 5)"
now=$(date +%s)
for t in "" "$((now + 3600))" "0" "abc" "-5" "1.5"; do echo "club_last_visit='$t' → $(raw "$B/visits.php" -H "Cookie: club_visits=5$([ -n "$t" ] && echo "; club_last_visit=$t")" >/dev/null; visit)$(warn)"; done
echo "club_last_visit[x]=1 (масив) → $(raw "$B/visits.php" -H "Cookie: club_visits=5; club_last_visit[x]=1" >/dev/null; visit)$(warn)"

echo; echo "== 10. Некоректна тема в cookie"
for v in unknown '<b>dark</b>' '%3Cb%3Edark%3C%2Fb%3E' DARK ' dark'; do
    echo "club_theme='$v' → $(raw "$B/index.php" -H "Cookie: club_theme=$v") / тема $(theme), сирий тег <b> у відповіді: $(grep -c '<b>dark' "$OUT")$(warn)"; done
echo "club_theme[test]=dark (масив) → $(raw "$B/index.php" -H "Cookie: club_theme[test]=dark") / тема $(theme)$(warn)"

echo; echo "== 11. Невідома дія, тема чи захід; масив замість поля POST (стан не змінюється)"
S=$(mktemp)
t=$(token $S preferences.php); post $S preferences.php --data-urlencode "csrf=$t" --data-urlencode action=save --data-urlencode theme=dark >/dev/null
t=$(token $S favorites.php); post $S favorites.php --data-urlencode "csrf=$t" --data-urlencode action=add --data-urlencode id=eco >/dev/null
echo "початковий стан: тема $(req $S "$B/index.php" >/dev/null; theme), обраних $(favs)"
pf() { local t; t=$(token $S preferences.php); echo "$1: $(post $S preferences.php --data-urlencode "csrf=$t" "${@:2}")$(alert); Set-Cookie club_theme: $(grep -ci '^set-cookie: club_theme' "$H")$(warn)"; }
pf "preferences action=delete"   --data-urlencode action=delete --data-urlencode theme=light
pf "preferences без action"      --data-urlencode theme=light
pf "preferences theme=blue"      --data-urlencode action=save --data-urlencode theme=blue
pf "preferences theme=<b>dark</b>" --data-urlencode action=save --data-urlencode 'theme=<b>dark</b>'
pf "preferences theme[]=light"   --data-urlencode action=save --data-urlencode 'theme[]=light'
pf "preferences action[]=reset"  --data-urlencode 'action[]=reset'
ff() { local t; t=$(token $S favorites.php); echo "$1: $(post $S favorites.php --data-urlencode "csrf=$t" "${@:2}")$(alert)$(warn)"; }
ff "favorites action=delete"     --data-urlencode action=delete --data-urlencode id=quiz
ff "favorites id=party"          --data-urlencode action=add --data-urlencode id=party
ff "favorites id=<script>"       --data-urlencode action=add --data-urlencode 'id=<script>'
ff "favorites без id"            --data-urlencode action=add
ff "favorites id[]=quiz"         --data-urlencode action=add --data-urlencode 'id[]=quiz'
ff "favorites action[]=clear"    --data-urlencode 'action[]=clear'
echo "стан після відмов: тема $(req $S "$B/index.php" >/dev/null; theme), обраних $(favs)"
echo "GET end_session.php: $(req $S "$B/end_session.php") $(tr -d '\n' < "$OUT"), Allow: $(grep -i '^allow' "$H" | tr -d '\r' | sed 's/.*: //')"
echo "POST visits.php: $(req $S "$B/visits.php" -X POST --data x=1) $(tr -d '\n' < "$OUT"), Allow: $(grep -i '^allow' "$H" | tr -d '\r' | sed 's/.*: //')"
echo "PUT preferences.php: $(req $S "$B/preferences.php" -X PUT) $(tr -d '\n' < "$OUT")"

echo; echo "== 12. CSRF-токен: відсутній, змінений, масивом (HTTP 403, стан не змінюється)"
t=$(token $S favorites.php)
for page in "preferences.php action=save&theme=light" "favorites.php action=add&id=quiz" "favorites.php action=clear" "end_session.php x=1"; do
    set -- $page
    echo "$1 [$2]: без токена $(post $S "$1" --data "$2"); змінений $(post $S "$1" --data "$2&csrf=${t%?}0"); масив $(post $S "$1" --data "$2&csrf[]=$t") $(tr -d '\n' < "$OUT")"
done
s0=$(sid $S)
echo "стан: тема $(req $S "$B/index.php" >/dev/null; theme), обраних $(favs), ідентифікатор сесії $(same "$s0" "$(sid $S)")"

echo; echo "== 13. Обране: двічі доданий захід, видалення відсутнього, оновлення після POST"
F=$(mktemp)
t=$(token $F favorites.php)
echo "add cinema: $(post $F favorites.php --data-urlencode "csrf=$t" --data-urlencode action=add --data-urlencode id=cinema) → $(loc)"
echo "add cinema ще раз: $(post $F favorites.php --data-urlencode "csrf=$t" --data-urlencode action=add --data-urlencode id=cinema) → $(loc)"
echo "add trip: $(post $F favorites.php --data-urlencode "csrf=$t" --data-urlencode action=add --data-urlencode id=trip) → $(loc)"
echo "GET favorites.php: обраних $(req $F "$B/favorites.php" >/dev/null; favs): $(favlist) |$(flash)"
echo "оновлення (повторний GET після 303): обраних $(req $F "$B/favorites.php" >/dev/null; favs) — POST не повторено"
echo "remove eco (не було в списку): $(post $F favorites.php --data-urlencode "csrf=$t" --data-urlencode action=remove --data-urlencode id=eco) → $(loc); обраних $(req $F "$B/favorites.php" >/dev/null; favs)$(warn)"
echo "remove cinema: $(post $F favorites.php --data-urlencode "csrf=$t" --data-urlencode action=remove --data-urlencode id=cinema) → $(loc); обраних $(req $F "$B/favorites.php" >/dev/null; favs): $(favlist)"
echo "кількість на index.php: $(req $F "$B/index.php" >/dev/null; favs)"

echo; echo "== 14. Друга вкладка (той самий cookie-файл) і приватний контекст (окремий)"
echo "add quiz у першій вкладці: $(post $F favorites.php --data-urlencode "csrf=$t" --data-urlencode action=add --data-urlencode id=quiz)"
echo "друга вкладка, GET favorites.php: обраних $(req $F "$B/favorites.php" >/dev/null; favs): $(favlist)"
echo "приватний контекст: index $(req $P "$B/index.php") / тема $(theme), обраних $(favs); visits: $(req $P "$B/visits.php" >/dev/null; visit)"
echo "сесії різні: $( [ "$(sid $F)" != "$(sid $P)" ] && echo так || echo НІ)"

echo; echo "== 15. Очищення обраного і завершення сеансу"
t=$(token $F preferences.php); post $F preferences.php --data-urlencode "csrf=$t" --data-urlencode action=save --data-urlencode theme=dark >/dev/null
req $F "$B/visits.php" >/dev/null; v_before=$(jarval $F club_visits)
s1=$(sid $F); t=$(token $F favorites.php)
echo "clear: $(post $F favorites.php --data-urlencode "csrf=$t" --data-urlencode action=clear) → $(loc); обраних $(req $F "$B/favorites.php" >/dev/null; favs); ідентифікатор сесії $(same "$s1" "$(sid $F)"); токен $(same "$t" "$(grep -o 'name="csrf" value="[a-f0-9]*"' "$OUT" | head -1 | sed 's/.*value="//;s/"//')")"
t=$(token $F favorites.php); post $F favorites.php --data-urlencode "csrf=$t" --data-urlencode action=add --data-urlencode id=eco >/dev/null
echo "знову додано eco: обраних $(req $F "$B/favorites.php" >/dev/null; favs)"
echo "POST end_session.php: $(post $F end_session.php --data-urlencode "csrf=$t") → $(loc)"; setck
echo "   LAB07SESSID у сховищі після відповіді: $(grep -c LAB07SESSID $F)"
echo "   GET $(loc): $(req $F "$B/index.php?ended=1") |$(flash); тема $(theme), обраних $(favs)"; setck
echo "   ідентифікатор сесії після переходу: $(same "$s1" "$(sid $F)"); CSRF-токен: $(same "$t" "$(grep -o 'name="csrf" value="[a-f0-9]*"' "$OUT" | head -1 | sed 's/.*value="//;s/"//')")"
echo "   історія збережена: club_visits=$v_before → visits.php $(req $F "$B/visits.php" >/dev/null; visit)"
echo "   старий токен після завершення: $(post $F favorites.php --data-urlencode "csrf=$t" --data-urlencode action=add --data-urlencode id=eco) $(tr -d '\n' < "$OUT")"

echo; echo "== 16. Cookies заблоковані (кожен запит без cookies)"
for i in 1 2; do echo "visits.php запит $i: $(raw "$B/visits.php") / $(visit)"; done
t=$(raw "$B/preferences.php" >/dev/null; grep -o 'name="csrf" value="[a-f0-9]*"' "$OUT" | head -1 | sed 's/.*value="//;s/"//')
echo "POST save dark з токеном зі сторінки: $(raw "$B/preferences.php" --data-urlencode "csrf=$t" --data-urlencode action=save --data-urlencode theme=dark) $(tr -d '\n' < "$OUT") — сесію втрачено"
echo "index.php: тема $(raw "$B/index.php" >/dev/null; theme)"

echo; echo "== 17. Строк дії: тимчасово короткий інтервал (час завершення club_theme у сховищі змінено на +2 с)"
E=$(mktemp)
t=$(token $E preferences.php); post $E preferences.php --data-urlencode "csrf=$t" --data-urlencode action=save --data-urlencode theme=dark >/dev/null
echo "Max-Age теми: $(grep -io 'club_theme=dark;.*' "$H" | grep -o 'Max-Age=[0-9]*') (30 днів = $((30*24*3600))); visits: $(req $E "$B/visits.php" >/dev/null; grep -io 'club_visits=.*' "$H" | grep -o 'Max-Age=[0-9]*' | head -1) (7 днів = $((7*24*3600)))"
echo "одразу: тема $(req $E "$B/index.php" >/dev/null; theme)"
awk -v t=$(( $(date +%s) + 2 )) 'BEGIN{FS=OFS="\t"} $6=="club_theme"{$5=t} {print}' $E > $E.tmp && mv $E.tmp $E
sleep 3
echo "через 3 с: тема $(req $E "$B/index.php" >/dev/null; theme) — прострочену cookie не надіслано"

rm -f "$OUT" "$H" "$A" "$P" "$V" "$S" "$F" "$E"
