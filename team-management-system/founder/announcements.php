<?php
/**
 * Founder — Company Announcements Hub & Management
 * 
 * Allows the Founder to post global announcements and view ALL announcements
 * posted across the entire organization (Founder, HR, Managers).
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

// Handle Posting Global Announcement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'post_announcement') {
    requireCsrf();
    
    $title = trim(post('title'));
    $content = trim(post('content'));
    
    if (empty($title)) $formErrors[] = 'Title cannot be empty.';
    if (empty($content)) $formErrors[] = 'Content cannot be empty.';
    
    if (empty($formErrors)) {
        try {
            $stmt = $db->prepare("INSERT INTO announcements (sender_id, title, content) VALUES (?, ?, ?)");
            $stmt->execute([$founderId, $title, $content]);
            
            // Notify all active team members
            $usersStmt = $db->prepare("SELECT id, role FROM users WHERE status = 'active' AND id != ?");
            $usersStmt->execute([$founderId]);
            $activeUsers = $usersStmt->fetchAll();
            foreach ($activeUsers as $u) {
                $baseRole = function_exists('getRoleBaseType') ? getRoleBaseType($u['role']) : $u['role'];
                $link = ($baseRole === 'employee') ? BASE_URL . '/employee/announcements.php' : (($baseRole === 'manager') ? BASE_URL . '/manager/announcements.php' : (($baseRole === 'hr') ? BASE_URL . '/hr/team.php?tab=announcements' : BASE_URL . '/founder/announcements.php'));
                createNotification(
                    $u['id'],
                    '📢 Founder Announcement: ' . $title,
                    $content,
                    $link,
                    'info'
                );
            }

            setFlash('success', 'Global announcement broadcasted successfully to all staff.');
            header('Location: ' . BASE_URL . '/founder/announcements.php');
            exit;
        } catch (PDOException $e) {
            error_log("Error founder posting announcement: " . $e->getMessage());
            $formErrors[] = 'A database error occurred.';
        }
    }
}

// Handle Deleting Announcement (Founder can delete any announcement)
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $annId = (int)$_GET['delete'];
    try {
        $stmt = $db->prepare("DELETE FROM announcements WHERE id = ?");
        $stmt->execute([$annId]);
        if ($stmt->rowCount() > 0) {
            setFlash('success', 'Announcement removed successfully.');
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

// Filter parameters
$filterSenderRole = get('sender_role', 'all');
$search = trim(get('search', ''));

$where = [];
$params = [];

if (!empty($search)) {
    $where[] = "(a.title LIKE ? OR a.content LIKE ? OR u.name LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

if ($filterSenderRole !== 'all') {
    $where[] = "u.role = ?";
    $params[] = $filterSenderRole;
}

$whereClause = !empty($where) ? "WHERE " . implode(' AND ', $where) : "";

// Fetch all organization announcements with sender details
$stmt = $db->prepare("
    SELECT a.*, u.name AS sender_name, u.role AS sender_role, u.designation AS sender_designation
    FROM announcements a
    JOIN users u ON a.sender_id = u.id
    {$whereClause}
    ORDER BY a.created_at DESC
");
$stmt->execute($params);
$announcements = $stmt->fetchAll();

// Breakdown counts by sender role
$counts = $db->query("
    SELECT 
        COUNT(*) AS total,
        SUM(CASE WHEN u.role = 'founder' THEN 1 ELSE 0 END) AS from_founder,
        SUM(CASE WHEN u.role = 'hr' THEN 1 ELSE 0 END) AS from_hr,
        SUM(CASE WHEN u.role = 'manager' THEN 1 ELSE 0 END) AS from_manager
    FROM announcements a
    JOIN users u ON a.sender_id = u.id
")->fetch() ?: ['total' => 0, 'from_founder' => 0, 'from_hr' => 0, 'from_manager' => 0];

$pageTitle = 'Company Announcements';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title"><i class="fa-solid fa-bullhorn" style="color: var(--color-primary); margin-right: 8px;"></i> Company Announcements</h1>
        <p class="page-subtitle">View company-wide broadcasts from Founder, HR Desk, and Managers, or post new official updates</p>
    </div>
</div>

<?php if (!empty($formErrors)): ?>
    <div class="alert alert-error">
        <span><?php echo e(implode(' ', $formErrors)); ?></span>
        <button class="alert-close" onclick="this.parentElement.remove()">×</button>
    </div>
<?php endif; ?>

<!-- Stats Overview -->
<div class="stats-grid mb-6">
    <div class="stat-card accent-blue fade-in">
        <div class="stat-icon bg-blue"><i class="fa-solid fa-bullhorn"></i></div>
        <div class="stat-content">
            <div class="stat-value"><?php echo (int)$counts['total']; ?></div>
            <div class="stat-label">Total Broadcasts</div>
        </div>
    </div>
    <div class="stat-card accent-purple fade-in stagger-1">
        <div class="stat-icon bg-purple"><i class="fa-solid fa-crown"></i></div>
        <div class="stat-content">
            <div class="stat-value"><?php echo (int)$counts['from_founder']; ?></div>
            <div class="stat-label">From Founder</div>
        </div>
    </div>
    <div class="stat-card accent-green fade-in stagger-2">
        <div class="stat-icon bg-green"><i class="fa-solid fa-building-user"></i></div>
        <div class="stat-content">
            <div class="stat-value"><?php echo (int)$counts['from_hr']; ?></div>
            <div class="stat-label">From HR Desk</div>
        </div>
    </div>
    <div class="stat-card accent-yellow fade-in stagger-3">
        <div class="stat-icon bg-yellow"><i class="fa-solid fa-user-tie"></i></div>
        <div class="stat-content">
            <div class="stat-value"><?php echo (int)$counts['from_manager']; ?></div>
            <div class="stat-label">From Managers</div>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Left: Post Announcement Form -->
    <div class="lg:col-span-1">
        <div class="card fade-in" style="position: sticky; top: 80px;">
            <div class="card-header">
                <h3 class="card-title"><i class="fa-solid fa-paper-plane" style="color: var(--color-primary); margin-right: 6px;"></i> Post Official Announcement</h3>
            </div>
            <form method="POST" action="" data-validate>
                <?php echo csrfField(); ?>
                <input type="hidden" name="action" value="post_announcement">
                
                <div class="form-group">
                    <label class="form-label">Announcement Title *</label>
                    <input type="text" name="title" class="form-input" placeholder="e.g. Office Holiday Notice / Company Policy Update" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Announcement Message *</label>
                    <textarea name="content" class="form-textarea" placeholder="Write full announcement details here for the organization..." required style="height: 160px;"></textarea>
                </div>
                
                <div class="form-actions mt-4">
                    <button type="submit" class="btn btn-primary" style="width: 100%;">
                        <i class="fa-solid fa-bullhorn"></i> Broadcast to Everyone
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Right: All Announcements Feed -->
    <div class="lg:col-span-2">
        <div class="card fade-in">
            <div class="card-header" style="flex-direction: column; align-items: stretch; gap: 12px;">
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
                    <h3 class="card-title">All Organization Announcements</h3>
                    <span class="badge badge-secondary"><?php echo count($announcements); ?> Displayed</span>
                </div>

                <!-- Filters -->
                <form method="GET" action="" style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <div style="flex: 1; min-width: 180px; position: relative;">
                        <input 
                            type="text" 
                            name="search" 
                            class="form-input" 
                            placeholder="Search title, content, author..." 
                            value="<?php echo e($search); ?>"
                            style="padding-left: 32px;"
                        >
                        <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: var(--color-text-muted); font-size: 13px;"></i>
                    </div>

                    <select name="sender_role" class="form-select" onchange="this.form.submit()" style="min-width: 140px;">
                        <option value="all" <?php echo $filterSenderRole === 'all' ? 'selected' : ''; ?>>All Roles</option>
                        <option value="founder" <?php echo $filterSenderRole === 'founder' ? 'selected' : ''; ?>>Founder Only</option>
                        <option value="hr" <?php echo $filterSenderRole === 'hr' ? 'selected' : ''; ?>>HR Desk Only</option>
                        <option value="manager" <?php echo $filterSenderRole === 'manager' ? 'selected' : ''; ?>>Managers Only</option>
                    </select>

                    <?php if (!empty($search) || $filterSenderRole !== 'all'): ?>
                        <a href="<?php echo BASE_URL; ?>/founder/announcements.php" class="btn btn-outline"><i class="fa-solid fa-xmark"></i> Clear</a>
                    <?php endif; ?>
                    <button type="submit" class="btn btn-secondary"><i class="fa-solid fa-filter"></i></button>
                </form>
            </div>

            <?php if (empty($announcements)): ?>
                <div class="empty-state" style="padding: 40px 20px;">
                    <div class="empty-state-icon"><i class="fa-solid fa-bullhorn" style="font-size: 36px;"></i></div>
                    <div class="empty-state-title" style="margin-top: 10px;">No announcements found</div>
                    <div class="empty-state-text" style="color: var(--color-text-muted); font-size: 13px;">
                        No broadcasts match your search or filter criteria.
                    </div>
                </div>
            <?php else: ?>
                <div style="display: flex; flex-direction: column; gap: 16px;">
                    <?php foreach ($announcements as $ann): ?>
                        <div class="card" style="background: var(--color-bg-secondary); border: 1px solid var(--color-border); padding: 16px; border-radius: var(--radius-md);">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; margin-bottom: 10px; flex-wrap: wrap;">
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <div class="table-user-avatar" style="width: 36px; height: 36px; font-size: 13px;">
                                        <?php echo e(getInitials($ann['sender_name'])); ?>
                                    </div>
                                    <div>
                                        <div style="font-weight: 600; font-size: 14px; color: var(--color-text-white);">
                                            <?php echo e($ann['sender_name']); ?>
                                        </div>
                                        <div style="display: flex; align-items: center; gap: 6px; margin-top: 2px;">
                                            <span class="badge <?php echo roleBadge($ann['sender_role']); ?>" style="font-size: 10px;">
                                                <?php echo ucfirst(e($ann['sender_role'])); ?>
                                            </span>
                                            <?php if (!empty($ann['sender_designation'])): ?>
                                                <small class="text-muted" style="font-size: 11px;">• <?php echo e($ann['sender_designation']); ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <span class="text-muted" style="font-size: 12px;">
                                        <i class="fa-regular fa-clock"></i> <?php echo formatDate($ann['created_at']); ?> (<?php echo timeAgo($ann['created_at']); ?>)
                                    </span>
                                    <a href="?delete=<?php echo $ann['id']; ?>" class="btn btn-ghost btn-sm text-danger" data-confirm="Are you sure you want to delete this announcement?" title="Delete Announcement">
                                        <i class="fa-solid fa-trash-can"></i>
                                    </a>
                                </div>
                            </div>

                            <h4 style="margin: 0 0 8px 0; font-size: 15px; font-weight: 600; color: var(--color-primary);">
                                <?php echo e($ann['title']); ?>
                            </h4>

                            <div style="font-size: 13px; color: var(--color-text-secondary); line-height: 1.6; white-space: pre-line; background: var(--color-bg-primary); padding: 12px; border-radius: var(--radius-sm); border: 1px solid var(--color-border);">
                                <?php echo e($ann['content']); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
