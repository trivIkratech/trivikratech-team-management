<?php
/**
 * Founder Support Tickets View
 * 
 * Allows Founders to view, filter, and resolve tickets sent to Founder and HR.
 * Anonymous submissions hide employee name/details.
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/middleware.php';

requireRole([ROLE_FOUNDER]);

$db = getDB();

// Handle Ticket Status Resolve
if (isset($_GET['resolve']) && is_numeric($_GET['resolve'])) {
    $ticketId = (int)$_GET['resolve'];
    
    try {
        $stmt = $db->prepare("UPDATE support_tickets SET status = 'resolved' WHERE id = ?");
        $stmt->execute([$ticketId]);
        
        setFlash('success', 'Ticket marked as resolved.');
        header('Location: ' . BASE_URL . '/founder/tickets.php');
        exit;
    } catch (PDOException $e) {
        error_log("Error resolving support ticket: " . $e->getMessage());
        setFlash('error', 'A database error occurred.');
    }
}

// Filters
$filterStatus = get('status');
$filterCategory = get('category');
$filterTarget = get('send_to');

$where = ["(FIND_IN_SET('founder', t.send_to) > 0 OR FIND_IN_SET('hr', t.send_to) > 0)"];
$params = [];

if ($filterStatus) {
    $where[] = "t.status = ?";
    $params[] = $filterStatus;
}
if ($filterCategory) {
    $where[] = "t.category = ?";
    $params[] = $filterCategory;
}
if ($filterTarget) {
    $where[] = "FIND_IN_SET(?, t.send_to) > 0";
    $params[] = $filterTarget;
}

$whereClause = implode(' AND ', $where);

// Fetch tickets
$stmt = $db->prepare("
    SELECT t.*, u.name AS employee_name, u.email AS employee_email
    FROM support_tickets t 
    JOIN users u ON t.user_id = u.id 
    WHERE {$whereClause}
    ORDER BY 
        CASE t.status WHEN 'pending' THEN 1 WHEN 'resolved' THEN 2 END,
        t.created_at DESC
");
$stmt->execute($params);
$tickets = $stmt->fetchAll();

$pageTitle = 'Support Tickets';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Support Tickets</h1>
        <p class="page-subtitle"><?php echo count($tickets); ?> tickets(s) sent to Founder / HR</p>
    </div>
</div>

<!-- Filters -->
<form method="GET" class="filter-bar">
    <select name="status" class="form-select" onchange="this.form.submit()">
        <option value="">All Statuses</option>
        <option value="pending" <?php echo $filterStatus === 'pending' ? 'selected' : ''; ?>>Pending</option>
        <option value="resolved" <?php echo $filterStatus === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
    </select>
    <select name="category" class="form-select" onchange="this.form.submit()">
        <option value="">All Categories</option>
        <option value="HR & Leave" <?php echo $filterCategory === 'HR & Leave' ? 'selected' : ''; ?>>HR & Leave</option>
        <option value="Attendance" <?php echo $filterCategory === 'Attendance' ? 'selected' : ''; ?>>Attendance</option>
        <option value="Payroll" <?php echo $filterCategory === 'Payroll' ? 'selected' : ''; ?>>Payroll</option>
        <option value="Task & Work Support" <?php echo $filterCategory === 'Task & Work Support' ? 'selected' : ''; ?>>Task & Work Support</option>
        <option value="General Request" <?php echo $filterCategory === 'General Request' ? 'selected' : ''; ?>>General Request</option>
        <option value="Complaint / Grievance" <?php echo $filterCategory === 'Complaint / Grievance' ? 'selected' : ''; ?>>Complaint & Grievance</option>
    </select>
    <select name="send_to" class="form-select" onchange="this.form.submit()">
        <option value="">All Recipients</option>
        <option value="founder" <?php echo $filterTarget === 'founder' ? 'selected' : ''; ?>>Founder Only</option>
        <option value="hr" <?php echo $filterTarget === 'hr' ? 'selected' : ''; ?>>HR Only</option>
    </select>
    <?php if ($filterStatus || $filterCategory || $filterTarget): ?>
        <a href="<?php echo BASE_URL; ?>/founder/tickets.php" class="btn btn-sm btn-outline">Clear Filters</a>
    <?php endif; ?>
</form>

<?php if (empty($tickets)): ?>
    <div class="card">
        <div class="empty-state">
            <div class="empty-state-icon">🎟️</div>
            <div class="empty-state-title">No support tickets found</div>
            <div class="empty-state-text">No requests correspond to the selected criteria.</div>
        </div>
    </div>
<?php else: ?>
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Employee</th>
                    <th>Category / Subcategory</th>
                    <th>Sent To</th>
                    <th>Submitted On</th>
                    <th>Status</th>
                    <th>Details</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tickets as $ticket): ?>
                    <tr>
                        <td>
                            <?php if ($ticket['is_anonymous']): ?>
                                <div class="table-user">
                                    <div class="table-user-avatar" style="background: var(--color-purple);">🎭</div>
                                    <div>
                                        <div class="table-user-name">Anonymous Employee</div>
                                        <div class="table-user-email">Identity Hidden</div>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="table-user">
                                    <div class="table-user-avatar"><?php echo e(getInitials($ticket['employee_name'])); ?></div>
                                    <div>
                                        <div class="table-user-name"><?php echo e($ticket['employee_name']); ?></div>
                                        <div class="table-user-email"><?php echo e($ticket['employee_email']); ?></div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div style="font-weight: 600; color: var(--color-text-white);"><?php echo e($ticket['category']); ?></div>
                            <div class="text-muted" style="font-size: var(--text-xs);"><?php echo e($ticket['sub_category']); ?></div>
                        </td>
                        <td>
                            <?php foreach (explode(',', $ticket['send_to']) as $target): ?>
                                <span class="badge badge-secondary" style="margin-right: 4px;"><?php echo strtoupper(trim($target)); ?></span>
                            <?php endforeach; ?>
                        </td>
                        <td><?php echo formatDateTime($ticket['created_at']); ?></td>
                        <td>
                            <?php if ($ticket['status'] === 'pending'): ?>
                                <span class="badge badge-warning">⏳ Pending</span>
                            <?php else: ?>
                                <span class="badge badge-success">✅ Resolved</span>
                            <?php endif; ?>
                        </td>
                        <td style="max-width: 300px; white-space: normal; font-size: var(--text-sm);">
                            <?php echo e($ticket['description']); ?>
                        </td>
                        <td>
                            <?php if ($ticket['status'] === 'pending'): ?>
                                <a href="?resolve=<?php echo $ticket['id']; ?>" class="btn btn-success btn-sm" title="Mark as Resolved">
                                    ✓ Resolve
                                </a>
                            <?php else: ?>
                                <span class="text-muted" style="font-size: var(--text-xs);">N/A</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
