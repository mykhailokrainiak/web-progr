# Будує HTML-сторінки «Network» і «Application → Cookies» із журналу браузера (results/browser-log.json)
# для знімків у звіті. Ідентифікатор сесії та CSRF-токени в журналі вже приховано («…»).
# Запуск із каталогу lab-07:  python tools/render_evidence.py
import html
import json
import pathlib

ROOT = pathlib.Path(__file__).resolve().parent.parent
log = json.loads((ROOT / "results/browser-log.json").read_text(encoding="utf-8"))
by_prefix = {s["title"].split(" ")[0].rstrip("."): s for s in log["steps"]}
OUT = ROOT / "report/shots"
OUT.mkdir(parents=True, exist_ok=True)

CSS = """
body { font-family: Segoe UI, sans-serif; margin: 0; padding: 12px 16px; background: #fff; color: #202124; width: 880px; }
h1 { font-size: 15px; margin: 0 0 8px; } .note { font-size: 12px; color: #5f6368; margin: 6px 0; }
.req { border: 1px solid #dadce0; border-radius: 4px; margin-bottom: 8px; font-size: 12px; }
.line { background: #f1f3f4; padding: 4px 8px; font-family: Consolas, monospace; font-weight: bold; }
.ok { color: #188038; } .redir { color: #b06000; } .bad { color: #d93025; }
.hdr { padding: 3px 8px 5px; font-family: Consolas, monospace; } .hdr div { padding: 1px 0; word-break: break-all; }
.k { color: #881280; } .dir { color: #5f6368; font-family: Segoe UI, sans-serif; font-size: 11px; text-transform: uppercase; margin-top: 3px; }
table { border-collapse: collapse; width: 100%; font-size: 12px; } th, td { border: 1px solid #dadce0; padding: 3px 6px; text-align: left; }
th { background: #f1f3f4; font-weight: 600; } td.c { text-align: center; }
pre { background: #202124; color: #e8eaed; font-family: Consolas, monospace; font-size: 12px; padding: 6px 8px; margin: 4px 0; }
"""


def status_cls(code):
    return "ok" if code < 300 else "redir" if code < 400 else "bad"


def network(entries):
    rows = []
    for n in entries:
        hdr = []
        if n.get("postData"):
            hdr.append(f"<div class='dir'>Тіло запиту (Form Data)</div><div>{html.escape(n['postData'])}</div>")
        if n.get("cookie"):
            hdr.append(f"<div class='dir'>Заголовок запиту</div><div><span class='k'>Cookie:</span> {html.escape(n['cookie'])}</div>")
        resp = []
        if n.get("location"):
            resp.append(f"<div><span class='k'>Location:</span> {html.escape(n['location'])}</div>")
        resp += [f"<div><span class='k'>Set-Cookie:</span> {html.escape(s)}</div>" for s in n["setCookie"]]
        if resp:
            hdr.append("<div class='dir'>Заголовки відповіді</div>" + "".join(resp))
        rows.append(f"<div class='req'><div class='line'>{n['method']} /lab-07{html.escape(n['url'])} → "
                    f"<span class='{status_cls(n['status'])}'>{n['status']}</span></div><div class='hdr'>{''.join(hdr)}</div></div>")
    return "".join(rows)


def store(cookies):
    order = ["LAB07SESSID", "club_theme", "club_visits", "club_last_visit"]
    cookies = sorted(cookies, key=lambda c: order.index(c["name"]) if c["name"] in order else 9)
    tick = lambda v: "✓" if v else ""
    rows = "".join(
        f"<tr><td>{html.escape(c['name'])}</td><td>{html.escape(c['value'])}</td><td>{c['domain']}</td><td>{c['path']}</td>"
        f"<td>{c['expires']}</td><td class='c'>{tick(c['httpOnly'])}</td><td class='c'>{tick(c['secure'])}</td><td>{c['sameSite']}</td></tr>"
        for c in cookies)
    return ("<table><tr><th>Name</th><th>Value</th><th>Domain</th><th>Path</th><th>Expires / Max-Age</th>"
            f"<th>HttpOnly</th><th>Secure</th><th>SameSite</th></tr>{rows}</table>")


def page(name, body):
    (OUT / name).write_text(f"<!doctype html><html lang='uk'><head><meta charset='utf-8'><title>{name}</title>"
                            f"<style>{CSS}</style></head><body>{body}</body></html>", encoding="utf-8")


s2, s2c, s3, s9, s10b, s12 = (by_prefix[k] for k in ("2", "2в", "3", "9", "10б", "12"))
page("e01_network_theme.html",
     "<h1>Network: збереження темної теми — POST → 303 → GET (Edge, " + html.escape(log["browser"]) + ")</h1>"
     + network(s2["network"][1:])
     + "<p class='note'>Відповідь на POST містить Set-Cookie з club_theme=dark; наступний GET index.php уже надсилає Cookie: … club_theme=dark. "
       "Значення LAB07SESSID і csrf приховано.</p>")
page("e02_storage_httponly.html",
     "<h1>Application → Cookies → http://127.0.0.1:8070 (після збереження теми)</h1>" + store(s2c["cookieStore"])
     + "<h1 style='margin-top:12px'>Console</h1><pre>&gt; document.cookie\n&lt; " + html.escape(json.dumps(s2c["documentCookie"])) + "</pre>"
     + "<p class='note'>Усі чотири cookies позначені HttpOnly, тому JavaScript їх не бачить: document.cookie повертає порожній рядок, "
       "хоча cookies є у сховищі браузера й надсилаються в заголовку Cookie. LAB07SESSID — сесійна (Session), інші мають строк дії.</p>")
page("e03_network_visits.html",
     "<h1>Network: другий і третій перегляд visits.php</h1>" + network(s3["network"])
     + "<p class='note'>Кожна відповідь записує новий лічильник і поточний Unix-час на 7 днів (Max-Age=604800); "
       "наступний запит надсилає ці значення в Cookie.</p>")
page("e04_network_end_session.html",
     "<h1>Network: «Завершити сеанс»</h1>" + network(s9["network"][3:])
     + "<p class='note'>Відповідь видаляє cookie сесії (минулий expires, той самий path); GET після перенаправлення надходить без LAB07SESSID, "
       "тому PHP створює нову сесію. club_theme і club_visits не змінилися. Ідентифікатор сесії після переходу: "
       + ("змінився" if s9["sessionChanged"] else "не змінився") + ".</p>")
page("e05_after_restart.html",
     "<h1>Після закриття й повторного відкриття браузера (той самий профіль)</h1>" + network(s10b["network"])
     + store(s10b["cookieStore"])
     + "<p class='note'>Постійні cookies (тема, лічильник, час) збереглися й надіслані; сесійна LAB07SESSID зникла разом із закриттям браузера, "
       "тому сервер створив нову сесію з порожнім списком обраного.</p>")
page("e06_blocked.html",
     "<h1>Cookies заблоковані для сайту (налаштування профілю браузера)</h1>" + network(s12["network"])
     + "<p class='note'>Сервер щоразу надсилає Set-Cookie, але браузер їх не приймає й не надсилає Cookie: кожен перегляд — №1, "
       "кожен запит — нова сесія. Тому POST-форма з токеном попередньої сесії отримує 403.</p>")
print("Сторінки створено:", len(list(OUT.glob("e*.html"))))
