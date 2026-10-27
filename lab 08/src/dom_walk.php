<?php
declare(strict_types=1);
// Власне доповнення (крок 4): обхід DOM persons.xml з виведенням у таблицю.
require __DIR__ . '/common.php';

$rows = [];
$error = null;
$rootName = '';
try {
    // loadXML() у parseXml() розбирає рядок; load() читав би файл за шляхом.
    $dom = parseXml(readText(__DIR__ . '/persons.xml'));
    peopleFromXml($dom); // Перевірка структури перед обходом.
    $rootName = $dom->documentElement->tagName; // documentElement — кореневий елемент <people>.
    $persons = $dom->getElementsByTagName('person'); // DOMNodeList усіх нащадків <person>.
    foreach ($persons as $index => $person) {
        $name = $person->getElementsByTagName('name')->item(0);
        $age = $person->getElementsByTagName('age')->item(0);
        if ($name === null || $age === null) { // item(0) повертає null, якщо елемента немає.
            throw new RuntimeException('Запис без name або age.');
        }
        // textContent — текст вузла з уже декодованими &amp; та &lt;.
        $rows[] = [$index + 1, $name->textContent, $age->textContent, $person->childNodes->length];
    }
} catch (RuntimeException $exception) {
    http_response_code(500);
    $error = $exception->getMessage();
}
header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Обхід DOM persons.xml</title>
</head>
<body>
<h1>Обхід DOM: persons.xml</h1>
<?php if ($error !== null): ?>
    <p><?= e($error) ?></p>
<?php else: ?>
    <p>Кореневий елемент (documentElement): <code>&lt;<?= e($rootName) ?>&gt;</code>, елементів person у DOMNodeList: <?= count($rows) ?>.</p>
    <table>
        <caption>Значення textContent полів кожного запису</caption>
        <thead><tr><th scope="col">item()</th><th scope="col">name</th><th scope="col">age (текст)</th><th scope="col">Дочірніх вузлів</th></tr></thead>
        <tbody>
        <?php foreach ($rows as [$number, $name, $age, $children]): ?>
            <tr><td><?= $number - 1 ?></td><td><?= e($name) ?></td><td><?= e($age) ?></td><td><?= $children ?></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
</body>
</html>
