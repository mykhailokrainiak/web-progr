<?php
declare(strict_types=1);
// Перегляд списку рейсів із пошуком за пунктом призначення.
require __DIR__ . '/common.php';

$routes = [];
$error = null;
$query = $_GET['q'] ?? '';
if (!is_string($query) || mb_strlen($query, 'UTF-8') > 100) {
    $query = '';
    $error = 'Некоректний пошуковий запит.';
}
$query = trim($query);

if ($error === null) {
    try {
        $sql = 'SELECT id, number, destination, departure, platform, price FROM routes';
        $params = [];
        if ($query !== '') {
            // Значення передається параметром, а символи % і _ екрануються для LIKE.
            $sql .= ' WHERE destination LIKE :pattern';
            $params['pattern'] = '%' . addcslashes($query, '%_\\') . '%';
        }
        $stmt = db()->prepare($sql . ' ORDER BY departure, number');
        $stmt->execute($params);
        $routes = $stmt->fetchAll();
    } catch (PDOException $exception) {
        $error = dbError($exception);
    }
} else {
    http_response_code(400);
}

pageStart('Розклад маршрутів автовокзалу');
?>
<form method="get" action="index.php">
    <label for="q">Пункт призначення</label>
    <input id="q" name="q" type="search" maxlength="100" value="<?= e($query) ?>">
    <button type="submit">Знайти</button>
    <?php if ($query !== ''): ?>
        <a href="index.php">Скинути</a>
    <?php endif; ?>
</form>
<?php if ($error !== null): ?>
    <p class="error" role="alert"><?= e($error) ?></p>
<?php elseif ($routes === []): ?>
    <p><?= $query === '' ? 'Рейсів немає.' : 'За запитом «' . e($query) . '» рейсів не знайдено.' ?></p>
<?php else: ?>
    <table>
        <caption>Рейси за часом відправлення (знайдено: <?= count($routes) ?>)</caption>
        <thead>
        <tr><th scope="col">Рейс</th><th scope="col">Пункт призначення</th><th scope="col">Відправлення</th><th scope="col">Платформа</th><th scope="col">Ціна, грн</th></tr>
        </thead>
        <tbody>
        <?php foreach ($routes as $route): ?>
            <tr>
                <td><a href="route.php?id=<?= (int) $route['id'] ?>"><?= e($route['number']) ?></a></td>
                <td><?= e($route['destination']) ?></td>
                <td><?= e(substr($route['departure'], 0, 5)) ?></td>
                <td class="num"><?= (int) $route['platform'] ?></td>
                <td class="num"><?= (int) $route['price'] ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
<?php pageEnd();
