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
    
    $title = post('title');
    $description = post('description');
    $assignedToInput = post('assigned_to');
    $priority = post('priority');
    $deadline = post('deadline');
    
    if (empty($title) || empty($assignedToInput)) {
        setFlash('error', 'Task title and assignee are required.');
    } elseif (strpos($assignedToInput, 'team_') === 0) {
        $targetManagerId = (int)str_replace('team_', '', $assignedToInput);
        // Fetch all active employees under this manager
        $stmt = $db->prepare("SELECT id FROM users WHERE manager_id = ? AND role = 'employee' AND status = 'active'");
        $stmt->execute([$targetManagerId]);
        $teamMemberIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (empty($teamMemberIds)) {
            setFlash('error', 'This manager team is empty or has no active employees.');
        } else {
            $stmt = $db->prepare("INSERT INTO tasks (title, description, assigned_to, assigned_by, priority, deadline) VALUES (?, ?, ?, ?, ?, ?)");
            foreach ($teamMemberIds as $empId) {
                $stmt->execute([$title, $description, $empId, getUserId(), $priority ?: 'medium', $deadline ?: null]);
            }
            setFlash('success', 'Team task created and assigned to all team members under the manager.');
            header('Location: ' . BASE_URL . '/founder/tasks.php');
            exit;
        }
    } else {
        $assignedTo = (int)$assignedToInput;
        $stmt = $db->prepare("INSERT INTO tasks (title, description, assigned_to, assigned_by, priority, deadline) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$title, $description, $assignedTo, getUserId(), $priority ?: 'medium', $deadline ?: null]);
        setFlash('success', 'Task created successfully.');
        header('Location: ' . BASE_URL . '/founder/tasks.php');
        exit;
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
    $deadline = post('deadline');
    $status = post('status');
    
    if (empty($title) || empty($assignedTo)) {
        setFlash('error', 'Task title and assignee are required.');
    } else {
        $completedAt = ($status === 'completed') ? date('Y-m-d H:i:s') : null;
        $stmt = $db->prepare("UPDATE tasks SET title=?, description=?, assigned_to=?, priority=?, deadline=?, status=?, completed_at=?, updated_at=NOW() WHERE id=?");
        $stmt->execute([$title, $description, $assignedTo, $priority, $deadline ?: null, $status, $completedAt, $taskId]);
        setFlash('success', 'Task updated successfully.');
        header('Location: ' . BASE_URL . '/founder/tasks.php');
        exit;
    }
}

// Handle task delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $taskId = (int)$_GET['delete'];
    $db->prepare("DELETE FROM tasks WHERE id = ?")->execute([$taskId]);
    setFlash('success', 'Task deleted.');
    header('Location: ' . BASE_URL . '/founder/tasks.php');
    exit;
}

// Filters
$filterStatus = get('status');
$filterPriority = get('priority');
$filterAssignee = get('assigned_to');
$page = max(1, (int)get('page', '1'));

$where = ['1=1'];
$params = [];

if ($filterStatus) { $where[] = "t.status = ?"; $params[] = $filterStatus; }
if ($filterPriority) { $where[] = "t.priority = ?"; $params[] = $filterPriority; }
if ($filterAssignee) { $where[] = "t.assigned_to = ?"; $params[] = (int)$filterAssignee; }

$whereClause = implode(' AND ', $where);

$countStmt = $db->prepare("SELECT COUNT(*) FROM tasks t WHERE {$whereClause}");
$countStmt->execute($params);
$totalRecords = $countStmt->fetchColumn();
$pagination = paginate($totalRecords, $page);

$stmt = $db->prepare("
    SELECT t.*, u1.name AS assigned_to_name, u1.designation AS assigned_to_designation, u2.name AS assigned_by_name
    FROM tasks t
    JOIN users u1 ON t.assigned_to = u1.id
    JOIN users u2 ON t.assigned_by = u2.id
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
            <div class="empty-state-icon">✅</div>
            <div class="empty-state-title">No tasks found</div>
            <div class="empty-state-text">Create a task or adjust your filters.</div>
        </div>
    </div>
<?php else: ?>
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Task</th>
                    <th>Assigned To</th>
                    <th>Priority</th>
                    <th>Status</th>
                    <th>Deadline</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tasks as $task): ?>
                    <?php $overdue = isOverdue($task['deadline'], $task['status']); ?>
                    <tr>
                        <td>
                            <div class="table-user-name"><?php echo e($task['title']); ?></div>
                            <div class="table-user-email">by <?php echo e($task['assigned_by_name']); ?> • <?php echo formatDate($task['created_at']); ?></div>
                        </td>
                        <td>
                            <div><?php echo e($task['assigned_to_name']); ?></div>
                            <small class="text-muted" style="font-size: var(--text-xs);"><?php echo e($task['assigned_to_designation'] ?: '—'); ?></small>
                        </td>
                        <td><span class="badge <?php echo priorityBadge($task['priority']); ?>"><?php echo priorityLabel($task['priority']); ?></span></td>
                        <td>
                            <?php if ($overdue): ?>
                                <span class="badge badge-overdue">Overdue</span>
                            <?php else: ?>
                                <span class="badge <?php echo taskStatusBadge($task['status']); ?>"><?php echo taskStatusLabel($task['status']); ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo formatDate($task['deadline']); ?></td>
                        <td>
                            <div class="table-actions">
                                <a href="<?php echo BASE_URL; ?>/founder/tasks.php?edit=<?php echo $task['id']; ?>" class="btn btn-ghost btn-sm" title="Edit">✏️</a>
                                <a href="<?php echo BASE_URL; ?>/founder/tasks.php?delete=<?php echo $task['id']; ?>" class="btn btn-ghost btn-sm" data-confirm="Delete this task permanently?" title="Delete">🗑️</a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <?php
    $qs = http_build_query(array_filter(['status' => $filterStatus, 'priority' => $filterPriority, 'assigned_to' => $filterAssignee]));
    echo renderPagination($pagination, BASE_URL . '/founder/tasks.php?' . $qs);
    ?>
<?php endif; ?>

<!-- Create Task Modal -->
<div class="modal-overlay" id="create-task-modal">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">Create New Task</h3>
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
                        <select name="assigned_to" class="form-select" required>
                            <option value="">Select Employee</option>
                            <optgroup label="Entire Teams">
                                <?php 
                                $managers = array_filter($allUsers, fn($u) => $u['role'] === 'manager');
                                foreach ($managers as $m): 
                                ?>
                                    <option value="team_<?php echo $m['id']; ?>">Assign to <?php echo e($m['name']); ?>'s Team</option>
                                <?php endforeach; ?>
                            </optgroup>
                            <optgroup label="Individual Employees">
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
                <div class="form-group">
                    <label class="form-label">Deadline</label>
                    <input type="date" name="deadline" class="form-input">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('create-task-modal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Create Task</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Task Modal -->
<?php if ($editTask): ?>
<div class="modal-overlay active" id="edit-task-modal">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">Edit Task</h3>
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
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="todo" <?php echo $editTask['status'] === 'todo' ? 'selected' : ''; ?>>To Do</option>
                            <option value="in_progress" <?php echo $editTask['status'] === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                            <option value="completed" <?php echo $editTask['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Deadline</label>
                        <input type="date" name="deadline" class="form-input" value="<?php echo e($editTask['deadline']); ?>">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <a href="<?php echo BASE_URL; ?>/founder/tasks.php" class="btn btn-outline">Cancel</a>
                <button type="submit" class="btn btn-primary">Update Task</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
