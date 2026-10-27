-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Хост: 127.0.0.1:3306
-- Час створення: Жов 27 2026 р., 14:49
-- Версія сервера: 10.11.19-MariaDB
-- Версія PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- База даних: `lab11`
--

-- --------------------------------------------------------

--
-- Структура таблиці `bus_routes`
--

CREATE TABLE `bus_routes` (
  `id` int(10) UNSIGNED NOT NULL,
  `route_number` varchar(10) NOT NULL COMMENT 'Номер рейсу: 1–10 літер, цифр, дефісів',
  `destination` varchar(100) NOT NULL COMMENT 'Пункт призначення: 2–100 символів',
  `departure_time` time NOT NULL COMMENT 'Час відправлення 00:00–23:59',
  `platform` tinyint(3) UNSIGNED NOT NULL COMMENT 'Платформа 1–20',
  `price` decimal(7,2) NOT NULL COMMENT 'Ціна квитка, грн: 1.00–5000.00',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ;

--
-- Дамп даних таблиці `bus_routes`
--

INSERT INTO `bus_routes` (`id`, `route_number`, `destination`, `departure_time`, `platform`, `price`, `created_at`) VALUES
(1, '7', 'Фастів', '06:40:00', 1, 85.50, '2026-10-27 13:43:16'),
(2, '88-К', 'Умань', '11:25:00', 6, 390.00, '2026-10-27 13:43:16'),
(3, '301', 'Черкаси', '16:05:00', 4, 300.00, '2026-10-27 13:43:16'),
(4, '19', 'Вінниця', '18:20:00', 2, 260.00, '2026-10-27 13:43:16'),
(5, '512', 'Львів', '22:15:00', 10, 780.00, '2026-10-27 13:43:16'),
(14, '105', 'Житомир', '07:45:00', 3, 180.00, '2026-10-27 13:47:31'),
(15, '212-А', 'Біла Церква', '09:10:00', 5, 120.00, '2026-10-27 13:47:31'),
(16, '47', 'Кам\'янець-Подільський', '14:30:00', 8, 450.00, '2026-10-27 13:47:31');

-- --------------------------------------------------------

--
-- Структура таблиці `products`
--

CREATE TABLE `products` (
  `id` int(10) UNSIGNED NOT NULL,
  `product_code` varchar(20) NOT NULL,
  `product_name` varchar(80) NOT NULL,
  `product_desc` varchar(500) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп даних таблиці `products`
--

INSERT INTO `products` (`id`, `product_code`, `product_name`, `product_desc`, `price`, `created_at`) VALUES
(1, 'PD1001', 'Зошит', 'Зошит для навчальних нотаток', 25.50, '2026-10-27 13:39:02'),
(2, 'PD1002', 'Папка', 'Папка для матеріалів курсу', 40.00, '2026-10-27 13:39:02'),
(3, 'PD1003', 'Набір ручок', 'Навчальний набір, 3 кольори', 60.25, '2026-10-27 13:39:02');

--
-- Індекси збережених таблиць
--

--
-- Індекси таблиці `bus_routes`
--
ALTER TABLE `bus_routes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_bus_routes_number_time` (`route_number`,`departure_time`),
  ADD KEY `idx_bus_routes_destination` (`destination`);

--
-- Індекси таблиці `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_products_code` (`product_code`);

--
-- AUTO_INCREMENT для збережених таблиць
--

--
-- AUTO_INCREMENT для таблиці `bus_routes`
--
ALTER TABLE `bus_routes`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблиці `products`
--
ALTER TABLE `products`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
