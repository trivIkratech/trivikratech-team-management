<?php
/**
 * Employee — My Attendance History
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/middleware.php';

requireRole([ROLE_EMPLOYEE]);

$db = getDB();
$userId = getUserId();

// Filter
$filterMonth = get('month') ?: date('Y-m');
$page = max(1, (int)get('page', '1'));

// Count total records for this month
$stmt = $db->prepare("SELECT COUNT(*) FROM attendance WHERE user_id = ? AND DATE_FORMAT(date, '%Y-%m') = ?");
$stmt->execute([$userId, $filterMonth]);
$totalRecords = $stmt->fetchColumn();
$pagination = paginate($totalRecords, $page);

// Get records
$stmt = $db->prepare("
    SELECT * FROM attendance 
    WHERE user_id = ? AND DATE_FORMAT(date, '%Y-%m') = ?
    ORDER BY date DESC 
    LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}
");
$stmt->execute([$userId, $filterMonth]);
$records = $stmt->fetchAll();

// Monthly summary
$stmt = $db->prepare("
    SELECT 
        COUNT(*) AS total_days,
        SUM(CASE WHEN check_in IS NOT NULL THEN 1 ELSE 0 END) AS present_days,
        SEC_TO_TIME(AVG(TIME_TO_SEC(total_working_time))) AS avg_working_time
    FROM attendance 
    WHERE user_id = ? AND DATE_FORMAT(date, '%Y-%m') = ?
");
$stmt->execute([$userId, $filterMonth]);
$summary = $stmt->fetch();

$pageTitle = 'My Attendance';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">My Attendance</h1>
        <p class="page-subtitle">Your personal attendance history</p>
    </div>
</div>

<!-- Month Filter -->
<form method="GET" class="filter-bar">
    <input type="month" name="month" class="form-input" value="<?php echo e($filterMonth); ?>" onchange="this.form.submit()">
    <?php if ($filterMonth !== date('Y-m')): ?>
        <a href="<?php echo BASE_URL; ?>/employee/attendance.php" class="btn btn-sm btn-outline">Current Month</a>
    <?php endif; ?>
</form>

<!-- Monthly Summary -->
<div class="stats-grid" style="margin-bottom: var(--space-6);">
    <div class="stat-card accent-green fade-in stagger-1">
        <div class="stat-icon bg-green">✅</div>
        <div class="stat-content">
            <div class="stat-value"><?php echo $summary['present_days'] ?? 0; ?></div>
            <div class="stat-label">Days Present</div>
        </div>
    </div>
    <div class="stat-card accent-blue fade-in stagger-2">
        <div class="stat-icon bg-blue">📊</div>
        <div class="stat-content">
            <div class="stat-value"><?php echo $summary['total_days'] ?? 0; ?></div>
            <div class="stat-label">Total Records</div>
        </div>
    </div>
    <div class="stat-card accent-purple fade-in stagger-3">
        <div class="stat-icon bg-purple">⏱️</div>
        <div class="stat-content">
            <div class="stat-value"><?php echo $summary['avg_working_time'] ? substr($summary['avg_working_time'], 0, 5) : '—'; ?></div>
            <div class="stat-label">Avg Working Time</div>
        </div>
    </div>
</div>

<?php if (empty($records)): ?>
    <div class="card">
        <div class="empty-state">
            <div class="empty-state-icon">📋</div>
            <div class="empty-state-title">No attendance records</div>
            <div class="empty-state-text">No records found for <?php echo date('F Y', strtotime($filterMonth . '-01')); ?>.</div>
        </div>
    </div>
<?php else: ?>
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
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
                            <strong><?php echo formatDate($record['date'], 'D, d M Y'); ?></strong>
                        </td>
                        <td><?php echo formatTime($record['check_in']); ?></td>
                        <td><?php echo formatTime($record['check_out']); ?></td>
                        <td><?php echo $record['total_working_time'] ?: '—'; ?></td>
                        <td><span class="badge <?php echo attendanceStatusBadge($record['status']); ?>"><?php echo ucfirst(e($record['status'])); ?></span></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php
    echo renderPagination($pagination, BASE_URL . '/employee/attendance.php?month=' . urlencode($filterMonth));
    ?>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
