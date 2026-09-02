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
    $description = post('description', '');
    $meetingDate = post('meeting_date');
    $startTime = post('start_time');
    $endTime = post('end_time');
    $meetLink = trim(post('meet_link', ''));
    if (!empty($meetLink) && !preg_match('#^https?://#i', $meetLink)) {
        $meetLink = 'https://' . $meetLink;
    }
    $agenda = post('agenda', '');
    $participants = isset($_POST['participants']) && is_array($_POST['participants']) ? $_POST['participants'] : [];
    $meetingType = count($participants) === 1 ? 'individual' : 'team';
    
    // Validate inputs
    if (empty($title)) $formErrors[] = 'Meeting title is required.';
    if (empty($meetingDate)) $formErrors[] = 'Meeting date is required.';
    if (empty($meetLink)) {
        $formErrors[] = 'Google Meet link is required.';
    } elseif (!filter_var($meetLink, FILTER_VALIDATE_URL)) {
        $formErrors[] = 'Please enter a valid Google Meet URL.';
    }
    if (empty($startTime)) $formErrors[] = 'Start time is required.';
    if (empty($endTime)) $formErrors[] = 'End time is required.';
    if (strtotime($endTime) <= strtotime($startTime)) $formErrors[] = 'End time must be after start time.';
    if (empty($participants)) $formErrors[] = 'Please select at least one participant.';
    
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
                    $partIdInt = (int)$partId;
                    $stmt->execute([$meetingId, $partIdInt]);
                    if ($partIdInt !== $userId) {
                        createNotification(
                            $partIdInt,
                            '📅 Meeting Scheduled: ' . $title,
                            'Manager scheduled meeting "' . $title . '" on ' . date('d M Y', strtotime($meetingDate)) . ' at ' . date('h:i A', strtotime($startTime)),
                            $meetLink ?: (BASE_URL . '/employee/meetings.php'),
                            'info'
                        );
                    }
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

// Fetch all active users across the company (excluding self)
$stmt = $db->prepare("
    SELECT id, name, role, email, designation 
    FROM users 
    WHERE status = 'active' AND id != ?
    ORDER BY FIELD(role, 'founder', 'hr', 'manager', 'employee'), name ASC
");
$stmt->execute([$userId]);
$allUsersList = $stmt->fetchAll();

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
                        <span class="badge" style="font-size: var(--text-xs); margin-left: var(--space-1); background-color: var(--color-success); color: white;"><i class="fa-solid fa-check"></i> Done</span>
                    <?php elseif ($m['status'] === 'cancelled'): ?>
                        <span class="badge" style="font-size: var(--text-xs); margin-left: var(--space-1); background-color: var(--color-danger); color: white;"><i class="fa-solid fa-xmark"></i> Cancelled</span>
                    <?php elseif ($isHistory): ?>
                        <span class="badge badge-secondary" style="font-size: var(--text-xs); margin-left: var(--space-1); background-color: var(--color-border); color: var(--color-text-secondary);"><i class="fa-solid fa-check"></i> Ended</span>
                    <?php elseif ($isActive): ?>
                        <span class="badge badge-success" style="font-size: var(--text-xs); margin-left: var(--space-1); background-color: var(--color-success); color: white;">● Live Now</span>
                    <?php endif; ?>
                </div>
            </div>
            
            <div style="display: flex; gap: var(--space-2); align-items: center; flex-wrap: wrap;">
                <?php if (!$isHistory && $m['status'] === 'scheduled'): ?>
                    <a href="<?php echo !empty($m['meet_link']) ? e($m['meet_link']) : 'https://meet.google.com/new'; ?>" target="_blank" class="btn btn-sm" style="background: linear-gradient(135deg, #0f9d58, #0b8043); color: white; font-weight: 600; padding: var(--space-1) var(--space-3); border-radius: var(--radius-sm); font-size: var(--text-xs); border: none; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;">
                        <i class="fa-solid fa-video"></i> Join Meeting
                    </a>
                <?php endif; ?>

                <?php if (!$isHistory && $m['host_id'] === $userId && $m['status'] === 'scheduled'): ?>
                    <a href="?action=complete&id=<?php echo $m['id']; ?>" class="btn btn-sm" onclick="return confirm('Mark this meeting as completed?')" style="background-color: var(--color-success); color: white; font-weight: 600; padding: var(--space-1) var(--space-2); border-radius: var(--radius-sm); font-size: var(--text-xs); border: none; text-decoration: none;">
                        <i class="fa-solid fa-check"></i> Meeting Done
                    </a>
                    <a href="?action=cancel&id=<?php echo $m['id']; ?>" class="btn btn-sm" onclick="return confirm('Are you sure you want to cancel this meeting?')" style="background-color: var(--color-danger); color: white; font-weight: 600; padding: var(--space-1) var(--space-2); border-radius: var(--radius-sm); font-size: var(--text-xs); border: none; text-decoration: none;">
                        <i class="fa-solid fa-xmark"></i> Cancel Meeting
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
            <div><i class="fa-solid fa-calendar-days"></i> <strong>Date:</strong> <?php echo formatDate($m['meeting_date']); ?></div>
            <div><i class="fa-solid fa-clock"></i> <strong>Time:</strong> <?php echo formatTime($m['start_time']); ?> - <?php echo formatTime($m['end_time']); ?></div>
        </div>

        <?php if (!$isHistory && $m['status'] === 'scheduled'): ?>
            <div style="margin-bottom: var(--space-3);">
                <a href="<?php echo !empty($m['meet_link']) ? e($m['meet_link']) : 'https://meet.google.com/new'; ?>" target="_blank" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: var(--space-2); background: linear-gradient(135deg, #1a73e8, #0f9d58); border: none; font-size: var(--text-sm); font-weight: 600; padding: var(--space-2) var(--space-4); border-radius: 6px; color: white; text-decoration: none; box-shadow: 0 2px 6px rgba(15, 157, 88, 0.25);">
                    <i class="fa-solid fa-video"></i> Join Google Meet
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
                            <i class="fa-solid fa-user"></i> <?php echo $p['id'] === $userId ? 'You' : e($p['name']); ?> <span class="text-muted" style="font-size: 10px;">(<?php echo ucfirst($p['role']); ?>)</span>
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
            <h3 class="card-title">Schedule Meeting</h3>
        </div>

        <form method="POST" action="" id="meeting-form">
            <?php echo csrfField(); ?>
            <input type="hidden" name="form_action" value="create_meeting">
            
            <div class="form-group">
                <label class="form-label">Meeting Title *</label>
                <input type="text" name="title" class="form-input" placeholder="e.g. Daily Standup / Project Review" required value="<?php echo e(post('title')); ?>">
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Date *</label>
                    <input type="date" name="meeting_date" class="form-input" required min="<?php echo today(); ?>" value="<?php echo e(post('meeting_date', today())); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Google Meet Link *</label>
                    <input type="url" name="meet_link" class="form-input" placeholder="e.g. https://meet.google.com/abc-defg-hij" required value="<?php echo e(post('meet_link')); ?>">
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

            <div class="form-group">
                <label class="form-label">Agenda</label>
                <textarea name="agenda" class="form-textarea" placeholder="What will be discussed..." style="height: 90px;"><?php echo e(post('agenda')); ?></textarea>
            </div>

            <div class="form-group" id="participants-container">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                    <label class="form-label" style="margin-bottom: 0;">Select Participants *</label>
                    <span style="font-size: 11px;">
                        <a href="javascript:void(0)" onclick="toggleAllParticipants(true)" style="color: var(--color-primary); text-decoration: none; font-weight: 500;">Select All</a> &bull; 
                        <a href="javascript:void(0)" onclick="toggleAllParticipants(false)" style="color: var(--color-text-muted); text-decoration: none;">Clear</a>
                    </span>
                </div>
                
                <input type="text" id="participant-search" class="form-input" placeholder="🔍 Search user by name, role, email..." style="margin-bottom: 8px; font-size: 12px; padding: 6px 10px;" oninput="filterParticipants(this.value)">

                <div id="participants-list-box" style="max-height: 200px; overflow-y: auto; border: 1px solid var(--color-border); border-radius: var(--radius-sm); padding: 8px 10px; background-color: var(--color-bg-secondary); display: flex; flex-direction: column; gap: 4px;">
                    <?php if (empty($allUsersList)): ?>
                        <div style="padding: 10px; text-align: center; color: var(--color-text-muted); font-size: 12px;">No other users available</div>
                    <?php else: ?>
                        <?php foreach ($allUsersList as $u): ?>
                            <label class="participant-item" style="display: flex; align-items: center; gap: 10px; cursor: pointer; padding: 5px 8px; border-radius: 4px; transition: background 0.15s ease;">
                                <input type="checkbox" name="participants[]" value="<?php echo $u['id']; ?>" class="participant-checkbox">
                                <div style="display: flex; align-items: center; justify-content: space-between; width: 100%; font-size: 12px;">
                                    <div>
                                        <strong style="color: var(--color-text-main);"><?php echo e($u['name']); ?></strong> 
                                        <span style="color: var(--color-text-muted); font-size: 11px;">(<?php echo e($u['email']); ?>)</span>
                                    </div>
                                    <div>
                                        <?php if ($u['role'] === 'founder'): ?>
                                            <span class="badge badge-primary" style="font-size: 10px; padding: 2px 6px;"><i class="fa-solid fa-crown" style="font-size: 9px;"></i> Founder</span>
                                        <?php elseif ($u['role'] === 'manager'): ?>
                                            <span class="badge badge-info" style="font-size: 10px; padding: 2px 6px;">Manager</span>
                                        <?php elseif ($u['role'] === 'hr'): ?>
                                            <span class="badge badge-warning" style="font-size: 10px; padding: 2px 6px;">HR</span>
                                        <?php else: ?>
                                            <span class="badge badge-secondary" style="font-size: 10px; padding: 2px 6px;">Employee</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </label>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="form-actions" style="margin-top: var(--space-4);">
                <button type="submit" class="btn btn-primary" style="width: 100%;">Schedule Meeting</button>
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
                    <div class="empty-state-icon"><i class="fa-solid fa-calendar-days"></i></div>
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
                    <div class="empty-state-icon"><i class="fa-solid fa-scroll"></i></div>
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
function toggleAllParticipants(check) {
    document.querySelectorAll('#participants-list-box .participant-item').forEach(item => {
        if (item.style.display !== 'none') {
            const cb = item.querySelector('.participant-checkbox');
            if (cb) cb.checked = check;
        }
    });
}

function filterParticipants(query) {
    const q = query.toLowerCase().trim();
    document.querySelectorAll('#participants-list-box .participant-item').forEach(item => {
        const text = item.innerText.toLowerCase();
        item.style.display = text.includes(q) ? 'flex' : 'none';
    });
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
