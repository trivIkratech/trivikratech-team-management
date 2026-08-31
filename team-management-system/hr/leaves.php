<?php
/**
 * HR Leaves Management
 * 
 * Allows HR to:
 * 1. Apply for their own leaves (which route to the Founder for approval).
 * 2. View and approve/deny leaves requested by all Employees.
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/middleware.php';

requireRole([ROLE_HR]);

$db = getDB();
$hrId = getUserId();
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
            $stmt->execute([$hrId, $leaveType, $startDate, $endDate, $reason, $prescriptionDoc]);
            
            setFlash('success', 'Leave request submitted successfully.');
            header('Location: ' . BASE_URL . '/hr/leaves.php');
            exit;
        } catch (PDOException $e) {
            error_log("Error HR applying for leave: " . $e->getMessage());
            $formErrors[] = 'Database error occurred.';
        }
    }
}

// Handle Approve / Deny action for employee leaves
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $leaveId = (int)$_GET['id'];
    
    if (in_array($action, ['approve', 'deny'])) {
        $status = ($action === 'approve') ? 'approved' : 'denied';
        
        try {
            // Verify that the leave requested belongs to an employee
            $stmt = $db->prepare("
                SELECT l.id 
                FROM leaves l 
                JOIN users u ON l.user_id = u.id 
                WHERE l.id = ? AND u.role = 'employee'
            ");
            $stmt->execute([$leaveId]);
            
            if ($stmt->fetch()) {
                $updateStmt = $db->prepare("UPDATE leaves SET status = ?, actioned_by = ?, updated_at = NOW() WHERE id = ?");
                $updateStmt->execute([$status, $hrId, $leaveId]);
                setFlash('success', 'Employee leave request status updated to ' . $status);
            } else {
                setFlash('error', 'Unauthorized or request not found.');
            }
        } catch (PDOException $e) {
            error_log("Error HR actioning leave: " . $e->getMessage());
            setFlash('error', 'Database error.');
        }
        
        header('Location: ' . BASE_URL . '/hr/leaves.php');
        exit;
    }
}

// Fetch HR's own leave requests
$stmt = $db->prepare("SELECT * FROM leaves WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$hrId]);
$myLeaves = $stmt->fetchAll();

// Fetch pending leave requests from all employees
$stmt = $db->query("
    SELECT l.*, u.name AS employee_name, u.email AS employee_email 
    FROM leaves l 
    JOIN users u ON l.user_id = u.id 
    WHERE u.role = 'employee'
    ORDER BY CASE l.status WHEN 'pending' THEN 1 WHEN 'approved' THEN 2 WHEN 'denied' THEN 3 END, l.created_at DESC
");
$employeeLeaves = $stmt->fetchAll();

$pageTitle = 'Leaves Management';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Leaves Center</h1>
        <p class="page-subtitle">Manage company employee leaves and apply for your own leaves</p>
    </div>
</div>

<?php if (!empty($formErrors)): ?>
    <div class="alert alert-error">
        <span><?php echo e(implode(' ', $formErrors)); ?></span>
        <button class="alert-close" onclick="this.parentElement.remove()">×</button>
    </div>
<?php endif; ?>

<!-- Tabs Navigation -->
<div class="flex gap-4 mb-6">
    <button class="btn btn-primary" onclick="switchTab('employee-leaves-tab')">👥 Employee Leave Requests</button>
    <button class="btn btn-outline" onclick="switchTab('my-leaves-tab')">🌴 Apply / My Leaves</button>
</div>

<!-- Section A: Employee Leave Requests (Default active) -->
<div id="employee-leaves-tab" class="tab-content active">
    <div class="card fade-in">
        <div class="card-header">
            <h3 class="card-title">Employee Requests</h3>
        </div>
        <?php if (empty($employeeLeaves)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">👥</div>
                <div class="empty-state-title">No requests found</div>
                <div class="empty-state-text">Employees have not submitted any leaves.</div>
            </div>
        <?php else: ?>
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Leave Type</th>
                            <th>Dates</th>
                            <th>Duration</th>
                            <th>Reason</th>
                            <th>Prescription</th>
                            <th>Status</th>
                            <th>Action</th>
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
                                    <div class="table-user">
                                        <div class="table-user-avatar"><?php echo e(getInitials($leave['employee_name'])); ?></div>
                                        <div>
                                            <div class="table-user-name"><?php echo e($leave['employee_name']); ?></div>
                                            <div class="table-user-email"><?php echo e($leave['employee_email']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="badge badge-info"><?php echo ucfirst(e($leave['leave_type'])); ?></span></td>
                                <td><?php echo formatDate($leave['start_date']); ?> - <?php echo formatDate($leave['end_date']); ?></td>
                                <td><strong><?php echo $days; ?> day(s)</strong></td>
                                <td style="max-width: 200px;" class="truncate" title="<?php echo e($leave['reason']); ?>"><?php echo e($leave['reason']); ?></td>
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
    </div>
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
                    <select name="leave_type" id="hr-leave-select" class="form-select" required>
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
                
                <div class="form-group" id="hr-upload-group" style="display: none;">
                    <label class="form-label">Upload Medical Prescription *</label>
                    <input type="file" name="prescription_doc" id="hr-upload-input" class="form-input" accept=".pdf,image/*">
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
                    <div class="empty-state-icon">🌴</div>
                    <div class="empty-state-text">No leaves applied yet.</div>
                </div>
            <?php else: ?>
                <div style="display: flex; flex-direction: column; gap: var(--space-3);">
                    <?php foreach ($myLeaves as $own): ?>
                        <div style="border-bottom: 1px solid var(--color-border); padding-bottom: var(--space-3);">
                            <div class="flex-between mb-2">
                                <span class="badge badge-info"><?php echo ucfirst($own['leave_type']); ?></span>
                                <?php if ($own['status'] === 'pending'): ?>
                                    <span class="badge badge-warning">⏳ Pending</span>
                                <?php elseif ($own['status'] === 'approved'): ?>
                                    <span class="badge badge-success">✅ Approved</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">❌ Denied</span>
                                <?php endif; ?>
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
    
    const btns = document.querySelectorAll('.flex.gap-4.mb-6 button');
    if (tabId === 'employee-leaves-tab') {
        btns[0].className = 'btn btn-primary';
        btns[1].className = 'btn btn-outline';
    } else {
        btns[0].className = 'btn btn-outline';
        btns[1].className = 'btn btn-primary';
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const leaveSelect = document.getElementById('hr-leave-select');
    const uploadGroup = document.getElementById('hr-upload-group');
    const uploadInput = document.getElementById('hr-upload-input');

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
