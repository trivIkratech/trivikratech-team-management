<?php
/**
 * Sales Counsellor — Follow-ups & Assigned Tasks
 * 
 * Shows both scheduled follow-up calls and operational tasks assigned by management.
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/middleware.php';

ensureSalesTablesExist();
requireRole([ROLE_SALES_COUNSELLOR]);

$db = getDB();
$userId = getUserId();
$today = today();

$filterStatus = get('status', '');

// Fetch project / general tasks
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

// Fetch Pending Scheduled Follow-up Calls
$followupLeads = getLeads([
    'assigned_to' => $userId,
    'status' => 'interested'
], 1, 15)['data'];

$pageTitle = 'Follow-ups & Tasks';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header" style="margin-bottom: var(--space-5);">
    <div>
        <h1 class="page-title"><i class="fa-solid fa-list-check" style="color: var(--color-primary); margin-right: 8px;"></i>Follow-ups & Tasks</h1>
        <p class="page-subtitle">Track your daily outreach calls and assigned management tasks.</p>
    </div>
</div>

<!-- Scheduled Outreach Follow-ups Section -->
<div class="card fade-in" style="margin-bottom: var(--space-6); padding: 0; overflow: hidden; border: 1px solid var(--color-border);">
    <div style="display: flex; justify-content: space-between; align-items: center; padding: 16px 20px; border-bottom: 1px solid var(--color-border); background: var(--color-bg-secondary);">
        <div style="display: flex; align-items: center; gap: 10px;">
            <div style="width: 34px; height: 34px; border-radius: var(--radius-md); background: rgba(99, 102, 241, 0.15); display: flex; align-items: center; justify-content: center; color: var(--color-primary);">
                <i class="fa-solid fa-phone-volume"></i>
            </div>
            <div>
                <h3 style="margin: 0; font-size: 15px; font-weight: 700; color: var(--color-text-white);">Active In-Progress Follow-up Calls</h3>
                <span style="font-size: 12px; color: var(--color-text-muted);"><?php echo count($followupLeads); ?> scheduled callbacks needing your attention</span>
            </div>
        </div>
        <a href="<?php echo BASE_URL; ?>/sales/leads.php?status=interested" class="btn btn-sm btn-outline-primary" style="display: inline-flex; align-items: center; gap: 6px;">
            <i class="fa-solid fa-arrow-up-right-from-square"></i> View All in CRM
        </a>
    </div>

    <?php if (empty($followupLeads)): ?>
        <div style="text-align: center; padding: 40px 20px; color: var(--color-text-muted);">
            <div style="width: 48px; height: 48px; border-radius: 50%; background: var(--color-bg-tertiary); display: inline-flex; align-items: center; justify-content: center; margin-bottom: 12px; color: #10b981;">
                <i class="fa-solid fa-circle-check" style="font-size: 22px;"></i>
            </div>
            <p style="margin: 0; font-weight: 500;">No pending follow-ups scheduled right now.</p>
        </div>
    <?php else: ?>
        <div class="data-table-container" style="border: none; border-radius: 0; margin: 0;">
            <table class="data-table" style="width: 100%;">
                <thead>
                    <tr>
                        <th style="width: 25%; padding-left: 20px;">Prospect</th>
                        <th style="width: 30%;">Target Offering</th>
                        <th style="width: 20%;">Scheduled Follow-Up</th>
                        <th style="width: 25%; text-align: right; padding-right: 20px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($followupLeads as $fl): ?>
                        <tr>
                            <td style="padding-left: 20px;">
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <div style="width: 32px; height: 32px; border-radius: 50%; background: rgba(99, 102, 241, 0.12); color: var(--color-primary-light); font-weight: 700; font-size: 11px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                        <?php echo strtoupper(substr($fl['name'] ?? 'L', 0, 2)); ?>
                                    </div>
                                    <div style="display: flex; flex-direction: column; gap: 2px;">
                                        <span style="font-weight: 600; color: var(--color-text-white); font-size: 13px;"><?php echo e($fl['name']); ?></span>
                                        <span style="font-size: 11px; color: var(--color-text-muted); font-family: monospace;">
                                            <i class="fa-solid fa-phone" style="font-size: 9px; margin-right: 3px;"></i><?php echo e($fl['phone']); ?>
                                        </span>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div style="display: flex; flex-direction: column; align-items: flex-start; gap: 4px;">
                                    <span class="badge <?php echo $fl['offering_type'] === 'course' ? 'badge-primary' : 'badge-warning'; ?>" style="font-size: 10px; padding: 2px 6px; font-weight: 600; text-transform: uppercase;">
                                        <i class="fa-solid <?php echo $fl['offering_type'] === 'course' ? 'fa-graduation-cap' : 'fa-laptop-code'; ?>" style="margin-right: 3px;"></i>
                                        <?php echo ucfirst($fl['offering_type']); ?>
                                    </span>
                                    <span style="font-weight: 500; font-size: 12px; color: var(--color-text);"><?php echo e($fl['course_service_name']); ?></span>
                                </div>
                            </td>
                            <td>
                                <?php if ($fl['next_followup_date']): ?>
                                    <div style="display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; background: var(--color-bg-tertiary); border: 1px solid var(--color-border); border-radius: var(--radius-sm);">
                                        <i class="fa-regular fa-calendar-check" style="color: var(--color-primary); font-size: 11px;"></i>
                                        <span style="font-size: 12px; font-weight: 600; color: var(--color-text-white);">
                                            <?php echo formatDate($fl['next_followup_date'], 'd M, h:i A'); ?>
                                        </span>
                                    </div>
                                <?php else: ?>
                                    <span style="color: var(--color-text-muted); font-size: 12px;">Not scheduled</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: right; padding-right: 20px;">
                                <div style="display: inline-flex; align-items: center; justify-content: flex-end; gap: 6px;">
                                    <a href="tel:<?php echo e($fl['phone']); ?>" class="btn btn-sm btn-outline-primary" style="padding: 5px 9px; font-size: 11px;" title="Direct Call">
                                        <i class="fa-solid fa-phone"></i> Call
                                    </a>
                                    <button type="button" class="btn btn-sm btn-outline-success" style="padding: 5px 9px; font-size: 11px; cursor: pointer;" title="Open WhatsApp" onclick="openWhatsAppModal(<?php echo (int)$fl['id']; ?>, '<?php echo addslashes(e($fl['name'])); ?>', '<?php echo e($fl['phone']); ?>', 1, '<?php echo addslashes(e($fl['course_service_name'] ?? 'UI/UX Design')); ?>')">
                                        <i class="fa-brands fa-whatsapp"></i> WhatsApp
                                    </button>
                                    <a href="<?php echo BASE_URL; ?>/sales/leads.php?lead_id=<?php echo (int)$fl['id']; ?>" class="btn btn-sm btn-primary" style="padding: 5px 10px; font-size: 11px;">
                                        <i class="fa-solid fa-clock-rotate-left"></i> Log Activity
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Assigned Operational Tasks Section -->
<div class="card fade-in" style="border: 1px solid var(--color-border);">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--space-4); border-bottom: 1px solid var(--color-border); padding-bottom: var(--space-3); flex-wrap: wrap; gap: 12px;">
        <div style="display: flex; align-items: center; gap: 10px;">
            <div style="width: 34px; height: 34px; border-radius: var(--radius-md); background: rgba(59, 130, 246, 0.15); display: flex; align-items: center; justify-content: center; color: #3b82f6;">
                <i class="fa-solid fa-list-check"></i>
            </div>
            <div>
                <h3 style="margin: 0; font-size: 15px; font-weight: 700; color: var(--color-text-white);">Operational Tasks Assigned by Management</h3>
                <span style="font-size: 12px; color: var(--color-text-muted);">Manage your action items, deliverables, and team goals</span>
            </div>
        </div>
        <form method="GET" style="display: flex; align-items: center; gap: 8px;">
            <select name="status" class="form-select" style="font-size: 12px; padding: 6px 12px; border-radius: var(--radius-md); min-width: 140px;" onchange="this.form.submit()">
                <option value="">All Tasks (<?php echo count($tasks); ?>)</option>
                <option value="todo" <?php echo $filterStatus === 'todo' ? 'selected' : ''; ?>>To Do</option>
                <option value="in_progress" <?php echo $filterStatus === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                <option value="completed" <?php echo $filterStatus === 'completed' ? 'selected' : ''; ?>>Completed</option>
            </select>
        </form>
    </div>

    <?php if (empty($tasks)): ?>
        <div style="text-align: center; padding: 36px 20px; color: var(--color-text-muted);">
            <div style="width: 48px; height: 48px; border-radius: 50%; background: var(--color-bg-tertiary); display: inline-flex; align-items: center; justify-content: center; margin-bottom: 10px; color: #10b981;">
                <i class="fa-solid fa-check-double" style="font-size: 20px;"></i>
            </div>
            <p style="margin: 0; font-size: 13px; font-weight: 500;">No management tasks assigned right now.</p>
        </div>
    <?php else: ?>
        <div style="display: grid; gap: 12px;">
            <?php foreach ($tasks as $task): ?>
                <div style="padding: 16px; background: var(--color-bg-secondary); border-radius: var(--radius-md); border: 1px solid var(--color-border); border-left: 4px solid <?php echo $task['status'] === 'completed' ? '#10b981' : ($task['status'] === 'in_progress' ? '#f59e0b' : 'var(--color-primary)'); ?>;">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 8px; margin-bottom: 8px;">
                        <h4 style="margin: 0; font-size: 14px; font-weight: 600; color: var(--color-text-white);"><?php echo e($task['title']); ?></h4>
                        <div style="display: flex; gap: 6px; align-items: center;">
                            <?php echo getPriorityBadge($task['priority']); ?>
                            <?php echo getStatusBadge($task['status']); ?>
                        </div>
                    </div>
                    <?php if (!empty($task['description'])): ?>
                        <p style="margin: 0 0 10px 0; font-size: 13px; color: var(--color-text-secondary); line-height: 1.5;"><?php echo nl2br(e($task['description'])); ?></p>
                    <?php endif; ?>
                    <div style="display: flex; justify-content: space-between; align-items: center; font-size: 12px; color: var(--color-text-muted); flex-wrap: wrap; gap: 8px; border-top: 1px dashed var(--color-border); padding-top: 10px; margin-top: 6px;">
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <span><i class="fa-solid fa-user-tie" style="margin-right: 4px; color: var(--color-text-secondary);"></i> Assigned by: <strong style="color: var(--color-text);"><?php echo e($task['assigned_by_name']); ?></strong></span>
                            <span><i class="fa-regular fa-clock" style="margin-right: 4px; color: var(--color-text-secondary);"></i> Deadline: <strong style="color: var(--color-text);"><?php echo formatDate($task['deadline']); ?></strong></span>
                        </div>
                        <div>
                            <?php if ($task['status'] !== 'completed'): ?>
                                <button type="button" class="btn btn-sm btn-primary" style="padding: 4px 10px; font-size: 11px;" onclick="markTaskStatus(<?php echo (int)$task['id']; ?>, 'completed')">
                                    <i class="fa-solid fa-check"></i> Mark Completed
                                </button>
                            <?php else: ?>
                                <span style="color: #10b981; font-weight: 600; display: inline-flex; align-items: center; gap: 4px;"><i class="fa-solid fa-circle-check"></i> Completed</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
function markTaskStatus(taskId, status) {
    const formData = new FormData();
    formData.append('action', 'update_status');
    formData.append('task_id', taskId);
    formData.append('status', status);
    formData.append('csrf_token', '<?php echo csrfToken(); ?>');

    fetch('<?php echo BASE_URL; ?>/api/tasks.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            window.location.reload();
        } else {
            alert(data.message || 'Error updating task.');
        }
    })
    .catch(err => alert('Request failed.'));
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
