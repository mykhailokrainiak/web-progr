<?php
declare(strict_types=1);
// Крок 13: двовимірний асоціативний масив товарів (кіоск автовокзалу, варіант 10 — вигадані дані).
// Функції: таблиця товарів, друк в алфавітному порядку, друк у діапазоні цін (?min_price=…&max_price=… в адресному рядку).
require __DIR__ . '/layout.php';

const UK_ALPHABET = 'абвгґдеєжзиіїйклмнопрстуфхцчшщьюя';
const PRICE_LIMIT = 100000.0;

$goods = [
    'water' => ['name' => 'Вода мінеральна 0,5 л', 'price' => 22.50, 'qty' => 40],
    'coffee' => ['name' => 'Кава американо', 'price' => 45.00, 'qty' => 100],
    'map' => ['name' => 'Карта автодоріг України', 'price' => 120.00, 'qty' => 12],
    'pillow' => ['name' => 'Подушка дорожня', 'price' => 310.00, 'qty' => 6],
    'powerbank' => ['name' => 'Павербанк 10000 мА·год', 'price' => 749.00, 'qty' => 4],
    'sandwich' => ['name' => 'Сендвіч з куркою', 'price' => 68.00, 'qty' => 15],
    'guide' => ['name' => 'Ілюстрований путівник Черкащиною', 'price' => 185.00, 'qty' => 9],
    'umbrella' => ['name' => 'Парасолька складана', 'price' => 275.00, 'qty' => 8],
    'keychain' => ['name' => 'Їжачок-брелок', 'price' => 35.00, 'qty' => 25],
    'badge' => ['name' => 'Ґудзик-значок «Автовокзал»', 'price' => 15.00, 'qty' => 30],
    'apple' => ['name' => 'Яблуко', 'price' => 9.50, 'qty' => 50],
    'cover' => ['name' => 'Єдина обкладинка для квитків', 'price' => 55.00, 'qty' => 20],
];

/** Ключ сортування за українською абеткою: Ґ після Г, Є після Е, І та Ї після И (strcmp ставить їх у кінець). */
function ukSortKey(string $text): array
{
    $letters = mb_str_split(UK_ALPHABET);
    $order = array_flip($letters);
    $key = [];
    foreach (mb_str_split(mb_strtolower($text, 'UTF-8')) as $char) {
        $key[] = $order[$char] ?? 100 + mb_ord($char, 'UTF-8');   // не літери абетки — після неї, за кодом
    }
    return $key;
}

/**
 * Лексикографічне порівняння ключів. Не через $keyA <=> $keyB: для масивів PHP спершу порівнює
 * кількість елементів, тож коротша назва завжди виявлялася б «меншою».
 */
function ukCompare(string $a, string $b): int
{
    $keyA = ukSortKey($a);
    $keyB = ukSortKey($b);
    foreach ($keyA as $i => $code) {
        if (!array_key_exists($i, $keyB)) {
            return 1;                       // $b — префікс $a
        }
        if ($code !== $keyB[$i]) {
            return $code <=> $keyB[$i];
        }
    }
    return count($keyA) <=> count($keyB);
}

/** Таблиця товарів: код, назва, ціна, кількість. */
function printGoods(array $goods, string $caption): void
{
    if ($goods === []) {
        echo "<p>Товарів, що відповідають умові, немає.</p>\n";
        return;
    }
    echo "<table>\n<caption>", e($caption), "</caption>\n";
    echo "<thead><tr><th scope=\"col\">Код</th><th scope=\"col\">Назва</th><th scope=\"col\" class=\"num\">Ціна, грн</th><th scope=\"col\" class=\"num\">Кількість</th></tr></thead>\n<tbody>\n";
    foreach ($goods as $code => $item) {
        echo '<tr><td>', e($code), '</td><td>', e($item['name']), '</td><td class="num">',
            number_format($item['price'], 2, ',', ' '), '</td><td class="num">', $item['qty'], "</td></tr>\n";
    }
    echo "</tbody>\n</table>\n";
}

/** Друкує товари в алфавітному порядку назв (ключі-коди зберігаються: uasort). */
function printGoodsAlphabetical(array $goods): void
{
    uasort($goods, fn(array $a, array $b): int => ukCompare($a['name'], $b['name']));
    printGoods($goods, 'Товари в алфавітному порядку');
}

/** Друкує товари з ціною в межах [min, max], упорядковані за ціною. */
function printGoodsInPriceRange(array $goods, float $min, float $max): void
{
    $selected = array_filter($goods, fn(array $item): bool => $item['price'] >= $min && $item['price'] <= $max);
    uasort($selected, fn(array $a, array $b): int => $a['price'] <=> $b['price']);
    printGoods($selected, 'Ціна від ' . number_format($min, 2, ',', ' ') . ' до ' . number_format($max, 2, ',', ' ')
        . ' грн (' . count($selected) . ' з ' . count($goods) . ')');
}

/** Межа ціни з GET: відсутня або порожня — $default; інакше число 0–100000 з ≤2 знаками після коми чи крапки. */
function priceParam(string $name, float $default, array &$errors): float
{
    $raw = $_GET[$name] ?? '';
    if (!is_string($raw)) {
        $errors[] = "Параметр $name має бути числом, а не масивом.";
        return $default;
    }
    $raw = trim($raw);
    if ($raw === '') {
        return $default;
    }
    if (!preg_match('/^\d{1,6}([.,]\d{1,2})?$/D', $raw)) {
        $errors[] = "Параметр $name: невід’ємне число, до двох знаків після коми (отримано «{$raw}»).";
        return $default;
    }
    $value = (float) str_replace(',', '.', $raw);
    if ($value > PRICE_LIMIT) {
        $errors[] = "Параметр $name: не більше " . PRICE_LIMIT . '.';
        return $default;
    }
    return $value;
}

$errors = [];
$min = priceParam('min_price', 0.0, $errors);
$max = priceParam('max_price', PRICE_LIMIT, $errors);
if ($errors === [] && $min > $max) {
    $errors[] = 'Нижня межа ціни більша за верхню.';
}
if ($errors !== []) {
    http_response_code(400);
}

pageStart('Товари магазину');
?>
    <h2>Товари кіоску автовокзалу</h2>
<?php printGoods($goods, 'Усі товари (порядок масиву)'); ?>
    <h2>Алфавітний порядок</h2>
<?php printGoodsAlphabetical($goods); ?>
    <h2>Діапазон цін</h2>
    <form class="filter" action="step13_shop.php" method="get">
        <p><label for="min_price">Від, грн</label><input id="min_price" name="min_price" type="text" inputmode="decimal" value="<?= e(is_string($_GET['min_price'] ?? null) ? $_GET['min_price'] : '') ?>"></p>
        <p><label for="max_price">До, грн</label><input id="max_price" name="max_price" type="text" inputmode="decimal" value="<?= e(is_string($_GET['max_price'] ?? null) ? $_GET['max_price'] : '') ?>"></p>
        <p><button type="submit">Показати</button></p>
    </form>
    <p class="note">Межі можна задати й в адресному рядку: <code>step13_shop.php?min_price=20&amp;max_price=150</code>. Порожня межа — без обмеження.</p>
<?php if ($errors !== []): ?>
    <div class="error" role="alert">
        <ul>
<?php foreach ($errors as $error): ?>
            <li><?= e($error) ?></li>
<?php endforeach; ?>
        </ul>
    </div>
<?php else: ?>
<?php printGoodsInPriceRange($goods, $min, $max); ?>
<?php endif; ?>
<?php
pageEnd();
