<?php
/**
 * Manager — Task Management
 * 
 * Create, assign, edit, and monitor tasks for assigned employees.
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

// Handle task creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('form_action') === 'create_task') {
    requireCsrf();
    
    $title = trim(post('title', ''));
    $description = trim(post('description', ''));
    $assignedToInput = post('assigned_to');
    $priority = post('priority') ?: 'medium';
    $startDate = post('start_date') ?: null;
    $deadline = post('deadline') ?: null;
    
    if (empty($title) || empty($assignedToInput)) {
        setFlash('error', 'Task title and assignee are required.');
    } elseif (strpos($assignedToInput, 'team_') === 0) {
        // Custom Squad / Team created by Manager
        $teamId = (int)str_replace('team_', '', $assignedToInput);
        $team = getTeamById($teamId);
        $teamMembers = getTeamMembers($teamId);
        
        if (!$team || empty($teamMembers)) {
            setFlash('error', 'Selected squad has no active members.');
        } else {
            $stmt = $db->prepare("INSERT INTO tasks (title, description, assigned_to, assigned_by, team_id, priority, start_date, deadline) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            foreach ($teamMembers as $m) {
                $stmt->execute([$title, $description, $m['id'], $managerId, $teamId, $priority, $startDate, $deadline]);
                
                // Notify each squad member
                if ($m['id'] != $managerId) {
                    createNotification(
                        $m['id'],
                        '📋 New Squad Task: ' . $title,
                        'Task assigned to squad "' . $team['name'] . '": ' . ($description ? substr($description, 0, 80) : $title),
                        BASE_URL . '/employee/tasks.php',
                        'info'
                    );
                }
            }
            setFlash('success', 'Squad task created and assigned to all ' . count($teamMembers) . ' members of "' . e($team['name']) . '".');
            header('Location: ' . BASE_URL . '/manager/tasks.php?tab=assigned_by');
            exit;
        }
    } elseif ($assignedToInput === 'team' || $assignedToInput === 'entire_team') {
        // Fetch all active team members
        $stmt = $db->prepare("SELECT id, name FROM users WHERE manager_id = ? AND role = 'employee' AND status = 'active'");
        $stmt->execute([$managerId]);
        $teamMembers = $stmt->fetchAll();
        
        if (empty($teamMembers)) {
            setFlash('error', 'Your direct team is empty. Please assign employees first.');
        } else {
            $stmt = $db->prepare("INSERT INTO tasks (title, description, assigned_to, assigned_by, priority, start_date, deadline) VALUES (?, ?, ?, ?, ?, ?, ?)");
            foreach ($teamMembers as $m) {
                $stmt->execute([$title, $description, $m['id'], $managerId, $priority, $startDate, $deadline]);
                if ($m['id'] != $managerId) {
                    createNotification(
                        $m['id'],
                        '📋 New Team Task: ' . $title,
                        ($description ? substr($description, 0, 80) : $title),
                        BASE_URL . '/employee/tasks.php',
                        'info'
                    );
                }
            }
            setFlash('success', 'Team task created and assigned to all direct team members.');
            header('Location: ' . BASE_URL . '/manager/tasks.php?tab=assigned_by');
            exit;
        }
    } else {
        $assignedTo = (int)$assignedToInput;
        // Verify the assigned employee belongs to this manager or is in manager's squad
        $stmt = $db->prepare("SELECT id FROM users WHERE id = ? AND (manager_id = ? OR id IN (SELECT tm.user_id FROM team_members tm JOIN teams t ON tm.team_id = t.id WHERE t.created_by = ?))");
        $stmt->execute([$assignedTo, $managerId, $managerId]);
        
        if (!$stmt->fetch()) {
            setFlash('error', 'Invalid task data. Employee must be in your team.');
        } else {
            $stmt = $db->prepare("INSERT INTO tasks (title, description, assigned_to, assigned_by, priority, start_date, deadline) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$title, $description, $assignedTo, $managerId, $priority, $startDate, $deadline]);
            
            if ($assignedTo != $managerId) {
                createNotification(
                    $assignedTo,
                    '📋 New Task: ' . $title,
                    ($description ? substr($description, 0, 80) : $title),
                    BASE_URL . '/employee/tasks.php',
                    'info'
                );
            }
            
            setFlash('success', 'Task created successfully.');
            header('Location: ' . BASE_URL . '/manager/tasks.php?tab=assigned_by');
            exit;
        }
    }
}

// Handle task edit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('form_action') === 'edit_task') {
    requireCsrf();
    
    $taskId = (int)post('task_id');
    $title = post('title');
    $description = post('description');
    $assignedTo = (int)post('assigned_to');
    $priority = post('priority');
    $startDate = post('start_date') ?: null;
    $deadline = post('deadline') ?: null;
    $status = post('status');
    $comments = post('comments');
    
    // Verify task belongs to this manager or is assigned to an employee under this manager
    $stmt = $db->prepare("
        SELECT id FROM tasks 
        WHERE id = ? AND (assigned_by = ? OR assigned_to IN (SELECT id FROM users WHERE manager_id = ?))
    ");
    $stmt->execute([$taskId, $managerId, $managerId]);
    
    if (!$stmt->fetch() || empty($title)) {
        setFlash('error', 'Invalid request or permission denied.');
    } else {
        $completedAt = ($status === 'completed') ? date('Y-m-d H:i:s') : null;
        $stmt = $db->prepare("UPDATE tasks SET title=?, description=?, assigned_to=?, priority=?, start_date=?, deadline=?, status=?, comments=?, completed_at=?, updated_at=NOW() WHERE id=?");
        $stmt->execute([$title, $description, $assignedTo, $priority, $startDate, $deadline, $status, $comments, $completedAt, $taskId]);
        setFlash('success', 'Task updated.');
        header('Location: ' . BASE_URL . '/manager/tasks.php');
        exit;
    }
}

// Counts for tabs
$countAssignedToStmt = $db->prepare("SELECT COUNT(*) FROM tasks WHERE assigned_to = ?");
$countAssignedToStmt->execute([$managerId]);
$countAssignedTo = $countAssignedToStmt->fetchColumn();

// Count team tasks (assigned to team members by anyone including Founder, or assigned by manager)
$countTeamTasksStmt = $db->prepare("
    SELECT COUNT(*) FROM tasks t 
    WHERE (t.assigned_by = ? OR t.assigned_to IN (SELECT id FROM users WHERE manager_id = ?))
      AND t.assigned_to != ?
");
$countTeamTasksStmt->execute([$managerId, $managerId, $managerId]);
$countTeamTasks = $countTeamTasksStmt->fetchColumn();

// Active tab ('assigned_to' or 'assigned_by')
$activeTab = get('tab');
if (!$activeTab) {
    $activeTab = ($countAssignedTo > 0) ? 'assigned_to' : 'assigned_by';
}

// Filters
$filterStatus = get('status');
$filterAssignee = get('assigned_to_filter');
$page = max(1, (int)get('page', '1'));

if ($activeTab === 'assigned_to') {
    // Tasks assigned TO the manager (by Founder/HR)
    $where = ["t.assigned_to = ?"];
    $params = [$managerId];

    if ($filterStatus) { $where[] = "t.status = ?"; $params[] = $filterStatus; }

    $whereClause = implode(' AND ', $where);

    $countStmt = $db->prepare("SELECT COUNT(*) FROM tasks t WHERE {$whereClause}");
    $countStmt->execute($params);
    $totalRecords = $countStmt->fetchColumn();
    $pagination = paginate($totalRecords, $page);

    $stmt = $db->prepare("
        SELECT t.*, tm.name AS team_name, u.name AS assigned_by_name, u.role AS assigned_by_role
        FROM tasks t 
        JOIN users u ON t.assigned_by = u.id 
        LEFT JOIN teams tm ON t.team_id = tm.id
        WHERE {$whereClause}
        ORDER BY 
            CASE t.status WHEN 'todo' THEN 1 WHEN 'in_progress' THEN 2 WHEN 'completed' THEN 3 END,
            CASE t.priority WHEN 'high' THEN 1 WHEN 'medium' THEN 2 WHEN 'low' THEN 3 END,
            t.deadline ASC
        LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}
    ");
    $stmt->execute($params);
    $tasks = $stmt->fetchAll();
} else {
    // Team Tasks: Assigned to my team members (by Founder or Manager) OR assigned by Manager
    $where = [
        "(t.assigned_by = ? OR t.assigned_to IN (SELECT id FROM users WHERE manager_id = ?))",
        "t.assigned_to != ?"
    ];
    $params = [$managerId, $managerId, $managerId];

    if ($filterStatus) { $where[] = "t.status = ?"; $params[] = $filterStatus; }
    if ($filterAssignee) { $where[] = "t.assigned_to = ?"; $params[] = (int)$filterAssignee; }

    $whereClause = implode(' AND ', $where);

    $countStmt = $db->prepare("SELECT COUNT(*) FROM tasks t WHERE {$whereClause}");
    $countStmt->execute($params);
    $totalRecords = $countStmt->fetchColumn();
    $pagination = paginate($totalRecords, $page);

    $stmt = $db->prepare("
        SELECT t.*, tm.name AS team_name,
               u1.name AS assigned_to_name, u1.designation AS assigned_to_designation,
               u2.name AS assigned_by_name, u2.role AS assigned_by_role
        FROM tasks t 
        JOIN users u1 ON t.assigned_to = u1.id 
        JOIN users u2 ON t.assigned_by = u2.id 
        LEFT JOIN teams tm ON t.team_id = tm.id
        WHERE {$whereClause}
        ORDER BY 
            CASE t.status WHEN 'todo' THEN 1 WHEN 'in_progress' THEN 2 WHEN 'completed' THEN 3 END,
            CASE t.priority WHEN 'high' THEN 1 WHEN 'medium' THEN 2 WHEN 'low' THEN 3 END,
            t.deadline ASC
        LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}
    ");
    $stmt->execute($params);
    $tasks = $stmt->fetchAll();
}

// My employees for assignment & filter
$empStmt = $db->prepare("SELECT id, name FROM users WHERE manager_id = ? AND status = 'active' ORDER BY name");
$empStmt->execute([$managerId]);
$myEmployees = $empStmt->fetchAll();

// My custom teams / squads
$mySquads = getManagerTeams($managerId);

// Preselected team or user for task modal shortcut
$preselectedTeam = get('create_for_team', '');
$assignToUser = get('assign_to', '');

// Edit task
$editTask = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $stmt = $db->prepare("
        SELECT * FROM tasks 
        WHERE id = ? AND (assigned_by = ? OR assigned_to IN (SELECT id FROM users WHERE manager_id = ?))
    ");
    $stmt->execute([(int)$_GET['edit'], $managerId, $managerId]);
    $editTask = $stmt->fetch();
}

$pageTitle = 'Tasks';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Task Management</h1>
        <p class="page-subtitle">Manage tasks assigned to you & tasks assigned to your team members</p>
    </div>
    <div class="page-header-actions">
        <button class="btn btn-primary" onclick="openModal('create-task-modal')"><i class="fa-solid fa-plus"></i> Create Team Task</button>
    </div>
</div>

<!-- Tab Navigation Header -->
<div style="display: flex; gap: 12px; margin-bottom: 20px; border-bottom: 1px solid var(--color-border); padding-bottom: 12px;">
    <a href="?tab=assigned_to" class="btn <?php echo $activeTab === 'assigned_to' ? 'btn-primary' : 'btn-outline'; ?>" style="font-size: 13px; padding: 8px 16px; display: inline-flex; align-items: center; gap: 8px;">
        <i class="fa-solid fa-inbox"></i> Assigned to Me
        <span class="badge <?php echo $activeTab === 'assigned_to' ? 'badge-info' : 'badge-secondary'; ?>" style="font-size: 11px; padding: 2px 6px;"><?php echo $countAssignedTo; ?></span>
    </a>
    <a href="?tab=assigned_by" class="btn <?php echo $activeTab === 'assigned_by' ? 'btn-primary' : 'btn-outline'; ?>" style="font-size: 13px; padding: 8px 16px; display: inline-flex; align-items: center; gap: 8px;">
        <i class="fa-solid fa-people-group"></i> Team Tasks (Assigned to My Team)
        <span class="badge <?php echo $activeTab === 'assigned_by' ? 'badge-info' : 'badge-secondary'; ?>" style="font-size: 11px; padding: 2px 6px;"><?php echo $countTeamTasks; ?></span>
    </a>
</div>

<!-- Filters -->
<form method="GET" class="filter-bar">
    <input type="hidden" name="tab" value="<?php echo e($activeTab); ?>">
    <select name="status" class="form-select" onchange="this.form.submit()">
        <option value="">All Status</option>
        <option value="todo" <?php echo $filterStatus === 'todo' ? 'selected' : ''; ?>>To Do</option>
        <option value="in_progress" <?php echo $filterStatus === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
        <option value="completed" <?php echo $filterStatus === 'completed' ? 'selected' : ''; ?>>Completed</option>
    </select>
    
    <?php if ($activeTab === 'assigned_by'): ?>
        <select name="assigned_to_filter" class="form-select" onchange="this.form.submit()">
            <option value="">All Employees</option>
            <?php foreach ($myEmployees as $emp): ?>
                <option value="<?php echo $emp['id']; ?>" <?php echo $filterAssignee == $emp['id'] ? 'selected' : ''; ?>><?php echo e($emp['name']); ?></option>
            <?php endforeach; ?>
        </select>
    <?php endif; ?>
    
    <?php if ($filterStatus || $filterAssignee): ?>
        <a href="<?php echo BASE_URL; ?>/manager/tasks.php?tab=<?php echo $activeTab; ?>" class="btn btn-sm btn-outline">Clear</a>
    <?php endif; ?>
</form>

<?php if ($activeTab === 'assigned_to'): ?>
    <!-- TAB 1: TASKS ASSIGNED TO MANAGER -->
    <?php if (empty($tasks)): ?>
        <div class="card">
            <div class="empty-state">
                <div class="empty-state-icon"><i class="fa-solid fa-circle-check"></i></div>
                <div class="empty-state-title">No tasks assigned to you</div>
                <div class="empty-state-text">You don't have any tasks assigned by the Founder or HR right now.</div>
            </div>
        </div>
    <?php else: ?>
        <div class="task-list">
            <?php foreach ($tasks as $task): ?>
                <?php $overdue = isOverdue($task['deadline'], $task['status']); ?>
                <div class="task-item priority-<?php echo e($task['priority']); ?> fade-in card" data-task-id="<?php echo $task['id']; ?>" data-status="<?php echo e($task['status']); ?>" style="margin-bottom: 14px; padding: 16px;">
                    <div class="task-info" style="flex: 1;">
                        <div class="task-title" style="font-size: 16px; font-weight: 600; color: var(--color-text-main); display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                            <span><?php echo e($task['title']); ?></span>
                            <?php if (!empty($task['team_name'])): ?>
                                <span class="badge badge-info" style="font-size: 11px; padding: 2px 7px; display: inline-flex; align-items: center; gap: 4px;">
                                    <i class="fa-solid fa-people-group" style="font-size: 10px;"></i> Squad: <?php echo e($task['team_name']); ?>
                                </span>
                            <?php endif; ?>
                            <?php if ($overdue): ?>
                                <span class="badge badge-overdue">Overdue</span>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($task['description']): ?>
                            <p style="font-size: 13px; color: var(--color-text-secondary); margin: 8px 0; line-height: 1.5;">
                                <?php echo e($task['description']); ?>
                            </p>
                        <?php endif; ?>
                        
                        <div class="task-meta" style="margin-top: 8px; font-size: 12px; color: var(--color-text-muted); display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
                            <span><i class="fa-solid fa-calendar-days"></i> Assigned: <?php echo formatDate($task['created_at']); ?></span>
                            <?php if (!empty($task['start_date'])): ?>
                                <span>•</span>
                                <span><i class="fa-regular fa-calendar-plus" style="color: var(--color-primary);"></i> Start: <strong><?php echo formatDate($task['start_date']); ?></strong></span>
                            <?php endif; ?>
                            <?php if ($task['deadline']): ?>
                                <span>•</span>
                                <span><i class="fa-regular fa-calendar-check" style="<?php echo $overdue ? 'color: var(--color-danger); font-weight: 600;' : ''; ?>"></i> Due: <strong><?php echo formatDate($task['deadline']); ?></strong></span>
                            <?php endif; ?>
                            <span>•</span>
                            <span>Assigned By: <strong style="color: var(--color-text-main);"><?php echo e($task['assigned_by_name']); ?></strong> (<?php echo ucfirst($task['assigned_by_role']); ?>)</span>
                            <span>•</span>
                            <span class="badge <?php echo priorityBadge($task['priority']); ?>"><?php echo priorityLabel($task['priority']); ?></span>
                        </div>
                        
                        <div style="margin-top: 12px; display: flex; gap: 10px; align-items: center; width: 100%; flex-wrap: wrap;">
                            <span style="font-size: 12px; color: var(--color-text-secondary); white-space: nowrap;">Status Note / Comment:</span>
                            <input type="text" class="form-input task-comment-input" 
                                   data-task-id="<?php echo $task['id']; ?>" 
                                   value="<?php echo e($task['comments']); ?>" 
                                   placeholder="Add a status update note..." 
                                   style="padding: 6px 10px; font-size: 12px; flex: 1; min-width: 200px;">
                            <button class="btn btn-sm btn-outline btn-save-comment" data-task-id="<?php echo $task['id']; ?>" style="padding: 6px 12px; font-size: 12px; white-space: nowrap;"><i class="fa-solid fa-check"></i> Save Note</button>
                        </div>
                    </div>
                    
                    <div class="task-actions" style="display: flex; flex-direction: column; align-items: flex-end; gap: 10px; min-width: 140px;">
                        <span class="badge task-status-badge <?php echo taskStatusBadge($task['status']); ?>" style="font-size: 12px; padding: 6px 12px;"><?php echo taskStatusLabel($task['status']); ?></span>
                        
                        <div class="task-completed-at" style="font-size: 11px; color: var(--color-text-muted); <?php echo ($task['status'] === 'completed' && $task['completed_at']) ? '' : 'display: none;'; ?>">
                            <i class="fa-solid fa-circle-check" style="color: var(--color-success); font-size: 10px;"></i> Done: <?php echo $task['completed_at'] ? formatDate($task['completed_at']) : ''; ?>
                        </div>

                        <select class="form-select task-status-select" 
                                data-task-id="<?php echo $task['id']; ?>" 
                                data-original-value="<?php echo e($task['status']); ?>"
                                style="font-size: 12px; padding: 4px 8px; width: 130px;">
                            <option value="todo" <?php echo $task['status'] === 'todo' ? 'selected' : ''; ?>>To Do</option>
                            <option value="in_progress" <?php echo $task['status'] === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                            <option value="completed" <?php echo $task['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                        </select>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
        $qs = http_build_query(array_filter(['tab' => 'assigned_to', 'status' => $filterStatus]));
        echo renderPagination($pagination, BASE_URL . '/manager/tasks.php?' . $qs);
        ?>
    <?php endif; ?>

<?php else: ?>
    <!-- TAB 2: TASKS CREATED BY MANAGER FOR TEAM -->
    <?php if (empty($tasks)): ?>
        <div class="card">
            <div class="empty-state">
                <div class="empty-state-icon"><i class="fa-solid fa-circle-check"></i></div>
                <div class="empty-state-title">No team tasks found</div>
                <div class="empty-state-text">Create a task to assign to your team members.</div>
            </div>
        </div>
    <?php else: ?>
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width: 34%;">Task Title</th>
                        <th style="width: 16%;">Assigned To</th>
                        <th style="width: 10%;">Priority</th>
                        <th style="width: 12%;">Status</th>
                        <th style="width: 18%;">Timeline / Dates</th>
                        <th style="width: 10%; text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tasks as $task): ?>
                        <?php $overdue = isOverdue($task['deadline'], $task['status']); ?>
                        <tr data-task-id="<?php echo $task['id']; ?>" data-status="<?php echo e($task['status']); ?>">
                            <td>
                                <div class="table-user-name" style="font-weight: 600; display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">
                                    <span><?php echo e($task['title']); ?></span>
                                    <?php if (!empty($task['team_name'])): ?>
                                        <span class="badge badge-info" style="font-size: 10px; padding: 2px 6px; display: inline-flex; align-items: center; gap: 4px;">
                                            <i class="fa-solid fa-people-group" style="font-size: 9px;"></i> <?php echo e($task['team_name']); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div class="table-user-email" style="margin-top: 2px; display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">
                                    <span><?php echo formatDate($task['created_at']); ?></span>
                                    <span>•</span>
                                    <span>By: <strong><?php echo e($task['assigned_by_name']); ?></strong></span>
                                    <?php if ($task['assigned_by_role'] === 'founder'): ?>
                                        <span class="badge badge-info" style="font-size: 10px; padding: 2px 6px; display: inline-flex; align-items: center; gap: 4px;"><i class="fa-solid fa-crown" style="font-size: 9px;"></i> Founder Task</span>
                                    <?php elseif ($task['assigned_by'] == $managerId): ?>
                                        <span class="badge badge-secondary" style="font-size: 10px; padding: 2px 6px;">Assigned by You</span>
                                    <?php else: ?>
                                        <span class="badge badge-secondary" style="font-size: 10px; padding: 2px 6px;"><?php echo ucfirst($task['assigned_by_role']); ?></span>
                                    <?php endif; ?>
                                </div>
                                <?php if ($task['description']): ?>
                                    <div style="font-size: var(--text-xs); color: var(--color-text-secondary); margin-top: 4px; line-height: 1.4;">
                                        <?php echo e($task['description']); ?>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- Employee Status Note / Comment Display -->
                                <div class="task-comment-display" style="<?php echo empty($task['comments']) ? 'display: none;' : ''; ?> margin-top: 8px; font-size: var(--text-xs); line-height: 1.4; color: var(--color-text-secondary); background: rgba(59, 130, 246, 0.08); border-left: 3px solid var(--color-primary); padding: 6px 10px; border-radius: 4px; display: <?php echo !empty($task['comments']) ? 'block' : 'none'; ?>; max-width: 480px; word-break: break-word;">
                                    <div style="display: flex; align-items: flex-start; gap: 6px;">
                                        <i class="fa-solid fa-comment-dots" style="color: var(--color-primary); margin-top: 2px; flex-shrink: 0;"></i>
                                        <span><strong>Employee Note:</strong> <span class="task-comment-preview-text"><?php echo e($task['comments']); ?></span></span>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div style="font-weight: 500;"><?php echo e($task['assigned_to_name']); ?></div>
                                <small class="text-muted" style="font-size: var(--text-xs);"><?php echo e($task['assigned_to_designation'] ?: '—'); ?></small>
                            </td>
                            <td><span class="badge <?php echo priorityBadge($task['priority']); ?>"><?php echo priorityLabel($task['priority']); ?></span></td>
                            <td>
                                <?php if ($overdue): ?>
                                    <span class="badge badge-overdue task-status-badge">Overdue</span>
                                <?php else: ?>
                                    <span class="badge task-status-badge <?php echo taskStatusBadge($task['status']); ?>"><?php echo taskStatusLabel($task['status']); ?></span>
                                <?php endif; ?>
                                
                                <div class="task-completed-at" style="font-size: 11px; color: var(--color-text-muted); margin-top: 4px; <?php echo ($task['status'] === 'completed' && $task['completed_at']) ? '' : 'display: none;'; ?>">
                                    <i class="fa-solid fa-circle-check" style="color: var(--color-success); font-size: 10px;"></i> Done: <?php echo $task['completed_at'] ? formatDate($task['completed_at']) : ''; ?>
                                </div>
                            </td>
                            <td style="white-space: nowrap; font-size: 12px;">
                                <?php if (!empty($task['start_date'])): ?>
                                    <div style="color: var(--color-text-secondary); margin-bottom: 3px;">
                                        <i class="fa-regular fa-calendar-plus" style="color: var(--color-primary); font-size: 11px;"></i> Start: <strong><?php echo formatDate($task['start_date']); ?></strong>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($task['deadline'])): ?>
                                    <div style="<?php echo $overdue ? 'color: var(--color-danger); font-weight: 600;' : 'color: var(--color-text-secondary);'; ?>">
                                        <i class="fa-regular fa-calendar-check" style="<?php echo $overdue ? 'color: var(--color-danger);' : 'color: var(--color-warning);'; ?> font-size: 11px;"></i> End: <strong><?php echo formatDate($task['deadline']); ?></strong>
                                    </div>
                                <?php else: ?>
                                    <?php if (empty($task['start_date'])): ?><span class="text-muted">—</span><?php endif; ?>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: right;">
                                <div class="table-actions" style="justify-content: flex-end;">
                                    <a href="?tab=assigned_by&edit=<?php echo $task['id']; ?>" class="btn btn-ghost btn-sm" title="Edit"><i class="fa-solid fa-pen"></i></a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php
        $qs = http_build_query(array_filter(['tab' => 'assigned_by', 'status' => $filterStatus, 'assigned_to_filter' => $filterAssignee]));
        echo renderPagination($pagination, BASE_URL . '/manager/tasks.php?' . $qs);
        ?>
    <?php endif; ?>
<?php endif; ?>

<!-- Create Task Modal -->
<div class="modal-overlay" id="create-task-modal">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title"><i class="fa-solid fa-list-check"></i> Create New Task</h3>
            <button class="modal-close" onclick="closeModal('create-task-modal')">×</button>
        </div>
        <form method="POST" action="" data-validate>
            <div class="modal-body">
                <?php echo csrfField(); ?>
                <input type="hidden" name="form_action" value="create_task">
                
                <div class="form-group">
                    <label class="form-label">Task Title *</label>
                    <input type="text" name="title" class="form-input" placeholder="Enter task title" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-textarea" placeholder="Task details..."></textarea>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Assign To *</label>
                        <select name="assigned_to" class="form-select" required id="create_assigned_to">
                            <option value="">Select Assignee / Squad</option>
                            <?php if (!empty($mySquads)): ?>
                                <optgroup label="🌟 My Squads & Teams (Batch Assign Entire Squad)">
                                    <?php foreach ($mySquads as $sq): ?>
                                        <option value="team_<?php echo $sq['id']; ?>" <?php echo $preselectedTeam === ('team_' . $sq['id']) ? 'selected' : ''; ?>>
                                            👥 Entire Squad: <?php echo e($sq['name']); ?> (<?php echo $sq['member_count']; ?> members)
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                                
                                <optgroup label="👥 Squad Members (By Squad)">
                                    <?php foreach ($mySquads as $sq): ?>
                                        <?php $sqMembers = getTeamMembers((int)$sq['id']); ?>
                                        <?php foreach ($sqMembers as $sm): ?>
                                            <option value="<?php echo $sm['id']; ?>" <?php echo ($assignToUser == $sm['id'] && empty($preselectedTeam)) ? 'selected' : ''; ?>>
                                                &nbsp;&nbsp;↳ [<?php echo e($sq['name']); ?>] <?php echo e($sm['name']); ?> (<?php echo e($sm['designation'] ?: ucfirst($sm['role'])); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endif; ?>
                            <optgroup label="🏢 Direct Team">
                                <option value="team" <?php echo $preselectedTeam === 'team' ? 'selected' : ''; ?>>— Assign to Entire Direct Team (<?php echo count($myEmployees); ?>) —</option>
                            </optgroup>
                            <optgroup label="👤 Individual Direct Employees">
                                <?php foreach ($myEmployees as $emp): ?>
                                    <option value="<?php echo $emp['id']; ?>" <?php echo ($assignToUser == $emp['id'] && empty($preselectedTeam)) ? 'selected' : ''; ?>><?php echo e($emp['name']); ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Priority</label>
                        <select name="priority" class="form-select">
                            <option value="low">Low</option>
                            <option value="medium" selected>Medium</option>
                            <option value="high">High</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label"><i class="fa-regular fa-calendar-plus" style="color: var(--color-primary);"></i> Starting Date</label>
                        <input type="date" name="start_date" class="form-input" value="<?php echo today(); ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label"><i class="fa-regular fa-calendar-check" style="color: var(--color-warning);"></i> End Date / Deadline *</label>
                        <input type="date" name="deadline" class="form-input" required>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('create-task-modal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Create Task</button>
            </div>
        </form>
    </div>
</div>

<?php if (!empty($preselectedTeam) || !empty($assignToUser)): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof openModal === 'function') {
        openModal('create-task-modal');
    }
});
</script>
<?php endif; ?>

<!-- Edit Task Modal -->
<?php if ($editTask): ?>
<div class="modal-overlay active" id="edit-task-modal">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title"><i class="fa-solid fa-pen-to-square"></i> Edit Task</h3>
            <a href="<?php echo BASE_URL; ?>/manager/tasks.php" class="modal-close">×</a>
        </div>
        <form method="POST" action="" data-validate>
            <div class="modal-body">
                <?php echo csrfField(); ?>
                <input type="hidden" name="form_action" value="edit_task">
                <input type="hidden" name="task_id" value="<?php echo $editTask['id']; ?>">
                
                <div class="form-group">
                    <label class="form-label">Task Title *</label>
                    <input type="text" name="title" class="form-input" value="<?php echo e($editTask['title']); ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-textarea"><?php echo e($editTask['description']); ?></textarea>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Assign To *</label>
                        <select name="assigned_to" class="form-select" required>
                            <?php foreach ($myEmployees as $emp): ?>
                                <option value="<?php echo $emp['id']; ?>" <?php echo $editTask['assigned_to'] == $emp['id'] ? 'selected' : ''; ?>><?php echo e($emp['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Priority</label>
                        <select name="priority" class="form-select">
                            <option value="low" <?php echo $editTask['priority'] === 'low' ? 'selected' : ''; ?>>Low</option>
                            <option value="medium" <?php echo $editTask['priority'] === 'medium' ? 'selected' : ''; ?>>Medium</option>
                            <option value="high" <?php echo $editTask['priority'] === 'high' ? 'selected' : ''; ?>>High</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label"><i class="fa-regular fa-calendar-plus" style="color: var(--color-primary);"></i> Starting Date</label>
                        <input type="date" name="start_date" class="form-input" value="<?php echo e($editTask['start_date'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label"><i class="fa-regular fa-calendar-check" style="color: var(--color-warning);"></i> End Date / Deadline</label>
                        <input type="date" name="deadline" class="form-input" value="<?php echo e($editTask['deadline'] ?? ''); ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="todo" <?php echo $editTask['status'] === 'todo' ? 'selected' : ''; ?>>To Do</option>
                        <option value="in_progress" <?php echo $editTask['status'] === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                        <option value="completed" <?php echo $editTask['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Status Notes / Employee Comments</label>
                    <textarea name="comments" class="form-textarea" rows="3" placeholder="Status update notes or employee comments..."><?php echo e($editTask['comments']); ?></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <a href="<?php echo BASE_URL; ?>/manager/tasks.php" class="btn btn-outline">Cancel</a>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Update Task</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
