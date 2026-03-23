-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 20, 2026 at 07:15 AM
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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `appointments`
--

INSERT INTO `appointments` (`id`, `user_id`, `pet_id`, `service_id`, `appointment_type`, `appointment_date`, `appointment_time`, `address`, `contact`, `status`, `notes`, `created_at`) VALUES
(2, 2, 1, 1, 'clinic', '2026-03-20', '13:00:00', NULL, NULL, 'pending', NULL, '2026-03-18 07:39:55'),
(3, 2, NULL, NULL, 'home_service', '2026-04-25', '13:00:00', 'Tuburan, Ligao City Albay', '09656658993', 'pending', NULL, '2026-03-18 11:05:10'),
(4, 2, 1, 1, 'clinic', '2026-03-19', '10:00:00', NULL, NULL, 'completed', NULL, '2026-03-19 01:53:49');

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
(2, 3, 'Browny', 'Dog/Aspin', NULL, NULL);

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
(4, 2, 1, 'hi', 1, '2026-03-19 03:09:40');

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
(1, 1, 'New Appointment', 'New clinic appointment booked by Ana Santos for Browny — CheckUp on March 20, 2026', 0, 'appointment', '2026-03-18 07:39:55'),
(2, 2, 'New Announcement', 'Strike', 1, 'announcement', '2026-03-18 08:01:55'),
(3, 1, 'New Message', 'You have a new message from Ana Santos', 0, 'message', '2026-03-18 08:22:32'),
(4, 2, 'New Message from Clinic', 'You have a new message from the clinic.', 1, 'message', '2026-03-18 10:51:34'),
(5, 1, 'New Appointment', 'New clinic appointment booked by Ana Santos for Browny — CheckUp on March 19, 2026', 0, 'appointment', '2026-03-19 01:53:49'),
(6, 2, 'Appointment Update', 'Your appointment for Browny (CheckUp) has been confirmed.', 1, 'appointment', '2026-03-19 02:08:18'),
(7, 2, 'Appointment Update', 'Your appointment for Browny (CheckUp) has been completed.', 1, 'appointment', '2026-03-19 02:09:32'),
(8, 2, 'New Message from Clinic', 'You have a new message from the clinic.', 1, 'message', '2026-03-19 02:26:16'),
(9, 1, 'New Message', 'You have a new message from Ana Santos', 0, 'message', '2026-03-19 03:09:40');

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
(1, 2, 'Browny', 'dog', 'Aspin', '2021-03-05', '5', 'male', 10.00, 'Brown-white', 'pet_1773832163_471.jpg', 'active', '2026-03-18 07:31:09'),
(2, 2, 'Bella', 'cat', 'Perssian', '2019-02-05', '7', 'female', 5.00, 'Light brown', 'pet_1773832120_216.jpg', 'active', '2026-03-18 07:36:33');

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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
(1, 'Groom and Bloom Shampoo', 'pet_care', 280.00, 13, '2027-02-01', 'in_stock', NULL, '2026-03-18 06:43:51'),
(2, 'Activated Charcoal Pet Shampoo', 'pet_care', 280.00, 12, '2027-01-01', 'in_stock', NULL, '2026-03-18 06:43:51'),
(3, 'Dermovet', 'pet_care', 200.00, 5, '2026-08-01', 'low_stock', NULL, '2026-03-18 06:43:51'),
(4, 'Pedigree', 'pet_care', 55.00, 4, '2026-09-01', 'low_stock', NULL, '2026-03-18 06:43:51'),
(5, 'Collar', 'pet_supplies', 150.00, 10, NULL, 'in_stock', NULL, '2026-03-18 06:43:51'),
(6, 'Toys', 'pet_supplies', 100.00, 19, NULL, 'in_stock', NULL, '2026-03-18 06:43:51'),
(7, 'Cage for Puppy and Kittens', 'pet_supplies', 500.00, 1, NULL, 'out_of_stock', NULL, '2026-03-18 06:43:51'),
(8, 'Large Bowl', 'pet_supplies', 300.00, 10, NULL, 'in_stock', NULL, '2026-03-18 06:43:51');

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
(1, 'CheckUp', 'A general health examination of your pet to monitor its condition, detect illnesses early, and ensure it is healthy.', 300.00, 400.00, 'veterinary', 'not_available', NULL, '2026-03-18 06:43:51'),
(2, 'Confinement', 'A service where pets stay in the clinic for observation, treatment, and care when they are sick or recovering.', 500.00, 1500.00, 'veterinary', 'available', NULL, '2026-03-18 06:43:51'),
(3, 'Treatment', 'Medical care provided to pets to cure illnesses, infections, or injuries through proper diagnosis and medication.', 300.00, 500.00, 'veterinary', 'available', NULL, '2026-03-18 06:43:51'),
(4, 'Deworming', 'A procedure that removes internal parasites such as worms to keep pets healthy and prevent digestive problems.', 100.00, 300.00, 'veterinary', 'not_available', NULL, '2026-03-18 06:43:51'),
(5, 'Vaccination', 'The administration of vaccines to protect pets from dangerous diseases and strengthen their immune system.', 350.00, 550.00, 'veterinary', 'available', NULL, '2026-03-18 06:43:51'),
(6, 'Grooming', 'Cleaning and maintenance of a pets hygiene including bathing, hair trimming, nail cutting, and ear cleaning.', 500.00, 1000.00, 'grooming', 'available', NULL, '2026-03-18 06:43:51'),
(7, 'Surgery', 'Medical operations performed by a veterinarian to treat injuries, diseases, or conditions that require surgical procedures.', 1000.00, 10000.00, 'veterinary', 'available', NULL, '2026-03-18 06:43:51'),
(8, 'Laboratory', 'Diagnostic tests such as blood tests, fecal exams, or other analyses to help veterinarians accurately diagnose pet illnesses.', 800.00, 2200.00, 'veterinary', 'available', NULL, '2026-03-18 06:43:51'),
(9, 'Home Service', 'Veterinary care provided at the pet owners home for check-ups, vaccinations, or basic treatments for convenience.', 500.00, 1500.00, 'home', 'available', NULL, '2026-03-18 06:43:51');

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
  `role` enum('user','admin') DEFAULT 'user',
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `address`, `contact_no`, `email`, `password`, `profile_picture`, `role`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Dr. Ann Lawrence S. Polidario', 'National Highway, Zone 4, Tuburan, Ligao City, Albay', '0926-396-7678', 'admin@ligaopetcare.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, 'admin', 'active', '2026-03-18 06:43:51', '2026-03-18 06:43:51'),
(2, 'Ana Santos', 'Tuburan, Ligao City, Albay', '09123737786', 'anasantos123@gmail.com', '$2y$10$UxSew.fBlbGBK1P357/U.eCOoAyB0Cp.zxLtpk4GaQYeR/zwnrax2', NULL, 'user', 'active', '2026-03-18 07:13:08', '2026-03-18 07:13:08');

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
-- Indexes for table `services`
--
ALTER TABLE `services`
  ADD PRIMARY KEY (`id`);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `home_service_pets`
--
ALTER TABLE `home_service_pets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `pets`
--
ALTER TABLE `pets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `pet_allergies`
--
ALTER TABLE `pet_allergies`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pet_medications`
--
ALTER TABLE `pet_medications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pet_vaccines`
--
ALTER TABLE `pet_vaccines`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `services`
--
ALTER TABLE `services`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `transaction_items`
--
ALTER TABLE `transaction_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

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
