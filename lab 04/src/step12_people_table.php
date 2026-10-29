<?php
declare(strict_types=1);
// Крок 12: дані масиву людей у таблиці через foreach; функція приймає масив і друкує таблицю.
require __DIR__ . '/layout.php';

/**
 * Друкує масив записів (асоціативних масивів з однаковими ключами) як таблицю.
 * Заголовки — ключі першого запису або підписи з $labels; true/false показуються як «так»/«ні».
 */
function printTable(array $rows, array $labels = [], string $caption = ''): void
{
    if ($rows === []) {
        echo "<p>Даних немає.</p>\n";
        return;
    }
    $keys = array_keys(reset($rows));
    echo "<table>\n";
    if ($caption !== '') {
        echo '<caption>', e($caption), "</caption>\n";
    }
    echo "<thead><tr><th scope=\"col\">№</th>";
    foreach ($keys as $key) {
        echo '<th scope="col">', e($labels[$key] ?? $key), '</th>';
    }
    echo "</tr></thead>\n<tbody>\n";
    $n = 0;
    foreach ($rows as $row) {
        $n++;
        echo "<tr><td class=\"num\">$n</td>";
        foreach ($keys as $key) {
            $value = $row[$key] ?? '';
            if (is_bool($value)) {
                $value = $value ? 'так' : 'ні';
            }
            echo is_int($value) || is_float($value) ? '<td class="num">' : '<td>', e($value), '</td>';
        }
        echo "</tr>\n";
    }
    echo "</tbody>\n</table>\n";
}

$arPeople = [
    ["name" => "Ivan", "surname" => "Kostenko", "age" => 20, "student" => true],
    ["name" => "Ruslan", "surname" => "Kostenko", "age" => 12, "student" => false],
    ["name" => "Petro", "surname" => "Ilchenko", "age" => 18, "student" => true],
];

pageStart('Люди в таблиці');
?>
    <h2>Багатовимірний масив у таблиці (foreach)</h2>
<?php
printTable($arPeople, ['name' => "Ім’я", 'surname' => 'Прізвище', 'age' => 'Вік', 'student' => 'Студент'], 'Масив $arPeople');
pageEnd();
