# Автоматичні перевірки ЛР 4. Запуск із каталогу lab-04 (сервер: каталог src доступний як /lab-04/,
# лістинги методички — як /lab-04-given/):  python tools/checks.py > tools/checks-output.txt
# Аргумент --no-w3c вимикає перевірку валідатором W3C (потрібен інтернет).
import html
import json
import re
import subprocess
import sys
import time
import urllib.error
import urllib.parse
import urllib.request
from collections import Counter

BASE = 'http://127.0.0.1:8040/lab-04/'
GIVEN = 'http://127.0.0.1:8040/lab-04-given/'
PHP = 'C:/xampp/php/php.exe'
W3C = '--no-w3c' not in sys.argv
pages_for_w3c = {}


def get(url):
    try:
        with urllib.request.urlopen(url) as r:
            return r.status, r.read().decode('utf-8')
    except urllib.error.HTTPError as e:
        return e.code, e.read().decode('utf-8')


def text(fragment):
    return html.unescape(re.sub(r'<[^>]+>', ' ', fragment))


def by_id(page, tag, ident):
    m = re.search(rf'<{tag}[^>]*id="{ident}"[^>]*>(.*?)</{tag}>', page, re.S)
    return m[1] if m else ''


def numbers(fragment):
    return [int(x) for x in re.findall(r'-?\d+', text(fragment))]


def warn(page):
    return ' [!] службове повідомлення PHP' if re.search(r'Warning|Notice|Deprecated|Fatal error|TypeError', page) else ''


def tables(page):
    result = []
    for t in re.findall(r'<table[^>]*>(.*?)</table>', page, re.S):
        rows = [[text(c).strip() for c in re.findall(r'<t[dh][^>]*>(.*?)</t[dh]>', r, re.S)] for r in re.findall(r'<tr>(.*?)</tr>', t, re.S)]
        cap = re.search(r'<caption>(.*?)</caption>', t, re.S)
        result.append((text(cap[1]).strip() if cap else '', rows))
    return result


def matrix(page, ident):
    t = re.search(rf'<table class="matrix" id="{ident}">(.*?)</table>', page, re.S)[1]
    return [[int(c) for c in re.findall(r'<td>(-?\d+)</td>', r)] for r in re.findall(r'<tr>(.*?)</tr>', t, re.S)]


print('== 1. Середовище')
status, page = get(BASE + 'index.php')
step_links = len(re.findall(r'<li><a href="step', re.search(r'<ol>(.*?)</ol>', page, re.S)[1]))
print(f'index.php: {status}; {re.search(r"PHP [0-9.]+", page)[0]}; посилань на етапи: {step_links}')
pages_for_w3c['index.php'] = page

print('\n== 2. Лістинги з методички (кроки 3, 5, 8, 10, 11)')
lint = subprocess.run([PHP, '-l', 'given/listing_1.php'], capture_output=True, text=True)
print('listing_1.php (крок 5), php -l:', (lint.stdout + lint.stderr).strip().splitlines()[0].replace('given/', ''))
for n in (2, 3, 4):
    status, page = get(GIVEN + f'listing_{n}.php')
    pages_for_w3c[f'методичка listing_{n}.php'] = page
    print(f'listing_{n}.php: {status}{warn(page)}')

print('\n== 3. Крок 5: парні числа (for і while), виправлений лістинг 1')
status, page = get(BASE + 'step05_even.php')
pages_for_w3c['step05_even.php'] = page
parts = re.findall(r'<p class="numbers">(.*?)</p>', page, re.S)
for name, part in zip(('for', 'while'), parts):
    nums = numbers(part)
    print(f'{name}: {len(nums)} чисел, {nums[:4]}…{nums[-2:]}, усі парні: {all(x % 2 == 0 for x in nums)}, = 0..100 крок 2: {nums == list(range(0, 101, 2))}, <br> між числами: {part.count("<br>")}')

print('\n== 4. Крок 7: непарні числа в рядок')
status, page = get(BASE + 'step07_odd_inline.php')
pages_for_w3c['step07_odd_inline.php'] = page
for ident in ('odd-for', 'odd-while'):
    part = by_id(page, 'p', ident)
    nums = numbers(part)
    print(f'{ident}: {len(nums)} чисел, {nums[:4]}…{nums[-2:]}, = 1..99 крок 2: {nums == list(range(1, 100, 2))}, <br>: {part.count("<br>")}, перенесень рядка: {part.strip().count(chr(10))}')

print('\n== 5. Крок 8: асоціативний масив k0…k100')
status, page = get(BASE + 'step08_assoc.php')
pages_for_w3c['step08_assoc.php'] = page
dump = html.unescape(by_id(page, 'pre', 'dump'))
pairs = re.findall(r'\["(k\d+)"\]=>\s*int\((\d+)\)', dump)
print(f'var_dump: {dump.strip().splitlines()[0]}; пар ключ=>значення: {len(pairs)}; перша {pairs[0]}, остання {pairs[-1]}; '
      f'усі ключі = "k"+значення: {all(k == "k" + v for k, v in pairs)}')

print('\n== 6. Крок 10: сортування «бульбашкою» (тричі, щоразу нові випадкові числа)')
for attempt in range(3):
    status, page = get(BASE + 'step10_bubble.php')
    before, after = numbers(by_id(page, 'p', 'before')), numbers(by_id(page, 'p', 'after'))
    print(f'спроба {attempt + 1}: до {len(before)} чисел (0–100: {min(before) >= 0 and max(before) <= 100}), після {len(after)}; '
          f'неспадний порядок: {all(a <= b for a, b in zip(after, after[1:]))}; той самий набір: {Counter(before) == Counter(after)}; '
          f'{text(by_id(page, "p", "check")).strip()}')
pages_for_w3c['step10_bubble.php'] = page

print('\n== 7. Крок 11: багатовимірний масив')
status, page = get(BASE + 'step11_people.php')
pages_for_w3c['step11_people.php'] = page
dumps = [html.unescape(d) for d in re.findall(r'<pre>(.*?)</pre>', page, re.S)]
name_re = r'"name"\]=>\s*string\(\d+\) "(\w+)"'
first_lines = [d.strip().splitlines()[0] for d in dumps]
print(f'var_dump($arPerson): {first_lines[0]}, ім’я: {re.findall(name_re, dumps[0])}')
print(f'var_dump($arPeople): {first_lines[1]}, імена: {re.findall(name_re, dumps[1])}')

print('\n== 8. Крок 12: таблиця людей (функція printTable, foreach)')
status, page = get(BASE + 'step12_people_table.php')
pages_for_w3c['step12_people_table.php'] = page
cap, rows = tables(page)[0]
print(f'{cap}: {len(rows) - 1} рядки')
for r in rows:
    print('   ' + ' | '.join(r))

print('\n== 9. Крок 13: товари магазину')
status, page = get(BASE + 'step13_shop.php')
pages_for_w3c['step13_shop.php'] = page
all_t = tables(page)
print(f'{status}; таблиць: {len(all_t)}; {all_t[0][0]}: {len(all_t[0][1]) - 1} товарів')
names = [r[1] for r in all_t[1][1][1:]]
print(f'{all_t[1][0]}:')
print('   ' + '; '.join(names))
alphabet = 'абвгґдеєжзиіїйклмнопрстуфхцчшщьюя'
expected = sorted(names, key=lambda s: [alphabet.index(c) if c in alphabet else 100 + ord(c) for c in s.lower()])
print(f'   збігається з порядком української абетки: {names == expected}; для порівняння, побайтове сортування (strcmp) дало б: '
      f'{"; ".join(sorted(names, key=lambda s: s.encode())[-4:])} — у кінці')
print(f'{all_t[2][0]} (без параметрів)')
ranges = [('20', '150'), ('', '100'), ('300', ''), ('45', '45'), ('45,00', '68.5'), ('0', '0'), ('1000', '2000')]
for lo, hi in ranges:
    q = urllib.parse.urlencode({'min_price': lo, 'max_price': hi})
    status, page = get(BASE + 'step13_shop.php?' + q)
    cap, rows = tables(page)[2] if len(tables(page)) > 2 else ('', [])
    prices = [float(r[2].replace(' ', '').replace(',', '.')) for r in rows[1:]]
    lo_v, hi_v = float((lo or '0').replace(',', '.')), float((hi or '100000').replace(',', '.'))
    found = cap or re.search(r'Товарів, що відповідають умові, немає', page) and 'немає товарів'
    print(f'?{q}: {status}; {found}; ціни {prices}; усі в межах: {all(lo_v <= p <= hi_v for p in prices)}{warn(page)}')
pages_for_w3c['step13_shop.php?min_price=20&max_price=150'] = get(BASE + 'step13_shop.php?min_price=20&max_price=150')[1]
for q in ['min_price=abc', 'min_price=-5', 'max_price=1e3', 'min_price=12.345', 'min_price=200&max_price=100', 'min_price[]=1', 'max_price=100001', 'min_price=%2010%20']:
    status, page = get(BASE + 'step13_shop.php?' + q)
    err = re.search(r'role="alert">(.*?)</div>', page, re.S)
    print(f'?{q}: {status}; {text(err[1]).strip() if err else "фільтр застосовано"}{warn(page)}')
pages_for_w3c['step13_shop.php?min_price=abc'] = get(BASE + 'step13_shop.php?min_price=abc')[1]

print('\n== 10. Крок 14: матриці 10×10')
for q in ('seed=42', 'seed=2026', ''):
    status, page = get(BASE + 'step14_matrices.php' + ('?' + q if q else ''))
    a, b, s, d = (matrix(page, i) for i in ('matrix-a', 'matrix-b', 'matrix-sum', 'matrix-diff'))
    ok_sum = all(s[i][j] == a[i][j] + b[i][j] for i in range(10) for j in range(10))
    ok_diff = all(d[i][j] == a[i][j] - b[i][j] for i in range(10) for j in range(10))
    values = [x for m in (a, b) for row in m for x in row]
    print(f'?{q or "(без seed)"}: {status}; розміри {len(a)}×{len(a[0])}, {len(b)}×{len(b[0])}, {len(s)}×{len(s[0])}, {len(d)}×{len(d[0])}; '
          f'A, B у 0–100: {min(values) >= 0 and max(values) <= 100}; A+B правильно: {ok_sum}; A−B правильно: {ok_diff}; A[0][:5] = {a[0][:5]}')
    if q == 'seed=42':
        pages_for_w3c['step14_matrices.php?seed=42'] = page
        first42 = a
again = matrix(get(BASE + 'step14_matrices.php?seed=42')[1], 'matrix-a')
print(f'повтор seed=42 дає ту саму A: {again == first42}; два запити без seed різні: '
      f'{matrix(get(BASE + "step14_matrices.php")[1], "matrix-a") != matrix(get(BASE + "step14_matrices.php")[1], "matrix-a")}')
for q in ('seed=abc', 'seed=-1', 'seed[]=1', 'seed=2147483648'):
    status, page = get(BASE + 'step14_matrices.php?' + q)
    err = re.search(r'role="alert">(.*?)</p>', page, re.S)
    print(f'?{q}: {status}; {text(err[1]).strip() if err else "—"}{warn(page)}')
pages_for_w3c['step14_matrices.php?seed=abc'] = get(BASE + 'step14_matrices.php?seed=abc')[1]

if W3C:
    print('\n== 11. W3C Nu HTML Checker (згенерований HTML)')
    for name, page in pages_for_w3c.items():
        req = urllib.request.Request('https://validator.w3.org/nu/?out=json', data=page.encode('utf-8'),
                                     headers={'Content-Type': 'text/html; charset=utf-8', 'User-Agent': 'lab04-checks'})
        msgs = json.load(urllib.request.urlopen(req))['messages']
        print(f'{name}: {len(msgs)}' + ''.join(f'\n   {m["type"]}: {m["message"]}' for m in msgs[:6]))
        time.sleep(1)
