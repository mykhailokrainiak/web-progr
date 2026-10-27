<?php
declare(strict_types=1);
// Список користувачів — лише для авторизованих.
require __DIR__ . '/common.php';

$users = [];
$error = null;
try {
    $me = requireLogin();
    $users = db()->query('SELECT id, login, full_name, email, created_at FROM users ORDER BY id')->fetchAll();
} catch (PDOException $exception) {
    $error = dbError($exception);
}

pageStart('Користувачі');
?>
<?php if ($error !== null): ?>
    <p class="error" role="alert"><?= e($error) ?></p>
<?php else: ?>
    <table>
        <caption>Зареєстровані користувачі (<?= count($users) ?>). Паролі зберігаються лише як хеш і не показуються.</caption>
        <thead>
        <tr><th scope="col">ID</th><th scope="col">Логін</th><th scope="col">ПІБ</th><th scope="col">Email</th><th scope="col">Зареєстровано</th></tr>
        </thead>
        <tbody>
        <?php foreach ($users as $user): ?>
            <tr<?= (int) $user['id'] === (int) $me['id'] ? ' class="ok"' : '' ?>>
                <td class="num"><?= (int) $user['id'] ?></td>
                <td><?= e($user['login']) ?></td>
                <td><?= e($user['full_name']) ?></td>
                <td><?= e($user['email']) ?></td>
                <td><?= e($user['created_at']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
<?php pageEnd();
