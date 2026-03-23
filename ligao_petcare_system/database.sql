-- ============================================================
-- Ligao Petcare & Veterinary Clinic - Database Schema
-- Run this in phpMyAdmin > SQL tab
-- ============================================================

CREATE DATABASE IF NOT EXISTS ligao_petcare CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE ligao_petcare;

-- ============================================================
-- TABLE: users (Pet Owners)
-- ============================================================
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    address VARCHAR(255),
    contact_no VARCHAR(20),
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    profile_picture VARCHAR(255) DEFAULT NULL,
    role ENUM('user', 'admin') DEFAULT 'user',
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ============================================================
-- TABLE: pets
-- ============================================================
CREATE TABLE pets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    species ENUM('dog', 'cat', 'other') NOT NULL,
    breed VARCHAR(100),
    date_of_birth DATE,
    age VARCHAR(20),
    gender ENUM('male', 'female') NOT NULL,
    weight DECIMAL(5,2),
    color VARCHAR(100),
    photo VARCHAR(255) DEFAULT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ============================================================
-- TABLE: pet_medications
-- ============================================================
CREATE TABLE pet_medications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pet_id INT NOT NULL,
    medication_name VARCHAR(150) NOT NULL,
    dosage VARCHAR(100),
    frequency VARCHAR(100),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (pet_id) REFERENCES pets(id) ON DELETE CASCADE
);

-- ============================================================
-- TABLE: pet_vaccines
-- ============================================================
CREATE TABLE pet_vaccines (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pet_id INT NOT NULL,
    vaccine_name VARCHAR(150) NOT NULL,
    date_given DATE,
    next_due DATE,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (pet_id) REFERENCES pets(id) ON DELETE CASCADE
);

-- ============================================================
-- TABLE: pet_allergies
-- ============================================================
CREATE TABLE pet_allergies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pet_id INT NOT NULL,
    allergen VARCHAR(150) NOT NULL,
    reaction TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (pet_id) REFERENCES pets(id) ON DELETE CASCADE
);

-- ============================================================
-- TABLE: services
-- ============================================================
CREATE TABLE services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    description TEXT,
    price_min DECIMAL(10,2) DEFAULT 0,
    price_max DECIMAL(10,2) DEFAULT 0,
    category VARCHAR(100),
    status ENUM('available', 'not_available') DEFAULT 'available',
    image VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- TABLE: appointments
-- ============================================================
CREATE TABLE appointments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    pet_id INT,
    service_id INT,
    appointment_type ENUM('clinic', 'home_service') DEFAULT 'clinic',
    appointment_date DATE NOT NULL,
    appointment_time TIME NOT NULL,
    address VARCHAR(255) DEFAULT NULL,
    contact VARCHAR(20) DEFAULT NULL,
    status ENUM('pending', 'confirmed', 'completed', 'cancelled') DEFAULT 'pending',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (pet_id) REFERENCES pets(id) ON DELETE SET NULL,
    FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE SET NULL
);

-- ============================================================
-- TABLE: home_service_pets (for multi-pet home service bookings)
-- ============================================================
CREATE TABLE home_service_pets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    appointment_id INT NOT NULL,
    pet_name VARCHAR(100),
    species VARCHAR(100),
    breed VARCHAR(100),
    service_id INT,
    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE,
    FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE SET NULL
);

-- ============================================================
-- TABLE: products
-- ============================================================
CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    category ENUM('pet_care', 'pet_supplies') DEFAULT 'pet_care',
    price DECIMAL(10,2) NOT NULL,
    quantity INT DEFAULT 0,
    expiry_date DATE DEFAULT NULL,
    status ENUM('in_stock', 'low_stock', 'out_of_stock') DEFAULT 'in_stock',
    image VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- TABLE: transactions (Billing)
-- ============================================================
CREATE TABLE transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    appointment_id INT,
    user_id INT NOT NULL,
    pet_id INT,
    total_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    status ENUM('paid', 'pending', 'overdue') DEFAULT 'pending',
    transaction_date DATE,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE SET NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (pet_id) REFERENCES pets(id) ON DELETE SET NULL
);

-- ============================================================
-- TABLE: transaction_items
-- ============================================================
CREATE TABLE transaction_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transaction_id INT NOT NULL,
    item_type ENUM('service', 'product') DEFAULT 'service',
    item_name VARCHAR(150) NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE CASCADE
);

-- ============================================================
-- TABLE: messages
-- ============================================================
CREATE TABLE messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ============================================================
-- TABLE: announcements
-- ============================================================
CREATE TABLE announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    posted_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (posted_by) REFERENCES users(id) ON DELETE SET NULL
);

-- ============================================================
-- TABLE: notifications
-- ============================================================
CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255),
    message TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    type VARCHAR(50) DEFAULT 'general',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ============================================================
-- SEED DATA: Default Admin Account
-- Password: admin123 (bcrypt hashed)
-- ============================================================
INSERT INTO users (name, address, contact_no, email, password, role) VALUES 
(
    'Dr. Ann Lawrence S. Polidario',
    'National Highway, Zone 4, Tuburan, Ligao City, Albay',
    '0926-396-7678',
    'admin@ligaopetcare.com',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- password: password
    'admin'
);

-- ============================================================
-- SEED DATA: Default Services
-- ============================================================
INSERT INTO services (name, description, price_min, price_max, category, status) VALUES
('CheckUp', 'A general health examination of your pet to monitor its condition, detect illnesses early, and ensure it is healthy.', 300, 400, 'veterinary', 'available'),
('Confinement', 'A service where pets stay in the clinic for observation, treatment, and care when they are sick or recovering.', 500, 1500, 'veterinary', 'available'),
('Treatment', 'Medical care provided to pets to cure illnesses, infections, or injuries through proper diagnosis and medication.', 300, 500, 'veterinary', 'available'),
('Deworming', 'A procedure that removes internal parasites such as worms to keep pets healthy and prevent digestive problems.', 100, 300, 'veterinary', 'available'),
('Vaccination', 'The administration of vaccines to protect pets from dangerous diseases and strengthen their immune system.', 350, 550, 'veterinary', 'available'),
('Grooming', 'Cleaning and maintenance of a pets hygiene including bathing, hair trimming, nail cutting, and ear cleaning.', 500, 1000, 'grooming', 'available'),
('Surgery', 'Medical operations performed by a veterinarian to treat injuries, diseases, or conditions that require surgical procedures.', 1000, 10000, 'veterinary', 'available'),
('Laboratory', 'Diagnostic tests such as blood tests, fecal exams, or other analyses to help veterinarians accurately diagnose pet illnesses.', 800, 2200, 'veterinary', 'available'),
('Home Service', 'Veterinary care provided at the pet owners home for check-ups, vaccinations, or basic treatments for convenience.', 500, 1500, 'home', 'available');

-- ============================================================
-- SEED DATA: Sample Products
-- ============================================================
INSERT INTO products (name, category, price, quantity, expiry_date, status) VALUES
('Groom and Bloom Shampoo', 'pet_care', 280.00, 13, '2027-02-01', 'low_stock'),
('Activated Charcoal Pet Shampoo', 'pet_care', 280.00, 12, '2027-01-01', 'in_stock'),
('Dermovet', 'pet_care', 200.00, 5, '2026-08-01', 'low_stock'),
('Pedigree', 'pet_care', 55.00, 4, '2026-09-01', 'low_stock'),
('Collar', 'pet_supplies', 150.00, 10, NULL, 'in_stock'),
('Toys', 'pet_supplies', 100.00, 19, NULL, 'in_stock'),
('Cage for Puppy and Kittens', 'pet_supplies', 500.00, 1, NULL, 'out_of_stock'),
('Large Bowl', 'pet_supplies', 300.00, 10, NULL, 'in_stock');

-- ============================================================
-- SEED DATA: Sample Announcement
-- ============================================================
INSERT INTO announcements (title, content, posted_by) VALUES
('Clinic Closure - Labor Day', 'Our clinic will be closed on May 1 for the Labor Day Holiday. Please schedule your appointments accordingly. Thank you!', 1);
