<?php
// Окремий обробник «Завершити сеанс» (лише POST із CSRF-токеном).
require __DIR__ . '/bootstrap.php';

requireValidPost();

$_SESSION = [];
$params = session_get_cookie_params();
$cookieDeleted = setcookie(session_name(), '', [
    'expires' => time() - 3600,
    'path' => $params['path'],
    'domain' => $params['domain'],
    'secure' => $params['secure'],
    'httponly' => $params['httponly'],
    'samesite' => $params['samesite'],
]);
$destroyed = session_destroy();

if (!$cookieDeleted || !$destroyed) {
    http_response_code(500);
    exit('Не вдалося завершити сеанс.');
}
// Тема й історія відвідувань (постійні cookies) не змінюються.
header('Location: index.php?ended=1', true, 303);
exit;
