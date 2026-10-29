<?php
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/events.php';

$theme = currentTheme();
$count = count(favorites());
$ended = ($_GET['ended'] ?? null) === '1';

pageStart('Панель клубу', 'index.php');
?>
<?php if ($ended): ?>
    <p class="flash" role="status">Сеанс завершено: список обраного очищено, створено нову сесію. Тема й історія відвідувань збережені.</p>
<?php endif; ?>
    <p>Вітаємо на панелі студентського клубу. Тут можна вибрати оформлення, переглянути історію відвідувань і скласти список заходів, які вас цікавлять.</p>
    <dl class="summary">
        <dt>Поточна тема</dt>
        <dd><?= e(THEMES[$theme]) ?> (<code><?= $theme ?></code>, cookie <code>club_theme</code>)</dd>
        <dt>Обраних заходів у сесії</dt>
        <dd id="favorites-count"><?= $count ?> із <?= count(EVENTS) ?></dd>
    </dl>
    <ul class="links">
        <li><a href="preferences.php">Змінити тему</a></li>
        <li><a href="visits.php">Історія відвідувань</a></li>
        <li><a href="favorites.php">Каталог заходів і обране</a></li>
    </ul>
    <form method="post" action="end_session.php">
        <?= csrfField() ?>
        <button type="submit">Завершити сеанс</button>
    </form>
<?php
pageEnd();
