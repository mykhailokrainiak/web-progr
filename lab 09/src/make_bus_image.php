<?php
declare(strict_types=1);
// Власне доповнення: генератор власного навчального PNG images/bus.png (800×450) — автобус біля автовокзалу.
// Запуск лише з термінала: php make_bus_image.php  (замінює тільки images/bus.png)
require __DIR__ . '/bus-common.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(405);
    exit('Запустіть php make_bus_image.php у терміналі.');
}
runImage(function (): void {
    $image = canvas(800, 450);
    $rgb = static fn(int $r, int $g, int $b): int => imagecolorallocate($image, $r, $g, $b);
    // Небо з градієнтом, сонце, будівля автовокзалу, дорога.
    for ($y = 0; $y < 300; $y++) {
        imageline($image, 0, $y, 799, $y, $rgb(186 - intdiv($y, 6), 220 - intdiv($y, 8), 250));
    }
    imagefilledellipse($image, 690, 70, 70, 70, $rgb(253, 224, 71));
    imagefilledrectangle($image, 60, 120, 470, 300, $rgb(226, 232, 240));
    imagefilledrectangle($image, 60, 105, 470, 125, $rgb(71, 85, 105));
    for ($x = 85; $x < 450; $x += 75) {
        imagefilledrectangle($image, $x, 150, $x + 45, 200, $rgb(125, 211, 252));
    }
    imagefilledrectangle($image, 0, 300, 799, 449, $rgb(71, 85, 105));
    for ($x = 20; $x < 800; $x += 90) {
        imagefilledrectangle($image, $x, 372, $x + 50, 378, $rgb(248, 250, 252));
    }
    // Автобус.
    imagefilledrectangle($image, 330, 215, 740, 335, $rgb(234, 179, 8));
    imagefilledrectangle($image, 330, 300, 740, 312, $rgb(202, 138, 4));
    for ($x = 350; $x < 660; $x += 62) {
        imagefilledrectangle($image, $x, 232, $x + 48, 275, $rgb(30, 64, 175));
    }
    imagefilledrectangle($image, 675, 232, 725, 300, $rgb(30, 64, 175));
    foreach ([400, 660] as $x) {
        imagefilledellipse($image, $x, 338, 54, 54, $rgb(15, 23, 42));
        imagefilledellipse($image, $x, 338, 22, 22, $rgb(148, 163, 184));
    }
    $font = requireTtf();
    ttfText($image, $font, 20, 265, 98, $rgb(15, 23, 42), 'АВТОВОКЗАЛ', 'center');
    ttfText($image, $font, 13, 535, 296, $rgb(15, 23, 42), 'Черкаси — Київ', 'center');

    $bytes = pngBytes($image);
    $target = busImagesDir() . '/bus.png';
    if (file_put_contents($target, $bytes, LOCK_EX) !== strlen($bytes)) {
        throw new RuntimeException('Не вдалося зберегти images/bus.png.');
    }
    echo "Створено images/bus.png (800 x 450).\n";
});
