<?php
declare(strict_types=1);
// Крок 2: окремий обробник. Перевіряє метод, типи й непорожність полів;
// облікові дані ще не перевіряються. Пароль ніде не виводиться.
$errors = [];
$login = null;

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $rawLogin = $_POST['login'] ?? null;
    $password = $_POST['password'] ?? null;
    if (!is_string($rawLogin) || !is_string($password)) {
        $errors[] = 'Логін і пароль мають бути рядками.';
    } elseif (trim($rawLogin) === '' || $password === '') {
        $errors[] = 'Заповніть логін і пароль.';
    } else {
        $login = trim($rawLogin);
    }
    if ($errors !== []) {
        http_response_code(422);
    }
}

function escapeHtml(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Лабораторна робота 5 — обробник</title>
</head>
<body>
    <h1>Обробник форми входу</h1>
<?php if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST'): ?>
    <p>Цю сторінку відкрито без надсилання форми. <a href="index.php">Перейдіть до форми входу</a>.</p>
<?php elseif ($errors !== []): ?>
    <div role="alert">
        <ul>
<?php foreach ($errors as $error): ?>
            <li><?= escapeHtml($error) ?></li>
<?php endforeach; ?>
        </ul>
    </div>
    <p><a href="index.php">Повернутися до форми</a></p>
<?php else: ?>
    <p role="status">Дані отримано для перевірки. Логін: <strong><?= escapeHtml($login) ?></strong></p>
    <p><a href="index.php">Повернутися до форми</a></p>
<?php endif; ?>
</body>
</html>
