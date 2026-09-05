<?php
/**
 * HR — Workforce & Employee Management
 * 
 * Comprehensive directory for HR to view all workforce profiles (Employees, Managers, Founders, HR),
 * and manage (Add, Edit, Delete) Employee & Manager accounts.
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

// ADD EMPLOYEE OR MANAGER (HR Power)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('form_action') === 'add_user') {
    requireCsrf();
    
    $employeeId = trim(post('employee_id'));
    $name = trim(post('name'));
    $email = trim(post('email'));
    $contactNo = trim(post('contact_no'));
    $designation = trim(post('designation'));
    $role = post('role', 'employee');
    $baseSalary = post('base_salary') !== '' ? (float)post('base_salary') : 30000.00;
    $password = $_POST['password'] ?? '';
    $pin = post('pin');
    $managerId = post('manager_id') ? (int)post('manager_id') : null;
    $joiningDate = post('joining_date') ?: date('Y-m-d');
    
    // Validation
    if (empty($employeeId)) $formErrors[] = 'Employee ID is required.';
    if (empty($name)) $formErrors[] = 'Name is required.';
    if (empty($email)) $formErrors[] = 'Email is required.';
    if (empty($password)) $formErrors[] = 'Password is required.';
    if (strlen($password) < 6) $formErrors[] = 'Password must be at least 6 characters.';
    if (!empty($pin) && !preg_match('/^\d{4}$/', $pin)) $formErrors[] = 'PIN must be exactly 4 digits.';
    if (!in_array($role, ['employee', 'manager'])) {
        $formErrors[] = 'HR can only create Employee or Manager accounts.';
    }
    
    // Check Employee ID Uniqueness
    if (empty($formErrors)) {
        $stmt = $db->prepare("SELECT id FROM users WHERE employee_id = ?");
        $stmt->execute([$employeeId]);
        if ($stmt->fetch()) $formErrors[] = 'Employee ID is already registered.';
    }
    
    // Check Email Uniqueness
    if (empty($formErrors)) {
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) $formErrors[] = 'Email is already registered.';
    }
    
    if (empty($formErrors)) {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $usePin = !empty($pin) ? $pin : '1234';
        $pinHash = password_hash($usePin, PASSWORD_BCRYPT);
        
        $stmt = $db->prepare("
            INSERT INTO users (employee_id, name, email, contact_no, designation, joining_date, base_salary, password, pin, role, manager_id, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')
        ");
        $stmt->execute([
            $employeeId, 
            $name, 
            $email, 
            $contactNo ?: null, 
            $designation ?: null, 
            $joiningDate, 
            $baseSalary, 
            $hash, 
            $pinHash, 
            $role, 
            ($role === 'manager' ? null : $managerId)
        ]);
        
        setFlash('success', ucfirst($role) . ' account created successfully.');
        header('Location: ' . BASE_URL . '/hr/employees.php');
        exit;
    }
}

// EDIT EMPLOYEE OR MANAGER (HR Power)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('form_action') === 'edit_user') {
    requireCsrf();
    
    $targetUserId = (int)post('user_id');
    $employeeId = trim(post('employee_id'));
    $name = trim(post('name'));
    $email = trim(post('email'));
    $contactNo = trim(post('contact_no'));
    $designation = trim(post('designation'));
    $role = post('role', 'employee');
    $joiningDate = post('joining_date') ?: null;
    $baseSalary = post('base_salary') !== '' ? (float)post('base_salary') : 30000.00;
    $status = post('status', 'active');
    $managerId = post('manager_id') ? (int)post('manager_id') : null;
    $newPassword = $_POST['new_password'] ?? '';
    $pin = post('pin');
    
    // Validate HR permission: target must be employee or manager
    $checkStmt = $db->prepare("SELECT role FROM users WHERE id = ?");
    $checkStmt->execute([$targetUserId]);
    $existingRole = $checkStmt->fetchColumn();
    
    if (!$existingRole || !in_array($existingRole, ['employee', 'manager'])) {
        $formErrors[] = 'Access Denied: HR can only edit Employee or Manager accounts.';
    }
    
    if (empty($employeeId)) $formErrors[] = 'Employee ID is required.';
    if (empty($name)) $formErrors[] = 'Name is required.';
    if (empty($email)) $formErrors[] = 'Email is required.';
    if (!empty($pin) && !preg_match('/^\d{4}$/', $pin)) $formErrors[] = 'PIN must be exactly 4 digits.';
    if (!in_array($role, ['employee', 'manager'])) {
        $formErrors[] = 'Invalid role specified.';
    }
    
    if (empty($formErrors)) {
        $stmt = $db->prepare("SELECT id FROM users WHERE employee_id = ? AND id != ?");
        $stmt->execute([$employeeId, $targetUserId]);
        if ($stmt->fetch()) $formErrors[] = 'Employee ID is already in use by another user.';
    }
    
    if (empty($formErrors)) {
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $targetUserId]);
        if ($stmt->fetch()) $formErrors[] = 'Email is already in use by another user.';
    }
    
    if (empty($formErrors)) {
        $query = "UPDATE users SET employee_id = ?, name = ?, email = ?, contact_no = ?, designation = ?, joining_date = ?, base_salary = ?, role = ?, status = ?, manager_id = ?, updated_at = NOW()";
        $params = [$employeeId, $name, $email, $contactNo ?: null, $designation ?: null, $joiningDate, $baseSalary, $role, $status, ($role === 'manager' ? null : $managerId)];
        
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
            
            $query .= " WHERE id = ? AND role IN ('employee', 'manager')";
            $params[] = $targetUserId;
            
            $stmt = $db->prepare($query);
            $stmt->execute($params);
            
            setFlash('success', ucfirst($role) . ' record updated successfully.');
            header('Location: ' . BASE_URL . '/hr/employees.php');
            exit;
        }
    }
}

// DELETE USER (HR can delete Employee or Manager)
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $targetUserId = (int)$_GET['delete'];
    
    if ($targetUserId === $userId) {
        setFlash('error', 'You cannot delete your own account.');
    } else {
        try {
            $stmt = $db->prepare("DELETE FROM users WHERE id = ? AND role IN ('employee', 'manager')");
            $stmt->execute([$targetUserId]);
            if ($stmt->rowCount() > 0) {
                setFlash('success', 'User deleted successfully.');
            } else {
                setFlash('error', 'Could not delete record (permission denied or user not found).');
            }
        } catch (PDOException $e) {
            setFlash('error', 'Could not delete the record due to linked data dependencies.');
        }
    }
    header('Location: ' . BASE_URL . '/hr/employees.php');
    exit;
}

// Fetch user for edit tab (Employee or Manager)
if ($tab === 'edit' && isset($_GET['id']) && is_numeric($_GET['id'])) {
    $editId = (int)$_GET['id'];
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ? AND role IN ('employee', 'manager')");
    $stmt->execute([$editId]);
    $editUser = $stmt->fetch();
    
    if (!$editUser) {
        setFlash('error', 'User not found or permission denied.');
        header('Location: ' . BASE_URL . '/hr/employees.php');
        exit;
    }
}

// Fetch overview modal data if requested (Works for all workforce members: Founder, Manager, Employee, HR)
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
        $stmt = $db->prepare("SELECT t.*, u.name as assigner_name FROM tasks t JOIN users u ON t.assigned_by = u.id WHERE t.assigned_to = ? ORDER BY t.created_at DESC LIMIT 10");
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

// Fetch active managers for dropdowns
$managers = $db->query("SELECT id, name, designation FROM users WHERE role = 'manager' AND status = 'active' ORDER BY name ASC")->fetchAll();

// Filter parameters for Profiles list
$search = trim(get('search', ''));
$filterRole = get('role_filter', 'all');
$filterStatus = get('status_filter', 'all');

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

if ($filterRole !== 'all' && in_array($filterRole, ['founder', 'manager', 'employee', 'hr'])) {
    $where[] = "u.role = ?";
    $params[] = $filterRole;
}

if ($filterStatus !== 'all' && in_array($filterStatus, ['active', 'inactive'])) {
    $where[] = "u.status = ?";
    $params[] = $filterStatus;
}

$whereClause = !empty($where) ? "WHERE " . implode(' AND ', $where) : "";

// Fetch all workforce members matching filters (Employee, Manager, Founder, HR)
$query = "
    SELECT u.*, m.name AS manager_name
    FROM users u 
    LEFT JOIN users m ON u.manager_id = m.id
    {$whereClause}
    ORDER BY FIELD(u.role, 'founder', 'hr', 'manager', 'employee'), u.status ASC, u.name ASC
";
$stmt = $db->prepare($query);
$stmt->execute($params);
$employees = $stmt->fetchAll();

// Workforce Counts for Quick Badges / Metrics
$totalAllUsers = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
$countEmployees = $db->query("SELECT COUNT(*) FROM users WHERE role = 'employee'")->fetchColumn();
$countManagers = $db->query("SELECT COUNT(*) FROM users WHERE role = 'manager'")->fetchColumn();
$countFounders = $db->query("SELECT COUNT(*) FROM users WHERE role = 'founder'")->fetchColumn();
$countHR = $db->query("SELECT COUNT(*) FROM users WHERE role = 'hr'")->fetchColumn();

// Group workforce for Teams tab
$allWorkforce = $db->query("
    SELECT u.*, m.name AS manager_name
    FROM users u 
    LEFT JOIN users m ON u.manager_id = m.id
    ORDER BY FIELD(u.role, 'founder', 'hr', 'manager', 'employee'), u.name ASC
")->fetchAll();

$teams = [];
$foundersList = [];
$hrList = [];

foreach ($allWorkforce as $person) {
    if ($person['role'] === 'founder') {
        $foundersList[] = $person;
    } elseif ($person['role'] === 'hr') {
        $hrList[] = $person;
    } elseif ($person['role'] === 'employee') {
        $mgrName = $person['manager_name'] ?? 'Unassigned / Direct HR';
        $teams[$mgrName][] = $person;
    }
}

// Fetch assigned tasks for Tasks tab (employees & managers)
$allAssignedTasks = $db->query("
    SELECT t.*, u.name AS employee_name, u.employee_id, u.role AS employee_role, m.name AS manager_name
    FROM tasks t
    JOIN users u ON t.assigned_to = u.id
    LEFT JOIN users m ON t.assigned_by = m.id
    ORDER BY t.created_at DESC
")->fetchAll();

$pageTitle = 'HR — Workforce & Employee Management';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Workforce Directory</h1>
        <p class="page-subtitle">View all organization members (Founder, Managers, Employees & HR) and manage staff accounts</p>
    </div>
    <div style="display: flex; gap: 8px;">
        <a href="?tab=add" class="btn btn-primary"><i class="fa-solid fa-user-plus"></i> Add Employee / Manager</a>
    </div>
</div>

<!-- Top Metrics / Count Cards -->
<div class="stats-grid" style="margin-bottom: var(--space-6);">
    <div class="stat-card accent-blue fade-in">
        <div class="stat-icon bg-blue"><i class="fa-solid fa-users"></i></div>
        <div class="stat-content">
            <div class="stat-value"><?php echo $totalAllUsers; ?></div>
            <div class="stat-label">Total Workforce</div>
        </div>
    </div>
    <div class="stat-card accent-green fade-in">
        <div class="stat-icon bg-green"><i class="fa-solid fa-id-badge"></i></div>
        <div class="stat-content">
            <div class="stat-value"><?php echo $countEmployees; ?></div>
            <div class="stat-label">Employees</div>
        </div>
    </div>
    <div class="stat-card accent-orange fade-in">
        <div class="stat-icon bg-orange"><i class="fa-solid fa-user-tie"></i></div>
        <div class="stat-content">
            <div class="stat-value"><?php echo $countManagers; ?></div>
            <div class="stat-label">Managers</div>
        </div>
    </div>
    <div class="stat-card accent-purple fade-in">
        <div class="stat-icon bg-purple"><i class="fa-solid fa-crown"></i></div>
        <div class="stat-content">
            <div class="stat-value"><?php echo $countFounders + $countHR; ?></div>
            <div class="stat-label">Founders & HR (<?php echo $countFounders; ?> / <?php echo $countHR; ?>)</div>
        </div>
    </div>
</div>

<!-- Tabs Bar -->
<div class="nav-tabs" style="display: flex; gap: var(--space-2); border-bottom: 1px solid var(--color-border); margin-bottom: var(--space-5);">
    <a href="?tab=profiles" class="tab-item <?php echo $tab === 'profiles' ? 'active' : ''; ?>" style="padding: var(--space-3) var(--space-4); text-decoration: none; font-weight: 500; border-bottom: 2px solid <?php echo $tab === 'profiles' ? 'var(--color-primary)' : 'transparent'; ?>; color: <?php echo $tab === 'profiles' ? 'var(--color-primary)' : 'var(--color-text-secondary)'; ?>;">
        <i class="fa-solid fa-address-book"></i> All Workforce Profiles
    </a>
    <a href="?tab=teams" class="tab-item <?php echo $tab === 'teams' ? 'active' : ''; ?>" style="padding: var(--space-3) var(--space-4); text-decoration: none; font-weight: 500; border-bottom: 2px solid <?php echo $tab === 'teams' ? 'var(--color-primary)' : 'transparent'; ?>; color: <?php echo $tab === 'teams' ? 'var(--color-primary)' : 'var(--color-text-secondary)'; ?>;">
        <i class="fa-solid fa-people-group"></i> Teams & Squads View
    </a>
    <a href="?tab=tasks" class="tab-item <?php echo $tab === 'tasks' ? 'active' : ''; ?>" style="padding: var(--space-3) var(--space-4); text-decoration: none; font-weight: 500; border-bottom: 2px solid <?php echo $tab === 'tasks' ? 'var(--color-primary)' : 'transparent'; ?>; color: <?php echo $tab === 'tasks' ? 'var(--color-primary)' : 'var(--color-text-secondary)'; ?>;">
        <i class="fa-solid fa-list-check"></i> Assigned Tasks
    </a>
    <a href="?tab=add" class="tab-item <?php echo $tab === 'add' ? 'active' : ''; ?>" style="padding: var(--space-3) var(--space-4); text-decoration: none; font-weight: 500; border-bottom: 2px solid <?php echo $tab === 'add' ? 'var(--color-primary)' : 'transparent'; ?>; color: <?php echo $tab === 'add' ? 'var(--color-primary)' : 'var(--color-text-secondary)'; ?>;">
        <i class="fa-solid fa-plus-circle"></i> Add New Member
    </a>
</div>

<?php if (!empty($formErrors)): ?>
    <div class="alert alert-error" style="margin-bottom: var(--space-4);">
        <span><?php echo e(implode(' ', $formErrors)); ?></span>
        <button class="alert-close" onclick="this.parentElement.remove()">×</button>
    </div>
<?php endif; ?>

<!-- TAB 1: ALL WORKFORCE PROFILES (EMPLOYEE, MANAGER, FOUNDER, HR) -->
<?php if ($tab === 'profiles'): ?>

    <!-- Search & Filter Bar -->
    <div class="card" style="margin-bottom: var(--space-4); padding: var(--space-4);">
        <form method="GET" action="" style="display: flex; gap: 12px; flex-wrap: wrap; align-items: flex-end;">
            <input type="hidden" name="tab" value="profiles">
            
            <div style="flex: 1; min-width: 220px;">
                <label class="form-label" style="font-size: 12px; margin-bottom: 4px;">Search Members</label>
                <div style="position: relative;">
                    <input type="text" name="search" class="form-input" placeholder="Search by name, email, ID, title..." value="<?php echo e($search); ?>" style="padding-left: 32px;">
                    <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: var(--color-text-muted); font-size: 13px;"></i>
                </div>
            </div>

            <div style="min-width: 150px;">
                <label class="form-label" style="font-size: 12px; margin-bottom: 4px;">Filter by Role</label>
                <select name="role_filter" class="form-select">
                    <option value="all" <?php echo $filterRole === 'all' ? 'selected' : ''; ?>>All Roles (<?php echo $totalAllUsers; ?>)</option>
                    <option value="employee" <?php echo $filterRole === 'employee' ? 'selected' : ''; ?>>Employees (<?php echo $countEmployees; ?>)</option>
                    <option value="manager" <?php echo $filterRole === 'manager' ? 'selected' : ''; ?>>Managers (<?php echo $countManagers; ?>)</option>
                    <option value="founder" <?php echo $filterRole === 'founder' ? 'selected' : ''; ?>>Founders (<?php echo $countFounders; ?>)</option>
                    <option value="hr" <?php echo $filterRole === 'hr' ? 'selected' : ''; ?>>HR Team (<?php echo $countHR; ?>)</option>
                </select>
            </div>

            <div style="min-width: 140px;">
                <label class="form-label" style="font-size: 12px; margin-bottom: 4px;">Status</label>
                <select name="status_filter" class="form-select">
                    <option value="all" <?php echo $filterStatus === 'all' ? 'selected' : ''; ?>>All Status</option>
                    <option value="active" <?php echo $filterStatus === 'active' ? 'selected' : ''; ?>>Active Only</option>
                    <option value="inactive" <?php echo $filterStatus === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                </select>
            </div>

            <div style="display: flex; gap: 6px;">
                <button type="submit" class="btn btn-primary" style="height: 38px;">
                    <i class="fa-solid fa-filter"></i> Filter
                </button>
                <?php if (!empty($search) || $filterRole !== 'all' || $filterStatus !== 'all'): ?>
                    <a href="?tab=profiles" class="btn btn-ghost" style="height: 38px;" title="Reset filters">
                        <i class="fa-solid fa-rotate-left"></i> Reset
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Profiles Table -->
    <div class="table-container fade-in">
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 100px;">Emp ID</th>
                    <th style="min-width: 200px;">Member Name</th>
                    <th style="width: 110px;">Role</th>
                    <th style="min-width: 200px;">Email & Contact</th>
                    <th style="min-width: 140px;">Designation</th>
                    <th style="min-width: 140px;">Reporting To</th>
                    <th style="text-align: center; width: 100px;">Status</th>
                    <th style="width: 110px;">Joining Date</th>
                    <th style="text-align: right; width: 140px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($employees)): ?>
                    <tr>
                        <td colspan="9" class="text-center text-muted" style="padding: 30px;">
                            <i class="fa-solid fa-user-slash" style="font-size: 24px; margin-bottom: 8px; display: block; opacity: 0.5;"></i>
                            No members found matching your search and filter criteria.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($employees as $emp): ?>
                        <?php 
                            $canManage = in_array($emp['role'], ['employee', 'manager']);
                        ?>
                        <tr>
                            <td>
                                <span class="badge badge-secondary" style="font-family: var(--font-mono, monospace); font-size: 11.5px;">
                                    <?php echo e($emp['employee_id'] ?: '—'); ?>
                                </span>
                            </td>
                            <td>
                                <div class="table-user">
                                    <div class="table-user-avatar"><?php echo e(getInitials($emp['name'])); ?></div>
                                    <div class="table-user-info">
                                        <div class="table-user-name">
                                            <a href="?tab=profiles&overview=<?php echo $emp['id']; ?>" style="color: inherit; text-decoration: none; font-weight: 600;">
                                                <?php echo e($emp['name']); ?>
                                            </a>
                                            <?php if ($emp['id'] === $userId): ?>
                                                <span class="badge badge-secondary" style="font-size: 10px; margin-left: 4px;">You</span>
                                            <?php endif; ?>
                                        </div>
                                        <?php if (!empty($emp['designation'])): ?>
                                            <div class="table-user-designation"><?php echo e($emp['designation']); ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="badge <?php echo roleBadge($emp['role']); ?>" style="font-weight: 600; text-transform: capitalize;">
                                    <?php echo e($emp['role']); ?>
                                </span>
                            </td>
                            <td>
                                <div style="display: flex; flex-direction: column; gap: 2px;">
                                    <span style="font-size: 12.5px; color: var(--color-text); font-weight: 500;"><?php echo e($emp['email']); ?></span>
                                    <?php if (!empty($emp['contact_no'])): ?>
                                        <span style="font-size: 11.5px; color: var(--color-text-muted); display: inline-flex; align-items: center; gap: 4px;">
                                            <i class="fa-solid fa-phone" style="font-size: 9px; opacity: 0.7;"></i>
                                            <?php echo e($emp['contact_no']); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td><span style="font-size: 12.5px;"><?php echo e($emp['designation'] ?: '—'); ?></span></td>
                            <td>
                                <?php if ($emp['role'] === 'founder'): ?>
                                    <span style="font-size: 12px; color: var(--color-purple, #8b5cf6); font-weight: 500;"><i class="fa-solid fa-crown" style="font-size: 10px;"></i> Executive</span>
                                <?php elseif ($emp['role'] === 'manager' || $emp['role'] === 'hr'): ?>
                                    <span style="font-size: 12px; color: var(--color-text-muted);">Direct Management</span>
                                <?php else: ?>
                                    <span style="font-size: 12.5px; color: var(--color-text-secondary);"><?php echo e($emp['manager_name'] ?? '— (Direct)'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: center;">
                                <span class="badge <?php echo userStatusBadge($emp['status']); ?>"><?php echo ucfirst(e($emp['status'])); ?></span>
                            </td>
                            <td><span style="font-size: 12px; color: var(--color-text-muted);"><?php echo !empty($emp['joining_date']) ? formatDate($emp['joining_date']) : formatDate($emp['created_at']); ?></span></td>
                            <td style="text-align: right;">
                                <div class="table-actions" style="display: inline-flex; gap: 4px; justify-content: flex-end;">
                                    <!-- Overview Modal Action -->
                                    <a href="?tab=profiles&overview=<?php echo $emp['id']; ?>" class="btn btn-ghost btn-sm" style="color: var(--color-info);" title="View Overview">
                                        <i class="fa-solid fa-eye"></i>
                                    </a>

                                    <?php if ($canManage): ?>
                                        <!-- Edit Action for Employee & Manager -->
                                        <a href="?tab=edit&id=<?php echo $emp['id']; ?>" class="btn btn-ghost btn-sm" style="color: var(--color-primary);" title="Edit <?php echo ucfirst($emp['role']); ?>">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </a>
                                        <!-- Delete Action -->
                                        <a href="?delete=<?php echo $emp['id']; ?>" class="btn btn-ghost btn-sm text-danger" onclick="return confirm('Are you sure you want to delete this <?php echo $emp['role']; ?> record permanently?')" title="Delete <?php echo ucfirst($emp['role']); ?>">
                                            <i class="fa-solid fa-trash-can"></i>
                                        </a>
                                    <?php else: ?>
                                        <!-- Protected Role Indicator -->
                                        <span class="btn btn-ghost btn-sm" style="opacity: 0.3; cursor: not-allowed;" title="Protected System Account">
                                            <i class="fa-solid fa-lock"></i>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

<!-- TAB 2: TEAMS VIEW -->
<?php elseif ($tab === 'teams'): ?>
    
    <!-- Founders & Leadership -->
    <?php if (!empty($foundersList) || !empty($hrList)): ?>
        <div style="margin-bottom: var(--space-6);">
            <h3 style="font-size: 16px; margin-bottom: 12px; display: flex; align-items: center; gap: 8px;">
                <i class="fa-solid fa-shield-halved" style="color: var(--color-primary);"></i> Leadership & HR Team
            </h3>
            <div class="content-grid" style="grid-template-columns: repeat(auto-fill, minmax(min(100%, 280px), 1fr));">
                <?php foreach (array_merge($foundersList, $hrList) as $lead): ?>
                    <div class="card" style="border-left: 4px solid <?php echo $lead['role'] === 'founder' ? 'var(--color-purple, #8b5cf6)' : 'var(--color-primary)'; ?>;">
                        <div style="display: flex; align-items: center; justify-content: space-between;">
                            <div style="display: flex; align-items: center; gap: var(--space-3);">
                                <div class="table-user-avatar"><?php echo e(getInitials($lead['name'])); ?></div>
                                <div>
                                    <strong style="font-size: 14px;"><?php echo e($lead['name']); ?></strong><br>
                                    <small class="text-muted"><?php echo e($lead['designation'] ?: ucfirst($lead['role'])); ?></small>
                                </div>
                            </div>
                            <span class="badge <?php echo roleBadge($lead['role']); ?>"><?php echo ucfirst($lead['role']); ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Manager & Squad Breakdown -->
    <h3 style="font-size: 16px; margin-bottom: 12px; display: flex; align-items: center; gap: 8px;">
        <i class="fa-solid fa-people-group" style="color: var(--color-primary);"></i> Manager Squads & Direct Teams
    </h3>
    <div class="content-grid fade-in" style="grid-template-columns: repeat(auto-fill, minmax(min(100%, 300px), 1fr));">
        <?php if (empty($teams)): ?>
            <div class="card" style="grid-column: 1 / -1; text-align: center; padding: 30px;">
                <p class="text-muted">No team assignments configured yet.</p>
            </div>
        <?php else: ?>
            <?php foreach ($teams as $managerName => $members): ?>
                <div class="card">
                    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                        <h3 class="card-title" style="font-size: 15px;"><i class="fa-solid fa-user-tie" style="color: var(--color-warning);"></i> <?php echo e($managerName); ?></h3>
                        <span class="badge badge-info"><?php echo count($members); ?> Member(s)</span>
                    </div>
                    <div class="activity-list">
                        <?php foreach ($members as $m): ?>
                            <div class="activity-item" style="display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid var(--color-border);">
                                <div style="display: flex; align-items: center; gap: var(--space-3);">
                                    <div class="table-user-avatar" style="width: 32px; height: 32px; font-size: 11px;"><?php echo e(getInitials($m['name'])); ?></div>
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
        <?php endif; ?>
    </div>

<!-- TAB 3: ASSIGNED TASKS -->
<?php elseif ($tab === 'tasks'): ?>
    <div class="table-container fade-in">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Task Title</th>
                    <th>Assigned To</th>
                    <th>Role</th>
                    <th>Assigned By</th>
                    <th>Priority</th>
                    <th>Deadline</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($allAssignedTasks)): ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted" style="padding: 24px;">No assigned tasks found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($allAssignedTasks as $t): ?>
                        <tr>
                            <td><strong><?php echo e($t['title']); ?></strong></td>
                            <td><?php echo e($t['employee_name']); ?> (<code><?php echo e($t['employee_id']); ?></code>)</td>
                            <td><span class="badge <?php echo roleBadge($t['employee_role']); ?>"><?php echo ucfirst($t['employee_role']); ?></span></td>
                            <td><?php echo e($t['manager_name'] ?? 'System / Founder'); ?></td>
                            <td><span class="badge <?php echo priorityBadge($t['priority']); ?>"><?php echo ucfirst(e($t['priority'])); ?></span></td>
                            <td><?php echo formatDate($t['deadline']); ?></td>
                            <td><span class="badge <?php echo taskStatusBadge($t['status']); ?>"><?php echo ucfirst(e($t['status'])); ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

<!-- TAB 4: ADD EMPLOYEE OR MANAGER (HR Power) -->
<?php elseif ($tab === 'add'): ?>
    <div class="card fade-in" style="max-width: 650px; margin: 0 auto;">
        <div class="card-header">
            <h3 class="card-title"><i class="fa-solid fa-user-plus" style="color: var(--color-primary);"></i> Add New Employee or Manager</h3>
        </div>
        <form method="POST" action="">
            <?php echo csrfField(); ?>
            <input type="hidden" name="form_action" value="add_user">
            
            <div class="form-row">
                <div class="form-group" style="flex: 1;">
                    <label class="form-label">Role *</label>
                    <select name="role" id="add-role-select" class="form-select" required onchange="toggleManagerField(this.value)">
                        <option value="employee" <?php echo post('role') === 'employee' ? 'selected' : ''; ?>>Employee</option>
                        <option value="manager" <?php echo post('role') === 'manager' ? 'selected' : ''; ?>>Manager</option>
                    </select>
                </div>
                <div class="form-group" style="flex: 1;">
                    <label class="form-label">Employee ID No *</label>
                    <input type="text" name="employee_id" class="form-input" required placeholder="e.g. EMP-101 / MGR-002" value="<?php echo e(post('employee_id')); ?>">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Full Name *</label>
                <input type="text" name="name" class="form-input" required placeholder="e.g. John Doe" value="<?php echo e(post('name')); ?>">
            </div>
            
            <div class="form-group">
                <label class="form-label">Email Address (Login Username) *</label>
                <input type="email" name="email" class="form-input" required placeholder="e.g. john@trivikratech.com" value="<?php echo e(post('email')); ?>">
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Contact Number</label>
                    <input type="text" name="contact_no" class="form-input" placeholder="e.g. 9876543210" value="<?php echo e(post('contact_no')); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Designation / Title</label>
                    <input type="text" name="designation" class="form-input" placeholder="e.g. Senior Developer / Team Lead" value="<?php echo e(post('designation')); ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Joining Date *</label>
                    <input type="date" name="joining_date" class="form-input" required value="<?php echo e(post('joining_date', date('Y-m-d'))); ?>">
                </div>
                <div class="form-group" id="manager-select-container">
                    <label class="form-label">Reporting Manager (for Employees)</label>
                    <select name="manager_id" id="manager_id" class="form-select">
                        <option value="">— Direct HR / No Manager —</option>
                        <?php foreach ($managers as $mgr): ?>
                            <option value="<?php echo $mgr['id']; ?>" <?php echo post('manager_id') == $mgr['id'] ? 'selected' : ''; ?>>
                                <?php echo e($mgr['name']); ?> <?php echo !empty($mgr['designation']) ? '('.e($mgr['designation']).')' : ''; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Base Monthly Salary (₹)</label>
                    <input type="number" step="0.01" name="base_salary" class="form-input" placeholder="30000.00" value="<?php echo e(post('base_salary', '30000')); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Security PIN (4 Digits)</label>
                    <input type="text" name="pin" class="form-input" maxlength="4" placeholder="Default is 1234" value="<?php echo e(post('pin', '1234')); ?>">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Login Password *</label>
                <input type="password" name="password" class="form-input" required placeholder="Minimum 6 characters">
            </div>
            
            <div class="form-actions" style="margin-top: var(--space-4); display: flex; gap: 10px;">
                <button type="submit" class="btn btn-primary" style="flex: 1;">Create Account</button>
                <a href="?tab=profiles" class="btn btn-ghost">Cancel</a>
            </div>
        </form>
    </div>

    <script>
        function toggleManagerField(role) {
            const container = document.getElementById('manager-select-container');
            if (role === 'manager') {
                container.style.opacity = '0.5';
                document.getElementById('manager_id').value = '';
                document.getElementById('manager_id').disabled = true;
            } else {
                container.style.opacity = '1';
                document.getElementById('manager_id').disabled = false;
            }
        }
        document.addEventListener('DOMContentLoaded', () => {
            toggleManagerField(document.getElementById('add-role-select').value);
        });
    </script>

<!-- TAB 5: EDIT EMPLOYEE OR MANAGER (HR Power) -->
<?php elseif ($tab === 'edit' && $editUser): ?>
    <div class="card fade-in" style="max-width: 650px; margin: 0 auto;">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
            <h3 class="card-title"><i class="fa-solid fa-pen-to-square" style="color: var(--color-primary);"></i> Edit <?php echo ucfirst($editUser['role']); ?>: <?php echo e($editUser['name']); ?></h3>
            <span class="badge <?php echo roleBadge($editUser['role']); ?>"><?php echo ucfirst($editUser['role']); ?></span>
        </div>
        <form method="POST" action="">
            <?php echo csrfField(); ?>
            <input type="hidden" name="form_action" value="edit_user">
            <input type="hidden" name="user_id" value="<?php echo $editUser['id']; ?>">
            
            <div class="form-row">
                <div class="form-group" style="flex: 1;">
                    <label class="form-label">Role *</label>
                    <select name="role" id="edit-role-select" class="form-select" required onchange="toggleEditManagerField(this.value)">
                        <option value="employee" <?php echo post('role', $editUser['role']) === 'employee' ? 'selected' : ''; ?>>Employee</option>
                        <option value="manager" <?php echo post('role', $editUser['role']) === 'manager' ? 'selected' : ''; ?>>Manager</option>
                    </select>
                </div>
                <div class="form-group" style="flex: 1;">
                    <label class="form-label">Employee ID No *</label>
                    <input type="text" name="employee_id" class="form-input" required value="<?php echo e(post('employee_id', $editUser['employee_id'])); ?>">
                </div>
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
                <div class="form-group" id="edit-manager-container">
                    <label class="form-label">Reporting Manager</label>
                    <select name="manager_id" id="edit-manager-id" class="form-select">
                        <option value="">— Direct HR / No Manager —</option>
                        <?php foreach ($managers as $mgr): ?>
                            <?php if ($mgr['id'] !== $editUser['id']): ?>
                                <option value="<?php echo $mgr['id']; ?>" <?php echo (post('manager_id', $editUser['manager_id']) == $mgr['id']) ? 'selected' : ''; ?>>
                                    <?php echo e($mgr['name']); ?>
                                </option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Account Status *</label>
                    <select name="status" class="form-select" required>
                        <option value="active" <?php echo post('status', $editUser['status']) === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo post('status', $editUser['status']) === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Base Monthly Salary (₹)</label>
                    <input type="number" step="0.01" name="base_salary" class="form-input" value="<?php echo e(post('base_salary', $editUser['base_salary'] ?? '30000.00')); ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Change PIN (4 Digits)</label>
                    <input type="text" name="pin" class="form-input" maxlength="4" placeholder="Leave blank to keep current">
                </div>
                <div class="form-group">
                    <label class="form-label">Change Password</label>
                    <input type="password" name="new_password" class="form-input" placeholder="Leave blank to keep current">
                </div>
            </div>
            
            <div class="form-actions" style="margin-top: var(--space-4); display: flex; gap: 10px;">
                <button type="submit" class="btn btn-primary" style="flex: 1;">Save Changes</button>
                <a href="?tab=profiles" class="btn btn-ghost">Cancel</a>
            </div>
        </form>
    </div>

    <script>
        function toggleEditManagerField(role) {
            const container = document.getElementById('edit-manager-container');
            const select = document.getElementById('edit-manager-id');
            if (role === 'manager') {
                container.style.opacity = '0.5';
                select.disabled = true;
            } else {
                container.style.opacity = '1';
                select.disabled = false;
            }
        }
        document.addEventListener('DOMContentLoaded', () => {
            toggleEditManagerField(document.getElementById('edit-role-select').value);
        });
    </script>
<?php endif; ?>

<!-- WORKFORCE MEMBER OVERVIEW MODAL -->
<?php if ($overviewData): ?>
    <div class="modal-backdrop" style="position: fixed; inset: 0; background: rgba(0,0,0,0.65); backdrop-filter: blur(2px); display: flex; align-items: center; justify-content: center; z-index: 1000; padding: 20px;">
        <div class="card fade-in" style="max-width: 680px; width: 100%; max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.5);">
            <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--color-border); padding-bottom: 12px;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <div class="table-user-avatar"><?php echo e(getInitials($overviewData['user']['name'])); ?></div>
                    <div>
                        <h3 class="card-title" style="margin-bottom: 2px;"><?php echo e($overviewData['user']['name']); ?></h3>
                        <span class="badge <?php echo roleBadge($overviewData['user']['role']); ?>"><?php echo ucfirst($overviewData['user']['role']); ?></span>
                    </div>
                </div>
                <a href="?tab=profiles" class="btn btn-ghost btn-sm" style="text-decoration: none; font-size: 16px;">✕ Close</a>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin: 18px 0;">
                <div style="background: var(--color-bg-secondary); padding: 12px; border-radius: 8px;">
                    <small class="text-muted" style="display: block; margin-bottom: 2px;">Employee ID</small>
                    <div><strong><code><?php echo e($overviewData['user']['employee_id'] ?: '—'); ?></code></strong></div>
                </div>
                <div style="background: var(--color-bg-secondary); padding: 12px; border-radius: 8px;">
                    <small class="text-muted" style="display: block; margin-bottom: 2px;">Designation</small>
                    <div><strong><?php echo e($overviewData['user']['designation'] ?: '—'); ?></strong></div>
                </div>
                <div style="background: var(--color-bg-secondary); padding: 12px; border-radius: 8px;">
                    <small class="text-muted" style="display: block; margin-bottom: 2px;">Email Address</small>
                    <div><strong><?php echo e($overviewData['user']['email']); ?></strong></div>
                </div>
                <div style="background: var(--color-bg-secondary); padding: 12px; border-radius: 8px;">
                    <small class="text-muted" style="display: block; margin-bottom: 2px;">Contact Number</small>
                    <div><strong><?php echo e($overviewData['user']['contact_no'] ?: '—'); ?></strong></div>
                </div>
                <div style="background: var(--color-bg-secondary); padding: 12px; border-radius: 8px;">
                    <small class="text-muted" style="display: block; margin-bottom: 2px;">Reporting Manager</small>
                    <div><strong><?php echo e($overviewData['user']['manager_name'] ?: ($overviewData['user']['role'] === 'founder' ? 'Executive' : 'Direct')); ?></strong></div>
                </div>
                <div style="background: var(--color-bg-secondary); padding: 12px; border-radius: 8px;">
                    <small class="text-muted" style="display: block; margin-bottom: 2px;">Joining Date</small>
                    <div><strong><?php echo !empty($overviewData['user']['joining_date']) ? formatDate($overviewData['user']['joining_date']) : formatDate($overviewData['user']['created_at']); ?></strong></div>
                </div>
            </div>

            <div style="margin-top: 18px;">
                <h4 style="margin-bottom: 8px; font-size: 14px;"><i class="fa-solid fa-list-check" style="color: var(--color-primary);"></i> Assigned Tasks (<?php echo count($overviewData['tasks']); ?>)</h4>
                <?php if (empty($overviewData['tasks'])): ?>
                    <p class="text-muted" style="font-size: 13px;">No tasks recorded.</p>
                <?php else: ?>
                    <div style="background: var(--color-bg-secondary); border-radius: 8px; padding: 10px;">
                        <ul style="padding-left: 20px; margin: 0;">
                            <?php foreach ($overviewData['tasks'] as $t): ?>
                                <li style="margin-bottom: 6px; font-size: 13px;">
                                    <strong><?php echo e($t['title']); ?></strong> — 
                                    <span class="badge <?php echo taskStatusBadge($t['status']); ?>"><?php echo ucfirst(e($t['status'])); ?></span>
                                    <span style="font-size: 11px; color: var(--color-text-muted); margin-left: 6px;">Due: <?php echo formatDate($t['deadline']); ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>

            <div style="margin-top: 18px;">
                <h4 style="margin-bottom: 8px; font-size: 14px;"><i class="fa-solid fa-umbrella-beach" style="color: var(--color-warning);"></i> Recent Leave Applications (<?php echo count($overviewData['leaves']); ?>)</h4>
                <?php if (empty($overviewData['leaves'])): ?>
                    <p class="text-muted" style="font-size: 13px;">No leave applications recorded.</p>
                <?php else: ?>
                    <div style="background: var(--color-bg-secondary); border-radius: 8px; padding: 10px;">
                        <ul style="padding-left: 20px; margin: 0;">
                            <?php foreach ($overviewData['leaves'] as $l): ?>
                                <li style="margin-bottom: 6px; font-size: 13px;">
                                    <?php echo ucfirst(e($l['leave_type'])); ?> Leave (<?php echo formatDate($l['start_date']); ?> to <?php echo formatDate($l['end_date']); ?>) — 
                                    <span class="badge <?php echo $l['status'] === 'approved' ? 'badge-success' : ($l['status'] === 'denied' ? 'badge-danger' : 'badge-warning'); ?>"><?php echo ucfirst(e($l['status'])); ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (in_array($overviewData['user']['role'], ['employee', 'manager'])): ?>
                <div style="margin-top: 20px; display: flex; justify-content: flex-end; gap: 8px; border-top: 1px solid var(--color-border); padding-top: 14px;">
                    <a href="?tab=edit&id=<?php echo $overviewData['user']['id']; ?>" class="btn btn-primary btn-sm">
                        <i class="fa-solid fa-pen-to-square"></i> Edit <?php echo ucfirst($overviewData['user']['role']); ?> Profile
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
