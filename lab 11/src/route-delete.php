<?php
declare(strict_types=1);
// Власне доповнення (крок 8): видалення рейсу з явним підтвердженням (DELETE лише через POST + CSRF).
require __DIR__ . '/app.php';
require __DIR__ . '/routes-common.php';

$route = null;
$error = null;
$db = null;
try {
    $method = $_SERVER['REQUEST_METHOD'];
    if ($method === 'GET') {
        $id = idFrom($_GET) ?? throw new DomainException('Некоректний id рейсу.', 400);
        $db = connectDatabase();
        $route = findRouteById($db, $id) ?? throw new DomainException('Рейс не знайдено.', 404);
    } elseif ($method === 'POST') {
        $id = idFrom($_POST) ?? throw new DomainException('Некоректний id рейсу.', 400);
        requireCsrf();
        $db = connectDatabase();
        $stmt = mysqli_prepare($db, 'DELETE FROM bus_routes WHERE id = ?');
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $deleted = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);
        if ($deleted !== 1) {
            throw new DomainException('Рейс не знайдено; нічого не видалено.', 404);
        }
        flash("Рейс id $id видалено.");
        redirect('routes.php');
    } else {
        throw new DomainException('Метод не підтримується.', 405);
    }
} catch (DomainException $exception) {
    http_response_code($exception->getCode());
    $error = $exception->getMessage();
} catch (Throwable $exception) {
    $error = dbFailure($exception, 'route delete');
} finally {
    closeDb($db);
}

pageStart('Видалення рейсу');
alertBox($error);
if ($route !== null): ?>
    <p>Видалити цей рейс із розкладу? Дію не можна скасувати.</p>
    <dl>
        <dt>Рейс</dt><dd><?= e($route['route_number']) ?></dd>
        <dt>Пункт призначення</dt><dd><?= e($route['destination']) ?></dd>
        <dt>Відправлення</dt><dd><?= e(shortTime($route['departure_time'])) ?></dd>
        <dt>Платформа</dt><dd><?= (int) $route['platform'] ?></dd>
        <dt>Ціна</dt><dd><?= e($route['price']) ?> грн</dd>
    </dl>
    <form method="post" action="route-delete.php">
        <?= csrfField() ?>
        <input type="hidden" name="id" value="<?= (int) $route['id'] ?>">
        <button type="submit" class="danger">Так, видалити</button>
        <a href="routes.php">Скасувати</a>
    </form>
<?php else: ?>
    <p><a href="routes.php">До розкладу</a></p>
<?php endif;
pageEnd();
