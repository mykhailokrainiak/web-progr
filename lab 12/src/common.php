<?php
declare(strict_types=1);

// Спільний код усіх сторінок: підключення PDO, сесія, CSRF, меню та шаблон.

session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax',
    'use_strict_mode' => true,
]);
$_SESSION['csrf'] ??= bin2hex(random_bytes(32));

/** Єдине з’єднання PDO на запит. Помилки — винятками PDOException. */
function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $config = require __DIR__ . '/config.php';
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $config['host'], $config['port'], $config['name']);
        $pdo = new PDO($dsn, $config['user'], $config['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false, // Справжні підготовлені запити на сервері.
        ]);
    }
    return $pdo;
}

/** Деталі помилки — лише в журнал сервера; користувачу — загальне повідомлення. */
function dbError(PDOException $exception): string
{
    error_log('PDO: ' . $exception->getMessage());
    http_response_code(500);
    return 'Помилка роботи з базою даних. Перевірте, що сервер MySQL запущено, і спробуйте пізніше.';
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function csrfField(): string
{
    return '<input type="hidden" name="csrf" value="' . e($_SESSION['csrf']) . '">';
}

function csrfValid(): bool
{
    $token = $_POST['csrf'] ?? null;
    return is_string($token) && hash_equals($_SESSION['csrf'], $token);
}

/** Рядкове поле POST після trim(); масив чи відсутнє поле дає ''. */
function postString(string $name): string
{
    $value = $_POST[$name] ?? '';
    return is_string($value) ? trim($value) : '';
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

/** Поточний користувач із БД або null для гостя. */
function currentUser(): ?array
{
    static $user = false;
    if ($user !== false) {
        return $user;
    }
    $user = null;
    $id = $_SESSION['user_id'] ?? null;
    if (is_int($id)) {
        $stmt = db()->prepare('SELECT id, login, full_name, email FROM users WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $user = $stmt->fetch() ?: null;
        if ($user === null) {
            unset($_SESSION['user_id']); // Користувача видалено з БД.
        }
    }
    return $user;
}

/** Сторінки лише для авторизованих: гостя перенаправляємо на вхід. */
function requireLogin(): array
{
    $user = currentUser();
    if ($user === null) {
        flash('Увійдіть, щоб переглянути цю сторінку.');
        redirect('login.php');
    }
    return $user;
}

function pageStart(string $title): void
{
    try {
        $user = currentUser();
    } catch (PDOException) {
        $user = null; // Помилку БД показує сама сторінка; меню — як для гостя.
    }
    $message = flash();
    header('Content-Type: text/html; charset=UTF-8');
    ?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title) ?> — Автовокзал «Навчальний»</title>
    <style>
        body { font-family: system-ui, sans-serif; margin: 0; color: #222; }
        header { display: flex; flex-wrap: wrap; align-items: center; gap: 1rem; padding: 0.6rem 1.5rem; background: #1f4e79; color: #fff; }
        header a { color: #fff; }
        header nav { display: flex; gap: 1rem; flex: 1; }
        header .account { display: flex; align-items: center; gap: 0.6rem; }
        header form { margin: 0; }
        header button, header .button { font: inherit; padding: 0.25rem 0.8rem; border: 1px solid #fff; border-radius: 4px; background: transparent; color: #fff; cursor: pointer; text-decoration: none; }
        main { padding: 1rem 1.5rem; max-width: 62rem; }
        table { border-collapse: collapse; margin-top: 0.8rem; }
        th, td { border: 1px solid #999; padding: 0.3rem 0.6rem; text-align: left; }
        td.num { text-align: right; }
        dl { display: grid; grid-template-columns: max-content 1fr; gap: 0.3rem 1rem; }
        dt { font-weight: bold; }
        .ok { color: #1a6b1a; font-weight: bold; }
        .error { color: #b00020; font-weight: bold; }
        .field { margin-bottom: 0.8rem; }
        .field label { display: block; font-weight: bold; }
        .field small { display: block; color: #555; }
        .field .error { display: block; font-weight: normal; }
        input[aria-invalid="true"] { border: 2px solid #b00020; }
    </style>
</head>
<body>
<header>
    <nav aria-label="Головне меню">
        <a href="index.php">Розклад</a>
        <?php if ($user !== null): ?>
            <a href="users.php">Користувачі</a>
        <?php endif; ?>
        <a href="db_check.php">Стан БД</a>
    </nav>
    <div class="account">
        <?php if ($user !== null): ?>
            <span>Вітаємо, <?= e($user['full_name']) ?>!</span>
            <form method="post" action="logout.php">
                <?= csrfField() ?>
                <button type="submit">Вийти</button>
            </form>
        <?php else: ?>
            <a class="button" href="login.php">Увійти</a>
            <a href="register.php">Реєстрація</a>
        <?php endif; ?>
    </div>
</header>
<main>
<h1><?= e($title) ?></h1>
<?php if ($message !== null): ?>
    <p class="ok" role="status"><?= e($message) ?></p>
<?php endif; ?>
    <?php
}

function pageEnd(): void
{
    ?>
</main>
</body>
</html>
    <?php
}

/** Поле форми з підписом, підказкою та помилкою. */
function formField(string $name, string $label, string $type, string $value, ?string $error, string $hint, string $extra = ''): void
{
    $describedBy = $name . '-hint' . ($error !== null ? ' ' . $name . '-error' : '');
    ?>
    <div class="field">
        <label for="<?= e($name) ?>"><?= e($label) ?></label>
        <input id="<?= e($name) ?>" name="<?= e($name) ?>" type="<?= e($type) ?>" <?= $extra ?>
               value="<?= e($value) ?>" aria-describedby="<?= e($describedBy) ?>"<?= $error !== null ? ' aria-invalid="true"' : '' ?>>
        <small id="<?= e($name) ?>-hint"><?= e($hint) ?></small>
        <?php if ($error !== null): ?>
            <span class="error" id="<?= e($name) ?>-error"><?= e($error) ?></span>
        <?php endif; ?>
    </div>
    <?php
}

function formatDuration(int $minutes): string
{
    return sprintf('%d год %02d хв', intdiv($minutes, 60), $minutes % 60);
}
