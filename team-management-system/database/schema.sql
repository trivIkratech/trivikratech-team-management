-- ============================================
-- Team Management System — Database Schema
-- Version: 1.0.0
-- ============================================

-- Create database (if not already created via Hostinger panel)
-- CREATE DATABASE IF NOT EXISTS team_management CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- USE team_management;

-- ============================================
-- Table: users
-- ============================================
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `employee_id` VARCHAR(50) NULL,
    `name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(150) NOT NULL,
    `contact_no` VARCHAR(20) NULL,
    `designation` VARCHAR(100) NULL,
    `password` VARCHAR(255) NOT NULL,
    `pin` VARCHAR(255) NULL,
    `role` ENUM('founder', 'manager', 'hr', 'employee') NOT NULL DEFAULT 'employee',
    `manager_id` INT NULL,
    `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY `uk_email` (`email`),
    UNIQUE KEY `uk_employee_id` (`employee_id`),
    INDEX `idx_role` (`role`),
    INDEX `idx_manager_id` (`manager_id`),
    INDEX `idx_status` (`status`),
    
    CONSTRAINT `fk_users_manager` FOREIGN KEY (`manager_id`) 
        REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Table: attendance
-- ============================================
CREATE TABLE IF NOT EXISTS `attendance` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `date` DATE NOT NULL,
    `check_in` TIME NULL,
    `check_out` TIME NULL,
    `total_working_time` TIME NULL,
    `status` ENUM('present', 'absent', 'half-day') NOT NULL DEFAULT 'present',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    UNIQUE KEY `uk_user_date` (`user_id`, `date`),
    INDEX `idx_date` (`date`),
    INDEX `idx_status` (`status`),
    
    CONSTRAINT `fk_attendance_user` FOREIGN KEY (`user_id`) 
        REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Table: tasks
-- ============================================
CREATE TABLE IF NOT EXISTS `tasks` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(200) NOT NULL,
    `description` TEXT NULL,
    `assigned_to` INT NOT NULL,
    `assigned_by` INT NOT NULL,
    `priority` ENUM('low', 'medium', 'high') NOT NULL DEFAULT 'medium',
    `status` ENUM('todo', 'in_progress', 'completed') NOT NULL DEFAULT 'todo',
    `deadline` DATE NULL,
    `completed_at` TIMESTAMP NULL,
    `comments` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX `idx_assigned_to` (`assigned_to`),
    INDEX `idx_assigned_by` (`assigned_by`),
    INDEX `idx_status` (`status`),
    INDEX `idx_priority` (`priority`),
    INDEX `idx_deadline` (`deadline`),
    
    CONSTRAINT `fk_tasks_assigned_to` FOREIGN KEY (`assigned_to`) 
        REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_tasks_assigned_by` FOREIGN KEY (`assigned_by`) 
        REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Table: support_tickets
-- ============================================
CREATE TABLE IF NOT EXISTS `support_tickets` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `category` VARCHAR(50) NOT NULL,
    `sub_category` VARCHAR(100) NOT NULL,
    `description` TEXT NOT NULL,
    `send_to` VARCHAR(50) NOT NULL,
    `is_anonymous` TINYINT(1) DEFAULT 0,
    `status` ENUM('pending', 'resolved') DEFAULT 'pending',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_send_to` (`send_to`),
    INDEX `idx_status` (`status`),
    
    CONSTRAINT `fk_support_tickets_user` FOREIGN KEY (`user_id`) 
        REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Table: leaves
-- ============================================
CREATE TABLE IF NOT EXISTS `leaves` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `leave_type` ENUM('casual', 'sick', 'paid', 'unpaid') NOT NULL,
    `start_date` DATE NOT NULL,
    `end_date` DATE NOT NULL,
    `reason` TEXT NOT NULL,
    `prescription_doc` VARCHAR(255) NULL,
    `status` ENUM('pending', 'approved', 'denied') DEFAULT 'pending',
    `actioned_by` INT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_status` (`status`),
    
    CONSTRAINT `fk_leaves_user` FOREIGN KEY (`user_id`) 
        REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_leaves_actioned_by` FOREIGN KEY (`actioned_by`) 
        REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Table: announcements
-- ============================================
CREATE TABLE IF NOT EXISTS `announcements` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `sender_id` INT NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `content` TEXT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX `idx_sender_id` (`sender_id`),
    
    CONSTRAINT `fk_announcements_sender` FOREIGN KEY (`sender_id`) 
        REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Table: meetings
-- ============================================
CREATE TABLE IF NOT EXISTS `meetings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT NULL,
    `meeting_date` DATE NOT NULL,
    `start_time` TIME NOT NULL,
    `end_time` TIME NOT NULL,
    `host_id` INT NOT NULL,
    `meeting_type` ENUM('team', 'individual', 'self') NOT NULL DEFAULT 'team',
    `meet_link` VARCHAR(255) NULL,
    `agenda` TEXT NULL,
    `status` ENUM('scheduled', 'completed', 'cancelled') NOT NULL DEFAULT 'scheduled',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX `idx_host_id` (`host_id`),
    INDEX `idx_meeting_date` (`meeting_date`),
    
    CONSTRAINT `fk_meetings_host` FOREIGN KEY (`host_id`) 
        REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Table: meeting_participants
-- ============================================
CREATE TABLE IF NOT EXISTS `meeting_participants` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `meeting_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    
    UNIQUE KEY `uk_meeting_user` (`meeting_id`, `user_id`),
    INDEX `idx_meeting_id` (`meeting_id`),
    INDEX `idx_user_id` (`user_id`),
    
    CONSTRAINT `fk_participants_meeting` FOREIGN KEY (`meeting_id`) 
        REFERENCES `meetings` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_participants_user` FOREIGN KEY (`user_id`) 
        REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================
-- Seed Data: Default Founder Account
-- Email: founder@company.com
-- Password: Founder@123
-- ============================================
INSERT INTO `users` (`name`, `email`, `password`, `role`, `status`) VALUES
('System Founder', 'founder@company.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'founder', 'active');

-- NOTE: The password hash above is a placeholder.
-- After deployment, run this PHP script once to generate the correct hash:
-- 
--   <?php
--   $hash = password_hash('Founder@123', PASSWORD_BCRYPT);
--   echo "UPDATE users SET password = '$hash' WHERE email = 'founder@company.com';";
--   ?>
--
-- Or use the setup script below:

-- ============================================
-- Alternative: Use this after importing to set
-- the founder password correctly via PHP
-- ============================================
-- Run setup_password.php (provided separately) to
-- update the founder password with a proper bcrypt hash.
