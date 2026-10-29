<?php
declare(strict_types=1);
// Крок 14: дві матриці 10×10 з довільними значеннями 0–100, їх сума й різниця (окремі функції), виведення таблицями.
// Необов’язковий ?seed=N (ціле 0–2147483647) робить значення відтворюваними для перевірки.
require __DIR__ . '/layout.php';

const SIZE = 10;

function randomMatrix(int $rows, int $cols, int $min, int $max): array
{
    $matrix = [];
    for ($i = 0; $i < $rows; $i++) {
        for ($j = 0; $j < $cols; $j++) {
            $matrix[$i][$j] = mt_rand($min, $max);
        }
    }
    return $matrix;
}

/** Перевіряє, що матриці мають однакові розміри (інакше поелементна дія неможлива). */
function assertSameSize(array $a, array $b): void
{
    if (count($a) !== count($b) || array_map('count', $a) !== array_map('count', $b)) {
        throw new InvalidArgumentException('Матриці мають різні розміри.');
    }
}

function addMatrices(array $a, array $b): array
{
    assertSameSize($a, $b);
    $result = [];
    foreach ($a as $i => $row) {
        foreach ($row as $j => $value) {
            $result[$i][$j] = $value + $b[$i][$j];
        }
    }
    return $result;
}

function subtractMatrices(array $a, array $b): array
{
    assertSameSize($a, $b);
    $result = [];
    foreach ($a as $i => $row) {
        foreach ($row as $j => $value) {
            $result[$i][$j] = $value - $b[$i][$j];
        }
    }
    return $result;
}

function printMatrix(array $matrix, string $caption, string $id): void
{
    echo "<table class=\"matrix\" id=\"$id\">\n<caption>", e($caption), "</caption>\n<tbody>\n";
    foreach ($matrix as $row) {
        echo '<tr>';
        foreach ($row as $value) {
            echo '<td>', $value, '</td>';
        }
        echo "</tr>\n";
    }
    echo "</tbody>\n</table>\n";
}

$seedNote = 'випадкові значення (оновіть сторінку — отримаєте нові)';
$rawSeed = $_GET['seed'] ?? null;
$error = null;
if ($rawSeed !== null) {
    $seed = is_string($rawSeed) && ctype_digit($rawSeed)
        ? filter_var($rawSeed, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 2147483647]])
        : false;
    if ($seed === false) {
        $error = 'Параметр seed має бути цілим числом 0–2147483647; використано випадкові значення.';
        http_response_code(400);
    } else {
        mt_srand($seed);
        $seedNote = "seed = $seed (значення відтворювані)";
    }
}

$a = randomMatrix(SIZE, SIZE, 0, 100);
$b = randomMatrix(SIZE, SIZE, 0, 100);
$sum = addMatrices($a, $b);
$difference = subtractMatrices($a, $b);

pageStart('Матриці 10×10');
?>
    <h2>Матриці 10×10: додавання й віднімання</h2>
    <p class="note">Значення 0–100, <?= e($seedNote) ?>. Сума в межах 0–200, різниця — від −100 до 100.</p>
<?php if ($error !== null): ?>
    <p class="error" role="alert"><?= e($error) ?></p>
<?php endif; ?>
    <div class="matrices">
<?php
printMatrix($a, 'Матриця A', 'matrix-a');
printMatrix($b, 'Матриця B', 'matrix-b');
printMatrix($sum, 'A + B', 'matrix-sum');
printMatrix($difference, 'A − B', 'matrix-diff');
?>
    </div>
<?php
pageEnd();
