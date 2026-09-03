<?php
/**
 * Employee — Announcements Feed
 * 
 * Allows employees to read all company-wide, HR, and team announcements
 * published by Founder, HR, and their Manager.
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/middleware.php';

requireRole([ROLE_EMPLOYEE]);

$db = getDB();
$currentUser = getCurrentUser();
$userId = (int)$currentUser['id'];
$managerId = (int)($currentUser['manager_id'] ?? 0);

// Filter params
$filterRole = get('role', 'all');
$searchQuery = trim(get('search', ''));

// Build Announcements Query
// An employee can view:
// 1. All announcements from Founder (Company-wide)
// 2. All announcements from HR (HR & Policy updates)
// 3. Announcements from their assigned Manager (Team updates)
$where = ["(u.role IN ('founder', 'hr')" . ($managerId > 0 ? " OR a.sender_id = {$managerId}" : "") . ")"];
$params = [];

if (!empty($filterRole) && in_array($filterRole, ['founder', 'hr', 'manager'])) {
    $where[] = "u.role = ?";
    $params[] = $filterRole;
}

if (!empty($searchQuery)) {
    $where[] = "(a.title LIKE ? OR a.content LIKE ? OR u.name LIKE ?)";
    $params[] = "%{$searchQuery}%";
    $params[] = "%{$searchQuery}%";
    $params[] = "%{$searchQuery}%";
}

$whereSql = implode(' AND ', $where);

$stmt = $db->prepare("
    SELECT a.*, u.name AS sender_name, u.role AS sender_role, u.designation AS sender_designation
    FROM announcements a
    JOIN users u ON a.sender_id = u.id
    WHERE {$whereSql}
    ORDER BY a.created_at DESC
");
$stmt->execute($params);
$announcements = $stmt->fetchAll();

// Calculate counts
$allCountStmt = $db->query("
    SELECT 
        COUNT(*) AS total,
        SUM(CASE WHEN u.role = 'founder' THEN 1 ELSE 0 END) AS founder_count,
        SUM(CASE WHEN u.role = 'hr' THEN 1 ELSE 0 END) AS hr_count,
        SUM(CASE WHEN u.role = 'manager' THEN 1 ELSE 0 END) AS manager_count
    FROM announcements a
    JOIN users u ON a.sender_id = u.id
    WHERE u.role IN ('founder', 'hr')" . ($managerId > 0 ? " OR a.sender_id = {$managerId}" : "") . "
");
$counts = $allCountStmt->fetch() ?: ['total' => 0, 'founder_count' => 0, 'hr_count' => 0, 'manager_count' => 0];

$pageTitle = 'Announcements';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title"><i class="fa-solid fa-bullhorn" style="color: var(--color-primary); margin-right: 8px;"></i> Company & Team Announcements</h1>
        <p class="page-subtitle">Stay updated with official broadcasts from Founder, HR, and Management</p>
    </div>
</div>

<!-- Stats Grid -->
<div class="stats-grid">
    <a href="?role=all" style="text-decoration: none; color: inherit;">
        <div class="stat-card accent-blue fade-in <?php echo $filterRole === 'all' ? 'active-card-filter' : ''; ?>">
            <div class="stat-icon bg-blue"><i class="fa-solid fa-bullhorn"></i></div>
            <div class="stat-content">
                <div class="stat-value"><?php echo (int)$counts['total']; ?></div>
                <div class="stat-label">All Announcements</div>
            </div>
        </div>
    </a>
    <a href="?role=founder" style="text-decoration: none; color: inherit;">
        <div class="stat-card accent-purple fade-in stagger-1 <?php echo $filterRole === 'founder' ? 'active-card-filter' : ''; ?>">
            <div class="stat-icon bg-purple"><i class="fa-solid fa-crown"></i></div>
            <div class="stat-content">
                <div class="stat-value"><?php echo (int)$counts['founder_count']; ?></div>
                <div class="stat-label">From Founder</div>
            </div>
        </div>
    </a>
    <a href="?role=hr" style="text-decoration: none; color: inherit;">
        <div class="stat-card accent-green fade-in stagger-2 <?php echo $filterRole === 'hr' ? 'active-card-filter' : ''; ?>">
            <div class="stat-icon bg-green"><i class="fa-solid fa-building-user"></i></div>
            <div class="stat-content">
                <div class="stat-value"><?php echo (int)$counts['hr_count']; ?></div>
                <div class="stat-label">From HR Desk</div>
            </div>
        </div>
    </a>
    <a href="?role=manager" style="text-decoration: none; color: inherit;">
        <div class="stat-card accent-yellow fade-in stagger-3 <?php echo $filterRole === 'manager' ? 'active-card-filter' : ''; ?>">
            <div class="stat-icon bg-yellow"><i class="fa-solid fa-user-tie"></i></div>
            <div class="stat-content">
                <div class="stat-value"><?php echo (int)$counts['manager_count']; ?></div>
                <div class="stat-label">From Team Manager</div>
            </div>
        </div>
    </a>
</div>

<!-- Search & Filter Bar -->
<form method="GET" action="" class="filter-bar fade-in">
    <div style="flex: 1; min-width: 220px; position: relative;">
        <input 
            type="text" 
            name="search" 
            class="form-input" 
            placeholder="Search announcement title, text, or author..." 
            value="<?php echo e($searchQuery); ?>"
            style="padding-left: 36px;"
        >
        <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--color-text-muted); font-size: 14px;"></i>
    </div>

    <div style="min-width: 160px;">
        <select name="role" class="form-select" onchange="this.form.submit()">
            <option value="all" <?php echo $filterRole === 'all' ? 'selected' : ''; ?>>All Senders</option>
            <option value="founder" <?php echo $filterRole === 'founder' ? 'selected' : ''; ?>>Founder Only</option>
            <option value="hr" <?php echo $filterRole === 'hr' ? 'selected' : ''; ?>>HR Desk Only</option>
            <option value="manager" <?php echo $filterRole === 'manager' ? 'selected' : ''; ?>>Manager Only</option>
        </select>
    </div>

    <?php if (!empty($searchQuery) || $filterRole !== 'all'): ?>
        <a href="?" class="btn btn-outline"><i class="fa-solid fa-xmark"></i> Clear Filters</a>
    <?php endif; ?>
    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-filter"></i> Apply</button>
</form>

<!-- Announcements List -->
<div class="card fade-in" style="padding: 0; overflow: hidden;">
    <div class="card-header" style="padding: 18px 24px; margin: 0; background: var(--color-bg-secondary);">
        <h3 class="card-title" style="display: flex; align-items: center; gap: 8px;">
            <i class="fa-solid fa-bullhorn" style="color: var(--color-primary);"></i> Published Announcements Feed
        </h3>
        <span class="badge badge-primary"><?php echo count($announcements); ?> Total</span>
    </div>

    <div style="padding: 24px;">
        <?php if (empty($announcements)): ?>
            <div class="empty-state" style="padding: 40px 20px;">
                <div class="empty-state-icon"><i class="fa-solid fa-bullhorn" style="font-size: 40px; color: var(--color-text-muted);"></i></div>
                <div class="empty-state-title" style="margin-top: 12px; font-size: 16px; font-weight: 700;">No Announcements Found</div>
                <div class="empty-state-text" style="color: var(--color-text-muted); font-size: 13px;">
                    <?php if (!empty($searchQuery) || $filterRole !== 'all'): ?>
                        No announcements match your search criteria. Try clearing the filter.
                    <?php else: ?>
                        No announcements have been published yet by Founder, HR, or Management.
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <div style="display: flex; flex-direction: column; gap: 16px;">
                <?php foreach ($announcements as $ann): ?>
                    <?php
                    $roleClass = 'badge-role-' . $ann['sender_role'];
                    $senderIcon = ($ann['sender_role'] === 'founder') ? 'fa-crown' : (($ann['sender_role'] === 'hr') ? 'fa-building-user' : 'fa-user-tie');
                    $senderBg = ($ann['sender_role'] === 'founder') ? 'linear-gradient(135deg, #8b5cf6, #ec4899)' : (($ann['sender_role'] === 'hr') ? 'linear-gradient(135deg, #10b981, #06b6d4)' : 'linear-gradient(135deg, #f59e0b, #ef4444)');
                    ?>
                    <div class="card" style="background: var(--color-bg-secondary); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: 18px 20px; transition: transform 0.15s ease, border-color 0.15s ease;" onmouseover="this.style.borderColor='var(--color-primary)'" onmouseout="this.style.borderColor='var(--color-border)'">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; flex-wrap: wrap; margin-bottom: 12px;">
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <div style="width: 42px; height: 42px; border-radius: 50%; background: <?php echo $senderBg; ?>; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 15px; font-weight: bold; flex-shrink: 0; box-shadow: 0 4px 10px rgba(0,0,0,0.15);">
                                    <?php echo e(getInitials($ann['sender_name'])); ?>
                                </div>
                                <div>
                                    <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                                        <strong style="font-size: 14px; color: var(--color-text-main);"><?php echo e($ann['sender_name']); ?></strong>
                                        <span class="chat-role-badge <?php echo $roleClass; ?>" style="font-size: 10px; padding: 2px 8px;"><i class="fa-solid <?php echo $senderIcon; ?>"></i> <?php echo ucfirst(e($ann['sender_role'])); ?></span>
                                    </div>
                                    <div style="font-size: 11px; color: var(--color-text-muted); margin-top: 2px;">
                                        <?php echo e($ann['sender_designation'] ?: ucfirst(e($ann['sender_role']))); ?>
                                    </div>
                                </div>
                            </div>
                            <div style="font-size: 12px; color: var(--color-text-muted); display: flex; align-items: center; gap: 6px;">
                                <i class="fa-regular fa-clock"></i> <?php echo timeAgo($ann['created_at']); ?> · <?php echo formatDate($ann['created_at']); ?>
                            </div>
                        </div>

                        <div style="border-top: 1px dashed var(--color-border); padding-top: 12px; margin-top: 6px;">
                            <h4 style="margin: 0 0 8px 0; font-size: 15px; font-weight: 700; color: var(--color-text-white);">
                                📢 <?php echo e($ann['title']); ?>
                            </h4>
                            <div style="font-size: 13.5px; color: var(--color-text); line-height: 1.6; white-space: pre-line; word-break: break-word;">
                                <?php echo nl2br(e($ann['content'])); ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.active-card-filter {
    border-color: var(--color-primary) !important;
    box-shadow: 0 0 0 2px var(--color-primary-border);
}
</style>

<?php include __DIR__ . '/../includes/footer.php'; ?>
