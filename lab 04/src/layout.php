<?php
declare(strict_types=1);
// Спільний шаблон сторінок ЛР 4: валідний HTML5 (lang, charset першим у head), меню етапів, час формування.

const STEPS = [
    'index.php' => 'Зміст',
    'step05_even.php' => '5. Парні числа (for, while)',
    'step07_odd_inline.php' => '7. Непарні числа в рядок',
    'step08_assoc.php' => '8. Асоціативний масив',
    'step10_bubble.php' => '10. Сортування «бульбашкою»',
    'step11_people.php' => '11. Багатовимірний масив',
    'step12_people_table.php' => '12. Люди в таблиці',
    'step13_shop.php' => '13. Товари магазину',
    'step14_matrices.php' => '14. Матриці 10×10',
];

function e(string|int|float $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function pageStart(string $title): void
{
    $current = basename($_SERVER['SCRIPT_NAME'] ?? '');
    ?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> — ЛР 4</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<nav aria-label="Етапи роботи">
    <ul>
<?php foreach (STEPS as $href => $label): ?>
        <li><a href="<?= $href ?>"<?= $href === $current ? ' aria-current="page"' : '' ?>><?= e($label) ?></a></li>
<?php endforeach; ?>
    </ul>
</nav>
<main>
    <h1>Тестова сторінка. ЛР4</h1>
<?php
}

function pageEnd(): void
{
    ?>
    <p class="time">Сторінку сформовано: <time datetime="<?= date('c') ?>"><?= date('Y-m-d H:i:s') ?></time></p>
</main>
</body>
</html>
<?php
}

date_default_timezone_set('Europe/Kyiv');
