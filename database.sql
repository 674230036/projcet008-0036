-- NPRU Room Booking System - อาคาร C
-- Database Schema & Initial Data
CREATE DATABASE IF NOT EXISTS `npru_room_booking`
CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE `npru_room_booking`;

-- 1. Rooms Table
CREATE TABLE IF NOT EXISTS `rooms` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `room_code` VARCHAR(10) NOT NULL UNIQUE,
    `room_name` VARCHAR(100) NOT NULL,
    `building` VARCHAR(50) NOT NULL DEFAULT 'อาคาร C',
    `room_type` VARCHAR(50) NOT NULL,
    `capacity` INT NOT NULL DEFAULT 40,
    `floor` VARCHAR(20) DEFAULT 'ชั้น 3',
    `facilities` VARCHAR(255) DEFAULT 'โปรเจกเตอร์, ไมโครโฟน, เครื่องปรับอากาศ, ไวไฟ',
    `status` ENUM('available','maintenance') DEFAULT 'available'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Users Table
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `student_id` VARCHAR(20) NULL UNIQUE,
    `name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(150) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `phone` VARCHAR(20) DEFAULT NULL,
    `role` ENUM('student','academic','building','admin') DEFAULT 'student',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Bookings Table
CREATE TABLE IF NOT EXISTS `bookings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `room_id` INT NOT NULL,
    `user_id` INT NULL,
    `requester_name` VARCHAR(100) NOT NULL,
    `requester_email` VARCHAR(150) NOT NULL,
    `requester_phone` VARCHAR(20) DEFAULT NULL,
    `topic` VARCHAR(255) NOT NULL,
    `booking_date` DATE NOT NULL,
    `start_time` TIME NOT NULL,
    `end_time` TIME NOT NULL,
    `people` INT NOT NULL DEFAULT 1,
    `status` ENUM('pending','approved','ready','completed','rejected','cancelled') DEFAULT 'pending',
    `academic_notes` VARCHAR(255) NULL,
    `building_notes` VARCHAR(255) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_booking_date` (`booking_date`),
    INDEX `idx_status` (`status`),
    CONSTRAINT `fk_booking_room` FOREIGN KEY (`room_id`) REFERENCES `rooms`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_booking_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Notifications Table
CREATE TABLE IF NOT EXISTS `notifications` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `booking_id` INT NULL,
    `title` VARCHAR(150) NOT NULL,
    `message` TEXT NOT NULL,
    `type` ENUM('info','success','warning','danger') DEFAULT 'info',
    `is_read` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_user_read` (`user_id`, `is_read`),
    CONSTRAINT `fk_notification_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_notification_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Initial Rooms
INSERT INTO `rooms` (`room_code`, `room_name`, `building`, `room_type`, `capacity`, `floor`, `facilities`, `status`) VALUES
('C301','ห้องประชุมใหญ่','อาคาร C','ห้องประชุม',200,'ชั้น 3','โปรเจกเตอร์ความคมชัดสูง, จอขนาดใหญ่, ระบบเสียงรอบทิศทาง, ไมค์ลอย 4 ตัว, โต๊ะประชุม','available'),
('C302','ห้องเรียน C302','อาคาร C','ห้องเรียน',40,'ชั้น 3','โปรเจกเตอร์, เครื่องปรับอากาศ, สมาร์ททีวี, ไมโครโฟน, ไวท์บอร์ด','available'),
('C303','ห้องเรียน C303','อาคาร C','ห้องเรียน',40,'ชั้น 3','โปรเจกเตอร์, เครื่องปรับอากาศ, สมาร์ททีวี, ไมโครโฟน, ไวท์บอร์ด','available'),
('C304','ห้องเรียน C304','อาคาร C','ห้องเรียน',40,'ชั้น 3','โปรเจกเตอร์, เครื่องปรับอากาศ, สมาร์ททีวี, ไมโครโฟน, ไวท์บอร์ด','available'),
('C305','ห้องเรียน C305','อาคาร C','ห้องเรียน',40,'ชั้น 3','โปรเจกเตอร์, เครื่องปรับอากาศ, สมาร์ททีวี, ไมโครโฟน, ไวท์บอร์ด','available'),
('C306','ห้องเรียน C306','อาคาร C','ห้องเรียน',40,'ชั้น 3','โปรเจกเตอร์, เครื่องปรับอากาศ, สมาร์ททีวี, ไมโครโฟน, ไวท์บอร์ด','available'),
('C307','ห้องเรียน C307','อาคาร C','ห้องเรียน',40,'ชั้น 3','โปรเจกเตอร์, เครื่องปรับอากาศ, สมาร์ททีวี, ไมโครโฟน, ไวท์บอร์ด','available'),
('C308','ห้องเรียน C308','อาคาร C','ห้องเรียน',40,'ชั้น 3','โปรเจกเตอร์, เครื่องปรับอากาศ, สมาร์ททีวี, ไมโครโฟน, ไวท์บอร์ด','available'),
('C309','ห้องเรียน C309','อาคาร C','ห้องเรียน',40,'ชั้น 3','โปรเจกเตอร์, เครื่องปรับอากาศ, สมาร์ททีวี, ไมโครโฟน, ไวท์บอร์ด','available'),
('C310','ห้องเรียน C310','อาคาร C','ห้องเรียน',40,'ชั้น 3','โปรเจกเตอร์, เครื่องปรับอากาศ, สมาร์ททีวี, ไมโครโฟน, ไวท์บอร์ด','available')
ON DUPLICATE KEY UPDATE `room_name`=VALUES(`room_name`);

-- Initial Staff / Admin Accounts (Password: 1234)
INSERT INTO `users` (`student_id`, `name`, `email`, `password`, `phone`, `role`) VALUES
(NULL, 'ผู้ดูแลระบบสูงสุด', 'admin@npru.ac.th', '$2y$10$eE0mQYmC3p6Zq5VjX0Y9/eLpS6C0L7C4qW0Q6.qQ8vE1A2vM3vN3W', '034109300', 'admin'),
(NULL, 'อาจารย์วิภาดา รักษ์วิชาการ (ฝ่ายวิชาการ)', 'academic@npru.ac.th', '$2y$10$eE0mQYmC3p6Zq5VjX0Y9/eLpS6C0L7C4qW0Q6.qQ8vE1A2vM3vN3W', '034109301', 'academic'),
(NULL, 'นายสมเกียรติ มั่นคง (ฝ่ายอาคารสถานที่)', 'building@npru.ac.th', '$2y$10$eE0mQYmC3p6Zq5VjX0Y9/eLpS6C0L7C4qW0Q6.qQ8vE1A2vM3vN3W', '034109302', 'building')
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`);
