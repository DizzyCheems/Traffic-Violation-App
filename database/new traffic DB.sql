-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Sep 24, 2025 at 03:07 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.1.25

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `traffic`
--

-- --------------------------------------------------------

--
-- Table structure for table `appeals`
--

CREATE TABLE `appeals` (
  `id` int(11) NOT NULL,
  `violation_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `appeal_reason` text NOT NULL,
  `status` enum('PENDING','APPROVED','REJECTED') DEFAULT 'PENDING',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `appeals`
--

INSERT INTO `appeals` (`id`, `violation_id`, `user_id`, `appeal_reason`, `status`, `notes`, `created_at`, `updated_at`) VALUES
(1, 3, 1, 'I believe the traffic signal was malfunctioning.', 'PENDING', 'Under review by officer', '2025-08-21 01:00:00', '2025-08-21 01:00:00'),
(2, 4, 1, 'I had a helmet but it fell off during the ride.', 'APPROVED', 'Evidence shows no helmet at time of violation', '2025-08-26 03:00:00', '2025-09-13 13:41:39'),
(3, 3, 1, 'I believe the traffic signal was malfunctioning.', 'PENDING', 'Under review by officer', '2025-08-21 01:00:00', '2025-08-21 01:00:00'),
(4, 4, 2, 'I had a helmet but it fell off during the ride.', 'REJECTED', 'Evidence shows no helmet at time of violation', '2025-08-26 03:00:00', '2025-08-27 06:30:00'),
(5, 3, 1, 'Additional evidence for malfunctioning signal', 'APPROVED', 'Awaiting further review', '2025-08-22 02:00:00', '2025-09-01 06:39:23'),
(6, 3, 1, 'I believe the traffic signal was malfunctioning.', 'PENDING', 'Under review by officer', '2025-08-21 01:00:00', '2025-08-21 01:00:00'),
(7, 4, 2, 'I had a helmet but it fell off during the ride.', 'REJECTED', 'Evidence shows no helmet at time of violation', '2025-08-26 03:00:00', '2025-08-27 06:30:00'),
(8, 3, 1, 'Additional evidence for malfunctioning signal', 'PENDING', 'Awaiting further review', '2025-08-22 02:00:00', '2025-08-22 02:00:00'),
(9, 3, 1, 'I believe the traffic signal was malfunctioning.', 'PENDING', 'Under review by officer', '2025-08-21 01:00:00', '2025-08-21 01:00:00'),
(10, 4, 2, 'I had a helmet but it fell off during the ride.', 'REJECTED', 'Evidence shows no helmet at time of violation', '2025-08-26 03:00:00', '2025-08-27 06:30:00'),
(11, 3, 1, 'Additional evidence for malfunctioning signal', 'PENDING', 'Awaiting further review', '2025-08-22 02:00:00', '2025-08-22 02:00:00'),
(12, 3, 1, 'I believe the traffic signal was malfunctioning.', 'PENDING', 'Under review by officer', '2025-08-21 01:00:00', '2025-08-21 01:00:00'),
(13, 4, 2, 'I had a helmet but it fell off during the ride.', 'REJECTED', 'Evidence shows no helmet at time of violation', '2025-08-26 03:00:00', '2025-08-27 06:30:00'),
(14, 3, 1, 'Additional evidence for malfunctioning signal', 'PENDING', 'Awaiting further review', '2025-08-22 02:00:00', '2025-08-22 02:00:00'),
(15, 3, 1, 'I believe the traffic signal was malfunctioning.', 'PENDING', 'Under review by officer', '2025-08-21 01:00:00', '2025-08-21 01:00:00'),
(16, 4, 2, 'I had a helmet but it fell off during the ride.', 'REJECTED', 'Evidence shows no helmet at time of violation', '2025-08-26 03:00:00', '2025-08-27 06:30:00'),
(17, 3, 1, 'Additional evidence for malfunctioning signal', 'PENDING', 'Awaiting further review', '2025-08-22 02:00:00', '2025-08-22 02:00:00'),
(18, 3, 1, 'I believe the traffic signal was malfunctioning.', 'PENDING', 'Under review by officer', '2025-08-21 01:00:00', '2025-08-21 01:00:00'),
(19, 4, 2, 'I had a helmet but it fell off during the ride.', 'REJECTED', 'Evidence shows no helmet at time of violation', '2025-08-26 03:00:00', '2025-08-27 06:30:00'),
(20, 3, 1, 'Additional evidence for malfunctioning signal', 'PENDING', 'Awaiting further review', '2025-08-22 02:00:00', '2025-08-22 02:00:00'),
(21, 3, 1, 'I believe the traffic signal was malfunctioning.', 'PENDING', 'Under review by officer', '2025-08-21 01:00:00', '2025-08-21 01:00:00'),
(22, 4, 2, 'I had a helmet but it fell off during the ride.', 'REJECTED', 'Evidence shows no helmet at time of violation', '2025-08-26 03:00:00', '2025-08-27 06:30:00'),
(23, 3, 1, 'Additional evidence for malfunctioning signal', 'PENDING', 'Awaiting further review', '2025-08-22 02:00:00', '2025-08-22 02:00:00'),
(24, 3, 1, 'I believe the traffic signal was malfunctioning.', 'PENDING', 'Under review by officer', '2025-08-21 01:00:00', '2025-08-21 01:00:00'),
(25, 4, 2, 'I had a helmet but it fell off during the ride.', 'REJECTED', 'Evidence shows no helmet at time of violation', '2025-08-26 03:00:00', '2025-08-27 06:30:00'),
(26, 3, 1, 'Additional evidence for malfunctioning signal', 'PENDING', 'Awaiting further review', '2025-08-22 02:00:00', '2025-08-22 02:00:00'),
(27, 3, 1, 'I believe the traffic signal was malfunctioning.', 'PENDING', 'Under review by officer', '2025-08-21 01:00:00', '2025-08-21 01:00:00'),
(28, 4, 2, 'I had a helmet but it fell off during the ride.', 'REJECTED', 'Evidence shows no helmet at time of violation', '2025-08-26 03:00:00', '2025-08-27 06:30:00'),
(29, 3, 1, 'Additional evidence for malfunctioning signal', 'PENDING', 'Awaiting further review', '2025-08-22 02:00:00', '2025-08-22 02:00:00'),
(30, 3, 1, 'I believe the traffic signal was malfunctioning.', 'PENDING', 'Under review by officer', '2025-08-21 01:00:00', '2025-08-21 01:00:00'),
(31, 4, 2, 'I had a helmet but it fell off during the ride.', 'REJECTED', 'Evidence shows no helmet at time of violation', '2025-08-26 03:00:00', '2025-08-27 06:30:00'),
(32, 3, 1, 'Additional evidence for malfunctioning signal', 'PENDING', 'Awaiting further review', '2025-08-22 02:00:00', '2025-08-22 02:00:00'),
(33, 3, 1, 'I believe the traffic signal was malfunctioning.', 'PENDING', 'Under review by officer', '2025-08-21 01:00:00', '2025-08-21 01:00:00'),
(34, 4, 2, 'I had a helmet but it fell off during the ride.', 'REJECTED', 'Evidence shows no helmet at time of violation', '2025-08-26 03:00:00', '2025-08-27 06:30:00'),
(35, 3, 1, 'Additional evidence for malfunctioning signal', 'PENDING', 'Awaiting further review', '2025-08-22 02:00:00', '2025-08-22 02:00:00'),
(36, 3, 1, 'I believe the traffic signal was malfunctioning.', 'PENDING', 'Under review by officer', '2025-08-21 01:00:00', '2025-08-21 01:00:00'),
(37, 4, 2, 'I had a helmet but it fell off during the ride.', 'REJECTED', 'Evidence shows no helmet at time of violation', '2025-08-26 03:00:00', '2025-08-27 06:30:00'),
(38, 3, 1, 'Additional evidence for malfunctioning signal', 'PENDING', 'Awaiting further review', '2025-08-22 02:00:00', '2025-08-22 02:00:00'),
(39, 1, 4, 'BAKIT !', 'PENDING', NULL, '2025-09-20 06:53:02', '2025-09-20 06:53:02');

-- --------------------------------------------------------

--
-- Table structure for table `audit_log`
--

CREATE TABLE `audit_log` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action` varchar(255) NOT NULL,
  `details` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `audit_log`
--

INSERT INTO `audit_log` (`id`, `user_id`, `action`, `details`, `created_at`) VALUES
(1, 5, 'Issue Violation', 'Officer Lopez issued violation #1 to Juan Dela Cruz', '2025-08-01 06:00:00'),
(2, 7, 'Issue Violation', 'Officer Reyes issued violation #3 to Jose Mercado', '2025-08-20 08:30:00'),
(3, 1, 'Submit Appeal', 'Juan Dela Cruz submitted appeal for violation #3', '2025-08-21 01:00:00'),
(4, 1, 'Submit Appeal', 'Juan Dela Cruz submitted another appeal for violation #3', '2025-08-22 02:00:00'),
(5, 6, 'Reject Appeal', 'Admin Garcia rejected appeal for violation #4', '2025-08-27 06:30:00'),
(6, 2, 'Report Concern', 'Maria Santos reported a broken traffic light', '2025-08-15 02:30:00'),
(7, 5, 'Issue Violation', 'Officer Lopez issued violation #1 to Juan Dela Cruz', '2025-08-01 06:00:00'),
(8, 7, 'Issue Violation', 'Officer Reyes issued violation #3 to Jose Mercado', '2025-08-20 08:30:00'),
(9, 1, 'Submit Appeal', 'Juan Dela Cruz submitted appeal for violation #3', '2025-08-21 01:00:00'),
(10, 1, 'Submit Appeal', 'Juan Dela Cruz submitted another appeal for violation #3', '2025-08-22 02:00:00'),
(11, 6, 'Reject Appeal', 'Admin Garcia rejected appeal for violation #4', '2025-08-27 06:30:00'),
(12, 2, 'Report Concern', 'Maria Santos reported a broken traffic light', '2025-08-15 02:30:00'),
(13, 5, 'Issue Violation', 'Officer Lopez issued violation #1 to Juan Dela Cruz', '2025-08-01 06:00:00'),
(14, 7, 'Issue Violation', 'Officer Reyes issued violation #3 to Jose Mercado', '2025-08-20 08:30:00'),
(15, 1, 'Submit Appeal', 'Juan Dela Cruz submitted appeal for violation #3', '2025-08-21 01:00:00'),
(16, 1, 'Submit Appeal', 'Juan Dela Cruz submitted another appeal for violation #3', '2025-08-22 02:00:00'),
(17, 6, 'Reject Appeal', 'Admin Garcia rejected appeal for violation #4', '2025-08-27 06:30:00'),
(18, 2, 'Report Concern', 'Maria Santos reported a broken traffic light', '2025-08-15 02:30:00'),
(19, 5, 'Issue Violation', 'Officer Lopez issued violation #1 to Juan Dela Cruz', '2025-08-01 06:00:00'),
(20, 7, 'Issue Violation', 'Officer Reyes issued violation #3 to Jose Mercado', '2025-08-20 08:30:00'),
(21, 1, 'Submit Appeal', 'Juan Dela Cruz submitted appeal for violation #3', '2025-08-21 01:00:00'),
(22, 1, 'Submit Appeal', 'Juan Dela Cruz submitted another appeal for violation #3', '2025-08-22 02:00:00'),
(23, 6, 'Reject Appeal', 'Admin Garcia rejected appeal for violation #4', '2025-08-27 06:30:00'),
(24, 2, 'Report Concern', 'Maria Santos reported a broken traffic light', '2025-08-15 02:30:00'),
(25, 5, 'Issue Violation', 'Officer Lopez issued violation #1 to Juan Dela Cruz', '2025-08-01 06:00:00'),
(26, 7, 'Issue Violation', 'Officer Reyes issued violation #3 to Jose Mercado', '2025-08-20 08:30:00'),
(27, 1, 'Submit Appeal', 'Juan Dela Cruz submitted appeal for violation #3', '2025-08-21 01:00:00'),
(28, 1, 'Submit Appeal', 'Juan Dela Cruz submitted another appeal for violation #3', '2025-08-22 02:00:00'),
(29, 6, 'Reject Appeal', 'Admin Garcia rejected appeal for violation #4', '2025-08-27 06:30:00'),
(30, 2, 'Report Concern', 'Maria Santos reported a broken traffic light', '2025-08-15 02:30:00'),
(31, 5, 'Issue Violation', 'Officer Lopez issued violation #1 to Juan Dela Cruz', '2025-08-01 06:00:00'),
(32, 7, 'Issue Violation', 'Officer Reyes issued violation #3 to Jose Mercado', '2025-08-20 08:30:00'),
(33, 1, 'Submit Appeal', 'Juan Dela Cruz submitted appeal for violation #3', '2025-08-21 01:00:00'),
(34, 1, 'Submit Appeal', 'Juan Dela Cruz submitted another appeal for violation #3', '2025-08-22 02:00:00'),
(35, 6, 'Reject Appeal', 'Admin Garcia rejected appeal for violation #4', '2025-08-27 06:30:00'),
(36, 2, 'Report Concern', 'Maria Santos reported a broken traffic light', '2025-08-15 02:30:00'),
(37, 5, 'Issue Violation', 'Officer Lopez issued violation #1 to Juan Dela Cruz', '2025-08-01 06:00:00'),
(38, 7, 'Issue Violation', 'Officer Reyes issued violation #3 to Jose Mercado', '2025-08-20 08:30:00'),
(39, 1, 'Submit Appeal', 'Juan Dela Cruz submitted appeal for violation #3', '2025-08-21 01:00:00'),
(40, 1, 'Submit Appeal', 'Juan Dela Cruz submitted another appeal for violation #3', '2025-08-22 02:00:00'),
(41, 6, 'Reject Appeal', 'Admin Garcia rejected appeal for violation #4', '2025-08-27 06:30:00'),
(42, 2, 'Report Concern', 'Maria Santos reported a broken traffic light', '2025-08-15 02:30:00'),
(43, 5, 'Issue Violation', 'Officer Lopez issued violation #1 to Juan Dela Cruz', '2025-08-01 06:00:00'),
(44, 7, 'Issue Violation', 'Officer Reyes issued violation #3 to Jose Mercado', '2025-08-20 08:30:00'),
(45, 1, 'Submit Appeal', 'Juan Dela Cruz submitted appeal for violation #3', '2025-08-21 01:00:00'),
(46, 1, 'Submit Appeal', 'Juan Dela Cruz submitted another appeal for violation #3', '2025-08-22 02:00:00'),
(47, 6, 'Reject Appeal', 'Admin Garcia rejected appeal for violation #4', '2025-08-27 06:30:00'),
(48, 2, 'Report Concern', 'Maria Santos reported a broken traffic light', '2025-08-15 02:30:00'),
(49, 5, 'Issue Violation', 'Officer Lopez issued violation #1 to Juan Dela Cruz', '2025-08-01 06:00:00'),
(50, 7, 'Issue Violation', 'Officer Reyes issued violation #3 to Jose Mercado', '2025-08-20 08:30:00'),
(51, 1, 'Submit Appeal', 'Juan Dela Cruz submitted appeal for violation #3', '2025-08-21 01:00:00'),
(52, 1, 'Submit Appeal', 'Juan Dela Cruz submitted another appeal for violation #3', '2025-08-22 02:00:00'),
(53, 6, 'Reject Appeal', 'Admin Garcia rejected appeal for violation #4', '2025-08-27 06:30:00'),
(54, 2, 'Report Concern', 'Maria Santos reported a broken traffic light', '2025-08-15 02:30:00');

-- --------------------------------------------------------

--
-- Table structure for table `concerns`
--

CREATE TABLE `concerns` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL COMMENT 'Reference to users table',
  `description` text DEFAULT NULL COMMENT 'Concern details',
  `status` enum('OPEN','IN_PROGRESS','RESOLVED') NOT NULL DEFAULT 'OPEN',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `concerns`
--

INSERT INTO `concerns` (`id`, `user_id`, `description`, `status`, `created_at`) VALUES
(1, 1, 'Appeal for speeding violation', 'OPEN', '2025-08-31 05:28:02'),
(2, 2, 'Dispute over parking fine', 'OPEN', '2025-08-31 05:28:02'),
(3, 1, 'Pothole on Quezon Avenue near EDSA', 'OPEN', '2025-08-10 00:00:00'),
(4, 2, 'Broken traffic light at España Blvd', 'IN_PROGRESS', '2025-08-15 02:30:00'),
(5, 1, 'Streetlight outage in Makati CBD', 'RESOLVED', '2025-08-20 04:00:00'),
(6, 6, 'Illegal parking issues in BGC', 'OPEN', '2025-08-28 07:00:00'),
(7, 1, 'Pothole on Quezon Avenue near EDSA', 'OPEN', '2025-08-10 00:00:00'),
(8, 2, 'Broken traffic light at España Blvd', 'IN_PROGRESS', '2025-08-15 02:30:00'),
(9, 1, 'Pothole on Quezon Avenue near EDSA', 'OPEN', '2025-08-16 01:00:00'),
(10, 3, 'Streetlight outage in Makati CBD', 'RESOLVED', '2025-08-20 04:00:00'),
(11, 6, 'Illegal parking issues in BGC', 'OPEN', '2025-08-28 07:00:00'),
(12, 1, 'Pothole on Quezon Avenue near EDSA', 'OPEN', '2025-08-10 00:00:00'),
(13, 2, 'Broken traffic light at España Blvd', 'IN_PROGRESS', '2025-08-15 02:30:00'),
(14, 1, 'Pothole on Quezon Avenue near EDSA', 'OPEN', '2025-08-16 01:00:00'),
(15, 3, 'Streetlight outage in Makati CBD', 'RESOLVED', '2025-08-20 04:00:00'),
(16, 6, 'Illegal parking issues in BGC', 'OPEN', '2025-08-28 07:00:00'),
(17, 1, 'Pothole on Quezon Avenue near EDSA', 'OPEN', '2025-08-10 00:00:00'),
(18, 2, 'Broken traffic light at España Blvd', 'IN_PROGRESS', '2025-08-15 02:30:00'),
(19, 1, 'Pothole on Quezon Avenue near EDSA', 'OPEN', '2025-08-16 01:00:00'),
(20, 3, 'Streetlight outage in Makati CBD', 'RESOLVED', '2025-08-20 04:00:00'),
(21, 6, 'Illegal parking issues in BGC', 'OPEN', '2025-08-28 07:00:00'),
(22, 1, 'Pothole on Quezon Avenue near EDSA', 'OPEN', '2025-08-10 00:00:00'),
(23, 2, 'Broken traffic light at España Blvd', 'IN_PROGRESS', '2025-08-15 02:30:00'),
(24, 1, 'Pothole on Quezon Avenue near EDSA', 'OPEN', '2025-08-16 01:00:00'),
(25, 3, 'Streetlight outage in Makati CBD', 'RESOLVED', '2025-08-20 04:00:00'),
(26, 6, 'Illegal parking issues in BGC', 'OPEN', '2025-08-28 07:00:00'),
(27, 1, 'Pothole on Quezon Avenue near EDSA', 'OPEN', '2025-08-10 00:00:00'),
(28, 2, 'Broken traffic light at España Blvd', 'IN_PROGRESS', '2025-08-15 02:30:00'),
(29, 1, 'Pothole on Quezon Avenue near EDSA', 'OPEN', '2025-08-16 01:00:00'),
(30, 3, 'Streetlight outage in Makati CBD', 'RESOLVED', '2025-08-20 04:00:00'),
(31, 6, 'Illegal parking issues in BGC', 'OPEN', '2025-08-28 07:00:00'),
(32, 1, 'Pothole on Quezon Avenue near EDSA', 'OPEN', '2025-08-10 00:00:00'),
(33, 2, 'Broken traffic light at España Blvd', 'IN_PROGRESS', '2025-08-15 02:30:00'),
(34, 1, 'Pothole on Quezon Avenue near EDSA', 'OPEN', '2025-08-16 01:00:00'),
(35, 3, 'Streetlight outage in Makati CBD', 'RESOLVED', '2025-08-20 04:00:00'),
(36, 6, 'Illegal parking issues in BGC', 'OPEN', '2025-08-28 07:00:00'),
(37, 1, 'Pothole on Quezon Avenue near EDSA', 'OPEN', '2025-08-10 00:00:00'),
(38, 2, 'Broken traffic light at España Blvd', 'IN_PROGRESS', '2025-08-15 02:30:00'),
(39, 1, 'Pothole on Quezon Avenue near EDSA', 'OPEN', '2025-08-16 01:00:00'),
(40, 3, 'Streetlight outage in Makati CBD', 'RESOLVED', '2025-08-20 04:00:00'),
(41, 6, 'Illegal parking issues in BGC', 'OPEN', '2025-08-28 07:00:00'),
(42, 1, 'Pothole on Quezon Avenue near EDSA', 'OPEN', '2025-08-10 00:00:00'),
(43, 2, 'Broken traffic light at España Blvd', 'IN_PROGRESS', '2025-08-15 02:30:00'),
(44, 1, 'Pothole on Quezon Avenue near EDSA', 'OPEN', '2025-08-16 01:00:00'),
(45, 3, 'Streetlight outage in Makati CBD', 'RESOLVED', '2025-08-20 04:00:00'),
(46, 6, 'Illegal parking issues in BGC', 'OPEN', '2025-08-28 07:00:00'),
(47, 1, 'Pothole on Quezon Avenue near EDSA', 'OPEN', '2025-08-10 00:00:00'),
(48, 2, 'Broken traffic light at España Blvd', 'IN_PROGRESS', '2025-08-15 02:30:00'),
(49, 1, 'Pothole on Quezon Avenue near EDSA', 'OPEN', '2025-08-16 01:00:00'),
(50, 3, 'Streetlight outage in Makati CBD', 'RESOLVED', '2025-08-20 04:00:00'),
(51, 6, 'Illegal parking issues in BGC', 'OPEN', '2025-08-28 07:00:00'),
(52, 1, 'Pothole on Quezon Avenue near EDSA', 'OPEN', '2025-08-10 00:00:00'),
(53, 2, 'Broken traffic light at España Blvd', 'IN_PROGRESS', '2025-08-15 02:30:00'),
(54, 1, 'Pothole on Quezon Avenue near EDSA', 'OPEN', '2025-08-16 01:00:00'),
(55, 3, 'Streetlight outage in Makati CBD', 'RESOLVED', '2025-08-20 04:00:00'),
(56, 6, 'Illegal parking issues in BGC', 'OPEN', '2025-08-28 07:00:00'),
(57, 1, 'Pothole on Quezon Avenue near EDSA', 'OPEN', '2025-08-10 00:00:00'),
(58, 2, 'Broken traffic light at España Blvd', 'IN_PROGRESS', '2025-08-15 02:30:00'),
(59, 1, 'Pothole on Quezon Avenue near EDSA', 'OPEN', '2025-08-16 01:00:00'),
(60, 3, 'Streetlight outage in Makati CBD', 'RESOLVED', '2025-08-20 04:00:00'),
(61, 6, 'Illegal parking issues in BGC', 'OPEN', '2025-08-28 07:00:00'),
(62, 1, 'Pothole on Quezon Avenue near EDSA', 'OPEN', '2025-08-10 00:00:00'),
(63, 2, 'Broken traffic light at España Blvd', 'IN_PROGRESS', '2025-08-15 02:30:00'),
(64, 1, 'Pothole on Quezon Avenue near EDSA', 'OPEN', '2025-08-16 01:00:00'),
(65, 3, 'Streetlight outage in Makati CBD', 'RESOLVED', '2025-08-20 04:00:00'),
(66, 6, 'Illegal parking issues in BGC', 'OPEN', '2025-08-28 07:00:00'),
(67, 4, 'YYA', 'OPEN', '2025-09-20 06:38:56'),
(68, 4, 'Appeal for Violation #25: asd', '', '2025-09-20 06:46:00'),
(69, 4, 'Appeal for Violation #25: Huh bakit?', '', '2025-09-20 06:49:14');

-- --------------------------------------------------------

--
-- Table structure for table `holiday_rules`
--

CREATE TABLE `holiday_rules` (
  `id` int(11) NOT NULL,
  `holiday_name` varchar(100) NOT NULL,
  `date` date NOT NULL,
  `fine_multiplier` decimal(4,2) DEFAULT 1.00,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `holiday_rules`
--

INSERT INTO `holiday_rules` (`id`, `holiday_name`, `date`, `fine_multiplier`, `description`) VALUES
(1, 'Legal Holiday', '2025-09-01', 1.00, 'W'),
(2, 'Christmas Day', '2025-12-25', 1.50, 'Increased fines due to holiday traffic'),
(3, 'New Year\'s Day', '2026-01-01', 1.50, 'Higher fines for New Year violations'),
(4, 'All Saints\' Day', '2025-11-01', 1.25, 'Moderate fine increase for holiday'),
(5, 'Christmas Day', '2025-12-25', 1.50, 'Increased fines due to holiday traffic'),
(6, 'Christmas Day', '2025-12-25', 1.75, 'Special enforcement zone'),
(7, 'New Year\'s Day', '2026-01-01', 1.50, 'Higher fines for New Year violations'),
(8, 'All Saints\' Day', '2025-11-01', 1.25, 'Moderate fine increase for holiday'),
(9, 'Christmas Day', '2025-12-25', 1.50, 'Increased fines due to holiday traffic'),
(10, 'Christmas Day', '2025-12-25', 1.75, 'Special enforcement zone'),
(11, 'New Year\'s Day', '2026-01-01', 1.50, 'Higher fines for New Year violations'),
(12, 'All Saints\' Day', '2025-11-01', 1.25, 'Moderate fine increase for holiday'),
(13, 'Christmas Day', '2025-12-25', 1.50, 'Increased fines due to holiday traffic'),
(14, 'Christmas Day', '2025-12-25', 1.75, 'Special enforcement zone'),
(15, 'New Year\'s Day', '2026-01-01', 1.50, 'Higher fines for New Year violations'),
(16, 'All Saints\' Day', '2025-11-01', 1.25, 'Moderate fine increase for holiday'),
(17, 'Christmas Day', '2025-12-25', 1.50, 'Increased fines due to holiday traffic'),
(18, 'Christmas Day', '2025-12-25', 1.75, 'Special enforcement zone'),
(19, 'New Year\'s Day', '2026-01-01', 1.50, 'Higher fines for New Year violations'),
(20, 'All Saints\' Day', '2025-11-01', 1.25, 'Moderate fine increase for holiday'),
(21, 'Christmas Day', '2025-12-25', 1.50, 'Increased fines due to holiday traffic'),
(22, 'Christmas Day', '2025-12-25', 1.75, 'Special enforcement zone'),
(23, 'New Year\'s Day', '2026-01-01', 1.50, 'Higher fines for New Year violations'),
(24, 'All Saints\' Day', '2025-11-01', 1.25, 'Moderate fine increase for holiday'),
(25, 'Christmas Day', '2025-12-25', 1.50, 'Increased fines due to holiday traffic'),
(26, 'Christmas Day', '2025-12-25', 1.75, 'Special enforcement zone'),
(27, 'New Year\'s Day', '2026-01-01', 1.50, 'Higher fines for New Year violations'),
(28, 'All Saints\' Day', '2025-11-01', 1.25, 'Moderate fine increase for holiday'),
(29, 'Christmas Day', '2025-12-25', 1.50, 'Increased fines due to holiday traffic'),
(30, 'Christmas Day', '2025-12-25', 1.75, 'Special enforcement zone'),
(31, 'New Year\'s Day', '2026-01-01', 1.50, 'Higher fines for New Year violations'),
(32, 'All Saints\' Day', '2025-11-01', 1.25, 'Moderate fine increase for holiday'),
(33, 'Christmas Day', '2025-12-25', 1.50, 'Increased fines due to holiday traffic'),
(34, 'Christmas Day', '2025-12-25', 1.75, 'Special enforcement zone'),
(35, 'New Year\'s Day', '2026-01-01', 1.50, 'Higher fines for New Year violations'),
(36, 'All Saints\' Day', '2025-11-01', 1.25, 'Moderate fine increase for holiday'),
(37, 'Christmas Day', '2025-12-25', 1.50, 'Increased fines due to holiday traffic'),
(38, 'Christmas Day', '2025-12-25', 1.75, 'Special enforcement zone'),
(39, 'New Year\'s Day', '2026-01-01', 1.50, 'Higher fines for New Year violations'),
(40, 'All Saints\' Day', '2025-11-01', 1.25, 'Moderate fine increase for holiday'),
(41, 'Christmas Day', '2025-12-25', 1.50, 'Increased fines due to holiday traffic'),
(42, 'Christmas Day', '2025-12-25', 1.75, 'Special enforcement zone'),
(43, 'New Year\'s Day', '2026-01-01', 1.50, 'Higher fines for New Year violations'),
(44, 'All Saints\' Day', '2025-11-01', 1.25, 'Moderate fine increase for holiday'),
(45, 'Christmas Day', '2025-12-25', 1.50, 'Increased fines due to holiday traffic'),
(46, 'Christmas Day', '2025-12-25', 1.75, 'Special enforcement zone'),
(47, 'New Year\'s Day', '2026-01-01', 1.50, 'Higher fines for New Year violations'),
(48, 'All Saints\' Day', '2025-11-01', 1.25, 'Moderate fine increase for holiday'),
(49, 'Christmas Day', '2025-12-25', 1.50, 'Increased fines due to holiday traffic'),
(50, 'Christmas Day', '2025-12-25', 1.75, 'Special enforcement zone'),
(51, 'New Year\'s Day', '2026-01-01', 1.50, 'Higher fines for New Year violations'),
(52, 'All Saints\' Day', '2025-11-01', 1.25, 'Moderate fine increase for holiday');

-- --------------------------------------------------------

--
-- Table structure for table `license_status`
--

CREATE TABLE `license_status` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `license_number` varchar(50) DEFAULT NULL,
  `status` enum('VALID','SUSPENDED','EXPIRED') DEFAULT 'VALID',
  `expiry_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `license_status`
--

INSERT INTO `license_status` (`id`, `user_id`, `license_number`, `status`, `expiry_date`, `created_at`) VALUES
(1, 1, 'L123456', 'VALID', '2026-06-30', '2024-01-01 00:00:00'),
(2, 2, 'L789012', 'SUSPENDED', '2025-12-31', '2024-02-15 01:30:00'),
(3, 6, 'L345678', 'VALID', '2027-03-15', '2024-06-01 05:45:00'),
(4, 1, 'L123456', 'VALID', '2026-06-30', '2024-01-01 00:00:00'),
(5, 2, 'L789012', 'SUSPENDED', '2025-12-31', '2024-02-15 01:30:00'),
(6, 3, 'L789012', 'SUSPENDED', '2025-12-31', '2024-02-16 02:00:00'),
(7, 6, 'L345678', 'VALID', '2027-03-15', '2024-06-01 05:45:00'),
(8, 1, 'L123456', 'VALID', '2026-06-30', '2024-01-01 00:00:00'),
(9, 2, 'L789012', 'SUSPENDED', '2025-12-31', '2024-02-15 01:30:00'),
(10, 3, 'L789012', 'SUSPENDED', '2025-12-31', '2024-02-16 02:00:00'),
(11, 6, 'L345678', 'VALID', '2027-03-15', '2024-06-01 05:45:00'),
(12, 1, 'L123456', 'VALID', '2026-06-30', '2024-01-01 00:00:00'),
(13, 2, 'L789012', 'SUSPENDED', '2025-12-31', '2024-02-15 01:30:00'),
(14, 3, 'L789012', 'SUSPENDED', '2025-12-31', '2024-02-16 02:00:00'),
(15, 6, 'L345678', 'VALID', '2027-03-15', '2024-06-01 05:45:00'),
(16, 1, 'L123456', 'VALID', '2026-06-30', '2024-01-01 00:00:00'),
(17, 2, 'L789012', 'SUSPENDED', '2025-12-31', '2024-02-15 01:30:00'),
(18, 3, 'L789012', 'SUSPENDED', '2025-12-31', '2024-02-16 02:00:00'),
(19, 6, 'L345678', 'VALID', '2027-03-15', '2024-06-01 05:45:00'),
(20, 1, 'L123456', 'VALID', '2026-06-30', '2024-01-01 00:00:00'),
(21, 2, 'L789012', 'SUSPENDED', '2025-12-31', '2024-02-15 01:30:00'),
(22, 3, 'L789012', 'SUSPENDED', '2025-12-31', '2024-02-16 02:00:00'),
(23, 6, 'L345678', 'VALID', '2027-03-15', '2024-06-01 05:45:00'),
(24, 1, 'L123456', 'VALID', '2026-06-30', '2024-01-01 00:00:00'),
(25, 2, 'L789012', 'SUSPENDED', '2025-12-31', '2024-02-15 01:30:00'),
(26, 3, 'L789012', 'SUSPENDED', '2025-12-31', '2024-02-16 02:00:00'),
(27, 6, 'L345678', 'VALID', '2027-03-15', '2024-06-01 05:45:00'),
(28, 1, 'L123456', 'VALID', '2026-06-30', '2024-01-01 00:00:00'),
(29, 2, 'L789012', 'SUSPENDED', '2025-12-31', '2024-02-15 01:30:00'),
(30, 3, 'L789012', 'SUSPENDED', '2025-12-31', '2024-02-16 02:00:00'),
(31, 6, 'L345678', 'VALID', '2027-03-15', '2024-06-01 05:45:00'),
(32, 1, 'L123456', 'VALID', '2026-06-30', '2024-01-01 00:00:00'),
(33, 2, 'L789012', 'SUSPENDED', '2025-12-31', '2024-02-15 01:30:00'),
(34, 3, 'L789012', 'SUSPENDED', '2025-12-31', '2024-02-16 02:00:00'),
(35, 6, 'L345678', 'VALID', '2027-03-15', '2024-06-01 05:45:00'),
(36, 1, 'L123456', 'VALID', '2026-06-30', '2024-01-01 00:00:00'),
(37, 2, 'L789012', 'SUSPENDED', '2025-12-31', '2024-02-15 01:30:00'),
(38, 3, 'L789012', 'SUSPENDED', '2025-12-31', '2024-02-16 02:00:00'),
(39, 6, 'L345678', 'VALID', '2027-03-15', '2024-06-01 05:45:00'),
(40, 1, 'L123456', 'VALID', '2026-06-30', '2024-01-01 00:00:00'),
(41, 2, 'L789012', 'SUSPENDED', '2025-12-31', '2024-02-15 01:30:00'),
(42, 3, 'L789012', 'SUSPENDED', '2025-12-31', '2024-02-16 02:00:00'),
(43, 6, 'L345678', 'VALID', '2027-03-15', '2024-06-01 05:45:00'),
(44, 1, 'L123456', 'VALID', '2026-06-30', '2024-01-01 00:00:00'),
(45, 2, 'L789012', 'SUSPENDED', '2025-12-31', '2024-02-15 01:30:00'),
(46, 3, 'L789012', 'SUSPENDED', '2025-12-31', '2024-02-16 02:00:00'),
(47, 6, 'L345678', 'VALID', '2027-03-15', '2024-06-01 05:45:00'),
(48, 1, 'L123456', 'VALID', '2026-06-30', '2024-01-01 00:00:00'),
(49, 2, 'L789012', 'SUSPENDED', '2025-12-31', '2024-02-15 01:30:00'),
(50, 3, 'L789012', 'SUSPENDED', '2025-12-31', '2024-02-16 02:00:00'),
(51, 6, 'L345678', 'VALID', '2027-03-15', '2024-06-01 05:45:00');

-- --------------------------------------------------------

--
-- Table structure for table `officer_earnings`
--

CREATE TABLE `officer_earnings` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `month_year` varchar(7) NOT NULL COMMENT 'Format: YYYY-MM',
  `total_fines` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Total fines collected by officer',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `officer_earnings`
--

INSERT INTO `officer_earnings` (`id`, `user_id`, `month_year`, `total_fines`, `created_at`) VALUES
(1, 5, '2025-08', 4500.00, '2025-08-24 16:00:00'),
(2, 7, '2025-08', 8000.00, '2025-08-24 16:00:00'),
(3, 5, '2025-08', 2000.00, '2025-08-24 16:00:00'),
(4, 5, '2025-07', 3000.00, '2025-08-17 16:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `officer_status`
--

CREATE TABLE `officer_status` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL COMMENT 'Reference to users table',
  `status` enum('ONLINE','OFFLINE') NOT NULL DEFAULT 'OFFLINE',
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `officer_status`
--

INSERT INTO `officer_status` (`id`, `user_id`, `status`, `last_updated`) VALUES
(1, 1, 'ONLINE', '2025-08-31 05:28:02'),
(2, 2, 'ONLINE', '2025-08-31 05:28:02'),
(3, 3, 'OFFLINE', '2025-08-31 05:28:02'),
(4, 4, 'ONLINE', '2025-08-31 05:28:02'),
(5, 5, 'OFFLINE', '2025-08-31 05:28:02'),
(6, 5, 'ONLINE', '2025-09-01 00:00:00'),
(7, 7, 'OFFLINE', '2025-08-31 23:30:00'),
(8, 6, 'ONLINE', '2025-09-01 01:00:00'),
(9, 5, 'ONLINE', '2025-09-01 02:00:00'),
(10, 5, 'ONLINE', '2025-09-01 00:00:00'),
(11, 7, 'OFFLINE', '2025-08-31 23:30:00'),
(12, 6, 'ONLINE', '2025-09-01 01:00:00'),
(13, 5, 'ONLINE', '2025-09-01 02:00:00'),
(14, 5, 'ONLINE', '2025-09-01 00:00:00'),
(15, 7, 'OFFLINE', '2025-08-31 23:30:00'),
(16, 6, 'ONLINE', '2025-09-01 01:00:00'),
(17, 5, 'ONLINE', '2025-09-01 02:00:00'),
(18, 5, 'ONLINE', '2025-09-01 00:00:00'),
(19, 7, 'OFFLINE', '2025-08-31 23:30:00'),
(20, 6, 'ONLINE', '2025-09-01 01:00:00'),
(21, 5, 'ONLINE', '2025-09-01 02:00:00'),
(22, 5, 'ONLINE', '2025-09-01 00:00:00'),
(23, 7, 'OFFLINE', '2025-08-31 23:30:00'),
(24, 6, 'ONLINE', '2025-09-01 01:00:00'),
(25, 5, 'ONLINE', '2025-09-01 02:00:00'),
(26, 5, 'ONLINE', '2025-09-01 00:00:00'),
(27, 7, 'OFFLINE', '2025-08-31 23:30:00'),
(28, 6, 'ONLINE', '2025-09-01 01:00:00'),
(29, 5, 'ONLINE', '2025-09-01 02:00:00'),
(30, 5, 'ONLINE', '2025-09-01 00:00:00'),
(31, 7, 'OFFLINE', '2025-08-31 23:30:00'),
(32, 6, 'ONLINE', '2025-09-01 01:00:00'),
(33, 5, 'ONLINE', '2025-09-01 02:00:00'),
(34, 5, 'ONLINE', '2025-09-01 00:00:00'),
(35, 7, 'OFFLINE', '2025-08-31 23:30:00'),
(36, 6, 'ONLINE', '2025-09-01 01:00:00'),
(37, 5, 'ONLINE', '2025-09-01 02:00:00'),
(38, 5, 'ONLINE', '2025-09-01 00:00:00'),
(39, 7, 'OFFLINE', '2025-08-31 23:30:00'),
(40, 6, 'ONLINE', '2025-09-01 01:00:00'),
(41, 5, 'ONLINE', '2025-09-01 02:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `patrol_zones`
--

CREATE TABLE `patrol_zones` (
  `id` int(11) NOT NULL,
  `officer_id` int(11) NOT NULL,
  `zone_name` varchar(255) NOT NULL,
  `coordinates` text DEFAULT NULL,
  `hotspots` text DEFAULT NULL,
  `urgency` enum('Low','Medium','High') NOT NULL DEFAULT 'Low',
  `assigned_date` datetime NOT NULL,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `patrol_zones`
--

INSERT INTO `patrol_zones` (`id`, `officer_id`, `zone_name`, `coordinates`, `hotspots`, `urgency`, `assigned_date`, `created_at`) VALUES
(1, 5, 'Downtown Central', '[{\"lat\": 40.7128, \"lng\": -74.0060}, {\"lat\": 40.7120, \"lng\": -74.0050}, {\"lat\": 40.7115, \"lng\": -74.0070}, {\"lat\": 40.7130, \"lng\": -74.0080}]', '[{\"lat\": 40.7125, \"lng\": -74.0065, \"desc\": \"High speeding area near Main St intersection\"}, {\"lat\": 40.7118, \"lng\": -74.0055, \"desc\": \"Frequent illegal parking near 5th Ave\"}]', 'High', '2025-09-01 08:00:00', '2025-09-01 18:16:00'),
(2, 5, 'Test', 'Lat: 40.7128, Lng: -74.0060', 'Lat: 40.7125, Lng: -74.0065', 'High', '2025-09-01 12:14:00', '2025-09-01 12:19:53');

-- --------------------------------------------------------

--
-- Table structure for table `revenue_metrics`
--

CREATE TABLE `revenue_metrics` (
  `id` int(11) NOT NULL,
  `month_year` varchar(7) NOT NULL COMMENT 'Format: YYYY-MM',
  `total_revenue` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Monthly total revenue',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `revenue_metrics`
--

INSERT INTO `revenue_metrics` (`id`, `month_year`, `total_revenue`, `created_at`) VALUES
(1, '2025-08', 85240.00, '2025-08-31 05:28:02'),
(2, '2025-08', 10500.00, '2025-08-31 15:59:59'),
(3, '2025-08', 12000.00, '2025-08-31 15:59:59'),
(4, '2025-07', 8200.00, '2025-07-31 15:59:59'),
(5, '2025-06', 6500.00, '2025-06-30 15:59:59'),
(6, '2025-08', 10500.00, '2025-08-31 15:59:59'),
(7, '2025-08', 12000.00, '2025-08-31 15:59:59'),
(8, '2025-07', 8200.00, '2025-07-31 15:59:59'),
(9, '2025-06', 6500.00, '2025-06-30 15:59:59'),
(10, '2025-08', 10500.00, '2025-08-31 15:59:59'),
(11, '2025-08', 12000.00, '2025-08-31 15:59:59'),
(12, '2025-07', 8200.00, '2025-07-31 15:59:59'),
(13, '2025-06', 6500.00, '2025-06-30 15:59:59'),
(14, '2025-08', 10500.00, '2025-08-31 15:59:59'),
(15, '2025-08', 12000.00, '2025-08-31 15:59:59'),
(16, '2025-07', 8200.00, '2025-07-31 15:59:59'),
(17, '2025-06', 6500.00, '2025-06-30 15:59:59'),
(18, '2025-08', 10500.00, '2025-08-31 15:59:59'),
(19, '2025-08', 12000.00, '2025-08-31 15:59:59'),
(20, '2025-07', 8200.00, '2025-07-31 15:59:59'),
(21, '2025-06', 6500.00, '2025-06-30 15:59:59'),
(22, '2025-08', 10500.00, '2025-08-31 15:59:59'),
(23, '2025-08', 12000.00, '2025-08-31 15:59:59'),
(24, '2025-07', 8200.00, '2025-07-31 15:59:59'),
(25, '2025-06', 6500.00, '2025-06-30 15:59:59'),
(26, '2025-08', 10500.00, '2025-08-31 15:59:59'),
(27, '2025-08', 12000.00, '2025-08-31 15:59:59'),
(28, '2025-07', 8200.00, '2025-07-31 15:59:59'),
(29, '2025-06', 6500.00, '2025-06-30 15:59:59'),
(30, '2025-08', 10500.00, '2025-08-31 15:59:59'),
(31, '2025-08', 12000.00, '2025-08-31 15:59:59'),
(32, '2025-07', 8200.00, '2025-07-31 15:59:59'),
(33, '2025-06', 6500.00, '2025-06-30 15:59:59'),
(34, '2025-08', 10500.00, '2025-08-31 15:59:59'),
(35, '2025-08', 12000.00, '2025-08-31 15:59:59'),
(36, '2025-07', 8200.00, '2025-07-31 15:59:59'),
(37, '2025-06', 6500.00, '2025-06-30 15:59:59');

-- --------------------------------------------------------

--
-- Table structure for table `system_health`
--

CREATE TABLE `system_health` (
  `id` int(11) NOT NULL,
  `uptime` decimal(5,2) NOT NULL DEFAULT 0.00 COMMENT 'System uptime percentage',
  `api_status` enum('OK','DOWN','MAINTENANCE') NOT NULL DEFAULT 'OK' COMMENT 'API status',
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `system_health`
--

INSERT INTO `system_health` (`id`, `uptime`, `api_status`, `last_updated`) VALUES
(1, 99.99, 'OK', '2025-08-31 05:28:02'),
(2, 99.95, 'OK', '2025-09-01 04:00:00'),
(3, 99.95, 'OK', '2025-09-01 05:45:00'),
(4, 99.90, 'MAINTENANCE', '2025-08-30 22:00:00'),
(5, 99.85, 'OK', '2025-08-30 04:00:00'),
(6, 99.95, 'OK', '2025-09-01 04:00:00'),
(7, 99.95, 'OK', '2025-09-01 05:45:00'),
(8, 99.90, 'MAINTENANCE', '2025-08-30 22:00:00'),
(9, 99.85, 'OK', '2025-08-30 04:00:00'),
(10, 99.95, 'OK', '2025-09-01 04:00:00'),
(11, 99.95, 'OK', '2025-09-01 05:45:00'),
(12, 99.90, 'MAINTENANCE', '2025-08-30 22:00:00'),
(13, 99.85, 'OK', '2025-08-30 04:00:00'),
(14, 99.95, 'OK', '2025-09-01 04:00:00'),
(15, 99.95, 'OK', '2025-09-01 05:45:00'),
(16, 99.90, 'MAINTENANCE', '2025-08-30 22:00:00'),
(17, 99.85, 'OK', '2025-08-30 04:00:00'),
(18, 99.95, 'OK', '2025-09-01 04:00:00'),
(19, 99.95, 'OK', '2025-09-01 05:45:00'),
(20, 99.90, 'MAINTENANCE', '2025-08-30 22:00:00'),
(21, 99.85, 'OK', '2025-08-30 04:00:00'),
(22, 99.95, 'OK', '2025-09-01 04:00:00'),
(23, 99.95, 'OK', '2025-09-01 05:45:00'),
(24, 99.90, 'MAINTENANCE', '2025-08-30 22:00:00'),
(25, 99.85, 'OK', '2025-08-30 04:00:00'),
(26, 99.95, 'OK', '2025-09-01 04:00:00'),
(27, 99.95, 'OK', '2025-09-01 05:45:00'),
(28, 99.90, 'MAINTENANCE', '2025-08-30 22:00:00'),
(29, 99.85, 'OK', '2025-08-30 04:00:00'),
(30, 99.95, 'OK', '2025-09-01 04:00:00'),
(31, 99.95, 'OK', '2025-09-01 05:45:00'),
(32, 99.90, 'MAINTENANCE', '2025-08-30 22:00:00'),
(33, 99.85, 'OK', '2025-08-30 04:00:00'),
(34, 99.95, 'OK', '2025-09-01 04:00:00'),
(35, 99.95, 'OK', '2025-09-01 05:45:00'),
(36, 99.90, 'MAINTENANCE', '2025-08-30 22:00:00'),
(37, 99.85, 'OK', '2025-08-30 04:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `types`
--

CREATE TABLE `types` (
  `id` int(11) NOT NULL,
  `violation_type` varchar(100) NOT NULL,
  `fine_amount` decimal(10,2) NOT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `types`
--

INSERT INTO `types` (`id`, `violation_type`, `fine_amount`, `description`) VALUES
(13, 'Speeding', 2000.00, 'Exceeding speed limit in a residential area'),
(14, 'Illegal Parking', 1000.00, 'Parking in a no-parking zone'),
(17, 'Drunk Driving', 5000.00, 'Driving under the influence of alcohol'),
(115, 'No Helmet', 1000.00, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `role` enum('officer','admin','user') DEFAULT 'officer',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `officer_id` text DEFAULT NULL,
  `email` text DEFAULT NULL,
  `contact_number` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `full_name`, `role`, `created_at`, `officer_id`, `email`, `contact_number`) VALUES
(1, 'Admin', 'admin', 'Admin', 'admin', '2025-08-30 10:38:10', NULL, NULL, NULL),
(4, 'User', 'user', 'User', 'user', '2025-08-30 10:38:10', NULL, NULL, NULL),
(5, 'Officer', 'officer', 'officer', 'officer', '2025-08-30 10:38:10', NULL, NULL, NULL),
(27, 'juan_dela_cruz', '123', 'Juan Dela Cruz', 'user', '2024-01-01 00:00:00', '5', NULL, NULL),
(28, 'maria_santos', '123', 'Maria Santos', 'user', '2024-02-15 01:30:00', NULL, NULL, NULL),
(29, 'officer_lopez', '123', 'Pedro Lopez', 'officer', '2024-03-10 02:00:00', NULL, NULL, NULL),
(30, 'admin_garcia', '123', 'Ana Garcia', 'admin', '2024-04-05 03:15:00', NULL, NULL, NULL),
(31, 'officer_reyes', '123', 'Carlos Reyes', 'officer', '2024-05-20 04:00:00', NULL, NULL, NULL),
(32, 'jose_mercado', '123', 'Jose Mercado', 'user', '2024-06-01 05:45:00', NULL, NULL, NULL),
(144, 'rthtj', '123', 'tyjhk', 'user', '2025-09-14 04:12:43', NULL, NULL, NULL),
(146, 'sfdsdf', '123', 'sdfsdsfwef', 'officer', '2025-09-14 04:23:37', NULL, NULL, NULL),
(151, 'wefwdfsd', '123', 'sdfsdf', 'user', '2025-09-14 04:32:06', NULL, NULL, NULL),
(153, 'fwef', 'x', 'fwef', 'user', '2025-09-20 06:09:16', '5', NULL, NULL),
(154, 'YYEBH', 'x', 'YYEBH', 'user', '2025-09-20 06:17:27', '5', NULL, NULL),
(156, 'dfgdfgerger', 'x', 'dfgdfgerger', 'user', '2025-09-20 07:45:09', '5', NULL, NULL),
(157, 'sdfsd', 'x', 'sdfsd', 'user', '2025-09-20 07:54:22', '5', NULL, NULL),
(158, 'dfgfd', 'x', 'dfgfd', 'user', '2025-09-20 08:56:10', '5', NULL, NULL),
(159, 'sdfe', 'x', 'sdfe', 'user', '2025-09-20 09:24:48', '5', 'rgrg@sdfg.com', 0),
(160, 'fef', 'x', 'fef', 'user', '2025-09-20 09:25:24', '5', NULL, 980879978);

-- --------------------------------------------------------

--
-- Table structure for table `violations`
--

CREATE TABLE `violations` (
  `id` int(11) NOT NULL,
  `violator_name` varchar(255) NOT NULL,
  `plate_number` varchar(50) NOT NULL,
  `remarks` text DEFAULT NULL,
  `reason` varchar(255) NOT NULL,
  `violation_type_id` varchar(50) NOT NULL,
  `user_id` varchar(50) NOT NULL,
  `issue_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `has_license` tinyint(1) NOT NULL DEFAULT 0,
  `license_number` varchar(50) DEFAULT NULL,
  `is_impounded` tinyint(1) DEFAULT 0,
  `is_paid` tinyint(1) DEFAULT 0,
  `or_number` varchar(50) DEFAULT NULL,
  `issued_date` datetime DEFAULT NULL,
  `status` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `officer_id` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `violations`
--

INSERT INTO `violations` (`id`, `violator_name`, `plate_number`, `remarks`, `reason`, `violation_type_id`, `user_id`, `issue_date`, `has_license`, `license_number`, `is_impounded`, `is_paid`, `or_number`, `issued_date`, `status`, `notes`, `officer_id`) VALUES
(1, 'fs', '95XD', NULL, 'BAWAL TAE!', '13', '4', '2025-08-31 03:41:12', 0, '1WT5224', 1, 1, 'OR1758351697', '2025-08-31 05:40:00', 'Pending', 'GDG', NULL),
(2, 'Juan Dela Cruz', 'ABC123', 'Caught on EDSA', 'Speeding on a 40kph zone', '1', '3', '2025-08-01 06:00:00', 1, 'L123456', 0, 0, NULL, '2025-08-01 14:00:00', 'Pending', 'First offense', NULL),
(3, 'Maria Santos', 'XYZ789', 'Parked near Quiapo Church', 'Blocking pedestrian lane', '2', '3', '2025-08-15 01:00:00', 1, 'L789012', 0, 1, 'OR12345', '2025-08-15 09:00:00', 'Resolved', 'Paid on the spot', NULL),
(4, 'Jose Mercado', 'DEF456', 'Intersection violation', 'Ignored traffic signal', '3', '5', '2025-08-20 08:30:00', 1, 'L345678', 0, 0, NULL, '2025-08-20 16:30:00', 'Disputed', 'Driver claims signal was green', NULL),
(5, 'Juan Dela Cruz', 'GHI789', NULL, 'No helmet on motorcycle', '4', '3', '2025-08-25 02:00:00', 0, NULL, 1, 0, NULL, '2025-08-25 10:00:00', 'Pending', 'Vehicle impounded', NULL),
(6, 'Maria Santos', 'JKL012', 'Highway incident', 'Suspected intoxication', '5', '5', '2025-08-30 14:00:00', 1, 'L789012', 1, 0, NULL, '2025-08-30 22:00:00', 'Pending', 'Awaiting breathalyzer results', NULL),
(7, 'Juan Dela Cruz', 'ABC123', 'Caught on EDSA', 'Speeding on a 40kph zone', '1', '5', '2025-08-01 06:00:00', 1, 'L123456', 0, 0, NULL, '2025-08-01 14:00:00', 'Pending', 'First offense', NULL),
(8, 'Maria Santos', 'XYZ789', 'Parked near Quiapo Church', 'Blocking pedestrian lane', '3', '5', '2025-08-15 01:00:00', 1, 'L789012', 0, 1, 'OR12345', '2025-08-15 09:00:00', 'Resolved', 'Paid on the spot', NULL),
(9, 'Jose Mercado', 'DEF456', 'Intersection violation', 'Ignored traffic signal', '5', '7', '2025-08-20 08:30:00', 1, 'L345678', 0, 0, NULL, '2025-08-20 16:30:00', 'Disputed', 'Driver claims signal was green', NULL),
(10, 'Juan Dela Cruz Jr', 'GHI789', NULL, 'No helmet on motorcycle', '6', '5', '2025-08-25 02:00:00', 0, NULL, 1, 0, NULL, '2025-08-25 10:00:00', 'Pending', 'Vehicle impounded', NULL),
(11, 'Maria Ana Santos', 'JKL012', 'Highway incident', 'Suspected intoxication', '7', '7', '2025-08-30 14:00:00', 1, 'L789012', 1, 0, NULL, '2025-08-30 22:00:00', 'Pending', 'Awaiting breathalyzer results', NULL),
(12, 'Juan Dela Cruz', 'MNO345', 'Repeat offense', 'Speeding again on EDSA', '2', '5', '2025-08-31 07:00:00', 1, 'L123456', 0, 0, NULL, '2025-08-31 15:00:00', 'Pending', 'Second speeding offense', NULL),
(13, 'Juan Dela Cruz', 'ABC123', 'Caught on EDSA', 'Speeding on a 40kph zone', '1', '5', '2025-08-01 06:00:00', 1, 'L123456', 0, 0, NULL, '2025-08-01 14:00:00', 'Pending', 'First offense', NULL),
(14, 'Maria Santos', 'XYZ789', 'Parked near Quiapo Church', 'Blocking pedestrian lane', '3', '5', '2025-08-15 01:00:00', 1, 'L789012', 0, 1, 'OR12345', '2025-08-15 09:00:00', 'Resolved', 'Paid on the spot', NULL),
(15, 'Jose Mercado', 'DEF456', 'Intersection violation', 'Ignored traffic signal', '5', '7', '2025-08-20 08:30:00', 1, 'L345678', 0, 0, NULL, '2025-08-20 16:30:00', 'Disputed', 'Driver claims signal was green', NULL),
(16, 'Juan Dela Cruz Jr', 'GHI789', NULL, 'No helmet on motorcycle', '6', '5', '2025-08-25 02:00:00', 0, NULL, 1, 0, NULL, '2025-08-25 10:00:00', 'Pending', 'Vehicle impounded', NULL),
(17, 'Maria Ana Santos', 'JKL012', 'Highway incident', 'Suspected intoxication', '7', '7', '2025-08-30 14:00:00', 1, 'L789012', 1, 0, NULL, '2025-08-30 22:00:00', 'Pending', 'Awaiting breathalyzer results', NULL),
(18, 'Juan Dela Cruz', 'MNO345', 'Repeat offense', 'Speeding again on EDSA', '2', '5', '2025-08-31 07:00:00', 1, 'L123456', 0, 0, NULL, '2025-08-31 15:00:00', 'Pending', 'Second speeding offense', NULL),
(19, 'Juan Dela Cruz', 'ABC123', 'Caught on EDSA', 'Speeding on a 40kph zone', '1', '5', '2025-08-01 06:00:00', 1, 'L123456', 0, 0, NULL, '2025-08-01 14:00:00', 'Pending', 'First offense', NULL),
(20, 'Maria Santos', 'XYZ789', 'Parked near Quiapo Church', 'Blocking pedestrian lane', '3', '5', '2025-08-15 01:00:00', 1, 'L789012', 0, 1, 'OR12345', '2025-08-15 09:00:00', 'Resolved', 'Paid on the spot', NULL),
(21, 'Jose Mercado', 'DEF456', 'Intersection violation', 'Ignored traffic signal', '5', '7', '2025-08-20 08:30:00', 1, 'L345678', 0, 0, NULL, '2025-08-20 16:30:00', 'Disputed', 'Driver claims signal was green', NULL),
(22, 'Juan Dela Cruz Jr', 'GHI789', NULL, 'No helmet on motorcycle', '6', '5', '2025-08-25 02:00:00', 0, NULL, 1, 0, NULL, '2025-08-25 10:00:00', 'Pending', 'Vehicle impounded', NULL),
(23, 'Maria Ana Santos', 'JKL012', 'Highway incident', 'Suspected intoxication', '7', '7', '2025-08-30 14:00:00', 1, 'L789012', 1, 0, NULL, '2025-08-30 22:00:00', 'Pending', 'Awaiting breathalyzer results', NULL),
(24, 'Juan Dela Cruz', 'MNO345', 'Repeat offense', 'Speeding again on EDSA', '2', '5', '2025-08-31 07:00:00', 1, 'L123456', 0, 0, NULL, '2025-08-31 15:00:00', 'Pending', 'Second speeding offense', NULL),
(26, 'Maria Santos', 'XYZ789', 'Parked near Quiapo Church', 'Blocking pedestrian lane', '3', '5', '2025-08-15 01:00:00', 1, 'L789012', 0, 1, 'OR12345', '2025-08-15 09:00:00', 'Resolved', 'Paid on the spot', NULL),
(27, 'Jose Mercado', 'DEF456', 'Intersection violation', 'Ignored traffic signal', '5', '7', '2025-08-20 08:30:00', 1, 'L345678', 0, 0, NULL, '2025-08-20 16:30:00', 'Disputed', 'Driver claims signal was green', NULL),
(28, 'Juan Dela Cruz Jr', 'GHI789', NULL, 'No helmet on motorcycle', '6', '5', '2025-08-25 02:00:00', 0, NULL, 1, 0, NULL, '2025-08-25 10:00:00', 'Pending', 'Vehicle impounded', NULL),
(29, 'Maria Ana Santos', 'JKL012', 'Highway incident', 'Suspected intoxication', '7', '7', '2025-08-30 14:00:00', 1, 'L789012', 1, 0, NULL, '2025-08-30 22:00:00', 'Pending', 'Awaiting breathalyzer results', NULL),
(30, 'Juan Dela Cruz', 'MNO345', 'Repeat offense', 'Speeding again on EDSA', '2', '5', '2025-08-31 07:00:00', 1, 'L123456', 0, 0, NULL, '2025-08-31 15:00:00', 'Pending', 'Second speeding offense', NULL),
(31, 'Juan Dela Cruz', 'ABC123', 'Caught on EDSA', 'Speeding on a 40kph zone', '1', '5', '2025-08-01 06:00:00', 1, 'L123456', 0, 0, NULL, '2025-08-01 14:00:00', 'Pending', 'First offense', NULL),
(32, 'Maria Santos', 'XYZ789', 'Parked near Quiapo Church', 'Blocking pedestrian lane', '3', '5', '2025-08-15 01:00:00', 1, 'L789012', 0, 1, 'OR12345', '2025-08-15 09:00:00', 'Resolved', 'Paid on the spot', NULL),
(33, 'Jose Mercado', 'DEF456', 'Intersection violation', 'Ignored traffic signal', '5', '7', '2025-08-20 08:30:00', 1, 'L345678', 0, 0, NULL, '2025-08-20 16:30:00', 'Disputed', 'Driver claims signal was green', NULL),
(34, 'Juan Dela Cruz Jr', 'GHI789', NULL, 'No helmet on motorcycle', '6', '5', '2025-08-25 02:00:00', 0, NULL, 1, 0, NULL, '2025-08-25 10:00:00', 'Pending', 'Vehicle impounded', NULL),
(35, 'Maria Ana Santos', 'JKL012', 'Highway incident', 'Suspected intoxication', '7', '7', '2025-08-30 14:00:00', 1, 'L789012', 1, 0, NULL, '2025-08-30 22:00:00', 'Pending', 'Awaiting breathalyzer results', NULL),
(36, 'Juan Dela Cruz', 'MNO345', 'Repeat offense', 'Speeding again on EDSA', '2', '5', '2025-08-31 07:00:00', 1, 'L123456', 0, 0, NULL, '2025-08-31 15:00:00', 'Pending', 'Second speeding offense', NULL),
(37, 'Juan Dela Cruz', 'ABC123', 'Caught on EDSA', 'Speeding on a 40kph zone', '1', '5', '2025-08-01 06:00:00', 1, 'L123456', 0, 0, NULL, '2025-08-01 14:00:00', 'Pending', 'First offense', NULL),
(38, 'Maria Santos', 'XYZ789', 'Parked near Quiapo Church', 'Blocking pedestrian lane', '3', '5', '2025-08-15 01:00:00', 1, 'L789012', 0, 1, 'OR12345', '2025-08-15 09:00:00', 'Resolved', 'Paid on the spot', NULL),
(39, 'Jose Mercado', 'DEF456', 'Intersection violation', 'Ignored traffic signal', '5', '7', '2025-08-20 08:30:00', 1, 'L345678', 0, 0, NULL, '2025-08-20 16:30:00', 'Disputed', 'Driver claims signal was green', NULL),
(40, 'Juan Dela Cruz Jr', 'GHI789', NULL, 'No helmet on motorcycle', '6', '5', '2025-08-25 02:00:00', 0, NULL, 1, 0, NULL, '2025-08-25 10:00:00', 'Pending', 'Vehicle impounded', NULL),
(41, 'Maria Ana Santos', 'JKL012', 'Highway incident', 'Suspected intoxication', '7', '7', '2025-08-30 14:00:00', 1, 'L789012', 1, 0, NULL, '2025-08-30 22:00:00', 'Pending', 'Awaiting breathalyzer results', NULL),
(42, 'Juan Dela Cruz', 'MNO345', 'Repeat offense', 'Speeding again on EDSA', '2', '5', '2025-08-31 07:00:00', 1, 'L123456', 0, 0, NULL, '2025-08-31 15:00:00', 'Pending', 'Second speeding offense', NULL),
(43, 'Juan Dela Cruz', 'ABC123', 'Caught on EDSA', 'Speeding on a 40kph zone', '1', '5', '2025-08-01 06:00:00', 1, 'L123456', 0, 0, NULL, '2025-08-01 14:00:00', 'Pending', 'First offense', NULL),
(44, 'Maria Santos', 'XYZ789', 'Parked near Quiapo Church', 'Blocking pedestrian lane', '3', '5', '2025-08-15 01:00:00', 1, 'L789012', 0, 1, 'OR12345', '2025-08-15 09:00:00', 'Resolved', 'Paid on the spot', NULL),
(45, 'Jose Mercado', 'DEF456', 'Intersection violation', 'Ignored traffic signal', '5', '7', '2025-08-20 08:30:00', 1, 'L345678', 0, 0, NULL, '2025-08-20 16:30:00', 'Disputed', 'Driver claims signal was green', NULL),
(46, 'Juan Dela Cruz Jr', 'GHI789', NULL, 'No helmet on motorcycle', '6', '5', '2025-08-25 02:00:00', 0, NULL, 1, 0, NULL, '2025-08-25 10:00:00', 'Pending', 'Vehicle impounded', NULL),
(47, 'Maria Ana Santos', 'JKL012', 'Highway incident', 'Suspected intoxication', '7', '7', '2025-08-30 14:00:00', 1, 'L789012', 1, 0, NULL, '2025-08-30 22:00:00', 'Pending', 'Awaiting breathalyzer results', NULL),
(48, 'Juan Dela Cruz', 'MNO345', 'Repeat offense', 'Speeding again on EDSA', '2', '5', '2025-08-31 07:00:00', 1, 'L123456', 0, 0, NULL, '2025-08-31 15:00:00', 'Pending', 'Second speeding offense', NULL),
(49, 'Juan Dela Cruz', 'ABC123', 'Caught on EDSA', 'Speeding on a 40kph zone', '1', '5', '2025-08-01 06:00:00', 1, 'L123456', 0, 0, NULL, '2025-08-01 14:00:00', 'Pending', 'First offense', NULL),
(50, 'Maria Santos', 'XYZ789', 'Parked near Quiapo Church', 'Blocking pedestrian lane', '3', '5', '2025-08-15 01:00:00', 1, 'L789012', 0, 1, 'OR12345', '2025-08-15 09:00:00', 'Resolved', 'Paid on the spot', NULL),
(51, 'Jose Mercado', 'DEF456', 'Intersection violation', 'Ignored traffic signal', '5', '7', '2025-08-20 08:30:00', 1, 'L345678', 0, 0, NULL, '2025-08-20 16:30:00', 'Disputed', 'Driver claims signal was green', NULL),
(52, 'Juan Dela Cruz Jr', 'GHI789', NULL, 'No helmet on motorcycle', '6', '5', '2025-08-25 02:00:00', 0, NULL, 1, 0, NULL, '2025-08-25 10:00:00', 'Pending', 'Vehicle impounded', NULL),
(53, 'Maria Ana Santos', 'JKL012', 'Highway incident', 'Suspected intoxication', '7', '7', '2025-08-30 14:00:00', 1, 'L789012', 1, 0, NULL, '2025-08-30 22:00:00', 'Pending', 'Awaiting breathalyzer results', NULL),
(54, 'Juan Dela Cruz', 'MNO345', 'Repeat offense', 'Speeding again on EDSA', '2', '5', '2025-08-31 07:00:00', 1, 'L123456', 0, 0, NULL, '2025-08-31 15:00:00', 'Pending', 'Second speeding offense', NULL),
(55, 'Juan Dela Cruz', 'ABC123', 'Caught on EDSA', 'Speeding on a 40kph zone', '1', '5', '2025-08-01 06:00:00', 1, 'L123456', 0, 0, NULL, '2025-08-01 14:00:00', 'Pending', 'First offense', NULL),
(56, 'Maria Santos', 'XYZ789', 'Parked near Quiapo Church', 'Blocking pedestrian lane', '3', '5', '2025-08-15 01:00:00', 1, 'L789012', 0, 1, 'OR12345', '2025-08-15 09:00:00', 'Resolved', 'Paid on the spot', NULL),
(57, 'Jose Mercado', 'DEF456', 'Intersection violation', 'Ignored traffic signal', '5', '7', '2025-08-20 08:30:00', 1, 'L345678', 0, 0, NULL, '2025-08-20 16:30:00', 'Disputed', 'Driver claims signal was green', NULL),
(58, 'Juan Dela Cruz Jr', 'GHI789', NULL, 'No helmet on motorcycle', '6', '5', '2025-08-25 02:00:00', 0, NULL, 1, 0, NULL, '2025-08-25 10:00:00', 'Pending', 'Vehicle impounded', NULL),
(59, 'Maria Ana Santos', 'JKL012', 'Highway incident', 'Suspected intoxication', '7', '7', '2025-08-30 14:00:00', 1, 'L789012', 1, 0, NULL, '2025-08-30 22:00:00', 'Pending', 'Awaiting breathalyzer results', NULL),
(60, 'Juan Dela Cruz', 'MNO345', 'Repeat offense', 'Speeding again on EDSA', '2', '5', '2025-08-31 07:00:00', 1, 'L123456', 0, 0, NULL, '2025-08-31 15:00:00', 'Pending', 'Second speeding offense', NULL),
(61, 'Juan Dela Cruz', 'ABC123', 'Caught on EDSA', 'Speeding on a 40kph zone', '1', '5', '2025-08-01 06:00:00', 1, 'L123456', 0, 0, NULL, '2025-08-01 14:00:00', 'Pending', 'First offense', NULL),
(62, 'Maria Santos', 'XYZ789', 'Parked near Quiapo Church', 'Blocking pedestrian lane', '3', '5', '2025-08-15 01:00:00', 1, 'L789012', 0, 1, 'OR12345', '2025-08-15 09:00:00', 'Resolved', 'Paid on the spot', NULL),
(63, 'Jose Mercado', 'DEF456', 'Intersection violation', 'Ignored traffic signal', '5', '7', '2025-08-20 08:30:00', 1, 'L345678', 0, 0, NULL, '2025-08-20 16:30:00', 'Disputed', 'Driver claims signal was green', NULL),
(64, 'Juan Dela Cruz Jr', 'GHI789', NULL, 'No helmet on motorcycle', '6', '5', '2025-08-25 02:00:00', 0, NULL, 1, 0, NULL, '2025-08-25 10:00:00', 'Pending', 'Vehicle impounded', NULL),
(65, 'Maria Ana Santos', 'JKL012', 'Highway incident', 'Suspected intoxication', '7', '7', '2025-08-30 14:00:00', 1, 'L789012', 1, 0, NULL, '2025-08-30 22:00:00', 'Pending', 'Awaiting breathalyzer results', NULL),
(66, 'Juan Dela Cruz', 'MNO345', 'Repeat offense', 'Speeding again on EDSA', '2', '5', '2025-08-31 07:00:00', 1, 'L123456', 0, 0, NULL, '2025-08-31 15:00:00', 'Pending', 'Second speeding offense', NULL),
(67, 'Juan Dela Cruz', 'ABC123', 'Caught on EDSA', 'Speeding on a 40kph zone', '1', '5', '2025-08-01 06:00:00', 1, 'L123456', 0, 0, NULL, '2025-08-01 14:00:00', 'Pending', 'First offense', NULL),
(68, 'Maria Santos', 'XYZ789', 'Parked near Quiapo Church', 'Blocking pedestrian lane', '3', '5', '2025-08-15 01:00:00', 1, 'L789012', 0, 1, 'OR12345', '2025-08-15 09:00:00', 'Resolved', 'Paid on the spot', NULL),
(69, 'Jose Mercado', 'DEF456', 'Intersection violation', 'Ignored traffic signal', '5', '7', '2025-08-20 08:30:00', 1, 'L345678', 0, 0, NULL, '2025-08-20 16:30:00', 'Disputed', 'Driver claims signal was green', NULL),
(70, 'Juan Dela Cruz Jr', 'GHI789', NULL, 'No helmet on motorcycle', '6', '5', '2025-08-25 02:00:00', 0, NULL, 1, 0, NULL, '2025-08-25 10:00:00', 'Pending', 'Vehicle impounded', NULL),
(71, 'Maria Ana Santos', 'JKL012', 'Highway incident', 'Suspected intoxication', '7', '7', '2025-08-30 14:00:00', 1, 'L789012', 1, 0, NULL, '2025-08-30 22:00:00', 'Pending', 'Awaiting breathalyzer results', NULL),
(72, 'Juan Dela Cruz', 'MNO345', 'Repeat offense', 'Speeding again on EDSA', '2', '5', '2025-08-31 07:00:00', 1, 'L123456', 0, 0, NULL, '2025-08-31 15:00:00', 'Pending', 'Second speeding offense', NULL),
(73, 'Juan Dela Cruz', 'ABC123', 'Caught on EDSA', 'Speeding on a 40kph zone', '1', '5', '2025-08-01 06:00:00', 1, 'L123456', 0, 0, NULL, '2025-08-01 14:00:00', 'Pending', 'First offense', NULL),
(74, 'Maria Santos', 'XYZ789', 'Parked near Quiapo Church', 'Blocking pedestrian lane', '3', '5', '2025-08-15 01:00:00', 1, 'L789012', 0, 1, 'OR12345', '2025-08-15 09:00:00', 'Resolved', 'Paid on the spot', NULL),
(75, 'Jose Mercado', 'DEF456', 'Intersection violation', 'Ignored traffic signal', '5', '7', '2025-08-20 08:30:00', 1, 'L345678', 0, 0, NULL, '2025-08-20 16:30:00', 'Disputed', 'Driver claims signal was green', NULL),
(76, 'Juan Dela Cruz Jr', 'GHI789', NULL, 'No helmet on motorcycle', '6', '5', '2025-08-25 02:00:00', 0, NULL, 1, 0, NULL, '2025-08-25 10:00:00', 'Pending', 'Vehicle impounded', NULL),
(77, 'Maria Ana Santos', 'JKL012', 'Highway incident', 'Suspected intoxication', '7', '7', '2025-08-30 14:00:00', 1, 'L789012', 1, 0, NULL, '2025-08-30 22:00:00', 'Pending', 'Awaiting breathalyzer results', NULL),
(78, 'Juan Dela Cruz', 'MNO345', 'Repeat offense', 'Speeding again on EDSA', '2', '5', '2025-08-31 07:00:00', 1, 'L123456', 0, 0, NULL, '2025-08-31 15:00:00', 'Pending', 'Second speeding offense', NULL),
(79, 'Johns', '0678678', NULL, '124', '14', '5', '2025-09-01 08:56:55', 0, NULL, 0, 0, '07897', '2025-09-01 10:56:00', 'Pending', 'Kadto LTO office', NULL),
(80, 'Eddie', 'GED23', NULL, 'Dasig Dasig gid!', '13', '5', '2025-09-13 13:15:28', 1, NULL, 0, 0, NULL, NULL, 'Pending', 'Pay Fine', NULL),
(81, 'John', 'OIO123', NULL, 'No helmet', '115', '5', '2025-09-18 14:05:08', 1, NULL, 1, 0, NULL, NULL, 'Resolved', 'OK kaayo', NULL),
(82, 'Gaben', 'XVC123', NULL, 'Over Speeding', '13', '5', '2025-09-18 14:16:53', 0, NULL, 0, 0, NULL, NULL, 'Resolved', 'Okay Na', NULL),
(83, 'URNCVN', '09795746', NULL, 'EW213', '14', '152', '2025-09-20 06:03:58', 1, NULL, 0, 0, NULL, NULL, 'Pending', 'qew', NULL),
(84, 'URNCVN', '09795746', NULL, 'EW213', '14', '152', '2025-09-20 06:08:40', 1, NULL, 0, 0, NULL, NULL, 'Pending', 'qew', NULL),
(85, 'fwef', 'sf1231', NULL, 'ewrwefsvv', '14', '153', '2025-09-20 06:09:16', 1, NULL, 0, 0, NULL, NULL, 'Pending', 'ewrwer', NULL),
(86, 'YYEBH', 'YXG123', NULL, 'qwe', '14', '154', '2025-09-20 06:17:27', 1, NULL, 0, 0, NULL, NULL, 'Pending', '123a', NULL),
(101, 'fwef', 'fhfg', NULL, 'htrhrt', '14', '153', '2025-09-20 08:30:00', 0, 'rthrth', 0, 0, 'shtr', '2025-09-20 10:29:00', 'Resolved', 'rhrt', '5'),
(102, 'dfgdfgerger', 'sdvd', NULL, 'dsv', '14', '156', '2025-09-20 08:36:31', 0, 'dv', 0, 0, 'sdv', '2025-09-20 10:36:00', 'Resolved', 'dv', '5'),
(103, 'sdfsd', 'saf', NULL, 'sdas', '14', '157', '2025-09-20 08:44:51', 0, 'dasd', 0, 0, 'dssd', '2025-09-20 10:44:00', 'Resolved', 'sd', '5'),
(107, 'dfgfd', 'efew', NULL, 'fgerg', '17', '158', '2025-09-20 09:24:06', 0, 'eger', 0, 0, 'erger', '2025-09-20 11:23:00', 'Pending', 'erg', '5'),
(108, 'sdfe', 'efw', NULL, 'dfgfd', '14', '159', '2025-09-20 09:24:48', 0, 'ege', 1, 0, 'ere', '2025-09-20 11:24:00', 'Pending', 'ege', '5'),
(109, 'fef', 'gerg', NULL, 'ere', '14', '160', '2025-09-20 09:25:24', 0, 'ere', 0, 0, 'ewr', '2025-09-20 11:24:00', 'Pending', 'ere', '5');

-- --------------------------------------------------------

--
-- Table structure for table `violation_analytics`
--

CREATE TABLE `violation_analytics` (
  `id` int(11) NOT NULL,
  `month_year` varchar(7) NOT NULL COMMENT 'Format: YYYY-MM',
  `violation_type_id` int(11) NOT NULL,
  `violation_count` int(11) NOT NULL DEFAULT 0,
  `percentage` decimal(5,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `violation_analytics`
--

INSERT INTO `violation_analytics` (`id`, `month_year`, `violation_type_id`, `violation_count`, `percentage`, `created_at`) VALUES
(1, '2025-08', 1, 10, 33.33, '2025-08-31 15:59:59'),
(2, '2025-08', 1, 5, 16.67, '2025-08-31 15:59:59'),
(3, '2025-08', 3, 8, 26.67, '2025-08-31 15:59:59'),
(4, '2025-08', 5, 6, 20.00, '2025-08-31 15:59:59'),
(5, '2025-08', 6, 4, 13.33, '2025-08-31 15:59:59'),
(6, '2025-08', 7, 2, 6.67, '2025-08-31 15:59:59');

-- --------------------------------------------------------

--
-- Table structure for table `violation_heatmap_report`
--

CREATE TABLE `violation_heatmap_report` (
  `id` int(11) NOT NULL,
  `latitude` decimal(9,6) NOT NULL,
  `longitude` decimal(9,6) NOT NULL,
  `violation_count` int(11) NOT NULL DEFAULT 0,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `violation_heatmap_report`
--

INSERT INTO `violation_heatmap_report` (`id`, `latitude`, `longitude`, `violation_count`, `last_updated`) VALUES
(1, 14.599512, 120.984219, 2, '2025-08-31 07:00:00'),
(2, 14.598147, 120.983665, 1, '2025-08-15 01:00:00'),
(3, 14.609057, 120.994346, 1, '2025-08-20 08:30:00'),
(4, 14.553691, 121.024445, 1, '2025-08-25 02:00:00'),
(5, 14.558490, 121.025810, 1, '2025-08-30 14:00:00'),
(6, 14.599512, 120.984219, 1, '2025-08-31 08:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `violation_locations`
--

CREATE TABLE `violation_locations` (
  `id` int(11) NOT NULL,
  `violation_id` int(11) NOT NULL,
  `latitude` decimal(9,6) NOT NULL,
  `longitude` decimal(9,6) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `violation_locations`
--

INSERT INTO `violation_locations` (`id`, `violation_id`, `latitude`, `longitude`, `created_at`) VALUES
(1, 1, 14.599512, 120.984219, '2025-08-01 06:00:00'),
(2, 2, 14.598147, 120.983665, '2025-08-15 01:00:00'),
(3, 3, 14.609057, 120.994346, '2025-08-20 08:30:00'),
(4, 4, 14.553691, 121.024445, '2025-08-25 02:00:00'),
(5, 5, 14.558490, 121.025810, '2025-08-30 14:00:00'),
(6, 1, 14.599512, 120.984219, '2025-08-31 07:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `violation_location_details`
--

CREATE TABLE `violation_location_details` (
  `id` int(11) NOT NULL,
  `violation_id` int(11) NOT NULL,
  `city` varchar(100) NOT NULL,
  `municipality` varchar(100) DEFAULT NULL,
  `province` varchar(100) NOT NULL,
  `street` varchar(255) DEFAULT NULL,
  `barangay` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `violation_location_details`
--

INSERT INTO `violation_location_details` (`id`, `violation_id`, `city`, `municipality`, `province`, `street`, `barangay`, `created_at`) VALUES
(1, 1, 'Quezon City', NULL, 'Metro Manila', 'EDSA', 'Diliman', '2025-08-01 06:00:00'),
(2, 2, 'Manila', NULL, 'Metro Manila', 'Rizal Avenue', 'Quiapo', '2025-08-15 01:00:00'),
(3, 3, 'Manila', NULL, 'Metro Manila', 'España Boulevard', 'Sampaloc', '2025-08-20 08:30:00'),
(4, 4, 'Makati City', NULL, 'Metro Manila', 'Ayala Avenue', 'San Lorenzo', '2025-08-25 02:00:00'),
(5, 5, 'Taguig City', NULL, 'Metro Manila', '5th Avenue', 'Fort Bonifacio', '2025-08-30 14:00:00'),
(6, 1, 'Quezon City', NULL, 'Metro Manila', 'EDSA', 'Diliman', '2025-08-31 07:00:00');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `appeals`
--
ALTER TABLE `appeals`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `audit_log`
--
ALTER TABLE `audit_log`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `concerns`
--
ALTER TABLE `concerns`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `holiday_rules`
--
ALTER TABLE `holiday_rules`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `license_status`
--
ALTER TABLE `license_status`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `officer_earnings`
--
ALTER TABLE `officer_earnings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `officer_status`
--
ALTER TABLE `officer_status`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `patrol_zones`
--
ALTER TABLE `patrol_zones`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `revenue_metrics`
--
ALTER TABLE `revenue_metrics`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `system_health`
--
ALTER TABLE `system_health`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `types`
--
ALTER TABLE `types`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `violation_type` (`violation_type`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `violations`
--
ALTER TABLE `violations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `violation_analytics`
--
ALTER TABLE `violation_analytics`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `violation_heatmap_report`
--
ALTER TABLE `violation_heatmap_report`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `violation_locations`
--
ALTER TABLE `violation_locations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `violation_location_details`
--
ALTER TABLE `violation_location_details`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `appeals`
--
ALTER TABLE `appeals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=40;

--
-- AUTO_INCREMENT for table `audit_log`
--
ALTER TABLE `audit_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=55;

--
-- AUTO_INCREMENT for table `concerns`
--
ALTER TABLE `concerns`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=70;

--
-- AUTO_INCREMENT for table `holiday_rules`
--
ALTER TABLE `holiday_rules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=53;

--
-- AUTO_INCREMENT for table `license_status`
--
ALTER TABLE `license_status`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=52;

--
-- AUTO_INCREMENT for table `officer_earnings`
--
ALTER TABLE `officer_earnings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `officer_status`
--
ALTER TABLE `officer_status`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=42;

--
-- AUTO_INCREMENT for table `patrol_zones`
--
ALTER TABLE `patrol_zones`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `revenue_metrics`
--
ALTER TABLE `revenue_metrics`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- AUTO_INCREMENT for table `system_health`
--
ALTER TABLE `system_health`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- AUTO_INCREMENT for table `types`
--
ALTER TABLE `types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=116;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=161;

--
-- AUTO_INCREMENT for table `violations`
--
ALTER TABLE `violations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=110;

--
-- AUTO_INCREMENT for table `violation_analytics`
--
ALTER TABLE `violation_analytics`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `violation_heatmap_report`
--
ALTER TABLE `violation_heatmap_report`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `violation_locations`
--
ALTER TABLE `violation_locations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `violation_location_details`
--
ALTER TABLE `violation_location_details`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;






ALTER TABLE violations ADD COLUMN email_sent BOOLEAN DEFAULT FALSE;