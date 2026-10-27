# Генерує колекцію й середовище Postman (формат Collection v2.1) для ЛР 10.
# Запуск із каталогу lab-10:  python tools/build_collection.py
import json
import pathlib

ROOT = pathlib.Path(__file__).resolve().parent.parent
OUT = ROOT / "postman"
JSON_HEADERS = [{"key": "Accept", "value": "application/json"}]


def url(**query):
    pairs = [{"key": k.replace("_arr", "[]"), "value": str(v)} for k, v in query.items()]
    raw = "{{base_url}}" + ("?" + "&".join(f"{p['key']}={p['value']}" for p in pairs) if pairs else "")
    return {"raw": raw, "host": ["{{base_url}}"], "query": pairs}


def request(name, method, query, tests, body=None, headers=None, auth=None, prerequest=None, description=""):
    req = {"method": method, "header": list(headers if headers is not None else JSON_HEADERS), "url": url(**query)}
    if description:
        req["description"] = description
    if body is not None:
        req["body"] = body
    if auth is not None:
        req["auth"] = auth
    events = []
    if prerequest:
        events.append({"listen": "prerequest", "script": {"type": "text/javascript", "exec": prerequest}})
    events.append({"listen": "test", "script": {"type": "text/javascript", "exec": tests}})
    return {"name": name, "request": req, "event": events, "response": []}


def raw_json(text):
    return {"mode": "raw", "raw": text, "options": {"raw": {"language": "json"}}}


def json_post_headers():
    return JSON_HEADERS + [{"key": "Content-Type", "value": "application/json"}]


def status(code, text):
    return [f'pm.test("Статус {code} {text}", () => pm.response.to.have.status({code}));']


JSON_MIME = ['pm.test("Відповідь JSON", () => pm.expect(pm.response.headers.get("Content-Type")).to.include("application/json"));']


def error_tests(code, text, fragment):
    return status(code, text) + JSON_MIME + [
        "const body = pm.response.json();",
        f'pm.test("error.message пояснює причину", () => {{ pm.expect(body.error.message).to.be.a("string").and.include({json.dumps(fragment, ensure_ascii=False)}); }});',
        'pm.test("Немає data", () => pm.expect(body).to.not.have.property("data"));',
    ]


def list_tests(expected_count, extra=()):
    return status(200, "OK") + JSON_MIME + [
        "const body = pm.response.json();",
        'pm.test("data — масив, meta.count = довжина", () => { pm.expect(body.data).to.be.an("array"); pm.expect(body.meta.count).to.equal(body.data.length); });',
        f'pm.test("Записів: {expected_count}", () => pm.expect(body.meta.count).to.equal({expected_count}));',
        *extra,
    ]


main = [
    request("01 — Список завдань", "GET", {"resource": "tasks"}, list_tests(2, [
        'pm.test("Початкові записи id 1 і 2", () => pm.expect(body.data.map(t => t.id)).to.eql([1, 2]));',
    ])),
    request("02 — Фільтр виконаних (done=1)", "GET", {"resource": "tasks", "done": 1}, list_tests(1, [
        'pm.test("Лише done = true", () => body.data.forEach(t => pm.expect(t.done).to.equal(true)));',
        'pm.test("Це завдання id 2", () => pm.expect(body.data[0].id).to.equal(2));',
    ])),
    request("02a — Фільтр невиконаних (done=0)", "GET", {"resource": "tasks", "done": 0}, list_tests(1, [
        'pm.test("Лише done = false", () => body.data.forEach(t => pm.expect(t.done).to.equal(false)));',
    ])),
    request("02b — Один запис (id=1)", "GET", {"resource": "tasks", "id": 1}, status(200, "OK") + JSON_MIME + [
        "const body = pm.response.json();",
        'pm.test("data — об’єкт id 1", () => { pm.expect(body.data).to.be.an("object"); pm.expect(body.data.id).to.equal(1); pm.expect(body.data.title).to.equal("Перевірити GET"); });',
        'pm.test("Для одного запису немає meta", () => pm.expect(body).to.not.have.property("meta"));',
    ]),
    request("02c — id разом із done", "GET", {"resource": "tasks", "id": 1, "done": 1}, error_tests(400, "Bad Request", "done")),
    request("03 — URL-encoded форма", "POST", {"resource": "echo"},
            status(200, "OK") + JSON_MIME + [
                "const d = pm.response.json().data;",
                'pm.test("content_type — urlencoded", () => pm.expect(d.content_type).to.equal("application/x-www-form-urlencoded"));',
                'pm.test("Поля тіла в $_POST", () => { pm.expect(d.fields.name).to.equal("Студент DEMO"); pm.expect(d.fields.group).to.equal("DEMO"); });',
                'pm.test("resource — з URL, а не з тіла", () => pm.expect(d.fields).to.not.have.property("resource"));',
                'pm.test("Файла немає", () => pm.expect(d.file).to.equal(null));',
            ],
            body={"mode": "urlencoded", "urlencoded": [{"key": "name", "value": "Студент DEMO"}, {"key": "group", "value": "DEMO"}]}),
    request("04 — Створити завдання", "POST", {"resource": "tasks"},
            status(201, "Created") + JSON_MIME + [
                "const body = pm.response.json();",
                'pm.test("Новий запис", () => { pm.expect(body.data.id).to.be.a("number").above(0); pm.expect(body.data.title).to.equal("Перевірити POST"); pm.expect(body.data.done).to.equal(false); });',
                'pm.test("Location веде на новий запис", () => pm.expect(pm.response.headers.get("Location")).to.equal("api.php?resource=tasks&id=" + body.data.id));',
                'if (pm.response.code === 201 && Number.isInteger(body.data.id)) { pm.environment.set("task_id", body.data.id); }',
            ],
            body=raw_json('{"title": "Перевірити POST", "done": false}'), headers=json_post_headers(),
            prerequest=['pm.environment.unset("task_id");']),
    request("05 — Прочитати створене", "GET", {"resource": "tasks", "id": "{{task_id}}"}, status(200, "OK") + [
        "const t = pm.response.json().data;",
        'pm.test("Той самий запис, що створив POST", () => { pm.expect(t.id).to.equal(Number(pm.environment.get("task_id"))); pm.expect(t.title).to.equal("Перевірити POST"); pm.expect(t.done).to.equal(false); });',
    ]),
    request("06 — Оновити завдання (PUT)", "PUT", {"resource": "tasks", "id": "{{task_id}}"}, status(200, "OK") + JSON_MIME + [
        "const t = pm.response.json().data;",
        'pm.test("id незмінний, нові title і done", () => { pm.expect(t.id).to.equal(Number(pm.environment.get("task_id"))); pm.expect(t.title).to.equal("Перевірити PUT"); pm.expect(t.done).to.equal(true); });',
    ], body=raw_json('{"title": "Перевірити PUT", "done": true}'), headers=json_post_headers()),
    request("06a — PUT без done", "PUT", {"resource": "tasks", "id": "{{task_id}}"},
            error_tests(422, "Unprocessable Content", "done"),
            body=raw_json('{"title": "Без done"}'), headers=json_post_headers()),
    request("07 — Підтвердити збереження PUT", "GET", {"resource": "tasks", "id": "{{task_id}}"}, status(200, "OK") + [
        "const t = pm.response.json().data;",
        'pm.test("Збережено PUT, а не невдалий PUT без done", () => { pm.expect(t.title).to.equal("Перевірити PUT"); pm.expect(t.done).to.equal(true); });',
    ]),
    request("08 — Видалити завдання", "DELETE", {"resource": "tasks", "id": "{{task_id}}"}, status(204, "No Content") + [
        'pm.test("Тіло порожнє", () => pm.expect(pm.response.text()).to.equal(""));',
        'pm.test("Немає Content-Type JSON", () => pm.expect(pm.response.headers.get("Content-Type") || "").to.not.include("application/json"));',
    ]),
    request("09 — Перевірити відсутність", "GET", {"resource": "tasks", "id": "{{task_id}}"}, error_tests(404, "Not Found", "не знайдено")),
    request("09a — Повторний DELETE", "DELETE", {"resource": "tasks", "id": "{{task_id}}"}, error_tests(404, "Not Found", "не знайдено")),
    request("10 — Список після циклу CRUD", "GET", {"resource": "tasks"}, list_tests(2, [
        'pm.test("Видаленого запису немає, інші залишилися", () => { const ids = body.data.map(t => t.id); pm.expect(ids).to.eql([1, 2]); pm.expect(ids).to.not.include(Number(pm.environment.get("task_id"))); });',
    ])),
]

negative = [
    request("11 — Порожній title", "POST", {"resource": "tasks"}, error_tests(422, "Unprocessable Content", "title"),
            body=raw_json('{"title": "   ", "done": false}'), headers=json_post_headers()),
    request("12 — Неправильні типи", "POST", {"resource": "tasks"}, error_tests(422, "Unprocessable Content", "title"),
            body=raw_json('{"title": [], "done": "false"}'), headers=json_post_headers()),
    request("12a — Рядок \"false\" замість false", "POST", {"resource": "tasks"}, error_tests(422, "Unprocessable Content", "done"),
            body=raw_json('{"title": "Тест", "done": "false"}'), headers=json_post_headers()),
    request("12b — Поле id у тілі", "POST", {"resource": "tasks"}, error_tests(422, "Unprocessable Content", "id генерує сервер"),
            body=raw_json('{"id": 77, "title": "Тест", "done": false}'), headers=json_post_headers()),
    request("13 — Пошкоджений JSON", "POST", {"resource": "tasks"}, error_tests(400, "Bad Request", "JSON"),
            body=raw_json('{"title": "Тест",}'), headers=json_post_headers()),
    request("14 — Невідповідний Content-Type", "POST", {"resource": "tasks"}, error_tests(415, "Unsupported Media Type", "application/json"),
            body={"mode": "raw", "raw": '{"title": "Тест", "done": false}', "options": {"raw": {"language": "text"}}},
            headers=JSON_HEADERS + [{"key": "Content-Type", "value": "text/plain"}]),
    request("15 — Масив у query (id[])", "GET", {"resource": "tasks", "id_arr": 1}, error_tests(400, "Bad Request", "рядком")),
    request("16 — Невідомий запис", "GET", {"resource": "tasks", "id": 999999999}, error_tests(404, "Not Found", "не знайдено")),
    request("17 — Невідомий ресурс", "GET", {"resource": "unknown"}, error_tests(404, "Not Found", "Ресурс")),
    request("18 — Непідтримуваний метод PATCH", "PATCH", {"resource": "tasks"}, error_tests(405, "Method Not Allowed", "Метод") + [
        'pm.test("Allow перелічує підтримувані методи", () => pm.expect(pm.response.headers.get("Allow")).to.equal("GET, POST, PUT, DELETE"));',
    ]),
    request("19 — DELETE без id", "DELETE", {"resource": "tasks"}, error_tests(400, "Bad Request", "id")),
    request("20 — Невдалі POST не створили записів", "GET", {"resource": "tasks"}, list_tests(2, [
        'pm.test("Лише початкові id 1 і 2", () => pm.expect(body.data.map(t => t.id)).to.eql([1, 2]));',
    ])),
]

basic = {"type": "basic", "basic": [{"key": "username", "value": "student", "type": "string"},
                                     {"key": "password", "value": "demo-pass", "type": "string"}]}
wrong = {"type": "basic", "basic": [{"key": "username", "value": "student", "type": "string"},
                                     {"key": "password", "value": "wrong-pass", "type": "string"}]}
auth = [
    request("21 — Без автентифікації", "GET", {"resource": "auth"}, error_tests(401, "Unauthorized", "Basic Auth") + [
        'pm.test("WWW-Authenticate: Basic", () => pm.expect(pm.response.headers.get("WWW-Authenticate")).to.include("Basic"));',
    ], auth={"type": "noauth"}),
    request("22 — Basic Auth (демонстраційні дані)", "GET", {"resource": "auth"}, status(200, "OK") + [
        "const d = pm.response.json().data;",
        'pm.test("authenticated = true", () => { pm.expect(d.authenticated).to.equal(true); pm.expect(d.user).to.equal("student"); });',
        'pm.test("Cookie входу не створено", () => pm.expect(pm.response.headers.has("Set-Cookie")).to.equal(false));',
    ], auth=basic),
    request("23 — Basic Auth з неправильним паролем", "GET", {"resource": "auth"}, error_tests(401, "Unauthorized", "Basic Auth"), auth=wrong),
]

search = [
    request("24 — q великими літерами", "GET", {"resource": "tasks", "q": "ПЕРЕВІРИТИ"}, list_tests(1, [
        'pm.test("Знайдено «Перевірити GET» без урахування регістру", () => pm.expect(body.data[0].title).to.equal("Перевірити GET"));',
    ])),
    request("25 — q іншим регістром у середині слова", "GET", {"resource": "tasks", "q": "ТестОві"}, list_tests(1, [
        'pm.test("Знайдено id 2", () => pm.expect(body.data[0].id).to.equal(2));',
    ])),
    request("26 — q без збігів", "GET", {"resource": "tasks", "q": "автобус"}, list_tests(0, [
        'pm.test("Порожній масив, не помилка", () => pm.expect(body.data).to.eql([]));',
    ])),
    request("27 — q разом із done=0", "GET", {"resource": "tasks", "q": "и", "done": 0}, list_tests(1, [
        'pm.test("Обидва фільтри: лише невиконане id 1", () => { pm.expect(body.data[0].id).to.equal(1); pm.expect(body.data[0].done).to.equal(false); });',
    ])),
    request("27a — Той самий q без done", "GET", {"resource": "tasks", "q": "и"}, list_tests(2)),
    request("28 — Масив q[]", "GET", {"resource": "tasks", "q_arr": "Перевірити"}, error_tests(400, "Bad Request", "Параметр q")),
    request("29 — q разом з id", "GET", {"resource": "tasks", "id": 1, "q": "Перевірити"}, error_tests(400, "Bad Request", "фільтр q")),
    request("30 — q довший за 40 символів", "GET", {"resource": "tasks", "q": "а" * 41}, error_tests(400, "Bad Request", "40")),
    request("31 — q лише з пробілів = без пошуку", "GET", {"resource": "tasks", "q": "   "}, list_tests(2)),
    request("32 — q з пробілами по краях (trim)", "GET", {"resource": "tasks", "q": "  дані  "}, list_tests(1, [
        'pm.test("Знайдено id 2", () => pm.expect(body.data[0].id).to.equal(2));',
    ])),
]

upload = [
    request("Вручну — Вкладення attachment", "POST", {"resource": "echo"}, status(200, "OK") + [
        "const d = pm.response.json().data;",
        'pm.test("multipart/form-data", () => pm.expect(d.content_type).to.equal("multipart/form-data"));',
        'pm.test("Текстове поле name", () => pm.expect(d.fields.name).to.equal("Студент DEMO"));',
        'pm.test("Метадані файла", () => { pm.expect(d.file.name).to.equal("sample.txt"); pm.expect(d.file.size).to.equal(150); pm.expect(d.file.detected_mime).to.equal("text/plain"); });',
    ], body={"mode": "formdata", "formdata": [
        {"key": "name", "value": "Студент DEMO", "type": "text"},
        {"key": "attachment", "type": "file", "src": "sample.txt"},
    ]}, description="У Postman виберіть src/sample.txt у полі attachment (тип File). Newman бере файл із --working-dir src."),
]

collection = {
    "info": {
        "name": "ЛР 10 — HTTP і Postman (Крайняк М. Ю., 451)",
        "description": "Локальний API «Список завдань». Перед запуском: php reset.php у каталозі src, середовище Lab10 Local. "
                       "Папки 01–04 запускаються Runner/Newman по черзі за одну ітерацію; папка 05 потребує вибору файла.",
        "schema": "https://schema.getpostman.com/json/collection/v2.1.0/collection.json",
    },
    "auth": {"type": "noauth"},
    "item": [
        {"name": "01 — Основний сценарій", "item": main},
        {"name": "02 — Помилкові запити", "item": negative},
        {"name": "03 — Basic Auth", "item": auth},
        {"name": "04 — Пошук q (власне доповнення)", "item": search},
        {"name": "05 — Ручне завантаження файла", "item": upload},
    ],
}
environment = {
    "name": "Lab10 Local",
    "values": [
        {"key": "base_url", "value": "http://127.0.0.1:8080/api.php", "type": "default", "enabled": True},
        {"key": "task_id", "value": "", "type": "default", "enabled": True},
    ],
    "_postman_variable_scope": "environment",
}
OUT.mkdir(exist_ok=True)
(OUT / "lab10.postman_collection.json").write_text(json.dumps(collection, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
(OUT / "lab10.postman_environment.json").write_text(json.dumps(environment, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
count = sum(len(f["item"]) for f in collection["item"])
print(f"Колекція: {count} запитів у {len(collection['item'])} папках; середовище: Lab10 Local")
