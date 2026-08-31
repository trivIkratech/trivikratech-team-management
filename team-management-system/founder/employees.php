<?php
/**
 * Founder — Employees List
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/middleware.php';

requireRole([ROLE_FOUNDER]);

$db = getDB();

// Filters
$filterManager = get('manager_id');
$filterStatus = get('status');

$where = ["u.role = 'employee'"];
$params = [];

if ($filterManager) {
    $where[] = "u.manager_id = ?";
    $params[] = (int)$filterManager;
}
if ($filterStatus) {
    $where[] = "u.status = ?";
    $params[] = $filterStatus;
}

$whereClause = implode(' AND ', $where);

// Get employees
$stmt = $db->prepare("
    SELECT u.*, m.name AS manager_name
    FROM users u 
    LEFT JOIN users m ON u.manager_id = m.id 
    WHERE {$whereClause}
    ORDER BY u.status ASC, u.name ASC
");
$stmt->execute($params);
$employees = $stmt->fetchAll();

// Get managers for filter dropdown
$managers = $db->query("SELECT id, name FROM users WHERE role = 'manager' AND status = 'active' ORDER BY name")->fetchAll();

$pageTitle = 'Employees';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Employees</h1>
        <p class="page-subtitle"><?php echo count($employees); ?> employee(s) found</p>
    </div>
    <div class="page-header-actions">
        <a href="<?php echo BASE_URL; ?>/founder/user-management.php?action=add&role=employee" class="btn btn-primary">+ Add Employee</a>
    </div>
</div>

<!-- Filters -->
<form method="GET" class="filter-bar">
    <select name="manager_id" class="form-select" onchange="this.form.submit()">
        <option value="">All Managers</option>
        <?php foreach ($managers as $mgr): ?>
            <option value="<?php echo $mgr['id']; ?>" <?php echo $filterManager == $mgr['id'] ? 'selected' : ''; ?>>
                <?php echo e($mgr['name']); ?>
            </option>
        <?php endforeach; ?>
    </select>
    <select name="status" class="form-select" onchange="this.form.submit()">
        <option value="">All Status</option>
        <option value="active" <?php echo $filterStatus === 'active' ? 'selected' : ''; ?>>Active</option>
        <option value="inactive" <?php echo $filterStatus === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
    </select>
    <?php if ($filterManager || $filterStatus): ?>
        <a href="<?php echo BASE_URL; ?>/founder/employees.php" class="btn btn-sm btn-outline">Clear Filters</a>
    <?php endif; ?>
</form>

<?php if (empty($employees)): ?>
    <div class="card">
        <div class="empty-state">
            <div class="empty-state-icon"><i class="fa-solid fa-users"></i></div>
            <div class="empty-state-title">No employees found</div>
            <div class="empty-state-text">Add employees or adjust your filters.</div>
        </div>
    </div>
<?php else: ?>
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Employee</th>
                    <th>Email</th>
                    <th>Manager</th>
                    <th>Status</th>
                    <th>Joined</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($employees as $emp): ?>
                    <tr>
                        <td>
                            <div class="table-user">
                                <div class="table-user-avatar"><?php echo e(getInitials($emp['name'])); ?></div>
                                <div>
                                    <div class="table-user-name"><?php echo e($emp['name']); ?></div>
                                </div>
                            </div>
                        </td>
                        <td><?php echo e($emp['email']); ?></td>
                        <td><?php echo e($emp['manager_name'] ?? '—'); ?></td>
                        <td><span class="badge <?php echo userStatusBadge($emp['status']); ?>"><?php echo ucfirst(e($emp['status'])); ?></span></td>
                        <td><?php echo formatDate($emp['created_at']); ?></td>
                        <td>
                            <div class="table-actions">
                                <a href="<?php echo BASE_URL; ?>/founder/user-management.php?action=edit&id=<?php echo $emp['id']; ?>" class="btn btn-ghost btn-sm" title="Edit"><i class="fa-solid fa-pen"></i></a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
