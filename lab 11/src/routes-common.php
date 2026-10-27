<?php
declare(strict_types=1);
// Власне доповнення (крок 8): правила полів таблиці bus_routes і запити до неї (процедурний MySQLi).

const ROUTE_FIELDS = ['route_number', 'destination', 'departure_time', 'platform', 'price'];
const ROUTE_LABELS = [
    'route_number' => 'Номер рейсу',
    'destination' => 'Пункт призначення',
    'departure_time' => 'Час відправлення',
    'platform' => 'Платформа',
    'price' => 'Ціна квитка, грн',
];
const ROUTE_NUMBER_PATTERN = '/\A[0-9A-Za-zА-ЩЬЮЯҐЄІЇа-щьюяґєії-]{1,10}\z/u';

/**
 * Перевіряє поля форми. Повертає [нормалізовані значення, помилки полів].
 * Значення готові для bind_param: усі — рядки, platform — ціле число.
 */
function validateRoute(array $input): array
{
    $values = [];
    $errors = [];
    foreach (ROUTE_FIELDS as $field) {
        $raw = $input[$field] ?? null;
        if (!is_string($raw) || !mb_check_encoding($raw, 'UTF-8')) {
            $errors[$field] = 'Поле має бути текстовим значенням UTF-8.';
            $values[$field] = '';
            continue;
        }
        $values[$field] = trim($raw);
        if ($values[$field] === '') {
            $errors[$field] = 'Заповніть поле.';
        }
    }
    if (!isset($errors['route_number']) && !preg_match(ROUTE_NUMBER_PATTERN, $values['route_number'])) {
        $errors['route_number'] = 'Номер рейсу: 1–10 літер, цифр або дефісів.';
    }
    $length = mb_strlen($values['destination'], 'UTF-8');
    if (!isset($errors['destination']) && ($length < 2 || $length > 100 || preg_match('/\p{Cc}/u', $values['destination']))) {
        $errors['destination'] = 'Пункт призначення: 2–100 символів без керівних символів.';
    }
    if (!isset($errors['departure_time'])) {
        if (preg_match('/\A([01][0-9]|2[0-3]):[0-5][0-9]\z/', $values['departure_time'])) {
            $values['departure_time'] .= ':00';
        } else {
            $errors['departure_time'] = 'Час у форматі ГГ:ХХ, 00:00–23:59.';
        }
    }
    if (!isset($errors['platform'])) {
        if (preg_match('/\A[1-9][0-9]?\z/', $values['platform']) && (int) $values['platform'] <= 20) {
            $values['platform'] = (int) $values['platform'];
        } else {
            $errors['platform'] = 'Платформа — ціле число 1–20.';
        }
    }
    if (!isset($errors['price'])) {
        // Ціну залишаємо десятковим рядком (тип s), межі перевіряємо в копійках.
        if (preg_match('/\A(0|[1-9][0-9]{0,3})(?:\.([0-9]{1,2}))?\z/', $values['price'], $m)) {
            $fraction = str_pad($m[2] ?? '', 2, '0');
            $cents = (int) $m[1] * 100 + (int) $fraction;
            if ($cents >= 100 && $cents <= 500000) {
                $values['price'] = $m[1] . '.' . $fraction;
            } else {
                $errors['price'] = 'Ціна: від 1.00 до 5000.00 грн.';
            }
        } else {
            $errors['price'] = 'Ціна: число з крапкою, до двох дробових цифр (наприклад, 180 або 85.50).';
        }
    }
    return [$values, $errors];
}

/** Список рейсів або точний пошук за номером рейсу. */
function findRoutes(mysqli $db, string $number): array
{
    $sql = 'SELECT id, route_number, destination, departure_time, platform, price FROM bus_routes';
    if ($number === '') {
        $result = mysqli_query($db, $sql . ' ORDER BY departure_time, route_number LIMIT 200');
    } else {
        $stmt = mysqli_prepare($db, $sql . ' WHERE route_number = ? ORDER BY departure_time');
        mysqli_stmt_bind_param($stmt, 's', $number);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        mysqli_stmt_close($stmt);
    }
    try {
        return mysqli_fetch_all($result, MYSQLI_ASSOC);
    } finally {
        mysqli_free_result($result);
    }
}

function findRouteById(mysqli $db, int $id): ?array
{
    $stmt = mysqli_prepare($db,
        'SELECT id, route_number, destination, departure_time, platform, price, created_at FROM bus_routes WHERE id = ?');
    try {
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        mysqli_free_result($result);
        return $row ?: null;
    } finally {
        mysqli_stmt_close($stmt);
    }
}

/** HH:MM:SS → HH:MM для показу та форми. */
function shortTime(string $time): string
{
    return substr($time, 0, 5);
}
