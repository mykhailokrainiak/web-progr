// Перевірка ЛР 7 у справжньому браузері (headless Microsoft Edge через Chrome DevTools Protocol).
// Записує журнал мережі (Set-Cookie / Cookie), вміст сховища cookies, document.cookie і знімки сторінок.
// Запуск із каталогу lab-07 (сервер має працювати):  node tools/browser_check.mjs [профіль] [базова адреса]
// Node 22+ (вбудований WebSocket). Ідентифікатор сесії та CSRF-токени в журналі приховано.
import { spawn } from 'node:child_process';
import { mkdirSync, rmSync, writeFileSync, readFileSync, existsSync } from 'node:fs';
import { join, resolve } from 'node:path';
import { tmpdir } from 'node:os';

const EDGE = process.env.EDGE || 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe';
const PROFILE = resolve(process.argv[2] || join(tmpdir(), 'edge-lab07-profile'));   // тимчасовий профіль, перестворюється
const B = process.argv[3] || 'http://127.0.0.1:8070/lab-07';
const SHOTS = resolve('report/shots');
const PORT = 9333;
mkdirSync(SHOTS, { recursive: true });
rmSync(PROFILE, { recursive: true, force: true });

const log = { base: B, steps: [] };
const mask = (s) => String(s ?? '')
    .replace(/LAB07SESSID=[a-z0-9]{8,}/gi, 'LAB07SESSID=…')
    .replace(/csrf=[a-f0-9]{16,}/gi, 'csrf=…');
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

let edge, ws, nextId = 1;
const pending = new Map();
const listeners = [];

async function launch() {
    edge = spawn(EDGE, ['--headless=new', `--remote-debugging-port=${PORT}`, `--user-data-dir=${PROFILE}`,
        '--no-first-run', '--no-default-browser-check', '--disable-extensions', '--disable-sync',
        '--window-size=920,760', 'about:blank'], { stdio: 'ignore' });
    let info;
    for (let i = 0; i < 50 && !info; i++) {
        await sleep(200);
        info = await fetch(`http://127.0.0.1:${PORT}/json/version`).then((r) => r.json()).catch(() => null);
    }
    if (!info) throw new Error('Edge не запустився');
    log.browser = info.Browser;
    ws = new WebSocket(info.webSocketDebuggerUrl);
    await new Promise((r) => ws.addEventListener('open', r, { once: true }));
    ws.addEventListener('message', (ev) => {
        const msg = JSON.parse(ev.data);
        if (msg.id && pending.has(msg.id)) {
            const { ok, fail } = pending.get(msg.id);
            pending.delete(msg.id);
            msg.error ? fail(new Error(JSON.stringify(msg.error))) : ok(msg.result);
        } else if (msg.method) {
            for (const l of [...listeners]) l(msg);
        }
    });
}

function send(method, params = {}, sessionId) {
    const id = nextId++;
    ws.send(JSON.stringify({ id, method, params, ...(sessionId ? { sessionId } : {}) }));
    return new Promise((ok, fail) => {
        const t = setTimeout(() => { pending.delete(id); fail(new Error(`timeout ${method}`)); }, 15000);
        pending.set(id, { ok: (v) => { clearTimeout(t); ok(v); }, fail: (e) => { clearTimeout(t); fail(e); } });
    });
}

function once(method, sessionId, timeout = 10000) {
    return new Promise((ok, fail) => {
        const t = setTimeout(() => fail(new Error(`timeout ${method}`)), timeout);
        const l = (msg) => {
            if (msg.method === method && msg.sessionId === sessionId) {
                clearTimeout(t); listeners.splice(listeners.indexOf(l), 1); ok(msg.params);
            }
        };
        listeners.push(l);
    });
}

/** Вкладка: мережевий журнал зберігає запити до сайту (без style.css і favicon). */
async function openTab(browserContextId) {
    const { targetId } = await send('Target.createTarget', { url: 'about:blank', ...(browserContextId ? { browserContextId } : {}) });
    const { sessionId } = await send('Target.attachToTarget', { targetId, flatten: true });
    const tab = { sessionId, targetId, requests: new Map(), order: [] };
    listeners.push((msg) => {
        if (msg.sessionId !== sessionId) return;
        const p = msg.params;
        const get = (id) => { if (!tab.requests.has(id)) { tab.requests.set(id, { hops: [] }); } return tab.requests.get(id); };
        if (msg.method === 'Network.requestWillBeSent') {
            if (!p.request.url.startsWith(B) || /style\.css|favicon/.test(p.request.url)) return;
            const r = get(p.requestId);
            if (p.redirectResponse && r.hops.length) r.hops.at(-1).status = p.redirectResponse.status;
            r.hops.push({ method: p.request.method, url: p.request.url, postData: p.request.postData });
            if (!tab.order.includes(p.requestId)) tab.order.push(p.requestId);
        } else if (msg.method === 'Network.requestWillBeSentExtraInfo' && tab.requests.has(p.requestId)) {
            const hop = tab.requests.get(p.requestId).hops.find((h) => !h.requestHeaders);
            if (hop) hop.requestHeaders = p.headers;
        } else if (msg.method === 'Network.responseReceivedExtraInfo' && tab.requests.has(p.requestId)) {
            const hop = tab.requests.get(p.requestId).hops.find((h) => !h.responseHeaders);
            if (hop) { hop.responseHeaders = p.headers; hop.status = p.statusCode; }
        }
    });
    await send('Page.enable', {}, sessionId);
    await send('Network.enable', {}, sessionId);
    await send('Runtime.enable', {}, sessionId);
    await send('Emulation.setDeviceMetricsOverride', { width: 900, height: 700, deviceScaleFactor: 1, mobile: false }, sessionId);
    return tab;
}

async function goto(tab, url) {
    const loaded = once('Page.loadEventFired', tab.sessionId);
    await send('Page.navigate', { url }, tab.sessionId);
    await loaded;
}

async function evaluate(tab, expression) {
    const r = await send('Runtime.evaluate', { expression, returnByValue: true }, tab.sessionId);
    return r.result.value;
}

/** Натискання кнопки форми (як користувач): чекаємо завантаження сторінки після 303. */
async function click(tab, selector) {
    const loaded = once('Page.loadEventFired', tab.sessionId);
    const ok = await evaluate(tab, `(() => { const b = document.querySelector(${JSON.stringify(selector)}); if (!b) return false; b.click(); return true; })()`);
    if (!ok) throw new Error(`немає кнопки ${selector}`);
    await loaded;
}

async function shot(tab, name) {
    await send('Page.bringToFront', {}, tab.sessionId);
    await sleep(150);
    try {
        const { data } = await send('Page.captureScreenshot', { format: 'png', captureBeyondViewport: true }, tab.sessionId);
        writeFileSync(join(SHOTS, name), Buffer.from(data, 'base64'));
    } catch (e) {
        console.log(`   [знімок ${name} не створено: ${e.message}]`);
    }
}

async function pageState(tab) {
    return evaluate(tab, `({ url: location.pathname + location.search, theme: document.body ? document.body.className : '',
        count: document.getElementById('favorites-count')?.textContent ?? null,
        visit: document.getElementById('visit-number')?.textContent ?? null,
        previous: document.getElementById('previous-time')?.textContent ?? null,
        flash: document.querySelector('[role=status]')?.textContent ?? null,
        error: document.querySelector('[role=alert]')?.textContent ?? null,
        text: document.body ? document.body.innerText.slice(0, 80) : '',
        favorites: [...document.querySelectorAll('#favorites-list li')].map((li) => li.textContent) })`);
}

async function cookies(tab) {
    const { cookies } = await send('Network.getCookies', { urls: [B + '/'] }, tab.sessionId);
    return cookies.map((c) => ({ name: c.name, value: c.name === 'LAB07SESSID' ? '…' : c.value, domain: c.domain, path: c.path,
        expires: c.session ? 'Session' : new Date(c.expires * 1000).toISOString().replace('.000', ''),
        httpOnly: c.httpOnly, secure: c.secure, sameSite: c.sameSite ?? '', session: c.session }));
}
const sessionValue = async (tab) => (await send('Network.getCookies', { urls: [B + '/'] }, tab.sessionId))
    .cookies.find((c) => c.name === 'LAB07SESSID')?.value ?? null;

/** Запити з моменту mark: метод, адреса, статус, Cookie запиту, Set-Cookie відповіді. */
function network(tab, mark) {
    return tab.order.slice(mark).flatMap((id) => tab.requests.get(id).hops.map((h) => ({
        method: h.method, url: h.url.replace(B, ''), status: h.status,
        postData: h.postData ? mask(h.postData) : undefined,
        cookie: mask(h.requestHeaders?.Cookie ?? h.requestHeaders?.cookie ?? ''),
        setCookie: mask(h.responseHeaders?.['Set-Cookie'] ?? h.responseHeaders?.['set-cookie'] ?? '').split('\n').filter(Boolean),
        location: h.responseHeaders?.Location ?? h.responseHeaders?.location,
    })));
}

async function step(title, tab, fn) {
    const mark = tab.order.length;
    const extra = (await fn()) || {};
    const entry = { title, state: await pageState(tab), network: network(tab, mark), ...extra };
    log.steps.push(entry);
    console.log(`\n== ${title}\n   ${entry.state.url} | ${entry.state.theme} | обраних ${entry.state.count ?? '—'}`
        + (entry.state.visit ? ` | перегляд №${entry.state.visit}, попередній: ${entry.state.previous}` : '')
        + (entry.state.flash ? ` | «${entry.state.flash}»` : '') + (entry.state.error ? ` | помилка «${entry.state.error}»` : '')
        + (entry.state.favorites.length ? `\n   обране: ${entry.state.favorites.join('; ')}` : ''));
    for (const n of entry.network) {
        console.log(`   ${n.method} ${n.url} → ${n.status}${n.location ? ' Location: ' + n.location : ''}`);
        if (n.postData) console.log(`      тіло: ${n.postData}`);
        if (n.cookie) console.log(`      Cookie: ${n.cookie}`);
        for (const s of n.setCookie) console.log(`      Set-Cookie: ${s}`);
    }
    for (const [k, v] of Object.entries(extra)) console.log(`   ${k}: ${typeof v === 'string' ? v : JSON.stringify(v)}`);
    return entry;
}

process.on('unhandledRejection', (e) => { console.error('ПОМИЛКА:', e.message); edge?.kill(); process.exit(1); });

await launch();
let tab = await openTab();

await step('1. Перший GET без cookies', tab, async () => { await goto(tab, `${B}/index.php`); await shot(tab, 'b01_index_first.png'); });
await step('1б. Перший перегляд visits.php', tab, async () => { await goto(tab, `${B}/visits.php`); await shot(tab, 'b02_visits_first.png'); });

await step('2. Збереження темної теми: POST → 303 → GET', tab, async () => {
    await goto(tab, `${B}/preferences.php`);
    await evaluate(tab, `document.getElementById('theme-dark').checked = true`);
    await click(tab, 'button[value=save]');
    await shot(tab, 'b03_index_dark_flash.png');
});
await step('2б. Тема на інших сторінках', tab, async () => { await goto(tab, `${B}/preferences.php`); await shot(tab, 'b04_preferences_dark.png'); });
await step('2в. HttpOnly: cookies у сховищі та document.cookie', tab, async () => ({
    documentCookie: await evaluate(tab, 'document.cookie'),
    cookieStore: await cookies(tab),
}));

await step('3. Другий і третій перегляд visits.php', tab, async () => {
    await sleep(1100); await goto(tab, `${B}/visits.php`);
    await sleep(1100); await goto(tab, `${B}/visits.php`);
    await shot(tab, 'b05_visits_third.png');
});

await step('4. Обране: додати «Кіновечір» і «Автобусна екскурсія»', tab, async () => {
    await goto(tab, `${B}/favorites.php`);
    await click(tab, 'form:has(input[value=cinema]) button');
    await click(tab, 'form:has(input[value=trip]) button');
    await shot(tab, 'b06_favorites.png');
});
await step('4б. Оновлення сторінки після POST (Page.reload)', tab, async () => {
    const loaded = once('Page.loadEventFired', tab.sessionId);
    await send('Page.reload', {}, tab.sessionId); await loaded;
});

const tab2 = await openTab();
await step('5. Друга вкладка того самого браузера', tab2, async () => {
    await goto(tab2, `${B}/favorites.php`);
    await click(tab2, 'form:has(input[value=quiz]) button');
}).then(async () => {
    await step('5б. Перша вкладка після оновлення', tab, async () => { await goto(tab, `${B}/index.php`); await shot(tab, 'b07_index_count.png'); });
});

const { browserContextId } = await send('Target.createBrowserContext');
const priv = await openTab(browserContextId);
await step('6. Окремий приватний контекст', priv, async () => {
    await goto(priv, `${B}/favorites.php`); await shot(priv, 'b08_private_context.png');
    return { sessionDiffers: (await sessionValue(priv)) !== (await sessionValue(tab)) };
});

await step('7. Невідома тема з форми (value змінено на blue)', tab, async () => {
    await goto(tab, `${B}/preferences.php`);
    await evaluate(tab, `const r = document.getElementById('theme-dark'); r.value = 'blue'; r.checked = true`);
    await click(tab, 'button[value=save]');
    await shot(tab, 'b09_preferences_error.png');
    return { themeCookieAfter: (await cookies(tab)).find((c) => c.name === 'club_theme')?.value };
});
await step('7б. Змінений CSRF-токен', tab, async () => {
    await goto(tab, `${B}/favorites.php`);
    await evaluate(tab, `document.querySelectorAll('input[name=csrf]').forEach((i) => i.value = '0'.repeat(64))`);
    await click(tab, 'form:has(input[value=eco]) button');
    await shot(tab, 'b10_csrf_403.png');
});

await step('8. Очистити обране', tab, async () => {
    const before = await sessionValue(tab);
    await goto(tab, `${B}/favorites.php`);
    await click(tab, 'button[value=clear]');
    return { sessionChanged: before !== (await sessionValue(tab)) };
});
await step('9. Завершити сеанс', tab, async () => {
    await goto(tab, `${B}/favorites.php`);
    await click(tab, 'form:has(input[value=cinema]) button');
    const before = await sessionValue(tab);
    await goto(tab, `${B}/index.php`);
    await click(tab, 'form[action="end_session.php"] button');
    await shot(tab, 'b11_session_ended.png');
    return { sessionChanged: before !== (await sessionValue(tab)), cookieStore: await cookies(tab) };
});

// Перезапуск браузера з тим самим профілем: постійні cookies лишаються, сесійна — ні.
await step('10. Перед закриттям браузера: додати «Вікторину»', tab, async () => {
    await goto(tab, `${B}/favorites.php`);
    await click(tab, 'form:has(input[value=quiz]) button');
});
await send('Browser.close').catch(() => {});
await new Promise((r) => edge.once('exit', r));
await launch();
tab = await openTab();
await step('10б. Після повторного відкриття браузера', tab, async () => {
    await goto(tab, `${B}/index.php`); await shot(tab, 'b12_after_restart.png');
    return { cookieStore: await cookies(tab) };
});
await step('10в. visits.php після повторного відкриття', tab, async () => { await goto(tab, `${B}/visits.php`); });

await step('11. Скинути налаштування', tab, async () => {
    await goto(tab, `${B}/preferences.php`);
    await click(tab, 'button[value=reset]');
    await shot(tab, 'b13_reset_light.png');
    return { cookieStore: await cookies(tab) };
});

// Cookies заблоковані для сайту: налаштування профілю «блокувати cookies», новий запуск.
await send('Browser.close').catch(() => {});
await new Promise((r) => edge.once('exit', r));
const prefsFile = join(PROFILE, 'Default', 'Preferences');
const prefs = existsSync(prefsFile) ? JSON.parse(readFileSync(prefsFile, 'utf8')) : {};
prefs.profile = { ...(prefs.profile || {}), default_content_setting_values: { ...(prefs.profile?.default_content_setting_values || {}), cookies: 2 } };
writeFileSync(prefsFile, JSON.stringify(prefs));
await launch();
tab = await openTab();
await step('12. Cookies заблоковані: visits.php двічі', tab, async () => {
    await goto(tab, `${B}/visits.php`); await goto(tab, `${B}/visits.php`);
    return { cookieStore: await cookies(tab) };
});
await step('12б. Cookies заблоковані: POST «Додати» в обране', tab, async () => {
    await goto(tab, `${B}/favorites.php`);
    await click(tab, 'form:has(input[value=eco]) button');
    await shot(tab, 'b14_blocked_403.png');
});

await send('Browser.close').catch(() => {});
writeFileSync('results/browser-log.json', JSON.stringify(log, null, 2));
console.log(`\nБраузер: ${log.browser}; кроків: ${log.steps.length}; журнал: results/browser-log.json`);
process.exit(0);
