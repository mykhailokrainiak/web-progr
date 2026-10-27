<?php
declare(strict_types=1);
// Власне доповнення (крок 8): додавання й редагування рейсу. GET — форма, POST — зміна даних.
require __DIR__ . '/app.php';
require __DIR__ . '/routes-common.php';

$values = array_fill_keys(ROUTE_FIELDS, '');
$errors = [];
$id = null;
$error = null;
$showForm = true;
$db = null;
$inTransaction = false;

try {
    $method = $_SERVER['REQUEST_METHOD'];
    if ($method === 'GET') {
        if (array_key_exists('id', $_GET)) {
            $id = idFrom($_GET) ?? throw new DomainException('Некоректний id рейсу.', 400);
            $db = connectDatabase();
            $row = findRouteById($db, $id) ?? throw new DomainException('Рейс не знайдено.', 404);
            $values = array_intersect_key($row, $values);
            $values['departure_time'] = shortTime($values['departure_time']);
            $values['platform'] = (string) $values['platform'];
        }
    } elseif ($method === 'POST') {
        $values = postedStrings(ROUTE_FIELDS);
        if (array_key_exists('id', $_POST)) {
            $id = idFrom($_POST) ?? throw new DomainException('Некоректний id рейсу.', 400);
        }
        requireCsrf();
        [$route, $errors] = validateRoute($_POST);
        if ($errors !== []) {
            throw new InvalidArgumentException('Перевірте виділені поля.');
        }
        $db = connectDatabase();
        if ($id === null) {
            $stmt = mysqli_prepare($db, 'INSERT INTO bus_routes (route_number, destination, departure_time, platform, price)
                                         VALUES (?, ?, ?, ?, ?)');
            mysqli_stmt_bind_param($stmt, 'sssis', $route['route_number'], $route['destination'],
                $route['departure_time'], $route['platform'], $route['price']);
            mysqli_stmt_execute($stmt);
            $newId = mysqli_insert_id($db);
            mysqli_stmt_close($stmt);
            flash("Рейс {$route['route_number']} → {$route['destination']} додано (id $newId).");
        } else {
            mysqli_begin_transaction($db);
            $inTransaction = true;
            $stmt = mysqli_prepare($db, 'SELECT id FROM bus_routes WHERE id = ? FOR UPDATE');
            mysqli_stmt_bind_param($stmt, 'i', $id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_store_result($stmt);
            $exists = mysqli_stmt_num_rows($stmt) === 1;
            mysqli_stmt_close($stmt);
            if (!$exists) {
                throw new DomainException('Рейс не знайдено; новий запис не створено.', 404);
            }
            $stmt = mysqli_prepare($db, 'UPDATE bus_routes SET route_number = ?, destination = ?, departure_time = ?,
                                         platform = ?, price = ? WHERE id = ?');
            mysqli_stmt_bind_param($stmt, 'sssisi', $route['route_number'], $route['destination'],
                $route['departure_time'], $route['platform'], $route['price'], $id);
            mysqli_stmt_execute($stmt);
            $changed = mysqli_stmt_affected_rows($stmt);
            mysqli_stmt_close($stmt);
            mysqli_commit($db);
            $inTransaction = false;
            flash($changed === 0
                ? "Рейс id $id збережено без змін (affected_rows = 0)."
                : "Рейс id $id оновлено.");
        }
        redirect('routes.php');
    } else {
        http_response_code(405);
        header('Allow: GET, POST');
        $error = 'Метод не підтримується.';
        $showForm = false;
    }
} catch (InvalidArgumentException $exception) {
    http_response_code(422);
    $error = $exception->getMessage();
} catch (DomainException $exception) {
    http_response_code($exception->getCode());
    $error = $exception->getMessage();
    $showForm = $exception->getCode() === 403;
} catch (Throwable $exception) {
    if (isDuplicate($exception)) {
        http_response_code(409);
        $error = 'Такий рейс уже є в розкладі.';
        $errors['route_number'] = 'Рейс із цим номером уже відправляється о ' . trim($values['departure_time']) . '.';
    } else {
        $error = dbFailure($exception, 'route save');
    }
} finally {
    if ($inTransaction) {
        try {
            mysqli_rollback($db);
        } catch (Throwable) {
        }
    }
    closeDb($db);
}

pageStart($id === null ? 'Новий рейс' : 'Редагування рейсу id ' . $id);
alertBox($error);
if ($showForm):
    $inputs = [
        'route_number' => ['До 10 літер, цифр або дефісів (наприклад, 212-А).', 'maxlength="10"'],
        'destination' => ['Від 2 до 100 символів.', 'maxlength="100"'],
        'departure_time' => ['ГГ:ХХ, наприклад 07:45.', 'inputmode="numeric" maxlength="5"'],
        'platform' => ['Ціле число 1–20.', 'inputmode="numeric" maxlength="2"'],
        'price' => ['1.00–5000.00, крапка, до двох дробових цифр.', 'inputmode="decimal" maxlength="7"'],
    ];
    ?>
<form method="post" action="route-form.php" novalidate>
    <?= csrfField() ?>
    <?php if ($id !== null): ?><input type="hidden" name="id" value="<?= $id ?>"><?php endif; ?>
    <?php foreach ($inputs as $name => [$hint, $extra]): $hasError = isset($errors[$name]); ?>
        <div class="field">
            <label for="<?= $name ?>"><?= e(ROUTE_LABELS[$name]) ?></label>
            <input id="<?= $name ?>" name="<?= $name ?>" type="text" <?= $extra ?> required
                   value="<?= e($values[$name]) ?>"
                   aria-describedby="<?= $name ?>-hint<?= $hasError ? " $name-error" : '' ?>"<?= $hasError ? ' aria-invalid="true"' : '' ?>>
            <small id="<?= $name ?>-hint"><?= e($hint) ?></small>
            <?php if ($hasError): ?><span class="error" id="<?= $name ?>-error"><?= e($errors[$name]) ?></span><?php endif; ?>
        </div>
    <?php endforeach; ?>
    <button type="submit"><?= $id === null ? 'Додати рейс' : 'Зберегти зміни' ?></button>
    <a href="routes.php">Скасувати</a>
</form>
<?php else: ?>
    <p><a href="routes.php">До розкладу</a></p>
<?php endif;
pageEnd();
