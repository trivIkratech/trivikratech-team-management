<?php
/**
 * Manager — Team & Company Employees Directory
 * 
 * Allows managers to view employees, accurately monitor today's attendance status,
 * view task progression, and add/edit team members.
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
    
    $employeeId = trim(post('employee_id'));
    $name = trim(post('name'));
    $email = trim(post('email'));
    $contactNo = trim(post('contact_no'));
    $designation = trim(post('designation'));
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
        $usePin = !empty($pin) ? $pin : '1234';
        $pinHash = password_hash($usePin, PASSWORD_BCRYPT);
        $joiningDate = post('joining_date') ?: date('Y-m-d');
        
        $stmt = $db->prepare("INSERT INTO users (employee_id, name, email, contact_no, designation, joining_date, password, pin, role, manager_id, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'employee', ?, 'active')");
        $stmt->execute([$employeeId, $name, $email, $contactNo ?: null, $designation ?: null, $joiningDate, $hash, $pinHash, $managerId]);
        
        setFlash('success', 'Employee added successfully to your team.');
        header('Location: ' . BASE_URL . '/manager/employees.php');
        exit;
    }
}

// EDIT EMPLOYEE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('form_action') === 'edit_employee') {
    requireCsrf();
    
    $userId = (int)post('user_id');
    $employeeId = trim(post('employee_id'));
    $name = trim(post('name'));
    $email = trim(post('email'));
    $contactNo = trim(post('contact_no'));
    $designation = trim(post('designation'));
    $joiningDate = post('joining_date') ?: null;
    $status = post('status');
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
        $query = "UPDATE users SET employee_id = ?, name = ?, email = ?, contact_no = ?, designation = ?, joining_date = ?, status = ?, updated_at = NOW()";
        $params = [$employeeId, $name, $email, $contactNo ?: null, $designation ?: null, $joiningDate, $status];
        
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
            
            $query .= " WHERE id = ?";
            $params[] = $userId;
            
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
        $stmt = $db->prepare("DELETE FROM users WHERE id = ? AND role = 'employee'");
        $stmt->execute([$userId]);
        if ($stmt->rowCount() > 0) {
            setFlash('success', 'Employee deleted successfully.');
        } else {
            setFlash('error', 'Employee not found.');
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
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ? AND role = 'employee'");
    $stmt->execute([$editId]);
    $editUser = $stmt->fetch();
    if (!$editUser) {
        setFlash('error', 'Employee record not found.');
        header('Location: ' . BASE_URL . '/manager/employees.php');
        exit;
    }
}

// Filter parameters
$filterScope = get('scope', 'all'); // 'all' or 'my_team'
$filterStatus = get('status_filter', 'all'); // 'all', 'present', 'half-day', 'leave', 'absent'
$search = trim(get('search', ''));

$where = ["u.role = 'employee'"];
$params = [$today, $today];

if ($filterScope === 'my_team') {
    $where[] = "u.manager_id = ?";
    $params[] = $managerId;
}

if (!empty($search)) {
    $where[] = "(u.name LIKE ? OR u.email LIKE ? OR u.employee_id LIKE ? OR u.designation LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

$whereClause = implode(' AND ', $where);

// Accurate Employee & Today Attendance Query
$stmt = $db->prepare("
    SELECT u.*, m.name AS manager_name,
        (SELECT COUNT(*) FROM tasks t WHERE t.assigned_to = u.id AND t.status = 'completed') AS completed_tasks,
        (SELECT COUNT(*) FROM tasks t WHERE t.assigned_to = u.id AND t.status IN ('todo','in_progress')) AS pending_tasks,
        a.id AS today_attendance_id,
        a.check_in AS today_check_in,
        a.check_out AS today_check_out,
        a.total_working_time AS today_working_time,
        a.status AS today_att_status,
        l.id AS today_leave_id,
        l.leave_type AS today_leave_type,
        l.status AS today_leave_status
    FROM users u 
    LEFT JOIN users m ON u.manager_id = m.id
    LEFT JOIN attendance a ON u.id = a.user_id AND a.date = ?
    LEFT JOIN leaves l ON u.id = l.user_id AND ? BETWEEN l.start_date AND l.end_date AND l.status = 'approved'
    WHERE {$whereClause}
    ORDER BY (u.manager_id = {$managerId}) DESC, u.status ASC, u.name ASC
");
$stmt->execute($params);
$employees = $stmt->fetchAll();

// Filter by today's attendance status in PHP if applied
if ($filterStatus !== 'all') {
    $employees = array_filter($employees, function($emp) use ($filterStatus, $today) {
        $hasJoined = isEmployeeJoinedOnDate($today, $emp['joining_date'] ?? null, $emp['created_at'] ?? null);
        if (!$hasJoined) {
            return ($filterStatus === 'absent') ? false : false;
        }

        $resolved = resolveAttendanceStatus(
            $emp['today_check_in'],
            $emp['today_check_out'],
            $emp['today_working_time'],
            $emp['today_att_status'],
            !empty($emp['today_leave_id']) ? (int)$emp['today_leave_id'] : null
        );

        return $resolved === $filterStatus;
    });
}

// Summary stats
$totalMyTeam = $db->prepare("SELECT COUNT(*) FROM users WHERE manager_id = ? AND role = 'employee' AND status = 'active'");
$totalMyTeam->execute([$managerId]);
$myTeamCount = (int)$totalMyTeam->fetchColumn();

$pageTitle = 'Manage Employees';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title"><i class="fa-solid fa-users" style="color: var(--color-primary); margin-right: 8px;"></i> Manage Employees</h1>
        <p class="page-subtitle">Track team and company employees, accurate real-time attendance, and task status</p>
    </div>
    <div style="display: flex; gap: 8px; flex-wrap: wrap;">
        <a href="<?php echo BASE_URL; ?>/manager/attendance.php" class="btn btn-outline"><i class="fa-solid fa-clipboard-user"></i> Full Attendance Logs</a>
        <?php if ($action === 'list'): ?>
            <a href="?action=add" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Add Employee</a>
        <?php else: ?>
            <a href="<?php echo BASE_URL; ?>/manager/employees.php" class="btn btn-secondary">Back to List</a>
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
            <h3 class="card-title"><i class="fa-solid fa-plus" style="color: var(--color-primary); margin-right: 6px;"></i> Add New Employee to Your Team</h3>
        </div>
        <form method="POST" action="" data-validate>
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

            <div class="form-group">
                <label class="form-label">Joining Date *</label>
                <input type="date" name="joining_date" class="form-input" required value="<?php echo e(post('joining_date', date('Y-m-d'))); ?>">
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Password *</label>
                    <input type="password" name="password" class="form-input" required placeholder="Min 6 characters" minlength="6">
                </div>
                <div class="form-group">
                    <label class="form-label">Security PIN (4 Digits)</label>
                    <input type="text" name="pin" class="form-input" maxlength="4" pattern="\d{4}" placeholder="Default is 1234" value="<?php echo e(post('pin')); ?>">
                </div>
            </div>
            
            <div class="form-actions" style="margin-top: var(--space-4);">
                <button type="submit" class="btn btn-primary" style="width: 100%;"><i class="fa-solid fa-circle-check"></i> Create Employee Account</button>
            </div>
        </form>
    </div>

<!-- EDIT EMPLOYEE INTERFACE -->
<?php elseif ($action === 'edit' && $editUser): ?>
    <div class="card fade-in" style="max-width: 600px; margin: 0 auto;">
        <div class="card-header">
            <h3 class="card-title"><i class="fa-solid fa-pen" style="color: var(--color-primary); margin-right: 6px;"></i> Edit Employee: <?php echo e($editUser['name']); ?></h3>
        </div>
        <form method="POST" action="" data-validate>
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
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="active" <?php echo $editUser['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo $editUser['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Change Password <span class="text-muted">(optional)</span></label>
                    <input type="password" name="new_password" class="form-input" placeholder="Leave blank to keep current" minlength="6">
                </div>
                <div class="form-group">
                    <label class="form-label">Security PIN <span class="text-muted">(optional)</span></label>
                    <input type="text" name="pin" class="form-input" placeholder="e.g. 1234" maxlength="4" pattern="\d{4}">
                </div>
            </div>
            
            <div class="form-actions" style="margin-top: var(--space-4);">
                <button type="submit" class="btn btn-primary" style="width: 100%;"><i class="fa-solid fa-circle-check"></i> Save Changes</button>
            </div>
        </form>
    </div>

<!-- DEFAULT LIST INTERFACE -->
<?php else: ?>
    <!-- Filters -->
    <form method="GET" action="" class="filter-bar fade-in" style="margin-bottom: 20px; display: flex; flex-wrap: wrap; gap: 12px; align-items: center; background: var(--color-bg-secondary); padding: 14px 18px; border-radius: var(--radius-md); border: 1px solid var(--color-border);">
        <div style="flex: 1; min-width: 200px; position: relative;">
            <input 
                type="text" 
                name="search" 
                class="form-input" 
                placeholder="Search by Name, Email, Emp ID, Designation..." 
                value="<?php echo e($search); ?>"
                style="padding-left: 36px; height: 38px;"
            >
            <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--color-text-muted); font-size: 14px;"></i>
        </div>

        <div style="min-width: 160px;">
            <select name="scope" class="form-select" onchange="this.form.submit()" style="height: 38px;">
                <option value="all" <?php echo $filterScope === 'all' ? 'selected' : ''; ?>>All Company Staff</option>
                <option value="my_team" <?php echo $filterScope === 'my_team' ? 'selected' : ''; ?>>My Direct Team (<?php echo $myTeamCount; ?>)</option>
            </select>
        </div>

        <div style="min-width: 160px;">
            <select name="status_filter" class="form-select" onchange="this.form.submit()" style="height: 38px;">
                <option value="all" <?php echo $filterStatus === 'all' ? 'selected' : ''; ?>>All Today Status</option>
                <option value="present" <?php echo $filterStatus === 'present' ? 'selected' : ''; ?>>🟢 Present</option>
                <option value="half-day" <?php echo $filterStatus === 'half-day' ? 'selected' : ''; ?>>⏳ Half-Day</option>
                <option value="leave" <?php echo $filterStatus === 'leave' ? 'selected' : ''; ?>>🏖️ On Leave</option>
                <option value="absent" <?php echo $filterStatus === 'absent' ? 'selected' : ''; ?>>🔴 Absent</option>
            </select>
        </div>

        <?php if (!empty($search) || $filterScope !== 'all' || $filterStatus !== 'all'): ?>
            <a href="<?php echo BASE_URL; ?>/manager/employees.php" class="btn btn-outline" style="height: 38px; display: inline-flex; align-items: center;"><i class="fa-solid fa-xmark"></i> Clear</a>
        <?php endif; ?>
        <button type="submit" class="btn btn-primary" style="height: 38px;"><i class="fa-solid fa-filter"></i> Filter</button>
    </form>

    <?php if (empty($employees)): ?>
        <div class="card">
            <div class="empty-state" style="padding: 40px 20px;">
                <div class="empty-state-icon"><i class="fa-solid fa-users" style="font-size: 36px;"></i></div>
                <div class="empty-state-title" style="margin-top: 10px;">No employees match your filter</div>
                <div class="empty-state-text" style="color: var(--color-text-muted); font-size: 13px;">Try clearing search or switching team scope.</div>
            </div>
        </div>
    <?php else: ?>
        <div class="table-container fade-in">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Emp ID</th>
                        <th>Employee</th>
                        <th>Manager</th>
                        <th>Email / Contact</th>
                        <th>Today Attendance</th>
                        <th>Tasks (Pending)</th>
                        <th>Tasks (Done)</th>
                        <th>Account Status</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $isSunday = (date('l') === 'Sunday');
                    foreach ($employees as $emp): 
                        $isMine = ((int)($emp['manager_id'] ?? 0) === $managerId);
                    ?>
                        <tr>
                            <td><code><?php echo e($emp['employee_id'] ?: '—'); ?></code></td>
                            <td>
                                <div class="table-user">
                                    <div class="table-user-avatar"><?php echo e(getInitials($emp['name'])); ?></div>
                                    <div>
                                        <div class="table-user-name">
                                            <?php echo e($emp['name']); ?>
                                            <?php if ($isMine): ?>
                                                <span class="badge badge-primary" style="font-size: 10px; margin-left: 4px;">My Team</span>
                                            <?php endif; ?>
                                        </div>
                                        <small class="text-muted" style="font-size: var(--text-xs);"><?php echo e($emp['designation'] ?: '—'); ?></small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <?php if ($isMine): ?>
                                    <strong style="color: var(--color-primary); font-size: 12px;">You</strong>
                                <?php else: ?>
                                    <span class="text-muted" style="font-size: 12px;"><?php echo e($emp['manager_name'] ?? 'Unassigned'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo e($emp['email']); ?><br>
                                <small class="text-muted"><?php echo e($emp['contact_no'] ?: '—'); ?></small>
                            </td>
                            <td>
                                <?php
                                $hasJoined = isEmployeeJoinedOnDate($today, $emp['joining_date'] ?? null, $emp['created_at'] ?? null);
                                if (!$hasJoined) {
                                    echo '<span class="badge badge-secondary" title="Joining Date: ' . e($emp['joining_date'] ?? '') . '"><i class="fa-solid fa-user-clock"></i> Joins on ' . formatDate($emp['joining_date'] ?? '') . '</span>';
                                } elseif (!empty($emp['today_leave_id'])) {
                                    $lt = strtolower($emp['today_leave_type']);
                                    if ($lt === 'sick') {
                                        echo '<span class="badge badge-purple"><i class="fa-solid fa-notes-medical"></i> Sick Leave</span>';
                                    } elseif ($lt === 'paid') {
                                        echo '<span class="badge badge-info"><i class="fa-solid fa-award"></i> Paid Leave</span>';
                                    } elseif ($lt === 'casual' || $lt === 'planned') {
                                        echo '<span class="badge badge-primary"><i class="fa-solid fa-calendar-check"></i> Planned Leave</span>';
                                    } else {
                                        echo '<span class="badge badge-info"><i class="fa-solid fa-umbrella-beach"></i> ' . ucfirst(e($lt)) . ' Leave</span>';
                                    }
                                } else {
                                    $resolved = resolveAttendanceStatus(
                                        $emp['today_check_in'],
                                        $emp['today_check_out'],
                                        $emp['today_working_time'],
                                        $emp['today_att_status']
                                    );

                                    if ($resolved === 'present') {
                                        $checkInText = !empty($emp['today_check_in']) ? ' (' . formatTime($emp['today_check_in']) . ')' : '';
                                        echo '<span class="badge badge-success" title="Working: ' . e($emp['today_working_time'] ?? '') . '"><i class="fa-solid fa-circle-check"></i> Present' . $checkInText . '</span>';
                                    } elseif ($resolved === 'half-day') {
                                        $checkInText = !empty($emp['today_check_in']) ? ' (' . formatTime($emp['today_check_in']) . ')' : '';
                                        echo '<span class="badge badge-warning" title="Working: ' . e($emp['today_working_time'] ?? '') . '"><i class="fa-solid fa-hourglass-half"></i> Half-Day' . $checkInText . '</span>';
                                    } elseif ($isSunday) {
                                        echo '<span class="badge badge-secondary"><i class="fa-solid fa-bed"></i> Sunday Off</span>';
                                    } else {
                                        echo '<span class="badge badge-danger"><i class="fa-solid fa-circle-xmark"></i> Absent</span>';
                                    }
                                }
                                ?>
                            </td>
                            <td><span class="badge badge-warning"><?php echo (int)$emp['pending_tasks']; ?></span></td>
                            <td><span class="badge badge-success"><?php echo (int)$emp['completed_tasks']; ?></span></td>
                            <td><span class="badge <?php echo userStatusBadge($emp['status']); ?>"><?php echo ucfirst(e($emp['status'])); ?></span></td>
                            <td style="text-align: right;">
                                <div class="table-actions" style="display: inline-flex; gap: 4px;">
                                    <a href="?action=edit&id=<?php echo $emp['id']; ?>" class="btn btn-ghost btn-sm" title="Edit Employee">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </a>
                                    <a href="?delete=<?php echo $emp['id']; ?>" class="btn btn-ghost btn-sm text-danger" data-confirm="Are you sure you want to delete this employee account?" title="Delete Employee">
                                        <i class="fa-solid fa-trash-can"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
