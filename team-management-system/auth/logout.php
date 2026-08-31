<?php
/**
 * Logout Handler
 * 
 * Destroys the session and redirects to login page.
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

logoutUser();

header('Location: ' . BASE_URL . '/auth/login.php');
exit;
