-- Лабораторна робота №12. Варіант 10: розклад маршрутів автовокзалу.
-- Імпорт: phpMyAdmin → «Імпорт» або  mysql -u root < sql/schema.sql
-- Усі дані вигадані.

CREATE DATABASE IF NOT EXISTS lab12_bus
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE lab12_bus;

DROP TABLE IF EXISTS routes;
DROP TABLE IF EXISTS users;

-- Користувачі: ідентифікатор, логін, ПІБ, email, пароль (лише хеш).
CREATE TABLE users (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    login         VARCHAR(30)  NOT NULL,
    full_name     VARCHAR(100) NOT NULL,
    email         VARCHAR(100) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_login (login),
    UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Рейси автовокзалу.
CREATE TABLE routes (
    id           INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    number       VARCHAR(10)       NOT NULL COMMENT 'Номер рейсу',
    destination  VARCHAR(100)      NOT NULL COMMENT 'Пункт призначення',
    departure    TIME              NOT NULL COMMENT 'Час відправлення',
    duration_min SMALLINT UNSIGNED NOT NULL COMMENT 'Тривалість поїздки, хв',
    platform     TINYINT UNSIGNED  NOT NULL COMMENT 'Платформа 1–20',
    price        INT UNSIGNED      NOT NULL COMMENT 'Ціна квитка, грн',
    carrier      VARCHAR(100)      NOT NULL COMMENT 'Перевізник',
    days         VARCHAR(50)       NOT NULL COMMENT 'Дні курсування',
    PRIMARY KEY (id),
    KEY idx_routes_departure (departure),
    CONSTRAINT chk_routes_platform CHECK (platform BETWEEN 1 AND 20),
    CONSTRAINT chk_routes_price CHECK (price BETWEEN 1 AND 5000)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO routes (number, destination, departure, duration_min, platform, price, carrier, days) VALUES
    ('105',   'Житомир',               '07:45', 150, 3, 180, 'ТОВ «Полісся-Авто»',    'щодня'),
    ('212-А', 'Біла Церква',           '09:10',  95, 5, 120, 'ФОП Коваленко О. П.',   'пн–пт'),
    ('88-К',  'Умань',                 '11:25', 210, 6, 390, 'ТОВ «Черкаситранс»',    'щодня'),
    ('47',    'Кам''янець-Подільський', '14:30', 420, 8, 450, 'ПрАТ «Поділля-Експрес»', 'пт, нд'),
    ('301',   'Черкаси',               '16:05', 200, 4, 300, 'ТОВ «Черкаситранс»',    'щодня'),
    ('19',    'Вінниця',               '18:20', 240, 2, 260, 'ФОП Мельник І. В.',     'сб, нд');
