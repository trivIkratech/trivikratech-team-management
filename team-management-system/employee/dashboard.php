<?php
/**
 * Employee Dashboard — Simple & Clean
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/middleware.php';

requireRole([ROLE_EMPLOYEE]);

$db = getDB();
$userId = getUserId();
$today = today();

// Today's attendance
$stmt = $db->prepare("SELECT * FROM attendance WHERE user_id = ? AND date = ? LIMIT 1");
$stmt->execute([$userId, $today]);
$todayAttendance = $stmt->fetch();

$checkedIn = $todayAttendance && $todayAttendance['check_in'];
$checkedOut = $todayAttendance && $todayAttendance['check_out'];

// Task stats
$stmt = $db->prepare("SELECT 
    SUM(CASE WHEN status = 'todo' THEN 1 ELSE 0 END) AS todo,
    SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) AS in_progress,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed,
    COUNT(*) AS total
    FROM tasks WHERE assigned_to = ?
");
$stmt->execute([$userId]);
$taskStats = $stmt->fetch();

// Upcoming deadlines (next 7 days, not completed)
$stmt = $db->prepare("
    SELECT title, deadline, priority, status 
    FROM tasks 
    WHERE assigned_to = ? AND status != 'completed' AND deadline IS NOT NULL AND deadline >= ? AND deadline <= DATE_ADD(?, INTERVAL 7 DAY)
    ORDER BY deadline ASC
    LIMIT 5
");
$stmt->execute([$userId, $today, $today]);
$upcomingDeadlines = $stmt->fetchAll();

// Overdue tasks
$stmt = $db->prepare("SELECT COUNT(*) FROM tasks WHERE assigned_to = ? AND status != 'completed' AND deadline < ?");
$stmt->execute([$userId, $today]);
$overdueTasks = $stmt->fetchColumn();

$pageTitle = 'Employee Dashboard';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">My Dashboard</h1>
        <p class="page-subtitle">Welcome back, <?php echo e(getUserName()); ?>!</p>
    </div>
</div>

<!-- Attendance Card -->
<div class="attendance-card fade-in">
    <div class="attendance-status">
        <?php if (!$checkedIn): ?>
            ⏰
        <?php elseif (!$checkedOut): ?>
            🟢
        <?php else: ?>
            ✅
        <?php endif; ?>
    </div>
    
    <h2 style="margin-bottom: var(--space-2);">
        <?php if (!$checkedIn): ?>
            You haven't checked in yet
        <?php elseif (!$checkedOut): ?>
            You're checked in
        <?php else: ?>
            You're done for today!
        <?php endif; ?>
    </h2>
    
    <p class="text-muted" style="margin-bottom: var(--space-6);">
        <?php echo date('l, d F Y'); ?> · <span id="live-clock"></span>
    </p>
    
    <?php if (!$checkedIn): ?>
        <button id="btn-check-in" class="btn btn-checkin">🟢 Check In</button>
    <?php elseif (!$checkedOut): ?>
        <button id="btn-check-out" class="btn btn-checkout">🔴 Check Out</button>
    <?php endif; ?>
    
    <?php if ($checkedIn): ?>
        <div class="attendance-time">
            <div class="attendance-time-item">
                <div class="attendance-time-label">Check In</div>
                <div class="attendance-time-value"><?php echo formatTime($todayAttendance['check_in']); ?></div>
            </div>
            <?php if ($checkedOut): ?>
                <div class="attendance-time-item">
                    <div class="attendance-time-label">Check Out</div>
                    <div class="attendance-time-value"><?php echo formatTime($todayAttendance['check_out']); ?></div>
                </div>
                <div class="attendance-time-item">
                    <div class="attendance-time-label">Working Time</div>
                    <div class="attendance-time-value"><?php echo $todayAttendance['total_working_time'] ?: '—'; ?></div>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Task Stats -->
<div class="stats-grid">
    <div class="stat-card accent-yellow fade-in stagger-1">
        <div class="stat-icon bg-yellow">📝</div>
        <div class="stat-content">
            <div class="stat-value"><?php echo $taskStats['todo']; ?></div>
            <div class="stat-label">To Do</div>
        </div>
    </div>
    <div class="stat-card accent-blue fade-in stagger-2">
        <div class="stat-icon bg-blue">🔄</div>
        <div class="stat-content">
            <div class="stat-value"><?php echo $taskStats['in_progress']; ?></div>
            <div class="stat-label">In Progress</div>
        </div>
    </div>
    <div class="stat-card accent-green fade-in stagger-3">
        <div class="stat-icon bg-green">✅</div>
        <div class="stat-content">
            <div class="stat-value"><?php echo $taskStats['completed']; ?></div>
            <div class="stat-label">Completed</div>
        </div>
    </div>
    <div class="stat-card accent-red fade-in stagger-4">
        <div class="stat-icon bg-red">🔥</div>
        <div class="stat-content">
            <div class="stat-value"><?php echo $overdueTasks; ?></div>
            <div class="stat-label">Overdue</div>
        </div>
    </div>
</div>

<!-- Upcoming Deadlines -->
<div class="card fade-in">
    <div class="card-header">
        <h3 class="card-title">Upcoming Deadlines</h3>
        <a href="<?php echo BASE_URL; ?>/employee/tasks.php" class="btn btn-sm btn-outline">All Tasks</a>
    </div>
    <?php if (empty($upcomingDeadlines)): ?>
        <div class="empty-state">
            <div class="empty-state-icon">🎉</div>
            <div class="empty-state-title">No upcoming deadlines</div>
            <div class="empty-state-text">You're all caught up!</div>
        </div>
    <?php else: ?>
        <div class="task-list">
            <?php foreach ($upcomingDeadlines as $task): ?>
                <div class="task-item priority-<?php echo e($task['priority']); ?>">
                    <div class="task-info">
                        <div class="task-title"><?php echo e($task['title']); ?></div>
                        <div class="task-meta">
                            <span>Due: <?php echo formatDate($task['deadline']); ?></span>
                            <span>•</span>
                            <span class="badge <?php echo priorityBadge($task['priority']); ?>"><?php echo priorityLabel($task['priority']); ?></span>
                        </div>
                    </div>
                    <span class="badge <?php echo taskStatusBadge($task['status']); ?>"><?php echo taskStatusLabel($task['status']); ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
