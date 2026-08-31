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

// Handle Approve / Deny action for employee leaves
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $leaveId = (int)$_GET['id'];
    
    if (in_array($action, ['approve', 'deny'])) {
        $status = ($action === 'approve') ? 'approved' : 'denied';
        $stmt = $db->prepare("UPDATE leaves SET status = ?, actioned_by = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$status, $hrId, $leaveId]);
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

// Fetch Leave Balances per employee
$employeesList = $db->query("SELECT id, name, employee_id, designation FROM users WHERE role = 'employee' AND status = 'active'")->fetchAll();
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

$pageTitle = 'HR — Leave Management';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Leave Management</h1>
        <p class="page-subtitle">Track, approve, and analyze employee leaves and balances</p>
    </div>
</div>

<!-- Tabs Bar -->
<div class="nav-tabs" style="display: flex; gap: var(--space-2); border-bottom: 1px solid var(--color-border); margin-bottom: var(--space-6);">
    <a href="?tab=requests" class="tab-item <?php echo $tab === 'requests' ? 'active' : ''; ?>" style="padding: var(--space-3) var(--space-4); text-decoration: none; font-weight: 500; border-bottom: 2px solid <?php echo $tab === 'requests' ? 'var(--color-primary)' : 'transparent'; ?>; color: <?php echo $tab === 'requests' ? 'var(--color-primary)' : 'var(--color-text-secondary)'; ?>;">
        <i class="fa-solid fa-clipboard-user"></i> Leave Requests
    </a>
    <a href="?tab=calendar" class="tab-item <?php echo $tab === 'calendar' ? 'active' : ''; ?>" style="padding: var(--space-3) var(--space-4); text-decoration: none; font-weight: 500; border-bottom: 2px solid <?php echo $tab === 'calendar' ? 'var(--color-primary)' : 'transparent'; ?>; color: <?php echo $tab === 'calendar' ? 'var(--color-primary)' : 'var(--color-text-secondary)'; ?>;">
        <i class="fa-solid fa-calendar-days"></i> Leave Calendar
    </a>
    <a href="?tab=balance" class="tab-item <?php echo $tab === 'balance' ? 'active' : ''; ?>" style="padding: var(--space-3) var(--space-4); text-decoration: none; font-weight: 500; border-bottom: 2px solid <?php echo $tab === 'balance' ? 'var(--color-primary)' : 'transparent'; ?>; color: <?php echo $tab === 'balance' ? 'var(--color-primary)' : 'var(--color-text-secondary)'; ?>;">
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
    <div class="table-container fade-in">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Employee</th>
                    <th>Leave Type</th>
                    <th>Dates</th>
                    <th>Duration</th>
                    <th>Reason</th>
                    <th>Document</th>
                    <th>Status</th>
                    <th style="text-align: right;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($employeeLeaves)): ?>
                    <tr><td colspan="8" class="text-center text-muted" style="padding: 24px;">No leave applications found.</td></tr>
                <?php else: ?>
                    <?php foreach ($employeeLeaves as $leave): ?>
                        <?php 
                        $in = new DateTime($leave['start_date']);
                        $out = new DateTime($leave['end_date']);
                        $days = $in->diff($out)->days + 1;
                        ?>
                        <tr>
                            <td>
                                <strong><?php echo e($leave['employee_name']); ?></strong><br>
                                <small class="text-muted"><code><?php echo e($leave['employee_id']); ?></code></small>
                            </td>
                            <td><span class="badge badge-info"><?php echo ucfirst(e($leave['leave_type'])); ?></span></td>
                            <td><?php echo formatDate($leave['start_date']); ?> - <?php echo formatDate($leave['end_date']); ?></td>
                            <td><strong><?php echo $days; ?> day(s)</strong></td>
                            <td style="max-width: 200px;" class="truncate" title="<?php echo e($leave['reason']); ?>"><?php echo e($leave['reason']); ?></td>
                            <td>
                                <?php if ($leave['prescription_doc']): ?>
                                    <a href="<?php echo BASE_URL . '/' . e($leave['prescription_doc']); ?>" target="_blank" class="btn btn-sm btn-outline"><i class="fa-solid fa-eye"></i> View</a>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($leave['status'] === 'pending'): ?>
                                    <span class="badge badge-warning"><span class="badge badge-warning"><i class="fa-solid fa-hourglass-half"></i> Pending</span></span>
                                <?php elseif ($leave['status'] === 'approved'): ?>
                                    <span class="badge badge-success"><span class="badge badge-success"><i class="fa-solid fa-circle-check"></i> Approved</span></span>
                                <?php else: ?>
                                    <span class="badge badge-danger"><span class="badge badge-danger"><i class="fa-solid fa-circle-xmark"></i> Denied</span></span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: right;">
                                <?php if ($leave['status'] === 'pending'): ?>
                                    <a href="?action=approve&id=<?php echo $leave['id']; ?>" class="btn btn-success btn-sm">Approve</a>
                                    <a href="?action=deny&id=<?php echo $leave['id']; ?>" class="btn btn-danger btn-sm">Deny</a>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

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
                    <div class="activity-item" style="display: flex; justify-content: space-between; align-items: center;">
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
    <div class="table-container fade-in">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Emp ID</th>
                    <th>Employee Name</th>
                    <th>Casual Leave Used</th>
                    <th>Sick Leave Used</th>
                    <th>Paid Leave Used</th>
                    <th>Unpaid Leave Used</th>
                    <th>Total Days Taken</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($leaveBalances as $lb): ?>
                    <tr>
                        <td><code><?php echo e($lb['user']['employee_id']); ?></code></td>
                        <td><strong><?php echo e($lb['user']['name']); ?></strong></td>
                        <td><?php echo $lb['casual_used']; ?> day(s)</td>
                        <td><?php echo $lb['sick_used']; ?> day(s)</td>
                        <td><?php echo $lb['paid_used']; ?> day(s)</td>
                        <td><?php echo $lb['unpaid_used']; ?> day(s)</td>
                        <td><span class="badge badge-purple"><?php echo $lb['total_used']; ?> Total Days</span></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
