<?php
/**
 * HR — Sales & Leads CRM Executive Monitoring Hub
 * 
 * Comprehensive monitoring of all course & service leads, sales counsellors'
 * conversion rates, revenue generation, candidate databases, and lead reassignment.
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/middleware.php';

ensureSalesTablesExist();
requireRole([ROLE_HR, ROLE_FOUNDER, ROLE_MANAGER]);

$db = getDB();
$userId = getUserId();

// Filters
$counsellorFilter = get('counsellor_id', '');
$statusFilter = get('status', '');
$offeringFilter = get('offering_type', '');
$searchQuery = get('search', '');
$page = max(1, (int)get('page', 1));
$limit = 25;

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
    WHERE u.role IN ('sales_counsellor', 'employee', 'manager', 'hr') AND u.status = 'active'
    GROUP BY u.id, u.name, u.email, u.role
    HAVING total_assigned > 0 OR u.role = 'sales_counsellor'
    ORDER BY enrolled_count DESC, total_revenue DESC, total_assigned DESC
");
$counsellorPerf = $perfStmt ? $perfStmt->fetchAll() : [];

// Offerings list
$coursesList = getCourseServicesList('course');
$servicesList = getCourseServicesList('service');

$pageTitle = 'Sales & Leads CRM — HR Hub';
include __DIR__ . '/../includes/header.php';
?>

<!-- Header -->
<div class="page-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: var(--space-4); margin-bottom: var(--space-5);">
    <div>
        <h1 class="page-title"><i class="fa-solid fa-funnel-dollar" style="color: var(--color-primary); margin-right: 8px;"></i>Sales & Leads CRM Management</h1>
        <p class="page-subtitle">Supervise course admissions, IT service clients, candidate database, and team sales conversions.</p>
    </div>
    <div style="display: flex; gap: var(--space-2); flex-wrap: wrap;">
        <a href="<?php echo BASE_URL; ?>/hr/revenue.php" class="btn btn-warning" style="background: #f59e0b; color: #fff; border: none; font-weight: 600;">
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
        <a href="<?php echo BASE_URL; ?>/sales/offerings.php" class="btn btn-outline">
            <i class="fa-solid fa-graduation-cap"></i> Catalogue
        </a>
    </div>
</div>

<!-- Global KPI Cards (Symmetrical 4x2 Grid) -->
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

    <!-- 3. Interested / Warm Leads -->
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

    <!-- 5. Enrolled Revenue -->
    <div class="counsellor-kpi-card fade-in">
        <div class="counsellor-kpi-icon" style="background: rgba(16, 185, 129, 0.15); color: #10b981;">
            <i class="fa-solid fa-indian-rupee-sign"></i>
        </div>
        <div class="counsellor-kpi-info">
            <div class="counsellor-kpi-val" style="color: #10b981;">₹<?php echo number_format($globalMetrics['total_enrolled_value']); ?></div>
            <div class="counsellor-kpi-lbl">Enrolled Revenue</div>
        </div>
    </div>

    <!-- 6. Conversion Rate -->
    <div class="counsellor-kpi-card fade-in">
        <div class="counsellor-kpi-icon" style="background: rgba(168, 85, 247, 0.15); color: #a855f7;">
            <i class="fa-solid fa-bullseye"></i>
        </div>
        <div class="counsellor-kpi-info">
            <div class="counsellor-kpi-val"><?php echo $globalMetrics['conversion_rate']; ?>%</div>
            <div class="counsellor-kpi-lbl">Global Conversion %</div>
        </div>
    </div>

    <!-- 7. Today's Due Followups -->
    <div class="counsellor-kpi-card fade-in">
        <div class="counsellor-kpi-icon" style="background: rgba(239, 68, 68, 0.15); color: #ef4444;">
            <i class="fa-solid fa-phone-volume"></i>
        </div>
        <div class="counsellor-kpi-info">
            <div class="counsellor-kpi-val"><?php echo $globalMetrics['today_followups_count']; ?></div>
            <div class="counsellor-kpi-lbl">Today's Due Calls</div>
        </div>
    </div>

    <!-- 8. Active Counsellors -->
    <div class="counsellor-kpi-card fade-in">
        <div class="counsellor-kpi-icon" style="background: rgba(6, 182, 212, 0.15); color: #06b6d4;">
            <i class="fa-solid fa-user-tie"></i>
        </div>
        <div class="counsellor-kpi-info">
            <div class="counsellor-kpi-val"><?php echo count($counsellorPerf); ?></div>
            <div class="counsellor-kpi-lbl">Active Counsellors</div>
        </div>
    </div>
</div>

<!-- Counsellor Performance Leaderboard -->
<div class="data-table-container fade-in" style="background: var(--color-bg-card); border: 1px solid var(--color-border); border-radius: var(--radius-lg); overflow: hidden; margin-bottom: var(--space-6);">
    <div style="display: flex; justify-content: space-between; align-items: center; padding: var(--space-4) var(--space-5); border-bottom: 1px solid var(--color-border);">
        <h3 style="margin: 0; font-size: 16px; font-weight: 600; display: flex; align-items: center; gap: 8px;">
            <i class="fa-solid fa-trophy" style="color: #f59e0b;"></i>
            Counsellor Conversion & Performance Board
        </h3>
        <span style="font-size: 12px; color: var(--color-text-secondary);"><?php echo count($counsellorPerf); ?> Active Reps</span>
    </div>

    <?php if (empty($counsellorPerf)): ?>
        <div style="text-align: center; padding: var(--space-6); color: var(--color-text-muted);">
            No counsellors currently have assigned leads.
        </div>
    <?php else: ?>
        <div style="overflow-x: auto;">
            <table class="data-table" style="width: 100%;">
                <thead>
                    <tr>
                        <th>Counsellor Name</th>
                        <th>Role</th>
                        <th>Assigned</th>
                        <th>Fresh Leads</th>
                        <th>In Discussion</th>
                        <th>Enrolled</th>
                        <th>Lost</th>
                        <th>Conversion %</th>
                        <th>Enrolled Revenue</th>
                        <th style="text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($counsellorPerf as $cp): ?>
                        <?php 
                            $conv = ($cp['total_assigned'] > 0) ? round(($cp['enrolled_count'] / $cp['total_assigned']) * 100, 1) : 0;
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
                            <td style="text-align: right;">
                                <a href="<?php echo BASE_URL; ?>/hr/sales.php?counsellor_id=<?php echo (int)$cp['id']; ?>" class="btn btn-sm btn-outline" style="padding: 4px 10px; font-size: 12px;">
                                    Filter Leads
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- 4 DEDICATED SECTIONS SWITCHER -->
<div style="display: flex; gap: var(--space-2); margin-bottom: var(--space-4); flex-wrap: wrap;">
    <a href="<?php echo BASE_URL; ?>/hr/sales.php<?php echo !empty($counsellorFilter) ? '?counsellor_id=' . urlencode($counsellorFilter) : ''; ?>" class="btn btn-sm <?php echo empty($offeringFilter) ? 'btn-primary' : 'btn-secondary'; ?>" style="border-radius: var(--radius-full);">
        <i class="fa-solid fa-layer-group"></i> All Master Leads (<?php echo $globalMetrics['total_leads']; ?>)
    </a>
    <a href="<?php echo BASE_URL; ?>/hr/sales.php?offering_type=course<?php echo !empty($counsellorFilter) ? '&counsellor_id=' . urlencode($counsellorFilter) : ''; ?>" class="btn btn-sm <?php echo $offeringFilter === 'course' ? 'btn-primary' : 'btn-secondary'; ?>" style="border-radius: var(--radius-full);">
        <i class="fa-solid fa-graduation-cap"></i> Courses & Admissions (<?php echo $globalMetrics['course_leads']; ?>)
    </a>
    <a href="<?php echo BASE_URL; ?>/hr/sales.php?offering_type=service<?php echo !empty($counsellorFilter) ? '&counsellor_id=' . urlencode($counsellorFilter) : ''; ?>" class="btn btn-sm <?php echo $offeringFilter === 'service' ? 'btn-primary' : 'btn-secondary'; ?>" style="border-radius: var(--radius-full);">
        <i class="fa-solid fa-laptop-code"></i> IT & Digital Services (<?php echo $globalMetrics['service_leads']; ?>)
    </a>
    <a href="<?php echo BASE_URL; ?>/sales/raw_leads.php" class="btn btn-sm btn-secondary" style="border-radius: var(--radius-full);">
        <i class="fa-solid fa-address-book"></i> Raw / Manual Calling Bank (<?php echo $globalMetrics['manual_leads']; ?>)
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
                Filter, supervise counseling discussions, and reassign leads across the team.
            </p>
        </div>

        <!-- Filter Bar -->
        <form method="GET" action="<?php echo BASE_URL; ?>/hr/sales.php" class="filter-bar" style="margin-bottom: 0; padding: 0; border: none; background: transparent; display: flex; gap: var(--space-2); flex-wrap: wrap; align-items: center;">
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

            <?php if (!empty($offeringFilter)): ?>
                <input type="hidden" name="offering_type" value="<?php echo e($offeringFilter); ?>">
            <?php endif; ?>

            <input type="text" name="search" class="form-input" placeholder="Search leads..." value="<?php echo e($searchQuery); ?>" style="width: 160px; padding: 6px 10px; font-size: 12px;">

            <button type="submit" class="btn btn-sm btn-primary" style="padding: 6px 12px; font-size: 12px;">
                <i class="fa-solid fa-magnifying-glass"></i>
            </button>

            <?php if (!empty($counsellorFilter) || !empty($statusFilter) || !empty($offeringFilter) || !empty($searchQuery)): ?>
                <a href="<?php echo BASE_URL; ?>/hr/sales.php" class="btn btn-sm btn-ghost" title="Reset Filters" style="padding: 6px 8px;">
                    <i class="fa-solid fa-rotate-left"></i>
                </a>
            <?php endif; ?>
        </form>
    </div>

    <?php if (empty($leads)): ?>
        <div style="text-align: center; padding: var(--space-10) var(--space-4); color: var(--color-text-muted);">
            <i class="fa-solid fa-inbox" style="font-size: 40px; margin-bottom: var(--space-3); color: var(--color-text-muted);"></i>
            <h3 style="font-size: 16px; margin: 0 0 6px 0; color: var(--color-text-main);">No leads found</h3>
            <p style="margin: 0; font-size: 13px;">Try adjusting your filters or search query.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive leads-table-desktop" style="overflow-x: auto;">
            <table class="data-table" style="width: 100%;">
                <thead>
                    <tr>
                        <th style="width: 38px; text-align: center;">
                            <input type="checkbox" id="selectAllLeads" onchange="toggleSelectAllLeads(this)" title="Select all on this page">
                        </th>
                        <th style="min-width: 180px;">Prospect & Details</th>
                        <th>Offering</th>
                        <th>Assigned Counsellor</th>
                        <th>Status</th>
                        <th>Follow-up</th>
                        <th>Created</th>
                        <th style="text-align: right; min-width: 180px;">Admin Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($leads as $l): ?>
                        <tr id="lead-row-<?php echo (int)$l['id']; ?>">
                            <td style="text-align: center;">
                                <input type="checkbox" class="lead-row-checkbox lead-checkbox-<?php echo (int)$l['id']; ?>" value="<?php echo (int)$l['id']; ?>" onchange="onLeadCheckboxToggle(this)">
                            </td>
                            <td>
                                <div style="font-weight: 600; color: var(--color-text-white); font-size: 13.5px;">
                                    <?php echo e($l['name']); ?>
                                </div>
                                <div style="font-size: 11.5px; color: var(--color-text-secondary); margin-top: 2px; font-family: monospace;">
                                    <span><i class="fa-solid fa-phone" style="font-size: 9px;"></i> <?php echo e($l['phone']); ?></span>
                                    <?php if (!empty($l['email'])): ?><span> • <?php echo e($l['email']); ?></span><?php endif; ?>
                                </div>
                                <?php if (!empty($l['graduation'])): ?>
                                    <div style="font-size: 11px; color: var(--color-primary-light); margin-top: 2px;">
                                        <i class="fa-solid fa-user-graduate" style="font-size: 9px;"></i> <?php echo e($l['graduation']); ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge <?php echo $l['offering_type'] === 'course' ? 'badge-primary' : 'badge-warning'; ?>" style="font-size: 10px; margin-bottom: 4px; display: inline-block;">
                                    <?php echo ucfirst($l['offering_type']); ?>
                                </span>
                                <div style="font-size: 12.5px; font-weight: 500; color: var(--color-text-white);">
                                    <?php echo e($l['course_service_name']); ?>
                                </div>
                                <?php if ($l['expected_revenue'] > 0): ?>
                                    <div style="font-size: 11px; color: var(--color-success); font-weight: 600;">
                                        ₹<?php echo number_format($l['expected_revenue']); ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 6px;">
                                    <div style="width: 26px; height: 26px; border-radius: 50%; background: var(--color-bg-tertiary); color: var(--color-primary); font-size: 10px; font-weight: 700; display: flex; align-items: center; justify-content: center;">
                                        <?php echo strtoupper(substr($l['assigned_to_name'] ?? 'U', 0, 2)); ?>
                                    </div>
                                    <span style="font-size: 13px; font-weight: 500; color: var(--color-text-main);">
                                        <?php echo e($l['assigned_to_name'] ?: 'Unassigned'); ?>
                                    </span>
                                </div>
                            </td>
                            <td>
                                <?php echo getLeadStatusBadge($l['status']); ?>
                                <?php if ($l['status'] === 'enrolled' || (!empty($l['payment_plan']) && $l['payment_plan'] !== 'none')): ?>
                                    <div style="margin-top: 4px;">
                                        <?php echo getPaymentStatusBadge($l['payment_status'] ?? 'unpaid', (float)($l['paid_amount'] ?? 0), (float)($l['expected_revenue'] ?? 0), $l['payment_plan'] ?? 'none', $l['offering_type'] ?? 'course'); ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($l['next_followup_date'])): ?>
                                    <div style="font-size: 11.5px; color: var(--color-text-secondary);">
                                        <i class="fa-regular fa-clock" style="font-size: 9px;"></i> <?php echo formatDate($l['next_followup_date'], 'd M, h:i A'); ?>
                                    </div>
                                <?php else: ?>
                                    <span style="font-size: 11px; color: var(--color-text-muted);">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span style="font-size: 11.5px; color: var(--color-text-muted);"><?php echo formatDate($l['created_at'], 'd M Y'); ?></span>
                            </td>
                            <td style="text-align: right;">
                                <div style="display: inline-flex; gap: 4px; align-items: center;">
                                    <button type="button" class="btn btn-sm btn-ghost text-success" title="Send WhatsApp Message" style="padding: 4px 8px; color: var(--color-success); border: none; background: none; cursor: pointer;" onclick="openWhatsAppModal(<?php echo (int)$l['id']; ?>, '<?php echo addslashes(e($l['name'])); ?>', '<?php echo e($l['phone']); ?>', <?php echo ($l['is_manual'] ? 2 : 1); ?>, '<?php echo addslashes(e($l['course_service_name'] ?? 'UI/UX Design')); ?>')">
                                        <i class="fa-brands fa-whatsapp"></i>
                                    </button>
                                    <?php if ($l['status'] === 'enrolled' || $l['offering_type'] === 'course'): ?>
                                        <button type="button" class="btn btn-sm" onclick="openPaymentModal(<?php echo (int)$l['id']; ?>)" title="Manage Course Payment & Installments" style="padding: 4px 8px; font-size: 12px; background: rgba(16, 185, 129, 0.15); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.3);">
                                            <i class="fa-solid fa-credit-card"></i> Pay
                                        </button>
                                    <?php endif; ?>
                                    <button type="button" class="btn btn-sm btn-ghost" style="padding: 4px 8px; font-size: 12px;" onclick="viewLeadDrawer(<?php echo (int)$l['id']; ?>)" title="View Activity Notes & Timeline">
                                        <i class="fa-solid fa-clock-rotate-left"></i> Notes (<?php echo (int)$l['activity_count']; ?>)
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline" style="padding: 4px 8px; font-size: 12px;" onclick="openReassignModal(<?php echo (int)$l['id']; ?>, <?php echo (int)($l['assigned_to'] ?? 0); ?>, '<?php echo addslashes(e($l['name'])); ?>')" title="Reassign Lead">
                                        <i class="fa-solid fa-user-gear"></i> Reassign
                                    </button>
                                    <button type="button" class="btn btn-sm btn-ghost text-danger" style="padding: 4px 8px; font-size: 12px;" onclick="confirmDeleteSingleLead(<?php echo (int)$l['id']; ?>, '<?php echo addslashes(e($l['name'])); ?>')" title="Delete Lead">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Mobile Cards View (<= 768px) -->
        <div class="leads-cards-mobile">
            <div class="mobile-toolbar-header" style="border-radius: var(--radius-md); margin-bottom: 2px;">
                <label style="display: inline-flex; align-items: center; gap: 8px; cursor: pointer; user-select: none; margin: 0;">
                    <input type="checkbox" id="selectAllLeadsMobile" onchange="toggleSelectAllLeads(this)" style="cursor: pointer; width: 18px; height: 18px;">
                    <span>Select All on Page</span>
                </label>
                <span style="font-size: 12px; color: var(--color-text-secondary);"><?php echo count($leads); ?> leads</span>
            </div>

            <?php foreach ($leads as $l): 
                $leadId = (int)$l['id'];
                $leadName = e($l['name']);
            ?>
                <div class="lead-mobile-card" id="lead-card-<?php echo $leadId; ?>">
                    <!-- Card Top: Checkbox, Avatar, Name & Status -->
                    <div class="lead-card-header">
                        <div class="lead-card-identity">
                            <input type="checkbox" class="lead-card-checkbox lead-checkbox-<?php echo $leadId; ?>" value="<?php echo $leadId; ?>" onchange="onLeadCheckboxToggle(this)" style="cursor: pointer; width: 18px; height: 18px; flex-shrink: 0;">
                            <div class="lead-card-avatar">
                                <?php echo strtoupper(substr($l['name'] ?? 'P', 0, 2)); ?>
                            </div>
                            <div class="lead-card-name-wrap">
                                <div class="lead-card-name" onclick="viewLeadDrawer(<?php echo $leadId; ?>)">
                                    <?php echo $leadName; ?>
                                </div>
                                <?php if (!empty($l['graduation'])): ?>
                                    <div class="lead-card-grad-badge">
                                        <i class="fa-solid fa-user-graduate"></i> <?php echo e($l['graduation']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div id="status-card-<?php echo $leadId; ?>">
                            <?php echo getLeadStatusBadge($l['status']); ?>
                        </div>
                    </div>

                    <!-- Offering & Deal Value Bar -->
                    <div class="lead-card-offering-bar">
                        <div class="lead-card-offering-title">
                            <span class="badge <?php echo $l['offering_type'] === 'course' ? 'badge-primary' : 'badge-warning'; ?>" style="font-size: 10px; font-weight: 700; padding: 2px 6px;">
                                <i class="fa-solid <?php echo $l['offering_type'] === 'course' ? 'fa-graduation-cap' : 'fa-laptop-code'; ?>" style="margin-right: 2px;"></i><?php echo ucfirst($l['offering_type']); ?>
                            </span>
                            <span style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?php echo e($l['course_service_name']); ?></span>
                        </div>
                        <div class="lead-card-deal-val">
                            ₹<?php echo number_format($l['expected_revenue']); ?>
                        </div>
                    </div>

                    <!-- Payment Status Badge if enrolled -->
                    <?php if ($l['status'] === 'enrolled' || (!empty($l['payment_plan']) && $l['payment_plan'] !== 'none')): ?>
                        <div style="margin-bottom: 8px;">
                            <?php echo getPaymentStatusBadge($l['payment_status'] ?? 'unpaid', (float)($l['paid_amount'] ?? 0), (float)($l['expected_revenue'] ?? 0), $l['payment_plan'] ?? 'none', $l['offering_type'] ?? 'course'); ?>
                        </div>
                    <?php endif; ?>

                    <!-- Contact Details -->
                    <div class="lead-card-contacts">
                        <a href="tel:<?php echo e($l['phone']); ?>" class="lead-card-contact-item">
                            <i class="fa-solid fa-phone" style="color: var(--color-primary); font-size: 11px;"></i>
                            <span><?php echo e($l['phone']); ?></span>
                            <span style="font-size: 10px; background: rgba(99, 102, 241, 0.15); color: var(--color-primary-light); padding: 1px 5px; border-radius: 4px; margin-left: auto;">Tap to call</span>
                        </a>
                        <?php if (!empty($l['email'])): ?>
                            <a href="mailto:<?php echo e($l['email']); ?>" class="lead-card-contact-item">
                                <i class="fa-solid fa-envelope" style="color: var(--color-text-muted); font-size: 11px;"></i>
                                <span style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?php echo e($l['email']); ?></span>
                            </a>
                        <?php endif; ?>
                    </div>

                    <!-- Meta Grid (Assigned & Followup) -->
                    <div class="lead-card-meta-grid">
                        <div class="lead-meta-cell">
                            <span class="lead-meta-lbl">Assigned Counsellor</span>
                            <span class="lead-meta-val" style="font-weight: 600;">
                                <?php echo !empty($l['assigned_to_name']) ? e($l['assigned_to_name']) : '<span style="color: var(--color-danger);">Unassigned</span>'; ?>
                            </span>
                        </div>
                        <div class="lead-meta-cell">
                            <span class="lead-meta-lbl">Next Follow-up</span>
                            <span class="lead-meta-val">
                                <?php if ($l['next_followup_date']): ?>
                                    <span style="font-size: 11px;"><?php echo formatDate($l['next_followup_date'], 'd M, h:i A'); ?></span>
                                <?php else: ?>
                                    <span style="color: var(--color-text-muted); font-size: 11px;">—</span>
                                <?php endif; ?>
                            </span>
                        </div>
                    </div>

                    <!-- Action Buttons Grid -->
                    <div class="lead-card-actions">
                        <a href="tel:<?php echo e($l['phone']); ?>" class="lead-btn-action" style="color: var(--color-primary); border-color: rgba(99, 102, 241, 0.3); background: rgba(99, 102, 241, 0.08);">
                            <i class="fa-solid fa-phone"></i> Call
                        </a>
                        <button type="button" class="lead-btn-action" style="color: #25D366; border-color: rgba(37, 211, 102, 0.3); background: rgba(37, 211, 102, 0.08);" onclick="openWhatsAppModal(<?php echo (int)$l['id']; ?>, '<?php echo addslashes(e($l['name'])); ?>', '<?php echo e($l['phone']); ?>', <?php echo ($l['is_manual'] ? 2 : 1); ?>, '<?php echo addslashes(e($l['course_service_name'] ?? 'UI/UX Design')); ?>')">
                            <i class="fa-brands fa-whatsapp"></i> WhatsApp
                        </button>
                        <?php if ($l['status'] === 'enrolled' || $l['offering_type'] === 'course'): ?>
                            <button type="button" class="lead-btn-action" style="color: #10b981; border-color: rgba(16, 185, 129, 0.3); background: rgba(16, 185, 129, 0.08);" onclick="openPaymentModal(<?php echo (int)$l['id']; ?>)">
                                <i class="fa-solid fa-credit-card"></i> Payment
                            </button>
                        <?php endif; ?>
                        <button type="button" class="lead-btn-action" onclick="viewLeadDrawer(<?php echo (int)$l['id']; ?>)">
                            <i class="fa-solid fa-clock-rotate-left"></i> Notes (<?php echo (int)$l['activity_count']; ?>)
                        </button>
                        <button type="button" class="lead-btn-action" onclick="openReassignModal(<?php echo (int)$l['id']; ?>, <?php echo (int)($l['assigned_to'] ?? 0); ?>, '<?php echo addslashes(e($l['name'])); ?>')">
                            <i class="fa-solid fa-user-gear"></i> Reassign
                        </button>
                        <button type="button" class="lead-btn-action" style="color: var(--color-danger);" onclick="confirmDeleteSingleLead(<?php echo (int)$l['id']; ?>, '<?php echo addslashes(e($l['name'])); ?>')">
                            <i class="fa-solid fa-trash"></i> Delete
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if ($totalPages > 1): ?>
            <div style="display: flex; justify-content: space-between; align-items: center; padding: 14px 18px; border-top: 1px solid var(--color-border); flex-wrap: wrap; gap: 8px;">
                <div style="font-size: 13px; color: var(--color-text-secondary);">
                    Showing page <?php echo $page; ?> of <?php echo $totalPages; ?> (<?php echo $totalLeads; ?> leads total)
                </div>
                <div style="display: flex; gap: var(--space-2);">
                    <?php if ($page > 1): ?>
                        <a href="<?php echo BASE_URL; ?>/hr/sales.php?page=<?php echo $page - 1; ?>&counsellor_id=<?php echo urlencode($counsellorFilter); ?>&status=<?php echo urlencode($statusFilter); ?>&offering_type=<?php echo urlencode($offeringFilter); ?>&search=<?php echo urlencode($searchQuery); ?>" class="btn btn-sm btn-secondary">
                            &laquo; Prev
                        </a>
                    <?php endif; ?>
                    <?php if ($page < $totalPages): ?>
                        <a href="<?php echo BASE_URL; ?>/hr/sales.php?page=<?php echo $page + 1; ?>&counsellor_id=<?php echo urlencode($counsellorFilter); ?>&status=<?php echo urlencode($statusFilter); ?>&offering_type=<?php echo urlencode($offeringFilter); ?>&search=<?php echo urlencode($searchQuery); ?>" class="btn btn-sm btn-secondary">
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

<!-- 1. ADD LEAD MODAL -->
<div class="modal-overlay" id="addLeadModal">
    <div class="modal" style="max-width: 600px;">
        <div class="modal-header">
            <h3 class="modal-title"><i class="fa-solid fa-user-plus" style="color: var(--color-primary); margin-right: 6px;"></i> Add New Inbound Lead</h3>
            <button type="button" class="modal-close" onclick="closeAddLeadModal()">×</button>
        </div>
        <form id="addLeadForm" onsubmit="submitAddLead(event)">
            <input type="hidden" name="action" value="add_lead">
            <?php echo csrfField(); ?>

            <div class="modal-body">
                <div class="form-row" style="margin-bottom: var(--space-4);">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">Prospect Name *</label>
                        <input type="text" name="name" class="form-input" required placeholder="e.g. Rahul Sharma">
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">Graduation / Degree</label>
                        <input type="text" name="graduation" class="form-input" placeholder="e.g. B.Tech / BCA">
                    </div>
                </div>

                <div class="form-row" style="margin-bottom: var(--space-4);">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">Contact Phone No *</label>
                        <input type="tel" name="phone" class="form-input" required placeholder="e.g. 9876543210">
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">Email Address</label>
                        <input type="email" name="email" class="form-input" placeholder="e.g. rahul@gmail.com">
                    </div>
                </div>

                <div class="form-row" style="margin-bottom: var(--space-4);">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">Offering Type *</label>
                        <select name="offering_type" id="leadOfferingType" class="form-select" onchange="syncOfferingOptions()">
                            <option value="course">Training Course Program</option>
                            <option value="service">IT / Software Service</option>
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">Assign To Counsellor / Staff *</label>
                        <select name="assigned_to" class="form-select" required>
                            <?php foreach ($counsellors as $cs): ?>
                                <option value="<?php echo (int)$cs['id']; ?>" <?php echo $cs['id'] == $userId ? 'selected' : ''; ?>>
                                    <?php echo e($cs['name']); ?> (<?php echo ucfirst($cs['role']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: var(--space-4);">
                    <label class="form-label">Select Program / Package</label>
                    <select id="leadOfferingSelect" class="form-select" onchange="syncSelectedOffering(this)">
                        <option value="">-- Select from Catalogue --</option>
                        <?php foreach ($coursesList as $c): ?>
                            <option value="<?php echo (int)$c['id']; ?>" data-name="<?php echo e($c['name']); ?>" data-price="<?php echo (float)$c['price']; ?>">
                                <?php echo e($c['name']); ?> (₹<?php echo number_format($c['price']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="hidden" name="course_service_id" id="leadCsId" value="">
                    <input type="hidden" name="course_service_name" id="leadCsName" value="General Course Program">
                </div>

                <div class="form-row" style="margin-bottom: var(--space-4);">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">Lead Source</label>
                        <select name="source" class="form-select">
                            <option value="Website Inflow">Website Inflow</option>
                            <option value="WhatsApp Direct">WhatsApp Direct</option>
                            <option value="College Database">College Database</option>
                            <option value="Cold Calling">Cold Calling</option>
                            <option value="Instagram / Ads">Instagram / Ads</option>
                            <option value="Referral">Referral</option>
                            <option value="Direct Walk-in">Direct Walk-in</option>
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">Initial Status</label>
                        <select name="status" class="form-select">
                            <option value="new_lead">New Lead</option>
                            <option value="interested">Interested</option>
                            <option value="enrolled">Enrolled</option>
                        </select>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Counselor Notes / Remarks</label>
                    <textarea name="notes" class="form-textarea" rows="2" placeholder="Candidate background, inquiry details..."></textarea>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeAddLeadModal()">Cancel</button>
                <button type="submit" class="btn btn-primary" id="addLeadBtn">
                    <i class="fa-solid fa-plus"></i> Save Lead
                </button>
            </div>
        </form>
    </div>
</div>

<!-- 2. REASSIGN LEAD MODAL -->
<div class="modal-overlay" id="reassignModal">
    <div class="modal" style="max-width: 440px;">
        <div class="modal-header">
            <h3 class="modal-title"><i class="fa-solid fa-user-gear" style="color: var(--color-primary); margin-right: 6px;"></i> Reassign Lead</h3>
            <button type="button" class="modal-close" onclick="closeReassignModal()">×</button>
        </div>
        <form id="reassignForm" onsubmit="submitReassign(event)">
            <input type="hidden" name="action" value="assign_lead">
            <input type="hidden" name="lead_id" id="reassignLeadId" value="">
            <?php echo csrfField(); ?>

            <div class="modal-body">
                <p style="font-size: 13px; color: var(--color-text-secondary); margin-bottom: var(--space-4);">
                    Reassign lead for <strong id="reassignLeadName" style="color: var(--color-text-white);"></strong> to another counsellor:
                </p>

                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Select Sales Counsellor *</label>
                    <select name="assigned_to" id="reassignCounsellorSelect" class="form-select" required>
                        <option value="">-- Choose Team Member --</option>
                        <?php foreach ($counsellors as $cs): ?>
                            <option value="<?php echo (int)$cs['id']; ?>">
                                <?php echo e($cs['name']); ?> (<?php echo ucfirst($cs['role']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeReassignModal()">Cancel</button>
                <button type="submit" class="btn btn-primary" id="reassignSubmitBtn">Confirm Assignment</button>
            </div>
        </form>
    </div>
</div>

<!-- 3. LEAD DETAILS & ACTIVITY HISTORY MODAL -->
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
            <!-- Populated via AJAX -->
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

// Table & Mobile Card Checkbox Multi-Selection Handlers
function onLeadCheckboxToggle(cb) {
    const leadId = cb.value;
    const twinCbs = document.querySelectorAll('.lead-checkbox-' + leadId);
    twinCbs.forEach(c => c.checked = cb.checked);
    updateSelectedLeadsCount();
}

function getSelectedLeadIds() {
    const checked = document.querySelectorAll('.lead-row-checkbox:checked, .lead-card-checkbox:checked');
    const idSet = new Set();
    checked.forEach(cb => {
        if (cb.value) idSet.add(cb.value);
    });
    return Array.from(idSet);
}

function toggleSelectAllLeads(masterCb) {
    const state = masterCb.checked;
    document.querySelectorAll('.lead-row-checkbox, .lead-card-checkbox').forEach(cb => cb.checked = state);
    const masterDesk = document.getElementById('selectAllLeads');
    const masterMob = document.getElementById('selectAllLeadsMobile');
    if (masterDesk) { masterDesk.checked = state; masterDesk.indeterminate = false; }
    if (masterMob) { masterMob.checked = state; masterMob.indeterminate = false; }
    updateSelectedLeadsCount();
}

function updateSelectedLeadsCount() {
    const selectedIds = getSelectedLeadIds();
    const count = selectedIds.length;
    const bar = document.getElementById('floating-bulk-bar');
    const countBadge = document.getElementById('selected-leads-count');
    
    if (countBadge) countBadge.textContent = count;
    if (bar) {
        bar.style.display = (count > 0) ? 'flex' : 'none';
    }

    const allCheckboxes = document.querySelectorAll('.lead-row-checkbox, .lead-card-checkbox');
    const totalUniqueLeads = new Set(Array.from(allCheckboxes).map(cb => cb.value)).size;
    const masterDesk = document.getElementById('selectAllLeads');
    const masterMob = document.getElementById('selectAllLeadsMobile');

    [masterDesk, masterMob].forEach(master => {
        if (master && totalUniqueLeads > 0) {
            master.checked = (count === totalUniqueLeads);
            master.indeterminate = (count > 0 && count < totalUniqueLeads);
        }
    });
}

function clearSelectedLeads() {
    document.querySelectorAll('.lead-row-checkbox, .lead-card-checkbox').forEach(cb => cb.checked = false);
    const masterDesk = document.getElementById('selectAllLeads');
    const masterMob = document.getElementById('selectAllLeadsMobile');
    [masterDesk, masterMob].forEach(master => {
        if (master) {
            master.checked = false;
            master.indeterminate = false;
        }
    });
    updateSelectedLeadsCount();
}

async function submitBulkAssignSelected() {
    const leadIds = getSelectedLeadIds();
    const targetSelect = document.getElementById('bulk_selected_counsellor');
    const targetValue = targetSelect ? targetSelect.value : '';

    if (leadIds.length === 0) {
        alert('Please select at least one lead from the table or cards.');
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

function openAddLeadModal(defaultType = 'course') {
    const modal = document.getElementById('addLeadModal');
    if (modal) {
        const offeringSelect = document.getElementById('leadOfferingType');
        if (offeringSelect) offeringSelect.value = defaultType;
        modal.classList.add('active');
        syncOfferingOptions();
    }
}
function closeAddLeadModal() {
    document.getElementById('addLeadModal').classList.remove('active');
}

function openReassignModal(leadId, currentAssigneeId, leadName) {
    document.getElementById('reassignLeadId').value = leadId;
    document.getElementById('reassignLeadName').textContent = leadName;
    document.getElementById('reassignCounsellorSelect').value = currentAssigneeId || '';
    document.getElementById('reassignModal').classList.add('active');
}
function closeReassignModal() {
    document.getElementById('reassignModal').classList.remove('active');
}

function syncOfferingOptions() {
    const type = document.getElementById('leadOfferingType').value;
    const select = document.getElementById('leadOfferingSelect');
    select.innerHTML = '<option value="">-- Select from Catalogue --</option>';

    const list = (type === 'course') ? coursesList : servicesList;
    list.forEach(item => {
        const opt = document.createElement('option');
        opt.value = item.id;
        opt.textContent = `${item.name} (₹${Number(item.price).toLocaleString()})`;
        opt.setAttribute('data-name', item.name);
        opt.setAttribute('data-price', item.price);
        select.appendChild(opt);
    });

    document.getElementById('leadCsId').value = '';
    document.getElementById('leadCsName').value = (type === 'course') ? 'General Course Program' : 'General IT Service';
}

function syncSelectedOffering(elem) {
    const opt = elem.options[elem.selectedIndex];
    if (opt && opt.value) {
        document.getElementById('leadCsId').value = opt.value;
        document.getElementById('leadCsName').value = opt.getAttribute('data-name') || '';
    }
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
            alert(data.message || 'Error adding lead.');
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-plus"></i> Save Lead';
        }
    })
    .catch(err => {
        alert('Request failed.');
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-plus"></i> Save Lead';
    });
}

function submitReassign(e) {
    e.preventDefault();
    const btn = document.getElementById('reassignSubmitBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Assigning...';

    const form = document.getElementById('reassignForm');
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
            alert(data.message || 'Error reassigning lead.');
            btn.disabled = false;
            btn.innerHTML = 'Confirm Assignment';
        }
    })
    .catch(err => {
        alert('Request failed.');
        btn.disabled = false;
        btn.innerHTML = 'Confirm Assignment';
    });
}

function deleteLeadPermanent(leadId, leadName) {
    confirmDeleteSingleLead(leadId, leadName);
}

function viewLeadDrawer(leadId) {
    const modal = document.getElementById('leadDrawerModal');
    const content = document.getElementById('drawerContent');
    content.innerHTML = '<div style="text-align: center; padding: 40px;"><i class="fa-solid fa-spinner fa-spin fa-2x" style="color: var(--color-primary);"></i></div>';
    modal.classList.add('active');

    fetch(`<?php echo BASE_URL; ?>/api/leads.php?action=get_lead&lead_id=${leadId}`)
        .then(res => res.json())
        .then(data => {
            if (!data.success || !data.lead) {
                content.innerHTML = `<div class="alert alert-danger">${data.message || 'Failed to load details.'}</div>`;
                return;
            }
            const l = data.lead;
            document.getElementById('drawerLeadName').textContent = l.name;
            document.getElementById('drawerLeadMeta').textContent = `${l.offering_type.toUpperCase()} • ${l.course_service_name} • Assigned to: ${l.assigned_to_name || 'Unassigned'}`;

            let activitiesHtml = '';
            if (l.activities && l.activities.length > 0) {
                activitiesHtml = l.activities.map(a => `
                    <div style="padding: 10px 12px; background: var(--color-bg-secondary); border-radius: var(--radius-sm); border-left: 3px solid var(--color-primary); font-size: 12.5px; margin-bottom: 8px;">
                        <div style="display: flex; justify-content: space-between; font-size: 11px; color: var(--color-text-secondary); margin-bottom: 4px;">
                            <span><strong>${a.user_name || 'Staff'}</strong> (${a.activity_type.toUpperCase()})</span>
                            <span>${a.created_at}</span>
                        </div>
                        <div style="color: var(--color-text-main);">${a.remarks}</div>
                    </div>
                `).join('');
            } else {
                activitiesHtml = '<div style="font-size: 12px; color: var(--color-text-secondary); text-align: center; padding: 12px;">No activity logged yet.</div>';
            }

            content.innerHTML = `
                <div style="background: var(--color-bg-tertiary); padding: 14px; border-radius: var(--radius-md); display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 10px; margin-bottom: 16px;">
                    <div>
                        <span style="font-size: 11px; color: var(--color-text-secondary); display: block;">Status</span>
                        <strong>${l.status.toUpperCase()}</strong>
                    </div>
                    <div>
                        <span style="font-size: 11px; color: var(--color-text-secondary); display: block;">Phone</span>
                        <a href="tel:${l.phone}" style="font-weight: 600; text-decoration: none;">${l.phone}</a>
                    </div>
                    ${l.email ? `<div><span style="font-size: 11px; color: var(--color-text-secondary); display: block;">Email</span><a href="mailto:${l.email}">${l.email}</a></div>` : ''}
                    ${l.graduation ? `<div><span style="font-size: 11px; color: var(--color-text-secondary); display: block;">Graduation</span><strong>${l.graduation}</strong></div>` : ''}
                </div>
                <h4 style="font-size: 13px; font-weight: 600; margin-bottom: 8px;"><i class="fa-solid fa-clock-rotate-left"></i> Interaction Trail</h4>
                <div>${activitiesHtml}</div>
            `;
        })
        .catch(err => {
            content.innerHTML = '<div class="alert alert-danger">Error loading lead details.</div>';
        });
}

function closeLeadDrawer() {
    document.getElementById('leadDrawerModal').classList.remove('active');
}

// Close modals when clicking backdrop
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
include __DIR__ . '/../includes/footer.php'; 
?>
