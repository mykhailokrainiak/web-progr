<?php
declare(strict_types=1);

// Форма додавання рейсу. Підключається після storage.php версії.
require __DIR__ . '/layout.php';

$raw = array_fill_keys(array_keys(ROUTE_LABELS), '');
$errors = [];
$formError = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        [$route, $errors, $raw] = routeFromInput($_POST);
        checkCsrf();
        if ($errors !== []) {
            http_response_code(422);
            $formError = 'Запис не додано: перевірте виділені поля.';
        } else {
            withLock(STORAGE_LOCK, function () use ($route): void {
                $routes = loadRoutes(); // Пошкоджений файл не замінюємо — буде виняток.
                $routes[] = ['id' => newRouteId($routes)] + $route;
                saveRoutes($routes);
            });
            // Успіх показуємо лише після збереження, на сторінці каталогу.
            flash('Рейс ' . $route['number'] . ' → ' . $route['destination'] . ' додано.');
            header('Location: index.php', true, 303);
            exit;
        }
    } catch (RuntimeException $exception) {
        http_response_code($exception->getCode() ?: 500);
        $formError = $exception->getMessage();
    }
} elseif ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    header('Allow: GET, POST');
    exit;
}

pageStart('Додати рейс');
?>
<?php if ($formError !== null): ?>
    <p class="error" role="alert"><?= e($formError) ?></p>
<?php endif; ?>
<?php routeForm('add.php', $raw, $errors, null, 'Додати рейс'); ?>
<?php pageEnd();
