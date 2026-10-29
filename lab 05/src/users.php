<?php
declare(strict_types=1);
// Вигадані навчальні користувачі: ключ — логін, запис — ім’я та bcrypt-хеш пароля.
// Хеші створено один раз командою php tools/make_hash.php; самі паролі тут не зберігаються.
return [
    'olena_k' => [
        'name' => 'Олена Коваленко',
        'password_hash' => '$2y$10$UYXiQuysPobe/HggObhBb.JQmtTHcx2RIRP4jibJ9AiM7Td4rKdaK',
    ],
    'taras_m' => [
        'name' => 'Тарас Мельник',
        'password_hash' => '$2y$10$gI5AaY2RzKuobOg4MshypuAcOS4jVB3DegWSGJfFYVnNG3LznE602',
    ],
    'iryna_99' => [
        'name' => 'Ірина Бондар',
        'password_hash' => '$2y$10$98SDXJMQrkk7fcCKCyxfZux/oI21cYipEZGyIbD2hGiziuCPYe0.K',
    ],
];
