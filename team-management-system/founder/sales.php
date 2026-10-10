<?php
/**
 * Founder — Sales & Leads CRM Executive Monitoring Hub
 * 
 * 360-degree real-time monitoring of all course & service leads, sales counsellors'
 * conversion rates, revenue generation, lead distribution & reassignment.
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/middleware.php';

ensureSalesTablesExist();
requireRole([ROLE_FOUNDER]);

$db = getDB();
$userId = getUserId();

// Filters
$counsellorFilter = get('counsellor_id', '');
$statusFilter = get('status', '');
$offeringFilter = get('offering_type', '');
$searchQuery = get('search', '');
$page = max(1, (int)get('page', 1));
$limit = 25;

// Monthly Target & Commission Cycle Filter (Founder Controlled)
$selectedMonth = get('month', date('Y-m'));
if (!preg_match('/^\d{4}-\d{2}$/', $selectedMonth)) {
    $selectedMonth = date('Y-m');
}
$monthTime = strtotime($selectedMonth . '-01');
$selectedMonthName = date('F Y', $monthTime);
$isCurrentMonth = ($selectedMonth === date('Y-m'));
$prevMonth = date('Y-m', strtotime('-1 month', $monthTime));
$nextMonth = date('Y-m', strtotime('+1 month', $monthTime));

// Last Commission Refresh Record for this month
$lastCommissionRefresh = getLastCommissionRefresh($selectedMonth);

// Month-specific Enrolled Admissions & Commission Pool (Refreshed per month)
$commRate = defined('COMMISSION_PER_ADMISSION') ? (float)COMMISSION_PER_ADMISSION : 5000.00;
$monthEnrolledCount = 0;
$monthCommissionTotal = 0.0;
$monthCollectedTotal = 0.0;

try {
    $mEnrStmt = $db->prepare("
        SELECT 
            COUNT(*) as month_enrolled,
            COALESCE(SUM(expected_revenue), 0) as month_revenue,
            COALESCE(SUM(paid_amount), 0) as month_collected
        FROM leads 
        WHERE status = 'enrolled' 
          AND (DATE_FORMAT(COALESCE(enrolled_at, updated_at, created_at), '%Y-%m') = ?)
    ");
    $mEnrStmt->execute([$selectedMonth]);
    $mEnrData = $mEnrStmt->fetch();
    $monthEnrolledCount = (int)($mEnrData['month_enrolled'] ?? 0);
    $monthCommissionTotal = $monthEnrolledCount * $commRate;
    $monthCollectedTotal = (float)($mEnrData['month_collected'] ?? 0.0);
} catch (Throwable $e) {
    error_log("founder/sales.php mEnrStmt error: " . $e->getMessage());
    try {
        $mEnrStmtFallback = $db->prepare("
            SELECT COUNT(*) as month_enrolled, COALESCE(SUM(expected_revenue), 0) as month_revenue
            FROM leads 
            WHERE status = 'enrolled' 
              AND (DATE_FORMAT(COALESCE(updated_at, created_at), '%Y-%m') = ?)
        ");
        $mEnrStmtFallback->execute([$selectedMonth]);
        $fallbackData = $mEnrStmtFallback->fetch();
        $monthEnrolledCount = (int)($fallbackData['month_enrolled'] ?? 0);
        $monthCommissionTotal = $monthEnrolledCount * $commRate;
        $monthCollectedTotal = (float)($fallbackData['month_revenue'] ?? 0.0);
    } catch (Throwable $e2) {
        $monthEnrolledCount = 0;
        $monthCommissionTotal = 0.0;
        $monthCollectedTotal = 0.0;
    }
}

$filters = [
    'assigned_to' => !empty($counsellorFilter) ? (int)$counsellorFilter : null,
    'status' => $statusFilter,
    'offering_type' => $offeringFilter,
    'search' => $searchQuery
];

$leadsResult = getLeads($filters, $page, $limit);
$leads = $leadsResult['data'];
$totalLeads = $leadsResult['total'];
$totalPages = $leadsResult['total_pages'];

// Global Sales Metrics
$globalMetrics = getSalesMetrics();

// Sales Counsellors List
$counsellors = getSalesCounsellorsList();

// Counsellor Performance Leaderboard Query
$counsellorPerf = [];
try {
    $perfStmt = $db->query("
        SELECT 
            u.id, u.name, u.email, u.role,
            COUNT(l.id) as total_assigned,
            SUM(CASE WHEN l.status = 'new_lead' THEN 1 ELSE 0 END) as new_count,
            SUM(CASE WHEN l.status = 'interested' THEN 1 ELSE 0 END) as interested_count,
            SUM(CASE WHEN l.status = 'enrolled' THEN 1 ELSE 0 END) as enrolled_count,
            SUM(CASE WHEN l.status = 'uninterested' THEN 1 ELSE 0 END) as uninterested_count,
            SUM(CASE WHEN l.status = 'enrolled' THEN l.expected_revenue ELSE 0 END) as total_revenue
        FROM users u
        LEFT JOIN leads l ON l.assigned_to = u.id
        WHERE u.role IN ('sales_counsellor', 'employee', 'manager') AND u.status = 'active'
        GROUP BY u.id, u.name, u.email, u.role
        HAVING total_assigned > 0 OR u.role = 'sales_counsellor'
        ORDER BY enrolled_count DESC, total_revenue DESC, total_assigned DESC
    ");
    $counsellorPerf = $perfStmt ? $perfStmt->fetchAll() : [];
} catch (Throwable $e) {
    error_log("founder/sales.php perfStmt error: " . $e->getMessage());
    $counsellorPerf = [];
}

// Offerings list
$coursesList = getCourseServicesList('course');
$servicesList = getCourseServicesList('service');

$pageTitle = 'Sales & Leads CRM — Founder';
include __DIR__ . '/../includes/header.php';
?>

<!-- Header -->
<div class="page-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: var(--space-4); margin-bottom: var(--space-5);">
    <div>
        <h1 class="page-title"><i class="fa-solid fa-funnel-dollar" style="color: var(--color-primary); margin-right: 8px;"></i>Sales & Leads CRM Command Center</h1>
        <p class="page-subtitle">Executive monitoring of course admissions, IT service leads, and counsellor conversion metrics.</p>
    </div>
    <div style="display: flex; gap: var(--space-2); flex-wrap: wrap;">
        <button type="button" class="btn btn-primary" onclick="openSalesTargetModal()" style="background: #10b981; color: #fff; border: none; font-weight: 600;">
            <i class="fa-solid fa-bullseye"></i> Set Monthly Target
        </button>
        <a href="<?php echo BASE_URL; ?>/founder/revenue.php" class="btn btn-warning" style="background: #f59e0b; color: #fff; border: none; font-weight: 600;">
            <i class="fa-solid fa-coins"></i> Revenue Model
        </a>
        <button type="button" class="btn btn-warning" onclick="openBulkAssignCountModal()" style="background: #6366f1; color: #fff; border: none; font-weight: 600;">
            <i class="fa-solid fa-bolt"></i> Bulk Assign Leads
        </button>
        <button type="button" class="btn btn-primary" onclick="openAddLeadModal('course')">
            <i class="fa-solid fa-graduation-cap"></i> + Add Course Lead
        </button>
        <button type="button" class="btn btn-warning" onclick="openAddLeadModal('service')" style="background: #f59e0b; color: #fff; border: none; font-weight: 600;">
            <i class="fa-solid fa-laptop-code"></i> + Add Service Lead
        </button>
        <a href="<?php echo BASE_URL; ?>/sales/raw_leads.php" class="btn btn-secondary">
            <i class="fa-solid fa-address-book"></i> Raw Prospects Bank
        </a>
        <button type="button" class="btn btn-secondary" onclick="openAddOfferingModal()">
            <i class="fa-solid fa-plus-circle"></i> Add Course / Service
        </button>
        <a href="<?php echo BASE_URL; ?>/sales/offerings.php" class="btn btn-outline">
            <i class="fa-solid fa-graduation-cap"></i> Catalogue
        </a>
        <button type="button" class="btn btn-danger" onclick="triggerDeleteAllLeads()" style="background: rgba(239, 68, 68, 0.15); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.4); font-weight: 600;" title="Delete all leads with mandatory Google Sheets backup">
            <i class="fa-solid fa-trash-can"></i> Delete All Leads
        </button>
    </div>
</div>

<!-- Global Sales KPI Cards (Symmetrical 4x2 Grid) -->
<div class="counsellor-kpi-grid">
    <!-- 1. Total Leads -->
    <div class="counsellor-kpi-card fade-in">
        <div class="counsellor-kpi-icon" style="background: rgba(99, 102, 241, 0.15); color: var(--color-primary);">
            <i class="fa-solid fa-users"></i>
        </div>
        <div class="counsellor-kpi-info">
            <div class="counsellor-kpi-val"><?php echo $globalMetrics['total_leads']; ?></div>
            <div class="counsellor-kpi-lbl">Total Leads</div>
        </div>
    </div>

    <!-- 2. Fresh Leads -->
    <div class="counsellor-kpi-card fade-in">
        <div class="counsellor-kpi-icon" style="background: rgba(59, 130, 246, 0.15); color: #3b82f6;">
            <i class="fa-solid fa-user-plus"></i>
        </div>
        <div class="counsellor-kpi-info">
            <div class="counsellor-kpi-val"><?php echo $globalMetrics['new_leads']; ?></div>
            <div class="counsellor-kpi-lbl">Fresh Leads</div>
        </div>
    </div>

    <!-- 3. Interested Leads -->
    <div class="counsellor-kpi-card fade-in">
        <div class="counsellor-kpi-icon" style="background: rgba(245, 158, 11, 0.15); color: #f59e0b;">
            <i class="fa-solid fa-clock-rotate-left"></i>
        </div>
        <div class="counsellor-kpi-info">
            <div class="counsellor-kpi-val"><?php echo $globalMetrics['interested']; ?></div>
            <div class="counsellor-kpi-lbl">In Follow-up / Warm</div>
        </div>
    </div>

    <!-- 4. Enrolled / Converted -->
    <div class="counsellor-kpi-card fade-in">
        <div class="counsellor-kpi-icon" style="background: rgba(16, 185, 129, 0.15); color: #10b981;">
            <i class="fa-solid fa-circle-check"></i>
        </div>
        <div class="counsellor-kpi-info">
            <div class="counsellor-kpi-val"><?php echo $globalMetrics['enrolled']; ?></div>
            <div class="counsellor-kpi-lbl">Enrolled / Converted</div>
        </div>
    </div>

    <!-- 5. Admissions Commission KPI Card -->
    <div class="counsellor-kpi-card fade-in" style="border: 1.5px solid rgba(245, 158, 11, 0.45); background: linear-gradient(135deg, rgba(245, 158, 11, 0.12), rgba(245, 158, 11, 0.02));">
        <div class="counsellor-kpi-icon" style="background: rgba(245, 158, 11, 0.25); color: #f59e0b;">
            <i class="fa-solid fa-coins"></i>
        </div>
        <div class="counsellor-kpi-info">
            <div class="counsellor-kpi-val" style="color: #f59e0b;">₹<?php echo number_format($monthCommissionTotal); ?></div>
            <div class="counsellor-kpi-lbl" style="color: #f59e0b; font-weight: 600;">Commission (<?php echo date('M', $monthTime); ?> · <?php echo $monthEnrolledCount; ?> adm)</div>
        </div>
    </div>

    <!-- 6. Enrolled Revenue -->
    <div class="counsellor-kpi-card fade-in">
        <div class="counsellor-kpi-icon" style="background: rgba(16, 185, 129, 0.15); color: #10b981;">
            <i class="fa-solid fa-indian-rupee-sign"></i>
        </div>
        <div class="counsellor-kpi-info">
            <div class="counsellor-kpi-val" style="color: #10b981;">₹<?php echo number_format($globalMetrics['total_enrolled_value']); ?></div>
            <div class="counsellor-kpi-lbl">Enrolled Revenue</div>
        </div>
    </div>

    <!-- 7. Conversion Rate -->
    <div class="counsellor-kpi-card fade-in">
        <div class="counsellor-kpi-icon" style="background: rgba(168, 85, 247, 0.15); color: #a855f7;">
            <i class="fa-solid fa-bullseye"></i>
        </div>
        <div class="counsellor-kpi-info">
            <div class="counsellor-kpi-val"><?php echo $globalMetrics['conversion_rate']; ?>%</div>
            <div class="counsellor-kpi-lbl">Global Conversion %</div>
        </div>
    </div>

    <!-- 8. Today's Due Followups -->
    <div class="counsellor-kpi-card fade-in">
        <div class="counsellor-kpi-icon" style="background: rgba(239, 68, 68, 0.15); color: #ef4444;">
            <i class="fa-solid fa-phone-volume"></i>
        </div>
        <div class="counsellor-kpi-info">
            <div class="counsellor-kpi-val"><?php echo $globalMetrics['today_followups_count']; ?></div>
            <div class="counsellor-kpi-lbl">Today's Due Calls</div>
        </div>
    </div>
</div>

<!-- 🔄 Monthly Target & Commission Cycle Control Bar (Founder Controlled) -->
<div class="card fade-in" style="margin-bottom: var(--space-5); border: 1.5px solid rgba(99, 102, 241, 0.35); background: linear-gradient(135deg, rgba(99, 102, 241, 0.08) 0%, rgba(16, 185, 129, 0.04) 100%); padding: 16px 20px;">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px;">
        <!-- Left: Month Navigation & Cycle Badge -->
        <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
            <div style="width: 38px; height: 38px; border-radius: 10px; background: linear-gradient(135deg, #6366f1, #4f46e5); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 16px; box-shadow: 0 4px 12px rgba(99, 102, 241, 0.35);">
                <i class="fa-solid fa-calendar-check"></i>
            </div>
            <div>
                <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                    <h3 style="margin: 0; font-size: 16px; font-weight: 700; color: var(--color-text-white);">
                        Monthly Cycle: <?php echo $selectedMonthName; ?>
                    </h3>
                    <?php if ($isCurrentMonth): ?>
                        <span class="badge badge-success" style="font-size: 11px; font-weight: 700;"><i class="fa-solid fa-circle-dot"></i> Active Month</span>
                    <?php elseif ($selectedMonth > date('Y-m')): ?>
                        <span class="badge badge-info" style="font-size: 11px; font-weight: 700;"><i class="fa-solid fa-calendar-plus"></i> Next Planning Month</span>
                    <?php else: ?>
                        <span class="badge badge-secondary" style="font-size: 11px; font-weight: 700;"><i class="fa-solid fa-clock-rotate-left"></i> Past Audited Month</span>
                    <?php endif; ?>
                </div>
                <div style="font-size: 12px; color: var(--color-text-secondary); margin-top: 3px;">
                    <i class="fa-solid fa-info-circle" style="color: var(--color-primary);"></i>
                    Founder sets monthly target · Commission refreshes every month (₹5,000/admission).
                </div>
            </div>
        </div>

        <!-- Center/Right: Month Selector & Founder Action Controls -->
        <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
            <!-- Prev Month -->
            <a href="<?php echo BASE_URL; ?>/founder/sales.php?month=<?php echo $prevMonth; ?>" class="btn btn-sm btn-ghost" style="border: 1px solid var(--color-border); padding: 6px 10px;" title="Previous Month">
                <i class="fa-solid fa-chevron-left"></i>
            </a>

            <!-- Month Quick Picker Form -->
            <form method="GET" action="<?php echo BASE_URL; ?>/founder/sales.php" style="margin: 0; display: inline-flex; align-items: center;">
                <?php if (!empty($counsellorFilter)): ?><input type="hidden" name="counsellor_id" value="<?php echo e($counsellorFilter); ?>"><?php endif; ?>
                <input type="month" name="month" value="<?php echo e($selectedMonth); ?>" class="form-input" onchange="this.form.submit()" 
                       style="padding: 6px 12px; font-size: 12.5px; font-weight: 600; width: auto; height: 36px; border-radius: var(--radius-md);">
            </form>

            <!-- Next Month -->
            <a href="<?php echo BASE_URL; ?>/founder/sales.php?month=<?php echo $nextMonth; ?>" class="btn btn-sm btn-ghost" style="border: 1px solid var(--color-border); padding: 6px 10px;" title="Next Month">
                <i class="fa-solid fa-chevron-right"></i>
            </a>

            <!-- 🔄 Refresh Commission Button -->
            <button type="button" class="btn btn-warning" id="refreshCommissionBtn" onclick="refreshMonthlyCommission('<?php echo $selectedMonth; ?>')" 
                    style="background: linear-gradient(135deg, #f59e0b, #d97706); color: #fff; font-weight: 700; border: none; height: 36px; font-size: 12.5px; box-shadow: 0 4px 12px rgba(245, 158, 11, 0.35);">
                <i class="fa-solid fa-arrows-rotate" id="refreshCommissionIcon"></i> Refresh Commission (<?php echo date('M', $monthTime); ?>)
            </button>

            <!-- 🎯 Set Target for This Month -->
            <button type="button" class="btn btn-primary" onclick="openSalesTargetModal(null, '<?php echo $selectedMonth; ?>')" 
                    style="background: #10b981; color: #fff; border: none; font-weight: 700; height: 36px; font-size: 12.5px; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);">
                <i class="fa-solid fa-bullseye"></i> Set Target for <?php echo date('M', $monthTime); ?>
            </button>
        </div>
    </div>

    <!-- Cycle Status Quick Strip -->
    <div style="display: flex; gap: 16px; margin-top: 12px; padding-top: 10px; border-top: 1px dashed rgba(99, 102, 241, 0.2); font-size: 12px; color: var(--color-text-secondary); flex-wrap: wrap;">
        <span><i class="fa-solid fa-graduation-cap" style="color: var(--color-primary);"></i> Month Admissions: <strong style="color: var(--color-text-white);"><?php echo $monthEnrolledCount; ?></strong></span>
        <span><i class="fa-solid fa-wallet" style="color: #10b981;"></i> Month Commission Pool: <strong style="color: #10b981;">₹<?php echo number_format($monthCommissionTotal); ?></strong></span>
        <span><i class="fa-solid fa-hand-holding-dollar" style="color: #f59e0b;"></i> Month Collected: <strong style="color: #f59e0b;">₹<?php echo number_format($monthCollectedTotal); ?></strong></span>
        <span>
            <i class="fa-solid fa-clock-rotate-left"></i> Status: 
            <?php if ($lastCommissionRefresh): ?>
                <span style="color: var(--color-text-white);">Refreshed <?php echo date('d M, h:i A', strtotime($lastCommissionRefresh['created_at'])); ?> by <?php echo e($lastCommissionRefresh['refreshed_by_name'] ?? 'Founder'); ?></span>
            <?php else: ?>
                <span style="color: var(--color-text-muted);">Real-time live sync</span>
            <?php endif; ?>
        </span>
    </div>
</div>

<!-- Counsellor Performance Leaderboard -->
<div class="data-table-container fade-in" style="background: var(--color-bg-card); border: 1px solid var(--color-border); border-radius: var(--radius-lg); overflow: hidden; margin-bottom: var(--space-6);">
    <div style="display: flex; justify-content: space-between; align-items: center; padding: var(--space-4) var(--space-5); border-bottom: 1px solid var(--color-border);">
        <h3 style="margin: 0; font-size: 16px; font-weight: 600; display: flex; align-items: center; gap: 8px;">
            <i class="fa-solid fa-trophy" style="color: #f59e0b;"></i>
            Sales Counsellors Performance, Targets & Commission — <?php echo $selectedMonthName; ?>
        </h3>
        <span class="badge badge-secondary"><?php echo count($counsellorPerf); ?> Active Staff</span>
    </div>

    <?php if (empty($counsellorPerf)): ?>
        <p style="text-align: center; color: var(--color-text-muted); padding: var(--space-6);">No sales staff performance records found.</p>
    <?php else: ?>
        <div style="overflow-x: auto;">
            <table class="data-table" style="width: 100%;">
                <thead>
                    <tr>
                        <th>Counsellor Name</th>
                        <th>Role</th>
                        <th>Assigned</th>
                        <th>Fresh</th>
                        <th>In Discussion</th>
                        <th>Enrolled</th>
                        <th>Lost</th>
                        <th>Conversion %</th>
                        <th>Enrolled Rev</th>
                        <th>Commission (<?php echo date('M Y', $monthTime); ?>)</th>
                        <th>Target Added by Founder (<?php echo date('M Y', $monthTime); ?>)</th>
                        <th>Month Progress</th>
                        <th style="text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($counsellorPerf as $cp): ?>
                        <?php 
                            $conv = ($cp['total_assigned'] > 0) ? round(($cp['enrolled_count'] / $cp['total_assigned']) * 100, 1) : 0;
                            $cpTarget = getMonthlySalesTargetAndProgress((int)$cp['id'], $selectedMonth);
                        ?>
                        <tr>
                            <td>
                                <strong><?php echo e($cp['name']); ?></strong>
                                <div style="font-size: 11px; color: var(--color-text-secondary);"><?php echo e($cp['email']); ?></div>
                            </td>
                            <td><span class="badge badge-secondary" style="font-size: 10px;"><?php echo ucwords(str_replace('_', ' ', e($cp['role']))); ?></span></td>
                            <td><strong><?php echo (int)$cp['total_assigned']; ?></strong></td>
                            <td><span class="badge badge-info" style="font-size: 10px;"><?php echo (int)$cp['new_count']; ?></span></td>
                            <td><span class="badge badge-warning" style="font-size: 10px;"><?php echo (int)$cp['interested_count']; ?></span></td>
                            <td><span class="badge badge-success" style="font-size: 10px;"><?php echo (int)$cp['enrolled_count']; ?></span></td>
                            <td><span class="badge badge-danger" style="font-size: 10px;"><?php echo (int)$cp['uninterested_count']; ?></span></td>
                            <td>
                                <strong style="color: <?php echo $conv >= 20 ? 'var(--color-success)' : ($conv >= 10 ? 'var(--color-warning)' : 'var(--color-text-white)'); ?>;">
                                    <?php echo $conv; ?>%
                                </strong>
                            </td>
                            <td><strong style="color: var(--color-success);">₹<?php echo number_format((float)$cp['total_revenue']); ?></strong></td>
                            <td>
                                <div style="font-size: 13px; font-weight: 700; color: #f59e0b;">
                                    ₹<?php echo number_format($cpTarget['earned_commission']); ?>
                                </div>
                                <div style="font-size: 10.5px; color: var(--color-text-muted);">
                                    <?php echo $cpTarget['achieved_enrollments']; ?> adm in <?php echo date('M', $monthTime); ?> · ₹<?php echo number_format($cpTarget['lifetime_commission']); ?> life
                                </div>
                            </td>
                            <td>
                                <?php if ($cpTarget['has_target']): ?>
                                    <div style="font-weight: 700; color: var(--color-text-main); font-size: 12.5px;">
                                        ₹<?php echo number_format($cpTarget['target_revenue']); ?>
                                    </div>
                                    <div style="font-size: 11px; color: var(--color-text-secondary);">
                                        <?php echo $cpTarget['target_enrollments']; ?> Admissions
                                    </div>
                                    <span class="badge badge-success" style="font-size: 9.5px; margin-top: 2px;">Target Set</span>
                                <?php else: ?>
                                    <div>
                                        <span class="badge badge-warning" style="font-size: 10.5px; font-weight: 600;">
                                            <i class="fa-solid fa-clock"></i> Target Pending
                                        </span>
                                    </div>
                                    <div style="font-size: 10.5px; color: var(--color-text-muted); margin-top: 2px;">
                                        Founder target not set yet
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($cpTarget['has_target']): ?>
                                    <div style="display: flex; align-items: center; gap: 6px;">
                                        <div style="background: var(--color-bg-secondary); border-radius: 8px; height: 6px; width: 60px; overflow: hidden;">
                                            <div style="background: linear-gradient(90deg, #10b981, #6366f1); height: 100%; width: <?php echo min(100, $cpTarget['revenue_pct']); ?>%;"></div>
                                        </div>
                                        <span style="font-size: 11px; font-weight: 700; color: <?php echo $cpTarget['revenue_pct'] >= 100 ? 'var(--color-success)' : 'var(--color-primary)'; ?>;">
                                            <?php echo $cpTarget['revenue_pct']; ?>%
                                        </span>
                                    </div>
                                    <div style="font-size: 10.5px; color: var(--color-text-muted); margin-top: 2px;">
                                        ₹<?php echo number_format($cpTarget['achieved_revenue']); ?> · <?php echo $cpTarget['achieved_enrollments']; ?> adm
                                    </div>
                                <?php else: ?>
                                    <span style="font-size: 11px; color: var(--color-text-muted);">—</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: right;">
                                <div style="display: inline-flex; gap: 6px;">
                                    <?php if ($cpTarget['has_target']): ?>
                                        <button type="button" class="btn btn-sm btn-ghost" onclick="openSalesTargetModal(<?php echo (int)$cp['id']; ?>, '<?php echo $selectedMonth; ?>', <?php echo $cpTarget['target_revenue']; ?>, <?php echo $cpTarget['target_enrollments']; ?>, <?php echo $cpTarget['target_leads']; ?>, '<?php echo addslashes($cpTarget['notes']); ?>')" style="padding: 4px 8px; font-size: 11px; color: #10b981; border: 1px solid rgba(16, 185, 129, 0.4);" title="Edit Target for <?php echo date('M Y', $monthTime); ?>">
                                            <i class="fa-solid fa-pen-to-square"></i> Edit Target
                                        </button>
                                    <?php else: ?>
                                        <button type="button" class="btn btn-sm btn-primary" onclick="openSalesTargetModal(<?php echo (int)$cp['id']; ?>, '<?php echo $selectedMonth; ?>')" style="padding: 4px 8px; font-size: 11px; background: #10b981; border: none;" title="Add Target for <?php echo date('M Y', $monthTime); ?>">
                                            <i class="fa-solid fa-plus"></i> Add Target
                                        </button>
                                    <?php endif; ?>
                                    <a href="<?php echo BASE_URL; ?>/founder/sales.php?counsellor_id=<?php echo (int)$cp['id']; ?>&month=<?php echo urlencode($selectedMonth); ?>" class="btn btn-sm btn-outline" style="padding: 4px 8px; font-size: 11px;">
                                        Filter
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

<!-- 4 DEDICATED SECTIONS SWITCHER (Master vs Courses vs Services vs Manual Calling Data) -->
<div style="display: flex; gap: var(--space-2); margin-bottom: var(--space-4); flex-wrap: wrap;">
    <a href="<?php echo BASE_URL; ?>/founder/sales.php<?php echo !empty($counsellorFilter) ? '?counsellor_id=' . urlencode($counsellorFilter) : ''; ?>" class="btn btn-sm <?php echo empty($offeringFilter) ? 'btn-primary' : 'btn-secondary'; ?>" style="border-radius: var(--radius-full);">
        <i class="fa-solid fa-layer-group"></i> All Master Leads (<?php echo $globalMetrics['total_leads']; ?>)
    </a>
    <a href="<?php echo BASE_URL; ?>/founder/sales.php?offering_type=course<?php echo !empty($counsellorFilter) ? '&counsellor_id=' . urlencode($counsellorFilter) : ''; ?>" class="btn btn-sm <?php echo $offeringFilter === 'course' ? 'btn-primary' : 'btn-secondary'; ?>" style="border-radius: var(--radius-full);">
        <i class="fa-solid fa-graduation-cap"></i> Courses & Admissions (<?php echo $globalMetrics['course_leads']; ?>)
    </a>
    <a href="<?php echo BASE_URL; ?>/founder/sales.php?offering_type=service<?php echo !empty($counsellorFilter) ? '&counsellor_id=' . urlencode($counsellorFilter) : ''; ?>" class="btn btn-sm <?php echo $offeringFilter === 'service' ? 'btn-primary' : 'btn-secondary'; ?>" style="border-radius: var(--radius-full);">
        <i class="fa-solid fa-laptop-code"></i> IT & Digital Services (<?php echo $globalMetrics['service_leads']; ?>)
    </a>
    <a href="<?php echo BASE_URL; ?>/founder/sales.php?offering_type=raw_data<?php echo !empty($counsellorFilter) ? '&counsellor_id=' . urlencode($counsellorFilter) : ''; ?>" class="btn btn-sm <?php echo $offeringFilter === 'raw_data' ? 'btn-primary' : 'btn-secondary'; ?>" style="border-radius: var(--radius-full); <?php echo $offeringFilter === 'raw_data' ? 'background: #0284c7; border-color: #0284c7;' : ''; ?>">
        <i class="fa-solid fa-address-book"></i> Raw / Manual Calling Data (<?php echo $globalMetrics['manual_leads']; ?>)
    </a>
</div>

<!-- Master Leads Pipeline Table -->
<div class="data-table-container fade-in" style="background: var(--color-bg-card); border: 1px solid var(--color-border); border-radius: var(--radius-lg); overflow: hidden; margin-bottom: var(--space-6);">
    <div style="display: flex; justify-content: space-between; align-items: center; padding: var(--space-4) var(--space-5); border-bottom: 1px solid var(--color-border); flex-wrap: wrap; gap: var(--space-3);">
        <div>
            <h3 style="margin: 0; font-size: 16px; font-weight: 600; display: flex; align-items: center; gap: 8px;">
                <?php if ($offeringFilter === 'course'): ?>
                    <i class="fa-solid fa-graduation-cap" style="color: var(--color-primary);"></i>
                    Course Admissions Pipeline
                <?php elseif ($offeringFilter === 'service'): ?>
                    <i class="fa-solid fa-laptop-code" style="color: #f59e0b;"></i>
                    Software & IT Services Pipeline
                <?php else: ?>
                    <i class="fa-solid fa-list" style="color: var(--color-primary);"></i>
                    Master Leads Pipeline
                <?php endif; ?>
            </h3>
            <p style="margin: 4px 0 0 0; font-size: 12px; color: var(--color-text-secondary);">
                <?php if ($offeringFilter === 'course'): ?>
                    Student registrations, course counseling, and batch admissions
                <?php elseif ($offeringFilter === 'service'): ?>
                    B2B project inquiries, client development proposals, and deals
                <?php else: ?>
                    Filter, monitor notes, and reassign leads among counsellors
                <?php endif; ?>
            </p>
        </div>

        <!-- Filter Bar -->
        <form method="GET" action="<?php echo BASE_URL; ?>/founder/sales.php" class="filter-bar" style="margin-bottom: 0; padding: 0; border: none; background: transparent; display: flex; gap: var(--space-2); flex-wrap: wrap; align-items: center;">
            <select name="counsellor_id" class="form-select" style="min-width: 140px; padding: 6px 10px; font-size: 12px;" onchange="this.form.submit()">
                <option value="">All Counsellors</option>
                <?php foreach ($counsellors as $cs): ?>
                    <option value="<?php echo (int)$cs['id']; ?>" <?php echo $counsellorFilter == $cs['id'] ? 'selected' : ''; ?>>
                        <?php echo e($cs['name']); ?> (<?php echo ucfirst($cs['role']); ?>)
                    </option>
                <?php endforeach; ?>
            </select>

            <select name="status" class="form-select" style="min-width: 120px; padding: 6px 10px; font-size: 12px;" onchange="this.form.submit()">
                <option value="">All Statuses</option>
                <option value="new_lead" <?php echo $statusFilter === 'new_lead' ? 'selected' : ''; ?>>New Lead</option>
                <option value="interested" <?php echo $statusFilter === 'interested' ? 'selected' : ''; ?>>Interested</option>
                <option value="enrolled" <?php echo $statusFilter === 'enrolled' ? 'selected' : ''; ?>>Enrolled</option>
                <option value="uninterested" <?php echo $statusFilter === 'uninterested' ? 'selected' : ''; ?>>Uninterested</option>
            </select>

            <select name="offering_type" class="form-select" style="min-width: 120px; padding: 6px 10px; font-size: 12px;" onchange="this.form.submit()">
                <option value="">All Types</option>
                <option value="course" <?php echo $offeringFilter === 'course' ? 'selected' : ''; ?>>Courses</option>
                <option value="service" <?php echo $offeringFilter === 'service' ? 'selected' : ''; ?>>Services</option>
            </select>

            <div style="display: flex; gap: 4px;">
                <input type="text" name="search" class="form-input" placeholder="Search name, phone, city..." value="<?php echo e($searchQuery); ?>" style="padding: 6px 10px; font-size: 12px; width: 170px;">
                <button type="submit" class="btn btn-sm btn-primary" style="padding: 6px 10px;">
                    <i class="fa-solid fa-magnifying-glass"></i>
                </button>
            </div>

            <?php if (!empty($counsellorFilter) || !empty($statusFilter) || !empty($offeringFilter) || !empty($searchQuery)): ?>
                <a href="<?php echo BASE_URL; ?>/founder/sales.php" class="btn btn-sm btn-outline" style="padding: 6px 10px;" title="Reset Filters">
                    <i class="fa-solid fa-rotate-left"></i>
                </a>
            <?php endif; ?>
        </form>
    </div>

    <?php if (empty($leads)): ?>
        <p style="text-align: center; color: var(--color-text-muted); padding: var(--space-8);">No leads match the selected criteria.</p>
    <?php else: ?>
        <div style="overflow-x: auto;">
            <table class="data-table" style="width: 100%;">
                <thead>
                    <tr>
                        <th style="width: 38px; text-align: center;">
                            <input type="checkbox" id="selectAllLeads" onchange="toggleSelectAllLeads(this)" title="Select all on this page">
                        </th>
                        <th>Prospect</th>
                        <th>Type & Offering</th>
                        <th>Assigned To</th>
                        <th>Status</th>
                        <th>Next Call</th>
                        <th>Value</th>
                        <th style="text-align: right;">Operations</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($leads as $lead): ?>
                        <tr id="lead-row-<?php echo (int)$lead['id']; ?>">
                            <td style="text-align: center;">
                                <input type="checkbox" class="lead-row-checkbox" value="<?php echo (int)$lead['id']; ?>" onchange="updateSelectedLeadsCount()">
                            </td>
                            <td>
                                <div style="font-weight: 600; color: var(--color-text-white); display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">
                                    <?php echo e($lead['name']); ?>
                                    <?php if (!empty($lead['graduation'])): ?>
                                        <span class="badge" style="font-size: 10px; padding: 1px 6px; background: rgba(99, 102, 241, 0.15); color: var(--color-primary-light); border: 1px solid rgba(99, 102, 241, 0.3);">
                                            <i class="fa-solid fa-user-graduate" style="font-size: 9px; margin-right: 2px;"></i><?php echo e($lead['graduation']); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div style="font-size: 11.5px; color: var(--color-text-secondary); margin-top: 2px; font-family: monospace;">
                                    <i class="fa-solid fa-phone" style="font-size: 9px;"></i> <?php echo e($lead['phone']); ?>
                                    <?php if ($lead['email']): ?> • <?php echo e($lead['email']); ?><?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <span class="badge <?php echo $lead['offering_type'] === 'course' ? 'badge-primary' : 'badge-warning'; ?>" style="font-size: 10px;">
                                    <?php echo ucfirst($lead['offering_type']); ?>
                                </span>
                                <div style="font-size: 12px; font-weight: 500; margin-top: 2px;">
                                    <?php echo e($lead['course_service_name']); ?>
                                </div>
                            </td>
                            <td>
                                <?php if (!empty($lead['assigned_to_name'])): ?>
                                    <div style="font-weight: 600; color: var(--color-text-white);"><?php echo e($lead['assigned_to_name']); ?></div>
                                <?php else: ?>
                                    <span style="color: var(--color-danger); font-size: 11px;">Unassigned</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo getLeadStatusBadge($lead['status']); ?>
                                <?php if ($lead['status'] === 'enrolled' || (!empty($lead['payment_plan']) && $lead['payment_plan'] !== 'none')): ?>
                                    <div style="margin-top: 4px;">
                                        <?php echo getPaymentStatusBadge($lead['payment_status'] ?? 'unpaid', (float)($lead['paid_amount'] ?? 0), (float)($lead['expected_revenue'] ?? 0), $lead['payment_plan'] ?? 'none', $lead['offering_type'] ?? 'course'); ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($lead['next_followup_date']): ?>
                                    <span style="font-size: 11px;"><?php echo formatDate($lead['next_followup_date'], 'd M, h:i A'); ?></span>
                                <?php else: ?>
                                    <span style="color: var(--color-text-muted); font-size: 11px;">—</span>
                                <?php endif; ?>
                            </td>
                            <td><strong style="color: var(--color-success);">₹<?php echo number_format($lead['expected_revenue']); ?></strong></td>
                            <td style="text-align: right;">
                                <div style="display: inline-flex; gap: 4px;">
                                    <button type="button" class="btn btn-sm btn-ghost text-success" title="Send WhatsApp Message" style="padding: 4px 8px; color: var(--color-success); border: none; background: none; cursor: pointer;" onclick="openWhatsAppModal(<?php echo (int)$lead['id']; ?>, '<?php echo addslashes(e($lead['name'])); ?>', '<?php echo e($lead['phone']); ?>', <?php echo ($lead['is_manual'] ? 2 : 1); ?>, '<?php echo addslashes(e($lead['course_service_name'] ?? 'UI/UX Design')); ?>')">
                                        <i class="fa-brands fa-whatsapp"></i>
                                    </button>
                                    <?php if ($lead['status'] === 'enrolled' || $lead['offering_type'] === 'course'): ?>
                                        <button type="button" class="btn btn-sm" onclick="openPaymentModal(<?php echo (int)$lead['id']; ?>)" title="Manage Course Payment & Installments" style="padding: 4px 8px; font-size: 12px; background: rgba(16, 185, 129, 0.15); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.3);">
                                            <i class="fa-solid fa-credit-card"></i> Pay
                                        </button>
                                    <?php endif; ?>
                                    <button type="button" class="btn btn-sm btn-primary" onclick="openAssignModal(<?php echo (int)$lead['id']; ?>, '<?php echo addslashes(e($lead['name'])); ?>', <?php echo (int)($lead['assigned_to'] ?? 0); ?>)" title="Assign Counsellor" style="padding: 4px 8px; font-size: 12px;">
                                        <i class="fa-solid fa-user-gear"></i> Assign
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline" onclick="viewLeadDrawer(<?php echo (int)$lead['id']; ?>)" title="Activity Log" style="padding: 4px 8px; font-size: 12px;">
                                        <i class="fa-solid fa-clock-rotate-left"></i> (<?php echo (int)$lead['activity_count']; ?>)
                                    </button>
                                    <button type="button" class="btn btn-sm btn-ghost text-danger" onclick="confirmDeleteSingleLead(<?php echo (int)$lead['id']; ?>, '<?php echo addslashes(e($lead['name'])); ?>')" title="Delete" style="padding: 4px 8px;">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div style="display: flex; justify-content: space-between; align-items: center; padding: 14px 18px; border-top: 1px solid var(--color-border); flex-wrap: wrap; gap: 8px;">
                <div style="font-size: 13px; color: var(--color-text-secondary);">
                    Showing page <?php echo $page; ?> of <?php echo $totalPages; ?> (<?php echo $totalLeads; ?> total leads)
                </div>
                <div style="display: flex; gap: var(--space-2);">
                    <?php if ($page > 1): ?>
                        <a href="<?php echo BASE_URL; ?>/founder/sales.php?page=<?php echo $page - 1; ?>&counsellor_id=<?php echo urlencode($counsellorFilter); ?>&status=<?php echo urlencode($statusFilter); ?>&offering_type=<?php echo urlencode($offeringFilter); ?>&search=<?php echo urlencode($searchQuery); ?>" class="btn btn-sm btn-secondary">
                            &laquo; Previous
                        </a>
                    <?php endif; ?>
                    <?php if ($page < $totalPages): ?>
                        <a href="<?php echo BASE_URL; ?>/founder/sales.php?page=<?php echo $page + 1; ?>&counsellor_id=<?php echo urlencode($counsellorFilter); ?>&status=<?php echo urlencode($statusFilter); ?>&offering_type=<?php echo urlencode($offeringFilter); ?>&search=<?php echo urlencode($searchQuery); ?>" class="btn btn-sm btn-secondary">
                            Next &raquo;
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<!-- ================= FLOATING BULK SELECTION ACTION BAR ================= -->
<div id="floating-bulk-bar" style="display: none; position: fixed; bottom: 24px; left: 50%; transform: translateX(-50%); background: #1e293b; color: #fff; padding: 12px 20px; border-radius: 50px; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.5), 0 8px 10px -6px rgba(0,0,0,0.5); z-index: 1000; align-items: center; gap: 16px; border: 1px solid rgba(255,255,255,0.15); animation: fadeInUp 0.25s ease;">
    <div style="display: flex; align-items: center; gap: 8px;">
        <span class="badge" style="background: var(--color-primary); color: #fff; font-size: 12px; font-weight: 700; border-radius: 20px; padding: 3px 10px;" id="selected-leads-count">0</span>
        <span style="font-size: 13.5px; font-weight: 600;">Leads Selected</span>
    </div>
    
    <div style="height: 20px; width: 1px; background: rgba(255,255,255,0.2);"></div>

    <div style="display: flex; align-items: center; gap: 8px;">
        <select id="bulk_selected_counsellor" class="form-select" style="background: #0f172a; color: #fff; border-color: #334155; padding: 6px 12px; font-size: 12.5px; min-width: 170px; border-radius: 20px;">
            <option value="">-- Assign To Counsellor --</option>
            <option value="distribute_all" style="font-weight: 700; color: #38bdf8;">🔀 Divide Equally (All Active)</option>
            <?php foreach ($counsellors as $cn): ?>
                <option value="<?php echo $cn['id']; ?>">
                    <?php echo e($cn['name']); ?> (<?php echo ucwords(str_replace('_', ' ', e($cn['role']))); ?>)
                </option>
            <?php endforeach; ?>
        </select>

        <button type="button" class="btn btn-sm btn-primary" onclick="submitBulkAssignSelected()" style="border-radius: 20px; padding: 6px 14px; font-size: 12.5px; font-weight: 600;">
            <i class="fa-solid fa-user-check"></i> Assign
        </button>

        <button type="button" class="btn btn-sm btn-danger" onclick="submitBulkDeleteSelected()" style="border-radius: 20px; padding: 6px 12px; font-size: 12.5px; font-weight: 600;" title="Delete selected leads">
            <i class="fa-solid fa-trash"></i>
        </button>

        <button type="button" class="btn btn-sm btn-secondary" onclick="clearSelectedLeads()" style="border-radius: 20px; padding: 6px 10px; font-size: 12.5px; background: rgba(255,255,255,0.1); color: #fff; border: none;" title="Deselect All">
            <i class="fa-solid fa-xmark"></i>
        </button>
    </div>
</div>

<!-- ================= MODAL: BULK ASSIGN LEADS BY COUNT ================= -->
<div class="modal-overlay" id="modal-bulk-assign-count">
    <div class="modal" style="max-width: 580px;">
        <div class="modal-header">
            <h3 class="modal-title">
                <i class="fa-solid fa-bolt" style="color: #f59e0b; margin-right: 8px;"></i>Bulk Assign Leads by Count
            </h3>
            <button type="button" class="modal-close" onclick="closeBulkAssignCountModal()">&times;</button>
        </div>
        <form id="form-bulk-assign-count" onsubmit="submitBulkAssignByCount(event)">
            <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
            <input type="hidden" name="action" value="bulk_assign_by_count">
            <input type="hidden" name="offering_type" value="<?php echo e($offeringFilter); ?>">

            <div class="modal-body" style="padding: var(--space-5); display: flex; flex-direction: column; gap: var(--space-3);">
                <div style="background: rgba(245, 158, 11, 0.1); border-left: 4px solid #f59e0b; padding: 12px 14px; border-radius: var(--radius-sm); font-size: 13px; display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <div style="font-weight: 700; color: #f59e0b;"><i class="fa-solid fa-database" style="margin-right: 5px;"></i>Lead Pool Status</div>
                        <div style="font-size: 12px; color: var(--color-text-secondary); margin-top: 2px;">
                            Unassigned leads ready for assignment
                        </div>
                    </div>
                    <span class="badge" style="background: #f59e0b; color: #fff; font-size: 14px; font-weight: 700; padding: 4px 10px;">
                        <?php echo $globalMetrics['new_leads'] ?? 0; ?> Leads
                    </span>
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label required" style="display: flex; justify-content: space-between; align-items: center;">
                        <span>How many leads to assign?</span>
                        <span style="font-size: 11.5px; color: var(--color-text-muted);">Quick presets:</span>
                    </label>
                    <div style="display: flex; gap: 8px; margin-bottom: 8px;">
                        <input type="number" name="count" id="assign_lead_count_input" class="form-input" min="1" max="10000" placeholder="e.g. 50" required style="font-size: 16px; font-weight: 700; flex: 1;">
                    </div>
                    <div style="display: flex; gap: 6px; flex-wrap: wrap;">
                        <button type="button" class="btn btn-sm btn-outline" onclick="setCountPreset(10)" style="padding: 3px 9px; font-size: 12px;">+10</button>
                        <button type="button" class="btn btn-sm btn-outline" onclick="setCountPreset(25)" style="padding: 3px 9px; font-size: 12px;">+25</button>
                        <button type="button" class="btn btn-sm btn-outline" onclick="setCountPreset(50)" style="padding: 3px 9px; font-size: 12px;">+50</button>
                        <button type="button" class="btn btn-sm btn-outline" onclick="setCountPreset(100)" style="padding: 3px 9px; font-size: 12px;">+100</button>
                        <button type="button" class="btn btn-sm btn-outline" onclick="setCountPreset(<?php echo max(1, (int)($globalMetrics['new_leads'] ?? 50)); ?>)" style="padding: 3px 9px; font-size: 12px; color: #f59e0b;">All Available</button>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Source Pool</label>
                    <select name="source_pool" class="form-select">
                        <option value="unassigned" selected>Unassigned Leads Only (Recommended)</option>
                        <option value="fresh">All Fresh / New Leads</option>
                    </select>
                </div>

                <div class="form-group" style="margin-bottom: 0; background: var(--color-bg-secondary); padding: 12px; border-radius: var(--radius-md); border: 1px solid var(--color-border);">
                    <label class="form-label" style="font-weight: 700; margin-bottom: 8px;">
                        <i class="fa-solid fa-users" style="color: var(--color-primary); margin-right: 5px;"></i>Assign To:
                    </label>

                    <div style="display: flex; gap: 12px; margin-bottom: 12px;">
                        <label style="display: flex; align-items: center; gap: 6px; cursor: pointer; font-size: 13px; font-weight: 600;">
                            <input type="radio" name="assign_mode" value="distribute" checked onchange="toggleCountAssignMode('distribute')">
                            <span>🔀 Divide Equally Among Team</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 6px; cursor: pointer; font-size: 13px; font-weight: 600;">
                            <input type="radio" name="assign_mode" value="single" onchange="toggleCountAssignMode('single')">
                            <span>👤 Single Counsellor</span>
                        </label>
                    </div>

                    <div id="count-distribute-wrap">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                            <span style="font-size: 12px; color: var(--color-text-secondary);">Distribute equally across:</span>
                            <button type="button" class="btn btn-sm btn-link" onclick="toggleAllCountCounsellors()" style="font-size: 11.5px; padding: 0;">Toggle Select All</button>
                        </div>
                        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(170px, 1fr)); gap: 6px; max-height: 140px; overflow-y: auto; padding: 4px; background: var(--color-bg-card); border-radius: var(--radius-sm); border: 1px solid var(--color-border);">
                            <?php foreach ($counsellors as $cn): ?>
                                <label style="display: flex; align-items: center; gap: 6px; font-size: 12px; padding: 4px 6px; border-radius: var(--radius-sm); cursor: pointer; background: rgba(99,102,241,0.04);">
                                    <input type="checkbox" name="distribute_counsellors[]" value="<?php echo $cn['id']; ?>" class="count-counsellor-cb" checked>
                                    <span style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?php echo e($cn['name']); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div id="count-single-wrap" style="display: none;">
                        <select name="assigned_to" class="form-select">
                            <option value="">-- Choose Counsellor --</option>
                            <?php foreach ($counsellors as $cn): ?>
                                <option value="<?php echo $cn['id']; ?>">
                                    <?php echo e($cn['name']); ?> (<?php echo ucwords(str_replace('_', ' ', e($cn['role']))); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <div class="modal-footer" style="padding: var(--space-4) var(--space-5);">
                <button type="button" class="btn btn-secondary" onclick="closeBulkAssignCountModal()">Cancel</button>
                <button type="submit" class="btn btn-warning" id="btn-save-count-assign" style="background: #f59e0b; color: #fff; border: none; font-weight: 600;">
                    <i class="fa-solid fa-bolt"></i> Assign Leads Now
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ================= MODALS ================= -->

<!-- 1. ADD NEW LEAD MODAL -->
<div class="modal-overlay" id="addLeadModal">
    <div class="modal" style="max-width: 600px;">
        <div class="modal-header">
            <h3 class="modal-title"><i class="fa-solid fa-user-plus" style="color: var(--color-primary); margin-right: 6px;"></i> Add New Lead</h3>
            <button type="button" class="modal-close" onclick="closeAddLeadModal()">×</button>
        </div>

        <form id="addLeadForm" onsubmit="submitAddLead(event)">
            <input type="hidden" name="action" value="add_lead">
            <?php echo csrfField(); ?>

            <div class="modal-body">
                <div class="form-row" style="margin-bottom: var(--space-4);">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">Prospect Name *</label>
                        <input type="text" name="name" class="form-input" required placeholder="Prospect Name">
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">Phone Number *</label>
                        <input type="tel" name="phone" class="form-input" required placeholder="Phone Number">
                    </div>
                </div>

                <div class="form-row" style="margin-bottom: var(--space-4);">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-input" placeholder="Email Address">
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">City</label>
                        <input type="text" name="city" class="form-input" placeholder="City">
                    </div>
                </div>

                <div class="form-row" style="margin-bottom: var(--space-4);">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">Type *</label>
                        <select name="offering_type" id="leadOfferingType" class="form-select" onchange="syncOfferingDropdown()">
                            <option value="course">Course</option>
                            <option value="service">Service</option>
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">Offering *</label>
                        <select name="course_service_select" id="leadOfferingSelect" class="form-select" onchange="syncOfferingName(this)" required>
                            <option value="">-- Choose Course --</option>
                            <?php foreach ($coursesList as $c): ?>
                                <option value="<?php echo (int)$c['id']; ?>" data-name="<?php echo e($c['name']); ?>" data-price="<?php echo (float)$c['price']; ?>">
                                    <?php echo e($c['name']); ?> (₹<?php echo number_format($c['price']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="hidden" name="course_service_id" id="leadCsId" value="">
                        <input type="hidden" name="course_service_name" id="leadCsName" value="">
                    </div>
                </div>

                <div class="form-row" style="margin-bottom: var(--space-4);">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">Assign To Counsellor</label>
                        <select name="assigned_to" class="form-select">
                            <?php foreach ($counsellors as $cs): ?>
                                <option value="<?php echo (int)$cs['id']; ?>"><?php echo e($cs['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="new_lead">✨ New Lead</option>
                            <option value="interested">⏳ Interested</option>
                            <option value="enrolled">🎉 Enrolled</option>
                            <option value="uninterested">❌ Uninterested</option>
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">Expected Value (₹)</label>
                        <input type="number" step="0.01" name="expected_revenue" id="leadExpRev" class="form-input" value="0.00">
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" class="form-textarea" rows="2" placeholder="Prospect notes..."></textarea>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeAddLeadModal()">Cancel</button>
                <button type="submit" class="btn btn-primary" id="addLeadBtn">Save Lead</button>
            </div>
        </form>
    </div>
</div>

<!-- 2. ASSIGN LEAD MODAL -->
<div class="modal-overlay" id="assignLeadModal">
    <div class="modal" style="max-width: 440px;">
        <div class="modal-header">
            <h3 class="modal-title">Assign Lead: <span id="assignLeadName" style="color: var(--color-primary);"></span></h3>
            <button type="button" class="modal-close" onclick="closeAssignModal()">×</button>
        </div>
        <form onsubmit="submitAssignLead(event)">
            <input type="hidden" id="assignLeadId" value="">
            <div class="modal-body">
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Select Sales Counsellor *</label>
                    <select id="assignToSelect" class="form-select" required>
                        <?php foreach ($counsellors as $cs): ?>
                            <option value="<?php echo (int)$cs['id']; ?>"><?php echo e($cs['name']); ?> (<?php echo ucfirst($cs['role']); ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeAssignModal()">Cancel</button>
                <button type="submit" class="btn btn-primary" id="assignSubmitBtn">Confirm Assignment</button>
            </div>
        </form>
    </div>
</div>

<!-- 3. ADD OFFERING MODAL -->
<div class="modal-overlay" id="addOfferingModal">
    <div class="modal" style="max-width: 520px;">
        <div class="modal-header">
            <h3 class="modal-title"><i class="fa-solid fa-plus-circle" style="color: var(--color-primary); margin-right: 6px;"></i> Add Course / Service</h3>
            <button type="button" class="modal-close" onclick="closeAddOfferingModal()">×</button>
        </div>
        <form onsubmit="submitAddOffering(event)">
            <input type="hidden" name="action" value="save_offering">
            <?php echo csrfField(); ?>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Title / Name *</label>
                    <input type="text" name="name" class="form-input" required placeholder="e.g. Data Analytics Bootcamp">
                </div>
                <div class="form-row" style="margin-bottom: var(--space-4);">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">Type</label>
                        <select name="type" class="form-select">
                            <option value="course">Course</option>
                            <option value="service">Service</option>
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">Category</label>
                        <input type="text" name="category" class="form-input" placeholder="e.g. AI / Web / Cloud">
                    </div>
                </div>
                <div class="form-row" style="margin-bottom: var(--space-4);">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">Price (₹)</label>
                        <input type="number" step="0.01" name="price" class="form-input" required placeholder="25000">
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">Duration</label>
                        <input type="text" name="duration" class="form-input" placeholder="e.g. 3 Months / Project">
                    </div>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-textarea" rows="2" placeholder="Course highlights / service scope..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeAddOfferingModal()">Cancel</button>
                <button type="submit" class="btn btn-primary" id="addOfferingBtn">Add to Catalogue</button>
            </div>
        </form>
    </div>
</div>

<!-- 4. LEAD DETAILS & ACTIVITY HISTORY MODAL -->
<div class="modal-overlay" id="leadDrawerModal">
    <div class="modal" style="max-width: 680px;">
        <div class="modal-header">
            <div>
                <h3 class="modal-title" id="drawerLeadName">Lead Profile</h3>
                <div style="font-size: 12px; color: var(--color-text-secondary); margin-top: 2px;" id="drawerLeadMeta"></div>
            </div>
            <button type="button" class="modal-close" onclick="closeLeadDrawer()">×</button>
        </div>
        <div class="modal-body" id="drawerContent">
            <div style="text-align: center; padding: 30px;"><i class="fa-solid fa-spinner fa-spin fa-2x"></i></div>
        </div>
    </div>
</div>

<script>
const coursesList = <?php echo json_encode($coursesList); ?>;
const servicesList = <?php echo json_encode($servicesList); ?>;

// Bulk Assign by Count Modal Handlers
function openBulkAssignCountModal() {
    const modal = document.getElementById('modal-bulk-assign-count');
    if (modal) {
        document.getElementById('assign_lead_count_input').value = '25';
        modal.classList.add('active');
    }
}
function closeBulkAssignCountModal() {
    const modal = document.getElementById('modal-bulk-assign-count');
    if (modal) modal.classList.remove('active');
}

function setCountPreset(num) {
    const input = document.getElementById('assign_lead_count_input');
    if (input) input.value = num;
}

function toggleCountAssignMode(mode) {
    const distWrap = document.getElementById('count-distribute-wrap');
    const singleWrap = document.getElementById('count-single-wrap');
    if (distWrap) distWrap.style.display = (mode === 'distribute') ? 'block' : 'none';
    if (singleWrap) singleWrap.style.display = (mode === 'single') ? 'block' : 'none';
}

function toggleAllCountCounsellors() {
    const cbs = document.querySelectorAll('.count-counsellor-cb');
    const allChecked = Array.from(cbs).every(cb => cb.checked);
    cbs.forEach(cb => cb.checked = !allChecked);
}

async function submitBulkAssignByCount(e) {
    e.preventDefault();
    const form = e.target;
    const btn = document.getElementById('btn-save-count-assign');
    const formData = new FormData(form);

    const count = parseInt(formData.get('count') || 0);
    if (count <= 0) {
        alert('Please enter a valid number of leads to assign.');
        return;
    }

    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Assigning Leads...';

    try {
        const res = await fetch('<?php echo BASE_URL; ?>/api/leads.php', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        if (data.success) {
            alert(data.message);
            closeBulkAssignCountModal();
            window.location.reload();
        } else {
            alert(data.message || 'Error assigning leads.');
        }
    } catch (err) {
        console.error(err);
        alert('An unexpected error occurred.');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-bolt"></i> Assign Leads Now';
    }
}

// Table Checkbox Multi-Selection Handlers
function toggleSelectAllLeads(masterCb) {
    const cbs = document.querySelectorAll('.lead-row-checkbox');
    cbs.forEach(cb => cb.checked = masterCb.checked);
    updateSelectedLeadsCount();
}

function updateSelectedLeadsCount() {
    const checkedBoxes = document.querySelectorAll('.lead-row-checkbox:checked');
    const count = checkedBoxes.length;
    const bar = document.getElementById('floating-bulk-bar');
    const countBadge = document.getElementById('selected-leads-count');
    
    if (countBadge) countBadge.textContent = count;
    if (bar) {
        bar.style.display = (count > 0) ? 'flex' : 'none';
    }

    const masterCb = document.getElementById('selectAllLeads');
    const allCbs = document.querySelectorAll('.lead-row-checkbox');
    if (masterCb && allCbs.length > 0) {
        masterCb.checked = (checkedBoxes.length === allCbs.length);
        masterCb.indeterminate = (checkedBoxes.length > 0 && checkedBoxes.length < allCbs.length);
    }
}

function clearSelectedLeads() {
    document.querySelectorAll('.lead-row-checkbox').forEach(cb => cb.checked = false);
    const masterCb = document.getElementById('selectAllLeads');
    if (masterCb) {
        masterCb.checked = false;
        masterCb.indeterminate = false;
    }
    updateSelectedLeadsCount();
}

async function submitBulkAssignSelected() {
    const checkedBoxes = document.querySelectorAll('.lead-row-checkbox:checked');
    const leadIds = Array.from(checkedBoxes).map(cb => cb.value);
    const targetSelect = document.getElementById('bulk_selected_counsellor');
    const targetValue = targetSelect ? targetSelect.value : '';

    if (leadIds.length === 0) {
        alert('Please select at least one lead from the table.');
        return;
    }
    if (!targetValue) {
        alert('Please select a counsellor to assign the selected leads to, or choose "Divide Equally".');
        return;
    }

    const formData = new FormData();
    formData.append('csrf_token', '<?php echo csrfToken(); ?>');
    formData.append('action', 'bulk_assign_selected');
    leadIds.forEach(id => formData.append('lead_ids[]', id));

    if (targetValue === 'distribute_all') {
        formData.append('assign_mode', 'distribute');
    } else {
        formData.append('assign_mode', 'single');
        formData.append('assigned_to', targetValue);
    }

    try {
        const res = await fetch('<?php echo BASE_URL; ?>/api/leads.php', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        if (data.success) {
            alert(data.message);
            window.location.reload();
        } else {
            alert(data.message || 'Error assigning selected leads.');
        }
    } catch (err) {
        console.error(err);
        alert('An unexpected error occurred.');
    }
}

function submitBulkDeleteSelected() {
    confirmBulkDeleteLeads();
}

function triggerDeleteAllLeads() {
    const counsellorId = "<?php echo e($counsellorFilter); ?>";
    const status = "<?php echo e($statusFilter); ?>";
    const offeringType = "<?php echo e($offeringFilter); ?>";
    const search = "<?php echo e($searchQuery); ?>";
    const hasFilter = counsellorId || status || offeringType || search;

    if (hasFilter) {
        if (confirm('Active filters are applied. Do you want to delete ONLY the filtered leads?\n\n- Click OK to delete only filtered leads\n- Click Cancel to delete ALL master leads in the database')) {
            confirmDeleteAllLeads('filtered', {
                counsellor_id: counsellorId,
                status: status,
                offering_type: offeringType,
                search: search
            });
            return;
        }
    }
    confirmDeleteAllLeads('all');
}

function openAddLeadModal(defaultType = 'course') {
    const modal = document.getElementById('addLeadModal');
    if (modal) {
        const offeringSelect = document.getElementById('leadOfferingType');
        if (offeringSelect) offeringSelect.value = defaultType;
        modal.classList.add('active');
        syncOfferingDropdown();
    }
}
function closeAddLeadModal() { document.getElementById('addLeadModal').classList.remove('active'); }

function openAddOfferingModal() { document.getElementById('addOfferingModal').classList.add('active'); }
function closeAddOfferingModal() { document.getElementById('addOfferingModal').classList.remove('active'); }

function openAssignModal(id, name, cur) { 
    document.getElementById('assignLeadId').value = id; 
    document.getElementById('assignLeadName').textContent = name; 
    if (cur > 0) document.getElementById('assignToSelect').value = cur; 
    document.getElementById('assignLeadModal').classList.add('active'); 
}
function closeAssignModal() { document.getElementById('assignLeadModal').classList.remove('active'); }
function closeLeadDrawer() { document.getElementById('leadDrawerModal').classList.remove('active'); }

function syncOfferingDropdown() {
    const type = document.getElementById('leadOfferingType').value;
    const select = document.getElementById('leadOfferingSelect');
    select.innerHTML = '<option value="">-- Choose ' + (type === 'course' ? 'Course' : 'Service') + ' --</option>';
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
        document.getElementById('leadExpRev').value = selectedOption.dataset.price || '0.00';
    } else {
        document.getElementById('leadCsId').value = '';
        document.getElementById('leadCsName').value = '';
    }
}

function submitAddLead(e) {
    e.preventDefault();
    const btn = document.getElementById('addLeadBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';

    const formData = new FormData(document.getElementById('addLeadForm'));
    fetch('<?php echo BASE_URL; ?>/api/leads.php', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
        if (data.success) { alert('✅ ' + data.message); window.location.reload(); }
        else { alert('❌ ' + (data.message || 'Error creating lead.')); btn.disabled = false; btn.innerHTML = 'Save Lead'; }
    })
    .catch(err => { alert('❌ Request failed.'); btn.disabled = false; btn.innerHTML = 'Save Lead'; });
}

function submitAssignLead(e) {
    e.preventDefault();
    const btn = document.getElementById('assignSubmitBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';

    const formData = new FormData();
    formData.append('action', 'assign_lead');
    formData.append('lead_id', document.getElementById('assignLeadId').value);
    formData.append('assigned_to', document.getElementById('assignToSelect').value);
    formData.append('csrf_token', '<?php echo csrfToken(); ?>');

    fetch('<?php echo BASE_URL; ?>/api/leads.php', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
        if (data.success) { alert('✅ ' + data.message); window.location.reload(); }
        else { alert('❌ ' + (data.message || 'Error assigning lead.')); btn.disabled = false; btn.innerHTML = 'Confirm Assignment'; }
    })
    .catch(err => { alert('❌ Request failed.'); btn.disabled = false; btn.innerHTML = 'Confirm Assignment'; });
}

function submitAddOffering(e) {
    e.preventDefault();
    const btn = document.getElementById('addOfferingBtn');
    btn.disabled = true;

    const formData = new FormData(e.target);
    fetch('<?php echo BASE_URL; ?>/api/leads.php', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
        if (data.success) { alert('✅ ' + data.message); window.location.reload(); }
        else { alert('❌ ' + (data.message || 'Error')); btn.disabled = false; }
    })
    .catch(err => { alert('❌ Request failed.'); btn.disabled = false; });
}

function deleteLead(leadId, leadName) {
    confirmDeleteSingleLead(leadId, leadName);
}

function viewLeadDrawer(leadId) {
    document.getElementById('leadDrawerModal').classList.add('active');
    document.getElementById('drawerContent').innerHTML = '<div style="text-align: center; padding: 40px;"><i class="fa-solid fa-spinner fa-spin fa-2x" style="color: var(--color-primary);"></i></div>';

    fetch('<?php echo BASE_URL; ?>/api/leads.php?action=get_lead&lead_id=' + leadId)
    .then(res => res.json())
    .then(data => {
        if (!data.success || !data.lead) {
            document.getElementById('drawerContent').innerHTML = '<div style="color: var(--color-danger); padding: 20px;">Could not load details.</div>';
            return;
        }

        const l = data.lead;
        document.getElementById('drawerLeadName').textContent = l.name;
        document.getElementById('drawerLeadMeta').textContent = l.offering_type.toUpperCase() + ': ' + l.course_service_name + ' • Assigned to: ' + (l.assigned_to_name || 'Unassigned');

        let actHtml = '';
        if (l.activities && l.activities.length > 0) {
            l.activities.forEach(a => {
                actHtml += `
                    <div style="padding: 12px; background: var(--color-bg-tertiary); border-radius: var(--radius-md); margin-bottom: 8px; border-left: 3px solid var(--color-primary);">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                            <strong style="font-size: 12px;">${a.user_name} (${a.user_role})</strong>
                            <span style="font-size: 11px; color: var(--color-text-secondary);">${new Date(a.created_at).toLocaleString()}</span>
                        </div>
                        <div style="font-size: 13px;">${a.remarks}</div>
                    </div>
                `;
            });
        } else {
            actHtml = '<div style="font-size: 13px; color: var(--color-text-muted);">No prior activity.</div>';
        }

        document.getElementById('drawerContent').innerHTML = `
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 16px; background: var(--color-bg-tertiary); padding: 12px; border-radius: var(--radius-md);">
                <div><strong>Phone:</strong> ${l.phone}</div>
                <div><strong>Email:</strong> ${l.email || '—'}</div>
                <div><strong>City:</strong> ${l.city || '—'}</div>
                <div><strong>Status:</strong> ${l.status.replace('_', ' ')}</div>
            </div>
            <h4 style="margin: 0 0 10px 0; font-size: 14px;">Activity Trail</h4>
            <div>${actHtml}</div>
        `;
    })
    .catch(err => { document.getElementById('drawerContent').innerHTML = '<div style="color: var(--color-danger); padding: 20px;">Error.</div>'; });
}

function refreshMonthlyCommission(targetMonth) {
    const btn = document.getElementById('refreshCommissionBtn');
    const icon = document.getElementById('refreshCommissionIcon');
    if (!targetMonth) targetMonth = '<?php echo $selectedMonth; ?>';

    if (btn) btn.disabled = true;
    if (icon) icon.classList.add('fa-spin');

    const formData = new FormData();
    formData.append('action', 'refresh_monthly_commission');
    formData.append('month', targetMonth);
    formData.append('csrf_token', '<?php echo csrfToken(); ?>');

    fetch('<?php echo BASE_URL; ?>/api/leads.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (btn) btn.disabled = false;
        if (icon) icon.classList.remove('fa-spin');

        if (data.success) {
            alert('✅ ' + data.message);
            window.location.reload();
        } else {
            alert('❌ ' + (data.message || 'Failed to refresh commission.'));
        }
    })
    .catch(err => {
        if (btn) btn.disabled = false;
        if (icon) icon.classList.remove('fa-spin');
        alert('❌ Network error while refreshing commission.');
    });
}

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
require_once __DIR__ . '/../includes/lead_delete_modal.php';
require_once __DIR__ . '/../includes/sales_target_modal.php';
include __DIR__ . '/../includes/footer.php'; 
?>
