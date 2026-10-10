<?php
/**
 * Sales Counsellor — My Attendance History & Clock In/Out
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/middleware.php';

requireRole([ROLE_SALES_COUNSELLOR]);

$db = getDB();
$userId = getUserId();
$today = today();

// Check-in status today
$stmt = $db->prepare("SELECT * FROM attendance WHERE user_id = ? AND date = ? LIMIT 1");
$stmt->execute([$userId, $today]);
$todayAttendance = $stmt->fetch();
$checkedIn = $todayAttendance && $todayAttendance['check_in'];
$checkedOut = $todayAttendance && $todayAttendance['check_out'];

// Filter
$filterMonth = get('month') ?: date('Y-m');
$page = max(1, (int)get('page', '1'));

// Count total records for this month
$stmt = $db->prepare("SELECT COUNT(*) FROM attendance WHERE user_id = ? AND DATE_FORMAT(date, '%Y-%m') = ?");
$stmt->execute([$userId, $filterMonth]);
$totalRecords = (int)$stmt->fetchColumn();
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

$pageTitle = 'Sales Counsellor Attendance';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title"><i class="fa-solid fa-clipboard-user" style="color: var(--color-primary); margin-right: 8px;"></i>My Attendance</h1>
        <p class="page-subtitle">Your personal working hours and daily shift logs</p>
    </div>
</div>

<?php echo renderWorkingModuleBanner(); ?>

<!-- Today's Clock In / Out Action Widget -->
<div class="card fade-in" style="margin-bottom: var(--space-6);">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: var(--space-4);">
        <div>
            <h3 style="margin: 0 0 4px 0; font-size: 16px; font-weight: 600;">Today's Shift: <?php echo date('l, d F Y'); ?></h3>
            <div style="font-size: 13px; color: var(--color-text-secondary);">
                <?php if (!$checkedIn): ?>
                    <span style="color: #f59e0b;"><i class="fa-solid fa-clock"></i> Not checked in today</span>
                <?php elseif ($checkedIn && !$checkedOut): ?>
                    <span style="color: #10b981;"><i class="fa-solid fa-circle-check"></i> Checked in at <?php echo date('h:i A', strtotime($todayAttendance['check_in'])); ?></span>
                <?php else: ?>
                    <span style="color: var(--color-primary);"><i class="fa-solid fa-check-double"></i> Shift Completed (Total: <?php echo e($todayAttendance['total_working_hours'] ?? '—'); ?> hrs)</span>
                <?php endif; ?>
            </div>
        </div>
        <div>
            <?php if (!$checkedIn): ?>
                <button type="button" class="btn btn-primary" onclick="performAttendance('check_in')">
                    <i class="fa-solid fa-right-to-bracket"></i> Clock In Now
                </button>
            <?php elseif ($checkedIn && !$checkedOut): ?>
                <button type="button" class="btn btn-danger" onclick="performAttendance('check_out')">
                    <i class="fa-solid fa-right-from-bracket"></i> Clock Out Now
                </button>
            <?php else: ?>
                <button type="button" class="btn btn-secondary" disabled>
                    <i class="fa-solid fa-lock"></i> Day Completed
                </button>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Monthly Summary Cards -->
<div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: var(--space-4); margin-bottom: var(--space-6);">
    <div class="card stat-card fade-in">
        <div class="stat-icon" style="background: rgba(99, 102, 241, 0.15); color: var(--color-primary);">
            <i class="fa-solid fa-calendar-days"></i>
        </div>
        <div class="stat-info">
            <div class="stat-value"><?php echo $summary['present_days'] ?? 0; ?></div>
            <div class="stat-label">Days Present This Month</div>
        </div>
    </div>
    <div class="card stat-card fade-in">
        <div class="stat-icon" style="background: rgba(16, 185, 129, 0.15); color: #10b981;">
            <i class="fa-solid fa-clock"></i>
        </div>
        <div class="stat-info">
            <div class="stat-value"><?php echo !empty($summary['avg_working_time']) ? substr($summary['avg_working_time'], 0, 5) : '00:00'; ?></div>
            <div class="stat-label">Avg Daily Hours</div>
        </div>
    </div>
</div>

<!-- History Table -->
<div class="card fade-in" style="padding: 0; overflow: hidden; border: 1px solid var(--color-border);">
    <div style="display: flex; justify-content: space-between; align-items: center; padding: 16px 20px; border-bottom: 1px solid var(--color-border); background: var(--color-bg-secondary); flex-wrap: wrap; gap: 12px;">
        <div style="display: flex; align-items: center; gap: 10px;">
            <div style="width: 34px; height: 34px; border-radius: var(--radius-md); background: rgba(99, 102, 241, 0.15); display: flex; align-items: center; justify-content: center; color: var(--color-primary);">
                <i class="fa-solid fa-list-ol"></i>
            </div>
            <h3 style="margin: 0; font-size: 15px; font-weight: 700; color: var(--color-text-white);">Monthly Log History</h3>
        </div>
        <form method="GET" style="display: flex; gap: 8px;">
            <input type="month" name="month" class="form-input" style="font-size: 12px; padding: 6px 12px; border-radius: var(--radius-md);" value="<?php echo e($filterMonth); ?>" onchange="this.form.submit()">
        </form>
    </div>

    <?php if (empty($records)): ?>
        <p style="text-align: center; color: var(--color-text-muted); padding: 40px 20px; margin: 0;">No attendance logs found for this month.</p>
    <?php else: ?>
        <div class="data-table-container" style="border: none; border-radius: 0; margin: 0;">
            <table class="data-table" style="width: 100%;">
                <thead>
                    <tr>
                        <th style="padding-left: 20px;">Date</th>
                        <th>Clock In</th>
                        <th>Clock Out</th>
                        <th>Total Break</th>
                        <th>Net Working Hours</th>
                        <th style="padding-right: 20px;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($records as $r): ?>
                        <tr>
                            <td style="padding-left: 20px;"><strong><?php echo formatDate($r['date'], 'd M Y, D'); ?></strong></td>
                            <td><?php echo $r['check_in'] ? date('h:i A', strtotime($r['check_in'])) : '—'; ?></td>
                            <td><?php echo $r['check_out'] ? date('h:i A', strtotime($r['check_out'])) : '—'; ?></td>
                            <td><?php echo $r['total_break_time'] ?? '—'; ?></td>
                            <td><strong style="color: var(--color-primary-light);"><?php echo $r['total_working_hours'] ?? '—'; ?> hrs</strong></td>
                            <td style="padding-right: 20px;">
                                <?php if ($r['status'] === 'present'): ?>
                                    <span class="badge badge-success">Present</span>
                                <?php elseif ($r['status'] === 'half-day'): ?>
                                    <span class="badge badge-warning">Half Day</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">Absent</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<script>
function performAttendance(actionType) {
    if (!confirm('Are you sure you want to ' + actionType.replace('_', ' ') + '?')) return;

    const formData = new FormData();
    formData.append('action', actionType);
    formData.append('csrf_token', '<?php echo csrfToken(); ?>');

    fetch('<?php echo BASE_URL; ?>/api/attendance.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            window.location.reload();
        } else {
            alert(data.message || 'Error occurred.');
        }
    })
    .catch(err => alert('Request failed.'));
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
