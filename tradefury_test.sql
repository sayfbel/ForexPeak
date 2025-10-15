-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1
-- Généré le : mer. 06 août 2025 à 23:13
-- Version du serveur : 10.4.32-MariaDB
-- Version de PHP : 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `tradefury_test`
--

-- --------------------------------------------------------

--
-- Structure de la table `community_1111`
--

CREATE TABLE `community_1111` (
  `id` int(11) NOT NULL,
  `sender_id` int(11) DEFAULT NULL,
  `receiver_id` int(11) DEFAULT NULL,
  `party_id` int(11) DEFAULT NULL,
  `community_message` text NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  `image_path` varchar(255) DEFAULT NULL,
  `reply_to_message_id` int(11) DEFAULT NULL,
  `original_message` text DEFAULT NULL,
  `replied` tinyint(1) DEFAULT 0,
  `name_sender` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `community_1111`
--

INSERT INTO `community_1111` (`id`, `sender_id`, `receiver_id`, `party_id`, `community_message`, `timestamp`, `image_path`, `reply_to_message_id`, `original_message`, `replied`, `name_sender`) VALUES
(97, 1, 2, NULL, 'psps', '2025-06-16 00:36:28', NULL, 0, '', 0, NULL);

-- --------------------------------------------------------

--
-- Structure de la table `community_5475`
--

CREATE TABLE `community_5475` (
  `id` int(11) NOT NULL,
  `sender_id` int(11) DEFAULT NULL,
  `receiver_id` int(11) DEFAULT NULL,
  `party_id` int(11) DEFAULT NULL,
  `community_message` text NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  `image_path` varchar(255) DEFAULT NULL,
  `reply_to_message_id` int(11) DEFAULT NULL,
  `original_message` text DEFAULT NULL,
  `replied` tinyint(1) DEFAULT 0,
  `name_sender` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `community_5475`
--

INSERT INTO `community_5475` (`id`, `sender_id`, `receiver_id`, `party_id`, `community_message`, `timestamp`, `image_path`, `reply_to_message_id`, `original_message`, `replied`, `name_sender`) VALUES
(1, 1, 2, NULL, 'salam', '2025-04-17 22:53:12', NULL, 0, '', 0, NULL),
(2, 1, 2, NULL, 'cc', '2025-04-17 22:53:23', NULL, 1, 'salam', 0, 'saif');

-- --------------------------------------------------------

--
-- Structure de la table `journal`
--

CREATE TABLE `journal` (
  `id` int(11) NOT NULL,
  `date_journal` datetime DEFAULT NULL,
  `pair` varchar(255) DEFAULT NULL,
  `entry` double DEFAULT NULL,
  `sl` double DEFAULT NULL,
  `tp` double DEFAULT NULL,
  `close_journal` double DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `id_login` int(11) DEFAULT NULL,
  `id_users` int(11) DEFAULT NULL,
  `date_close` datetime DEFAULT NULL,
  `trade_type` enum('manual','file','payout','commission','deposit') DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `journal`
--

INSERT INTO `journal` (`id`, `date_journal`, `pair`, `entry`, `sl`, `tp`, `close_journal`, `description`, `image`, `id_login`, `id_users`, `date_close`, `trade_type`) VALUES
(1, '2024-11-08 13:13:50', 'XAUUSD', 2687.34, 2691.84, 2682.84, -181.6, 'ma39ltch', '', 1, 20, '2024-11-08 13:37:44', NULL),
(2, '2024-11-11 11:24:13', 'XAUUSD', 2666.45, 2665.22, 2670.95, 180, 'ma39ltch', '', 1, 20, '2024-11-11 12:10:01', NULL),
(3, '2024-11-26 15:49:51', 'XAUUSD', 2629.84, 2626.1, 2647.33, -56.1, 'ma39ltch', '', 1, 20, '2024-11-26 15:51:48', NULL),
(4, '2024-11-27 16:27:27', 'EURUSD', 1.0556, 1.0604, 1.03957, 1.65, 'ma39ltch', '', 1, 20, '2024-11-27 17:40:44', NULL),
(5, '2024-11-28 17:44:04', 'XAUUSD', 2638.58, 2623.89, 2698.78, 207.3, 'ma39ltch', '', 1, 20, '2024-11-29 18:14:37', NULL),
(32, '2024-12-31 06:06:00', 'GBPUSD', 1.25502, 1.2542, 1.2597, 3, 'Break Even ', '', 1, 20, '2024-12-31 22:13:00', NULL),
(33, '2024-12-31 10:19:00', 'XAUUSD', 2613.69, 2615.2, 2602.7, -60.4, 'Hzito ', '', 1, 20, '2024-12-31 10:24:00', NULL),
(34, '2025-01-02 07:07:00', 'EURUSD', 1.03683, 1.0375, 1.0333, 280, 'AHSAN TREAD ENDI ', '', 1, 20, '2025-01-02 00:47:00', NULL),
(35, '2025-01-03 00:01:00', 'EURUSD', 1.0292, 1.0297, 1.0225, -32.9, 'Drt ghalat mn sbah ou ana kantsna ou flkhr khrjt ela ruls analyse', '', 1, 20, '2025-01-03 13:25:00', NULL),
(36, '2025-01-03 13:08:00', 'EURUSD', 1.02859, 1.0297, 1.02794, -75.6, 'yalaj khsrt ou bghit nslhha ou dkhlt f rivangh li khlani nkhsr tread big loss', '', 1, 20, '2025-01-03 13:24:00', NULL),
(37, '2025-01-06 16:37:00', 'XAUUSD', 2624.75, 2613.49, 2650.79, 112.4, 'trade mzyan rj3t bih loss lifat khrjt 9bl lw9t ', '', 1, 20, '2025-01-06 18:35:00', NULL),
(39, '2025-01-07 18:37:00', 'USDCAD', 1.43152, 1.431, 1.4345, -54.51, 'khsara sl kan ssghir endi mochkil f sl', '', 1, 20, '2025-01-07 21:20:00', NULL),
(40, '2025-01-08 16:27:00', 'EURUSD', 1.03532, 1.0355, 1.0333, -2.5, 'Break even ', '', 1, 20, '2025-01-08 16:44:00', NULL),
(41, '2025-01-08 10:08:00', 'XAUUSD', 2652.08, 2649.96, 2664.38, -53.5, 'sl sghir endi mochkil f sl ', '', 1, 20, '2025-01-08 13:27:00', NULL),
(42, '2025-01-09 10:41:00', 'XAUUSD', 2663.67, 2661.29, 2681.86, -59.75, 'sl sghir endi mochkil sl', '', 1, 20, '2025-01-09 10:47:00', NULL),
(43, '2025-01-14 11:01:00', 'NZDUSD', 0.56183, 0.56298, 0.5577, 180, 'hna khrjt hit kayna l ppi ou riskit 100$ hit ghad payout makhasch hadchi it3awd\r\n', '', 1, 20, '2025-01-14 13:58:00', NULL),
(44, '2024-12-02 18:32:14', 'XAUUSD', 2642.28, 2638.42, 2663.97, -58.05, 'ma39ltch', '', 1, 20, '2024-12-02 19:05:03', NULL),
(45, '2024-12-03 06:04:42', 'GBPUSD', 1.26476, 1.22879, 1.27274, 1.5, 'ma39ltch', '', 1, 20, '2024-12-03 14:30:03', NULL),
(46, '2024-12-03 17:18:28', 'EURUSD', 1.05047, 1.0484, 1.05947, 69.6, 'ma39ltch', '', 1, 20, '2024-12-03 20:52:11', NULL),
(47, '2024-12-04 13:19:55', 'XAUUSD', 2644.81, 2649.6, 2622.43, -58.44, 'ma39ltch', '', 1, 20, '2024-12-04 16:03:05', NULL),
(48, '2024-12-06 02:32:26', 'XAUUSD', 2632.68, 2635.15, 2614.92, 357.9, 'ma39ltch', '', 1, 20, '2024-12-06 03:09:23', NULL),
(49, '2024-12-10 16:38:27', 'GBPUSD', 1.27546, 1.27427, 1.27932, -38.5, 'ma39ltch', '', 1, 20, '2024-12-10 16:53:04', NULL),
(50, '2024-12-11 05:27:12', 'EURUSD', 1.05296, 1.05608, 1.04621, 105.6, 'ma39ltch', '', 1, 20, '2024-12-11 11:50:15', NULL),
(51, '2024-12-11 05:45:31', 'EURUSD', 1.05313, 1.05621, 1.04621, 112.4, 'ma39ltch', '', 1, 20, '2024-12-11 11:50:15', NULL),
(52, '2024-12-12 05:06:21', 'XAUUSD', 2706.43, 2696.66, 2726.04, 4.6, 'ma39ltch', '', 1, 20, '2024-12-12 14:55:40', NULL),
(53, '2024-12-13 06:31:05', 'GBPUSD', 1.2633, 1.26415, 1.26075, -51.5, 'ma39ltch', '', 1, 20, '2024-12-13 07:42:22', NULL),
(55, '2024-12-19 14:48:51', 'EURUSD', 1.04042, 1.04452, 1.03198, 102.75, 'ma39ltch', '', 1, 20, '2024-12-19 23:17:11', NULL),
(56, '2024-12-20 12:21:42', 'EURUSD', 1.0382, 1.0399, 1.03439, -68.4, 'ma39ltch', '', 1, 20, '2024-12-20 14:31:54', NULL),
(57, '2024-12-24 06:22:41', 'GBPUSD', 1.2537, 1.25428, 1.24862, -58, 'ma39ltch', '', 1, 20, '2024-12-24 10:37:36', NULL),
(58, '2024-12-16 16:30:00', 'GBPUSD', 1.2633, 1.26415, 1.26075, 0.24, 'ma39ltch', '', 1, 20, '2024-12-16 16:32:00', NULL),
(59, '2024-12-18 12:14:00', 'GBPUSD', 1.26908, 1.27154, 1.26103, -59.04, 'ma39ltch', '', 1, 20, '2024-12-18 12:56:00', NULL),
(65, '2025-01-15 16:14:00', 'AUDJPY', 97.496, 98.047, 96.381, 1.28, 'Breack even', '', 1, 20, '2025-01-15 21:55:00', NULL),
(66, '2025-01-15 18:59:00', 'AUDJPY', 97.338, 97.492, 96.381, -49.2, 'hna jbtha frasi mora ma dkhlt 2 entre f nhar wahd ', '', 1, 20, '2025-01-15 21:55:00', NULL),
(67, '2025-01-16 09:24:52', 'USDJPY', 156.12, 156.319, 155.212, 61.25, 'ma39ltch', NULL, 1, 20, '2025-01-16 10:51:11', NULL),
(68, '2025-01-17 12:20:14', 'USDJPY', 155.663, 155.76, 154.968, -31.46, 'ma39ltch', NULL, 1, 20, '2025-01-17 16:16:02', NULL),
(69, '2025-01-17 15:19:02', 'GBPUSD', 1.21862, 1.22239, 1.21092, 2.8, 'ma39ltch', NULL, 1, 20, '2025-01-17 15:33:16', NULL),
(70, '2025-01-17 15:40:42', 'USDJPY', 155.592, 155.76, 154.968, -54.25, 'ma39ltch', NULL, 1, 20, '2025-01-17 16:16:02', NULL),
(71, '2025-01-21 15:30:37', 'EURUSD', 1.03633, 1.03455, 1.04213, 145, 'ma39ltch', NULL, 1, 20, '2025-01-21 16:27:45', NULL),
(72, '2025-01-22 09:48:26', 'EURUSD', 1.04134, 1.04089, 1.04597, 206.5, 'ma39ltch', NULL, 1, 20, '2025-01-22 12:31:07', NULL),
(930, '2025-01-31 15:29:00', 'EURUSD', 1.03892, 1.03955, 1.03631, 208.8, 'I don\'t save it', '', 1, 20, '2025-01-31 17:09:00', NULL),
(954, '2024-12-09 23:49:00', NULL, NULL, NULL, NULL, -442.11, 'Type: payout, Amount: 442.11', 'WhatsApp Image 2025-08-05 at 08.55.20_8e4fcc71.jpg', 1, 20, NULL, 'payout'),
(955, '2024-12-24 23:48:34', NULL, NULL, NULL, NULL, -23.68, 'Type: payout, Amount: 23.68', 'WhatsApp Image 2025-08-05 at 08.55.20_d8265521.jpg', 1, 20, NULL, 'payout'),
(956, '2025-01-14 23:48:00', NULL, NULL, NULL, NULL, -189.48, 'Type: payout, Amount: 189.48', 'WhatsApp Image 2025-08-05 at 08.55.21_674e34f4.jpg', 1, 20, NULL, 'payout'),
(957, '2025-01-29 23:49:00', NULL, NULL, NULL, NULL, -254.14, 'Type: payout, Amount: 254.14', 'WhatsApp Image 2025-08-05 at 08.55.20_f31e57cb.jpg', 1, 20, NULL, 'payout'),
(984, '2025-01-15 12:39:00', 'NZDUSD', 0.56035, 0.59851, 0.56544, -0.41, 'I don\'t save it', '', 1, 20, '2025-01-15 12:39:00', NULL),
(985, '2024-12-23 00:01:00', 'EURUSD', 1.04068, 1.05119, 1.03524, 1.6, 'I don\'t save it', '', 1, 20, '2024-12-23 04:10:00', NULL),
(1021, '2025-02-07 15:50:00', NULL, NULL, NULL, NULL, -153.1, 'commition', NULL, 1, 20, NULL, 'commission'),
(1111, '2025-02-15 06:34:00', NULL, NULL, NULL, NULL, -203.2, 'payout makayn ghir ng3a', 'WhatsApp Image 2025-08-05 at 08.55.20_b2b632ee.jpg', 1, 20, NULL, 'payout'),
(1274, '2024-12-11 03:32:06', 'EURUSDm', 1.05288, 1.05388, 1.0462, 68, 'I don\'t save it', NULL, 5, 31, '2024-12-11 07:52:41', ''),
(1275, '2024-12-12 03:07:20', 'XAUUSDm', 2706.414, 2706.548, 2726.04, 1.07, 'I don\'t save it', NULL, 5, 31, '2024-12-12 12:55:40', ''),
(1276, '2024-12-13 04:32:04', 'XAUUSDm', 2687.236, 2689.11, 2675.498, -14.99, 'I don\'t save it', NULL, 5, 31, '2024-12-13 05:42:25', ''),
(1277, '2024-12-16 02:31:22', 'GBPUSDm', 1.26326, 1.2641, 1.25983, -10.08, 'I don\'t save it', NULL, 5, 31, '2024-12-16 07:19:12', ''),
(1278, '2024-12-18 10:13:26', 'GBPUSDm', 1.26899, 1.27154, 1.26025, -25.5, 'I don\'t save it', NULL, 5, 31, '2024-12-18 10:56:47', ''),
(1279, '2024-12-19 12:46:48', 'EURUSDm', 1.04042, 1.04032, 1.03198, 40.6, 'I don\'t save it', NULL, 5, 31, '2024-12-19 21:14:52', ''),
(1280, '2024-12-20 10:05:38', 'EURUSDm', 1.03837, 1.03996, 1.03452, -15.9, 'I don\'t save it', NULL, 5, 31, '2024-12-20 12:32:02', ''),
(1281, '2024-12-20 10:06:24', 'EURUSDm', 1.03849, 1.03998, 1.03452, -14.9, 'I don\'t save it', NULL, 5, 31, '2024-12-20 12:32:03', ''),
(1282, '2024-12-23 10:03:50', 'EURUSDm', 1.04068, 1.04064, 1.03524, 0.6, 'I don\'t save it', NULL, 5, 31, '2024-12-23 14:10:39', ''),
(1283, '2024-12-24 04:23:44', 'GBPUSDm', 1.25366, 1.25428, 1.24862, -12.4, 'I don\'t save it', NULL, 5, 31, '2024-12-24 08:36:36', ''),
(1284, '2024-12-31 03:55:44', 'GBPUSDm', 1.2552, 1.25418, 1.26035, -20.4, 'I don\'t save it', NULL, 5, 31, '2024-12-31 06:45:56', ''),
(1285, '2025-01-02 05:16:33', 'EURUSDm', 1.03656, 1.03742, 1.03333, 93.67, 'I don\'t save it', NULL, 5, 31, '2025-01-02 10:48:48', ''),
(1286, '2025-01-03 11:08:21', 'EURUSDm', 1.02854, 1.03002, 1.02246, -44.4, 'I don\'t save it', NULL, 5, 31, '2025-01-03 11:44:35', ''),
(1287, '2025-01-06 14:40:48', 'XAUUSDm', 2625.366, 2625.599, 2650.79, 39.92, 'I don\'t save it', NULL, 5, 31, '2025-01-06 16:35:46', ''),
(1288, '2025-01-08 08:54:26', 'XAUUSDm', 2651.335, 2649.209, 2664.38, -10.63, 'I don\'t save it', NULL, 5, 31, '2025-01-08 11:28:05', ''),
(1289, '2025-01-09 08:44:36', 'XAUUSDm', 2662.604, 2662.681, 2681.86, 0.39, 'I don\'t save it', NULL, 5, 31, '2025-01-09 09:10:00', ''),
(1290, '2025-01-16 07:19:07', 'USDJPYm', 156.057, 156.275, 155.429, 11.64, 'I don\'t save it', NULL, 5, 31, '2025-01-16 08:51:53', ''),
(1291, '2025-01-17 10:12:19', 'USDJPYm', 155.666, 155.86, 154.203, -31.12, 'I don\'t save it', NULL, 5, 31, '2025-01-17 14:21:05', ''),
(1292, '2025-01-17 12:43:09', 'GBPUSDm', 1.21839, 1.22171, 1.21005, 5.7, 'I don\'t save it', NULL, 5, 31, '2025-01-17 13:54:18', ''),
(1293, '2025-01-21 13:31:18', 'EURUSDm', 1.03661, 1.03565, 1.04213, 64, 'I don\'t save it', NULL, 5, 31, '2025-01-21 14:27:43', ''),
(1294, '2025-01-22 07:47:39', 'EURUSDm', 1.04133, 1.04141, 1.0446, 81.75, 'I don\'t save it', NULL, 5, 31, '2025-01-22 09:59:21', ''),
(1295, '2025-01-27 14:02:41', 'XAUUSDm', 2756.716, 2752.21, 2768.17, -45.06, 'I don\'t save it', NULL, 5, 31, '2025-01-27 14:16:45', ''),
(1296, '2025-01-28 09:48:39', 'EURUSDm', 1.04374, 1.04187, 1.05157, -37.4, 'I don\'t save it', NULL, 5, 31, '2025-01-28 12:03:18', ''),
(1297, '2025-01-30 07:50:08', 'USDJPYm', 154.588, 154.5, 153.69, 11.39, 'I don\'t save it', NULL, 5, 31, '2025-01-30 18:46:06', ''),
(1298, '2025-01-30 07:52:21', 'USDJPYm', 154.615, 154.5, 153.69, 7.44, 'I don\'t save it', NULL, 5, 31, '2025-01-30 18:46:06', ''),
(1299, '2025-01-30 08:04:49', 'USDJPYm', 154.525, 154.5, 153.69, 3.24, 'I don\'t save it', NULL, 5, 31, '2025-01-30 18:46:06', ''),
(1300, '2025-01-31 09:54:02', 'EURUSDm', 1.03793, 1.03897, 1.03427, 5.6, 'I don\'t save it', NULL, 5, 31, '2025-01-31 11:05:54', ''),
(1301, '2025-01-31 13:27:41', 'EURUSDm', 1.0387, 1.03864, 1.03429, 100.8, 'I don\'t save it', NULL, 5, 31, '2025-01-31 15:07:50', ''),
(1302, '2025-02-05 08:20:38', 'EURUSDm', 1.03968, 1.04004, 1.04218, 12.45, 'I don\'t save it', NULL, 5, 31, '2025-02-05 09:52:48', ''),
(1303, '2025-02-05 08:26:39', 'EURUSDm', 1.04, 1.04004, 1.04219, 7.65, 'I don\'t save it', NULL, 5, 31, '2025-02-05 09:52:48', ''),
(1304, '2025-02-10 06:36:09', 'GBPJPYm', 188.365, 188.672, 187.27, -40.4, 'I don\'t save it', NULL, 5, 31, '2025-02-10 07:06:03', ''),
(1305, '2025-02-10 06:48:29', 'EURJPYm', 156.637, 156.929, 155.73, -38.42, 'I don\'t save it', NULL, 5, 31, '2025-02-10 07:06:03', ''),
(1306, '2025-02-13 01:25:02', 'USDJPYm', 154.455, 154.12, 155.783, -43.47, 'I don\'t save it', NULL, 5, 31, '2025-02-13 06:07:43', ''),
(1307, '2025-02-13 01:56:00', 'USDJPYm', 154.257, 154.12, 155.783, -8.89, 'I don\'t save it', NULL, 5, 31, '2025-02-13 06:07:43', ''),
(1308, '2025-02-18 14:02:00', 'USDCAD', 1.41862, 1.41949, 1.41416, -61.29, 'knt dakhl f position ou 3awd conferma down move ou dkhlt 9bl newyourk kill zone swepani lmarche ou nchofo fin ikml \r\n<a href=\"https://www.tradingview.com/x/7IraIBeW\" target=\"_blank\">https://www.tradingview.com/x/7IraIBeW</a>/', '', 1, 20, '2025-02-18 14:13:00', ''),
(1309, '2025-02-19 14:12:00', 'NZDUSD', 0.57127, 0.57093, 0.57283, -68, 'tread kan mzyan jayb contecst mn day fvg + reacte elih zid eliha 4h fvg ou hta 1h fvg + breack away walakin confermet b 5 min matsnitch 15min \r\n<a href=\"https://www.tradingview.com/x/xoI1p6NS\" target=\"_blank\">https://www.tradingview.com/x/xoI1p6NS</a>/', '', 1, 20, '2025-02-19 14:35:00', ''),
(1315, '2025-02-21 14:00:00', 'NZDUSD', 0.5758, 0.57533, 0.5793, -54, 'saraha kolchi kan mzyan sl fabor mamchach l marche m3a narative dyali \r\n<a href=\"https://www.tradingview.com/x/kbU3g9bV\" target=\"_blank\">https://www.tradingview.com/x/kbU3g9bV</a>/', '', 1, 20, '2025-02-21 14:58:00', ''),
(1615, '2025-02-26 09:39:00', 'USDCHF', 0.89469, 0.89533, 0.89021, -54.94, 'saraha l bis dyali kan khata narative khata \r\n<a href=\"https://www.tradingview.com/x/NWC4gWlg\" target=\"_blank\">https://www.tradingview.com/x/NWC4gWlg</a>/', '', 1, 20, '2025-02-26 15:46:00', ''),
(1616, '2025-02-27 15:42:00', 'GBPUSD', 1.26602, 1.26512, 1.27163, -49, 'tmngila dyal confermation + new + zmla \r\n+ manb9ach nsm3 l hmzawi \r\nchat gpt 9adi gharad \r\n<a href=\"https://www.tradingview.com/x/qJ5HkNlR\" target=\"_blank\">https://www.tradingview.com/x/qJ5HkNlR</a>/', '', 1, 20, '2025-02-27 15:46:00', ''),
(1617, '2025-02-28 09:36:00', 'GBPAUD', 2.02613, 2.02386, 2.0288, 71.5, 'khrjt mn l position hit low proba anaha tdrb tp ou dakchi li tra kna aslan nhar jm3a \r\n<a href=\"https://www.tradingview.com/x/7a0aPqX6\" target=\"_blank\">https://www.tradingview.com/x/7a0aPqX6</a>/', '', 1, 20, '2025-02-28 16:39:00', ''),
(1757, '2025-03-03 09:27:00', 'AUDCHF', 0.56091, 0.56159, 0.55817, -84.63, 'trade can zwin khrjt b spred trade narative ou hta mn l entre can mzyana ghir sl was swepped ou haniya thna f worst proba <a href=\"https://www.tradingview.com/x/SqGw4E6Z\" target=\"_blank\">https://www.tradingview.com/x/SqGw4E6Z</a>/', '', 1, 20, '2025-03-03 14:08:00', ''),
(1758, '2025-03-04 15:07:00', 'AUDCAD', 0.89962, 0.90034, 0.89521, -113.24, 'bad trade idea ghir zhr ou mcha m3aya l prix drt ghalat hit can payout dak nhar kna l marche 3ta 1/2 ou mabghithach <a href=\"https://www.tradingview.com/x/953c8Gur\" target=\"_blank\">https://www.tradingview.com/x/953c8Gur</a>/', '', 1, 20, '2025-03-04 18:23:00', ''),
(1764, '2025-04-17 16:43:00', 'USDCAD', 1.38754, 1.38893, 1.38429, -380, 'bad tread idea i don\'t wait for midnight touch and 1h imbalence touch\r\n<a href=\"https://www.tradingview.com/x/MvF6DxC7\" target=\"_blank\">https://www.tradingview.com/x/MvF6DxC7</a>/ ', '', 1, 39, '2025-04-17 17:45:00', ''),
(1766, '2025-04-18 00:02:00', 'XAUUSD', 2512, 2510, 2530, 30, 'zsd', '', 1, 39, '2025-04-18 01:02:00', '');

-- --------------------------------------------------------

--
-- Structure de la table `login`
--

CREATE TABLE `login` (
  `id` int(11) NOT NULL,
  `fullname` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `Address` varchar(255) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `reset_token` varchar(255) DEFAULT NULL,
  `reset_token_expires` datetime DEFAULT NULL,
  `role` enum('user','admin') NOT NULL DEFAULT 'user',
  `login_typ` enum('basic','gold','diamond') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `login`
--

INSERT INTO `login` (`id`, `fullname`, `email`, `Address`, `phone`, `password`, `reset_token`, `reset_token_expires`, `role`, `login_typ`) VALUES
(1, 'saif', 'saif@gmail.com', 'rue 430 nur 29 cite des fonctioner agadir', '0649157151', '$2y$10$tbs5szp2Lby64UIKrs3aWOCgm.woOxKqDG0iqp767VyDgxfvKzK2O', NULL, NULL, 'user', 'diamond'),
(2, 'admin', 'admin@admin.com', NULL, NULL, '$2y$10$JPI37zoowD28f.8oR/eo8urVWbzTeGf2vu9yv2Uz1iLAtNR7qdQui', NULL, NULL, 'admin', 'basic'),
(5, 'steve', '99@gmail.com', 'rue 430 nur 29 cite des fonctioner agadir', '0649157151', '$2y$10$ma8sOPzondibSKX9dhaoGuZH1DOeYBkkv0HmQJyZCTNcMBKCM2ag6', NULL, NULL, 'user', 'basic'),
(6, 'steven', 'steven@steven.com', NULL, NULL, '$2y$10$ubtE0LOUtFauDdzU9nvLbOSQ77trjRxAyM.0lFpnSQKTzQbfIw8SS', NULL, NULL, 'user', 'basic');

-- --------------------------------------------------------

--
-- Structure de la table `messages`
--

CREATE TABLE `messages` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `mobile` varchar(15) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `messages`
--

INSERT INTO `messages` (`id`, `user_id`, `name`, `email`, `mobile`, `subject`, `message`, `created_at`) VALUES
(1, 1, 'Saif', 'hamza.emilie23@gmail.com', '0649157151', 'lihwak', 'afdsaf', '2024-12-18 08:36:27'),
(2, 1, 'Saif', 'hamza.emilie23@gmail.com', '0649157151', 'sada', 'asdasd', '2024-12-19 14:19:58'),
(3, 1, 'Saif', 'hamza.emilie23@gmail.com', '0649157151', 'lihwak', 'dadasd', '2024-12-19 14:22:01'),
(4, 1, 'Saif', 'hamza.emilie23@gmail.com', '0649157151', 'lihwak', 'dadasd', '2024-12-19 14:25:14'),
(5, 1, 'Saif', 'hamza.emilie23@gmail.com', '0649157151', 'asdas', 'sssss', '2024-12-19 14:25:41'),
(6, 1, 'Saif', 'hamza.emilie23@gmail.com', '0649157151', 'l9wada', 'wazaml jawb', '2024-12-19 14:29:31'),
(7, 1, 'Saif', 'hamza.emilie23@gmail.com', '0649157151', 'lihwak', 'wach a l3chir \r\n', '2025-01-13 19:15:59'),
(8, 1, 'Saif', 'hamza.emilie23@gmail.com', '0649157151', 'lihwak', 'said\r\n', '2025-02-08 01:40:25');

-- --------------------------------------------------------

--
-- Structure de la table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `message`, `created_at`) VALUES
(1, 1, 'wa fen', '2024-12-06 07:03:57'),
(2, 1, 'wa zaml', '2024-12-18 09:21:18'),
(3, 2, 'New message from Saif: lihwak', '2024-12-18 09:36:27'),
(4, 2, 'New message from Saif: sada', '2024-12-19 15:19:58'),
(5, 2, 'New message from Saif: lihwak', '2024-12-19 15:22:01'),
(6, 2, 'New message from Saif: lihwak', '2024-12-19 15:25:14'),
(7, 2, 'New message from Saif: asdas', '2024-12-19 15:25:42'),
(8, 2, 'New message from Saif: l9wada', '2024-12-19 15:29:31'),
(9, 5, 'wa zaml', '2025-01-11 13:24:34'),
(10, 5, 'zaml', '2025-01-11 13:25:07'),
(11, 2, 'New message from Saif: lihwak', '2025-01-13 20:16:00'),
(12, 1, 'wach a saif', '2025-01-13 20:18:18'),
(13, 2, 'New message from Saif: lihwak', '2025-02-08 02:40:25'),
(14, 1, 'ser t9awd ', '2025-02-08 02:42:26'),
(15, 1, 'nta zmlti m3 akrk', '2025-02-08 02:42:36');

-- --------------------------------------------------------

--
-- Structure de la table `parties`
--

CREATE TABLE `parties` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `code` varchar(10) NOT NULL,
  `created_by` int(10) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `joiner_ids` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `parties`
--

INSERT INTO `parties` (`id`, `name`, `code`, `created_by`, `created_at`, `joiner_ids`) VALUES
(1, 'general', '1111', 0, '2025-01-18 15:24:00', NULL),
(14, 'traders', '5475', 1, '2025-04-15 06:26:26', NULL);

-- --------------------------------------------------------

--
-- Structure de la table `party_members`
--

CREATE TABLE `party_members` (
  `id` int(11) NOT NULL,
  `party_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `joined_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `fullname` varchar(255) DEFAULT NULL,
  `capital` double DEFAULT NULL,
  `login_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `users`
--

INSERT INTO `users` (`id`, `fullname`, `capital`, `login_id`) VALUES
(20, 'account number 1', 5000, 1),
(31, 'oussama', 100, 5),
(39, 'steven', 10000, 1);

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `community_1111`
--
ALTER TABLE `community_1111`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `community_5475`
--
ALTER TABLE `community_5475`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `journal`
--
ALTER TABLE `journal`
  ADD PRIMARY KEY (`id`),
  ADD KEY `test` (`id_login`),
  ADD KEY `test2` (`id_users`);

--
-- Index pour la table `login`
--
ALTER TABLE `login`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `parties`
--
ALTER TABLE `parties`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Index pour la table `party_members`
--
ALTER TABLE `party_members`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD KEY `test3` (`login_id`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `community_1111`
--
ALTER TABLE `community_1111`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=98;

--
-- AUTO_INCREMENT pour la table `community_5475`
--
ALTER TABLE `community_5475`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT pour la table `journal`
--
ALTER TABLE `journal`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1779;

--
-- AUTO_INCREMENT pour la table `login`
--
ALTER TABLE `login`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT pour la table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT pour la table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT pour la table `parties`
--
ALTER TABLE `parties`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT pour la table `party_members`
--
ALTER TABLE `party_members`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=40;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `journal`
--
ALTER TABLE `journal`
  ADD CONSTRAINT `test` FOREIGN KEY (`id_login`) REFERENCES `login` (`id`),
  ADD CONSTRAINT `test2` FOREIGN KEY (`id_users`) REFERENCES `users` (`id`);

--
-- Contraintes pour la table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `test3` FOREIGN KEY (`login_id`) REFERENCES `login` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
