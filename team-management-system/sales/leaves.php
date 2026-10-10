<?php
/**
 * Sales Counsellor — Apply & View Leave Requests
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/middleware.php';

requireRole([ROLE_SALES_COUNSELLOR]);

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
    
    if (!in_array($leaveType, ['casual', 'sick', 'paid', 'unpaid'])) $formErrors[] = 'Invalid leave type chosen.';
    if (empty($startDate)) $formErrors[] = 'Please select a start date.';
    if (empty($endDate)) $formErrors[] = 'Please select an end date.';
    if (strtotime($startDate) > strtotime($endDate)) $formErrors[] = 'Start date cannot be after end date.';
    if (empty($reason)) $formErrors[] = 'Please describe the reason for your leave.';
    
    if (empty($formErrors)) {
        $days = (int)((strtotime($endDate) - strtotime($startDate)) / 86400) + 1;
        $stmt = $db->prepare("INSERT INTO leaves (user_id, leave_type, start_date, end_date, days, reason, status, created_at) VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())");
        $stmt->execute([$userId, $leaveType, $startDate, $endDate, $days, $reason]);
        
        setFlash('success', 'Leave application submitted successfully for review.');
        redirect(BASE_URL . '/sales/leaves.php');
    }
}

// Fetch my leave history
$stmt = $db->prepare("SELECT * FROM leaves WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$userId]);
$leaves = $stmt->fetchAll();

$pageTitle = 'Apply Leave — Sales Counsellor';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title"><i class="fa-solid fa-umbrella-beach" style="color: var(--color-primary); margin-right: 8px;"></i>Apply Leave</h1>
        <p class="page-subtitle">Submit leave applications and monitor approval statuses.</p>
    </div>
</div>

<div style="display: grid; grid-template-columns: 1fr 2fr; gap: var(--space-6);" class="dashboard-grid-responsive">
    <!-- Apply Form -->
    <div class="card fade-in" style="border: 1px solid var(--color-border); box-shadow: var(--shadow-md);">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: var(--space-4); border-bottom: 1px solid var(--color-border); padding-bottom: 12px;">
            <h3 style="margin: 0; font-size: 16px; font-weight: 700; display: flex; align-items: center; gap: 8px;">
                <i class="fa-solid fa-paper-plane" style="color: var(--color-primary);"></i> Submit Leave Request
            </h3>
            <span class="badge badge-info" style="font-size: 11px; font-weight: 600;">Standard Shift</span>
        </div>

        <?php if (!empty($formErrors)): ?>
            <div class="alert alert-danger" style="margin-bottom: var(--space-4); border-radius: var(--radius-md);">
                <?php foreach ($formErrors as $err) echo '<div style="display: flex; align-items: center; gap: 6px;"><i class="fa-solid fa-circle-exclamation"></i> ' . e($err) . '</div>'; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="<?php echo BASE_URL; ?>/sales/leaves.php" id="leaveRequestForm">
            <input type="hidden" name="action" value="apply_leave">
            <?php echo csrfField(); ?>

            <div class="form-group" style="margin-bottom: var(--space-4);">
                <label class="form-label" style="font-size: 13px; font-weight: 600; display: flex; align-items: center; gap: 6px; margin-bottom: 6px;">
                    <i class="fa-solid fa-tag" style="color: var(--color-primary); font-size: 12px;"></i> Leave Type
                </label>
                <select name="leave_type" class="form-select" required>
                    <option value="casual">Casual Leave</option>
                    <option value="sick">Sick Leave</option>
                    <option value="paid">Paid Leave</option>
                    <option value="unpaid">Unpaid Leave</option>
                </select>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: var(--space-4);">
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" style="font-size: 13px; font-weight: 600; display: flex; align-items: center; gap: 6px; margin-bottom: 6px;">
                        <i class="fa-solid fa-calendar-day" style="color: var(--color-primary); font-size: 12px;"></i> Start Date
                    </label>
                    <input type="date" name="start_date" id="leave_start_date" class="form-input" required min="<?php echo today(); ?>">
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" style="font-size: 13px; font-weight: 600; display: flex; align-items: center; gap: 6px; margin-bottom: 6px;">
                        <i class="fa-solid fa-calendar-check" style="color: var(--color-primary); font-size: 12px;"></i> End Date
                    </label>
                    <input type="date" name="end_date" id="leave_end_date" class="form-input" required min="<?php echo today(); ?>">
                </div>
            </div>

            <div id="leaveDaysPill" style="display: none; margin-bottom: var(--space-4); padding: 8px 12px; background: var(--color-primary-bg); border-radius: var(--radius-md); font-size: 12px; color: var(--color-primary); font-weight: 600; align-items: center; gap: 6px;">
                <i class="fa-solid fa-calculator"></i> Total Requested: <span id="leaveDaysCount">1</span> Day(s)
            </div>

            <div class="form-group" style="margin-bottom: var(--space-5);">
                <label class="form-label" style="font-size: 13px; font-weight: 600; display: flex; align-items: center; gap: 6px; margin-bottom: 6px;">
                    <i class="fa-solid fa-comment-dots" style="color: var(--color-primary); font-size: 12px;"></i> Reason
                </label>
                <textarea name="reason" class="form-textarea" rows="3" required placeholder="State your reason for leave..." style="min-height: 90px;"></textarea>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; height: 42px; display: inline-flex; align-items: center; justify-content: center; gap: 8px; font-weight: 600; font-size: 14px; box-shadow: 0 4px 12px rgba(79, 110, 247, 0.25);">
                <i class="fa-solid fa-paper-plane"></i> Submit Leave Request
            </button>
        </form>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const startDateInput = document.getElementById('leave_start_date');
        const endDateInput = document.getElementById('leave_end_date');
        const pill = document.getElementById('leaveDaysPill');
        const countSpan = document.getElementById('leaveDaysCount');

        function updateDays() {
            if (startDateInput && endDateInput && startDateInput.value && endDateInput.value) {
                const s = new Date(startDateInput.value);
                const e = new Date(endDateInput.value);
                if (e >= s) {
                    const diffTime = Math.abs(e - s);
                    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;
                    countSpan.textContent = diffDays;
                    pill.style.display = 'flex';
                } else {
                    pill.style.display = 'none';
                }
            } else {
                pill.style.display = 'none';
            }
        }

        if (startDateInput && endDateInput) {
            startDateInput.addEventListener('change', function() {
                if (endDateInput.value && endDateInput.value < this.value) {
                    endDateInput.value = this.value;
                }
                endDateInput.min = this.value;
                updateDays();
            });
            endDateInput.addEventListener('change', updateDays);
        }
    });
    </script>

    <!-- Leave History -->
    <div class="card fade-in">
        <h3 style="margin: 0 0 var(--space-4) 0; font-size: 16px; font-weight: 600;">My Leave History</h3>

        <?php if (empty($leaves)): ?>
            <p style="text-align: center; color: var(--color-text-muted); padding: var(--space-6);">No leave requests submitted yet.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table" style="width: 100%; font-size: 13px;">
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Dates</th>
                            <th>Days</th>
                            <th>Reason</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($leaves as $l): ?>
                            <tr>
                                <td><strong><?php echo ucfirst(e($l['leave_type'])); ?></strong></td>
                                <td><?php echo formatDate($l['start_date']); ?> to <?php echo formatDate($l['end_date']); ?></td>
                                <td><?php echo (int)$l['days']; ?> day(s)</td>
                                <td style="max-width: 200px;"><?php echo e($l['reason']); ?></td>
                                <td>
                                    <?php if ($l['status'] === 'approved'): ?>
                                        <span class="badge badge-success">Approved</span>
                                    <?php elseif ($l['status'] === 'denied'): ?>
                                        <span class="badge badge-danger">Denied</span>
                                    <?php else: ?>
                                        <span class="badge badge-warning">Pending</span>
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

<style>
@media (max-width: 800px) {
    .dashboard-grid-responsive {
        grid-template-columns: 1fr !important;
    }
}
</style>

<?php include __DIR__ . '/../includes/footer.php'; ?>
