<?php
declare(strict_types=1);

// Власне доповнення: читання й перевірка даних варіанта 10, спільна розкладка PNG/SVG, підписи TrueType.
require_once __DIR__ . '/common.php';
require_once __DIR__ . '/config.php';

const BUS_MAX_FILE = 64 * 1024;
const BUS_MAX_VALUE = 1000000;

/** Рядок без керівних символів, 1–$max символів після trim(); інакше null. */
function cleanText(mixed $value, int $max): ?string
{
    if (!is_string($value) || !mb_check_encoding($value, 'UTF-8')) {
        return null;
    }
    $value = trim($value);
    $length = mb_strlen($value, 'UTF-8');
    return $length >= 1 && $length <= $max && !preg_match('/\p{C}/u', $value) ? $value : null;
}

/**
 * Дані діаграми з фіксованого JSON-файла: {"title", "unit", "items": [{"label", "value"}, …]}.
 * 1–10 категорій; мітка — до 24 символів Unicode; значення — ціле 0–1000000.
 * Будь-яка проблема — RuntimeException (HTTP 500, причина — у журналі PHP).
 */
function loadBusData(): array
{
    $path = busDataFile();
    if (!is_file($path) || !is_readable($path)) {
        throw new RuntimeException("Файл даних недоступний: $path");
    }
    $size = filesize($path);
    if ($size === false || $size > BUS_MAX_FILE) {
        throw new RuntimeException('Файл даних завеликий.');
    }
    $text = file_get_contents($path);
    if ($text === false) {
        throw new RuntimeException('Не вдалося прочитати файл даних.');
    }
    try {
        $data = json_decode($text, true, 16, JSON_THROW_ON_ERROR);
    } catch (JsonException $exception) {
        throw new RuntimeException('Пошкоджений JSON: ' . $exception->getMessage(), 0, $exception);
    }
    if (!is_array($data) || array_is_list($data)) {
        throw new RuntimeException('Очікується об’єкт з полями title, unit, items.');
    }
    $title = cleanText($data['title'] ?? null, 80);
    $unit = cleanText($data['unit'] ?? null, 40);
    $items = $data['items'] ?? null;
    if ($title === null || $unit === null) {
        throw new RuntimeException('Поля title (до 80 символів) і unit (до 40) мають бути непорожніми рядками.');
    }
    if (!is_array($items) || !array_is_list($items) || count($items) < 1 || count($items) > 10) {
        throw new RuntimeException('items має бути списком з 1–10 категорій.');
    }
    $result = [];
    foreach ($items as $i => $row) {
        $label = is_array($row) ? cleanText($row['label'] ?? null, 24) : null;
        $value = is_array($row) ? ($row['value'] ?? null) : null;
        if ($label === null || !is_int($value) || $value < 0 || $value > BUS_MAX_VALUE) {
            throw new RuntimeException('Категорія №' . ($i + 1) . ': потрібні label (1–24 символи) і ціле value 0–' . BUS_MAX_VALUE . '.');
        }
        $result[] = ['label' => $label, 'value' => $value];
    }
    return ['title' => $title, 'unit' => $unit, 'items' => $result];
}

/**
 * Розкладка стовпців — та сама для PNG і SVG. Логіка barLayout() з готового прикладу,
 * але з полями для українських підписів, сітки та підпису автора.
 */
function busLayout(array $items, int $width, int $height): array
{
    $left = 64;
    $right = $width - 20;
    $top = 70;
    $bottom = $height - 78;
    $plotHeight = $bottom - $top;
    $slot = ($right - $left) / count($items);
    $max = max(array_column($items, 'value'));
    $scale = max(1, $max);                  // усі нулі — без ділення на нуль
    $bars = [];
    foreach ($items as $i => $row) {
        $x1 = (int) round($left + $i * $slot + $slot * 0.15);
        $x2 = (int) round($left + ($i + 1) * $slot - $slot * 0.15);
        $y = $bottom - (int) round($row['value'] / $scale * $plotHeight);
        $bars[] = ['x1' => $x1, 'x2' => $x2, 'cx' => (int) round(($x1 + $x2) / 2), 'y' => $y] + $row;
    }
    // Сітка: чверті шкали; підписи — округлені значення.
    $grid = [];
    foreach ([0, 1, 2, 3, 4] as $k) {
        $grid[] = ['y' => $bottom - (int) round($k / 4 * $plotHeight), 'value' => (int) round($k / 4 * $scale)];
    }
    return ['left' => $left, 'right' => $right, 'top' => $top, 'bottom' => $bottom, 'slot' => $slot,
        'max' => $max, 'scale' => $scale, 'bars' => $bars, 'grid' => $max > 0 ? $grid : [$grid[0]]];
}

/**
 * Підбирає розмір шрифту ($maxSize…$minSize), щоб текст умістився в $maxWidth;
 * якщо не вміщується й найменшим розміром — скорочує з «…». $measure(text, size) повертає ширину.
 */
function fitText(string $text, float $maxWidth, callable $measure, int $maxSize, int $minSize): array
{
    for ($size = $maxSize; $size >= $minSize; $size--) {
        if ($measure($text, $size) <= $maxWidth) {
            return [$text, $size];
        }
    }
    $chars = mb_str_split($text, 1, 'UTF-8');
    while (count($chars) > 1) {
        array_pop($chars);
        $short = rtrim(implode('', $chars)) . '…';
        if ($measure($short, $minSize) <= $maxWidth) {
            return [$short, $minSize];
        }
    }
    return ['…', $minSize];
}

/**
 * Підпис категорії під стовпцем: один рядок (кегль 11…9), два рядки з розривом після пробілу
 * чи дефіса (кегль 11…8) або скорочення з «…» (кегль 8). Повертає ['lines' => [...], 'size' => n].
 */
function fitLabel(string $text, float $maxWidth, callable $measure, int $maxSize = 11): array
{
    for ($size = $maxSize; $size >= min($maxSize, 9); $size--) {
        if ($measure($text, $size) <= $maxWidth) {
            return ['lines' => [$text], 'size' => $size];
        }
    }
    preg_match_all('/[ -]/u', $text, $breaks, PREG_OFFSET_CAPTURE);
    for ($size = min($maxSize, 11); $size >= 8; $size--) {
        foreach ($breaks[0] as [$char, $offset]) {
            $first = rtrim(substr($text, 0, $offset + ($char === '-' ? 1 : 0)));
            $second = ltrim(substr($text, $offset + 1));
            if ($first !== '' && $second !== '' && $measure($first, $size) <= $maxWidth && $measure($second, $size) <= $maxWidth) {
                return ['lines' => [$first, $second], 'size' => $size];
            }
        }
    }
    [$short, $size] = fitText($text, $maxWidth, $measure, 8, 8);
    return ['lines' => [$short], 'size' => $size];
}

/** Підписи всіх категорій одним кеглем: найменшим із потрібних окремим підписам. */
function fitLabels(array $bars, float $maxWidth, callable $measure): array
{
    $size = min(array_map(fn(array $bar): int => fitLabel($bar['label'], $maxWidth, $measure)['size'], $bars));
    return array_map(fn(array $bar): array => fitLabel($bar['label'], $maxWidth, $measure, $size), $bars);
}

/** Шрифт TrueType обов’язковий для українських підписів (без нього — контрольована помилка 500). */
function requireTtf(): string
{
    $font = ttfFont();
    if ($font === null || !function_exists('imagettftext')) {
        throw new RuntimeException('Не знайдено TrueType-шрифт або FreeType: задайте LAB09_FONT чи покладіть src/fonts/DejaVuSans.ttf.');
    }
    return $font;
}

/** Ширина тексту в GD за imagettfbbox(). */
function ttfWidth(string $font): callable
{
    return static function (string $text, int $size) use ($font): int {
        $box = imagettfbbox($size, 0, $font, $text);
        if ($box === false) {
            throw new RuntimeException('imagettfbbox() не змогла виміряти текст.');
        }
        return max($box[2], $box[4]) - min($box[0], $box[6]);
    };
}

/**
 * Кегль GD задається в пунктах при 96 dpi (imageresolution() за замовчуванням), SVG — у пікселях:
 * той самий розмір у SVG = пункти × 96 / 72.
 */
function svgFontSize(int $points): float
{
    return round($points * 96 / 72, 1);
}

/** Наближена ширина для SVG (без GD): ~0,58 em на символ шрифту DejaVu Sans / Arial. */
function svgWidth(string $text, int $size): float
{
    return mb_strlen($text, 'UTF-8') * svgFontSize($size) * 0.58;
}

/** Текст у GD: вирівнювання left|center|right відносно $x, $y — базова лінія. */
function ttfText(GdImage $image, string $font, int $size, int $x, int $y, int $color, string $text, string $align = 'left'): void
{
    $width = ttfWidth($font)($text, $size);
    $x = match ($align) {
        'center' => $x - intdiv($width, 2),
        'right' => $x - $width,
        default => $x,
    };
    if (imagettftext($image, $size, 0, $x, $y, $color, $font, $text) === false) {
        throw new RuntimeException('Не вдалося додати текст.');
    }
}
