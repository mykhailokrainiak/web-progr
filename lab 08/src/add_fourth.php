<?php
declare(strict_types=1);
// Власне доповнення (крок 7): четвертий запис у КОПІЇ persons.xml (через DOM)
// та persons.json (через масив). Оригінали не змінюються. Запуск: php add_fourth.php
require __DIR__ . '/common.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(405);
    exit('Запустіть: php add_fourth.php');
}
$new = ['name' => 'Ярина "ʼ" & <Ко>', 'age' => 19];
try {
    foreach (['xml', 'json'] as $ext) {
        if (!copy(__DIR__ . "/persons.$ext", __DIR__ . "/persons-copy.$ext")) {
            throw new RuntimeException("Не вдалося скопіювати persons.$ext.");
        }
    }

    // XML: змінюємо дерево, а не дописуємо текст після кореня.
    $xmlPath = __DIR__ . '/persons-copy.xml';
    $dom = parseXml(readText($xmlPath));
    peopleFromXml($dom);
    $person = $dom->createElement('person');
    foreach ($new as $key => $value) {
        $field = $dom->createElement($key);
        $field->appendChild($dom->createTextNode((string) $value));
        $person->appendChild($field);
    }
    $dom->documentElement->appendChild($person);
    $dom->formatOutput = true;
    $xmlBytes = $dom->save($xmlPath);
    if ($xmlBytes === false) {
        throw new RuntimeException('Не вдалося зберегти XML.');
    }

    // JSON: додаємо елемент до масиву й кодуємо весь список.
    $jsonPath = __DIR__ . '/persons-copy.json';
    $people = validatePeople(json_decode(readText($jsonPath), true, 512, JSON_THROW_ON_ERROR));
    $people[] = $new;
    $content = json_encode(validatePeople($people), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
    $jsonBytes = file_put_contents($jsonPath, $content, LOCK_EX);
    if ($jsonBytes === false || $jsonBytes !== strlen($content)) {
        throw new RuntimeException('Не вдалося повністю записати JSON.');
    }

    // Повторне читання й порівняння даних (не тексту файлів).
    $fromXml = peopleFromXml(parseXml(readText($xmlPath)));
    $fromJson = validatePeople(json_decode(readText($jsonPath), true, 512, JSON_THROW_ON_ERROR));
    clearstatcache();
    printf("persons-copy.xml:  save() = %d байтів, filesize = %d, записів %d\n", $xmlBytes, filesize($xmlPath), count($fromXml));
    printf("persons-copy.json: записано %d байтів, filesize = %d, записів %d\n", $jsonBytes, filesize($jsonPath), count($fromJson));
    printf("Четвертий запис: %s, %d років\n", $fromXml[3]['name'], $fromXml[3]['age']);
    echo 'Дані XML і JSON однакові: ', $fromXml === $fromJson ? 'так' : 'ні', "\n";
} catch (JsonException) {
    fwrite(STDERR, "Некоректний JSON.\n");
    exit(1);
} catch (RuntimeException $exception) {
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
}
