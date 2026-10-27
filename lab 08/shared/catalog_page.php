<?php
declare(strict_types=1);

// Сторінка табличного перегляду розкладу. Підключається після storage.php версії.
require __DIR__ . '/layout.php';

$routes = [];
$error = null;
try {
    $routes = loadRoutes();
    usort($routes, fn(array $a, array $b) => [$a['departure'], $a['number']] <=> [$b['departure'], $b['number']]);
} catch (RuntimeException $exception) {
    http_response_code(500);
    $error = $exception->getMessage();
}
$message = flash();

pageStart('Розклад маршрутів автовокзалу');
?>
<?php if ($message !== null): ?>
    <p class="ok" role="status"><?= e($message) ?></p>
<?php endif; ?>
<?php if ($error !== null): ?>
    <p class="error" role="alert"><?= e($error) ?></p>
<?php elseif ($routes === []): ?>
    <p>Записів немає.</p>
<?php else: ?>
    <table>
        <caption>Рейси за часом відправлення (усього: <?= count($routes) ?>)</caption>
        <thead>
        <tr>
            <?php foreach (ROUTE_LABELS as $label): ?>
                <th scope="col"><?= e($label) ?></th>
            <?php endforeach; ?>
            <th scope="col">Дії</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($routes as $route): ?>
            <tr>
                <td><?= e($route['number']) ?></td>
                <td><?= e($route['destination']) ?></td>
                <td><?= e($route['departure']) ?></td>
                <td class="num"><?= $route['platform'] ?></td>
                <td class="num"><?= $route['price'] ?></td>
                <td><a href="edit.php?id=<?= e(rawurlencode($route['id'])) ?>">Редагувати</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
<?php pageEnd();
