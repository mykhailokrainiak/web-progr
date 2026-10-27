-- Власне доповнення (крок 9). phpMyAdmin 5.2.3 під час експорту не переносить CHECK-обмеження
-- таблиці bus_routes (у дампі вони відсутні). Після імпорту export/lab11.sql в іншу базу
-- виконайте цей файл у ній, щоб структура повністю збіглася з оригіналом.
ALTER TABLE bus_routes
    ADD CONSTRAINT chk_bus_routes_platform CHECK (platform BETWEEN 1 AND 20),
    ADD CONSTRAINT chk_bus_routes_price CHECK (price BETWEEN 1.00 AND 5000.00);
