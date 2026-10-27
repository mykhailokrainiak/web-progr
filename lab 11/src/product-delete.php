<?php
declare(strict_types=1);
// Власне доповнення (крок 7): видалення товару з явним підтвердженням.
// GET показує запис і кнопку підтвердження, DELETE виконує лише POST із CSRF-токеном.
require __DIR__ . '/app.php';

$product = null;
$id = null;
$error = null;
$db = null;
try {
    $method = $_SERVER['REQUEST_METHOD'];
    if ($method === 'GET') {
        $id = idFrom($_GET) ?? throw new DomainException('Некоректний id товару.', 400);
        $db = connectDatabase();
        $product = findProductById($db, $id) ?? throw new DomainException('Товар не знайдено.', 404);
    } elseif ($method === 'POST') {
        $id = idFrom($_POST) ?? throw new DomainException('Некоректний id товару.', 400);
        requireCsrf();
        $db = connectDatabase();
        $stmt = mysqli_prepare($db, 'DELETE FROM products WHERE id = ?');
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $deleted = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);
        if ($deleted !== 1) {
            throw new DomainException('Товар не знайдено; нічого не видалено.', 404);
        }
        flash("Товар id $id видалено.");
        redirect('products.php');
    } else {
        throw new DomainException('Метод не підтримується.', 405);
    }
} catch (DomainException $exception) {
    http_response_code($exception->getCode());
    $error = $exception->getMessage();
} catch (Throwable $exception) {
    $error = dbFailure($exception, 'product delete');
} finally {
    closeDb($db);
}

pageStart('Видалення товару');
alertBox($error);
if ($product !== null): ?>
    <p>Видалити цей товар? Дію не можна скасувати.</p>
    <dl>
        <dt>ID</dt><dd><?= (int) $product['id'] ?></dd>
        <dt>Код</dt><dd><?= e($product['product_code']) ?></dd>
        <dt>Назва</dt><dd><?= e($product['product_name']) ?></dd>
        <dt>Ціна</dt><dd><?= e($product['price']) ?> грн</dd>
    </dl>
    <form method="post" action="product-delete.php">
        <?= csrfField() ?>
        <input type="hidden" name="id" value="<?= (int) $product['id'] ?>">
        <button type="submit" class="danger">Так, видалити</button>
        <a href="products.php">Скасувати</a>
    </form>
<?php else: ?>
    <p><a href="products.php">До списку товарів</a></p>
<?php endif;
pageEnd();
