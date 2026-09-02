<?php
/**
 * Founder — Company-Wide Attendance (Manage & Edit Employee, HR & Manager Attendance)
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/middleware.php';

requireRole([ROLE_FOUNDER]);

$db = getDB();

// Handle Edit / Manual Save Attendance by Founder (Founder can edit Employee, Manager & HR)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('form_action') === 'save_attendance') {
    requireCsrf();
    
    $targetUserId = (int)post('user_id');
    $attDate = post('date');
    $checkIn = post('check_in') ? (strlen(post('check_in')) === 5 ? post('check_in') . ':00' : post('check_in')) : null;
    $checkOut = post('check_out') ? (strlen(post('check_out')) === 5 ? post('check_out') . ':00' : post('check_out')) : null;
    $breakStart = post('break_start') ? (strlen(post('break_start')) === 5 ? post('break_start') . ':00' : post('break_start')) : null;
    $breakEnd = post('break_end') ? (strlen(post('break_end')) === 5 ? post('break_end') . ':00' : post('break_end')) : null;
    $statusOverride = post('status');
    
    // Permission Verification: Founder can edit Employee, Manager, and HR
    $userStmt = $db->prepare("SELECT id, name, role, email FROM users WHERE id = ?");
    $userStmt->execute([$targetUserId]);
    $targetUser = $userStmt->fetch();
    
    if (!$targetUser) {
        setFlash('error', 'Selected user not found.');
        redirect(BASE_URL . '/founder/attendance.php');
    }
    
    if (!in_array($targetUser['role'], [ROLE_EMPLOYEE, ROLE_MANAGER, ROLE_HR])) {
        setFlash('error', 'Access Denied: You cannot modify attendance for this role.');
        redirect(BASE_URL . '/founder/attendance.php');
    }
    
    // Calculate Working Time & Breaks
    $totalBreakSeconds = 0;
    $totalBreakTimeStr = null;
    if ($breakStart && $breakEnd) {
        $bIn = new DateTime($breakStart);
        $bOut = new DateTime($breakEnd);
        if ($bOut > $bIn) {
            $totalBreakSeconds = $bOut->getTimestamp() - $bIn->getTimestamp();
            $totalBreakTimeStr = calculateWorkingTime($breakStart, $breakEnd);
        }
    }
    
    $workingTimeStr = null;
    $computedStatus = 'absent';
    if ($checkIn && $checkOut) {
        $inTime = new DateTime($checkIn);
        $outTime = new DateTime($checkOut);
        if ($outTime > $inTime) {
            $totalSpanSeconds = $outTime->getTimestamp() - $inTime->getTimestamp();
            $netWorkingSeconds = max(0, $totalSpanSeconds - $totalBreakSeconds);
            
            $wHours = floor($netWorkingSeconds / 3600);
            $wMins = floor(($netWorkingSeconds % 3600) / 60);
            $wSecs = $netWorkingSeconds % 60;
            $workingTimeStr = sprintf('%02d:%02d:%02d', $wHours, $wMins, $wSecs);
            
            if ($netWorkingSeconds >= 21600) {
                $computedStatus = 'present';
            } elseif ($netWorkingSeconds >= 10800) {
                $computedStatus = 'half-day';
            } else {
                $computedStatus = 'absent';
            }
        }
    } elseif ($checkIn && !$checkOut) {
        $computedStatus = 'present';
    }
    
    $finalStatus = ($statusOverride && $statusOverride !== 'auto') ? $statusOverride : $computedStatus;
    
    // Upsert into attendance table
    $stmt = $db->prepare("SELECT id FROM attendance WHERE user_id = ? AND date = ?");
    $stmt->execute([$targetUserId, $attDate]);
    $existing = $stmt->fetch();
    
    if ($existing) {
        $updateStmt = $db->prepare("
            UPDATE attendance 
            SET check_in = ?, check_out = ?, break_start = ?, break_end = ?, total_break_time = ?, total_working_time = ?, status = ?
            WHERE id = ?
        ");
        $updateStmt->execute([$checkIn, $checkOut, $breakStart, $breakEnd, $totalBreakTimeStr, $workingTimeStr, $finalStatus, $existing['id']]);
    } else {
        $insertStmt = $db->prepare("
            INSERT INTO attendance (user_id, date, check_in, check_out, break_start, break_end, total_break_time, total_working_time, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $insertStmt->execute([$targetUserId, $attDate, $checkIn, $checkOut, $breakStart, $breakEnd, $totalBreakTimeStr, $workingTimeStr, $finalStatus]);
    }
    
    setFlash('success', 'Attendance record for ' . $targetUser['name'] . ' (' . ucfirst($targetUser['role']) . ') on ' . formatDate($attDate) . ' updated successfully.');
    redirect(BASE_URL . '/founder/attendance.php?date=' . urlencode($attDate));
}

// Filters
$filterDate = isset($_GET['date']) ? trim($_GET['date']) : today();
$filterRole = get('role_filter');
$filterEmployee = get('employee_id');
$filterStatus = get('status');
$page = max(1, (int)get('page', '1'));

// Base role condition
$roleIn = ['employee', 'manager', 'hr'];
if ($filterRole && in_array($filterRole, ['employee', 'manager', 'hr'])) {
    $roleIn = [$filterRole];
}
$roleInSql = "'" . implode("','", $roleIn) . "'";

// If a specific date is selected
if (!empty($filterDate)) {
    $where = ["u.role IN ({$roleInSql})", "u.status = 'active'"];
    $params = [$filterDate, $filterDate];

    if ($filterEmployee) {
        $where[] = "u.id = ?";
        $params[] = (int)$filterEmployee;
    }

    if ($filterStatus === 'present') {
        $where[] = "(a.status = 'present' OR (a.check_in IS NOT NULL AND a.status != 'half-day' AND l.id IS NULL))";
    } elseif ($filterStatus === 'leave') {
        $where[] = "l.id IS NOT NULL";
    } elseif ($filterStatus === 'paid') {
        $where[] = "(l.id IS NOT NULL AND l.leave_type = 'paid')";
    } elseif ($filterStatus === 'sick') {
        $where[] = "(l.id IS NOT NULL AND l.leave_type = 'sick')";
    } elseif ($filterStatus === 'planned' || $filterStatus === 'casual') {
        $where[] = "(l.id IS NOT NULL AND l.leave_type IN ('casual', 'unpaid'))";
    } elseif ($filterStatus === 'half-day') {
        $where[] = "a.status = 'half-day'";
    } elseif ($filterStatus === 'absent') {
        $where[] = "(a.status = 'absent' OR (a.check_in IS NULL AND l.id IS NULL))";
    }

    $whereClause = implode(' AND ', $where);

    $countQuery = "
        SELECT COUNT(*) 
        FROM users u
        LEFT JOIN attendance a ON u.id = a.user_id AND a.date = ?
        LEFT JOIN leaves l ON u.id = l.user_id AND ? BETWEEN l.start_date AND l.end_date AND l.status = 'approved'
        WHERE {$whereClause}
    ";
    $countStmt = $db->prepare($countQuery);
    $countStmt->execute($params);
    $totalRecords = (int)$countStmt->fetchColumn();

    $pagination = paginate($totalRecords, $page);

    $dataQuery = "
        SELECT u.id as user_id, u.name, u.email, u.role, u.designation, u.employee_id,
               a.id as attendance_id, COALESCE(a.date, ?) as attendance_date, 
               a.check_in, a.check_out, a.break_start, a.break_end, a.total_break_time, a.total_working_time, a.status as att_status,
               l.id as leave_id, l.leave_type, l.reason as leave_reason, l.status as leave_status
        FROM users u
        LEFT JOIN attendance a ON u.id = a.user_id AND a.date = ?
        LEFT JOIN leaves l ON u.id = l.user_id AND ? BETWEEN l.start_date AND l.end_date AND l.status = 'approved'
        WHERE {$whereClause}
        ORDER BY FIELD(u.role, 'manager', 'hr', 'employee'), u.name ASC
        LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}
    ";
    $dataParams = array_merge([$filterDate, $filterDate, $filterDate], array_slice($params, 2));
    $stmt = $db->prepare($dataQuery);
    $stmt->execute($dataParams);
    $records = $stmt->fetchAll();
} else {
    // All dates
    $where = ["u.role IN ({$roleInSql})"];
    $params = [];

    if ($filterEmployee) {
        $where[] = "a.user_id = ?";
        $params[] = (int)$filterEmployee;
    }

    if ($filterStatus === 'present') {
        $where[] = "a.status = 'present'";
    } elseif ($filterStatus === 'leave') {
        $where[] = "l.id IS NOT NULL";
    } elseif ($filterStatus === 'paid') {
        $where[] = "(l.id IS NOT NULL AND l.leave_type = 'paid')";
    } elseif ($filterStatus === 'sick') {
        $where[] = "(l.id IS NOT NULL AND l.leave_type = 'sick')";
    } elseif ($filterStatus === 'planned' || $filterStatus === 'casual') {
        $where[] = "(l.id IS NOT NULL AND l.leave_type IN ('casual', 'unpaid'))";
    } elseif ($filterStatus === 'half-day') {
        $where[] = "a.status = 'half-day'";
    } elseif ($filterStatus === 'absent') {
        $where[] = "a.status = 'absent'";
    }

    $whereClause = implode(' AND ', $where);

    $countQuery = "
        SELECT COUNT(*) 
        FROM attendance a 
        JOIN users u ON a.user_id = u.id 
        LEFT JOIN leaves l ON u.id = l.user_id AND a.date BETWEEN l.start_date AND l.end_date AND l.status = 'approved'
        WHERE {$whereClause}
    ";
    $countStmt = $db->prepare($countQuery);
    $countStmt->execute($params);
    $totalRecords = (int)$countStmt->fetchColumn();

    $pagination = paginate($totalRecords, $page);

    $dataQuery = "
        SELECT a.id as attendance_id, a.date as attendance_date, a.check_in, a.check_out, a.break_start, a.break_end, a.total_break_time, a.total_working_time, a.status as att_status,
               u.id as user_id, u.name, u.email, u.role, u.designation, u.employee_id,
               l.id as leave_id, l.leave_type, l.reason as leave_reason, l.status as leave_status
        FROM attendance a 
        JOIN users u ON a.user_id = u.id 
        LEFT JOIN leaves l ON u.id = l.user_id AND a.date BETWEEN l.start_date AND l.end_date AND l.status = 'approved'
        WHERE {$whereClause}
        ORDER BY a.date DESC, a.check_in DESC 
        LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}
    ";
    $stmt = $db->prepare($dataQuery);
    $stmt->execute($params);
    $records = $stmt->fetchAll();
}

// Get all manageable users (Employee, Manager, HR) for filter and modal
$allUsers = $db->query("SELECT id, name, role, designation FROM users WHERE role IN ('employee','manager','hr') AND status = 'active' ORDER BY FIELD(role, 'manager', 'hr', 'employee'), name ASC")->fetchAll();

$pageTitle = 'Attendance Records';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
    <div>
        <h1 class="page-title">Company Attendance Records</h1>
        <p class="page-subtitle">Master oversight & attendance management for Employees, HR & Managers</p>
    </div>
    <div>
        <button type="button" class="btn btn-primary" onclick="openNewAttendanceModal()">
            <i class="fa-solid fa-plus"></i> Manual Attendance Entry
        </button>
    </div>
</div>

<?php echo renderWorkingModuleBanner(); ?>

<!-- Filters -->
<form method="GET" class="filter-bar" style="display: flex; gap: 12px; flex-wrap: wrap; align-items: center; background: var(--color-bg-secondary); padding: 14px 18px; border-radius: var(--radius-md); border: 1px solid var(--color-border); margin-bottom: 20px;">
    <div style="display: flex; flex-direction: column; gap: 4px;">
        <label style="font-size: 11px; font-weight: 600; text-transform: uppercase; color: var(--color-text-secondary); letter-spacing: 0.5px;">Date</label>
        <input type="date" name="date" class="form-input" value="<?php echo e($filterDate); ?>" onchange="this.form.submit()" style="min-width: 160px; height: 38px;">
    </div>

    <div style="display: flex; flex-direction: column; gap: 4px;">
        <label style="font-size: 11px; font-weight: 600; text-transform: uppercase; color: var(--color-text-secondary); letter-spacing: 0.5px;">Role</label>
        <select name="role_filter" class="form-select" onchange="this.form.submit()" style="min-width: 150px; height: 38px;">
            <option value="">All Roles (Emp/Mgr/HR)</option>
            <option value="employee" <?php echo $filterRole === 'employee' ? 'selected' : ''; ?>>Employees</option>
            <option value="manager" <?php echo $filterRole === 'manager' ? 'selected' : ''; ?>>Managers</option>
            <option value="hr" <?php echo $filterRole === 'hr' ? 'selected' : ''; ?>>HR Team</option>
        </select>
    </div>

    <div style="display: flex; flex-direction: column; gap: 4px;">
        <label style="font-size: 11px; font-weight: 600; text-transform: uppercase; color: var(--color-text-secondary); letter-spacing: 0.5px;">Person</label>
        <select name="employee_id" class="form-select" onchange="this.form.submit()" style="min-width: 180px; height: 38px;">
            <option value="">All Staff</option>
            <?php foreach ($allUsers as $emp): ?>
                <?php if (!$filterRole || $emp['role'] === $filterRole): ?>
                    <option value="<?php echo $emp['id']; ?>" <?php echo $filterEmployee == $emp['id'] ? 'selected' : ''; ?>>
                        <?php echo e($emp['name']); ?> (<?php echo ucfirst(e($emp['role'])); ?>)
                    </option>
                <?php endif; ?>
            <?php endforeach; ?>
        </select>
    </div>

    <div style="display: flex; flex-direction: column; gap: 4px;">
        <label style="font-size: 11px; font-weight: 600; text-transform: uppercase; color: var(--color-text-secondary); letter-spacing: 0.5px;">Status / Attendance</label>
        <select name="status" class="form-select" onchange="this.form.submit()" style="min-width: 190px; height: 38px;">
            <option value="">All Statuses</option>
            <option value="present" <?php echo $filterStatus === 'present' ? 'selected' : ''; ?>>🟢 Present</option>
            <option value="leave" <?php echo $filterStatus === 'leave' ? 'selected' : ''; ?>>🏖️ Leave (All)</option>
            <option value="paid" <?php echo $filterStatus === 'paid' ? 'selected' : ''; ?>>⭐ Paid Leave</option>
            <option value="sick" <?php echo $filterStatus === 'sick' ? 'selected' : ''; ?>>🩺 Sick Leave</option>
            <option value="planned" <?php echo in_array($filterStatus, ['planned', 'casual']) ? 'selected' : ''; ?>>📅 Planned Leave</option>
            <option value="half-day" <?php echo $filterStatus === 'half-day' ? 'selected' : ''; ?>>⏳ Half-Day</option>
            <option value="absent" <?php echo $filterStatus === 'absent' ? 'selected' : ''; ?>>🔴 Absent</option>
        </select>
    </div>

    <?php if ($filterRole || $filterEmployee || $filterStatus || ($filterDate !== today() && !empty($filterDate)) || empty($filterDate)): ?>
        <div style="margin-top: auto; padding-bottom: 2px;">
            <a href="<?php echo BASE_URL; ?>/founder/attendance.php" class="btn btn-sm btn-outline" style="height: 38px; display: inline-flex; align-items: center; gap: 6px;">
                <i class="fa-solid fa-arrow-rotate-left"></i> Reset
            </a>
        </div>
    <?php endif; ?>
</form>

<?php if (empty($records)): ?>
    <div class="card">
        <div class="empty-state">
            <div class="empty-state-icon"><i class="fa-solid fa-clipboard-user"></i></div>
            <div class="empty-state-title">No attendance records</div>
            <div class="empty-state-text">No records found for the selected filters.</div>
        </div>
    </div>
<?php else: ?>
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>User / Staff</th>
                    <th>Role</th>
                    <th>Date</th>
                    <th>Check In</th>
                    <th>Check Out</th>
                    <th>Working Time</th>
                    <th>Status</th>
                    <th style="text-align: right;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($records as $record): ?>
                    <tr>
                        <td>
                            <div class="table-user">
                                <div class="table-user-avatar"><?php echo e(getInitials($record['name'])); ?></div>
                                <div>
                                    <div class="table-user-name"><?php echo e($record['name']); ?></div>
                                    <div class="table-user-email">
                                        <?php if (!empty($record['designation'])): ?>
                                            <?php echo e($record['designation']); ?> • 
                                        <?php endif; ?>
                                        <?php echo e($record['email']); ?>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <?php 
                            if ($record['role'] === ROLE_MANAGER) {
                                echo '<span class="badge badge-purple">Manager</span>';
                            } elseif ($record['role'] === ROLE_HR) {
                                echo '<span class="badge badge-info">HR</span>';
                            } else {
                                echo '<span class="badge badge-outline">Employee</span>';
                            }
                            ?>
                        </td>
                        <td><?php echo formatDate($record['attendance_date']); ?></td>
                        <td><?php echo !empty($record['check_in']) ? formatTime($record['check_in']) : '<span class="text-muted">—</span>'; ?></td>
                        <td><?php echo !empty($record['check_out']) ? formatTime($record['check_out']) : '<span class="text-muted">—</span>'; ?></td>
                        <td><?php echo !empty($record['total_working_time']) ? e($record['total_working_time']) : '<span class="text-muted">—</span>'; ?></td>
                        <td>
                            <?php
                            if (!empty($record['leave_id'])) {
                                $lt = strtolower($record['leave_type']);
                                if ($lt === 'sick') {
                                    echo '<span class="badge badge-purple" title="' . e($record['leave_reason'] ?? '') . '"><i class="fa-solid fa-notes-medical"></i> Sick Leave</span>';
                                } elseif ($lt === 'paid') {
                                    echo '<span class="badge badge-info" title="' . e($record['leave_reason'] ?? '') . '"><i class="fa-solid fa-award"></i> Paid Leave</span>';
                                } elseif ($lt === 'casual' || $lt === 'planned') {
                                    echo '<span class="badge badge-primary" title="' . e($record['leave_reason'] ?? '') . '"><i class="fa-solid fa-calendar-check"></i> Planned Leave</span>';
                                } else {
                                    echo '<span class="badge badge-info" title="' . e($record['leave_reason'] ?? '') . '"><i class="fa-solid fa-umbrella-beach"></i> ' . ucfirst(e($lt)) . ' Leave</span>';
                                }
                            } elseif (!empty($record['check_in'])) {
                                if ($record['att_status'] === 'half-day') {
                                    echo '<span class="badge badge-warning"><i class="fa-solid fa-hourglass-half"></i> Half-Day</span>';
                                } elseif ($record['att_status'] === 'absent') {
                                    echo '<span class="badge badge-danger"><i class="fa-solid fa-circle-xmark"></i> Absent</span>';
                                } else {
                                    echo '<span class="badge badge-success"><i class="fa-solid fa-circle-check"></i> Present</span>';
                                }
                            } else {
                                echo '<span class="badge badge-danger"><i class="fa-solid fa-circle-xmark"></i> Absent</span>';
                            }
                            ?>
                        </td>
                        <td style="text-align: right;">
                            <button type="button" class="btn btn-sm btn-outline" 
                                    onclick='openEditAttendanceModal(<?php echo json_encode([
                                        "attendance_id" => $record["attendance_id"] ?? "",
                                        "user_id" => $record["user_id"],
                                        "user_name" => $record["name"],
                                        "role" => ucfirst($record["role"]),
                                        "date" => $record["attendance_date"],
                                        "check_in" => $record["check_in"] ? substr($record["check_in"], 0, 5) : "",
                                        "check_out" => $record["check_out"] ? substr($record["check_out"], 0, 5) : "",
                                        "break_start" => $record["break_start"] ? substr($record["break_start"], 0, 5) : "",
                                        "break_end" => $record["break_end"] ? substr($record["break_end"], 0, 5) : "",
                                        "status" => $record["att_status"] ?: "auto"
                                    ]); ?>)'
                                    style="padding: 4px 10px; font-size: 12px; display: inline-flex; align-items: center; gap: 4px;">
                                <i class="fa-solid fa-pen-to-square"></i> Edit
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php
    $queryString = http_build_query(array_filter([
        'date' => $filterDate,
        'role_filter' => $filterRole,
        'employee_id' => $filterEmployee,
        'status' => $filterStatus,
    ]));
    echo renderPagination($pagination, BASE_URL . '/founder/attendance.php?' . $queryString);
    ?>
<?php endif; ?>

<!-- Founder Edit / Manual Attendance Modal -->
<div class="modal-overlay" id="editAttendanceModal">
    <div class="modal" style="max-width: 480px;">
        <div class="modal-header">
            <h3 class="modal-title" id="modal_title_text"><i class="fa-solid fa-calendar-check"></i> Edit Attendance Log</h3>
            <button type="button" class="modal-close" onclick="closeModal('editAttendanceModal')">&times;</button>
        </div>
        <form method="POST" action="">
            <?php echo csrfField(); ?>
            <input type="hidden" name="form_action" value="save_attendance">
            <input type="hidden" name="attendance_id" id="edit_attendance_id" value="">
            
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Staff Member (Employee / HR / Manager) *</label>
                    <select name="user_id" id="edit_user_id" class="form-select" required>
                        <option value="">Select Staff Member</option>
                        <?php foreach ($allUsers as $emp): ?>
                            <option value="<?php echo $emp['id']; ?>"><?php echo e($emp['name']); ?> (<?php echo ucfirst(e($emp['role'])); ?><?php echo $emp['designation'] ? ' — ' . e($emp['designation']) : ''; ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Attendance Date *</label>
                    <input type="date" name="date" id="edit_date" class="form-input" required value="<?php echo e($filterDate ?: today()); ?>">
                </div>

                <div style="background: var(--color-bg-secondary); padding: 12px; border-radius: var(--radius-md); border: 1px solid var(--color-border); margin-bottom: 14px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                        <strong style="font-size: 13px; color: var(--color-primary);"><i class="fa-solid fa-clock"></i> Shift Timings (10 AM – 5 PM)</strong>
                        <button type="button" class="btn btn-ghost btn-sm" onclick="fillStandardShift()" style="font-size: 11px; padding: 2px 8px; color: var(--color-primary); border: 1px solid var(--color-primary-bg);">
                            <i class="fa-solid fa-wand-magic-sparkles"></i> 10-to-5 Shift
                        </button>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label" style="font-size: 11px;">Check In Time</label>
                            <input type="time" name="check_in" id="edit_check_in" class="form-input">
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label" style="font-size: 11px;">Check Out Time</label>
                            <input type="time" name="check_out" id="edit_check_out" class="form-input">
                        </div>
                    </div>
                </div>

                <div style="background: var(--color-bg-secondary); padding: 12px; border-radius: var(--radius-md); border: 1px solid var(--color-border); margin-bottom: 14px;">
                    <strong style="font-size: 13px; display: block; margin-bottom: 8px; color: #d97706;"><i class="fa-solid fa-mug-hot"></i> Break Timings (1 Hour Excluded)</strong>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label" style="font-size: 11px;">Break Start</label>
                            <input type="time" name="break_start" id="edit_break_start" class="form-input">
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label" style="font-size: 11px;">Break End</label>
                            <input type="time" name="break_end" id="edit_break_end" class="form-input">
                        </div>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Attendance Status</label>
                    <select name="status" id="edit_status" class="form-select">
                        <option value="auto">Auto-Calculate (From Net Working Hours)</option>
                        <option value="present">🟢 Present (Full Day)</option>
                        <option value="half-day">⏳ Half-Day</option>
                        <option value="absent">🔴 Absent</option>
                    </select>
                    <small class="text-muted" style="display: block; margin-top: 4px; font-size: 11px;">Net Work ≥ 6h = Present | ≥ 3h = Half-Day | &lt; 3h = Absent.</small>
                </div>
            </div>

            <div class="modal-footer" style="display: flex; justify-content: flex-end; gap: 10px; padding: 12px 18px; border-top: 1px solid var(--color-border);">
                <button type="button" class="btn btn-outline" onclick="closeModal('editAttendanceModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Save Attendance</button>
            </div>
        </form>
    </div>
</div>

<script>
function openNewAttendanceModal() {
    document.getElementById('modal_title_text').innerHTML = '<i class="fa-solid fa-plus"></i> Manual Attendance Entry';
    document.getElementById('edit_attendance_id').value = '';
    document.getElementById('edit_user_id').value = '';
    document.getElementById('edit_user_id').disabled = false;
    document.getElementById('edit_date').value = '<?php echo e($filterDate ?: today()); ?>';
    document.getElementById('edit_check_in').value = '10:00';
    document.getElementById('edit_check_out').value = '17:00';
    document.getElementById('edit_break_start').value = '13:00';
    document.getElementById('edit_break_end').value = '14:00';
    document.getElementById('edit_status').value = 'auto';
    openModal('editAttendanceModal');
}

function openEditAttendanceModal(data) {
    document.getElementById('modal_title_text').innerHTML = '<i class="fa-solid fa-pen-to-square"></i> Edit Attendance: ' + data.user_name + ' (' + data.role + ')';
    document.getElementById('edit_attendance_id').value = data.attendance_id || '';
    document.getElementById('edit_user_id').value = data.user_id;
    document.getElementById('edit_date').value = data.date;
    document.getElementById('edit_check_in').value = data.check_in || '';
    document.getElementById('edit_check_out').value = data.check_out || '';
    document.getElementById('edit_break_start').value = data.break_start || '';
    document.getElementById('edit_break_end').value = data.break_end || '';
    document.getElementById('edit_status').value = data.status || 'auto';
    openModal('editAttendanceModal');
}

function fillStandardShift() {
    document.getElementById('edit_check_in').value = '10:00';
    document.getElementById('edit_check_out').value = '17:00';
    document.getElementById('edit_break_start').value = '13:00';
    document.getElementById('edit_break_end').value = '14:00';
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
