<?php
declare(strict_types=1);
// Власне доповнення (крок 7): список товарів із діями додавання, редагування й видалення.
require __DIR__ . '/app.php';

$rows = [];
$error = null;
$db = null;
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    $error = 'Ця сторінка підтримує лише GET.';
} else {
    try {
        $db = connectDatabase();
        $rows = findProducts($db, ''); // Готова функція з common.php.
    } catch (Throwable $exception) {
        $error = dbFailure($exception, 'products list');
    } finally {
        closeDb($db);
    }
}

pageStart('Товари: додавання, редагування, видалення');
?>
<p><a href="product-form.php">+ Додати товар</a></p>
<?php alertBox($error); ?>
<?php if ($error === null && $rows === []): ?>
    <p>Товарів немає.</p>
<?php elseif ($error === null): ?>
    <table>
        <caption>Товари (<?= count($rows) ?>)</caption>
        <thead><tr><th scope="col">ID</th><th scope="col">Код</th><th scope="col">Назва</th><th scope="col">Опис</th>
            <th scope="col">Ціна, грн</th><th scope="col">Дії</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $row): $id = (int) $row['id']; ?>
            <tr>
                <td class="num"><?= $id ?></td>
                <td><?= e($row['product_code']) ?></td>
                <td><?= e($row['product_name']) ?></td>
                <td><?= e($row['product_desc']) ?></td>
                <td class="num"><?= e($row['price']) ?></td>
                <td><a href="product-form.php?id=<?= $id ?>">Редагувати</a> · <a href="product-delete.php?id=<?= $id ?>">Видалити</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
<?php pageEnd();
