<?php
declare(strict_types=1);
// Власне доповнення: HTML-сторінка з діаграмами PNG/SVG, таблицями вихідних даних і водяним знаком.
require __DIR__ . '/src/bus-common.php';

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$gd = extension_loaded('gd') ? gd_info() : null;
$font = ttfFont();
try {
    $given = loadChartData();
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    $given = null;
}
try {
    $bus = loadBusData();
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    $bus = null;
}
?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ЛР 9. Обробка графіки. Бібліотека GD</title>
    <style>
        body { font-family: "Segoe UI", Arial, sans-serif; margin: 1.5rem auto; max-width: 70rem; padding: 0 1rem; color: #1e293b; line-height: 1.45; }
        h1 { font-size: 1.5rem; } h2 { font-size: 1.2rem; margin-top: 2rem; border-bottom: 2px solid #2563eb; }
        .pair { display: flex; flex-wrap: wrap; gap: 1rem; align-items: flex-start; }
        figure { margin: 0; } figcaption { font-size: 0.9rem; color: #475569; }
        img { border: 1px solid #cbd5e1; max-width: 100%; height: auto; }
        table { border-collapse: collapse; margin: 0.6rem 0; } th, td { border: 1px solid #94a3b8; padding: 0.2rem 0.6rem; text-align: left; }
        td.num { text-align: right; } .error { color: #b91c1c; font-weight: bold; } .env { font-size: 0.95rem; }
    </style>
</head>
<body>
<h1>ЛР 9. Обробка графіки. Бібліотека GD</h1>
<p class="env">PHP <?= h(PHP_VERSION) ?>;
    GD: <?= $gd === null ? 'не завантажено' : h($gd['GD Version']) . ', PNG: ' . ($gd['PNG Support'] ? 'так' : 'ні') . ', FreeType: ' . ($gd['FreeType Support'] ? 'так' : 'ні') ?>;
    шрифт TrueType: <?= $font === null ? 'не знайдено' : h(basename($font)) ?>.</p>

<h2>1. Готові приклади: PNG і SVG з chart-data.json</h2>
<div class="pair">
    <figure>
        <img src="src/diagram_png_2.php?width=640&amp;height=400" width="640" height="400"
             alt="Активності клубу: A — 20, B — 50, C — 30, D — 80, E — 40">
        <figcaption>PNG (GD), diagram_png_2.php</figcaption>
    </figure>
    <figure>
        <img src="src/diagram_svg.php?width=640&amp;height=400" width="640" height="400"
             alt="Ті самі дані у векторній стовпчастій діаграмі">
        <figcaption>SVG, diagram_svg.php</figcaption>
    </figure>
</div>
<?php if ($given === null): ?>
<p class="error">Дані chart-data.json недоступні.</p>
<?php else: ?>
<table>
    <caption>Вихідні дані chart-data.json</caption>
    <thead><tr><th scope="col">Категорія</th><th scope="col">Значення</th></tr></thead>
    <tbody>
<?php foreach ($given as $row): ?>
        <tr><td><?= h($row['label']) ?></td><td class="num"><?= (int) $row['value'] ?></td></tr>
<?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>
<figure>
    <img src="src/diagram_png_1.php" width="640" height="400" alt="Кругова діаграма з трьома частками: 40%, 30% і 30%">
    <figcaption>Кругова діаграма diagram_png_1.php (40 / 30 / 30)</figcaption>
</figure>

<h2>2. Власні діаграми з bus-data.json (варіант 10 — автовокзал)</h2>
<?php if ($bus === null): ?>
<p class="error">Дані bus-data.json недоступні або некоректні; причину записано в журнал PHP.</p>
<?php else:
    $alt = $bus['title'] . ', ' . $bus['unit'] . ': '
        . implode(', ', array_map(fn(array $row): string => $row['label'] . ' — ' . $row['value'], $bus['items'])); ?>
<div class="pair">
    <figure>
        <img src="src/bus_png.php?width=640&amp;height=400" width="640" height="400" alt="<?= h($alt) ?>">
        <figcaption>PNG (GD, TrueType), bus_png.php</figcaption>
    </figure>
    <figure>
        <img src="src/bus_svg.php?width=640&amp;height=400" width="640" height="400" alt="Ті самі дані у векторній діаграмі SVG">
        <figcaption>SVG, bus_svg.php</figcaption>
    </figure>
</div>
<table>
    <caption><?= h($bus['title']) ?> (<?= h($bus['unit']) ?>)</caption>
    <thead><tr><th scope="col">Напрямок</th><th scope="col">Рейсів на тиждень</th></tr></thead>
    <tbody>
<?php foreach ($bus['items'] as $row): ?>
        <tr><td><?= h($row['label']) ?></td><td class="num"><?= $row['value'] ?></td></tr>
<?php endforeach; ?>
    </tbody>
</table>
<p>Інші розміри: <a href="src/bus_png.php?width=400&amp;height=300">PNG 400×300</a>,
    <a href="src/bus_png.php?width=1200&amp;height=800">PNG 1200×800</a>,
    <a href="src/bus_svg.php?width=400&amp;height=300">SVG 400×300</a>,
    <a href="src/bus_svg.php?width=1200&amp;height=800">SVG 1200×800</a>.</p>
<?php endif; ?>

<h2>3. Водяний знак</h2>
<div class="pair">
    <figure>
        <img src="src/watermark_bus.php?image=bus" width="800" height="450"
             alt="Малюнок автобуса біля автовокзалу з підписом: ЛР 9, група, ім’я автора, дата">
        <figcaption>Власне зображення images/bus.png, watermark_bus.php (<a href="src/watermark_bus.php?image=bus&amp;download=1">завантажити PNG</a>)</figcaption>
    </figure>
    <figure>
        <img src="src/watermark.php?image=sample" width="640" height="360" alt="Навчальне зображення з геометричними фігурами та підписом">
        <figcaption>Готовий приклад watermark.php (<a href="src/watermark.php?image=sample&amp;download=1">завантажити PNG</a>)</figcaption>
    </figure>
</div>

<h2>4. Кругова діаграма зі зміненими значеннями</h2>
<figure>
    <img src="src/pie_png.php?values=50,25,15,10" width="640" height="400" alt="Кругова діаграма: 50%, 25%, 15% і 10%">
    <figcaption>pie_png.php?values=50,25,15,10</figcaption>
</figure>
</body>
</html>
