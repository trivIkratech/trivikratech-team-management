<?php
/**
 * Login Page
 * 
 * Handles both the login form display (GET) and authentication (POST) with Dark/Light Theme Switcher.
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
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Login to Team Management System">
    <title>Login — <?php echo APP_NAME; ?></title>
    <!-- Zero-Flicker Theme Initializer -->
    <script>
    (function() {
        const savedTheme = localStorage.getItem('app_theme') || 'dark';
        document.documentElement.setAttribute('data-theme', savedTheme);
    })();
    </script>
    <!-- Font Awesome 6 CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
</head>
<body>
    <!-- Theme Switcher Top Right Button -->
    <div style="position: absolute; top: 20px; right: 20px; z-index: 100;">
        <button type="button" id="theme-toggle-btn" onclick="toggleLoginTheme()" style="cursor: pointer; display: flex; align-items: center; justify-content: center; width: 40px; height: 40px; border-radius: 50%; background: var(--color-bg-card); border: 1px solid var(--color-border); color: var(--color-text-main); font-size: 16px; box-shadow: var(--shadow-md);" title="Switch Light/Dark Mode">
            <i class="fa-solid fa-moon" id="theme-toggle-icon"></i>
        </button>
    </div>

    <div class="login-page">
        <div class="login-container">
            <div class="login-card fade-in">
                <!-- Brand -->
                <div class="login-brand">
                    <div class="login-brand-icon"><i class="fa-solid fa-layer-group"></i></div>
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
                        <div style="position: relative;">
                            <input 
                                type="password" 
                                id="password" 
                                name="password" 
                                class="form-input" 
                                placeholder="Enter password or 4-digit PIN" 
                                required
                                autocomplete="current-password"
                                style="padding-right: 44px;"
                            >
                            <button type="button" onclick="togglePassword('password', this)" style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); background: none; border: none; color: var(--color-text-secondary); cursor: pointer; font-size: 15px; padding: 4px;" title="Toggle Visibility">
                                <i class="fa-regular fa-eye"></i>
                            </button>
                        </div>
                        <div style="display: flex; justify-content: flex-end; gap: var(--space-2); align-items: center; margin-top: 6px;">
                            <a href="<?php echo BASE_URL; ?>/auth/forgot-password.php?fresh=1" style="color: var(--color-primary); font-size: var(--text-xs); text-decoration: none; font-weight: 500;">Forgot Password?</a>
                            <span style="color: var(--color-text-muted); font-size: var(--text-xs);">•</span>
                            <a href="<?php echo BASE_URL; ?>/auth/forgot-pin.php" style="color: var(--color-primary); font-size: var(--text-xs); text-decoration: none; font-weight: 500;">Forgot PIN?</a>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg" style="width: 100%;">
                        Sign In →
                    </button>
                </form>
            </div>

            <p class="text-center text-muted" style="margin-top: var(--space-5); font-size: var(--text-xs);">
                <?php echo APP_NAME; ?> v<?php echo APP_VERSION; ?>
            </p>
        </div>
    </div>

    <script>
    function toggleLoginTheme() {
        const currentTheme = document.documentElement.getAttribute('data-theme') || 'dark';
        const newTheme = (currentTheme === 'dark') ? 'light' : 'dark';
        document.documentElement.setAttribute('data-theme', newTheme);
        localStorage.setItem('app_theme', newTheme);
        updateLoginThemeIcon(newTheme);
    }

    function updateLoginThemeIcon(theme) {
        const icon = document.getElementById('theme-toggle-icon');
        if (icon) {
            if (theme === 'light') {
                icon.className = 'fa-solid fa-sun';
                icon.style.color = '#f59e0b';
            } else {
                icon.className = 'fa-solid fa-moon';
                icon.style.color = 'var(--color-text-main)';
            }
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        const theme = document.documentElement.getAttribute('data-theme') || 'dark';
        updateLoginThemeIcon(theme);
    });

    function togglePassword(inputId, btn) {
        const input = document.getElementById(inputId);
        if (!input) return;
        const icon = btn.querySelector('i');
        if (input.type === 'password') {
            input.type = 'text';
            if (icon) icon.className = 'fa-regular fa-eye-slash';
        } else {
            input.type = 'password';
            if (icon) icon.className = 'fa-regular fa-eye';
        }
    }
    </script>
</body>
</html>
