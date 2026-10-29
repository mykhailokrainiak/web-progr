<?php
declare(strict_types=1);
// Тимчасова GET-форма з нейтральним полем query: значення потрапляє в URL (рядок запиту), а не в тіло.
$query = $_GET['query'] ?? null;
$shown = is_string($query) ? trim($query) : null;
?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Лабораторна робота 5 — GET-форма</title>
</head>
<body>
    <h1>Пошук (GET)</h1>
    <form action="query.php" method="get">
        <p>
            <label for="query">Запит</label>
            <input id="query" name="query" type="search" value="<?= htmlspecialchars($shown ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
        </p>
        <button type="submit">Шукати</button>
    </form>
<?php if ($shown !== null && $shown !== ''): ?>
    <p role="status">Отримано з $_GET['query']: <?= htmlspecialchars($shown, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
<?php elseif ($query !== null && !is_string($query)): ?>
    <p role="alert">Параметр query має бути рядком.</p>
<?php endif; ?>
    <p><a href="index.php">До POST-форми входу</a></p>
</body>
</html>
