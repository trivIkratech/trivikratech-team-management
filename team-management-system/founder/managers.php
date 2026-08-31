<?php
/**
 * Founder — Managers List
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/middleware.php';

requireRole([ROLE_FOUNDER]);

$db = getDB();

// Get all managers with their employee counts
$stmt = $db->query("
    SELECT u.*, 
        (SELECT COUNT(*) FROM users e WHERE e.manager_id = u.id AND e.status = 'active') AS employee_count,
        (SELECT COUNT(*) FROM tasks t WHERE t.assigned_by = u.id) AS tasks_assigned
    FROM users u 
    WHERE u.role = 'manager' 
    ORDER BY u.status ASC, u.name ASC
");
$managers = $stmt->fetchAll();

$pageTitle = 'Managers';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Managers</h1>
        <p class="page-subtitle"><?php echo count($managers); ?> manager(s) in the system</p>
    </div>
    <div class="page-header-actions">
        <a href="<?php echo BASE_URL; ?>/founder/user-management.php?action=add&role=manager" class="btn btn-primary">+ Add Manager</a>
    </div>
</div>

<?php if (empty($managers)): ?>
    <div class="card">
        <div class="empty-state">
            <div class="empty-state-icon"><i class="fa-solid fa-user-tie"></i></div>
            <div class="empty-state-title">No managers yet</div>
            <div class="empty-state-text">Add a manager to start building your team.</div>
        </div>
    </div>
<?php else: ?>
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Manager</th>
                    <th>Email</th>
                    <th>Employees</th>
                    <th>Tasks Assigned</th>
                    <th>Status</th>
                    <th>Joined</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($managers as $manager): ?>
                    <tr>
                        <td>
                            <div class="table-user">
                                <div class="table-user-avatar"><?php echo e(getInitials($manager['name'])); ?></div>
                                <div>
                                    <div class="table-user-name"><?php echo e($manager['name']); ?></div>
                                </div>
                            </div>
                        </td>
                        <td><?php echo e($manager['email']); ?></td>
                        <td><span class="badge badge-info"><?php echo $manager['employee_count']; ?></span></td>
                        <td><?php echo $manager['tasks_assigned']; ?></td>
                        <td><span class="badge <?php echo userStatusBadge($manager['status']); ?>"><?php echo ucfirst(e($manager['status'])); ?></span></td>
                        <td><?php echo formatDate($manager['created_at']); ?></td>
                        <td>
                            <div class="table-actions">
                                <a href="<?php echo BASE_URL; ?>/founder/user-management.php?action=edit&id=<?php echo $manager['id']; ?>" class="btn btn-ghost btn-sm" title="Edit"><i class="fa-solid fa-pen"></i></a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
