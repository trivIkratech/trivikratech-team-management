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

// Handle Approve / Deny / Cancel Action
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $leaveId = (int)$_GET['id'];
    
    if (in_array($action, ['approve', 'deny', 'cancel'])) {
        $status = ($action === 'approve') ? 'approved' : (($action === 'cancel') ? 'cancelled' : 'denied');
        
        try {
            $stmt = $db->prepare("UPDATE leaves SET status = ?, actioned_by = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$status, $founderId, $leaveId]);

            $lStmt = $db->prepare("SELECT user_id, leave_type FROM leaves WHERE id = ?");
            $lStmt->execute([$leaveId]);
            $leaveRow = $lStmt->fetch();
            if ($leaveRow) {
                $notifTitle = ($status === 'approved') ? '✅ Leave Approved' : (($status === 'cancelled') ? '🚫 Leave Cancelled' : '❌ Leave Rejected');
                $notifMsg = 'Your ' . ucfirst($leaveRow['leave_type']) . ' leave request was ' . $status . ' by Founder.';
                $notifType = ($status === 'approved') ? 'success' : 'danger';

                createNotification(
                    (int)$leaveRow['user_id'],
                    $notifTitle,
                    $notifMsg,
                    BASE_URL . '/employee/leaves.php',
                    $notifType
                );
            }

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

// Fetch stats for founder
$totalPending = $db->query("SELECT COUNT(*) FROM leaves WHERE status = 'pending'")->fetchColumn();
$totalApproved = $db->query("SELECT COUNT(*) FROM leaves WHERE status = 'approved'")->fetchColumn();
$totalDenied = $db->query("SELECT COUNT(*) FROM leaves WHERE status = 'denied'")->fetchColumn();
$totalAll = $db->query("SELECT COUNT(*) FROM leaves")->fetchColumn();

$pageTitle = 'Leave Requests Control';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Leave Requests Control</h1>
        <p class="page-subtitle">Review, manage, and approve/deny company-wide leave applications</p>
    </div>
</div>

<!-- Summary Stat Cards -->
<div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 14px; margin-bottom: 20px; width: 100%;">
    <div class="stat-card accent-orange fade-in" style="padding: 16px;">
        <div class="stat-icon" style="width: 40px; height: 40px; font-size: 16px;"><i class="fa-solid fa-hourglass-half"></i></div>
        <div class="stat-content">
            <div class="stat-value" style="font-size: 22px;"><?php echo $totalPending; ?></div>
            <div class="stat-label" style="font-size: 12px;">Pending Approval</div>
        </div>
    </div>
    <div class="stat-card accent-green fade-in stagger-1" style="padding: 16px;">
        <div class="stat-icon" style="width: 40px; height: 40px; font-size: 16px;"><i class="fa-solid fa-circle-check"></i></div>
        <div class="stat-content">
            <div class="stat-value" style="font-size: 22px;"><?php echo $totalApproved; ?></div>
            <div class="stat-label" style="font-size: 12px;">Approved Leaves</div>
        </div>
    </div>
    <div class="stat-card accent-red fade-in stagger-2" style="padding: 16px;">
        <div class="stat-icon" style="width: 40px; height: 40px; font-size: 16px;"><i class="fa-solid fa-circle-xmark"></i></div>
        <div class="stat-content">
            <div class="stat-value" style="font-size: 22px;"><?php echo $totalDenied; ?></div>
            <div class="stat-label" style="font-size: 12px;">Denied Requests</div>
        </div>
    </div>
    <div class="stat-card accent-blue fade-in stagger-3" style="padding: 16px;">
        <div class="stat-icon" style="width: 40px; height: 40px; font-size: 16px;"><i class="fa-solid fa-calendar-days"></i></div>
        <div class="stat-content">
            <div class="stat-value" style="font-size: 22px;"><?php echo $totalAll; ?></div>
            <div class="stat-label" style="font-size: 12px;">Total Applications</div>
        </div>
    </div>
</div>

<!-- Filters Bar -->
<div class="card" style="margin-bottom: 20px; padding: 12px 16px;">
    <form method="GET" style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px; margin: 0;">
        <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap; flex: 1;">
            <span style="font-size: 12px; font-weight: 600; color: var(--color-text-secondary); display: flex; align-items: center; gap: 6px;">
                <i class="fa-solid fa-filter"></i> Filters:
            </span>
            <select name="status" class="form-select" onchange="this.form.submit()" style="width: auto; min-width: 130px; height: 34px; padding: 4px 10px; font-size: 12px;">
                <option value="">All Statuses</option>
                <option value="pending" <?php echo $filterStatus === 'pending' ? 'selected' : ''; ?>>⏳ Pending</option>
                <option value="approved" <?php echo $filterStatus === 'approved' ? 'selected' : ''; ?>>✓ Approved</option>
                <option value="cancelled" <?php echo $filterStatus === 'cancelled' ? 'selected' : ''; ?>>🚫 Cancelled</option>
                <option value="denied" <?php echo $filterStatus === 'denied' ? 'selected' : ''; ?>>✗ Denied</option>
            </select>
            <select name="leave_type" class="form-select" onchange="this.form.submit()" style="width: auto; min-width: 140px; height: 34px; padding: 4px 10px; font-size: 12px;">
                <option value="">All Leave Types</option>
                <option value="casual" <?php echo $filterType === 'casual' ? 'selected' : ''; ?>>Casual Leave</option>
                <option value="sick" <?php echo $filterType === 'sick' ? 'selected' : ''; ?>>Sick Leave</option>
                <option value="paid" <?php echo $filterType === 'paid' ? 'selected' : ''; ?>>Paid Leave</option>
                <option value="unpaid" <?php echo $filterType === 'unpaid' ? 'selected' : ''; ?>>Unpaid Leave</option>
            </select>
            <select name="role" class="form-select" onchange="this.form.submit()" style="width: auto; min-width: 140px; height: 34px; padding: 4px 10px; font-size: 12px;">
                <option value="">All Roles</option>
                <option value="employee" <?php echo $filterRole === 'employee' ? 'selected' : ''; ?>>Employee</option>
                <option value="manager" <?php echo $filterRole === 'manager' ? 'selected' : ''; ?>>Manager</option>
                <option value="hr" <?php echo $filterRole === 'hr' ? 'selected' : ''; ?>>HR</option>
            </select>
        </div>
        <?php if ($filterStatus || $filterType || $filterRole): ?>
            <a href="<?php echo BASE_URL; ?>/founder/leaves.php" class="btn btn-sm btn-outline" style="height: 32px; font-size: 12px; display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px;">
                <i class="fa-solid fa-rotate-left"></i> Reset
            </a>
        <?php endif; ?>
    </form>
</div>

<?php if (empty($leaves)): ?>
    <div class="card">
        <div class="empty-state">
            <div class="empty-state-icon"><i class="fa-solid fa-umbrella-beach"></i></div>
            <div class="empty-state-title">No leave requests found</div>
            <div class="empty-state-text">No leave applications match the selected filter criteria.</div>
        </div>
    </div>
<?php else: ?>
    <div class="table-container fade-in" style="width: 100%; overflow-x: auto;">
        <table class="data-table" style="width: 100%;">
            <thead>
                <tr>
                    <th style="min-width: 160px;">Applicant</th>
                    <th style="min-width: 90px; text-align: center;">Leave Type</th>
                    <th style="min-width: 140px;">Dates & Duration</th>
                    <th style="min-width: 150px;">Reason & Doc</th>
                    <th style="min-width: 100px; text-align: center;">Status</th>
                    <th style="min-width: 130px; text-align: right;">Action</th>
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
                            <div class="table-user" style="display: flex; align-items: center; gap: 8px;">
                                <div class="table-user-avatar" style="width: 30px; height: 30px; font-size: 11px; flex-shrink: 0;"><?php echo e(getInitials($leave['employee_name'])); ?></div>
                                <div style="min-width: 0;">
                                    <div class="table-user-name" style="font-weight: 600; font-size: 13px; line-height: 1.2;"><?php echo e($leave['employee_name']); ?></div>
                                    <div style="font-size: 11px; display: flex; align-items: center; gap: 6px; margin-top: 2px;">
                                        <span class="badge <?php echo roleBadge($leave['employee_role']); ?>" style="padding: 1px 5px; font-size: 9px; line-height: 1.2;">
                                            <?php echo ucfirst(e($leave['employee_role'])); ?>
                                        </span>
                                        <span style="color: var(--color-text-muted); font-size: 11px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 110px;">
                                            <?php echo e($leave['employee_email']); ?>

                                        </span>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td style="text-align: center;">
                            <span class="badge badge-info" style="white-space: nowrap; font-size: 11px; padding: 2px 7px;">
                                <?php echo ucfirst(e($leave['leave_type'])); ?>
                            </span>
                        </td>
                        <td>
                            <div style="white-space: nowrap; font-size: 12px; font-weight: 600;">
                                <?php echo formatDate($leave['start_date']); ?>
                                <?php if ($leave['start_date'] !== $leave['end_date']): ?>
                                    <span style="font-weight: 400; color: var(--color-text-muted);">to</span> <?php echo formatDate($leave['end_date']); ?>
                                <?php endif; ?>
                            </div>
                            <div style="margin-top: 2px;">
                                <span class="badge badge-outline" style="font-size: 10px; padding: 1px 5px;">
                                    <?php echo $days; ?> day<?php echo $days > 1 ? 's' : ''; ?>
                                </span>
                            </div>
                        </td>
                        <td>
                            <div style="font-size: 12px; line-height: 1.3; max-width: 200px;" title="<?php echo e($leave['reason']); ?>">
                                <span style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap; display: inline-block; max-width: 140px; vertical-align: middle;">
                                    <?php echo e($leave['reason']); ?>
                                </span>
                                <?php if ($leave['prescription_doc']): ?>
                                    <a href="<?php echo BASE_URL . '/' . e($leave['prescription_doc']); ?>" target="_blank" class="badge badge-purple" style="white-space: nowrap; padding: 1px 6px; font-size: 10px; text-decoration: none; vertical-align: middle;" title="View Attached Prescription">
                                        <i class="fa-solid fa-file-medical"></i> Doc
                                    </a>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td style="text-align: center;">
                            <?php if ($leave['status'] === 'pending'): ?>
                                <span class="badge badge-warning" style="white-space: nowrap; font-size: 11px;"><i class="fa-solid fa-hourglass-half"></i> Pending</span>
                            <?php elseif ($leave['status'] === 'approved'): ?>
                                <span class="badge badge-success" style="white-space: nowrap; font-size: 11px;"><i class="fa-solid fa-circle-check"></i> Approved</span>
                            <?php elseif ($leave['status'] === 'cancelled'): ?>
                                <span class="badge badge-danger" style="white-space: nowrap; font-size: 11px; background: rgba(239, 68, 68, 0.15); color: var(--color-danger); border: 1px solid var(--color-danger);"><i class="fa-solid fa-ban"></i> Cancelled</span>
                            <?php else: ?>
                                <span class="badge badge-danger" style="white-space: nowrap; font-size: 11px;"><i class="fa-solid fa-circle-xmark"></i> Denied</span>
                            <?php endif; ?>
                            <?php if ($leave['actioned_by_name']): ?>
                                <div style="font-size: 10px; color: var(--color-text-muted); margin-top: 2px; white-space: nowrap;">
                                    by <?php echo e($leave['actioned_by_name']); ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td style="text-align: right;">
                            <?php if ($leave['status'] === 'pending'): ?>
                                <div class="table-actions" style="display: flex; gap: 4px; justify-content: flex-end; align-items: center;">
                                    <a href="?action=approve&id=<?php echo $leave['id']; ?>" class="btn btn-success btn-sm" style="white-space: nowrap; padding: 3px 8px; font-size: 11px; display: inline-flex; align-items: center; gap: 3px;" onclick="return confirm('Approve this leave application?')">
                                        <i class="fa-solid fa-check"></i> Approve
                                    </a>
                                    <a href="?action=deny&id=<?php echo $leave['id']; ?>" class="btn btn-danger btn-sm" style="white-space: nowrap; padding: 3px 8px; font-size: 11px; display: inline-flex; align-items: center; gap: 3px;" onclick="return confirm('Deny this leave application?')">
                                        <i class="fa-solid fa-xmark"></i> Deny
                                    </a>
                                </div>
                            <?php elseif ($leave['status'] === 'approved'): ?>
                                <div class="table-actions" style="display: flex; gap: 4px; justify-content: flex-end; align-items: center;">
                                    <a href="?action=cancel&id=<?php echo $leave['id']; ?>" class="btn btn-danger btn-sm" style="white-space: nowrap; padding: 3px 8px; font-size: 11px; display: inline-flex; align-items: center; gap: 3px;" onclick="return confirm('Cancel this approved leave?')">
                                        <i class="fa-solid fa-ban"></i> Cancel Leave
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="table-actions" style="display: flex; gap: 4px; justify-content: flex-end; align-items: center;">
                                    <a href="?action=approve&id=<?php echo $leave['id']; ?>" class="btn btn-ghost btn-sm" style="white-space: nowrap; padding: 3px 8px; font-size: 11px; border: 1px solid var(--color-border);" onclick="return confirm('Re-approve this leave application?')">
                                        <i class="fa-solid fa-rotate-left"></i> Re-Approve
                                    </a>
                                </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
