<?php
/**
 * Sales Counsellor — Main Dashboard
 * 
 * Overview of sales performance, lead pipeline metrics, today's follow-up queue,
 * quick lead registration, and attendance status.
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/middleware.php';

ensureSalesTablesExist();
requireRole([ROLE_SALES_COUNSELLOR, ROLE_FOUNDER, ROLE_MANAGER, ROLE_HR]);

$currentUser = getCurrentUser();
$userId = (int)$currentUser['id'];
$userRole = $currentUser['role'];
$baseRole = function_exists('getRoleBaseType') ? getRoleBaseType($userRole) : $userRole;

$db = getDB();
$today = today();

// 1. Sales Metrics for this Counsellor
$salesMetrics = getSalesMetrics($userId);

// Monthly Target & Achievement Progress for this Counsellor (Refreshed Monthly)
$selectedMonth = get('month', date('Y-m'));
if (!preg_match('/^\d{4}-\d{2}$/', $selectedMonth)) {
    $selectedMonth = date('Y-m');
}
$monthlyTarget = getMonthlySalesTargetAndProgress($userId, $selectedMonth);
$canManageTargets = in_array($baseRole, [ROLE_FOUNDER, ROLE_MANAGER, ROLE_HR]);
$canViewCommission = in_array($baseRole, [ROLE_FOUNDER, ROLE_SALES_COUNSELLOR]) || in_array($userRole, ['founder', 'sales_counsellor']);

// 2. Today's Due Follow-ups Queue
$todayFollowups = getLeads([
    'assigned_to' => $userId,
    'followup_filter' => 'today'
], 1, 10)['data'];

// 3. Overdue Follow-ups
$overdueFollowups = getLeads([
    'assigned_to' => $userId,
    'followup_filter' => 'overdue'
], 1, 5)['data'];

// 4a. Recent Course Leads
$recentCourseLeads = getLeads([
    'assigned_to' => $userId,
    'offering_type' => 'course'
], 1, 6)['data'];

// 4b. Recent Service Leads
$recentServiceLeads = getLeads([
    'assigned_to' => $userId,
    'offering_type' => 'service',
    'is_manual' => 0
], 1, 6)['data'];

// 4c. Recent Raw / Manual Calling Leads
$recentManualLeads = getLeads([
    'assigned_to' => $userId,
    'is_manual' => 1
], 1, 6)['data'];

// 5. Course/Services catalogue for quick add dropdown
$coursesList = getCourseServicesList('course');
$servicesList = getCourseServicesList('service');
$canAddLeads = in_array($baseRole, [ROLE_FOUNDER, ROLE_MANAGER, ROLE_HR]);

// 6. Today's Attendance Info
$stmt = $db->prepare("SELECT * FROM attendance WHERE user_id = ? AND date = ? LIMIT 1");
$stmt->execute([$userId, $today]);
$todayAttendance = $stmt->fetch();
$checkedIn = $todayAttendance && $todayAttendance['check_in'];
$checkedOut = $todayAttendance && $todayAttendance['check_out'];

$checkInTimestamp = ($checkedIn && !empty($todayAttendance['check_in'])) ? strtotime($today . ' ' . $todayAttendance['check_in']) : 0;
$breakStartTimestamp = (!empty($todayAttendance['break_start'])) ? strtotime($today . ' ' . $todayAttendance['break_start']) : 0;
$breakEndTimestamp = (!empty($todayAttendance['break_end'])) ? strtotime($today . ' ' . $todayAttendance['break_end']) : 0;
$totalBreakSec = 0;
if (!empty($todayAttendance['total_break_time'])) {
    $bParts = explode(':', $todayAttendance['total_break_time']);
    if (count($bParts) >= 2) {
        $totalBreakSec = ((int)$bParts[0] * 3600) + ((int)$bParts[1] * 60) + ((int)($bParts[2] ?? 0));
    }
}
$isTodaySunday = (date('l') === 'Sunday');

$pageTitle = 'Sales Counsellor Dashboard';
include __DIR__ . '/../includes/header.php';
?>

<!-- Header -->
<div class="page-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: var(--space-4); margin-bottom: var(--space-5);">
    <div>
        <h1 class="page-title"><i class="fa-solid fa-chart-pie" style="color: var(--color-primary); margin-right: 8px;"></i>Sales Counsellor Dashboard</h1>
        <p class="page-subtitle">Welcome back, <strong><?php echo e(getUserName()); ?></strong>! Manage your leads, follow-ups, and admissions.</p>
    </div>
    <div style="display: flex; gap: var(--space-2); flex-wrap: wrap;">
        <?php if ($canAddLeads): ?>
            <button type="button" class="btn btn-primary" onclick="openAddLeadModal('course')">
                <i class="fa-solid fa-graduation-cap"></i> + Add Course Lead
            </button>
            <button type="button" class="btn btn-warning" onclick="openAddLeadModal('service')" style="background: #f59e0b; color: #fff; border: none; font-weight: 600;">
                <i class="fa-solid fa-laptop-code"></i> + Add Service Lead
            </button>
            <button type="button" class="btn btn-secondary" onclick="openAddLeadModal('course', 'bulk')">
                <i class="fa-solid fa-file-import"></i> Bulk Upload Leads
            </button>
        <?php endif; ?>
        <a href="<?php echo BASE_URL; ?>/sales/leads.php" class="btn btn-secondary">
            <i class="fa-solid fa-users-rays"></i> View Full Pipeline
        </a>
    </div>
</div>

<!-- Daily Attendance & Shift Login / Logout Card -->
<div class="attendance-card fade-in" style="margin-bottom: var(--space-6);">
    <div class="attendance-status">
        <?php if (!$checkedIn): ?>
            <?php if ($isTodaySunday): ?>
                <i class="fa-solid fa-mug-hot" style="color: #f59e0b;"></i>
            <?php else: ?>
                <i class="fa-solid fa-clock"></i>
            <?php endif; ?>
        <?php elseif (!$checkedOut): ?>
            <i class="fa-solid fa-circle" style="color: var(--color-success);"></i>
        <?php else: ?>
            <i class="fa-solid fa-circle-check"></i>
        <?php endif; ?>
    </div>
    
    <h2 style="margin-bottom: var(--space-2);">
        <?php if (!$checkedIn): ?>
            <?php if ($isTodaySunday): ?>
                Today is Sunday · Weekly Off
            <?php else: ?>
                You haven't logged in yet
            <?php endif; ?>
        <?php elseif (!$checkedOut): ?>
            <?php if (!empty($todayAttendance['break_start']) && empty($todayAttendance['break_end'])): ?>
                On Break <i class="fa-solid fa-mug-hot"></i> (Work Timer Paused)
            <?php else: ?>
                Logged In & Working <i class="fa-solid fa-briefcase"></i> (Timer Active)
            <?php endif; ?>
        <?php else: ?>
            You're done for today!
        <?php endif; ?>
    </h2>
    
    <p class="text-muted" style="margin-bottom: var(--space-4);">
        <?php echo date('l, d F Y'); ?> · <span id="live-clock"></span>
        <?php if (!$checkedIn && $isTodaySunday): ?>
            <br><span style="font-size: 12px; color: #ca8a04; font-weight: 500;">(Official Paid Weekly Off · Optional: You can still log in if working extra)</span>
        <?php endif; ?>
    </p>

    <!-- Official Working Module & Shift Info Banner -->
    <?php echo renderWorkingModuleBanner(); ?>

    <!-- Live 6-Hour Shift Timer & Break Widget -->
    <?php if ($checkedIn && !$checkedOut): ?>
        <div id="shiftTimerWidget" 
             data-checked-in="1"
             data-checked-out="0"
             data-check-in-ts="<?php echo $checkInTimestamp; ?>"
             data-break-start-ts="<?php echo $breakStartTimestamp; ?>"
             data-break-end-ts="<?php echo $breakEndTimestamp; ?>"
             data-total-break-sec="<?php echo $totalBreakSec; ?>"
             style="background: var(--color-bg-tertiary); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: 18px 24px; max-width: 540px; margin: 0 auto 24px; text-align: center;">
            
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; flex-wrap: wrap; gap: 10px;">
                <span style="font-size: 13px; font-weight: 600; color: var(--color-text-secondary); display: flex; align-items: center; gap: 6px;">
                    <span id="timer-status-icon"><i class="fa-solid fa-stopwatch"></i></span> <span id="timer-status-text">Working Timer (6-Hour Goal)</span>
                </span>
                <span id="timer-badge" class="badge badge-primary" style="font-size: 12px;">Active</span>
            </div>

            <!-- Main Live Timer Display -->
            <div style="display: flex; justify-content: center; align-items: baseline; gap: 12px; margin-bottom: 12px;">
                <div style="font-size: 40px; font-weight: 800; font-family: monospace; letter-spacing: 2px; color: var(--color-primary);" id="timer-work-display">
                    00:00:00
                </div>
                <div style="font-size: 14px; color: var(--color-text-muted);">
                    / 06:00:00
                </div>
            </div>

            <!-- Live Progress Bar -->
            <div style="background: var(--color-bg-secondary); border-radius: 20px; height: 12px; width: 100%; overflow: hidden; margin-bottom: 12px; position: relative;">
                <div id="timer-progress-bar" style="background: linear-gradient(90deg, #4f6ef7, #10b981); height: 100%; width: 0%; transition: width 0.5s ease; border-radius: 20px;"></div>
            </div>

            <!-- Timer Details Subtext -->
            <div style="display: flex; justify-content: space-between; font-size: 12px; color: var(--color-text-muted);">
                <span>Remaining for Full Day: <strong id="timer-rem-display" style="color: var(--color-text-main);">06:00:00</strong></span>
                <span>Progress: <strong id="timer-progress-pct" style="color: var(--color-success);">0%</strong></span>
            </div>

            <!-- Break Timer Banner (Visible on Break) -->
            <div id="timer-break-banner" style="display: none; margin-top: 14px; padding: 10px; background: rgba(217, 119, 6, 0.15); border: 1px solid #d97706; border-radius: 8px; font-size: 13px; color: #fde68a;">
                <i class="fa-solid fa-mug-hot"></i> <strong>On Break (Working Timer Paused)</strong> · Break Time: <strong id="timer-break-display">00m 00s</strong> (Max 1h)
            </div>
        </div>
    <?php endif; ?>
    
    <!-- Action Buttons: Login / Break / Logout -->
    <div style="display: flex; gap: 12px; justify-content: center; align-items: center; flex-wrap: wrap; margin-bottom: 20px;">
        <?php if (!$checkedIn): ?>
            <button id="btn-check-in" class="btn btn-checkin" style="min-width: 160px; font-size: 15px; padding: 12px 24px;"><i class="fa-solid fa-circle" style="color: var(--color-success);"></i> Login / Check In</button>
        <?php elseif (!$checkedOut): ?>
            <?php if (empty($todayAttendance['break_start'])): ?>
                <button id="btn-start-break" class="btn btn-warning" style="min-width: 150px; font-size: 14px; padding: 10px 20px; background: #d97706; color: #fff;"><i class="fa-solid fa-mug-hot"></i> Start Break (1h)</button>
            <?php elseif (empty($todayAttendance['break_end'])): ?>
                <button id="btn-end-break" class="btn btn-info" style="min-width: 150px; font-size: 14px; padding: 10px 20px;"><i class="fa-solid fa-play"></i> End Break (Resume)</button>
            <?php endif; ?>
            
            <button id="btn-check-out" class="btn btn-checkout" style="min-width: 160px; font-size: 14px; padding: 10px 20px;"><i class="fa-solid fa-power-off"></i> Logout / Check Out</button>
        <?php endif; ?>
    </div>
    
    <?php if ($checkedIn): ?>
        <div class="attendance-time" style="display: flex; justify-content: center; gap: 20px; flex-wrap: wrap;">
            <div class="attendance-time-item">
                <div class="attendance-time-label">Login Time</div>
                <div class="attendance-time-value"><?php echo formatTime($todayAttendance['check_in']); ?></div>
            </div>
            
            <?php if (!empty($todayAttendance['break_start'])): ?>
                <div class="attendance-time-item">
                    <div class="attendance-time-label">Break Start</div>
                    <div class="attendance-time-value"><?php echo formatTime($todayAttendance['break_start']); ?></div>
                </div>
            <?php endif; ?>

            <?php if (!empty($todayAttendance['break_end'])): ?>
                <div class="attendance-time-item">
                    <div class="attendance-time-label">Break Duration</div>
                    <div class="attendance-time-value"><?php echo $todayAttendance['total_break_time'] ?: '—'; ?></div>
                </div>
            <?php endif; ?>

            <?php if ($checkedOut): ?>
                <div class="attendance-time-item">
                    <div class="attendance-time-label">Logout Time</div>
                    <div class="attendance-time-value"><?php echo formatTime($todayAttendance['check_out']); ?></div>
                </div>
                <div class="attendance-time-item">
                    <div class="attendance-time-label">Net Working Time</div>
                    <div class="attendance-time-value"><?php echo $todayAttendance['total_working_time'] ?: '—'; ?></div>
                </div>
                <div class="attendance-time-item">
                    <div class="attendance-time-label">Status</div>
                    <div class="attendance-time-value">
                        <span class="badge <?php echo $todayAttendance['status'] === 'present' ? 'badge-success' : ($todayAttendance['status'] === 'half-day' ? 'badge-warning' : 'badge-danger'); ?>">
                            <?php echo ucfirst($todayAttendance['status']); ?>
                        </span>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<!-- 🎯 Target of This Month (Monthly Performance Goal & Milestone) -->
<div class="card fade-in" style="margin-bottom: var(--space-6); padding: 0; overflow: hidden; border: 1px solid rgba(99, 102, 241, 0.25); background: linear-gradient(135deg, rgba(30, 41, 59, 0.7) 0%, rgba(15, 23, 42, 0.9) 100%); box-shadow: 0 10px 30px rgba(0, 0, 0, 0.25); position: relative;">
    
    <!-- Top Glowing Accent Line -->
    <div style="height: 3px; background: linear-gradient(90deg, #6366f1, #10b981, #f59e0b); width: 100%;"></div>

    <!-- Header -->
    <div style="padding: 18px 24px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px; border-bottom: 1px solid var(--color-border); background: rgba(255, 255, 255, 0.02);">
        <div style="display: flex; align-items: center; gap: 12px;">
            <div style="width: 44px; height: 44px; border-radius: 12px; background: linear-gradient(135deg, #6366f1, #4338ca); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 20px; box-shadow: 0 4px 16px rgba(99, 102, 241, 0.35);">
                <i class="fa-solid fa-bullseye"></i>
            </div>
            <div>
                <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                    <h2 style="margin: 0; font-size: 17px; font-weight: 700; color: var(--color-text-main); letter-spacing: -0.2px;">
                        Target of This Month · <?php echo e($monthlyTarget['month_label']); ?>
                    </h2>
                    <?php if ($monthlyTarget['has_target']): ?>
                        <span class="badge <?php echo $monthlyTarget['is_target_met'] ? 'badge-success' : ($monthlyTarget['revenue_pct'] >= 50 ? 'badge-primary' : 'badge-warning'); ?>" style="font-size: 11.5px; padding: 3px 10px; font-weight: 600;">
                            <?php echo $monthlyTarget['status_badge']; ?>
                        </span>
                    <?php else: ?>
                        <span class="badge badge-secondary" style="font-size: 11.5px; padding: 3px 10px;">
                            Target Pending
                        </span>
                    <?php endif; ?>
                </div>
                <div style="font-size: 12.5px; color: var(--color-text-secondary); margin-top: 3px;">
                    <?php if ($monthlyTarget['has_target'] && !empty($monthlyTarget['set_by_name'])): ?>
                        Assigned by <strong><?php echo e($monthlyTarget['set_by_name']); ?></strong> (<?php echo e(ucwords(str_replace('_', ' ', $monthlyTarget['set_by_role']))); ?>)
                    <?php else: ?>
                        Monthly conversion & revenue milestone
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
            <!-- Days Remaining Pill -->
            <div style="display: flex; align-items: center; gap: 6px; padding: 6px 14px; background: var(--color-bg-secondary); border: 1px solid var(--color-border); border-radius: 20px; font-size: 12.5px; color: var(--color-text-main); font-weight: 600;">
                <i class="fa-solid fa-hourglass-half" style="color: #f59e0b;"></i>
                <span><?php echo $monthlyTarget['days_remaining']; ?> Days Left in <?php echo date('M'); ?></span>
            </div>

            <?php if ($canManageTargets): ?>
                <button type="button" class="btn btn-sm btn-primary" onclick="openSalesTargetModal(<?php echo $userId; ?>, '<?php echo $monthlyTarget['month']; ?>', <?php echo $monthlyTarget['target_revenue']; ?>, <?php echo $monthlyTarget['target_enrollments']; ?>, <?php echo $monthlyTarget['target_leads']; ?>, '<?php echo addslashes($monthlyTarget['notes']); ?>')" style="font-size: 12.5px; font-weight: 600; padding: 6px 14px; display: inline-flex; align-items: center; gap: 6px;">
                    <i class="fa-solid fa-pen-to-square"></i> <?php echo $monthlyTarget['has_target'] ? 'Edit Target' : 'Set Target'; ?>
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Target Body Content -->
    <div style="padding: 22px 24px;">
        <?php if ($monthlyTarget['has_target']): ?>
            
            <!-- 3 Main Metric Progress Pillars -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 18px; margin-bottom: <?php echo !empty($monthlyTarget['notes']) ? '18px' : '0'; ?>;">
                
                <!-- 1. Revenue Milestone -->
                <div style="background: var(--color-bg-tertiary); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: 18px 20px; position: relative; overflow: hidden;">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px;">
                        <div>
                            <span style="font-size: 12px; font-weight: 600; color: var(--color-text-secondary); text-transform: uppercase; letter-spacing: 0.5px; display: flex; align-items: center; gap: 6px;">
                                <i class="fa-solid fa-indian-rupee-sign" style="color: #10b981;"></i> Collected Revenue Target
                            </span>
                            <div style="font-size: 24px; font-weight: 800; color: var(--color-text-white); margin-top: 4px; font-family: monospace;">
                                ₹<?php echo number_format($monthlyTarget['achieved_revenue'], 2); ?>
                            </div>
                            <div style="font-size: 11px; color: var(--color-text-muted); margin-top: 2px;">
                                Actual installments paid in <?php echo date('M Y'); ?>
                            </div>
                        </div>
                        <div style="text-align: right;">
                            <span style="font-size: 11px; color: var(--color-text-muted); display: block;">Monthly Goal</span>
                            <div style="font-size: 15px; font-weight: 700; color: var(--color-primary); font-family: monospace;">
                                ₹<?php echo number_format($monthlyTarget['target_revenue'], 2); ?>
                            </div>
                            <?php if ($monthlyTarget['booked_deal_value'] > 0): ?>
                                <div style="font-size: 10.5px; color: var(--color-text-muted); margin-top: 2px;" title="Total Course Fee Value of Admissions Closed This Month">
                                    Booked Deal: ₹<?php echo number_format($monthlyTarget['booked_deal_value']); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Progress Bar -->
                    <div style="background: var(--color-bg-secondary); border-radius: 12px; height: 10px; width: 100%; overflow: hidden; margin-bottom: 10px;">
                        <div style="background: linear-gradient(90deg, #10b981, #059669); height: 100%; width: <?php echo min(100, $monthlyTarget['revenue_pct']); ?>%; border-radius: 12px; transition: width 0.6s ease;"></div>
                    </div>

                    <div style="display: flex; justify-content: space-between; align-items: center; font-size: 12px;">
                        <span style="color: var(--color-success); font-weight: 700;">
                            <?php echo $monthlyTarget['revenue_pct']; ?>% Achieved
                        </span>
                        <span style="color: var(--color-text-secondary);">
                            <?php if ($monthlyTarget['revenue_gap'] > 0): ?>
                                Gap: <strong style="color: var(--color-warning);">₹<?php echo number_format($monthlyTarget['revenue_gap'], 2); ?></strong>
                            <?php else: ?>
                                <strong style="color: var(--color-success);"><i class="fa-solid fa-circle-check"></i> Goal Surpassed!</strong>
                            <?php endif; ?>
                        </span>
                    </div>
                </div>

                <!-- 2. Admissions / Enrollments Goal -->
                <div style="background: var(--color-bg-tertiary); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: 18px 20px; position: relative; overflow: hidden;">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px;">
                        <div>
                            <span style="font-size: 12px; font-weight: 600; color: var(--color-text-secondary); text-transform: uppercase; letter-spacing: 0.5px; display: flex; align-items: center; gap: 6px;">
                                <i class="fa-solid fa-graduation-cap" style="color: var(--color-primary);"></i> Admissions Goal
                            </span>
                            <div style="font-size: 24px; font-weight: 800; color: var(--color-text-white); margin-top: 4px; font-family: monospace;">
                                <?php echo $monthlyTarget['achieved_enrollments']; ?> <span style="font-size: 14px; font-weight: 500; color: var(--color-text-secondary);">Closed</span>
                            </div>
                        </div>
                        <div style="text-align: right;">
                            <span style="font-size: 11px; color: var(--color-text-muted); display: block;">Goal</span>
                            <div style="font-size: 15px; font-weight: 700; color: var(--color-primary); font-family: monospace;">
                                <?php echo $monthlyTarget['target_enrollments']; ?> Admissions
                            </div>
                        </div>
                    </div>

                    <!-- Progress Bar -->
                    <div style="background: var(--color-bg-secondary); border-radius: 12px; height: 10px; width: 100%; overflow: hidden; margin-bottom: 10px;">
                        <div style="background: linear-gradient(90deg, #6366f1, #4f46e5); height: 100%; width: <?php echo min(100, $monthlyTarget['enrollments_pct']); ?>%; border-radius: 12px; transition: width 0.6s ease;"></div>
                    </div>

                    <div style="display: flex; justify-content: space-between; align-items: center; font-size: 12px;">
                        <span style="color: var(--color-primary); font-weight: 700;">
                            <?php echo $monthlyTarget['enrollments_pct']; ?>% Completed
                        </span>
                        <span style="color: var(--color-text-secondary);">
                            <?php if ($monthlyTarget['enrollments_gap'] > 0): ?>
                                Needed: <strong style="color: var(--color-warning);"><?php echo $monthlyTarget['enrollments_gap']; ?> more</strong>
                            <?php else: ?>
                                <strong style="color: var(--color-success);"><i class="fa-solid fa-circle-check"></i> Target Met!</strong>
                            <?php endif; ?>
                        </span>
                    </div>
                </div>

                <!-- 3. Outreach / Leads Processing Target -->
                <div style="background: var(--color-bg-tertiary); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: 18px 20px; position: relative; overflow: hidden;">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px;">
                        <div>
                            <span style="font-size: 12px; font-weight: 600; color: var(--color-text-secondary); text-transform: uppercase; letter-spacing: 0.5px; display: flex; align-items: center; gap: 6px;">
                                <i class="fa-solid fa-phone-volume" style="color: #f59e0b;"></i> Prospect Outreach
                            </span>
                            <div style="font-size: 24px; font-weight: 800; color: var(--color-text-white); margin-top: 4px; font-family: monospace;">
                                <?php echo $monthlyTarget['achieved_leads']; ?> <span style="font-size: 14px; font-weight: 500; color: var(--color-text-secondary);">Contacted</span>
                            </div>
                        </div>
                        <div style="text-align: right;">
                            <span style="font-size: 11px; color: var(--color-text-muted); display: block;">Target</span>
                            <div style="font-size: 15px; font-weight: 700; color: #f59e0b; font-family: monospace;">
                                <?php echo $monthlyTarget['target_leads'] > 0 ? $monthlyTarget['target_leads'] . ' Leads' : 'Standard'; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Progress Bar -->
                    <div style="background: var(--color-bg-secondary); border-radius: 12px; height: 10px; width: 100%; overflow: hidden; margin-bottom: 10px;">
                        <div style="background: linear-gradient(90deg, #f59e0b, #d97706); height: 100%; width: <?php echo min(100, $monthlyTarget['target_leads'] > 0 ? $monthlyTarget['leads_pct'] : min(100, ($monthlyTarget['achieved_leads'] / 30) * 100)); ?>%; border-radius: 12px; transition: width 0.6s ease;"></div>
                    </div>

                    <div style="display: flex; justify-content: space-between; align-items: center; font-size: 12px;">
                        <span style="color: #f59e0b; font-weight: 700;">
                            <?php echo $monthlyTarget['target_leads'] > 0 ? $monthlyTarget['leads_pct'] . '% Outreach Target' : 'Active Pipeline'; ?>
                        </span>
                        <span style="color: var(--color-text-secondary);">
                            Collected: <strong style="color: var(--color-success);">₹<?php echo number_format($monthlyTarget['collected_revenue'], 2); ?></strong>
                        </span>
                    </div>
                </div>

            </div>

            <!-- 💰 Admissions & Sales Commission Earnings (Confidential: Founder & Sales Counsellor Only) -->
            <?php if ($canViewCommission): ?>
                <div style="margin-top: 18px; padding: 16px 20px; background: linear-gradient(135deg, rgba(245, 158, 11, 0.12) 0%, rgba(16, 185, 129, 0.08) 100%); border: 1.5px solid rgba(245, 158, 11, 0.35); border-radius: var(--radius-lg); position: relative; overflow: hidden;">
                    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; margin-bottom: 12px; border-bottom: 1px solid rgba(245, 158, 11, 0.2); padding-bottom: 8px;">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <div style="width: 32px; height: 32px; border-radius: 8px; background: linear-gradient(135deg, #f59e0b, #d97706); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 15px; box-shadow: 0 4px 10px rgba(245, 158, 11, 0.35);">
                                <i class="fa-solid fa-coins"></i>
                            </div>
                            <div>
                                <h3 style="margin: 0; font-size: 14.5px; font-weight: 700; color: var(--color-text-main); display: flex; align-items: center; gap: 8px;">
                                    Admissions Commission & Incentive Payout
                                    <span class="badge" style="background: rgba(245, 158, 11, 0.2); color: #f59e0b; font-size: 10.5px; border: 1px solid rgba(245, 158, 11, 0.4); font-weight: 700;">₹5,000 / Admission</span>
                                </h3>
                            </div>
                        </div>
                        <div>
                            <span style="font-size: 11.5px; color: var(--color-text-muted); font-weight: 500;">
                                <i class="fa-solid fa-lock" style="font-size: 10px; color: #f59e0b;"></i> Visible only to Sales & Founder
                            </span>
                        </div>
                    </div>

                    <!-- 4 Commission Metrics Grid -->
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px;">
                        <!-- 1. Month Admissions Done -->
                        <div style="background: var(--color-bg-card); border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: 12px 14px;">
                            <span style="font-size: 11px; font-weight: 600; color: var(--color-text-secondary); text-transform: uppercase; letter-spacing: 0.5px; display: block; margin-bottom: 3px;">
                                <i class="fa-solid fa-graduation-cap" style="color: var(--color-primary);"></i> Admissions (<?php echo date('M'); ?>)
                            </span>
                            <div style="font-size: 20px; font-weight: 800; color: var(--color-text-white); font-family: monospace;">
                                <?php echo $monthlyTarget['achieved_enrollments']; ?> <span style="font-size: 12px; font-weight: 500; color: var(--color-text-secondary);">Closed</span>
                            </div>
                            <div style="font-size: 11px; color: var(--color-text-muted); margin-top: 3px;">
                                Rate: ₹5,000 per admission
                            </div>
                        </div>

                        <!-- 2. Earned Commission This Month -->
                        <div style="background: var(--color-bg-card); border: 1.5px solid rgba(16, 185, 129, 0.4); border-radius: var(--radius-md); padding: 12px 14px; background: linear-gradient(135deg, rgba(16, 185, 129, 0.08), rgba(6, 78, 59, 0.15));">
                            <span style="font-size: 11px; font-weight: 600; color: #10b981; text-transform: uppercase; letter-spacing: 0.5px; display: block; margin-bottom: 3px;">
                                <i class="fa-solid fa-wallet" style="color: #10b981;"></i> Commission (<?php echo date('M'); ?>)
                            </span>
                            <div style="font-size: 20px; font-weight: 800; color: #10b981; font-family: monospace;">
                                ₹<?php echo number_format($monthlyTarget['earned_commission'], 2); ?>
                            </div>
                            <div style="font-size: 11px; color: var(--color-text-secondary); margin-top: 3px;">
                                <?php echo $monthlyTarget['achieved_enrollments']; ?> × ₹5,000 earned this month
                            </div>
                        </div>

                        <!-- 3. Target Potential Commission -->
                        <?php if ($monthlyTarget['has_target'] && $monthlyTarget['target_enrollments'] > 0): ?>
                        <div style="background: var(--color-bg-card); border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: 12px 14px;">
                            <span style="font-size: 11px; font-weight: 600; color: var(--color-text-secondary); text-transform: uppercase; letter-spacing: 0.5px; display: block; margin-bottom: 3px;">
                                <i class="fa-solid fa-bullseye" style="color: #f59e0b;"></i> Target Commission
                            </span>
                            <div style="font-size: 20px; font-weight: 800; color: #f59e0b; font-family: monospace;">
                                ₹<?php echo number_format($monthlyTarget['target_potential_commission'], 2); ?>
                            </div>
                            <div style="font-size: 11px; color: var(--color-text-muted); margin-top: 3px;">
                                On <?php echo $monthlyTarget['target_enrollments']; ?> target admissions
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- 4. Lifetime Admissions & Total Payout -->
                        <div style="background: var(--color-bg-card); border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: 12px 14px;">
                            <span style="font-size: 11px; font-weight: 600; color: var(--color-text-secondary); text-transform: uppercase; letter-spacing: 0.5px; display: block; margin-bottom: 3px;">
                                <i class="fa-solid fa-trophy" style="color: #6366f1;"></i> Lifetime Commission
                            </span>
                            <div style="font-size: 20px; font-weight: 800; color: var(--color-text-white); font-family: monospace;">
                                ₹<?php echo number_format($monthlyTarget['lifetime_commission'], 2); ?>
                            </div>
                            <div style="font-size: 11px; color: var(--color-text-secondary); margin-top: 3px;">
                                All-Time: <strong><?php echo $monthlyTarget['lifetime_admissions']; ?></strong> Total Admissions
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Optional Incentive / Bonus Banner -->
            <?php if (!empty($monthlyTarget['notes'])): ?>
                <div style="margin-top: 14px; display: flex; align-items: center; gap: 10px; padding: 12px 16px; background: rgba(236, 72, 153, 0.1); border: 1px solid rgba(236, 72, 153, 0.3); border-radius: var(--radius-md); font-size: 13px; color: #fbcfe8;">
                    <i class="fa-solid fa-gift" style="color: #ec4899; font-size: 16px;"></i>
                    <div>
                        <strong style="color: #ffffff;">Target Incentive & Note:</strong> <?php echo e($monthlyTarget['notes']); ?>
                    </div>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <!-- Empty State / No Target Set Yet -->
            <div style="text-align: center; padding: 24px 16px;">
                <div style="width: 50px; height: 50px; border-radius: 50%; background: rgba(99, 102, 241, 0.12); color: var(--color-primary); display: inline-flex; align-items: center; justify-content: center; font-size: 22px; margin-bottom: 12px;">
                    <i class="fa-solid fa-bullseye"></i>
                </div>
                <h3 style="margin: 0 0 6px 0; font-size: 16px; font-weight: 600; color: var(--color-text-main);">
                    No Monthly Target Assigned for <?php echo e($monthlyTarget['month_label']); ?>
                </h3>
                <p style="margin: 0 auto 16px auto; font-size: 13px; color: var(--color-text-secondary); max-width: 480px;">
                    <?php if ($canManageTargets): ?>
                        As a Founder/Manager, you can set a monthly revenue and admissions goal for this sales counsellor to drive performance.
                    <?php else: ?>
                        Your monthly sales targets will appear here once assigned by the Founder or Manager. Keep working through your follow-up queue to maximize admissions!
                    <?php endif; ?>
                </p>
                <?php if ($canManageTargets): ?>
                    <button type="button" class="btn btn-primary" onclick="openSalesTargetModal(<?php echo $userId; ?>, '<?php echo $monthlyTarget['month']; ?>')" style="font-size: 13.5px; font-weight: 600; padding: 8px 20px;">
                        <i class="fa-solid fa-plus-circle"></i> Set Target for <?php echo e($monthlyTarget['month_label']); ?>
                    </button>
                <?php endif; ?>
            </div>

            <!-- Even if no target, show Commission Section if user is Sales Counsellor / Founder -->
            <?php if ($canViewCommission): ?>
                <div style="margin-top: 14px; padding: 14px 18px; background: linear-gradient(135deg, rgba(245, 158, 11, 0.12) 0%, rgba(16, 185, 129, 0.08) 100%); border: 1.5px solid rgba(245, 158, 11, 0.35); border-radius: var(--radius-lg);">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                        <span style="font-size: 13.5px; font-weight: 700; color: var(--color-text-main);">
                            <i class="fa-solid fa-coins" style="color: #f59e0b;"></i> Admissions Commission: ₹5,000 / Admission
                        </span>
                        <span style="font-size: 11px; color: var(--color-text-muted);"><i class="fa-solid fa-lock" style="color: #f59e0b;"></i> Confidential</span>
                    </div>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 10px;">
                        <div style="background: var(--color-bg-card); padding: 10px 14px; border-radius: var(--radius-md); border: 1px solid var(--color-border);">
                            <div style="font-size: 11px; color: var(--color-text-secondary);">Admissions (<?php echo date('M'); ?>)</div>
                            <div style="font-size: 18px; font-weight: 800; color: var(--color-text-white);"><?php echo $monthlyTarget['achieved_enrollments']; ?> Admissions</div>
                        </div>
                        <div style="background: var(--color-bg-card); padding: 10px 14px; border-radius: var(--radius-md); border: 1.5px solid rgba(16, 185, 129, 0.4);">
                            <div style="font-size: 11px; color: #10b981;">Commission Earned (<?php echo date('M'); ?>)</div>
                            <div style="font-size: 18px; font-weight: 800; color: #10b981;">₹<?php echo number_format($monthlyTarget['earned_commission'], 2); ?></div>
                        </div>
                        <div style="background: var(--color-bg-card); padding: 10px 14px; border-radius: var(--radius-md); border: 1px solid var(--color-border);">
                            <div style="font-size: 11px; color: var(--color-text-secondary);">All-Time Commission</div>
                            <div style="font-size: 18px; font-weight: 800; color: var(--color-text-white);">₹<?php echo number_format($monthlyTarget['lifetime_commission'], 2); ?></div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

        <?php endif; ?>
    </div>
</div>

<!-- Sales Performance KPI Cards (4x2 Symmetrical Grid) -->
<style>
.counsellor-kpi-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: var(--space-4);
    margin-bottom: var(--space-6);
}
@media (max-width: 1180px) {
    .counsellor-kpi-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}
@media (max-width: 580px) {
    .counsellor-kpi-grid {
        grid-template-columns: 1fr;
    }
}
.counsellor-kpi-card {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 16px 18px;
    background: var(--color-bg-card);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-sm);
    transition: transform var(--transition-fast), box-shadow var(--transition-fast);
}
.counsellor-kpi-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-md);
}
.counsellor-kpi-icon {
    width: 48px;
    height: 48px;
    border-radius: var(--radius-md);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 19px;
    flex-shrink: 0;
}
.counsellor-kpi-info {
    min-width: 0;
    flex: 1;
}
.counsellor-kpi-val {
    font-size: 22px;
    font-weight: 800;
    line-height: 1.2;
    color: var(--color-text-white);
    font-family: monospace;
}
.counsellor-kpi-lbl {
    font-size: 12px;
    color: var(--color-text-secondary);
    margin-top: 3px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
</style>

<div class="counsellor-kpi-grid">
    <!-- 1. Total Leads -->
    <div class="counsellor-kpi-card fade-in">
        <div class="counsellor-kpi-icon" style="background: rgba(99, 102, 241, 0.15); color: var(--color-primary);">
            <i class="fa-solid fa-users"></i>
        </div>
        <div class="counsellor-kpi-info">
            <div class="counsellor-kpi-val"><?php echo $salesMetrics['total_leads']; ?></div>
            <div class="counsellor-kpi-lbl">Total Assigned Leads</div>
        </div>
    </div>

    <!-- 2. Fresh Leads -->
    <div class="counsellor-kpi-card fade-in">
        <div class="counsellor-kpi-icon" style="background: rgba(59, 130, 246, 0.15); color: #3b82f6;">
            <i class="fa-solid fa-user-plus"></i>
        </div>
        <div class="counsellor-kpi-info">
            <div class="counsellor-kpi-val"><?php echo $salesMetrics['new_leads']; ?></div>
            <div class="counsellor-kpi-lbl">Fresh Leads</div>
        </div>
    </div>

    <!-- 3. Interested Leads -->
    <div class="counsellor-kpi-card fade-in">
        <div class="counsellor-kpi-icon" style="background: rgba(245, 158, 11, 0.15); color: #f59e0b;">
            <i class="fa-solid fa-clock-rotate-left"></i>
        </div>
        <div class="counsellor-kpi-info">
            <div class="counsellor-kpi-val"><?php echo $salesMetrics['interested']; ?></div>
            <div class="counsellor-kpi-lbl">In Follow-up</div>
        </div>
    </div>

    <!-- 4. Enrolled / Converted -->
    <div class="counsellor-kpi-card fade-in">
        <div class="counsellor-kpi-icon" style="background: rgba(16, 185, 129, 0.15); color: #10b981;">
            <i class="fa-solid fa-circle-check"></i>
        </div>
        <div class="counsellor-kpi-info">
            <div class="counsellor-kpi-val"><?php echo $salesMetrics['enrolled']; ?></div>
            <div class="counsellor-kpi-lbl">Enrolled (Admissions)</div>
        </div>
    </div>

    <!-- 5. Admissions Commission KPI Card -->
    <?php if ($canViewCommission): ?>
    <div class="counsellor-kpi-card fade-in" style="border: 1.5px solid rgba(245, 158, 11, 0.45); background: linear-gradient(135deg, rgba(245, 158, 11, 0.12), rgba(245, 158, 11, 0.02));">
        <div class="counsellor-kpi-icon" style="background: rgba(245, 158, 11, 0.25); color: #f59e0b;">
            <i class="fa-solid fa-coins"></i>
        </div>
        <div class="counsellor-kpi-info">
            <div class="counsellor-kpi-val" style="color: #f59e0b;">₹<?php echo number_format($monthlyTarget['earned_commission']); ?></div>
            <div class="counsellor-kpi-lbl" style="color: #f59e0b; font-weight: 600;">Month Commission (₹5k/adm)</div>
        </div>
    </div>
    <?php else: ?>
    <div class="counsellor-kpi-card fade-in">
        <div class="counsellor-kpi-icon" style="background: rgba(245, 158, 11, 0.15); color: #f59e0b;">
            <i class="fa-solid fa-business-time"></i>
        </div>
        <div class="counsellor-kpi-info">
            <div class="counsellor-kpi-val"><?php echo $monthlyTarget['achieved_leads']; ?></div>
            <div class="counsellor-kpi-lbl">Contacted Leads</div>
        </div>
    </div>
    <?php endif; ?>

    <!-- 6. Enrolled Revenue -->
    <div class="counsellor-kpi-card fade-in">
        <div class="counsellor-kpi-icon" style="background: rgba(16, 185, 129, 0.15); color: #10b981;">
            <i class="fa-solid fa-indian-rupee-sign"></i>
        </div>
        <div class="counsellor-kpi-info">
            <div class="counsellor-kpi-val" style="color: #10b981;">₹<?php echo number_format($salesMetrics['total_enrolled_value']); ?></div>
            <div class="counsellor-kpi-lbl">Enrolled Revenue</div>
        </div>
    </div>

    <!-- 7. Conversion Rate -->
    <div class="counsellor-kpi-card fade-in">
        <div class="counsellor-kpi-icon" style="background: rgba(168, 85, 247, 0.15); color: #a855f7;">
            <i class="fa-solid fa-bullseye"></i>
        </div>
        <div class="counsellor-kpi-info">
            <div class="counsellor-kpi-val"><?php echo $salesMetrics['conversion_rate']; ?>%</div>
            <div class="counsellor-kpi-lbl">Conversion Rate</div>
        </div>
    </div>

    <!-- 8. Today's Due Followups -->
    <div class="counsellor-kpi-card fade-in">
        <div class="counsellor-kpi-icon" style="background: rgba(239, 68, 68, 0.15); color: #ef4444;">
            <i class="fa-solid fa-phone-volume"></i>
        </div>
        <div class="counsellor-kpi-info">
            <div class="counsellor-kpi-val"><?php echo $salesMetrics['today_followups_count']; ?></div>
            <div class="counsellor-kpi-lbl">Today's Due Calls</div>
        </div>
    </div>
</div>

<!-- Two-Column Layout: Today's Follow-up Queue & Pipeline Split -->
<div style="display: grid; grid-template-columns: 2fr 1fr; gap: var(--space-6); margin-bottom: var(--space-6);" class="dashboard-grid-responsive">
    
    <!-- LEFT: Today's Follow-up Queue -->
    <div class="card fade-in" style="padding: var(--space-5);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--space-4); border-bottom: 1px solid var(--color-border); padding-bottom: var(--space-3);">
            <div>
                <h3 style="margin: 0; font-size: 16px; font-weight: 600; display: flex; align-items: center; gap: 8px;">
                    <i class="fa-solid fa-calendar-check" style="color: var(--color-primary);"></i>
                    Today's Priority Follow-up Queue
                </h3>
                <p style="margin: 4px 0 0 0; font-size: 12px; color: var(--color-text-secondary);">Scheduled calls due for today</p>
            </div>
            <span class="badge badge-primary"><?php echo count($todayFollowups); ?> Due</span>
        </div>

        <?php if (empty($todayFollowups)): ?>
            <div style="text-align: center; padding: var(--space-8) var(--space-4); color: var(--color-text-muted);">
                <i class="fa-solid fa-circle-check" style="font-size: 36px; color: var(--color-success); margin-bottom: var(--space-2);"></i>
                <p style="margin: 0; font-size: 14px; font-weight: 500;">All caught up for today!</p>
                <p style="margin: 4px 0 0 0; font-size: 12px;">No pending scheduled follow-ups left for today.</p>
            </div>
        <?php else: ?>
            <div style="overflow-x: auto;">
                <table class="data-table" style="width: 100%;">
                    <thead>
                        <tr>
                            <th>Prospect</th>
                            <th>Interest</th>
                            <th>Status</th>
                            <th style="text-align: right;">Quick Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($todayFollowups as $lead): ?>
                            <tr>
                                <td>
                                    <div style="font-weight: 600; color: var(--color-text-white);"><?php echo e($lead['name']); ?></div>
                                    <div style="font-size: 12px; color: var(--color-text-secondary);"><i class="fa-solid fa-phone" style="font-size: 10px;"></i> <?php echo e($lead['phone']); ?></div>
                                </td>
                                <td>
                                    <span class="badge <?php echo $lead['offering_type'] === 'course' ? 'badge-primary' : 'badge-warning'; ?>" style="font-size: 10px;">
                                        <?php echo ucfirst($lead['offering_type']); ?>
                                    </span>
                                    <div style="font-size: 12px; color: var(--color-text-white); margin-top: 2px;">
                                        <?php echo e($lead['course_service_name']); ?>
                                    </div>
                                </td>
                                <td data-lead-status-id="<?php echo $leadId; ?>"><?php echo getLeadStatusBadge($lead['status']); ?></td>
                                <td style="text-align: right;">
                                    <div style="display: inline-flex; gap: 6px;">
                                        <a href="tel:<?php echo e($lead['phone']); ?>" class="btn btn-sm btn-ghost" title="Call" style="padding: 4px 8px; color: var(--color-primary);">
                                            <i class="fa-solid fa-phone"></i>
                                        </a>
                                        <button type="button" class="btn btn-sm btn-ghost text-success" title="WhatsApp" style="padding: 4px 8px; color: var(--color-success); border: none; background: none; cursor: pointer;" onclick="openWhatsAppModal(<?php echo (int)$lead['id']; ?>, '<?php echo addslashes(e($lead['name'])); ?>', '<?php echo e($lead['phone']); ?>', 1, '<?php echo addslashes(e($lead['course_service_name'] ?? 'UI/UX Design')); ?>')">
                                            <i class="fa-brands fa-whatsapp"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-primary" onclick="openUpdateStatusModal(<?php echo (int)$lead['id']; ?>, '<?php echo addslashes(e($lead['name'])); ?>', '<?php echo e($lead['status']); ?>', '<?php echo e($lead['offering_type'] ?? 'course'); ?>', <?php echo (int)($lead['course_service_id'] ?? 0); ?>, '<?php echo addslashes(e($lead['course_service_name'] ?? '')); ?>', <?php echo (float)($lead['expected_revenue'] ?? 0); ?>)" title="Update Status & Note" style="padding: 4px 10px; font-size: 12px;">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- RIGHT: Course vs Service Breakdown & Quick Overview -->
    <div>
        <div class="card fade-in" style="margin-bottom: var(--space-4); padding: var(--space-5);">
            <h4 style="margin: 0 0 var(--space-4) 0; font-size: 14px; font-weight: 600; display: flex; align-items: center; gap: 8px;">
                <i class="fa-solid fa-shapes" style="color: var(--color-primary);"></i> Offering Breakdown
            </h4>
            <div style="display: flex; flex-direction: column; gap: var(--space-3);">
                <!-- Courses -->
                <div style="padding: var(--space-3) var(--space-4); background: var(--color-bg-tertiary); border-radius: var(--radius-md); display: flex; justify-content: space-between; align-items: center;">
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <span style="width: 10px; height: 10px; border-radius: 50%; background: var(--color-primary);"></span>
                        <span style="font-size: 13px; font-weight: 500;">Courses Inquiries</span>
                    </div>
                    <span style="font-weight: 700; font-size: 15px;"><?php echo $salesMetrics['course_leads']; ?></span>
                </div>
                <!-- Services -->
                <div style="padding: var(--space-3) var(--space-4); background: var(--color-bg-tertiary); border-radius: var(--radius-md); display: flex; justify-content: space-between; align-items: center;">
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <span style="width: 10px; height: 10px; border-radius: 50%; background: #f59e0b;"></span>
                        <span style="font-size: 13px; font-weight: 500;">Services Inquiries</span>
                    </div>
                    <span style="font-weight: 700; font-size: 15px;"><?php echo $salesMetrics['service_leads']; ?></span>
                </div>
            </div>
        </div>

        <?php if (!empty($overdueFollowups)): ?>
            <div class="card fade-in" style="border-left: 4px solid var(--color-danger); background: var(--color-danger-bg); padding: var(--space-4);">
                <h4 style="margin: 0 0 8px 0; font-size: 13px; font-weight: 600; color: var(--color-danger); display: flex; align-items: center; gap: 6px;">
                    <i class="fa-solid fa-triangle-exclamation"></i> Overdue Follow-ups (<?php echo count($overdueFollowups); ?>)
                </h4>
                <div style="display: flex; flex-direction: column; gap: 8px;">
                    <?php foreach ($overdueFollowups as $od): ?>
                        <div style="font-size: 12px; display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <strong><?php echo e($od['name']); ?></strong> (<?php echo e($od['phone']); ?>)
                            </div>
                            <button type="button" class="btn btn-xs btn-outline text-danger" onclick="openUpdateStatusModal(<?php echo (int)$od['id']; ?>, '<?php echo addslashes(e($od['name'])); ?>', '<?php echo e($od['status']); ?>', '<?php echo e($od['offering_type'] ?? 'course'); ?>', <?php echo (int)($od['course_service_id'] ?? 0); ?>, '<?php echo addslashes(e($od['course_service_name'] ?? '')); ?>', <?php echo (float)($od['expected_revenue'] ?? 0); ?>)">
                                Call
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- SECTION 1: COURSE LEADS & ADMISSIONS -->
<div class="data-table-container fade-in" style="background: var(--color-bg-card); border: 1px solid var(--color-border); border-radius: var(--radius-lg); overflow: hidden; margin-bottom: var(--space-6);">
    <div style="display: flex; justify-content: space-between; align-items: center; padding: var(--space-4) var(--space-5); border-bottom: 1px solid var(--color-border); flex-wrap: wrap; gap: var(--space-3);">
        <div style="display: flex; align-items: center; gap: 10px;">
            <div style="width: 36px; height: 36px; border-radius: var(--radius-md); background: rgba(99, 102, 241, 0.15); color: var(--color-primary); display: flex; align-items: center; justify-content: center; font-size: 16px;">
                <i class="fa-solid fa-graduation-cap"></i>
            </div>
            <div>
                <h3 style="margin: 0; font-size: 15px; font-weight: 600;">Course Leads & Student Admissions</h3>
                <p style="margin: 2px 0 0 0; font-size: 12px; color: var(--color-text-secondary);">Student training inquiries, batch enrollments, and syllabus counseling</p>
            </div>
        </div>
        <div style="display: flex; align-items: center; gap: var(--space-2);">
            <a href="<?php echo BASE_URL; ?>/sales/leads.php?offering_type=course" class="btn btn-sm btn-outline-primary">
                View All Course Leads (<?php echo $salesMetrics['course_leads']; ?>) <i class="fa-solid fa-arrow-right" style="font-size: 11px; margin-left: 4px;"></i>
            </a>
        </div>
    </div>

    <?php if (empty($recentCourseLeads)): ?>
        <div style="text-align: center; padding: var(--space-8) var(--space-4); color: var(--color-text-muted);">
            <i class="fa-solid fa-graduation-cap" style="font-size: 32px; margin-bottom: var(--space-2); color: var(--color-text-muted);"></i>
            <p style="margin: 0; font-size: 13.5px;">No course leads assigned yet.</p>
            <?php if ($canAddLeads): ?>
                <button type="button" class="btn btn-sm btn-primary" onclick="openAddLeadModal('course')" style="margin-top: 10px;">
                    <i class="fa-solid fa-plus"></i> Add Course Lead
                </button>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div style="overflow-x: auto;">
            <table class="data-table" style="width: 100%;">
                <thead>
                    <tr>
                        <th>Prospect Student</th>
                        <th>Course Name</th>
                        <th>Source</th>
                        <th>Status</th>
                        <th>Next Follow-up</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentCourseLeads as $lead): ?>
                        <tr>
                            <td>
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <div style="width: 32px; height: 32px; border-radius: 50%; background: rgba(99, 102, 241, 0.12); color: var(--color-primary-light); font-weight: 700; font-size: 11px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                        <?php echo strtoupper(substr($lead['name'] ?? 'P', 0, 2)); ?>
                                    </div>
                                    <div>
                                        <div style="font-weight: 600; color: var(--color-text-white); display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">
                                            <?php echo e($lead['name']); ?>
                                            <?php if (!empty($lead['graduation'])): ?>
                                                <span class="badge" style="font-size: 10px; padding: 1px 6px; background: rgba(99, 102, 241, 0.15); color: var(--color-primary-light); border: 1px solid rgba(99, 102, 241, 0.3);">
                                                    <i class="fa-solid fa-user-graduate" style="font-size: 9px; margin-right: 2px;"></i><?php echo e($lead['graduation']); ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <div style="font-size: 11.5px; color: var(--color-text-secondary); font-family: monospace; margin-top: 2px;">
                                            <i class="fa-solid fa-phone" style="font-size: 9px; margin-right: 3px;"></i><?php echo e($lead['phone']); ?>
                                            <?php if (!empty($lead['email'])): ?> • <?php echo e($lead['email']); ?><?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div style="font-weight: 500; color: var(--color-text-white);"><?php echo e($lead['course_service_name']); ?></div>
                                <?php if ($lead['expected_revenue'] > 0): ?>
                                    <div style="font-size: 11px; color: var(--color-success);">₹<?php echo number_format($lead['expected_revenue']); ?></div>
                                <?php endif; ?>
                            </td>
                            <td><span style="font-size: 12px; color: var(--color-text-secondary);"><?php echo e($lead['source']); ?></span></td>
                            <td data-lead-status-id="<?php echo (int)$lead['id']; ?>">
                                <?php echo getLeadStatusBadge($lead['status']); ?>
                                <?php if ($lead['status'] === 'enrolled' || (!empty($lead['payment_plan']) && $lead['payment_plan'] !== 'none')): ?>
                                    <div style="margin-top: 4px;">
                                        <?php echo getPaymentStatusBadge($lead['payment_status'] ?? 'unpaid', (float)($lead['paid_amount'] ?? 0), (float)($lead['expected_revenue'] ?? 0), $lead['payment_plan'] ?? 'none', $lead['offering_type'] ?? 'course'); ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($lead['next_followup_date'])): ?>
                                    <div style="font-size: 12px; <?php echo (strtotime($lead['next_followup_date']) < time() && $lead['status'] !== 'enrolled') ? 'color: var(--color-danger); font-weight: 600;' : ''; ?>">
                                        <i class="fa-regular fa-clock" style="font-size: 10px;"></i> <?php echo formatDate($lead['next_followup_date'], 'd M, h:i A'); ?>
                                    </div>
                                <?php else: ?>
                                    <span style="font-size: 12px; color: var(--color-text-muted);">—</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: right;">
                                <div style="display: inline-flex; gap: 6px;">
                                    <a href="tel:<?php echo e($lead['phone']); ?>" class="btn btn-sm btn-ghost" title="Direct Phone Call" style="padding: 4px 8px; color: var(--color-primary);">
                                        <i class="fa-solid fa-phone"></i>
                                    </a>
                                    <button type="button" class="btn btn-sm btn-ghost text-success" title="WhatsApp Message" style="padding: 4px 8px; color: var(--color-success); border: none; background: none; cursor: pointer;" onclick="openWhatsAppModal(<?php echo (int)$lead['id']; ?>, '<?php echo addslashes(e($lead['name'])); ?>', '<?php echo e($lead['phone']); ?>', 1, '<?php echo addslashes(e($lead['course_service_name'] ?? 'UI/UX Design')); ?>')">
                                        <i class="fa-brands fa-whatsapp"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-primary" onclick="openUpdateStatusModal(<?php echo (int)$lead['id']; ?>, '<?php echo addslashes(e($lead['name'])); ?>', '<?php echo e($lead['status']); ?>', 'course', <?php echo (int)($lead['course_service_id'] ?? 0); ?>, '<?php echo addslashes(e($lead['course_service_name'] ?? '')); ?>', <?php echo (float)($lead['expected_revenue'] ?? 0); ?>)" style="padding: 4px 10px; font-size: 12px;">
                                        <i class="fa-solid fa-pen-to-square"></i> Status
                                    </button>
                                    <?php if ($lead['status'] === 'enrolled' || $lead['offering_type'] === 'course'): ?>
                                        <button type="button" class="btn btn-sm" onclick="openPaymentModal(<?php echo (int)$lead['id']; ?>)" title="Manage Course Payment & Installments" style="padding: 4px 8px; font-size: 12px; background: rgba(16, 185, 129, 0.15); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.3);">
                                            <i class="fa-solid fa-credit-card"></i> Pay
                                        </button>
                                    <?php endif; ?>
                                    <a href="<?php echo BASE_URL; ?>/sales/leads.php?lead_id=<?php echo (int)$lead['id']; ?>" class="btn btn-sm btn-outline" style="padding: 4px 8px; font-size: 12px;" title="View Details">
                                        <i class="fa-solid fa-eye"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- SECTION 2: SOFTWARE & DIGITAL SERVICES LEADS -->
<div class="data-table-container fade-in" style="background: var(--color-bg-card); border: 1px solid var(--color-border); border-radius: var(--radius-lg); overflow: hidden;">
    <div style="display: flex; justify-content: space-between; align-items: center; padding: var(--space-4) var(--space-5); border-bottom: 1px solid var(--color-border); flex-wrap: wrap; gap: var(--space-3);">
        <div style="display: flex; align-items: center; gap: 10px;">
            <div style="width: 36px; height: 36px; border-radius: var(--radius-md); background: rgba(245, 158, 11, 0.15); color: #f59e0b; display: flex; align-items: center; justify-content: center; font-size: 16px;">
                <i class="fa-solid fa-laptop-code"></i>
            </div>
            <div>
                <h3 style="margin: 0; font-size: 15px; font-weight: 600;">Software & Digital IT Services Leads</h3>
                <p style="margin: 2px 0 0 0; font-size: 12px; color: var(--color-text-secondary);">B2B client proposals, custom web/app projects, and consulting deals</p>
            </div>
        </div>
        <div style="display: flex; align-items: center; gap: var(--space-2);">
            <a href="<?php echo BASE_URL; ?>/sales/leads.php?offering_type=service" class="btn btn-sm btn-outline-warning">
                View All Service Leads (<?php echo $salesMetrics['service_leads']; ?>) <i class="fa-solid fa-arrow-right" style="font-size: 11px; margin-left: 4px;"></i>
            </a>
        </div>
    </div>

    <?php if (empty($recentServiceLeads)): ?>
        <div style="text-align: center; padding: var(--space-8) var(--space-4); color: var(--color-text-muted);">
            <i class="fa-solid fa-laptop-code" style="font-size: 32px; margin-bottom: var(--space-2); color: var(--color-text-muted);"></i>
            <p style="margin: 0; font-size: 13.5px;">No service inquiries assigned yet.</p>
            <?php if ($canAddLeads): ?>
                <button type="button" class="btn btn-sm btn-primary" onclick="openAddLeadModal('service')" style="margin-top: 10px;">
                    <i class="fa-solid fa-plus"></i> Add Service Inquiry
                </button>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div style="overflow-x: auto;">
            <table class="data-table" style="width: 100%;">
                <thead>
                    <tr>
                        <th>Client / Prospect</th>
                        <th>Service Package</th>
                        <th>Source</th>
                        <th>Status</th>
                        <th>Next Follow-up</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentServiceLeads as $lead): ?>
                        <tr>
                            <td>
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <div style="width: 32px; height: 32px; border-radius: 50%; background: rgba(245, 158, 11, 0.15); color: #f59e0b; font-weight: 700; font-size: 11px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                        <?php echo strtoupper(substr($lead['name'] ?? 'C', 0, 2)); ?>
                                    </div>
                                    <div>
                                        <div style="font-weight: 600; color: var(--color-text-white);"><?php echo e($lead['name']); ?></div>
                                        <div style="font-size: 11.5px; color: var(--color-text-secondary); font-family: monospace; margin-top: 2px;">
                                            <i class="fa-solid fa-phone" style="font-size: 9px; margin-right: 3px;"></i><?php echo e($lead['phone']); ?>
                                            <?php if (!empty($lead['city'])): ?> • <?php echo e($lead['city']); ?><?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div style="font-weight: 500; color: var(--color-text-white);"><?php echo e($lead['course_service_name']); ?></div>
                                <?php if ($lead['expected_revenue'] > 0): ?>
                                    <div style="font-size: 11px; color: var(--color-success);">Value: ₹<?php echo number_format($lead['expected_revenue']); ?></div>
                                <?php endif; ?>
                            </td>
                            <td><span style="font-size: 12px; color: var(--color-text-secondary);"><?php echo e($lead['source']); ?></span></td>
                            <td data-lead-status-id="<?php echo (int)$lead['id']; ?>"><?php echo getLeadStatusBadge($lead['status']); ?></td>
                            <td>
                                <?php if (!empty($lead['next_followup_date'])): ?>
                                    <div style="font-size: 12px; <?php echo (strtotime($lead['next_followup_date']) < time() && $lead['status'] !== 'enrolled') ? 'color: var(--color-danger); font-weight: 600;' : ''; ?>">
                                        <i class="fa-regular fa-clock" style="font-size: 10px;"></i> <?php echo formatDate($lead['next_followup_date'], 'd M, h:i A'); ?>
                                    </div>
                                <?php else: ?>
                                    <span style="font-size: 12px; color: var(--color-text-muted);">—</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: right;">
                                <div style="display: inline-flex; gap: 6px;">
                                    <a href="tel:<?php echo e($lead['phone']); ?>" class="btn btn-sm btn-ghost" title="Direct Phone Call" style="padding: 4px 8px; color: var(--color-primary);">
                                        <i class="fa-solid fa-phone"></i>
                                    </a>
                                    <button type="button" class="btn btn-sm btn-ghost text-success" title="WhatsApp Message" style="padding: 4px 8px; color: var(--color-success); border: none; background: none; cursor: pointer;" onclick="openWhatsAppModal(<?php echo (int)$lead['id']; ?>, '<?php echo addslashes(e($lead['name'])); ?>', '<?php echo e($lead['phone']); ?>', 1, '<?php echo addslashes(e($lead['course_service_name'] ?? 'UI/UX Design')); ?>')">
                                        <i class="fa-brands fa-whatsapp"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-primary" onclick="openUpdateStatusModal(<?php echo (int)$lead['id']; ?>, '<?php echo addslashes(e($lead['name'])); ?>', '<?php echo e($lead['status']); ?>', 'service', <?php echo (int)($lead['course_service_id'] ?? 0); ?>, '<?php echo addslashes(e($lead['course_service_name'] ?? '')); ?>', <?php echo (float)($lead['expected_revenue'] ?? 0); ?>)" style="padding: 4px 10px; font-size: 12px;">
                                        <i class="fa-solid fa-pen-to-square"></i> Status
                                    </button>
                                    <a href="<?php echo BASE_URL; ?>/sales/leads.php?lead_id=<?php echo (int)$lead['id']; ?>" class="btn btn-sm btn-outline" style="padding: 4px 8px; font-size: 12px;" title="View Details">
                                        <i class="fa-solid fa-eye"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- SECTION 3: RAW / MANUAL CALLING PROSPECTS BANK -->
<div class="card fade-in" style="padding: var(--space-5); margin-bottom: var(--space-6); border: 1px solid rgba(2, 132, 199, 0.3);">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--space-4); border-bottom: 1px solid var(--color-border); padding-bottom: var(--space-3); flex-wrap: wrap; gap: var(--space-2);">
        <div style="display: flex; align-items: center; gap: 10px;">
            <div style="width: 36px; height: 36px; border-radius: var(--radius-md); background: rgba(2, 132, 199, 0.15); color: #0284c7; display: flex; align-items: center; justify-content: center; font-size: 16px;">
                <i class="fa-solid fa-address-book"></i>
            </div>
            <div>
                <h3 style="margin: 0; font-size: 15px; font-weight: 600;">Manual & Cold Calling Prospects Bank</h3>
                <p style="margin: 2px 0 0 0; font-size: 12px; color: var(--color-text-secondary);">Manual candidate entries, college datasets, and cold calling lists for tele-counseling</p>
            </div>
        </div>
        <div style="display: flex; align-items: center; gap: var(--space-2);">
            <a href="<?php echo BASE_URL; ?>/sales/raw_leads.php" class="btn btn-sm" style="background: rgba(2, 132, 199, 0.15); color: #0284c7; border: 1px solid rgba(2, 132, 199, 0.3);">
                View Calling Bank (<?php echo $salesMetrics['manual_leads']; ?>) <i class="fa-solid fa-arrow-right" style="font-size: 11px; margin-left: 4px;"></i>
            </a>
        </div>
    </div>

    <?php if (empty($recentManualLeads)): ?>
        <div style="text-align: center; padding: var(--space-8) var(--space-4); color: var(--color-text-muted);">
            <i class="fa-solid fa-address-book" style="font-size: 32px; margin-bottom: var(--space-2); color: var(--color-text-muted);"></i>
            <p style="margin: 0; font-size: 13.5px;">No raw calling prospects added yet.</p>
            <?php if ($canAddLeads): ?>
                <button type="button" class="btn btn-sm btn-primary" onclick="openAddLeadModal()" style="margin-top: 10px;">
                    <i class="fa-solid fa-plus"></i> Add Manual Data
                </button>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div style="overflow-x: auto;">
            <table class="data-table" style="width: 100%;">
                <thead>
                    <tr>
                        <th>Candidate / Prospect</th>
                        <th>Graduation</th>
                        <th>Source Batch</th>
                        <th>Status</th>
                        <th>Last Contact</th>
                        <th style="text-align: right;">Quick Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentManualLeads as $lead): 
                        $leadId = (int)$lead['id'];
                    ?>
                        <tr>
                            <td>
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <div style="width: 32px; height: 32px; border-radius: 50%; background: rgba(2, 132, 199, 0.15); color: #0284c7; font-weight: 700; font-size: 11px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                        <?php echo strtoupper(substr($lead['name'] ?? 'M', 0, 2)); ?>
                                    </div>
                                    <div>
                                        <div style="font-weight: 600; color: var(--color-text-white);"><?php echo e($lead['name']); ?></div>
                                        <div style="font-size: 11.5px; color: var(--color-text-secondary); font-family: monospace; margin-top: 2px;">
                                            <i class="fa-solid fa-phone" style="font-size: 9px; margin-right: 3px;"></i><?php echo e($lead['phone']); ?>
                                            <?php if (!empty($lead['email'])): ?> • <?php echo e($lead['email']); ?><?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <?php if (!empty($lead['graduation'])): ?>
                                    <span class="badge" style="font-size: 11px; padding: 2px 7px; background: rgba(2, 132, 199, 0.15); color: #0284c7; border: 1px solid rgba(2, 132, 199, 0.3);">
                                        <i class="fa-solid fa-user-graduate" style="font-size: 10px; margin-right: 3px;"></i><?php echo e($lead['graduation']); ?>
                                    </span>
                                <?php else: ?>
                                    <span style="font-size: 11px; color: var(--color-text-muted);">—</span>
                                <?php endif; ?>
                            </td>
                            <td><span style="font-size: 12px; color: var(--color-text-secondary);"><?php echo e($lead['source']); ?></span></td>
                            <td data-lead-status-id="<?php echo (int)$lead['id']; ?>"><?php echo getLeadStatusBadge($lead['status']); ?></td>
                            <td>
                                <?php if (!empty($lead['last_contact_at'])): ?>
                                    <div style="font-size: 11.5px; color: var(--color-text-secondary);">
                                        <i class="fa-regular fa-clock" style="font-size: 10px;"></i> <?php echo timeAgo($lead['last_contact_at']); ?>
                                    </div>
                                <?php else: ?>
                                    <span style="font-size: 11.5px; color: #f59e0b; font-weight: 600;">Fresh / Uncalled</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: right;">
                                <div style="display: inline-flex; gap: 6px;">
                                    <a href="tel:<?php echo e($lead['phone']); ?>" class="btn btn-sm btn-ghost" title="Direct Phone Call" style="padding: 4px 8px; color: var(--color-primary);">
                                        <i class="fa-solid fa-phone"></i>
                                    </a>
                                    <button type="button" class="btn btn-sm btn-ghost text-success" title="WhatsApp Message" style="padding: 4px 8px; color: var(--color-success); border: none; background: none; cursor: pointer;" onclick="openWhatsAppModal(<?php echo (int)$lead['id']; ?>, '<?php echo addslashes(e($lead['name'])); ?>', '<?php echo e($lead['phone']); ?>', 2, '<?php echo addslashes(e($lead['course_service_name'] ?? 'UI/UX Design')); ?>')">
                                        <i class="fa-brands fa-whatsapp"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-primary" onclick="openUpdateStatusModal(<?php echo (int)$lead['id']; ?>, '<?php echo addslashes(e($lead['name'])); ?>', '<?php echo e($lead['status']); ?>', '<?php echo e($lead['offering_type'] ?? 'course'); ?>', <?php echo (int)($lead['course_service_id'] ?? 0); ?>, '<?php echo addslashes(e($lead['course_service_name'] ?? '')); ?>', <?php echo (float)($lead['expected_revenue'] ?? 0); ?>)" style="padding: 4px 10px; font-size: 12px;">
                                        <i class="fa-solid fa-pen-to-square"></i> Status
                                    </button>
                                    <a href="<?php echo BASE_URL; ?>/sales/raw_leads.php?lead_id=<?php echo (int)$lead['id']; ?>" class="btn btn-sm btn-outline" style="padding: 4px 8px; font-size: 12px;" title="View Details">
                                        <i class="fa-solid fa-eye"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- ================= MODALS ================= -->

<?php if ($canAddLeads): ?>
<!-- 1. ADD NEW LEAD / RAW DATA MODAL -->
<div class="modal-overlay" id="addLeadModal">
    <div class="modal" style="max-width: 620px;">
        <div class="modal-header">
            <h3 class="modal-title"><i class="fa-solid fa-user-plus" style="color: var(--color-primary); margin-right: 6px;"></i> Add Prospect / Raw Calling Data</h3>
            <button type="button" class="modal-close" onclick="closeAddLeadModal()">×</button>
        </div>

        <!-- Mode Switcher Tabs (Single Entry vs Bulk Paste) -->
        <div style="display: flex; gap: 8px; padding: 12px 24px 0 24px; border-bottom: 1px solid var(--color-border); background: var(--color-bg-secondary);">
            <button type="button" id="tabSingleBtn" class="btn btn-sm btn-primary" onclick="switchAddLeadTab('single')" style="border-bottom-left-radius: 0; border-bottom-right-radius: 0;">
                <i class="fa-solid fa-user"></i> Single Candidate Entry
            </button>
            <button type="button" id="tabBulkBtn" class="btn btn-sm btn-ghost" onclick="switchAddLeadTab('bulk')" style="border-bottom-left-radius: 0; border-bottom-right-radius: 0;">
                <i class="fa-solid fa-file-import"></i> Bulk Paste Calling List
            </button>
        </div>

        <!-- TAB 1: SINGLE LEAD ENTRY -->
        <div id="tabSingleContent">
            <form id="addLeadForm" onsubmit="submitAddLead(event)">
                <input type="hidden" name="action" value="add_lead">
                <?php echo csrfField(); ?>

                <div class="modal-body">
                    <div style="padding: 8px 12px; background: rgba(99, 102, 241, 0.1); border-radius: var(--radius-sm); border: 1px solid rgba(99, 102, 241, 0.2); font-size: 12px; color: var(--color-primary-light); margin-bottom: var(--space-4);">
                        <i class="fa-solid fa-info-circle"></i> Add candidate details to immediately start calling, WhatsApp outreach, and course counselling.
                    </div>

                    <div class="form-row" style="margin-bottom: var(--space-4);">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label">Candidate / Prospect Name *</label>
                            <input type="text" name="name" class="form-input" required placeholder="e.g. Rahul Sharma">
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label">Graduation / Degree *</label>
                            <input type="text" name="graduation" class="form-input" placeholder="e.g. B.Tech CS / BCA / MCA / Graduate">
                        </div>
                    </div>

                    <div class="form-row" style="margin-bottom: var(--space-4);">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label">Contact No (Phone / WhatsApp) *</label>
                            <input type="tel" name="phone" class="form-input" required placeholder="e.g. 9876543210">
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label">Email ID</label>
                            <input type="email" name="email" class="form-input" placeholder="e.g. rahul@example.com">
                        </div>
                    </div>

                    <div class="form-row" style="margin-bottom: var(--space-4);">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label">Category *</label>
                            <select name="offering_type" id="leadOfferingType" class="form-select" onchange="syncOfferingDropdown()">
                                <option value="course">Training Courses (Student)</option>
                                <option value="service">IT Services (Client)</option>
                            </select>
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label">Target Program / Interest</label>
                            <select name="course_service_select" id="leadOfferingSelect" class="form-select" onchange="syncOfferingName(this)">
                                <option value="">-- General / To be Counseled --</option>
                                <?php foreach ($coursesList as $c): ?>
                                    <option value="<?php echo (int)$c['id']; ?>" data-name="<?php echo e($c['name']); ?>" data-price="<?php echo (float)$c['price']; ?>">
                                        <?php echo e($c['name']); ?> (₹<?php echo number_format($c['price']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <input type="hidden" name="course_service_id" id="leadCsId" value="">
                            <input type="hidden" name="course_service_name" id="leadCsName" value="General Inquiry">
                        </div>
                    </div>

                    <div class="form-row" style="margin-bottom: var(--space-4);">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label">Lead Source</label>
                            <select name="source" class="form-select">
                                <option value="Raw Calling Data">Raw Calling List</option>
                                <option value="College Database">College / Campus Database</option>
                                <option value="Cold Outreach">Cold Calling Outreach</option>
                                <option value="WhatsApp">WhatsApp Inflow</option>
                                <option value="Website">Website Inflow</option>
                                <option value="Social Media">Instagram / Facebook Ads</option>
                                <option value="Referral">Student Referral</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label">City / Location</label>
                            <input type="text" name="city" class="form-input" placeholder="e.g. Pune, Mumbai, Nagpur">
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">Caller Remarks / Initial Notes</label>
                        <textarea name="notes" class="form-textarea" rows="2" placeholder="Passout year, background, batch timing preference, call remarks..."></textarea>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeAddLeadModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="addLeadBtn">
                        <i class="fa-solid fa-plus"></i> Save & Add to Pipeline
                    </button>
                </div>
            </form>
        </div>

        <!-- TAB 2: BULK RAW DATA PASTE -->
        <div id="tabBulkContent" style="display: none;">
            <form id="bulkImportForm" onsubmit="submitBulkImport(event)">
                <input type="hidden" name="action" value="import_raw_leads">
                <?php echo csrfField(); ?>

                <div class="modal-body">
                    <div style="padding: 10px 14px; background: rgba(16, 185, 129, 0.1); border-radius: var(--radius-sm); border: 1px solid rgba(16, 185, 129, 0.2); font-size: 12px; color: #10b981; margin-bottom: var(--space-4);">
                        <strong style="display: block; margin-bottom: 4px;"><i class="fa-solid fa-bolt"></i> Quick Batch Import:</strong>
                        Paste multiple lines in format: <code>Name, Graduation, Phone, Email</code> (one prospect per line).
                    </div>

                    <!-- 3-Way Category Selection (Course vs Service vs Smart Auto-Filter) -->
                    <div class="form-group" style="margin-bottom: var(--space-3);">
                        <label class="form-label required">Target Category / Pipeline Filter</label>
                        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: var(--space-2);">
                            <label style="border: 2px solid var(--color-primary); padding: 8px 10px; border-radius: var(--radius-md); display: flex; align-items: center; gap: 6px; cursor: pointer; background: rgba(99, 102, 241, 0.08);">
                                <input type="radio" name="offering_type" value="course" checked>
                                <div>
                                    <div style="font-weight: 700; font-size: 12px;"><i class="fa-solid fa-graduation-cap" style="color: var(--color-primary);"></i> Courses</div>
                                    <div style="font-size: 10px; color: var(--color-text-secondary);">Student CRM</div>
                                </div>
                            </label>
                            <label style="border: 2px solid #f59e0b; padding: 8px 10px; border-radius: var(--radius-md); display: flex; align-items: center; gap: 6px; cursor: pointer; background: rgba(245, 158, 11, 0.08);">
                                <input type="radio" name="offering_type" value="service">
                                <div>
                                    <div style="font-weight: 700; font-size: 12px;"><i class="fa-solid fa-laptop-code" style="color: #f59e0b;"></i> IT Services</div>
                                    <div style="font-size: 10px; color: var(--color-text-secondary);">Client CRM</div>
                                </div>
                            </label>
                            <label style="border: 2px solid #10b981; padding: 8px 10px; border-radius: var(--radius-md); display: flex; align-items: center; gap: 6px; cursor: pointer; background: rgba(16, 185, 129, 0.08);">
                                <input type="radio" name="offering_type" value="auto_detect">
                                <div>
                                    <div style="font-weight: 700; font-size: 12px; color: #059669;"><i class="fa-solid fa-wand-magic-sparkles"></i> Auto-Filter</div>
                                    <div style="font-size: 10px; color: var(--color-text-secondary);">Smart 2-Way</div>
                                </div>
                            </label>
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom: var(--space-3);">
                        <label class="form-label">Default Source</label>
                        <input type="text" name="source" class="form-input" value="Raw Calling List">
                    </div>

                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">Paste Raw Data *</label>
                        <textarea name="raw_data" id="bulkRawData" class="form-textarea" rows="6" required style="font-family: monospace; font-size: 12px;" placeholder="Rahul Sharma, B.Tech CS, 9876543210, rahul@gmail.com&#10;Priya Nair, BCA, 9890123456, priya@gmail.com&#10;Amit Patel, MCA, 9823456789, amit@gmail.com"></textarea>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeAddLeadModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="bulkImportBtn">
                        <i class="fa-solid fa-file-import"></i> Import Raw Leads
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- 2. UPDATE STATUS MODAL -->
<div class="modal-overlay" id="updateStatusModal">
    <div class="modal" style="max-width: 480px;">
        <div class="modal-header">
            <h3 class="modal-title">
                Update Status: <span id="modalLeadName" style="color: var(--color-primary);"></span>
            </h3>
            <button type="button" class="modal-close" onclick="closeUpdateStatusModal()">×</button>
        </div>

        <form id="updateStatusForm" onsubmit="submitUpdateStatus(event)">
            <input type="hidden" name="action" value="update_status">
            <input type="hidden" name="lead_id" id="statusModalLeadId" value="">
            <?php echo csrfField(); ?>

            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Lead Status *</label>
                    <select name="status" id="statusModalSelect" class="form-select" onchange="toggleStatusEnrollmentPaymentSection()" required>
                        <option value="new_lead">New Lead (Not Contacted / In Queue)</option>
                        <option value="interested">Interested (In Discussion / Demo Scheduled)</option>
                        <option value="enrolled">Enrolled (Paid / Converted)</option>
                        <option value="uninterested">Uninterested (Lost / Dropped)</option>
                    </select>
                </div>

                <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-3);">
                    <div class="form-group">
                        <label class="form-label">Type</label>
                        <select name="offering_type" id="statusOfferingType" class="form-select" onchange="syncStatusOfferingDropdown()">
                            <option value="course">🎓 Course</option>
                            <option value="service">💼 Service</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Program / Service</label>
                        <select name="course_service_id" id="statusCourseServiceId" class="form-select" onchange="syncStatusOfferingName()">
                            <option value="">-- Select --</option>
                        </select>
                        <input type="hidden" name="course_service_name" id="statusCourseServiceName" value="">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Deal Amount / Course Fee (₹)</label>
                    <div style="position: relative;">
                        <span style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--color-text-muted); font-weight: 600;">₹</span>
                        <input type="number" step="any" min="0" name="expected_revenue" id="statusExpectedRevenue" class="form-input" placeholder="0.00" style="padding-left: 28px;" oninput="renderStatusInstallmentInputs()">
                    </div>
                    <small style="font-size: 11px; color: var(--color-text-muted);">Auto-fills from catalogue price or customize as needed</small>
                </div>

                <!-- Enrolled Course / Service Payment Plan Setup -->
                <div id="statusEnrollmentPaymentSection" style="display: none; background: rgba(16, 185, 129, 0.08); border: 1px solid rgba(16, 185, 129, 0.3); border-radius: var(--radius-md); padding: 14px; margin-bottom: var(--space-3);">
                    <div style="font-weight: 700; font-size: 12.5px; color: #10b981; margin-bottom: 8px; display: flex; align-items: center; justify-content: space-between;">
                        <span id="statusPayPlanHeaderTitle"><i class="fa-solid fa-credit-card"></i> Payment Plan & Installments</span>
                        <label style="font-size: 11px; font-weight: normal; color: var(--color-text-secondary); cursor: pointer; display: flex; align-items: center; gap: 4px;">
                            <input type="checkbox" name="setup_payment_plan" id="statusSetupPayPlanCheck" value="1" checked onchange="toggleStatusPayPlanDetails()"> Setup Plan
                        </label>
                    </div>

                    <div id="statusPayPlanDetails">
                        <!-- Course Plan Options -->
                        <div id="statusCoursePlanSelectWrapper" style="margin-bottom: 12px;">
                            <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 10px;">
                                <div>
                                    <label class="form-label" style="font-size: 11.5px; font-weight: 600;">Plan Type</label>
                                    <select name="payment_plan_type" id="statusPayPlanType" class="form-select" onchange="renderStatusInstallmentInputs()">
                                        <option value="one_time">⚡ 1-Time Full Payment</option>
                                        <option value="installment" selected>📅 Installments (Up to 3 Parts)</option>
                                    </select>
                                </div>
                                <div id="statusNumInstWrapper">
                                    <label class="form-label" style="font-size: 11.5px; font-weight: 600;">No. of Installments</label>
                                    <select id="statusNumInstallments" class="form-select" onchange="renderStatusInstallmentInputs()">
                                        <option value="2">2 Installments</option>
                                        <option value="3" selected>3 Installments</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Collected Amount & Calculation Section -->
                            <div id="statusCollectedBox" style="background: rgba(16, 185, 129, 0.12); border: 1px solid rgba(16, 185, 129, 0.35); border-radius: var(--radius-md); padding: 10px 12px; margin-bottom: 10px;">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                                    <label style="font-size: 11.5px; font-weight: 700; color: #10b981; display: flex; align-items: center; gap: 5px; margin-bottom: 0;">
                                        <i class="fa-solid fa-hand-holding-dollar"></i> Collected Amount (₹)
                                    </label>
                                    <span id="statusRemainingBalanceBadge" style="font-size: 11px; font-weight: 700; color: #f59e0b; background: rgba(245, 158, 11, 0.12); padding: 2px 8px; border-radius: 12px; border: 1px solid rgba(245, 158, 11, 0.3);">
                                        Remaining: ₹0
                                    </span>
                                </div>
                                <div style="display: flex; gap: 8px; align-items: center;">
                                    <div style="position: relative; flex: 1;">
                                        <span style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: var(--color-text-muted); font-size: 12px; font-weight: 600;">₹</span>
                                        <input type="number" step="any" min="0" id="statusCollectedAmount" class="form-input" placeholder="Enter collected amount" style="padding-left: 24px; font-size: 12px; font-weight: 700;" oninput="renderStatusInstallmentInputs()">
                                    </div>
                                    <button type="button" class="btn btn-sm btn-primary" onclick="renderStatusInstallmentInputs()" style="font-size: 11.5px; padding: 6px 12px; white-space: nowrap; display: flex; align-items: center; gap: 5px; cursor: pointer;">
                                        <i class="fa-solid fa-calculator"></i> Calculate
                                    </button>
                                </div>
                                <div style="display: flex; gap: 5px; margin-top: 6px; flex-wrap: wrap; align-items: center;">
                                    <span style="font-size: 10.5px; color: var(--color-text-muted);">Quick Split:</span>
                                    <button type="button" class="btn btn-xs btn-secondary" onclick="setStatusCollectedPreset('full')" style="font-size: 10px; padding: 2px 7px;">Full 100%</button>
                                    <button type="button" class="btn btn-xs btn-secondary" onclick="setStatusCollectedPreset('half')" style="font-size: 10px; padding: 2px 7px;">50%</button>
                                    <button type="button" class="btn btn-xs btn-secondary" onclick="setStatusCollectedPreset(15000)" style="font-size: 10px; padding: 2px 7px;">₹15,000</button>
                                    <button type="button" class="btn btn-xs btn-secondary" onclick="setStatusCollectedPreset(10000)" style="font-size: 10px; padding: 2px 7px;">₹10,000</button>
                                    <button type="button" class="btn btn-xs btn-secondary" onclick="setStatusCollectedPreset(5000)" style="font-size: 10px; padding: 2px 7px;">₹5,000</button>
                                </div>
                            </div>
                        </div>

                        <!-- Service Scope-based Presets (Only for Services) -->
                        <div id="statusServiceMilestonePresets" style="display: none; margin-bottom: 10px;">
                            <div style="font-size: 11px; color: var(--color-text-muted); margin-bottom: 4px;">Scope-Based Milestone Splits:</div>
                            <div style="display: flex; gap: 6px; flex-wrap: wrap;">
                                <button type="button" class="btn btn-sm btn-secondary" onclick="applyDashStatusServicePreset(1)" style="font-size: 11px; padding: 2px 8px;">1-Time (100%)</button>
                                <button type="button" class="btn btn-sm btn-secondary" onclick="applyDashStatusServicePreset(2)" style="font-size: 11px; padding: 2px 8px;">2 Milestones (50/50)</button>
                                <button type="button" class="btn btn-sm btn-secondary" onclick="applyDashStatusServicePreset(3)" style="font-size: 11px; padding: 2px 8px;">3 Milestones (40/30/30)</button>
                                <button type="button" class="btn btn-sm btn-secondary" onclick="applyDashStatusServicePreset(4)" style="font-size: 11px; padding: 2px 8px;">4 Milestones (30/30/30/10)</button>
                            </div>
                        </div>

                        <!-- Dynamic Installment / Milestone Rows Container -->
                        <div id="statusInstallmentsContainer" style="display: flex; flex-direction: column; gap: 8px; margin-bottom: 10px;">
                            <!-- Injected by JS -->
                        </div>

                        <div id="statusAddMilestoneBtnWrapper" style="display: none; text-align: right; margin-bottom: 8px;">
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="addDashStatusServiceMilestoneRow()" style="font-size: 11.5px; padding: 3px 8px;">
                                <i class="fa-solid fa-plus-circle"></i> Add Custom Milestone
                            </button>
                        </div>

                        <input type="hidden" name="installments_json" id="statusInstallmentsJson" value="">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Next Follow-up Date & Time</label>
                    <input type="datetime-local" name="next_followup_date" id="statusModalFollowup" class="form-input">
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Call Discussion / Activity Remarks *</label>
                    <textarea name="remarks" id="statusModalRemarks" class="form-textarea" rows="3" required placeholder="What was discussed in this call/WhatsApp message?"></textarea>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeUpdateStatusModal()">Cancel</button>
                <button type="submit" class="btn btn-primary" id="updateStatusBtn">Save Update</button>
            </div>
        </form>
    </div>
</div>

<script>
const coursesList = <?php echo json_encode($coursesList); ?>;
const servicesList = <?php echo json_encode($servicesList); ?>;

function openAddLeadModal(defaultType = 'course', tab = 'single') {
    const modal = document.getElementById('addLeadModal');
    if (modal) {
        if (modal.parentElement && modal.parentElement !== document.body) {
            document.body.appendChild(modal);
        }
        const offeringSelect = document.getElementById('leadOfferingType');
        if (offeringSelect) offeringSelect.value = defaultType;
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
        switchAddLeadTab(tab);
        syncOfferingDropdown();
    }
}

function closeAddLeadModal() {
    const modal = document.getElementById('addLeadModal');
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = '';
    }
}

function switchAddLeadTab(tab) {
    if (tab === 'single') {
        document.getElementById('tabSingleContent').style.display = 'block';
        document.getElementById('tabBulkContent').style.display = 'none';
        document.getElementById('tabSingleBtn').className = 'btn btn-sm btn-primary';
        document.getElementById('tabBulkBtn').className = 'btn btn-sm btn-ghost';
    } else {
        document.getElementById('tabSingleContent').style.display = 'none';
        document.getElementById('tabBulkContent').style.display = 'block';
        document.getElementById('tabSingleBtn').className = 'btn btn-sm btn-ghost';
        document.getElementById('tabBulkBtn').className = 'btn btn-sm btn-primary';
    }
}

function syncOfferingDropdown() {
    const type = document.getElementById('leadOfferingType').value;
    const select = document.getElementById('leadOfferingSelect');
    select.innerHTML = '<option value="">-- General / To be Counseled --</option>';
    
    const list = (type === 'course') ? coursesList : servicesList;
    list.forEach(item => {
        const opt = document.createElement('option');
        opt.value = item.id;
        opt.textContent = item.name + ' (₹' + Number(item.price).toLocaleString() + ')';
        opt.dataset.name = item.name;
        opt.dataset.price = item.price;
        select.appendChild(opt);
    });
}

function syncOfferingName(selectEl) {
    const selectedOption = selectEl.options[selectEl.selectedIndex];
    if (selectedOption && selectedOption.dataset.name) {
        document.getElementById('leadCsId').value = selectedOption.value;
        document.getElementById('leadCsName').value = selectedOption.dataset.name;
    } else {
        document.getElementById('leadCsId').value = '';
        document.getElementById('leadCsName').value = 'General Inquiry';
    }
}

function submitBulkImport(e) {
    e.preventDefault();
    const btn = document.getElementById('bulkImportBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Importing...';

    const form = document.getElementById('bulkImportForm');
    const formData = new FormData(form);

    fetch('<?php echo BASE_URL; ?>/api/leads.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            window.location.reload();
        } else {
            alert(data.message || 'Error importing raw leads.');
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-file-import"></i> Import Raw Leads';
        }
    })
    .catch(err => {
        alert('Request failed.');
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-file-import"></i> Import Raw Leads';
    });
}

function syncStatusOfferingDropdown(selectedId = null) {
    const type = document.getElementById('statusOfferingType').value;
    const select = document.getElementById('statusCourseServiceId');
    select.innerHTML = '<option value="">-- Select Offering --</option>';

    const items = type === 'course' ? coursesList : servicesList;
    items.forEach(item => {
        const opt = document.createElement('option');
        opt.value = item.id;
        opt.textContent = `${item.name} (${type === 'course' ? item.code : (item.category || 'Service')}) - ₹${parseFloat(item.price).toLocaleString('en-IN')}`;
        opt.dataset.name = item.name;
        opt.dataset.price = item.price;
        if (selectedId && parseInt(item.id) === parseInt(selectedId)) {
            opt.selected = true;
        }
        select.appendChild(opt);
    });

    syncStatusOfferingName();
}

function syncStatusOfferingName() {
    const select = document.getElementById('statusCourseServiceId');
    const nameInput = document.getElementById('statusCourseServiceName');
    const revInput = document.getElementById('statusExpectedRevenue');
    const selectedOption = select.options[select.selectedIndex];

    if (selectedOption && selectedOption.dataset.name) {
        nameInput.value = selectedOption.dataset.name;
        if (selectedOption.dataset.price && (!revInput.value || revInput.value == '0' || revInput.dataset.manual !== 'true')) {
            revInput.value = selectedOption.dataset.price;
        }
    } else {
        nameInput.value = '';
    }
}

function toggleStatusEnrollmentPaymentSection() {
    const status = document.getElementById('statusModalSelect').value;
    const section = document.getElementById('statusEnrollmentPaymentSection');
    if (!section) return;

    if (status === 'enrolled') {
        section.style.display = 'block';
        renderStatusInstallmentInputs();
    } else {
        section.style.display = 'none';
    }
}

function toggleStatusPayPlanDetails() {
    const check = document.getElementById('statusSetupPayPlanCheck');
    const details = document.getElementById('statusPayPlanDetails');
    if (details) {
        details.style.display = check.checked ? 'block' : 'none';
    }
}

function setStatusCollectedPreset(preset) {
    const totalFee = parseFloat(document.getElementById('statusExpectedRevenue')?.value) || 0;
    const input = document.getElementById('statusCollectedAmount');
    if (!input) return;

    if (preset === 'full') {
        input.value = totalFee > 0 ? totalFee : '';
    } else if (preset === 'half') {
        input.value = totalFee > 0 ? Math.round(totalFee / 2) : '';
    } else {
        const amt = parseFloat(preset) || 0;
        input.value = (totalFee > 0 && amt > totalFee) ? totalFee : amt;
    }
    renderStatusInstallmentInputs();
}

let dashStatusServiceMilestones = [];

function renderStatusInstallmentInputs() {
    const container = document.getElementById('statusInstallmentsContainer');
    const offeringType = document.getElementById('statusOfferingType')?.value || 'course';
    const isService = (offeringType === 'service');
    const totalFee = parseFloat(document.getElementById('statusExpectedRevenue')?.value) || 0;

    const courseSelectWrapper = document.getElementById('statusCoursePlanSelectWrapper');
    const servicePresetsWrapper = document.getElementById('statusServiceMilestonePresets');
    const addMilestoneBtnWrapper = document.getElementById('statusAddMilestoneBtnWrapper');
    const headerTitle = document.getElementById('statusPayPlanHeaderTitle');

    if (!container) return;

    if (isService) {
        if (headerTitle) headerTitle.innerHTML = '<i class="fa-solid fa-diagram-project"></i> Project Scope-Based Milestones';
        if (courseSelectWrapper) courseSelectWrapper.style.display = 'none';
        if (servicePresetsWrapper) servicePresetsWrapper.style.display = 'block';
        if (addMilestoneBtnWrapper) addMilestoneBtnWrapper.style.display = 'block';

        if (dashStatusServiceMilestones.length === 0) {
            applyDashStatusServicePreset(3);
            return;
        }

        let html = '';
        dashStatusServiceMilestones.forEach((m, idx) => {
            const num = idx + 1;
            html += `
                <div class="dash-status-ms-row" style="padding: 8px 10px; background: var(--color-bg-card); border: 1px solid var(--color-border); border-radius: var(--radius-md);">
                    <div style="display: grid; grid-template-columns: 2fr 1fr 1fr 1fr auto; gap: 6px; align-items: center;">
                        <div>
                            <label style="font-size: 10px; color: var(--color-text-muted); display: block;">Milestone ${num} Title *</label>
                            <input type="text" class="form-input dash-ms-title" value="${m.title}" style="font-size: 11px; padding: 4px 6px;" required>
                        </div>
                        <div>
                            <label style="font-size: 10px; color: var(--color-text-muted); display: block;">Amount (₹) *</label>
                            <input type="number" step="any" min="0" class="form-input dash-ms-amt" value="${m.amount}" style="font-size: 11px; padding: 4px 6px;" required>
                        </div>
                        <div>
                            <label style="font-size: 10px; color: var(--color-text-muted); display: block;">Target Date</label>
                            <input type="date" class="form-input dash-ms-due" value="${m.due_date}" style="font-size: 11px; padding: 4px 6px;">
                        </div>
                        <div>
                            <label style="font-size: 10px; color: var(--color-text-muted); display: block;">Paid Now (₹)</label>
                            <input type="number" step="any" min="0" class="form-input dash-ms-paid" value="${m.paid_amount || 0}" placeholder="0" style="font-size: 11px; padding: 4px 6px;">
                        </div>
                        <div style="padding-top: 14px;">
                            <button type="button" class="btn btn-sm btn-ghost text-danger" onclick="removeDashStatusServiceMilestoneRow(this)" style="padding: 4px 6px;">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            `;
        });
        container.innerHTML = html;
    } else {
        if (headerTitle) headerTitle.innerHTML = '<i class="fa-solid fa-credit-card"></i> Student Course Payment Plan';
        if (courseSelectWrapper) courseSelectWrapper.style.display = 'block';
        if (servicePresetsWrapper) servicePresetsWrapper.style.display = 'none';
        if (addMilestoneBtnWrapper) addMilestoneBtnWrapper.style.display = 'none';

        const planType = document.getElementById('statusPayPlanType')?.value || 'one_time';
        const numInstWrapper = document.getElementById('statusNumInstWrapper');
        const collectedInput = document.getElementById('statusCollectedAmount');
        const remainingBadge = document.getElementById('statusRemainingBalanceBadge');

        // Check user-entered collected amount
        let collectedVal = parseFloat(collectedInput?.value);
        if (isNaN(collectedVal)) {
            // Default initial collected if empty
            if (planType === 'one_time') {
                collectedVal = totalFee;
            } else {
                if (totalFee >= 35000) {
                    collectedVal = 15000;
                } else if (totalFee > 0) {
                    collectedVal = Math.round(totalFee / 2);
                } else {
                    collectedVal = 0;
                }
            }
            if (collectedInput && totalFee > 0) {
                collectedInput.value = collectedVal;
            }
        }

        const remainingBal = Math.max(0, totalFee - (collectedVal || 0));
        if (remainingBadge) {
            remainingBadge.textContent = `Remaining: ₹${remainingBal.toLocaleString('en-IN')}`;
            if (remainingBal === 0) {
                remainingBadge.style.color = '#10b981';
                remainingBadge.style.background = 'rgba(16, 185, 129, 0.12)';
                remainingBadge.style.borderColor = 'rgba(16, 185, 129, 0.3)';
            } else {
                remainingBadge.style.color = '#f59e0b';
                remainingBadge.style.background = 'rgba(245, 158, 11, 0.12)';
                remainingBadge.style.borderColor = 'rgba(245, 158, 11, 0.3)';
            }
        }

        if (planType === 'one_time') {
            if (numInstWrapper) numInstWrapper.style.display = 'none';
            const initialPaid = (!isNaN(collectedVal) && collectedVal > 0) ? Math.min(totalFee, collectedVal) : totalFee;
            container.innerHTML = `
                <div style="padding: 10px 12px; background: var(--color-bg-card); border: 1px solid var(--color-border); border-radius: var(--radius-md);">
                    <div style="font-size: 11.5px; font-weight: 600; color: var(--color-text-white); margin-bottom: 6px;">⚡ 1-Time Full Payment (Fee: ₹${totalFee.toLocaleString('en-IN')})</div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 8px;">
                        <div>
                            <label style="font-size: 10.5px; color: var(--color-text-muted); display: block; margin-bottom: 2px;">Initial Paid Amount (₹)</label>
                            <input type="number" step="any" min="0" id="dashPlanInstPaid_1" class="form-input" style="font-size: 11.5px; padding: 4px 8px;" placeholder="0.00" value="${initialPaid > 0 ? initialPaid : ''}">
                        </div>
                        <div>
                            <label style="font-size: 10.5px; color: var(--color-text-muted); display: block; margin-bottom: 2px;">Payment Method</label>
                            <select id="dashPlanInstMethod_1" class="form-select" style="font-size: 11.5px; padding: 4px 8px;">
                                <option value="upi">UPI / GPay / PhonePe</option>
                                <option value="cash">Cash</option>
                                <option value="bank_transfer">Bank Transfer / NEFT</option>
                                <option value="card">Card / POS</option>
                                <option value="cheque">Cheque</option>
                            </select>
                        </div>
                        <div>
                            <label style="font-size: 10.5px; color: var(--color-text-muted); display: block; margin-bottom: 2px;">Txn / UTR Ref</label>
                            <input type="text" id="dashPlanInstTxn_1" class="form-input" style="font-size: 11.5px; padding: 4px 8px;" placeholder="UTR # (optional)">
                        </div>
                    </div>
                </div>
            `;
        } else {
            if (numInstWrapper) numInstWrapper.style.display = 'block';
            const numInst = parseInt(document.getElementById('statusNumInstallments')?.value) || 3;
            
            // Calculate split based on collected amount
            const inst1Amt = (collectedVal > 0) ? Math.min(totalFee, collectedVal) : (numInst === 2 ? Math.round(totalFee / 2) : Math.round(totalFee / 3));
            const balAfterInst1 = Math.max(0, totalFee - inst1Amt);

            let splitAmts = [];
            if (numInst === 2) {
                splitAmts = [inst1Amt, balAfterInst1];
            } else { // 3 installments
                const inst2Amt = Math.round(balAfterInst1 / 2);
                const inst3Amt = Math.max(0, balAfterInst1 - inst2Amt);
                splitAmts = [inst1Amt, inst2Amt, inst3Amt];
            }

            let html = '';
            const today = new Date();
            for (let i = 1; i <= numInst; i++) {
                const dueD = new Date();
                dueD.setDate(today.getDate() + (i - 1) * 30);
                const dueStr = dueD.toISOString().split('T')[0];
                const title = (i === 1) ? '1st Installment (At Admission)' : (i === 2 ? '2nd Installment (Month 2)' : '3rd Installment (Month 3)');
                const instAmt = splitAmts[i - 1] || 0;
                const paidNowVal = (i === 1) ? instAmt : 0;

                html += `
                    <div style="padding: 10px 12px; background: var(--color-bg-card); border: 1px solid var(--color-border); border-radius: var(--radius-md);">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                            <span style="font-size: 11.5px; font-weight: 600; color: #10b981;"><i class="fa-solid fa-calendar-check"></i> ${title}</span>
                            <span style="font-size: 11px; color: var(--color-text-muted);">Due: ${dueStr}</span>
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr 1.2fr 1fr 1fr; gap: 8px;">
                            <div>
                                <label style="font-size: 10.5px; color: var(--color-text-muted); display: block; margin-bottom: 2px;">Inst Amount (₹)</label>
                                <input type="number" step="any" min="0" id="dashPlanInstAmt_${i}" class="form-input" style="font-size: 11.5px; padding: 4px 8px; font-weight: 600;" value="${instAmt}">
                            </div>
                            <div>
                                <label style="font-size: 10.5px; color: var(--color-text-muted); display: block; margin-bottom: 2px;">Due Date</label>
                                <input type="date" id="dashPlanInstDue_${i}" class="form-input" style="font-size: 11.5px; padding: 4px 8px;" value="${dueStr}">
                            </div>
                            <div>
                                <label style="font-size: 10.5px; color: var(--color-text-muted); display: block; margin-bottom: 2px;">Paid Now (₹)</label>
                                <input type="number" step="any" min="0" id="dashPlanInstPaid_${i}" class="form-input" style="font-size: 11.5px; padding: 4px 8px; font-weight: 600; color: #10b981;" placeholder="0" value="${paidNowVal}">
                            </div>
                            <div>
                                <label style="font-size: 10.5px; color: var(--color-text-muted); display: block; margin-bottom: 2px;">Method</label>
                                <select id="dashPlanInstMethod_${i}" class="form-select" style="font-size: 11.5px; padding: 4px 8px;">
                                    <option value="upi">UPI</option>
                                    <option value="cash">Cash</option>
                                    <option value="bank_transfer">Bank</option>
                                    <option value="card">Card</option>
                                </select>
                            </div>
                        </div>
                    </div>
                `;
            }
            container.innerHTML = html;
        }
    }
}

function applyDashStatusServicePreset(count) {
    const totalFee = parseFloat(document.getElementById('statusExpectedRevenue')?.value) || 50000;
    dashStatusServiceMilestones = [];

    if (count === 1) {
        dashStatusServiceMilestones.push({
            title: '1-Time Full Project Advance',
            amount: totalFee,
            due_date: new Date().toISOString().split('T')[0],
            paid_amount: totalFee
        });
    } else if (count === 2) {
        const half = Math.round(totalFee / 2);
        const d2 = new Date(); d2.setDate(d2.getDate() + 30);
        dashStatusServiceMilestones.push(
            { title: 'Milestone 1: Project Kickoff & Advance (50%)', amount: half, due_date: new Date().toISOString().split('T')[0], paid_amount: half },
            { title: 'Milestone 2: Final Delivery & Handover (50%)', amount: totalFee - half, due_date: d2.toISOString().split('T')[0], paid_amount: 0 }
        );
    } else if (count === 3) {
        const p1 = Math.round(totalFee * 0.40);
        const p2 = Math.round(totalFee * 0.30);
        const p3 = totalFee - p1 - p2;
        const d2 = new Date(); d2.setDate(d2.getDate() + 20);
        const d3 = new Date(); d3.setDate(d3.getDate() + 45);
        dashStatusServiceMilestones.push(
            { title: 'Milestone 1: Project Advance & UI/UX Signoff (40%)', amount: p1, due_date: new Date().toISOString().split('T')[0], paid_amount: p1 },
            { title: 'Milestone 2: Core Development & Beta Review (30%)', amount: p2, due_date: d2.toISOString().split('T')[0], paid_amount: 0 },
            { title: 'Milestone 3: Final Deployment & Handover (30%)', amount: p3, due_date: d3.toISOString().split('T')[0], paid_amount: 0 }
        );
    } else if (count === 4) {
        const p1 = Math.round(totalFee * 0.30);
        const p2 = Math.round(totalFee * 0.30);
        const p3 = Math.round(totalFee * 0.30);
        const p4 = totalFee - p1 - p2 - p3;
        const d2 = new Date(); d2.setDate(d2.getDate() + 15);
        const d3 = new Date(); d3.setDate(d3.getDate() + 35);
        const d4 = new Date(); d4.setDate(d4.getDate() + 60);
        dashStatusServiceMilestones.push(
            { title: 'Milestone 1: Advance / Kickoff & Architecture (30%)', amount: p1, due_date: new Date().toISOString().split('T')[0], paid_amount: p1 },
            { title: 'Milestone 2: UI/UX & Backend Integration (30%)', amount: p2, due_date: d2.toISOString().split('T')[0], paid_amount: 0 },
            { title: 'Milestone 3: Beta Testing & Client Review (30%)', amount: p3, due_date: d3.toISOString().split('T')[0], paid_amount: 0 },
            { title: 'Milestone 4: Production Deployment & Handover (10%)', amount: p4, due_date: d4.toISOString().split('T')[0], paid_amount: 0 }
        );
    }

    renderStatusInstallmentInputs();
}

function addDashStatusServiceMilestoneRow() {
    const nextNum = dashStatusServiceMilestones.length + 1;
    const today = new Date();
    today.setDate(today.getDate() + (nextNum * 15));
    dashStatusServiceMilestones.push({
        title: `Milestone ${nextNum}: Deliverable Phase`,
        amount: 0,
        due_date: today.toISOString().split('T')[0],
        paid_amount: 0
    });
    renderStatusInstallmentInputs();
}

function removeDashStatusServiceMilestoneRow(btn) {
    const row = btn.closest('.dash-status-ms-row');
    const container = document.getElementById('statusInstallmentsContainer');
    const allRows = container.querySelectorAll('.dash-status-ms-row');
    if (allRows.length <= 1) {
        alert('At least one milestone is required.');
        return;
    }
    const idx = Array.from(allRows).indexOf(row);
    if (idx > -1) {
        dashStatusServiceMilestones.splice(idx, 1);
        renderStatusInstallmentInputs();
    }
}

function prepareStatusInstallmentsJson() {
    const status = document.getElementById('statusModalSelect').value;
    const check = document.getElementById('statusSetupPayPlanCheck');
    const jsonInput = document.getElementById('statusInstallmentsJson');
    const offeringType = document.getElementById('statusOfferingType')?.value || 'course';
    const isService = (offeringType === 'service');

    if (!check || !check.checked || status !== 'enrolled') {
        if (jsonInput) jsonInput.value = '';
        return;
    }

    const totalFee = parseFloat(document.getElementById('statusExpectedRevenue')?.value) || 0;
    const installments = [];

    if (isService) {
        const rows = document.querySelectorAll('.dash-status-ms-row');
        rows.forEach((row, idx) => {
            const title = row.querySelector('.dash-ms-title')?.value.trim() || `Milestone ${idx + 1}`;
            const amt = parseFloat(row.querySelector('.dash-ms-amt')?.value) || 0;
            const due = row.querySelector('.dash-ms-due')?.value || new Date().toISOString().split('T')[0];
            const paid = parseFloat(row.querySelector('.dash-ms-paid')?.value) || 0;

            installments.push({
                installment_no: idx + 1,
                title: title,
                amount: amt,
                due_date: due,
                paid_amount: paid,
                payment_method: 'upi',
                transaction_id: '',
                status: (paid >= amt && amt > 0) ? 'paid' : (paid > 0 ? 'partially_paid' : 'pending'),
                notes: `Milestone ${idx + 1}`
            });
        });
    } else {
        const planType = document.getElementById('statusPayPlanType')?.value || 'one_time';
        if (planType === 'one_time') {
            const paid = parseFloat(document.getElementById('dashPlanInstPaid_1')?.value) || 0;
            const method = document.getElementById('dashPlanInstMethod_1')?.value || 'upi';
            const txn = document.getElementById('dashPlanInstTxn_1')?.value || '';
            installments.push({
                installment_no: 1,
                title: '1-Time Full Payment',
                amount: totalFee,
                due_date: new Date().toISOString().split('T')[0],
                paid_amount: paid,
                payment_method: method,
                transaction_id: txn,
                status: (paid >= totalFee && totalFee > 0) ? 'paid' : (paid > 0 ? 'partially_paid' : 'pending'),
                notes: 'Enrollment 1-time payment'
            });
        } else {
            const numInst = parseInt(document.getElementById('statusNumInstallments')?.value) || 3;
            for (let i = 1; i <= numInst; i++) {
                const amt = parseFloat(document.getElementById(`dashPlanInstAmt_${i}`)?.value) || 0;
                const due = document.getElementById(`dashPlanInstDue_${i}`)?.value || new Date().toISOString().split('T')[0];
                const paid = parseFloat(document.getElementById(`dashPlanInstPaid_${i}`)?.value) || 0;
                const method = document.getElementById(`dashPlanInstMethod_${i}`)?.value || 'upi';
                const title = (i === 1) ? '1st Installment (At Admission)' : (i === 2 ? '2nd Installment (Month 2)' : '3rd Installment (Month 3)');
                
                installments.push({
                    installment_no: i,
                    title: title,
                    amount: amt,
                    due_date: due,
                    paid_amount: paid,
                    payment_method: method,
                    transaction_id: '',
                    status: (paid >= amt && amt > 0) ? 'paid' : (paid > 0 ? 'partially_paid' : 'pending'),
                    notes: `Installment ${i}`
                });
            }
        }
    }

    if (jsonInput) jsonInput.value = JSON.stringify(installments);
}

function openUpdateStatusModal(leadId, leadName, currentStatus, offeringType = 'course', courseServiceId = 0, courseServiceName = '', expectedRevenue = 0) {
    document.getElementById('statusModalLeadId').value = leadId;
    document.getElementById('modalLeadName').textContent = leadName;
    document.getElementById('statusModalSelect').value = currentStatus;
    document.getElementById('statusModalRemarks').value = '';

    document.getElementById('statusOfferingType').value = offeringType || 'course';
    syncStatusOfferingDropdown(courseServiceId);

    const revInput = document.getElementById('statusExpectedRevenue');
    revInput.dataset.manual = 'false';
    if (expectedRevenue > 0) {
        revInput.value = expectedRevenue;
        revInput.dataset.manual = 'true';
    }

    if (courseServiceName) {
        document.getElementById('statusCourseServiceName').value = courseServiceName;
    }

    toggleStatusEnrollmentPaymentSection();
    document.getElementById('updateStatusModal').classList.add('active');
}

function closeUpdateStatusModal() {
    document.getElementById('updateStatusModal').classList.remove('active');
}

function submitAddLead(e) {
    e.preventDefault();
    const btn = document.getElementById('addLeadBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';

    const form = document.getElementById('addLeadForm');
    const formData = new FormData(form);

    fetch('<?php echo BASE_URL; ?>/api/leads.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            window.location.reload();
        } else {
            alert(data.message || 'Error creating lead.');
            btn.disabled = false;
            btn.innerHTML = 'Save Lead';
        }
    })
    .catch(err => {
        alert('Request failed.');
        btn.disabled = false;
        btn.innerHTML = 'Save Lead';
    });
}

function submitUpdateStatus(e) {
    e.preventDefault();
    prepareStatusInstallmentsJson();

    const btn = document.getElementById('updateStatusBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';

    const form = document.getElementById('updateStatusForm');
    const formData = new FormData(form);

    fetch('<?php echo BASE_URL; ?>/api/leads.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            window.location.reload();
        } else {
            alert(data.message || 'Error updating status.');
            btn.disabled = false;
            btn.innerHTML = 'Save Update';
        }
    })
    .catch(err => {
        alert('Request failed.');
        btn.disabled = false;
        btn.innerHTML = 'Save Update';
    });
}

// Shift Timer Logic
document.addEventListener('DOMContentLoaded', function() {
    const widget = document.getElementById('shiftTimerWidget');
    if (!widget) return;

    const isCheckedIn = widget.dataset.checkedIn === '1';
    const isCheckedOut = widget.dataset.checkedOut === '1';
    const checkInTs = parseInt(widget.dataset.checkInTs);
    const breakStartTs = parseInt(widget.dataset.breakStartTs);
    const breakEndTs = parseInt(widget.dataset.breakEndTs);
    const totalBreakSec = parseInt(widget.dataset.totalBreakSec);

    if (!isCheckedIn || isCheckedOut || !checkInTs) return;

    function formatHHMMSS(seconds) {
        const h = Math.floor(seconds / 3600);
        const m = Math.floor((seconds % 3600) / 60);
        const s = seconds % 60;
        return String(h).padStart(2, '0') + ':' + String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
    }

    function updateShiftTimer() {
        const now = Math.floor(Date.now() / 1000);
        const isOnBreak = breakStartTs > 0 && breakEndTs === 0;

        let currentBreakSec = totalBreakSec;
        if (isOnBreak) {
            currentBreakSec += Math.max(0, now - breakStartTs);
        }

        const effectiveEnd = isOnBreak ? breakStartTs : now;
        let netWorkSec = Math.max(0, effectiveEnd - checkInTs - totalBreakSec);

        const workStr = formatHHMMSS(netWorkSec);
        const reqSec = 21600; // 6 hours
        const remSec = Math.max(0, reqSec - netWorkSec);
        const remStr = formatHHMMSS(remSec);

        const workElem = document.getElementById('timer-work-display');
        const remElem = document.getElementById('timer-rem-display');
        const barElem = document.getElementById('timer-progress-bar');
        const pctElem = document.getElementById('timer-progress-pct');
        const statusIcon = document.getElementById('timer-status-icon');
        const statusText = document.getElementById('timer-status-text');
        const badgeElem = document.getElementById('timer-badge');
        const breakBanner = document.getElementById('timer-break-banner');
        const breakElem = document.getElementById('timer-break-display');

        if (workElem) workElem.innerText = workStr;
        if (remElem) remElem.innerText = remStr;

        const pct = Math.min(100, Math.floor((netWorkSec / reqSec) * 100));
        if (barElem) barElem.style.width = pct + '%';
        if (pctElem) pctElem.innerText = pct + '%';

        if (isOnBreak) {
            if (statusIcon) statusIcon.innerText = '⏸';
            if (statusText) statusText.innerText = 'Timer Paused (On Break)';
            if (badgeElem) {
                badgeElem.innerText = 'On Break';
                badgeElem.className = 'badge badge-warning';
            }
            if (breakBanner) breakBanner.style.display = 'block';
            if (breakElem) {
                const bM = Math.floor(currentBreakSec / 60);
                const bS = currentBreakSec % 60;
                breakElem.innerText = String(bM).padStart(2, '0') + 'm ' + String(bS).padStart(2, '0') + 's';
            }
        } else {
            if (statusIcon) statusIcon.innerHTML = '<i class="fa-solid fa-stopwatch"></i>';
            if (statusText) statusText.innerHTML = netWorkSec >= reqSec ? 'Full Day Working Goal Completed! <i class="fa-solid fa-circle-check"></i>' : 'Working Timer (6-Hour Goal)';
            if (badgeElem) {
                if (netWorkSec >= reqSec) {
                    badgeElem.innerText = 'Full Day (6h+)';
                    badgeElem.className = 'badge badge-success';
                } else if (netWorkSec >= 10800) {
                    badgeElem.innerText = 'Half Day Achieved (3h+)';
                    badgeElem.className = 'badge badge-info';
                } else {
                    badgeElem.innerText = 'Working Active';
                    badgeElem.className = 'badge badge-primary';
                }
            }
            if (breakBanner) breakBanner.style.display = 'none';
        }
    }

    updateShiftTimer();
    setInterval(updateShiftTimer, 1000);
});

document.querySelectorAll('.modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.remove('active');
        }
    });
});
</script>

<?php 
require_once __DIR__ . '/../includes/payment_modal.php';
require_once __DIR__ . '/../includes/sales_target_modal.php';
include __DIR__ . '/../includes/footer.php'; 
?>
