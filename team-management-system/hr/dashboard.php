<?php
/**
 * HR Dashboard
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/middleware.php';

requireRole([ROLE_HR]);

$db = getDB();
$today = today();

// Active Employees count
$totalEmployees = $db->query("SELECT COUNT(*) FROM users WHERE role = 'employee' AND status = 'active'")->fetchColumn();

// Active Managers count
$totalManagers = $db->query("SELECT COUNT(*) FROM users WHERE role = 'manager' AND status = 'active'")->fetchColumn();

// Pending leaves (that HR has authority to approve/deny: employee leaves)
$pendingLeaves = $db->query("
    SELECT COUNT(*) 
    FROM leaves l
    JOIN users u ON l.user_id = u.id
    WHERE l.status = 'pending' AND u.role = 'employee'
")->fetchColumn();

// Today's attendance present
$stmt = $db->prepare("SELECT COUNT(*) FROM attendance WHERE date = ? AND check_in IS NOT NULL");
$stmt->execute([$today]);
$presentToday = $stmt->fetchColumn();

// Recent check-ins today
$stmt = $db->prepare("
    SELECT u.name, u.role, a.check_in, a.check_out 
    FROM attendance a 
    JOIN users u ON a.user_id = u.id 
    WHERE a.date = ? 
    ORDER BY a.check_in DESC 
    LIMIT 5
");
$stmt->execute([$today]);
$recentAttendance = $stmt->fetchAll();

// Recent leave applications
$recentLeaves = $db->query("
    SELECT l.*, u.name AS employee_name
    FROM leaves l
    JOIN users u ON l.user_id = u.id
    ORDER BY l.created_at DESC
    LIMIT 5
")->fetchAll();

$pageTitle = 'HR Dashboard';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Dashboard</h1>
        <p class="page-subtitle">HR Overview & Operations</p>
    </div>
</div>

<!-- Stats -->
<div class="stats-grid">
    <div class="stat-card accent-blue fade-in stagger-1">
        <div class="stat-icon bg-blue">👥</div>
        <div class="stat-content">
            <div class="stat-value"><?php echo $totalEmployees; ?></div>
            <div class="stat-label">Employees</div>
        </div>
    </div>
    <div class="stat-card accent-purple fade-in stagger-2">
        <div class="stat-icon bg-purple">👔</div>
        <div class="stat-content">
            <div class="stat-value"><?php echo $totalManagers; ?></div>
            <div class="stat-label">Managers</div>
        </div>
    </div>
    <div class="stat-card accent-green fade-in stagger-3">
        <div class="stat-icon bg-green">✅</div>
        <div class="stat-content">
            <div class="stat-value"><?php echo $presentToday; ?></div>
            <div class="stat-label">Present Today</div>
        </div>
    </div>
    <div class="stat-card accent-purple fade-in stagger-4" onclick="window.location.href='<?php echo BASE_URL; ?>/hr/leaves.php'" style="cursor: pointer;">
        <div class="stat-icon bg-purple">🌴</div>
        <div class="stat-content">
            <div class="stat-value"><?php echo $pendingLeaves; ?></div>
            <div class="stat-label">Pending Leaves</div>
        </div>
    </div>
</div>

<div class="content-grid">
    <!-- Today's Attendance -->
    <div class="card fade-in">
        <div class="card-header">
            <h3 class="card-title">Recent Check-ins Today</h3>
        </div>
        <?php if (empty($recentAttendance)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">📋</div>
                <div class="empty-state-text">No attendance logs for today yet.</div>
            </div>
        <?php else: ?>
            <div class="activity-list">
                <?php foreach ($recentAttendance as $att): ?>
                    <div class="activity-item">
                        <div class="activity-icon bg-green">✓</div>
                        <div class="activity-text">
                            <strong><?php echo e($att['name']); ?></strong> (<?php echo ucfirst(e($att['role'])); ?>) — Checked in at <?php echo formatTime($att['check_in']); ?>
                            <?php if ($att['check_out']): ?>
                                | Out at <?php echo formatTime($att['check_out']); ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Recent Leaves -->
    <div class="card fade-in">
        <div class="card-header">
            <h3 class="card-title">Recent Leave Requests</h3>
            <a href="<?php echo BASE_URL; ?>/hr/leaves.php" class="btn btn-sm btn-outline">View All</a>
        </div>
        <?php if (empty($recentLeaves)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">🌴</div>
                <div class="empty-state-text">No leave requests submitted yet.</div>
            </div>
        <?php else: ?>
            <div class="task-list">
                <?php foreach ($recentLeaves as $leave): ?>
                    <div class="task-item">
                        <div class="task-info">
                            <div class="task-title"><strong><?php echo e($leave['employee_name']); ?></strong> — <?php echo ucfirst(e($leave['leave_type'])); ?> Leave</div>
                            <div class="task-meta">
                                <span><?php echo formatDate($leave['start_date']); ?> to <?php echo formatDate($leave['end_date']); ?></span>
                            </div>
                        </div>
                        <?php if ($leave['status'] === 'pending'): ?>
                            <span class="badge badge-warning">Pending</span>
                        <?php elseif ($leave['status'] === 'approved'): ?>
                            <span class="badge badge-success">Approved</span>
                        <?php else: ?>
                            <span class="badge badge-danger">Denied</span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
