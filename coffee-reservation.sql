-- Coffee Table Reservation System Database
-- Created: November 22, 2025

-- Create database
CREATE DATABASE IF NOT EXISTS coffee_reservation;
USE coffee_reservation;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    role ENUM('user', 'admin') DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tables table (coffee shop tables)
CREATE TABLE IF NOT EXISTS tables (
    id INT AUTO_INCREMENT PRIMARY KEY,
    table_number VARCHAR(10) NOT NULL UNIQUE,
    capacity INT NOT NULL,
    location VARCHAR(50),
    status ENUM('available', 'reserved', 'occupied') DEFAULT 'available',
    image_url VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Reservations table
CREATE TABLE IF NOT EXISTS reservations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    table_id INT NOT NULL,
    reservation_date DATE NOT NULL,
    reservation_time TIME NOT NULL,
    duration INT DEFAULT 2,
    guests INT NOT NULL,
    status ENUM('pending', 'confirmed', 'cancelled', 'completed', 'rejected') DEFAULT 'pending',
    special_requests TEXT,
    payment_amount DECIMAL(10, 2) DEFAULT 0.00,
    payment_status ENUM('unpaid', 'paid') DEFAULT 'unpaid',
    payment_date TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (table_id) REFERENCES tables(id) ON DELETE CASCADE,
    INDEX idx_user_date (user_id, reservation_date),
    INDEX idx_table_date (table_id, reservation_date, reservation_time),
    INDEX idx_status (status)
);

-- Notifications table
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    reservation_id INT,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (reservation_id) REFERENCES reservations(id) ON DELETE CASCADE,
    INDEX idx_user_read (user_id, is_read),
    INDEX idx_created (created_at)
);

-- Payments table
CREATE TABLE IF NOT EXISTS payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reservation_id INT NOT NULL,
    user_id INT NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    payment_method VARCHAR(50) DEFAULT 'Credit Card',
    transaction_id VARCHAR(100),
    payment_status ENUM('pending', 'completed', 'failed', 'refunded') DEFAULT 'completed',
    payment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    card_last_four VARCHAR(4),
    cardholder_name VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (reservation_id) REFERENCES reservations(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_payment (user_id, payment_date),
    INDEX idx_reservation (reservation_id),
    INDEX idx_status (payment_status)
);

-- Insert default admin user
-- Password: admin123
INSERT INTO users (username, email, password, role) 
VALUES ('Admin', 'admin@coffee.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

-- Insert sample tables with capacity-appropriate images
INSERT INTO tables (table_number, capacity, location, image_url) VALUES
('T1', 2, 'Window Side', 'https://images.unsplash.com/photo-1559339352-11d035aa65de?w=400'),
('T2', 4, 'Center', 'https://images.unsplash.com/photo-1517248135467-4c7edcad34c4?w=400'),
('T3', 2, 'Corner', 'https://images.unsplash.com/photo-1530018607912-eff2daa1bac4?w=400'),
('T4', 6, 'Private Room', 'https://images.unsplash.com/photo-1590846406792-0adc7f938f1d?w=400'),
('T5', 4, 'Patio', 'https://images.unsplash.com/photo-1554118811-1e0d58224f24?w=400'),
('T6', 2, 'Window Side', 'https://images.unsplash.com/photo-1552566626-52f8b828add9?w=400'),
('T7', 8, 'Large Table', 'https://images.unsplash.com/photo-1414235077428-338989a2e8c0?w=400'),
('T8', 4, 'Center', 'https://images.unsplash.com/photo-1521017432531-fbd92d768814?w=400');
