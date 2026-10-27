<?php
declare(strict_types=1);
// Власне доповнення (крок 8.3): імпорт каталогу з ЛР 8 (JSON або XML) до таблиці bus_routes.
// Запуск із каталогу src:  php routes-import.php json   або   php routes-import.php xml
// 1) Файл перевіряється за правилами ЛР 8 (структура, типи, межі, унікальність id).
// 2) Записи додаються одним підготовленим INSERT у транзакції.
// Політика дублікатів: якщо хоча б один рейс (номер + час) уже є в таблиці, відкочується ВСЯ група,
// тож повторний імпорт того самого файла нічого не додає.
require __DIR__ . '/common.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(405);
    header('Content-Type: text/plain; charset=UTF-8');
    exit('Запустіть php routes-import.php у терміналі.');
}

const LAB08_FIELDS = ['id', 'number', 'destination', 'departure', 'platform', 'price'];

/** Правила ЛР 8: platform і price — цілі числа (у XML — рядки цифр без початкових нулів). */
function validateLab08(array $routes, bool $fromXml): array
{
    if (!array_is_list($routes)) {
        throw new UnexpectedValueException('Очікується список маршрутів.');
    }
    $ids = [];
    foreach ($routes as $index => $route) {
        $place = 'Запис ' . ($index + 1) . ': ';
        if (!is_array($route) || count($route) !== count(LAB08_FIELDS)
            || array_diff(LAB08_FIELDS, array_keys($route)) !== []) {
            throw new UnexpectedValueException($place . 'потрібні рівно поля ' . implode(', ', LAB08_FIELDS) . '.');
        }
        if ($fromXml) {
            foreach (['platform', 'price'] as $numeric) {
                if (is_string($route[$numeric]) && preg_match('/\A[1-9][0-9]*\z/', $route[$numeric])) {
                    $route[$numeric] = (int) $route[$numeric];
                }
            }
        }
        $ok = is_string($route['id']) && preg_match('/\A[a-f0-9]{16}\z/', $route['id']) && !isset($ids[$route['id']])
            && is_string($route['number']) && preg_match('/\A[0-9A-Za-zА-ЩЬЮЯҐЄІЇа-щьюяґєії-]{1,10}\z/u', $route['number'])
            && is_string($route['destination']) && trim($route['destination']) === $route['destination']
            && mb_strlen($route['destination'], 'UTF-8') >= 2 && mb_strlen($route['destination'], 'UTF-8') <= 100
            && !preg_match('/\p{Cc}/u', $route['destination'])
            && is_string($route['departure']) && preg_match('/\A([01]\d|2[0-3]):[0-5]\d\z/', $route['departure'])
            && is_int($route['platform']) && $route['platform'] >= 1 && $route['platform'] <= 20
            && is_int($route['price']) && $route['price'] >= 1 && $route['price'] <= 5000;
        if (!$ok) {
            throw new UnexpectedValueException($place . 'значення не відповідають правилам ЛР 8.');
        }
        $ids[$route['id']] = true;
        $routes[$index] = $route;
    }
    return $routes;
}

function readLab08(string $format): array
{
    $path = dirname(__DIR__) . '/data/routes-lab08.' . $format;
    $text = is_file($path) ? file_get_contents($path) : false;
    if ($text === false || trim($text) === '' || !mb_check_encoding($text, 'UTF-8')) {
        throw new UnexpectedValueException("Файл $path відсутній, порожній або не в UTF-8.");
    }
    if ($format === 'json') {
        if (!is_array(json_decode($text, false, 512, JSON_THROW_ON_ERROR))) {
            throw new UnexpectedValueException('Кореневе значення JSON має бути масивом.');
        }
        return validateLab08(json_decode($text, true, 512, JSON_THROW_ON_ERROR), false);
    }
    $previous = libxml_use_internal_errors(true);
    try {
        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = false;
        if (!$dom->loadXML($text, LIBXML_NONET) || $dom->doctype !== null
            || $dom->documentElement->tagName !== 'routes') {
            throw new UnexpectedValueException('Некоректний XML або DTD/інший корінь.');
        }
        $routes = [];
        foreach ($dom->documentElement->childNodes as $node) {
            if ($node instanceof DOMText && trim($node->textContent) === '') {
                continue;
            }
            if (!$node instanceof DOMElement || $node->tagName !== 'route') {
                throw new UnexpectedValueException('У routes дозволено лише елементи route.');
            }
            $fields = [];
            foreach ($node->childNodes as $field) {
                if ($field instanceof DOMText && trim($field->textContent) === '') {
                    continue;
                }
                if (!$field instanceof DOMElement || isset($fields[$field->tagName])) {
                    throw new UnexpectedValueException('Повторне або некоректне поле route.');
                }
                $fields[$field->tagName] = $field->textContent;
            }
            $routes[] = $fields;
        }
        return validateLab08($routes, true);
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
    }
}

$format = $argv[1] ?? 'json';
if (!in_array($format, ['json', 'xml'], true)) {
    fwrite(STDERR, "Формат: json або xml.\n");
    exit(2);
}
$db = null;
$inTransaction = false;
$exitCode = 0;
try {
    $routes = readLab08($format);
    $db = connectDatabase();
    mysqli_begin_transaction($db);
    $inTransaction = true;
    // Один підготовлений вираз; змінні прив’язано один раз, значення змінюються в циклі.
    $stmt = mysqli_prepare($db, 'INSERT INTO bus_routes (route_number, destination, departure_time, platform, price)
                                 VALUES (?, ?, ?, ?, ?)');
    $number = $destination = $time = $price = '';
    $platform = 0;
    mysqli_stmt_bind_param($stmt, 'sssis', $number, $destination, $time, $platform, $price);
    foreach ($routes as $route) {
        $number = $route['number'];
        $destination = $route['destination'];
        $time = $route['departure'] . ':00';
        $platform = $route['platform'];
        $price = $route['price'] . '.00';
        mysqli_stmt_execute($stmt);
    }
    mysqli_stmt_close($stmt);
    mysqli_commit($db);
    $inTransaction = false;
    printf("Імпортовано з routes-lab08.%s: %d рейс(и); транзакцію підтверджено.\n", $format, count($routes));
} catch (JsonException) {
    fwrite(STDERR, "Некоректний JSON; нічого не імпортовано.\n");
    $exitCode = 1;
} catch (UnexpectedValueException $exception) {
    fwrite(STDERR, $exception->getMessage() . " Нічого не імпортовано.\n");
    $exitCode = 1;
} catch (Throwable $exception) {
    $duplicate = $exception instanceof mysqli_sql_exception && $exception->getCode() === 1062;
    error_log('lab11 import: code=' . $exception->getCode());
    fwrite(STDERR, $duplicate
        ? "Рейс із таким номером і часом уже є в таблиці; транзакцію відкочено, нічого не імпортовано.\n"
        : "Помилка БД; транзакцію відкочено, нічого не імпортовано.\n");
    $exitCode = 1;
} finally {
    if ($inTransaction) {
        try {
            mysqli_rollback($db);
        } catch (Throwable) {
        }
    }
    if ($db !== null) {
        mysqli_close($db);
    }
}
exit($exitCode);
