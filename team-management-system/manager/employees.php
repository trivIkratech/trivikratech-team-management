<?php
/**
 * Manager — My Employees (Add, Edit, and Delete)
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/middleware.php';

requireRole([ROLE_MANAGER]);

$db = getDB();
$managerId = getUserId();
$today = today();
$action = get('action', 'list');
$editUser = null;
$formErrors = [];

// --- Handle form actions ---

// ADD EMPLOYEE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('form_action') === 'add_employee') {
    requireCsrf();
    
    $employeeId = post('employee_id');
    $name = post('name');
    $email = post('email');
    $contactNo = post('contact_no');
    $designation = post('designation');
    $password = $_POST['password'] ?? '';
    $pin = post('pin');
    
    // Validate
    if (empty($employeeId)) $formErrors[] = 'Employee ID is required.';
    if (empty($name)) $formErrors[] = 'Name is required.';
    if (empty($email)) $formErrors[] = 'Email is required.';
    if (empty($password)) $formErrors[] = 'Password is required.';
    if (strlen($password) < 6) $formErrors[] = 'Password must be at least 6 characters.';
    if (!empty($pin) && !preg_match('/^\d{4}$/', $pin)) $formErrors[] = 'PIN must be exactly 4 digits.';
    
    // Check Employee ID uniqueness
    if (empty($formErrors)) {
        $stmt = $db->prepare("SELECT id FROM users WHERE employee_id = ?");
        $stmt->execute([$employeeId]);
        if ($stmt->fetch()) {
            $formErrors[] = 'Employee ID is already registered.';
        }
    }
    
    // Check email uniqueness
    if (empty($formErrors)) {
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $formErrors[] = 'Email is already registered.';
        }
    }
    
    if (empty($formErrors)) {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        // Default pin to '1234' if none provided
        $usePin = !empty($pin) ? $pin : '1234';
        $pinHash = password_hash($usePin, PASSWORD_BCRYPT);
        
        $stmt = $db->prepare("INSERT INTO users (employee_id, name, email, contact_no, designation, password, pin, role, manager_id, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'employee', ?, 'active')");
        $stmt->execute([$employeeId, $name, $email, $contactNo ?: null, $designation ?: null, $hash, $pinHash, $managerId]);
        
        setFlash('success', 'Employee added successfully.');
        header('Location: ' . BASE_URL . '/manager/employees.php');
        exit;
    }
}

// EDIT EMPLOYEE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('form_action') === 'edit_employee') {
    requireCsrf();
    
    $userId = (int)post('user_id');
    $employeeId = post('employee_id');
    $name = post('name');
    $email = post('email');
    $contactNo = post('contact_no');
    $designation = post('designation');
    $status = post('status');
    $newPassword = $_POST['new_password'] ?? '';
    $pin = post('pin');
    
    // Validate
    if (empty($employeeId)) $formErrors[] = 'Employee ID is required.';
    if (empty($name)) $formErrors[] = 'Name is required.';
    if (empty($email)) $formErrors[] = 'Email is required.';
    if (!empty($pin) && !preg_match('/^\d{4}$/', $pin)) $formErrors[] = 'PIN must be exactly 4 digits.';
    
    // Double check manager ownership
    $stmt = $db->prepare("SELECT id FROM users WHERE id = ? AND manager_id = ? AND role = 'employee'");
    $stmt->execute([$userId, $managerId]);
    if (!$stmt->fetch()) {
        $formErrors[] = 'Invalid employee record.';
    }
    
    // Check Employee ID uniqueness
    if (empty($formErrors)) {
        $stmt = $db->prepare("SELECT id FROM users WHERE employee_id = ? AND id != ?");
        $stmt->execute([$employeeId, $userId]);
        if ($stmt->fetch()) {
            $formErrors[] = 'Employee ID is already in use.';
        }
    }
    
    // Check email uniqueness
    if (empty($formErrors)) {
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $userId]);
        if ($stmt->fetch()) {
            $formErrors[] = 'Email is already in use by another user.';
        }
    }
    
    if (empty($formErrors)) {
        $query = "UPDATE users SET employee_id = ?, name = ?, email = ?, contact_no = ?, designation = ?, status = ?, updated_at = NOW()";
        $params = [$employeeId, $name, $email, $contactNo ?: null, $designation ?: null, $status];
        
        if (!empty($newPassword)) {
            if (strlen($newPassword) < 6) {
                $formErrors[] = 'Password must be at least 6 characters.';
            } else {
                $query .= ", password = ?";
                $params[] = password_hash($newPassword, PASSWORD_BCRYPT);
            }
        }
        
        if (empty($formErrors)) {
            if (!empty($pin)) {
                $query .= ", pin = ?";
                $params[] = password_hash($pin, PASSWORD_BCRYPT);
            }
            
            $query .= " WHERE id = ? AND manager_id = ?";
            $params[] = $userId;
            $params[] = $managerId;
            
            $stmt = $db->prepare($query);
            $stmt->execute($params);
            
            setFlash('success', 'Employee record updated successfully.');
            header('Location: ' . BASE_URL . '/manager/employees.php');
            exit;
        }
    }
}

// DELETE EMPLOYEE
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $userId = (int)$_GET['delete'];
    try {
        $stmt = $db->prepare("DELETE FROM users WHERE id = ? AND manager_id = ? AND role = 'employee'");
        $stmt->execute([$userId, $managerId]);
        if ($stmt->rowCount() > 0) {
            setFlash('success', 'Employee deleted successfully.');
        } else {
            setFlash('error', 'You are not authorized to delete this employee.');
        }
    } catch (PDOException $e) {
        error_log("Error deleting employee: " . $e->getMessage());
        setFlash('error', 'Could not delete the employee record.');
    }
    header('Location: ' . BASE_URL . '/manager/employees.php');
    exit;
}

// Fetch user for editing
if ($action === 'edit' && isset($_GET['id']) && is_numeric($_GET['id'])) {
    $editId = (int)$_GET['id'];
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ? AND manager_id = ? AND role = 'employee'");
    $stmt->execute([$editId, $managerId]);
    $editUser = $stmt->fetch();
    if (!$editUser) {
        setFlash('error', 'Employee record not found.');
        header('Location: ' . BASE_URL . '/manager/employees.php');
        exit;
    }
}

// Get employees list
$stmt = $db->prepare("
    SELECT u.*,
        (SELECT COUNT(*) FROM tasks t WHERE t.assigned_to = u.id AND t.status = 'completed') AS completed_tasks,
        (SELECT COUNT(*) FROM tasks t WHERE t.assigned_to = u.id AND t.status IN ('todo','in_progress')) AS pending_tasks,
        (SELECT a.check_in FROM attendance a WHERE a.user_id = u.id AND a.date = ? LIMIT 1) AS today_check_in
    FROM users u 
    WHERE u.manager_id = ? AND u.role = 'employee'
    ORDER BY u.status ASC, u.name ASC
");
$stmt->execute([$today, $managerId]);
$employees = $stmt->fetchAll();

$pageTitle = 'Manage Team Employees';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Manage Team Employees</h1>
        <p class="page-subtitle"><?php echo count($employees); ?> employee(s) reporting to you</p>
    </div>
    <div>
        <?php if ($action === 'list'): ?>
            <a href="?action=add" class="btn btn-primary">➕ Add Employee</a>
        <?php else: ?>
            <a href="?" class="btn btn-secondary">Back to List</a>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($formErrors)): ?>
    <div class="alert alert-error">
        <span><?php echo e(implode(' ', $formErrors)); ?></span>
        <button class="alert-close" onclick="this.parentElement.remove()">×</button>
    </div>
<?php endif; ?>

<!-- ADD EMPLOYEE INTERFACE -->
<?php if ($action === 'add'): ?>
    <div class="card fade-in" style="max-width: 600px; margin: 0 auto;">
        <div class="card-header">
            <h3 class="card-title">Add New Employee</h3>
        </div>
        <form method="POST" action="">
            <?php echo csrfField(); ?>
            <input type="hidden" name="form_action" value="add_employee">
            
            <div class="form-group">
                <label class="form-label">Employee ID No *</label>
                <input type="text" name="employee_id" class="form-input" required placeholder="e.g. EMP-021" value="<?php echo e(post('employee_id')); ?>">
            </div>

            <div class="form-group">
                <label class="form-label">Full Name *</label>
                <input type="text" name="name" class="form-input" required placeholder="e.g. John Doe" value="<?php echo e(post('name')); ?>">
            </div>
            
            <div class="form-group">
                <label class="form-label">Email Address *</label>
                <input type="email" name="email" class="form-input" required placeholder="e.g. john@company.com" value="<?php echo e(post('email')); ?>">
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Contact Number</label>
                    <input type="text" name="contact_no" class="form-input" placeholder="e.g. 9876543210" value="<?php echo e(post('contact_no')); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Designation</label>
                    <input type="text" name="designation" class="form-input" placeholder="e.g. Software Engineer" value="<?php echo e(post('designation')); ?>">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Password *</label>
                    <input type="password" name="password" class="form-input" required placeholder="Min 6 characters">
                </div>
                <div class="form-group">
                    <label class="form-label">Security PIN (4 Digits)</label>
                    <input type="text" name="pin" class="form-input" maxlength="4" placeholder="Default is 1234" value="<?php echo e(post('pin')); ?>">
                </div>
            </div>
            
            <div class="form-actions" style="margin-top: var(--space-4);">
                <button type="submit" class="btn btn-primary" style="width: 100%;">Create Employee Account</button>
            </div>
        </form>
    </div>

<!-- EDIT EMPLOYEE INTERFACE -->
<?php elseif ($action === 'edit' && $editUser): ?>
    <div class="card fade-in" style="max-width: 600px; margin: 0 auto;">
        <div class="card-header">
            <h3 class="card-title">Edit Employee: <?php echo e($editUser['name']); ?></h3>
        </div>
        <form method="POST" action="">
            <?php echo csrfField(); ?>
            <input type="hidden" name="form_action" value="edit_employee">
            <input type="hidden" name="user_id" value="<?php echo $editUser['id']; ?>">
            
            <div class="form-group">
                <label class="form-label">Employee ID No *</label>
                <input type="text" name="employee_id" class="form-input" required value="<?php echo e(post('employee_id', $editUser['employee_id'])); ?>">
            </div>

            <div class="form-group">
                <label class="form-label">Full Name *</label>
                <input type="text" name="name" class="form-input" required value="<?php echo e(post('name', $editUser['name'])); ?>">
            </div>
            
            <div class="form-group">
                <label class="form-label">Email Address *</label>
                <input type="email" name="email" class="form-input" required value="<?php echo e(post('email', $editUser['email'])); ?>">
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Contact Number</label>
                    <input type="text" name="contact_no" class="form-input" value="<?php echo e(post('contact_no', $editUser['contact_no'])); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Designation</label>
                    <input type="text" name="designation" class="form-input" value="<?php echo e(post('designation', $editUser['designation'])); ?>">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Status *</label>
                    <select name="status" class="form-select" required>
                        <option value="active" <?php echo $editUser['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo $editUser['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Change PIN (4 Digits)</label>
                    <input type="text" name="pin" class="form-input" maxlength="4" placeholder="Leave blank to keep current">
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label">Change Password</label>
                <input type="password" name="new_password" class="form-input" placeholder="Leave blank to keep current">
            </div>
            
            <div class="form-actions" style="margin-top: var(--space-4);">
                <button type="submit" class="btn btn-primary" style="width: 100%;">Save Changes</button>
            </div>
        </form>
    </div>

<!-- DEFAULT LIST INTERFACE -->
<?php else: ?>
    <?php if (empty($employees)): ?>
        <div class="card">
            <div class="empty-state">
                <div class="empty-state-icon">👥</div>
                <div class="empty-state-title">No employees assigned to you</div>
                <div class="empty-state-text">Use the "+ Add Employee" button above to add employees to your team.</div>
            </div>
        </div>
    <?php else: ?>
        <div class="table-container fade-in">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Emp ID</th>
                        <th>Employee</th>
                        <th>Email / Contact</th>
                        <th>Today</th>
                        <th>Tasks (Pending)</th>
                        <th>Tasks (Done)</th>
                        <th>Status</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($employees as $emp): ?>
                        <tr>
                            <td><code><?php echo e($emp['employee_id'] ?: '—'); ?></code></td>
                            <td>
                                <div class="table-user">
                                    <div class="table-user-avatar"><?php echo e(getInitials($emp['name'])); ?></div>
                                    <div>
                                        <div class="table-user-name"><?php echo e($emp['name']); ?></div>
                                        <small class="text-muted" style="font-size: var(--text-xs);"><?php echo e($emp['designation'] ?: '—'); ?></small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <?php echo e($emp['email']); ?><br>
                                <small class="text-muted"><?php echo e($emp['contact_no'] ?: '—'); ?></small>
                            </td>
                            <td>
                                <?php if ($emp['today_check_in']): ?>
                                    <span class="badge badge-success">Present</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">Absent</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge badge-warning"><?php echo $emp['pending_tasks']; ?></span></td>
                            <td><span class="badge badge-success"><?php echo $emp['completed_tasks']; ?></span></td>
                            <td><span class="badge <?php echo userStatusBadge($emp['status']); ?>"><?php echo ucfirst(e($emp['status'])); ?></span></td>
                            <td style="text-align: right;">
                                <a href="?action=edit&id=<?php echo $emp['id']; ?>" class="btn btn-ghost btn-sm" style="color: var(--color-primary); padding: var(--space-1) var(--space-2); border-radius: var(--radius-sm); text-decoration: none;">
                                    📝 Edit
                                </a>
                                <a href="?delete=<?php echo $emp['id']; ?>" class="btn btn-ghost btn-sm" onclick="return confirm('Are you sure you want to remove this employee from your team?')" title="Remove Employee" style="color: var(--color-danger); padding: var(--space-1) var(--space-2); border-radius: var(--radius-sm); text-decoration: none; margin-left: var(--space-1);">
                                    🗑️ Remove
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
