<?php
declare(strict_types=1);

// Власне доповнення (завдання для самостійного виконання): фільтр q у GET списку завдань.

const SEARCH_MAX_LENGTH = 40;

/**
 * Пошуковий рядок із query-параметра q.
 * null — пошуку немає (параметр відсутній або порожній після trim()).
 */
function searchQuery(): ?string
{
    $raw = queryString('q'); // Масив q[] → 400 «Параметр q має бути рядком».
    if ($raw === null) {
        return null;
    }
    if (!mb_check_encoding($raw, 'UTF-8')) {
        throw new ApiError(400, 'q має бути рядком UTF-8.');
    }
    $query = trim($raw);
    if ($query === '') {
        return null;
    }
    if (mb_strlen($query, 'UTF-8') > SEARCH_MAX_LENGTH) {
        throw new ApiError(400, 'q після trim() має містити не більше ' . SEARCH_MAX_LENGTH . ' символів.');
    }
    return $query;
}

/** Чи містить title підрядок q без урахування регістру (українські літери теж). */
function titleMatches(string $title, string $query): bool
{
    return str_contains(mb_strtolower($title, 'UTF-8'), mb_strtolower($query, 'UTF-8'));
}
