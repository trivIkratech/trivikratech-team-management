<?php
/**
 * Authentication Helpers
 * 
 * Session management and user authentication functions.
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
    session_start();
}

/**
 * Check if user is logged in
 */
function isLoggedIn(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Get current user data from session
 */
function getCurrentUser(): ?array {
    if (!isLoggedIn()) {
        return null;
    }
    
    return [
        'id' => $_SESSION['user_id'],
        'name' => $_SESSION['user_name'],
        'email' => $_SESSION['user_email'],
        'role' => $_SESSION['user_role']
    ];
}

/**
 * Get current user's role
 */
function getUserRole(): ?string {
    return $_SESSION['user_role'] ?? null;
}

/**
 * Get current user's ID
 */
function getUserId(): ?int {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Get current user's name
 */
function getUserName(): ?string {
    return $_SESSION['user_name'] ?? null;
}

/**
 * Authenticate user with email and password
 */
function authenticateUser(string $email, string $passwordOrPin): array {
    $db = getDB();
    
    $stmt = $db->prepare("SELECT id, name, email, password, pin, role, status FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if (!$user) {
        return ['success' => false, 'message' => 'Invalid email, password, or PIN.'];
    }
    
    if ($user['status'] !== USER_ACTIVE) {
        return ['success' => false, 'message' => 'Your account has been deactivated. Contact the administrator.'];
    }
    
    $authenticated = false;
    
    // Check if input is a 4-digit PIN and match against user pin
    if (preg_match('/^\d{4}$/', $passwordOrPin) && !empty($user['pin'])) {
        if (password_verify($passwordOrPin, $user['pin'])) {
            $authenticated = true;
        }
    }
    
    // If not authenticated via PIN, check via standard password
    if (!$authenticated && password_verify($passwordOrPin, $user['password'])) {
        $authenticated = true;
    }
    
    if (!$authenticated) {
        return ['success' => false, 'message' => 'Invalid email, password, or PIN.'];
    }
    
    // Regenerate session ID to prevent session fixation
    session_regenerate_id(true);
    
    // Store user data in session
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['login_time'] = time();
    
    return ['success' => true, 'role' => $user['role']];
}

/**
 * Log out the current user
 */
function logoutUser(): void {
    $_SESSION = [];
    
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    session_destroy();
}

/**
 * Get dashboard URL based on user role
 */
function getDashboardUrl(string $role): string {
    return match($role) {
        ROLE_FOUNDER  => BASE_URL . '/founder/dashboard.php',
        ROLE_MANAGER  => BASE_URL . '/manager/dashboard.php',
        ROLE_HR       => BASE_URL . '/hr/dashboard.php',
        ROLE_EMPLOYEE => BASE_URL . '/employee/dashboard.php',
        default       => BASE_URL . '/auth/login.php'
    };
}
