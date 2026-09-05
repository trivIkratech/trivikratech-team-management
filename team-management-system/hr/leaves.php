<?php
/**
 * HR — Leave Management (Leave Requests, Leave Calendar, Leave Balance)
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/middleware.php';

requireRole([ROLE_HR]);

$db = getDB();
$hrId = getUserId();
$tab = get('tab', 'requests'); // 'requests', 'calendar', 'balance'
$formErrors = [];

// Handle HR's Own Leave Application (routes to Founder)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'apply_leave') {
    requireCsrf();
    
    $leaveType = post('leave_type');
    $startDate = post('start_date');
    $endDate = post('end_date');
    $reason = post('reason');
    $prescriptionDoc = null;
    
    if (!in_array($leaveType, ['casual', 'sick', 'paid', 'unpaid'])) $formErrors[] = 'Invalid leave type.';
    if (empty($startDate)) $formErrors[] = 'Select start date.';
    if (empty($endDate)) $formErrors[] = 'Select end date.';
    if (strtotime($startDate) > strtotime($endDate)) $formErrors[] = 'Start date cannot be after end date.';
    if (empty($reason)) $formErrors[] = 'Specify reason.';
    
    if ($leaveType === 'sick' && isset($_FILES['prescription_doc']) && $_FILES['prescription_doc']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['prescription_doc'];
        $uploadDir = __DIR__ . '/../uploads/prescriptions/';
        if (!file_exists($uploadDir)) mkdir($uploadDir, 0755, true);
        $filename = time() . '_' . uniqid() . '_' . basename($file['name']);
        if (move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
            $prescriptionDoc = 'uploads/prescriptions/' . $filename;
        }
    }
    
    if (empty($formErrors)) {
        $stmt = $db->prepare("INSERT INTO leaves (user_id, leave_type, start_date, end_date, reason, prescription_doc, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')");
        $stmt->execute([$hrId, $leaveType, $startDate, $endDate, $reason, $prescriptionDoc]);
        setFlash('success', 'Leave request submitted successfully.');
        header('Location: ' . BASE_URL . '/hr/leaves.php');
        exit;
    }
}

// Handle Approve / Deny / Cancel action for employee leaves
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $leaveId = (int)$_GET['id'];
    
    if (in_array($action, ['approve', 'deny', 'cancel'])) {
        $status = ($action === 'approve') ? 'approved' : (($action === 'cancel') ? 'cancelled' : 'denied');
        $stmt = $db->prepare("UPDATE leaves SET status = ?, actioned_by = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$status, $hrId, $leaveId]);

        $lStmt = $db->prepare("SELECT user_id, leave_type FROM leaves WHERE id = ?");
        $lStmt->execute([$leaveId]);
        $leaveRow = $lStmt->fetch();
        if ($leaveRow) {
            $notifTitle = ($status === 'approved') ? '✅ Leave Approved' : (($status === 'cancelled') ? '🚫 Leave Cancelled' : '❌ Leave Rejected');
            $notifMsg = 'Your ' . ucfirst($leaveRow['leave_type']) . ' leave request was ' . $status . ' by HR.';
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
        header('Location: ' . BASE_URL . '/hr/leaves.php?tab=' . $tab);
        exit;
    }
}

// Fetch all employee leaves
$employeeLeaves = $db->query("
    SELECT l.*, u.name AS employee_name, u.email AS employee_email, u.employee_id
    FROM leaves l 
    JOIN users u ON l.user_id = u.id 
    ORDER BY CASE l.status WHEN 'pending' THEN 1 WHEN 'approved' THEN 2 WHEN 'denied' THEN 3 END, l.created_at DESC
")->fetchAll();

// Fetch HR's own leaves
$stmt = $db->prepare("SELECT * FROM leaves WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$hrId]);
$myLeaves = $stmt->fetchAll();

// Fetch Leave Balances per staff member (Employees & Managers)
$employeesList = $db->query("SELECT id, name, employee_id, designation, role FROM users WHERE role IN ('employee', 'manager') AND status = 'active' ORDER BY role ASC, name ASC")->fetchAll();
$leaveBalances = [];

foreach ($employeesList as $emp) {
    $stmt = $db->prepare("
        SELECT leave_type, SUM(DATEDIFF(end_date, start_date) + 1) as total_days
        FROM leaves 
        WHERE user_id = ? AND status = 'approved'
        GROUP BY leave_type
    ");
    $stmt->execute([$emp['id']]);
    $used = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    $leaveBalances[] = [
        'user' => $emp,
        'casual_used' => $used['casual'] ?? 0,
        'sick_used' => $used['sick'] ?? 0,
        'paid_used' => $used['paid'] ?? 0,
        'unpaid_used' => $used['unpaid'] ?? 0,
        'total_used' => array_sum($used)
    ];
}

// Fetch stats for HR overview
$totalPending = $db->query("SELECT COUNT(*) FROM leaves l JOIN users u ON l.user_id = u.id WHERE l.status = 'pending'")->fetchColumn();
$totalApproved = $db->query("SELECT COUNT(*) FROM leaves l JOIN users u ON l.user_id = u.id WHERE l.status = 'approved'")->fetchColumn();
$activeEmployees = count($employeesList);

$pageTitle = 'HR — Leave Management';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Leave Management</h1>
        <p class="page-subtitle">Track, approve, and analyze employee leaves and balances</p>
    </div>
</div>

<!-- Stats Grid -->
<div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 14px; margin-bottom: 20px; width: 100%;">
    <div class="stat-card accent-orange fade-in" style="padding: 16px;">
        <div class="stat-icon" style="width: 40px; height: 40px; font-size: 16px;"><i class="fa-solid fa-hourglass-half"></i></div>
        <div class="stat-content">
            <div class="stat-value" style="font-size: 22px;"><?php echo $totalPending; ?></div>
            <div class="stat-label" style="font-size: 12px;">Pending Requests</div>
        </div>
    </div>
    <div class="stat-card accent-green fade-in stagger-1" style="padding: 16px;">
        <div class="stat-icon" style="width: 40px; height: 40px; font-size: 16px;"><i class="fa-solid fa-circle-check"></i></div>
        <div class="stat-content">
            <div class="stat-value" style="font-size: 22px;"><?php echo $totalApproved; ?></div>
            <div class="stat-label" style="font-size: 12px;">Approved Leaves</div>
        </div>
    </div>
    <div class="stat-card accent-purple fade-in stagger-2" style="padding: 16px;">
        <div class="stat-icon" style="width: 40px; height: 40px; font-size: 16px;"><i class="fa-solid fa-users"></i></div>
        <div class="stat-content">
            <div class="stat-value" style="font-size: 22px;"><?php echo $activeEmployees; ?></div>
            <div class="stat-label" style="font-size: 12px;">Active Staff</div>
        </div>
    </div>
</div>

<!-- Tabs Bar -->
<div class="nav-tabs" style="display: flex; gap: var(--space-2); border-bottom: 1px solid var(--color-border); margin-bottom: 20px;">
    <a href="?tab=requests" class="tab-item <?php echo $tab === 'requests' ? 'active' : ''; ?>" style="padding: 10px 16px; text-decoration: none; font-weight: 600; border-bottom: 2px solid <?php echo $tab === 'requests' ? 'var(--color-primary)' : 'transparent'; ?>; color: <?php echo $tab === 'requests' ? 'var(--color-primary)' : 'var(--color-text-secondary)'; ?>; display: inline-flex; align-items: center; gap: 6px; font-size: 13px;">
        <i class="fa-solid fa-clipboard-user"></i> Leave Requests
    </a>
    <a href="?tab=calendar" class="tab-item <?php echo $tab === 'calendar' ? 'active' : ''; ?>" style="padding: 10px 16px; text-decoration: none; font-weight: 600; border-bottom: 2px solid <?php echo $tab === 'calendar' ? 'var(--color-primary)' : 'transparent'; ?>; color: <?php echo $tab === 'calendar' ? 'var(--color-primary)' : 'var(--color-text-secondary)'; ?>; display: inline-flex; align-items: center; gap: 6px; font-size: 13px;">
        <i class="fa-solid fa-calendar-days"></i> Leave Calendar
    </a>
    <a href="?tab=balance" class="tab-item <?php echo $tab === 'balance' ? 'active' : ''; ?>" style="padding: 10px 16px; text-decoration: none; font-weight: 600; border-bottom: 2px solid <?php echo $tab === 'balance' ? 'var(--color-primary)' : 'transparent'; ?>; color: <?php echo $tab === 'balance' ? 'var(--color-primary)' : 'var(--color-text-secondary)'; ?>; display: inline-flex; align-items: center; gap: 6px; font-size: 13px;">
        <i class="fa-solid fa-scale-balanced"></i> Leave Balance Overview
    </a>
</div>

<?php if (!empty($formErrors)): ?>
    <div class="alert alert-error">
        <span><?php echo e(implode(' ', $formErrors)); ?></span>
        <button class="alert-close" onclick="this.parentElement.remove()">×</button>
    </div>
<?php endif; ?>

<!-- TAB 1: LEAVE REQUESTS -->
<?php if ($tab === 'requests'): ?>
    <?php if (empty($employeeLeaves)): ?>
        <div class="card">
            <div class="empty-state">
                <div class="empty-state-icon"><i class="fa-solid fa-umbrella-beach"></i></div>
                <div class="empty-state-title">No leave applications found</div>
                <div class="empty-state-text">There are currently no employee leave applications.</div>
            </div>
        </div>
    <?php else: ?>
        <div class="table-container fade-in" style="width: 100%; overflow-x: auto;">
            <table class="data-table" style="width: 100%;">
                <thead>
                    <tr>
                        <th style="min-width: 160px;">Employee</th>
                        <th style="min-width: 90px; text-align: center;">Leave Type</th>
                        <th style="min-width: 140px;">Dates & Duration</th>
                        <th style="min-width: 150px;">Reason & Doc</th>
                        <th style="min-width: 100px; text-align: center;">Status</th>
                        <th style="min-width: 130px; text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($employeeLeaves as $leave): ?>
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
                                        <div style="font-size: 11px; color: var(--color-text-muted); margin-top: 2px;">
                                            <code><?php echo e($leave['employee_id']); ?></code> • <?php echo e($leave['employee_email']); ?>

                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td style="text-align: center;">
                                <span class="badge badge-info" style="white-space: nowrap; font-size: 11px; padding: 2px 7px;"><?php echo ucfirst(e($leave['leave_type'])); ?></span>
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
                            </td>
                            <td style="text-align: right;">
                                <?php if ($leave['status'] === 'pending'): ?>
                                    <div class="table-actions" style="display: flex; gap: 4px; justify-content: flex-end; align-items: center;">
                                        <a href="?action=approve&id=<?php echo $leave['id']; ?>&tab=<?php echo $tab; ?>" class="btn btn-success btn-sm" style="white-space: nowrap; padding: 3px 8px; font-size: 11px; display: inline-flex; align-items: center; gap: 3px;" onclick="return confirm('Approve this leave application?')">
                                            <i class="fa-solid fa-check"></i> Approve
                                        </a>
                                        <a href="?action=deny&id=<?php echo $leave['id']; ?>&tab=<?php echo $tab; ?>" class="btn btn-danger btn-sm" style="white-space: nowrap; padding: 3px 8px; font-size: 11px; display: inline-flex; align-items: center; gap: 3px;" onclick="return confirm('Deny this leave application?')">
                                            <i class="fa-solid fa-xmark"></i> Deny
                                        </a>
                                    </div>
                                <?php elseif ($leave['status'] === 'approved'): ?>
                                    <div class="table-actions" style="display: flex; gap: 4px; justify-content: flex-end; align-items: center;">
                                        <a href="?action=cancel&id=<?php echo $leave['id']; ?>&tab=<?php echo $tab; ?>" class="btn btn-danger btn-sm" style="white-space: nowrap; padding: 3px 8px; font-size: 11px; display: inline-flex; align-items: center; gap: 3px;" onclick="return confirm('Cancel this approved leave?')">
                                            <i class="fa-solid fa-ban"></i> Cancel Leave
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <div class="table-actions" style="display: flex; gap: 4px; justify-content: flex-end; align-items: center;">
                                        <a href="?action=approve&id=<?php echo $leave['id']; ?>&tab=<?php echo $tab; ?>" class="btn btn-ghost btn-sm" style="white-space: nowrap; padding: 3px 8px; font-size: 11px; border: 1px solid var(--color-border);" onclick="return confirm('Re-approve this leave application?')">
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

<!-- TAB 2: LEAVE CALENDAR -->
<?php elseif ($tab === 'calendar'): ?>
    <div class="card fade-in">
        <div class="card-header">
            <h3 class="card-title"><i class="fa-solid fa-calendar-days"></i> Active & Upcoming Employee Leaves</h3>
        </div>
        <?php 
        $activeLeaves = array_filter($employeeLeaves, function($l) {
            return $l['status'] === 'approved' || $l['status'] === 'pending';
        });
        ?>
        <?php if (empty($activeLeaves)): ?>
            <div class="empty-state">
                <div class="empty-state-icon"><i class="fa-solid fa-umbrella-beach"></i></div>
                <div class="empty-state-title">No Active Leaves</div>
                <div class="empty-state-text">There are no approved or pending leaves scheduled.</div>
            </div>
        <?php else: ?>
            <div class="activity-list">
                <?php foreach ($activeLeaves as $al): ?>
                    <div class="activity-item" style="display: flex; justify-content: space-between; align-items: center; padding: 14px 16px; border-bottom: 1px solid var(--color-border);">
                        <div>
                            <strong><?php echo e($al['employee_name']); ?></strong> — <span class="badge badge-info"><?php echo ucfirst(e($al['leave_type'])); ?></span><br>
                            <small class="text-muted"><?php echo formatDate($al['start_date']); ?> to <?php echo formatDate($al['end_date']); ?> (Reason: <?php echo e($al['reason']); ?>)</small>
                        </div>
                        <div>
                            <span class="badge <?php echo $al['status'] === 'approved' ? 'badge-success' : 'badge-warning'; ?>"><?php echo ucfirst(e($al['status'])); ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

<!-- TAB 3: LEAVE BALANCE OVERVIEW -->
<?php elseif ($tab === 'balance'): ?>
    <div class="table-container card fade-in" style="overflow-x: auto; padding: 0;">
        <table class="data-table" style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr>
                    <th style="min-width: 100px;">Emp ID</th>
                    <th style="min-width: 180px;">Employee Name</th>
                    <th style="min-width: 120px; text-align: center;">Casual Leave</th>
                    <th style="min-width: 120px; text-align: center;">Sick Leave</th>
                    <th style="min-width: 120px; text-align: center;">Paid Leave</th>
                    <th style="min-width: 120px; text-align: center;">Unpaid Leave</th>
                    <th style="min-width: 140px; text-align: center;">Total Days Taken</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($leaveBalances as $lb): ?>
                    <tr>
                        <td><code><?php echo e($lb['user']['employee_id']); ?></code></td>
                        <td><strong><?php echo e($lb['user']['name']); ?></strong></td>
                        <td style="text-align: center;"><?php echo $lb['casual_used']; ?> day(s)</td>
                        <td style="text-align: center;"><?php echo $lb['sick_used']; ?> day(s)</td>
                        <td style="text-align: center;"><?php echo $lb['paid_used']; ?> day(s)</td>
                        <td style="text-align: center;"><?php echo $lb['unpaid_used']; ?> day(s)</td>
                        <td style="text-align: center;"><span class="badge badge-purple"><?php echo $lb['total_used']; ?> Total Days</span></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
