<?php
/**
 * Manager Dashboard
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

// My employees count
$stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE manager_id = ? AND status = 'active'");
$stmt->execute([$managerId]);
$totalEmployees = $stmt->fetchColumn();

// Present today (my employees)
$stmt = $db->prepare("
    SELECT COUNT(DISTINCT a.user_id) 
    FROM attendance a 
    JOIN users u ON a.user_id = u.id 
    WHERE u.manager_id = ? AND a.date = ? AND a.check_in IS NOT NULL
");
$stmt->execute([$managerId, $today]);
$presentToday = $stmt->fetchColumn();

$absentToday = max(0, $totalEmployees - $presentToday);

// Task stats (tasks I assigned)
$stmt = $db->prepare("SELECT 
    COUNT(*) AS total,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed,
    SUM(CASE WHEN status IN ('todo','in_progress') THEN 1 ELSE 0 END) AS pending,
    SUM(CASE WHEN status != 'completed' AND deadline IS NOT NULL AND deadline < ? THEN 1 ELSE 0 END) AS overdue
    FROM tasks WHERE assigned_by = ?
");
$stmt->execute([$today, $managerId]);
$taskStats = $stmt->fetch();

// Recent tasks
$stmt = $db->prepare("
    SELECT t.*, u.name AS assigned_to_name
    FROM tasks t 
    JOIN users u ON t.assigned_to = u.id 
    WHERE t.assigned_by = ? 
    ORDER BY t.updated_at DESC 
    LIMIT 5
");
$stmt->execute([$managerId]);
$recentTasks = $stmt->fetchAll();

// Today's attendance for my team
$stmt = $db->prepare("
    SELECT u.name, a.check_in, a.check_out
    FROM users u
    LEFT JOIN attendance a ON u.id = a.user_id AND a.date = ?
    WHERE u.manager_id = ? AND u.status = 'active'
    ORDER BY a.check_in DESC
");
$stmt->execute([$today, $managerId]);
$todayAttendance = $stmt->fetchAll();

// Pending support tickets (sent to manager by their team)
$stmt = $db->prepare("
    SELECT COUNT(*) 
    FROM support_tickets t
    JOIN users u ON t.user_id = u.id
    WHERE t.status = 'pending' AND FIND_IN_SET('manager', t.send_to) > 0 AND u.manager_id = ?
");
$stmt->execute([$managerId]);
$pendingTickets = $stmt->fetchColumn();

// Pending leave requests (from team)
$stmt = $db->prepare("
    SELECT COUNT(*) 
    FROM leaves l
    JOIN users u ON l.user_id = u.id
    WHERE l.status = 'pending' AND u.manager_id = ?
");
$stmt->execute([$managerId]);
$pendingLeaves = $stmt->fetchColumn();

$pageTitle = 'Manager Dashboard';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Dashboard</h1>
        <p class="page-subtitle">Your team overview</p>
    </div>
</div>

<!-- Stats -->
<div class="stats-grid">
    <div class="stat-card accent-blue fade-in stagger-1">
        <div class="stat-icon bg-blue">👥</div>
        <div class="stat-content">
            <div class="stat-value"><?php echo $totalEmployees; ?></div>
            <div class="stat-label">My Employees</div>
        </div>
    </div>
    <div class="stat-card accent-green fade-in stagger-2">
        <div class="stat-icon bg-green">✅</div>
        <div class="stat-content">
            <div class="stat-value"><?php echo $presentToday; ?></div>
            <div class="stat-label">Present Today</div>
        </div>
    </div>
    <div class="stat-card accent-red fade-in stagger-3">
        <div class="stat-icon bg-red">❌</div>
        <div class="stat-content">
            <div class="stat-value"><?php echo $absentToday; ?></div>
            <div class="stat-label">Absent Today</div>
        </div>
    </div>
    <div class="stat-card accent-cyan fade-in stagger-4">
        <div class="stat-icon bg-cyan">📋</div>
        <div class="stat-content">
            <div class="stat-value"><?php echo $taskStats['total']; ?></div>
            <div class="stat-label">Tasks Assigned</div>
        </div>
    </div>
    <div class="stat-card accent-green fade-in stagger-5">
        <div class="stat-icon bg-green">🎯</div>
        <div class="stat-content">
            <div class="stat-value"><?php echo $taskStats['completed']; ?></div>
            <div class="stat-label">Completed</div>
        </div>
    </div>
    <div class="stat-card accent-yellow fade-in stagger-6">
        <div class="stat-icon bg-yellow">⏳</div>
        <div class="stat-content">
            <div class="stat-value"><?php echo $taskStats['pending']; ?></div>
            <div class="stat-label">Pending</div>
        </div>
    </div>
    <div class="stat-card accent-purple fade-in stagger-7" onclick="window.location.href='<?php echo BASE_URL; ?>/manager/tickets.php'" style="cursor: pointer;">
        <div class="stat-icon bg-purple">🎟️</div>
        <div class="stat-content">
            <div class="stat-value"><?php echo $pendingTickets; ?></div>
            <div class="stat-label">Pending Tickets</div>
        </div>
    </div>
    <div class="stat-card accent-purple fade-in stagger-8" onclick="window.location.href='<?php echo BASE_URL; ?>/manager/leaves.php'" style="cursor: pointer;">
        <div class="stat-icon bg-purple">🌴</div>
        <div class="stat-content">
            <div class="stat-value"><?php echo $pendingLeaves; ?></div>
            <div class="stat-label">Pending Leaves</div>
        </div>
    </div>
</div>

<div class="content-grid">
    <!-- Today's Team Attendance -->
    <div class="card fade-in">
        <div class="card-header">
            <h3 class="card-title">Today's Attendance</h3>
            <a href="<?php echo BASE_URL; ?>/manager/attendance.php" class="btn btn-sm btn-outline">View All</a>
        </div>
        <?php if (empty($todayAttendance)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">👥</div>
                <div class="empty-state-text">No employees assigned to you yet.</div>
            </div>
        <?php else: ?>
            <div class="activity-list">
                <?php foreach ($todayAttendance as $att): ?>
                    <div class="activity-item">
                        <div class="activity-icon <?php echo $att['check_in'] ? 'bg-green' : 'bg-red'; ?>">
                            <?php echo $att['check_in'] ? '✓' : '✕'; ?>
                        </div>
                        <div class="activity-text">
                            <strong><?php echo e($att['name']); ?></strong>
                            <?php if ($att['check_in']): ?>
                                — In: <?php echo formatTime($att['check_in']); ?>
                                <?php if ($att['check_out']): ?>
                                    | Out: <?php echo formatTime($att['check_out']); ?>
                                <?php endif; ?>
                            <?php else: ?>
                                — <span class="text-danger">Not checked in</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Recent Tasks -->
    <div class="card fade-in">
        <div class="card-header">
            <h3 class="card-title">Recent Tasks</h3>
            <a href="<?php echo BASE_URL; ?>/manager/tasks.php" class="btn btn-sm btn-outline">View All</a>
        </div>
        <?php if (empty($recentTasks)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">✅</div>
                <div class="empty-state-text">No tasks created yet.</div>
            </div>
        <?php else: ?>
            <div class="task-list">
                <?php foreach ($recentTasks as $task): ?>
                    <div class="task-item priority-<?php echo e($task['priority']); ?>">
                        <div class="task-info">
                            <div class="task-title"><?php echo e($task['title']); ?></div>
                            <div class="task-meta">
                                <span><?php echo e($task['assigned_to_name']); ?></span>
                                <span>•</span>
                                <span><?php echo timeAgo($task['created_at']); ?></span>
                            </div>
                        </div>
                        <span class="badge <?php echo taskStatusBadge($task['status']); ?>"><?php echo taskStatusLabel($task['status']); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
