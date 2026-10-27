-- Власне доповнення (крок 1.4). Виконайте обліковим записом адміністратора (наприклад, у вкладці SQL phpMyAdmin).
-- Замініть ЗАМІНІТЬ_ПАРОЛЬ на власний пароль; не зберігайте справжній пароль у репозиторії.
CREATE DATABASE IF NOT EXISTS lab11 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE USER 'lab11_app'@'localhost' IDENTIFIED BY 'ЗАМІНІТЬ_ПАРОЛЬ';
CREATE USER 'lab11_app'@'127.0.0.1' IDENTIFIED BY 'ЗАМІНІТЬ_ПАРОЛЬ';

-- Лише операції з даними: структуру змінює адміністратор.
GRANT SELECT, INSERT, UPDATE, DELETE ON lab11.* TO 'lab11_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON lab11.* TO 'lab11_app'@'127.0.0.1';
