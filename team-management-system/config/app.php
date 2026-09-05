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

// Base URL — Auto-detects directory path dynamically for localhost, subdirectories, and production subdomains
if (!defined('BASE_URL')) {
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    $requestUri = $_SERVER['REQUEST_URI'] ?? '';
    
    if (strpos($scriptName, '/team-management-system') !== false || strpos($requestUri, '/team-management-system') !== false) {
        define('BASE_URL', '/team-management-system');
    } else {
        // Production Hostinger subdomain (e.g., team.trivikratech.com -> root path '')
        define('BASE_URL', '');
    }
}

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

// Working Schedule & Shift Configuration (Monday to Saturday, 10 AM to 5 PM, 6h Work + 1h Break)
define('WORKING_DAYS', 'Monday to Saturday');
define('WORKING_SHIFT_START', '10:00 AM');
define('WORKING_SHIFT_END', '05:00 PM');
define('WORKING_SHIFT_TOTAL_HOURS', 7); // 7 Hours total shift span
define('WORKING_HOURS_PER_DAY', 6);     // 6 Hours net working time required
define('BREAK_HOURS_PER_DAY', 1);       // 1 Hour break excluded from net working hours
define('HALF_DAY_HOURS', 3);            // 3 Hours for half-day

// User status constants
define('USER_ACTIVE', 'active');
define('USER_INACTIVE', 'inactive');

// Pagination
define('RECORDS_PER_PAGE', 15);
