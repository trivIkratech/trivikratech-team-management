<?php
/**
 * Manager — Meetings Management
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/middleware.php';

requireRole([ROLE_MANAGER]);

$db = getDB();
$userId = getUserId();
$formErrors = [];

// Handle Meeting Creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('form_action') === 'create_meeting') {
    requireCsrf();
    
    $title = post('title');
    $description = post('description');
    $meetingDate = post('meeting_date');
    $startTime = post('start_time');
    $endTime = post('end_time');
    $meetingType = post('meeting_type', 'team');
    $meetLink = post('meet_link');
    $agenda = post('agenda');
    $participants = isset($_POST['participants']) && is_array($_POST['participants']) ? $_POST['participants'] : [];
    
    // Validate inputs
    if (empty($title)) $formErrors[] = 'Meeting title is required.';
    if (empty($meetingDate)) $formErrors[] = 'Meeting date is required.';
    if (empty($startTime)) $formErrors[] = 'Start time is required.';
    if (empty($endTime)) $formErrors[] = 'End time is required.';
    if (strtotime($endTime) <= strtotime($startTime)) $formErrors[] = 'End time must be after start time.';
    if (!empty($meetLink) && !filter_var($meetLink, FILTER_VALIDATE_URL)) $formErrors[] = 'Please enter a valid Google Meet URL.';
    if ($meetingType !== 'self' && empty($participants)) $formErrors[] = 'Please select at least one participant.';
    
    if (empty($formErrors)) {
        try {
            $db->beginTransaction();
            
            // Insert meeting
            $stmt = $db->prepare("
                INSERT INTO meetings (title, description, meeting_date, start_time, end_time, host_id, meeting_type, meet_link, agenda) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$title, $description ?: null, $meetingDate, $startTime, $endTime, $userId, $meetingType, $meetLink ?: null, $agenda ?: null]);
            $meetingId = $db->lastInsertId();
            
            // Add participants
            if ($meetingType === 'self') {
                $stmt = $db->prepare("INSERT INTO meeting_participants (meeting_id, user_id) VALUES (?, ?)");
                $stmt->execute([$meetingId, $userId]);
            } else {
                $stmt = $db->prepare("INSERT INTO meeting_participants (meeting_id, user_id) VALUES (?, ?)");
                foreach ($participants as $partId) {
                    $stmt->execute([$meetingId, (int)$partId]);
                }
                if (!in_array($userId, $participants)) {
                    $stmt->execute([$meetingId, $userId]);
                }
            }
            
            $db->commit();
            setFlash('success', 'Meeting scheduled successfully!');
            header('Location: ' . BASE_URL . '/manager/meetings.php');
            exit;
        } catch (PDOException $e) {
            $db->rollBack();
            error_log("Error creating meeting: " . $e->getMessage());
            $formErrors[] = 'A database error occurred. Please try again.';
        }
    }
}

// Handle Meeting Status Update (Done or Cancelled)
if (isset($_GET['action']) && isset($_GET['id']) && is_numeric($_GET['id'])) {
    $action = $_GET['action'];
    $meetingId = (int)$_GET['id'];
    try {
        $stmt = $db->prepare("SELECT host_id FROM meetings WHERE id = ?");
        $stmt->execute([$meetingId]);
        $hostId = $stmt->fetchColumn();
        
        if ($hostId === $userId) {
            if ($action === 'complete') {
                $currTime = currentTime();
                $stmt = $db->prepare("UPDATE meetings SET status = 'completed', end_time = ? WHERE id = ?");
                $stmt->execute([$currTime, $meetingId]);
                setFlash('success', 'Meeting marked as completed successfully.');
            } elseif ($action === 'cancel') {
                $stmt = $db->prepare("UPDATE meetings SET status = 'cancelled' WHERE id = ?");
                $stmt->execute([$meetingId]);
                setFlash('success', 'Meeting cancelled successfully.');
            }
        } else {
            setFlash('error', 'You are not authorized to update this meeting.');
        }
    } catch (PDOException $e) {
        error_log("Error updating meeting status: " . $e->getMessage());
        setFlash('error', 'Could not update meeting status.');
    }
    header('Location: ' . BASE_URL . '/manager/meetings.php');
    exit;
}

// Fetch active employees reporting to this manager
$stmt = $db->prepare("
    SELECT id, name, role, email 
    FROM users 
    WHERE manager_id = ? AND status = 'active' AND role = 'employee'
    ORDER BY name ASC
");
$stmt->execute([$userId]);
$myEmployees = $stmt->fetchAll();

// Fetch meetings hosted by the manager OR where they are a participant
$stmt = $db->prepare("
    SELECT DISTINCT m.*, u.name AS host_name, u.role AS host_role
    FROM meetings m
    JOIN users u ON m.host_id = u.id
    LEFT JOIN meeting_participants mp ON m.id = mp.meeting_id
    WHERE m.host_id = ? OR mp.user_id = ?
    ORDER BY m.meeting_date DESC, m.start_time DESC
");
$stmt->execute([$userId, $userId]);
$meetings = $stmt->fetchAll();

// For each meeting, fetch participants
$allMeetingsData = [];
foreach ($meetings as $meeting) {
    $stmt = $db->prepare("
        SELECT u.name, u.role, u.id
        FROM meeting_participants mp
        JOIN users u ON mp.user_id = u.id
        WHERE mp.meeting_id = ?
    ");
    $stmt->execute([$meeting['id']]);
    $participants = $stmt->fetchAll();
    
    $meeting['participants'] = $participants;
    $allMeetingsData[] = $meeting;
}

// Categorize into Upcoming and Past
$upcomingMeetings = [];
$pastMeetings = [];
$currTime = currentTime();

foreach ($allMeetingsData as $m) {
    $isToday = ($m['meeting_date'] === today());
    $isPastDate = (strtotime($m['meeting_date']) < strtotime(today()));
    $isEnded = $m['status'] !== 'scheduled' || $isPastDate || ($isToday && $currTime > $m['end_time']);
    
    if ($isEnded) {
        $pastMeetings[] = $m;
    } else {
        $upcomingMeetings[] = $m;
    }
}

// Reusable render function for meetings
function renderMeetingCard(array $m, int $userId, bool $isHistory = false): void {
    $isToday = ($m['meeting_date'] === today());
    $currTime = currentTime();
    $isActive = $isToday && ($currTime >= $m['start_time'] && $currTime <= $m['end_time']);
    ?>
    <div style="border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: var(--space-4); background-color: var(--color-bg-secondary); position: relative; transition: border-color var(--transition-base);" onmouseover="this.style.borderColor='var(--color-primary)'" onmouseout="this.style.borderColor='var(--color-border)'">
        
        <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: var(--space-2);">
            <div>
                <h4 style="color: var(--color-text-white); font-size: var(--text-lg); font-weight: 600; display: inline-flex; align-items: center; gap: var(--space-2);">
                    <?php echo e($m['title']); ?>
                </h4>
                <div style="margin-top: var(--space-1);">
                    <span class="badge badge-secondary" style="font-size: var(--text-xs);">Host: <?php echo $m['host_id'] === $userId ? 'You' : e($m['host_name']); ?> (<?php echo ucfirst($m['host_role']); ?>)</span>
                    <span class="badge <?php echo ($m['meeting_type'] === 'self') ? 'badge-info' : (($m['meeting_type'] === 'individual') ? 'badge-purple' : 'badge-primary'); ?>" style="font-size: var(--text-xs); margin-left: var(--space-1);">
                        <?php echo ($m['meeting_type'] === 'self') ? 'Self Session' : (($m['meeting_type'] === 'individual') ? '1-on-1' : 'Team Sync'); ?>
                    </span>
                    <?php if ($m['status'] === 'completed'): ?>
                        <span class="badge" style="font-size: var(--text-xs); margin-left: var(--space-1); background-color: var(--color-success); color: white;">✓ Done</span>
                    <?php elseif ($m['status'] === 'cancelled'): ?>
                        <span class="badge" style="font-size: var(--text-xs); margin-left: var(--space-1); background-color: var(--color-danger); color: white;">✗ Cancelled</span>
                    <?php elseif ($isHistory): ?>
                        <span class="badge badge-secondary" style="font-size: var(--text-xs); margin-left: var(--space-1); background-color: var(--color-border); color: var(--color-text-secondary);">✓ Ended</span>
                    <?php elseif ($isActive): ?>
                        <span class="badge badge-success" style="font-size: var(--text-xs); margin-left: var(--space-1); background-color: var(--color-success); color: white;">● Live Now</span>
                    <?php endif; ?>
                </div>
            </div>
            
            <div style="display: flex; gap: var(--space-2); align-items: center;">
                <?php if (!$isHistory && $m['host_id'] === $userId): ?>
                    <a href="?action=complete&id=<?php echo $m['id']; ?>" class="btn btn-sm" onclick="return confirm('Mark this meeting as completed?')" style="background-color: var(--color-success); color: white; font-weight: 600; padding: var(--space-1) var(--space-2); border-radius: var(--radius-sm); font-size: var(--text-xs); border: none; text-decoration: none;">
                        ✓ Meeting Done
                    </a>
                    <a href="?action=cancel&id=<?php echo $m['id']; ?>" class="btn btn-sm" onclick="return confirm('Are you sure you want to cancel this meeting?')" style="background-color: var(--color-danger); color: white; font-weight: 600; padding: var(--space-1) var(--space-2); border-radius: var(--radius-sm); font-size: var(--text-xs); border: none; text-decoration: none;">
                        ✗ Cancel Meeting
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($m['description']): ?>
            <p class="text-muted" style="font-size: var(--text-sm); margin-bottom: var(--space-3); line-height: 1.4;">
                <?php echo e($m['description']); ?>
            </p>
        <?php endif; ?>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-2); font-size: var(--text-sm); padding-top: var(--space-2); border-top: 1px dashed var(--color-border); margin-bottom: var(--space-3);">
            <div>📅 <strong>Date:</strong> <?php echo formatDate($m['meeting_date']); ?></div>
            <div>⏰ <strong>Time:</strong> <?php echo formatTime($m['start_time']); ?> - <?php echo formatTime($m['end_time']); ?></div>
        </div>

        <?php if ($m['meet_link'] && !$isHistory && $m['status'] === 'scheduled'): ?>
            <div style="margin-bottom: var(--space-3);">
                <a href="<?php echo e($m['meet_link']); ?>" target="_blank" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: var(--space-2); background-color: #0f9d58; border-color: #0f9d58; font-size: var(--text-sm); font-weight: 600; padding: var(--space-2) var(--space-4);">
                    📹 Join Google Meet
                </a>
            </div>
        <?php endif; ?>

        <?php if ($m['agenda']): ?>
            <div style="font-size: var(--text-sm); background-color: var(--color-bg-tertiary); padding: var(--space-3); border-radius: var(--radius-sm); border-left: 3px solid var(--color-primary); margin-bottom: var(--space-3);">
                <strong>Agenda:</strong><br>
                <span class="text-muted"><?php echo nl2br(e($m['agenda'])); ?></span>
            </div>
        <?php endif; ?>

        <?php if ($m['meeting_type'] !== 'self'): ?>
            <div style="font-size: var(--text-sm);">
                <strong>Participants:</strong>
                <div style="display: flex; flex-wrap: wrap; gap: var(--space-2); margin-top: var(--space-1);">
                    <?php foreach ($m['participants'] as $p): ?>
                        <span class="badge badge-secondary" style="background-color: var(--color-bg-tertiary); color: var(--color-text); font-size: var(--text-xs); border: 1px solid var(--color-border);">
                            👤 <?php echo $p['id'] === $userId ? 'You' : e($p['name']); ?> <span class="text-muted" style="font-size: 10px;">(<?php echo ucfirst($p['role']); ?>)</span>
                        </span>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <?php
}

$pageTitle = 'Meetings';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Team Meetings</h1>
        <p class="page-subtitle">Coordinate syncs with your team members and view founder-scheduled meetings</p>
    </div>
</div>

<?php if (!empty($formErrors)): ?>
    <div class="alert alert-error">
        <span><?php echo e(implode(' ', $formErrors)); ?></span>
        <button class="alert-close" onclick="this.parentElement.remove()">×</button>
    </div>
<?php endif; ?>

<div class="content-grid" style="display: grid; grid-template-columns: 1fr 2fr; gap: var(--space-6); align-items: start;">
    
    <!-- Schedule a Meeting Form -->
    <div class="card fade-in">
        <div class="card-header">
            <h3 class="card-title">Schedule Team Meeting</h3>
        </div>
        
        <?php if (empty($myEmployees)): ?>
            <div class="empty-state" style="padding: var(--space-4) 0;">
                <div class="empty-state-icon">👥</div>
                <div class="empty-state-title" style="font-size: var(--text-base);">No assigned employees</div>
                <div class="empty-state-text" style="font-size: var(--text-xs);">You can only schedule team meetings once employees are assigned to you.</div>
                <div style="margin-top: var(--space-3);">
                    <a href="<?php echo BASE_URL; ?>/manager/meetings.php?self=1" class="btn btn-outline btn-sm" id="btn-self-plan">Plan Self Session Instead</a>
                </div>
            </div>
        <?php endif; ?>

        <form method="POST" action="" id="meeting-form" style="<?php echo empty($myEmployees) ? 'display: none;' : ''; ?>">
            <?php echo csrfField(); ?>
            <input type="hidden" name="form_action" value="create_meeting">
            
            <div class="form-group">
                <label class="form-label">Meeting Title *</label>
                <input type="text" name="title" class="form-input" placeholder="e.g. Daily Standup / Project Review" required value="<?php echo e(post('title')); ?>">
            </div>
            
            <div class="form-group">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-textarea" placeholder="Brief details..." style="height: 80px;"><?php echo e(post('description')); ?></textarea>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Date *</label>
                    <input type="date" name="meeting_date" class="form-input" required min="<?php echo today(); ?>" value="<?php echo e(post('meeting_date', today())); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Type *</label>
                    <select name="meeting_type" id="meeting-type" class="form-select" required>
                        <option value="team" <?php echo post('meeting_type') === 'team' ? 'selected' : ''; ?>>Team Sync</option>
                        <option value="individual" <?php echo post('meeting_type') === 'individual' ? 'selected' : ''; ?>>1-on-1 Session</option>
                        <option value="self" <?php echo post('meeting_type') === 'self' ? 'selected' : ''; ?>>Self/Focus Time</option>
                    </select>
                </div>
            </div>

            <div class="form-group" id="meet-link-container">
                <label class="form-label">Google Meet Link</label>
                <input type="url" name="meet_link" class="form-input" placeholder="e.g. https://meet.google.com/abc-defg-hij" value="<?php echo e(post('meet_link')); ?>">
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Start Time *</label>
                    <input type="time" name="start_time" class="form-input" required value="<?php echo e(post('start_time')); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">End Time *</label>
                    <input type="time" name="end_time" class="form-input" required value="<?php echo e(post('end_time')); ?>">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Agenda</label>
                <textarea name="agenda" class="form-textarea" placeholder="What will be discussed..." style="height: 100px;"><?php echo e(post('agenda')); ?></textarea>
            </div>

            <div class="form-group" id="participants-container">
                <label class="form-label">Select Team Participants *</label>
                <div style="max-height: 200px; overflow-y: auto; border: 1px solid var(--color-border); border-radius: var(--radius-sm); padding: var(--space-3); background-color: var(--color-bg-secondary); display: flex; flex-direction: column; gap: var(--space-2);">
                    <?php foreach ($myEmployees as $e): ?>
                        <label style="display: flex; align-items: center; gap: var(--space-2); cursor: pointer; padding: var(--space-1) 0;">
                            <input type="checkbox" name="participants[]" value="<?php echo $e['id']; ?>" class="participant-checkbox">
                            <span style="font-size: var(--text-sm);">
                                <strong><?php echo e($e['name']); ?></strong> 
                                <span class="text-muted" style="font-size: var(--text-xs);"> (<?php echo e($e['email']); ?>)</span>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="form-actions" style="margin-top: var(--space-4);">
                <button type="submit" class="btn btn-primary" style="width: 100%;">Schedule Meeting</button>
            </div>
        </form>
        
        <!-- Fallback Self form when no employees assigned -->
        <form method="POST" action="" id="self-meeting-form" style="<?php echo !empty($myEmployees) ? 'display: none;' : ''; ?>">
            <?php echo csrfField(); ?>
            <input type="hidden" name="form_action" value="create_meeting">
            <input type="hidden" name="meeting_type" value="self">
            
            <div class="form-group">
                <label class="form-label">Self Session Title *</label>
                <input type="text" name="title" class="form-input" placeholder="e.g. Focus Block / Admin Work" required value="<?php echo e(post('title')); ?>">
            </div>
            
            <div class="form-group">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-textarea" placeholder="Notes for yourself..." style="height: 80px;"><?php echo e(post('description')); ?></textarea>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Date *</label>
                    <input type="date" name="meeting_date" class="form-input" required min="<?php echo today(); ?>" value="<?php echo e(post('meeting_date', today())); ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Start Time *</label>
                    <input type="time" name="start_time" class="form-input" required value="<?php echo e(post('start_time')); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">End Time *</label>
                    <input type="time" name="end_time" class="form-input" required value="<?php echo e(post('end_time')); ?>">
                </div>
            </div>

            <div class="form-actions" style="margin-top: var(--space-4);">
                <button type="submit" class="btn btn-primary" style="width: 100%;">Schedule Self Session</button>
            </div>
        </form>
    </div>

    <!-- Meetings Listing Column -->
    <div style="display: flex; flex-direction: column; gap: var(--space-6);">
        
        <!-- Active & Upcoming Meetings -->
        <div class="card fade-in stagger-1">
            <div class="card-header" style="border-bottom: 1px solid var(--color-border); padding-bottom: var(--space-3); margin-bottom: var(--space-4);">
                <h3 class="card-title">Active & Upcoming Meetings</h3>
            </div>
            
            <?php if (empty($upcomingMeetings)): ?>
                <div class="empty-state" style="padding: var(--space-6) 0;">
                    <div class="empty-state-icon">📅</div>
                    <div class="empty-state-title">No upcoming meetings scheduled</div>
                    <div class="empty-state-text">Scheduled syncs and calls will appear here.</div>
                </div>
            <?php else: ?>
                <div style="display: flex; flex-direction: column; gap: var(--space-4);">
                    <?php foreach ($upcomingMeetings as $m): ?>
                        <?php renderMeetingCard($m, $userId, false); ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Past Meeting Records (History Log) -->
        <div class="card fade-in stagger-2">
            <div class="card-header" style="border-bottom: 1px solid var(--color-border); padding-bottom: var(--space-3); margin-bottom: var(--space-4);">
                <h3 class="card-title">Past Meeting Records (History)</h3>
            </div>
            
            <?php if (empty($pastMeetings)): ?>
                <div class="empty-state" style="padding: var(--space-6) 0;">
                    <div class="empty-state-icon">📜</div>
                    <div class="empty-state-title">No past meeting records</div>
                    <div class="empty-state-text">History logs of finished calls will appear here.</div>
                </div>
            <?php else: ?>
                <div style="display: flex; flex-direction: column; gap: var(--space-4);">
                    <?php foreach ($pastMeetings as $m): ?>
                        <?php renderMeetingCard($m, $userId, true); ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

    </div>

</div>

<script>
const selectType = document.getElementById('meeting-type');
if (selectType) {
    selectType.addEventListener('change', function() {
        const container = document.getElementById('participants-container');
        const checkboxes = document.querySelectorAll('.participant-checkbox');
        const meetLinkContainer = document.getElementById('meet-link-container');
        if (this.value === 'self') {
            container.style.display = 'none';
            meetLinkContainer.style.display = 'none';
            checkboxes.forEach(c => c.removeAttribute('required'));
        } else {
            container.style.display = '';
            meetLinkContainer.style.display = '';
        }
    });
}

const btnSelfPlan = document.getElementById('btn-self-plan');
if (btnSelfPlan) {
    btnSelfPlan.addEventListener('click', function(e) {
        e.preventDefault();
        document.getElementById('meeting-form').style.display = 'none';
        document.getElementById('self-meeting-form').style.display = 'block';
    });
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
