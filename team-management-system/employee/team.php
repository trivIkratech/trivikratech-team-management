<?php
/**
 * Employee Team Workspace
 * 
 * Shows team members, team-wide tasks and their status notes (comments),
 * overall metrics, and announcements from their Manager or Founder.
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/middleware.php';

requireRole([ROLE_EMPLOYEE]);

$db = getDB();
$userId = getUserId();

// Fetch current user details to get their manager
$stmt = $db->prepare("SELECT manager_id FROM users WHERE id = ?");
$stmt->execute([$userId]);
$currentUser = $stmt->fetch();
$managerId = $currentUser['manager_id'] ?? null;

$manager = null;
$teamMembers = [];
$teamTasks = [];
$stats = ['total' => 0, 'completed' => 0, 'in_progress' => 0, 'todo' => 0];
$announcements = [];

if ($managerId) {
    // Get Manager details
    $stmt = $db->prepare("SELECT name, email FROM users WHERE id = ?");
    $stmt->execute([$managerId]);
    $manager = $stmt->fetch();
    
    // Get other team members under the same manager
    $stmt = $db->prepare("SELECT id, name, email, status FROM users WHERE manager_id = ? AND role = 'employee' AND status = 'active' ORDER BY name ASC");
    $stmt->execute([$managerId]);
    $teamMembers = $stmt->fetchAll();
    
    // Get all team tasks
    $stmt = $db->prepare("
        SELECT t.*, u.name AS employee_name, u.email AS employee_email 
        FROM tasks t 
        JOIN users u ON t.assigned_to = u.id 
        WHERE u.manager_id = ? AND u.role = 'employee'
        ORDER BY t.created_at DESC
    ");
    $stmt->execute([$managerId]);
    $teamTasks = $stmt->fetchAll();
    
    // Calculate stats
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) AS total,
            SUM(CASE WHEN t.status = 'completed' THEN 1 ELSE 0 END) AS completed,
            SUM(CASE WHEN t.status = 'in_progress' THEN 1 ELSE 0 END) AS in_progress,
            SUM(CASE WHEN t.status = 'todo' THEN 1 ELSE 0 END) AS todo
        FROM tasks t
        JOIN users u ON t.assigned_to = u.id
        WHERE u.manager_id = ? AND u.role = 'employee'
    ");
    $stmt->execute([$managerId]);
    $dbStats = $stmt->fetch();
    if ($dbStats) {
        $stats = $dbStats;
    }
    
    // Get announcements (from their Manager OR Founder)
    $stmt = $db->prepare("
        SELECT a.*, u.name AS sender_name, u.role AS sender_role 
        FROM announcements a 
        JOIN users u ON a.sender_id = u.id 
        WHERE a.sender_id = ? OR u.role = 'founder'
        ORDER BY a.created_at DESC 
        LIMIT 10
    ");
    $stmt->execute([$managerId]);
    $announcements = $stmt->fetchAll();
} else {
    // If no manager, get announcements from Founder only
    $stmt = $db->prepare("
        SELECT a.*, u.name AS sender_name, u.role AS sender_role 
        FROM announcements a 
        JOIN users u ON a.sender_id = u.id 
        WHERE u.role = 'founder'
        ORDER BY a.created_at DESC 
        LIMIT 10
    ");
    $stmt->execute();
    $announcements = $stmt->fetchAll();
}

$pageTitle = 'My Team';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">My Team</h1>
        <p class="page-subtitle">
            <?php if ($manager): ?>
                Team Manager: <strong><?php echo e($manager['name']); ?></strong> (<?php echo e($manager['email']); ?>)
            <?php else: ?>
                No Manager Assigned (Company-wide Workspace)
            <?php endif; ?>
        </p>
    </div>
</div>

<?php if (!$managerId): ?>
    <div class="card">
        <div class="empty-state">
            <div class="empty-state-icon"><i class="fa-solid fa-users"></i></div>
            <div class="empty-state-title">No Team Assigned</div>
            <div class="empty-state-text">You are not currently assigned to a manager's team. However, you can see global founder announcements below.</div>
        </div>
    </div>
<?php else: ?>
    <!-- overall Task Status Metrics -->
    <div class="stats-grid mb-6">
        <div class="stat-card accent-blue fade-in stagger-1">
            <div class="stat-icon bg-blue"><i class="fa-solid fa-clipboard-user"></i></div>
            <div class="stat-content">
                <div class="stat-value"><?php echo $stats['total'] ?: 0; ?></div>
                <div class="stat-label">Total Team Tasks</div>
            </div>
        </div>
        <div class="stat-card accent-green fade-in stagger-2">
            <div class="stat-icon bg-green"><i class="fa-solid fa-circle-check"></i></div>
            <div class="stat-content">
                <div class="stat-value"><?php echo $stats['completed'] ?: 0; ?></div>
                <div class="stat-label">Completed</div>
            </div>
        </div>
        <div class="stat-card accent-yellow fade-in stagger-3">
            <div class="stat-icon bg-yellow"><i class="fa-solid fa-hourglass-half"></i></div>
            <div class="stat-content">
                <div class="stat-value"><?php echo $stats['in_progress'] ?: 0; ?></div>
                <div class="stat-label">In Progress</div>
            </div>
        </div>
        <div class="stat-card accent-red fade-in stagger-4">
            <div class="stat-icon bg-red"><i class="fa-solid fa-clipboard-user"></i></div>
            <div class="stat-content">
                <div class="stat-value"><?php echo $stats['todo'] ?: 0; ?></div>
                <div class="stat-label">To Do</div>
            </div>
        </div>
    </div>

    <!-- Main Grid -->
    <div class="content-grid">
        <!-- Team Members List -->
        <div class="card fade-in">
            <div class="card-header">
                <h3 class="card-title">Team Members</h3>
            </div>
            <div style="display: flex; flex-direction: column; gap: var(--space-3);">
                <!-- Include Manager in Team List -->
                <div style="display: flex; align-items: center; gap: var(--space-3); padding-bottom: var(--space-2); border-bottom: 1px solid var(--color-border);">
                    <div class="table-user-avatar" style="background-color: var(--color-accent-purple); color: white;">
                        <?php echo e(getInitials($manager['name'])); ?>
                    </div>
                    <div>
                        <div style="font-weight: 600; font-size: var(--text-sm);"><?php echo e($manager['name']); ?> (Manager)</div>
                        <div style="font-size: var(--text-xs); color: var(--color-text-muted);"><?php echo e($manager['email']); ?></div>
                    </div>
                </div>
                
                <?php if (empty($teamMembers)): ?>
                    <div class="text-muted" style="font-size: var(--text-sm); text-align: center; padding: var(--space-3);">No other team members.</div>
                <?php else: ?>
                    <?php foreach ($teamMembers as $member): ?>
                        <div style="display: flex; align-items: center; gap: var(--space-3); padding-bottom: var(--space-2);">
                            <div class="table-user-avatar" style="<?php echo $member['id'] == $userId ? 'background-color: var(--color-accent-blue); color:white;' : ''; ?>">
                                <?php echo e(getInitials($member['name'])); ?>
                            </div>
                            <div>
                                <div style="font-weight: 500; font-size: var(--text-sm);"><?php echo e($member['name']); ?> <?php echo $member['id'] == $userId ? '<strong>(You)</strong>' : ''; ?></div>
                                <div style="font-size: var(--text-xs); color: var(--color-text-muted);"><?php echo e($member['email']); ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Team Tasks Board -->
        <div class="card fade-in">
            <div class="card-header">
                <h3 class="card-title">Team Task Statuses</h3>
            </div>
            <?php if (empty($teamTasks)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon"><i class="fa-solid fa-clipboard-user"></i></div>
                    <div class="empty-state-text">No tasks assigned to this team yet.</div>
                </div>
            <?php else: ?>
                <div style="display: flex; flex-direction: column; gap: var(--space-4);">
                    <?php foreach ($teamTasks as $task): ?>
                        <div style="padding: var(--space-3); border: 1px solid var(--color-border); border-radius: 10px; background-color: var(--color-bg-tertiary);">
                            <div class="flex-between mb-2">
                                <span style="font-weight: 600; font-size: var(--text-sm);"><?php echo e($task['employee_name']); ?></span>
                                <span class="badge <?php echo taskStatusBadge($task['status']); ?>"><?php echo taskStatusLabel($task['status']); ?></span>
                            </div>
                            <div style="font-size: var(--text-sm); font-weight: 500; margin-bottom: var(--space-1);">
                                <?php echo e($task['title']); ?>
                            </div>
                            <?php if ($task['description']): ?>
                                <div style="font-size: var(--text-xs); color: var(--color-text-secondary); margin-bottom: var(--space-2);">
                                    <?php echo e($task['description']); ?>
                                </div>
                            <?php endif; ?>
                            <div style="font-size: var(--text-xs); color: var(--color-text-muted); display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--color-border); padding-top: var(--space-2);">
                                <span><i class="fa-solid fa-clock"></i> Due: <?php echo $task['deadline'] ? formatDate($task['deadline']) : 'No deadline'; ?></span>
                            </div>
                            <?php if ($task['comments']): ?>
                                <div style="margin-top: var(--space-2); padding: var(--space-2); background: rgba(255,255,255,0.05); border-left: 3px solid var(--color-accent-blue); border-radius: 4px; font-size: var(--text-xs); color: var(--color-text-secondary);">
                                    <strong>Comment:</strong> <?php echo e($task['comments']); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<!-- Section 4: Team Announcements (Always visible at the bottom) -->
<div class="card fade-in mt-6">
    <div class="card-header">
        <h3 class="card-title">Team Announcements</h3>
    </div>
    <?php if (empty($announcements)): ?>
        <div class="empty-state">
            <div class="empty-state-icon"><i class="fa-solid fa-bullhorn"></i></div>
            <div class="empty-state-text">No announcements posted yet.</div>
        </div>
    <?php else: ?>
        <div class="activity-list">
            <?php foreach ($announcements as $ann): ?>
                <div class="activity-item" style="border-bottom: 1px solid var(--color-border); padding-bottom: var(--space-3); margin-bottom: var(--space-3);">
                    <div class="activity-icon bg-purple" style="font-size: var(--text-base); padding: 8px;"><i class="fa-solid fa-bullhorn"></i></div>
                    <div style="flex: 1;">
                        <div class="flex-between">
                            <h4 style="font-size: var(--text-sm); font-weight: 600; margin: 0;"><?php echo e($ann['title']); ?></h4>
                            <span class="text-muted" style="font-size: var(--text-xs);"><?php echo timeAgo($ann['created_at']); ?></span>
                        </div>
                        <div style="font-size: var(--text-xs); color: var(--color-text-secondary); margin-top: 4px;">
                            By: <strong><?php echo e($ann['sender_name']); ?></strong> (<?php echo ucfirst($ann['sender_role']); ?>)
                        </div>
                        <p style="font-size: var(--text-xs); color: var(--color-text-secondary); margin-top: var(--space-2); line-height: 1.5;">
                            <?php echo nl2br(e($ann['content'])); ?>
                        </p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
