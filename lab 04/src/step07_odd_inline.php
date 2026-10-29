<?php
declare(strict_types=1);
// Крок 7: непарні числа 1–99 В РЯДОК. Замість перевірки остачі — крок 2 від 1; замість <br> — пробіл-роздільник.
require __DIR__ . '/layout.php';
pageStart('Непарні числа в рядок');
?>
    <h2>Виведення непарних чисел for</h2>
    <p class="numbers" id="odd-for"><?php
for ($i = 1; $i <= 100; $i += 2) {
    echo $i, ' ';
}
?></p>
    <h2>Виведення непарних чисел while</h2>
    <p class="numbers" id="odd-while"><?php
$i = 0;
while ($i <= 100) {
    if ($i % 2 !== 0) {
        echo $i, ' ';
    }
    $i++;
}
?></p>
<?php
pageEnd();
