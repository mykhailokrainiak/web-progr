<?php
// Шаблон форми входу. Очікує $errors (масив рядків), $login (рядок), $userName (рядок або null).
// Пароль у поле не повертається; усі значення екрануються.
pageHeader('Вхід', 'index.php');
?>
<p class="note">Навчальна перевірка логіна й пароля в межах одного запиту: сеанс входу не створюється.</p>
<?php if ($userName !== null): ?>
<p class="success" role="status">Вхід перевірено. Вітаємо, <?= escapeHtml($userName) ?>!</p>
<?php endif; ?>
<?php if ($errors !== []): ?>
<div class="errors" role="alert">
    <ul>
<?php foreach ($errors as $error): ?>
        <li><?= escapeHtml($error) ?></li>
<?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>
<form action="index.php" method="post">
    <p>
        <label for="login">Логін</label>
        <input id="login" name="login" type="text" required maxlength="30" autocomplete="username"
               value="<?= escapeHtml($login) ?>" aria-describedby="login-hint">
        <span id="login-hint" class="hint">3–30 символів: латинські літери, цифри, «_»</span>
    </p>
    <p>
        <label for="password">Пароль</label>
        <input id="password" name="password" type="password" required autocomplete="current-password"
               aria-describedby="password-hint">
        <span id="password-hint" class="hint">8–72 байти</span>
    </p>
    <button type="submit">Увійти</button>
</form>
<?php
pageFooter();
