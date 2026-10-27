-- Власне доповнення (крок 8). Варіант 10: розклад маршрутів автовокзалу.
-- Виконайте один раз у навчальній базі lab11 (вкладка SQL або «Імпорт» у phpMyAdmin).
-- Усі дані вигадані.
CREATE TABLE bus_routes (
    id             INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    route_number   VARCHAR(10)      NOT NULL COMMENT 'Номер рейсу: 1–10 літер, цифр, дефісів',
    destination    VARCHAR(100)     NOT NULL COMMENT 'Пункт призначення: 2–100 символів',
    departure_time TIME             NOT NULL COMMENT 'Час відправлення 00:00–23:59',
    platform       TINYINT UNSIGNED NOT NULL COMMENT 'Платформа 1–20',
    price          DECIMAL(7,2)     NOT NULL COMMENT 'Ціна квитка, грн: 1.00–5000.00',
    created_at     TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    -- Той самий рейс не може відправлятися двічі в один час.
    UNIQUE KEY uq_bus_routes_number_time (route_number, departure_time),
    KEY idx_bus_routes_destination (destination),
    CONSTRAINT chk_bus_routes_platform CHECK (platform BETWEEN 1 AND 20),
    CONSTRAINT chk_bus_routes_price CHECK (price BETWEEN 1.00 AND 5000.00)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO bus_routes (route_number, destination, departure_time, platform, price) VALUES
    ('7',    'Фастів',  '06:40',  1,  '85.50'),
    ('88-К', 'Умань',   '11:25',  6, '390.00'),
    ('301',  'Черкаси', '16:05',  4, '300.00'),
    ('19',   'Вінниця', '18:20',  2, '260.00'),
    ('512',  'Львів',   '22:15', 10, '780.00');
