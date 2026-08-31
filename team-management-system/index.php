<?php
/**
 * Entry Point — index.php
 * 
 * Redirects to dashboard if logged in, otherwise to login page.
 */

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/middleware.php';

if (isLoggedIn()) {
    $role = getUserRole();
    header('Location: ' . getDashboardUrl($role));
} else {
    header('Location: ' . BASE_URL . '/auth/login.php');
}
exit;
