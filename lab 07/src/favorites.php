<?php
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/events.php';

$error = null;
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    requireValidPost();
    $action = postString('action');
    $id = postString('id');
    $list = favorites();
    if ($action === 'clear') {
        $_SESSION['favorites'] = [];
        setFlash('Список обраного очищено.');
        redirect('favorites.php');
    } elseif ($action !== 'add' && $action !== 'remove') {
        $error = 'Невідома дія.';
    } elseif ($id === null || !array_key_exists($id, EVENTS)) {
        $error = 'Невідомий захід.';
    } elseif ($action === 'add') {
        if (!in_array($id, $list, true)) {   // повторне додавання не створює дубліката
            $_SESSION['favorites'][] = $id;
        }
        setFlash('«' . EVENTS[$id] . '» додано до обраного.');
        redirect('favorites.php');
    } else {
        // Видалення відсутнього заходу не є помилкою.
        $_SESSION['favorites'] = array_values(array_filter($list, fn($item): bool => $item !== $id));
        setFlash('«' . EVENTS[$id] . '» прибрано з обраного.');
        redirect('favorites.php');
    }
    http_response_code(400);
} elseif (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    header('Allow: GET, POST');
    http_response_code(405);
    exit('Метод не підтримується.');
}

// Показуємо лише відомі ідентифікатори, назви — із серверного каталогу.
$chosen = array_values(array_filter(favorites(), fn($item): bool => is_string($item) && array_key_exists($item, EVENTS)));

pageStart('Заходи клубу та обране', 'favorites.php');
if ($error !== null) {
    echo errorMessage($error), "\n";
}
?>
    <h2>Каталог заходів</h2>
    <table>
        <thead><tr><th scope="col">Захід</th><th scope="col">Дія</th></tr></thead>
        <tbody>
<?php foreach (EVENTS as $id => $title): ?>
            <tr>
                <td><?= e($title) ?></td>
                <td>
                    <form method="post" action="favorites.php">
                        <?= csrfField() ?>
                        <input type="hidden" name="id" value="<?= e($id) ?>">
<?php if (in_array($id, $chosen, true)): ?>
                        <button type="submit" name="action" value="remove" aria-label="Прибрати з обраного: <?= e($title) ?>">Прибрати</button>
<?php else: ?>
                        <button type="submit" name="action" value="add" aria-label="Додати до обраного: <?= e($title) ?>">Додати</button>
<?php endif; ?>
                    </form>
                </td>
            </tr>
<?php endforeach; ?>
        </tbody>
    </table>

    <h2>Обране (<span id="favorites-count"><?= count($chosen) ?></span>)</h2>
<?php if ($chosen === []): ?>
    <p>Список обраного порожній.</p>
<?php else: ?>
    <ol id="favorites-list">
<?php foreach ($chosen as $id): ?>
        <li><?= e(EVENTS[$id]) ?></li>
<?php endforeach; ?>
    </ol>
<?php endif; ?>
    <div class="actions">
        <form method="post" action="favorites.php">
            <?= csrfField() ?>
            <button type="submit" name="action" value="clear">Очистити обране</button>
        </form>
        <form method="post" action="end_session.php">
            <?= csrfField() ?>
            <button type="submit">Завершити сеанс</button>
        </form>
    </div>
    <p class="note">Список зберігається на сервері в <code>$_SESSION['favorites']</code>; браузер має лише cookie з ідентифікатором сесії.</p>
<?php
pageEnd();
