<?php
/**
 * Manager — Employee Attendance
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/middleware.php';

requireRole([ROLE_MANAGER]);

$db = getDB();
$managerId = getUserId();

// Filters
$filterDate = isset($_GET['date']) ? trim($_GET['date']) : today();
$filterEmployee = get('employee_id');
$filterStatus = get('status');
$page = max(1, (int)get('page', '1'));

// Employees under this manager
$empStmt = $db->prepare("SELECT id, name, email, designation, employee_id FROM users WHERE manager_id = ? AND status = 'active' ORDER BY name");
$empStmt->execute([$managerId]);
$myEmployees = $empStmt->fetchAll();
$myEmpIds = array_column($myEmployees, 'id');

if (empty($myEmpIds)) {
    $records = [];
    $totalRecords = 0;
    $pagination = paginate(0, 1);
} else {
    // If a specific date is selected (e.g. today or chosen date)
    if (!empty($filterDate)) {
        $where = ["u.manager_id = ?", "u.status = 'active'"];
        $params = [$filterDate, $filterDate, $managerId];

        if ($filterEmployee) {
            $where[] = "u.id = ?";
            $params[] = (int)$filterEmployee;
        }

        // Apply status filter
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

        // Count total matching
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

        // Query records
        $dataQuery = "
            SELECT u.id as user_id, u.name, u.email, u.designation, u.employee_id,
                   a.id as attendance_id, COALESCE(a.date, ?) as attendance_date, 
                   a.check_in, a.check_out, a.total_working_time, a.status as att_status,
                   l.id as leave_id, l.leave_type, l.reason as leave_reason, l.status as leave_status
            FROM users u
            LEFT JOIN attendance a ON u.id = a.user_id AND a.date = ?
            LEFT JOIN leaves l ON u.id = l.user_id AND ? BETWEEN l.start_date AND l.end_date AND l.status = 'approved'
            WHERE {$whereClause}
            ORDER BY u.name ASC
            LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}
        ";
        $dataParams = array_merge([$filterDate, $filterDate, $filterDate], array_slice($params, 2));
        $stmt = $db->prepare($dataQuery);
        $stmt->execute($dataParams);
        $records = $stmt->fetchAll();
    } else {
        // When date is empty / all dates
        $where = ["u.manager_id = ?"];
        $params = [$managerId];

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
            SELECT a.id as attendance_id, a.date as attendance_date, a.check_in, a.check_out, a.total_working_time, a.status as att_status,
                   u.id as user_id, u.name, u.email, u.designation, u.employee_id,
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
}

$pageTitle = 'Attendance';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Employee Attendance</h1>
        <p class="page-subtitle">Track your team's attendance and leave records</p>
    </div>
</div>

<!-- Filters -->
<form method="GET" class="filter-bar" style="display: flex; gap: 12px; flex-wrap: wrap; align-items: center; background: var(--color-bg-secondary); padding: 14px 18px; border-radius: var(--radius-md); border: 1px solid var(--color-border); margin-bottom: 20px;">
    <div style="display: flex; flex-direction: column; gap: 4px;">
        <label style="font-size: 11px; font-weight: 600; text-transform: uppercase; color: var(--color-text-secondary); letter-spacing: 0.5px;">Date</label>
        <input type="date" name="date" class="form-input" value="<?php echo e($filterDate); ?>" onchange="this.form.submit()" style="min-width: 160px; height: 38px;">
    </div>
    
    <div style="display: flex; flex-direction: column; gap: 4px;">
        <label style="font-size: 11px; font-weight: 600; text-transform: uppercase; color: var(--color-text-secondary); letter-spacing: 0.5px;">Employee</label>
        <select name="employee_id" class="form-select" onchange="this.form.submit()" style="min-width: 180px; height: 38px;">
            <option value="">All My Employees</option>
            <?php foreach ($myEmployees as $emp): ?>
                <option value="<?php echo $emp['id']; ?>" <?php echo $filterEmployee == $emp['id'] ? 'selected' : ''; ?>><?php echo e($emp['name']); ?></option>
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

    <?php if ($filterEmployee || $filterStatus || ($filterDate !== today() && !empty($filterDate)) || empty($filterDate)): ?>
        <div style="margin-top: auto; padding-bottom: 2px;">
            <a href="<?php echo BASE_URL; ?>/manager/attendance.php" class="btn btn-sm btn-outline" style="height: 38px; display: inline-flex; align-items: center; gap: 6px;">
                <i class="fa-solid fa-arrow-rotate-left"></i> Reset
            </a>
        </div>
    <?php endif; ?>
</form>

<?php if (empty($records)): ?>
    <div class="card">
        <div class="empty-state">
            <div class="empty-state-icon"><i class="fa-solid fa-clipboard-user"></i></div>
            <div class="empty-state-title">No records found</div>
            <div class="empty-state-text">No attendance data matches the selected filters.</div>
        </div>
    </div>
<?php else: ?>
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Employee</th>
                    <th>Date</th>
                    <th>Check In</th>
                    <th>Check Out</th>
                    <th>Working Time</th>
                    <th>Status</th>
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
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php
    $qs = http_build_query(array_filter([
        'date' => $filterDate,
        'employee_id' => $filterEmployee,
        'status' => $filterStatus,
    ]));
    echo renderPagination($pagination, BASE_URL . '/manager/attendance.php?' . $qs);
    ?>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
