<?php
/**
 * Founder — Reports / Overview
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/middleware.php';

requireRole([ROLE_FOUNDER]);

$db = getDB();
$today = today();

// --- Attendance Summary (last 7 days) ---
$stmt = $db->prepare("
    SELECT a.date, COUNT(DISTINCT a.user_id) AS present_count
    FROM attendance a 
    WHERE a.date >= DATE_SUB(?, INTERVAL 7 DAY) AND a.check_in IS NOT NULL
    GROUP BY a.date 
    ORDER BY a.date ASC
");
$stmt->execute([$today]);
$weeklyAttendance = $stmt->fetchAll();

// Total active staff
$totalStaff = $db->query("SELECT COUNT(*) FROM users WHERE role IN ('employee','manager') AND status = 'active'")->fetchColumn();

// --- Task Distribution ---
$taskStats = $db->query("
    SELECT 
        SUM(CASE WHEN status = 'todo' THEN 1 ELSE 0 END) AS todo,
        SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) AS in_progress,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed,
        COUNT(*) AS total
    FROM tasks
")->fetch();

// --- Task Completion Rate ---
$completionRate = $taskStats['total'] > 0 
    ? round(($taskStats['completed'] / $taskStats['total']) * 100) 
    : 0;

// --- Top Performers (most tasks completed) ---
$topPerformers = $db->query("
    SELECT u.name, u.role, COUNT(t.id) AS completed_count
    FROM users u
    JOIN tasks t ON t.assigned_to = u.id AND t.status = 'completed'
    WHERE u.status = 'active'
    GROUP BY u.id, u.name, u.role
    ORDER BY completed_count DESC
    LIMIT 5
")->fetchAll();

// --- Overdue Tasks by assignee ---
$stmt = $db->prepare("
    SELECT u.name, COUNT(t.id) AS overdue_count
    FROM tasks t 
    JOIN users u ON t.assigned_to = u.id
    WHERE t.status != 'completed' AND t.deadline IS NOT NULL AND t.deadline < ?
    GROUP BY u.id, u.name
    ORDER BY overdue_count DESC
    LIMIT 5
");
$stmt->execute([$today]);
$overdueByUser = $stmt->fetchAll();

// --- Monthly attendance rate ---
$stmt = $db->prepare("
    SELECT COUNT(DISTINCT user_id) * 100.0 / GREATEST(?, 1) AS avg_rate
    FROM attendance 
    WHERE MONTH(date) = MONTH(?) AND YEAR(date) = YEAR(?) AND check_in IS NOT NULL
");
$stmt->execute([$totalStaff, $today, $today]);
$monthlyAttendanceRate = round($stmt->fetchColumn(), 1);

$pageTitle = 'Reports';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Reports & Overview</h1>
        <p class="page-subtitle">Company performance insights</p>
    </div>
</div>

<!-- Summary Stats -->
<div class="stats-grid">
    <div class="stat-card accent-green fade-in stagger-1">
        <div class="stat-icon bg-green">📊</div>
        <div class="stat-content">
            <div class="stat-value"><?php echo $completionRate; ?>%</div>
            <div class="stat-label">Task Completion Rate</div>
        </div>
    </div>
    <div class="stat-card accent-blue fade-in stagger-2">
        <div class="stat-icon bg-blue">👥</div>
        <div class="stat-content">
            <div class="stat-value"><?php echo $totalStaff; ?></div>
            <div class="stat-label">Active Staff</div>
        </div>
    </div>
    <div class="stat-card accent-purple fade-in stagger-3">
        <div class="stat-icon bg-purple">📈</div>
        <div class="stat-content">
            <div class="stat-value"><?php echo $monthlyAttendanceRate; ?>%</div>
            <div class="stat-label">Monthly Attendance Rate</div>
        </div>
    </div>
    <div class="stat-card accent-cyan fade-in stagger-4">
        <div class="stat-icon bg-cyan">✅</div>
        <div class="stat-content">
            <div class="stat-value"><?php echo $taskStats['total']; ?></div>
            <div class="stat-label">Total Tasks Created</div>
        </div>
    </div>
</div>

<div class="content-grid">
    <!-- Task Distribution -->
    <div class="card fade-in">
        <div class="card-header">
            <h3 class="card-title">Task Distribution</h3>
        </div>
        <div style="display: flex; flex-direction: column; gap: var(--space-4);">
            <div>
                <div class="flex-between mb-4" style="margin-bottom: 6px;">
                    <span class="text-muted" style="font-size: var(--text-sm);">To Do</span>
                    <span style="font-size: var(--text-sm); font-weight: 600;"><?php echo $taskStats['todo']; ?></span>
                </div>
                <div style="height: 8px; background: var(--color-bg-tertiary); border-radius: 4px; overflow: hidden;">
                    <div style="height: 100%; width: <?php echo $taskStats['total'] > 0 ? ($taskStats['todo']/$taskStats['total']*100) : 0; ?>%; background: var(--color-text-muted); border-radius: 4px; transition: width 0.5s ease;"></div>
                </div>
            </div>
            <div>
                <div class="flex-between mb-4" style="margin-bottom: 6px;">
                    <span class="text-muted" style="font-size: var(--text-sm);">In Progress</span>
                    <span style="font-size: var(--text-sm); font-weight: 600;"><?php echo $taskStats['in_progress']; ?></span>
                </div>
                <div style="height: 8px; background: var(--color-bg-tertiary); border-radius: 4px; overflow: hidden;">
                    <div style="height: 100%; width: <?php echo $taskStats['total'] > 0 ? ($taskStats['in_progress']/$taskStats['total']*100) : 0; ?>%; background: var(--color-primary); border-radius: 4px; transition: width 0.5s ease;"></div>
                </div>
            </div>
            <div>
                <div class="flex-between mb-4" style="margin-bottom: 6px;">
                    <span class="text-muted" style="font-size: var(--text-sm);">Completed</span>
                    <span style="font-size: var(--text-sm); font-weight: 600;"><?php echo $taskStats['completed']; ?></span>
                </div>
                <div style="height: 8px; background: var(--color-bg-tertiary); border-radius: 4px; overflow: hidden;">
                    <div style="height: 100%; width: <?php echo $taskStats['total'] > 0 ? ($taskStats['completed']/$taskStats['total']*100) : 0; ?>%; background: var(--color-success); border-radius: 4px; transition: width 0.5s ease;"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Weekly Attendance -->
    <div class="card fade-in">
        <div class="card-header">
            <h3 class="card-title">Weekly Attendance</h3>
        </div>
        <?php if (empty($weeklyAttendance)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">📋</div>
                <div class="empty-state-text">No data for this week yet.</div>
            </div>
        <?php else: ?>
            <div style="display: flex; flex-direction: column; gap: var(--space-3);">
                <?php foreach ($weeklyAttendance as $day): ?>
                    <div class="flex-between" style="padding: var(--space-2) 0;">
                        <span style="font-size: var(--text-sm);"><?php echo formatDate($day['date'], 'D, d M'); ?></span>
                        <div class="flex gap-3" style="align-items: center;">
                            <div style="width: 120px; height: 6px; background: var(--color-bg-tertiary); border-radius: 3px; overflow: hidden;">
                                <div style="height: 100%; width: <?php echo $totalStaff > 0 ? ($day['present_count']/$totalStaff*100) : 0; ?>%; background: var(--color-success); border-radius: 3px;"></div>
                            </div>
                            <span class="badge badge-success"><?php echo $day['present_count']; ?>/<?php echo $totalStaff; ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Top Performers -->
    <div class="card fade-in">
        <div class="card-header">
            <h3 class="card-title">Top Performers</h3>
        </div>
        <?php if (empty($topPerformers)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">🏆</div>
                <div class="empty-state-text">No completed tasks yet.</div>
            </div>
        <?php else: ?>
            <div style="display: flex; flex-direction: column; gap: var(--space-3);">
                <?php foreach ($topPerformers as $i => $performer): ?>
                    <div class="flex-between" style="padding: var(--space-2) 0; border-bottom: 1px solid var(--color-border);">
                        <div class="table-user">
                            <div class="table-user-avatar"><?php echo e(getInitials($performer['name'])); ?></div>
                            <div>
                                <div class="table-user-name"><?php echo e($performer['name']); ?></div>
                                <div class="table-user-email"><?php echo ucfirst(e($performer['role'])); ?></div>
                            </div>
                        </div>
                        <span class="badge badge-success"><?php echo $performer['completed_count']; ?> done</span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Overdue Alerts -->
    <div class="card fade-in">
        <div class="card-header">
            <h3 class="card-title">Overdue Tasks by Person</h3>
        </div>
        <?php if (empty($overdueByUser)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">🎉</div>
                <div class="empty-state-title">All clear!</div>
                <div class="empty-state-text">No overdue tasks.</div>
            </div>
        <?php else: ?>
            <div style="display: flex; flex-direction: column; gap: var(--space-3);">
                <?php foreach ($overdueByUser as $item): ?>
                    <div class="flex-between" style="padding: var(--space-2) 0; border-bottom: 1px solid var(--color-border);">
                        <span style="font-size: var(--text-sm); font-weight: 500;"><?php echo e($item['name']); ?></span>
                        <span class="badge badge-danger"><?php echo $item['overdue_count']; ?> overdue</span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
