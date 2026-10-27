# Будує HTML-сторінки «запит — відповідь — Test Results» із журналу Newman (results/newman-run.json)
# для знімків у звіті, а також сторінку-підсумок у стилі Collection Runner.
# Запуск із каталогу lab-10:  python tools/render_evidence.py
import html
import json
import pathlib

ROOT = pathlib.Path(__file__).resolve().parent.parent
run = json.loads((ROOT / "results/newman-run.json").read_text(encoding="utf-8"))["run"]
OUT = ROOT / "report/shots"
OUT.mkdir(parents=True, exist_ok=True)
by_name = {e["item"]["name"]: e for e in run["executions"]}

CSS = """
body { font-family: Segoe UI, sans-serif; margin: 0; padding: 14px 18px; background: #fff; color: #212121; width: 900px; }
h1 { font-size: 17px; margin: 0 0 10px; } h2 { font-size: 13px; text-transform: uppercase; color: #6b6b6b; margin: 14px 0 6px; }
.req { border: 1px solid #ddd; border-radius: 6px; margin-bottom: 14px; }
.bar { display: flex; gap: 8px; align-items: center; padding: 8px 10px; border-bottom: 1px solid #eee; background: #fafafa; font-family: Consolas, monospace; font-size: 13px; }
.m { font-weight: bold; color: #0a7d32; } .m.POST { color: #ad7a03; } .m.PUT { color: #0053b8; } .m.DELETE { color: #b42318; } .m.PATCH { color: #6941c6; }
.cols { display: grid; grid-template-columns: 1fr 1fr; } .col { padding: 6px 10px; min-width: 0; } .col + .col { border-left: 1px solid #eee; }
table { border-collapse: collapse; font-size: 12px; width: 100%; } td { padding: 2px 6px; border-bottom: 1px solid #f0f0f0; vertical-align: top; word-break: break-all; }
td.k { color: #555; white-space: nowrap; width: 1%; }
pre { font-family: Consolas, monospace; font-size: 12px; background: #f6f8fa; border: 1px solid #eee; padding: 6px 8px; margin: 0; white-space: pre-wrap; word-break: break-all; }
.st { font-weight: bold; } .ok { color: #0a7d32; } .bad { color: #b42318; }
.tests div { font-size: 12.5px; padding: 1px 0; } .note { font-size: 12px; color: #666; }
.runner td, .runner th { border: 1px solid #ddd; padding: 3px 6px; font-size: 12px; text-align: left; } .runner th { background: #f3f3f3; }
"""


def body_text(entry):
    stream = (entry or {}).get("stream")
    if isinstance(stream, dict) and stream.get("data"):
        return bytes(stream["data"]).decode("utf-8", "replace")
    return ""


def request_body(req):
    body = req.get("body") or {}
    mode = body.get("mode")
    if mode == "raw":
        return body.get("raw", "")
    if mode == "urlencoded":
        return "&".join(f"{p['key']}={p['value']}" for p in body["urlencoded"])
    if mode == "formdata":
        return "\n".join(f"{p['key']} ({p.get('type', 'text')}): {p.get('value') or p.get('src')}" for p in body["formdata"])
    return ""


def full_url(req):
    u = req["url"]
    host = ".".join(u["host"])
    query = "&".join(f"{q['key']}={q['value']}" for q in u.get("query", []) if not q.get("disabled"))
    return f"{u['protocol']}://{host}:{u['port']}/{'/'.join(u['path'])}" + (f"?{query}" if query else "")


def headers_table(headers, hide_system=True):
    rows = []
    for h in headers:
        if hide_system and h.get("system") and h["key"] not in ("Content-Type", "Authorization", "Content-Length"):
            continue
        value = h["value"]
        if h["key"].lower() == "authorization":
            value = value.split(" ")[0] + " ••••••••  (значення приховано)"
        rows.append(f"<tr><td class='k'>{html.escape(h['key'])}</td><td>{html.escape(value)}</td></tr>")
    return "<table>" + "".join(rows) + "</table>" if rows else "<p class='note'>—</p>"


def pretty(text):
    try:
        return json.dumps(json.loads(text), ensure_ascii=False, indent=2)
    except (ValueError, TypeError):
        return text


def execution_block(name):
    e = by_name[name]
    req, res = e["request"], e["response"]
    tests = e.get("assertions", [])
    body_in = request_body(e["item"]["request"])
    body_out = body_text(res)
    test_rows = "".join(
        f"<div class='{'bad' if t.get('error') else 'ok'}'>{'✗' if t.get('error') else '✓'} {html.escape(t['assertion'])}</div>"
        for t in tests)
    status_cls = "ok" if res["code"] < 400 else "bad"
    return f"""
<div class="req">
  <div class="bar"><span class="m {req['method']}">{req['method']}</span><span>{html.escape(full_url(req))}</span></div>
  <div class="cols">
    <div class="col"><h2>Запит: заголовки</h2>{headers_table(req.get('header', []))}
      <h2>Запит: тіло</h2>{f'<pre>{html.escape(body_in)}</pre>' if body_in else "<p class='note'>Тіла немає</p>"}</div>
    <div class="col"><h2>Відповідь <span class="st {status_cls}">{res['code']} {html.escape(res['status'])}</span> · {res.get('responseTime', '?')} мс</h2>
      {headers_table([h for h in res.get('header', []) if h['key'] not in ('Host', 'Date', 'Connection', 'X-Powered-By')], False)}
      <h2>Тіло відповіді</h2>{f'<pre>{html.escape(pretty(body_out))}</pre>' if body_out else "<p class='note'>Порожнє тіло</p>"}</div>
  </div>
  <div class="col tests"><h2>Test Results ({sum(1 for t in tests if not t.get('error'))}/{len(tests)})</h2>{test_rows}</div>
</div>"""


def page(filename, title, names):
    blocks = "".join(f"<h1>{html.escape(n)}</h1>{execution_block(n)}" for n in names)
    (OUT / filename).write_text(f"<!doctype html><html lang='uk'><head><meta charset='utf-8'><title>{html.escape(title)}</title>"
                                f"<style>{CSS}</style></head><body>{blocks}</body></html>", encoding="utf-8")


page("e01_get_list.html", "GET список", ["01 — Список завдань", "02 — Фільтр виконаних (done=1)"])
page("e02_urlencoded.html", "urlencoded", ["03 — URL-encoded форма"])
page("e03_post_201.html", "POST 201", ["04 — Створити завдання"])
page("e04_put_get.html", "PUT і GET", ["06 — Оновити завдання (PUT)", "07 — Підтвердити збереження PUT"])
page("e05_delete_404.html", "DELETE 204 і 404", ["08 — Видалити завдання", "09 — Перевірити відсутність"])
page("e06_multipart.html", "multipart", ["Вручну — Вкладення attachment"])
page("e07_auth.html", "Basic Auth", ["21 — Без автентифікації", "22 — Basic Auth (демонстраційні дані)"])
page("e08_errors.html", "Помилки", ["14 — Невідповідний Content-Type", "18 — Непідтримуваний метод PATCH"])
page("e09_search.html", "Пошук q", ["24 — q великими літерами", "27 — q разом із done=0"])

# Підсумок у стилі Collection Runner.
stats = run["stats"]
rows = []
folder_of = {}
for f in json.loads((ROOT / "postman/lab10.postman_collection.json").read_text(encoding="utf-8"))["item"]:
    for it in f["item"]:
        folder_of[it["name"]] = f["name"]
for e in run["executions"]:
    tests = e.get("assertions", [])
    failed = sum(1 for t in tests if t.get("error"))
    res = e["response"]
    rows.append(f"<tr><td>{html.escape(folder_of[e['item']['name']][:2])}</td><td>{html.escape(e['item']['name'])}</td>"
                f"<td>{e['request']['method']}</td><td class='st {'ok' if res['code'] < 400 else 'bad'}'>{res['code']} {html.escape(res['status'])}</td>"
                f"<td class='{'bad' if failed else 'ok'}'>{len(tests) - failed}/{len(tests)}</td></tr>")
summary = (f"<h1>Collection Runner (Newman): ЛР 10 — HTTP і Postman · середовище Lab10 Local · 1 ітерація</h1>"
           f"<p>Запитів: {stats['requests']['total']} (помилок {stats['requests']['failed']}) · перевірок: {stats['assertions']['total']} "
           f"(невдалих {stats['assertions']['failed']}) · тестових скриптів: {stats['testScripts']['total']}</p>"
           "<table class='runner'><tr><th>Папка</th><th>Запит</th><th>Метод</th><th>Статус</th><th>Тести</th></tr>" + "".join(rows) + "</table>")
(OUT / "e10_runner.html").write_text(f"<!doctype html><html lang='uk'><head><meta charset='utf-8'><title>Runner</title><style>{CSS}</style></head>"
                                     f"<body>{summary}</body></html>", encoding="utf-8")
print("Сторінки створено:", len(list(OUT.glob("e*.html"))))
