<?php
/**
 * Employee — My Tasks
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

// Filters
$filterStatus = get('status');

$where = ["t.assigned_to = ?"];
$params = [$userId];

if ($filterStatus) {
    $where[] = "t.status = ?";
    $params[] = $filterStatus;
}

$whereClause = implode(' AND ', $where);

$stmt = $db->prepare("
    SELECT t.*, u.name AS assigned_by_name
    FROM tasks t 
    JOIN users u ON t.assigned_by = u.id 
    WHERE {$whereClause}
    ORDER BY 
        CASE t.status WHEN 'todo' THEN 1 WHEN 'in_progress' THEN 2 WHEN 'completed' THEN 3 END,
        CASE t.priority WHEN 'high' THEN 1 WHEN 'medium' THEN 2 WHEN 'low' THEN 3 END,
        t.deadline ASC
");
$stmt->execute($params);
$tasks = $stmt->fetchAll();

$pageTitle = 'My Tasks';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">My Tasks</h1>
        <p class="page-subtitle"><?php echo count($tasks); ?> task(s) assigned to you</p>
    </div>
</div>

<!-- Filters -->
<form method="GET" class="filter-bar">
    <select name="status" class="form-select" onchange="this.form.submit()">
        <option value="">All Tasks</option>
        <option value="todo" <?php echo $filterStatus === 'todo' ? 'selected' : ''; ?>>To Do</option>
        <option value="in_progress" <?php echo $filterStatus === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
        <option value="completed" <?php echo $filterStatus === 'completed' ? 'selected' : ''; ?>>Completed</option>
    </select>
    <?php if ($filterStatus): ?>
        <a href="<?php echo BASE_URL; ?>/employee/tasks.php" class="btn btn-sm btn-outline">Show All</a>
    <?php endif; ?>
</form>

<?php if (empty($tasks)): ?>
    <div class="card">
        <div class="empty-state">
            <div class="empty-state-icon">🎉</div>
            <div class="empty-state-title">No tasks</div>
            <div class="empty-state-text">You don't have any tasks<?php echo $filterStatus ? ' with this status' : ''; ?> right now.</div>
        </div>
    </div>
<?php else: ?>
    <div class="task-list">
        <?php foreach ($tasks as $task): ?>
            <?php $overdue = isOverdue($task['deadline'], $task['status']); ?>
            <div class="task-item priority-<?php echo e($task['priority']); ?> fade-in">
                <div class="task-info" style="flex: 1;">
                    <div class="task-title" style="font-size: var(--text-base);">
                        <?php echo e($task['title']); ?>
                        <?php if ($overdue): ?>
                            <span class="badge badge-overdue" style="margin-left: 8px;">Overdue</span>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($task['description']): ?>
                        <p style="font-size: var(--text-sm); color: var(--color-text-secondary); margin: var(--space-2) 0; line-height: 1.5;">
                            <?php echo e($task['description']); ?>
                        </p>
                    <?php endif; ?>
                    
                    <div class="task-meta" style="margin-top: var(--space-2);">
                        <span>📅 Assigned: <?php echo formatDate($task['created_at']); ?></span>
                        <?php if ($task['deadline']): ?>
                            <span>•</span>
                            <span>⏰ Due: <?php echo formatDate($task['deadline']); ?></span>
                        <?php endif; ?>
                        <span>•</span>
                        <span>By: <?php echo e($task['assigned_by_name']); ?></span>
                        <span>•</span>
                        <span class="badge <?php echo priorityBadge($task['priority']); ?>"><?php echo priorityLabel($task['priority']); ?></span>
                    </div>
                    
                    <div style="margin-top: var(--space-3); display: flex; gap: var(--space-2); align-items: center; width: 100%;">
                        <span style="font-size: var(--text-xs); color: var(--color-text-secondary); white-space: nowrap;">Status Note / Comment:</span>
                        <input type="text" class="form-input task-comment-input" 
                               data-task-id="<?php echo $task['id']; ?>" 
                               value="<?php echo e($task['comments']); ?>" 
                               placeholder="Add a status update note..." 
                               style="padding: 4px 8px; font-size: var(--text-xs); flex: 1;">
                        <button class="btn btn-sm btn-outline btn-save-comment" data-task-id="<?php echo $task['id']; ?>" style="padding: 4px 8px; font-size: var(--text-xs); white-space: nowrap;">✓ Save</button>
                    </div>
                </div>
                
                <div class="task-actions" style="flex-direction: column; align-items: flex-end; gap: var(--space-3);">
                    <span class="badge task-status-badge <?php echo taskStatusBadge($task['status']); ?>"><?php echo taskStatusLabel($task['status']); ?></span>
                    
                    <?php if ($task['status'] !== 'completed'): ?>
                        <select class="form-select task-status-select" 
                                data-task-id="<?php echo $task['id']; ?>" 
                                data-original-value="<?php echo e($task['status']); ?>"
                                style="width: auto; padding: 4px 28px 4px 8px; font-size: var(--text-xs);">
                            <option value="todo" <?php echo $task['status'] === 'todo' ? 'selected' : ''; ?>>To Do</option>
                            <option value="in_progress" <?php echo $task['status'] === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                            <option value="completed" <?php echo $task['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                        </select>
                    <?php else: ?>
                        <span style="font-size: var(--text-xs); color: var(--color-text-muted);">
                            Done: <?php echo formatDate($task['completed_at']); ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
