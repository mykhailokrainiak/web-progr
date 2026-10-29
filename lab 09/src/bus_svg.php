<?php
declare(strict_types=1);
// Самостійне завдання 2: та сама діаграма у SVG (ті самі дані, масштаб і порядок категорій; GD не потрібне).
require __DIR__ . '/bus-common.php';

runImage(function (): void {
    [$width, $height] = chartSize();
    $data = loadBusData();
    $layout = busLayout($data['items'], $width, $height);
    $measure = static fn(string $text, int $size): float => svgWidth($text, $size);
    $text = static fn(int|float $x, int|float $y, string $value, int $size, string $extra = ''): string =>
        '<text x="' . $x . '" y="' . $y . '" font-size="' . svgFontSize($size) . '"' . $extra . '>' . xmlText($value) . '</text>';

    $summary = implode('; ', array_map(fn(array $row): string => $row['label'] . ' — ' . $row['value'], $data['items']));
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $width . ' ' . $height . '" width="' . $width
        . '" height="' . $height . '" role="img" aria-labelledby="title desc">';
    $svg .= '<title id="title">' . xmlText($data['title'] . ' (' . $data['unit'] . ')') . '</title>';
    $svg .= '<desc id="desc">' . xmlText('Стовпчаста діаграма. ' . $summary . '.') . '</desc>';
    $svg .= '<rect width="100%" height="100%" fill="white"/>';
    $svg .= '<g font-family="DejaVu Sans, Arial, sans-serif" fill="#1e293b">';

    [$title, $size] = fitText($data['title'], $width - 30, $measure, 15, 9);
    $svg .= $text(15, 28, $title, $size, ' font-weight="bold"');
    [$unit, $size] = fitText('Одиниця: ' . $data['unit'], $width - 30, $measure, 11, 8);
    $svg .= $text(15, 50, $unit, $size, ' fill="#5a687d"');

    foreach ($layout['grid'] as $line) {
        if ($line['y'] !== $layout['bottom']) {
            $svg .= '<line x1="' . ($layout['left'] + 1) . '" y1="' . $line['y'] . '" x2="' . $layout['right'] . '" y2="' . $line['y'] . '" stroke="#e2e8f0"/>';
        }
        $svg .= '<line x1="' . ($layout['left'] - 4) . '" y1="' . $line['y'] . '" x2="' . $layout['left'] . '" y2="' . $line['y'] . '" stroke="#1e293b"/>';
        $svg .= $text($layout['left'] - 8, $line['y'] + 4, (string) $line['value'], 10, ' text-anchor="end"');
    }
    $svg .= '<path d="M ' . $layout['left'] . ' ' . ($layout['top'] - 6) . ' V ' . $layout['bottom'] . ' H ' . $layout['right']
        . '" fill="none" stroke="#1e293b"/>';

    $labels = fitLabels($layout['bars'], $layout['slot'] - 4, $measure);
    foreach ($layout['bars'] as $i => $bar) {
        $svg .= '<g><title>' . xmlText($bar['label'] . ': ' . $bar['value']) . '</title>';
        $svg .= '<rect x="' . $bar['x1'] . '" y="' . $bar['y'] . '" width="' . ($bar['x2'] - $bar['x1'] + 1)
            . '" height="' . ($layout['bottom'] - $bar['y']) . '" fill="#2563eb"/></g>';
        $svg .= $text($bar['cx'], $bar['y'] - 6, (string) $bar['value'], 10, ' text-anchor="middle"');
        $label = $labels[$i];
        foreach ($label['lines'] as $n => $line) {
            $svg .= $text($bar['cx'], $layout['bottom'] + 18 + $n * 14, $line, $label['size'], ' text-anchor="middle"');
        }
    }
    [$caption, $size] = fitText(authorCaption(), $width - 30, $measure, 10, 8);
    $svg .= $text($width - 12, $height - 12, $caption, $size, ' text-anchor="end" fill="#5a687d"');
    $svg .= '</g></svg>';

    header('Content-Type: image/svg+xml; charset=UTF-8');
    header('X-Content-Type-Options: nosniff');
    echo $svg;
});
