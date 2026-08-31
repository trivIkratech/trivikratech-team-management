<?php
/**
 * Middleware — Role-Based Access Control
 * 
 * Include this at the top of every protected page.
 */

/**
 * Require user to be logged in, redirect to login if not
 */
function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . '/auth/login.php');
        exit;
    }
}

/**
 * Require specific role(s), show 403 or redirect if unauthorized
 * 
 * @param array $allowedRoles Array of allowed role constants
 */
function requireRole(array $allowedRoles): void {
    requireLogin();
    
    $userRole = getUserRole();
    
    if (!in_array($userRole, $allowedRoles)) {
        // Redirect to their own dashboard instead of showing error
        header('Location: ' . getDashboardUrl($userRole));
        exit;
    }
}

/**
 * Redirect logged-in users to their dashboard
 */
function redirectIfLoggedIn(): void {
    if (isLoggedIn()) {
        $role = getUserRole();
        header('Location: ' . getDashboardUrl($role));
        exit;
    }
}

/**
 * Check if current user has a specific role
 */
function hasRole(string $role): bool {
    return getUserRole() === $role;
}

/**
 * Check if current user is founder
 */
function isFounder(): bool {
    return hasRole(ROLE_FOUNDER);
}

/**
 * Check if current user is manager
 */
function isManager(): bool {
    return hasRole(ROLE_MANAGER);
}

/**
 * Check if current user is employee
 */
function isEmployee(): bool {
    return hasRole(ROLE_EMPLOYEE);
}

/**
 * Check if current user is HR
 */
function isHR(): bool {
    return hasRole(ROLE_HR);
}
