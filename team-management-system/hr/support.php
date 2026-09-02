<?php
/**
 * HR — Support Desk & Ticket System
 * 
 * 1. Received Employee Tickets (View & Resolve tickets sent to HR).
 * 2. Raise Support Ticket (Send ticket to Manager or Founder).
 * 3. My Submitted Tickets.
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/middleware.php';

requireRole([ROLE_HR]);

$db = getDB();
$hrUserId = getUserId();
$tab = get('tab', 'all'); // 'all', 'open', 'resolved', 'create', 'my_tickets'
$formErrors = [];

// Handle HR Support Ticket Creation (HR -> Manager / Founder)
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
    
    // HR can send to Manager or Founder
    $validTargets = ['manager', 'founder'];
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
        $formErrors[] = 'Please select at least one recipient (Manager or Founder).';
    }
    
    if (empty($formErrors)) {
        $sendTo = implode(',', $cleanTargets);
        try {
            $stmt = $db->prepare("
                INSERT INTO support_tickets (user_id, category, sub_category, description, send_to, is_anonymous, status) 
                VALUES (?, ?, ?, ?, ?, ?, 'pending')
            ");
            $stmt->execute([$hrUserId, $category, $subcategory, $description, $sendTo, $isAnonymous]);
            
            setFlash('success', 'HR Support ticket submitted successfully!');
            header('Location: ' . BASE_URL . '/hr/support.php?tab=my_tickets');
            exit;
        } catch (PDOException $e) {
            error_log("Error creating HR support ticket: " . $e->getMessage());
            $formErrors[] = 'A database error occurred. Please try again.';
        }
    }
}

// Handle Ticket Status Update (Resolve / Reopen for Received Tickets)
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $ticketId = (int)$_GET['id'];
    
    if (in_array($action, ['resolve', 'reopen'])) {
        $status = ($action === 'resolve') ? 'resolved' : 'pending';
        $stmt = $db->prepare("UPDATE support_tickets SET status = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$status, $ticketId]);

        $tStmt = $db->prepare("SELECT user_id, category FROM support_tickets WHERE id = ?");
        $tStmt->execute([$ticketId]);
        $tRow = $tStmt->fetch();
        if ($tRow) {
            createNotification(
                (int)$tRow['user_id'],
                ($status === 'resolved' ? '✅ Support Ticket Resolved' : '🔄 Support Ticket Re-opened'),
                'Your support ticket (' . $tRow['category'] . ') was ' . $status . ' by HR.',
                BASE_URL . '/employee/support.php',
                ($status === 'resolved' ? 'success' : 'info')
            );
        }

        setFlash('success', 'Ticket status updated successfully.');
        header('Location: ' . BASE_URL . '/hr/support.php?tab=' . $tab);
        exit;
    }
}

// Fetch Received Tickets (Sent to HR)
$query = "
    SELECT st.*, u.name as user_name, u.email as user_email, u.employee_id, u.role as user_role
    FROM support_tickets st
    JOIN users u ON st.user_id = u.id
    WHERE FIND_IN_SET('hr', st.send_to) > 0 OR st.send_to IS NULL
";
if ($tab === 'open') {
    $query .= " AND st.status = 'pending'";
} elseif ($tab === 'resolved') {
    $query .= " AND st.status = 'resolved'";
}

$query .= " ORDER BY st.created_at DESC";
$stmt = $db->prepare($query);
$stmt->execute();
$receivedTickets = $stmt->fetchAll();

// Counts for Received Tabs
$totalCount = $db->query("SELECT COUNT(*) FROM support_tickets WHERE FIND_IN_SET('hr', send_to) > 0 OR send_to IS NULL")->fetchColumn();
$openCount = $db->query("SELECT COUNT(*) FROM support_tickets WHERE status = 'pending' AND (FIND_IN_SET('hr', send_to) > 0 OR send_to IS NULL)")->fetchColumn();
$resolvedCount = $db->query("SELECT COUNT(*) FROM support_tickets WHERE status = 'resolved' AND (FIND_IN_SET('hr', send_to) > 0 OR send_to IS NULL)")->fetchColumn();

// Fetch My Submitted Tickets (Raised by HR)
$stmt = $db->prepare("
    SELECT * FROM support_tickets 
    WHERE user_id = ? 
    ORDER BY created_at DESC
");
$stmt->execute([$hrUserId]);
$mySubmittedTickets = $stmt->fetchAll();

$pageTitle = 'HR — Support & Tickets Desk';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
    <div>
        <h1 class="page-title">HR Support & Help Center</h1>
        <p class="page-subtitle">Manage workforce tickets or raise requests to Manager & Founder</p>
    </div>
    
    <!-- Header Actions -->
    <div style="display: flex; gap: 10px;">
        <a href="?tab=create" class="btn <?php echo $tab === 'create' ? 'btn-primary' : 'btn-outline'; ?>" style="font-size: 13px;">
            <i class="fa-solid fa-plus"></i> Raise Support Ticket
        </a>
        <a href="?tab=my_tickets" class="btn <?php echo $tab === 'my_tickets' ? 'btn-primary' : 'btn-outline'; ?>" style="font-size: 13px;">
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

<?php if ($tab === 'create'): ?>
    <!-- TAB: RAISE SUPPORT TICKET (HR -> MANAGER / FOUNDER) -->
    <div class="card fade-in" style="max-width: 750px; margin: 0 auto;">
        <div class="card-header">
            <h3 class="card-title"><i class="fa-solid fa-plus"></i> Submit Support Request to Manager / Founder</h3>
            <p class="card-subtitle">Request management guidance, policy clarifications, or escalate issues</p>
        </div>
        <form method="POST" action="" data-validate id="hr-support-form">
            <?php echo csrfField(); ?>
            <input type="hidden" name="action" value="create_ticket">

            <div class="form-group">
                <label class="form-label" for="category">Ticket Category *</label>
                <select id="category" name="category" class="form-select" required onchange="updateSubcategories()">
                    <option value="">-- Select Category --</option>
                    <option value="HR & Policy">HR & Policy Escalation</option>
                    <option value="Management Request">Management & Leadership Query</option>
                    <option value="Staffing & Payroll">Payroll & Staffing Support</option>
                    <option value="Complaint / Grievance">Confidential Complaint / Grievance</option>
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
                        <input type="checkbox" name="send_to[]" value="manager" checked style="width: 16px; height: 16px;">
                        <span><i class="fa-solid fa-user-tie"></i> Reporting Manager</span>
                    </label>
                    <label style="display: inline-flex; align-items: center; gap: 8px; cursor: pointer; font-weight: 500;">
                        <input type="checkbox" name="send_to[]" value="founder" style="width: 16px; height: 16px;">
                        <span><i class="fa-solid fa-crown"></i> Founder / Executive</span>
                    </label>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="description">Detailed Description *</label>
                <textarea id="description" name="description" class="form-textarea" rows="5" required placeholder="Describe your query or request in detail..."></textarea>
            </div>

            <div class="form-group" style="background: rgba(245, 158, 11, 0.1); border: 1px solid rgba(245, 158, 11, 0.3); padding: 12px 16px; border-radius: var(--radius-md);">
                <label style="display: inline-flex; align-items: center; gap: 8px; cursor: pointer;">
                    <input type="checkbox" name="is_anonymous" value="1" style="width: 16px; height: 16px;">
                    <span style="font-size: 13px; color: var(--color-text-main);"><strong>Submit Anonymously</strong> (Hide your name and identity)</span>
                </label>
            </div>

            <div class="form-actions" style="margin-top: 24px;">
                <button type="submit" class="btn btn-primary btn-lg" style="width: 100%;"><i class="fa-solid fa-rocket"></i> Submit Ticket</button>
            </div>
        </form>
    </div>

<?php elseif ($tab === 'my_tickets'): ?>
    <!-- TAB: MY SUBMITTED TICKETS (RAISED BY HR) -->
    <div class="card fade-in" style="padding: 0;">
        <div class="card-header" style="padding: 20px 24px;">
            <h3 class="card-title" style="margin: 0;">My Submitted Support Tickets</h3>
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
                                        if ($tg === 'manager') echo '<span class="badge badge-purple" style="margin-right: 4px;"><i class="fa-solid fa-user-tie"></i> Manager</span>';
                                        elseif ($tg === 'founder') echo '<span class="badge badge-info" style="margin-right: 4px;"><i class="fa-solid fa-crown"></i> Founder</span>';
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
    <!-- TAB: RECEIVED EMPLOYEE TICKETS -->
    <div class="nav-tabs" style="display: flex; gap: var(--space-2); border-bottom: 1px solid var(--color-border); margin-bottom: var(--space-6);">
        <a href="?tab=all" class="tab-item <?php echo $tab === 'all' ? 'active' : ''; ?>" style="padding: var(--space-3) var(--space-4); text-decoration: none; font-weight: 500; border-bottom: 2px solid <?php echo $tab === 'all' ? 'var(--color-primary)' : 'transparent'; ?>; color: <?php echo $tab === 'all' ? 'var(--color-primary)' : 'var(--color-text-secondary)'; ?>;">
            <i class="fa-solid fa-headset"></i> All Received Tickets (<?php echo $totalCount; ?>)
        </a>
        <a href="?tab=open" class="tab-item <?php echo $tab === 'open' ? 'active' : ''; ?>" style="padding: var(--space-3) var(--space-4); text-decoration: none; font-weight: 500; border-bottom: 2px solid <?php echo $tab === 'open' ? 'var(--color-primary)' : 'transparent'; ?>; color: <?php echo $tab === 'open' ? 'var(--color-primary)' : 'var(--color-text-secondary)'; ?>;">
            <i class="fa-solid fa-hourglass-half"></i> Open Requests (<?php echo $openCount; ?>)
        </a>
        <a href="?tab=resolved" class="tab-item <?php echo $tab === 'resolved' ? 'active' : ''; ?>" style="padding: var(--space-3) var(--space-4); text-decoration: none; font-weight: 500; border-bottom: 2px solid <?php echo $tab === 'resolved' ? 'active' : 'transparent'; ?>; color: <?php echo $tab === 'resolved' ? 'var(--color-primary)' : 'var(--color-text-secondary)'; ?>;">
            <i class="fa-solid fa-circle-check"></i> Resolved Requests (<?php echo $resolvedCount; ?>)
        </a>
    </div>

    <div class="table-container fade-in">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Ticket ID</th>
                    <th>Sender</th>
                    <th>Category</th>
                    <th>Description</th>
                    <th>Sent To</th>
                    <th>Submitted On</th>
                    <th>Status</th>
                    <th style="text-align: right;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($receivedTickets)): ?>
                    <tr><td colspan="8" class="text-center text-muted" style="padding: 24px;">No support tickets found in this section.</td></tr>
                <?php else: ?>
                    <?php foreach ($receivedTickets as $t): ?>
                        <tr>
                            <td><code>#TICK-<?php echo $t['id']; ?></code></td>
                            <td>
                                <?php if ($t['is_anonymous']): ?>
                                    <div class="table-user">
                                        <div class="table-user-avatar" style="background: var(--color-purple);"><i class="fa-solid fa-mask"></i></div>
                                        <div>
                                            <div class="table-user-name">Anonymous Sender</div>
                                            <div class="table-user-email">Identity Hidden</div>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="table-user">
                                        <div class="table-user-avatar"><?php echo e(getInitials($t['user_name'])); ?></div>
                                        <div>
                                            <div class="table-user-name"><?php echo e($t['user_name']); ?></div>
                                            <div class="table-user-email"><?php echo e($t['user_email']); ?> <small class="text-muted">(<?php echo ucfirst(e($t['user_role'])); ?>)</small></div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="font-weight: 600; color: var(--color-text-white);"><?php echo e($t['category']); ?></div>
                                <div class="text-muted" style="font-size: var(--text-xs);"><?php echo e($t['sub_category']); ?></div>
                            </td>
                            <td style="max-width: 250px; white-space: normal; font-size: var(--text-sm);"><?php echo e($t['description']); ?></td>
                            <td>
                                <?php 
                                if (!empty($t['send_to'])) {
                                    $targets = explode(',', $t['send_to']);
                                    foreach ($targets as $tg) {
                                        echo '<span class="badge badge-info" style="margin-right: 2px;">' . strtoupper(e($tg)) . '</span>';
                                    }
                                } else {
                                    echo '<span class="badge badge-info">HR</span>';
                                }
                                ?>
                            </td>
                            <td><?php echo formatDateTime($t['created_at']); ?></td>
                            <td>
                                <?php if ($t['status'] === 'pending'): ?>
                                    <span class="badge badge-warning"><i class="fa-solid fa-hourglass-half"></i> Open</span>
                                <?php else: ?>
                                    <span class="badge badge-success"><i class="fa-solid fa-circle-check"></i> Resolved</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: right;">
                                <?php if ($t['status'] === 'pending'): ?>
                                    <a href="?tab=<?php echo $tab; ?>&action=resolve&id=<?php echo $t['id']; ?>" class="btn btn-success btn-sm">Mark Resolved</a>
                                <?php else: ?>
                                    <a href="?tab=<?php echo $tab; ?>&action=reopen&id=<?php echo $t['id']; ?>" class="btn btn-outline btn-sm">Re-open</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<script>
const subcategoryData = {
    "HR & Policy": ["Policy Clarification", "Escalation to Management", "Workplace Safety / Environment", "General Policy Query"],
    "Management Request": ["Executive Approval Required", "Budget / Expense Request", "Strategic Query", "Leadership Support"],
    "Staffing & Payroll": ["Staff Recruitment Needs", "Payroll Budget Adjustment", "Software / Infrastructure Request"],
    "Complaint / Grievance": ["Management Escalation", "Confidential Complaint", "Inter-Department Dispute"],
    "General Request": ["Feedback / Suggestion", "Other Inquiry"]
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
