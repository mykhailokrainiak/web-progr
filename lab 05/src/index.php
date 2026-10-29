<?php
declare(strict_types=1);
// Кроки 3–4: обробка входу в тому самому файлі. Тут лише керування запитом;
// дані — users.php, розмітка форми — login-form.php (фіксовані шляхи від __DIR__).
require __DIR__ . '/helpers.php';
$users = require __DIR__ . '/users.php';   // обов’язкові дані: без них сторінка не має сенсу → require

// Хеш для відсутнього логіна: password_verify() виконується завжди, тож час відповіді
// не підказує, чи існує логін. Хеш випадкового пароля, який ніде не збережено.
const DUMMY_HASH = '$2y$10$6jxAfbRsnbiU1wzxmGsYy.pZKNoY1eCEWEj/siZUJ6SZ/Khf689vS';
const LOGIN_PATTERN = '/^[A-Za-z0-9_]{3,30}$/D';

$errors = [];
$login = '';
$userName = null;

if (postBodyLost()) {
    $errors[] = 'Дані форми не отримано: запит, імовірно, перевищив post_max_size.';
} elseif (isPost()) {
    $rawLogin = $_POST['login'] ?? null;
    $password = $_POST['password'] ?? null;

    if (!is_string($rawLogin) || !is_string($password)) {
        $errors[] = 'Логін і пароль мають бути рядками.';
    } else {
        $login = trim($rawLogin);          // логін нормалізуємо, пароль — ні: пробіли можуть бути його частиною
        if ($login === '' || $password === '') {
            $errors[] = 'Заповніть логін і пароль.';
        } else {
            // Правила формату — до пошуку користувача й перевірки хешу.
            if (!preg_match(LOGIN_PATTERN, $login)) {
                $errors[] = 'Логін: від 3 до 30 символів — латинські літери, цифри або «_».';
            }
            $bytes = strlen($password);    // bcrypt враховує не більше 72 байтів
            if ($bytes < 8 || $bytes > 72) {
                $errors[] = 'Пароль: від 8 до 72 байтів (українська літера займає 2 байти).';
            }
        }
        if ($errors === []) {
            $user = $users[$login] ?? null;
            $valid = password_verify($password, $user['password_hash'] ?? DUMMY_HASH);
            if ($user !== null && $valid) {
                $userName = $user['name'];
            } else {
                $errors[] = 'Неправильний логін або пароль.';   // однаково для невідомого логіна й хибного пароля
            }
        }
    }
} elseif (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    header('Allow: GET, POST');
    http_response_code(405);
    exit('Метод не підтримується.');
}

if ($errors !== [] && isPost()) {
    http_response_code(postBodyLost() ? 413 : 422);
}

require __DIR__ . '/login-form.php';   // шаблон: використовує $errors, $login, $userName
