<?php
declare(strict_types=1);

// Параметри підключення. Типові значення відповідають XAMPP (root без пароля).
// Їх можна змінити змінними середовища LAB12_DB_* або файлом config.local.php
// (він не додається до репозиторію), який повертає масив із тими самими ключами.
$config = [
    'host' => getenv('LAB12_DB_HOST') ?: '127.0.0.1',
    'port' => (int) (getenv('LAB12_DB_PORT') ?: 3306),
    'name' => getenv('LAB12_DB_NAME') ?: 'lab12_bus',
    'user' => getenv('LAB12_DB_USER') ?: 'root',
    'pass' => getenv('LAB12_DB_PASS') !== false ? (string) getenv('LAB12_DB_PASS') : '',
];
if (is_file(__DIR__ . '/config.local.php')) {
    $config = array_merge($config, require __DIR__ . '/config.local.php');
}
return $config;
