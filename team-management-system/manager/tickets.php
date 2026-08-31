<?php
/**
 * Manager — Support Desk & Ticket System
 * 
 * 1. Received Team Tickets (View & Resolve tickets sent to manager).
 * 2. Raise Support Ticket (Send ticket to HR or Founder).
 * 3. My Submitted Tickets.
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/middleware.php';

requireRole([ROLE_MANAGER]);

$db = getDB();
$managerId = getUserId();
$activeTab = get('tab', 'received');
$formErrors = [];

// Handle Ticket Creation by Manager
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'create_ticket') {
    requireCsrf();
    
    $category = post('category');
    $subcategory = post('subcategory');
    $description = post('description');
    $isAnonymous = isset($_POST['is_anonymous']) ? 1 : 0;
    
    $sendToArr = $_POST['send_to'] ?? [];
    if (!is_array($sendToArr)) {
        $sendToArr = [$sendToArr];
    }
    
    $validTargets = ['hr', 'founder'];
    $cleanTargets = [];
    foreach ($sendToArr as $target) {
        if (in_array($target, $validTargets)) {
            $cleanTargets[] = $target;
        }
    }
    
    if (empty($category)) $formErrors[] = 'Please select a category.';
    if (empty($subcategory)) $formErrors[] = 'Please select a subcategory.';
    if (empty($description)) $formErrors[] = 'Please describe the issue.';
    if (empty($cleanTargets)) {
        $formErrors[] = 'Please select at least one recipient (HR or Founder).';
    }
    
    if (empty($formErrors)) {
        $sendTo = implode(',', $cleanTargets);
        try {
            $stmt = $db->prepare("
                INSERT INTO support_tickets (user_id, category, sub_category, description, send_to, is_anonymous, status) 
                VALUES (?, ?, ?, ?, ?, ?, 'pending')
            ");
            $stmt->execute([$managerId, $category, $subcategory, $description, $sendTo, $isAnonymous]);
            
            // Trigger in-app notifications to recipients
            if (in_array('hr', $cleanTargets)) {
                $hrUsers = $db->query("SELECT id FROM users WHERE role = 'hr'")->fetchAll(PDO::FETCH_COLUMN);
                foreach ($hrUsers as $hId) {
                    createNotification($hId, '<i class="fa-solid fa-headset"></i> Manager Support Ticket', 'Manager submitted a support ticket: ' . $category, BASE_URL . '/hr/support.php', 'info');
                }
            }
            if (in_array('founder', $cleanTargets)) {
                $founderUsers = $db->query("SELECT id FROM users WHERE role = 'founder'")->fetchAll(PDO::FETCH_COLUMN);
                foreach ($founderUsers as $fId) {
                    createNotification($fId, '<i class="fa-solid fa-headset"></i> Executive Ticket from Manager', 'Manager submitted a ticket to Founder.', BASE_URL . '/founder/tickets.php', 'info');
                }
            }
            
            setFlash('success', 'Support ticket submitted successfully!');
            header('Location: ' . BASE_URL . '/manager/tickets.php?tab=my_tickets');
            exit;
        } catch (PDOException $e) {
            error_log("Error creating support ticket: " . $e->getMessage());
            $formErrors[] = 'A database error occurred. Please try again.';
        }
    }
}

// Handle Ticket Resolve Action (for received tickets)
if (isset($_GET['resolve']) && is_numeric($_GET['resolve'])) {
    $ticketId = (int)$_GET['resolve'];
    try {
        $stmt = $db->prepare("
            SELECT t.id, t.user_id, t.category 
            FROM support_tickets t 
            JOIN users u ON t.user_id = u.id 
            WHERE t.id = ? AND FIND_IN_SET('manager', t.send_to) > 0
        ");
        $stmt->execute([$ticketId]);
        $ticket = $stmt->fetch();
        
        if ($ticket) {
            $updateStmt = $db->prepare("UPDATE support_tickets SET status = 'resolved' WHERE id = ?");
            $updateStmt->execute([$ticketId]);
            
            // Notify ticket creator
            createNotification($ticket['user_id'], '<i class="fa-solid fa-circle-check"></i> Support Ticket Resolved', 'Your support ticket (' . $ticket['category'] . ') was resolved by Manager.', BASE_URL . '/employee/support.php', 'success');
            
            setFlash('success', 'Ticket marked as resolved.');
        } else {
            setFlash('error', 'Unauthorized access or ticket not found.');
        }
        header('Location: ' . BASE_URL . '/manager/tickets.php?tab=received');
        exit;
    } catch (PDOException $e) {
        error_log("Error resolving support ticket: " . $e->getMessage());
        setFlash('error', 'A database error occurred.');
    }
}

// Fetch Received Tickets (Sent to Manager)
$filterStatus = get('status');
$filterCategory = get('category');

$where = ["FIND_IN_SET('manager', t.send_to) > 0"];
$params = [];

if ($filterStatus) {
    $where[] = "t.status = ?";
    $params[] = $filterStatus;
}
if ($filterCategory) {
    $where[] = "t.category = ?";
    $params[] = $filterCategory;
}

$whereClause = implode(' AND ', $where);

$stmt = $db->prepare("
    SELECT t.*, u.name AS employee_name, u.email AS employee_email, u.role AS user_role
    FROM support_tickets t 
    JOIN users u ON t.user_id = u.id 
    WHERE {$whereClause}
    ORDER BY CASE t.status WHEN 'pending' THEN 1 WHEN 'resolved' THEN 2 END, t.created_at DESC
");
$stmt->execute($params);
$receivedTickets = $stmt->fetchAll();

// Fetch My Submitted Tickets (Raised by this Manager)
$stmt = $db->prepare("
    SELECT * FROM support_tickets 
    WHERE user_id = ? 
    ORDER BY created_at DESC
");
$stmt->execute([$managerId]);
$mySubmittedTickets = $stmt->fetchAll();

$pageTitle = 'Manager Support Desk';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
    <div>
        <h1 class="page-title">Manager Support & Help Center</h1>
        <p class="page-subtitle">View team requests or raise support tickets to HR & Founder</p>
    </div>
    
    <!-- Tab Navigation Buttons -->
    <div style="display: flex; gap: 8px;">
        <a href="?tab=received" class="btn <?php echo $activeTab === 'received' ? 'btn-primary' : 'btn-outline'; ?>" style="font-size: 13px;">
            <i class="fa-solid fa-inbox"></i> Received Team Tickets (<?php echo count($receivedTickets); ?>)
        </a>
        <a href="?tab=create" class="btn <?php echo $activeTab === 'create' ? 'btn-primary' : 'btn-outline'; ?>" style="font-size: 13px;">
            <i class="fa-solid fa-plus"></i> Raise Support Ticket
        </a>
        <a href="?tab=my_tickets" class="btn <?php echo $activeTab === 'my_tickets' ? 'btn-primary' : 'btn-outline'; ?>" style="font-size: 13px;">
            <i class="fa-solid fa-paper-plane"></i> My Submitted Tickets (<?php echo count($mySubmittedTickets); ?>)
        </a>
    </div>
</div>

<?php if (!empty($formErrors)): ?>
    <div class="alert alert-error">
        <span><?php echo e(implode(' ', $formErrors)); ?></span>
        <button class="alert-close" onclick="this.parentElement.remove()">×</button>
    </div>
<?php endif; ?>

<?php if ($activeTab === 'create'): ?>
    <!-- TAB: CREATE SUPPORT TICKET (MANAGER -> HR / FOUNDER) -->
    <div class="card fade-in" style="max-width: 750px; margin: 0 auto;">
        <div class="card-header">
            <h3 class="card-title"><i class="fa-solid fa-plus"></i> Submit Support Request to HR / Founder</h3>
            <p class="card-subtitle">Request assistance, report management issues, or ask for resources</p>
        </div>
        <form method="POST" action="" data-validate id="manager-support-form">
            <?php echo csrfField(); ?>
            <input type="hidden" name="action" value="create_ticket">

            <div class="form-group">
                <label class="form-label" for="category">Ticket Category *</label>
                <select id="category" name="category" class="form-select" required onchange="updateSubcategories()">
                    <option value="">-- Select Category --</option>
                    <option value="HR & Leave">HR & Leave Management</option>
                    <option value="Payroll">Payroll & Compensation Query</option>
                    <option value="Team Resource">Team Resource & Staffing Request</option>
                    <option value="Task & Work Support">Technical & Management Support</option>
                    <option value="Complaint / Grievance">Escalation / Confidential Grievance</option>
                    <option value="General Request">General Inquiry / Other</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label" for="subcategory">Subcategory *</label>
                <select id="subcategory" name="subcategory" class="form-select" required disabled>
                    <option value="">-- Select Subcategory --</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Send Request To * (Select at least one recipient)</label>
                <div style="display: flex; gap: 20px; flex-wrap: wrap; margin-top: 6px; background: var(--color-bg-secondary); padding: 12px 16px; border-radius: var(--radius-md); border: 1px solid var(--color-border);">
                    <label style="display: inline-flex; align-items: center; gap: 8px; cursor: pointer; font-weight: 500;">
                        <input type="checkbox" name="send_to[]" value="hr" checked style="width: 16px; height: 16px;">
                        <span><i class="fa-solid fa-clipboard-user"></i> HR Department</span>
                    </label>
                    <label style="display: inline-flex; align-items: center; gap: 8px; cursor: pointer; font-weight: 500;">
                        <input type="checkbox" name="send_to[]" value="founder" style="width: 16px; height: 16px;">
                        <span><i class="fa-solid fa-crown"></i> Founder / Executive</span>
                    </label>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="description">Detailed Description *</label>
                <textarea id="description" name="description" class="form-textarea" rows="5" required placeholder="Describe your issue or request in detail..."></textarea>
            </div>

            <div class="form-group" style="background: rgba(245, 158, 11, 0.1); border: 1px solid rgba(245, 158, 11, 0.3); padding: 12px 16px; border-radius: var(--radius-md);">
                <label style="display: inline-flex; align-items: center; gap: 8px; cursor: pointer;">
                    <input type="checkbox" name="is_anonymous" value="1" style="width: 16px; height: 16px;">
                    <span style="font-size: 13px; color: var(--color-text-main);"><strong>Submit Anonymously</strong> (Hide your name and identity from recipients)</span>
                </label>
            </div>

            <div class="form-actions" style="margin-top: 24px;">
                <button type="submit" class="btn btn-primary btn-lg" style="width: 100%;"><i class="fa-solid fa-rocket"></i> Submit Ticket</button>
            </div>
        </form>
    </div>

<?php elseif ($activeTab === 'my_tickets'): ?>
    <!-- TAB: MY SUBMITTED TICKETS (RAISED BY MANAGER) -->
    <div class="card fade-in" style="padding: 0;">
        <div class="card-header" style="padding: 20px 24px;">
            <h3 class="card-title" style="margin: 0;">My Submitted Tickets</h3>
        </div>
        <?php if (empty($mySubmittedTickets)): ?>
            <div class="empty-state" style="padding: 30px;">
                <div class="empty-state-icon"><i class="fa-solid fa-paper-plane"></i></div>
                <div class="empty-state-title">No tickets submitted yet</div>
                <div class="empty-state-text">You haven't submitted any support requests.</div>
                <a href="?tab=create" class="btn btn-primary btn-sm" style="margin-top: 12px;">Raise Support Ticket</a>
            </div>
        <?php else: ?>
            <div class="table-container" style="border: none;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Ticket ID</th>
                            <th>Category / Subcategory</th>
                            <th>Recipients</th>
                            <th>Submitted Date</th>
                            <th>Status</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($mySubmittedTickets as $t): ?>
                            <tr>
                                <td><code>#TICK-<?php echo $t['id']; ?></code></td>
                                <td>
                                    <strong><?php echo e($t['category']); ?></strong><br>
                                    <small class="text-muted"><?php echo e($t['sub_category']); ?></small>
                                </td>
                                <td>
                                    <?php 
                                    $targets = explode(',', $t['send_to']);
                                    foreach ($targets as $tg) {
                                        if ($tg === 'hr') echo '<span class="badge badge-info" style="margin-right: 4px;"><i class="fa-solid fa-clipboard-user"></i> HR</span>';
                                        elseif ($tg === 'founder') echo '<span class="badge badge-purple" style="margin-right: 4px;"><i class="fa-solid fa-crown"></i> Founder</span>';
                                    }
                                    ?>
                                </td>
                                <td><?php echo formatDateTime($t['created_at']); ?></td>
                                <td>
                                    <?php if ($t['status'] === 'pending'): ?>
                                        <span class="badge badge-warning"><span class="badge badge-warning"><i class="fa-solid fa-hourglass-half"></i> Pending</span></span>
                                    <?php else: ?>
                                        <span class="badge badge-success"><i class="fa-solid fa-circle-check"></i> Resolved</span>
                                    <?php endif; ?>
                                </td>
                                <td style="max-width: 300px; white-space: normal; font-size: 13px;"><?php echo e($t['description']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

<?php else: ?>
    <!-- TAB: RECEIVED TEAM TICKETS -->
    <form method="GET" class="filter-bar" style="margin-bottom: 20px;">
        <input type="hidden" name="tab" value="received">
        <select name="status" class="form-select" onchange="this.form.submit()">
            <option value="">All Statuses</option>
            <option value="pending" <?php echo $filterStatus === 'pending' ? 'selected' : ''; ?>>Pending</option>
            <option value="resolved" <?php echo $filterStatus === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
        </select>
        <select name="category" class="form-select" onchange="this.form.submit()">
            <option value="">All Categories</option>
            <option value="HR & Leave">HR & Leave</option>
            <option value="Attendance">Attendance</option>
            <option value="Payroll">Payroll</option>
            <option value="Task & Work Support">Task & Work Support</option>
            <option value="General Request">General Request</option>
            <option value="Complaint / Grievance">Complaint & Grievance</option>
        </select>
        <?php if ($filterStatus || $filterCategory): ?>
            <a href="<?php echo BASE_URL; ?>/manager/tickets.php?tab=received" class="btn btn-sm btn-outline">Clear Filters</a>
        <?php endif; ?>
    </form>

    <?php if (empty($receivedTickets)): ?>
        <div class="card">
            <div class="empty-state">
                <div class="empty-state-icon"><i class="fa-solid fa-headset"></i></div>
                <div class="empty-state-title">No received support tickets</div>
                <div class="empty-state-text">No tickets assigned to you under these filters.</div>
            </div>
        </div>
    <?php else: ?>
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Sender</th>
                        <th>Category / Subcategory</th>
                        <th>Submitted On</th>
                        <th>Status</th>
                        <th>Details</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($receivedTickets as $ticket): ?>
                        <tr>
                            <td>
                                <?php if ($ticket['is_anonymous']): ?>
                                    <div class="table-user">
                                        <div class="table-user-avatar" style="background: var(--color-purple);"><i class="fa-solid fa-mask"></i></div>
                                        <div>
                                            <div class="table-user-name">Anonymous Sender</div>
                                            <div class="table-user-email">Identity Hidden</div>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="table-user">
                                        <div class="table-user-avatar"><?php echo e(getInitials($ticket['employee_name'])); ?></div>
                                        <div>
                                            <div class="table-user-name"><?php echo e($ticket['employee_name']); ?></div>
                                            <div class="table-user-email"><?php echo e($ticket['employee_email']); ?> <small class="text-muted">(<?php echo ucfirst(e($ticket['user_role'])); ?>)</small></div>
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
                                    <span class="badge badge-warning"><span class="badge badge-warning"><i class="fa-solid fa-hourglass-half"></i> Pending</span></span>
                                <?php else: ?>
                                    <span class="badge badge-success"><i class="fa-solid fa-circle-check"></i> Resolved</span>
                                <?php endif; ?>
                            </td>
                            <td style="max-width: 300px; white-space: normal; font-size: var(--text-sm);">
                                <?php echo e($ticket['description']); ?>
                            </td>
                            <td>
                                <?php if ($ticket['status'] === 'pending'): ?>
                                    <a href="?tab=received&resolve=<?php echo $ticket['id']; ?>" class="btn btn-success btn-sm" title="Mark as Resolved">
                                        <i class="fa-solid fa-check"></i> Resolve
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted" style="font-size: var(--text-xs);">Resolved</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
<?php endif; ?>

<script>
const subcategoryData = {
    "HR & Leave": ["Leave Policy Clarification", "Leave Balance Dispute", "Workplace Conduct", "General HR Query"],
    "Payroll": ["Salary Slip Discrepancy", "Overtime / Deductions Query", "Bank Account Update", "Tax / Reimbursement"],
    "Team Resource": ["Staffing / Additional Member Request", "Software License Request", "Hardware / Equipment Requirement"],
    "Task & Work Support": ["Project Deadline Extension", "Technical Support / Access", "Inter-Department Escalation"],
    "Complaint / Grievance": ["Workplace Harassment", "Management Dispute", "Unfair Treatment", "Safety Concern"],
    "General Request": ["Suggestion / Feedback", "Process Improvement", "Other Query"]
};

function updateSubcategories() {
    const catSelect = document.getElementById('category');
    const subSelect = document.getElementById('subcategory');
    const selectedCat = catSelect.value;
    
    subSelect.innerHTML = '<option value="">-- Select Subcategory --</option>';
    
    if (selectedCat && subcategoryData[selectedCat]) {
        subSelect.disabled = false;
        subcategoryData[selectedCat].forEach(sub => {
            const opt = document.createElement('option');
            opt.value = sub;
            opt.textContent = sub;
            subSelect.appendChild(opt);
        });
    } else {
        subSelect.disabled = true;
    }
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
