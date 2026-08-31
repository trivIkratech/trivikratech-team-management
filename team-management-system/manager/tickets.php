<?php
/**
 * Manager Support Tickets View
 * 
 * Allows managers to view and resolve support tickets sent to 'manager' 
 * by their assigned team members.
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/middleware.php';

requireRole([ROLE_MANAGER]);

$db = getDB();
$managerId = getUserId();

// Handle Ticket Status Resolve
if (isset($_GET['resolve']) && is_numeric($_GET['resolve'])) {
    $ticketId = (int)$_GET['resolve'];
    
    try {
        // Verify the ticket's owner has this manager
        $stmt = $db->prepare("
            SELECT t.id 
            FROM support_tickets t 
            JOIN users u ON t.user_id = u.id 
            WHERE t.id = ? AND u.manager_id = ? AND FIND_IN_SET('manager', t.send_to) > 0
        ");
        $stmt->execute([$ticketId, $managerId]);
        
        if ($stmt->fetch()) {
            $updateStmt = $db->prepare("UPDATE support_tickets SET status = 'resolved' WHERE id = ?");
            $updateStmt->execute([$ticketId]);
            setFlash('success', 'Ticket marked as resolved.');
        } else {
            setFlash('error', 'Unauthorized access or ticket not found.');
        }
        
        header('Location: ' . BASE_URL . '/manager/tickets.php');
        exit;
    } catch (PDOException $e) {
        error_log("Error resolving support ticket: " . $e->getMessage());
        setFlash('error', 'A database error occurred.');
    }
}

// Filters
$filterStatus = get('status');
$filterCategory = get('category');

$where = ["FIND_IN_SET('manager', t.send_to) > 0", "u.manager_id = ?"];
$params = [$managerId];

if ($filterStatus) {
    $where[] = "t.status = ?";
    $params[] = $filterStatus;
}
if ($filterCategory) {
    $where[] = "t.category = ?";
    $params[] = $filterCategory;
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

$pageTitle = 'Team Support Tickets';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Team Support Tickets</h1>
        <p class="page-subtitle"><?php echo count($tickets); ?> ticket(s) received from your team</p>
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
    <?php if ($filterStatus || $filterCategory): ?>
        <a href="<?php echo BASE_URL; ?>/manager/tickets.php" class="btn btn-sm btn-outline">Clear Filters</a>
    <?php endif; ?>
</form>

<?php if (empty($tickets)): ?>
    <div class="card">
        <div class="empty-state">
            <div class="empty-state-icon">🎟️</div>
            <div class="empty-state-title">No support tickets found</div>
            <div class="empty-state-text">Your team hasn't raised any requests under these filters.</div>
        </div>
    </div>
<?php else: ?>
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Employee</th>
                    <th>Category / Subcategory</th>
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
