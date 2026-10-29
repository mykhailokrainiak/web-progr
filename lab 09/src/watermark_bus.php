<?php
declare(strict_types=1);
// Самостійне завдання 3: водяний знак з українським підписом (група, вигадане ім’я, дата) на дозволених PNG.
// Логіка перевірок — за готовим watermark.php; сам готовий приклад не змінено.
//   GET  watermark_bus.php?image=bus            — PNG у відповіді (без запису на сервер)
//   GET  watermark_bus.php?image=bus&download=1 — завантаження watermark.png
//   CLI  php watermark_bus.php bus              — запис watermark-bus-output.png поруч зі сценарієм
require __DIR__ . '/bus-common.php';

const WM_MAX_BYTES = 2 * 1024 * 1024;

/** Дозволений перелік: ключ → файл. Клієнт передає лише ключ. */
function allowedImages(): array
{
    return [
        'sample' => __DIR__ . '/images/sample.png',
        'bus' => busImagesDir() . '/bus.png',
    ];
}

/** Перевірки розміру, типу, габаритів і декодування; повертає GdImage. */
function openAllowedPng(string $path): GdImage
{
    clearstatcache(true, $path);
    if (!is_file($path) || !is_readable($path)) {
        throw new RuntimeException('Зображення недоступне.');
    }
    $bytes = filesize($path);
    if ($bytes === false || $bytes > WM_MAX_BYTES) {
        throw new RuntimeException('Файл зображення більший за 2 MiB.');
    }
    $info = getimagesize($path);
    if ($info === false || $info[2] !== IMAGETYPE_PNG) {
        throw new RuntimeException('Файл не є PNG (розширення не гарантує формату).');
    }
    [$w, $h] = $info;
    if ($w < 400 || $h < 200 || $w > 2000 || $h > 2000 || $w * $h > 2000000) {
        throw new RuntimeException("Неприпустимі габарити {$w}×{$h}: від 400×200 до 2000×2000, не більше 2 млн пікселів.");
    }
    $image = imagecreatefrompng($path);   // попередження декодера runImage() перетворює на виняток
    if ($image === false) {
        throw new RuntimeException('Не вдалося декодувати PNG.');
    }
    return $image;
}

function addWatermark(GdImage $image): void
{
    $font = requireTtf();
    $measure = ttfWidth($font);
    imagealphablending($image, true);
    imagesavealpha($image, true);
    $width = imagesx($image);
    $height = imagesy($image);
    $label = 'ЛР 9 · ' . authorCaption();
    // Найбільший кегль 22…10, за якого підпис уміщується з полями по 16 px.
    [$label, $size] = fitText($label, $width - 32, $measure, 22, 10);
    $box = imagettfbbox($size, 0, $font, $label);
    $textHeight = max($box[1], $box[3]) - min($box[5], $box[7]);
    $panelHeight = $textHeight + 24;
    $panel = imagecolorallocatealpha($image, 15, 23, 42, 35);
    $white = imagecolorallocate($image, 255, 255, 255);
    imagefilledrectangle($image, 0, $height - $panelHeight, $width - 1, $height - 1, $panel);
    ttfText($image, $font, $size, 16, $height - 12 - max($box[1], $box[3]), $white, $label);
}

runImage(function (): void {
    requireGd();
    $allowed = allowedImages();
    if (PHP_SAPI === 'cli') {
        $key = $GLOBALS['argv'][1] ?? 'bus';
        if (!isset($allowed[$key])) {
            throw new InvalidArgumentException('Невідоме зображення. Дозволено: ' . implode(', ', array_keys($allowed)));
        }
        $image = openAllowedPng($allowed[$key]);
        addWatermark($image);
        $bytes = pngBytes($image);
        $target = __DIR__ . '/watermark-bus-output.png';
        if (file_put_contents($target, $bytes, LOCK_EX) !== strlen($bytes)) {
            throw new RuntimeException('Не вдалося зберегти результат.');
        }
        echo "Збережено watermark-bus-output.png ({$key}, " . strlen($bytes) . " байт)\n";
        return;
    }
    $key = $_GET['image'] ?? 'bus';
    if (!is_string($key) || !isset($allowed[$key])) {
        throw new InvalidArgumentException('Невідоме зображення.');
    }
    $download = queryInt('download', 0, 0, 1) === 1;
    $image = openAllowedPng($allowed[$key]);
    addWatermark($image);
    sendPng($image, $download);
});
