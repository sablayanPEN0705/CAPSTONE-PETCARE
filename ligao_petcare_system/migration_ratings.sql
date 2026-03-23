-- ============================================================
-- Ligao Petcare: Add Ratings Table
-- Run in phpMyAdmin on capstone_db
-- ============================================================
CREATE TABLE IF NOT EXISTS `ratings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `appointment_id` int(11) NOT NULL,
  `stars` tinyint(1) NOT NULL DEFAULT 5,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_appt` (`user_id`, `appointment_id`),
  KEY `user_id` (`user_id`),
  KEY `appointment_id` (`appointment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
