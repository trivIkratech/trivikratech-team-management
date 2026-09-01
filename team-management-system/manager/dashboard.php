<?php
/**
 * Manager Dashboard
 * 
 * Includes Manager overview stats, team monitoring, and Manager's own attendance/shift timer.
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

// Manager's own attendance today
$stmt = $db->prepare("SELECT * FROM attendance WHERE user_id = ? AND date = ? LIMIT 1");
$stmt->execute([$managerId, $today]);
$myAttendance = $stmt->fetch();

$checkedIn = $myAttendance && $myAttendance['check_in'];
$checkedOut = $myAttendance && $myAttendance['check_out'];

$checkInTimestamp = ($checkedIn && !empty($myAttendance['check_in'])) ? strtotime($today . ' ' . $myAttendance['check_in']) : 0;
$breakStartTimestamp = (!empty($myAttendance['break_start'])) ? strtotime($today . ' ' . $myAttendance['break_start']) : 0;
$breakEndTimestamp = (!empty($myAttendance['break_end'])) ? strtotime($today . ' ' . $myAttendance['break_end']) : 0;
$totalBreakSec = 0;
if (!empty($myAttendance['total_break_time'])) {
    $bParts = explode(':', $myAttendance['total_break_time']);
    if (count($bParts) >= 2) {
        $totalBreakSec = ((int)$bParts[0] * 3600) + ((int)$bParts[1] * 60) + ((int)($bParts[2] ?? 0));
    }
}

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

// Task stats (tasks assigned to me OR assigned by me)
$stmt = $db->prepare("SELECT 
    COUNT(*) AS total,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed,
    SUM(CASE WHEN status IN ('todo','in_progress') THEN 1 ELSE 0 END) AS pending,
    SUM(CASE WHEN status != 'completed' AND deadline IS NOT NULL AND deadline < ? THEN 1 ELSE 0 END) AS overdue
    FROM tasks WHERE assigned_to = ? OR assigned_by = ?
");
$stmt->execute([$today, $managerId, $managerId]);
$taskStats = $stmt->fetch();

// Recent tasks
$stmt = $db->prepare("
    SELECT t.*, u.name AS assigned_to_name
    FROM tasks t 
    JOIN users u ON t.assigned_to = u.id 
    WHERE t.assigned_to = ? OR t.assigned_by = ? 
    ORDER BY t.updated_at DESC 
    LIMIT 5
");
$stmt->execute([$managerId, $managerId]);
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
$teamAttendance = $stmt->fetchAll();

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
        <h1 class="page-title">Manager Dashboard</h1>
        <p class="page-subtitle">Welcome back, <?php echo e(getUserName()); ?>!</p>
    </div>
</div>

<!-- My Daily Attendance Card for Manager -->
<div class="attendance-card fade-in" style="margin-bottom: 24px;">
    <div class="attendance-status">
        <?php if (!$checkedIn): ?><i class="fa-solid fa-clock"></i><?php elseif (!$checkedOut): ?><i class="fa-solid fa-circle" style="color: var(--color-success);"></i><?php else: ?><i class="fa-solid fa-circle-check"></i><?php endif; ?>
    </div>
    
    <h2 style="margin-bottom: var(--space-2);">
        <?php if (!$checkedIn): ?>
            You haven't logged in yet
        <?php elseif (!$checkedOut): ?>
            <?php if (!empty($myAttendance['break_start']) && empty($myAttendance['break_end'])): ?>
                On Break <i class="fa-solid fa-mug-hot"></i> (Work Timer Paused)
            <?php else: ?>
                Logged In & Working <i class="fa-solid fa-briefcase"></i> (Timer Active)
            <?php endif; ?>
        <?php else: ?>
            You're done for today!
        <?php endif; ?>
    </h2>
    
    <p class="text-muted" style="margin-bottom: var(--space-4);">
        <?php echo date('l, d F Y'); ?> · <span id="live-clock"></span>
    </p>

    <!-- Shift Info Banner -->
    <div style="background: var(--color-bg-secondary); border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: 10px 16px; max-width: 540px; margin: 0 auto 20px; font-size: 13px; text-align: left;">
        <div style="font-weight: 600; margin-bottom: 4px; display: flex; justify-content: space-between; align-items: center;">
            <span><i class="fa-solid fa-stopwatch"></i> Manager Shift Requirement: 7-Hour Total</span>
            <span class="badge badge-info" style="font-size: 11px;">6h Net Work + 1h Break</span>
        </div>
        <div style="display: flex; gap: 14px; color: var(--color-text-secondary); font-size: 12px; margin-top: 6px; flex-wrap: wrap;">
            <span>1st Half: <strong>3h</strong> (Half Day)</span>
            <span>Break: <strong>1h</strong> (Not counted)</span>
            <span>2nd Half: <strong>3h</strong> (Half Day)</span>
            <span>Total: <strong>6h</strong> (Full Day)</span>
        </div>
    </div>

    <!-- Live 6-Hour Shift Timer & Break Widget -->
    <?php if ($checkedIn && !$checkedOut): ?>
        <div id="shiftTimerWidget" 
             data-checked-in="1"
             data-checked-out="0"
             data-check-in-ts="<?php echo $checkInTimestamp; ?>"
             data-break-start-ts="<?php echo $breakStartTimestamp; ?>"
             data-break-end-ts="<?php echo $breakEndTimestamp; ?>"
             data-total-break-sec="<?php echo $totalBreakSec; ?>"
             style="background: var(--color-bg-tertiary); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: 18px 24px; max-width: 540px; margin: 0 auto 24px; text-align: center;">
            
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; flex-wrap: wrap; gap: 10px;">
                <span style="font-size: 13px; font-weight: 600; color: var(--color-text-secondary); display: flex; align-items: center; gap: 6px;">
                    <span id="timer-status-icon"><i class="fa-solid fa-stopwatch"></i></span> <span id="timer-status-text">Working Timer (6-Hour Goal)</span>
                </span>
                <span id="timer-badge" class="badge badge-primary" style="font-size: 12px;">Active</span>
            </div>

            <div style="display: flex; justify-content: center; align-items: baseline; gap: 12px; margin-bottom: 12px;">
                <div style="font-size: 40px; font-weight: 800; font-family: monospace; letter-spacing: 2px; color: var(--color-primary);" id="timer-work-display">
                    00:00:00
                </div>
                <div style="font-size: 14px; color: var(--color-text-muted);">
                    / 06:00:00
                </div>
            </div>

            <div style="background: var(--color-bg-secondary); border-radius: 20px; height: 12px; width: 100%; overflow: hidden; margin-bottom: 12px; position: relative;">
                <div id="timer-progress-bar" style="background: linear-gradient(90deg, #4f6ef7, #10b981); height: 100%; width: 0%; transition: width 0.5s ease; border-radius: 20px;"></div>
            </div>

            <div style="display: flex; justify-content: space-between; font-size: 12px; color: var(--color-text-muted);">
                <span>Remaining for Full Day: <strong id="timer-rem-display" style="color: var(--color-text-main);">06:00:00</strong></span>
                <span>Progress: <strong id="timer-progress-pct" style="color: var(--color-success);">0%</strong></span>
            </div>

            <div id="timer-break-banner" style="display: none; margin-top: 14px; padding: 10px; background: rgba(217, 119, 6, 0.15); border: 1px solid #d97706; border-radius: 8px; font-size: 13px; color: #fde68a;">
                <i class="fa-solid fa-mug-hot"></i> <strong>On Break (Working Timer Paused)</strong> · Break Time: <strong id="timer-break-display">00m 00s</strong> (Max 1h)
            </div>
        </div>
    <?php endif; ?>
    
    <!-- Action Buttons -->
    <div style="display: flex; gap: 12px; justify-content: center; align-items: center; flex-wrap: wrap; margin-bottom: 20px;">
        <?php if (!$checkedIn): ?>
            <button id="btn-check-in" class="btn btn-checkin" style="min-width: 150px; font-size: 15px; padding: 12px 24px;"><i class="fa-solid fa-circle" style="color: var(--color-success);"></i> Login / Check In</button>
        <?php elseif (!$checkedOut): ?>
            <?php if (empty($myAttendance['break_start'])): ?>
                <button id="btn-start-break" class="btn btn-warning" style="min-width: 150px; font-size: 14px; padding: 10px 20px; background: #d97706; color: #fff;"><i class="fa-solid fa-mug-hot"></i> Start Break (1h)</button>
            <?php elseif (empty($myAttendance['break_end'])): ?>
                <button id="btn-end-break" class="btn btn-info" style="min-width: 150px; font-size: 14px; padding: 10px 20px;"><i class="fa-solid fa-play"></i> End Break (Resume)</button>
            <?php endif; ?>
            
            <button id="btn-check-out" class="btn btn-checkout" style="min-width: 150px; font-size: 14px; padding: 10px 20px;"><i class="fa-solid fa-power-off"></i> Logout / Check Out</button>
        <?php endif; ?>
    </div>
    
    <?php if ($checkedIn): ?>
        <div class="attendance-time" style="display: flex; justify-content: center; gap: 20px; flex-wrap: wrap;">
            <div class="attendance-time-item">
                <div class="attendance-time-label">Login Time</div>
                <div class="attendance-time-value"><?php echo formatTime($myAttendance['check_in']); ?></div>
            </div>
            
            <?php if (!empty($myAttendance['break_start'])): ?>
                <div class="attendance-time-item">
                    <div class="attendance-time-label">Break Start</div>
                    <div class="attendance-time-value"><?php echo formatTime($myAttendance['break_start']); ?></div>
                </div>
            <?php endif; ?>

            <?php if (!empty($myAttendance['break_end'])): ?>
                <div class="attendance-time-item">
                    <div class="attendance-time-label">Break Duration</div>
                    <div class="attendance-time-value"><?php echo $myAttendance['total_break_time'] ?: '—'; ?></div>
                </div>
            <?php endif; ?>

            <?php if ($checkedOut): ?>
                <div class="attendance-time-item">
                    <div class="attendance-time-label">Logout Time</div>
                    <div class="attendance-time-value"><?php echo formatTime($myAttendance['check_out']); ?></div>
                </div>
                <div class="attendance-time-item">
                    <div class="attendance-time-label">Net Working Time</div>
                    <div class="attendance-time-value"><?php echo $myAttendance['total_working_time'] ?: '—'; ?></div>
                </div>
                <div class="attendance-time-item">
                    <div class="attendance-time-label">Status</div>
                    <div class="attendance-time-value">
                        <span class="badge <?php echo $myAttendance['status'] === 'present' ? 'badge-success' : ($myAttendance['status'] === 'half-day' ? 'badge-warning' : 'badge-danger'); ?>">
                            <?php echo ucfirst($myAttendance['status']); ?>
                        </span>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Stats -->
<div class="stats-grid">
    <div class="stat-card accent-blue fade-in stagger-1">
        <div class="stat-icon bg-blue"><i class="fa-solid fa-users"></i></div>
        <div class="stat-content">
            <div class="stat-value"><?php echo $totalEmployees; ?></div>
            <div class="stat-label">My Employees</div>
        </div>
    </div>
    <div class="stat-card accent-green fade-in stagger-2">
        <div class="stat-icon bg-green"><i class="fa-solid fa-circle-check"></i></div>
        <div class="stat-content">
            <div class="stat-value"><?php echo $presentToday; ?></div>
            <div class="stat-label">Present Today</div>
        </div>
    </div>
    <div class="stat-card accent-red fade-in stagger-3">
        <div class="stat-icon bg-red"><i class="fa-solid fa-circle-xmark"></i></div>
        <div class="stat-content">
            <div class="stat-value"><?php echo $absentToday; ?></div>
            <div class="stat-label">Absent Today</div>
        </div>
    </div>
    <div class="stat-card accent-orange fade-in stagger-4" onclick="window.location.href='<?php echo BASE_URL; ?>/manager/leaves.php'" style="cursor: pointer;">
        <div class="stat-icon bg-warning"><i class="fa-solid fa-umbrella-beach"></i></div>
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
            <h3 class="card-title">Team Attendance Today</h3>
        </div>
        <?php if (empty($teamAttendance)): ?>
            <div class="empty-state">
                <div class="empty-state-icon"><i class="fa-solid fa-users"></i></div>
                <div class="empty-state-title">No employees assigned</div>
                <div class="empty-state-text">You don't have any employees assigned to your team yet.</div>
            </div>
        <?php else: ?>
            <div class="activity-feed">
                <?php foreach ($teamAttendance as $att): ?>
                    <div class="activity-item">
                        <div class="activity-icon <?php echo $att['check_in'] ? 'bg-green' : 'bg-red'; ?>">
                            <?php echo $att['check_in'] ? '<i class="fa-solid fa-check"></i>' : '✕'; ?>
                        </div>
                        <div class="activity-content">
                            <div class="activity-title">
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
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Recent Tasks Assigned -->
    <div class="card fade-in">
        <div class="card-header">
            <h3 class="card-title">Recent Assigned Tasks</h3>
        </div>
        <?php if (empty($recentTasks)): ?>
            <div class="empty-state">
                <div class="empty-state-icon"><i class="fa-solid fa-clipboard-user"></i></div>
                <div class="empty-state-title">No tasks assigned</div>
            </div>
        <?php else: ?>
            <div class="task-list">
                <?php foreach ($recentTasks as $t): ?>
                    <div class="task-item priority-<?php echo e($t['priority']); ?>">
                        <div class="task-info">
                            <div class="task-title"><?php echo e($t['title']); ?></div>
                            <div class="task-meta">
                                <span>Assigned to: <?php echo e($t['assigned_to_name']); ?></span>
                                <span>•</span>
                                <span>Due: <?php echo formatDate($t['deadline']); ?></span>
                            </div>
                        </div>
                        <span class="badge <?php echo taskStatusBadge($t['status']); ?>"><?php echo taskStatusLabel($t['status']); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const widget = document.getElementById('shiftTimerWidget');
    if (!widget) return;

    const isCheckedIn = widget.dataset.checkedIn === '1';
    const isCheckedOut = widget.dataset.checkedOut === '1';
    const checkInTs = parseInt(widget.dataset.checkInTs);
    const breakStartTs = parseInt(widget.dataset.breakStartTs);
    const breakEndTs = parseInt(widget.dataset.breakEndTs);
    const totalBreakSec = parseInt(widget.dataset.totalBreakSec);

    if (!isCheckedIn || isCheckedOut || !checkInTs) return;

    function formatHHMMSS(seconds) {
        const h = Math.floor(seconds / 3600);
        const m = Math.floor((seconds % 3600) / 60);
        const s = seconds % 60;
        return String(h).padStart(2, '0') + ':' + String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
    }

    function updateShiftTimer() {
        const now = Math.floor(Date.now() / 1000);
        const isOnBreak = breakStartTs > 0 && breakEndTs === 0;

        let currentBreakSec = totalBreakSec;
        if (isOnBreak) {
            currentBreakSec += Math.max(0, now - breakStartTs);
        }

        const effectiveEnd = isOnBreak ? breakStartTs : now;
        let netWorkSec = Math.max(0, effectiveEnd - checkInTs - totalBreakSec);

        const workStr = formatHHMMSS(netWorkSec);
        const reqSec = 21600; // 6 hours
        const remSec = Math.max(0, reqSec - netWorkSec);
        const remStr = formatHHMMSS(remSec);

        const workElem = document.getElementById('timer-work-display');
        const remElem = document.getElementById('timer-rem-display');
        const barElem = document.getElementById('timer-progress-bar');
        const pctElem = document.getElementById('timer-progress-pct');
        const statusIcon = document.getElementById('timer-status-icon');
        const statusText = document.getElementById('timer-status-text');
        const badgeElem = document.getElementById('timer-badge');
        const breakBanner = document.getElementById('timer-break-banner');
        const breakElem = document.getElementById('timer-break-display');

        if (workElem) workElem.innerText = workStr;
        if (remElem) remElem.innerText = remStr;

        const pct = Math.min(100, Math.floor((netWorkSec / reqSec) * 100));
        if (barElem) barElem.style.width = pct + '%';
        if (pctElem) pctElem.innerText = pct + '%';

        if (isOnBreak) {
            if (statusIcon) statusIcon.innerText = '⏸';
            if (statusText) statusText.innerText = 'Timer Paused (On Break)';
            if (badgeElem) {
                badgeElem.innerText = 'On Break';
                badgeElem.className = 'badge badge-warning';
            }
            if (breakBanner) breakBanner.style.display = 'block';
            if (breakElem) {
                const bM = Math.floor(currentBreakSec / 60);
                const bS = currentBreakSec % 60;
                breakElem.innerText = String(bM).padStart(2, '0') + 'm ' + String(bS).padStart(2, '0') + 's';
            }
        } else {
            if (statusIcon) statusIcon.innerText = '<i class="fa-solid fa-stopwatch"></i>';
            if (statusText) statusText.innerText = netWorkSec >= reqSec ? 'Full Day Working Goal Completed! <i class="fa-solid fa-circle-check"></i>' : 'Working Timer (6-Hour Goal)';
            if (badgeElem) {
                if (netWorkSec >= reqSec) {
                    badgeElem.innerText = 'Full Day (6h+)';
                    badgeElem.className = 'badge badge-success';
                } else if (netWorkSec >= 10800) {
                    badgeElem.innerText = 'Half Day Achieved (3h+)';
                    badgeElem.className = 'badge badge-info';
                } else {
                    badgeElem.innerText = 'Working Active';
                    badgeElem.className = 'badge badge-primary';
                }
            }
            if (breakBanner) breakBanner.style.display = 'none';
        }
    }

    updateShiftTimer();
    setInterval(updateShiftTimer, 1000);
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
