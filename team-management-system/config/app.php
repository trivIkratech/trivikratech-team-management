<?php
/**
 * Application Configuration
 * 
 * Global constants and settings for the Team Management System.
 */

// Application settings
define('APP_NAME', 'Team Management System');
define('APP_VERSION', '1.0.0');
define('APP_TIMEZONE', 'Asia/Kolkata');

// Set timezone
date_default_timezone_set(APP_TIMEZONE);

// Session configuration
define('SESSION_LIFETIME', 3600); // 1 hour

// Base URL — UPDATE FOR PRODUCTION
// Example: 'https://yourdomain.com/team-management-system'
define('BASE_URL', '/team-management-system');

// Role constants
define('ROLE_FOUNDER', 'founder');
define('ROLE_MANAGER', 'manager');
define('ROLE_EMPLOYEE', 'employee');
define('ROLE_HR', 'hr');

// Task status constants
define('TASK_TODO', 'todo');
define('TASK_IN_PROGRESS', 'in_progress');
define('TASK_COMPLETED', 'completed');

// Task priority constants
define('PRIORITY_LOW', 'low');
define('PRIORITY_MEDIUM', 'medium');
define('PRIORITY_HIGH', 'high');

// Attendance status constants
define('ATTENDANCE_PRESENT', 'present');
define('ATTENDANCE_ABSENT', 'absent');
define('ATTENDANCE_HALF_DAY', 'half-day');

// User status constants
define('USER_ACTIVE', 'active');
define('USER_INACTIVE', 'inactive');

// Pagination
define('RECORDS_PER_PAGE', 15);
