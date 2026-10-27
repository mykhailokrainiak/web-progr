<?php
declare(strict_types=1);
// Власне доповнення (крок 8): розклад рейсів із точним пошуком за номером рейсу.
require __DIR__ . '/app.php';
require __DIR__ . '/routes-common.php';

$rows = [];
$number = '';
$error = null;
$db = null;
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    $error = 'Ця сторінка підтримує лише GET.';
} else {
    try {
        $raw = $_GET['number'] ?? '';
        if (!is_string($raw)) {
            throw new DomainException('Номер рейсу має бути рядком.', 400);
        }
        $number = trim($raw);
        if ($number !== '' && !preg_match(ROUTE_NUMBER_PATTERN, $number)) {
            throw new DomainException('Номер рейсу: 1–10 літер, цифр або дефісів.', 400);
        }
        $db = connectDatabase();
        $rows = findRoutes($db, $number);
    } catch (DomainException $exception) {
        http_response_code($exception->getCode());
        $error = $exception->getMessage();
    } catch (Throwable $exception) {
        $error = dbFailure($exception, 'routes list');
    } finally {
        closeDb($db);
    }
}

pageStart('Розклад маршрутів автовокзалу');
?>
<form method="get" action="routes.php">
    <label for="number">Номер рейсу (точний збіг; порожнє поле — весь розклад)</label>
    <input id="number" name="number" maxlength="10" value="<?= e($number) ?>">
    <button type="submit">Знайти</button>
    <a href="routes.php">Весь розклад</a>
</form>
<p><a href="route-form.php">+ Додати рейс</a></p>
<?php alertBox($error); ?>
<?php if ($error === null && $rows === []): ?>
    <p><?= $number === '' ? 'Рейсів немає.' : 'Рейс «' . e($number) . '» не знайдено.' ?></p>
<?php elseif ($error === null): ?>
    <table>
        <caption>Рейси за часом відправлення (<?= count($rows) ?>)</caption>
        <thead><tr><th scope="col">ID</th><th scope="col">Рейс</th><th scope="col">Пункт призначення</th>
            <th scope="col">Відправлення</th><th scope="col">Платформа</th><th scope="col">Ціна, грн</th><th scope="col">Дії</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $row): $id = (int) $row['id']; ?>
            <tr>
                <td class="num"><?= $id ?></td>
                <td><?= e($row['route_number']) ?></td>
                <td><?= e($row['destination']) ?></td>
                <td><?= e(shortTime($row['departure_time'])) ?></td>
                <td class="num"><?= (int) $row['platform'] ?></td>
                <td class="num"><?= e($row['price']) ?></td>
                <td><a href="route-form.php?id=<?= $id ?>">Редагувати</a> · <a href="route-delete.php?id=<?= $id ?>">Видалити</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
<?php pageEnd();
