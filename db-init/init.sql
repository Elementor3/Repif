-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Хост: 127.0.0.1
-- Час створення: Квт 15 2026 р., 17:59
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
-- Структура таблиці `admin_post`
--

CREATE TABLE `admin_post` (
  `pk_postID` int(11) NOT NULL,
  `fk_author` varchar(50) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `createdAt` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп даних таблиці `admin_post`
--

INSERT INTO `admin_post` (`pk_postID`, `fk_author`, `title`, `content`, `createdAt`) VALUES
(21, 'admin', 'тест', '1234уцкенегншгщдрмопсооооооооооооооооооооооооооооооооооооооооооооооооооооооооооооооооооооо', '2026-03-06 14:53:12'),
(22, 'admin', 'test2', '123123', '2026-03-06 15:07:18'),
(23, 'admin', 'testmail', 'qwertt', '2026-03-06 16:31:32'),
(24, 'krud', 'Olexandr posmotri SonyBoy', 'Olexandr posmotri SonyBoy', '2026-04-13 11:03:35'),
(25, 'admin', 'fjg,kg', ',kg.klutg.kuh.', '2026-04-13 11:13:18');

-- --------------------------------------------------------

--
-- Структура таблиці `chat_conversation`
--

CREATE TABLE `chat_conversation` (
  `pk_conversationID` int(11) NOT NULL,
  `type` enum('private','group') NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `createdAt` datetime NOT NULL DEFAULT current_timestamp(),
  `createdBy` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп даних таблиці `chat_conversation`
--

INSERT INTO `chat_conversation` (`pk_conversationID`, `type`, `name`, `description`, `avatar`, `createdAt`, `createdBy`) VALUES
(1, 'private', NULL, NULL, NULL, '2026-02-22 00:11:06', 'admin'),
(3, 'private', NULL, NULL, NULL, '2026-02-22 00:32:12', 'cheol904'),
(4, 'private', NULL, NULL, NULL, '2026-02-22 18:06:04', 'cheol904'),
(6, 'group', 'qwerty', 'sfhgld11', NULL, '2026-02-22 20:17:42', 'admin'),
(7, 'group', 'йцукейd', 'йцукеffwdkfцк', 'group_upload:group_avatar_7_65a4eb7b662cc2e9.png', '2026-02-22 20:22:52', 'cheol904'),
(8, 'private', NULL, NULL, NULL, '2026-02-22 20:51:41', 'admin'),
(9, 'group', 'stestgroup', 'asdhjf', NULL, '2026-02-23 15:41:25', 'admin'),
(10, 'private', NULL, NULL, NULL, '2026-03-15 18:18:55', 'testtime'),
(11, 'private', NULL, NULL, NULL, '2026-03-30 00:24:24', 'alex'),
(12, 'private', NULL, NULL, NULL, '2026-03-30 00:27:57', 'alex'),
(13, 'private', NULL, NULL, NULL, '2026-04-01 18:14:44', 'maria'),
(14, 'group', 'test chat', 'test what happens if admin is deleted', NULL, '2026-04-02 17:23:57', 'admin'),
(15, 'private', NULL, NULL, NULL, '2026-04-02 19:12:40', 'admin'),
(16, 'private', NULL, NULL, NULL, '2026-04-02 19:40:53', 'admin'),
(17, 'private', NULL, NULL, NULL, '2026-04-02 19:57:09', 'maria'),
(18, 'private', NULL, NULL, NULL, '2026-04-02 21:55:29', NULL),
(19, 'private', NULL, NULL, NULL, '2026-04-02 21:55:46', NULL),
(20, 'group', 'sfg', 'sfg', 'group_upload:group_avatar_20_5f387ee5aa2fb00b.png', '2026-04-02 21:58:07', 'admin'),
(22, 'group', 't', 't', NULL, '2026-04-02 22:45:10', 'cheol904'),
(23, 'group', 'q', '', NULL, '2026-04-03 10:01:25', 'admin'),
(24, 'private', NULL, NULL, NULL, '2026-04-08 14:58:52', 'admin'),
(25, 'private', NULL, NULL, NULL, '2026-04-08 15:13:54', 'admin'),
(26, 'private', NULL, NULL, NULL, '2026-04-08 18:42:13', 'admin'),
(27, 'private', NULL, NULL, NULL, '2026-04-13 10:32:40', 'krud'),
(28, 'private', NULL, NULL, NULL, '2026-04-13 10:34:31', 'cheol904'),
(29, 'group', 'Gei', '', NULL, '2026-04-13 10:35:53', 'cheol904');

-- --------------------------------------------------------

--
-- Структура таблиці `chat_draft`
--

CREATE TABLE `chat_draft` (
  `fk_conversation` int(11) NOT NULL,
  `fk_user` varchar(50) NOT NULL,
  `text` text NOT NULL,
  `updatedAt` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп даних таблиці `chat_draft`
--

INSERT INTO `chat_draft` (`fk_conversation`, `fk_user`, `text`, `updatedAt`) VALUES
(7, 'admin', 'sdtdythhuu', '2026-04-10 13:50:30'),
(20, 'wtrey', 'djfjfj', '2026-04-02 21:58:19');

-- --------------------------------------------------------

--
-- Структура таблиці `chat_draft_file`
--

CREATE TABLE `chat_draft_file` (
  `pk_fileID` int(11) NOT NULL,
  `fk_conversation` int(11) NOT NULL,
  `fk_user` varchar(50) NOT NULL,
  `filePath` varchar(255) NOT NULL,
  `fileName` varchar(255) NOT NULL,
  `createdAt` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп даних таблиці `chat_draft_file`
--

INSERT INTO `chat_draft_file` (`pk_fileID`, `fk_conversation`, `fk_user`, `filePath`, `fileName`, `createdAt`) VALUES
(7, 7, 'admin', 'chatdraft_69d8e3e1e491b3.96876286.png', 'chart_temperature.png', '2026-04-10 13:49:53');

-- --------------------------------------------------------

--
-- Структура таблиці `chat_message`
--

CREATE TABLE `chat_message` (
  `pk_messageID` int(11) NOT NULL,
  `fk_conversation` int(11) NOT NULL,
  `fk_sender` varchar(50) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `filePath` varchar(500) DEFAULT NULL,
  `fileName` varchar(255) DEFAULT NULL,
  `createdAt` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп даних таблиці `chat_message`
--

INSERT INTO `chat_message` (`pk_messageID`, `fk_conversation`, `fk_sender`, `message`, `filePath`, `fileName`, `createdAt`) VALUES
(1, 3, 'admin', 'Привет', NULL, NULL, '2026-02-22 18:00:43'),
(2, 3, 'admin', 'как дела?', NULL, NULL, '2026-02-22 18:00:53'),
(3, 3, 'admin', NULL, 'chat_699b3664b2a104.10044040.png', 'chart.png', '2026-02-22 18:01:24'),
(4, 3, 'cheol904', 'четко', NULL, NULL, '2026-02-22 18:02:39'),
(5, 6, 'admin', 'gkkgk', 'chat_699b5683271702.07637765.png', 'chart.png', '2026-02-22 20:18:27'),
(6, 6, 'admin', 'kfkfk', NULL, NULL, '2026-02-22 20:18:30'),
(7, 6, 'admin', NULL, 'chat_699b5d416de3e0.64871680.pdf', '1TPIF - SYSOP1 A3.pdf', '2026-02-22 20:47:13'),
(8, 6, 'admin', 'жыпьвэроьаыэ', NULL, NULL, '2026-02-23 17:16:52'),
(9, 6, 'admin', 'до', NULL, NULL, '2026-02-23 18:33:14'),
(10, 6, 'admin', NULL, 'chat_699c8f6caed923.84303907.png', 'chart.png', '2026-02-23 18:33:32'),
(11, 6, 'admin', NULL, 'chat_699c8fa3e74f78.22434998.png', 'chart.png', '2026-02-23 18:34:27'),
(12, 6, 'admin', NULL, 'chat_699c8fa3e8c5e8.43568959.docx', 'RAID6_clean.docx', '2026-02-23 18:34:27'),
(13, 6, 'admin', 'палдлж', NULL, NULL, '2026-02-23 18:34:27'),
(14, 6, 'admin', NULL, 'chat_699c91ec67b6f5.22984019.png', 'chart.png', '2026-02-23 18:44:12'),
(15, 6, 'admin', NULL, 'chat_699c91ec6d31c2.38867559.png', 'chart.png', '2026-02-23 18:44:12'),
(16, 6, 'admin', NULL, 'chat_699c91ec6f4d03.20471857.png', 'chart.png', '2026-02-23 18:44:12'),
(17, 6, 'admin', NULL, 'chat_699c91ec7039f3.79593182.png', 'chart.png', '2026-02-23 18:44:12'),
(18, 6, 'admin', NULL, 'chat_699c91ec716e87.86115580.png', 'chart.png', '2026-02-23 18:44:12'),
(19, 6, 'admin', NULL, 'chat_699c95bdeb80f7.84403933.csv', 'measurements_20260222_165625.csv', '2026-02-23 19:00:29'),
(20, 6, 'admin', NULL, 'chat_699c95e51a9494.69586333.csv', 'measurements_20260222_165625.csv', '2026-02-23 19:01:09'),
(21, 6, 'admin', NULL, 'chat_699c9631278575.33222054.csv', 'measurements_20260222_154603.csv', '2026-02-23 19:02:25'),
(22, 6, 'admin', NULL, 'chat_699c96587a4052.43916577.csv', 'measurements_20260222_165625.csv', '2026-02-23 19:03:04'),
(23, 6, 'admin', NULL, 'chat_699c96698d0e72.79999109.png', 'chart(3).png', '2026-02-23 19:03:21'),
(24, 6, 'admin', 'яврпаов', NULL, NULL, '2026-02-23 19:03:31'),
(25, 6, 'admin', NULL, 'chat_699c96f4013c70.25984529.txt', 'ТЗ.txt', '2026-02-23 19:05:40'),
(26, 6, 'admin', 'тз тест', NULL, NULL, '2026-02-23 19:05:40'),
(27, 6, 'admin', NULL, 'chat_699c9735c6a2c3.55207882.png', 'chart(1).png', '2026-02-23 19:06:45'),
(28, 6, 'admin', 'ор', NULL, NULL, '2026-02-23 19:08:19'),
(29, 6, 'admin', NULL, 'chat_699c97ada49c07.43493490.png', 'chart(1).png', '2026-02-23 19:08:45'),
(30, 6, 'admin', NULL, 'chat_699c991374f081.03271888.png', 'chart(1).png', '2026-02-23 19:14:43'),
(31, 6, 'admin', NULL, 'chat_699c99f28cc896.42385980.png', 'chart(1).png', '2026-02-23 19:18:26'),
(32, 6, 'admin', 'afgdg', NULL, NULL, '2026-03-01 12:58:08'),
(33, 6, 'admin', NULL, 'chat_69a429dd3060a3.85815603.png', 'chart(1).png', '2026-03-01 12:58:21'),
(34, 6, 'admin', 'sfgdbd', NULL, NULL, '2026-03-01 12:58:21'),
(35, 6, 'admin', NULL, 'chat_69a477feb58a09.74050584.png', 'image (5).png', '2026-03-01 18:31:42'),
(36, 6, 'admin', NULL, 'chat_69a477febb4c92.26712312.png', 'image (4).png', '2026-03-01 18:31:42'),
(37, 6, 'admin', NULL, 'chat_69a477febd0079.93349378.png', 'image (3).png', '2026-03-01 18:31:42'),
(38, 6, 'admin', 'sfdfgh', NULL, NULL, '2026-03-01 18:31:42'),
(39, 8, 'maria', 'hello', NULL, NULL, '2026-03-23 16:08:21'),
(40, 11, 'alex', 'hello', NULL, NULL, '2026-03-30 00:24:32'),
(41, 11, 'alex', 'its me', NULL, NULL, '2026-03-30 00:24:36'),
(42, 12, 'alex', 'hello', NULL, NULL, '2026-03-30 00:28:07'),
(43, 12, 'alex', 'how are you', NULL, NULL, '2026-03-30 00:28:13'),
(44, 12, NULL, 'goood', NULL, NULL, '2026-03-30 00:28:26'),
(45, 12, NULL, 'goood', NULL, NULL, '2026-03-30 00:28:38'),
(46, 12, NULL, 'good', NULL, NULL, '2026-03-30 00:28:45'),
(47, 12, 'alex', 'good', NULL, NULL, '2026-03-30 00:28:55'),
(48, 12, NULL, 'dg', NULL, NULL, '2026-03-30 00:29:03'),
(49, 12, NULL, 'thf', NULL, NULL, '2026-03-30 00:29:41'),
(50, 12, NULL, 'xf\\\\', NULL, NULL, '2026-03-30 00:30:14'),
(51, 12, NULL, 'fghj', NULL, NULL, '2026-03-30 00:30:53'),
(52, 8, 'admin', 'fgk', NULL, NULL, '2026-03-30 00:32:12'),
(53, 7, 'maria', NULL, 'chat_69ce578397f3f6.91554983.png', 'chart_temperature_20260331_170604.png', '2026-04-02 13:48:19'),
(54, 7, 'maria', NULL, 'chat_69ce5783a1bdc9.84108451.png', 'chart_airQuality_20260331_223851.png', '2026-04-02 13:48:19'),
(55, 7, 'maria', 'ewjtuj', NULL, NULL, '2026-04-02 13:48:19'),
(56, 14, NULL, 'hello', NULL, NULL, '2026-04-02 17:25:20'),
(57, 14, 'admin', 'hi', NULL, NULL, '2026-04-02 17:25:38'),
(58, 14, 'admin', 'jfj', NULL, NULL, '2026-04-02 17:25:52'),
(59, 13, 'maria', 'sflkgd', NULL, NULL, '2026-04-02 17:53:20'),
(60, 8, 'maria', 'ffff', NULL, NULL, '2026-04-02 18:25:31'),
(61, 8, 'maria', 'ssssss', NULL, NULL, '2026-04-02 18:26:26'),
(62, 8, 'admin', 'ааааа', NULL, NULL, '2026-04-02 18:30:07'),
(63, 8, 'admin', 'ааааа', NULL, NULL, '2026-04-02 18:30:20'),
(64, 15, 'admin', 'hello', NULL, NULL, '2026-04-02 19:12:45'),
(65, 8, 'maria', 'ffff', NULL, NULL, '2026-04-02 19:14:04'),
(66, 15, 'admin', 'sdg', NULL, NULL, '2026-04-02 19:14:11'),
(67, 8, 'maria', 'fdg', NULL, NULL, '2026-04-02 19:14:15'),
(68, 8, 'maria', 'алп', NULL, NULL, '2026-04-02 19:22:32'),
(69, 8, 'maria', 'gjgjdj', NULL, NULL, '2026-04-02 20:09:17'),
(70, 8, 'admin', NULL, 'chat_69ceb4310ce439.80057813.png', 'chart_airQuality_20260331_223851.png', '2026-04-02 20:23:45'),
(71, 8, 'admin', NULL, 'chat_69ceb431100dc0.95070391.png', 'chart_temperature_20260331_170604.png', '2026-04-02 20:23:45'),
(72, 8, 'admin', 'алфлп', NULL, NULL, '2026-04-02 20:23:45'),
(73, 8, 'admin', NULL, 'chat_69ceb523678821.74907701.png', 'chart_airQuality_20260331_223851.png', '2026-04-02 20:27:47'),
(74, 8, 'admin', 'алфлп', NULL, NULL, '2026-04-02 20:27:47'),
(75, 8, 'admin', NULL, 'chat_69ceb8fab2a9d9.08674767.png', 'chart_temperature_st_measurements-test1_10032026.png', '2026-04-02 20:44:10'),
(76, 8, 'admin', 'одор', NULL, NULL, '2026-04-02 20:44:10'),
(77, 18, NULL, 'dflksglhk', NULL, NULL, '2026-04-02 21:55:32'),
(78, 18, NULL, 'dsflbsgbl\'s', NULL, NULL, '2026-04-02 21:55:33'),
(79, 19, NULL, 'aslfgdflgk', NULL, NULL, '2026-04-02 21:55:48'),
(80, 19, NULL, 'as;sdkbds', NULL, NULL, '2026-04-02 21:55:49'),
(81, 20, NULL, 'sfdgsfh', NULL, NULL, '2026-04-02 21:58:10'),
(82, 20, NULL, 'asfg', NULL, NULL, '2026-04-02 21:58:11'),
(83, 7, 'alex', '[[sys:left_group|alex|Oleksandr Petrov]]', NULL, NULL, '2026-04-02 22:25:29'),
(84, 7, 'admin', '[[sys:left_group|admin|System Admin]]', NULL, NULL, '2026-04-02 22:25:57'),
(88, 7, 'admin', '[[sys:left_group|admin|System Admin]]', NULL, NULL, '2026-04-02 22:44:37'),
(89, 7, 'admin', '[[sys:joined_group|admin|System Admin]]', NULL, NULL, '2026-04-02 22:44:53'),
(90, 22, 'cheol904', '[[sys:joined_group|cheol904|Oleksandr Cherepkov]]', NULL, NULL, '2026-04-02 22:45:10'),
(91, 15, NULL, NULL, 'chat_69cf73ad04c161.62444457.csv', 'measurements_st_measurements-test1_t10032026.csv', '2026-04-03 10:00:45'),
(92, 23, 'admin', '[[sys:joined_group|admin|System Admin]]', NULL, NULL, '2026-04-03 10:01:25'),
(93, 23, NULL, '[[sys:joined_group|test2|1 1]]', NULL, NULL, '2026-04-03 10:01:25'),
(94, 23, NULL, NULL, 'chat_69cf73e1b4ee05.97036965.png', 'chart_temperature_st_measurements-test1_10032026.png', '2026-04-03 10:01:37'),
(95, 9, NULL, '[[sys:left_group|test2|1 1]]', NULL, NULL, '2026-04-03 10:02:12'),
(96, 23, NULL, '[[sys:left_group|test2|1 1]]', NULL, NULL, '2026-04-03 10:02:12'),
(97, 13, 'maria', 'ыа', NULL, NULL, '2026-04-03 11:20:59'),
(98, 15, 'admin', 'jj', NULL, NULL, '2026-04-07 13:51:06'),
(99, 23, 'admin', 'hh', NULL, NULL, '2026-04-07 13:51:22'),
(100, 23, 'admin', 'bb', NULL, NULL, '2026-04-07 13:51:25'),
(101, 23, 'admin', 'nn', NULL, NULL, '2026-04-07 13:51:29'),
(102, 23, 'admin', 'jj', NULL, NULL, '2026-04-07 13:51:39'),
(103, 23, 'admin', 'nm.mn', NULL, NULL, '2026-04-07 13:51:58'),
(104, 23, 'admin', 'ввв', NULL, NULL, '2026-04-08 15:13:44'),
(105, 1, 'admin', 'пп', NULL, NULL, '2026-04-08 21:19:36'),
(106, 24, 'admin', 'ааа', NULL, NULL, '2026-04-09 12:14:03'),
(107, 27, 'krud', 'Ya v ahue', NULL, NULL, '2026-04-13 10:32:51'),
(108, 27, 'admin', 'ya tezh', NULL, NULL, '2026-04-13 10:33:01'),
(109, 27, 'admin', NULL, 'chat_69dcaa93e61e01.32620279.png', 'Screenshot 2026-04-13 103405.png', '2026-04-13 10:34:27'),
(110, 28, 'cheol904', 'davay vmesto tg ispolsovat', NULL, NULL, '2026-04-13 10:35:04'),
(111, 27, 'krud', '👍', NULL, NULL, '2026-04-13 10:35:23'),
(112, 27, 'admin', 'c=3', NULL, NULL, '2026-04-13 10:35:43'),
(113, 29, 'admin', '[[sys:joined_group|admin|System Admin]]', NULL, NULL, '2026-04-13 10:35:53'),
(114, 29, 'cheol904', '[[sys:joined_group|cheol904|Oleksandrw Cherepkovw]]', NULL, NULL, '2026-04-13 10:35:53'),
(115, 29, 'krud', '[[sys:joined_group|krud|K R]]', NULL, NULL, '2026-04-13 10:37:20'),
(116, 29, 'cheol904', 'mojno vmesto tg ispolsovat', NULL, NULL, '2026-04-13 10:37:56'),
(117, 29, 'admin', 'kyn\' ludyam z rosii', NULL, NULL, '2026-04-13 10:40:08'),
(118, 29, 'admin', 'yim korysno bude', NULL, NULL, '2026-04-13 10:40:15'),
(119, 29, 'krud', '😂🤣😂🤣😂🤣', NULL, NULL, '2026-04-13 10:40:55'),
(120, 29, 'cheol904', 'pereimenovka v max', NULL, NULL, '2026-04-13 10:41:17'),
(121, 3, 'admin', 'uui', NULL, NULL, '2026-04-13 10:54:38'),
(122, 3, 'cheol904', '????', NULL, NULL, '2026-04-13 11:28:13');

-- --------------------------------------------------------

--
-- Структура таблиці `chat_participant`
--

CREATE TABLE `chat_participant` (
  `fk_conversation` int(11) NOT NULL,
  `fk_user` varchar(50) NOT NULL,
  `joinedAt` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп даних таблиці `chat_participant`
--

INSERT INTO `chat_participant` (`fk_conversation`, `fk_user`, `joinedAt`) VALUES
(1, 'admin', '2026-02-22 00:11:06'),
(1, 'alex', '2026-02-22 00:11:06'),
(3, 'admin', '2026-02-22 00:32:12'),
(3, 'cheol904', '2026-02-22 00:32:12'),
(4, 'alex', '2026-02-22 18:06:04'),
(4, 'cheol904', '2026-02-22 18:06:04'),
(6, 'admin', '2026-02-22 20:17:42'),
(6, 'cheol904', '2026-02-23 14:57:47'),
(7, 'admin', '2026-04-02 22:44:53'),
(7, 'cheol904', '2026-02-22 20:22:52'),
(7, 'maria', '2026-04-02 21:04:45'),
(8, 'admin', '2026-02-22 20:51:41'),
(8, 'maria', '2026-02-22 20:51:41'),
(9, 'admin', '2026-02-23 15:41:25'),
(9, 'alex', '2026-02-23 15:41:25'),
(9, 'cheol904', '2026-02-23 15:41:25'),
(9, 'test3', '2026-02-23 15:51:20'),
(10, 'admin', '2026-03-15 18:18:55'),
(10, 'testtime', '2026-03-15 18:18:55'),
(11, 'alex', '2026-03-30 00:24:24'),
(12, 'alex', '2026-03-30 00:27:57'),
(13, 'cheol904', '2026-04-01 18:14:44'),
(13, 'maria', '2026-04-01 18:14:44'),
(14, 'admin', '2026-04-02 17:23:57'),
(14, 'cheol904', '2026-04-02 17:23:57'),
(14, 'maria', '2026-04-02 17:23:57'),
(15, 'admin', '2026-04-02 19:12:40'),
(16, 'admin', '2026-04-02 19:40:53'),
(16, 'mailtest1', '2026-04-02 19:40:53'),
(17, 'mailtest1', '2026-04-02 19:57:09'),
(17, 'maria', '2026-04-02 19:57:09'),
(18, 'maria', '2026-04-02 21:55:29'),
(19, 'admin', '2026-04-02 21:55:46'),
(20, 'admin', '2026-04-02 21:58:07'),
(20, 'maria', '2026-04-02 21:58:07'),
(22, 'cheol904', '2026-04-02 22:45:10'),
(23, 'admin', '2026-04-03 10:01:25'),
(24, 'admin', '2026-04-08 14:58:52'),
(24, 'fadmin', '2026-04-08 14:58:52'),
(25, 'admin', '2026-04-08 15:13:54'),
(25, 'и', '2026-04-08 15:13:54'),
(26, 'admin', '2026-04-08 18:42:13'),
(26, 'test3', '2026-04-08 18:42:13'),
(27, 'admin', '2026-04-13 10:32:40'),
(27, 'krud', '2026-04-13 10:32:40'),
(28, 'cheol904', '2026-04-13 10:34:31'),
(28, 'krud', '2026-04-13 10:34:31'),
(29, 'admin', '2026-04-13 10:35:53'),
(29, 'cheol904', '2026-04-13 10:35:53'),
(29, 'krud', '2026-04-13 10:37:20');

-- --------------------------------------------------------

--
-- Структура таблиці `chat_read_state`
--

CREATE TABLE `chat_read_state` (
  `fk_conversation` int(11) NOT NULL,
  `fk_user` varchar(50) NOT NULL,
  `lastReadMessageId` int(11) DEFAULT NULL,
  `updatedAt` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп даних таблиці `chat_read_state`
--

INSERT INTO `chat_read_state` (`fk_conversation`, `fk_user`, `lastReadMessageId`, `updatedAt`) VALUES
(1, 'admin', 105, '2026-04-13 10:34:46'),
(3, 'admin', 122, '2026-04-13 11:29:47'),
(3, 'cheol904', 122, '2026-04-13 11:28:13'),
(6, 'admin', 38, '2026-04-08 15:14:24'),
(6, 'cheol904', 38, '2026-04-02 22:44:16'),
(7, 'admin', 89, '2026-04-10 13:50:02'),
(7, 'cheol904', 89, '2026-04-03 09:58:26'),
(7, 'maria', 89, '2026-04-03 11:28:16'),
(8, 'admin', 76, '2026-04-13 10:25:40'),
(8, 'maria', 76, '2026-04-03 11:06:38'),
(9, 'admin', 95, '2026-04-10 13:48:53'),
(10, 'admin', NULL, '2026-04-13 10:54:13'),
(13, 'cheol904', 97, '2026-04-13 10:37:40'),
(13, 'maria', 97, '2026-04-03 11:28:14'),
(14, 'admin', 58, '2026-04-08 15:14:25'),
(14, 'cheol904', 58, '2026-04-13 10:37:41'),
(14, 'maria', 58, '2026-04-03 11:28:10'),
(15, 'admin', 98, '2026-04-10 13:48:58'),
(15, 'test2', 91, '2026-04-03 10:00:45'),
(16, 'admin', NULL, '2026-04-08 15:20:35'),
(17, 'maria', NULL, '2026-04-02 19:58:02'),
(18, 'maria', 78, '2026-04-03 11:06:35'),
(18, 'wtrey', 78, '2026-04-02 21:55:33'),
(19, 'admin', 80, '2026-04-08 15:14:28'),
(19, 'wtrey', 80, '2026-04-02 21:55:50'),
(20, 'admin', 82, '2026-04-10 13:48:54'),
(20, 'maria', 82, '2026-04-03 11:28:15'),
(20, 'wtrey', 82, '2026-04-02 21:58:11'),
(22, 'cheol904', 90, '2026-04-02 23:00:21'),
(23, 'admin', 104, '2026-04-13 10:34:49'),
(24, 'admin', 106, '2026-04-10 13:48:51'),
(25, 'admin', NULL, '2026-04-08 17:56:13'),
(26, 'admin', NULL, '2026-04-08 18:42:13'),
(27, 'admin', 112, '2026-04-13 11:41:31'),
(27, 'krud', 112, '2026-04-13 10:53:30'),
(28, 'cheol904', 110, '2026-04-13 10:35:04'),
(28, 'krud', 110, '2026-04-13 10:54:50'),
(29, 'admin', 120, '2026-04-13 10:41:19'),
(29, 'cheol904', 120, '2026-04-13 10:41:17'),
(29, 'krud', 120, '2026-04-13 10:53:32');

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
(3, 'в', '', 'admin', '2026-01-16 14:55:21'),
(4, 'вв', ';jkg,fg', 'admin', '2026-01-16 14:55:52'),
(5, 'CollectionLimitTest1', 'et', 'alex', '2026-01-19 19:15:10'),
(10, 'qwerty', 'fsn;k', 'cheol904', '2026-02-22 15:47:31'),
(12, 'ыпф', 'ыап', 'fadmin', '2026-02-22 16:43:11'),
(13, 'ымив', 'ыпаи', 'admin', '2026-02-22 16:43:21'),
(15, 'ввввв', 'to test collection funcionalityааа', 'admin', '2026-04-01 17:00:45'),
(16, 'вввв', 'dghвв', 'admin', '2026-04-07 16:51:46'),
(17, 'admin test collection', 'to test admins', 'fadmin', '2026-04-07 17:43:03'),
(18, 'ввв', 'улулу', 'fadmin', '2026-04-08 21:23:15'),
(19, 'ввв', 'цйкйОПУПШОЫВПРХОПЭЫЗВАИЭЫМЬЯВЩОРШФХПОХРПОЫЗЪВПФЗФЫАРХПФЪАВРОЫПЛЫЪПОРЪЗПЩФЪЗВРЩОЪЗЩПФЪЕРШОЪФПЩОЫЪНЗТОПЩФЪЗРЩЫЪПРОФЕЪРкрфзшеорЗШПФЖЩПТхишт', 'admin', '2026-04-09 15:19:09'),
(20, 'lesia', '', 'admin', '2026-04-10 13:43:40'),
(21, 'collection kr', '', 'krud', '2026-04-13 11:14:04');

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
(3, 17),
(6, 17),
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
(123456, 17),
(123576, 15),
(123577, 15),
(123578, 15),
(123579, 15),
(123580, 15),
(123581, 15),
(123582, 15),
(123583, 15),
(123584, 15),
(123585, 15),
(123586, 15),
(123587, 15),
(123588, 15),
(123589, 15),
(123590, 15),
(123591, 15),
(123592, 15),
(123593, 15),
(123594, 15),
(123595, 15),
(123606, 17),
(123607, 17),
(123608, 17),
(123609, 17),
(123618, 17),
(123619, 17),
(123620, 16),
(123625, 16),
(123627, 20),
(123628, 20),
(123629, 20),
(123630, 20),
(123631, 20),
(123632, 20),
(123633, 20),
(123634, 20),
(123635, 20),
(123636, 20),
(123637, 20),
(123638, 20),
(123639, 20),
(123640, 15),
(123640, 20),
(123641, 15),
(123641, 20),
(123642, 15),
(123642, 20),
(123643, 15),
(123643, 20),
(123644, 15),
(123644, 20),
(123645, 15),
(123645, 20),
(123646, 15),
(123646, 20),
(123647, 15),
(123647, 20),
(123648, 15),
(123648, 20),
(123649, 15),
(123649, 20),
(123650, 15),
(123650, 20),
(123651, 15),
(123651, 20),
(123652, 15),
(123652, 20),
(123653, 15),
(123653, 20),
(123654, 15),
(123654, 20),
(123655, 15),
(123655, 20),
(123656, 15),
(123656, 20),
(123657, 15),
(123657, 20),
(123658, 15),
(123658, 20),
(123659, 15),
(123659, 20),
(123660, 15),
(123660, 20),
(123661, 15),
(123661, 20),
(123662, 15),
(123662, 20),
(123663, 15),
(123663, 20),
(123664, 15),
(123664, 20),
(123665, 15),
(123665, 20),
(123666, 15),
(123666, 20),
(123667, 15),
(123667, 20),
(123668, 16),
(123668, 20),
(123669, 16),
(123669, 20),
(123670, 16),
(123670, 20),
(123671, 16),
(123671, 20),
(123672, 16),
(123672, 20),
(123673, 16),
(123673, 20),
(123674, 16),
(123674, 20),
(123675, 16),
(123675, 20);

-- --------------------------------------------------------

--
-- Структура таблиці `email_verification`
--

CREATE TABLE `email_verification` (
  `pk_verificationID` int(11) NOT NULL,
  `fk_user` varchar(50) NOT NULL,
  `token` varchar(255) NOT NULL,
  `expiresAt` datetime NOT NULL,
  `used` tinyint(4) NOT NULL DEFAULT 0,
  `createdAt` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп даних таблиці `email_verification`
--

INSERT INTO `email_verification` (`pk_verificationID`, `fk_user`, `token`, `expiresAt`, `used`, `createdAt`) VALUES
(2, 'testmail', '9b193d8908467c2ecf604812b95e4baf10c326179d4ff05f47724fb68f1b40a9', '2026-03-07 17:15:51', 0, '2026-03-06 17:15:51'),
(3, 'testmail', 'd4498551e23602bf7d2cc5b635c6b73976f21de5e4af5c8c1587326da3db038b', '2026-03-07 17:16:18', 1, '2026-03-06 17:16:18'),
(4, 'maria', '03b5db050feaf2f7df2989e6a44a2eb68670ca3ada79efaf369e5b39380fec30', '2026-03-07 17:44:50', 1, '2026-03-06 17:44:50'),
(5, 'admin', '251bea2137175f92205323db7f5bdbc9fb5a30c8e26685453601fb7f3bd771e9', '2026-03-07 17:46:05', 1, '2026-03-06 17:46:05'),
(6, 'testtime', 'fb168d84c7916ceec7e90d3dd53cac4c4fba4760cd76867134cd13a60d007355', '2026-03-07 18:06:15', 0, '2026-03-06 18:06:15'),
(7, 'testtime', '489c890274a61e2a8c3ae014b8dc0fcb73bba0290c3b934ede5d0ac4f7f60c89', '2026-03-07 18:06:30', 1, '2026-03-06 18:06:30'),
(8, 'testtime', '3bd390fe92c00d6df28d80db1c8309eeb0cae85bc5dbd7e3de4b7ca478fd16e7', '2026-03-07 18:15:53', 0, '2026-03-06 18:15:53'),
(9, 'testtime', '9464edde2916cdd7a3b8f84a3f4f40c633a531d8dbdbeed284d54f54c81858a4', '2026-03-07 18:38:00', 1, '2026-03-06 18:38:00'),
(10, 'cheol904', '729c21ab607371520e0f28e81fc5a3ea3b2925d18ecd5a27e878939fdfc4aaaf', '2026-03-24 16:08:59', 0, '2026-03-23 16:08:59'),
(11, 'cheol904', 'abb647420bbf71f2ba9f7d938a36710abe437795deda70a57b5d12a0d41a1f9c', '2026-03-24 16:09:03', 0, '2026-03-23 16:09:03'),
(12, 'cheol904', '5cf20b6338f4834e9612625536ec882d1e82ca14913c7efa2db8bda2fcd999fa', '2026-03-24 16:10:52', 1, '2026-03-23 16:10:52'),
(13, 'alex', '42fc1df4c14c47a56a1da2331922eeee9fa7b3458c55920b0661883d022c32b6', '2026-03-30 21:43:29', 1, '2026-03-29 21:43:29');

-- --------------------------------------------------------

--
-- Структура таблиці `friendship`
--

CREATE TABLE `friendship` (
  `pkfk_user1` varchar(50) NOT NULL,
  `pkfk_user2` varchar(50) NOT NULL,
  `createdAt` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп даних таблиці `friendship`
--

INSERT INTO `friendship` (`pkfk_user1`, `pkfk_user2`, `createdAt`) VALUES
('admin', 'alex', '2026-03-30 00:23:54'),
('admin', 'cheol904', '2026-04-01 20:29:29'),
('admin', 'fadmin', '2026-04-08 21:24:03'),
('admin', 'krud', '2026-04-13 10:32:36'),
('admin', 'testmail', '2026-04-08 17:29:08'),
('admin', 'testtime', '2026-04-08 16:40:53'),
('admin', 'и', '2026-04-08 14:42:27'),
('admin', 'ШШШШШШШШШШШШШШШШШШШШ', '2026-04-10 13:54:07'),
('alex', 'maria', '2025-03-10 09:00:00'),
('cheol904', 'krud', '2026-04-13 10:36:35'),
('cheol904', 'maria', '2026-04-01 19:09:45'),
('mailtest1', 'admin', '2026-04-08 14:45:24'),
('maria', 'admin', '2026-01-16 17:22:56'),
('newuser', 'admin', '2026-04-08 16:40:53');

-- --------------------------------------------------------

--
-- Структура таблиці `measurement`
--

CREATE TABLE `measurement` (
  `pk_measurementID` int(11) NOT NULL,
  `timestamp` datetime NOT NULL,
  `temperature` decimal(5,2) DEFAULT NULL,
  `airPressure` decimal(6,2) DEFAULT NULL,
  `lightIntensity` decimal(6,2) DEFAULT NULL,
  `airQuality` decimal(6,2) DEFAULT NULL,
  `fk_station` varchar(50) NOT NULL,
  `fk_ownerId` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп даних таблиці `measurement`
--

INSERT INTO `measurement` (`pk_measurementID`, `timestamp`, `temperature`, `airPressure`, `lightIntensity`, `airQuality`, `fk_station`, `fk_ownerId`) VALUES
(1, '2025-03-12 08:00:00', 12.50, 1012.30, 350.00, 600.00, 'ST001', 'alex'),
(2, '2025-03-12 12:00:00', 16.20, 1010.80, 400.00, 650.00, 'ST001', 'alex'),
(3, '2025-03-12 08:05:00', 22.10, 1008.50, 600.00, 95.00, 'ST002', 'maria'),
(6, '2026-01-31 16:31:01', 14.00, 4.00, 4.00, 4.00, 'ST002', 'maria'),
(10, '2026-01-19 18:47:02', 12.00, NULL, NULL, NULL, 'ST001', 'alex'),
(11, '2026-01-19 18:47:02', 13.00, NULL, NULL, NULL, 'ST001', 'alex'),
(12, '2026-03-09 18:48:56', 536.00, NULL, NULL, NULL, 'ST001', 'alex'),
(14, '2026-01-19 19:12:09', 12.00, NULL, NULL, NULL, 'ST001', 'alex'),
(15, '2026-01-19 19:12:09', 45.00, NULL, NULL, NULL, 'ST001', 'alex'),
(16, '2026-01-19 19:12:09', 78.00, NULL, NULL, NULL, 'ST001', 'alex'),
(17, '2026-01-19 19:12:09', 101.00, NULL, NULL, NULL, 'ST001', 'alex'),
(18, '2026-01-19 19:12:09', 33.00, NULL, NULL, NULL, 'ST001', 'alex'),
(19, '2026-01-19 19:12:09', 67.00, NULL, NULL, NULL, 'ST001', 'alex'),
(20, '2026-01-19 19:12:09', 89.00, NULL, NULL, NULL, 'ST001', 'alex'),
(21, '2026-01-19 19:12:09', 14.00, NULL, NULL, NULL, 'ST001', 'alex'),
(22, '2026-01-19 19:12:09', 55.00, NULL, NULL, NULL, 'ST001', 'alex'),
(23, '2026-01-19 19:12:09', 92.00, NULL, NULL, NULL, 'ST001', 'alex'),
(24, '2026-01-19 19:12:09', 38.00, NULL, NULL, NULL, 'ST001', 'alex'),
(25, '2026-01-19 19:12:09', 73.00, NULL, NULL, NULL, 'ST001', 'alex'),
(26, '2026-01-19 19:12:09', 64.00, NULL, NULL, NULL, 'ST001', 'alex'),
(27, '2026-01-19 19:12:09', 27.00, NULL, NULL, NULL, 'ST001', 'alex'),
(28, '2026-01-19 19:12:09', 81.00, NULL, NULL, NULL, 'ST001', 'alex'),
(29, '2026-01-19 19:12:09', 49.00, NULL, NULL, NULL, 'ST001', 'alex'),
(30, '2026-01-19 19:12:09', 95.00, NULL, NULL, NULL, 'ST001', 'alex'),
(31, '2026-01-19 19:12:09', 58.00, NULL, NULL, NULL, 'ST001', 'alex'),
(32, '2026-01-19 19:12:09', 11.00, NULL, NULL, NULL, 'ST001', 'alex'),
(33, '2026-01-19 19:12:09', 66.00, NULL, NULL, NULL, 'ST001', 'alex'),
(34, '2026-01-19 19:12:09', 74.00, NULL, NULL, NULL, 'ST001', 'alex'),
(35, '2026-01-19 19:12:09', 82.00, NULL, NULL, NULL, 'ST001', 'alex'),
(36, '2026-01-19 19:12:09', 93.00, NULL, NULL, NULL, 'ST001', 'alex'),
(37, '2026-01-19 19:12:09', 25.00, NULL, NULL, NULL, 'ST001', 'alex'),
(38, '2026-01-19 19:12:09', 47.00, NULL, NULL, NULL, 'ST001', 'alex'),
(39, '2026-01-19 19:12:09', 59.00, NULL, NULL, NULL, 'ST001', 'alex'),
(40, '2026-01-19 19:12:09', 36.00, NULL, NULL, NULL, 'ST001', 'alex'),
(41, '2026-01-19 19:12:09', 44.00, NULL, NULL, NULL, 'ST001', 'alex'),
(42, '2026-01-19 19:12:09', 71.00, NULL, NULL, NULL, 'ST001', 'alex'),
(43, '2026-01-19 19:12:09', 83.00, NULL, NULL, NULL, 'ST001', 'alex'),
(44, '2026-01-19 19:12:09', 19.00, NULL, NULL, NULL, 'ST001', 'alex'),
(45, '2026-01-19 19:12:09', 52.00, NULL, NULL, NULL, 'ST001', 'alex'),
(46, '2026-01-19 19:12:09', 68.00, NULL, NULL, NULL, 'ST001', 'alex'),
(47, '2026-01-19 19:12:09', 97.00, NULL, NULL, NULL, 'ST001', 'alex'),
(48, '2026-01-19 19:12:09', 41.00, NULL, NULL, NULL, 'ST001', 'alex'),
(49, '2026-01-19 19:12:09', 63.00, NULL, NULL, NULL, 'ST001', 'alex'),
(50, '2026-01-19 19:12:09', 88.00, NULL, NULL, NULL, 'ST001', 'alex'),
(51, '2026-01-19 19:12:09', 29.00, NULL, NULL, NULL, 'ST001', 'alex'),
(52, '2026-01-19 19:12:09', 57.00, NULL, NULL, NULL, 'ST001', 'alex'),
(53, '2026-01-19 19:12:09', 76.00, NULL, NULL, NULL, 'ST001', 'alex'),
(54, '2026-01-19 19:12:09', 84.00, NULL, NULL, NULL, 'ST001', 'alex'),
(56, '2026-01-19 19:12:09', 23.00, NULL, NULL, NULL, 'ST001', 'alex'),
(57, '2026-01-19 19:12:09', 34.00, NULL, NULL, NULL, 'ST001', 'alex'),
(58, '2026-01-19 19:12:09', 62.00, NULL, NULL, NULL, 'ST001', 'alex'),
(59, '2026-01-19 19:12:09', 79.00, NULL, NULL, NULL, 'ST001', 'alex'),
(60, '2026-01-19 19:12:09', 96.00, NULL, NULL, NULL, 'ST001', 'alex'),
(61, '2026-01-19 19:12:09', 53.00, NULL, NULL, NULL, 'ST001', 'alex'),
(62, '2026-01-19 19:12:09', 17.00, NULL, NULL, NULL, 'ST001', 'alex'),
(63, '2026-01-19 19:12:47', 12.00, NULL, NULL, NULL, 'ST001', 'alex'),
(64, '2026-01-19 19:12:47', 45.00, NULL, NULL, NULL, 'ST001', 'alex'),
(65, '2026-01-19 19:12:47', 78.00, NULL, NULL, NULL, 'ST001', 'alex'),
(66, '2026-01-19 19:12:47', 101.00, NULL, NULL, NULL, 'ST001', 'alex'),
(67, '2026-01-19 19:12:47', 33.00, NULL, NULL, NULL, 'ST001', 'alex'),
(68, '2026-01-19 19:12:47', 67.00, NULL, NULL, NULL, 'ST001', 'alex'),
(69, '2026-01-19 19:12:47', 89.00, NULL, NULL, NULL, 'ST001', 'alex'),
(70, '2026-01-19 19:12:47', 14.00, NULL, NULL, NULL, 'ST001', 'alex'),
(71, '2026-01-19 19:12:47', 55.00, NULL, NULL, NULL, 'ST001', 'alex'),
(72, '2026-01-19 19:12:47', 92.00, NULL, NULL, NULL, 'ST001', 'alex'),
(73, '2026-01-19 19:12:47', 38.00, NULL, NULL, NULL, 'ST001', 'alex'),
(74, '2026-01-19 19:12:47', 73.00, NULL, NULL, NULL, 'ST001', 'alex'),
(75, '2026-01-19 19:12:47', 64.00, NULL, NULL, NULL, 'ST001', 'alex'),
(76, '2026-01-19 19:12:47', 27.00, NULL, NULL, NULL, 'ST001', 'alex'),
(77, '2026-01-19 19:12:47', 81.00, NULL, NULL, NULL, 'ST001', 'alex'),
(78, '2026-01-19 19:12:47', 49.00, NULL, NULL, NULL, 'ST001', 'alex'),
(79, '2026-01-19 19:12:47', 95.00, NULL, NULL, NULL, 'ST001', 'alex'),
(80, '2026-01-19 19:12:47', 58.00, NULL, NULL, NULL, 'ST001', 'alex'),
(81, '2026-01-19 19:12:47', 11.00, NULL, NULL, NULL, 'ST001', 'alex'),
(82, '2026-01-19 19:12:47', 66.00, NULL, NULL, NULL, 'ST001', 'alex'),
(83, '2026-01-19 19:12:47', 74.00, NULL, NULL, NULL, 'ST001', 'alex'),
(84, '2026-01-19 19:12:47', 82.00, NULL, NULL, NULL, 'ST001', 'alex'),
(85, '2026-01-19 19:12:47', 93.00, NULL, NULL, NULL, 'ST001', 'alex'),
(86, '2026-01-19 19:12:47', 25.00, NULL, NULL, NULL, 'ST001', 'alex'),
(87, '2026-01-19 19:12:47', 47.00, NULL, NULL, NULL, 'ST001', 'alex'),
(88, '2026-01-19 19:12:47', 59.00, NULL, NULL, NULL, 'ST001', 'alex'),
(89, '2026-01-19 19:12:47', 36.00, NULL, NULL, NULL, 'ST001', 'alex'),
(90, '2026-01-19 19:12:47', 44.00, NULL, NULL, NULL, 'ST001', 'alex'),
(91, '2026-01-19 19:12:47', 71.00, NULL, NULL, NULL, 'ST001', 'alex'),
(92, '2026-01-19 19:12:47', 83.00, NULL, NULL, NULL, 'ST001', 'alex'),
(93, '2026-01-19 19:12:47', 19.00, NULL, NULL, NULL, 'ST001', 'alex'),
(94, '2026-01-19 19:12:47', 52.00, NULL, NULL, NULL, 'ST001', 'alex'),
(95, '2026-01-19 19:12:47', 68.00, NULL, NULL, NULL, 'ST001', 'alex'),
(96, '2026-01-19 19:12:47', 97.00, NULL, NULL, NULL, 'ST001', 'alex'),
(97, '2026-01-19 19:12:47', 41.00, NULL, NULL, NULL, 'ST001', 'alex'),
(98, '2026-01-19 19:12:47', 63.00, NULL, NULL, NULL, 'ST001', 'alex'),
(99, '2026-01-19 19:12:47', 88.00, NULL, NULL, NULL, 'ST001', 'alex'),
(100, '2026-01-19 19:12:47', 29.00, NULL, NULL, NULL, 'ST001', 'alex'),
(101, '2026-01-19 19:12:47', 57.00, NULL, NULL, NULL, 'ST001', 'alex'),
(102, '2026-01-19 19:12:47', 76.00, NULL, NULL, NULL, 'ST001', 'alex'),
(103, '2026-01-19 19:12:47', 84.00, NULL, NULL, NULL, 'ST001', 'alex'),
(104, '2026-01-19 19:12:47', 91.00, NULL, NULL, NULL, 'ST001', 'alex'),
(105, '2026-01-19 19:12:47', 23.00, NULL, NULL, NULL, 'ST001', 'alex'),
(106, '2026-01-19 19:12:47', 34.00, NULL, NULL, NULL, 'ST001', 'alex'),
(107, '2026-01-19 19:12:47', 62.00, NULL, NULL, NULL, 'ST001', 'alex'),
(108, '2026-01-19 19:12:47', 79.00, NULL, NULL, NULL, 'ST001', 'alex'),
(109, '2026-01-19 19:12:47', 96.00, NULL, NULL, NULL, 'ST001', 'alex'),
(110, '2026-01-19 19:12:47', 53.00, NULL, NULL, NULL, 'ST001', 'alex'),
(111, '2026-01-19 19:12:47', 17.00, NULL, NULL, NULL, 'ST001', 'alex'),
(112, '2026-01-19 19:12:53', 12.00, NULL, NULL, NULL, 'ST001', 'alex'),
(113, '2026-01-19 19:12:53', 45.00, NULL, NULL, NULL, 'ST001', 'alex'),
(114, '2026-01-19 19:12:53', 78.00, NULL, NULL, NULL, 'ST001', 'alex'),
(115, '2026-01-19 19:12:53', 101.00, NULL, NULL, NULL, 'ST001', 'alex'),
(116, '2026-01-19 19:12:53', 33.00, NULL, NULL, NULL, 'ST001', 'alex'),
(117, '2026-01-19 19:12:53', 67.00, NULL, NULL, NULL, 'ST001', 'alex'),
(118, '2026-01-19 19:12:53', 89.00, NULL, NULL, NULL, 'ST001', 'alex'),
(119, '2026-01-19 19:12:53', 14.00, NULL, NULL, NULL, 'ST001', 'alex'),
(120, '2026-01-19 19:12:53', 55.00, NULL, NULL, NULL, 'ST001', 'alex'),
(121, '2026-01-19 19:12:53', 92.00, NULL, NULL, NULL, 'ST001', 'alex'),
(122, '2026-01-19 19:12:53', 38.00, NULL, NULL, NULL, 'ST001', 'alex'),
(123, '2026-01-19 19:12:53', 73.00, NULL, NULL, NULL, 'ST001', 'alex'),
(124, '2026-01-19 19:12:53', 64.00, NULL, NULL, NULL, 'ST001', 'alex'),
(125, '2026-01-19 19:12:53', 27.00, NULL, NULL, NULL, 'ST001', 'alex'),
(126, '2026-01-19 19:12:53', 81.00, NULL, NULL, NULL, 'ST001', 'alex'),
(127, '2026-01-19 19:12:53', 49.00, NULL, NULL, NULL, 'ST001', 'alex'),
(128, '2026-01-19 19:12:53', 95.00, NULL, NULL, NULL, 'ST001', 'alex'),
(129, '2026-01-19 19:12:53', 58.00, NULL, NULL, NULL, 'ST001', 'alex'),
(130, '2026-01-19 19:12:53', 11.00, NULL, NULL, NULL, 'ST001', 'alex'),
(131, '2026-01-19 19:12:53', 66.00, NULL, NULL, NULL, 'ST001', 'alex'),
(132, '2026-01-19 19:12:53', 74.00, NULL, NULL, NULL, 'ST001', 'alex'),
(133, '2026-01-19 19:12:53', 82.00, NULL, NULL, NULL, 'ST001', 'alex'),
(134, '2026-01-19 19:12:53', 93.00, NULL, NULL, NULL, 'ST001', 'alex'),
(135, '2026-01-19 19:12:53', 25.00, NULL, NULL, NULL, 'ST001', 'alex'),
(136, '2026-01-19 19:12:53', 47.00, NULL, NULL, NULL, 'ST001', 'alex'),
(137, '2026-01-19 19:12:53', 59.00, NULL, NULL, NULL, 'ST001', 'alex'),
(138, '2026-01-19 19:12:53', 36.00, NULL, NULL, NULL, 'ST001', 'alex'),
(139, '2026-01-19 19:12:53', 44.00, NULL, NULL, NULL, 'ST001', 'alex'),
(140, '2026-01-19 19:12:53', 71.00, NULL, NULL, NULL, 'ST001', 'alex'),
(141, '2026-01-19 19:12:53', 83.00, NULL, NULL, NULL, 'ST001', 'alex'),
(142, '2026-01-19 19:12:53', 19.00, NULL, NULL, NULL, 'ST001', 'alex'),
(143, '2026-01-19 19:12:53', 52.00, NULL, NULL, NULL, 'ST001', 'alex'),
(144, '2026-01-19 19:12:53', 68.00, NULL, NULL, NULL, 'ST001', 'alex'),
(145, '2026-01-19 19:12:53', 97.00, NULL, NULL, NULL, 'ST001', 'alex'),
(146, '2026-01-19 19:12:53', 41.00, NULL, NULL, NULL, 'ST001', 'alex'),
(147, '2026-01-19 19:12:53', 63.00, NULL, NULL, NULL, 'ST001', 'alex'),
(148, '2026-01-19 19:12:53', 88.00, NULL, NULL, NULL, 'ST001', 'alex'),
(149, '2026-01-19 19:12:53', 29.00, NULL, NULL, NULL, 'ST001', 'alex'),
(150, '2026-01-19 19:12:53', 57.00, NULL, NULL, NULL, 'ST001', 'alex'),
(151, '2026-01-19 19:12:53', 76.00, NULL, NULL, NULL, 'ST001', 'alex'),
(152, '2026-01-19 19:12:53', 84.00, NULL, NULL, NULL, 'ST001', 'alex'),
(153, '2026-01-19 19:12:53', 91.00, NULL, NULL, NULL, 'ST001', 'alex'),
(154, '2026-01-19 19:12:53', 23.00, NULL, NULL, NULL, 'ST001', 'alex'),
(155, '2026-01-19 19:12:53', 34.00, NULL, NULL, NULL, 'ST001', 'alex'),
(156, '2026-01-19 19:12:53', 62.00, NULL, NULL, NULL, 'ST001', 'alex'),
(157, '2026-01-19 19:12:53', 79.00, NULL, NULL, NULL, 'ST001', 'alex'),
(158, '2026-01-19 19:12:53', 96.00, NULL, NULL, NULL, 'ST001', 'alex'),
(159, '2026-01-19 19:12:53', 53.00, NULL, NULL, NULL, 'ST001', 'alex'),
(160, '2026-01-19 19:12:53', 17.00, NULL, NULL, NULL, 'ST001', 'alex'),
(161, '2026-01-19 19:12:57', 12.00, NULL, NULL, NULL, 'ST001', 'alex'),
(162, '2026-01-19 19:12:57', 45.00, NULL, NULL, NULL, 'ST001', 'alex'),
(163, '2026-01-19 19:12:57', 78.00, NULL, NULL, NULL, 'ST001', 'alex'),
(164, '2026-01-19 19:12:57', 101.00, NULL, NULL, NULL, 'ST001', 'alex'),
(165, '2026-01-19 19:12:57', 33.00, NULL, NULL, NULL, 'ST001', 'alex'),
(166, '2026-01-19 19:12:57', 67.00, NULL, NULL, NULL, 'ST001', 'alex'),
(167, '2026-01-19 19:12:57', 89.00, NULL, NULL, NULL, 'ST001', 'alex'),
(168, '2026-01-19 19:12:57', 14.00, NULL, NULL, NULL, 'ST001', 'alex'),
(169, '2026-01-19 19:12:57', 55.00, NULL, NULL, NULL, 'ST001', 'alex'),
(170, '2026-01-19 19:12:57', 92.00, NULL, NULL, NULL, 'ST001', 'alex'),
(171, '2026-01-19 19:12:57', 38.00, NULL, NULL, NULL, 'ST001', 'alex'),
(172, '2026-01-19 19:12:57', 73.00, NULL, NULL, NULL, 'ST001', 'alex'),
(173, '2026-01-19 19:12:57', 64.00, NULL, NULL, NULL, 'ST001', 'alex'),
(174, '2026-01-19 19:12:57', 27.00, NULL, NULL, NULL, 'ST001', 'alex'),
(175, '2026-01-19 19:12:57', 81.00, NULL, NULL, NULL, 'ST001', 'alex'),
(176, '2026-01-19 19:12:57', 49.00, NULL, NULL, NULL, 'ST001', 'alex'),
(177, '2026-01-19 19:12:57', 95.00, NULL, NULL, NULL, 'ST001', 'alex'),
(178, '2026-01-19 19:12:57', 58.00, NULL, NULL, NULL, 'ST001', 'alex'),
(179, '2026-01-19 19:12:57', 11.00, NULL, NULL, NULL, 'ST001', 'alex'),
(180, '2026-01-19 19:12:57', 66.00, NULL, NULL, NULL, 'ST001', 'alex'),
(181, '2026-01-19 19:12:57', 74.00, NULL, NULL, NULL, 'ST001', 'alex'),
(182, '2026-01-19 19:12:57', 82.00, NULL, NULL, NULL, 'ST001', 'alex'),
(183, '2026-01-19 19:12:57', 93.00, NULL, NULL, NULL, 'ST001', 'alex'),
(184, '2026-01-19 19:12:57', 25.00, NULL, NULL, NULL, 'ST001', 'alex'),
(185, '2026-01-19 19:12:57', 47.00, NULL, NULL, NULL, 'ST001', 'alex'),
(186, '2026-01-19 19:12:57', 59.00, NULL, NULL, NULL, 'ST001', 'alex'),
(187, '2026-01-19 19:12:57', 36.00, NULL, NULL, NULL, 'ST001', 'alex'),
(188, '2026-01-19 19:12:57', 44.00, NULL, NULL, NULL, 'ST001', 'alex'),
(189, '2026-01-19 19:12:57', 71.00, NULL, NULL, NULL, 'ST001', 'alex'),
(190, '2026-01-19 19:12:57', 83.00, NULL, NULL, NULL, 'ST001', 'alex'),
(191, '2026-01-19 19:12:57', 19.00, NULL, NULL, NULL, 'ST001', 'alex'),
(192, '2026-01-19 19:12:57', 52.00, NULL, NULL, NULL, 'ST001', 'alex'),
(193, '2026-01-19 19:12:57', 68.00, NULL, NULL, NULL, 'ST001', 'alex'),
(194, '2026-01-19 19:12:57', 97.00, NULL, NULL, NULL, 'ST001', 'alex'),
(195, '2026-01-19 19:12:57', 41.00, NULL, NULL, NULL, 'ST001', 'alex'),
(196, '2026-01-19 19:12:57', 63.00, NULL, NULL, NULL, 'ST001', 'alex'),
(197, '2026-01-19 19:12:57', 88.00, NULL, NULL, NULL, 'ST001', 'alex'),
(198, '2026-01-19 19:12:57', 29.00, NULL, NULL, NULL, 'ST001', 'alex'),
(199, '2026-01-19 19:12:57', 57.00, NULL, NULL, NULL, 'ST001', 'alex'),
(200, '2026-01-19 19:12:57', 76.00, NULL, NULL, NULL, 'ST001', 'alex'),
(201, '2026-01-19 19:12:57', 84.00, NULL, NULL, NULL, 'ST001', 'alex'),
(202, '2026-01-19 19:12:57', 91.00, NULL, NULL, NULL, 'ST001', 'alex'),
(203, '2026-01-19 19:12:57', 23.00, NULL, NULL, NULL, 'ST001', 'alex'),
(204, '2026-01-19 19:12:57', 34.00, NULL, NULL, NULL, 'ST001', 'alex'),
(205, '2026-01-19 19:12:57', 62.00, NULL, NULL, NULL, 'ST001', 'alex'),
(206, '2026-01-19 19:12:57', 79.00, NULL, NULL, NULL, 'ST001', 'alex'),
(207, '2026-01-19 19:12:57', 96.00, NULL, NULL, NULL, 'ST001', 'alex'),
(208, '2026-01-19 19:12:57', 53.00, NULL, NULL, NULL, 'ST001', 'alex'),
(209, '2026-01-19 19:12:57', 17.00, NULL, NULL, NULL, 'ST001', 'alex'),
(210, '2026-01-19 19:13:17', 12.00, NULL, NULL, NULL, 'ST001', 'alex'),
(212, '2026-01-19 19:13:17', 78.00, NULL, NULL, NULL, 'ST001', 'alex'),
(213, '2026-01-19 19:13:17', 101.00, NULL, NULL, NULL, 'ST001', 'alex'),
(214, '2026-01-19 19:13:17', 33.00, NULL, NULL, NULL, 'ST001', 'alex'),
(215, '2026-01-19 19:13:17', 67.00, NULL, NULL, NULL, 'ST001', 'alex'),
(216, '2026-01-19 19:13:17', 89.00, NULL, NULL, NULL, 'ST001', 'alex'),
(217, '2026-01-19 19:13:17', 14.00, NULL, NULL, NULL, 'ST001', 'alex'),
(218, '2026-01-19 19:13:17', 55.00, NULL, NULL, NULL, 'ST001', 'alex'),
(219, '2026-01-19 19:13:17', 92.00, NULL, NULL, NULL, 'ST001', 'alex'),
(220, '2026-01-19 19:13:17', 38.00, NULL, NULL, NULL, 'ST001', 'alex'),
(221, '2026-01-19 19:13:17', 73.00, NULL, NULL, NULL, 'ST001', 'alex'),
(223, '2026-01-19 19:13:17', 27.00, NULL, NULL, NULL, 'ST001', 'alex'),
(224, '2026-01-19 19:13:17', 81.00, NULL, NULL, NULL, 'ST001', 'alex'),
(228, '2026-01-19 19:13:17', 11.00, NULL, NULL, NULL, 'ST001', 'alex'),
(229, '2026-01-19 19:13:17', 66.00, NULL, NULL, NULL, 'ST001', 'alex'),
(230, '2026-01-19 19:13:17', 74.00, NULL, NULL, NULL, 'ST001', 'alex'),
(232, '2026-01-19 19:13:17', 93.00, NULL, NULL, NULL, 'ST001', 'alex'),
(233, '2026-01-19 19:13:17', 25.00, NULL, NULL, NULL, 'ST001', 'alex'),
(234, '2026-01-19 19:13:17', 47.00, NULL, NULL, NULL, 'ST001', 'alex'),
(235, '2026-01-19 19:13:17', 59.00, NULL, NULL, NULL, 'ST001', 'alex'),
(236, '2026-01-19 19:13:17', 36.00, NULL, NULL, NULL, 'ST001', 'alex'),
(237, '2026-01-19 19:13:17', 44.00, NULL, NULL, NULL, 'ST001', 'alex'),
(238, '2026-01-19 19:13:17', 71.00, NULL, NULL, NULL, 'ST001', 'alex'),
(239, '2026-01-19 19:13:17', 83.00, NULL, NULL, NULL, 'ST001', 'alex'),
(240, '2026-01-19 19:13:17', 19.00, NULL, NULL, NULL, 'ST001', 'alex'),
(241, '2026-01-19 19:13:17', 52.00, NULL, NULL, NULL, 'ST001', 'alex'),
(242, '2026-01-19 19:13:17', 68.00, NULL, NULL, NULL, 'ST001', 'alex'),
(243, '2026-01-19 19:13:17', 97.00, NULL, NULL, NULL, 'ST001', 'alex'),
(244, '2026-01-19 19:13:17', 41.00, NULL, NULL, NULL, 'ST001', 'alex'),
(245, '2026-01-19 19:13:17', 63.00, NULL, NULL, NULL, 'ST001', 'alex'),
(246, '2026-01-19 19:13:17', 88.00, NULL, NULL, NULL, 'ST001', 'alex'),
(247, '2026-01-19 19:13:17', 29.00, NULL, NULL, NULL, 'ST001', 'alex'),
(248, '2026-01-19 19:13:17', 57.00, NULL, NULL, NULL, 'ST001', 'alex'),
(249, '2026-01-19 19:13:17', 76.00, NULL, NULL, NULL, 'ST001', 'alex'),
(250, '2026-01-19 19:13:17', 84.00, NULL, NULL, NULL, 'ST001', 'alex'),
(251, '2026-01-19 19:13:17', 91.00, NULL, NULL, NULL, 'ST001', 'alex'),
(252, '2026-01-19 19:13:17', 23.00, NULL, NULL, NULL, 'ST001', 'alex'),
(253, '2026-01-19 19:13:17', 34.00, NULL, NULL, NULL, 'ST001', 'alex'),
(254, '2026-01-19 19:13:17', 62.00, NULL, NULL, NULL, 'ST001', 'alex'),
(255, '2026-01-19 19:13:17', 79.00, 1034.00, NULL, NULL, 'ST001', 'alex'),
(259, '2026-01-25 19:39:19', 1.00, NULL, NULL, NULL, 'ST007', 'maria'),
(123456, '2026-03-23 17:08:04', 1.00, 2.00, NULL, NULL, 'ST002', 'maria'),
(123457, '2026-03-12 22:23:19', 12.00, 1023.00, 200.00, 700.00, 'ST006', 'alex'),
(123458, '2026-01-19 22:38:39', 26.00, 1100.00, 150.00, 725.00, 'ST006', 'alex'),
(123576, '2026-03-01 00:00:00', 20.00, 1002.10, 300.00, 40.00, 'ST008', 'admin'),
(123577, '2026-03-02 00:00:00', 20.00, 1002.00, 305.00, 41.00, 'ST008', 'admin'),
(123578, '2026-03-03 00:00:00', 20.10, 1002.30, 298.00, 40.00, 'ST008', 'admin'),
(123579, '2026-03-04 00:00:00', 19.90, 1001.90, 302.00, 39.00, 'ST008', 'admin'),
(123580, '2026-03-05 00:00:00', 20.00, 1002.20, 307.00, 40.00, 'ST008', 'admin'),
(123581, '2026-03-06 00:00:00', 20.00, 1002.10, 300.00, 40.00, 'ST008', 'admin'),
(123582, '2026-03-07 00:00:00', 20.10, 1002.40, 304.00, 41.00, 'ST008', 'admin'),
(123583, '2026-03-08 00:00:00', 19.80, 1001.70, 297.00, 39.00, 'ST008', 'admin'),
(123584, '2026-03-09 00:00:00', 20.00, 1002.00, 301.00, 40.00, 'ST008', 'admin'),
(123585, '2026-03-10 00:00:00', 20.00, 1002.20, 305.00, 41.00, 'ST008', 'admin'),
(123586, '2026-03-11 00:00:00', 20.10, 1002.30, 302.00, 40.00, 'ST008', 'admin'),
(123587, '2026-03-12 00:00:00', 19.90, 1001.80, 299.00, 39.00, 'ST008', 'admin'),
(123588, '2026-03-13 00:00:00', 20.00, 1002.00, 303.00, 40.00, 'ST008', 'admin'),
(123589, '2026-03-14 00:00:00', 20.00, 1002.10, 306.00, 41.00, 'ST008', 'admin'),
(123590, '2026-03-15 00:00:00', 20.10, 1002.30, 300.00, 40.00, 'ST008', 'admin'),
(123591, '2026-03-16 00:00:00', 20.30, 1002.50, 310.00, 41.00, 'ST008', 'admin'),
(123592, '2026-03-17 00:00:00', 20.60, 1002.80, 320.00, 42.00, 'ST008', 'admin'),
(123593, '2026-03-18 00:00:00', 20.90, 1003.00, 330.00, 43.00, 'ST008', 'admin'),
(123594, '2026-03-19 00:00:00', 21.20, 1003.30, 340.00, 44.00, 'ST008', 'admin'),
(123595, '2026-03-20 00:00:00', 21.50, 1003.60, 350.00, 45.00, 'ST008', 'admin'),
(123596, '2026-03-21 00:00:00', 21.80, 1003.90, 360.00, 46.00, 'ST008', 'admin'),
(123597, '2026-03-22 00:00:00', 22.10, 1004.10, 370.00, 47.00, 'ST008', 'admin'),
(123598, '2026-03-23 00:00:00', 22.40, 1004.40, 380.00, 48.00, 'ST008', 'admin'),
(123599, '2026-03-24 00:00:00', 22.70, 1004.70, 390.00, 49.00, 'ST008', 'admin'),
(123600, '2026-03-25 00:00:00', 23.00, 1005.00, 400.00, 50.00, 'ST008', 'admin'),
(123601, '2026-03-26 00:00:00', 23.30, 1005.30, 410.00, 51.00, 'ST008', 'admin'),
(123602, '2026-03-27 00:00:00', 23.60, 1005.60, 420.00, 52.00, 'ST008', 'admin'),
(123603, '2026-03-28 00:00:00', 23.90, 1005.90, 430.00, 53.00, 'ST008', 'admin'),
(123604, '2026-03-29 00:00:00', 24.20, 1006.20, 440.00, 54.00, 'ST008', 'admin'),
(123605, '2026-03-30 00:00:00', 24.50, 1006.50, 450.00, 55.00, 'ST008', 'admin'),
(123606, '2026-03-31 00:00:00', 24.80, 1006.80, 460.00, 56.00, 'ST008', 'admin'),
(123607, '2026-03-31 06:00:00', 25.00, 1007.00, 470.00, 57.00, 'ST008', 'admin'),
(123608, '2026-03-31 12:00:00', 25.00, 1007.10, 480.00, 57.00, 'ST008', 'admin'),
(123609, '2026-03-31 18:00:00', 25.00, 1007.20, 490.00, 58.00, 'ST008', 'admin'),
(123610, '2026-03-31 23:59:00', 25.00, 1007.30, 500.00, 58.00, 'ST008', 'admin'),
(123611, '2026-04-01 00:00:00', 25.00, 1007.40, 510.00, 58.00, 'ST008', 'admin'),
(123612, '2026-04-02 00:00:00', 25.00, 1007.50, 515.00, 59.00, 'ST008', 'admin'),
(123613, '2026-04-03 00:00:00', 25.10, 1007.60, 520.00, 59.00, 'ST008', 'admin'),
(123614, '2026-04-04 00:00:00', 25.00, 1007.40, 525.00, 58.00, 'ST008', 'admin'),
(123615, '2026-04-05 00:00:00', 25.00, 1007.30, 530.00, 58.00, 'ST008', 'admin'),
(123616, '2026-04-06 00:00:00', 25.00, 1007.20, 535.00, 58.00, 'ST008', 'admin'),
(123617, '2026-04-07 00:00:00', 25.10, 1007.50, 540.00, 59.00, 'ST008', 'admin'),
(123618, '2026-04-08 00:00:00', 25.00, 1007.30, 545.00, 58.00, 'ST008', 'admin'),
(123619, '2026-04-09 00:00:00', 25.00, 1007.20, 550.00, 58.00, 'ST008', 'admin'),
(123620, '2026-04-10 00:00:00', 25.00, 1007.10, 555.00, 58.00, 'ST008', 'admin'),
(123621, '2026-04-11 00:00:00', 25.10, 1007.40, 560.00, 59.00, 'ST008', 'admin'),
(123622, '2026-04-12 00:00:00', 25.00, 1007.20, 565.00, 58.00, 'ST008', 'admin'),
(123623, '2026-04-13 00:00:00', 25.00, 1007.10, 570.00, 58.00, 'ST008', 'admin'),
(123624, '2026-04-14 00:00:00', 25.00, 1007.00, 575.00, 58.00, 'ST008', 'admin'),
(123625, '2026-04-15 00:00:00', 25.00, 1007.00, 580.00, 58.00, 'ST008', 'admin'),
(123626, '2026-03-01 00:00:00', 25.00, 1015.00, 600.00, 60.00, 'ST009', 'admin'),
(123627, '2026-03-02 00:00:00', 25.00, 1015.20, 605.00, 61.00, 'ST009', 'admin'),
(123628, '2026-03-03 00:00:00', 25.10, 1015.30, 598.00, 60.00, 'ST009', 'admin'),
(123629, '2026-03-04 00:00:00', 24.90, 1014.90, 602.00, 59.00, 'ST009', 'admin'),
(123630, '2026-03-05 00:00:00', 25.00, 1015.10, 607.00, 60.00, 'ST009', 'admin'),
(123631, '2026-03-06 00:00:00', 25.00, 1015.00, 600.00, 60.00, 'ST009', 'admin'),
(123632, '2026-03-07 00:00:00', 25.10, 1015.30, 604.00, 61.00, 'ST009', 'admin'),
(123633, '2026-03-08 00:00:00', 24.80, 1014.70, 597.00, 59.00, 'ST009', 'admin'),
(123634, '2026-03-09 00:00:00', 25.00, 1015.00, 601.00, 60.00, 'ST009', 'admin'),
(123635, '2026-03-10 00:00:00', 25.00, 1015.20, 605.00, 61.00, 'ST009', 'admin'),
(123636, '2026-03-11 00:00:00', 25.10, 1015.30, 602.00, 60.00, 'ST009', 'admin'),
(123637, '2026-03-12 00:00:00', 24.90, 1014.80, 599.00, 59.00, 'ST009', 'admin'),
(123638, '2026-03-13 00:00:00', 25.00, 1015.00, 603.00, 60.00, 'ST009', 'admin'),
(123639, '2026-03-14 00:00:00', 25.00, 1015.10, 606.00, 61.00, 'ST009', 'admin'),
(123640, '2026-03-15 00:00:00', 25.10, 1015.30, 600.00, 60.00, 'ST009', 'admin'),
(123641, '2026-03-16 00:00:00', 24.70, 1014.90, 590.00, 59.00, 'ST009', 'admin'),
(123642, '2026-03-17 00:00:00', 24.40, 1014.60, 580.00, 58.00, 'ST009', 'admin'),
(123643, '2026-03-18 00:00:00', 24.10, 1014.30, 570.00, 57.00, 'ST009', 'admin'),
(123644, '2026-03-19 00:00:00', 23.80, 1014.00, 560.00, 56.00, 'ST009', 'admin'),
(123645, '2026-03-20 00:00:00', 23.50, 1013.70, 550.00, 55.00, 'ST009', 'admin'),
(123646, '2026-03-21 00:00:00', 23.20, 1013.40, 540.00, 54.00, 'ST009', 'admin'),
(123647, '2026-03-22 00:00:00', 22.90, 1013.10, 530.00, 53.00, 'ST009', 'admin'),
(123648, '2026-03-23 00:00:00', 22.60, 1012.80, 520.00, 52.00, 'ST009', 'admin'),
(123649, '2026-03-24 00:00:00', 22.30, 1012.50, 510.00, 51.00, 'ST009', 'admin'),
(123650, '2026-03-25 00:00:00', 22.00, 1012.20, 500.00, 50.00, 'ST009', 'admin'),
(123651, '2026-03-26 00:00:00', 21.70, 1011.90, 490.00, 49.00, 'ST009', 'admin'),
(123652, '2026-03-27 00:00:00', 21.40, 1011.60, 480.00, 48.00, 'ST009', 'admin'),
(123653, '2026-03-28 00:00:00', 21.10, 1011.30, 470.00, 47.00, 'ST009', 'admin'),
(123654, '2026-03-29 00:00:00', 20.80, 1011.00, 460.00, 46.00, 'ST009', 'admin'),
(123655, '2026-03-30 00:00:00', 20.50, 1010.70, 450.00, 45.00, 'ST009', 'admin'),
(123656, '2026-03-31 00:00:00', 20.20, 1010.40, 440.00, 44.00, 'ST009', 'admin'),
(123657, '2026-03-31 06:00:00', 20.00, 1010.20, 430.00, 43.00, 'ST009', 'admin'),
(123658, '2026-03-31 12:00:00', 20.00, 1010.10, 420.00, 43.00, 'ST009', 'admin'),
(123659, '2026-03-31 18:00:00', 20.00, 1010.00, 410.00, 42.00, 'ST009', 'admin'),
(123660, '2026-03-31 23:59:00', 20.00, 1009.90, 400.00, 42.00, 'ST009', 'admin'),
(123661, '2026-04-01 00:00:00', 20.00, 1009.80, 395.00, 42.00, 'ST009', 'admin'),
(123662, '2026-04-02 00:00:00', 20.00, 1009.70, 390.00, 41.00, 'ST009', 'admin'),
(123663, '2026-04-03 00:00:00', 20.10, 1009.80, 385.00, 41.00, 'ST009', 'admin'),
(123664, '2026-04-04 00:00:00', 20.00, 1009.70, 380.00, 41.00, 'ST009', 'admin'),
(123665, '2026-04-05 00:00:00', 20.00, 1009.60, 375.00, 41.00, 'ST009', 'admin'),
(123666, '2026-04-06 00:00:00', 20.00, 1009.50, 370.00, 41.00, 'ST009', 'admin'),
(123667, '2026-04-07 00:00:00', 20.10, 1009.70, 365.00, 42.00, 'ST009', 'admin'),
(123668, '2026-04-08 00:00:00', 20.00, 1009.60, 360.00, 41.00, 'ST009', 'admin'),
(123669, '2026-04-09 00:00:00', 20.00, 1009.50, 355.00, 41.00, 'ST009', 'admin'),
(123670, '2026-04-10 00:00:00', 20.00, 1009.40, 350.00, 41.00, 'ST009', 'admin'),
(123671, '2026-04-11 00:00:00', 20.10, 1009.60, 345.00, 42.00, 'ST009', 'admin'),
(123672, '2026-04-12 00:00:00', 20.00, 1009.50, 340.00, 41.00, 'ST009', 'admin'),
(123673, '2026-04-13 00:00:00', 20.00, 1009.40, 335.00, 41.00, 'ST009', 'admin'),
(123674, '2026-04-14 00:00:00', 20.00, 1009.30, 330.00, 41.00, 'ST009', 'admin'),
(123675, '2026-04-15 00:00:00', 20.00, 1009.30, 325.00, 39.00, 'ST009', 'admin'),
(123688, '2026-04-06 13:55:58', 17.00, 950.00, 400.00, 30.00, 'ST005', 'admin'),
(123689, '2026-03-01 13:55:58', 15.00, 950.00, 400.00, 30.00, 'ST005', 'admin'),
(123690, '2026-03-02 13:55:58', 15.00, 950.00, 400.00, 30.00, 'ST005', 'admin'),
(123691, '2026-03-03 13:55:58', 15.00, 950.00, 400.00, 30.00, 'ST005', 'admin'),
(123692, '2026-03-04 13:55:58', 15.00, 950.00, 400.00, 30.00, 'ST005', 'admin'),
(123693, '2026-03-05 13:55:58', 15.00, 950.00, 400.00, 30.00, 'ST005', 'admin'),
(123694, '2026-03-06 13:55:58', 15.00, 950.00, 400.00, 30.00, 'ST005', 'admin'),
(123695, '2026-03-07 13:55:58', 15.00, 950.00, 400.00, 30.00, 'ST005', 'admin'),
(123696, '2026-03-08 13:55:58', 15.00, 950.00, 400.00, 30.00, 'ST005', 'admin'),
(123697, '2026-03-09 13:55:58', 15.00, 950.00, 400.00, 30.00, 'ST005', 'admin'),
(123698, '2026-03-10 13:55:58', 15.00, 950.00, 400.00, 30.00, 'ST005', 'admin'),
(123699, '2026-03-11 13:55:58', 15.00, 950.00, 400.00, 30.00, 'ST005', 'admin'),
(123700, '2026-03-12 13:55:58', 15.00, 950.00, 400.00, 30.00, 'ST005', 'admin'),
(123701, '2026-03-13 13:55:58', 15.00, 950.00, 400.00, 30.00, 'ST005', 'admin'),
(123702, '2026-03-14 13:55:58', 15.00, 950.00, 400.00, 30.00, 'ST005', 'admin'),
(123703, '2026-03-15 13:55:58', 15.00, 950.00, 400.00, 30.00, 'ST005', 'admin'),
(123704, '2026-03-16 13:55:58', 15.00, 950.00, 400.00, 30.00, 'ST005', 'admin'),
(123705, '2026-04-06 13:55:58', 22.00, 970.00, 370.00, 52.00, 'ST010', 'admin'),
(123706, '2026-03-01 13:55:58', 22.00, 970.00, 370.00, 52.00, 'ST010', 'admin'),
(123707, '2026-03-02 13:55:58', 22.00, 970.00, 370.00, 52.00, 'ST010', 'admin'),
(123708, '2026-03-03 13:55:58', 22.00, 970.00, 370.00, 52.00, 'ST010', 'admin'),
(123709, '2026-03-04 13:55:58', 22.00, 970.00, 370.00, 52.00, 'ST010', 'admin'),
(123710, '2026-03-05 13:55:58', 22.00, 970.00, 370.00, 52.00, 'ST010', 'admin'),
(123711, '2026-03-06 13:55:58', 22.00, 970.00, 370.00, 52.00, 'ST010', 'admin'),
(123712, '2026-03-07 13:55:58', 22.00, 970.00, 370.00, 52.00, 'ST010', 'admin'),
(123713, '2026-03-08 13:55:58', 22.00, 970.00, 370.00, 52.00, 'ST010', 'admin'),
(123714, '2026-03-09 13:55:58', 22.00, 970.00, 370.00, 52.00, 'ST010', 'admin'),
(123715, '2026-03-10 13:55:58', 22.00, 970.00, 370.00, 52.00, 'ST010', 'admin'),
(123716, '2026-03-11 13:55:58', 22.00, 970.00, 370.00, 52.00, 'ST010', 'admin'),
(123717, '2026-03-12 13:55:58', 22.00, 970.00, 370.00, 52.00, 'ST010', 'admin'),
(123718, '2026-03-13 13:55:58', 22.00, 970.00, 370.00, 52.00, 'ST010', 'admin'),
(123719, '2026-03-14 13:55:58', 22.00, 970.00, 370.00, 52.00, 'ST010', 'admin'),
(123720, '2026-03-15 13:55:58', 22.00, 970.00, 370.00, 52.00, 'ST010', 'admin'),
(123721, '2026-03-16 13:55:58', 22.00, 970.00, 370.00, 52.00, 'ST010', 'admin'),
(123722, '2026-04-06 13:55:58', 24.00, 990.00, 390.00, 48.00, 'ST011', 'admin'),
(123723, '2026-03-01 13:55:58', 24.00, 990.00, 390.00, 48.00, 'ST011', 'admin'),
(123724, '2026-03-02 13:55:58', 24.00, 990.00, 390.00, 48.00, 'ST011', 'admin'),
(123725, '2026-03-03 13:55:58', 24.00, 990.00, 390.00, 48.00, 'ST011', 'admin'),
(123726, '2026-03-04 13:55:58', 24.00, 990.00, 390.00, 48.00, 'ST011', 'admin'),
(123727, '2026-03-05 13:55:58', 24.00, 990.00, 390.00, 48.00, 'ST011', 'admin'),
(123728, '2026-03-06 13:55:58', 24.00, 990.00, 390.00, 48.00, 'ST011', 'admin'),
(123729, '2026-03-07 13:55:58', 24.00, 990.00, 390.00, 48.00, 'ST011', 'admin'),
(123730, '2026-03-08 13:55:58', 24.00, 990.00, 390.00, 48.00, 'ST011', 'admin'),
(123731, '2026-03-09 13:55:58', 24.00, 990.00, 390.00, 48.00, 'ST011', 'admin'),
(123732, '2026-03-10 13:55:58', 24.00, 990.00, 390.00, 48.00, 'ST011', 'admin'),
(123733, '2026-03-11 13:55:58', 24.00, 990.00, 390.00, 48.00, 'ST011', 'admin'),
(123734, '2026-03-12 13:55:58', 24.00, 990.00, 390.00, 48.00, 'ST011', 'admin'),
(123735, '2026-03-13 13:55:58', 24.00, 990.00, 390.00, 48.00, 'ST011', 'admin'),
(123736, '2026-03-14 13:55:58', 24.00, 990.00, 390.00, 48.00, 'ST011', 'admin'),
(123737, '2026-03-15 13:55:58', 24.00, 990.00, 390.00, 48.00, 'ST011', 'admin'),
(123738, '2026-03-16 13:55:58', 24.00, 990.00, 390.00, 48.00, 'ST011', 'admin'),
(123739, '2026-04-06 13:55:58', 26.00, 940.00, 410.00, 46.00, 'ST012', 'admin'),
(123740, '2026-03-01 13:55:58', 26.00, 940.00, 410.00, 46.00, 'ST012', 'admin'),
(123741, '2026-03-02 13:55:58', 26.00, 940.00, 410.00, 46.00, 'ST012', 'admin'),
(123742, '2026-03-03 13:55:58', 26.00, 940.00, 410.00, 46.00, 'ST012', 'admin'),
(123743, '2026-03-04 13:55:58', 26.00, 940.00, 410.00, 46.00, 'ST012', 'admin'),
(123744, '2026-03-05 13:55:58', 26.00, 940.00, 410.00, 46.00, 'ST012', 'admin'),
(123745, '2026-03-06 13:55:58', 26.00, 940.00, 410.00, 46.00, 'ST012', 'admin'),
(123746, '2026-03-07 13:55:58', 26.00, 940.00, 410.00, 46.00, 'ST012', 'admin'),
(123747, '2026-03-08 13:55:58', 26.00, 940.00, 410.00, 46.00, 'ST012', 'admin'),
(123748, '2026-03-09 13:55:58', 26.00, 940.00, 410.00, 46.00, 'ST012', 'admin'),
(123749, '2026-03-10 13:55:58', 26.00, 940.00, 410.00, 46.00, 'ST012', 'admin'),
(123750, '2026-03-11 13:55:58', 26.00, 940.00, 410.00, 46.00, 'ST012', 'admin'),
(123751, '2026-03-12 13:55:58', 26.00, 940.00, 410.00, 46.00, 'ST012', 'admin'),
(123752, '2026-03-13 13:55:58', 26.00, 940.00, 410.00, 46.00, 'ST012', 'admin'),
(123753, '2026-03-14 13:55:58', 26.00, 940.00, 410.00, 46.00, 'ST012', 'admin'),
(123754, '2026-03-15 13:55:58', 26.00, 940.00, 410.00, 46.00, 'ST012', 'admin'),
(123755, '2026-03-16 13:55:58', 26.00, 940.00, 410.00, 46.00, 'ST012', 'admin'),
(123756, '2026-04-06 13:55:58', 28.00, 1010.00, 430.00, 44.00, 'ST013', 'admin'),
(123757, '2026-03-01 13:55:58', 28.00, 1010.00, 430.00, 44.00, 'ST013', 'admin'),
(123758, '2026-03-02 13:55:58', 28.00, 1010.00, 430.00, 44.00, 'ST013', 'admin'),
(123759, '2026-03-03 13:55:58', 28.00, 1010.00, 430.00, 44.00, 'ST013', 'admin'),
(123760, '2026-03-04 13:55:58', 28.00, 1010.00, 430.00, 44.00, 'ST013', 'admin'),
(123761, '2026-03-05 13:55:58', 28.00, 1010.00, 430.00, 44.00, 'ST013', 'admin'),
(123762, '2026-03-06 13:55:58', 28.00, 1010.00, 430.00, 44.00, 'ST013', 'admin'),
(123763, '2026-03-07 13:55:58', 28.00, 1010.00, 430.00, 44.00, 'ST013', 'admin'),
(123764, '2026-03-08 13:55:58', 28.00, 1010.00, 430.00, 44.00, 'ST013', 'admin'),
(123765, '2026-03-09 13:55:58', 28.00, 1010.00, 430.00, 44.00, 'ST013', 'admin'),
(123766, '2026-03-10 13:55:58', 28.00, 1010.00, 430.00, 44.00, 'ST013', 'admin'),
(123767, '2026-03-11 13:55:58', 28.00, 1010.00, 430.00, 44.00, 'ST013', 'admin'),
(123768, '2026-03-12 13:55:58', 28.00, 1010.00, 430.00, 44.00, 'ST013', 'admin'),
(123769, '2026-03-13 13:55:58', 28.00, 1010.00, 430.00, 44.00, 'ST013', 'admin'),
(123770, '2026-03-14 13:55:58', 28.00, 1010.00, 430.00, 44.00, 'ST013', 'admin'),
(123771, '2026-03-15 13:55:58', 28.00, 1010.00, 430.00, 44.00, 'ST013', 'admin'),
(123772, '2026-03-16 13:55:58', 28.00, 1010.00, 430.00, 44.00, 'ST013', 'admin'),
(123832, '2026-04-06 13:55:58', 15.00, 950.00, 400.00, 30.00, 'ST015', 'admin'),
(123833, '2026-03-01 13:55:58', 15.00, 950.00, 400.00, 30.00, 'ST015', 'admin'),
(123834, '2026-03-02 13:55:58', 15.00, 950.00, 400.00, 30.00, 'ST015', 'admin'),
(123835, '2026-03-03 13:55:58', 15.00, 950.00, 400.00, 30.00, 'ST015', 'admin'),
(123836, '2026-03-04 13:55:58', 15.00, 950.00, 400.00, 30.00, 'ST015', 'admin'),
(123837, '2026-03-05 13:55:58', 15.00, 950.00, 400.00, 30.00, 'ST015', 'admin'),
(123838, '2026-03-06 13:55:58', 15.00, 950.00, 400.00, 30.00, 'ST015', 'admin'),
(123839, '2026-03-07 13:55:58', 15.00, 950.00, 400.00, 30.00, 'ST015', 'admin'),
(123840, '2026-03-08 13:55:58', 15.00, 950.00, 400.00, 30.00, 'ST015', 'admin'),
(123841, '2026-03-09 13:55:58', 15.00, 950.00, 400.00, 30.00, 'ST015', 'admin'),
(123842, '2026-03-10 13:55:58', 15.00, 950.00, 400.00, 30.00, 'ST015', 'admin'),
(123843, '2026-03-11 13:55:58', 15.00, 950.00, 400.00, 30.00, 'ST015', 'admin'),
(123844, '2026-03-12 13:55:58', 15.00, 950.00, 400.00, 30.00, 'ST015', 'admin'),
(123845, '2026-03-13 13:55:58', 15.00, 950.00, 400.00, 30.00, 'ST015', 'admin'),
(123846, '2026-03-14 13:55:58', 15.00, 950.00, 400.00, 30.00, 'ST015', 'admin'),
(123847, '2026-03-15 13:55:58', 15.00, 950.00, 400.00, 30.00, 'ST015', 'admin'),
(123848, '2026-03-16 13:55:58', 15.00, 950.00, 400.00, 30.00, 'ST015', 'admin'),
(123849, '2026-04-06 13:55:58', 15.00, 950.00, 400.00, 30.00, 'ST016', 'alex'),
(123850, '2026-03-01 13:55:58', 15.00, 950.00, 400.00, 30.00, 'ST016', 'admin'),
(123851, '2026-03-02 13:55:58', 15.00, 950.00, 400.00, 30.00, 'ST016', 'admin'),
(123852, '2026-03-03 13:55:58', 15.00, 950.00, 400.00, 30.00, 'ST016', 'admin'),
(123853, '2026-03-04 13:55:58', 15.00, 950.00, 400.00, 30.00, 'ST016', 'admin'),
(123854, '2026-03-05 13:55:58', 15.00, 950.00, 400.00, 30.00, 'ST016', 'admin'),
(123855, '2026-03-06 13:55:58', 15.00, 950.00, 400.00, 30.00, 'ST016', 'admin'),
(123856, '2026-03-07 13:55:58', 15.00, 950.00, 400.00, 30.00, 'ST016', 'admin'),
(123857, '2026-03-08 13:55:58', 15.00, 950.00, 400.00, 30.00, 'ST016', 'admin'),
(123858, '2026-03-09 13:55:58', 15.00, 950.00, 400.00, 30.00, 'ST016', 'alex'),
(123859, '2026-03-10 13:55:58', 15.00, 950.00, 400.00, 30.00, 'ST016', 'alex'),
(123860, '2026-03-11 13:55:58', 15.00, 950.00, 400.00, 30.00, 'ST016', 'alex'),
(123861, '2026-03-12 13:55:58', 15.00, 950.00, 400.00, 30.00, 'ST016', 'alex'),
(123862, '2026-03-13 13:55:58', 15.00, 950.00, 400.00, 30.00, 'ST016', 'alex'),
(123863, '2026-03-14 13:55:58', 15.00, 950.00, 400.00, 30.00, 'ST016', 'alex'),
(123864, '2026-03-15 13:55:58', 15.00, 950.00, 400.00, 30.00, 'ST016', 'alex'),
(123865, '2026-03-16 13:55:58', 15.00, 950.00, 400.00, 30.00, 'ST016', 'alex'),
(123895, '2026-04-06 18:19:28', 20.00, 1000.00, 500.00, 50.00, 'ST006', 'alex'),
(123896, '2026-04-07 15:00:37', 11.00, 1111.00, 222.00, 44.00, 'ST001', 'alex');

--
-- Тригери `measurement`
--
DELIMITER $$
CREATE TRIGGER `trg_measurement_set_owner_before_insert` BEFORE INSERT ON `measurement` FOR EACH ROW BEGIN
    IF NEW.fk_ownerId IS NULL THEN
        SET NEW.fk_ownerId = (
            SELECT oh.fk_ownerId
            FROM ownership_history oh
            WHERE oh.fk_serialNumber = NEW.fk_station
              AND oh.unregisteredAt IS NULL
            ORDER BY oh.registeredAt DESC
            LIMIT 1
        );
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Структура таблиці `notification`
--

CREATE TABLE `notification` (
  `pk_notificationID` int(11) NOT NULL,
  `fk_user` varchar(50) NOT NULL,
  `type` varchar(50) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `link` varchar(500) DEFAULT NULL,
  `isRead` tinyint(1) NOT NULL DEFAULT 0,
  `createdAt` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп даних таблиці `notification`
--

INSERT INTO `notification` (`pk_notificationID`, `fk_user`, `type`, `title`, `message`, `link`, `isRead`, `createdAt`) VALUES
(174, 'test3', 'admin_post', 'test2', '123123', '/user/dashboard.php?post_id=22', 0, '2026-03-06 15:07:18'),
(192, 'test3', 'admin_post', 'тест', '1234уцкенегншгщдрмопсооооооооооооооооооооооооооооооооооооооооооооооооооооооооооооооооооооо', '/user/dashboard.php?post_id=21', 0, '2026-03-31 23:25:02'),
(197, 'maria', 'friend_request', 'Friend request sent', 'Oleksandr Cherepkov sent you a friend request', '/user/friends.php', 0, '2026-04-01 18:00:13'),
(199, 'maria', 'friend_accepted', 'Friend request accepted', 'Oleksandr Cherepkov accepted your friend request', '/user/friends.php', 0, '2026-04-01 18:01:53'),
(201, 'maria', 'friend_request', 'Friend request sent', 'Oleksandr Cherepkov sent you a friend request', '/user/friends.php', 0, '2026-04-01 18:04:03'),
(203, 'maria', 'friend_accepted', 'Friend request accepted', 'Oleksandr Cherepkov accepted your friend request', '/user/friends.php', 0, '2026-04-01 18:05:38'),
(204, 'maria', 'friend_request', 'Friend request sent', 'Oleksandr Cherepkov sent you a friend request', '/user/friends.php', 0, '2026-04-01 18:22:26'),
(207, 'testmail', 'friend_request', 'Friend request sent', 'Oleksandr Cherepkov sent you a friend request', '/user/friends.php', 0, '2026-04-01 19:07:30'),
(208, 'maria', 'friend_accepted', 'Friend request accepted', 'Oleksandr Cherepkov accepted your friend request', '/user/friends.php', 0, '2026-04-01 19:09:45'),
(229, 'maria', 'friend_accepted', 'Friend request accepted', 'System Admin accepted your friend request', '/user/friends.php', 0, '2026-04-02 16:28:55'),
(230, 'maria', 'friend_request', 'Friend request sent', 'test chat sent you a friend request', '/user/friends.php', 0, '2026-04-02 17:21:10'),
(231, 'cheol904', 'friend_request', 'Friend request sent', 'test chat sent you a friend request', '/user/friends.php', 0, '2026-04-02 17:21:17'),
(236, 'maria', 'friend_request', 'Friend request sent', 'wrt wrt sent you a friend request', '/user/friends.php', 0, '2026-04-02 21:57:00'),
(238, 'maria', 'friend_request', 'Friend request sent', 'q q sent you a friend request', '/user/friends.php', 0, '2026-04-02 22:30:38'),
(240, 'maria', 'collection_shared', 'Collection shared', 'System Admin shared collection: tester1', '/user/collections.php', 0, '2026-04-03 10:23:10'),
(241, 'alex', 'collection_shared', 'Collection shared', 'System Admin shared collection: tester1', '/user/collections.php', 1, '2026-04-03 10:23:21'),
(242, 'alex', 'collection_shared', 'Колекцію поділено', 'System Admin shared collection: ввв', '/user/collections.php', 0, '2026-04-08 14:14:04'),
(243, 'maria', 'friend_request', 'Friend request sent', 'System Admin sent you a friend request', '/user/friends.php', 0, '2026-04-08 14:16:02'),
(244, 'cheol904', 'collection_shared', 'Collection shared', 'System Admin shared collection: ввв', '/user/collections.php', 0, '2026-04-08 14:17:01'),
(245, 'cheol904', 'collection_shared', 'Collection shared', 'System Admin shared collection: ввв', '/user/collections.php', 0, '2026-04-08 14:17:25'),
(247, 'maria', 'collection_shared', 'Collection shared', 'Oleksandr Petrov shared collection: CollectionLimitTest1', '/user/collections.php', 0, '2026-04-08 14:41:01'),
(249, 'fadmin', 'collection_shared', 'Collection shared', 'System Admin shared collection: ввв', '/user/collections.php', 0, '2026-04-08 14:55:21'),
(250, 'mailtest', 'collection_shared', 'Collection shared', 'System Admin shared collection: ввв', '/user/collections.php', 0, '2026-04-08 14:55:21'),
(251, 'и', 'collection_shared', 'Collection shared', 'System Admin shared collection: ввв', '/user/collections.php', 0, '2026-04-08 14:55:21'),
(252, 'mailtest1', 'collection_shared', 'Collection shared', 'System Admin shared collection: ввв', '/user/collections.php', 0, '2026-04-08 15:20:21'),
(253, 'maria', 'collection_shared', 'Collection shared', 'System Admin shared collection: ввв', '/user/collections.php', 0, '2026-04-08 15:20:21'),
(254, 'alex', 'collection_shared', 'Collection shared', 'System Admin shared collection: test4', '/user/collections.php', 0, '2026-04-08 16:16:10'),
(255, 'cheol904', 'collection_shared', 'Collection shared', 'System Admin shared collection: test4', '/user/collections.php', 0, '2026-04-08 16:16:11'),
(256, 'mailtest', 'collection_shared', 'Collection shared', 'System Admin shared collection: test4', '/user/collections.php', 0, '2026-04-08 16:16:11'),
(257, 'и', 'collection_shared', 'Collection shared', 'System Admin shared collection: test4', '/user/collections.php', 0, '2026-04-08 16:16:11'),
(258, 'mailtest1', 'collection_shared', 'Collection shared', 'System Admin shared collection: test4', '/user/collections.php', 0, '2026-04-08 16:16:11'),
(259, 'maria', 'collection_shared', 'Collection shared', 'System Admin shared collection: test4', '/user/collections.php', 0, '2026-04-08 16:16:11'),
(260, 'testtime', 'collection_shared', 'Collection shared', 'System Admin shared collection: ввв', '/user/collections.php', 0, '2026-04-08 16:42:02'),
(261, 'newuser', 'collection_shared', 'Collection shared', 'System Admin shared collection: ввв', '/user/collections.php', 0, '2026-04-08 16:42:02'),
(262, 'test3', 'collection_shared', 'Collection shared', 'System Admin shared collection: ввв', '/user/collections.php', 0, '2026-04-08 17:30:39'),
(263, 'testmail', 'collection_shared', 'Collection shared', 'System Admin shared collection: ввв', '/user/collections.php', 0, '2026-04-08 17:30:39'),
(264, 'test3', 'collection_shared', 'Collection shared', 'System Admin shared collection: ввв', '/user/collections.php', 0, '2026-04-08 18:43:21'),
(265, 'fadmin', 'friend_request', 'Friend request sent', 'System Admin sent you a friend request', '/user/friends.php', 0, '2026-04-08 21:23:52'),
(268, 'fadmin', 'collection_shared', 'Collection shared', 'System Admin shared collection: ввв', '/user/collections.php', 0, '2026-04-08 21:33:47'),
(269, 'и', 'collection_shared', 'Collection shared', 'System Admin shared collection: ввв', '/user/collections.php', 0, '2026-04-08 21:33:57'),
(271, 'fadmin', 'collection_shared', 'Collection shared', 'System Admin shared collection: lesia', '/user/collections.php', 0, '2026-04-10 13:46:11'),
(272, 'maria', 'collection_shared', 'Collection shared', 'System Admin shared collection: lesia', '/user/collections.php', 0, '2026-04-10 13:46:11'),
(273, 'mailtest', 'friend_request', 'Friend request sent', 'System Admin sent you a friend request', '/user/friends.php', 0, '2026-04-10 13:48:03'),
(274, 'ШШШШШШШШШШШШШШШШШШШШ', 'friend_accepted', 'Friend request accepted', 'System Admin accepted your friend request', '/user/friends.php', 0, '2026-04-10 13:54:07'),
(275, 'qaz', 'admin_post', 'testmail', 'qwertt', '/user/dashboard.php?post_id=23', 0, '2026-04-12 18:29:13'),
(276, 'test3', 'admin_post', 'testmail', 'qwertt', '/user/dashboard.php?post_id=23', 0, '2026-04-12 18:29:14'),
(278, 'alex', 'admin_post', 'testmail', 'qwertt', '/user/dashboard.php?post_id=23', 0, '2026-04-12 18:29:14'),
(279, 'mailtest', 'admin_post', 'testmail', 'qwertt', '/user/dashboard.php?post_id=23', 0, '2026-04-12 18:29:14'),
(280, 'и', 'admin_post', 'testmail', 'qwertt', '/user/dashboard.php?post_id=23', 0, '2026-04-12 18:29:14'),
(281, 'Lesia', 'admin_post', 'testmail', 'qwertt', '/user/dashboard.php?post_id=23', 0, '2026-04-12 18:29:14'),
(282, 'maria', 'admin_post', 'testmail', 'qwertt', '/user/dashboard.php?post_id=23', 0, '2026-04-12 18:29:14'),
(283, 'newuser', 'admin_post', 'testmail', 'qwertt', '/user/dashboard.php?post_id=23', 0, '2026-04-12 18:29:14'),
(284, 'mailtest1', 'admin_post', 'testmail', 'qwertt', '/user/dashboard.php?post_id=23', 0, '2026-04-12 18:29:15'),
(285, 'fadmin', 'admin_post', 'testmail', 'qwertt', '/user/dashboard.php?post_id=23', 0, '2026-04-12 18:29:15'),
(286, 'testtime', 'admin_post', 'testmail', 'qwertt', '/user/dashboard.php?post_id=23', 0, '2026-04-12 18:29:15'),
(287, 'testmail', 'admin_post', 'testmail', 'qwertt', '/user/dashboard.php?post_id=23', 0, '2026-04-12 18:29:15'),
(288, 'cheol904', 'admin_post', 'testmail', 'qwertt', '/user/dashboard.php?post_id=23', 0, '2026-04-12 18:29:15'),
(289, 'ШШШШШШШШШШШШШШШШШШШШ', 'admin_post', 'testmail', 'qwertt', '/user/dashboard.php?post_id=23', 0, '2026-04-12 18:29:15'),
(290, 'krud', 'friend_request', 'Friend request sent', 'System Admin sent you a friend request', '/user/friends.php', 1, '2026-04-13 10:30:09'),
(292, 'krud', 'friend_request', 'Friend request sent', 'Oleksandrw Cherepkovw sent you a friend request', '/user/friends.php', 1, '2026-04-13 10:36:09'),
(293, 'cheol904', 'friend_accepted', 'Friend request accepted', 'K R accepted your friend request', '/user/friends.php', 0, '2026-04-13 10:36:35'),
(294, 'krud', 'collection_shared', 'Collection shared', 'Oleksandrw Cherepkovw shared collection: qwerty', '/user/collections.php', 1, '2026-04-13 10:37:02'),
(295, 'qaz', 'admin_post', 'Olexandr posmotri SonyBoy', 'Olexandr posmotri SonyBoy', '/user/dashboard.php?post_id=24', 0, '2026-04-13 11:03:35'),
(296, 'test3', 'admin_post', 'Olexandr posmotri SonyBoy', 'Olexandr posmotri SonyBoy', '/user/dashboard.php?post_id=24', 0, '2026-04-13 11:03:35'),
(297, 'abolo', 'admin_post', 'Olexandr posmotri SonyBoy', 'Olexandr posmotri SonyBoy', '/user/dashboard.php?post_id=24', 0, '2026-04-13 11:03:35'),
(299, 'alex', 'admin_post', 'Olexandr posmotri SonyBoy', 'Olexandr posmotri SonyBoy', '/user/dashboard.php?post_id=24', 0, '2026-04-13 11:03:35'),
(300, 'mailtest', 'admin_post', 'Olexandr posmotri SonyBoy', 'Olexandr posmotri SonyBoy', '/user/dashboard.php?post_id=24', 0, '2026-04-13 11:03:35'),
(301, 'и', 'admin_post', 'Olexandr posmotri SonyBoy', 'Olexandr posmotri SonyBoy', '/user/dashboard.php?post_id=24', 0, '2026-04-13 11:03:35'),
(302, 'krud', 'admin_post', 'Olexandr posmotri SonyBoy', 'Olexandr posmotri SonyBoy', '/user/dashboard.php?post_id=24', 1, '2026-04-13 11:03:35'),
(303, 'Lesia', 'admin_post', 'Olexandr posmotri SonyBoy', 'Olexandr posmotri SonyBoy', '/user/dashboard.php?post_id=24', 0, '2026-04-13 11:03:35'),
(304, 'maria', 'admin_post', 'Olexandr posmotri SonyBoy', 'Olexandr posmotri SonyBoy', '/user/dashboard.php?post_id=24', 0, '2026-04-13 11:03:35'),
(305, 'newuser', 'admin_post', 'Olexandr posmotri SonyBoy', 'Olexandr posmotri SonyBoy', '/user/dashboard.php?post_id=24', 0, '2026-04-13 11:03:35'),
(306, 'mailtest1', 'admin_post', 'Olexandr posmotri SonyBoy', 'Olexandr posmotri SonyBoy', '/user/dashboard.php?post_id=24', 0, '2026-04-13 11:03:35'),
(307, 'fadmin', 'admin_post', 'Olexandr posmotri SonyBoy', 'Olexandr posmotri SonyBoy', '/user/dashboard.php?post_id=24', 0, '2026-04-13 11:03:35'),
(308, 'testtime', 'admin_post', 'Olexandr posmotri SonyBoy', 'Olexandr posmotri SonyBoy', '/user/dashboard.php?post_id=24', 0, '2026-04-13 11:03:35'),
(309, 'testmail', 'admin_post', 'Olexandr posmotri SonyBoy', 'Olexandr posmotri SonyBoy', '/user/dashboard.php?post_id=24', 0, '2026-04-13 11:03:35'),
(310, 'cheol904', 'admin_post', 'Olexandr posmotri SonyBoy', 'Olexandr posmotri SonyBoy', '/user/dashboard.php?post_id=24', 0, '2026-04-13 11:03:35'),
(311, 'ШШШШШШШШШШШШШШШШШШШШ', 'admin_post', 'Olexandr posmotri SonyBoy', 'Olexandr posmotri SonyBoy', '/user/dashboard.php?post_id=24', 0, '2026-04-13 11:03:35'),
(312, 'abolo', 'admin_post', 'fjg,kg', ',kg.klutg.kuh.', '/user/dashboard.php?post_id=25', 1, '2026-04-13 11:13:18'),
(313, 'qaz', 'admin_post', 'fjg,kg', ',kg.klutg.kuh.', '/user/dashboard.php?post_id=25', 0, '2026-04-13 11:13:18'),
(314, 'admin', 'collection_shared', 'Collection shared', 'K R shared collection: lesia', '/user/collections.php', 0, '2026-04-13 11:43:09'),
(315, 'cheol904', 'collection_shared', 'Collection shared', 'K R shared collection: lesia', '/user/collections.php', 0, '2026-04-13 11:43:09');

-- --------------------------------------------------------

--
-- Структура таблиці `ownership_history`
--

CREATE TABLE `ownership_history` (
  `pk_ID` int(11) NOT NULL,
  `fk_serialNumber` varchar(50) NOT NULL,
  `fk_ownerId` varchar(50) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `description` varchar(1000) DEFAULT NULL,
  `registeredAt` datetime NOT NULL DEFAULT current_timestamp(),
  `unregisteredAt` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп даних таблиці `ownership_history`
--

INSERT INTO `ownership_history` (`pk_ID`, `fk_serialNumber`, `fk_ownerId`, `name`, `description`, `registeredAt`, `unregisteredAt`) VALUES
(1, 'ST001', 'alex', 'Weather Station North', 'Outdoor station near school yard hdhsd  tukfm ndjt fylisjdtfd jtmnb dtmdnhn tydnd sry mtm', '2025-02-21 09:00:00', '2026-04-12 14:56:23'),
(2, 'ST002', 'maria', 'Indoor Lab Station', 'Station inside physics lab', '2025-02-25 11:00:00', NULL),
(3, 'ST005', 'admin', 'newadminstation', '', '2026-04-06 14:46:43', NULL),
(4, 'ST006', 'alex', NULL, NULL, '2026-03-30 00:22:53', NULL),
(5, 'ST007', 'maria', NULL, NULL, '2026-04-03 10:54:31', NULL),
(6, 'ST008', 'admin', 'КККККККККккк', 'ои и', '2026-03-31 16:19:15', NULL),
(7, 'ST009', 'admin', 'measurements_test2', 'abolo changed2', '2026-03-31 16:19:22', NULL),
(8, 'ST010', 'admin', 'ten', '-', '2026-04-06 14:52:12', '2026-04-07 14:03:19'),
(9, 'ST011', 'admin', 'testChars', '', '2026-04-06 14:52:19', NULL),
(10, 'ST012', 'admin', 'test chars2', '', '2026-04-06 14:52:30', NULL),
(11, 'ST013', 'admin', 'Testchars3', '', '2026-04-06 14:52:35', '2026-04-07 13:54:17'),
(12, 'ST015', 'admin', 'ten1', '', '2026-04-06 15:46:21', NULL),
(13, 'ST016', 'admin', 'measurements_test0', '', '2026-04-06 15:46:31', '2026-04-06 23:01:13'),
(16, 'ST016', 'admin', NULL, NULL, '2026-04-06 23:01:28', '2026-04-06 23:20:07'),
(17, 'ST016', 'admin', 'Test Renaming', '1234', '2026-04-06 23:21:32', '2026-04-06 23:32:25'),
(18, 'ST016', 'admin', NULL, NULL, '2026-04-06 23:32:50', '2026-04-06 23:33:14'),
(19, 'ST016', 'admin', NULL, NULL, '2026-04-07 13:53:32', '2026-04-07 14:30:09'),
(20, 'ST010', 'admin', NULL, NULL, '2026-04-07 14:03:39', '2026-04-07 14:04:05'),
(21, 'ST010', 'admin', 'ттттттТТТ', '', '2026-04-07 14:04:25', '2026-04-13 11:33:11'),
(22, 'ST013', 'admin', 'ST0132', 'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAaa', '2026-04-07 14:20:49', '2026-04-07 14:21:28'),
(23, 'ST016', 'admin', 'ST016', NULL, '2026-04-07 14:30:31', '2026-04-07 14:51:56'),
(24, 'ST013', 'admin', 'ST013', NULL, '2026-04-07 14:31:27', '2026-04-07 14:31:51'),
(25, 'ST013', 'admin', 'ST013', NULL, '2026-04-07 14:36:45', '2026-04-07 14:37:31'),
(26, 'ST013', 'admin', 'ST013qwesssssssss', 'AAAAAAAAAAAAAAAAAAAAAAssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssss', '2026-04-07 14:48:06', '2026-04-07 14:49:09'),
(27, 'ST013', 'admin', 'ST013qwesssssssss', 'AAAAAAAAAAAAAAAAAAAAAAssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssss', '2026-04-07 14:49:25', '2026-04-07 14:49:29'),
(28, 'ST013', 'alex', 'ST013babab', 'bababba', '2026-04-07 14:50:10', '2026-04-07 14:51:21'),
(29, 'ST013', 'admin', 'ST013qwesssssssss', 'AAAAAAAAAAAAAAAAAAAAAAssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssss', '2026-04-07 14:52:23', '2026-04-07 15:03:11'),
(30, 'ST013', 'admin', 'ST013qwesssssssss', 'AAAAAAAAAAAAAAAAAAAAAAsssssssssssssssssssssssssssssss', '2026-04-07 15:03:34', NULL),
(31, 'ST001', 'admin', 'Weather Station North', 'Outdoor station near school yard hdhsd  tukfm ndjt fylisjdtfd jtmnb dtmdnhn tydnd sry mtm', '2026-04-12 14:50:29', NULL),
(32, 'ST016', 'admin', 'Test multi users', '', '2026-02-28 17:06:06', '2026-03-08 17:07:49'),
(33, 'ST016', 'alex', 'Test multi users', '', '2026-03-09 17:09:13', NULL),
(34, 'ST052', 'krud', 'ST052', NULL, '2026-04-13 11:16:15', NULL),
(35, 'ST010', 'krud', 'ST010', NULL, '2026-04-13 11:33:24', NULL);

-- --------------------------------------------------------

--
-- Структура таблиці `password_reset`
--

CREATE TABLE `password_reset` (
  `pk_resetID` int(11) NOT NULL,
  `fk_user` varchar(50) NOT NULL,
  `token` varchar(255) NOT NULL,
  `expiresAt` datetime NOT NULL,
  `used` tinyint(4) NOT NULL DEFAULT 0,
  `createdAt` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп даних таблиці `password_reset`
--

INSERT INTO `password_reset` (`pk_resetID`, `fk_user`, `token`, `expiresAt`, `used`, `createdAt`) VALUES
(1, 'newuser', '3ee3e004d7fe81f11578f51cacdc3817414d07449790eb1db9bb310d0e0b4e58', '2026-03-06 17:38:52', 1, '2026-03-06 16:38:52'),
(4, 'mailtest1', 'c90b1a2e449ccfdeda46419bbdad10d03777fefe204581628b7ee320b95f0ea4', '2026-03-23 17:32:28', 1, '2026-03-23 16:32:28');

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
(7, 'alex', 'admin', 'accepted', '2026-01-26 00:12:05'),
(8, 'alex', 'admin', 'accepted', '2026-02-15 17:41:58'),
(9, 'admin', 'cheol904', 'accepted', '2026-02-22 00:15:57'),
(10, 'cheol904', 'admin', 'accepted', '2026-02-22 16:44:37'),
(11, 'admin', 'cheol904', 'accepted', '2026-02-22 16:45:18'),
(12, 'admin', 'cheol904', 'accepted', '2026-02-22 16:47:10'),
(13, 'cheol904', 'admin', 'rejected', '2026-02-22 16:47:52'),
(17, 'admin', 'test3', 'accepted', '2026-02-23 14:15:10'),
(18, 'cheol904', 'admin', 'accepted', '2026-03-06 12:52:58'),
(19, 'admin', 'alex', 'accepted', '2026-03-06 16:32:46'),
(20, 'cheol904', 'maria', 'accepted', '2026-04-01 18:00:13'),
(21, 'maria', 'cheol904', 'accepted', '2026-04-01 18:01:22'),
(22, 'cheol904', 'maria', 'rejected', '2026-04-01 18:04:03'),
(23, 'maria', 'cheol904', 'accepted', '2026-04-01 18:04:36'),
(24, 'cheol904', 'maria', 'rejected', '2026-04-01 18:22:26'),
(25, 'maria', 'cheol904', 'rejected', '2026-04-01 18:38:12'),
(26, 'maria', 'cheol904', 'accepted', '2026-04-01 19:04:37'),
(27, 'cheol904', 'testmail', '', '2026-04-01 19:07:30'),
(28, 'admin', 'cheol904', 'rejected', '2026-04-01 19:48:29'),
(29, 'admin', 'cheol904', 'accepted', '2026-04-01 19:52:42'),
(30, 'cheol904', 'admin', '', '2026-04-01 19:55:41'),
(31, 'cheol904', 'admin', '', '2026-04-01 20:05:43'),
(32, 'cheol904', 'admin', 'rejected', '2026-04-01 20:05:47'),
(33, 'cheol904', 'admin', 'accepted', '2026-04-01 20:06:59'),
(34, 'admin', 'cheol904', '', '2026-04-01 20:16:16'),
(35, 'admin', 'cheol904', 'accepted', '2026-04-01 20:16:48'),
(36, 'cheol904', 'admin', 'accepted', '2026-04-01 20:18:56'),
(37, 'cheol904', 'admin', 'rejected', '2026-04-01 20:28:16'),
(38, 'cheol904', 'admin', 'accepted', '2026-04-01 20:28:54'),
(39, 'cheol904', 'admin', 'accepted', '2026-04-01 20:29:24'),
(40, 'maria', 'admin', '', '2026-04-02 16:27:41'),
(41, 'maria', 'admin', 'accepted', '2026-04-02 16:27:47'),
(43, 'admin', 'fadmin', 'accepted', '2026-04-08 21:23:52'),
(44, 'ШШШШШШШШШШШШШШШШШШШШ', 'admin', 'accepted', '2026-04-09 11:32:25'),
(45, 'admin', 'mailtest', 'pending', '2026-04-10 13:48:03'),
(46, 'admin', 'krud', 'accepted', '2026-04-13 10:30:09'),
(47, 'cheol904', 'krud', 'accepted', '2026-04-13 10:36:09');

-- --------------------------------------------------------

--
-- Структура таблиці `shares`
--

CREATE TABLE `shares` (
  `pkfk_user` varchar(50) NOT NULL,
  `pkfk_collection` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп даних таблиці `shares`
--

INSERT INTO `shares` (`pkfk_user`, `pkfk_collection`) VALUES
('admin', 5),
('admin', 18),
('admin', 20),
('alex', 15),
('alex', 16),
('alex', 17),
('cheol904', 16),
('cheol904', 17),
('cheol904', 20),
('fadmin', 17),
('fadmin', 20),
('krud', 10),
('mailtest', 17),
('mailtest1', 16),
('mailtest1', 17),
('maria', 1),
('maria', 5),
('maria', 16),
('maria', 17),
('maria', 20),
('newuser', 17),
('test3', 17),
('testmail', 17),
('testtime', 17),
('и', 17);

-- --------------------------------------------------------

--
-- Структура таблиці `slot`
--

CREATE TABLE `slot` (
  `pk_sampleID` int(11) NOT NULL,
  `fk_collection` int(11) NOT NULL,
  `fk_station` varchar(50) NOT NULL,
  `startDateTime` datetime NOT NULL,
  `endDateTime` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп даних таблиці `slot`
--

INSERT INTO `slot` (`pk_sampleID`, `fk_collection`, `fk_station`, `startDateTime`, `endDateTime`) VALUES
(1, 15, 'ST008', '2026-03-01 00:00:00', '2026-03-15 00:00:00'),
(4, 15, 'ST008', '2026-03-15 00:00:00', '2026-03-20 00:00:00'),
(5, 15, 'ST009', '2026-03-14 23:59:00', '2026-04-07 18:15:00'),
(9, 16, 'ST015', '2026-05-31 18:58:00', '2026-06-09 18:58:00'),
(10, 16, 'ST009', '2026-04-07 19:15:00', '2026-04-24 19:15:00'),
(17, 17, 'ST008', '2025-12-08 14:50:00', '2025-12-09 14:50:00'),
(18, 17, 'ST008', '2025-12-05 14:50:00', '2025-12-06 14:50:00'),
(20, 17, 'ST008', '2025-12-10 14:50:00', '2025-12-10 14:51:00'),
(21, 17, 'ST002', '2024-02-09 21:24:00', '2029-04-09 21:24:00'),
(22, 17, 'ST002', '2030-08-14 22:12:00', '2031-04-26 22:13:00'),
(23, 20, 'ST009', '2026-03-01 13:44:00', '2026-04-30 13:44:00'),
(24, 17, 'ST011', '2026-04-17 11:22:00', '2026-04-25 11:22:00');

-- --------------------------------------------------------

--
-- Структура таблиці `station`
--

CREATE TABLE `station` (
  `pk_serialNumber` varchar(50) NOT NULL,
  `fk_createdBy` varchar(50) DEFAULT NULL,
  `createdAt` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп даних таблиці `station`
--

INSERT INTO `station` (`pk_serialNumber`, `fk_createdBy`, `createdAt`) VALUES
('ST001', 'admin', '2025-02-20 08:00:00'),
('ST002', 'admin', '2025-02-25 10:00:00'),
('ST005', 'cheol904', '2026-01-23 19:48:38'),
('ST006', 'cheol904', '2026-01-23 19:49:07'),
('ST007', 'admin', '2026-01-25 19:38:33'),
('ST008', 'admin', '2026-03-31 16:18:08'),
('ST009', 'admin', '2026-03-31 16:18:41'),
('ST010', 'admin', '2026-04-06 14:50:18'),
('ST011', 'admin', '2026-04-06 14:50:39'),
('ST012', 'admin', '2026-04-06 14:51:01'),
('ST013', 'admin', '2026-04-06 14:51:30'),
('ST015', 'admin', '2026-04-06 15:45:34'),
('ST016', 'admin', '2026-04-06 15:45:44'),
('ST017', 'admin', '2026-04-12 14:15:05'),
('ST018', 'admin', '2026-04-12 14:24:34'),
('ST052', 'krud', '2026-04-13 11:16:03');

-- --------------------------------------------------------

--
-- Структура таблиці `user`
--

CREATE TABLE `user` (
  `pk_username` varchar(50) NOT NULL,
  `firstName` varchar(100) NOT NULL,
  `lastName` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `isEmailVerified` tinyint(1) NOT NULL DEFAULT 0,
  `emailVerifiedAt` datetime DEFAULT NULL,
  `passwordHash` varchar(255) NOT NULL,
  `role` enum('User','Admin') NOT NULL DEFAULT 'User',
  `avatar` varchar(255) DEFAULT NULL,
  `locale` varchar(5) NOT NULL DEFAULT 'en',
  `theme` varchar(10) NOT NULL DEFAULT 'light',
  `createdAt` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп даних таблиці `user`
--

INSERT INTO `user` (`pk_username`, `firstName`, `lastName`, `email`, `isEmailVerified`, `emailVerifiedAt`, `passwordHash`, `role`, `avatar`, `locale`, `theme`, `createdAt`) VALUES
('abolo', 'a', 'a', 'ablo@gmail.com', 1, '2026-04-13 10:49:19', '$2y$10$UAAum5Umxe9shuoidz7JRuk1cKiM87REJJdCw4ge/AVa9otBvxZ9W', 'User', NULL, 'en', 'light', '2026-04-13 10:49:19'),
('admin', 'System', 'Admin', 'admin@example.com', 1, '2026-04-10 14:02:54', '$2y$10$hafmooIznXZ/HbWnYjI3..zs75L6LflGlzNL47EMeFO2NW70JCYwi', 'Admin', 'monkey.webp', 'en', 'dark', '2025-01-01 00:00:00'),
('alex', 'Oleksandrw', 'Petrovwwww', 'alex.petrov@example.com', 1, '2026-03-29 21:48:58', '$2y$10$j86eYKRpUfyX9iVdsi3mI.tpSOdNjt4R7cBDDieXDPYN.8NhJJOYe', 'User', 'panda.jpg', 'en', 'dark', '2025-01-15 10:23:00'),
('cheol904', 'Oleksandrw', 'Cherepkovw', 'whatever@gmail.com', 1, '2026-03-23 16:11:19', '$2y$10$3z77scIaUihctBHJUfh7m.Vsrlur2J4Yggg.EWpX1xrYpo90Gd.Fa', 'Admin', 'cat.avif', 'en', 'dark', '2026-01-19 16:45:01'),
('fadmin', 'fake', 'admin', 'sf@f.com', 1, '2026-04-03 11:27:02', '$2y$10$B4v0q6HEuOpgommTjiOdcOu7mOvLyigOdo6Ra5AvUtPZf8gnOy992', 'User', 'monkey.webp', 'en', 'dark', '2026-04-03 11:27:02'),
('krud', 'K', 'R', 'krud@ch.uk', 1, '2026-04-13 10:27:36', '$2y$10$bS/DFwPinsz0EbMzw/NuUOSpqi7nTB0A5ql81Yco8cim8tlxb2FzS', 'Admin', 'monkey.webp', 'en', 'dark', '2026-04-13 10:27:36'),
('Lesia', 'Lesia', 'Buaraba', 'lesiapolunina@gmail.com', 1, '2026-04-10 13:32:24', '$2y$10$QqZmsxR7dfnp.Fn2vFUVX.d50Unu50KwYTsEzkfjdym8QEFO9j.SW', 'User', NULL, 'uk', 'dark', '2026-04-10 13:32:24'),
('mailtest', 'test', 'mail2', 'blablabla@bla.com', 1, '2026-03-23 16:55:26', '$2y$10$0ymydOOl2X8EBF6Tjfgz0ezkr.gxFk6xGkWozBsCBPTtzuJU1k7KS', 'User', NULL, 'en', 'light', '2026-03-23 16:28:07'),
('mailtest1', 'main', 'tester', 'qwe1@qwe.com', 1, '2026-03-23 16:31:57', '$2y$10$7EfEfuu7H6mxh8FlLHZjyueULw3IIJoQjXEG1ajKoMDNMm7jwlzDW', 'User', NULL, 'en', 'light', '2026-03-23 16:31:57'),
('maria', 'Maria', 'Schmidt', 'maria@exaplme.com', 1, '2026-04-02 15:38:55', '$2y$10$kSrl6Q5DgrvqfhZXXaYYVOmjfiP26YRxAEA3BRk5QKx.ee3SOsWE6', 'User', 'upload:avatar_maria_223a048275b029ed.jpg', 'en', 'dark', '2025-02-02 14:45:00'),
('newuser', 'new', 'new', 'new@new.com', 0, NULL, '$2y$10$ndJBThBoZwU9Y.PkYwx2yO0riBtTLWMMWRscpdaePG7cGwPYnAawO', 'User', NULL, 'en', 'light', '0000-00-00 00:00:00'),
('qaz', '1', '1', '1@122.com', 0, NULL, '$2y$10$5Yeo1.0RCr6RfpDLAyELyONiOmPECe9X7Hme3y0SyEHA631J5KjDa', 'User', NULL, 'en', 'light', '2026-04-10 12:44:26'),
('test3', '1', '1', '1@2.com', 0, NULL, '$2y$10$9A320YklCzYGamMe1Gd1Ou2Nb76Gr5GJJ5wND5MzIkNY7.uv4iRwK', 'User', NULL, 'en', 'light', '0000-00-00 00:00:00'),
('testmail', 'test', 'mail', 'test1@test.test', 1, '2026-03-06 17:16:34', '$2y$10$7oz6X9TFSBZ47zXkoRoiw.IkPNcTYzdje/JHmDBdMlap6eixNUCUG', 'User', NULL, 'en', 'light', '0000-00-00 00:00:00'),
('testtime', 'test', 'time', 't@time.time', 1, '2026-03-06 18:39:34', '$2y$10$6D5mMr35ojWA7gt.IOj5fOLD8JcGkXeWYAtSCqBZnYYxmCug3x61i', 'User', NULL, 'en', 'light', '2026-03-06 18:06:15'),
('и', 'йцукен', 'аасe', 'g@f.c', 1, '2026-04-07 13:49:56', '$2y$10$nvwmVH0/bHLoJfWQqBTU6uz5ex3kmpTnuOQomJtLsxsyINegT9Cf.', 'User', NULL, 'en', 'dark', '2026-04-07 13:49:56'),
('ШШШШШШШШШШШШШШШШШШШШ', 'ШШШШШШШШШШ', 'ШШШШШШШШШШ', 'WWW@WWWWWWWWWWWW.com', 1, '2026-04-09 11:30:31', '$2y$10$Tzv7GYhkxDWjq22WTtQ8TeYueMspUS2IMRLTx8iJkzrReX9KTHoDy', 'User', 'fish.avif', 'en', 'dark', '2026-04-09 11:30:31');

--
-- Індекси збережених таблиць
--

--
-- Індекси таблиці `admin_post`
--
ALTER TABLE `admin_post`
  ADD PRIMARY KEY (`pk_postID`),
  ADD KEY `fk_author` (`fk_author`);

--
-- Індекси таблиці `chat_conversation`
--
ALTER TABLE `chat_conversation`
  ADD PRIMARY KEY (`pk_conversationID`),
  ADD KEY `createdBy` (`createdBy`);

--
-- Індекси таблиці `chat_draft`
--
ALTER TABLE `chat_draft`
  ADD PRIMARY KEY (`fk_conversation`,`fk_user`),
  ADD KEY `idx_chat_draft_user` (`fk_user`);

--
-- Індекси таблиці `chat_draft_file`
--
ALTER TABLE `chat_draft_file`
  ADD PRIMARY KEY (`pk_fileID`),
  ADD KEY `idx_chat_draft_file_owner` (`fk_conversation`,`fk_user`),
  ADD KEY `fk_chat_draft_file_user` (`fk_user`);

--
-- Індекси таблиці `chat_message`
--
ALTER TABLE `chat_message`
  ADD PRIMARY KEY (`pk_messageID`),
  ADD KEY `fk_conversation` (`fk_conversation`),
  ADD KEY `fk_sender` (`fk_sender`);

--
-- Індекси таблиці `chat_participant`
--
ALTER TABLE `chat_participant`
  ADD PRIMARY KEY (`fk_conversation`,`fk_user`),
  ADD KEY `fk_user` (`fk_user`);

--
-- Індекси таблиці `chat_read_state`
--
ALTER TABLE `chat_read_state`
  ADD PRIMARY KEY (`fk_conversation`,`fk_user`),
  ADD KEY `idx_chat_read_user` (`fk_user`),
  ADD KEY `fk_chat_read_state_message` (`lastReadMessageId`);

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
-- Індекси таблиці `email_verification`
--
ALTER TABLE `email_verification`
  ADD PRIMARY KEY (`pk_verificationID`),
  ADD UNIQUE KEY `token` (`token`),
  ADD KEY `fk_user` (`fk_user`);

--
-- Індекси таблиці `friendship`
--
ALTER TABLE `friendship`
  ADD UNIQUE KEY `uniq_friendship` (`pkfk_user1`,`pkfk_user2`),
  ADD KEY `fk_friendship_user2` (`pkfk_user2`);

--
-- Індекси таблиці `measurement`
--
ALTER TABLE `measurement`
  ADD PRIMARY KEY (`pk_measurementID`),
  ADD KEY `idx_measurement_owner_id` (`fk_ownerId`),
  ADD KEY `idx_measurement_station_time` (`fk_station`,`timestamp`);

--
-- Індекси таблиці `notification`
--
ALTER TABLE `notification`
  ADD PRIMARY KEY (`pk_notificationID`),
  ADD KEY `fk_user` (`fk_user`);

--
-- Індекси таблиці `ownership_history`
--
ALTER TABLE `ownership_history`
  ADD PRIMARY KEY (`pk_ID`) USING BTREE,
  ADD KEY `idx_active_owner` (`fk_serialNumber`,`unregisteredAt`),
  ADD KEY `fk_ownership_history_user` (`fk_ownerId`),
  ADD KEY `idx_ownership_history_active` (`fk_serialNumber`,`unregisteredAt`);

--
-- Індекси таблиці `password_reset`
--
ALTER TABLE `password_reset`
  ADD PRIMARY KEY (`pk_resetID`),
  ADD UNIQUE KEY `token` (`token`),
  ADD KEY `fk_user` (`fk_user`);

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
  ADD PRIMARY KEY (`pkfk_user`,`pkfk_collection`),
  ADD KEY `fk_shares_collection` (`pkfk_collection`);

--
-- Індекси таблиці `slot`
--
ALTER TABLE `slot`
  ADD PRIMARY KEY (`pk_sampleID`),
  ADD KEY `fk_collection` (`fk_collection`),
  ADD KEY `fk_station` (`fk_station`);

--
-- Індекси таблиці `station`
--
ALTER TABLE `station`
  ADD PRIMARY KEY (`pk_serialNumber`),
  ADD KEY `fk_createdBy` (`fk_createdBy`);

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
-- AUTO_INCREMENT для таблиці `admin_post`
--
ALTER TABLE `admin_post`
  MODIFY `pk_postID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT для таблиці `chat_conversation`
--
ALTER TABLE `chat_conversation`
  MODIFY `pk_conversationID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT для таблиці `chat_draft_file`
--
ALTER TABLE `chat_draft_file`
  MODIFY `pk_fileID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT для таблиці `chat_message`
--
ALTER TABLE `chat_message`
  MODIFY `pk_messageID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=123;

--
-- AUTO_INCREMENT для таблиці `collection`
--
ALTER TABLE `collection`
  MODIFY `pk_collectionID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT для таблиці `email_verification`
--
ALTER TABLE `email_verification`
  MODIFY `pk_verificationID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT для таблиці `measurement`
--
ALTER TABLE `measurement`
  MODIFY `pk_measurementID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=123897;

--
-- AUTO_INCREMENT для таблиці `notification`
--
ALTER TABLE `notification`
  MODIFY `pk_notificationID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=316;

--
-- AUTO_INCREMENT для таблиці `ownership_history`
--
ALTER TABLE `ownership_history`
  MODIFY `pk_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT для таблиці `password_reset`
--
ALTER TABLE `password_reset`
  MODIFY `pk_resetID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT для таблиці `request`
--
ALTER TABLE `request`
  MODIFY `pk_requestID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=48;

--
-- AUTO_INCREMENT для таблиці `slot`
--
ALTER TABLE `slot`
  MODIFY `pk_sampleID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- Обмеження зовнішнього ключа збережених таблиць
--

--
-- Обмеження зовнішнього ключа таблиці `admin_post`
--
ALTER TABLE `admin_post`
  ADD CONSTRAINT `admin_post_ibfk_1` FOREIGN KEY (`fk_author`) REFERENCES `user` (`pk_username`) ON DELETE SET NULL;

--
-- Обмеження зовнішнього ключа таблиці `chat_conversation`
--
ALTER TABLE `chat_conversation`
  ADD CONSTRAINT `chat_conversation_ibfk_1` FOREIGN KEY (`createdBy`) REFERENCES `user` (`pk_username`) ON DELETE SET NULL;

--
-- Обмеження зовнішнього ключа таблиці `chat_draft`
--
ALTER TABLE `chat_draft`
  ADD CONSTRAINT `fk_chat_draft_conversation` FOREIGN KEY (`fk_conversation`) REFERENCES `chat_conversation` (`pk_conversationID`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_chat_draft_user` FOREIGN KEY (`fk_user`) REFERENCES `user` (`pk_username`) ON DELETE CASCADE;

--
-- Обмеження зовнішнього ключа таблиці `chat_draft_file`
--
ALTER TABLE `chat_draft_file`
  ADD CONSTRAINT `fk_chat_draft_file_conversation` FOREIGN KEY (`fk_conversation`) REFERENCES `chat_conversation` (`pk_conversationID`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_chat_draft_file_user` FOREIGN KEY (`fk_user`) REFERENCES `user` (`pk_username`) ON DELETE CASCADE;

--
-- Обмеження зовнішнього ключа таблиці `chat_message`
--
ALTER TABLE `chat_message`
  ADD CONSTRAINT `chat_message_ibfk_1` FOREIGN KEY (`fk_conversation`) REFERENCES `chat_conversation` (`pk_conversationID`) ON DELETE CASCADE,
  ADD CONSTRAINT `chat_message_ibfk_2` FOREIGN KEY (`fk_sender`) REFERENCES `user` (`pk_username`) ON DELETE SET NULL;

--
-- Обмеження зовнішнього ключа таблиці `chat_participant`
--
ALTER TABLE `chat_participant`
  ADD CONSTRAINT `chat_participant_ibfk_1` FOREIGN KEY (`fk_conversation`) REFERENCES `chat_conversation` (`pk_conversationID`) ON DELETE CASCADE,
  ADD CONSTRAINT `chat_participant_ibfk_2` FOREIGN KEY (`fk_user`) REFERENCES `user` (`pk_username`) ON DELETE CASCADE;

--
-- Обмеження зовнішнього ключа таблиці `chat_read_state`
--
ALTER TABLE `chat_read_state`
  ADD CONSTRAINT `fk_chat_read_state_message` FOREIGN KEY (`lastReadMessageId`) REFERENCES `chat_message` (`pk_messageID`) ON DELETE SET NULL;

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
-- Обмеження зовнішнього ключа таблиці `email_verification`
--
ALTER TABLE `email_verification`
  ADD CONSTRAINT `email_verification_ibfk_1` FOREIGN KEY (`fk_user`) REFERENCES `user` (`pk_username`) ON DELETE CASCADE;

--
-- Обмеження зовнішнього ключа таблиці `friendship`
--
ALTER TABLE `friendship`
  ADD CONSTRAINT `fk_friendship_user1` FOREIGN KEY (`pkfk_user1`) REFERENCES `user` (`pk_username`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_friendship_user2` FOREIGN KEY (`pkfk_user2`) REFERENCES `user` (`pk_username`) ON DELETE CASCADE,
  ADD CONSTRAINT `friendship_ibfk_1` FOREIGN KEY (`pkfk_user1`) REFERENCES `user` (`pk_username`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `friendship_ibfk_2` FOREIGN KEY (`pkfk_user2`) REFERENCES `user` (`pk_username`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Обмеження зовнішнього ключа таблиці `measurement`
--
ALTER TABLE `measurement`
  ADD CONSTRAINT `fk_measurement_owner` FOREIGN KEY (`fk_ownerId`) REFERENCES `user` (`pk_username`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `measurement_ibfk_1` FOREIGN KEY (`fk_station`) REFERENCES `station` (`pk_serialNumber`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Обмеження зовнішнього ключа таблиці `notification`
--
ALTER TABLE `notification`
  ADD CONSTRAINT `notification_ibfk_1` FOREIGN KEY (`fk_user`) REFERENCES `user` (`pk_username`) ON DELETE CASCADE;

--
-- Обмеження зовнішнього ключа таблиці `ownership_history`
--
ALTER TABLE `ownership_history`
  ADD CONSTRAINT `fk_history_station` FOREIGN KEY (`fk_serialNumber`) REFERENCES `station` (`pk_serialNumber`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ownership_history_user` FOREIGN KEY (`fk_ownerId`) REFERENCES `user` (`pk_username`) ON DELETE CASCADE;

--
-- Обмеження зовнішнього ключа таблиці `password_reset`
--
ALTER TABLE `password_reset`
  ADD CONSTRAINT `password_reset_ibfk_1` FOREIGN KEY (`fk_user`) REFERENCES `user` (`pk_username`) ON DELETE CASCADE;

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
  ADD CONSTRAINT `fk_shares_collection` FOREIGN KEY (`pkfk_collection`) REFERENCES `collection` (`pk_collectionID`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_shares_user` FOREIGN KEY (`pkfk_user`) REFERENCES `user` (`pk_username`) ON DELETE CASCADE,
  ADD CONSTRAINT `shares_ibfk_1` FOREIGN KEY (`pkfk_user`) REFERENCES `user` (`pk_username`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `shares_ibfk_2` FOREIGN KEY (`pkfk_collection`) REFERENCES `collection` (`pk_collectionID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Обмеження зовнішнього ключа таблиці `slot`
--
ALTER TABLE `slot`
  ADD CONSTRAINT `slot_ibfk_1` FOREIGN KEY (`fk_collection`) REFERENCES `collection` (`pk_collectionID`) ON DELETE CASCADE,
  ADD CONSTRAINT `slot_ibfk_2` FOREIGN KEY (`fk_station`) REFERENCES `station` (`pk_serialNumber`) ON DELETE CASCADE;

--
-- Обмеження зовнішнього ключа таблиці `station`
--
ALTER TABLE `station`
  ADD CONSTRAINT `station_ibfk_1` FOREIGN KEY (`fk_createdBy`) REFERENCES `user` (`pk_username`) ON DELETE SET NULL ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
