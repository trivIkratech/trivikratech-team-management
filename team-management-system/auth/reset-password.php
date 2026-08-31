<?php
/**
 * Reset Password Page
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/middleware.php';

// Ensure the user has been verified
if (!isset($_SESSION['reset_user_id'])) {
    setFlash('error', 'Please verify your identity first.');
    header('Location: ' . BASE_URL . '/auth/forgot-password.php?fresh=1');
    exit;
}

$error = '';
$userId = $_SESSION['reset_user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    
    $temporaryPassword = $_POST['temporary_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    if (empty($temporaryPassword) || empty($newPassword) || empty($confirmPassword)) {
        $error = 'All fields are required.';
    } elseif (strlen($newPassword) < 6) {
        $error = 'New password must be at least 6 characters.';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'New passwords do not match.';
    } else {
        try {
            $db = getDB();
            
            // Fetch current password from database (which should match the temporary password)
            $stmt = $db->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $currentDbHash = $stmt->fetchColumn();
            
            // Verify entered temporary password matches the DB hash
            if (!$currentDbHash || !password_verify($temporaryPassword, $currentDbHash)) {
                $error = 'Incorrect temporary password. Please enter the generated temporary password.';
            } else {
                // Successful verification! Hash and update the new permanent password
                $newHash = password_hash($newPassword, PASSWORD_BCRYPT);
                
                $updateStmt = $db->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
                $updateStmt->execute([$newHash, $userId]);
                
                // Clear session reset variables
                unset($_SESSION['reset_user_id']);
                unset($_SESSION['temp_password']);
                
                // Log out current user session before showing login screen
                logoutUser();
                
                if (session_status() === PHP_SESSION_NONE) {
                    session_start();
                }
                
                setFlash('success', 'Your password has been reset successfully. Please log in with your new password.');
                header('Location: ' . BASE_URL . '/auth/login.php');
                exit;
            }
        } catch (Exception $e) {
            error_log("Error resetting password: " . $e->getMessage());
            $error = 'Could not update your password. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password — <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
</head>
<body>
    <div class="login-page">
        <div class="login-container">
            <div class="login-card fade-in">
                <!-- Brand -->
                <div class="login-brand">
                    <div class="login-brand-icon">🔒</div>
                    <h1>Reset Password</h1>
                    <p>Enter temporary and new password</p>
                </div>

                <!-- Error Message -->
                <?php if ($error): ?>
                    <div class="alert alert-error" style="margin-bottom: var(--space-5);">
                        <span><?php echo e($error); ?></span>
                    </div>
                <?php endif; ?>

                <!-- Reset Password Form -->
                <form method="POST" action="" class="login-form" data-validate>
                    <?php echo csrfField(); ?>
                    
                    <div class="form-group">
                        <label for="temporary_password" class="form-label">Temporary Password *</label>
                        <input 
                            type="password" 
                            id="temporary_password" 
                            name="temporary_password" 
                            class="form-input" 
                            placeholder="Enter the generated temporary password" 
                            required
                            autofocus
                        >
                    </div>

                    <div class="form-group">
                        <label for="new_password" class="form-label">New Password *</label>
                        <input 
                            type="password" 
                            id="new_password" 
                            name="new_password" 
                            class="form-input" 
                            placeholder="Min 6 characters" 
                            required
                            minlength="6"
                        >
                    </div>

                    <div class="form-group">
                        <label for="confirm_password" class="form-label">Confirm New Password *</label>
                        <input 
                            type="password" 
                            id="confirm_password" 
                            name="confirm_password" 
                            class="form-input" 
                            placeholder="Confirm new password" 
                            required
                            minlength="6"
                        >
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg">
                        Update Password →
                    </button>
                    
                    <div style="text-align: center; margin-top: var(--space-4);">
                        <a href="<?php echo BASE_URL; ?>/auth/forgot-password.php?fresh=1" style="color: var(--color-text-secondary); text-decoration: none; font-size: var(--text-sm);">← Go Back</a>
                    </div>
                </form>
            </div>
            
            <p class="text-center text-muted" style="margin-top: var(--space-5); font-size: var(--text-xs);">
                <?php echo APP_NAME; ?> v<?php echo APP_VERSION; ?>
            </p>
        </div>
    </div>
</body>
</html>
