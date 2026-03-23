-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 23, 2026 at 10:10 AM
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
  `cancellation_reason` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `appointments`
--

INSERT INTO `appointments` (`id`, `user_id`, `pet_id`, `service_id`, `appointment_type`, `appointment_date`, `appointment_time`, `address`, `contact`, `status`, `notes`, `created_at`, `cancellation_reason`) VALUES
(3, 2, NULL, NULL, 'home_service', '2026-04-25', '13:00:00', 'Tuburan, Ligao City Albay', '09656658993', 'cancelled', NULL, '2026-03-18 11:05:10', NULL),
(4, 2, 1, 1, 'clinic', '2026-03-19', '10:00:00', NULL, NULL, 'completed', NULL, '2026-03-19 01:53:49', NULL),
(8, 2, NULL, NULL, 'home_service', '2026-04-15', '13:00:00', 'Tuburan, Ligao City, Albay', '09123737786', 'pending', NULL, '2026-03-23 05:29:56', NULL),
(9, 2, NULL, NULL, 'home_service', '2026-03-30', '13:00:00', 'Tuburan, Ligao City, Albay', '09123737786', 'pending', NULL, '2026-03-23 05:41:31', NULL);

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
(1, 3, 'Bella', 'Cat/Perssian', NULL, 5),
(2, 3, 'Browny', 'Dog/Aspin', NULL, NULL),
(5, 8, 'Browny', 'Dog', NULL, 3),
(6, 8, 'Bella', 'Cat', NULL, 5),
(7, 9, 'Browny', 'Dog', NULL, 8),
(8, 9, 'Bella', 'Cat', NULL, 4);

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `messages`
--

INSERT INTO `messages` (`id`, `sender_id`, `receiver_id`, `message`, `is_read`, `created_at`) VALUES
(1, 2, 1, 'Hello!', 1, '2026-03-18 08:22:32'),
(2, 1, 2, 'Hello', 1, '2026-03-18 10:51:34'),
(3, 1, 2, 'Hello!', 1, '2026-03-19 02:26:16'),
(4, 2, 1, 'hi', 1, '2026-03-19 03:09:40'),
(5, 7, 2, 'Hello!', 1, '2026-03-23 02:14:24'),
(6, 2, 7, 'Hello!', 0, '2026-03-23 04:22:54');

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
(1, 1, 'New Appointment', 'New clinic appointment booked by Ana Santos for Browny — CheckUp on March 20, 2026', 1, 'appointment', '2026-03-18 07:39:55'),
(2, 2, 'New Announcement', 'Strike', 1, 'announcement', '2026-03-18 08:01:55'),
(3, 1, 'New Message', 'You have a new message from Ana Santos', 1, 'message', '2026-03-18 08:22:32'),
(5, 1, 'New Appointment', 'New clinic appointment booked by Ana Santos for Browny — CheckUp on March 19, 2026', 1, 'appointment', '2026-03-19 01:53:49'),
(6, 2, 'Appointment Update', 'Your appointment for Browny (CheckUp) has been confirmed.', 1, 'appointment', '2026-03-19 02:08:18'),
(7, 2, 'Appointment Update', 'Your appointment for Browny (CheckUp) has been completed.', 1, 'appointment', '2026-03-19 02:09:32'),
(8, 2, 'New Message from Clinic', 'You have a new message from the clinic.', 1, 'message', '2026-03-19 02:26:16'),
(9, 1, 'New Message', 'You have a new message from Ana Santos', 1, 'message', '2026-03-19 03:09:40'),
(10, 1, '📦 Low Stock Alert', 'Low stock alert: &quot;Pedigree&quot; has only 4 item(s) remaining. Please restock soon. [prod:4]', 1, 'low_stock', '2026-03-20 12:31:15'),
(11, 1, '📦 Low Stock Alert', 'Low stock alert: &quot;Dermovet&quot; has only 5 item(s) remaining. Please restock soon. [prod:3]', 1, 'low_stock', '2026-03-20 12:31:15'),
(12, 1, '🚨 Out of Stock', '&quot;Cage for Puppy and Kittens&quot; is now OUT OF STOCK. Please reorder immediately. [prod:7]', 1, 'out_of_stock', '2026-03-20 12:31:15'),
(13, 1, 'New Appointment', 'New clinic appointment booked by Ana Santos for Bella — Grooming on March 28, 2026', 1, 'appointment', '2026-03-20 14:40:16'),
(14, 1, 'New Product Purchase', 'Product purchase by Ana Santos — Total: ₱280.00 (pending payment).', 1, 'billing', '2026-03-21 04:52:59'),
(15, 1, '📦 Low Stock Alert', 'Low stock alert: &quot;Pedigree&quot; has only 4 item(s) remaining. Please restock soon. [prod:4]', 1, 'low_stock', '2026-03-21 05:53:06'),
(16, 1, '📦 Low Stock Alert', 'Low stock alert: &quot;Dermovet&quot; has only 5 item(s) remaining. Please restock soon. [prod:3]', 1, 'low_stock', '2026-03-21 05:53:06'),
(17, 1, '🚨 Out of Stock', '&quot;Cage for Puppy and Kittens&quot; is now OUT OF STOCK. Please reorder immediately. [prod:7]', 1, 'out_of_stock', '2026-03-21 05:53:06'),
(18, 2, 'New Invoice', 'A billing summary of ₱555.00 has been created for your visit.', 1, 'billing', '2026-03-21 06:31:00'),
(19, 1, 'New Product Purchase', 'Product purchase by Ana Santos — Total: ₱150.00 (pending payment).', 0, 'billing', '2026-03-22 11:15:46'),
(20, 1, 'New Product Purchase', 'Product purchase by Pauline Sablayan — Total: ₱560.00 (pending payment).', 1, 'billing', '2026-03-22 11:25:57'),
(21, 2, 'New Message from Clinic', 'You have a new message from the clinic.', 1, 'message', '2026-03-23 02:14:24'),
(22, 2, 'New Invoice', 'A billing summary of ₱800.00 has been created for your visit.', 1, 'billing', '2026-03-23 02:43:08'),
(23, 4, 'New Invoice', 'A billing summary of ₱300.00 has been created for your visit.', 0, 'billing', '2026-03-23 02:43:58'),
(24, 4, 'New Invoice', 'A billing summary of ₱800.00 has been created for your visit.', 0, 'billing', '2026-03-23 02:54:22'),
(25, 2, 'New Invoice', 'A billing summary of ₱300.00 has been created for your visit.', 1, 'billing', '2026-03-23 03:14:22'),
(26, 2, 'New Invoice', 'A billing summary of ₱300.00 has been created for your visit.', 1, 'billing', '2026-03-23 03:27:22'),
(27, 7, 'New Message', 'You have a new message from Ana Santos', 0, 'message', '2026-03-23 04:22:54'),
(28, 1, 'New Product Purchase', 'Product purchase by Ana Santos — Total: ₱150.00 (pending payment).', 1, 'billing', '2026-03-23 04:37:11'),
(29, 1, 'New Appointment', 'New clinic appointment booked by Ana Santos for Browny — CheckUp on March 25, 2026', 1, 'appointment', '2026-03-23 04:55:49'),
(30, 1, 'New Product Purchase', 'Product purchase by Ana Santos — Total: ₱150.00 (pending payment).', 0, 'billing', '2026-03-23 06:28:54'),
(31, 1, 'New Appointment', 'New clinic appointment booked by Ana Santos for Bella — Surgery on March 28, 2026', 1, 'appointment', '2026-03-23 06:31:11'),
(32, 2, 'Appointment Update', 'Your appointment for Bella (Surgery) has been cancelled.', 1, 'appointment', '2026-03-23 07:57:32'),
(33, 1, '🚨 Out of Stock', '&quot;Cage for Puppy and Kittens&quot; is now OUT OF STOCK. Please reorder immediately. [prod:7]', 1, 'out_of_stock', '2026-03-23 07:59:03'),
(34, 2, 'Payment Confirmed', 'Your payment of ₱150.00 has been confirmed. Your receipt is now available.', 1, 'billing', '2026-03-23 08:50:53');

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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pets`
--

INSERT INTO `pets` (`id`, `user_id`, `name`, `species`, `breed`, `date_of_birth`, `age`, `gender`, `weight`, `color`, `photo`, `status`, `created_at`) VALUES
(1, 2, 'Browny', 'dog', 'Aspin', '2021-03-05', '5', 'male', 20.00, 'Brown-white', 'pet_1774229079_963.png', 'active', '2026-03-18 07:31:09'),
(2, 2, 'Bella', 'cat', 'Perssian', '2019-02-05', '7', 'female', 5.00, 'Light brown', 'pet_1774229099_774.jpg', 'active', '2026-03-18 07:36:33'),
(3, 4, 'Tofu', 'cat', 'Khao manee', '2023-12-06', '3', 'female', 7.00, 'White', 'pet_1774227629_419.jpg', 'active', '2026-03-22 11:22:03');

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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pet_consultations`
--

INSERT INTO `pet_consultations` (`id`, `pet_id`, `date_of_visit`, `reason_for_visit`, `diagnosis`, `treatment_given`, `vet_name`, `notes`, `created_at`) VALUES
(1, 2, '2026-02-01', 'Loss of appetite', 'Mild Gastrointestinal infection', 'Prescribed antibiotics and vitamins', 'Dr. Ann', '', '2026-03-22 05:22:12'),
(2, 1, '2026-02-01', 'Loss of appetite', 'Mild Gastrointestinal infection', 'Prescribed antibiotics and vitamins', 'Dr. Ann', '', '2026-03-22 05:23:19');

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
(1, 2, 'Pawprint Veterinary', 'Gastrointestinal infection', 'None', 'None', '', '2026-03-22 05:22:25'),
(2, 1, 'Pawprint Veterinary', 'Gastrointestinal infection', 'None', 'None', '', '2026-03-22 05:23:25');

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
(4, 2, 'Antihistamine', '2 mg', 'Once a Day', '', NULL, NULL, '2026-03-22 05:03:10');

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
(3, 2, 'Rabies', '2025-05-07', '2026-05-07', '', '2026-03-22 05:03:50'),
(4, 2, 'DHPP', '2025-06-07', '2026-06-07', '', '2026-03-22 05:04:33');

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
(2, 'Activated Charcoal Pet Shampoo', 'pet_care', 280.00, 11, '2027-01-01', 'in_stock', 'prod_1774153514.png', '2026-03-18 06:43:51'),
(3, 'Dermovet', 'pet_care', 200.00, 15, '2026-08-01', 'in_stock', 'prod_1774153556.png', '2026-03-18 06:43:51'),
(4, 'Pedigree', 'pet_care', 55.00, 20, '2026-09-01', 'in_stock', 'prod_1774153604.png', '2026-03-18 06:43:51'),
(5, 'Collar', 'pet_supplies', 150.00, 7, NULL, 'in_stock', 'prod_1774153549.jpg', '2026-03-18 06:43:51'),
(6, 'Toys', 'pet_supplies', 100.00, 19, NULL, 'in_stock', 'prod_1774153614.jpg', '2026-03-18 06:43:51'),
(7, 'Cage for Puppy and Kittens', 'pet_supplies', 500.00, 15, NULL, 'in_stock', 'prod_1774153529.jpg', '2026-03-18 06:43:51'),
(8, 'Large Bowl', 'pet_supplies', 300.00, 10, NULL, 'in_stock', 'prod_1774153595.png', '2026-03-18 06:43:51');

-- --------------------------------------------------------

--
-- Table structure for table `promotions`
--

CREATE TABLE `promotions` (
  `id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `icon` varchar(20) DEFAULT '?',
  `link_label` varchar(100) DEFAULT 'Learn More',
  `link_url` varchar(200) DEFAULT 'appointments.php',
  `sort_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `promotions`
--

INSERT INTO `promotions` (`id`, `title`, `description`, `icon`, `link_label`, `link_url`, `sort_order`, `is_active`, `created_at`) VALUES
(1, 'Vaccinationation Available 50% off', 'Come and Visit our Clinic on March 24, 2026 for a discounted vaccination for your fur babies. Seeyou!!', '🐾', 'Book Now', 'services.php', 0, 1, '2026-03-23 08:35:54'),
(2, '1 sack Treats', 'Come and visit to our clinic for a free 1 sack of treats for your fur babies on March 25-30, 2026. See you!', '🐾', 'Book Now', 'products.php', 0, 1, '2026-03-23 08:37:34');

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
(1, 2, 4, 5, '2026-03-22 06:50:16');

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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `transactions`
--

INSERT INTO `transactions` (`id`, `appointment_id`, `user_id`, `pet_id`, `total_amount`, `status`, `transaction_date`, `notes`, `created_at`) VALUES
(1, NULL, 2, NULL, 280.00, 'paid', '2026-03-21', 'Product purchase by user', '2026-03-21 04:52:59'),
(2, 4, 2, 1, 555.00, 'paid', '2026-03-21', '', '2026-03-21 06:31:00'),
(3, NULL, 2, 1, 150.00, 'pending', '2026-03-22', 'Product purchase by user', '2026-03-22 11:15:46'),
(4, NULL, 4, NULL, 560.00, 'pending', '2026-03-22', 'Product purchase by user', '2026-03-22 11:25:57'),
(5, NULL, 2, 1, 800.00, 'paid', '2026-03-23', '', '2026-03-23 02:43:08'),
(6, NULL, 4, 3, 300.00, 'paid', '2026-03-23', '', '2026-03-23 02:43:58'),
(7, NULL, 4, 3, 800.00, 'paid', '2026-03-23', '', '2026-03-23 02:54:22'),
(8, NULL, 2, 1, 300.00, 'pending', '2026-03-23', '', '2026-03-23 03:14:22'),
(9, NULL, 2, 1, 300.00, 'pending', '2026-03-23', '', '2026-03-23 03:27:22'),
(10, NULL, 2, 1, 150.00, 'pending', '2026-03-23', 'Product purchase by user', '2026-03-23 04:37:11'),
(11, NULL, 2, 1, 150.00, 'paid', '2026-03-23', 'Product purchase by user', '2026-03-23 06:28:54');

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
(1, 1, 'product', 'Activated Charcoal Pet Shampoo', 280.00),
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
(15, 11, 'product', 'Collar', 150.00);

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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `address`, `contact_no`, `email`, `password`, `profile_picture`, `gender`, `role`, `position`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Dr. Ann Lawrence S. Polidario', 'National Highway, Zone 4, Tuburan, Ligao City, Albay', '0926-396-7678', 'admin@ligaopetcare.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'av_adm_1_1774253245.jpg', 'female', 'admin', NULL, 'active', '2026-03-18 06:43:51', '2026-03-23 08:07:25'),
(2, 'Ana Santos', 'Tuburan, Ligao City, Albay', '09123737786', 'anasantos123@gmail.com', '$2y$10$UxSew.fBlbGBK1P357/U.eCOoAyB0Cp.zxLtpk4GaQYeR/zwnrax2', 'avatar_2_1774249991.jpg', 'female', 'user', NULL, 'active', '2026-03-18 07:13:08', '2026-03-23 07:13:11'),
(3, 'Penelope Sablayan', 'Napo,Polangui, Albay', '123456789', 'penelopesablayan@gmail.com', '$2y$10$NJfziTmsXelzWMf8sCoG3uAaqWvIKN.atb4LafYDtox1kgB1e7d7K', NULL, 'female', 'user', NULL, 'active', '2026-03-21 03:22:48', '2026-03-21 03:22:48'),
(4, 'Pauline Sablayan', 'Napo,Polangui, Albay', '123456789', 'paulinesablayan@gmail.com', '$2y$10$ew7ND..4exedRTWCrsL2eesV/gNIJLCh5nFNCx/SKEVpnRbpOYwuy', NULL, 'female', 'user', NULL, 'active', '2026-03-21 03:28:58', '2026-03-21 03:28:58'),
(6, 'Dr. Ann Lawrence S. Polidario', 'National Highway, Zone 4, Tuburan, Ligao City, Albay', '0926-396-7678', 'drann@ligaopetcare.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, 'female', 'staff', 'Veterinarian', 'active', '2026-03-22 12:03:11', '2026-03-23 01:27:37'),
(7, 'Kristen Barnedo', 'Ligao City, Albay', '', 'assistant@ligaopetcare.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'av_adm_7_1774231997.png', 'female', 'admin', 'Clinic Staff', 'active', '2026-03-22 12:03:11', '2026-03-23 02:13:17');

--
-- Indexes for dumped tables
--

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
-- AUTO_INCREMENT for table `announcements`
--
ALTER TABLE `announcements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `appointments`
--
ALTER TABLE `appointments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `home_service_pets`
--
ALTER TABLE `home_service_pets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT for table `pets`
--
ALTER TABLE `pets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `pet_allergies`
--
ALTER TABLE `pet_allergies`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `pet_consultations`
--
ALTER TABLE `pet_consultations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `pet_medical_history`
--
ALTER TABLE `pet_medical_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `pet_medications`
--
ALTER TABLE `pet_medications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `pet_vaccines`
--
ALTER TABLE `pet_vaccines`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `promotions`
--
ALTER TABLE `promotions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `ratings`
--
ALTER TABLE `ratings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `transaction_items`
--
ALTER TABLE `transaction_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

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
