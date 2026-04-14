-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 14, 2026 at 07:26 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `capstone_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `role` enum('admin','staff','user') NOT NULL DEFAULT 'user',
  `action` varchar(100) NOT NULL,
  `module` varchar(60) NOT NULL DEFAULT 'general',
  `description` text NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(300) DEFAULT NULL,
  `meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta`)),
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `announcements`
--

CREATE TABLE `announcements` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `posted_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `appointments`
--

CREATE TABLE `appointments` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `pet_id` int(11) DEFAULT NULL,
  `service_id` int(11) DEFAULT NULL,
  `appointment_type` enum('clinic','home_service') DEFAULT 'clinic',
  `appointment_date` date NOT NULL,
  `appointment_time` time NOT NULL,
  `address` varchar(255) DEFAULT NULL,
  `contact` varchar(20) DEFAULT NULL,
  `status` enum('pending','confirmed','completed','cancelled') DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `cancellation_reason` varchar(255) DEFAULT NULL,
  `archived` tinyint(1) DEFAULT 0,
  `queue_status` enum('none','arrived','waiting','ongoing','done') NOT NULL DEFAULT 'none'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `appointments`
--

INSERT INTO `appointments` (`id`, `user_id`, `pet_id`, `service_id`, `appointment_type`, `appointment_date`, `appointment_time`, `address`, `contact`, `status`, `notes`, `created_at`, `cancellation_reason`, `archived`, `queue_status`) VALUES
(4, 2, 1, 1, 'clinic', '2026-03-19', '10:00:00', NULL, NULL, 'completed', NULL, '2026-03-19 01:53:49', NULL, 0, 'none'),
(22, 9, 6, 1, 'clinic', '2026-03-28', '13:00:00', NULL, NULL, 'completed', NULL, '2026-03-28 02:32:47', NULL, 0, 'none'),
(24, 9, 6, 1, 'clinic', '2026-03-30', '13:00:00', NULL, NULL, 'completed', NULL, '2026-03-28 03:18:20', NULL, 0, 'none'),
(25, 9, 6, 4, 'clinic', '2026-04-05', '13:00:00', NULL, NULL, 'completed', NULL, '2026-04-05 13:58:14', NULL, 0, 'none'),
(26, 11, 8, 5, 'clinic', '2026-10-04', '13:00:00', NULL, NULL, 'completed', NULL, '2026-04-06 02:03:27', NULL, 0, 'none'),
(27, 11, NULL, NULL, 'home_service', '2026-12-04', '13:00:00', 'Bu polangui', '0928652628', 'completed', NULL, '2026-04-06 02:05:22', NULL, 1, 'none'),
(29, 2, 1, 1, 'clinic', '2026-04-09', '13:00:00', NULL, NULL, 'completed', NULL, '2026-04-09 14:58:28', NULL, 0, 'none'),
(34, 2, 1, 1, 'clinic', '2026-04-11', '21:00:00', NULL, NULL, 'completed', NULL, '2026-04-11 12:58:45', NULL, 0, 'done'),
(38, 9, 6, 1, 'clinic', '2026-04-14', '13:00:00', NULL, NULL, 'completed', NULL, '2026-04-14 02:01:16', NULL, 0, 'done'),
(39, 2, 1, 1, 'clinic', '2026-04-14', '13:00:00', NULL, NULL, 'confirmed', NULL, '2026-04-14 02:27:53', NULL, 0, 'done');

-- --------------------------------------------------------

--
-- Table structure for table `home_service_pets`
--

CREATE TABLE `home_service_pets` (
  `id` int(11) NOT NULL,
  `appointment_id` int(11) NOT NULL,
  `pet_name` varchar(100) DEFAULT NULL,
  `species` varchar(100) DEFAULT NULL,
  `breed` varchar(100) DEFAULT NULL,
  `service_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `home_service_pets`
--

INSERT INTO `home_service_pets` (`id`, `appointment_id`, `pet_name`, `species`, `breed`, `service_id`) VALUES
(13, 27, 'Kagata', 'Dog', NULL, 5),
(14, 27, 'Browny', 'Dog/Aspin', NULL, 5);

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `attachment` varchar(255) DEFAULT '',
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `deleted_by_admin` tinyint(1) DEFAULT 0,
  `deleted_by_user` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `messages`
--

INSERT INTO `messages` (`id`, `sender_id`, `receiver_id`, `message`, `attachment`, `is_read`, `created_at`, `deleted_by_admin`, `deleted_by_user`) VALUES
(1, 2, 1, 'Hello!', '', 1, '2026-03-18 08:22:32', 0, 0),
(2, 1, 2, 'Hello', '', 1, '2026-03-18 10:51:34', 0, 0),
(3, 1, 2, 'Hello!', '', 1, '2026-03-19 02:26:16', 0, 0),
(4, 2, 1, 'hi', '', 1, '2026-03-19 03:09:40', 0, 0),
(5, 7, 2, 'Hello!', '', 1, '2026-03-23 02:14:24', 0, 0),
(6, 2, 7, 'Hello!', '', 1, '2026-03-23 04:22:54', 0, 0),
(9, 9, 1, 'Hello Dr.', '', 1, '2026-03-24 09:01:11', 0, 0),
(13, 2, 1, 'Hello dr. hgdsmd sdbcd kdjbkdsbvahfdugbjasbhjgedga dkjdgfjka vfhjvqhoq;ha hjasvvdhasdn ahsjvdshqvdjks  gcjwdjbw', '', 1, '2026-03-28 05:17:12', 0, 0),
(15, 11, 7, 'Hello Assistant', '', 1, '2026-04-06 02:07:15', 0, 0),
(16, 7, 11, 'Hello', '', 0, '2026-04-06 02:10:00', 0, 0),
(17, 7, 11, '', 'msg_69d337b270c4c7.63542570.jpg', 0, '2026-04-06 04:33:54', 0, 0),
(20, 1, 9, 'Hello', '', 1, '2026-04-07 10:37:19', 0, 0);

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `type` varchar(50) DEFAULT 'general',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `title`, `message`, `is_read`, `type`, `created_at`) VALUES
(2, 2, 'New Announcement', 'Strike', 1, 'announcement', '2026-03-18 08:01:55'),
(6, 2, 'Appointment Update', 'Your appointment for Browny (CheckUp) has been confirmed.', 1, 'appointment', '2026-03-19 02:08:18'),
(7, 2, 'Appointment Update', 'Your appointment for Browny (CheckUp) has been completed.', 1, 'appointment', '2026-03-19 02:09:32'),
(8, 2, 'New Message from Clinic', 'You have a new message from the clinic.', 1, 'message', '2026-03-19 02:26:16'),
(18, 2, 'New Invoice', 'A billing summary of ₱555.00 has been created for your visit.', 1, 'billing', '2026-03-21 06:31:00'),
(21, 2, 'New Message from Clinic', 'You have a new message from the clinic.', 1, 'message', '2026-03-23 02:14:24'),
(22, 2, 'New Invoice', 'A billing summary of ₱800.00 has been created for your visit.', 1, 'billing', '2026-03-23 02:43:08'),
(23, 4, 'New Invoice', 'A billing summary of ₱300.00 has been created for your visit.', 0, 'billing', '2026-03-23 02:43:58'),
(24, 4, 'New Invoice', 'A billing summary of ₱800.00 has been created for your visit.', 0, 'billing', '2026-03-23 02:54:22'),
(25, 2, 'New Invoice', 'A billing summary of ₱300.00 has been created for your visit.', 1, 'billing', '2026-03-23 03:14:22'),
(26, 2, 'New Invoice', 'A billing summary of ₱300.00 has been created for your visit.', 1, 'billing', '2026-03-23 03:27:22'),
(27, 7, 'New Message', 'You have a new message from Ana Santos', 0, 'message', '2026-03-23 04:22:54'),
(32, 2, 'Appointment Update', 'Your appointment for Bella (Surgery) has been cancelled.', 1, 'appointment', '2026-03-23 07:57:32'),
(34, 2, 'Payment Confirmed', 'Your payment of ₱150.00 has been confirmed. Your receipt is now available.', 1, 'billing', '2026-03-23 08:50:53'),
(35, 2, 'New Announcement', 'Strike', 1, 'announcement', '2026-03-23 13:23:08'),
(36, 3, 'New Announcement', 'Strike', 1, 'announcement', '2026-03-23 13:23:08'),
(37, 4, 'New Announcement', 'Strike', 0, 'announcement', '2026-03-23 13:23:08'),
(38, 2, 'Payment Confirmed', 'Your payment of ₱150.00 has been confirmed. Your receipt is now available.', 1, 'billing', '2026-03-23 13:58:08'),
(39, 2, 'Payment Confirmed', 'Your payment of ₱150.00 has been confirmed. Your receipt is now available.', 1, 'billing', '2026-03-23 15:18:37'),
(40, 4, 'Payment Confirmed', 'Your payment of ₱560.00 has been confirmed. Your receipt is now available.', 0, 'billing', '2026-03-23 15:21:07'),
(41, 2, 'Payment Confirmed', 'Your payment of ₱150.00 has been confirmed. Your receipt is now available.', 1, 'billing', '2026-03-23 15:21:19'),
(42, 2, 'Payment Confirmed', 'Your payment of ₱150.00 has been confirmed. Your receipt is now available.', 1, 'billing', '2026-03-23 15:21:33'),
(43, 2, 'Payment Confirmed', 'Your payment of ₱300.00 has been confirmed. Your receipt is now available.', 1, 'billing', '2026-03-23 15:21:41'),
(44, 2, 'Payment Confirmed', 'Your payment of ₱300.00 has been confirmed. Your receipt is now available.', 1, 'billing', '2026-03-23 15:21:51'),
(45, 4, 'Payment Confirmed', 'Your payment of ₱300.00 has been confirmed. Your receipt is now available.', 0, 'billing', '2026-03-23 15:30:50'),
(46, 4, 'Payment Confirmed', 'Your payment of ₱300.00 has been confirmed. Your receipt is now available.', 0, 'billing', '2026-03-23 15:32:23'),
(47, 2, 'Appointment Update', 'Your appointment for  () has been cancelled.', 1, 'appointment', '2026-03-23 15:41:39'),
(48, 2, 'Vaccine Reminder', 'Bella&#039;s Rabies vaccine is due on February 28, 2027', 1, 'vaccine', '2026-03-24 01:11:32'),
(49, 2, 'Vaccine Reminder', 'Bella&#039;s DHPP vaccine is due on March 24, 2026', 1, 'vaccine', '2026-03-24 01:12:08'),
(51, 2, 'Appointment Update', 'Your appointment for  () has been confirmed.', 1, 'appointment', '2026-03-24 01:16:15'),
(52, 2, 'Appointment Update', 'Your appointment for Bella (CheckUp) has been confirmed.', 1, 'appointment', '2026-03-24 01:28:56'),
(53, 2, 'Appointment Update', 'Your appointment for Bella (CheckUp) has been completed.', 1, 'appointment', '2026-03-24 01:29:00'),
(86, 7, '📦 Low Stock Alert', 'Low stock alert: &quot;Collar&quot; has only 4 item(s) remaining. Please restock soon. [prod:5]', 0, 'low_stock', '2026-03-24 12:45:42'),
(117, 2, 'New Announcement', 'Labor Day', 1, 'announcement', '2026-04-03 01:50:17'),
(118, 3, 'New Announcement', 'Labor Day', 1, 'announcement', '2026-04-03 01:50:17'),
(119, 4, 'New Announcement', 'Labor Day', 0, 'announcement', '2026-04-03 01:50:17'),
(128, 2, 'New Announcement', 'Labor Day', 1, 'announcement', '2026-04-03 02:38:40'),
(129, 3, 'New Announcement', 'Labor Day', 1, 'announcement', '2026-04-03 02:38:40'),
(130, 4, 'New Announcement', 'Labor Day', 0, 'announcement', '2026-04-03 02:38:40'),
(132, 2, 'New Announcement', 'a,db,a', 1, 'announcement', '2026-04-03 02:59:07'),
(133, 3, 'New Announcement', 'a,db,a', 1, 'announcement', '2026-04-03 02:59:07'),
(134, 4, 'New Announcement', 'a,db,a', 0, 'announcement', '2026-04-03 02:59:07'),
(136, 2, 'New Announcement', 'Holoday', 1, 'announcement', '2026-04-03 03:00:20'),
(137, 3, 'New Announcement', 'Holoday', 1, 'announcement', '2026-04-03 03:00:20'),
(138, 4, 'New Announcement', 'Holoday', 0, 'announcement', '2026-04-03 03:00:20'),
(140, 7, '📦 Low Stock Alert', 'Low stock alert: &quot;Royal Care Wound Healing Cream&quot; has only 1 item(s) remaining. Please restock soon. [prod:19]', 0, 'low_stock', '2026-04-05 06:29:42'),
(141, 7, '📦 Low Stock Alert', 'Low stock alert: &quot;Lactusole&quot; has only 2 item(s) remaining. Please restock soon. [prod:14]', 0, 'low_stock', '2026-04-05 06:29:42'),
(142, 7, '📦 Low Stock Alert', 'Low stock alert: &quot;Anti Tick and Flea Soap&quot; has only 3 item(s) remaining. Please restock soon. [prod:16]', 0, 'low_stock', '2026-04-05 06:29:42'),
(143, 7, '📦 Low Stock Alert', 'Low stock alert: &quot;Pet Toothpaste&quot; has only 3 item(s) remaining. Please restock soon. [prod:17]', 0, 'low_stock', '2026-04-05 06:29:42'),
(144, 7, '📦 Low Stock Alert', 'Low stock alert: &quot;DR. Shiba Happy Tummy (Beef Flavour)&quot; has only 4 item(s) remaining. Please restock soon. [prod:9]', 0, 'low_stock', '2026-04-05 06:29:42'),
(145, 7, '📦 Low Stock Alert', 'Low stock alert: &quot;Dono Disposable Diapers (Large)&quot; has only 5 item(s) remaining. Please restock soon. [prod:12]', 0, 'low_stock', '2026-04-05 06:29:42'),
(155, 7, 'New Message', 'You have a new message from Rose Blue', 0, 'message', '2026-04-06 02:07:15'),
(156, 11, 'Appointment Update', 'Your appointment for Kagata (Vaccination) has been confirmed.', 0, 'appointment', '2026-04-06 02:08:26'),
(157, 11, 'Appointment Update', 'Your appointment for  () has been confirmed.', 0, 'appointment', '2026-04-06 02:09:01'),
(158, 11, 'New Message from Clinic', 'You have a new message from the clinic.', 0, 'message', '2026-04-06 02:10:00'),
(159, 7, '📦 Low Stock Alert', 'Low stock alert: &quot;Royal Care Wound Healing Cream&quot; has only 1 item(s) remaining. Please restock soon. [prod:19]', 0, 'low_stock', '2026-04-06 02:19:09'),
(160, 7, '📦 Low Stock Alert', 'Low stock alert: &quot;Lactusole&quot; has only 2 item(s) remaining. Please restock soon. [prod:14]', 0, 'low_stock', '2026-04-06 02:19:09'),
(161, 7, '📦 Low Stock Alert', 'Low stock alert: &quot;Pet Toothpaste&quot; has only 3 item(s) remaining. Please restock soon. [prod:17]', 0, 'low_stock', '2026-04-06 02:19:09'),
(162, 7, '📦 Low Stock Alert', 'Low stock alert: &quot;DR. Shiba Happy Tummy (Beef Flavour)&quot; has only 4 item(s) remaining. Please restock soon. [prod:9]', 0, 'low_stock', '2026-04-06 02:19:09'),
(163, 7, '📦 Low Stock Alert', 'Low stock alert: &quot;Dono Disposable Diapers (Large)&quot; has only 5 item(s) remaining. Please restock soon. [prod:12]', 0, 'low_stock', '2026-04-06 02:19:09'),
(164, 11, 'New Message from Clinic', 'You have a new message from the clinic.', 0, 'message', '2026-04-06 04:33:54'),
(166, 11, 'Appointment Update', 'Your appointment for Kagata (Vaccination) has been completed.', 0, 'appointment', '2026-04-06 04:34:42'),
(167, 2, 'New Announcement', 'Araw ng Kagitingan', 1, 'announcement', '2026-04-06 05:57:04'),
(168, 3, 'New Announcement', 'Araw ng Kagitingan', 1, 'announcement', '2026-04-06 05:57:04'),
(169, 4, 'New Announcement', 'Araw ng Kagitingan', 0, 'announcement', '2026-04-06 05:57:04'),
(171, 11, 'New Announcement', 'Araw ng Kagitingan', 0, 'announcement', '2026-04-06 05:57:04'),
(175, 11, 'Payment Confirmed', 'Your payment of ₱350.00 has been confirmed. Your receipt is now available.', 0, 'billing', '2026-04-07 10:37:36'),
(177, 11, 'Payment Confirmed', 'Your payment of ₱380.00 has been confirmed. Your receipt is now available.', 0, 'billing', '2026-04-07 10:37:52'),
(183, 11, 'Appointment Update', 'Your appointment for  () has been completed.', 0, 'appointment', '2026-04-07 13:04:28'),
(184, 11, 'Payment Confirmed', 'Your payment of ₱0.00 has been confirmed. Your receipt is now available.', 0, 'billing', '2026-04-07 13:05:32'),
(191, 2, 'Appointment Update', 'Your appointment for Browny (CheckUp) has been confirmed.', 1, 'appointment', '2026-04-09 15:00:11'),
(193, 2, 'Appointment Update', 'Your appointment for Browny (CheckUp) has been completed.', 1, 'appointment', '2026-04-09 15:13:29'),
(199, 2, 'Appointment Update', 'Your appointment for Browny (CheckUp) has been confirmed.', 1, 'appointment', '2026-04-11 13:25:48'),
(201, 2, 'Appointment Update', 'Your appointment for Browny (CheckUp) has been completed.', 1, 'appointment', '2026-04-11 13:31:14'),
(202, 2, 'Payment Confirmed', 'Your payment of ₱300.00 has been confirmed. Your receipt is now available.', 1, 'billing', '2026-04-11 13:33:14'),
(203, 2, 'Payment Confirmed', 'Your payment of ₱300.00 has been confirmed. Your receipt is now available.', 1, 'billing', '2026-04-11 13:33:29'),
(213, 1, '📦 Low Stock Alert', 'Low stock alert: &quot;Royal Care Wound Healing Cream&quot; has only 1 item(s) remaining. Please restock soon. [prod:19]', 1, 'low_stock', '2026-04-13 07:05:37'),
(214, 1, '📦 Low Stock Alert', 'Low stock alert: &quot;Lactusole&quot; has only 2 item(s) remaining. Please restock soon. [prod:14]', 1, 'low_stock', '2026-04-13 07:05:37'),
(215, 1, '📦 Low Stock Alert', 'Low stock alert: &quot;Pet Toothpaste&quot; has only 3 item(s) remaining. Please restock soon. [prod:17]', 1, 'low_stock', '2026-04-13 07:05:37'),
(216, 1, '📦 Low Stock Alert', 'Low stock alert: &quot;DR. Shiba Happy Tummy (Beef Flavour)&quot; has only 4 item(s) remaining. Please restock soon. [prod:9]', 1, 'low_stock', '2026-04-13 07:05:37'),
(217, 1, '📦 Low Stock Alert', 'Low stock alert: &quot;Dono Disposable Diapers (Large)&quot; has only 5 item(s) remaining. Please restock soon. [prod:12]', 1, 'low_stock', '2026-04-13 07:05:37'),
(218, 1, 'New Appointment', 'New clinic appointment booked by Shanley Resentes for Laufey — CheckUp on April 14, 2026', 0, 'appointment', '2026-04-14 02:01:16'),
(221, 1, 'New Appointment', 'New clinic appointment booked by Ana Santos for Browny — CheckUp on April 14, 2026', 0, 'appointment', '2026-04-14 02:27:53'),
(222, 2, 'Appointment Update', 'Your appointment for Browny (CheckUp) has been confirmed.', 0, 'appointment', '2026-04-14 02:29:17'),
(223, 2, 'Appointment Completed', 'Your appointment for Browny (CheckUp) has been completed.', 0, 'appointment', '2026-04-14 02:29:47'),
(224, 1, 'Payment Proof Submitted', 'New GCASH payment proof submitted for TXN-00033. Ref: 122334557. Please verify.', 0, 'billing', '2026-04-14 02:58:51'),
(226, 1, 'New Product Purchase', 'Product purchase by Shanley Resentes — Total: ₱500.00 (pending payment).', 0, 'billing', '2026-04-14 03:02:01'),
(227, 1, 'New Product Purchase', 'Product purchase by Shanley Resentes — Total: ₱150.00 (pending payment).', 0, 'billing', '2026-04-14 03:50:34'),
(228, 1, 'Payment Proof Submitted', 'New GCASH payment proof submitted for TXN-00036. Ref: 64745537. Please verify.', 0, 'billing', '2026-04-14 03:51:19'),
(230, 1, 'Payment Proof Submitted', 'New GCASH payment proof submitted for TXN-00036. Ref: 656543. Please verify.', 0, 'billing', '2026-04-14 03:53:34'),
(232, 1, 'New Product Purchase', 'Product purchase by Shanley Resentes — Total: ₱150.00 (pending payment).', 0, 'billing', '2026-04-14 04:00:06'),
(233, 1, 'Payment Proof Submitted', 'New GCASH payment proof submitted for TXN-00037. Ref: 387828538. Please verify.', 0, 'billing', '2026-04-14 04:00:23'),
(235, 1, 'New Product Purchase', 'Product purchase by Shanley Resentes — Total: ₱150.00 (pending payment).', 0, 'billing', '2026-04-14 04:32:31'),
(236, 1, 'Payment Proof Submitted', 'New GCASH payment proof submitted for TXN-00038. Ref: 636337387. Please verify.', 0, 'billing', '2026-04-14 04:32:53'),
(238, 1, 'New Product Purchase', 'Product purchase by Shanley Resentes — Total: ₱200.00 (pending payment).', 0, 'billing', '2026-04-14 04:43:08'),
(239, 1, 'Payment Proof Submitted', 'New GCASH payment proof submitted for TXN-00039. Ref: 76477. Please verify.', 0, 'billing', '2026-04-14 04:43:43'),
(241, 1, 'New Product Purchase', 'Product purchase by Shanley Resentes — Total: ₱200.00 (pending payment).', 0, 'billing', '2026-04-14 04:44:31'),
(242, 1, 'Payment Proof Submitted', 'New GCASH payment proof submitted for TXN-00040. Ref: 7352582. Please verify.', 0, 'billing', '2026-04-14 04:44:50'),
(244, 1, '📅 Appointment Today/Tomorrow', 'Upcoming: Ana Santos&#039;s CheckUp appointment for Browny on Apr 14, 2026 at 1:00 PM. [appt:39]', 0, 'appt_reminder_admin', '2026-04-14 04:59:24'),
(245, 1, '📦 Low Stock Alert', 'Low stock alert: &quot;Royal Care Wound Healing Cream&quot; has only 1 item(s) remaining. Please restock soon. [prod:19]', 0, 'low_stock', '2026-04-14 04:59:24'),
(246, 1, '📦 Low Stock Alert', 'Low stock alert: &quot;Lactusole&quot; has only 2 item(s) remaining. Please restock soon. [prod:14]', 0, 'low_stock', '2026-04-14 04:59:24'),
(247, 1, '📦 Low Stock Alert', 'Low stock alert: &quot;Pet Toothpaste&quot; has only 3 item(s) remaining. Please restock soon. [prod:17]', 0, 'low_stock', '2026-04-14 04:59:24'),
(248, 1, '📦 Low Stock Alert', 'Low stock alert: &quot;DR. Shiba Happy Tummy (Beef Flavour)&quot; has only 4 item(s) remaining. Please restock soon. [prod:9]', 0, 'low_stock', '2026-04-14 04:59:24'),
(249, 1, '📦 Low Stock Alert', 'Low stock alert: &quot;Dono Disposable Diapers (Large)&quot; has only 5 item(s) remaining. Please restock soon. [prod:12]', 0, 'low_stock', '2026-04-14 04:59:24');

-- --------------------------------------------------------

--
-- Table structure for table `payment_proofs`
--

CREATE TABLE `payment_proofs` (
  `id` int(11) NOT NULL,
  `transaction_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `method` enum('gcash','paymaya') NOT NULL,
  `reference_number` varchar(100) NOT NULL,
  `proof_image` varchar(255) NOT NULL,
  `status` enum('pending','verified','rejected') NOT NULL DEFAULT 'pending',
  `admin_note` varchar(255) DEFAULT NULL,
  `submitted_at` datetime DEFAULT current_timestamp(),
  `verified_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payment_proofs`
--

INSERT INTO `payment_proofs` (`id`, `transaction_id`, `user_id`, `method`, `reference_number`, `proof_image`, `status`, `admin_note`, `submitted_at`, `verified_at`) VALUES
(1, 33, 9, 'gcash', '122334557', 'proof_33_1776135531.png', 'verified', '', '2026-04-14 10:58:51', '2026-04-14 04:59:39');

-- --------------------------------------------------------

--
-- Table structure for table `pets`
--

CREATE TABLE `pets` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `species` enum('dog','cat','other') NOT NULL,
  `breed` varchar(100) DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `age` varchar(20) DEFAULT NULL,
  `gender` enum('male','female') NOT NULL,
  `weight` decimal(5,2) DEFAULT NULL,
  `color` varchar(100) DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `archived` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pets`
--

INSERT INTO `pets` (`id`, `user_id`, `name`, `species`, `breed`, `date_of_birth`, `age`, `gender`, `weight`, `color`, `photo`, `status`, `created_at`, `archived`) VALUES
(1, 2, 'Browny', 'dog', 'Aspin', '2021-03-05', '5', 'male', 10.00, 'Brown-white', 'pet_1774229079_963.png', 'active', '2026-03-18 07:31:09', 0),
(2, 2, 'Bella', 'cat', 'Perssian', '2019-02-05', '7', 'female', 5.00, 'Light brown', 'pet_1774229099_774.jpg', 'active', '2026-03-18 07:36:33', 0),
(3, 4, 'Tofu', 'cat', 'Khao manee', '2023-12-06', '3', 'female', 7.00, 'White', 'pet_1774227629_419.jpg', 'active', '2026-03-22 11:22:03', 0),
(6, 9, 'Laufey', 'dog', 'Chow chow', '2024-09-08', '1', 'male', 14.00, 'Cinnamon', 'pet_1774335709_941.jpg', 'active', '2026-03-24 07:01:49', 0),
(8, 11, 'Kagata', 'dog', 'Chow chow', '2024-01-08', '1', 'male', 8.00, 'Gray', 'pet_1775440723_255.jpg', '', '2026-04-06 01:58:43', 1);

-- --------------------------------------------------------

--
-- Table structure for table `pet_allergies`
--

CREATE TABLE `pet_allergies` (
  `id` int(11) NOT NULL,
  `pet_id` int(11) NOT NULL,
  `allergen` varchar(150) NOT NULL,
  `reaction` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pet_allergies`
--

INSERT INTO `pet_allergies` (`id`, `pet_id`, `allergen`, `reaction`, `created_at`) VALUES
(1, 1, 'Pollen', 'Itchy nose and watery eyes', '2026-03-22 04:55:46'),
(2, 1, 'Bees', 'Swollen tongue and racing heartbeat', '2026-03-22 04:56:08'),
(3, 2, 'Chicken', 'Skin rashes', '2026-03-22 05:04:56'),
(4, 2, 'Dust', 'Sneezing', '2026-03-22 05:05:13');

-- --------------------------------------------------------

--
-- Table structure for table `pet_consultations`
--

CREATE TABLE `pet_consultations` (
  `id` int(11) NOT NULL,
  `pet_id` int(11) NOT NULL,
  `date_of_visit` date NOT NULL,
  `reason_for_visit` varchar(255) DEFAULT NULL,
  `diagnosis` text DEFAULT NULL,
  `treatment_given` text DEFAULT NULL,
  `vet_name` varchar(150) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `followup_date` date DEFAULT NULL,
  `is_done` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pet_consultations`
--

INSERT INTO `pet_consultations` (`id`, `pet_id`, `date_of_visit`, `reason_for_visit`, `diagnosis`, `treatment_given`, `vet_name`, `notes`, `created_at`, `followup_date`, `is_done`) VALUES
(1, 2, '2026-02-01', 'Loss of appetite', 'Mild Gastrointestinal infection', 'Prescribed antibiotics and vitamins', 'Dr. Ann', '', '2026-03-22 05:22:12', NULL, 0),
(2, 1, '2026-02-01', 'Loss of appetite', 'Mild Gastrointestinal infection', 'Prescribed antibiotics and vitamins', 'Dr. Ann', '', '2026-03-22 05:23:19', NULL, 0),
(3, 2, '2026-03-20', 'Checkup', 'Cold', 'Antihistamine', 'Dr. Ann', '', '2026-03-24 00:31:52', NULL, 0),
(4, 2, '2026-03-19', 'Checkup', 'Fever', 'Antibiotics', 'Dr. Ann', '', '2026-03-24 01:06:22', NULL, 0),
(5, 2, '2026-02-09', 'loss of appetite', 'Fever', 'vitamins', 'Dr. Ann', '', '2026-03-24 01:07:01', NULL, 0),
(6, 2, '2026-01-07', 'Checkup', 'Cold', 'amoxicillin', 'Dr. Ann', '', '2026-03-24 01:07:38', NULL, 0),
(7, 2, '2026-03-18', 'Checkup', 'Fever', 'vitmins', 'Dr. Ann', '', '2026-03-24 01:08:12', NULL, 0),
(8, 6, '2026-03-28', 'Checkup', 'Hot spot', 'Vetericyn - Hot spot spray', 'Dr. Ann', '', '2026-04-05 06:37:05', NULL, 0),
(9, 6, '2026-03-30', 'Follow up Check up', 'Hot spot', 'Vetericyn - Hot spot spray', 'Dr. Ann', '', '2026-04-05 06:45:41', NULL, 0);

-- --------------------------------------------------------

--
-- Table structure for table `pet_documents`
--

CREATE TABLE `pet_documents` (
  `id` int(10) UNSIGNED NOT NULL,
  `pet_id` int(10) UNSIGNED NOT NULL,
  `uploaded_by` int(10) UNSIGNED NOT NULL,
  `document_type` varchar(60) NOT NULL DEFAULT 'general',
  `title` varchar(200) NOT NULL,
  `file_name` varchar(300) NOT NULL,
  `original_name` varchar(300) NOT NULL,
  `file_size` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `mime_type` varchar(100) NOT NULL DEFAULT '',
  `notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `pet_documents`
--

INSERT INTO `pet_documents` (`id`, `pet_id`, `uploaded_by`, `document_type`, `title`, `file_name`, `original_name`, `file_size`, `mime_type`, `notes`, `created_at`) VALUES
(1, 6, 1, 'photo', 'Lab Results', 'doc_6_1776143893_85903925.png', '664209820_1345475537407290_7758245040400584860_n.png', 33174, 'image/png', '', '2026-04-14 13:18:13');

-- --------------------------------------------------------

--
-- Table structure for table `pet_medical_history`
--

CREATE TABLE `pet_medical_history` (
  `id` int(11) NOT NULL,
  `pet_id` int(11) NOT NULL,
  `clinic_name` varchar(150) DEFAULT NULL,
  `past_illnesses` text DEFAULT NULL,
  `chronic_conditions` text DEFAULT NULL,
  `injuries_surgeries` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pet_medical_history`
--

INSERT INTO `pet_medical_history` (`id`, `pet_id`, `clinic_name`, `past_illnesses`, `chronic_conditions`, `injuries_surgeries`, `notes`, `updated_at`) VALUES
(1, 2, 'Pawprint', 'Gastrointestinal infection', 'Kidney', 'None', '', '2026-03-24 00:31:12'),
(2, 1, 'Pawprint Veterinary', 'Gastrointestinal infection', 'None', 'None', '', '2026-03-22 05:23:25'),
(3, 2, 'Pawprint Veterinary', 'Gastrointestinal infection', 'Kidney', '', '', '2026-03-24 00:39:01'),
(4, 2, 'Cat & Dog Veterinary', 'Gastrointestinal infection', '', '', '', '2026-03-24 01:13:41'),
(5, 2, 'Petcare Veterinary', 'Gastrointestinal infection', 'None', 'None', '', '2026-03-24 01:04:46'),
(6, 2, 'Vetcare Veterinary', 'Gastrointestinal infection', 'None', 'None', '', '2026-03-24 01:05:08'),
(7, 2, 'Ligao Veterinary', 'Gastrointestinal infection', 'None', 'None', '', '2026-03-24 01:05:18');

-- --------------------------------------------------------

--
-- Table structure for table `pet_medications`
--

CREATE TABLE `pet_medications` (
  `id` int(11) NOT NULL,
  `pet_id` int(11) NOT NULL,
  `medication_name` varchar(150) NOT NULL,
  `dosage` varchar(100) DEFAULT NULL,
  `frequency` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `prescribed_by` varchar(100) DEFAULT NULL,
  `prescription_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pet_medications`
--

INSERT INTO `pet_medications` (`id`, `pet_id`, `medication_name`, `dosage`, `frequency`, `notes`, `prescribed_by`, `prescription_date`, `created_at`) VALUES
(1, 1, 'Amoxicillin', '5 mg', 'Twice a Day', '', NULL, NULL, '2026-03-22 04:53:25'),
(2, 1, 'Antihistamine', '2 mg', 'Once a Day', '1 week medication', '', NULL, '2026-03-22 04:53:47'),
(3, 2, 'Amoxicillin', '5 mg', 'Twice a Day', '', NULL, NULL, '2026-03-22 05:02:56'),
(4, 2, 'Antihistamine', '2 mg', 'Once a Day', '', NULL, NULL, '2026-03-22 05:03:10'),
(5, 6, 'Vetericyn - Hot spot sprayer', '', '2x a day', 'Spray this medicine in the morning and in evening', 'Dr. Ann', '2026-03-30', '2026-04-05 07:01:11');

-- --------------------------------------------------------

--
-- Table structure for table `pet_vaccines`
--

CREATE TABLE `pet_vaccines` (
  `id` int(11) NOT NULL,
  `pet_id` int(11) NOT NULL,
  `vaccine_name` varchar(150) NOT NULL,
  `date_given` date DEFAULT NULL,
  `next_due` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pet_vaccines`
--

INSERT INTO `pet_vaccines` (`id`, `pet_id`, `vaccine_name`, `date_given`, `next_due`, `notes`, `created_at`) VALUES
(1, 1, 'Rabies', '2026-02-05', '2027-02-05', '', '2026-03-22 04:54:51'),
(2, 1, 'DHPP', '2026-03-05', '2027-03-05', '', '2026-03-22 04:55:22'),
(6, 2, 'Rabies', '2026-02-28', '2027-02-28', '', '2026-03-24 01:10:06'),
(7, 2, 'Rabies', '2026-02-28', '2027-02-28', '', '2026-03-24 01:11:32'),
(8, 2, 'DHPP', '2026-03-24', '2026-03-24', '', '2026-03-24 01:12:08');

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `category` enum('pet_care','pet_supplies') DEFAULT 'pet_care',
  `price` decimal(10,2) NOT NULL,
  `quantity` int(11) DEFAULT 0,
  `expiry_date` date DEFAULT NULL,
  `status` enum('in_stock','low_stock','out_of_stock') DEFAULT 'in_stock',
  `image` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `name`, `category`, `price`, `quantity`, `expiry_date`, `status`, `image`, `created_at`) VALUES
(1, 'Groom and Bloom Shampoo', 'pet_care', 280.00, 11, '2027-02-01', 'in_stock', 'prod_1774153587.png', '2026-03-18 06:43:51'),
(2, 'Activated Charcoal Pet Shampoo', 'pet_care', 280.00, 9, '2027-01-01', 'in_stock', 'prod_1774153514.png', '2026-03-18 06:43:51'),
(3, 'Dermovet', 'pet_care', 200.00, 15, '2026-08-01', 'in_stock', 'prod_1774153556.png', '2026-03-18 06:43:51'),
(4, 'Pedigree', 'pet_care', 55.00, 20, '2026-09-01', 'in_stock', 'prod_1774153604.png', '2026-03-18 06:43:51'),
(5, 'Collar', 'pet_supplies', 150.00, 15, NULL, 'in_stock', 'prod_1774153549.jpg', '2026-03-18 06:43:51'),
(6, 'Toys', 'pet_supplies', 100.00, 19, NULL, 'in_stock', 'prod_1774153614.jpg', '2026-03-18 06:43:51'),
(7, 'Cage for Puppy and Kittens', 'pet_supplies', 500.00, 14, NULL, 'in_stock', 'prod_1774153529.jpg', '2026-03-18 06:43:51'),
(8, 'Large Bowl', 'pet_supplies', 300.00, 10, NULL, 'in_stock', 'prod_1774153595.png', '2026-03-18 06:43:51'),
(9, 'DR. Shiba Happy Tummy (Beef Flavour)', 'pet_supplies', 120.00, 14, '2027-11-03', 'in_stock', 'prod_1774664553.png', '2026-03-28 01:39:38'),
(10, 'Dental Care Set', 'pet_care', 280.00, 7, NULL, 'in_stock', 'prod_1774664513.png', '2026-03-28 01:41:43'),
(11, 'Pro Diet (Ocean Fish) 85g', 'pet_supplies', 55.00, 7, '2026-11-12', 'in_stock', 'prod_1774664723.png', '2026-03-28 01:45:05'),
(12, 'Dono Disposable Diapers (Large)', 'pet_supplies', 50.00, 5, NULL, 'low_stock', 'prod_1774664529.png', '2026-03-28 01:46:09'),
(13, 'Dono Dsposable Male Wraps (xsmall)', 'pet_supplies', 30.00, 12, NULL, 'in_stock', 'prod_1774664541.png', '2026-03-28 01:47:30'),
(14, 'Lactusole', 'pet_care', 350.00, 2, '2028-08-01', 'low_stock', 'prod_1774664584.png', '2026-03-28 01:48:55'),
(15, 'Papi Bion Plus', 'pet_supplies', 380.00, 20, '2027-06-01', 'in_stock', 'prod_1774664605.png', '2026-03-28 01:50:27'),
(16, 'Anti Tick and Flea Soap', 'pet_care', 200.00, 13, '2028-03-01', 'in_stock', 'prod_1774664499.png', '2026-03-28 01:51:41'),
(17, 'Pet Toothpaste', 'pet_care', 150.00, 3, NULL, 'low_stock', 'prod_1774664630.png', '2026-03-28 01:53:15'),
(18, 'Papi Pet Powder', 'pet_care', 180.00, 12, '2028-05-01', 'in_stock', 'prod_1774664617.png', '2026-03-28 01:55:04'),
(19, 'Royal Care Wound Healing Cream', 'pet_care', 280.00, 1, '2028-07-19', 'low_stock', 'prod_1774664664.png', '2026-03-28 01:56:54'),
(20, 'Coat Maintenance Herbal Soap', 'pet_care', 180.00, 15, '2028-05-01', 'in_stock', 'prod_1774664453.png', '2026-03-28 01:59:16'),
(21, 'Abalone Beef', 'pet_care', 10.00, 28, '2027-09-11', 'in_stock', 'prod_1774664476.png', '2026-03-28 02:00:25');

-- --------------------------------------------------------

--
-- Table structure for table `promotions`
--

CREATE TABLE `promotions` (
  `id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `icon` varchar(20) DEFAULT '?',
  `image` varchar(300) DEFAULT NULL,
  `link_label` varchar(100) DEFAULT 'Learn More',
  `link_url` varchar(200) DEFAULT 'appointments.php',
  `sort_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `queue_entries`
--

CREATE TABLE `queue_entries` (
  `id` int(11) NOT NULL,
  `appointment_id` int(11) NOT NULL,
  `queue_number` int(11) NOT NULL,
  `status` enum('waiting','in_progress','done') DEFAULT 'waiting',
  `queue_date` date NOT NULL DEFAULT curdate(),
  `called_at` timestamp NULL DEFAULT NULL,
  `done_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ratings`
--

CREATE TABLE `ratings` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `appointment_id` int(11) NOT NULL,
  `stars` tinyint(1) NOT NULL DEFAULT 5,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `ratings`
--

INSERT INTO `ratings` (`id`, `user_id`, `appointment_id`, `stars`, `created_at`) VALUES
(1, 2, 4, 5, '2026-03-22 06:50:16'),
(2, 2, 11, 5, '2026-03-24 01:29:50'),
(3, 9, 16, 5, '2026-03-24 08:13:15'),
(4, 9, 17, 5, '2026-03-24 08:44:55'),
(5, 9, 18, 5, '2026-03-24 08:55:09'),
(6, 9, 19, 5, '2026-03-24 12:46:33'),
(7, 9, 14, 5, '2026-03-26 12:52:57'),
(8, 9, 22, 5, '2026-03-28 02:34:27'),
(9, 9, 23, 5, '2026-03-28 03:16:50'),
(10, 9, 24, 5, '2026-03-31 12:55:30'),
(11, 9, 25, 5, '2026-04-05 14:00:58'),
(12, 2, 29, 5, '2026-04-10 03:14:37'),
(13, 2, 34, 5, '2026-04-11 13:32:28'),
(14, 9, 35, 5, '2026-04-11 15:23:19'),
(15, 9, 15, 5, '2026-04-11 15:51:51'),
(16, 9, 38, 5, '2026-04-14 03:59:55');

-- --------------------------------------------------------

--
-- Table structure for table `services`
--

CREATE TABLE `services` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `price_min` decimal(10,2) DEFAULT 0.00,
  `price_max` decimal(10,2) DEFAULT 0.00,
  `category` varchar(100) DEFAULT NULL,
  `status` enum('available','not_available') DEFAULT 'available',
  `image` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `services`
--

INSERT INTO `services` (`id`, `name`, `description`, `price_min`, `price_max`, `category`, `status`, `image`, `created_at`) VALUES
(1, 'CheckUp', 'A general health examination of your pet to monitor its condition, detect illnesses early, and ensure it is healthy.', 300.00, 400.00, 'veterinary', 'available', NULL, '2026-03-18 06:43:51'),
(2, 'Confinement', 'A service where pets stay in the clinic for observation, treatment, and care when they are sick or recovering.', 500.00, 1500.00, 'veterinary', 'available', NULL, '2026-03-18 06:43:51'),
(3, 'Treatment', 'Medical care provided to pets to cure illnesses, infections, or injuries through proper diagnosis and medication.', 300.00, 500.00, 'veterinary', 'available', NULL, '2026-03-18 06:43:51'),
(4, 'Deworming', 'A procedure that removes internal parasites such as worms to keep pets healthy and prevent digestive problems.', 100.00, 300.00, 'veterinary', 'available', NULL, '2026-03-18 06:43:51'),
(5, 'Vaccination', 'The administration of vaccines to protect pets from dangerous diseases and strengthen their immune system.', 350.00, 550.00, 'veterinary', 'available', NULL, '2026-03-18 06:43:51'),
(6, 'Grooming', 'Cleaning and maintenance of a pets hygiene including bathing, hair trimming, nail cutting, and ear cleaning.', 500.00, 1000.00, 'grooming', 'available', NULL, '2026-03-18 06:43:51'),
(7, 'Surgery', 'Medical operations performed by a veterinarian to treat injuries, diseases, or conditions that require surgical procedures.', 1000.00, 10000.00, 'veterinary', 'available', NULL, '2026-03-18 06:43:51'),
(8, 'Laboratory', 'Diagnostic tests such as blood tests, fecal exams, or other analyses to help veterinarians accurately diagnose pet illnesses.', 800.00, 2200.00, 'veterinary', 'available', NULL, '2026-03-18 06:43:51'),
(9, 'Home Service', 'Veterinary care provided at the pet owners home for check-ups, vaccinations, or basic treatments for convenience.', 500.00, 1500.00, 'home', 'available', NULL, '2026-03-18 06:43:51');

-- --------------------------------------------------------

--
-- Table structure for table `sms_reminders`
--

CREATE TABLE `sms_reminders` (
  `id` int(11) NOT NULL,
  `appointment_id` int(11) NOT NULL,
  `reminder_type` enum('24h','1h') NOT NULL,
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `transactions`
--

CREATE TABLE `transactions` (
  `id` int(11) NOT NULL,
  `appointment_id` int(11) DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `pet_id` int(11) DEFAULT NULL,
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `status` enum('paid','pending','overdue') DEFAULT 'pending',
  `transaction_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `archived` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `transactions`
--

INSERT INTO `transactions` (`id`, `appointment_id`, `user_id`, `pet_id`, `total_amount`, `status`, `transaction_date`, `notes`, `created_at`, `archived`) VALUES
(2, 4, 2, 1, 555.00, 'paid', '2026-03-21', '', '2026-03-21 06:31:00', 0),
(3, NULL, 2, 1, 150.00, 'paid', '2026-03-22', 'Product purchase by user', '2026-03-22 11:15:46', 0),
(4, NULL, 4, NULL, 560.00, 'paid', '2026-03-22', 'Product purchase by user', '2026-03-22 11:25:57', 0),
(5, NULL, 2, 1, 800.00, 'paid', '2026-03-23', '', '2026-03-23 02:43:08', 0),
(6, NULL, 4, 3, 300.00, 'paid', '2026-03-23', '', '2026-03-23 02:43:58', 0),
(7, NULL, 4, 3, 800.00, 'paid', '2026-03-23', '', '2026-03-23 02:54:22', 0),
(8, NULL, 2, 1, 300.00, 'paid', '2026-03-23', '', '2026-03-23 03:14:22', 0),
(9, NULL, 2, 1, 300.00, 'paid', '2026-03-23', '', '2026-03-23 03:27:22', 0),
(10, NULL, 2, 1, 150.00, 'paid', '2026-03-23', 'Product purchase by user', '2026-03-23 04:37:11', 0),
(11, NULL, 2, 1, 150.00, 'paid', '2026-03-23', 'Product purchase by user', '2026-03-23 06:28:54', 0),
(17, NULL, 9, 6, 500.00, 'paid', '2026-03-26', 'Product purchase by user', '2026-03-26 13:56:25', 0),
(19, 22, 9, 6, 300.00, 'paid', '2026-03-28', NULL, '2026-03-28 02:33:12', 0),
(20, NULL, 9, 6, 500.00, 'paid', '2026-03-28', NULL, '2026-03-28 02:36:33', 0),
(21, 24, 9, 6, 300.00, 'paid', '2026-03-31', NULL, '2026-03-31 12:36:35', 0),
(24, NULL, 11, 8, 380.00, 'paid', '2026-04-06', 'Product purchase by user', '2026-04-06 02:01:22', 0),
(25, 25, 9, 6, 100.00, 'paid', '2026-04-06', NULL, '2026-04-06 04:34:30', 0),
(26, 26, 11, 8, 350.00, 'paid', '2026-04-06', NULL, '2026-04-06 04:34:42', 0),
(27, 27, 11, NULL, 0.00, 'paid', '2026-04-07', NULL, '2026-04-07 13:04:28', 1),
(29, 29, 2, 1, 300.00, 'paid', '2026-04-09', NULL, '2026-04-09 15:13:29', 0),
(30, 34, 2, 1, 300.00, 'paid', '2026-04-11', NULL, '2026-04-11 13:31:14', 0),
(33, 38, 9, 6, 300.00, 'paid', '2026-04-14', NULL, '2026-04-14 02:04:28', 0);

-- --------------------------------------------------------

--
-- Table structure for table `transaction_items`
--

CREATE TABLE `transaction_items` (
  `id` int(11) NOT NULL,
  `transaction_id` int(11) NOT NULL,
  `item_type` enum('service','product') DEFAULT 'service',
  `item_name` varchar(150) NOT NULL,
  `price` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `transaction_items`
--

INSERT INTO `transaction_items` (`id`, `transaction_id`, `item_type`, `item_name`, `price`) VALUES
(2, 2, 'service', 'Checkup', 500.00),
(3, 2, 'product', 'Pedigree', 55.00),
(4, 3, 'product', 'Collar', 150.00),
(5, 4, 'product', 'Groom and Bloom Shampoo x2', 560.00),
(6, 5, 'service', 'CheckUp', 300.00),
(7, 5, 'product', 'Cage for Puppy and Kittens', 500.00),
(8, 6, 'service', 'CheckUp', 300.00),
(9, 6, 'product', 'Cage for Puppy and Kittens', 0.00),
(10, 7, 'service', 'CheckUp', 300.00),
(11, 7, 'product', 'Cage for Puppy and Kittens [SOLD OUT]', 500.00),
(12, 8, 'service', 'CheckUp', 300.00),
(13, 9, 'service', 'CheckUp', 300.00),
(14, 10, 'product', 'Collar', 150.00),
(15, 11, 'product', 'Collar', 150.00),
(22, 17, 'product', 'Cage for Puppy and Kittens', 500.00),
(24, 20, 'service', 'Grooming', 500.00),
(26, 24, 'product', 'Papi Bion Plus', 380.00);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `address` varchar(255) DEFAULT NULL,
  `contact_no` varchar(20) DEFAULT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `profile_picture` varchar(255) DEFAULT NULL,
  `gender` enum('male','female') DEFAULT 'female',
  `role` enum('user','admin','staff') DEFAULT 'user',
  `position` varchar(100) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `archived` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `address`, `contact_no`, `email`, `password`, `profile_picture`, `gender`, `role`, `position`, `status`, `created_at`, `updated_at`, `archived`) VALUES
(1, 'Dr. Ann Lawrence S. Polidario', 'National Highway, Zone 4, Tuburan, Ligao City, Albay', '0926-396-7678', 'admin@ligaopetcare.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'av_adm_1_1775185557.jpg', 'female', 'admin', NULL, 'active', '2026-03-18 06:43:51', '2026-04-03 03:05:57', 0),
(2, 'Ana Santos', 'Tuburan, Ligao City, Albay', '09123737786', 'anasantos123@gmail.com', '$2y$10$UxSew.fBlbGBK1P357/U.eCOoAyB0Cp.zxLtpk4GaQYeR/zwnrax2', 'avatar_2_1775185537.jpg', 'female', 'user', NULL, 'active', '2026-03-18 07:13:08', '2026-04-03 03:05:37', 0),
(3, 'Penelope Sablayan', 'Napo,Polangui, Albay', '123456789', 'penelopesablayan@gmail.com', '$2y$10$NJfziTmsXelzWMf8sCoG3uAaqWvIKN.atb4LafYDtox1kgB1e7d7K', NULL, 'female', 'user', NULL, 'active', '2026-03-21 03:22:48', '2026-03-21 03:22:48', 0),
(4, 'Pauline Sablayan', 'Napo,Polangui, Albay', '123456789', 'paulinesablayan@gmail.com', '$2y$10$ew7ND..4exedRTWCrsL2eesV/gNIJLCh5nFNCx/SKEVpnRbpOYwuy', NULL, 'female', 'user', NULL, 'active', '2026-03-21 03:28:58', '2026-03-21 03:28:58', 0),
(6, 'Dr. Ann Lawrence S. Polidario', 'National Highway, Zone 4, Tuburan, Ligao City, Albay', '0926-396-7678', 'drann@ligaopetcare.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, 'female', 'staff', 'Veterinarian', 'active', '2026-03-22 12:03:11', '2026-03-24 02:42:20', 0),
(7, 'Kristen Barnedo', 'Ligao City, Albay', '', 'assistant@ligaopetcare.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'av_adm_7_1775185576.jpg', 'female', 'admin', 'Clinic Staff', 'active', '2026-03-22 12:03:11', '2026-04-03 03:06:16', 0),
(9, 'Shanley Resentes', 'Calzada, Oas, Albay', '09075193156', 'shanleyresentes@gmail.com', '$2y$10$2MjmvUuVMuINHuPz/a9fI.eIvUdewYHrBCPFZBvZ0M.sDQW4d9OOO', 'avatar_9_1775185703.jpg', 'female', 'user', NULL, 'active', '2026-03-24 06:46:34', '2026-04-03 03:08:23', 0),
(11, 'Rose Blue', 'Bu Polangui', '09524658618', 'roseblue123@gmail.com', '$2y$10$SGD2pRlaO2wbYeCxCUsi1ey6CR9eABnYUFXLt83OtdxlVvtcGEP5K', NULL, 'female', 'user', NULL, '', '2026-04-06 01:56:36', '2026-04-08 13:44:31', 1),
(12, 'Customer', 'Binatagan, Ligao City, Albay', '09654575995', 'customer123@gmail.com', '$2y$10$9nWKmq7CdQa.9xqVrx9niufm70yE.lKZ6j3Wq9RfgoqjQHkSYgq4W', NULL, 'female', 'user', NULL, 'active', '2026-04-13 06:35:18', '2026-04-13 06:35:18', 0);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user` (`user_id`,`created_at`),
  ADD KEY `idx_role` (`role`),
  ADD KEY `idx_module` (`module`),
  ADD KEY `idx_action` (`action`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indexes for table `announcements`
--
ALTER TABLE `announcements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `posted_by` (`posted_by`);

--
-- Indexes for table `appointments`
--
ALTER TABLE `appointments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `pet_id` (`pet_id`),
  ADD KEY `service_id` (`service_id`);

--
-- Indexes for table `home_service_pets`
--
ALTER TABLE `home_service_pets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `appointment_id` (`appointment_id`),
  ADD KEY `service_id` (`service_id`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sender_id` (`sender_id`),
  ADD KEY `receiver_id` (`receiver_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `payment_proofs`
--
ALTER TABLE `payment_proofs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `transaction_id` (`transaction_id`);

--
-- Indexes for table `pets`
--
ALTER TABLE `pets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `pet_allergies`
--
ALTER TABLE `pet_allergies`
  ADD PRIMARY KEY (`id`),
  ADD KEY `pet_id` (`pet_id`);

--
-- Indexes for table `pet_consultations`
--
ALTER TABLE `pet_consultations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `pet_id` (`pet_id`);

--
-- Indexes for table `pet_documents`
--
ALTER TABLE `pet_documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_pet` (`pet_id`),
  ADD KEY `idx_uploader` (`uploaded_by`),
  ADD KEY `idx_type` (`document_type`);

--
-- Indexes for table `pet_medical_history`
--
ALTER TABLE `pet_medical_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `pet_id` (`pet_id`);

--
-- Indexes for table `pet_medications`
--
ALTER TABLE `pet_medications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `pet_id` (`pet_id`);

--
-- Indexes for table `pet_vaccines`
--
ALTER TABLE `pet_vaccines`
  ADD PRIMARY KEY (`id`),
  ADD KEY `pet_id` (`pet_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `promotions`
--
ALTER TABLE `promotions`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `queue_entries`
--
ALTER TABLE `queue_entries`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_appt` (`appointment_id`,`queue_date`);

--
-- Indexes for table `ratings`
--
ALTER TABLE `ratings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_appt` (`user_id`,`appointment_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `appointment_id` (`appointment_id`);

--
-- Indexes for table `services`
--
ALTER TABLE `services`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `sms_reminders`
--
ALTER TABLE `sms_reminders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_appt_type` (`appointment_id`,`reminder_type`);

--
-- Indexes for table `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `appointment_id` (`appointment_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `pet_id` (`pet_id`);

--
-- Indexes for table `transaction_items`
--
ALTER TABLE `transaction_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `transaction_id` (`transaction_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `announcements`
--
ALTER TABLE `announcements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `appointments`
--
ALTER TABLE `appointments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=40;

--
-- AUTO_INCREMENT for table `home_service_pets`
--
ALTER TABLE `home_service_pets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=250;

--
-- AUTO_INCREMENT for table `payment_proofs`
--
ALTER TABLE `payment_proofs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `pets`
--
ALTER TABLE `pets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `pet_allergies`
--
ALTER TABLE `pet_allergies`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `pet_consultations`
--
ALTER TABLE `pet_consultations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `pet_documents`
--
ALTER TABLE `pet_documents`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `pet_medical_history`
--
ALTER TABLE `pet_medical_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `pet_medications`
--
ALTER TABLE `pet_medications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `pet_vaccines`
--
ALTER TABLE `pet_vaccines`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `promotions`
--
ALTER TABLE `promotions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `queue_entries`
--
ALTER TABLE `queue_entries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ratings`
--
ALTER TABLE `ratings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `services`
--
ALTER TABLE `services`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `sms_reminders`
--
ALTER TABLE `sms_reminders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT for table `transaction_items`
--
ALTER TABLE `transaction_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `announcements`
--
ALTER TABLE `announcements`
  ADD CONSTRAINT `announcements_ibfk_1` FOREIGN KEY (`posted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `appointments`
--
ALTER TABLE `appointments`
  ADD CONSTRAINT `appointments_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `appointments_ibfk_2` FOREIGN KEY (`pet_id`) REFERENCES `pets` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `appointments_ibfk_3` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `home_service_pets`
--
ALTER TABLE `home_service_pets`
  ADD CONSTRAINT `home_service_pets_ibfk_1` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `home_service_pets_ibfk_2` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payment_proofs`
--
ALTER TABLE `payment_proofs`
  ADD CONSTRAINT `payment_proofs_ibfk_1` FOREIGN KEY (`transaction_id`) REFERENCES `transactions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `pets`
--
ALTER TABLE `pets`
  ADD CONSTRAINT `pets_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `pet_allergies`
--
ALTER TABLE `pet_allergies`
  ADD CONSTRAINT `pet_allergies_ibfk_1` FOREIGN KEY (`pet_id`) REFERENCES `pets` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `pet_consultations`
--
ALTER TABLE `pet_consultations`
  ADD CONSTRAINT `pc_pet_fk` FOREIGN KEY (`pet_id`) REFERENCES `pets` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `pet_medical_history`
--
ALTER TABLE `pet_medical_history`
  ADD CONSTRAINT `pmh_pet_fk` FOREIGN KEY (`pet_id`) REFERENCES `pets` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `pet_medications`
--
ALTER TABLE `pet_medications`
  ADD CONSTRAINT `pet_medications_ibfk_1` FOREIGN KEY (`pet_id`) REFERENCES `pets` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `pet_vaccines`
--
ALTER TABLE `pet_vaccines`
  ADD CONSTRAINT `pet_vaccines_ibfk_1` FOREIGN KEY (`pet_id`) REFERENCES `pets` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `queue_entries`
--
ALTER TABLE `queue_entries`
  ADD CONSTRAINT `queue_entries_ibfk_1` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `transactions`
--
ALTER TABLE `transactions`
  ADD CONSTRAINT `transactions_ibfk_1` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `transactions_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `transactions_ibfk_3` FOREIGN KEY (`pet_id`) REFERENCES `pets` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `transaction_items`
--
ALTER TABLE `transaction_items`
  ADD CONSTRAINT `transaction_items_ibfk_1` FOREIGN KEY (`transaction_id`) REFERENCES `transactions` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
