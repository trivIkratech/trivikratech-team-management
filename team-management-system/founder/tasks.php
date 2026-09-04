<?php
/**
 * Founder — All Tasks
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/middleware.php';

requireRole([ROLE_FOUNDER]);

$db = getDB();

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
        // Custom created Squad / Team
        $teamId = (int)str_replace('team_', '', $assignedToInput);
        $team = getTeamById($teamId);
        $teamMembers = getTeamMembers($teamId);
        
        if (!$team || empty($teamMembers)) {
            setFlash('error', 'Selected team has no active members.');
        } else {
            $stmt = $db->prepare("INSERT INTO tasks (title, description, assigned_to, assigned_by, team_id, priority, start_date, deadline) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            foreach ($teamMembers as $m) {
                $stmt->execute([$title, $description, $m['id'], getUserId(), $teamId, $priority, $startDate, $deadline]);
                
                // Notify each team member
                if ($m['id'] != getUserId()) {
                    createNotification(
                        $m['id'],
                        '📋 New Team Task: ' . $title,
                        'Task assigned to team "' . $team['name'] . '": ' . ($description ? substr($description, 0, 80) : $title),
                        BASE_URL . '/employee/tasks.php',
                        'info'
                    );
                }
            }
            setFlash('success', 'Team task created and assigned to all ' . count($teamMembers) . ' members of "' . e($team['name']) . '".');
            redirect(BASE_URL . '/founder/tasks.php');
        }
    } elseif (strpos($assignedToInput, 'mgr_team_') === 0) {
        $targetManagerId = (int)str_replace('mgr_team_', '', $assignedToInput);
        // Fetch all active employees under this manager
        $stmt = $db->prepare("SELECT id, name FROM users WHERE manager_id = ? AND role = 'employee' AND status = 'active'");
        $stmt->execute([$targetManagerId]);
        $teamMembers = $stmt->fetchAll();
        
        if (empty($teamMembers)) {
            setFlash('error', 'This manager team is empty or has no active employees.');
        } else {
            $stmt = $db->prepare("INSERT INTO tasks (title, description, assigned_to, assigned_by, priority, start_date, deadline) VALUES (?, ?, ?, ?, ?, ?, ?)");
            foreach ($teamMembers as $m) {
                $stmt->execute([$title, $description, $m['id'], getUserId(), $priority, $startDate, $deadline]);
                if ($m['id'] != getUserId()) {
                    createNotification(
                        $m['id'],
                        '📋 New Task: ' . $title,
                        ($description ? substr($description, 0, 80) : $title),
                        BASE_URL . '/employee/tasks.php',
                        'info'
                    );
                }
            }
            setFlash('success', 'Task created and assigned to all employees under the manager.');
            redirect(BASE_URL . '/founder/tasks.php');
        }
    } else {
        $assignedTo = (int)$assignedToInput;
        $stmt = $db->prepare("INSERT INTO tasks (title, description, assigned_to, assigned_by, priority, start_date, deadline) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$title, $description, $assignedTo, getUserId(), $priority, $startDate, $deadline]);
        
        if ($assignedTo != getUserId()) {
            createNotification(
                $assignedTo,
                '📋 New Task: ' . $title,
                ($description ? substr($description, 0, 80) : $title),
                BASE_URL . '/employee/tasks.php',
                'info'
            );
        }
        
        setFlash('success', 'Task created successfully.');
        redirect(BASE_URL . '/founder/tasks.php');
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
    
    if (empty($title) || empty($assignedTo)) {
        setFlash('error', 'Task title and assignee are required.');
    } else {
        $completedAt = ($status === 'completed') ? date('Y-m-d H:i:s') : null;
        $stmt = $db->prepare("UPDATE tasks SET title=?, description=?, assigned_to=?, priority=?, start_date=?, deadline=?, status=?, comments=?, completed_at=?, updated_at=NOW() WHERE id=?");
        $stmt->execute([$title, $description, $assignedTo, $priority, $startDate, $deadline, $status, $comments, $completedAt, $taskId]);
        setFlash('success', 'Task updated successfully.');
        redirect(BASE_URL . '/founder/tasks.php');
    }
}

// Handle task delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $taskId = (int)$_GET['delete'];
    $db->prepare("DELETE FROM tasks WHERE id = ?")->execute([$taskId]);
    setFlash('success', 'Task deleted.');
    redirect(BASE_URL . '/founder/tasks.php');
}

// Filters
$filterStatus = get('status');
$filterPriority = get('priority');
$filterAssignee = get('assigned_to');
$filterTeam = get('team_id');
$page = max(1, (int)get('page', '1'));

$where = ['1=1'];
$params = [];

if ($filterStatus) { $where[] = "t.status = ?"; $params[] = $filterStatus; }
if ($filterPriority) { $where[] = "t.priority = ?"; $params[] = $filterPriority; }
if ($filterAssignee) { $where[] = "t.assigned_to = ?"; $params[] = (int)$filterAssignee; }
if ($filterTeam) { $where[] = "t.team_id = ?"; $params[] = (int)$filterTeam; }

$whereClause = implode(' AND ', $where);

$countStmt = $db->prepare("SELECT COUNT(*) FROM tasks t WHERE {$whereClause}");
$countStmt->execute($params);
$totalRecords = $countStmt->fetchColumn();
$pagination = paginate($totalRecords, $page);

$stmt = $db->prepare("
    SELECT t.*, u1.name AS assigned_to_name, u1.designation AS assigned_to_designation, u2.name AS assigned_by_name,
           tm.name AS team_name
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

// Get employees for assignment dropdown
$allUsers = $db->query("SELECT id, name, role FROM users WHERE role IN ('employee','manager') AND status = 'active' ORDER BY name")->fetchAll();

// Get all teams for assignment
$allTeams = getAllTeams();

// Edit task data
$editTask = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $stmt = $db->prepare("SELECT * FROM tasks WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $editTask = $stmt->fetch();
}

$pageTitle = 'Tasks';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Tasks</h1>
        <p class="page-subtitle"><?php echo $totalRecords; ?> task(s) across the company</p>
    </div>
    <div class="page-header-actions">
        <button class="btn btn-primary" onclick="openModal('create-task-modal')">+ Create Task</button>
    </div>
</div>

<!-- Filters -->
<form method="GET" class="filter-bar">
    <select name="status" class="form-select" onchange="this.form.submit()">
        <option value="">All Status</option>
        <option value="todo" <?php echo $filterStatus === 'todo' ? 'selected' : ''; ?>>To Do</option>
        <option value="in_progress" <?php echo $filterStatus === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
        <option value="completed" <?php echo $filterStatus === 'completed' ? 'selected' : ''; ?>>Completed</option>
    </select>
    <select name="priority" class="form-select" onchange="this.form.submit()">
        <option value="">All Priority</option>
        <option value="high" <?php echo $filterPriority === 'high' ? 'selected' : ''; ?>>High</option>
        <option value="medium" <?php echo $filterPriority === 'medium' ? 'selected' : ''; ?>>Medium</option>
        <option value="low" <?php echo $filterPriority === 'low' ? 'selected' : ''; ?>>Low</option>
    </select>
    <select name="assigned_to" class="form-select" onchange="this.form.submit()">
        <option value="">All Assignees</option>
        <?php foreach ($allUsers as $u): ?>
            <option value="<?php echo $u['id']; ?>" <?php echo $filterAssignee == $u['id'] ? 'selected' : ''; ?>><?php echo e($u['name']); ?></option>
        <?php endforeach; ?>
    </select>
    <?php if ($filterStatus || $filterPriority || $filterAssignee): ?>
        <a href="<?php echo BASE_URL; ?>/founder/tasks.php" class="btn btn-sm btn-outline">Clear</a>
    <?php endif; ?>
</form>

<?php if (empty($tasks)): ?>
    <div class="card">
        <div class="empty-state">
            <div class="empty-state-icon"><i class="fa-solid fa-circle-check"></i></div>
            <div class="empty-state-title">No tasks found</div>
            <div class="empty-state-text">Create a task or adjust your filters.</div>
        </div>
    </div>
<?php else: ?>
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 34%;">Task</th>
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
                            <div style="display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">
                                <span class="table-user-name" style="font-weight: 600;"><?php echo e($task['title']); ?></span>
                                <?php if (!empty($task['team_name'])): ?>
                                    <span class="badge badge-primary" style="font-size: 10px; padding: 2px 6px;">
                                        <i class="fa-solid fa-people-group" style="margin-right: 3px;"></i> <?php echo e($task['team_name']); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="table-user-email" style="margin-top: 2px;">by <?php echo e($task['assigned_by_name']); ?> • <?php echo formatDate($task['created_at']); ?></div>
                            <?php if ($task['description']): ?>
                                <div style="font-size: var(--text-xs); color: var(--color-text-secondary); margin-top: 4px; line-height: 1.4;">
                                    <?php echo e($task['description']); ?>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Status Note / Comment Display -->
                            <div class="task-comment-display" style="<?php echo empty($task['comments']) ? 'display: none;' : ''; ?> margin-top: 8px; font-size: var(--text-xs); line-height: 1.4; color: var(--color-text-secondary); background: rgba(59, 130, 246, 0.08); border-left: 3px solid var(--color-primary); padding: 6px 10px; border-radius: 4px; display: <?php echo !empty($task['comments']) ? 'block' : 'none'; ?>; max-width: 480px; word-break: break-word;">
                                <div style="display: flex; align-items: flex-start; gap: 6px;">
                                    <i class="fa-solid fa-comment-dots" style="color: var(--color-primary); margin-top: 2px; flex-shrink: 0;"></i>
                                    <span><strong>Note:</strong> <span class="task-comment-preview-text"><?php echo e($task['comments']); ?></span></span>
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
                                <a href="<?php echo BASE_URL; ?>/founder/tasks.php?edit=<?php echo $task['id']; ?>" class="btn btn-ghost btn-sm" title="Edit"><i class="fa-solid fa-pen"></i></a>
                                <a href="<?php echo BASE_URL; ?>/founder/tasks.php?delete=<?php echo $task['id']; ?>" class="btn btn-ghost btn-sm" data-confirm="Delete this task permanently?" title="Delete"><i class="fa-solid fa-trash-can"></i></a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <?php
    $qs = http_build_query(array_filter(['status' => $filterStatus, 'priority' => $filterPriority, 'assigned_to' => $filterAssignee, 'team_id' => $filterTeam]));
    echo renderPagination($pagination, BASE_URL . '/founder/tasks.php?' . $qs);
    ?>
<?php endif; ?>

<!-- Create Task Modal -->
<?php $createForTeam = get('create_for_team', ''); ?>
<div class="modal-overlay <?php echo !empty($createForTeam) ? 'active' : ''; ?>" id="create-task-modal">
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
                        <label class="form-label">Assign To (Team or Individual) *</label>
                        <select name="assigned_to" class="form-select" required>
                            <option value="">Select Team or Employee</option>
                            <?php if (!empty($allTeams)): ?>
                                <optgroup label="👥 Squads & Teams">
                                    <?php foreach ($allTeams as $t): ?>
                                        <option value="team_<?php echo $t['id']; ?>" <?php echo $createForTeam === 'team_' . $t['id'] ? 'selected' : ''; ?>>
                                            👥 <?php echo e($t['name']); ?> (<?php echo $t['member_count']; ?> members · Lead: <?php echo e($t['leader_name']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endif; ?>
                            <optgroup label="👔 Manager Department Teams">
                                <?php 
                                $managers = array_filter($allUsers, fn($u) => $u['role'] === 'manager');
                                foreach ($managers as $m): 
                                ?>
                                    <option value="mgr_team_<?php echo $m['id']; ?>">All Employees under <?php echo e($m['name']); ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                            <optgroup label="👤 Individual Staff">
                                <?php foreach ($allUsers as $u): ?>
                                    <option value="<?php echo $u['id']; ?>"><?php echo e($u['name']); ?> (<?php echo ucfirst($u['role']); ?>)</option>
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

<!-- Edit Task Modal -->
<?php if ($editTask): ?>
<div class="modal-overlay active" id="edit-task-modal">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title"><i class="fa-solid fa-pen-to-square"></i> Edit Task</h3>
            <a href="<?php echo BASE_URL; ?>/founder/tasks.php" class="modal-close">×</a>
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
                            <?php foreach ($allUsers as $u): ?>
                                <option value="<?php echo $u['id']; ?>" <?php echo $editTask['assigned_to'] == $u['id'] ? 'selected' : ''; ?>><?php echo e($u['name']); ?></option>
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
                    <textarea name="comments" class="form-textarea" rows="3" placeholder="Add or update status note / comments..."><?php echo e($editTask['comments']); ?></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <a href="<?php echo BASE_URL; ?>/founder/tasks.php" class="btn btn-outline">Cancel</a>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Update Task</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
