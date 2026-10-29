<?php
declare(strict_types=1);
// Одноразове створення bcrypt-хешу навчального пароля під час підготовки users.php (лише CLI).
// Пароль читається зі стандартного введення, тож не залишається в історії команд:
//   php tools/make_hash.php < файл-із-паролем     або     php tools/make_hash.php  (ввести й натиснути Enter)
if (PHP_SAPI !== 'cli') {
    http_response_code(405);
    exit("Лише для термінала.\n");
}
$password = rtrim((string) fgets(STDIN), "\r\n");   // обрізаємо лише символ кінця рядка, не пробіли
$bytes = strlen($password);
if ($bytes < 8 || $bytes > 72) {
    fwrite(STDERR, "Пароль має містити 8–72 байти (зараз $bytes).\n");
    exit(1);
}
echo password_hash($password, PASSWORD_BCRYPT), "\n";
