<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Лабораторна робота 5 — вхід</title>
</head>
<body>
    <h1>Вхід</h1>
    <!-- Крок 2: форма з методички без змін; обробник — окремий login.php. -->
    <form action="login.php" method="post">
        <p>
            <label for="login">Логін</label>
            <input id="login" name="login" type="text" required>
        </p>
        <p>
            <label for="password">Пароль</label>
            <input id="password" name="password" type="password" required>
        </p>
        <button type="submit">Увійти</button>
    </form>
    <p><a href="query.php">Тимчасова GET-форма для порівняння</a></p>
</body>
</html>
