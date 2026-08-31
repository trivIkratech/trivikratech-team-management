<?php
/**
 * Founder Leaves Review Console
 * 
 * Allows the Founder to view and approve/deny all leave requests 
 * across the company (from Employees, Managers, and HR).
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/middleware.php';

requireRole([ROLE_FOUNDER]);

$db = getDB();
$founderId = getUserId();

// Handle Approve / Deny Action
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $leaveId = (int)$_GET['id'];
    
    if (in_array($action, ['approve', 'deny'])) {
        $status = ($action === 'approve') ? 'approved' : 'denied';
        
        try {
            $stmt = $db->prepare("UPDATE leaves SET status = ?, actioned_by = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$status, $founderId, $leaveId]);
            setFlash('success', 'Leave request status updated to ' . $status);
        } catch (PDOException $e) {
            error_log("Error founder actioning leave: " . $e->getMessage());
            setFlash('error', 'A database error occurred.');
        }
        
        header('Location: ' . BASE_URL . '/founder/leaves.php');
        exit;
    }
}

// Filters
$filterStatus = get('status');
$filterType = get('leave_type');
$filterRole = get('role');

$where = ['1=1'];
$params = [];

if ($filterStatus) {
    $where[] = "l.status = ?";
    $params[] = $filterStatus;
}
if ($filterType) {
    $where[] = "l.leave_type = ?";
    $params[] = $filterType;
}
if ($filterRole) {
    $where[] = "u.role = ?";
    $params[] = $filterRole;
}

$whereClause = implode(' AND ', $where);

// Fetch all leaves
$stmt = $db->prepare("
    SELECT l.*, u.name AS employee_name, u.email AS employee_email, u.role AS employee_role, a.name AS actioned_by_name
    FROM leaves l 
    JOIN users u ON l.user_id = u.id 
    LEFT JOIN users a ON l.actioned_by = a.id
    WHERE {$whereClause}
    ORDER BY CASE l.status WHEN 'pending' THEN 1 WHEN 'approved' THEN 2 WHEN 'denied' THEN 3 END, l.created_at DESC
");
$stmt->execute($params);
$leaves = $stmt->fetchAll();

$pageTitle = 'Leave Requests Control';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Leave Requests Control</h1>
        <p class="page-subtitle"><?php echo count($leaves); ?> leave request(s) total across the company</p>
    </div>
</div>

<!-- Filters -->
<form method="GET" class="filter-bar">
    <select name="status" class="form-select" onchange="this.form.submit()">
        <option value="">All Statuses</option>
        <option value="pending" <?php echo $filterStatus === 'pending' ? 'selected' : ''; ?>>Pending</option>
        <option value="approved" <?php echo $filterStatus === 'approved' ? 'selected' : ''; ?>>Approved</option>
        <option value="denied" <?php echo $filterStatus === 'denied' ? 'selected' : ''; ?>>Denied</option>
    </select>
    <select name="leave_type" class="form-select" onchange="this.form.submit()">
        <option value="">All Leave Types</option>
        <option value="casual" <?php echo $filterType === 'casual' ? 'selected' : ''; ?>>Casual Leave</option>
        <option value="sick" <?php echo $filterType === 'sick' ? 'selected' : ''; ?>>Sick Leave</option>
        <option value="paid" <?php echo $filterType === 'paid' ? 'selected' : ''; ?>>Paid Leave</option>
        <option value="unpaid" <?php echo $filterType === 'unpaid' ? 'selected' : ''; ?>>Unpaid Leave</option>
    </select>
    <select name="role" class="form-select" onchange="this.form.submit()">
        <option value="">All Applicant Roles</option>
        <option value="employee" <?php echo $filterRole === 'employee' ? 'selected' : ''; ?>>Employee</option>
        <option value="manager" <?php echo $filterRole === 'manager' ? 'selected' : ''; ?>>Manager</option>
        <option value="hr" <?php echo $filterRole === 'hr' ? 'selected' : ''; ?>>HR</option>
    </select>
    <?php if ($filterStatus || $filterType || $filterRole): ?>
        <a href="<?php echo BASE_URL; ?>/founder/leaves.php" class="btn btn-sm btn-outline">Clear Filters</a>
    <?php endif; ?>
</form>

<?php if (empty($leaves)): ?>
    <div class="card">
        <div class="empty-state">
            <div class="empty-state-icon">🌴</div>
            <div class="empty-state-title">No leave requests found</div>
            <div class="empty-state-text">No requests match the selected criteria.</div>
        </div>
    </div>
<?php else: ?>
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Applicant</th>
                    <th>Role</th>
                    <th>Leave Type</th>
                    <th>Dates</th>
                    <th>Duration</th>
                    <th>Reason</th>
                    <th>Document</th>
                    <th>Status</th>
                    <th>Actioned By</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($leaves as $leave): ?>
                    <?php 
                    $in = new DateTime($leave['start_date']);
                    $out = new DateTime($leave['end_date']);
                    $days = $in->diff($out)->days + 1;
                    ?>
                    <tr>
                        <td>
                            <div class="table-user">
                                <div class="table-user-avatar"><?php echo e(getInitials($leave['employee_name'])); ?></div>
                                <div>
                                    <div class="table-user-name"><?php echo e($leave['employee_name']); ?></div>
                                    <div class="table-user-email"><?php echo e($leave['employee_email']); ?></div>
                                </div>
                            </div>
                        </td>
                        <td><span class="badge <?php echo roleBadge($leave['employee_role']); ?>"><?php echo ucfirst(e($leave['employee_role'])); ?></span></td>
                        <td><span class="badge badge-info"><?php echo ucfirst(e($leave['leave_type'])); ?></span></td>
                        <td><?php echo formatDate($leave['start_date']); ?> - <?php echo formatDate($leave['end_date']); ?></td>
                        <td><strong><?php echo $days; ?> day(s)</strong></td>
                        <td style="max-width: 180px;" class="truncate" title="<?php echo e($leave['reason']); ?>"><?php echo e($leave['reason']); ?></td>
                        <td>
                            <?php if ($leave['prescription_doc']): ?>
                                <a href="<?php echo BASE_URL . '/' . e($leave['prescription_doc']); ?>" target="_blank" class="btn btn-sm btn-outline">👁️ View Doc</a>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($leave['status'] === 'pending'): ?>
                                <span class="badge badge-warning">⏳ Pending</span>
                            <?php elseif ($leave['status'] === 'approved'): ?>
                                <span class="badge badge-success">✅ Approved</span>
                            <?php else: ?>
                                <span class="badge badge-danger">❌ Denied</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo $leave['actioned_by_name'] ? e($leave['actioned_by_name']) : '<span class="text-muted">—</span>'; ?></td>
                        <td>
                            <?php if ($leave['status'] === 'pending'): ?>
                                <div class="table-actions" style="gap: 6px;">
                                    <a href="?action=approve&id=<?php echo $leave['id']; ?>" class="btn btn-success btn-sm">Approve</a>
                                    <a href="?action=deny&id=<?php echo $leave['id']; ?>" class="btn btn-danger btn-sm">Deny</a>
                                </div>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
