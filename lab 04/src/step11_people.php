<?php
declare(strict_types=1);
// Крок 11: лістинг 4 — масив людей (масив асоціативних масивів).
// У лістингу var_dump($arPerson) виводить лише останню особу; тут додатково виведено весь $arPeople.
require __DIR__ . '/layout.php';
pageStart('Багатовимірний масив');
?>
    <h2>Багатовимірні масиви</h2>
<?php
$arPeople = array();
$arPerson = array(
    "name" => "Ivan",
    "surname" => "Kostenko",
    "age" => 20,
    "student" => true
);
$arPeople[] = $arPerson;
$arPerson = array(
    "name" => "Ruslan",
    "surname" => "Kostenko",
    "age" => 12,
    "student" => false
);
$arPeople[] = $arPerson;
$arPerson = array(
    "name" => "Petro",
    "surname" => "Ilchenko",
    "age" => 18,
    "student" => true
);
$arPeople[] = $arPerson;
?>
    <h3>var_dump($arPerson) — як у лістингу (остання особа)</h3>
<?php
echo "<pre>\n";
var_dump($arPerson);
echo "</pre>\n";
?>
    <h3>var_dump($arPeople) — увесь масив</h3>
<?php
echo "<pre>\n";
var_dump($arPeople);
echo "</pre>\n";
pageEnd();
