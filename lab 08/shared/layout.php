<?php
declare(strict_types=1);

// Спільні шаблони сторінок каталогу. Формат (XML/JSON) задає STORAGE_FORMAT.

function pageStart(string $title): void
{
    header('Content-Type: text/html; charset=UTF-8');
    ?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title) ?> — <?= e(STORAGE_FORMAT) ?></title>
    <style>
        body { font-family: system-ui, sans-serif; margin: 1.5rem; max-width: 60rem; }
        nav a { margin-right: 1rem; }
        table { border-collapse: collapse; margin-top: 1rem; }
        th, td { border: 1px solid #999; padding: 0.3rem 0.6rem; text-align: left; }
        td.num { text-align: right; }
        .ok { color: #1a6b1a; font-weight: bold; }
        .error { color: #b00020; font-weight: bold; }
        .field { margin-bottom: 0.8rem; }
        .field label { display: block; font-weight: bold; }
        .field small { display: block; color: #555; }
        .field .error { display: block; font-weight: normal; }
        input[aria-invalid="true"] { border: 2px solid #b00020; }
    </style>
</head>
<body>
<nav aria-label="Розділи">
    <a href="index.php">Розклад (<?= e(STORAGE_FORMAT) ?>)</a>
    <a href="add.php">Додати рейс</a>
    <a href="<?= STORAGE_FORMAT === 'XML' ? '../json/index.php' : '../xml/index.php' ?>">Версія <?= STORAGE_FORMAT === 'XML' ? 'JSON' : 'XML' ?></a>
</nav>
<h1><?= e($title) ?></h1>
    <?php
}

function pageEnd(): void
{
    ?>
<footer><p><small>Автовокзал «Навчальний». Вигадані дані для лабораторної роботи №8, сховище: <?= e(STORAGE_FORMAT) ?>.</small></p></footer>
</body>
</html>
    <?php
}

/** Форма додавання або редагування; $raw — введені рядки, $errors — помилки полів. */
function routeForm(string $action, array $raw, array $errors, ?string $id, string $submitLabel): void
{
    // Текстові поля з inputmode/pattern, а не time/number: так помилкове введення
    // можна повернути у value без порушення HTML. Межі однаково перевіряє сервер.
    $inputs = [
        'number' => 'type="text" maxlength="10" placeholder="105-А"',
        'destination' => 'type="text" maxlength="100" placeholder="Житомир"',
        'departure' => 'type="text" inputmode="numeric" maxlength="5" pattern="([01][0-9]|2[0-3]):[0-5][0-9]" placeholder="07:45"',
        'platform' => 'type="text" inputmode="numeric" maxlength="2" pattern="[1-9][0-9]?" placeholder="3"',
        'price' => 'type="text" inputmode="numeric" maxlength="4" pattern="[1-9][0-9]{0,3}" placeholder="180"',
    ];
    $hints = [
        'number' => 'До 10 літер, цифр або дефісів.',
        'destination' => 'Від 2 до 100 символів.',
        'departure' => 'ГГ:ХХ, наприклад 07:45.',
        'platform' => sprintf('Ціле число %d–%d.', PLATFORM_MIN, PLATFORM_MAX),
        'price' => sprintf('Ціле число гривень %d–%d.', PRICE_MIN, PRICE_MAX),
    ];
    ?>
<form method="post" action="<?= e($action) ?>" novalidate>
    <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
    <?php if ($id !== null): ?>
        <input type="hidden" name="id" value="<?= e($id) ?>">
    <?php endif; ?>
    <?php foreach (ROUTE_LABELS as $field => $label): ?>
        <div class="field">
            <label for="<?= $field ?>"><?= e($label) ?></label>
            <input id="<?= $field ?>" name="<?= $field ?>" <?= $inputs[$field] ?> required
                   value="<?= e($raw[$field] ?? '') ?>"
                   aria-describedby="<?= $field ?>-hint<?= isset($errors[$field]) ? ' ' . $field . '-error' : '' ?>"
                   <?= isset($errors[$field]) ? 'aria-invalid="true"' : '' ?>>
            <small id="<?= $field ?>-hint"><?= e($hints[$field]) ?></small>
            <?php if (isset($errors[$field])): ?>
                <span class="error" id="<?= $field ?>-error"><?= e($errors[$field]) ?></span>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
    <button type="submit"><?= e($submitLabel) ?></button>
</form>
    <?php
}
