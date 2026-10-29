<?php
declare(strict_types=1);
// Крок 8: лістинг 2 — асоціативний масив з ключами k0…k100. var_dump() обгорнуто в <pre> для читабельності.
require __DIR__ . '/layout.php';
pageStart('Асоціативний масив');
?>
    <h2>Створення асоціативного масиву</h2>
<?php
$arNumbers = array();
for ($i = 0; $i <= 100; $i++) {
    $key = 'k' . $i;
    $arNumbers[$key] = $i;
}
echo "<pre id=\"dump\">\n";
var_dump($arNumbers);
echo "</pre>\n";
?>
    <p class="note">Елементів: <?= count($arNumbers) ?>; перший ключ <code><?= e(array_key_first($arNumbers)) ?></code>, останній <code><?= e(array_key_last($arNumbers)) ?></code>.</p>
<?php
pageEnd();
