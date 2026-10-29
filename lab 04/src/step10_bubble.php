<?php
declare(strict_types=1);
// Крок 10: лістинг 3 — 101 випадкове число 0–100 і сортування «бульбашкою» (вкладені цикли).
require __DIR__ . '/layout.php';
pageStart('Сортування «бульбашкою»');
?>
    <h2>Сортування методом "бульбашки". Вкладені цикли</h2>
    <p class="numbers" id="before">
<?php
$arNumbers = array();
for ($i = 0; $i <= 100; $i++) {
    $arNumbers[] = rand(0, 100);
    echo $arNumbers[$i] . " ";
}
$original = $arNumbers;
?>
    </p>
    <p class="numbers" id="after">
<?php
// 101 прохід; на кожному порівнюються сусідні пари 0–1, …, 99–100, більший елемент «спливає» праворуч.
for ($i = 0; $i <= 100; $i++) {
    for ($j = 0; $j <= 99; $j++) {
        if ($arNumbers[$j] > $arNumbers[$j + 1]) {
            $tmp = $arNumbers[$j];
            $arNumbers[$j] = $arNumbers[$j + 1];
            $arNumbers[$j + 1] = $tmp;
        }
    }
}
foreach ($arNumbers as $item) {
    echo $item . " ";
}
// Контроль: результат збігається з вбудованим sort() того самого масиву.
sort($original);
?>
    </p>
    <p class="note" id="check">Перевірка: масив <?= $arNumbers === $original ? 'відсортовано за зростанням (збігається з sort())' : 'НЕ відсортовано' ?>.</p>
<?php
pageEnd();
