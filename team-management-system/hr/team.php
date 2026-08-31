<?php
/**
 * HR — Team & Communication (Team Members Directory & Announcements send/read)
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/middleware.php';

requireRole([ROLE_HR]);

$db = getDB();
$hrId = getUserId();
$tab = get('tab', 'announcements'); // 'announcements' or 'members'
$formErrors = [];

// Handle Broadcast Announcement Form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('form_action') === 'create_announcement') {
    requireCsrf();
    
    $title = post('title');
    $content = post('content');
    
    if (empty($title)) $formErrors[] = 'Announcement title is required.';
    if (empty($content)) $formErrors[] = 'Announcement content is required.';
    
    if (empty($formErrors)) {
        $stmt = $db->prepare("INSERT INTO announcements (sender_id, title, content, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$hrId, $title, $content]);
        
        setFlash('success', 'Announcement published and sent successfully.');
        header('Location: ' . BASE_URL . '/hr/team.php?tab=announcements');
        exit;
    }
}

// Fetch Announcements list
$announcements = $db->query("
    SELECT a.*, u.name as sender_name, u.role as sender_role
    FROM announcements a
    JOIN users u ON a.sender_id = u.id
    ORDER BY a.created_at DESC
")->fetchAll();

// Fetch Team Members
$teamMembers = $db->query("
    SELECT u.*, m.name as manager_name
    FROM users u
    LEFT JOIN users m ON u.manager_id = m.id
    WHERE u.status = 'active'
    ORDER BY u.role ASC, u.name ASC
")->fetchAll();

$pageTitle = 'HR — Team & Communication';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Team & Communication</h1>
        <p class="page-subtitle">Broadcast company announcements & connect with team members</p>
    </div>
</div>

<!-- Tabs Bar -->
<div class="nav-tabs" style="display: flex; gap: var(--space-2); border-bottom: 1px solid var(--color-border); margin-bottom: var(--space-6);">
    <a href="?tab=announcements" class="tab-item <?php echo $tab === 'announcements' ? 'active' : ''; ?>" style="padding: var(--space-3) var(--space-4); text-decoration: none; font-weight: 500; border-bottom: 2px solid <?php echo $tab === 'announcements' ? 'var(--color-primary)' : 'transparent'; ?>; color: <?php echo $tab === 'announcements' ? 'var(--color-primary)' : 'var(--color-text-secondary)'; ?>;">
        <i class="fa-solid fa-bullhorn"></i> Announcements (Send / Read)
    </a>
    <a href="?tab=members" class="tab-item <?php echo $tab === 'members' ? 'active' : ''; ?>" style="padding: var(--space-3) var(--space-4); text-decoration: none; font-weight: 500; border-bottom: 2px solid <?php echo $tab === 'members' ? 'var(--color-primary)' : 'transparent'; ?>; color: <?php echo $tab === 'members' ? 'var(--color-primary)' : 'var(--color-text-secondary)'; ?>;">
        <i class="fa-solid fa-users"></i> Team Members Directory
    </a>
</div>

<?php if (!empty($formErrors)): ?>
    <div class="alert alert-error">
        <span><?php echo e(implode(' ', $formErrors)); ?></span>
        <button class="alert-close" onclick="this.parentElement.remove()">×</button>
    </div>
<?php endif; ?>

<!-- TAB 1: ANNOUNCEMENTS -->
<?php if ($tab === 'announcements'): ?>
    <div class="content-grid">
        <!-- Broadcast Form -->
        <div class="card fade-in">
            <div class="card-header">
                <h3 class="card-title"><i class="fa-solid fa-bullhorn"></i> Broadcast New Announcement</h3>
            </div>
            <form method="POST" action="">
                <?php echo csrfField(); ?>
                <input type="hidden" name="form_action" value="create_announcement">
                
                <div class="form-group">
                    <label class="form-label">Announcement Title *</label>
                    <input type="text" name="title" class="form-input" required placeholder="e.g. Office Holiday Notice / HR Policy Update">
                </div>

                <div class="form-group">
                    <label class="form-label">Announcement Content *</label>
                    <textarea name="content" class="form-textarea" required rows="5" placeholder="Write message to broadcast to all employees..."></textarea>
                </div>

                <div class="form-actions" style="margin-top: 16px;">
                    <button type="submit" class="btn btn-primary" style="width: 100%;">Broadcast Announcement</button>
                </div>
            </form>
        </div>

        <!-- Announcements Feed -->
        <div class="card fade-in">
            <div class="card-header">
                <h3 class="card-title">Published Announcements Feed</h3>
            </div>
            <?php if (empty($announcements)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon"><i class="fa-solid fa-bullhorn"></i></div>
                    <div class="empty-state-text">No announcements broadcasted yet.</div>
                </div>
            <?php else: ?>
                <div class="activity-list">
                    <?php foreach ($announcements as $ann): ?>
                        <div class="activity-item" style="border-bottom: 1px solid var(--color-border); padding-bottom: 16px; margin-bottom: 16px;">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 6px;">
                                <strong><i class="fa-solid fa-bullhorn"></i> <?php echo e($ann['title']); ?></strong>
                                <small class="text-muted"><?php echo formatDate($ann['created_at']); ?></small>
                            </div>
                            <p style="margin-bottom: 8px; font-size: var(--text-sm); line-height: 1.5; color: var(--color-text-primary);">
                                <?php echo nl2br(e($ann['content'])); ?>

                            </p>
                            <small class="text-muted">By: <strong><?php echo e($ann['sender_name']); ?></strong> (<?php echo ucfirst(e($ann['sender_role'])); ?>)</small>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

<!-- TAB 2: TEAM MEMBERS DIRECTORY -->
<?php else: ?>
    <div class="table-container fade-in">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Emp ID</th>
                    <th>Name</th>
                    <th>Role</th>
                    <th>Designation</th>
                    <th>Email & Contact</th>
                    <th>Reporting Manager</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($teamMembers as $tm): ?>
                    <tr>
                        <td><code><?php echo e($tm['employee_id'] ?: '—'); ?></code></td>
                        <td>
                            <div class="table-user">
                                <div class="table-user-avatar"><?php echo e(getInitials($tm['name'])); ?></div>
                                <div>
                                    <strong><?php echo e($tm['name']); ?></strong>
                                </div>
                            </div>
                        </td>
                        <td><span class="badge badge-info"><?php echo ucfirst(e($tm['role'])); ?></span></td>
                        <td><?php echo e($tm['designation'] ?: '—'); ?></td>
                        <td>
                            <?php echo e($tm['email']); ?><br>
                            <small class="text-muted"><?php echo e($tm['contact_no'] ?: '—'); ?></small>
                        </td>
                        <td><?php echo e($tm['manager_name'] ?? '—'); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
