<?php
/**
 * Founder — Roles & Permissions Management
 * 
 * Allows the Founder to create, edit, and delete custom organizational roles
 * and configure their base workspace capabilities.
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/middleware.php';

requireRole([ROLE_FOUNDER]);

$db = getDB();
$action = get('action', 'list');
$editRole = null;
$formErrors = [];

// Handle CREATE ROLE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('form_action') === 'create_role') {
    requireCsrf();
    
    $name = trim(post('name'));
    $slug = trim(strtolower(post('slug')));
    $description = trim(post('description'));
    $baseRole = post('base_role');
    
    // Auto-generate slug if empty
    if (empty($slug) && !empty($name)) {
        $slug = preg_replace('/[^a-z0-9]+/', '_', strtolower($name));
        $slug = trim($slug, '_');
    }
    
    // Validate
    if (empty($name)) $formErrors[] = 'Role name is required.';
    if (empty($slug)) $formErrors[] = 'Role identifier (slug) is required.';
    if (!preg_match('/^[a-z0-9_]+$/', $slug)) $formErrors[] = 'Role identifier can only contain lowercase letters, numbers, and underscores.';
    if (!in_array($baseRole, ['employee', 'manager', 'hr'])) $formErrors[] = 'Please select a valid base access workspace.';
    
    // Check slug uniqueness
    if (empty($formErrors)) {
        $stmt = $db->prepare("SELECT id FROM roles WHERE slug = ?");
        $stmt->execute([$slug]);
        if ($stmt->fetch()) {
            $formErrors[] = 'A role with this identifier already exists.';
        }
    }
    
    if (empty($formErrors)) {
        $stmt = $db->prepare("INSERT INTO roles (name, slug, description, base_role, is_system) VALUES (?, ?, ?, ?, 0)");
        $stmt->execute([$name, $slug, $description ?: null, $baseRole]);
        
        setFlash('success', 'Custom role "' . $name . '" created successfully.');
        header('Location: ' . BASE_URL . '/founder/roles.php');
        exit;
    }
}

// Handle EDIT ROLE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('form_action') === 'edit_role') {
    requireCsrf();
    
    $roleId = (int)post('role_id');
    $name = trim(post('name'));
    $description = trim(post('description'));
    $baseRole = post('base_role');
    
    // Fetch role
    $stmt = $db->prepare("SELECT * FROM roles WHERE id = ?");
    $stmt->execute([$roleId]);
    $roleRecord = $stmt->fetch();
    
    if (!$roleRecord) {
        $formErrors[] = 'Role not found.';
    } elseif ($roleRecord['is_system']) {
        $formErrors[] = 'System default roles cannot be modified.';
    } else {
        if (empty($name)) $formErrors[] = 'Role name is required.';
        if (!in_array($baseRole, ['employee', 'manager', 'hr'])) $formErrors[] = 'Please select a valid base access workspace.';
        
        if (empty($formErrors)) {
            $stmt = $db->prepare("UPDATE roles SET name = ?, description = ?, base_role = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$name, $description ?: null, $baseRole, $roleId]);
            
            setFlash('success', 'Role "' . $name . '" updated successfully.');
            header('Location: ' . BASE_URL . '/founder/roles.php');
            exit;
        }
    }
}

// Handle DELETE ROLE
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $delId = (int)$_GET['delete'];
    
    $stmt = $db->prepare("SELECT * FROM roles WHERE id = ?");
    $stmt->execute([$delId]);
    $roleRecord = $stmt->fetch();
    
    if (!$roleRecord) {
        setFlash('error', 'Role not found.');
    } elseif ($roleRecord['is_system']) {
        setFlash('error', 'System default roles cannot be deleted.');
    } else {
        // Check if any users currently have this role
        $uStmt = $db->prepare("SELECT COUNT(*) FROM users WHERE role = ?");
        $uStmt->execute([$roleRecord['slug']]);
        $userCount = $uStmt->fetchColumn();
        
        if ($userCount > 0) {
            setFlash('error', 'Cannot delete role "' . $roleRecord['name'] . '" because ' . $userCount . ' user(s) are currently assigned to it. Reassign those users first.');
        } else {
            $delStmt = $db->prepare("DELETE FROM roles WHERE id = ?");
            $delStmt->execute([$delId]);
            setFlash('success', 'Custom role "' . $roleRecord['name'] . '" deleted successfully.');
        }
    }
    header('Location: ' . BASE_URL . '/founder/roles.php');
    exit;
}

// Fetch single role for edit
if ($action === 'edit' && isset($_GET['id']) && is_numeric($_GET['id'])) {
    $editId = (int)$_GET['id'];
    $stmt = $db->prepare("SELECT * FROM roles WHERE id = ?");
    $stmt->execute([$editId]);
    $editRole = $stmt->fetch();
    if (!$editRole || $editRole['is_system']) {
        setFlash('error', 'Role not editable.');
        header('Location: ' . BASE_URL . '/founder/roles.php');
        exit;
    }
}

// Fetch all roles with assigned user counts
$roles = $db->query("
    SELECT r.*, COUNT(u.id) AS assigned_users_count 
    FROM roles r 
    LEFT JOIN users u ON r.slug = u.role 
    GROUP BY r.id 
    ORDER BY r.is_system DESC, r.name ASC
")->fetchAll();

$systemRolesCount = count(array_filter($roles, fn($r) => $r['is_system'] == 1));
$customRolesCount = count(array_filter($roles, fn($r) => $r['is_system'] == 0));
$totalUsersCount = array_sum(array_column($roles, 'assigned_users_count'));

$pageTitle = 'Roles & Permissions';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title"><i class="fa-solid fa-user-shield" style="color: var(--color-primary); margin-right: 8px;"></i> Roles & Permissions</h1>
        <p class="page-subtitle">Configure organization roles, base workspace capabilities, and access levels</p>
    </div>
    <div style="display: flex; gap: 8px;">
        <a href="<?php echo BASE_URL; ?>/founder/user-management.php" class="btn btn-outline"><i class="fa-solid fa-users-gear"></i> User Management</a>
        <?php if ($action === 'list'): ?>
            <a href="?action=add" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Create New Role</a>
        <?php else: ?>
            <a href="<?php echo BASE_URL; ?>/founder/roles.php" class="btn btn-secondary">Back to Roles List</a>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($formErrors)): ?>
    <div class="alert alert-error">
        <span><?php echo e(implode(' ', $formErrors)); ?></span>
        <button class="alert-close" onclick="this.parentElement.remove()">×</button>
    </div>
<?php endif; ?>

<!-- Stats Grid -->
<div class="stats-grid mb-6">
    <div class="stat-card accent-blue fade-in">
        <div class="stat-icon bg-blue"><i class="fa-solid fa-shield-halved"></i></div>
        <div class="stat-content">
            <div class="stat-value"><?php echo count($roles); ?></div>
            <div class="stat-label">Total Defined Roles</div>
        </div>
    </div>
    <div class="stat-card accent-purple fade-in stagger-1">
        <div class="stat-icon bg-purple"><i class="fa-solid fa-cube"></i></div>
        <div class="stat-content">
            <div class="stat-value"><?php echo $systemRolesCount; ?></div>
            <div class="stat-label">Core System Roles</div>
        </div>
    </div>
    <div class="stat-card accent-green fade-in stagger-2">
        <div class="stat-icon bg-green"><i class="fa-solid fa-wand-magic-sparkles"></i></div>
        <div class="stat-content">
            <div class="stat-value"><?php echo $customRolesCount; ?></div>
            <div class="stat-label">Custom Created Roles</div>
        </div>
    </div>
    <div class="stat-card accent-yellow fade-in stagger-3">
        <div class="stat-icon bg-yellow"><i class="fa-solid fa-users"></i></div>
        <div class="stat-content">
            <div class="stat-value"><?php echo $totalUsersCount; ?></div>
            <div class="stat-label">Total Assigned Staff</div>
        </div>
    </div>
</div>

<?php if ($action === 'add'): ?>
    <!-- ADD ROLE FORM -->
    <div class="card fade-in" style="max-width: 640px; margin: 0 auto;">
        <div class="card-header">
            <h3 class="card-title"><i class="fa-solid fa-plus" style="color: var(--color-primary); margin-right: 6px;"></i> Create New Custom Role</h3>
        </div>
        <form method="POST" action="" data-validate>
            <?php echo csrfField(); ?>
            <input type="hidden" name="form_action" value="create_role">

            <div class="form-group">
                <label class="form-label">Role Display Name *</label>
                <input type="text" name="name" id="role-name-input" class="form-input" placeholder="e.g. QA Engineer, Finance Head, Team Lead, Product Designer" required value="<?php echo e(post('name')); ?>">
                <small class="text-muted" style="font-size: 11px;">The title shown across dashboards, badges, and user cards.</small>
            </div>

            <div class="form-group">
                <label class="form-label">Role Identifier (Slug) *</label>
                <input type="text" name="slug" id="role-slug-input" class="form-input" placeholder="e.g. qa_engineer, finance_head, team_lead" required value="<?php echo e(post('slug')); ?>">
                <small class="text-muted" style="font-size: 11px;">Unique database key. Lowercase letters, numbers, and underscores only.</small>
            </div>

            <div class="form-group">
                <label class="form-label">Base Workspace & Access Tier *</label>
                <select name="base_role" class="form-select" required>
                    <option value="employee" <?php echo post('base_role') === 'employee' ? 'selected' : ''; ?>>
                        Employee Workspace (Attendance, Tasks, Meetings, Leaves, Chat, Support)
                    </option>
                    <option value="manager" <?php echo post('base_role') === 'manager' ? 'selected' : ''; ?>>
                        Manager Workspace (Team Oversight, Task Supervision, Shift Monitoring, Leaves, Meetings)
                    </option>
                    <option value="hr" <?php echo post('base_role') === 'hr' ? 'selected' : ''; ?>>
                        HR Workspace (Employee Records, Payroll, Leaves Approval, Tickets, Company Attendance)
                    </option>
                </select>
                <small class="text-muted" style="font-size: 11px;">Determines the layout and tool modules this role can access upon login.</small>
            </div>

            <div class="form-group">
                <label class="form-label">Role Description / Scope</label>
                <textarea name="description" class="form-textarea" placeholder="Briefly describe the responsibilities and scope of this role..." rows="3"><?php echo e(post('description')); ?></textarea>
            </div>

            <div class="form-actions" style="margin-top: 20px; display: flex; gap: 10px;">
                <a href="<?php echo BASE_URL; ?>/founder/roles.php" class="btn btn-outline" style="flex: 1; text-align: center;">Cancel</a>
                <button type="submit" class="btn btn-primary" style="flex: 1;"><i class="fa-solid fa-circle-check"></i> Save Custom Role</button>
            </div>
        </form>
    </div>

    <script>
    document.getElementById('role-name-input')?.addEventListener('input', function() {
        const slugInput = document.getElementById('role-slug-input');
        if (slugInput && !slugInput.dataset.manual) {
            slugInput.value = this.value.toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '');
        }
    });
    document.getElementById('role-slug-input')?.addEventListener('input', function() {
        this.dataset.manual = 'true';
    });
    </script>

<?php elseif ($action === 'edit' && $editRole): ?>
    <!-- EDIT ROLE FORM -->
    <div class="card fade-in" style="max-width: 640px; margin: 0 auto;">
        <div class="card-header">
            <h3 class="card-title"><i class="fa-solid fa-pen" style="color: var(--color-primary); margin-right: 6px;"></i> Edit Role: <?php echo e($editRole['name']); ?></h3>
        </div>
        <form method="POST" action="" data-validate>
            <?php echo csrfField(); ?>
            <input type="hidden" name="form_action" value="edit_role">
            <input type="hidden" name="role_id" value="<?php echo $editRole['id']; ?>">

            <div class="form-group">
                <label class="form-label">Role Identifier (Slug)</label>
                <input type="text" class="form-input" disabled value="<?php echo e($editRole['slug']); ?>" style="background: var(--color-bg-tertiary); cursor: not-allowed;">
                <small class="text-muted" style="font-size: 11px;">Unique role key cannot be changed after creation.</small>
            </div>

            <div class="form-group">
                <label class="form-label">Role Display Name *</label>
                <input type="text" name="name" class="form-input" required value="<?php echo e(post('name', $editRole['name'])); ?>">
            </div>

            <div class="form-group">
                <label class="form-label">Base Workspace & Access Tier *</label>
                <select name="base_role" class="form-select" required>
                    <option value="employee" <?php echo $editRole['base_role'] === 'employee' ? 'selected' : ''; ?>>
                        Employee Workspace (Attendance, Tasks, Meetings, Leaves, Chat, Support)
                    </option>
                    <option value="manager" <?php echo $editRole['base_role'] === 'manager' ? 'selected' : ''; ?>>
                        Manager Workspace (Team Oversight, Task Supervision, Shift Monitoring, Leaves, Meetings)
                    </option>
                    <option value="hr" <?php echo $editRole['base_role'] === 'hr' ? 'selected' : ''; ?>>
                        HR Workspace (Employee Records, Payroll, Leaves Approval, Tickets, Company Attendance)
                    </option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Role Description / Scope</label>
                <textarea name="description" class="form-textarea" rows="3"><?php echo e(post('description', $editRole['description'])); ?></textarea>
            </div>

            <div class="form-actions" style="margin-top: 20px; display: flex; gap: 10px;">
                <a href="<?php echo BASE_URL; ?>/founder/roles.php" class="btn btn-outline" style="flex: 1; text-align: center;">Cancel</a>
                <button type="submit" class="btn btn-primary" style="flex: 1;"><i class="fa-solid fa-circle-check"></i> Update Role</button>
            </div>
        </form>
    </div>

<?php else: ?>
    <!-- ROLES TABLE LIST -->
    <div class="table-container fade-in">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Role Name</th>
                    <th>Identifier / Slug</th>
                    <th>Base Workspace Access</th>
                    <th>Description</th>
                    <th>Assigned Staff</th>
                    <th>Type</th>
                    <th style="text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($roles as $r): ?>
                    <?php 
                    $isSys = (bool)$r['is_system']; 
                    $badgeClass = roleBadge($r['slug']);
                    ?>
                    <tr>
                        <td>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <strong style="font-size: 13.5px; color: var(--color-text-white);"><?php echo e($r['name']); ?></strong>
                                <span class="badge <?php echo $badgeClass; ?>" style="font-size: 10px;"><?php echo strtoupper(e($r['slug'])); ?></span>
                            </div>
                        </td>
                        <td><code><?php echo e($r['slug']); ?></code></td>
                        <td>
                            <span style="font-size: 12px; font-weight: 500; color: var(--color-primary); display: inline-flex; align-items: center; gap: 5px;">
                                <?php if ($r['base_role'] === 'founder'): ?>
                                    <i class="fa-solid fa-crown" style="color: #ec4899;"></i> Founder Executive
                                <?php elseif ($r['base_role'] === 'manager'): ?>
                                    <i class="fa-solid fa-user-tie" style="color: #f59e0b;"></i> Manager Workspace
                                <?php elseif ($r['base_role'] === 'hr'): ?>
                                    <i class="fa-solid fa-building-user" style="color: #10b981;"></i> HR Workspace
                                <?php else: ?>
                                    <i class="fa-solid fa-laptop-code" style="color: #3b82f6;"></i> Employee Workspace
                                <?php endif; ?>
                            </span>
                        </td>
                        <td style="max-width: 260px;">
                            <span class="text-muted" style="font-size: 12px; line-height: 1.4; display: inline-block;">
                                <?php echo e($r['description'] ?: '—'); ?>
                            </span>
                        </td>
                        <td>
                            <a href="<?php echo BASE_URL; ?>/founder/user-management.php?role_filter=<?php echo urlencode($r['slug']); ?>" style="text-decoration: none;">
                                <span class="badge <?php echo $r['assigned_users_count'] > 0 ? 'badge-primary' : 'badge-secondary'; ?>">
                                    <?php echo (int)$r['assigned_users_count']; ?> User<?php echo $r['assigned_users_count'] == 1 ? '' : 's'; ?>
                                </span>
                            </a>
                        </td>
                        <td>
                            <?php if ($isSys): ?>
                                <span class="badge badge-purple" style="font-size: 10px;"><i class="fa-solid fa-lock"></i> System Core</span>
                            <?php else: ?>
                                <span class="badge badge-success" style="font-size: 10px;"><i class="fa-solid fa-wand-magic-sparkles"></i> Custom Role</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align: right;">
                            <div class="table-actions" style="display: inline-flex; gap: 4px;">
                                <?php if (!$isSys): ?>
                                    <a href="?action=edit&id=<?php echo $r['id']; ?>" class="btn btn-ghost btn-sm" title="Edit Role"><i class="fa-solid fa-pen"></i></a>
                                    <a href="?delete=<?php echo $r['id']; ?>" class="btn btn-ghost btn-sm" data-confirm="Are you sure you want to permanently delete the role '<?php echo e($r['name']); ?>'?" title="Delete Role"><i class="fa-solid fa-trash-can"></i></a>
                                <?php else: ?>
                                    <span class="text-muted" style="font-size: 11px; padding: 4px 8px;"><i class="fa-solid fa-shield"></i> Protected</span>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
