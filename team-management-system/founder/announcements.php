<?php
/**
 * Founder — Post Company-wide Announcements
 * 
 * Allows the Founder to post global announcements and manage them.
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/middleware.php';

requireRole([ROLE_FOUNDER]);

$db = getDB();
$founderId = getUserId();
$formErrors = [];

// Handle Posting Announcement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'post_announcement') {
    requireCsrf();
    
    $title = post('title');
    $content = post('content');
    
    if (empty($title)) $formErrors[] = 'Title cannot be empty.';
    if (empty($content)) $formErrors[] = 'Content cannot be empty.';
    
    if (empty($formErrors)) {
        try {
            $stmt = $db->prepare("INSERT INTO announcements (sender_id, title, content) VALUES (?, ?, ?)");
            $stmt->execute([$founderId, $title, $content]);
            setFlash('success', 'Global announcement posted successfully.');
            header('Location: ' . BASE_URL . '/founder/announcements.php');
            exit;
        } catch (PDOException $e) {
            error_log("Error founder posting announcement: " . $e->getMessage());
            $formErrors[] = 'A database error occurred.';
        }
    }
}

// Handle Deleting Announcement
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $annId = (int)$_GET['delete'];
    try {
        $stmt = $db->prepare("DELETE FROM announcements WHERE id = ? AND sender_id = ?");
        $stmt->execute([$annId, $founderId]);
        if ($stmt->rowCount() > 0) {
            setFlash('success', 'Announcement deleted.');
        } else {
            setFlash('error', 'Announcement not found.');
        }
    } catch (PDOException $e) {
        error_log("Error founder deleting announcement: " . $e->getMessage());
        setFlash('error', 'Database error.');
    }
    header('Location: ' . BASE_URL . '/founder/announcements.php');
    exit;
}

// Fetch global announcements (posted by Founder)
$stmt = $db->prepare("SELECT * FROM announcements WHERE sender_id = ? ORDER BY created_at DESC");
$stmt->execute([$founderId]);
$announcements = $stmt->fetchAll();

$pageTitle = 'Global Announcements';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Company Announcements</h1>
        <p class="page-subtitle">Post company-wide announcements and updates to everyone</p>
    </div>
</div>

<?php if (!empty($formErrors)): ?>
    <div class="alert alert-error">
        <span><?php echo e(implode(' ', $formErrors)); ?></span>
        <button class="alert-close" onclick="this.parentElement.remove()">×</button>
    </div>
<?php endif; ?>

<div class="content-grid">
    <!-- Form -->
    <div class="card fade-in">
        <div class="card-header">
            <h3 class="card-title">New Global Announcement</h3>
        </div>
        <form method="POST" action="" data-validate>
            <?php echo csrfField(); ?>
            <input type="hidden" name="action" value="post_announcement">
            
            <div class="form-group">
                <label class="form-label">Title *</label>
                <input type="text" name="title" class="form-input" placeholder="e.g. Office holiday announcement" required>
            </div>
            
            <div class="form-group">
                <label class="form-label">Content *</label>
                <textarea name="content" class="form-textarea" placeholder="Type global announcement message here..." required style="height: 150px;"></textarea>
            </div>
            
            <div class="form-actions mt-4">
                <button type="submit" class="btn btn-primary">Post to Everyone</button>
            </div>
        </form>
    </div>

    <!-- History -->
    <div class="card fade-in">
        <div class="card-header">
            <h3 class="card-title">Global History</h3>
        </div>
        <?php if (empty($announcements)): ?>
            <div class="empty-state">
                <div class="empty-state-icon"><i class="fa-solid fa-bullhorn"></i></div>
                <div class="empty-state-text">No announcements posted yet.</div>
            </div>
        <?php else: ?>
            <div style="display: flex; flex-direction: column; gap: var(--space-4);">
                <?php foreach ($announcements as $ann): ?>
                    <div style="padding-bottom: var(--space-3); border-bottom: 1px solid var(--color-border);">
                        <div class="flex-between mb-1">
                            <h4 style="margin: 0; font-size: var(--text-sm); font-weight: 600;"><?php echo e($ann['title']); ?></h4>
                            <span class="text-muted" style="font-size: var(--text-xs);"><?php echo formatDate($ann['created_at']); ?></span>
                        </div>
                        <p style="font-size: var(--text-xs); color: var(--color-text-secondary); line-height: 1.5; margin: var(--space-2) 0;">
                            <?php echo nl2br(e($ann['content'])); ?>
                        </p>
                        <div style="text-align: right;">
                            <a href="?delete=<?php echo $ann['id']; ?>" class="btn btn-sm btn-outline btn-danger" onclick="return confirm('Are you sure you want to delete this global announcement?')">Delete</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
