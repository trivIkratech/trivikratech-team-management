<?php
/**
 * Founder — User Management (Add / Edit / Deactivate / Delete)
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/middleware.php';

requireRole([ROLE_FOUNDER]);

$db = getDB();
$action = get('action', 'list');
$editUser = null;
$formErrors = [];

// --- Handle form submissions ---

// All available roles
$allRoles = getAllRoles();
$validRoleSlugs = array_column($allRoles, 'slug');

// ADD USER
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('form_action') === 'add_user') {
    requireCsrf();
    
    $employeeId = post('employee_id');
    $name = post('name');
    $email = post('email');
    $contactNo = post('contact_no');
    $designation = post('designation');
    $password = $_POST['password'] ?? '';
    $pin = post('pin');
    $role = post('role');
    $managerId = post('manager_id') ? (int)post('manager_id') : null;
    
    // Validate
    if (empty($employeeId)) $formErrors[] = 'Employee ID is required.';
    if (empty($name)) $formErrors[] = 'Name is required.';
    if (empty($email)) $formErrors[] = 'Email is required.';
    if (empty($password)) $formErrors[] = 'Password is required.';
    if (strlen($password) < 6) $formErrors[] = 'Password must be at least 6 characters.';
    if (!empty($pin) && !preg_match('/^\d{4}$/', $pin)) $formErrors[] = 'PIN must be exactly 4 digits.';
    if (!in_array($role, $validRoleSlugs)) $formErrors[] = 'Invalid role.';
    
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
        // Default pin to '1234' for employees/managers/hr if none provided
        $usePin = !empty($pin) ? $pin : '1234';
        $pinHash = password_hash($usePin, PASSWORD_BCRYPT);
        $joiningDate = post('joining_date') ?: date('Y-m-d');
        
        $stmt = $db->prepare("INSERT INTO users (employee_id, name, email, contact_no, designation, joining_date, password, pin, role, manager_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$employeeId, $name, $email, $contactNo ?: null, $designation ?: null, $joiningDate, $hash, $pinHash, $role, $managerId]);
        setFlash('success', ucfirst($role) . ' added successfully.');
        header('Location: ' . BASE_URL . '/founder/user-management.php');
        exit;
    }
}

// EDIT USER
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('form_action') === 'edit_user') {
    requireCsrf();
    
    $userId = (int)post('user_id');
    $employeeId = post('employee_id');
    $name = post('name');
    $email = post('email');
    $contactNo = post('contact_no');
    $designation = post('designation');
    $joiningDate = post('joining_date') ?: null;
    $role = post('role');
    $status = post('status');
    $managerId = post('manager_id') ? (int)post('manager_id') : null;
    $newPassword = $_POST['new_password'] ?? '';
    $pin = post('pin');
    
    // Validate
    if (empty($employeeId)) $formErrors[] = 'Employee ID is required.';
    if (empty($name)) $formErrors[] = 'Name is required.';
    if (empty($email)) $formErrors[] = 'Email is required.';
    if (!empty($pin) && !preg_match('/^\d{4}$/', $pin)) $formErrors[] = 'PIN must be exactly 4 digits.';
    
    // Check Employee ID uniqueness
    if (empty($formErrors)) {
        $stmt = $db->prepare("SELECT id FROM users WHERE employee_id = ? AND id != ?");
        $stmt->execute([$employeeId, $userId]);
        if ($stmt->fetch()) {
            $formErrors[] = 'Employee ID is already in use by another user.';
        }
    }
    
    // Check email uniqueness (exclude current user)
    if (empty($formErrors)) {
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $userId]);
        if ($stmt->fetch()) {
            $formErrors[] = 'Email is already in use by another user.';
        }
    }
    
    if (empty($formErrors)) {
        $query = "UPDATE users SET employee_id=?, name=?, email=?, contact_no=?, designation=?, joining_date=?, role=?, manager_id=?, status=?, updated_at=NOW()";
        $params = [$employeeId, $name, $email, $contactNo ?: null, $designation ?: null, $joiningDate, $role, $managerId, $status];
        
        if (!empty($newPassword)) {
            if (strlen($newPassword) < 6) {
                $formErrors[] = 'Password must be at least 6 characters.';
            } else {
                $query .= ", password=?";
                $params[] = password_hash($newPassword, PASSWORD_BCRYPT);
            }
        }
        
        if (empty($formErrors)) {
            if (!empty($pin)) {
                $query .= ", pin=?";
                $params[] = password_hash($pin, PASSWORD_BCRYPT);
            }
            
            $query .= " WHERE id=?";
            $params[] = $userId;
            
            $stmt = $db->prepare($query);
            $stmt->execute($params);
            
            setFlash('success', 'User updated successfully.');
            header('Location: ' . BASE_URL . '/founder/user-management.php');
            exit;
        }
    }
}

// DELETE USER
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $userId = (int)$_GET['delete'];
    // Prevent self-deletion
    if ($userId === getUserId()) {
        setFlash('error', 'You cannot delete your own account.');
    } else {
        $db->prepare("DELETE FROM users WHERE id = ? AND role != 'founder'")->execute([$userId]);
        setFlash('success', 'User deleted successfully.');
    }
    header('Location: ' . BASE_URL . '/founder/user-management.php');
    exit;
}

// TOGGLE STATUS
if (isset($_GET['toggle']) && is_numeric($_GET['toggle'])) {
    $userId = (int)$_GET['toggle'];
    if ($userId !== getUserId()) {
        $stmt = $db->prepare("SELECT status FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $current = $stmt->fetchColumn();
        $newStatus = ($current === 'active') ? 'inactive' : 'active';
        
        $stmt = $db->prepare("UPDATE users SET status = ? WHERE id = ? AND role != 'founder'");
        $stmt->execute([$newStatus, $userId]);
        setFlash('success', 'User status updated successfully.');
    }
    header('Location: ' . BASE_URL . '/founder/user-management.php');
    exit;
}

// --- Fetch records for view ---

// Managers list for dropdowns
$managers = $db->query("SELECT id, name FROM users WHERE role = 'manager' AND status = 'active' ORDER BY name ASC")->fetchAll();

// User to edit
if ($action === 'edit' && isset($_GET['id']) && is_numeric($_GET['id'])) {
    $editId = (int)$_GET['id'];
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$editId]);
    $editUser = $stmt->fetch();
    
    if (!$editUser) {
        setFlash('error', 'User not found.');
        header('Location: ' . BASE_URL . '/founder/user-management.php');
        exit;
    }
}

// Pre-defined role filtering for Add
$preRole = get('role', 'employee');
if (!in_array($preRole, ['employee', 'manager', 'hr'])) {
    $preRole = 'employee';
}

// Filter parameters for List view
$search = trim(get('search', ''));
$filterRole = get('role_filter', 'all');
$filterStatus = get('status_filter', 'all');
$filterManager = get('manager_filter', 'all');

$where = [];
$params = [];

if (!empty($search)) {
    $where[] = "(u.name LIKE ? OR u.email LIKE ? OR u.employee_id LIKE ? OR u.contact_no LIKE ? OR u.designation LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

if ($filterRole !== 'all' && in_array($filterRole, $validRoleSlugs)) {
    $where[] = "u.role = ?";
    $params[] = $filterRole;
}

if ($filterStatus !== 'all' && in_array($filterStatus, ['active', 'inactive'])) {
    $where[] = "u.status = ?";
    $params[] = $filterStatus;
}

if ($filterManager !== 'all') {
    if ($filterManager === 'none') {
        $where[] = "u.manager_id IS NULL";
    } else {
        $where[] = "u.manager_id = ?";
        $params[] = (int)$filterManager;
    }
}

$whereClause = !empty($where) ? "WHERE " . implode(' AND ', $where) : "";

// Fetch users matching filters
$stmt = $db->prepare("
    SELECT u.*, m.name AS manager_name 
    FROM users u 
    LEFT JOIN users m ON u.manager_id = m.id 
    {$whereClause}
    ORDER BY u.role ASC, u.name ASC
");
$stmt->execute($params);
$users = $stmt->fetchAll();

// User breakdown counts for quick filter cards
$counts = $db->query("
    SELECT 
        COUNT(*) AS total,
        SUM(CASE WHEN role = 'employee' THEN 1 ELSE 0 END) AS total_employees,
        SUM(CASE WHEN role = 'manager' THEN 1 ELSE 0 END) AS total_managers,
        SUM(CASE WHEN role = 'hr' THEN 1 ELSE 0 END) AS total_hr,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) AS total_active
    FROM users
")->fetch() ?: ['total' => 0, 'total_employees' => 0, 'total_managers' => 0, 'total_hr' => 0, 'total_active' => 0];

$pageTitle = 'User Management';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">User Management</h1>
        <p class="page-subtitle">Add and configure employee, manager, and HR accounts in the organization</p>
    </div>
    <div>
        <?php if ($action === 'list'): ?>
            <div style="display: flex; gap: var(--space-2); flex-wrap: wrap;">
                <a href="<?php echo BASE_URL; ?>/founder/roles.php" class="btn btn-outline"><i class="fa-solid fa-user-shield"></i> Roles & Permissions</a>
                <a href="?action=add&role=employee" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Add Employee</a>
                <a href="?action=add&role=manager" class="btn btn-secondary"><i class="fa-solid fa-plus"></i> Add Manager</a>
                <a href="?action=add&role=hr" class="btn btn-outline"><i class="fa-solid fa-plus"></i> Add HR</a>
            </div>
        <?php else: ?>
            <a href="<?php echo BASE_URL; ?>/founder/user-management.php" class="btn btn-secondary">Back to Users List</a>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($formErrors)): ?>
    <div class="alert alert-error">
        <span><?php echo e(implode(' ', $formErrors)); ?></span>
        <button class="alert-close" onclick="this.parentElement.remove()">×</button>
    </div>
<?php endif; ?>

<?php if ($action === 'add'): ?>
    <!-- ADD USER FORM -->
    <div class="card fade-in" style="max-width: 600px; margin: 0 auto;">
        <div class="card-header">
            <h3 class="card-title">Add New <?php echo ucfirst(e($preRole)); ?></h3>
        </div>
        <form method="POST" action="" data-validate>
            <?php echo csrfField(); ?>
            <input type="hidden" name="form_action" value="add_user">
            
            <div class="form-group">
                <label class="form-label">Employee ID No *</label>
                <input type="text" name="employee_id" class="form-input" placeholder="e.g. EMP-021" required value="<?php echo e(post('employee_id')); ?>">
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Full Name *</label>
                    <input type="text" name="name" class="form-input" placeholder="John Doe" required value="<?php echo e(post('name')); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Email Address *</label>
                    <input type="email" name="email" class="form-input" placeholder="john@company.com" required value="<?php echo e(post('email')); ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Contact Number</label>
                    <input type="text" name="contact_no" class="form-input" placeholder="e.g. 9876543210" value="<?php echo e(post('contact_no')); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Employee Designation</label>
                    <input type="text" name="designation" class="form-input" placeholder="e.g. Software Engineer" value="<?php echo e(post('designation')); ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Joining Date *</label>
                    <input type="date" name="joining_date" class="form-input" required value="<?php echo e(post('joining_date', date('Y-m-d'))); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Role *</label>
                    <select name="role" class="form-select" required id="role-select">
                        <?php foreach ($allRoles as $r): ?>
                            <?php if ($r['slug'] === 'founder') continue; ?>
                            <option value="<?php echo e($r['slug']); ?>" data-base="<?php echo e($r['base_role']); ?>" <?php echo $preRole === $r['slug'] ? 'selected' : ''; ?>>
                                <?php echo e($r['name']); ?> (<?php echo ucfirst(e($r['base_role'])); ?> Workspace)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Password *</label>
                    <input type="password" name="password" class="form-input" placeholder="Min 6 characters" required minlength="6">
                </div>
                <div class="form-group">
                    <label class="form-label">4-Digit Security PIN (Optional)</label>
                    <input type="text" name="pin" class="form-input" placeholder="Default is 1234" maxlength="4" pattern="\d{4}">
                </div>
            </div>
            
            <div class="form-group" id="manager-group" style="<?php 
                $currBase = getRoleBaseType($preRole);
                echo in_array($currBase, ['manager', 'hr', 'founder']) ? 'display:none;' : ''; 
            ?>">
                <label class="form-label">Assign to Manager</label>
                <select name="manager_id" class="form-select">
                    <option value="">— No Manager —</option>
                    <?php foreach ($managers as $mgr): ?>
                        <option value="<?php echo $mgr['id']; ?>"><?php echo e($mgr['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-actions" style="margin-top: var(--space-4);">
                <a href="<?php echo BASE_URL; ?>/founder/user-management.php" class="btn btn-outline" style="width: 48%; text-align: center;">Cancel</a>
                <button type="submit" class="btn btn-primary" style="width: 48%;">Add User</button>
            </div>
        </form>
    </div>
    
    <script>
    document.getElementById('role-select')?.addEventListener('change', function() {
        const opt = this.options[this.selectedIndex];
        const baseRole = opt ? (opt.getAttribute('data-base') || this.value) : this.value;
        document.getElementById('manager-group').style.display = (baseRole === 'manager' || baseRole === 'hr' || baseRole === 'founder') ? 'none' : '';
    });
    </script>

<?php elseif ($action === 'edit' && $editUser): ?>
    <!-- EDIT USER FORM -->
    <div class="card fade-in" style="max-width: 600px; margin: 0 auto;">
        <div class="card-header">
            <h3 class="card-title">Edit User: <?php echo e($editUser['name']); ?></h3>
            <span class="badge <?php echo roleBadge($editUser['role']); ?>"><?php echo ucfirst(e($editUser['role'])); ?></span>
        </div>
        <form method="POST" action="" data-validate>
            <?php echo csrfField(); ?>
            <input type="hidden" name="form_action" value="edit_user">
            <input type="hidden" name="user_id" value="<?php echo $editUser['id']; ?>">
            
            <div class="form-group">
                <label class="form-label">Employee ID No *</label>
                <input type="text" name="employee_id" class="form-input" required value="<?php echo e(post('employee_id', $editUser['employee_id'])); ?>">
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Full Name *</label>
                    <input type="text" name="name" class="form-input" required value="<?php echo e($editUser['name']); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Email Address *</label>
                    <input type="email" name="email" class="form-input" required value="<?php echo e($editUser['email']); ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Contact Number</label>
                    <input type="text" name="contact_no" class="form-input" value="<?php echo e(post('contact_no', $editUser['contact_no'])); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Employee Designation</label>
                    <input type="text" name="designation" class="form-input" value="<?php echo e(post('designation', $editUser['designation'])); ?>">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Joining Date</label>
                <input type="date" name="joining_date" class="form-input" value="<?php echo e(post('joining_date', $editUser['joining_date'] ?? '')); ?>">
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Role</label>
                    <select name="role" class="form-select" id="edit-role-select" <?php echo $editUser['role'] === 'founder' ? 'disabled' : ''; ?>>
                        <?php if ($editUser['role'] === 'founder'): ?>
                            <option value="founder" selected>Founder</option>
                        <?php else: ?>
                            <?php foreach ($allRoles as $r): ?>
                                <?php if ($r['slug'] === 'founder') continue; ?>
                                <option value="<?php echo e($r['slug']); ?>" data-base="<?php echo e($r['base_role']); ?>" <?php echo $editUser['role'] === $r['slug'] ? 'selected' : ''; ?>>
                                    <?php echo e($r['name']); ?> (<?php echo ucfirst(e($r['base_role'])); ?> Workspace)
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                    <?php if ($editUser['role'] === 'founder'): ?>
                        <input type="hidden" name="role" value="founder">
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select" <?php echo $editUser['role'] === 'founder' ? 'disabled' : ''; ?>>
                        <option value="active" <?php echo $editUser['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo $editUser['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                    <?php if ($editUser['role'] === 'founder'): ?>
                        <input type="hidden" name="status" value="active">
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="form-group" id="edit-manager-group" style="<?php 
                $editBase = getRoleBaseType($editUser['role']);
                echo in_array($editBase, ['manager', 'hr', 'founder']) ? 'display:none;' : ''; 
            ?>">
                <label class="form-label">Assign to Manager</label>
                <select name="manager_id" class="form-select">
                    <option value="">— No Manager —</option>
                    <?php foreach ($managers as $mgr): ?>
                        <option value="<?php echo $mgr['id']; ?>" <?php echo $editUser['manager_id'] == $mgr['id'] ? 'selected' : ''; ?>><?php echo e($mgr['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">New Password <span class="text-muted">(leave blank to keep current)</span></label>
                    <input type="password" name="new_password" class="form-input" placeholder="Enter new password" minlength="6">
                </div>
                <div class="form-group">
                    <label class="form-label">New 4-Digit Security PIN <span class="text-muted">(leave blank to keep current)</span></label>
                    <input type="text" name="pin" class="form-input" placeholder="e.g. 1234" maxlength="4" pattern="\d{4}">
                </div>
            </div>
            
            <div class="form-actions" style="margin-top: var(--space-4);">
                <a href="<?php echo BASE_URL; ?>/founder/user-management.php" class="btn btn-outline" style="width: 48%; text-align: center;">Cancel</a>
                <button type="submit" class="btn btn-primary" style="width: 48%;">Update User</button>
            </div>
        </form>
    </div>
    
    <script>
    const editRoleSelect = document.getElementById('edit-role-select');
    if (editRoleSelect) {
        editRoleSelect.addEventListener('change', function() {
            const opt = this.options[this.selectedIndex];
            const baseRole = opt ? (opt.getAttribute('data-base') || this.value) : this.value;
            document.getElementById('edit-manager-group').style.display = (baseRole === 'manager' || baseRole === 'hr' || baseRole === 'founder') ? 'none' : '';
        });
    }
    </script>

<?php else: ?>
    <!-- FILTER BAR -->
    <form method="GET" action="" class="filter-bar fade-in" style="margin-bottom: 20px; display: flex; flex-wrap: wrap; gap: 12px; align-items: center;">
        <div style="flex: 1; min-width: 220px; position: relative;">
            <input 
                type="text" 
                name="search" 
                class="form-input" 
                placeholder="Search by Name, Email, Emp ID, Designation..." 
                value="<?php echo e($search); ?>"
                style="padding-left: 36px;"
            >
            <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--color-text-muted); font-size: 14px;"></i>
        </div>

        <div style="min-width: 150px;">
            <select name="role_filter" class="form-select" onchange="this.form.submit()">
                <option value="all" <?php echo $filterRole === 'all' ? 'selected' : ''; ?>>All Roles (<?php echo $counts['total']; ?>)</option>
                <?php foreach ($allRoles as $r): ?>
                    <option value="<?php echo e($r['slug']); ?>" <?php echo $filterRole === $r['slug'] ? 'selected' : ''; ?>>
                        <?php echo e($r['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div style="min-width: 130px;">
            <select name="status_filter" class="form-select" onchange="this.form.submit()">
                <option value="all" <?php echo $filterStatus === 'all' ? 'selected' : ''; ?>>All Status</option>
                <option value="active" <?php echo $filterStatus === 'active' ? 'selected' : ''; ?>>Active Only</option>
                <option value="inactive" <?php echo $filterStatus === 'inactive' ? 'selected' : ''; ?>>Inactive Only</option>
            </select>
        </div>

        <div style="min-width: 160px;">
            <select name="manager_filter" class="form-select" onchange="this.form.submit()">
                <option value="all" <?php echo $filterManager === 'all' ? 'selected' : ''; ?>>All Managers</option>
                <option value="none" <?php echo $filterManager === 'none' ? 'selected' : ''; ?>>— No Manager —</option>
                <?php foreach ($managers as $mgr): ?>
                    <option value="<?php echo $mgr['id']; ?>" <?php echo $filterManager == $mgr['id'] ? 'selected' : ''; ?>>Mgr: <?php echo e($mgr['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <?php if (!empty($search) || $filterRole !== 'all' || $filterStatus !== 'all' || $filterManager !== 'all'): ?>
            <a href="<?php echo BASE_URL; ?>/founder/user-management.php" class="btn btn-outline"><i class="fa-solid fa-xmark"></i> Clear</a>
        <?php endif; ?>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-filter"></i> Filter</button>
    </form>

    <!-- USER LIST TABLE -->
    <div class="table-container fade-in">
        <?php if (empty($users)): ?>
            <div class="empty-state" style="padding: 40px 20px;">
                <div class="empty-state-icon"><i class="fa-solid fa-users" style="font-size: 36px;"></i></div>
                <div class="empty-state-title" style="margin-top: 10px; font-size: 16px;">No users found</div>
                <div class="empty-state-text" style="color: var(--color-text-muted); font-size: 13px;">
                    No accounts match your current filter criteria. Try resetting the filters.
                </div>
            </div>
        <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Emp ID</th>
                        <th>User</th>
                        <th>Email / Contact</th>
                        <th>Role</th>
                        <th>Manager</th>
                        <th>Status</th>
                        <th>Joining Date</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><code><?php echo e($user['employee_id'] ?: '—'); ?></code></td>
                            <td>
                                <div class="table-user">
                                    <div class="table-user-avatar"><?php echo e(getInitials($user['name'])); ?></div>
                                    <div>
                                        <div class="table-user-name"><?php echo e($user['name']); ?></div>
                                        <small class="text-muted" style="font-size: var(--text-xs);"><?php echo e($user['designation'] ?: '—'); ?></small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <?php echo e($user['email']); ?><br>
                                <small class="text-muted"><?php echo e($user['contact_no'] ?: '—'); ?></small>
                            </td>
                            <td><span class="badge <?php echo roleBadge($user['role']); ?>"><?php echo ucfirst(e($user['role'])); ?></span></td>
                            <td><?php echo e($user['manager_name'] ?? '—'); ?></td>
                            <td><span class="badge <?php echo userStatusBadge($user['status']); ?>"><?php echo ucfirst(e($user['status'])); ?></span></td>
                            <td><?php echo !empty($user['joining_date']) ? formatDate($user['joining_date']) : formatDate($user['created_at']); ?></td>
                            <td style="text-align: right;">
                                <div class="table-actions" style="display: inline-flex; gap: var(--space-1);">
                                    <a href="?action=edit&id=<?php echo $user['id']; ?>" class="btn btn-ghost btn-sm" title="Edit"><i class="fa-solid fa-pen"></i></a>
                                    <?php if ($user['role'] !== 'founder'): ?>
                                        <a href="?toggle=<?php echo $user['id']; ?>" class="btn btn-ghost btn-sm" title="Toggle Status">
                                            <?php echo $user['status'] === 'active' ? '<i class="fa-solid fa-lock"></i>' : '<i class="fa-solid fa-lock-open"></i>'; ?>
                                        </a>
                                        <a href="?delete=<?php echo $user['id']; ?>" class="btn btn-ghost btn-sm" data-confirm="Delete this user permanently? This will also delete their attendance and task records." title="Delete"><i class="fa-solid fa-trash-can"></i></a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
