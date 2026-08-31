-- ============================================
-- Team Management System — Complete Database Import for Hostinger
-- Exported Date: 2026-08-31 20:34:08
-- ============================================

SET FOREIGN_KEY_CHECKS = 0;

-- Table structure for `announcements`
DROP TABLE IF EXISTS `announcements`;
CREATE TABLE `announcements` (
  `id` int NOT NULL AUTO_INCREMENT,
  `sender_id` int NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `content` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sender_id` (`sender_id`),
  CONSTRAINT `fk_announcements_sender` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for `attendance`
DROP TABLE IF EXISTS `attendance`;
CREATE TABLE `attendance` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `date` date NOT NULL,
  `check_in` time DEFAULT NULL,
  `check_out` time DEFAULT NULL,
  `break_start` time DEFAULT NULL,
  `break_end` time DEFAULT NULL,
  `total_break_time` time DEFAULT NULL,
  `total_working_time` time DEFAULT NULL,
  `status` enum('present','absent','half-day') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'present',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_date` (`user_id`,`date`),
  KEY `idx_date` (`date`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_attendance_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed data for `attendance`
INSERT INTO `attendance` (`id`, `user_id`, `date`, `check_in`, `check_out`, `break_start`, `break_end`, `total_break_time`, `total_working_time`, `status`, `created_at`) VALUES ('1', '4', '2026-08-31', '12:46:29', '12:46:51', NULL, NULL, NULL, '00:00:22', 'absent', '2026-08-31 12:46:29');
INSERT INTO `attendance` (`id`, `user_id`, `date`, `check_in`, `check_out`, `break_start`, `break_end`, `total_break_time`, `total_working_time`, `status`, `created_at`) VALUES ('2', '2', '2026-08-31', '12:58:39', '17:07:40', '12:58:42', '12:58:47', '00:00:05', '04:08:56', 'half-day', '2026-08-31 12:58:39');
INSERT INTO `attendance` (`id`, `user_id`, `date`, `check_in`, `check_out`, `break_start`, `break_end`, `total_break_time`, `total_working_time`, `status`, `created_at`) VALUES ('3', '3', '2026-08-31', '17:29:14', '17:31:09', '17:29:20', '17:29:28', '00:00:08', '00:01:47', 'absent', '2026-08-31 17:29:14');

-- Table structure for `chat_messages`
DROP TABLE IF EXISTS `chat_messages`;
CREATE TABLE `chat_messages` (
  `id` int NOT NULL AUTO_INCREMENT,
  `room_id` int NOT NULL,
  `sender_id` int NOT NULL,
  `message` text,
  `file_path` varchar(255) DEFAULT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `file_type` varchar(100) DEFAULT NULL,
  `is_read` tinyint DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `room_id` (`room_id`),
  KEY `sender_id` (`sender_id`),
  CONSTRAINT `chat_messages_ibfk_1` FOREIGN KEY (`room_id`) REFERENCES `chat_rooms` (`id`) ON DELETE CASCADE,
  CONSTRAINT `chat_messages_ibfk_2` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Seed data for `chat_messages`
INSERT INTO `chat_messages` (`id`, `room_id`, `sender_id`, `message`, `file_path`, `file_name`, `file_type`, `is_read`, `created_at`) VALUES ('1', '5', '4', 'hello sir', NULL, NULL, NULL, '1', '2026-08-31 17:44:46');
INSERT INTO `chat_messages` (`id`, `room_id`, `sender_id`, `message`, `file_path`, `file_name`, `file_type`, `is_read`, `created_at`) VALUES ('2', '6', '1', 'hello manager', NULL, NULL, NULL, '0', '2026-08-31 18:07:35');
INSERT INTO `chat_messages` (`id`, `room_id`, `sender_id`, `message`, `file_path`, `file_name`, `file_type`, `is_read`, `created_at`) VALUES ('3', '6', '1', 'hello manager', NULL, NULL, NULL, '0', '2026-08-31 18:07:59');

-- Table structure for `chat_room_members`
DROP TABLE IF EXISTS `chat_room_members`;
CREATE TABLE `chat_room_members` (
  `id` int NOT NULL AUTO_INCREMENT,
  `room_id` int NOT NULL,
  `user_id` int NOT NULL,
  `joined_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `room_user` (`room_id`,`user_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `chat_room_members_ibfk_1` FOREIGN KEY (`room_id`) REFERENCES `chat_rooms` (`id`) ON DELETE CASCADE,
  CONSTRAINT `chat_room_members_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=307 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Seed data for `chat_room_members`
INSERT INTO `chat_room_members` (`id`, `room_id`, `user_id`, `joined_at`) VALUES ('1', '1', '1', '2026-08-31 17:23:24');
INSERT INTO `chat_room_members` (`id`, `room_id`, `user_id`, `joined_at`) VALUES ('2', '1', '2', '2026-08-31 17:23:24');
INSERT INTO `chat_room_members` (`id`, `room_id`, `user_id`, `joined_at`) VALUES ('3', '1', '3', '2026-08-31 17:23:24');
INSERT INTO `chat_room_members` (`id`, `room_id`, `user_id`, `joined_at`) VALUES ('4', '1', '4', '2026-08-31 17:23:24');
INSERT INTO `chat_room_members` (`id`, `room_id`, `user_id`, `joined_at`) VALUES ('9', '2', '3', '2026-08-31 17:26:12');
INSERT INTO `chat_room_members` (`id`, `room_id`, `user_id`, `joined_at`) VALUES ('10', '2', '1', '2026-08-31 17:26:12');
INSERT INTO `chat_room_members` (`id`, `room_id`, `user_id`, `joined_at`) VALUES ('11', '3', '3', '2026-08-31 17:26:14');
INSERT INTO `chat_room_members` (`id`, `room_id`, `user_id`, `joined_at`) VALUES ('12', '3', '2', '2026-08-31 17:26:14');
INSERT INTO `chat_room_members` (`id`, `room_id`, `user_id`, `joined_at`) VALUES ('13', '4', '3', '2026-08-31 17:26:14');
INSERT INTO `chat_room_members` (`id`, `room_id`, `user_id`, `joined_at`) VALUES ('14', '4', '4', '2026-08-31 17:26:14');
INSERT INTO `chat_room_members` (`id`, `room_id`, `user_id`, `joined_at`) VALUES ('20', '5', '4', '2026-08-31 17:44:41');
INSERT INTO `chat_room_members` (`id`, `room_id`, `user_id`, `joined_at`) VALUES ('21', '5', '1', '2026-08-31 17:44:41');
INSERT INTO `chat_room_members` (`id`, `room_id`, `user_id`, `joined_at`) VALUES ('224', '6', '1', '2026-08-31 18:00:00');
INSERT INTO `chat_room_members` (`id`, `room_id`, `user_id`, `joined_at`) VALUES ('225', '6', '2', '2026-08-31 18:00:00');

-- Table structure for `chat_rooms`
DROP TABLE IF EXISTS `chat_rooms`;
CREATE TABLE `chat_rooms` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL,
  `type` enum('direct','group') DEFAULT 'direct',
  `created_by` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Seed data for `chat_rooms`
INSERT INTO `chat_rooms` (`id`, `name`, `type`, `created_by`, `created_at`) VALUES ('1', '#General Team Chat', 'group', '1', '2026-08-31 17:23:24');
INSERT INTO `chat_rooms` (`id`, `name`, `type`, `created_by`, `created_at`) VALUES ('2', NULL, 'direct', '3', '2026-08-31 17:26:12');
INSERT INTO `chat_rooms` (`id`, `name`, `type`, `created_by`, `created_at`) VALUES ('3', NULL, 'direct', '3', '2026-08-31 17:26:14');
INSERT INTO `chat_rooms` (`id`, `name`, `type`, `created_by`, `created_at`) VALUES ('4', NULL, 'direct', '3', '2026-08-31 17:26:14');
INSERT INTO `chat_rooms` (`id`, `name`, `type`, `created_by`, `created_at`) VALUES ('5', NULL, 'direct', '4', '2026-08-31 17:44:41');
INSERT INTO `chat_rooms` (`id`, `name`, `type`, `created_by`, `created_at`) VALUES ('6', NULL, 'direct', '1', '2026-08-31 18:00:00');

-- Table structure for `leaves`
DROP TABLE IF EXISTS `leaves`;
CREATE TABLE `leaves` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `leave_type` enum('casual','sick','paid','unpaid') COLLATE utf8mb4_unicode_ci NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `reason` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `prescription_doc` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('pending','approved','denied') COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `actioned_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_status` (`status`),
  KEY `fk_leaves_actioned_by` (`actioned_by`),
  CONSTRAINT `fk_leaves_actioned_by` FOREIGN KEY (`actioned_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_leaves_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for `meeting_participants`
DROP TABLE IF EXISTS `meeting_participants`;
CREATE TABLE `meeting_participants` (
  `id` int NOT NULL AUTO_INCREMENT,
  `meeting_id` int NOT NULL,
  `user_id` int NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_meeting_user` (`meeting_id`,`user_id`),
  KEY `idx_meeting_id` (`meeting_id`),
  KEY `idx_user_id` (`user_id`),
  CONSTRAINT `fk_participants_meeting` FOREIGN KEY (`meeting_id`) REFERENCES `meetings` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_participants_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for `meetings`
DROP TABLE IF EXISTS `meetings`;
CREATE TABLE `meetings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `meeting_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `host_id` int NOT NULL,
  `meeting_type` enum('team','individual','self') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'team',
  `meet_link` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `agenda` text COLLATE utf8mb4_unicode_ci,
  `status` enum('scheduled','completed','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'scheduled',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_host_id` (`host_id`),
  KEY `idx_meeting_date` (`meeting_date`),
  CONSTRAINT `fk_meetings_host` FOREIGN KEY (`host_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for `notifications`
DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `link` varchar(255) DEFAULT NULL,
  `type` enum('info','success','warning','danger') DEFAULT 'info',
  `is_read` tinyint DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_read` (`user_id`,`is_read`),
  CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Seed data for `notifications`
INSERT INTO `notifications` (`id`, `user_id`, `title`, `message`, `link`, `type`, `is_read`, `created_at`) VALUES ('1', '1', '💬 New Message from Employee User', 'hello sir', '/team-management-system/chat/index.php?room_id=5', 'info', '1', '2026-08-31 17:44:46');
INSERT INTO `notifications` (`id`, `user_id`, `title`, `message`, `link`, `type`, `is_read`, `created_at`) VALUES ('2', '2', '<i class=\"fa-solid fa-comments\"></i> New Message from System Founder', 'hello manager', '/team-management-system/chat/index.php?room_id=6', 'info', '0', '2026-08-31 18:07:35');
INSERT INTO `notifications` (`id`, `user_id`, `title`, `message`, `link`, `type`, `is_read`, `created_at`) VALUES ('3', '2', '<i class=\"fa-solid fa-comments\"></i> New Message from System Founder', 'hello manager', '/team-management-system/chat/index.php?room_id=6', 'info', '0', '2026-08-31 18:07:59');

-- Table structure for `support_tickets`
DROP TABLE IF EXISTS `support_tickets`;
CREATE TABLE `support_tickets` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `category` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sub_category` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `send_to` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_anonymous` tinyint(1) DEFAULT '0',
  `status` enum('pending','resolved') COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_send_to` (`send_to`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_support_tickets_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for `tasks`
DROP TABLE IF EXISTS `tasks`;
CREATE TABLE `tasks` (
  `id` int NOT NULL AUTO_INCREMENT,
  `title` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `assigned_to` int NOT NULL,
  `assigned_by` int NOT NULL,
  `priority` enum('low','medium','high') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'medium',
  `status` enum('todo','in_progress','completed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'todo',
  `deadline` date DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `comments` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_assigned_to` (`assigned_to`),
  KEY `idx_assigned_by` (`assigned_by`),
  KEY `idx_status` (`status`),
  KEY `idx_priority` (`priority`),
  KEY `idx_deadline` (`deadline`),
  CONSTRAINT `fk_tasks_assigned_by` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_tasks_assigned_to` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for `users`
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `employee_id` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `contact_no` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `designation` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `base_salary` decimal(10,2) NOT NULL DEFAULT '30000.00',
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `pin` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `role` enum('founder','manager','hr','employee') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'employee',
  `manager_id` int DEFAULT NULL,
  `status` enum('active','inactive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_email` (`email`),
  UNIQUE KEY `uk_employee_id` (`employee_id`),
  KEY `idx_role` (`role`),
  KEY `idx_manager_id` (`manager_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_users_manager` FOREIGN KEY (`manager_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed data for `users`
INSERT INTO `users` (`id`, `employee_id`, `name`, `email`, `contact_no`, `designation`, `base_salary`, `password`, `pin`, `role`, `manager_id`, `status`, `created_at`, `updated_at`) VALUES ('1', NULL, 'System Founder', 'founder@company.com', NULL, NULL, '30000.00', '$2y$12$2zNy./ZnIVjqQ95aIO9pz.aE7lOE3SjMCRSwbBokJ7Klg657Uf9uy', '$2y$12$X.4J5O9pdjMMdIyE34gUqu3GyoTvq.NqKI8g8zOsLJBwDdABtO.c.', 'founder', NULL, 'active', '2026-08-31 09:35:38', '2026-08-31 09:42:05');
INSERT INTO `users` (`id`, `employee_id`, `name`, `email`, `contact_no`, `designation`, `base_salary`, `password`, `pin`, `role`, `manager_id`, `status`, `created_at`, `updated_at`) VALUES ('2', 'EMP001', 'Manager User', 'manager@company.com', NULL, NULL, '30000.00', '$2y$12$2zNy./ZnIVjqQ95aIO9pz.aE7lOE3SjMCRSwbBokJ7Klg657Uf9uy', '$2y$12$X.4J5O9pdjMMdIyE34gUqu3GyoTvq.NqKI8g8zOsLJBwDdABtO.c.', 'manager', NULL, 'active', '2026-08-31 09:38:49', '2026-08-31 09:42:05');
INSERT INTO `users` (`id`, `employee_id`, `name`, `email`, `contact_no`, `designation`, `base_salary`, `password`, `pin`, `role`, `manager_id`, `status`, `created_at`, `updated_at`) VALUES ('3', 'EMP002', 'HR User', 'hr@company.com', NULL, NULL, '30000.00', '$2y$12$2zNy./ZnIVjqQ95aIO9pz.aE7lOE3SjMCRSwbBokJ7Klg657Uf9uy', '$2y$12$X.4J5O9pdjMMdIyE34gUqu3GyoTvq.NqKI8g8zOsLJBwDdABtO.c.', 'hr', NULL, 'active', '2026-08-31 09:38:49', '2026-08-31 09:42:05');
INSERT INTO `users` (`id`, `employee_id`, `name`, `email`, `contact_no`, `designation`, `base_salary`, `password`, `pin`, `role`, `manager_id`, `status`, `created_at`, `updated_at`) VALUES ('4', 'EMP003', 'Employee User', 'employee@company.com', NULL, NULL, '5000.00', '$2y$12$2zNy./ZnIVjqQ95aIO9pz.aE7lOE3SjMCRSwbBokJ7Klg657Uf9uy', '$2y$12$X.4J5O9pdjMMdIyE34gUqu3GyoTvq.NqKI8g8zOsLJBwDdABtO.c.', 'employee', NULL, 'active', '2026-08-31 09:38:49', '2026-08-31 12:08:20');

SET FOREIGN_KEY_CHECKS = 1;
