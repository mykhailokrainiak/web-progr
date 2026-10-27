<?php
declare(strict_types=1);
// Вихід: лише POST із CSRF-токеном (кнопка «Вийти» у верхньому меню).
require __DIR__ . '/common.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php'); // GET-посилання не повинне змінювати стан сесії.
}
if (!csrfValid()) {
    http_response_code(403);
    pageStart('Вихід');
    echo '<p class="error" role="alert">Форма застаріла. Оновіть сторінку й натисніть «Вийти» ще раз.</p>';
    pageEnd();
    exit;
}

$_SESSION = [];
$params = session_get_cookie_params();
setcookie(session_name(), '', [
    'expires' => time() - 3600,
    'path' => $params['path'],
    'domain' => $params['domain'],
    'secure' => $params['secure'],
    'httponly' => $params['httponly'],
    'samesite' => $params['samesite'] ?: 'Lax',
]);
session_destroy();

// Нова порожня сесія лише для повідомлення про вихід.
session_start();
flash('Ви вийшли із системи.');
redirect('index.php');
