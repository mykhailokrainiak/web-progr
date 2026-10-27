<?php
declare(strict_types=1);
// Власне доповнення (крок 4): порівняння fetch-функцій MySQLi на тих самих стовпцях.
// Запуск із каталогу src:  php fetch-demo.php
require __DIR__ . '/common.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(405);
    header('Content-Type: text/plain; charset=UTF-8');
    exit('Запустіть php fetch-demo.php у терміналі.');
}
$db = null;
try {
    $db = connectDatabase();
    // mysqli_query повертає буферизований результат: курсор можна повернути mysqli_data_seek.
    $result = mysqli_query($db, 'SELECT id, product_code, product_name, price FROM products ORDER BY id LIMIT 3');
    try {
        echo "mysqli_fetch_assoc — доступ за назвою стовпця:\n";
        while ($row = mysqli_fetch_assoc($result)) {
            echo "  {$row['id']}: {$row['product_name']} — {$row['price']}\n";
        }
        echo '  ще один fetch після циклу: ', var_export(mysqli_fetch_assoc($result), true), " (курсор у кінці)\n";

        mysqli_data_seek($result, 0);
        echo "\nmysqli_fetch_row — доступ за індексом (назва — третій стовпець, [2]):\n";
        while ($row = mysqli_fetch_row($result)) {
            echo "  {$row[0]}: {$row[2]} — {$row[3]}\n";
        }

        mysqli_data_seek($result, 0);
        echo "\nmysqli_fetch_object — властивості об’єкта stdClass:\n";
        while ($row = mysqli_fetch_object($result)) {
            echo "  {$row->id}: {$row->product_name} — {$row->price}\n";
        }

        mysqli_data_seek($result, 0);
        echo "\nmysqli_fetch_array(MYSQLI_BOTH) — і назва, і індекс:\n";
        $row = mysqli_fetch_array($result, MYSQLI_BOTH);
        echo "  ['product_name'] = {$row['product_name']}, [2] = {$row[2]}, ключів у рядку: ", count($row), " (4 стовпці × 2)\n";
        echo '  типи значень: id — ', gettype($row['id']), ', price — ', gettype($row['price']), " (DECIMAL повертається рядком)\n";
    } finally {
        mysqli_free_result($result);
    }
    $empty = mysqli_query($db, "SELECT id FROM products WHERE product_code = 'NO-SUCH-CODE'");
    echo "\nПорожній результат: mysqli_num_rows = ", mysqli_num_rows($empty), ", fetch = ",
        var_export(mysqli_fetch_assoc($empty), true), " — це не помилка SQL.\n";
    mysqli_free_result($empty);
} catch (Throwable $exception) {
    error_log('lab11 fetch-demo: code=' . $exception->getCode());
    fwrite(STDERR, "Перевірте змінні DB_* і таблицю products.\n");
    exit(1);
} finally {
    if ($db !== null) {
        mysqli_close($db);
    }
}
