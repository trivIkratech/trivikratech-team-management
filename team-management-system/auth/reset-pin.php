<?php
/**
 * Reset Security PIN Page
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/middleware.php';

// Ensure the user has been verified
if (!isset($_SESSION['reset_pin_user_id'])) {
    setFlash('error', 'Please verify your identity first.');
    header('Location: ' . BASE_URL . '/auth/forgot-pin.php?fresh=1');
    exit;
}

$error = '';
$userId = $_SESSION['reset_pin_user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    
    $temporaryPin = post('temporary_pin');
    $newPin = post('new_pin');
    $confirmPin = post('confirm_pin');
    
    if (empty($temporaryPin) || empty($newPin) || empty($confirmPin)) {
        $error = 'All fields are required.';
    } elseif (!preg_match('/^\d{4}$/', $newPin)) {
        $error = 'New PIN must be exactly 4 digits.';
    } elseif ($newPin !== $confirmPin) {
        $error = 'New PINs do not match.';
    } else {
        try {
            $db = getDB();
            
            // Fetch current PIN from database (which should match the temporary PIN hash)
            $stmt = $db->prepare("SELECT pin FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $currentDbHash = $stmt->fetchColumn();
            
            // Verify entered temporary PIN matches database hash
            if (!$currentDbHash || !password_verify($temporaryPin, $currentDbHash)) {
                $error = 'Incorrect temporary PIN. Please enter the generated temporary PIN.';
            } else {
                // Successful verification! Hash and save new permanent PIN
                $newHash = password_hash($newPin, PASSWORD_BCRYPT);
                
                $updateStmt = $db->prepare("UPDATE users SET pin = ?, updated_at = NOW() WHERE id = ?");
                $updateStmt->execute([$newHash, $userId]);
                
                // Clear session variables
                unset($_SESSION['reset_pin_user_id']);
                unset($_SESSION['temp_pin']);
                
                // Log out current user session before showing login screen
                logoutUser();
                
                if (session_status() === PHP_SESSION_NONE) {
                    session_start();
                }
                
                setFlash('success', 'Your security PIN has been reset successfully. Please log in with your new PIN.');
                header('Location: ' . BASE_URL . '/auth/login.php');
                exit;
            }
        } catch (Exception $e) {
            error_log("Error resetting PIN: " . $e->getMessage());
            $error = 'Could not update your PIN. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Security PIN — <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
</head>
<body>
    <div class="login-page">
        <div class="login-container">
            <div class="login-card fade-in">
                <!-- Brand -->
                <div class="login-brand">
                    <div class="login-brand-icon"><i class="fa-solid fa-key"></i></div>
                    <h1>Reset PIN</h1>
                    <p>Enter temporary and new security PIN</p>
                </div>

                <!-- Error Message -->
                <?php if ($error): ?>
                    <div class="alert alert-error" style="margin-bottom: var(--space-5);">
                        <span><?php echo e($error); ?></span>
                    </div>
                <?php endif; ?>

                <!-- Reset PIN Form -->
                <form method="POST" action="" class="login-form" data-validate>
                    <?php echo csrfField(); ?>
                    
                    <div class="form-group">
                        <label for="temporary_pin" class="form-label">Temporary PIN *</label>
                        <input 
                            type="password" 
                            id="temporary_pin" 
                            name="temporary_pin" 
                            class="form-input" 
                            placeholder="Enter the generated temporary PIN" 
                            required
                            maxlength="4"
                            pattern="\d{4}"
                            autofocus
                        >
                    </div>

                    <div class="form-group">
                        <label for="new_pin" class="form-label">New 4-Digit PIN *</label>
                        <input 
                            type="password" 
                            id="new_pin" 
                            name="new_pin" 
                            class="form-input" 
                            placeholder="e.g. 1234" 
                            required
                            maxlength="4"
                            pattern="\d{4}"
                        >
                    </div>

                    <div class="form-group">
                        <label for="confirm_pin" class="form-label">Confirm New PIN *</label>
                        <input 
                            type="password" 
                            id="confirm_pin" 
                            name="confirm_pin" 
                            class="form-input" 
                            placeholder="Confirm new 4-digit PIN" 
                            required
                            maxlength="4"
                            pattern="\d{4}"
                        >
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg">
                        Update PIN →
                    </button>
                    
                    <div style="text-align: center; margin-top: var(--space-4);">
                        <a href="<?php echo BASE_URL; ?>/auth/forgot-pin.php?fresh=1" style="color: var(--color-text-secondary); text-decoration: none; font-size: var(--text-sm);">← Go Back</a>
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
