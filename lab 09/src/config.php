<?php
declare(strict_types=1);

// Власне доповнення: налаштування середовища для діаграм варіанта 10 і водяного знака.
// Шляхи задаються тут або змінними середовища сервера, але ніколи — параметрами HTTP-запиту.

const CAPTION_GROUP = '451 група';
const CAPTION_AUTHOR = 'Тарас Маршрутенко';   // вигадане навчальне ім’я автора

date_default_timezone_set('Europe/Kyiv');

/** Файл даних діаграми (фіксований серверний шлях; LAB09_DATA — лише для тестів на копіях). */
function busDataFile(): string
{
    return getenv('LAB09_DATA') ?: __DIR__ . '/bus-data.json';
}

/** Каталог власних зображень для водяного знака (LAB09_IMAGES — лише для тестів на копіях). */
function busImagesDir(): string
{
    return getenv('LAB09_IMAGES') ?: __DIR__ . '/images';
}

/**
 * TrueType-шрифт із кирилицею. Якщо задано LAB09_FONT, використовується лише він;
 * інакше — перший наявний із переліку. null — шрифту немає.
 */
function ttfFont(): ?string
{
    $configured = getenv('LAB09_FONT');
    $candidates = is_string($configured) && $configured !== ''
        ? [$configured]
        : [
            __DIR__ . '/fonts/DejaVuSans.ttf',
            'C:/Windows/Fonts/DejaVuSans.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
            'C:/Windows/Fonts/arial.ttf',
        ];
    foreach ($candidates as $path) {
        if (is_file($path) && is_readable($path)) {
            return $path;
        }
    }
    return null;
}

/** Підпис автора: група, вигадане ім’я, дата. */
function authorCaption(): string
{
    return CAPTION_GROUP . ' · ' . CAPTION_AUTHOR . ' · ' . date('d.m.Y');
}
