<?php
/**
 * HR — Attendance Management (Today's Attendance & Attendance History)
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/middleware.php';

requireRole([ROLE_HR]);

$db = getDB();
$today = today();
$tab = get('tab', 'today'); // 'today' or 'history'
$searchDate = get('date', $today);

// Fetch Today's Attendance for all active employees
$todayLogs = $db->prepare("
    SELECT u.id as user_id, u.employee_id, u.name, u.designation, m.name as manager_name,
           a.id as attendance_id, a.check_in, a.check_out, a.total_working_time, a.status as att_status
    FROM users u
    LEFT JOIN users m ON u.manager_id = m.id
    LEFT JOIN attendance a ON u.id = a.user_id AND a.date = ?
    WHERE u.role = 'employee' AND u.status = 'active'
    ORDER BY u.name ASC
");
$todayLogs->execute([$today]);
$todayList = $todayLogs->fetchAll();

// Calculate Today's Stats
$totalActiveEmployees = count($todayList);
$presentCount = 0;
$absentCount = 0;
$halfDayCount = 0;

foreach ($todayList as $row) {
    if (empty($row['check_in'])) {
        $absentCount++;
    } elseif ($row['att_status'] === 'half-day') {
        $halfDayCount++;
    } else {
        $presentCount++;
    }
}

// Fetch Attendance History if tab is 'history'
$historyQuery = "
    SELECT a.*, u.name as employee_name, u.employee_id, u.designation
    FROM attendance a
    JOIN users u ON a.user_id = u.id
    WHERE 1=1
";
$params = [];

if (!empty($_GET['date_from'])) {
    $historyQuery .= " AND a.date >= ?";
    $params[] = $_GET['date_from'];
}
if (!empty($_GET['date_to'])) {
    $historyQuery .= " AND a.date <= ?";
    $params[] = $_GET['date_to'];
}
if (!empty($_GET['search_emp'])) {
    $historyQuery .= " AND (u.name LIKE ? OR u.employee_id LIKE ?)";
    $params[] = '%' . $_GET['search_emp'] . '%';
    $params[] = '%' . $_GET['search_emp'] . '%';
}

$historyQuery .= " ORDER BY a.date DESC, a.check_in DESC LIMIT 100";
$stmt = $db->prepare($historyQuery);
$stmt->execute($params);
$historyList = $stmt->fetchAll();

$pageTitle = 'HR — Attendance';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Attendance Tracking</h1>
        <p class="page-subtitle">Monitor workforce daily check-ins & historical logs</p>
    </div>
</div>

<!-- Tabs Bar -->
<div class="nav-tabs" style="display: flex; gap: var(--space-2); border-bottom: 1px solid var(--color-border); margin-bottom: var(--space-6);">
    <a href="?tab=today" class="tab-item <?php echo $tab === 'today' ? 'active' : ''; ?>" style="padding: var(--space-3) var(--space-4); text-decoration: none; font-weight: 500; border-bottom: 2px solid <?php echo $tab === 'today' ? 'var(--color-primary)' : 'transparent'; ?>; color: <?php echo $tab === 'today' ? 'var(--color-primary)' : 'var(--color-text-secondary)'; ?>;">
        <i class="fa-solid fa-calendar-days"></i> Today's Attendance (<?php echo formatDate($today); ?>)
    </a>
    <a href="?tab=history" class="tab-item <?php echo $tab === 'history' ? 'active' : ''; ?>" style="padding: var(--space-3) var(--space-4); text-decoration: none; font-weight: 500; border-bottom: 2px solid <?php echo $tab === 'history' ? 'var(--color-primary)' : 'transparent'; ?>; color: <?php echo $tab === 'history' ? 'var(--color-primary)' : 'var(--color-text-secondary)'; ?>;">
        <i class="fa-solid fa-chart-pie"></i> Attendance History
    </a>
</div>

<!-- TAB 1: TODAY'S ATTENDANCE -->
<?php if ($tab === 'today'): ?>
    <!-- Summary Stats -->
    <div class="stats-grid" style="margin-bottom: 24px;">
        <div class="stat-card accent-blue">
            <div class="stat-icon bg-blue"><i class="fa-solid fa-users"></i></div>
            <div class="stat-content">
                <div class="stat-value"><?php echo $totalActiveEmployees; ?></div>
                <div class="stat-label">Total Active Employees</div>
            </div>
        </div>
        <div class="stat-card accent-green">
            <div class="stat-icon bg-green"><i class="fa-solid fa-circle-check"></i></div>
            <div class="stat-content">
                <div class="stat-value"><?php echo $presentCount; ?></div>
                <div class="stat-label">Present Today</div>
            </div>
        </div>
        <div class="stat-card accent-orange">
            <div class="stat-icon bg-warning"><i class="fa-solid fa-hourglass-half"></i></div>
            <div class="stat-content">
                <div class="stat-value"><?php echo $halfDayCount; ?></div>
                <div class="stat-label">Half Day</div>
            </div>
        </div>
        <div class="stat-card accent-red">
            <div class="stat-icon bg-red"><i class="fa-solid fa-circle-xmark"></i></div>
            <div class="stat-content">
                <div class="stat-value"><?php echo $absentCount; ?></div>
                <div class="stat-label">Not Checked In</div>
            </div>
        </div>
    </div>

    <div class="table-container fade-in">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Emp ID</th>
                    <th>Employee Name</th>
                    <th>Designation</th>
                    <th>Manager</th>
                    <th>Check In</th>
                    <th>Check Out</th>
                    <th>Working Hours</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($todayList as $row): ?>
                    <tr>
                        <td><code><?php echo e($row['employee_id']); ?></code></td>
                        <td><strong><?php echo e($row['name']); ?></strong></td>
                        <td><?php echo e($row['designation'] ?: '—'); ?></td>
                        <td><?php echo e($row['manager_name'] ?: '—'); ?></td>
                        <td><?php echo $row['check_in'] ? formatTime($row['check_in']) : '<span class="text-muted">—</span>'; ?></td>
                        <td><?php echo $row['check_out'] ? formatTime($row['check_out']) : '<span class="text-muted">—</span>'; ?></td>
                        <td><?php echo $row['total_working_time'] ? e($row['total_working_time']) : '<span class="text-muted">—</span>'; ?></td>
                        <td>
                            <?php if (!empty($row['check_in'])): ?>
                                <span class="badge badge-success">Present</span>
                            <?php else: ?>
                                <span class="badge badge-danger">Not Checked In</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

<!-- TAB 2: ATTENDANCE HISTORY -->
<?php else: ?>
    <!-- Filter Form -->
    <div class="card fade-in" style="margin-bottom: 20px;">
        <form method="GET" action="" style="display: flex; gap: 12px; flex-wrap: wrap; align-items: flex-end;">
            <input type="hidden" name="tab" value="history">
            
            <div class="form-group" style="margin-bottom: 0; flex: 1; min-width: 150px;">
                <label class="form-label">From Date</label>
                <input type="date" name="date_from" class="form-input" value="<?php echo e($_GET['date_from'] ?? ''); ?>">
            </div>
            
            <div class="form-group" style="margin-bottom: 0; flex: 1; min-width: 150px;">
                <label class="form-label">To Date</label>
                <input type="date" name="date_to" class="form-input" value="<?php echo e($_GET['date_to'] ?? ''); ?>">
            </div>
            
            <div class="form-group" style="margin-bottom: 0; flex: 1; min-width: 180px;">
                <label class="form-label">Search Employee</label>
                <input type="text" name="search_emp" class="form-input" placeholder="Name or Emp ID" value="<?php echo e($_GET['search_emp'] ?? ''); ?>">
            </div>
            
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-magnifying-glass"></i> Filter</button>
            <a href="?tab=history" class="btn btn-outline">Reset</a>
        </form>
    </div>

    <div class="table-container fade-in">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Emp ID</th>
                    <th>Employee Name</th>
                    <th>Check In</th>
                    <th>Check Out</th>
                    <th>Working Hours</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($historyList)): ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted" style="padding: 24px;">No attendance history records match your query.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($historyList as $h): ?>
                        <tr>
                            <td><strong><?php echo formatDate($h['date']); ?></strong></td>
                            <td><code><?php echo e($h['employee_id']); ?></code></td>
                            <td><?php echo e($h['employee_name']); ?></td>
                            <td><?php echo formatTime($h['check_in']); ?></td>
                            <td><?php echo $h['check_out'] ? formatTime($h['check_out']) : '—'; ?></td>
                            <td><?php echo $h['total_working_time'] ?: '—'; ?></td>
                            <td><span class="badge badge-success"><?php echo ucfirst(e($h['status'])); ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
