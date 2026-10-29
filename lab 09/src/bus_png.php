<?php
declare(strict_types=1);
// Самостійне завдання 1: стовпчаста PNG-діаграма з файла bus-data.json (варіант 10 — автовокзал).
require __DIR__ . '/bus-common.php';

runImage(function (): void {
    [$width, $height] = chartSize();         // GET width 400–1200, height 300–800 (готова перевірка)
    $data = loadBusData();
    $font = requireTtf();
    $measure = ttfWidth($font);
    $layout = busLayout($data['items'], $width, $height);

    $image = canvas($width, $height);
    $ink = imagecolorallocate($image, 30, 41, 59);
    $muted = imagecolorallocate($image, 90, 104, 125);
    $gridColor = imagecolorallocate($image, 226, 232, 240);
    $blue = imagecolorallocate($image, 37, 99, 235);

    [$title, $size] = fitText($data['title'], $width - 30, $measure, 15, 9);
    ttfText($image, $font, $size, 15, 28, $ink, $title);
    [$unit, $size] = fitText('Одиниця: ' . $data['unit'], $width - 30, $measure, 11, 8);
    ttfText($image, $font, $size, 15, 50, $muted, $unit);

    foreach ($layout['grid'] as $line) {
        if ($line['y'] !== $layout['bottom']) {
            imageline($image, $layout['left'] + 1, $line['y'], $layout['right'], $line['y'], $gridColor);
        }
        imageline($image, $layout['left'] - 4, $line['y'], $layout['left'], $line['y'], $ink);
        ttfText($image, $font, 10, $layout['left'] - 8, $line['y'] + 4, $ink, (string) $line['value'], 'right');
    }
    imageline($image, $layout['left'], $layout['top'] - 6, $layout['left'], $layout['bottom'], $ink);
    imageline($image, $layout['left'], $layout['bottom'], $layout['right'], $layout['bottom'], $ink);

    $labels = fitLabels($layout['bars'], $layout['slot'] - 4, $measure);
    foreach ($layout['bars'] as $i => $bar) {
        if ($bar['value'] > 0) {
            imagefilledrectangle($image, $bar['x1'], $bar['y'], $bar['x2'], $layout['bottom'] - 1, $blue);
        }
        ttfText($image, $font, 10, $bar['cx'], $bar['y'] - 6, $ink, (string) $bar['value'], 'center');
        $label = $labels[$i];
        foreach ($label['lines'] as $n => $line) {
            ttfText($image, $font, $label['size'], $bar['cx'], $layout['bottom'] + 18 + $n * 14, $ink, $line, 'center');
        }
    }

    [$caption, $size] = fitText(authorCaption(), $width - 30, $measure, 10, 8);
    ttfText($image, $font, $size, $width - 12, $height - 12, $muted, $caption, 'right');
    sendPng($image);
});
