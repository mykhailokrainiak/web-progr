<?php
declare(strict_types=1);
// Крок 5: лістинг 1 методички. Виправлено синтаксичну помилку while($i<=100{ → while ($i <= 100) {.
require __DIR__ . '/layout.php';
pageStart('Парні числа');
?>
    <h2>Виведення парних чисел for</h2>
    <p class="numbers">
<?php
for ($i = 0; $i <= 100; $i++) {
    if ($i % 2 == 0) {
        echo $i . "<br>\n";
    }
}
?>
    </p>
    <h2>Виведення парних чисел while</h2>
    <p class="numbers">
<?php
$i = 0;
while ($i <= 100) {
    if ($i % 2 == 0) {
        echo $i . "<br>\n";
    }
    $i++;
}
?>
    </p>
<?php
pageEnd();
