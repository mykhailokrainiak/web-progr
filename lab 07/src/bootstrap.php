<?php
// ===== Готова заготовка з методички (крок 1), без змін =====
const COOKIE_PATH = '/lab-07/';
const COOKIE_SECURE = false; // Локальний HTTP; для HTTPS — true.

ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
session_name('LAB07SESSID');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => COOKIE_PATH,
    'secure' => COOKIE_SECURE,
    'httponly' => true,
    'samesite' => 'Lax',
]);
if (!session_start()) {
    http_response_code(500);
    exit('Не вдалося відкрити сесію.');
}
if (!isset($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function cookieOptions(int $expires): array
{
    return [
        'expires' => $expires,
        'path' => COOKIE_PATH,
        'secure' => COOKIE_SECURE,
        'httponly' => true,
        'samesite' => 'Lax',
    ];
}

function requireValidPost(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        header('Allow: POST');
        http_response_code(405);
        exit('Потрібен POST-запит.');
    }
    $token = $_POST['csrf'] ?? null;
    if (!is_string($token) || !hash_equals($_SESSION['csrf'], $token)) {
        http_response_code(403);
        exit('Недійсний токен форми.');
    }
}

// ===== Власні доповнення =====

const CLUB_NAME = 'Студентський клуб «Політ»';
const THEMES = ['light' => 'Світла', 'dark' => 'Темна'];
const THEME_COOKIE = 'club_theme';
const THEME_TTL = 30 * 24 * 60 * 60;   // 30 днів

date_default_timezone_set('Europe/Kyiv');

/** Тема з cookie: лише значення з переліку THEMES, інакше light (масив, unknown, розмітка). */
function currentTheme(): string
{
    $rawTheme = $_COOKIE[THEME_COOKIE] ?? null;
    return is_string($rawTheme) && array_key_exists($rawTheme, THEMES) ? $rawTheme : 'light';
}

/** Рядкове поле POST або null, якщо поля немає чи передано масив. */
function postString(string $name): ?string
{
    $value = $_POST[$name] ?? null;
    return is_string($value) ? $value : null;
}

/** Розширення (крок 5): одноразове повідомлення в сесії, показується після перенаправлення. */
function setFlash(string $message): void
{
    $_SESSION['flash'] = $message;
}

function takeFlash(): ?string
{
    $message = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return is_string($message) ? $message : null;
}

/** 303 на GET-сторінку після успішного POST; до будь-якого виведення. */
function redirect(string $location): never
{
    header('Location: ' . $location, true, 303);
    exit;
}

/** Обрані заходи сесії (масив ідентифікаторів); некоректний стан замінюється порожнім масивом. */
function favorites(): array
{
    if (!isset($_SESSION['favorites']) || !is_array($_SESSION['favorites'])) {
        $_SESSION['favorites'] = [];
    }
    return $_SESSION['favorites'];
}

function csrfField(): string
{
    return '<input type="hidden" name="csrf" value="' . e($_SESSION['csrf']) . '">';
}

/** Початок сторінки: тема — CSS-клас лише з перевіреного значення, меню, одноразове повідомлення. */
function pageStart(string $title, string $active): void
{
    $menu = [
        'index.php' => 'Головна',
        'preferences.php' => 'Налаштування',
        'visits.php' => 'Відвідування',
        'favorites.php' => 'Обране',
    ];
    $flash = takeFlash();
    ?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> — <?= e(CLUB_NAME) ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="theme-<?= currentTheme() ?>">
<header>
    <p class="club"><?= e(CLUB_NAME) ?></p>
    <nav aria-label="Головне меню">
        <ul>
<?php foreach ($menu as $href => $label): ?>
            <li><a href="<?= $href ?>"<?= $href === $active ? ' aria-current="page"' : '' ?>><?= e($label) ?></a></li>
<?php endforeach; ?>
        </ul>
    </nav>
</header>
<main>
    <h1><?= e($title) ?></h1>
<?php if ($flash !== null): ?>
    <p class="flash" role="status"><?= e($flash) ?></p>
<?php endif;
}

function pageEnd(): void
{
    ?>
</main>
<footer><p>Тема: <?= e(THEMES[currentTheme()]) ?> · ЛР 7, Крайняк М. Ю., 451 група</p></footer>
</body>
</html>
<?php
}

/** Сторінка відмови (400 і т. п.) з повідомленням; стан при цьому не змінюється. */
function errorMessage(string $message): string
{
    return '<p class="error" role="alert">' . e($message) . '</p>';
}
