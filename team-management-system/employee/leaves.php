<?php
/**
 * Employee Leave Requests
 * 
 * Allows employees to request leaves, select leave types, specify dates & reasons,
 * upload a prescription for Sick leaves, and view past request statuses.
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/middleware.php';

requireRole([ROLE_EMPLOYEE]);

$db = getDB();
$userId = getUserId();
$formErrors = [];

// Handle Leave Request Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'apply_leave') {
    requireCsrf();
    
    $leaveType = post('leave_type');
    $startDate = post('start_date');
    $endDate = post('end_date');
    $reason = post('reason');
    $prescriptionDoc = null;
    
    // Validate inputs
    if (!in_array($leaveType, ['casual', 'sick', 'paid', 'unpaid'])) $formErrors[] = 'Invalid leave type chosen.';
    if (empty($startDate)) $formErrors[] = 'Please select a start date.';
    if (empty($endDate)) $formErrors[] = 'Please select an end date.';
    if (strtotime($startDate) > strtotime($endDate)) $formErrors[] = 'Start date cannot be after end date.';
    if (strtotime($startDate) < strtotime(today())) $formErrors[] = 'Start date cannot be in the past.';
    if (empty($reason)) $formErrors[] = 'Please describe the reason for your leave.';
    
    // Validate medical prescription for Sick Leave
    if ($leaveType === 'sick') {
        if (!isset($_FILES['prescription_doc']) || $_FILES['prescription_doc']['error'] === UPLOAD_ERR_NO_FILE) {
            $formErrors[] = 'Medical prescription document is required for Sick Leave.';
        } else {
            $file = $_FILES['prescription_doc'];
            $allowedTypes = ['application/pdf', 'image/jpeg', 'image/png', 'image/jpg'];
            $fileType = mime_content_type($file['tmp_name']);
            
            if (!in_array($fileType, $allowedTypes)) {
                $formErrors[] = 'Only PDF and image files (JPEG, PNG, JPG) are allowed for prescription upload.';
            } else {
                // Create uploads directory if not exists
                $uploadDir = __DIR__ . '/../uploads/prescriptions/';
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                // Save file with a unique name
                $filename = time() . '_' . uniqid() . '_' . basename($file['name']);
                $destPath = $uploadDir . $filename;
                
                if (move_uploaded_file($file['tmp_name'], $destPath)) {
                    $prescriptionDoc = 'uploads/prescriptions/' . $filename;
                } else {
                    $formErrors[] = 'Failed to upload prescription. Please try again.';
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
            $stmt->execute([$userId, $leaveType, $startDate, $endDate, $reason, $prescriptionDoc]);
            
            setFlash('success', 'Leave request submitted successfully!');
            header('Location: ' . BASE_URL . '/employee/leaves.php');
            exit;
        } catch (PDOException $e) {
            error_log("Error applying for leave: " . $e->getMessage());
            $formErrors[] = 'A database error occurred. Please try again.';
        }
    }
}

// Fetch past leaves for this employee
$stmt = $db->prepare("
    SELECT * FROM leaves 
    WHERE user_id = ? 
    ORDER BY created_at DESC
");
$stmt->execute([$userId]);
// Compute employee stats
$myPending = 0;
$myApproved = 0;
foreach ($myLeaves as $l) {
    if ($l['status'] === 'pending') $myPending++;
    if ($l['status'] === 'approved') $myApproved++;
}

$pageTitle = 'Apply Leave';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Leave Request</h1>
        <p class="page-subtitle">Request leaves and view your leave history</p>
    </div>
</div>

<!-- Stats Grid -->
<div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 24px;">
    <div class="stat-card accent-orange fade-in">
        <div class="stat-icon"><i class="fa-solid fa-hourglass-half"></i></div>
        <div class="stat-content">
            <div class="stat-value"><?php echo $myPending; ?></div>
            <div class="stat-label">Pending Requests</div>
        </div>
    </div>
    <div class="stat-card accent-green fade-in stagger-1">
        <div class="stat-icon"><i class="fa-solid fa-circle-check"></i></div>
        <div class="stat-content">
            <div class="stat-value"><?php echo $myApproved; ?></div>
            <div class="stat-label">Approved Leaves</div>
        </div>
    </div>
    <div class="stat-card accent-blue fade-in stagger-2">
        <div class="stat-icon"><i class="fa-solid fa-calendar-days"></i></div>
        <div class="stat-content">
            <div class="stat-value"><?php echo count($myLeaves); ?></div>
            <div class="stat-label">Total Applications</div>
        </div>
    </div>
</div>

<?php if (!empty($formErrors)): ?>
    <div class="alert alert-error">
        <span><?php echo e(implode(' ', $formErrors)); ?></span>
        <button class="alert-close" onclick="this.parentElement.remove()">×</button>
    </div>
<?php endif; ?>

<div class="content-grid" style="display: grid; grid-template-columns: 1fr; gap: 24px;">
    <!-- Leave Request Form -->
    <div class="card fade-in" style="max-width: 750px;">
        <div class="card-header">
            <h3 class="card-title"><i class="fa-solid fa-paper-plane"></i> Apply for Leave</h3>
        </div>
        <form method="POST" action="" enctype="multipart/form-data" data-validate>
            <?php echo csrfField(); ?>
            <input type="hidden" name="action" value="apply_leave">
            
            <div class="form-group">
                <label class="form-label">Leave Type *</label>
                <select name="leave_type" id="leave-type-select" class="form-select" required>
                    <option value="">— Select Leave Type —</option>
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
                <textarea name="reason" class="form-textarea" placeholder="State the reason for leave request..." required></textarea>
            </div>
            
            <!-- Dynamic prescription upload, visible only for sick leaves -->
            <div class="form-group" id="prescription-upload-group" style="display: none;">
                <label class="form-label">Upload Medical Prescription * <span class="text-muted">(PDF or Image)</span></label>
                <input type="file" name="prescription_doc" id="prescription-input" class="form-input" accept=".pdf,image/*">
            </div>
            
            <div class="form-actions mt-4">
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-paper-plane"></i> Submit Request</button>
            </div>
        </form>
    </div>
</div>

<!-- Past Leave History -->
<div class="card fade-in mt-6" style="padding: 0; overflow: hidden;">
    <div class="card-header" style="padding: 16px 20px; border-bottom: 1px solid var(--color-border);">
        <h3 class="card-title"><i class="fa-solid fa-clock-rotate-left"></i> My Leave History</h3>
    </div>
    <?php if (empty($myLeaves)): ?>
        <div class="empty-state" style="padding: 32px;">
            <div class="empty-state-icon"><i class="fa-solid fa-umbrella-beach"></i></div>
            <div class="empty-state-title">No leave history</div>
            <div class="empty-state-text">You haven't requested any leaves yet.</div>
        </div>
    <?php else: ?>
        <div class="table-container" style="overflow-x: auto;">
            <table class="data-table" style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr>
                        <th style="min-width: 120px; text-align: center;">Leave Type</th>
                        <th style="min-width: 160px;">Dates & Duration</th>
                        <th style="min-width: 220px;">Reason</th>
                        <th style="min-width: 100px; text-align: center;">Document</th>
                        <th style="min-width: 110px; text-align: center;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($myLeaves as $leave): ?>
                        <?php 
                        $in = new DateTime($leave['start_date']);
                        $out = new DateTime($leave['end_date']);
                        $days = $in->diff($out)->days + 1;
                        ?>
                        <tr>
                            <td style="text-align: center;">
                                <span class="badge badge-info" style="white-space: nowrap;"><?php echo ucfirst(e($leave['leave_type'])); ?></span>
                            </td>
                            <td>
                                <div style="white-space: nowrap; font-size: 13px; font-weight: 600;">
                                    <?php echo formatDate($leave['start_date']); ?>
                                    <?php if ($leave['start_date'] !== $leave['end_date']): ?>
                                        <span style="font-weight: 400; color: var(--color-text-muted);">to</span> <?php echo formatDate($leave['end_date']); ?>
                                    <?php endif; ?>
                                </div>
                                <div style="margin-top: 2px;">
                                    <span class="badge badge-outline" style="font-size: 11px; padding: 2px 6px;">
                                        <?php echo $days; ?> day<?php echo $days > 1 ? 's' : ''; ?>
                                    </span>
                                </div>
                            </td>
                            <td style="max-width: 280px;" title="<?php echo e($leave['reason']); ?>">
                                <div style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 280px;">
                                    <?php echo e($leave['reason']); ?>
                                </div>
                            </td>
                            <td style="text-align: center;">
                                <?php if ($leave['prescription_doc']): ?>
                                    <a href="<?php echo BASE_URL . '/' . e($leave['prescription_doc']); ?>" target="_blank" class="btn btn-sm btn-outline" style="white-space: nowrap; padding: 4px 8px; font-size: 11px;">
                                        <i class="fa-solid fa-file-medical"></i> View
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: center;">
                                <?php if ($leave['status'] === 'pending'): ?>
                                    <span class="badge badge-warning" style="white-space: nowrap;"><i class="fa-solid fa-hourglass-half"></i> Pending</span>
                                <?php elseif ($leave['status'] === 'approved'): ?>
                                    <span class="badge badge-success" style="white-space: nowrap;"><i class="fa-solid fa-circle-check"></i> Approved</span>
                                <?php else: ?>
                                    <span class="badge badge-danger" style="white-space: nowrap;"><i class="fa-solid fa-circle-xmark"></i> Denied</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const leaveSelect = document.getElementById('leave-type-select');
    const uploadGroup = document.getElementById('prescription-upload-group');
    const uploadInput = document.getElementById('prescription-input');

    leaveSelect.addEventListener('change', function() {
        if (this.value === 'sick') {
            uploadGroup.style.display = 'block';
            uploadInput.required = true;
        } else {
            uploadGroup.style.display = 'none';
            uploadInput.required = false;
            uploadInput.value = ''; // clear selected file
        }
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
