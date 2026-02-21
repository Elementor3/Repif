-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Хост: 127.0.0.1
-- Час створення: Січ 26 2026 р., 00:19
-- Версія сервера: 10.4.32-MariaDB
-- Версія PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- База даних: `cheol904_db`
--

-- --------------------------------------------------------

--
-- Структура таблиці `collection`
--

CREATE TABLE `collection` (
  `pk_collectionID` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `description` varchar(1000) DEFAULT NULL,
  `fk_user` varchar(50) NOT NULL,
  `createdAt` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп даних таблиці `collection`
--

INSERT INTO `collection` (`pk_collectionID`, `name`, `description`, `fk_user`, `createdAt`) VALUES
(1, 'SpringData 1', 'Measurements collected during March 2025', 'alex', '2025-03-12 13:00:00'),
(2, 'LabData', 'Indoor lab experiments', 'maria', '2025-03-12 13:30:00'),
(3, 'Test', '', 'admin', '2026-01-16 14:55:21'),
(4, 'test 1', ';jkg,fg', 'admin', '2026-01-16 14:55:52'),
(5, 'CollectionLimitTest1', 'et', 'alex', '2026-01-19 19:15:10'),
(8, 'qer', '', 'admin', '2026-01-25 23:11:59');

-- --------------------------------------------------------

--
-- Структура таблиці `contains`
--

CREATE TABLE `contains` (
  `pkfk_measurement` int(11) NOT NULL,
  `pkfk_collection` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп даних таблиці `contains`
--

INSERT INTO `contains` (`pkfk_measurement`, `pkfk_collection`) VALUES
(1, 1),
(1, 5),
(2, 1),
(2, 5),
(3, 2),
(4, 3),
(4, 4),
(5, 3),
(5, 4),
(10, 5),
(11, 5),
(12, 5),
(14, 5),
(15, 5),
(16, 5),
(17, 5),
(18, 5),
(19, 5),
(20, 5),
(21, 5),
(22, 5),
(23, 5),
(24, 5),
(25, 5),
(26, 5),
(27, 5),
(28, 5),
(29, 5),
(30, 5),
(31, 5),
(32, 5),
(33, 5),
(34, 5),
(35, 5),
(36, 5),
(37, 5),
(38, 5),
(39, 5),
(40, 5),
(41, 5),
(42, 5),
(43, 5),
(44, 5),
(45, 5),
(46, 5),
(47, 5),
(48, 5),
(49, 5),
(50, 5),
(51, 5),
(52, 5),
(53, 5),
(54, 5),
(56, 5),
(57, 5),
(58, 5),
(59, 5),
(60, 5),
(61, 5),
(62, 5),
(63, 5),
(64, 5),
(65, 5),
(66, 5),
(67, 5),
(68, 5),
(69, 5),
(70, 5),
(71, 5),
(72, 5),
(73, 5),
(74, 5),
(75, 5),
(76, 5),
(77, 5),
(78, 5),
(79, 5),
(80, 5),
(81, 5),
(82, 5),
(83, 5),
(84, 5),
(85, 5),
(86, 5),
(87, 5),
(88, 5),
(89, 5),
(90, 5),
(91, 5),
(92, 5),
(93, 5),
(94, 5),
(95, 5),
(96, 5),
(97, 5),
(98, 5),
(99, 5),
(100, 5),
(101, 5),
(102, 5),
(103, 5),
(104, 5),
(105, 5),
(106, 5),
(107, 5),
(108, 5),
(109, 5),
(110, 5),
(111, 5),
(112, 5),
(113, 5),
(114, 5),
(115, 5),
(116, 5),
(117, 5),
(118, 5),
(119, 5),
(120, 5),
(121, 5),
(122, 5),
(123, 5),
(124, 5),
(125, 5),
(126, 5),
(127, 5),
(128, 5),
(129, 5),
(130, 5),
(131, 5),
(132, 5),
(133, 5),
(134, 5),
(135, 5),
(136, 5),
(137, 5),
(138, 5),
(139, 5),
(140, 5),
(141, 5),
(142, 5),
(143, 5),
(144, 5),
(145, 5),
(146, 5),
(147, 5),
(148, 5),
(149, 5),
(150, 5),
(151, 5),
(152, 5),
(153, 5),
(154, 5),
(155, 5),
(156, 5),
(157, 5),
(158, 5),
(159, 5),
(160, 5),
(161, 5),
(162, 5),
(163, 5),
(164, 5),
(165, 5),
(166, 5),
(167, 5),
(168, 5),
(169, 5),
(170, 5),
(171, 5),
(172, 5),
(173, 5),
(174, 5),
(175, 5),
(176, 5),
(177, 5),
(178, 5),
(179, 5),
(180, 5),
(181, 5),
(182, 5),
(183, 5),
(184, 5),
(185, 5),
(186, 5),
(187, 5),
(188, 5),
(189, 5),
(190, 5),
(191, 5),
(192, 5),
(193, 5),
(194, 5),
(195, 5),
(196, 5),
(197, 5),
(198, 5),
(199, 5),
(200, 5),
(201, 5),
(202, 5),
(203, 5),
(204, 5),
(205, 5),
(206, 5),
(207, 5),
(208, 5),
(209, 5),
(210, 5),
(212, 5),
(213, 5),
(214, 5),
(215, 5),
(216, 5),
(217, 5),
(218, 5),
(219, 5),
(220, 5),
(221, 5),
(223, 5),
(224, 5),
(228, 5),
(229, 5),
(230, 5),
(232, 5),
(233, 5),
(234, 5),
(235, 5),
(236, 5),
(237, 5),
(238, 5),
(239, 5),
(240, 5),
(241, 5),
(242, 5),
(243, 5),
(244, 5),
(245, 5),
(246, 5),
(247, 5),
(248, 5),
(249, 5),
(250, 5),
(251, 5),
(252, 5),
(253, 5),
(254, 5),
(255, 5),
(259, 8);

-- --------------------------------------------------------

--
-- Структура таблиці `friendship`
--

CREATE TABLE `friendship` (
  `pk_user1` varchar(50) NOT NULL,
  `pk_user2` varchar(50) NOT NULL,
  `createdAt` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп даних таблиці `friendship`
--

INSERT INTO `friendship` (`pk_user1`, `pk_user2`, `createdAt`) VALUES
('admin', 'alex', '2026-01-26 00:12:34'),
('alex', 'maria', '2025-03-10 09:00:00'),
('maria', 'admin', '2026-01-16 17:22:56');

-- --------------------------------------------------------

--
-- Структура таблиці `measurement`
--

CREATE TABLE `measurement` (
  `pk_measurementID` int(11) NOT NULL,
  `timestamp` datetime NOT NULL,
  `temperature` decimal(5,2) DEFAULT NULL,
  `humidity` decimal(5,2) DEFAULT NULL,
  `airPressure` decimal(6,2) DEFAULT NULL,
  `lightIntensity` decimal(6,2) DEFAULT NULL,
  `airQuality` decimal(6,2) DEFAULT NULL,
  `fk_station` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп даних таблиці `measurement`
--

INSERT INTO `measurement` (`pk_measurementID`, `timestamp`, `temperature`, `humidity`, `airPressure`, `lightIntensity`, `airQuality`, `fk_station`) VALUES
(1, '2025-03-12 08:00:00', 12.50, 65.00, 1012.30, 350.00, 220.00, 'ST001'),
(2, '2025-03-12 12:00:00', 16.20, 55.00, 1010.80, 9999.99, 180.00, 'ST001'),
(3, '2025-03-12 08:05:00', 22.10, 40.00, 1008.50, 600.00, 95.00, 'ST002'),
(4, '2026-01-16 14:53:11', 1.00, 1.00, 1.00, 1.00, 1.00, 'ST003'),
(5, '2026-01-16 14:53:11', 2.00, 2.00, 2.00, 2.00, 2.00, 'ST003'),
(6, '2026-01-31 16:31:01', 4.00, 4.00, 4.00, 4.00, 4.00, 'ST002'),
(10, '2026-01-19 18:47:02', 12.00, NULL, NULL, NULL, NULL, 'ST001'),
(11, '2026-01-19 18:47:02', 13.00, NULL, NULL, NULL, NULL, 'ST001'),
(12, '2026-03-09 18:48:56', 536.00, NULL, NULL, NULL, NULL, 'ST001'),
(14, '2026-01-19 19:12:09', 12.00, NULL, NULL, NULL, NULL, 'ST001'),
(15, '2026-01-19 19:12:09', 45.00, NULL, NULL, NULL, NULL, 'ST001'),
(16, '2026-01-19 19:12:09', 78.00, NULL, NULL, NULL, NULL, 'ST001'),
(17, '2026-01-19 19:12:09', 101.00, NULL, NULL, NULL, NULL, 'ST001'),
(18, '2026-01-19 19:12:09', 33.00, NULL, NULL, NULL, NULL, 'ST001'),
(19, '2026-01-19 19:12:09', 67.00, NULL, NULL, NULL, NULL, 'ST001'),
(20, '2026-01-19 19:12:09', 89.00, NULL, NULL, NULL, NULL, 'ST001'),
(21, '2026-01-19 19:12:09', 14.00, NULL, NULL, NULL, NULL, 'ST001'),
(22, '2026-01-19 19:12:09', 55.00, NULL, NULL, NULL, NULL, 'ST001'),
(23, '2026-01-19 19:12:09', 92.00, NULL, NULL, NULL, NULL, 'ST001'),
(24, '2026-01-19 19:12:09', 38.00, NULL, NULL, NULL, NULL, 'ST001'),
(25, '2026-01-19 19:12:09', 73.00, NULL, NULL, NULL, NULL, 'ST001'),
(26, '2026-01-19 19:12:09', 64.00, NULL, NULL, NULL, NULL, 'ST001'),
(27, '2026-01-19 19:12:09', 27.00, NULL, NULL, NULL, NULL, 'ST001'),
(28, '2026-01-19 19:12:09', 81.00, NULL, NULL, NULL, NULL, 'ST001'),
(29, '2026-01-19 19:12:09', 49.00, NULL, NULL, NULL, NULL, 'ST001'),
(30, '2026-01-19 19:12:09', 95.00, NULL, NULL, NULL, NULL, 'ST001'),
(31, '2026-01-19 19:12:09', 58.00, NULL, NULL, NULL, NULL, 'ST001'),
(32, '2026-01-19 19:12:09', 11.00, NULL, NULL, NULL, NULL, 'ST001'),
(33, '2026-01-19 19:12:09', 66.00, NULL, NULL, NULL, NULL, 'ST001'),
(34, '2026-01-19 19:12:09', 74.00, NULL, NULL, NULL, NULL, 'ST001'),
(35, '2026-01-19 19:12:09', 82.00, NULL, NULL, NULL, NULL, 'ST001'),
(36, '2026-01-19 19:12:09', 93.00, NULL, NULL, NULL, NULL, 'ST001'),
(37, '2026-01-19 19:12:09', 25.00, NULL, NULL, NULL, NULL, 'ST001'),
(38, '2026-01-19 19:12:09', 47.00, NULL, NULL, NULL, NULL, 'ST001'),
(39, '2026-01-19 19:12:09', 59.00, NULL, NULL, NULL, NULL, 'ST001'),
(40, '2026-01-19 19:12:09', 36.00, NULL, NULL, NULL, NULL, 'ST001'),
(41, '2026-01-19 19:12:09', 44.00, NULL, NULL, NULL, NULL, 'ST001'),
(42, '2026-01-19 19:12:09', 71.00, NULL, NULL, NULL, NULL, 'ST001'),
(43, '2026-01-19 19:12:09', 83.00, NULL, NULL, NULL, NULL, 'ST001'),
(44, '2026-01-19 19:12:09', 19.00, NULL, NULL, NULL, NULL, 'ST001'),
(45, '2026-01-19 19:12:09', 52.00, NULL, NULL, NULL, NULL, 'ST001'),
(46, '2026-01-19 19:12:09', 68.00, NULL, NULL, NULL, NULL, 'ST001'),
(47, '2026-01-19 19:12:09', 97.00, NULL, NULL, NULL, NULL, 'ST001'),
(48, '2026-01-19 19:12:09', 41.00, NULL, NULL, NULL, NULL, 'ST001'),
(49, '2026-01-19 19:12:09', 63.00, NULL, NULL, NULL, NULL, 'ST001'),
(50, '2026-01-19 19:12:09', 88.00, NULL, NULL, NULL, NULL, 'ST001'),
(51, '2026-01-19 19:12:09', 29.00, NULL, NULL, NULL, NULL, 'ST001'),
(52, '2026-01-19 19:12:09', 57.00, NULL, NULL, NULL, NULL, 'ST001'),
(53, '2026-01-19 19:12:09', 76.00, NULL, NULL, NULL, NULL, 'ST001'),
(54, '2026-01-19 19:12:09', 84.00, NULL, NULL, NULL, NULL, 'ST001'),
(56, '2026-01-19 19:12:09', 23.00, NULL, NULL, NULL, NULL, 'ST001'),
(57, '2026-01-19 19:12:09', 34.00, NULL, NULL, NULL, NULL, 'ST001'),
(58, '2026-01-19 19:12:09', 62.00, NULL, NULL, NULL, NULL, 'ST001'),
(59, '2026-01-19 19:12:09', 79.00, NULL, NULL, NULL, NULL, 'ST001'),
(60, '2026-01-19 19:12:09', 96.00, NULL, NULL, NULL, NULL, 'ST001'),
(61, '2026-01-19 19:12:09', 53.00, NULL, NULL, NULL, NULL, 'ST001'),
(62, '2026-01-19 19:12:09', 17.00, NULL, NULL, NULL, NULL, 'ST001'),
(63, '2026-01-19 19:12:47', 12.00, NULL, NULL, NULL, NULL, 'ST001'),
(64, '2026-01-19 19:12:47', 45.00, NULL, NULL, NULL, NULL, 'ST001'),
(65, '2026-01-19 19:12:47', 78.00, NULL, NULL, NULL, NULL, 'ST001'),
(66, '2026-01-19 19:12:47', 101.00, NULL, NULL, NULL, NULL, 'ST001'),
(67, '2026-01-19 19:12:47', 33.00, NULL, NULL, NULL, NULL, 'ST001'),
(68, '2026-01-19 19:12:47', 67.00, NULL, NULL, NULL, NULL, 'ST001'),
(69, '2026-01-19 19:12:47', 89.00, NULL, NULL, NULL, NULL, 'ST001'),
(70, '2026-01-19 19:12:47', 14.00, NULL, NULL, NULL, NULL, 'ST001'),
(71, '2026-01-19 19:12:47', 55.00, NULL, NULL, NULL, NULL, 'ST001'),
(72, '2026-01-19 19:12:47', 92.00, NULL, NULL, NULL, NULL, 'ST001'),
(73, '2026-01-19 19:12:47', 38.00, NULL, NULL, NULL, NULL, 'ST001'),
(74, '2026-01-19 19:12:47', 73.00, NULL, NULL, NULL, NULL, 'ST001'),
(75, '2026-01-19 19:12:47', 64.00, NULL, NULL, NULL, NULL, 'ST001'),
(76, '2026-01-19 19:12:47', 27.00, NULL, NULL, NULL, NULL, 'ST001'),
(77, '2026-01-19 19:12:47', 81.00, NULL, NULL, NULL, NULL, 'ST001'),
(78, '2026-01-19 19:12:47', 49.00, NULL, NULL, NULL, NULL, 'ST001'),
(79, '2026-01-19 19:12:47', 95.00, NULL, NULL, NULL, NULL, 'ST001'),
(80, '2026-01-19 19:12:47', 58.00, NULL, NULL, NULL, NULL, 'ST001'),
(81, '2026-01-19 19:12:47', 11.00, NULL, NULL, NULL, NULL, 'ST001'),
(82, '2026-01-19 19:12:47', 66.00, NULL, NULL, NULL, NULL, 'ST001'),
(83, '2026-01-19 19:12:47', 74.00, NULL, NULL, NULL, NULL, 'ST001'),
(84, '2026-01-19 19:12:47', 82.00, NULL, NULL, NULL, NULL, 'ST001'),
(85, '2026-01-19 19:12:47', 93.00, NULL, NULL, NULL, NULL, 'ST001'),
(86, '2026-01-19 19:12:47', 25.00, NULL, NULL, NULL, NULL, 'ST001'),
(87, '2026-01-19 19:12:47', 47.00, NULL, NULL, NULL, NULL, 'ST001'),
(88, '2026-01-19 19:12:47', 59.00, NULL, NULL, NULL, NULL, 'ST001'),
(89, '2026-01-19 19:12:47', 36.00, NULL, NULL, NULL, NULL, 'ST001'),
(90, '2026-01-19 19:12:47', 44.00, NULL, NULL, NULL, NULL, 'ST001'),
(91, '2026-01-19 19:12:47', 71.00, NULL, NULL, NULL, NULL, 'ST001'),
(92, '2026-01-19 19:12:47', 83.00, NULL, NULL, NULL, NULL, 'ST001'),
(93, '2026-01-19 19:12:47', 19.00, NULL, NULL, NULL, NULL, 'ST001'),
(94, '2026-01-19 19:12:47', 52.00, NULL, NULL, NULL, NULL, 'ST001'),
(95, '2026-01-19 19:12:47', 68.00, NULL, NULL, NULL, NULL, 'ST001'),
(96, '2026-01-19 19:12:47', 97.00, NULL, NULL, NULL, NULL, 'ST001'),
(97, '2026-01-19 19:12:47', 41.00, NULL, NULL, NULL, NULL, 'ST001'),
(98, '2026-01-19 19:12:47', 63.00, NULL, NULL, NULL, NULL, 'ST001'),
(99, '2026-01-19 19:12:47', 88.00, NULL, NULL, NULL, NULL, 'ST001'),
(100, '2026-01-19 19:12:47', 29.00, NULL, NULL, NULL, NULL, 'ST001'),
(101, '2026-01-19 19:12:47', 57.00, NULL, NULL, NULL, NULL, 'ST001'),
(102, '2026-01-19 19:12:47', 76.00, NULL, NULL, NULL, NULL, 'ST001'),
(103, '2026-01-19 19:12:47', 84.00, NULL, NULL, NULL, NULL, 'ST001'),
(104, '2026-01-19 19:12:47', 91.00, NULL, NULL, NULL, NULL, 'ST001'),
(105, '2026-01-19 19:12:47', 23.00, NULL, NULL, NULL, NULL, 'ST001'),
(106, '2026-01-19 19:12:47', 34.00, NULL, NULL, NULL, NULL, 'ST001'),
(107, '2026-01-19 19:12:47', 62.00, NULL, NULL, NULL, NULL, 'ST001'),
(108, '2026-01-19 19:12:47', 79.00, NULL, NULL, NULL, NULL, 'ST001'),
(109, '2026-01-19 19:12:47', 96.00, NULL, NULL, NULL, NULL, 'ST001'),
(110, '2026-01-19 19:12:47', 53.00, NULL, NULL, NULL, NULL, 'ST001'),
(111, '2026-01-19 19:12:47', 17.00, NULL, NULL, NULL, NULL, 'ST001'),
(112, '2026-01-19 19:12:53', 12.00, NULL, NULL, NULL, NULL, 'ST001'),
(113, '2026-01-19 19:12:53', 45.00, NULL, NULL, NULL, NULL, 'ST001'),
(114, '2026-01-19 19:12:53', 78.00, NULL, NULL, NULL, NULL, 'ST001'),
(115, '2026-01-19 19:12:53', 101.00, NULL, NULL, NULL, NULL, 'ST001'),
(116, '2026-01-19 19:12:53', 33.00, NULL, NULL, NULL, NULL, 'ST001'),
(117, '2026-01-19 19:12:53', 67.00, NULL, NULL, NULL, NULL, 'ST001'),
(118, '2026-01-19 19:12:53', 89.00, NULL, NULL, NULL, NULL, 'ST001'),
(119, '2026-01-19 19:12:53', 14.00, NULL, NULL, NULL, NULL, 'ST001'),
(120, '2026-01-19 19:12:53', 55.00, NULL, NULL, NULL, NULL, 'ST001'),
(121, '2026-01-19 19:12:53', 92.00, NULL, NULL, NULL, NULL, 'ST001'),
(122, '2026-01-19 19:12:53', 38.00, NULL, NULL, NULL, NULL, 'ST001'),
(123, '2026-01-19 19:12:53', 73.00, NULL, NULL, NULL, NULL, 'ST001'),
(124, '2026-01-19 19:12:53', 64.00, NULL, NULL, NULL, NULL, 'ST001'),
(125, '2026-01-19 19:12:53', 27.00, NULL, NULL, NULL, NULL, 'ST001'),
(126, '2026-01-19 19:12:53', 81.00, NULL, NULL, NULL, NULL, 'ST001'),
(127, '2026-01-19 19:12:53', 49.00, NULL, NULL, NULL, NULL, 'ST001'),
(128, '2026-01-19 19:12:53', 95.00, NULL, NULL, NULL, NULL, 'ST001'),
(129, '2026-01-19 19:12:53', 58.00, NULL, NULL, NULL, NULL, 'ST001'),
(130, '2026-01-19 19:12:53', 11.00, NULL, NULL, NULL, NULL, 'ST001'),
(131, '2026-01-19 19:12:53', 66.00, NULL, NULL, NULL, NULL, 'ST001'),
(132, '2026-01-19 19:12:53', 74.00, NULL, NULL, NULL, NULL, 'ST001'),
(133, '2026-01-19 19:12:53', 82.00, NULL, NULL, NULL, NULL, 'ST001'),
(134, '2026-01-19 19:12:53', 93.00, NULL, NULL, NULL, NULL, 'ST001'),
(135, '2026-01-19 19:12:53', 25.00, NULL, NULL, NULL, NULL, 'ST001'),
(136, '2026-01-19 19:12:53', 47.00, NULL, NULL, NULL, NULL, 'ST001'),
(137, '2026-01-19 19:12:53', 59.00, NULL, NULL, NULL, NULL, 'ST001'),
(138, '2026-01-19 19:12:53', 36.00, NULL, NULL, NULL, NULL, 'ST001'),
(139, '2026-01-19 19:12:53', 44.00, NULL, NULL, NULL, NULL, 'ST001'),
(140, '2026-01-19 19:12:53', 71.00, NULL, NULL, NULL, NULL, 'ST001'),
(141, '2026-01-19 19:12:53', 83.00, NULL, NULL, NULL, NULL, 'ST001'),
(142, '2026-01-19 19:12:53', 19.00, NULL, NULL, NULL, NULL, 'ST001'),
(143, '2026-01-19 19:12:53', 52.00, NULL, NULL, NULL, NULL, 'ST001'),
(144, '2026-01-19 19:12:53', 68.00, NULL, NULL, NULL, NULL, 'ST001'),
(145, '2026-01-19 19:12:53', 97.00, NULL, NULL, NULL, NULL, 'ST001'),
(146, '2026-01-19 19:12:53', 41.00, NULL, NULL, NULL, NULL, 'ST001'),
(147, '2026-01-19 19:12:53', 63.00, NULL, NULL, NULL, NULL, 'ST001'),
(148, '2026-01-19 19:12:53', 88.00, NULL, NULL, NULL, NULL, 'ST001'),
(149, '2026-01-19 19:12:53', 29.00, NULL, NULL, NULL, NULL, 'ST001'),
(150, '2026-01-19 19:12:53', 57.00, NULL, NULL, NULL, NULL, 'ST001'),
(151, '2026-01-19 19:12:53', 76.00, NULL, NULL, NULL, NULL, 'ST001'),
(152, '2026-01-19 19:12:53', 84.00, NULL, NULL, NULL, NULL, 'ST001'),
(153, '2026-01-19 19:12:53', 91.00, NULL, NULL, NULL, NULL, 'ST001'),
(154, '2026-01-19 19:12:53', 23.00, NULL, NULL, NULL, NULL, 'ST001'),
(155, '2026-01-19 19:12:53', 34.00, NULL, NULL, NULL, NULL, 'ST001'),
(156, '2026-01-19 19:12:53', 62.00, NULL, NULL, NULL, NULL, 'ST001'),
(157, '2026-01-19 19:12:53', 79.00, NULL, NULL, NULL, NULL, 'ST001'),
(158, '2026-01-19 19:12:53', 96.00, NULL, NULL, NULL, NULL, 'ST001'),
(159, '2026-01-19 19:12:53', 53.00, NULL, NULL, NULL, NULL, 'ST001'),
(160, '2026-01-19 19:12:53', 17.00, NULL, NULL, NULL, NULL, 'ST001'),
(161, '2026-01-19 19:12:57', 12.00, NULL, NULL, NULL, NULL, 'ST001'),
(162, '2026-01-19 19:12:57', 45.00, NULL, NULL, NULL, NULL, 'ST001'),
(163, '2026-01-19 19:12:57', 78.00, NULL, NULL, NULL, NULL, 'ST001'),
(164, '2026-01-19 19:12:57', 101.00, NULL, NULL, NULL, NULL, 'ST001'),
(165, '2026-01-19 19:12:57', 33.00, NULL, NULL, NULL, NULL, 'ST001'),
(166, '2026-01-19 19:12:57', 67.00, NULL, NULL, NULL, NULL, 'ST001'),
(167, '2026-01-19 19:12:57', 89.00, NULL, NULL, NULL, NULL, 'ST001'),
(168, '2026-01-19 19:12:57', 14.00, NULL, NULL, NULL, NULL, 'ST001'),
(169, '2026-01-19 19:12:57', 55.00, NULL, NULL, NULL, NULL, 'ST001'),
(170, '2026-01-19 19:12:57', 92.00, NULL, NULL, NULL, NULL, 'ST001'),
(171, '2026-01-19 19:12:57', 38.00, NULL, NULL, NULL, NULL, 'ST001'),
(172, '2026-01-19 19:12:57', 73.00, NULL, NULL, NULL, NULL, 'ST001'),
(173, '2026-01-19 19:12:57', 64.00, NULL, NULL, NULL, NULL, 'ST001'),
(174, '2026-01-19 19:12:57', 27.00, NULL, NULL, NULL, NULL, 'ST001'),
(175, '2026-01-19 19:12:57', 81.00, NULL, NULL, NULL, NULL, 'ST001'),
(176, '2026-01-19 19:12:57', 49.00, NULL, NULL, NULL, NULL, 'ST001'),
(177, '2026-01-19 19:12:57', 95.00, NULL, NULL, NULL, NULL, 'ST001'),
(178, '2026-01-19 19:12:57', 58.00, NULL, NULL, NULL, NULL, 'ST001'),
(179, '2026-01-19 19:12:57', 11.00, NULL, NULL, NULL, NULL, 'ST001'),
(180, '2026-01-19 19:12:57', 66.00, NULL, NULL, NULL, NULL, 'ST001'),
(181, '2026-01-19 19:12:57', 74.00, NULL, NULL, NULL, NULL, 'ST001'),
(182, '2026-01-19 19:12:57', 82.00, NULL, NULL, NULL, NULL, 'ST001'),
(183, '2026-01-19 19:12:57', 93.00, NULL, NULL, NULL, NULL, 'ST001'),
(184, '2026-01-19 19:12:57', 25.00, NULL, NULL, NULL, NULL, 'ST001'),
(185, '2026-01-19 19:12:57', 47.00, NULL, NULL, NULL, NULL, 'ST001'),
(186, '2026-01-19 19:12:57', 59.00, NULL, NULL, NULL, NULL, 'ST001'),
(187, '2026-01-19 19:12:57', 36.00, NULL, NULL, NULL, NULL, 'ST001'),
(188, '2026-01-19 19:12:57', 44.00, NULL, NULL, NULL, NULL, 'ST001'),
(189, '2026-01-19 19:12:57', 71.00, NULL, NULL, NULL, NULL, 'ST001'),
(190, '2026-01-19 19:12:57', 83.00, NULL, NULL, NULL, NULL, 'ST001'),
(191, '2026-01-19 19:12:57', 19.00, NULL, NULL, NULL, NULL, 'ST001'),
(192, '2026-01-19 19:12:57', 52.00, NULL, NULL, NULL, NULL, 'ST001'),
(193, '2026-01-19 19:12:57', 68.00, NULL, NULL, NULL, NULL, 'ST001'),
(194, '2026-01-19 19:12:57', 97.00, NULL, NULL, NULL, NULL, 'ST001'),
(195, '2026-01-19 19:12:57', 41.00, NULL, NULL, NULL, NULL, 'ST001'),
(196, '2026-01-19 19:12:57', 63.00, NULL, NULL, NULL, NULL, 'ST001'),
(197, '2026-01-19 19:12:57', 88.00, NULL, NULL, NULL, NULL, 'ST001'),
(198, '2026-01-19 19:12:57', 29.00, NULL, NULL, NULL, NULL, 'ST001'),
(199, '2026-01-19 19:12:57', 57.00, NULL, NULL, NULL, NULL, 'ST001'),
(200, '2026-01-19 19:12:57', 76.00, NULL, NULL, NULL, NULL, 'ST001'),
(201, '2026-01-19 19:12:57', 84.00, NULL, NULL, NULL, NULL, 'ST001'),
(202, '2026-01-19 19:12:57', 91.00, NULL, NULL, NULL, NULL, 'ST001'),
(203, '2026-01-19 19:12:57', 23.00, NULL, NULL, NULL, NULL, 'ST001'),
(204, '2026-01-19 19:12:57', 34.00, NULL, NULL, NULL, NULL, 'ST001'),
(205, '2026-01-19 19:12:57', 62.00, NULL, NULL, NULL, NULL, 'ST001'),
(206, '2026-01-19 19:12:57', 79.00, NULL, NULL, NULL, NULL, 'ST001'),
(207, '2026-01-19 19:12:57', 96.00, NULL, NULL, NULL, NULL, 'ST001'),
(208, '2026-01-19 19:12:57', 53.00, NULL, NULL, NULL, NULL, 'ST001'),
(209, '2026-01-19 19:12:57', 17.00, NULL, NULL, NULL, NULL, 'ST001'),
(210, '2026-01-19 19:13:17', 12.00, NULL, NULL, NULL, NULL, 'ST001'),
(212, '2026-01-19 19:13:17', 78.00, NULL, NULL, NULL, NULL, 'ST001'),
(213, '2026-01-19 19:13:17', 101.00, NULL, NULL, NULL, NULL, 'ST001'),
(214, '2026-01-19 19:13:17', 33.00, NULL, NULL, NULL, NULL, 'ST001'),
(215, '2026-01-19 19:13:17', 67.00, NULL, NULL, NULL, NULL, 'ST001'),
(216, '2026-01-19 19:13:17', 89.00, NULL, NULL, NULL, NULL, 'ST001'),
(217, '2026-01-19 19:13:17', 14.00, NULL, NULL, NULL, NULL, 'ST001'),
(218, '2026-01-19 19:13:17', 55.00, NULL, NULL, NULL, NULL, 'ST001'),
(219, '2026-01-19 19:13:17', 92.00, NULL, NULL, NULL, NULL, 'ST001'),
(220, '2026-01-19 19:13:17', 38.00, NULL, NULL, NULL, NULL, 'ST001'),
(221, '2026-01-19 19:13:17', 73.00, NULL, NULL, NULL, NULL, 'ST001'),
(223, '2026-01-19 19:13:17', 27.00, NULL, NULL, NULL, NULL, 'ST001'),
(224, '2026-01-19 19:13:17', 81.00, NULL, NULL, NULL, NULL, 'ST001'),
(228, '2026-01-19 19:13:17', 11.00, NULL, NULL, NULL, NULL, 'ST001'),
(229, '2026-01-19 19:13:17', 66.00, NULL, NULL, NULL, NULL, 'ST001'),
(230, '2026-01-19 19:13:17', 74.00, NULL, NULL, NULL, NULL, 'ST001'),
(232, '2026-01-19 19:13:17', 93.00, NULL, NULL, NULL, NULL, 'ST001'),
(233, '2026-01-19 19:13:17', 25.00, NULL, NULL, NULL, NULL, 'ST001'),
(234, '2026-01-19 19:13:17', 47.00, NULL, NULL, NULL, NULL, 'ST001'),
(235, '2026-01-19 19:13:17', 59.00, NULL, NULL, NULL, NULL, 'ST001'),
(236, '2026-01-19 19:13:17', 36.00, NULL, NULL, NULL, NULL, 'ST001'),
(237, '2026-01-19 19:13:17', 44.00, NULL, NULL, NULL, NULL, 'ST001'),
(238, '2026-01-19 19:13:17', 71.00, NULL, NULL, NULL, NULL, 'ST001'),
(239, '2026-01-19 19:13:17', 83.00, NULL, NULL, NULL, NULL, 'ST001'),
(240, '2026-01-19 19:13:17', 19.00, NULL, NULL, NULL, NULL, 'ST001'),
(241, '2026-01-19 19:13:17', 52.00, NULL, NULL, NULL, NULL, 'ST001'),
(242, '2026-01-19 19:13:17', 68.00, NULL, NULL, NULL, NULL, 'ST001'),
(243, '2026-01-19 19:13:17', 97.00, NULL, NULL, NULL, NULL, 'ST001'),
(244, '2026-01-19 19:13:17', 41.00, NULL, NULL, NULL, NULL, 'ST001'),
(245, '2026-01-19 19:13:17', 63.00, NULL, NULL, NULL, NULL, 'ST001'),
(246, '2026-01-19 19:13:17', 88.00, NULL, NULL, NULL, NULL, 'ST001'),
(247, '2026-01-19 19:13:17', 29.00, NULL, NULL, NULL, NULL, 'ST001'),
(248, '2026-01-19 19:13:17', 57.00, NULL, NULL, NULL, NULL, 'ST001'),
(249, '2026-01-19 19:13:17', 76.00, NULL, NULL, NULL, NULL, 'ST001'),
(250, '2026-01-19 19:13:17', 84.00, NULL, NULL, NULL, NULL, 'ST001'),
(251, '2026-01-19 19:13:17', 91.00, NULL, NULL, NULL, NULL, 'ST001'),
(252, '2026-01-19 19:13:17', 23.00, NULL, NULL, NULL, NULL, 'ST001'),
(253, '2026-01-19 19:13:17', 34.00, NULL, NULL, NULL, NULL, 'ST001'),
(254, '2026-01-19 19:13:17', 62.00, NULL, NULL, NULL, NULL, 'ST001'),
(255, '2026-01-19 19:13:17', 79.00, NULL, NULL, NULL, NULL, 'ST001'),
(259, '2026-01-25 19:39:19', 1.00, NULL, NULL, NULL, NULL, 'ST007');

-- --------------------------------------------------------

--
-- Структура таблиці `request`
--

CREATE TABLE `request` (
  `pk_requestID` int(11) NOT NULL,
  `fk_sender` varchar(50) NOT NULL,
  `fk_receiver` varchar(50) NOT NULL,
  `status` enum('pending','accepted','rejected') NOT NULL,
  `createdAt` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп даних таблиці `request`
--

INSERT INTO `request` (`pk_requestID`, `fk_sender`, `fk_receiver`, `status`, `createdAt`) VALUES
(1, 'alex', 'maria', 'accepted', '2025-03-09 18:30:00'),
(2, 'admin', 'alex', 'accepted', '2026-01-25 23:30:56'),
(3, 'alex', 'admin', 'rejected', '2026-01-25 23:31:39'),
(4, 'admin', 'alex', 'accepted', '2026-01-25 23:32:39'),
(5, 'alex', 'admin', 'accepted', '2026-01-25 23:33:11'),
(6, 'admin', 'alex', 'accepted', '2026-01-26 00:10:16'),
(7, 'alex', 'admin', 'accepted', '2026-01-26 00:12:05');

-- --------------------------------------------------------

--
-- Структура таблиці `shares`
--

CREATE TABLE `shares` (
  `pk_user` varchar(50) NOT NULL,
  `pk_collection` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп даних таблиці `shares`
--

INSERT INTO `shares` (`pk_user`, `pk_collection`) VALUES
('alex', 2),
('maria', 1);

-- --------------------------------------------------------

--
-- Структура таблиці `station`
--

CREATE TABLE `station` (
  `pk_serialNumber` varchar(50) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `description` varchar(1000) DEFAULT NULL,
  `fk_createdBy` varchar(50) DEFAULT NULL,
  `fk_registeredBy` varchar(50) DEFAULT NULL,
  `createdAt` datetime NOT NULL,
  `registeredAt` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп даних таблиці `station`
--

INSERT INTO `station` (`pk_serialNumber`, `name`, `description`, `fk_createdBy`, `fk_registeredBy`, `createdAt`, `registeredAt`) VALUES
('ST001', 'Weather Station North', 'Outdoor station near school yard hdhsd  tukfm ndjt fylisjdtfd jtmnb dtmdnhn tydnd sry mtm', 'admin', 'alex', '2025-02-20 08:00:00', '2025-02-21 09:00:00'),
('ST002', 'Indoor Lab Station', 'Station inside physics lab', 'admin', 'maria', '2025-02-25 10:00:00', '2025-02-25 11:00:00'),
('ST003', 'Admin Station', ';jgnz;d', 'admin', 'admin', '2026-01-16 14:51:53', '2026-01-16 14:54:12'),
('ST005', 'newadminstation1', '', 'cheol904', 'admin', '2026-01-23 19:48:38', '2026-01-25 19:34:21'),
('ST006', NULL, NULL, 'cheol904', 'alex', '2026-01-23 19:49:07', '2026-01-25 19:33:54'),
('ST007', NULL, NULL, 'admin', NULL, '2026-01-25 19:38:33', '2026-01-25 19:41:03');

-- --------------------------------------------------------

--
-- Структура таблиці `user`
--

CREATE TABLE `user` (
  `pk_username` varchar(50) NOT NULL,
  `firstName` varchar(100) NOT NULL,
  `lastName` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('User','Admin') NOT NULL DEFAULT 'User',
  `createdAt` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп даних таблиці `user`
--

INSERT INTO `user` (`pk_username`, `firstName`, `lastName`, `email`, `password_hash`, `role`, `createdAt`) VALUES
('admin', 'System1', 'Admin', 'admin@example.com', '$2y$10$hafmooIznXZ/HbWnYjI3..zs75L6LflGlzNL47EMeFO2NW70JCYwi', 'Admin', '2025-01-01 00:00:00'),
('alex', 'Oleksandr', 'Petrov', 'alex.petrov@example.com', '$2y$10$j86eYKRpUfyX9iVdsi3mI.tpSOdNjt4R7cBDDieXDPYN.8NhJJOYe', 'User', '2025-01-15 10:23:00'),
('cheol904', 'Oleksandr', 'Cherepkov', 'whatever@gmail.com1', '$2y$10$3z77scIaUihctBHJUfh7m.Vsrlur2J4Yggg.EWpX1xrYpo90Gd.Fa', 'Admin', '2026-01-19 16:45:01'),
('maria', 'Maria', 'Schmidt', 'm.schmidt@example.com', '$2y$10$m.RPt0bYk4A7qoYlzedSpuenzGqwAJpZ6nbhfEH4DPFdPK04bjgeW', 'User', '2025-02-02 14:45:00'),
('wtrey', 'wrt', 'wrt', 'wrt@sg.c', '$2y$10$380MA1Hn8rBTna1TvHpqDe8IQox/vHPxY9q0U94lRnLxXCvmqqa8O', 'User', '2026-01-24 20:24:03');

--
-- Індекси збережених таблиць
--

--
-- Індекси таблиці `collection`
--
ALTER TABLE `collection`
  ADD PRIMARY KEY (`pk_collectionID`),
  ADD UNIQUE KEY `uq_collection_owner_name` (`fk_user`,`name`);

--
-- Індекси таблиці `contains`
--
ALTER TABLE `contains`
  ADD PRIMARY KEY (`pkfk_measurement`,`pkfk_collection`),
  ADD KEY `pkfk_collection` (`pkfk_collection`);

--
-- Індекси таблиці `friendship`
--
ALTER TABLE `friendship`
  ADD PRIMARY KEY (`pk_user1`,`pk_user2`),
  ADD UNIQUE KEY `uniq_friendship` (`pk_user1`,`pk_user2`),
  ADD KEY `pk_user2` (`pk_user2`);

--
-- Індекси таблиці `measurement`
--
ALTER TABLE `measurement`
  ADD PRIMARY KEY (`pk_measurementID`),
  ADD KEY `fk_station` (`fk_station`);

--
-- Індекси таблиці `request`
--
ALTER TABLE `request`
  ADD PRIMARY KEY (`pk_requestID`),
  ADD KEY `fk_sender` (`fk_sender`),
  ADD KEY `fk_receiver` (`fk_receiver`);

--
-- Індекси таблиці `shares`
--
ALTER TABLE `shares`
  ADD PRIMARY KEY (`pk_user`,`pk_collection`),
  ADD KEY `pk_collection` (`pk_collection`);

--
-- Індекси таблиці `station`
--
ALTER TABLE `station`
  ADD PRIMARY KEY (`pk_serialNumber`),
  ADD KEY `fk_createdBy` (`fk_createdBy`),
  ADD KEY `fk_registeredBy` (`fk_registeredBy`);

--
-- Індекси таблиці `user`
--
ALTER TABLE `user`
  ADD PRIMARY KEY (`pk_username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT для збережених таблиць
--

--
-- AUTO_INCREMENT для таблиці `collection`
--
ALTER TABLE `collection`
  MODIFY `pk_collectionID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT для таблиці `measurement`
--
ALTER TABLE `measurement`
  MODIFY `pk_measurementID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=260;

--
-- AUTO_INCREMENT для таблиці `request`
--
ALTER TABLE `request`
  MODIFY `pk_requestID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- Обмеження зовнішнього ключа збережених таблиць
--

--
-- Обмеження зовнішнього ключа таблиці `collection`
--
ALTER TABLE `collection`
  ADD CONSTRAINT `collection_ibfk_1` FOREIGN KEY (`fk_user`) REFERENCES `user` (`pk_username`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Обмеження зовнішнього ключа таблиці `contains`
--
ALTER TABLE `contains`
  ADD CONSTRAINT `contains_ibfk_1` FOREIGN KEY (`pkfk_measurement`) REFERENCES `measurement` (`pk_measurementID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `contains_ibfk_2` FOREIGN KEY (`pkfk_collection`) REFERENCES `collection` (`pk_collectionID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Обмеження зовнішнього ключа таблиці `friendship`
--
ALTER TABLE `friendship`
  ADD CONSTRAINT `friendship_ibfk_1` FOREIGN KEY (`pk_user1`) REFERENCES `user` (`pk_username`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `friendship_ibfk_2` FOREIGN KEY (`pk_user2`) REFERENCES `user` (`pk_username`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Обмеження зовнішнього ключа таблиці `measurement`
--
ALTER TABLE `measurement`
  ADD CONSTRAINT `measurement_ibfk_1` FOREIGN KEY (`fk_station`) REFERENCES `station` (`pk_serialNumber`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Обмеження зовнішнього ключа таблиці `request`
--
ALTER TABLE `request`
  ADD CONSTRAINT `request_ibfk_1` FOREIGN KEY (`fk_sender`) REFERENCES `user` (`pk_username`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `request_ibfk_2` FOREIGN KEY (`fk_receiver`) REFERENCES `user` (`pk_username`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Обмеження зовнішнього ключа таблиці `shares`
--
ALTER TABLE `shares`
  ADD CONSTRAINT `shares_ibfk_1` FOREIGN KEY (`pk_user`) REFERENCES `user` (`pk_username`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `shares_ibfk_2` FOREIGN KEY (`pk_collection`) REFERENCES `collection` (`pk_collectionID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Обмеження зовнішнього ключа таблиці `station`
--
ALTER TABLE `station`
  ADD CONSTRAINT `station_ibfk_1` FOREIGN KEY (`fk_createdBy`) REFERENCES `user` (`pk_username`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `station_ibfk_2` FOREIGN KEY (`fk_registeredBy`) REFERENCES `user` (`pk_username`) ON DELETE SET NULL ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
