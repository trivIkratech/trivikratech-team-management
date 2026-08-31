<?php
/**
 * Forgot Security PIN Verification Page (Employee ID only)
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/middleware.php';

$error = '';
$success = '';

// Clear temporary PINs if accessing the forgot page fresh (without POSTing)
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && isset($_GET['fresh'])) {
    unset($_SESSION['temp_pin']);
    unset($_SESSION['reset_pin_user_id']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('form_action') === 'verify_identity') {
    requireCsrf();
    
    $employeeId = post('employee_id');
    
    if (empty($employeeId)) {
        $error = 'Employee ID is required.';
    } else {
        try {
            $db = getDB();
            $stmt = $db->prepare("SELECT * FROM users WHERE employee_id = ?");
            $stmt->execute([$employeeId]);
            $user = $stmt->fetch();
            
            if ($user) {
                // Identity verified! Generate a temporary 4-digit PIN
                $tempPin = sprintf("%04d", rand(0, 9999));
                $tempPinHash = password_hash($tempPin, PASSWORD_BCRYPT);
                
                // Update database PIN to temporary hash
                $updateStmt = $db->prepare("UPDATE users SET pin = ?, updated_at = NOW() WHERE id = ?");
                $updateStmt->execute([$tempPinHash, $user['id']]);
                
                // Store verification data in session
                $_SESSION['reset_pin_user_id'] = $user['id'];
                $_SESSION['temp_pin'] = $tempPin;
                
                // Redirect back to same page to render PIN
                header('Location: ' . BASE_URL . '/auth/forgot-pin.php');
                exit;
            } else {
                $error = 'Verification failed. Employee ID not found.';
            }
        } catch (Exception $e) {
            error_log("Error verifying identity: " . $e->getMessage());
            $error = 'A system error occurred. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot PIN — <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
</head>
<body>
    <div class="login-page">
        <div class="login-container">
            <div class="login-card fade-in">
                
                <!-- Success State: Display Temporary PIN -->
                <?php if (isset($_SESSION['temp_pin'])): ?>
                    <div class="login-brand">
                        <div class="login-brand-icon">🔢</div>
                        <h1>Temporary PIN</h1>
                        <p>A temporary PIN has been generated for you</p>
                    </div>
                    
                    <div style="background-color: var(--color-bg-tertiary); border: 1px dashed var(--color-primary); padding: var(--space-4); border-radius: var(--radius-md); text-align: center; margin-bottom: var(--space-5);">
                        <span style="font-size: var(--text-xs); color: var(--color-text-muted); display: block; margin-bottom: var(--space-2); text-transform: uppercase; letter-spacing: 1px;">Copy Temporary PIN</span>
                        <code style="font-size: var(--text-lg); font-weight: bold; color: var(--color-primary); letter-spacing: 2px; font-family: monospace; user-select: all;"><?php echo e($_SESSION['temp_pin']); ?></code>
                    </div>

                    <p class="text-muted text-center" style="font-size: var(--text-sm); margin-bottom: var(--space-5); line-height: 1.5;">
                        Please copy the temporary 4-digit PIN above. You will need to enter it on the next page to reset your PIN.
                    </p>

                    <a href="<?php echo BASE_URL; ?>/auth/reset-pin.php" class="btn btn-primary btn-lg" style="text-align: center; text-decoration: none; display: block;">
                        Proceed to Reset PIN →
                    </a>

                <!-- Request Form State -->
                <?php else: ?>
                    <!-- Brand -->
                    <div class="login-brand">
                        <div class="login-brand-icon">🔢</div>
                        <h1>Forgot Security PIN</h1>
                        <p>Enter Employee ID to request temporary PIN</p>
                    </div>

                    <!-- Messages -->
                    <?php if ($error): ?>
                        <div class="alert alert-error" style="margin-bottom: var(--space-5);">
                            <span><?php echo e($error); ?></span>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="" class="login-form" data-validate>
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="form_action" value="verify_identity">
                        
                        <div class="form-group">
                            <label for="employee_id" class="form-label">Employee ID No</label>
                            <input 
                                type="text" 
                                id="employee_id" 
                                name="employee_id" 
                                class="form-input" 
                                placeholder="e.g. EMP-004" 
                                value="<?php echo e(post('employee_id')); ?>"
                                required
                                autofocus
                            >
                        </div>

                        <button type="submit" class="btn btn-primary btn-lg">
                            Request Temporary PIN →
                        </button>
                        
                        <div style="text-align: center; margin-top: var(--space-4);">
                            <a href="<?php echo BASE_URL; ?>/auth/login.php" style="color: var(--color-text-secondary); text-decoration: none; font-size: var(--text-sm);">← Back to Login</a>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
            
            <p class="text-center text-muted" style="margin-top: var(--space-5); font-size: var(--text-xs);">
                <?php echo APP_NAME; ?> v<?php echo APP_VERSION; ?>
            </p>
        </div>
    </div>
</body>
</html>
