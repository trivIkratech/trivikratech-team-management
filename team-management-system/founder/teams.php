<?php
/**
 * Founder — Teams & Squads Management
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/middleware.php';

requireRole([ROLE_FOUNDER]);

$db = getDB();
$userId = getUserId();
$formErrors = [];

// Handle Create Team
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('form_action') === 'create_team') {
    requireCsrf();
    
    $name = trim(post('name', ''));
    $description = trim(post('description', ''));
    $leaderId = (int)post('leader_id');
    $memberIds = post('members') ?: [];
    
    if (empty($name)) {
        $formErrors[] = 'Team name is required.';
    }
    if (empty($leaderId)) {
        $formErrors[] = 'Team leader is required.';
    }
    
    if (empty($formErrors)) {
        createTeam($name, $description, $leaderId, $userId, is_array($memberIds) ? $memberIds : []);
        setFlash('success', 'Team "' . e($name) . '" created successfully with designated members.');
        redirect(BASE_URL . '/founder/teams.php');
    }
}

// Handle Edit Team
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('form_action') === 'edit_team') {
    requireCsrf();
    
    $teamId = (int)post('team_id');
    $name = trim(post('name', ''));
    $description = trim(post('description', ''));
    $leaderId = (int)post('leader_id');
    $memberIds = post('members') ?: [];
    
    if (empty($name)) {
        $formErrors[] = 'Team name is required.';
    }
    if (empty($leaderId)) {
        $formErrors[] = 'Team leader is required.';
    }
    
    if (empty($formErrors)) {
        updateTeam($teamId, $name, $description, $leaderId, is_array($memberIds) ? $memberIds : []);
        setFlash('success', 'Team "' . e($name) . '" updated successfully.');
        redirect(BASE_URL . '/founder/teams.php');
    }
}

// Handle Delete Team
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $delId = (int)$_GET['delete'];
    deleteTeam($delId);
    setFlash('success', 'Team deleted successfully.');
    redirect(BASE_URL . '/founder/teams.php');
}

// Fetch all teams
$teams = getAllTeams();

// Fetch all staff users for selection
try {
    $allStaff = $db->query("
        SELECT id, employee_id, name, email, role, designation 
        FROM users 
        WHERE status = 'active' 
        ORDER BY role ASC, name ASC
    ")->fetchAll();
} catch (Throwable $e) {
    $allStaff = [];
}

$managersAndLeaders = array_filter($allStaff, function($u) {
    return in_array($u['role'], ['manager', 'founder', 'hr']);
});

// Edit team data if requested
$editTeam = null;
$editTeamMembers = [];
if (get('action') === 'edit' && get('id')) {
    $editTeam = getTeamById((int)get('id'));
    if ($editTeam) {
        $membersData = getTeamMembers((int)get('id'));
        $editTeamMembers = array_column($membersData, 'id');
    }
}

$pageTitle = 'Teams & Squads';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Teams & Squads</h1>
        <p class="page-subtitle">Organize employees into functional squads and assign collaborative team tasks</p>
    </div>
    <div class="page-header-actions">
        <button type="button" class="btn btn-primary" onclick="openModal('createTeamModal')">
            <i class="fa-solid fa-plus"></i> Create New Team
        </button>
    </div>
</div>

<?php if (!empty($formErrors)): ?>
    <div class="alert alert-error">
        <span><?php echo e(implode(' ', $formErrors)); ?></span>
        <button class="alert-close" onclick="this.parentElement.remove()">×</button>
    </div>
<?php endif; ?>

<!-- Summary Stats -->
<div class="stats-grid" style="margin-bottom: var(--space-6);">
    <div class="stat-card">
        <div class="stat-card-icon" style="background: rgba(99, 102, 241, 0.12); color: var(--color-primary);"><i class="fa-solid fa-people-group"></i></div>
        <div class="stat-card-info">
            <div class="stat-card-value"><?php echo count($teams); ?></div>
            <div class="stat-card-label">Active Teams</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-card-icon" style="background: rgba(16, 185, 129, 0.12); color: var(--color-success);"><i class="fa-solid fa-users"></i></div>
        <div class="stat-card-info">
            <div class="stat-card-value">
                <?php
                $totalMembers = 0;
                foreach ($teams as $t) {
                    $totalMembers += (int)$t['member_count'];
                }
                echo $totalMembers;
                ?>
            </div>
            <div class="stat-card-label">Total Assigned Members</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-card-icon" style="background: rgba(245, 158, 11, 0.12); color: var(--color-warning);"><i class="fa-solid fa-list-check"></i></div>
        <div class="stat-card-info">
            <div class="stat-card-value">
                <?php
                $totalTasks = 0;
                foreach ($teams as $t) {
                    $totalTasks += (int)$t['task_count'];
                }
                echo $totalTasks;
                ?>
            </div>
            <div class="stat-card-label">Assigned Team Tasks</div>
        </div>
    </div>
</div>

<?php if (empty($teams)): ?>
    <div class="card">
        <div class="empty-state" style="padding: 48px 20px;">
            <div class="empty-state-icon"><i class="fa-solid fa-people-group" style="font-size: 40px; color: var(--color-primary);"></i></div>
            <div class="empty-state-title" style="margin-top: 14px; font-size: 18px;">No Teams Created Yet</div>
            <div class="empty-state-text" style="color: var(--color-text-muted); font-size: 13.5px; max-width: 420px; margin: 8px auto 20px;">
                Create squads like "Frontend Team", "SEO & Marketing", or "Sales" to assign and monitor tasks across entire groups simultaneously.
            </div>
            <button type="button" class="btn btn-primary" onclick="openModal('createTeamModal')">
                <i class="fa-solid fa-plus"></i> Create First Team
            </button>
        </div>
    </div>
<?php else: ?>
    <!-- Teams Grid -->
    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 20px;" class="fade-in">
        <?php foreach ($teams as $team): ?>
            <?php 
            $members = getTeamMembers((int)$team['id']); 
            ?>
            <div class="card" style="display: flex; flex-direction: column; justify-content: space-between; border: 1px solid var(--color-border); border-radius: var(--radius-lg); background: var(--color-bg-card); transition: all var(--transition-fast);">
                <div>
                    <!-- Team Header -->
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 10px; margin-bottom: 12px; border-bottom: 1px solid var(--color-border); padding-bottom: 12px;">
                        <div>
                            <h3 style="margin: 0 0 4px 0; font-size: 16px; font-weight: 700; color: var(--color-text-white); display: flex; align-items: center; gap: 8px;">
                                <i class="fa-solid fa-users" style="color: var(--color-primary); font-size: 14px;"></i>
                                <?php echo e($team['name']); ?>
                            </h3>
                            <span class="badge badge-primary" style="font-size: 11px;">
                                <i class="fa-solid fa-user-group" style="margin-right: 4px;"></i> <?php echo count($members); ?> Member(s)
                            </span>
                        </div>
                        <div style="display: flex; gap: 4px;">
                            <a href="?action=edit&id=<?php echo $team['id']; ?>" class="btn btn-ghost btn-sm" title="Edit Team">
                                <i class="fa-solid fa-pen-to-square"></i>
                            </a>
                            <a href="?delete=<?php echo $team['id']; ?>" class="btn btn-ghost btn-sm text-danger" onclick="return confirm('Are you sure you want to delete this team? Members will not be deleted.')" title="Delete Team">
                                <i class="fa-solid fa-trash-can"></i>
                            </a>
                        </div>
                    </div>

                    <!-- Description -->
                    <?php if (!empty($team['description'])): ?>
                        <p style="font-size: 12.5px; color: var(--color-text-secondary); line-height: 1.4; margin-bottom: 14px;">
                            <?php echo e($team['description']); ?>
                        </p>
                    <?php endif; ?>

                    <!-- Team Leader -->
                    <div style="background: var(--color-bg-secondary); border-radius: var(--radius-md); padding: 10px 12px; margin-bottom: 14px; display: flex; align-items: center; gap: 10px;">
                        <div class="table-user-avatar" style="width: 32px; height: 32px; font-size: 11px;">
                            <?php echo e(getInitials($team['leader_name'])); ?>
                        </div>
                        <div style="flex: 1; min-width: 0;">
                            <div style="font-size: 10.5px; text-transform: uppercase; letter-spacing: 0.5px; color: var(--color-text-muted); font-weight: 700;">Team Leader</div>
                            <div style="font-size: 13px; font-weight: 600; color: var(--color-text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                <?php echo e($team['leader_name']); ?>
                            </div>
                        </div>
                        <span class="badge badge-purple" style="font-size: 10px;"><?php echo ucfirst(e($team['leader_role'])); ?></span>
                    </div>

                    <!-- Team Members Roster -->
                    <div style="margin-bottom: 14px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                            <div style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--color-text-muted); letter-spacing: 0.5px;">
                                Members (<?php echo count($members); ?>)
                            </div>
                            <span style="font-size: 10.5px; color: var(--color-text-muted); font-style: italic;">
                                Click member to assign individual task
                            </span>
                        </div>
                        <div style="display: flex; flex-wrap: wrap; gap: 6px; max-height: 120px; overflow-y: auto; padding-right: 4px;">
                            <?php foreach ($members as $m): ?>
                                <a href="<?php echo BASE_URL; ?>/founder/tasks.php?assign_to=<?php echo $m['id']; ?>" 
                                   class="badge badge-secondary" 
                                   title="Click to assign individual task to <?php echo e($m['name']); ?>"
                                   style="font-size: 11.5px; padding: 4px 8px; display: inline-flex; align-items: center; gap: 6px; background: var(--color-bg-secondary); text-decoration: none; cursor: pointer; transition: all 0.15s; border: 1px solid transparent;"
                                   onmouseover="this.style.borderColor='var(--color-primary)'; this.style.color='var(--color-primary)';"
                                   onmouseout="this.style.borderColor='transparent'; this.style.color='';">
                                    <span style="width: 6px; height: 6px; border-radius: 50%; background: <?php echo $m['role_in_team'] === 'leader' ? '#a855f7' : 'var(--color-primary)'; ?>;"></span>
                                    <span><?php echo e($m['name']); ?></span>
                                    <?php if (!empty($m['designation'])): ?>
                                        <small style="opacity: 0.7; font-size: 10px;">(<?php echo e($m['designation']); ?>)</small>
                                    <?php endif; ?>
                                    <i class="fa-solid fa-plus" style="font-size: 9px; opacity: 0.6; margin-left: 2px;"></i>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Footer Actions -->
                <div style="border-top: 1px solid var(--color-border); padding-top: 12px; margin-top: 8px; display: flex; justify-content: space-between; align-items: center; gap: 8px; flex-wrap: wrap;">
                    <span style="font-size: 12px; color: var(--color-text-muted);">
                        <i class="fa-solid fa-list-check" style="margin-right: 4px;"></i> <?php echo (int)$team['task_count']; ?> Task(s)
                    </span>
                    <div style="display: flex; gap: 6px;">
                        <a href="<?php echo BASE_URL; ?>/founder/tasks.php?create_for_team=team_<?php echo $team['id']; ?>" class="btn btn-primary btn-sm" style="font-size: 12px; padding: 6px 12px;" title="Assign task to all team members at once">
                            <i class="fa-solid fa-people-group"></i> Assign Entire Team
                        </a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- Create Team Modal -->
<div class="modal-overlay" id="createTeamModal">
    <div class="modal" style="max-width: 540px;">
        <div class="modal-header">
            <h3 class="modal-title"><i class="fa-solid fa-people-group" style="color: var(--color-primary);"></i> Create New Team</h3>
            <button type="button" class="modal-close" onclick="closeModal('createTeamModal')">×</button>
        </div>
        <form method="POST" action="">
            <?php echo csrfField(); ?>
            <input type="hidden" name="form_action" value="create_team">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Team Name *</label>
                    <input type="text" name="name" class="form-input" placeholder="e.g. Frontend Squad, Marketing Team, Sales Tigers" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Description / Purpose</label>
                    <textarea name="description" class="form-textarea" rows="2" placeholder="Brief details about what this team is responsible for..."></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label">Team Leader / Manager *</label>
                    <select name="leader_id" class="form-select" required>
                        <option value="">Select Team Leader</option>
                        <?php foreach ($managersAndLeaders as $ldr): ?>
                            <option value="<?php echo $ldr['id']; ?>"><?php echo e($ldr['name']); ?> (<?php echo ucfirst(e($ldr['role'])); ?><?php echo $ldr['designation'] ? ' — ' . e($ldr['designation']) : ''; ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Select Team Members</label>
                    <div style="border: 1px solid var(--color-border); border-radius: var(--radius-md); max-height: 180px; overflow-y: auto; padding: 10px; background: var(--color-bg-secondary);">
                        <?php foreach ($allStaff as $st): ?>
                            <label style="display: flex; align-items: center; gap: 8px; padding: 6px 4px; cursor: pointer; border-bottom: 1px solid rgba(255,255,255,0.03); font-size: 13px;">
                                <input type="checkbox" name="members[]" value="<?php echo $st['id']; ?>">
                                <span><strong><?php echo e($st['name']); ?></strong> <small style="color: var(--color-text-muted);">(<?php echo ucfirst(e($st['role'])); ?><?php echo $st['designation'] ? ' · ' . e($st['designation']) : ''; ?>)</small></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('createTeamModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Create Team</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Team Modal -->
<?php if ($editTeam): ?>
<div class="modal-overlay active" id="editTeamModal">
    <div class="modal" style="max-width: 540px;">
        <div class="modal-header">
            <h3 class="modal-title"><i class="fa-solid fa-pen-to-square" style="color: var(--color-primary);"></i> Edit Team: <?php echo e($editTeam['name']); ?></h3>
            <a href="<?php echo BASE_URL; ?>/founder/teams.php" class="modal-close">×</a>
        </div>
        <form method="POST" action="">
            <?php echo csrfField(); ?>
            <input type="hidden" name="form_action" value="edit_team">
            <input type="hidden" name="team_id" value="<?php echo $editTeam['id']; ?>">
            
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Team Name *</label>
                    <input type="text" name="name" class="form-input" value="<?php echo e($editTeam['name']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Description / Purpose</label>
                    <textarea name="description" class="form-textarea" rows="2"><?php echo e($editTeam['description']); ?></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label">Team Leader / Manager *</label>
                    <select name="leader_id" class="form-select" required>
                        <?php foreach ($managersAndLeaders as $ldr): ?>
                            <option value="<?php echo $ldr['id']; ?>" <?php echo $editTeam['leader_id'] == $ldr['id'] ? 'selected' : ''; ?>>
                                <?php echo e($ldr['name']); ?> (<?php echo ucfirst(e($ldr['role'])); ?><?php echo $ldr['designation'] ? ' — ' . e($ldr['designation']) : ''; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Select Team Members</label>
                    <div style="border: 1px solid var(--color-border); border-radius: var(--radius-md); max-height: 180px; overflow-y: auto; padding: 10px; background: var(--color-bg-secondary);">
                        <?php foreach ($allStaff as $st): ?>
                            <?php $isSelected = in_array($st['id'], $editTeamMembers) || ($st['id'] == $editTeam['leader_id']); ?>
                            <label style="display: flex; align-items: center; gap: 8px; padding: 6px 4px; cursor: pointer; border-bottom: 1px solid rgba(255,255,255,0.03); font-size: 13px;">
                                <input type="checkbox" name="members[]" value="<?php echo $st['id']; ?>" <?php echo $isSelected ? 'checked' : ''; ?>>
                                <span><strong><?php echo e($st['name']); ?></strong> <small style="color: var(--color-text-muted);">(<?php echo ucfirst(e($st['role'])); ?><?php echo $st['designation'] ? ' · ' . e($st['designation']) : ''; ?>)</small></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <a href="<?php echo BASE_URL; ?>/founder/teams.php" class="btn btn-outline">Cancel</a>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Save Changes</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
