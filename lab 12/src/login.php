<?php
declare(strict_types=1);
// Вхід: перевірка логіна й пароля через PDO, збереження користувача в сесії.
require __DIR__ . '/common.php';

$login = '';
$formError = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = postString('login');
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    if (!csrfValid()) {
        http_response_code(403);
        $formError = 'Форма застаріла. Оновіть сторінку й спробуйте ще раз.';
    } elseif ($login === '' || $password === '') {
        http_response_code(422);
        $formError = 'Введіть логін і пароль.';
    } else {
        try {
            $stmt = db()->prepare('SELECT id, password_hash FROM users WHERE login = :login');
            $stmt->execute(['login' => $login]);
            $user = $stmt->fetch();
            // Однакове повідомлення для невідомого логіна й неправильного пароля.
            if ($user === false || !password_verify($password, $user['password_hash'])) {
                http_response_code(401);
                $formError = 'Невірний логін або пароль.';
            } else {
                if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
                    $update = db()->prepare('UPDATE users SET password_hash = :hash WHERE id = :id');
                    $update->execute(['hash' => password_hash($password, PASSWORD_DEFAULT), 'id' => $user['id']]);
                }
                session_regenerate_id(true); // Новий ідентифікатор сесії після входу.
                $_SESSION['user_id'] = (int) $user['id'];
                $_SESSION['csrf'] = bin2hex(random_bytes(32));
                flash('Ви увійшли до системи.');
                redirect('index.php');
            }
        } catch (PDOException $exception) {
            $formError = dbError($exception);
        }
    }
} elseif (currentUserSafe() !== null) {
    redirect('index.php');
}

/** Для GET: якщо БД недоступна, просто показуємо форму. */
function currentUserSafe(): ?array
{
    try {
        return currentUser();
    } catch (PDOException) {
        return null;
    }
}

pageStart('Вхід');
?>
<?php if ($formError !== null): ?>
    <p class="error" role="alert"><?= e($formError) ?></p>
<?php endif; ?>
<form method="post" action="login.php" novalidate>
    <?= csrfField() ?>
    <?php
    formField('login', 'Логін', 'text', $login, null, 'Логін, указаний під час реєстрації.',
        'maxlength="30" autocomplete="username" required');
    formField('password', 'Пароль', 'password', '', null, 'Пароль чутливий до регістру.',
        'autocomplete="current-password" required');
    ?>
    <button type="submit">Увійти</button>
</form>
<p>Немає облікового запису? <a href="register.php">Зареєструватися</a></p>
<?php pageEnd();
