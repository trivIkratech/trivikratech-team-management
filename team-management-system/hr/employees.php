<?php
/**
 * HR — Employee Management (All Profiles, Add, Edit, Delete, Teams, Tasks & Overview)
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/middleware.php';

requireRole([ROLE_HR]);

$db = getDB();
$userId = getUserId();
$tab = get('tab', 'profiles'); // profiles, add, edit, teams, tasks
$editUser = null;
$overviewUser = null;
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
    $baseSalary = post('base_salary') !== '' ? (float)post('base_salary') : 30000.00;
    $password = $_POST['password'] ?? '';
    $pin = post('pin');
    $managerId = post('manager_id') ? (int)post('manager_id') : null;
    
    if (empty($employeeId)) $formErrors[] = 'Employee ID is required.';
    if (empty($name)) $formErrors[] = 'Name is required.';
    if (empty($email)) $formErrors[] = 'Email is required.';
    if (empty($password)) $formErrors[] = 'Password is required.';
    if (strlen($password) < 6) $formErrors[] = 'Password must be at least 6 characters.';
    if (!empty($pin) && !preg_match('/^\d{4}$/', $pin)) $formErrors[] = 'PIN must be exactly 4 digits.';
    
    if (empty($formErrors)) {
        $stmt = $db->prepare("SELECT id FROM users WHERE employee_id = ?");
        $stmt->execute([$employeeId]);
        if ($stmt->fetch()) $formErrors[] = 'Employee ID is already registered.';
    }
    
    if (empty($formErrors)) {
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) $formErrors[] = 'Email is already registered.';
    }
    
    if (empty($formErrors)) {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $usePin = !empty($pin) ? $pin : '1234';
        $pinHash = password_hash($usePin, PASSWORD_BCRYPT);
        $joiningDate = post('joining_date') ?: date('Y-m-d');
        
        $stmt = $db->prepare("INSERT INTO users (employee_id, name, email, contact_no, designation, joining_date, base_salary, password, pin, role, manager_id, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'employee', ?, 'active')");
        $stmt->execute([$employeeId, $name, $email, $contactNo ?: null, $designation ?: null, $joiningDate, $baseSalary, $hash, $pinHash, $managerId]);
        
        setFlash('success', 'Employee added successfully.');
        header('Location: ' . BASE_URL . '/hr/employees.php');
        exit;
    }
}

// EDIT EMPLOYEE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('form_action') === 'edit_employee') {
    requireCsrf();
    
    $targetUserId = (int)post('user_id');
    $employeeId = post('employee_id');
    $name = post('name');
    $email = post('email');
    $contactNo = post('contact_no');
    $designation = post('designation');
    $joiningDate = post('joining_date') ?: null;
    $baseSalary = post('base_salary') !== '' ? (float)post('base_salary') : 30000.00;
    $status = post('status');
    $managerId = post('manager_id') ? (int)post('manager_id') : null;
    $newPassword = $_POST['new_password'] ?? '';
    $pin = post('pin');
    
    if (empty($employeeId)) $formErrors[] = 'Employee ID is required.';
    if (empty($name)) $formErrors[] = 'Name is required.';
    if (empty($email)) $formErrors[] = 'Email is required.';
    if (!empty($pin) && !preg_match('/^\d{4}$/', $pin)) $formErrors[] = 'PIN must be exactly 4 digits.';
    
    if (empty($formErrors)) {
        $stmt = $db->prepare("SELECT id FROM users WHERE employee_id = ? AND id != ?");
        $stmt->execute([$employeeId, $targetUserId]);
        if ($stmt->fetch()) $formErrors[] = 'Employee ID is already in use.';
    }
    
    if (empty($formErrors)) {
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $targetUserId]);
        if ($stmt->fetch()) $formErrors[] = 'Email is already in use.';
    }
    
    if (empty($formErrors)) {
        $query = "UPDATE users SET employee_id = ?, name = ?, email = ?, contact_no = ?, designation = ?, joining_date = ?, base_salary = ?, status = ?, manager_id = ?, updated_at = NOW()";
        $params = [$employeeId, $name, $email, $contactNo ?: null, $designation ?: null, $joiningDate, $baseSalary, $status, $managerId];
        
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
            
            $query .= " WHERE id = ? AND role = 'employee'";
            $params[] = $targetUserId;
            
            $stmt = $db->prepare($query);
            $stmt->execute($params);
            
            setFlash('success', 'Employee record updated successfully.');
            header('Location: ' . BASE_URL . '/hr/employees.php');
            exit;
        }
    }
}

// DELETE EMPLOYEE
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $targetUserId = (int)$_GET['delete'];
    try {
        $stmt = $db->prepare("DELETE FROM users WHERE id = ? AND role = 'employee'");
        $stmt->execute([$targetUserId]);
        if ($stmt->rowCount() > 0) {
            setFlash('success', 'Employee deleted successfully.');
        } else {
            setFlash('error', 'Could not delete employee record.');
        }
    } catch (PDOException $e) {
        setFlash('error', 'Could not delete the employee record.');
    }
    header('Location: ' . BASE_URL . '/hr/employees.php');
    exit;
}

// Fetch employee for edit tab
if ($tab === 'edit' && isset($_GET['id']) && is_numeric($_GET['id'])) {
    $editId = (int)$_GET['id'];
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ? AND role = 'employee'");
    $stmt->execute([$editId]);
    $editUser = $stmt->fetch();
}

// Fetch overview modal data if requested
$overviewData = null;
if (isset($_GET['overview']) && is_numeric($_GET['overview'])) {
    $ovId = (int)$_GET['overview'];
    $stmt = $db->prepare("SELECT u.*, m.name AS manager_name FROM users u LEFT JOIN users m ON u.manager_id = m.id WHERE u.id = ?");
    $stmt->execute([$ovId]);
    $overviewUser = $stmt->fetch();
    
    if ($overviewUser) {
        // Attendance count
        $stmt = $db->prepare("SELECT COUNT(*) FROM attendance WHERE user_id = ? AND check_in IS NOT NULL");
        $stmt->execute([$ovId]);
        $totalPresent = $stmt->fetchColumn();
        
        // Assigned tasks count
        $stmt = $db->prepare("SELECT t.*, u.name as assigner_name FROM tasks t JOIN users u ON t.assigned_by = u.id WHERE t.assigned_to = ? ORDER BY t.created_at DESC");
        $stmt->execute([$ovId]);
        $empTasks = $stmt->fetchAll();
        
        // Leaves
        $stmt = $db->prepare("SELECT * FROM leaves WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
        $stmt->execute([$ovId]);
        $empLeaves = $stmt->fetchAll();
        
        $overviewData = [
            'user' => $overviewUser,
            'present_days' => $totalPresent,
            'tasks' => $empTasks,
            'leaves' => $empLeaves
        ];
    }
}

// Fetch active managers
$managers = $db->query("SELECT id, name FROM users WHERE role = 'manager' AND status = 'active' ORDER BY name ASC")->fetchAll();

// Get employees list
$employees = $db->query("
    SELECT u.*, m.name AS manager_name
    FROM users u 
    LEFT JOIN users m ON u.manager_id = m.id
    WHERE u.role = 'employee'
    ORDER BY u.status ASC, u.name ASC
")->fetchAll();

// Group employees by manager for Teams tab
$teams = [];
foreach ($employees as $emp) {
    $mgrName = $emp['manager_name'] ?? 'Unassigned / Direct HR';
    $teams[$mgrName][] = $emp;
}

// Fetch all assigned tasks for Tasks tab
$allAssignedTasks = $db->query("
    SELECT t.*, u.name AS employee_name, u.employee_id, m.name AS manager_name
    FROM tasks t
    JOIN users u ON t.assigned_to = u.id
    LEFT JOIN users m ON t.assigned_by = m.id
    WHERE u.role = 'employee'
    ORDER BY t.created_at DESC
")->fetchAll();

$pageTitle = 'HR — Employee Management';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Employees Directory</h1>
        <p class="page-subtitle"><?php echo count($employees); ?> registered employee(s) in company workforce</p>
    </div>
    <div>
        <a href="?tab=add" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Add Employee</a>
    </div>
</div>

<!-- Tabs Bar -->
<div class="nav-tabs" style="display: flex; gap: var(--space-2); border-bottom: 1px solid var(--color-border); margin-bottom: var(--space-6);">
    <a href="?tab=profiles" class="tab-item <?php echo $tab === 'profiles' ? 'active' : ''; ?>" style="padding: var(--space-3) var(--space-4); text-decoration: none; font-weight: 500; border-bottom: 2px solid <?php echo $tab === 'profiles' ? 'var(--color-primary)' : 'transparent'; ?>; color: <?php echo $tab === 'profiles' ? 'var(--color-primary)' : 'var(--color-text-secondary)'; ?>;">
        <i class="fa-solid fa-users"></i> All Employees Profile
    </a>
    <a href="?tab=teams" class="tab-item <?php echo $tab === 'teams' ? 'active' : ''; ?>" style="padding: var(--space-3) var(--space-4); text-decoration: none; font-weight: 500; border-bottom: 2px solid <?php echo $tab === 'teams' ? 'var(--color-primary)' : 'transparent'; ?>; color: <?php echo $tab === 'teams' ? 'var(--color-primary)' : 'var(--color-text-secondary)'; ?>;">
        <i class="fa-solid fa-user-tie"></i> Teams View
    </a>
    <a href="?tab=tasks" class="tab-item <?php echo $tab === 'tasks' ? 'active' : ''; ?>" style="padding: var(--space-3) var(--space-4); text-decoration: none; font-weight: 500; border-bottom: 2px solid <?php echo $tab === 'tasks' ? 'var(--color-primary)' : 'transparent'; ?>; color: <?php echo $tab === 'tasks' ? 'var(--color-primary)' : 'var(--color-text-secondary)'; ?>;">
        <i class="fa-solid fa-clipboard-user"></i> Assigned Tasks
    </a>
</div>

<?php if (!empty($formErrors)): ?>
    <div class="alert alert-error">
        <span><?php echo e(implode(' ', $formErrors)); ?></span>
        <button class="alert-close" onclick="this.parentElement.remove()">×</button>
    </div>
<?php endif; ?>

<!-- TAB 1: ALL EMPLOYEES PROFILES -->
<?php if ($tab === 'profiles'): ?>
    <div class="table-container fade-in">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Emp ID</th>
                    <th>Employee Name</th>
                    <th>Email & Contact</th>
                    <th>Designation</th>
                    <th>Reporting Manager</th>
                    <th>Status</th>
                    <th>Joining Date</th>
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
                                    <div class="table-user-name">
                                        <a href="?tab=profiles&overview=<?php echo $emp['id']; ?>" style="color: inherit; text-decoration: underline; font-weight: 600;">
                                            <?php echo e($emp['name']); ?>

                                        </a>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <?php echo e($emp['email']); ?><br>
                            <small class="text-muted"><?php echo e($emp['contact_no'] ?: '—'); ?></small>
                        </td>
                        <td><?php echo e($emp['designation'] ?: '—'); ?></td>
                        <td><?php echo e($emp['manager_name'] ?? '—'); ?></td>
                        <td><span class="badge <?php echo userStatusBadge($emp['status']); ?>"><?php echo ucfirst(e($emp['status'])); ?></span></td>
                        <td><?php echo !empty($emp['joining_date']) ? formatDate($emp['joining_date']) : formatDate($emp['created_at']); ?></td>
                        <td style="text-align: right;">
                            <a href="?tab=profiles&overview=<?php echo $emp['id']; ?>" class="btn btn-ghost btn-sm" style="color: var(--color-info); text-decoration: none;" title="Employee Overview">
                                <i class="fa-solid fa-magnifying-glass"></i> Overview
                            </a>
                            <a href="?tab=edit&id=<?php echo $emp['id']; ?>" class="btn btn-ghost btn-sm" style="color: var(--color-primary); text-decoration: none;">
                                <i class="fa-solid fa-pen-to-square"></i> Edit
                            </a>
                            <a href="?delete=<?php echo $emp['id']; ?>" class="btn btn-ghost btn-sm" onclick="return confirm('Are you sure you want to delete this employee permanently?')" style="color: var(--color-danger); text-decoration: none;">
                                <i class="fa-solid fa-trash-can"></i> Delete
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

<!-- TAB 2: TEAMS VIEW -->
<?php elseif ($tab === 'teams'): ?>
    <div class="content-grid fade-in" style="grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));">
        <?php foreach ($teams as $managerName => $members): ?>
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fa-solid fa-user-tie"></i> <?php echo e($managerName); ?></h3>
                    <span class="badge badge-info"><?php echo count($members); ?> Member(s)</span>
                </div>
                <div class="activity-list">
                    <?php foreach ($members as $m): ?>
                        <div class="activity-item" style="display: flex; justify-content: space-between; align-items: center;">
                            <div style="display: flex; align-items: center; gap: var(--space-3);">
                                <div class="table-user-avatar"><?php echo e(getInitials($m['name'])); ?></div>
                                <div>
                                    <strong><?php echo e($m['name']); ?></strong><br>
                                    <small class="text-muted"><?php echo e($m['designation'] ?: 'Employee'); ?></small>
                                </div>
                            </div>
                            <a href="?tab=profiles&overview=<?php echo $m['id']; ?>" class="btn btn-ghost btn-sm" style="color: var(--color-primary);">View</a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

<!-- TAB 3: ASSIGNED TASKS -->
<?php elseif ($tab === 'tasks'): ?>
    <div class="table-container fade-in">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Task Title</th>
                    <th>Assigned To</th>
                    <th>Assigned By</th>
                    <th>Priority</th>
                    <th>Deadline</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($allAssignedTasks)): ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted" style="padding: 24px;">No assigned tasks found for employees.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($allAssignedTasks as $t): ?>
                        <tr>
                            <td><strong><?php echo e($t['title']); ?></strong></td>
                            <td><?php echo e($t['employee_name']); ?> (<code><?php echo e($t['employee_id']); ?></code>)</td>
                            <td><?php echo e($t['manager_name'] ?? 'System'); ?></td>
                            <td><span class="badge <?php echo priorityBadge($t['priority']); ?>"><?php echo ucfirst(e($t['priority'])); ?></span></td>
                            <td><?php echo formatDate($t['deadline']); ?></td>
                            <td><span class="badge <?php echo taskStatusBadge($t['status']); ?>"><?php echo ucfirst(e($t['status'])); ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

<!-- TAB 4: ADD EMPLOYEE -->
<?php elseif ($tab === 'add'): ?>
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
                    <label class="form-label">Joining Date *</label>
                    <input type="date" name="joining_date" class="form-input" required value="<?php echo e(post('joining_date', date('Y-m-d'))); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Assign to Manager</label>
                    <select name="manager_id" class="form-select">
                        <option value="">— No Manager —</option>
                        <?php foreach ($managers as $mgr): ?>
                            <option value="<?php echo $mgr['id']; ?>"><?php echo e($mgr['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
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

<!-- TAB 5: EDIT EMPLOYEE -->
<?php elseif ($tab === 'edit' && $editUser): ?>
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
                    <label class="form-label">Joining Date</label>
                    <input type="date" name="joining_date" class="form-input" value="<?php echo e(post('joining_date', $editUser['joining_date'] ?? '')); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Assign to Manager</label>
                    <select name="manager_id" class="form-select">
                        <option value="">— No Manager —</option>
                        <?php foreach ($managers as $mgr): ?>
                            <option value="<?php echo $mgr['id']; ?>" <?php echo $editUser['manager_id'] == $mgr['id'] ? 'selected' : ''; ?>><?php echo e($mgr['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
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
<?php endif; ?>

<!-- EMPLOYEE OVERVIEW MODAL -->
<?php if ($overviewData): ?>
    <div class="modal-backdrop" style="position: fixed; inset: 0; background: rgba(0,0,0,0.6); display: flex; align-items: center; justify-content: center; z-index: 1000; padding: 20px;">
        <div class="card fade-in" style="max-width: 650px; width: 100%; max-height: 90vh; overflow-y: auto;">
            <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                <h3 class="card-title">Employee Overview: <?php echo e($overviewData['user']['name']); ?></h3>
                <a href="?tab=profiles" class="btn btn-ghost btn-sm" style="text-decoration: none;">✕ Close</a>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 20px;">
                <div style="background: var(--color-bg-secondary); padding: 12px; border-radius: 8px;">
                    <small class="text-muted">Employee ID</small>
                    <div><strong><code><?php echo e($overviewData['user']['employee_id']); ?></code></strong></div>
                </div>
                <div style="background: var(--color-bg-secondary); padding: 12px; border-radius: 8px;">
                    <small class="text-muted">Designation</small>
                    <div><strong><?php echo e($overviewData['user']['designation'] ?: '—'); ?></strong></div>
                </div>
                <div style="background: var(--color-bg-secondary); padding: 12px; border-radius: 8px;">
                    <small class="text-muted">Email / Contact</small>
                    <div><strong><?php echo e($overviewData['user']['email']); ?></strong> (<?php echo e($overviewData['user']['contact_no'] ?: 'No Contact'); ?>)</div>
                </div>
                <div style="background: var(--color-bg-secondary); padding: 12px; border-radius: 8px;">
                    <small class="text-muted">Reporting Manager</small>
                    <div><strong><?php echo e($overviewData['user']['manager_name'] ?: 'Direct HR'); ?></strong></div>
                </div>
                <div style="background: var(--color-bg-secondary); padding: 12px; border-radius: 8px; grid-column: 1 / -1;">
                    <small class="text-muted">Joining Date</small>
                    <div><strong><?php echo !empty($overviewData['user']['joining_date']) ? formatDate($overviewData['user']['joining_date']) : formatDate($overviewData['user']['created_at']); ?></strong></div>
                </div>
            </div>

            <h4>Assigned Tasks (<?php echo count($overviewData['tasks']); ?>)</h4>
            <?php if (empty($overviewData['tasks'])): ?>
                <p class="text-muted">No tasks assigned yet.</p>
            <?php else: ?>
                <ul style="padding-left: 20px; margin-bottom: 20px;">
                    <?php foreach ($overviewData['tasks'] as $t): ?>
                        <li>
                            <strong><?php echo e($t['title']); ?></strong> — 
                            <span class="badge <?php echo taskStatusBadge($t['status']); ?>"><?php echo ucfirst(e($t['status'])); ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <h4>Recent Leave Applications (<?php echo count($overviewData['leaves']); ?>)</h4>
            <?php if (empty($overviewData['leaves'])): ?>
                <p class="text-muted">No leave applications recorded.</p>
            <?php else: ?>
                <ul style="padding-left: 20px;">
                    <?php foreach ($overviewData['leaves'] as $l): ?>
                        <li>
                            <?php echo ucfirst(e($l['leave_type'])); ?> Leave (<?php echo formatDate($l['start_date']); ?> to <?php echo formatDate($l['end_date']); ?>) — 
                            <span class="badge <?php echo $l['status'] === 'approved' ? 'badge-success' : ($l['status'] === 'denied' ? 'badge-danger' : 'badge-warning'); ?>"><?php echo ucfirst(e($l['status'])); ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
