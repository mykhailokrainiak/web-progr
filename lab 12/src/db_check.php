<?php
declare(strict_types=1);
// Перевірка підключення PDO і структури таблиць (кроки 3–4). Облікові дані не показуються.
require __DIR__ . '/common.php';

$info = [];
$tables = [];
$columns = [];
$error = null;
try {
    $pdo = db();
    $info = [
        'Драйвер PDO' => $pdo->getAttribute(PDO::ATTR_DRIVER_NAME),
        'Версія сервера' => $pdo->getAttribute(PDO::ATTR_SERVER_VERSION),
        'База даних' => (string) $pdo->query('SELECT DATABASE()')->fetchColumn(),
        'Кодування з’єднання' => (string) $pdo->query('SELECT @@character_set_connection')->fetchColumn(),
        'Режим помилок' => $pdo->getAttribute(PDO::ATTR_ERRMODE) === PDO::ERRMODE_EXCEPTION ? 'ERRMODE_EXCEPTION' : 'інший',
        'Доступні драйвери PDO' => implode(', ', PDO::getAvailableDrivers()),
    ];
    foreach (['users', 'routes'] as $table) {
        // Назви таблиць — зі списку в коді, тому їх можна підставити в SQL.
        $tables[$table] = (int) $pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
    }
    $columns = $pdo->query('SHOW COLUMNS FROM users')->fetchAll();
} catch (PDOException $exception) {
    $error = dbError($exception);
}

pageStart('Стан бази даних');
?>
<?php if ($error !== null): ?>
    <p class="error" role="alert"><?= e($error) ?></p>
<?php else: ?>
    <p class="ok">Підключення до бази даних успішне.</p>
    <dl>
        <?php foreach ($info as $name => $value): ?>
            <dt><?= e($name) ?></dt><dd><?= e((string) $value) ?></dd>
        <?php endforeach; ?>
        <?php foreach ($tables as $table => $count): ?>
            <dt>Записів у <?= e($table) ?></dt><dd><?= $count ?></dd>
        <?php endforeach; ?>
    </dl>
    <table>
        <caption>Структура таблиці users</caption>
        <thead><tr><th scope="col">Поле</th><th scope="col">Тип</th><th scope="col">NULL</th><th scope="col">Ключ</th><th scope="col">Типово</th></tr></thead>
        <tbody>
        <?php foreach ($columns as $column): ?>
            <tr>
                <td><?= e($column['Field']) ?></td>
                <td><?= e($column['Type']) ?></td>
                <td><?= e($column['Null']) ?></td>
                <td><?= e($column['Key']) ?></td>
                <td><?= e((string) $column['Default']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
<?php pageEnd();
