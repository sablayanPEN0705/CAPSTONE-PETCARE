-- ============================================================
-- Ligao Petcare: Add Previous Consultations + Medical History
-- Run this in phpMyAdmin before using the updated PHP files
-- ============================================================

-- Previous Consultations table
CREATE TABLE IF NOT EXISTS `pet_consultations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pet_id` int(11) NOT NULL,
  `date_of_visit` date NOT NULL,
  `reason_for_visit` varchar(255) DEFAULT NULL,
  `diagnosis` text DEFAULT NULL,
  `treatment_given` text DEFAULT NULL,
  `vet_name` varchar(150) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `pet_id` (`pet_id`),
  CONSTRAINT `pc_pet_fk` FOREIGN KEY (`pet_id`) REFERENCES `pets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Medical History table
CREATE TABLE IF NOT EXISTS `pet_medical_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pet_id` int(11) NOT NULL,
  `clinic_name` varchar(150) DEFAULT NULL,
  `past_illnesses` text DEFAULT NULL,
  `chronic_conditions` text DEFAULT NULL,
  `injuries_surgeries` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `pet_id` (`pet_id`),
  CONSTRAINT `pmh_pet_fk` FOREIGN KEY (`pet_id`) REFERENCES `pets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
