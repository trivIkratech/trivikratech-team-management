<?php
/**
 * Database Configuration & Connection
 * 
 * PDO-based MySQL connection with prepared statement enforcement.
 * Update the credentials below for your Hostinger hosting.
 */

// Database credentials — UPDATE THESE FOR PRODUCTION
define('DB_HOST', 'localhost');
define('DB_NAME', 'team_management');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

/**
 * Get PDO database connection (singleton)
 * 
 * @return PDO
 * @throws PDOException
 */
function getDB(): PDO {
    static $pdo = null;
    
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        
        $initCmd = defined('Pdo\Mysql::ATTR_INIT_COMMAND') 
            ? Pdo\Mysql::ATTR_INIT_COMMAND 
            : (defined('PDO::MYSQL_ATTR_INIT_COMMAND') ? PDO::MYSQL_ATTR_INIT_COMMAND : 1002);

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            $initCmd                     => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        ];
        
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            // Synchronize MySQL session time_zone with APP_TIMEZONE (Asia/Kolkata: UTC+05:30)
            $pdo->exec("SET time_zone = '+05:30'");
        } catch (PDOException $e) {
            // In production, log the error and show a generic message
            error_log("Database Connection Error: " . $e->getMessage());
            die("Database connection failed. Please contact the administrator.");
        }
    }
    
    return $pdo;
}
