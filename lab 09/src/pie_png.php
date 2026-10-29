<?php
declare(strict_types=1);
// Власне доповнення до кроку 2.1: кругова діаграма diagram_png_1.php зі змінними значеннями.
// GET values=40,30,30 — від 2 до 6 цілих 0–1000 через кому; сума має бути додатною.
require __DIR__ . '/bus-common.php';

function pieValues(): array
{
    $raw = $_GET['values'] ?? '40,30,30';
    if (!is_string($raw) || !preg_match('/^\d{1,4}(,\d{1,4}){1,5}$/D', $raw)) {
        throw new InvalidArgumentException('values: від 2 до 6 цілих невід’ємних чисел через кому.');
    }
    $values = array_map('intval', explode(',', $raw));
    if (max($values) > 1000) {
        throw new InvalidArgumentException('values: кожне значення 0–1000.');
    }
    if (array_sum($values) <= 0) {
        throw new InvalidArgumentException('values: сума має бути додатною (інакше ділення на нуль).');
    }
    return $values;
}

runImage(function (): void {
    [$width, $height] = chartSize();
    $values = pieValues();
    $font = requireTtf();
    $image = canvas($width, $height);
    $palette = [[37, 99, 235], [22, 163, 74], [234, 88, 12], [147, 51, 234], [219, 39, 119], [8, 145, 178]];
    $ink = imagecolorallocate($image, 30, 41, 59);
    $diameter = min($width - 220, $height - 70);
    $cx = 30 + intdiv($diameter, 2);
    $cy = 45 + intdiv($diameter, 2);
    $sum = array_sum($values);
    $start = 0;
    $running = 0;
    foreach ($values as $i => $value) {
        $running += $value;
        $end = (int) round($running / $sum * 360);   // накопичена сума: останній сектор закінчується на 360°
        $color = imagecolorallocate($image, ...$palette[$i]);
        if ($end > $start) {
            imagefilledarc($image, $cx, $cy, $diameter, $diameter, $start, $end, $color, IMG_ARC_PIE);
        }
        $y = 60 + $i * 26;
        imagefilledrectangle($image, $width - 180, $y - 12, $width - 166, $y + 2, $color);
        $percent = round($value / $sum * 100, 1);
        ttfText($image, $font, 11, $width - 158, $y, $ink, 'Частка ' . ($i + 1) . ': ' . $value . ' (' . $percent . '%)');
        $start = $end;
    }
    ttfText($image, $font, 13, 15, 26, $ink, 'Кругова діаграма: ' . implode(' / ', $values));
    sendPng($image);
});
