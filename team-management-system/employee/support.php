<?php
/**
 * Employee Support Section
 * 
 * Allows employees to raise support tickets, select categories, dynamic subcategories,
 * toggle anonymity, and choose who to send it to (Founder, Manager, HR).
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

// Handle Support Ticket Creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'create_ticket') {
    requireCsrf();
    
    $category = post('category');
    $subcategory = post('subcategory');
    $description = post('description');
    $isAnonymous = isset($_POST['is_anonymous']) ? 1 : 0;
    
    // Process array of send_to values
    $sendToArr = $_POST['send_to'] ?? [];
    if (!is_array($sendToArr)) {
        $sendToArr = [$sendToArr];
    }
    
    $validTargets = ['founder', 'manager', 'hr'];
    $cleanTargets = [];
    foreach ($sendToArr as $target) {
        if (in_array($target, $validTargets)) {
            $cleanTargets[] = $target;
        }
    }
    
    // Validate inputs
    if (empty($category)) $formErrors[] = 'Please select a category.';
    if (empty($subcategory)) $formErrors[] = 'Please select a subcategory.';
    if (empty($description)) $formErrors[] = 'Please describe the issue.';
    if (empty($cleanTargets)) {
        $formErrors[] = 'Please select at least one recipient (Manager, HR, or Founder).';
    }
    
    if (empty($formErrors)) {
        $sendTo = implode(',', $cleanTargets);
        try {
            $stmt = $db->prepare("
                INSERT INTO support_tickets (user_id, category, sub_category, description, send_to, is_anonymous, status) 
                VALUES (?, ?, ?, ?, ?, ?, 'pending')
            ");
            $stmt->execute([$userId, $category, $subcategory, $description, $sendTo, $isAnonymous]);

            // Dispatch notifications to assigned teams
            $creatorName = $isAnonymous ? 'Anonymous' : ($_SESSION['user_name'] ?? 'Employee');
            if (in_array('hr', $cleanTargets)) {
                $hrIds = $db->query("SELECT id FROM users WHERE role = 'hr' AND status = 'active'")->fetchAll(PDO::FETCH_COLUMN);
                foreach ($hrIds as $hId) {
                    createNotification($hId, '🎫 Support Ticket: ' . $category, $creatorName . ' submitted ticket: ' . $category . ' - ' . $subcategory, BASE_URL . '/hr/support.php', 'info');
                }
            }
            if (in_array('founder', $cleanTargets)) {
                $founderIds = $db->query("SELECT id FROM users WHERE role = 'founder' AND status = 'active'")->fetchAll(PDO::FETCH_COLUMN);
                foreach ($founderIds as $fId) {
                    createNotification($fId, '🎫 Executive Ticket: ' . $category, $creatorName . ' submitted a support ticket to Founder.', BASE_URL . '/founder/tickets.php', 'info');
                }
            }
            if (in_array('manager', $cleanTargets)) {
                $mgrStmt = $db->prepare("SELECT manager_id FROM users WHERE id = ?");
                $mgrStmt->execute([$userId]);
                $mgrId = $mgrStmt->fetchColumn();
                if ($mgrId) {
                    createNotification($mgrId, '🎫 Team Support Ticket', $creatorName . ' submitted a support ticket: ' . $category, BASE_URL . '/manager/tickets.php', 'info');
                }
            }
            
            setFlash('success', 'Support ticket submitted successfully!');
            header('Location: ' . BASE_URL . '/employee/support.php');
            exit;
        } catch (PDOException $e) {
            error_log("Error creating support ticket: " . $e->getMessage());
            $formErrors[] = 'A database error occurred. Please try again.';
        }
    }
}

// Fetch all tickets submitted by this employee
$stmt = $db->prepare("
    SELECT * FROM support_tickets 
    WHERE user_id = ? 
    ORDER BY created_at DESC
");
$stmt->execute([$userId]);
$myTickets = $stmt->fetchAll();

$pageTitle = 'Support & Help';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Support & Help Center</h1>
        <p class="page-subtitle">Submit requests, report grievances, or ask for guidance</p>
    </div>
</div>

<?php if (!empty($formErrors)): ?>
    <div class="alert alert-error">
        <span><?php echo e(implode(' ', $formErrors)); ?></span>
        <button class="alert-close" onclick="this.parentElement.remove()">×</button>
    </div>
<?php endif; ?>

<div class="content-grid">
    <!-- Raise a Support Ticket Form -->
    <div class="card fade-in">
        <div class="card-header">
            <h3 class="card-title">Raise a Request</h3>
        </div>
        <form method="POST" action="" data-validate id="support-form">
            <?php echo csrfField(); ?>
            <input type="hidden" name="action" value="create_ticket">
            
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Category *</label>
                    <select name="category" id="ticket-category" class="form-select" required>
                        <option value="">— Select Category —</option>
                        <option value="HR & Leave">HR & Leave</option>
                        <option value="Attendance">Attendance</option>
                        <option value="Payroll">Payroll</option>
                        <option value="Task & Work Support">Task & Work Support</option>
                        <option value="General Request">General Request</option>
                        <option value="Complaint / Grievance">Complaint / Grievance</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Subcategory *</label>
                    <select name="subcategory" id="ticket-subcategory" class="form-select" required disabled>
                        <option value="">— Select Category First —</option>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label">Describe the Issue *</label>
                <textarea name="description" class="form-textarea" placeholder="Explain your request in detail..." required></textarea>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" style="margin-bottom: var(--space-2);">Send To *</label>
                    <div style="display: flex; gap: var(--space-4); align-items: center; margin-top: 10px;">
                        <label class="flex gap-2" style="cursor: pointer; font-weight: 500; font-size: var(--text-sm);">
                            <input type="checkbox" name="send_to[]" value="manager" checked>
                            <span>Manager</span>
                        </label>
                        <label class="flex gap-2" style="cursor: pointer; font-weight: 500; font-size: var(--text-sm);">
                            <input type="checkbox" name="send_to[]" value="hr">
                            <span>HR</span>
                        </label>
                        <label class="flex gap-2" style="cursor: pointer; font-weight: 500; font-size: var(--text-sm);">
                            <input type="checkbox" name="send_to[]" value="founder">
                            <span>Founder</span>
                        </label>
                    </div>
                </div>
                <div class="form-group" style="display: flex; align-items: center; padding-top: var(--space-6);">
                    <label class="flex gap-2" style="cursor: pointer; font-weight: 500; font-size: var(--text-sm);">
                        <input type="checkbox" name="is_anonymous" value="1">
                        <span>Submit Anonymously</span>
                    </label>
                </div>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Send Request</button>
            </div>
        </form>
    </div>
    
    <!-- Privacy & Policies Links -->
    <div class="card fade-in stagger-1">
        <div class="card-header">
            <h3 class="card-title">Quick Resources</h3>
        </div>
        <div style="display: flex; flex-direction: column; gap: var(--space-4);">
            <p class="text-muted" style="font-size: var(--text-sm);">Access standard legal documentation and company rules.</p>
            <a href="https://yourdomain.com/privacy-policy" target="_blank" class="btn btn-outline" style="justify-content: flex-start;">
                <i class="fa-solid fa-shield-halved"></i> Privacy & Policy
            </a>
            <a href="https://yourdomain.com/terms-conditions" target="_blank" class="btn btn-outline" style="justify-content: flex-start;">
                <i class="fa-solid fa-file-lines"></i> Terms & Conditions
            </a>
        </div>
    </div>
</div>

<!-- Past Tickets History -->
<div class="card fade-in mt-6">
    <div class="card-header">
        <h3 class="card-title">My Support History</h3>
    </div>
    <?php if (empty($myTickets)): ?>
        <div class="empty-state">
            <div class="empty-state-icon"><i class="fa-solid fa-clipboard-user"></i></div>
            <div class="empty-state-title">No requests raised yet</div>
            <div class="empty-state-text">All support tickets you submit will appear here.</div>
        </div>
    <?php else: ?>
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Category / Subcategory</th>
                        <th>Sent To</th>
                        <th>Submitted On</th>
                        <th>Anonymity</th>
                        <th>Status</th>
                        <th>Description</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($myTickets as $ticket): ?>
                        <tr>
                            <td>
                                <div style="font-weight: 600; color: var(--color-text-white);"><?php echo e($ticket['category']); ?></div>
                                <div class="text-muted" style="font-size: var(--text-xs);"><?php echo e($ticket['sub_category']); ?></div>
                            </td>
                             <td>
                                 <?php foreach(explode(',', $ticket['send_to']) as $target): ?>
                                     <span class="badge badge-secondary" style="margin-right: 4px;"><?php echo strtoupper(trim($target)); ?></span>
                                 <?php endforeach; ?>
                             </td>
                            <td><?php echo formatDateTime($ticket['created_at']); ?></td>
                            <td>
                                <?php if ($ticket['is_anonymous']): ?>
                                    <span class="badge badge-purple"><i class="fa-solid fa-mask"></i> Anonymous</span>
                                <?php else: ?>
                                    <span class="badge badge-info"><i class="fa-solid fa-user"></i> Standard</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($ticket['status'] === 'pending'): ?>
                                    <span class="badge badge-warning"><span class="badge badge-warning"><i class="fa-solid fa-hourglass-half"></i> Pending</span></span>
                                <?php else: ?>
                                    <span class="badge badge-success"><i class="fa-solid fa-circle-check"></i> Resolved</span>
                                <?php endif; ?>
                            </td>
                            <td style="max-width: 250px;" class="truncate" title="<?php echo e($ticket['description']); ?>">
                                <?php echo e($ticket['description']); ?>
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
    const subcategoryMap = {
        "HR & Leave": [
            "Leave-related issue",
            "Salary issue",
            "Attendance correction",
            "Joining/document queries",
            "Company policy questions"
        ],
        "Attendance": [
            "Check-in / Check-out missing",
            "Wrong attendance marked",
            "Late mark correction request",
            "Half-day correction",
            "Attendance regularization"
        ],
        "Payroll": [
            "Salary not received",
            "Incorrect salary amount",
            "Deduction-related query",
            "Incentive / bonus query",
            "Payslip request",
            "Reimbursement/payment issue"
        ],
        "Task & Work Support": [
            "Task unclear hai",
            "Deadline extension request",
            "Manager se guidance",
            "Workload issue",
            "Resource/access required"
        ],
        "General Request": [
            "New software/tool required",
            "Work-from-home request",
            "Equipment request",
            "Other general support"
        ],
        "Complaint / Grievance": [
            "Workplace issue",
            "Manager/team issue",
            "Harassment or serious complaint",
            "Anonymous complaint option"
        ]
    };

    const categorySelect = document.getElementById('ticket-category');
    const subcategorySelect = document.getElementById('ticket-subcategory');

    categorySelect.addEventListener('change', function() {
        const selectedCategory = this.value;
        
        // Clear subcategory list
        subcategorySelect.innerHTML = '<option value="">— Select Subcategory —</option>';
        
        if (selectedCategory && subcategoryMap[selectedCategory]) {
            subcategorySelect.disabled = false;
            subcategoryMap[selectedCategory].forEach(function(sub) {
                const opt = document.createElement('option');
                opt.value = sub;
                opt.textContent = sub;
                subcategorySelect.appendChild(opt);
            });
        } else {
            subcategorySelect.disabled = true;
            subcategorySelect.innerHTML = '<option value="">— Select Category First —</option>';
        }
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
