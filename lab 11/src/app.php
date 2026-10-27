<?php
declare(strict_types=1);

// Власне доповнення: спільна частина вебформ (сесія, CSRF, шаблон, перенаправлення).
// Підключення до БД і validateProduct() — з готового common.php.
require __DIR__ . '/common.php';

// Cookie сесії — лише для каталогу цього проєкту (наприклад, /lab-11/src/).
$cookiePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/') . '/';
session_start([
    'cookie_path' => $cookiePath,
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax',
    'use_strict_mode' => true,
]);
$_SESSION['csrf'] ??= bin2hex(random_bytes(32));

const MYSQL_DUPLICATE_KEY = 1062;

function csrfField(): string
{
    return '<input type="hidden" name="csrf" value="' . e($_SESSION['csrf']) . '">';
}

/** Перевірка до будь-якого INSERT, UPDATE чи DELETE. */
function requireCsrf(): void
{
    $token = $_POST['csrf'] ?? null;
    if (!is_string($token) || !hash_equals($_SESSION['csrf'], $token)) {
        throw new DomainException('Форма застаріла (CSRF-токен не збігається). Оновіть сторінку.', 403);
    }
}

/** id з GET/POST: рядок із додатним цілим числом. */
function idFrom(array $source): ?int
{
    $raw = $source['id'] ?? null;
    if (!is_string($raw) || !preg_match('/\A[1-9][0-9]{0,9}\z/', $raw)) {
        return null;
    }
    $id = filter_var($raw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 4294967295]]);
    return $id === false ? null : $id;
}

function redirect(string $location): never
{
    header('Location: ' . $location, true, 303);
    exit;
}

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

/** Збій БД: код і контекст — у журнал (без пароля та введених даних), користувачу — нейтральний текст. */
function dbFailure(Throwable $exception, string $context): string
{
    error_log('lab11 ' . $context . ': code=' . $exception->getCode());
    http_response_code(500);
    return 'База даних недоступна або сталася помилка. Зміни не виконано.';
}

function isDuplicate(Throwable $exception): bool
{
    return $exception instanceof mysqli_sql_exception && $exception->getCode() === MYSQL_DUPLICATE_KEY;
}

function closeDb(?mysqli $db): void
{
    if ($db !== null) {
        mysqli_close($db);
    }
}

function pageStart(string $title): void
{
    $message = flash();
    header('Content-Type: text/html; charset=UTF-8');
    ?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title) ?> — ЛР 11</title>
    <style>
        body { max-width: 1100px; margin: 1.5rem auto; padding: 0 1rem; font-family: sans-serif; line-height: 1.5; }
        nav { display: flex; gap: 1.2rem; padding-bottom: .5rem; border-bottom: 1px solid #94a3b8; }
        table { width: 100%; border-collapse: collapse; margin-top: .8rem; }
        th, td { padding: .4rem .5rem; border: 1px solid #94a3b8; text-align: left; vertical-align: top; }
        th { background: #e2e8f0; }
        td.num { text-align: right; white-space: nowrap; }
        .ok { color: #166534; font-weight: bold; }
        .error { color: #b00020; font-weight: bold; }
        .field { margin-bottom: .8rem; }
        .field label { display: block; font-weight: bold; }
        .field small { display: block; color: #475569; }
        .field .error { display: block; font-weight: normal; }
        input[aria-invalid="true"], textarea[aria-invalid="true"] { border: 2px solid #b00020; }
        .danger { background: #b00020; color: #fff; border: 0; padding: .4rem 1rem; cursor: pointer; }
        dl { display: grid; grid-template-columns: max-content 1fr; gap: .2rem 1rem; }
        dt { font-weight: bold; }
    </style>
</head>
<body>
<nav aria-label="Розділи">
    <a href="index.php">Готовий каталог (читання)</a>
    <a href="products.php">Товари: CRUD</a>
    <a href="routes.php">Розклад автовокзалу (варіант 10)</a>
</nav>
<h1><?= e($title) ?></h1>
<?php if ($message !== null): ?>
    <p class="ok" role="status"><?= e($message) ?></p>
<?php endif; ?>
    <?php
}

function pageEnd(): void
{
    echo "</body>\n</html>\n";
}

function alertBox(?string $error): void
{
    if ($error !== null) {
        echo '<p class="error" role="alert">' . e($error) . "</p>\n";
    }
}

/** Один товар за id або null. */
function findProductById(mysqli $db, int $id): ?array
{
    $stmt = mysqli_prepare($db,
        'SELECT id, product_code, product_name, product_desc, price, created_at FROM products WHERE id = ?');
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

/** Рядкові значення POST для повторного показу у формі (масив чи відсутнє поле → ''). */
function postedStrings(array $keys): array
{
    $values = [];
    foreach ($keys as $key) {
        $raw = $_POST[$key] ?? '';
        $values[$key] = is_string($raw) ? $raw : '';
    }
    return $values;
}
