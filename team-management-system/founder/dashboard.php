<?php
/**
 * Founder Dashboard
 * 
 * Shows company-wide summary: employees, attendance, tasks, recent activity.
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/middleware.php';

requireRole([ROLE_FOUNDER]);

$db = getDB();
$today = today();

// --- Stats Queries ---
// Total Employees (active, role=employee)
$stmt = $db->query("SELECT COUNT(*) FROM users WHERE role = 'employee' AND status = 'active'");
$totalEmployees = $stmt->fetchColumn();

// Total Managers (active)
$stmt = $db->query("SELECT COUNT(*) FROM users WHERE role = 'manager' AND status = 'active'");
$totalManagers = $stmt->fetchColumn();

// Present Today
$stmt = $db->prepare("SELECT COUNT(*) FROM attendance WHERE date = ? AND check_in IS NOT NULL");
$stmt->execute([$today]);
$presentToday = $stmt->fetchColumn();

// Total active staff (employees + managers) for absent calculation
$stmt = $db->query("SELECT COUNT(*) FROM users WHERE role IN ('employee','manager') AND status = 'active'");
$totalStaff = $stmt->fetchColumn();
$absentToday = max(0, $totalStaff - $presentToday);

// Total Tasks
$stmt = $db->query("SELECT COUNT(*) FROM tasks");
$totalTasks = $stmt->fetchColumn();

// Completed Tasks
$stmt = $db->query("SELECT COUNT(*) FROM tasks WHERE status = 'completed'");
$completedTasks = $stmt->fetchColumn();

// Pending Tasks (todo + in_progress)
$stmt = $db->query("SELECT COUNT(*) FROM tasks WHERE status IN ('todo', 'in_progress')");
$pendingTasks = $stmt->fetchColumn();

// Overdue Tasks
$stmt = $db->prepare("SELECT COUNT(*) FROM tasks WHERE status != 'completed' AND deadline IS NOT NULL AND deadline < ?");
$stmt->execute([$today]);
$overdueTasks = $stmt->fetchColumn();

// Pending Support Tickets
$stmt = $db->query("SELECT COUNT(*) FROM support_tickets WHERE status = 'pending' AND (FIND_IN_SET('founder', send_to) > 0 OR FIND_IN_SET('hr', send_to) > 0)");
$pendingTickets = $stmt->fetchColumn();

// Pending Leave Requests
$stmt = $db->query("SELECT COUNT(*) FROM leaves WHERE status = 'pending'");
$pendingLeaves = $stmt->fetchColumn();

// --- Recent Activity ---
// Recent check-ins today
$stmt = $db->prepare("
    SELECT u.name, a.check_in, a.check_out, a.date 
    FROM attendance a 
    JOIN users u ON a.user_id = u.id 
    WHERE a.date = ? 
    ORDER BY a.check_in DESC 
    LIMIT 5
");
$stmt->execute([$today]);
$recentAttendance = $stmt->fetchAll();

// Recent tasks (created or updated)
$stmt = $db->query("
    SELECT t.title, t.status, t.priority, t.created_at, 
           u1.name AS assigned_to_name, u2.name AS assigned_by_name
    FROM tasks t 
    JOIN users u1 ON t.assigned_to = u1.id 
    JOIN users u2 ON t.assigned_by = u2.id 
    ORDER BY t.updated_at DESC 
    LIMIT 5
");
$recentTasks = $stmt->fetchAll();

$pageTitle = 'Founder Dashboard';
include __DIR__ . '/../includes/header.php';
?>

<!-- Page Header -->
<div class="page-header">
    <div>
        <h1 class="page-title">Dashboard</h1>
        <p class="page-subtitle">Company overview and quick insights</p>
    </div>
</div>

<!-- Stats Grid -->
<div class="stats-grid">
    <div class="stat-card accent-blue fade-in stagger-1">
        <div class="stat-icon bg-blue"><i class="fa-solid fa-users"></i></div>
        <div class="stat-content">
            <div class="stat-value"><?php echo $totalEmployees; ?></div>
            <div class="stat-label">Total Employees</div>
        </div>
    </div>

    <div class="stat-card accent-purple fade-in stagger-2">
        <div class="stat-icon bg-purple"><i class="fa-solid fa-user-tie"></i></div>
        <div class="stat-content">
            <div class="stat-value"><?php echo $totalManagers; ?></div>
            <div class="stat-label">Total Managers</div>
        </div>
    </div>

    <div class="stat-card accent-green fade-in stagger-3">
        <div class="stat-icon bg-green"><i class="fa-solid fa-circle-check"></i></div>
        <div class="stat-content">
            <div class="stat-value"><?php echo $presentToday; ?></div>
            <div class="stat-label">Present Today</div>
        </div>
    </div>

    <div class="stat-card accent-red fade-in stagger-4">
        <div class="stat-icon bg-red"><i class="fa-solid fa-circle-xmark"></i></div>
        <div class="stat-content">
            <div class="stat-value"><?php echo $absentToday; ?></div>
            <div class="stat-label">Absent Today</div>
        </div>
    </div>

    <div class="stat-card accent-cyan fade-in stagger-5">
        <div class="stat-icon bg-cyan"><i class="fa-solid fa-clipboard-user"></i></div>
        <div class="stat-content">
            <div class="stat-value"><?php echo $totalTasks; ?></div>
            <div class="stat-label">Total Tasks</div>
        </div>
    </div>

    <div class="stat-card accent-green fade-in stagger-6">
        <div class="stat-icon bg-green"><i class="fa-solid fa-bullseye"></i></div>
        <div class="stat-content">
            <div class="stat-value"><?php echo $completedTasks; ?></div>
            <div class="stat-label">Completed Tasks</div>
        </div>
    </div>

    <div class="stat-card accent-yellow fade-in stagger-7">
        <div class="stat-icon bg-yellow"><i class="fa-solid fa-hourglass-half"></i></div>
        <div class="stat-content">
            <div class="stat-value"><?php echo $pendingTasks; ?></div>
            <div class="stat-label">Pending Tasks</div>
        </div>
    </div>

    <div class="stat-card accent-red fade-in stagger-8">
        <div class="stat-icon bg-red"><i class="fa-solid fa-fire"></i></div>
        <div class="stat-content">
            <div class="stat-value"><?php echo $overdueTasks; ?></div>
            <div class="stat-label">Overdue Tasks</div>
        </div>
    </div>

    <div class="stat-card accent-purple fade-in stagger-9" onclick="window.location.href='<?php echo BASE_URL; ?>/founder/tickets.php'" style="cursor: pointer;">
        <div class="stat-icon bg-purple"><i class="fa-solid fa-headset"></i></div>
        <div class="stat-content">
            <div class="stat-value"><?php echo $pendingTickets; ?></div>
            <div class="stat-label">Pending Tickets</div>
        </div>
    </div>

    <div class="stat-card accent-purple fade-in stagger-10" onclick="window.location.href='<?php echo BASE_URL; ?>/founder/leaves.php'" style="cursor: pointer;">
        <div class="stat-icon bg-purple"><i class="fa-solid fa-umbrella-beach"></i></div>
        <div class="stat-content">
            <div class="stat-value"><?php echo $pendingLeaves; ?></div>
            <div class="stat-label">Pending Leaves</div>
        </div>
    </div>
</div>

<!-- Content Grid: Recent Activity -->
<div class="content-grid">
    <!-- Today's Attendance -->
    <div class="card fade-in">
        <div class="card-header">
            <h3 class="card-title">Today's Attendance</h3>
            <a href="<?php echo BASE_URL; ?>/founder/attendance.php" class="btn btn-sm btn-outline">View All</a>
        </div>
        <?php if (empty($recentAttendance)): ?>
            <div class="empty-state">
                <div class="empty-state-icon"><i class="fa-solid fa-clipboard-user"></i></div>
                <div class="empty-state-title">No attendance recorded</div>
                <div class="empty-state-text">No one has checked in today yet.</div>
            </div>
        <?php else: ?>
            <div class="activity-list">
                <?php foreach ($recentAttendance as $record): ?>
                    <div class="activity-item">
                        <div class="activity-icon bg-green"><i class="fa-solid fa-check"></i></div>
                        <div class="activity-text">
                            <strong><?php echo e($record['name']); ?></strong> checked in at <?php echo formatTime($record['check_in']); ?>
                            <?php if ($record['check_out']): ?>
                                — out at <?php echo formatTime($record['check_out']); ?>
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
            <a href="<?php echo BASE_URL; ?>/founder/tasks.php" class="btn btn-sm btn-outline">View All</a>
        </div>
        <?php if (empty($recentTasks)): ?>
            <div class="empty-state">
                <div class="empty-state-icon"><i class="fa-solid fa-circle-check"></i></div>
                <div class="empty-state-title">No tasks yet</div>
                <div class="empty-state-text">Tasks will appear here once created.</div>
            </div>
        <?php else: ?>
            <div class="task-list">
                <?php foreach ($recentTasks as $task): ?>
                    <div class="task-item priority-<?php echo e($task['priority']); ?>">
                        <div class="task-info">
                            <div class="task-title"><?php echo e($task['title']); ?></div>
                            <div class="task-meta">
                                <span>Assigned to: <?php echo e($task['assigned_to_name']); ?></span>
                                <span>•</span>
                                <span><?php echo timeAgo($task['created_at']); ?></span>
                            </div>
                        </div>
                        <div class="task-actions">
                            <span class="badge <?php echo taskStatusBadge($task['status']); ?>"><?php echo taskStatusLabel($task['status']); ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
