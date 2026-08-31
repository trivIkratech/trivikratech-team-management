<?php
/**
 * Login Page
 * 
 * Handles both the login form display (GET) and authentication (POST).
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/middleware.php';

// Redirect to dashboard if already logged in
redirectIfLoggedIn();

$error = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    
    $email = post('email');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = 'Please enter email and password/PIN.';
    } else {
        $result = authenticateUser($email, $password);
        
        if ($result['success']) {
            header('Location: ' . getDashboardUrl($result['role']));
            exit;
        } else {
            $error = $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Login to Team Management System">
    <title>Login — <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
</head>
<body>
    <div class="login-page">
        <div class="login-container">
            <div class="login-card fade-in">
                <!-- Brand -->
                <div class="login-brand">
                    <div class="login-brand-icon">TM</div>
                    <h1>Team Manager</h1>
                    <p>Sign in to your account</p>
                </div>

                <!-- Messages -->
                <?php echo renderFlash(); ?>
                <?php if ($error): ?>
                    <div class="alert alert-error" style="margin-bottom: var(--space-5);">
                        <span><?php echo e($error); ?></span>
                    </div>
                <?php endif; ?>

                <!-- Login Form -->
                <form method="POST" action="" class="login-form" data-validate>
                    <?php echo csrfField(); ?>
                    
                    <div class="form-group">
                        <label for="email" class="form-label">Email Address</label>
                        <input 
                            type="email" 
                            id="email" 
                            name="email" 
                            class="form-input" 
                            placeholder="you@company.com" 
                            value="<?php echo e(post('email')); ?>"
                            required
                            autocomplete="email"
                            autofocus
                        >
                    </div>

                    <div class="form-group">
                        <label for="password" class="form-label">Password or 4-digit PIN</label>
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            class="form-input" 
                            placeholder="Enter password or 4-digit PIN" 
                            required
                            autocomplete="current-password"
                        >
                        <div style="display: flex; justify-content: flex-end; gap: var(--space-2); align-items: center; margin-top: 6px;">
                            <a href="<?php echo BASE_URL; ?>/auth/forgot-password.php?fresh=1" style="color: var(--color-primary); font-size: var(--text-xs); text-decoration: none; font-weight: 500;">Forgot Password?</a>
                            <span style="color: var(--color-text-muted); font-size: var(--text-xs);">•</span>
                            <a href="<?php echo BASE_URL; ?>/auth/forgot-pin.php" style="color: var(--color-primary); font-size: var(--text-xs); text-decoration: none; font-weight: 500;">Forgot PIN?</a>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg">
                        Sign In →
                    </button>
                </form>
            </div>

            <p class="text-center text-muted" style="margin-top: var(--space-5); font-size: var(--text-xs);">
                <?php echo APP_NAME; ?> v<?php echo APP_VERSION; ?>
            </p>
        </div>
    </div>
</body>
</html>
