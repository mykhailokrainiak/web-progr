<?php
require __DIR__ . '/bootstrap.php';

$error = null;
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    requireValidPost();
    $action = postString('action');
    if ($action === 'save') {
        $newTheme = postString('theme');
        if ($newTheme === null || !array_key_exists($newTheme, THEMES)) {
            $error = 'Невідома тема. Виберіть «Світла» або «Темна».';
        } elseif (!setcookie(THEME_COOKIE, $newTheme, cookieOptions(time() + THEME_TTL))) {
            $error = 'Не вдалося підготувати cookie теми.';
        } else {
            setFlash('Тему «' . THEMES[$newTheme] . '» збережено на 30 днів.');
            redirect('index.php');
        }
    } elseif ($action === 'reset') {
        // Видалення: минулий строк дії й ті самі path/domain, що й під час створення.
        if (!setcookie(THEME_COOKIE, '', cookieOptions(time() - 3600))) {
            $error = 'Не вдалося підготувати видалення cookie теми.';
        } else {
            setFlash('Налаштування скинуто: використовується світла тема.');
            redirect('index.php');
        }
    } else {
        $error = 'Невідома дія.';
    }
    http_response_code(str_starts_with($error, 'Не вдалося') ? 500 : 400);
} elseif (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    header('Allow: GET, POST');
    http_response_code(405);
    exit('Метод не підтримується.');
}

$theme = currentTheme();
pageStart('Налаштування оформлення', 'preferences.php');
if ($error !== null) {
    echo errorMessage($error), "\n";
}
?>
    <form method="post" action="preferences.php">
        <?= csrfField() ?>
        <fieldset>
            <legend>Тема оформлення</legend>
<?php foreach (THEMES as $value => $label): ?>
            <p>
                <input type="radio" name="theme" id="theme-<?= $value ?>" value="<?= $value ?>"<?= $value === $theme ? ' checked' : '' ?>>
                <label for="theme-<?= $value ?>"><?= e($label) ?></label>
            </p>
<?php endforeach; ?>
        </fieldset>
        <button type="submit" name="action" value="save">Зберегти тему</button>
    </form>
    <form method="post" action="preferences.php">
        <?= csrfField() ?>
        <button type="submit" name="action" value="reset">Скинути налаштування</button>
    </form>
    <p class="note">Тема зберігається в cookie <code>club_theme</code> на 30 днів (шлях <code><?= e(COOKIE_PATH) ?></code>, HttpOnly, SameSite=Lax).</p>
<?php
pageEnd();
