<?php
/**
 * Manager Leave Management
 * 
 * Allows managers to:
 * 1. Apply for their own leaves (which route to the Founder for approval).
 * 2. View and approve/deny leaves requested by their assigned team members.
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/middleware.php';

requireRole([ROLE_MANAGER]);

$db = getDB();
$managerId = getUserId();
$formErrors = [];

// Handle Manager's Own Leave Application (submits to Founder)
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
    if (strtotime($startDate) < strtotime(today())) $formErrors[] = 'Start date cannot be in the past.';
    if (empty($reason)) $formErrors[] = 'Specify reason.';
    
    if ($leaveType === 'sick') {
        if (!isset($_FILES['prescription_doc']) || $_FILES['prescription_doc']['error'] === UPLOAD_ERR_NO_FILE) {
            $formErrors[] = 'Prescription document required for Sick Leave.';
        } else {
            $file = $_FILES['prescription_doc'];
            $allowedTypes = ['application/pdf', 'image/jpeg', 'image/png', 'image/jpg'];
            $fileType = mime_content_type($file['tmp_name']);
            
            if (!in_array($fileType, $allowedTypes)) {
                $formErrors[] = 'PDF or JPEG/PNG image only.';
            } else {
                $uploadDir = __DIR__ . '/../uploads/prescriptions/';
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                $filename = time() . '_' . uniqid() . '_' . basename($file['name']);
                $destPath = $uploadDir . $filename;
                
                if (move_uploaded_file($file['tmp_name'], $destPath)) {
                    $prescriptionDoc = 'uploads/prescriptions/' . $filename;
                } else {
                    $formErrors[] = 'Upload failed. Try again.';
                }
            }
        }
    }
    
    if (empty($formErrors)) {
        try {
            $stmt = $db->prepare("
                INSERT INTO leaves (user_id, leave_type, start_date, end_date, reason, prescription_doc, status) 
                VALUES (?, ?, ?, ?, ?, ?, 'pending')
            ");
            $stmt->execute([$managerId, $leaveType, $startDate, $endDate, $reason, $prescriptionDoc]);
            
            setFlash('success', 'Leave request submitted successfully.');
            header('Location: ' . BASE_URL . '/manager/leaves.php');
            exit;
        } catch (PDOException $e) {
            error_log("Error manager applying for leave: " . $e->getMessage());
            $formErrors[] = 'Database error occurred.';
        }
    }
}

// Handle Approve / Deny / Cancel action for team members
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $leaveId = (int)$_GET['id'];
    
    if (in_array($action, ['approve', 'deny', 'cancel'])) {
        $status = ($action === 'approve') ? 'approved' : (($action === 'cancel') ? 'cancelled' : 'denied');
        
        try {
            // Verify that the leaves' owner has this manager assigned to them
            $stmt = $db->prepare("
                SELECT l.id, l.user_id, l.leave_type 
                FROM leaves l 
                JOIN users u ON l.user_id = u.id 
                WHERE l.id = ? AND u.manager_id = ?
            ");
            $stmt->execute([$leaveId, $managerId]);
            $leaveRow = $stmt->fetch();
            
            if ($leaveRow) {
                $updateStmt = $db->prepare("UPDATE leaves SET status = ?, actioned_by = ?, updated_at = NOW() WHERE id = ?");
                $updateStmt->execute([$status, $managerId, $leaveId]);

                $notifTitle = ($status === 'approved') ? '✅ Leave Approved' : (($status === 'cancelled') ? '🚫 Leave Cancelled' : '❌ Leave Rejected');
                $notifMsg = 'Your ' . ucfirst($leaveRow['leave_type']) . ' leave request was ' . $status . ' by Manager.';
                $notifType = ($status === 'approved') ? 'success' : 'danger';

                createNotification(
                    (int)$leaveRow['user_id'],
                    $notifTitle,
                    $notifMsg,
                    BASE_URL . '/employee/leaves.php',
                    $notifType
                );

                setFlash('success', 'Leave request status updated to ' . $status);
            } else {
                setFlash('error', 'Unauthorized or request not found.');
            }
        } catch (PDOException $e) {
            error_log("Error manager actioning leave: " . $e->getMessage());
            setFlash('error', 'Database error.');
        }
        
        header('Location: ' . BASE_URL . '/manager/leaves.php');
        exit;
    }
}

// Handle Manager's own leave cancellation
if (isset($_GET['action']) && $_GET['action'] === 'cancel_own' && isset($_GET['id'])) {
    $leaveId = (int)$_GET['id'];
    try {
        $stmt = $db->prepare("SELECT id, status, leave_type FROM leaves WHERE id = ? AND user_id = ?");
        $stmt->execute([$leaveId, $managerId]);
        $ownLeave = $stmt->fetch();
        if ($ownLeave && in_array($ownLeave['status'], ['pending', 'approved'])) {
            $stmt = $db->prepare("UPDATE leaves SET status = 'cancelled', updated_at = NOW() WHERE id = ?");
            $stmt->execute([$leaveId]);
            setFlash('success', 'Your leave request was cancelled successfully.');
        } else {
            setFlash('error', 'Unable to cancel this leave.');
        }
    } catch (PDOException $e) {
        setFlash('error', 'Database error.');
    }
    header('Location: ' . BASE_URL . '/manager/leaves.php');
    exit;
}

// Fetch manager's own leave requests
$stmt = $db->prepare("SELECT * FROM leaves WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$managerId]);
$myLeaves = $stmt->fetchAll();

// Fetch pending leave requests from team members
$stmt = $db->prepare("
    SELECT l.*, u.name AS employee_name, u.email AS employee_email 
    FROM leaves l 
    JOIN users u ON l.user_id = u.id 
    WHERE u.manager_id = ? AND u.role = 'employee'
    ORDER BY CASE l.status WHEN 'pending' THEN 1 WHEN 'approved' THEN 2 WHEN 'denied' THEN 3 END, l.created_at DESC
");
$stmt->execute([$managerId]);
$teamLeaves = $stmt->fetchAll();

// Fetch stats for manager
$teamPending = 0;
$teamApproved = 0;
foreach ($teamLeaves as $tl) {
    if ($tl['status'] === 'pending') $teamPending++;
    if ($tl['status'] === 'approved') $teamApproved++;
}

$pageTitle = 'Leaves Management';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Leaves Center</h1>
        <p class="page-subtitle">Manage team leaves and apply for your own leaves</p>
    </div>
</div>

<!-- Stats Grid -->
<div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 14px; margin-bottom: 20px; width: 100%;">
    <div class="stat-card accent-orange fade-in" style="padding: 16px;">
        <div class="stat-icon" style="width: 40px; height: 40px; font-size: 16px;"><i class="fa-solid fa-hourglass-half"></i></div>
        <div class="stat-content">
            <div class="stat-value" style="font-size: 22px;"><?php echo $teamPending; ?></div>
            <div class="stat-label" style="font-size: 12px;">Pending Team Requests</div>
        </div>
    </div>
    <div class="stat-card accent-green fade-in stagger-1" style="padding: 16px;">
        <div class="stat-icon" style="width: 40px; height: 40px; font-size: 16px;"><i class="fa-solid fa-circle-check"></i></div>
        <div class="stat-content">
            <div class="stat-value" style="font-size: 22px;"><?php echo $teamApproved; ?></div>
            <div class="stat-label" style="font-size: 12px;">Approved Leaves</div>
        </div>
    </div>
    <div class="stat-card accent-blue fade-in stagger-2" style="padding: 16px;">
        <div class="stat-icon" style="width: 40px; height: 40px; font-size: 16px;"><i class="fa-solid fa-umbrella-beach"></i></div>
        <div class="stat-content">
            <div class="stat-value" style="font-size: 22px;"><?php echo count($myLeaves); ?></div>
            <div class="stat-label" style="font-size: 12px;">My Leave History</div>
        </div>
    </div>
</div>

<?php if (!empty($formErrors)): ?>
    <div class="alert alert-error">
        <span><?php echo e(implode(' ', $formErrors)); ?></span>
        <button class="alert-close" onclick="this.parentElement.remove()">×</button>
    </div>
<?php endif; ?>

<!-- Tabs Navigation -->
<div class="nav-tabs" style="display: flex; gap: var(--space-2); border-bottom: 1px solid var(--color-border); margin-bottom: 20px;">
    <button id="btn-team-tab" class="tab-item active" onclick="switchTab('team-leaves-tab')" style="background: none; border: none; cursor: pointer; padding: 10px 16px; font-weight: 600; border-bottom: 2px solid var(--color-primary); color: var(--color-primary); display: inline-flex; align-items: center; gap: 6px; font-size: 13px;">
        <i class="fa-solid fa-users"></i> Team Leave Requests (<?php echo count($teamLeaves); ?>)
    </button>
    <button id="btn-my-tab" class="tab-item" onclick="switchTab('my-leaves-tab')" style="background: none; border: none; cursor: pointer; padding: 10px 16px; font-weight: 600; border-bottom: 2px solid transparent; color: var(--color-text-secondary); display: inline-flex; align-items: center; gap: 6px; font-size: 13px;">
        <i class="fa-solid fa-umbrella-beach"></i> Apply / My Leaves
    </button>
</div>

<!-- Section A: Team Leave Requests (Default active) -->
<div id="team-leaves-tab" class="tab-content active">
    <?php if (empty($teamLeaves)): ?>
        <div class="card">
            <div class="empty-state">
                <div class="empty-state-icon"><i class="fa-solid fa-users"></i></div>
                <div class="empty-state-title">No team requests found</div>
                <div class="empty-state-text">Your team members have not submitted any leave applications.</div>
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
                    <?php foreach ($teamLeaves as $leave): ?>
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
                                            <?php echo e($leave['employee_email']); ?>

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
</div>

<!-- Section B: Apply / My Leaves -->
<div id="my-leaves-tab" class="tab-content" style="display: none;">
    <div class="content-grid">
        <!-- Application Form -->
        <div class="card fade-in">
            <div class="card-header">
                <h3 class="card-title">Apply for My Leave</h3>
            </div>
            <form method="POST" action="" enctype="multipart/form-data" data-validate>
                <?php echo csrfField(); ?>
                <input type="hidden" name="action" value="apply_leave">
                
                <div class="form-group">
                    <label class="form-label">Leave Type *</label>
                    <select name="leave_type" id="mgr-leave-select" class="form-select" required>
                        <option value="">— Select Type —</option>
                        <option value="casual">Casual Leave</option>
                        <option value="sick">Sick Leave</option>
                        <option value="paid">Paid Leave</option>
                        <option value="unpaid">Unpaid Leave</option>
                    </select>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Start Date *</label>
                        <input type="date" name="start_date" class="form-input" min="<?php echo today(); ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">End Date *</label>
                        <input type="date" name="end_date" class="form-input" min="<?php echo today(); ?>" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Reason *</label>
                    <textarea name="reason" class="form-textarea" required placeholder="Specify reason..."></textarea>
                </div>
                
                <div class="form-group" id="mgr-upload-group" style="display: none;">
                    <label class="form-label">Upload Medical Prescription *</label>
                    <input type="file" name="prescription_doc" id="mgr-upload-input" class="form-input" accept=".pdf,image/*">
                </div>
                
                <div class="form-actions mt-4">
                    <button type="submit" class="btn btn-primary">Submit Application</button>
                </div>
            </form>
        </div>
        
        <!-- Own Leaves History -->
        <div class="card fade-in">
            <div class="card-header">
                <h3 class="card-title">My Leaves History</h3>
            </div>
            <?php if (empty($myLeaves)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon"><i class="fa-solid fa-umbrella-beach"></i></div>
                    <div class="empty-state-text">No leaves applied yet.</div>
                </div>
            <?php else: ?>
                <div style="display: flex; flex-direction: column; gap: var(--space-3);">
                    <?php foreach ($myLeaves as $own): ?>
                        <div style="border-bottom: 1px solid var(--color-border); padding-bottom: var(--space-3);">
                            <div class="flex-between mb-2">
                                <span class="badge badge-info"><?php echo ucfirst($own['leave_type']); ?></span>
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <?php if ($own['status'] === 'pending'): ?>
                                        <span class="badge badge-warning"><i class="fa-solid fa-hourglass-half"></i> Pending</span>
                                    <?php elseif ($own['status'] === 'approved'): ?>
                                        <span class="badge badge-success"><i class="fa-solid fa-circle-check"></i> Approved</span>
                                    <?php elseif ($own['status'] === 'cancelled'): ?>
                                        <span class="badge badge-danger" style="background: rgba(239, 68, 68, 0.15); color: var(--color-danger); border: 1px solid var(--color-danger);"><i class="fa-solid fa-ban"></i> Cancelled</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger"><i class="fa-solid fa-circle-xmark"></i> Denied</span>
                                    <?php endif; ?>
                                    
                                    <?php if (in_array($own['status'], ['pending', 'approved'])): ?>
                                        <a href="?action=cancel_own&id=<?php echo $own['id']; ?>" class="btn btn-danger btn-sm" style="padding: 2px 7px; font-size: 10px; display: inline-flex; align-items: center; gap: 3px;" onclick="return confirm('Cancel your <?php echo $own['status']; ?> leave?')">
                                            <i class="fa-solid fa-ban"></i> Cancel
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div style="font-size: var(--text-sm); font-weight: 500;">
                                <?php echo formatDate($own['start_date']); ?> to <?php echo formatDate($own['end_date']); ?>
                            </div>
                            <div class="text-muted" style="font-size: var(--text-xs); margin-top: 4px;">
                                Reason: <?php echo e($own['reason']); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function switchTab(tabId) {
    document.querySelectorAll('.tab-content').forEach(function(el) {
        el.style.display = 'none';
    });
    document.getElementById(tabId).style.display = 'block';
    
    const teamBtn = document.getElementById('btn-team-tab');
    const myBtn = document.getElementById('btn-my-tab');
    if (tabId === 'team-leaves-tab') {
        teamBtn.style.borderBottomColor = 'var(--color-primary)';
        teamBtn.style.color = 'var(--color-primary)';
        myBtn.style.borderBottomColor = 'transparent';
        myBtn.style.color = 'var(--color-text-secondary)';
    } else {
        myBtn.style.borderBottomColor = 'var(--color-primary)';
        myBtn.style.color = 'var(--color-primary)';
        teamBtn.style.borderBottomColor = 'transparent';
        teamBtn.style.color = 'var(--color-text-secondary)';
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const leaveSelect = document.getElementById('mgr-leave-select');
    const uploadGroup = document.getElementById('mgr-upload-group');
    const uploadInput = document.getElementById('mgr-upload-input');

    leaveSelect.addEventListener('change', function() {
        if (this.value === 'sick') {
            uploadGroup.style.display = 'block';
            uploadInput.required = true;
        } else {
            uploadGroup.style.display = 'none';
            uploadInput.required = false;
            uploadInput.value = '';
        }
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
