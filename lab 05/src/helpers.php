<?php
declare(strict_types=1);
// Спільні функції: екранування (з заготовки index001.php), шапка сторінок, повідомлення про ліміти.

function escapeHtml(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function isPost(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
}

/** Значення ini на кшталт «40M» у байтах. */
function iniBytes(string $key): int
{
    $value = trim((string) ini_get($key));
    $number = (int) $value;
    return match (strtoupper(substr($value, -1))) {
        'G' => $number * 1024 ** 3,
        'M' => $number * 1024 ** 2,
        'K' => $number * 1024,
        default => $number,
    };
}

/**
 * POST дійшов до PHP, але $_POST і $_FILES порожні при ненульовому тілі —
 * найімовірніше, перевищено post_max_size (PHP відкидає все тіло).
 */
function postBodyLost(): bool
{
    return isPost() && $_POST === [] && $_FILES === [] && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0;
}

function formatBytes(int $bytes): string
{
    return $bytes >= 1024 * 1024
        ? number_format($bytes / 1024 / 1024, 2, ',', ' ') . ' МіБ (' . number_format($bytes, 0, ',', ' ') . ' байт)'
        : number_format($bytes, 0, ',', ' ') . ' байт';
}

function pageHeader(string $title, string $active): void
{
    $menu = ['index.php' => 'Вхід', 'feedback.php' => 'Зворотний зв’язок'];
    ?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= escapeHtml($title) ?> — ЛР 5</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<nav aria-label="Розділи">
<?php foreach ($menu as $href => $label): ?>
    <a href="<?= $href ?>"<?= $href === $active ? ' aria-current="page"' : '' ?>><?= escapeHtml($label) ?></a>
<?php endforeach; ?>
</nav>
<main>
<h1><?= escapeHtml($title) ?></h1>
<?php
}

function pageFooter(): void
{
    ?>
</main>
</body>
</html>
<?php
}
