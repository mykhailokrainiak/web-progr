<?php
declare(strict_types=1);
// Зміст роботи: кожен етап index.php з методички збережено окремим файлом, щоб усі стани можна було відкрити й перевірити.
require __DIR__ . '/layout.php';
pageStart('Зміст');
?>
    <p>Крайняк Михайло Юрійович, 451 група. PHP <?= e(PHP_VERSION) ?>.</p>
    <ol>
<?php foreach (array_slice(STEPS, 1, null, true) as $href => $label): ?>
        <li><a href="<?= $href ?>"><?= e($label) ?></a></li>
<?php endforeach; ?>
    </ol>
<?php
pageEnd();
