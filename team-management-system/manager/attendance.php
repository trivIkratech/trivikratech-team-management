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
$filterDate = get('date') ?: today();
$filterEmployee = get('employee_id');
$page = max(1, (int)get('page', '1'));

$where = ["u.manager_id = ?"];
$params = [$managerId];

if ($filterDate) {
    $where[] = "a.date = ?";
    $params[] = $filterDate;
}

if ($filterEmployee) {
    $where[] = "a.user_id = ?";
    $params[] = (int)$filterEmployee;
}

$whereClause = implode(' AND ', $where);

// Count
$countStmt = $db->prepare("SELECT COUNT(*) FROM attendance a JOIN users u ON a.user_id = u.id WHERE {$whereClause}");
$countStmt->execute($params);
$totalRecords = $countStmt->fetchColumn();
$pagination = paginate($totalRecords, $page);

// Get records
$stmt = $db->prepare("
    SELECT a.*, u.name, u.email, u.designation
    FROM attendance a 
    JOIN users u ON a.user_id = u.id 
    WHERE {$whereClause}
    ORDER BY a.date DESC, a.check_in DESC 
    LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}
");
$stmt->execute($params);
$records = $stmt->fetchAll();

// My employees for filter
$empStmt = $db->prepare("SELECT id, name FROM users WHERE manager_id = ? AND status = 'active' ORDER BY name");
$empStmt->execute([$managerId]);
$myEmployees = $empStmt->fetchAll();

$pageTitle = 'Attendance';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Employee Attendance</h1>
        <p class="page-subtitle">Track your team's attendance</p>
    </div>
</div>

<!-- Filters -->
<form method="GET" class="filter-bar">
    <input type="date" name="date" class="form-input" value="<?php echo e($filterDate); ?>" onchange="this.form.submit()">
    <select name="employee_id" class="form-select" onchange="this.form.submit()">
        <option value="">All My Employees</option>
        <?php foreach ($myEmployees as $emp): ?>
            <option value="<?php echo $emp['id']; ?>" <?php echo $filterEmployee == $emp['id'] ? 'selected' : ''; ?>><?php echo e($emp['name']); ?></option>
        <?php endforeach; ?>
    </select>
    <?php if ($filterEmployee || $filterDate !== today()): ?>
        <a href="<?php echo BASE_URL; ?>/manager/attendance.php" class="btn btn-sm btn-outline">Reset</a>
    <?php endif; ?>
</form>

<?php if (empty($records)): ?>
    <div class="card">
        <div class="empty-state">
            <div class="empty-state-icon"><i class="fa-solid fa-clipboard-user"></i></div>
            <div class="empty-state-title">No records found</div>
            <div class="empty-state-text">No attendance data for the selected filters.</div>
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
                                        <?php if ($record['designation']): ?>
                                            <?php echo e($record['designation']); ?> • 
                                        <?php endif; ?>
                                        <?php echo e($record['email']); ?>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td><?php echo formatDate($record['date']); ?></td>
                        <td><?php echo formatTime($record['check_in']); ?></td>
                        <td><?php echo formatTime($record['check_out']); ?></td>
                        <td><?php echo $record['total_working_time'] ? e($record['total_working_time']) : '—'; ?></td>
                        <td><span class="badge <?php echo attendanceStatusBadge($record['status']); ?>"><?php echo ucfirst(e($record['status'])); ?></span></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php
    $qs = http_build_query(array_filter(['date' => $filterDate, 'employee_id' => $filterEmployee]));
    echo renderPagination($pagination, BASE_URL . '/manager/attendance.php?' . $qs);
    ?>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
