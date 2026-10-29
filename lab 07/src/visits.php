<?php
require __DIR__ . '/bootstrap.php';

const VISITS_COOKIE = 'club_visits';
const LAST_VISIT_COOKIE = 'club_last_visit';
const VISITS_MAX = 999999;
const VISITS_TTL = 7 * 24 * 60 * 60;   // сім днів

/** Ціле з cookie: лише рядок десяткових цифр у межах [min, max], інакше null (масив, -1, 1.5, завелике). */
function cookieInt(string $name, int $min, int $max): ?int
{
    $raw = $_COOKIE[$name] ?? null;
    if (!is_string($raw) || !ctype_digit($raw)) {
        return null;
    }
    $value = filter_var($raw, FILTER_VALIDATE_INT, ['options' => ['min_range' => $min, 'max_range' => $max]]);
    return $value === false ? null : $value;   // 0 — допустиме значення, false — невдала перевірка
}

// Рахуємо лише GET-запити до цієї сторінки.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    header('Allow: GET');
    http_response_code(405);
    exit('Сторінка відвідувань приймає лише GET.');
}

// 1) Спочатку перевірені старі значення.
$now = time();
$previousCount = cookieInt(VISITS_COOKIE, 0, VISITS_MAX);
$previousTime = cookieInt(LAST_VISIT_COOKIE, 1, $now);

// 2) Новий номер перегляду (верхня межа 999999) і запис поточного часу.
$isNew = $previousCount === null;
$count = $isNew ? 1 : min($previousCount + 1, VISITS_MAX);
$expires = $now + VISITS_TTL;
$countSaved = setcookie(VISITS_COOKIE, (string) $count, cookieOptions($expires));
$timeSaved = setcookie(LAST_VISIT_COOKIE, (string) $now, cookieOptions($expires));

pageStart('Історія відвідувань', 'visits.php');
if (!$countSaved || !$timeSaved) {
    echo errorMessage('Не вдалося підготувати заголовки cookies: історію не буде збережено.'), "\n";
}
?>
<?php if ($isNew): ?>
    <p class="welcome">Ласкаво просимо!</p>
<?php endif; ?>
    <dl class="summary">
        <dt>Номер поточного перегляду</dt>
        <dd id="visit-number"><?= $count ?></dd>
        <dt>Попередній перегляд</dt>
        <dd id="previous-time"><?= $previousTime === null ? 'Попередній час недоступний' : date('d.m.Y H:i:s', $previousTime) ?></dd>
    </dl>
    <p class="note">Лічильник і час зберігаються в cookies <code>club_visits</code> і <code>club_last_visit</code> на сім днів.
        Це стан лише цього браузера, а не кількість відвідувачів сайту: значення можна видалити або змінити.</p>
<?php
pageEnd();
