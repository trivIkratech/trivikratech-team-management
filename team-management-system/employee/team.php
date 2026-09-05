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
    $stmt = $db->prepare("SELECT id, name, email, contact_no, designation, employee_id, last_activity FROM users WHERE id = ?");
    $stmt->execute([$managerId]);
    $manager = $stmt->fetch();
    
    // Get other team members under the same manager
    $stmt = $db->prepare("SELECT id, name, email, designation, status, last_activity FROM users WHERE manager_id = ? AND role = 'employee' AND status = 'active' ORDER BY name ASC");
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
    
    // Get announcements (from their Manager, HR, OR Founder)
    $stmt = $db->prepare("
        SELECT a.*, u.name AS sender_name, u.role AS sender_role 
        FROM announcements a 
        JOIN users u ON a.sender_id = u.id 
        WHERE a.sender_id = ? OR u.role IN ('founder', 'hr')
        ORDER BY a.created_at DESC 
        LIMIT 10
    ");
    $stmt->execute([$managerId]);
    $announcements = $stmt->fetchAll();
} else {
    // If no manager, get announcements from Founder and HR
    $stmt = $db->prepare("
        SELECT a.*, u.name AS sender_name, u.role AS sender_role 
        FROM announcements a 
        JOIN users u ON a.sender_id = u.id 
        WHERE u.role IN ('founder', 'hr')
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
        <h1 class="page-title"><i class="fa-solid fa-users" style="color: var(--color-primary); margin-right: 8px;"></i> My Team & Reporting Manager</h1>
        <p class="page-subtitle">
            <?php if ($manager): ?>
                Reporting to: <strong><?php echo e($manager['name']); ?></strong> (<?php echo e($manager['designation'] ?: 'Team Lead / Manager'); ?>)
            <?php else: ?>
                No Manager Assigned (Company-wide Workspace)
            <?php endif; ?>
        </p>
    </div>
</div>

<?php if (!$managerId || !$manager): ?>
    <div class="card">
        <div class="empty-state">
            <div class="empty-state-icon"><i class="fa-solid fa-users"></i></div>
            <div class="empty-state-title">No Manager Assigned</div>
            <div class="empty-state-text">You are not currently assigned to a reporting manager. HR or Admin can assign your manager from the Employee Directory.</div>
        </div>
    </div>
<?php else: ?>
    <!-- Assigned Manager Profile Card -->
    <?php 
    $mgrStatus = formatLastSeen($manager['last_activity'] ?? null);
    $mgrOnline = $mgrStatus['is_online'];
    ?>
    <div class="card fade-in mb-6" style="background: linear-gradient(135deg, rgba(79, 110, 247, 0.08), rgba(139, 92, 246, 0.08)); border: 1px solid var(--color-primary-border); padding: 22px;">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
            <div style="display: flex; align-items: center; gap: 16px;">
                <div style="position: relative; width: 58px; height: 58px; border-radius: 50%; background: linear-gradient(135deg, #f59e0b, #ef4444); color: white; display: flex; align-items: center; justify-content: center; font-size: 22px; font-weight: 800; box-shadow: 0 4px 14px rgba(245, 158, 11, 0.35); flex-shrink: 0;">
                    <?php echo e(getInitials($manager['name'])); ?>
                    <span class="user-status-dot <?php echo $mgrOnline ? 'online' : 'offline'; ?>" style="position: absolute; bottom: 0; right: 0; width: 14px; height: 14px; border-radius: 50%; border: 2.5px solid var(--color-bg-card); background: <?php echo $mgrOnline ? '#10b981' : '#64748b'; ?>;" title="<?php echo $mgrOnline ? 'Online' : $mgrStatus['last_seen_text']; ?>"></span>
                </div>
                <div>
                    <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                        <h2 style="margin: 0; font-size: 18px; font-weight: 700; color: var(--color-text-white);"><?php echo e($manager['name']); ?></h2>
                        <span class="chat-role-badge badge-role-manager" style="font-size: 11px; padding: 2px 8px;"><i class="fa-solid fa-user-tie"></i> Assigned Reporting Manager</span>
                        <span class="badge <?php echo $mgrOnline ? 'badge-success' : 'badge-secondary'; ?>" style="font-size: 10.5px; padding: 2px 8px;">
                            <i class="fa-solid fa-circle" style="font-size: 6px; vertical-align: middle; margin-right: 3px;"></i> <?php echo $mgrOnline ? 'Online' : $mgrStatus['last_seen_text']; ?>
                        </span>
                    </div>
                    <div style="font-size: 13px; color: var(--color-primary); margin-top: 4px; font-weight: 500;">
                        <?php echo e($manager['designation'] ?: 'Team Manager'); ?>
                        <?php if (!empty($manager['employee_id'])): ?>
                            <span class="text-muted" style="margin-left: 6px;">(ID: <?php echo e($manager['employee_id']); ?>)</span>
                        <?php endif; ?>
                    </div>
                    <div style="font-size: 12.5px; color: var(--color-text-secondary); margin-top: 4px; display: flex; gap: 14px; flex-wrap: wrap;">
                        <span><i class="fa-regular fa-envelope" style="color: var(--color-text-muted);"></i> <?php echo e($manager['email']); ?></span>
                        <?php if (!empty($manager['contact_no'])): ?>
                            <span><i class="fa-solid fa-phone" style="color: var(--color-text-muted);"></i> <?php echo e($manager['contact_no']); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div>
                <a href="<?php echo BASE_URL; ?>/chat/index.php" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 8px;">
                    <i class="fa-solid fa-comments"></i> Direct Message Manager
                </a>
            </div>
        </div>
    </div>

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
            <div class="stat-icon bg-red"><i class="fa-solid fa-list-check"></i></div>
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
            <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                <h3 class="card-title"><i class="fa-solid fa-users" style="color: var(--color-primary); margin-right: 6px;"></i> Team Colleagues</h3>
                <span class="badge badge-primary"><?php echo count($teamMembers); ?> Members</span>
            </div>
            <div style="display: flex; flex-direction: column; gap: var(--space-3);">
                <?php if (empty($teamMembers)): ?>
                    <div class="text-muted" style="font-size: var(--text-sm); text-align: center; padding: var(--space-3);">No other team members assigned to this manager yet.</div>
                <?php else: ?>
                    <?php foreach ($teamMembers as $member): ?>
                        <?php 
                        $memStatus = formatLastSeen($member['last_activity'] ?? null);
                        $memOnline = $memStatus['is_online'];
                        ?>
                        <div style="display: flex; align-items: center; justify-content: space-between; gap: var(--space-3); padding-bottom: var(--space-2); border-bottom: 1px solid var(--color-border);">
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <div class="table-user-avatar" style="position: relative; <?php echo $member['id'] == $userId ? 'background: linear-gradient(135deg, #4f6ef7, #06b6d4); color:white;' : 'background: var(--color-bg-tertiary); color: var(--color-text-main);'; ?>">
                                    <?php echo e(getInitials($member['name'])); ?>
                                    <span style="position: absolute; bottom: -1px; right: -1px; width: 10px; height: 10px; border-radius: 50%; border: 2px solid var(--color-bg-card); background: <?php echo $memOnline ? '#10b981' : '#64748b'; ?>;" title="<?php echo $memOnline ? 'Online' : $memStatus['last_seen_text']; ?>"></span>
                                </div>
                                <div>
                                    <div style="font-weight: 600; font-size: 13.5px; color: var(--color-text-white); display: flex; align-items: center; gap: 6px;">
                                        <?php echo e($member['name']); ?> 
                                        <?php if ($member['id'] == $userId): ?>
                                            <span class="badge badge-primary" style="font-size: 10px;">You</span>
                                        <?php endif; ?>
                                        <span class="badge <?php echo $memOnline ? 'badge-success' : 'badge-secondary'; ?>" style="font-size: 9.5px; padding: 1px 6px;">
                                            <i class="fa-solid fa-circle" style="font-size: 5px; vertical-align: middle; margin-right: 2px;"></i> <?php echo $memOnline ? 'Online' : $memStatus['last_seen_text']; ?>
                                        </span>
                                    </div>
                                    <div style="font-size: 11.5px; color: var(--color-text-muted); margin-top: 2px;"><?php echo e($member['designation'] ?: 'Team Member'); ?> · <?php echo e($member['email']); ?></div>
                                </div>
                            </div>
                            <?php if ($member['id'] != $userId): ?>
                                <a href="<?php echo BASE_URL; ?>/chat/index.php" class="btn btn-sm btn-outline" style="padding: 4px 10px; font-size: 11px;">
                                    <i class="fa-solid fa-comment"></i> Chat
                                </a>
                            <?php endif; ?>
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
