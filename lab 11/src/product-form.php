<?php
declare(strict_types=1);
// Власне доповнення (крок 7): додавання (без id) і редагування товару за id.
// GET відкриває форму, POST змінює дані після перевірки CSRF і validateProduct().
require __DIR__ . '/app.php';

const PRODUCT_FIELDS = ['product_code', 'product_name', 'product_desc', 'price'];

$values = array_fill_keys(PRODUCT_FIELDS, '');
$id = null;
$error = null;
$showForm = true;
$db = null;
$inTransaction = false;

try {
    $method = $_SERVER['REQUEST_METHOD'];
    if ($method === 'GET') {
        if (array_key_exists('id', $_GET)) {
            $id = idFrom($_GET) ?? throw new DomainException('Некоректний id товару.', 400);
            $db = connectDatabase();
            $row = findProductById($db, $id) ?? throw new DomainException('Товар не знайдено.', 404);
            $values = array_intersect_key($row, $values);
        }
    } elseif ($method === 'POST') {
        $values = postedStrings(PRODUCT_FIELDS);
        if (array_key_exists('id', $_POST)) {
            $id = idFrom($_POST) ?? throw new DomainException('Некоректний id товару.', 400);
        }
        requireCsrf();
        $product = validateProduct($_POST); // Готова перевірка; кидає InvalidArgumentException.
        $db = connectDatabase();
        if ($id === null) {
            $stmt = mysqli_prepare($db,
                'INSERT INTO products (product_code, product_name, product_desc, price) VALUES (?, ?, ?, ?)');
            mysqli_stmt_bind_param($stmt, 'ssss', $product['product_code'], $product['product_name'],
                $product['product_desc'], $product['price']);
            mysqli_stmt_execute($stmt);
            $newId = mysqli_insert_id($db);
            mysqli_stmt_close($stmt);
            flash("Товар {$product['product_code']} додано (id $newId).");
        } else {
            mysqli_begin_transaction($db);
            $inTransaction = true;
            // Блокуємо рядок і переконуємося, що він існує: невідомий id не створює товар.
            $stmt = mysqli_prepare($db, 'SELECT id FROM products WHERE id = ? FOR UPDATE');
            mysqli_stmt_bind_param($stmt, 'i', $id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_store_result($stmt);
            $exists = mysqli_stmt_num_rows($stmt) === 1;
            mysqli_stmt_close($stmt);
            if (!$exists) {
                throw new DomainException('Товар не знайдено; новий запис не створено.', 404);
            }
            $stmt = mysqli_prepare($db,
                'UPDATE products SET product_code = ?, product_name = ?, product_desc = ?, price = ? WHERE id = ?');
            mysqli_stmt_bind_param($stmt, 'ssssi', $product['product_code'], $product['product_name'],
                $product['product_desc'], $product['price'], $id);
            mysqli_stmt_execute($stmt);
            $changed = mysqli_stmt_affected_rows($stmt);
            mysqli_stmt_close($stmt);
            mysqli_commit($db);
            $inTransaction = false;
            // affected_rows = 0: рядок існує (перевірено вище), але значення ті самі.
            flash($changed === 0
                ? "Товар id $id збережено без змін (affected_rows = 0)."
                : "Товар id $id оновлено (affected_rows = $changed).");
        }
        redirect('products.php');
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
        $error = 'Товар із кодом «' . trim($values['product_code']) . '» уже існує. Вкажіть інший код.';
    } else {
        $error = dbFailure($exception, 'product save');
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

pageStart($id === null ? 'Новий товар' : 'Редагування товару id ' . $id);
alertBox($error);
if ($showForm):
    $fields = [
        'product_code' => ['Код', '3–20 великих латинських літер, цифр або дефісів; унікальний.', 'maxlength="20"'],
        'product_name' => ['Назва', '1–80 символів.', 'maxlength="80"'],
        'price' => ['Ціна, грн', '0–99999999.99, крапка, до двох дробових цифр.', 'inputmode="decimal" maxlength="11"'],
    ];
    ?>
<form method="post" action="product-form.php" novalidate>
    <?= csrfField() ?>
    <?php if ($id !== null): ?><input type="hidden" name="id" value="<?= $id ?>"><?php endif; ?>
    <?php foreach ($fields as $name => [$label, $hint, $extra]): ?>
        <div class="field">
            <label for="<?= $name ?>"><?= e($label) ?></label>
            <input id="<?= $name ?>" name="<?= $name ?>" type="text" <?= $extra ?> required
                   value="<?= e($values[$name]) ?>" aria-describedby="<?= $name ?>-hint">
            <small id="<?= $name ?>-hint"><?= e($hint) ?></small>
        </div>
    <?php endforeach; ?>
    <div class="field">
        <label for="product_desc">Опис</label>
        <textarea id="product_desc" name="product_desc" rows="3" cols="60" maxlength="500" required
                  aria-describedby="product_desc-hint"><?= e($values['product_desc']) ?></textarea>
        <small id="product_desc-hint">1–500 символів.</small>
    </div>
    <button type="submit"><?= $id === null ? 'Додати товар' : 'Зберегти зміни' ?></button>
    <a href="products.php">Скасувати</a>
</form>
<?php else: ?>
    <p><a href="products.php">До списку товарів</a></p>
<?php endif;
pageEnd();
