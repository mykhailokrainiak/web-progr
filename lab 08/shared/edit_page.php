<?php
declare(strict_types=1);

// Редагування рейсу за id (завдання підвищеної складності).
// GET лише відкриває форму, зміни зберігає POST із CSRF-токеном.
require __DIR__ . '/layout.php';

$raw = array_fill_keys(array_keys(ROUTE_LABELS), '');
$errors = [];
$formError = null;
$id = null;
$showForm = false;

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $id = $_POST['id'] ?? null;
        if (!isRouteId($id)) {
            $id = null;
            throw new RuntimeException('Некоректний ідентифікатор рейсу.', 400);
        }
        $showForm = true;
        [$route, $errors, $raw] = routeFromInput($_POST);
        checkCsrf();
        if ($errors !== []) {
            http_response_code(422);
            $formError = 'Зміни не збережено: перевірте виділені поля.';
        } else {
            withLock(STORAGE_LOCK, function () use ($id, $route): void {
                $routes = loadRoutes();
                $index = findRouteIndex($routes, $id);
                if ($index === null) {
                    // Невідомий id не створює новий запис.
                    throw new RuntimeException('Рейс із таким id не знайдено; новий запис не створено.', 404);
                }
                $routes[$index] = ['id' => $id] + $route;
                saveRoutes($routes);
            });
            flash('Рейс ' . $route['number'] . ' → ' . $route['destination'] . ' оновлено.');
            header('Location: index.php', true, 303);
            exit;
        }
    } elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $id = $_GET['id'] ?? null;
        if (!isRouteId($id)) {
            $id = null;
            throw new RuntimeException('Некоректний ідентифікатор рейсу.', 400);
        }
        $routes = loadRoutes();
        $index = findRouteIndex($routes, $id);
        if ($index === null) {
            throw new RuntimeException('Рейс із таким id не знайдено.', 404);
        }
        foreach (array_keys(ROUTE_LABELS) as $field) {
            $raw[$field] = (string) $routes[$index][$field];
        }
        $showForm = true;
    } else {
        http_response_code(405);
        header('Allow: GET, POST');
        exit;
    }
} catch (RuntimeException $exception) {
    http_response_code($exception->getCode() ?: 500);
    $formError = $exception->getMessage();
    if ($exception->getCode() === 404) {
        $showForm = false;
    }
}

pageStart('Редагувати рейс');
?>
<?php if ($formError !== null): ?>
    <p class="error" role="alert"><?= e($formError) ?></p>
<?php endif; ?>
<?php if ($showForm && $id !== null): ?>
    <?php routeForm('edit.php', $raw, $errors, $id, 'Зберегти зміни'); ?>
<?php else: ?>
    <p><a href="index.php">Повернутися до розкладу</a></p>
<?php endif; ?>
<?php pageEnd();
