<?php
declare(strict_types=1);

// JSON-версія сховища: routes.json — масив об’єктів маршрутів.
require __DIR__ . '/../shared/routes.php';

const STORAGE_FORMAT = 'JSON';
const STORAGE_FILE = __DIR__ . '/routes.json';
const STORAGE_LOCK = __DIR__ . '/routes.lock';

function loadRoutes(): array
{
    $json = readText(STORAGE_FILE);
    try {
        // Спочатку відрізняємо масив [] від об’єкта {} — у вигляді масивів PHP вони однакові.
        if (!is_array(json_decode($json, false, 512, JSON_THROW_ON_ERROR))) {
            throw new RuntimeException('Кореневе значення JSON має бути масивом.');
        }
        return validateRoutes(json_decode($json, true, 512, JSON_THROW_ON_ERROR));
    } catch (JsonException) {
        throw new RuntimeException('Некоректний синтаксис JSON.');
    }
}

/** Зберігає весь масив; повертає кількість записаних байтів. */
function saveRoutes(array $routes): int
{
    if (!is_file(STORAGE_FILE) || !is_writable(STORAGE_FILE)) {
        throw new RuntimeException('Файл даних недоступний для запису. Зміни не збережено.');
    }
    try {
        $json = json_encode(validateRoutes(array_values($routes)),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        throw new RuntimeException('Не вдалося закодувати JSON. Зміни не збережено.');
    }
    $content = $json . "\n";
    $bytes = file_put_contents(STORAGE_FILE, $content, LOCK_EX);
    if ($bytes === false || $bytes !== strlen($content)) {
        throw new RuntimeException('Не вдалося повністю записати JSON. Зміни не збережено.');
    }
    return $bytes;
}
