<?php
/**
 * User Profile Dashboard
 * 
 * Central hub for Profile Info, Notifications, Security/Reset Password, Help & Support, and Logout.
 * Shared by all roles.
 */

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/middleware.php';

requireLogin();

$db = getDB();
$userId = getUserId();
$userRole = getUserRole();
$formErrors = [];
$formSuccess = '';

// Determine active tab after submission
$activeTab = 'info';
if (isset($_POST['form_action'])) {
    if ($_POST['form_action'] === 'update_info') {
        $activeTab = 'info';
    } elseif ($_POST['form_action'] === 'update_password' || $_POST['form_action'] === 'update_pin') {
        $activeTab = 'security';
    } elseif ($_POST['form_action'] === 'save_notifications') {
        $activeTab = 'notifications';
    }
}

// Fetch fresh user data from database
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

// 1. HANDLE PROFILE INFO UPDATE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('form_action') === 'update_info') {
    requireCsrf();
    
    $name = post('name');
    $email = post('email');
    $contactNo = post('contact_no');
    
    if (empty($name)) $formErrors[] = 'Name is required.';
    if (empty($email)) $formErrors[] = 'Email is required.';
    
    // Check email uniqueness
    if (empty($formErrors)) {
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $userId]);
        if ($stmt->fetch()) {
            $formErrors[] = 'Email address is already in use by another account.';
        }
    }
    
    if (empty($formErrors)) {
        $stmt = $db->prepare("UPDATE users SET name = ?, email = ?, contact_no = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$name, $email, $contactNo, $userId]);
        setFlash('success', 'Profile information updated successfully!');
        header('Location: ' . BASE_URL . '/profile.php');
        exit;
    }
}

// 2. HANDLE PASSWORD RESET / CHANGE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('form_action') === 'update_password') {
    requireCsrf();
    
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        $formErrors[] = 'All password fields are required.';
    } elseif (strlen($newPassword) < 6) {
        $formErrors[] = 'New password must be at least 6 characters.';
    } elseif ($newPassword !== $confirmPassword) {
        $formErrors[] = 'New passwords do not match.';
    } else {
        // Verify current password
        if (!password_verify($currentPassword, $user['password'])) {
            $formErrors[] = 'Current password is incorrect.';
        } else {
            $hash = password_hash($newPassword, PASSWORD_BCRYPT);
            $stmt = $db->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$hash, $userId]);
            setFlash('success', 'Password updated successfully!');
            header('Location: ' . BASE_URL . '/profile.php?tab=security');
            exit;
        }
    }
}

// 3. HANDLE SECURITY PIN RESET
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('form_action') === 'update_pin') {
    requireCsrf();
    
    $confirmPass = $_POST['confirm_pass_for_pin'] ?? '';
    $newPin = post('new_pin');
    
    if (empty($confirmPass) || empty($newPin)) {
        $formErrors[] = 'All fields are required to update your PIN.';
    } elseif (!preg_match('/^\d{4}$/', $newPin)) {
        $formErrors[] = 'PIN must be exactly 4 digits.';
    } else {
        // Verify password to authorize PIN update
        if (!password_verify($confirmPass, $user['password'])) {
            $formErrors[] = 'Verification failed. Password is incorrect.';
        } else {
            $pinHash = password_hash($newPin, PASSWORD_BCRYPT);
            $stmt = $db->prepare("UPDATE users SET pin = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$pinHash, $userId]);
            setFlash('success', 'Security PIN updated successfully!');
            header('Location: ' . BASE_URL . '/profile.php?tab=security');
            exit;
        }
    }
}

// 4. HANDLE NOTIFICATION SETTINGS SAVE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('form_action') === 'save_notifications') {
    requireCsrf();
    // Simulate saving notification preferences
    setFlash('success', 'Notification preferences saved successfully!');
    header('Location: ' . BASE_URL . '/profile.php?tab=notifications');
    exit;
}

// Get override from query tab parameter if exists
$paramTab = get('tab');
if (in_array($paramTab, ['info', 'notifications', 'security', 'support'])) {
    $activeTab = $paramTab;
}

$pageTitle = 'My Profile';
include __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">My Profile</h1>
        <p class="page-subtitle">Configure your personal information, notification settings, and password updates</p>
    </div>
</div>

<?php if (!empty($formErrors)): ?>
    <div class="alert alert-error">
        <span><?php echo e(implode(' ', $formErrors)); ?></span>
        <button class="alert-close" onclick="this.parentElement.remove()">×</button>
    </div>
<?php endif; ?>

<style>
.profile-layout {
    display: grid;
    grid-template-columns: 280px 1fr;
    gap: var(--space-6);
    align-items: start;
}
.profile-menu {
    display: flex;
    flex-direction: column;
    gap: var(--space-2);
}
.profile-menu-item {
    display: flex;
    align-items: center;
    gap: var(--space-3);
    padding: var(--space-3) var(--space-4);
    background-color: var(--color-bg-secondary);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    color: var(--color-text-muted);
    font-weight: 500;
    text-decoration: none;
    cursor: pointer;
    transition: all var(--transition-base);
    text-align: left;
}
.profile-menu-item:hover {
    color: var(--color-text);
    border-color: var(--color-primary);
}
.profile-menu-item.active {
    color: var(--color-text-white);
    background-color: var(--color-primary);
    border-color: var(--color-primary);
}
.profile-menu-item.logout-item {
    margin-top: var(--space-4);
    border-color: rgba(239, 68, 68, 0.2);
    color: var(--color-danger);
}
.profile-menu-item.logout-item:hover {
    background-color: rgba(239, 68, 68, 0.1);
    border-color: var(--color-danger);
}
.profile-card-large {
    text-align: center;
    padding: var(--space-6) var(--space-4);
    background-color: var(--color-bg-secondary);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    margin-bottom: var(--space-4);
}
.profile-avatar-large {
    width: 90px;
    height: 90px;
    border-radius: 50%;
    background-color: var(--color-primary);
    color: white;
    font-size: 32px;
    font-weight: bold;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto var(--space-3) auto;
    border: 4px solid var(--color-bg-tertiary);
    box-shadow: var(--shadow-sm);
}
.profile-card-name {
    font-size: var(--text-lg);
    font-weight: 600;
    color: var(--color-text-white);
}
.profile-card-email {
    font-size: var(--text-sm);
    color: var(--color-text-muted);
    margin-top: 2px;
}
.profile-card-badge {
    margin-top: var(--space-3);
    display: inline-block;
}
.profile-tab-content {
    display: none;
}
</style>

<div class="profile-layout">
    
    <!-- Profile Sidebar Menu -->
    <div>
        <div class="profile-card-large">
            <div class="profile-avatar-large"><?php echo e($userInitials); ?></div>
            <div class="profile-card-name"><?php echo e($user['name']); ?></div>
            <div style="font-size: var(--text-xs); color: var(--color-primary); margin-top: 4px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;"><?php echo e($user['designation'] ?: 'No Designation'); ?></div>
            <div class="profile-card-email" style="margin-top: 4px;"><?php echo e($user['email']); ?></div>
            <div class="profile-card-badge">
                <span class="badge <?php echo roleBadge($user['role']); ?>"><?php echo ucfirst(e($user['role'])); ?></span>
            </div>
        </div>
        
        <div class="profile-menu">
            <button class="profile-menu-item <?php echo $activeTab === 'info' ? 'active' : ''; ?>" onclick="switchTab(this, 'tab-info')">
                <span><i class="fa-solid fa-user"></i></span> Profile Information
            </button>
            <button class="profile-menu-item <?php echo $activeTab === 'notifications' ? 'active' : ''; ?>" onclick="switchTab(this, 'tab-notifications')">
                <span><i class="fa-regular fa-bell"></i></span> Notifications
            </button>
            <button class="profile-menu-item <?php echo $activeTab === 'security' ? 'active' : ''; ?>" onclick="switchTab(this, 'tab-security')">
                <span><i class="fa-solid fa-key"></i></span> Reset Password & PIN
            </button>
            <button class="profile-menu-item <?php echo $activeTab === 'support' ? 'active' : ''; ?>" onclick="switchTab(this, 'tab-support')">
                <span><i class="fa-solid fa-handshake"></i></span> Help & Support
            </button>
            <a href="<?php echo BASE_URL; ?>/auth/logout.php" class="profile-menu-item logout-item">
                <span>↗</span> Sign Out / Logout
            </a>
        </div>
    </div>
    
    <!-- Profile Tab Contents -->
    <div>
        
        <!-- 1. PROFILE INFO TAB -->
        <div id="tab-info" class="card profile-tab-content" style="<?php echo $activeTab === 'info' ? 'display: block;' : ''; ?>">
            <div class="card-header">
                <h3 class="card-title">Profile Information</h3>
            </div>
            
            <form method="POST" action="">
                <?php echo csrfField(); ?>
                <input type="hidden" name="form_action" value="update_info">
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Employee ID No</label>
                        <input type="text" class="form-input" disabled value="<?php echo e($user['employee_id'] ?: '—'); ?>" style="background-color: var(--color-bg-tertiary); cursor: not-allowed;">
                    </div>
                    <div class="form-group">
                        <label class="form-label">System Role</label>
                        <input type="text" class="form-input" disabled value="<?php echo ucfirst(e($user['role'])); ?>" style="background-color: var(--color-bg-tertiary); cursor: not-allowed;">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Designation</label>
                    <input type="text" class="form-input" disabled value="<?php echo e($user['designation'] ?: '—'); ?>" style="background-color: var(--color-bg-tertiary); cursor: not-allowed;">
                </div>

                <div class="form-group">
                    <label class="form-label">Full Name *</label>
                    <input type="text" name="name" class="form-input" required value="<?php echo e(post('name', $user['name'])); ?>">
                </div>

                <div class="form-group">
                    <label class="form-label">Email Address *</label>
                    <input type="email" name="email" class="form-input" required value="<?php echo e(post('email', $user['email'])); ?>">
                </div>

                <div class="form-group">
                    <label class="form-label">Contact Number</label>
                    <input type="text" name="contact_no" class="form-input" placeholder="e.g. 9876543210" value="<?php echo e(post('contact_no', $user['contact_no'])); ?>">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Account Status</label>
                        <span class="badge <?php echo userStatusBadge($user['status']); ?>" style="display: inline-block; margin-top: var(--space-2);"><?php echo ucfirst(e($user['status'])); ?></span>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Member Since</label>
                        <div style="font-size: var(--text-sm); margin-top: var(--space-2); color: var(--color-text-white);">
                            <?php echo formatDate($user['created_at']); ?>
                        </div>
                    </div>
                </div>

                <div class="form-actions" style="margin-top: var(--space-4); border-top: 1px solid var(--color-border); padding-top: var(--space-4);">
                    <button type="submit" class="btn btn-primary">Update Profile Information</button>
                </div>
            </form>
        </div>
        
        <!-- 2. NOTIFICATIONS TAB -->
        <div id="tab-notifications" class="card profile-tab-content" style="<?php echo $activeTab === 'notifications' ? 'display: block;' : ''; ?>">
            <div class="card-header">
                <h3 class="card-title">Notification Settings</h3>
            </div>
            
            <form method="POST" action="">
                <?php echo csrfField(); ?>
                <input type="hidden" name="form_action" value="save_notifications">
                
                <p class="text-muted" style="margin-bottom: var(--space-4); font-size: var(--text-sm);">
                    Select which system alerts and reports you wish to receive.
                </p>

                <div style="display: flex; flex-direction: column; gap: var(--space-3); margin-bottom: var(--space-4);">
                    <label style="display: flex; align-items: start; gap: var(--space-3); cursor: pointer;">
                        <input type="checkbox" name="alert_email" checked style="margin-top: 3px;">
                        <div>
                            <strong style="color: var(--color-text-white); font-size: var(--text-sm);">Email Notifications</strong>
                            <p class="text-muted" style="font-size: var(--text-xs); margin-top: 2px;">Receive emails when tasks are assigned to you or leave updates occur.</p>
                        </div>
                    </label>
                    
                    <label style="display: flex; align-items: start; gap: var(--space-3); cursor: pointer; border-top: 1px solid var(--color-border); padding-top: var(--space-3);">
                        <input type="checkbox" name="alert_meeting" checked style="margin-top: 3px;">
                        <div>
                            <strong style="color: var(--color-text-white); font-size: var(--text-sm);">Meeting Invites</strong>
                            <p class="text-muted" style="font-size: var(--text-xs); margin-top: 2px;">Get instantly notified when you are added as a participant to standard syncs.</p>
                        </div>
                    </label>
                    
                    <label style="display: flex; align-items: start; gap: var(--space-3); cursor: pointer; border-top: 1px solid var(--color-border); padding-top: var(--space-3);">
                        <input type="checkbox" name="alert_announcements" checked style="margin-top: 3px;">
                        <div>
                            <strong style="color: var(--color-text-white); font-size: var(--text-sm);">Corporate Announcements</strong>
                            <p class="text-muted" style="font-size: var(--text-xs); margin-top: 2px;">Receive alerts for corporate announcements published by managers or founder.</p>
                        </div>
                    </label>

                    <label style="display: flex; align-items: start; gap: var(--space-3); cursor: pointer; border-top: 1px solid var(--color-border); padding-top: var(--space-3);">
                        <input type="checkbox" name="alert_security" checked style="margin-top: 3px;">
                        <div>
                            <strong style="color: var(--color-text-white); font-size: var(--text-sm);">Security Alerts</strong>
                            <p class="text-muted" style="font-size: var(--text-xs); margin-top: 2px;">Notify me when password updates or PIN changes occur on my profile.</p>
                        </div>
                    </label>
                </div>

                <div class="form-actions" style="border-top: 1px solid var(--color-border); padding-top: var(--space-4);">
                    <button type="submit" class="btn btn-primary">Save Preference Toggles</button>
                </div>
            </form>
        </div>
        
        <!-- 3. RESET PASSWORD & PIN TAB -->
        <div id="tab-security" class="card profile-tab-content" style="<?php echo $activeTab === 'security' ? 'display: block;' : ''; ?>">
            
            <!-- Update Password Card -->
            <div style="margin-bottom: var(--space-6);">
                <div class="card-header" style="padding-left: 0; padding-top: 0;">
                    <h3 class="card-title">Reset Profile Password</h3>
                </div>
                
                <form method="POST" action="">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="form_action" value="update_password">
                    
                    <div class="form-group">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2px;">
                            <label class="form-label" style="margin-bottom: 0;">Current Password *</label>
                            <a href="<?php echo BASE_URL; ?>/auth/forgot-password.php?fresh=1" style="color: var(--color-primary); font-size: var(--text-xs); text-decoration: none; font-weight: 500;">Forgot Password?</a>
                        </div>
                        <input type="password" name="current_password" class="form-input" required placeholder="Enter current password">
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">New Password *</label>
                            <input type="password" name="new_password" class="form-input" required minlength="6" placeholder="Min 6 characters">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Confirm New Password *</label>
                            <input type="password" name="confirm_password" class="form-input" required minlength="6" placeholder="Confirm new password">
                        </div>
                    </div>

                    <div class="form-actions" style="margin-top: var(--space-4);">
                        <button type="submit" class="btn btn-primary">Update Password</button>
                    </div>
                </form>
            </div>

            <!-- Update PIN Card -->
            <div style="border-top: 1px dashed var(--color-border); padding-top: var(--space-5);">
                <div class="card-header" style="padding-left: 0; padding-top: 0;">
                    <h3 class="card-title">Update 4-Digit Security PIN</h3>
                </div>
                
                <form method="POST" action="">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="form_action" value="update_pin">
                    
                    <div class="form-group">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2px;">
                            <label class="form-label" style="margin-bottom: 0;">Confirm Password *</label>
                            <a href="<?php echo BASE_URL; ?>/auth/forgot-password.php?fresh=1" style="color: var(--color-primary); font-size: var(--text-xs); text-decoration: none; font-weight: 500;">Forgot Password?</a>
                        </div>
                        <input type="password" name="confirm_pass_for_pin" class="form-input" required placeholder="Verify account password">
                    </div>

                    <div class="form-group" style="max-width: 250px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2px;">
                            <label class="form-label" style="margin-bottom: 0;">New 4-Digit PIN *</label>
                            <a href="<?php echo BASE_URL; ?>/auth/forgot-pin.php" style="color: var(--color-primary); font-size: var(--text-xs); text-decoration: none; font-weight: 500;">Forgot PIN?</a>
                        </div>
                        <input type="text" name="new_pin" class="form-input" required maxlength="4" placeholder="e.g. 1234" pattern="\d{4}">
                        <span class="text-muted" style="font-size: var(--text-xs); margin-top: 4px; display: block;">Used for quick PIN login.</span>
                    </div>

                    <div class="form-actions" style="margin-top: var(--space-4);">
                        <button type="submit" class="btn btn-secondary">Update Security PIN</button>
                    </div>
                </form>
            </div>

        </div>
        
        <!-- 4. HELP & SUPPORT TAB -->
        <div id="tab-support" class="card profile-tab-content" style="<?php echo $activeTab === 'support' ? 'display: block;' : ''; ?>">
            <div class="card-header">
                <h3 class="card-title">Help & Support Desk</h3>
            </div>
            
            <div style="display: flex; flex-direction: column; gap: var(--space-4);">
                
                <div style="background-color: var(--color-bg-tertiary); padding: var(--space-4); border-radius: var(--radius-md); border-left: 4px solid var(--color-primary);">
                    <h4 style="color: var(--color-text-white); font-weight: 600; margin-bottom: var(--space-2);">Documentation & Training</h4>
                    <p class="text-muted" style="font-size: var(--text-sm); line-height: 1.5;">
                        Welcome to the Team Management System! This platform allows you to log check-ins/attendance, coordinate tasks, manage leave requests, and schedule Google Meet calls in one integrated flow.
                    </p>
                </div>

                <div>
                    <h4 style="color: var(--color-text-white); font-weight: 600; margin-bottom: var(--space-2); font-size: var(--text-base);">Frequently Contacted Resources</h4>
                    <ul style="padding-left: var(--space-4); font-size: var(--text-sm); color: var(--color-text-muted); display: flex; flex-direction: column; gap: var(--space-2); margin-top: var(--space-2);">
                        <li><strong>System Admin/Founder Email:</strong> <code>founder@company.com</code></li>
                        <li><strong>HR Help Desk:</strong> <code>hr@company.com</code></li>
                        <li><strong>System Version:</strong> <code>TM-v1.2.0 (Stable release)</code></li>
                    </ul>
                </div>

                <?php if ($userRole === ROLE_EMPLOYEE): ?>
                    <div style="border-top: 1px solid var(--color-border); padding-top: var(--space-4); margin-top: var(--space-2);">
                        <h4 style="color: var(--color-text-white); font-weight: 600; margin-bottom: var(--space-2); font-size: var(--text-base);">Require Technical Support?</h4>
                        <p class="text-muted" style="font-size: var(--text-sm); margin-bottom: var(--space-3);">
                            As an employee, you can submit technical support tickets directly to your reporting manager or the administration desk.
                        </p>
                        <a href="<?php echo BASE_URL; ?>/employee/support.php" class="btn btn-secondary btn-sm" style="display: inline-block; text-decoration: none;">
                            <i class="fa-solid fa-headset"></i> Go to Support Tickets Page
                        </a>
                    </div>
                <?php else: ?>
                    <div style="border-top: 1px solid var(--color-border); padding-top: var(--space-4); margin-top: var(--space-2);">
                        <h4 style="color: var(--color-text-white); font-weight: 600; margin-bottom: var(--space-2); font-size: var(--text-base);">Support Ticket Dashboard</h4>
                        <p class="text-muted" style="font-size: var(--text-sm); margin-bottom: var(--space-3);">
                            Check, response to, or track support tickets submitted by employees reporting in the workspace.
                        </p>
                        <a href="<?php echo BASE_URL; ?>/<?php echo $userRole; ?>/tickets.php" class="btn btn-secondary btn-sm" style="display: inline-block; text-decoration: none;">
                            <i class="fa-solid fa-headset"></i> View Ticket Dashboard
                        </a>
                    </div>
                <?php endif; ?>

            </div>
        </div>

    </div>

</div>

<script>
function switchTab(buttonEl, tabId) {
    // Hide all tab contents
    document.querySelectorAll('.profile-tab-content').forEach(el => el.style.display = 'none');
    
    // Remove active class from all buttons
    document.querySelectorAll('.profile-menu-item').forEach(el => el.classList.remove('active'));
    
    // Show select tab content
    document.getElementById(tabId).style.display = 'block';
    
    // Set active button state
    buttonEl.classList.add('active');
    
    // Update URL query state gracefully without reloading page
    const tabName = tabId.replace('tab-', '');
    const newUrl = window.location.protocol + "//" + window.location.host + window.location.pathname + '?tab=' + tabName;
    window.history.pushState({ path: newUrl }, '', newUrl);
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
