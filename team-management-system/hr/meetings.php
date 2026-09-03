<?php
/**
 * HR — Meetings Management (Today's Meetings, Create Meeting & Join Google Meet)
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/middleware.php';

requireRole([ROLE_HR]);

$db = getDB();
$hrId = getUserId();
$today = today();
$tab = get('tab', 'today'); // 'today', 'create', 'all'
$formErrors = [];

// Handle Create Meeting Form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('form_action') === 'create_meeting') {
    requireCsrf();
    
    $title = post('title');
    $description = post('description');
    $meetingDate = post('meeting_date');
    $startTime = post('start_time');
    $endTime = post('end_time');
    $participants = $_POST['participants'] ?? [];
    $meetingType = count($participants) === 1 ? 'individual' : 'team';
    $meetLink = trim(post('meet_link', ''));
    if (!empty($meetLink) && !preg_match('#^https?://#i', $meetLink)) {
        $meetLink = 'https://' . $meetLink;
    }
    $agenda = post('agenda', '');
    
    if (empty($title)) $formErrors[] = 'Meeting title is required.';
    if (empty($meetingDate)) $formErrors[] = 'Meeting date is required.';
    if (empty($meetLink)) {
        $formErrors[] = 'Google Meet link is required.';
    } elseif (!filter_var($meetLink, FILTER_VALIDATE_URL)) {
        $formErrors[] = 'Please enter a valid Google Meet link URL.';
    }
    if (empty($startTime)) $formErrors[] = 'Start time is required.';
    if (empty($endTime)) $formErrors[] = 'End time is required.';
    
    if (empty($formErrors)) {
        $stmt = $db->prepare("
            INSERT INTO meetings (title, description, meeting_date, start_time, end_time, host_id, meeting_type, meet_link, agenda, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'scheduled')
        ");
        $stmt->execute([$title, $description ?: null, $meetingDate, $startTime, $endTime, $hrId, $meetingType, $meetLink ?: null, $agenda ?: null]);
        $meetingId = $db->lastInsertId();
        
        // Add participants & send in-app notifications
        if (!empty($participants)) {
            $partStmt = $db->prepare("INSERT INTO meeting_participants (meeting_id, user_id) VALUES (?, ?)");
            foreach ($participants as $pId) {
                $pIdInt = (int)$pId;
                $partStmt->execute([$meetingId, $pIdInt]);
                
                // Send notification
                createNotification(
                    $pIdInt, 
                    'Scheduled Meeting: ' . $title, 
                    'You are invited to a meeting on ' . formatDate($meetingDate) . ' at ' . formatTime($startTime) . '. Click to join Google Meet.', 
                    $meetLink ?: BASE_URL . '/employee/meetings.php', 
                    'info'
                );
            }
        }
        
        setFlash('success', 'Meeting scheduled successfully with Google Meet integration!');
        header('Location: ' . BASE_URL . '/hr/meetings.php?tab=today');
        exit;
    }
}

// Today's Meetings
$stmt = $db->prepare("
    SELECT m.*, u.name as host_name
    FROM meetings m
    JOIN users u ON m.host_id = u.id
    WHERE m.meeting_date = ?
    ORDER BY m.start_time ASC
");
$stmt->execute([$today]);
$todayMeetings = $stmt->fetchAll();

// All Meetings
$stmt = $db->query("
    SELECT m.*, u.name as host_name
    FROM meetings m
    JOIN users u ON m.host_id = u.id
    ORDER BY m.meeting_date DESC, m.start_time ASC
    LIMIT 50
");
$allMeetings = $stmt->fetchAll();

// All active users for participant selection
$allUsers = $db->query("SELECT id, name, role, designation FROM users WHERE status = 'active' ORDER BY name ASC")->fetchAll();

$pageTitle = 'HR — Meetings';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Meetings & Google Meet Schedule</h1>
        <p class="page-subtitle">Organize, schedule, and join HR & company video meetings</p>
    </div>
    <div>
        <a href="?tab=create" class="btn btn-primary"><i class="fa-solid fa-video"></i> Schedule New Meeting</a>
    </div>
</div>

<!-- Tabs Bar -->
<div class="nav-tabs" style="display: flex; gap: var(--space-2); border-bottom: 1px solid var(--color-border); margin-bottom: var(--space-6);">
    <a href="?tab=today" class="tab-item <?php echo $tab === 'today' ? 'active' : ''; ?>" style="padding: var(--space-3) var(--space-4); text-decoration: none; font-weight: 500; border-bottom: 2px solid <?php echo $tab === 'today' ? 'var(--color-primary)' : 'transparent'; ?>; color: <?php echo $tab === 'today' ? 'var(--color-primary)' : 'var(--color-text-secondary)'; ?>;">
        <i class="fa-solid fa-calendar-days"></i> Today's Meetings (<?php echo count($todayMeetings); ?>)
    </a>
    <a href="?tab=all" class="tab-item <?php echo $tab === 'all' ? 'active' : ''; ?>" style="padding: var(--space-3) var(--space-4); text-decoration: none; font-weight: 500; border-bottom: 2px solid <?php echo $tab === 'all' ? 'var(--color-primary)' : 'transparent'; ?>; color: <?php echo $tab === 'all' ? 'var(--color-primary)' : 'var(--color-text-secondary)'; ?>;">
        <i class="fa-solid fa-chart-pie"></i> All Scheduled Meetings
    </a>
    <a href="?tab=create" class="tab-item <?php echo $tab === 'create' ? 'active' : ''; ?>" style="padding: var(--space-3) var(--space-4); text-decoration: none; font-weight: 500; border-bottom: 2px solid <?php echo $tab === 'create' ? 'var(--color-primary)' : 'transparent'; ?>; color: <?php echo $tab === 'create' ? 'var(--color-primary)' : 'var(--color-text-secondary)'; ?>;">
        <i class="fa-solid fa-video"></i> Create Meeting
    </a>
</div>

<?php if (!empty($formErrors)): ?>
    <div class="alert alert-error">
        <span><?php echo e(implode(' ', $formErrors)); ?></span>
        <button class="alert-close" onclick="this.parentElement.remove()">×</button>
    </div>
<?php endif; ?>

<!-- TAB 1: TODAY'S MEETINGS -->
<?php if ($tab === 'today'): ?>
    <?php if (empty($todayMeetings)): ?>
        <div class="card fade-in">
            <div class="empty-state">
                <div class="empty-state-icon"><i class="fa-solid fa-calendar-xmark"></i></div>
                <div class="empty-state-title">No Meetings Today</div>
                <div class="empty-state-text">There are no meetings scheduled for today (<?php echo formatDate($today); ?>).</div>
                <a href="?tab=create" class="btn btn-primary" style="margin-top: 16px;">Schedule A Meeting</a>
            </div>
        </div>
    <?php else: ?>
        <div class="content-grid fade-in" style="grid-template-columns: repeat(auto-fill, minmax(min(100%, 300px), 1fr));">
            <?php foreach ($todayMeetings as $m): ?>
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fa-solid fa-calendar-days"></i> <?php echo e($m['title']); ?></h3>
                        <span class="badge badge-info"><?php echo ucfirst(e($m['meeting_type'])); ?></span>
                    </div>
                    <p class="text-muted" style="margin-bottom: 12px; font-size: 13px;"><?php echo e($m['description'] ?: 'No description provided.'); ?></p>
                    
                    <div style="background: var(--color-bg-secondary); padding: 12px; border-radius: 8px; margin-bottom: 14px; font-size: var(--text-sm);">
                        <div><i class="fa-solid fa-clock"></i> <strong>Time:</strong> <?php echo formatTime($m['start_time']); ?> - <?php echo formatTime($m['end_time']); ?></div>
                        <div><i class="fa-solid fa-user"></i> <strong>Host:</strong> <?php echo e($m['host_name']); ?></div>
                        <?php if ($m['agenda']): ?>
                            <div style="margin-top: 6px;"><i class="fa-solid fa-pen-to-square"></i> <strong>Agenda:</strong> <?php echo e($m['agenda']); ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($m['meet_link']): ?>
                        <a href="<?php echo e($m['meet_link']); ?>" target="_blank" class="btn btn-primary btn-sm" style="width: 100%; text-align: center; background: #1a73e8; border-color: #1a73e8; display: inline-flex; align-items: center; justify-content: center; gap: 8px;">
                            <i class="fa-solid fa-video"></i> <i class="fa-solid fa-rocket"></i> Join Google Meet
                        </a>
                    <?php else: ?>
                        <button disabled class="btn btn-outline btn-sm" style="width: 100%; opacity: 0.6;">No Meet Link Attached</button>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

<!-- TAB 2: ALL MEETINGS -->
<?php elseif ($tab === 'all'): ?>
    <div class="table-container fade-in">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Date & Time</th>
                    <th>Title</th>
                    <th>Type</th>
                    <th>Host</th>
                    <th>Agenda / Notes</th>
                    <th>Google Meet Link</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($allMeetings as $m): ?>
                    <tr>
                        <td>
                            <strong><?php echo formatDate($m['meeting_date']); ?></strong><br>
                            <small class="text-muted"><?php echo formatTime($m['start_time']); ?> - <?php echo formatTime($m['end_time']); ?></small>
                        </td>
                        <td><strong><?php echo e($m['title']); ?></strong></td>
                        <td><span class="badge badge-secondary"><?php echo ucfirst(e($m['meeting_type'])); ?></span></td>
                        <td><?php echo e($m['host_name']); ?></td>
                        <td><?php echo e($m['agenda'] ?: '—'); ?></td>
                        <td>
                            <?php if ($m['meet_link']): ?>
                                <a href="<?php echo e($m['meet_link']); ?>" target="_blank" class="btn btn-sm btn-outline" style="color: #1a73e8; border-color: #1a73e8; display: inline-flex; align-items: center; gap: 6px;">
                                    <i class="fa-solid fa-video"></i> Join Meet
                                </a>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge badge-success"><?php echo ucfirst(e($m['status'])); ?></span></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

<!-- TAB 3: CREATE MEETING -->
<?php elseif ($tab === 'create'): ?>
    <div class="card fade-in" style="max-width: 680px; margin: 0 auto;">
        <div class="card-header">
            <h3 class="card-title"><i class="fa-solid fa-video"></i> Schedule New Meeting & Add Google Meet</h3>
            <p class="card-subtitle">Fill in details and attach a Google Meet video call URL for participants</p>
        </div>
        <form method="POST" action="">
            <?php echo csrfField(); ?>
            <input type="hidden" name="form_action" value="create_meeting">
            
            <div class="form-group">
                <label class="form-label">Meeting Title *</label>
                <input type="text" name="title" class="form-input" required placeholder="e.g. HR Weekly Sync / Performance Review">
            </div>

            <div class="form-group">
                <label class="form-label">Meeting Date *</label>
                <input type="date" name="meeting_date" class="form-input" required value="<?php echo today(); ?>">
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Start Time *</label>
                    <input type="time" name="start_time" class="form-input" required value="10:00">
                </div>
                <div class="form-group">
                    <label class="form-label">End Time *</label>
                    <input type="time" name="end_time" class="form-input" required value="11:00">
                </div>
            </div>

            <!-- GOOGLE MEET LINK INPUT BOX -->
            <div class="form-group" style="background: rgba(26, 115, 232, 0.08); border: 1px solid rgba(26, 115, 232, 0.25); padding: 16px; border-radius: var(--radius-md);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                    <label class="form-label" style="margin-bottom: 0; font-weight: 600; color: #1a73e8; display: flex; align-items: center; gap: 6px;">
                        <i class="fa-solid fa-video"></i> Google Meet Video Call Link *
                    </label>
                    <a href="https://meet.google.com/new" target="_blank" class="btn btn-ghost btn-sm" style="font-size: 11px; padding: 4px 10px; border: 1px solid #1a73e8; color: #1a73e8; text-decoration: none; display: inline-flex; align-items: center; gap: 4px;" title="Create new instant Google Meet link in a new tab">
                        <i class="fa-solid fa-plus"></i> Generate Google Meet Link ↗
                    </a>
                </div>
                <div style="position: relative;">
                    <input type="url" name="meet_link" id="meet_link_input" class="form-input" placeholder="https://meet.google.com/abc-defg-hij" style="padding-left: 36px; border-color: #1a73e8;" required>
                    <span style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #1a73e8; font-size: 14px;"><i class="fa-solid fa-video"></i></span>
                </div>
                <small class="text-muted" style="margin-top: 6px; display: block; font-size: 12px;">Paste your Google Meet link above. Participants will receive a direct <strong>"<i class="fa-solid fa-rocket"></i> Join Google Meet"</strong> button.</small>
            </div>

            <div class="form-group">
                <label class="form-label">Agenda / Outline</label>
                <textarea name="agenda" class="form-textarea" placeholder="Outline agenda items..."></textarea>
            </div>

            <div class="form-group">
                <label class="form-label">Select Participants</label>
                <div style="max-height: 160px; overflow-y: auto; background: var(--color-bg-secondary); padding: 12px; border-radius: 8px; border: 1px solid var(--color-border);">
                    <?php foreach ($allUsers as $u): ?>
                        <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 6px; cursor: pointer;">
                            <input type="checkbox" name="participants[]" value="<?php echo $u['id']; ?>">
                            <span><strong><?php echo e($u['name']); ?></strong> (<?php echo ucfirst(e($u['role'])); ?> — <?php echo e($u['designation'] ?: 'N/A'); ?>)</span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="form-actions" style="margin-top: 24px;">
                <button type="submit" class="btn btn-primary btn-lg" style="width: 100%; background: #1a73e8; border-color: #1a73e8;">
                    <i class="fa-solid fa-video"></i> Schedule Meeting with Google Meet
                </button>
            </div>
        </form>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
