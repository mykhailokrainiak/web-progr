<?php
// Шаблон форми зворотного зв’язку. Очікує $values, $fieldErrors, $formError, $summary.
// Після помилки несекретні поля повертаються екранованими; поле файла повторно не заповнюється.
pageHeader('Зворотний зв’язок', 'feedback.php');

$fieldError = static function (string $key) use ($fieldErrors): string {
    return isset($fieldErrors[$key])
        ? '<span class="field-error" id="' . $key . '-error">' . escapeHtml($fieldErrors[$key]) . '</span>'
        : '';
};
$describedBy = static fn(string $key, string $hint): string =>
    ' aria-describedby="' . $hint . (isset($fieldErrors[$key]) ? ' ' . $key . '-error' : '') . '"'
    . (isset($fieldErrors[$key]) ? ' aria-invalid="true"' : '');
?>
<?php if ($formError !== null): ?>
<p class="errors" role="alert"><?= escapeHtml($formError) ?></p>
<?php elseif ($fieldErrors !== []): ?>
<p class="errors" role="alert">Форму не опрацьовано: виправте поля з повідомленнями (<?= count($fieldErrors) ?>).</p>
<?php endif; ?>

<?php if ($summary !== null): ?>
<p class="success" role="status">Повідомлення перевірено й опрацьовано. Лист не надсилався, дані не зберігалися.</p>
<table class="summary">
    <caption>Зведення</caption>
    <tr><th scope="row">Ім’я</th><td><?= escapeHtml($summary['name']) ?></td></tr>
    <tr><th scope="row">Електронна пошта</th><td><?= escapeHtml($summary['email']) ?></td></tr>
    <tr><th scope="row">Повідомлення (<?= mb_strlen($summary['message'], 'UTF-8') ?> симв.)</th><td class="message"><?= escapeHtml($summary['message']) ?></td></tr>
    <tr><th scope="row">Вкладення</th><td>
<?php if ($summary['attachment'] === null): ?>
        Вкладення відсутнє.
<?php else: ?>
        Назва: <?= escapeHtml($summary['attachment']['name']) ?><br>
        Розмір: <?= escapeHtml(formatBytes($summary['attachment']['size'])) ?><br>
        MIME-тип: <?= escapeHtml($summary['attachment']['type'] === '' ? 'не вказано' : $summary['attachment']['type']) ?> (заявлений клієнтом)
<?php endif; ?>
    </td></tr>
</table>
<p><a href="feedback.php">Надіслати ще одне повідомлення</a></p>
<?php else: ?>
<form action="feedback.php" method="post" enctype="multipart/form-data">
    <p>
        <label for="name">Ім’я</label>
        <input id="name" name="name" type="text" required maxlength="100" autocomplete="name"
               value="<?= escapeHtml($values['name']) ?>"<?= $describedBy('name', 'name-hint') ?>>
        <span id="name-hint" class="hint">До 100 символів</span>
        <?= $fieldError('name') ?>
    </p>
    <p>
        <label for="email">Електронна пошта</label>
        <input id="email" name="email" type="email" required maxlength="254" autocomplete="email"
               value="<?= escapeHtml($values['email']) ?>"<?= $describedBy('email', 'email-hint') ?>>
        <span id="email-hint" class="hint">Наприклад, name@example.com</span>
        <?= $fieldError('email') ?>
    </p>
    <p>
        <label for="message">Повідомлення</label>
        <textarea id="message" name="message" required minlength="10" maxlength="1000"<?= $describedBy('message', 'message-hint') ?>><?= escapeHtml($values['message']) ?></textarea>
        <span id="message-hint" class="hint">Від 10 до 1000 символів</span>
        <?= $fieldError('message') ?>
    </p>
    <p>
        <label for="attachment">Вкладення (необов’язково)</label>
        <input id="attachment" name="attachment" type="file"<?= $describedBy('attachment', 'attachment-hint') ?>>
        <span id="attachment-hint" class="hint">Один файл до 1 МіБ; файл на сервері не зберігається</span>
        <?= $fieldError('attachment') ?>
    </p>
    <button type="submit">Надіслати</button>
</form>
<?php endif; ?>
<?php
pageFooter();
