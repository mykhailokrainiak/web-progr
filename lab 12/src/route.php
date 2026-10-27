<?php
declare(strict_types=1);
// Перегляд одного рейсу за ідентифікатором (аналог book.php з прикладу бібліотеки).
require __DIR__ . '/common.php';

$route = null;
$error = null;
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($id === false || $id === null) {
    http_response_code(400);
    $error = 'Некоректний ідентифікатор рейсу.';
} else {
    try {
        $stmt = db()->prepare('SELECT * FROM routes WHERE id = :id');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $route = $stmt->fetch() ?: null;
        if ($route === null) {
            http_response_code(404);
            $error = 'Рейс не знайдено.';
        }
    } catch (PDOException $exception) {
        $error = dbError($exception);
    }
}

pageStart($route !== null ? 'Рейс ' . $route['number'] . ' → ' . $route['destination'] : 'Рейс');
?>
<?php if ($error !== null): ?>
    <p class="error" role="alert"><?= e($error) ?></p>
<?php else: ?>
    <dl>
        <dt>Номер рейсу</dt><dd><?= e($route['number']) ?></dd>
        <dt>Пункт призначення</dt><dd><?= e($route['destination']) ?></dd>
        <dt>Відправлення</dt><dd><?= e(substr($route['departure'], 0, 5)) ?></dd>
        <dt>У дорозі</dt><dd><?= e(formatDuration((int) $route['duration_min'])) ?></dd>
        <dt>Платформа</dt><dd><?= (int) $route['platform'] ?></dd>
        <dt>Ціна квитка</dt><dd><?= (int) $route['price'] ?> грн</dd>
        <dt>Перевізник</dt><dd><?= e($route['carrier']) ?></dd>
        <dt>Дні курсування</dt><dd><?= e($route['days']) ?></dd>
    </dl>
<?php endif; ?>
<p><a href="index.php">← До розкладу</a></p>
<?php pageEnd();
