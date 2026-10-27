<?php
declare(strict_types=1);
// Реєстрація нового користувача: форма й обробка в одному файлі.
require __DIR__ . '/common.php';

$values = ['login' => '', 'full_name' => '', 'email' => ''];
$errors = [];
$formError = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach (array_keys($values) as $field) {
        $values[$field] = postString($field);
    }
    // Пароль не обрізаємо й не повертаємо у форму.
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    $confirm = is_string($_POST['password_confirm'] ?? null) ? $_POST['password_confirm'] : '';

    if (!preg_match('/\A[A-Za-z0-9_]{3,30}\z/', $values['login'])) {
        $errors['login'] = 'Логін: 3–30 латинських літер, цифр або «_».';
    }
    $nameLength = mb_strlen($values['full_name'], 'UTF-8');
    if ($nameLength < 5 || $nameLength > 100
        || !preg_match("/\\A\\p{L}+(?:[ '’ʼ-]\\p{L}+)+\\z/u", $values['full_name'])) {
        $errors['full_name'] = 'ПІБ: щонайменше два слова з літер, 5–100 символів.';
    }
    if (strlen($values['email']) > 100 || filter_var($values['email'], FILTER_VALIDATE_EMAIL) === false) {
        $errors['email'] = 'Вкажіть коректну адресу email (до 100 символів).';
    }
    if (mb_strlen($password, 'UTF-8') < 8 || strlen($password) > 72) {
        $errors['password'] = 'Пароль: від 8 символів (не більше 72 байтів).';
    } elseif (!hash_equals($password, $confirm)) {
        $errors['password_confirm'] = 'Паролі не збігаються.';
    }

    if (!csrfValid()) {
        http_response_code(403);
        $formError = 'Форма застаріла. Оновіть сторінку й спробуйте ще раз.';
    } elseif ($errors !== []) {
        http_response_code(422);
        $formError = 'Перевірте виділені поля.';
    } else {
        try {
            $stmt = db()->prepare('SELECT login, email FROM users WHERE login = :login OR email = :email');
            $stmt->execute(['login' => $values['login'], 'email' => $values['email']]);
            foreach ($stmt->fetchAll() as $taken) {
                if (strcasecmp($taken['login'], $values['login']) === 0) {
                    $errors['login'] = 'Такий логін уже зайнятий.';
                }
                if (strcasecmp($taken['email'], $values['email']) === 0) {
                    $errors['email'] = 'Цей email уже зареєстровано.';
                }
            }
            if ($errors === []) {
                $stmt = db()->prepare('INSERT INTO users (login, full_name, email, password_hash)
                                       VALUES (:login, :full_name, :email, :password_hash)');
                $stmt->execute([
                    'login' => $values['login'],
                    'full_name' => $values['full_name'],
                    'email' => $values['email'],
                    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                ]);
                flash('Реєстрацію завершено. Увійдіть зі своїм логіном і паролем.');
                redirect('login.php');
            }
            http_response_code(409);
            $formError = 'Перевірте виділені поля.';
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') { // Одночасна реєстрація того самого логіна/email.
                http_response_code(409);
                $formError = 'Логін або email уже зайнятий.';
            } else {
                $formError = dbError($exception);
            }
        }
    }
}

pageStart('Реєстрація');
?>
<?php if ($formError !== null): ?>
    <p class="error" role="alert"><?= e($formError) ?></p>
<?php endif; ?>
<form method="post" action="register.php" novalidate>
    <?= csrfField() ?>
    <?php
    formField('login', 'Логін', 'text', $values['login'], $errors['login'] ?? null,
        '3–30 латинських літер, цифр або «_».', 'maxlength="30" autocomplete="username" required');
    formField('full_name', 'ПІБ', 'text', $values['full_name'], $errors['full_name'] ?? null,
        'Наприклад: Коваленко Ірина Петрівна.', 'maxlength="100" autocomplete="name" required');
    formField('email', 'Email', 'email', $values['email'], $errors['email'] ?? null,
        'Наприклад: iryna@example.com.', 'maxlength="100" autocomplete="email" required');
    formField('password', 'Пароль', 'password', '', $errors['password'] ?? null,
        'Не менше 8 символів.', 'autocomplete="new-password" required');
    formField('password_confirm', 'Повторіть пароль', 'password', '', $errors['password_confirm'] ?? null,
        'Введіть пароль ще раз.', 'autocomplete="new-password" required');
    ?>
    <button type="submit">Зареєструватися</button>
</form>
<p>Уже маєте обліковий запис? <a href="login.php">Увійти</a></p>
<?php pageEnd();
