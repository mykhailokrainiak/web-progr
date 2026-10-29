<?php
declare(strict_types=1);
// Тестові файли для негативних перевірок (tests/fixtures). Запуск: php -d extension=gd tools/make_fixtures.php
// Файли застосовуються лише до копії src у tests/work, справжні дані не змінюються.

$dir = dirname(__DIR__) . '/tests/fixtures';
@mkdir($dir . '/data', 0777, true);
@mkdir($dir . '/images', 0777, true);

$bus = static fn(array $items): string => json_encode(
    ['title' => 'Відправлення з автовокзалу за напрямками', 'unit' => 'рейсів на тиждень', 'items' => $items],
    JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
$data = [
    'bus-one.json' => $bus([['label' => 'Київ', 'value' => 42]]),
    'bus-zeros.json' => $bus([['label' => 'Київ', 'value' => 0], ['label' => 'Черкаси', 'value' => 0], ['label' => 'Умань', 'value' => 0]]),
    'bus-empty.json' => $bus([]),
    'bus-broken.json' => '{"title": "Відправлення", "unit": "рейсів", "items": [{"label": "Київ", "value": 42},',
    'bus-negative.json' => $bus([['label' => 'Київ', 'value' => 42], ['label' => 'Черкаси', 'value' => -5]]),
    'bus-string.json' => $bus([['label' => 'Київ', 'value' => '42']]),
    'bus-float.json' => $bus([['label' => 'Київ', 'value' => 4.5]]),
    'bus-eleven.json' => $bus(array_map(fn(int $i): array => ['label' => "Рейс $i", 'value' => $i], range(1, 11))),
    'bus-longlabel.json' => $bus([['label' => str_repeat('Д', 25), 'value' => 1]]),
    'bus-list.json' => json_encode([['label' => 'Київ', 'value' => 42]], JSON_UNESCAPED_UNICODE),
    'bus-markup.json' => $bus([
        ['label' => 'Сміла & Черкаси', 'value' => 30],
        ['label' => '<script>x()</script>', 'value' => 20],
        ['label' => 'Ціни < 100 грн', 'value' => 10],
        ['label' => "Кам'янець \"Old\"", 'value' => 5],
    ]),
    'chart-one.json' => '[{"label": "A", "value": 20}]',
    'chart-zeros.json' => '[{"label": "A", "value": 0}, {"label": "B", "value": 0}, {"label": "C", "value": 0}]',
    'chart-empty.json' => '[]',
    'chart-broken.json' => '[{"label": "A", "value": 20},',
    'chart-negative.json' => '[{"label": "A", "value": -1}]',
    'chart-string.json' => '[{"label": "A", "value": "20"}]',
];
foreach ($data as $name => $content) {
    file_put_contents("$dir/data/$name", $content);
}

$png = static function (int $w, int $h, bool $noise = false): GdImage {
    $image = imagecreatetruecolor($w, $h);
    imagefill($image, 0, 0, imagecolorallocate($image, 200, 220, 240));
    if ($noise) {   // шум погано стискається: файл > 2 MiB за помірних габаритів
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                imagesetpixel($image, $x, $y, random_int(0, 0xFFFFFF));
            }
        }
    }
    return $image;
};
imagejpeg($png(640, 360), "$dir/images/jpeg-renamed.png");                 // JPEG з розширенням .png
file_put_contents("$dir/images/text-renamed.png", "Це текстовий файл, а не PNG.\n");
imagepng($png(640, 360), "$dir/images/full.png");
$full = file_get_contents("$dir/images/full.png");
file_put_contents("$dir/images/truncated.png", substr($full, 0, (int) (strlen($full) * 0.6)));   // пошкоджений
$bad = $full;
$bad[60] = chr(ord($bad[60]) ^ 0xFF);                                       // зіпсовано байт стиснених даних
file_put_contents("$dir/images/corrupted.png", $bad);
imagepng($png(300, 150), "$dir/images/small-300x150.png");
imagepng($png(2001, 300), "$dir/images/wide-2001x300.png");
imagepng($png(1500, 1500), "$dir/images/pixels-1500x1500.png");            // 2,25 млн пікселів
imagepng($png(1400, 1400, true), "$dir/images/heavy-noise.png", 0);        // > 2 MiB, 1,96 млн пікселів
foreach (glob("$dir/images/*") as $file) {
    $info = @getimagesize($file);
    printf("%-24s %9d байт  %s\n", basename($file), filesize($file), $info ? "{$info[0]}×{$info[1]} {$info['mime']}" : 'не зображення');
}
echo 'Дані: ', count($data), " файлів\n";
