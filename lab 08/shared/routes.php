<?php
declare(strict_types=1);

// Варіант 10. Розклад маршрутів автовокзалу.
// Спільна частина XML- та JSON-версій: схема полів, перевірки, CSRF, блокування.
// Формат-залежні функції loadRoutes() і saveRoutes() задає storage.php кожної версії.

session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax',
    'use_strict_mode' => true,
]);
$_SESSION['csrf'] ??= bin2hex(random_bytes(32));

/** Порядок полів запису; однаковий для XML-елементів і ключів JSON. */
const ROUTE_FIELDS = ['id', 'number', 'destination', 'departure', 'platform', 'price'];

/** Підписи полів для форми й таблиці. */
const ROUTE_LABELS = [
    'number' => 'Номер рейсу',
    'destination' => 'Пункт призначення',
    'departure' => 'Час відправлення',
    'platform' => 'Платформа',
    'price' => 'Ціна квитка, грн',
];

const PLATFORM_MIN = 1;
const PLATFORM_MAX = 20;
const PRICE_MIN = 1;
const PRICE_MAX = 5000;

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function readText(string $path): string
{
    if (!is_file($path) || !is_readable($path)) {
        throw new RuntimeException('Файл даних відсутній або недоступний для читання.');
    }
    $text = file_get_contents($path);
    if ($text === false) {
        throw new RuntimeException('Не вдалося прочитати файл даних.');
    }
    if (trim($text) === '') {
        throw new RuntimeException('Файл даних порожній.');
    }
    if (!mb_check_encoding($text, 'UTF-8')) {
        throw new RuntimeException('Файл даних не в кодуванні UTF-8.');
    }
    return $text;
}

function checkCsrf(): void
{
    $token = $_POST['csrf'] ?? null;
    if (!is_string($token) || !hash_equals($_SESSION['csrf'], $token)) {
        throw new RuntimeException('Форма застаріла (CSRF-токен не збігається). Оновіть сторінку.', 403);
    }
}

function isRouteId(mixed $id): bool
{
    return is_string($id) && preg_match('/\A[a-f0-9]{16}\z/', $id) === 1;
}

/**
 * Перевіряє значення одного запису з уже правильними типами.
 * Повертає масив «поле => повідомлення»; порожній масив — запис коректний.
 */
function routeFieldErrors(array $route): array
{
    $errors = [];
    $number = $route['number'] ?? null;
    if (!is_string($number) || preg_match('/\A[0-9A-Za-zА-ЩЬЮЯҐЄІЇа-щьюяґєії-]{1,10}\z/u', $number) !== 1) {
        $errors['number'] = 'Номер рейсу: 1–10 літер, цифр або дефісів.';
    }
    $destination = $route['destination'] ?? null;
    if (!is_string($destination) || trim($destination) !== $destination
        || mb_strlen($destination, 'UTF-8') < 2 || mb_strlen($destination, 'UTF-8') > 100
        || preg_match('/\p{Cc}/u', $destination) === 1) {
        $errors['destination'] = 'Пункт призначення: від 2 до 100 символів, без керівних символів.';
    }
    $departure = $route['departure'] ?? null;
    if (!is_string($departure) || preg_match('/\A([01]\d|2[0-3]):[0-5]\d\z/', $departure) !== 1) {
        $errors['departure'] = 'Час відправлення у форматі ГГ:ХХ (00:00–23:59).';
    }
    $platform = $route['platform'] ?? null;
    if (!is_int($platform) || $platform < PLATFORM_MIN || $platform > PLATFORM_MAX) {
        $errors['platform'] = sprintf('Платформа — ціле число %d–%d.', PLATFORM_MIN, PLATFORM_MAX);
    }
    $price = $route['price'] ?? null;
    if (!is_int($price) || $price < PRICE_MIN || $price > PRICE_MAX) {
        $errors['price'] = sprintf('Ціна — ціле число гривень %d–%d.', PRICE_MIN, PRICE_MAX);
    }
    return $errors;
}

/** Перевірка структури й типів усього списку, прочитаного з файла. */
function validateRoutes(mixed $routes): array
{
    if (!is_array($routes) || !array_is_list($routes)) {
        throw new RuntimeException('Очікується список маршрутів.');
    }
    $ids = [];
    foreach ($routes as $index => $route) {
        $place = 'Запис ' . ($index + 1) . ': ';
        if (!is_array($route) || array_is_list($route)) {
            throw new RuntimeException($place . 'очікується об’єкт із полями ' . implode(', ', ROUTE_FIELDS) . '.');
        }
        foreach (ROUTE_FIELDS as $field) {
            if (!array_key_exists($field, $route)) {
                throw new RuntimeException($place . "відсутнє поле $field.");
            }
        }
        if (count($route) !== count(ROUTE_FIELDS)) {
            throw new RuntimeException($place . 'містить зайві поля.');
        }
        if (!isRouteId($route['id'])) {
            throw new RuntimeException($place . 'id має бути рядком із 16 шістнадцяткових символів.');
        }
        if (isset($ids[$route['id']])) {
            throw new RuntimeException($place . 'id повторюється.');
        }
        $ids[$route['id']] = true;
        $errors = routeFieldErrors($route);
        if ($errors !== []) {
            throw new RuntimeException($place . reset($errors));
        }
    }
    return $routes;
}

/** Ціле число з рядка без знаку й початкових нулів; інакше null. */
function strictInt(string $raw): ?int
{
    if (!ctype_digit($raw)) {
        return null;
    }
    $value = filter_var($raw, FILTER_VALIDATE_INT);
    return $value === false ? null : $value;
}

/**
 * Читає поля форми з POST.
 * Повертає [запис із правильними типами, помилки полів, введені рядки для повторного показу].
 */
function routeFromInput(array $input): array
{
    $raw = [];
    $errors = [];
    foreach (array_keys(ROUTE_LABELS) as $field) {
        $value = $input[$field] ?? '';
        if (!is_string($value)) {
            $raw[$field] = '';
            $errors[$field] = 'Поле має бути текстовим значенням.';
            continue;
        }
        $raw[$field] = trim($value);
        if ($raw[$field] === '') {
            $errors[$field] = 'Заповніть поле.';
        }
    }
    $route = [
        'number' => $raw['number'],
        'destination' => $raw['destination'],
        'departure' => $raw['departure'],
        'platform' => strictInt($raw['platform']),
        'price' => strictInt($raw['price']),
    ];
    // Повідомлення про порожнє поле чи тип важливіше за перевірку значення.
    $errors += routeFieldErrors($route);
    return [$route, $errors, $raw];
}

function newRouteId(array $routes): string
{
    $used = array_column($routes, 'id');
    do {
        $id = bin2hex(random_bytes(8));
    } while (in_array($id, $used, true));
    return $id;
}

function findRouteIndex(array $routes, string $id): ?int
{
    foreach ($routes as $index => $route) {
        if ($route['id'] === $id) {
            return $index;
        }
    }
    return null;
}

/**
 * Виконує цикл «прочитати — змінити — записати» під одним блокуванням,
 * щоб два одночасні запити не перезаписали зміни один одного.
 */
function withLock(string $lockFile, callable $action): mixed
{
    $handle = fopen($lockFile, 'c');
    if ($handle === false) {
        throw new RuntimeException('Не вдалося відкрити файл блокування.');
    }
    try {
        if (!flock($handle, LOCK_EX)) {
            throw new RuntimeException('Не вдалося заблокувати дані.');
        }
        return $action();
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}

/** Одноразове повідомлення, яке показується після перенаправлення. */
function flash(?string $message = null): ?string
{
    if ($message !== null) {
        $_SESSION['flash'] = $message;
        return null;
    }
    $stored = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return is_string($stored) ? $stored : null;
}
