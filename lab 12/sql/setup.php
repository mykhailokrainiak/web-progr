<?php
declare(strict_types=1);
// Створює базу lab12_bus і таблиці з schema.sql через PDO (замість phpMyAdmin).
// Запуск із каталогу lab-12:  php sql/setup.php
// УВАГА: наявні таблиці users і routes буде видалено й створено заново.

if (PHP_SAPI !== 'cli') {
    http_response_code(405);
    exit('Запустіть із термінала: php sql/setup.php');
}
$config = require __DIR__ . '/../src/config.php';
try {
    // Підключення без dbname: бази ще може не існувати.
    $pdo = new PDO(sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $config['host'], $config['port']),
        $config['user'], $config['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $sql = file_get_contents(__DIR__ . '/schema.sql');
    if ($sql === false) {
        throw new RuntimeException('Не вдалося прочитати schema.sql.');
    }
    $sql = str_replace('lab12_bus', $config['name'], $sql);
    $pdo->exec($sql);
    $pdo->exec('USE `' . str_replace('`', '``', $config['name']) . '`');
    $count = $pdo->query('SELECT COUNT(*) FROM routes')->fetchColumn();
    echo "База {$config['name']} готова: таблиці users і routes, рейсів: $count.\n";
} catch (PDOException $exception) {
    fwrite(STDERR, 'Помилка MySQL: ' . $exception->getMessage() . "\n");
    exit(1);
} catch (RuntimeException $exception) {
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
}
