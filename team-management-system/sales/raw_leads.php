<?php
/**
 * Sales Counsellor — Raw & Manual Calling Data Bank
 * 
 * Dedicated section for managing manually added candidate prospects, cold calling lists,
 * graduation tracking, instant click-to-call, WhatsApp outreach, and 1-click conversion.
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

$statusFilter = get('status', '');
$counsellorFilter = get('counsellor_id', '');
$offeringFilter = get('offering_type', '');
$searchQuery = get('search', '');
$page = max(1, (int)get('page', 1));
$limit = 20;

$filters = [
    'is_manual' => 1,
    'status' => $statusFilter,
    'search' => $searchQuery
];

if (!empty($offeringFilter) && in_array($offeringFilter, ['course', 'service'])) {
    $filters['offering_type'] = $offeringFilter;
}

if ($baseRole === ROLE_SALES_COUNSELLOR) {
    $filters['assigned_to'] = $userId;
} elseif (!empty($counsellorFilter)) {
    $filters['assigned_to'] = (int)$counsellorFilter;
}

$leadsResult = getLeads($filters, $page, $limit);
$leads = $leadsResult['data'];
$totalLeads = $leadsResult['total'];
$totalPages = $leadsResult['total_pages'];

// Sales Metrics for tabs and counters
$salesMetrics = getSalesMetrics($baseRole === ROLE_SALES_COUNSELLOR ? $userId : (!empty($counsellorFilter) ? (int)$counsellorFilter : null));

// Sales Counsellors list for assignment and filters
$counsellors = getSalesCounsellorsList();

// Courses & Services list for 1-click conversion
$coursesList = getCourseServicesList('course');
$servicesList = getCourseServicesList('service');
$canAddLeads = in_array($baseRole, [ROLE_FOUNDER, ROLE_MANAGER, ROLE_HR]);

// Direct open lead ID if provided
$selectedLeadId = (int)get('lead_id', 0);

$pageTitle = 'Raw & Manual Calling Prospects Bank';
include __DIR__ . '/../includes/header.php';
?>

<!-- Header -->
<div class="page-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: var(--space-4); margin-bottom: var(--space-5);">
    <div>
        <h1 class="page-title">
            <i class="fa-solid fa-address-book" style="color: #0284c7; margin-right: 8px;"></i>Raw & Manual Calling Prospect Bank
        </h1>
        <p class="page-subtitle">
            Dedicated calling repository for candidate records, college databases, and cold outreach. Track graduation, call, WhatsApp, and convert to active course/service leads.
        </p>
    </div>
    <div style="display: flex; gap: var(--space-2); flex-wrap: wrap;">
        <?php if ($baseRole !== ROLE_SALES_COUNSELLOR): ?>
            <button type="button" class="btn btn-warning" onclick="openBulkAssignCountModal()" style="background: #f59e0b; color: #fff; border: none; font-weight: 600;">
                <i class="fa-solid fa-bolt"></i> Bulk Assign by Count
            </button>
        <?php endif; ?>
        <?php if ($canAddLeads): ?>
            <button type="button" class="btn btn-primary" onclick="openAddManualLeadModal('course')" style="background: var(--color-primary); border-color: var(--color-primary);">
                <i class="fa-solid fa-graduation-cap"></i> + Add Course Prospect
            </button>
            <button type="button" class="btn btn-warning" onclick="openAddManualLeadModal('service')" style="background: #f59e0b; color: #fff; border: none; font-weight: 600;">
                <i class="fa-solid fa-laptop-code"></i> + Add Service Prospect
            </button>
            <button type="button" class="btn btn-secondary" onclick="openBulkImportModal()">
                <i class="fa-solid fa-file-import"></i> Bulk Upload Leads
            </button>
        <?php endif; ?>
    </div>
</div>

<!-- 4 DEDICATED SECTIONS SWITCHER CARDS -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); gap: var(--space-3); margin-bottom: var(--space-5);">
    <!-- Master All Leads Tab -->
    <a href="<?php echo BASE_URL; ?>/sales/leads.php" style="text-decoration: none; display: flex; align-items: center; justify-content: space-between; padding: 14px 18px; border-radius: var(--radius-lg); background: var(--color-bg-card); border: 2px solid var(--color-border); transition: all 0.2s;" class="fade-in">
        <div style="display: flex; align-items: center; gap: 12px;">
            <div style="width: 38px; height: 38px; border-radius: var(--radius-md); background: rgba(99, 102, 241, 0.15); color: var(--color-primary); display: flex; align-items: center; justify-content: center; font-size: 18px;">
                <i class="fa-solid fa-layer-group"></i>
            </div>
            <div>
                <div style="font-weight: 700; font-size: 14px; color: var(--color-text-main);">All Master Leads</div>
                <div style="font-size: 11.5px; color: var(--color-text-secondary);">Combined Stream</div>
            </div>
        </div>
        <span class="badge badge-secondary" style="font-size: 12px; font-weight: 700;"><?php echo $salesMetrics['total_leads']; ?></span>
    </a>

    <!-- Course Section Tab -->
    <a href="<?php echo BASE_URL; ?>/sales/leads.php?offering_type=course" style="text-decoration: none; display: flex; align-items: center; justify-content: space-between; padding: 14px 18px; border-radius: var(--radius-lg); background: var(--color-bg-card); border: 2px solid var(--color-border); transition: all 0.2s;" class="fade-in">
        <div style="display: flex; align-items: center; gap: 12px;">
            <div style="width: 38px; height: 38px; border-radius: var(--radius-md); background: rgba(99, 102, 241, 0.2); color: var(--color-primary); display: flex; align-items: center; justify-content: center; font-size: 18px;">
                <i class="fa-solid fa-graduation-cap"></i>
            </div>
            <div>
                <div style="font-weight: 700; font-size: 14px; color: var(--color-text-main);">Courses Section</div>
                <div style="font-size: 11.5px; color: var(--color-text-secondary);">Admissions Pipeline</div>
            </div>
        </div>
        <span class="badge badge-primary" style="font-size: 12px; font-weight: 700;"><?php echo $salesMetrics['course_leads']; ?> Leads</span>
    </a>

    <!-- Service Section Tab -->
    <a href="<?php echo BASE_URL; ?>/sales/leads.php?offering_type=service" style="text-decoration: none; display: flex; align-items: center; justify-content: space-between; padding: 14px 18px; border-radius: var(--radius-lg); background: var(--color-bg-card); border: 2px solid var(--color-border); transition: all 0.2s;" class="fade-in">
        <div style="display: flex; align-items: center; gap: 12px;">
            <div style="width: 38px; height: 38px; border-radius: var(--radius-md); background: rgba(245, 158, 11, 0.2); color: #f59e0b; display: flex; align-items: center; justify-content: center; font-size: 18px;">
                <i class="fa-solid fa-laptop-code"></i>
            </div>
            <div>
                <div style="font-weight: 700; font-size: 14px; color: var(--color-text-main);">Services Section</div>
                <div style="font-size: 11.5px; color: var(--color-text-secondary);">IT Projects & Clients</div>
            </div>
        </div>
        <span class="badge badge-warning" style="font-size: 12px; font-weight: 700;"><?php echo $salesMetrics['service_leads']; ?> Leads</span>
    </a>

    <!-- Raw / Manual Calling Section Tab (ACTIVE) -->
    <a href="<?php echo BASE_URL; ?>/sales/raw_leads.php" style="text-decoration: none; display: flex; align-items: center; justify-content: space-between; padding: 14px 18px; border-radius: var(--radius-lg); background: rgba(2, 132, 199, 0.12); border: 2px solid #0284c7; transition: all 0.2s; box-shadow: 0 4px 12px rgba(2, 132, 199, 0.15);" class="fade-in">
        <div style="display: flex; align-items: center; gap: 12px;">
            <div style="width: 38px; height: 38px; border-radius: var(--radius-md); background: rgba(2, 132, 199, 0.2); color: #0284c7; display: flex; align-items: center; justify-content: center; font-size: 18px;">
                <i class="fa-solid fa-address-book"></i>
            </div>
            <div>
                <div style="font-weight: 700; font-size: 14px; color: var(--color-text-main);">Manual / Cold Data</div>
                <div style="font-size: 11.5px; color: #0284c7; font-weight: 600;">Dedicated Calling Bank</div>
            </div>
        </div>
        <span class="badge" style="background: #0284c7; color: #fff; font-size: 12px; font-weight: 700;"><?php echo $salesMetrics['manual_leads']; ?> Prospects</span>
    </a>
</div>

<!-- Raw Calling KPI Metrics (Symmetrical 4-Card Grid) -->
<div class="counsellor-kpi-grid" style="margin-bottom: var(--space-5);">
    <div class="counsellor-kpi-card fade-in">
        <div class="counsellor-kpi-icon" style="background: rgba(2, 132, 199, 0.15); color: #0284c7;">
            <i class="fa-solid fa-address-book"></i>
        </div>
        <div class="counsellor-kpi-info">
            <div class="counsellor-kpi-val"><?php echo $salesMetrics['manual_leads']; ?></div>
            <div class="counsellor-kpi-lbl">Total Raw Prospects</div>
        </div>
    </div>

    <div class="counsellor-kpi-card fade-in">
        <div class="counsellor-kpi-icon" style="background: rgba(59, 130, 246, 0.15); color: #3b82f6;">
            <i class="fa-solid fa-phone-volume"></i>
        </div>
        <div class="counsellor-kpi-info">
            <div class="counsellor-kpi-val"><?php echo $salesMetrics['manual_new']; ?></div>
            <div class="counsellor-kpi-lbl">Fresh / Uncalled</div>
        </div>
    </div>

    <div class="counsellor-kpi-card fade-in">
        <div class="counsellor-kpi-icon" style="background: rgba(245, 158, 11, 0.15); color: #f59e0b;">
            <i class="fa-solid fa-comments"></i>
        </div>
        <div class="counsellor-kpi-info">
            <div class="counsellor-kpi-val"><?php echo $salesMetrics['manual_interested']; ?></div>
            <div class="counsellor-kpi-lbl">Interested / In Talks</div>
        </div>
    </div>

    <div class="counsellor-kpi-card fade-in">
        <div class="counsellor-kpi-icon" style="background: rgba(16, 185, 129, 0.15); color: #10b981;">
            <i class="fa-solid fa-check-double"></i>
        </div>
        <div class="counsellor-kpi-info">
            <div class="counsellor-kpi-val"><?php echo $salesMetrics['manual_enrolled']; ?></div>
            <div class="counsellor-kpi-lbl">Converted Successfully</div>
        </div>
    </div>
</div>

<!-- Search & Calling Status Filter -->
<div class="card" style="margin-bottom: var(--space-4); padding: var(--space-3) var(--space-4);">
    <form method="GET" action="" style="display: flex; flex-wrap: wrap; gap: var(--space-3); align-items: center; justify-content: space-between;">
        <div style="display: flex; flex-wrap: wrap; gap: var(--space-3); align-items: center; flex: 1; min-width: 280px;">
            <!-- Search -->
            <div style="position: relative; flex: 1; min-width: 220px;">
                <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--color-text-muted); font-size: 14px;"></i>
                <input type="text" name="search" class="form-input" placeholder="Search candidate, graduation, phone, email..." value="<?php echo e($searchQuery); ?>" style="padding-left: 36px; width: 100%;">
            </div>

            <!-- Status -->
            <div style="min-width: 170px;">
                <select name="status" class="form-select" onchange="this.form.submit()">
                    <option value="">All Calling Statuses</option>
                    <option value="new_lead" <?php echo $statusFilter === 'new_lead' ? 'selected' : ''; ?>>Fresh / Uncalled</option>
                    <option value="interested" <?php echo $statusFilter === 'interested' ? 'selected' : ''; ?>>Interested / In-Talks</option>
                    <option value="enrolled" <?php echo $statusFilter === 'enrolled' ? 'selected' : ''; ?>>Converted / Enrolled</option>
                    <option value="uninterested" <?php echo $statusFilter === 'uninterested' ? 'selected' : ''; ?>>Not Interested / Dropped</option>
                </select>
            </div>

            <!-- Category Filter (Courses vs Services) -->
            <div style="min-width: 170px;">
                <select name="offering_type" class="form-select" onchange="this.form.submit()">
                    <option value="">All Categories</option>
                    <option value="course" <?php echo $offeringFilter === 'course' ? 'selected' : ''; ?>>🎓 Course Prospects</option>
                    <option value="service" <?php echo $offeringFilter === 'service' ? 'selected' : ''; ?>>💻 Service Prospects</option>
                </select>
            </div>

            <?php if ($baseRole !== ROLE_SALES_COUNSELLOR): ?>
                <!-- Counsellor Filter -->
                <div style="min-width: 180px;">
                    <select name="counsellor_id" class="form-select" onchange="this.form.submit()">
                        <option value="">All Team Counsellors</option>
                        <?php foreach ($counsellors as $cn): ?>
                            <option value="<?php echo $cn['id']; ?>" <?php echo $counsellorFilter == $cn['id'] ? 'selected' : ''; ?>>
                                <?php echo e($cn['name']); ?> (<?php echo ucwords(str_replace('_', ' ', e($cn['role']))); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
        </div>

        <div style="display: flex; gap: var(--space-2);">
            <button type="submit" class="btn btn-primary">
                <i class="fa-solid fa-filter"></i> Filter
            </button>
            <?php if (!empty($searchQuery) || !empty($statusFilter) || !empty($counsellorFilter) || !empty($offeringFilter)): ?>
                <a href="<?php echo BASE_URL; ?>/sales/raw_leads.php" class="btn btn-secondary" title="Reset Filters">
                    <i class="fa-solid fa-rotate-left"></i> Reset
                </a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Raw Prospects Table Card -->
<div class="card fade-in" style="margin-bottom: var(--space-6);">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: var(--space-3); padding: var(--space-4) var(--space-5); border-bottom: 1px solid var(--color-border);">
        <div style="display: flex; align-items: center; gap: 8px;">
            <h3 class="card-title" style="margin: 0; font-size: 16px; font-weight: 600;"><i class="fa-solid fa-list-ol" style="color: #0284c7; margin-right: 6px;"></i>Manual Calling Database</h3>
            <span class="badge badge-secondary" style="font-size: 11px;"><?php echo $totalLeads; ?> Prospects</span>
        </div>
        <div style="font-size: 12px; color: var(--color-text-secondary); display: flex; align-items: center; gap: 14px; flex-wrap: wrap;">
            <span><i class="fa-solid fa-phone" style="color: #10b981; margin-right: 4px;"></i>Click-to-Call</span>
            <span><i class="fa-brands fa-whatsapp" style="color: #25D366; margin-right: 4px;"></i>Instant WhatsApp</span>
            <span><i class="fa-solid fa-arrow-right-arrow-left" style="color: var(--color-primary); margin-right: 4px;"></i>1-Click Convert</span>
        </div>
    </div>

    <div class="table-responsive">
        <table class="data-table" id="raw-leads-table">
            <thead>
                <tr>
                    <?php if ($baseRole !== ROLE_SALES_COUNSELLOR): ?>
                        <th style="width: 38px; text-align: center;">
                            <input type="checkbox" id="selectAllLeads" onchange="toggleSelectAllLeads(this)" style="cursor: pointer; width: 16px; height: 16px;">
                        </th>
                    <?php endif; ?>
                    <th style="width: 40px;">#</th>
                    <th>Candidate & Graduation</th>
                    <th>Contact Details</th>
                    <th>Calling Status</th>
                    <th>Last Contact / Notes</th>
                    <th>Assigned To</th>
                    <th style="text-align: right; min-width: 240px;">Actions & Outreach</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($leads)): ?>
                    <tr>
                        <td colspan="<?php echo $baseRole !== ROLE_SALES_COUNSELLOR ? '8' : '7'; ?>" style="text-align: center; padding: 48px 16px;">
                            <div style="width: 64px; height: 64px; border-radius: 50%; background: rgba(2, 132, 199, 0.1); color: #0284c7; display: flex; align-items: center; justify-content: center; font-size: 28px; margin: 0 auto 16px;">
                                <i class="fa-solid fa-address-book"></i>
                            </div>
                            <h4 style="margin-bottom: 6px; font-weight: 600;">No Manual Calling Prospects Found</h4>
                            <p style="color: var(--color-text-secondary); max-width: 440px; margin: 0 auto 20px; font-size: 13.5px;">
                                Add individual candidate records or paste bulk raw calling lists to start tele-counseling and outreach.
                            </p>
                            <?php if ($canAddLeads): ?>
                                <div style="display: flex; gap: 10px; justify-content: center; flex-wrap: wrap;">
                                    <button type="button" class="btn btn-primary" onclick="openAddManualLeadModal()">
                                        <i class="fa-solid fa-user-plus"></i> Add Single Prospect
                                    </button>
                                    <button type="button" class="btn btn-secondary" onclick="openBulkImportModal()">
                                        <i class="fa-solid fa-file-import"></i> Paste Bulk Raw Data
                                    </button>
                                </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($leads as $idx => $lead): 
                        $leadId = (int)$lead['id'];
                        $leadName = e($lead['name']);
                        $waUrl = getWhatsAppLeadUrl($lead['phone'], $lead['name']);
                    ?>
                        <tr id="lead-row-<?php echo $leadId; ?>">
                            <?php if ($baseRole !== ROLE_SALES_COUNSELLOR): ?>
                                <td style="text-align: center;">
                                    <input type="checkbox" class="lead-row-checkbox" value="<?php echo $leadId; ?>" onchange="updateSelectedLeadsCount()" style="cursor: pointer; width: 16px; height: 16px;">
                                </td>
                            <?php endif; ?>
                            <td style="color: var(--color-text-muted); font-size: 12px; font-weight: 600;">
                                <?php echo ($page - 1) * $limit + $idx + 1; ?>
                            </td>

                            <!-- Candidate & Graduation -->
                            <td>
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <div class="user-avatar" style="width: 36px; height: 36px; font-size: 13px; font-weight: 700; background: rgba(2, 132, 199, 0.15); color: #0284c7; flex-shrink: 0;">
                                        <?php echo e(getInitials($lead['name'])); ?>
                                    </div>
                                    <div>
                                        <div style="font-weight: 600; font-size: 13.5px; color: var(--color-text-main); cursor: pointer;" onclick="openLeadDrawer(<?php echo $leadId; ?>)">
                                            <?php echo $leadName; ?>
                                        </div>
                                        <div style="margin-top: 4px; display: flex; gap: 4px; flex-wrap: wrap; align-items: center;">
                                            <?php if (($lead['offering_type'] ?? '') === 'service'): ?>
                                                <span class="badge badge-warning" style="font-size: 10.5px; font-weight: 700; padding: 2px 6px;">
                                                    <i class="fa-solid fa-laptop-code" style="margin-right: 3px;"></i>Service
                                                </span>
                                            <?php else: ?>
                                                <span class="badge badge-primary" style="font-size: 10.5px; font-weight: 700; padding: 2px 6px;">
                                                    <i class="fa-solid fa-graduation-cap" style="margin-right: 3px;"></i>Course
                                                </span>
                                            <?php endif; ?>
                                            <?php if (!empty($lead['graduation'])): ?>
                                                <span class="badge" style="background: rgba(2, 132, 199, 0.12); color: #0284c7; font-size: 11px; font-weight: 600; padding: 2px 7px;">
                                                    <i class="fa-solid fa-user-graduate" style="margin-right: 3px;"></i><?php echo e($lead['graduation']); ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <!-- Contact Details -->
                            <td>
                                <div style="font-size: 13px; font-weight: 600; display: flex; align-items: center; gap: 6px;">
                                    <i class="fa-solid fa-phone" style="color: var(--color-text-muted); font-size: 11px;"></i>
                                    <a href="tel:<?php echo e($lead['phone']); ?>" style="text-decoration: none; color: inherit;" title="Click to call via system dialer">
                                        <?php echo e($lead['phone']); ?>
                                    </a>
                                </div>
                                <?php if (!empty($lead['email'])): ?>
                                    <div style="font-size: 11.5px; color: var(--color-text-secondary); margin-top: 2px; display: flex; align-items: center; gap: 6px;">
                                        <i class="fa-solid fa-envelope" style="color: var(--color-text-muted); font-size: 11px;"></i>
                                        <a href="mailto:<?php echo e($lead['email']); ?>" style="text-decoration: none; color: inherit;">
                                            <?php echo e($lead['email']); ?>
                                        </a>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($lead['city'])): ?>
                                    <div style="font-size: 11px; color: var(--color-text-muted); margin-top: 2px;">
                                        <i class="fa-solid fa-location-dot" style="font-size: 10px; margin-right: 3px;"></i><?php echo e($lead['city']); ?>
                                    </div>
                                <?php endif; ?>
                            </td>

                            <!-- Status -->
                            <td id="status-cell-<?php echo $leadId; ?>">
                                <?php echo getLeadStatusBadge($lead['status']); ?>
                                <div style="font-size: 11px; color: var(--color-text-muted); margin-top: 4px;">
                                    <i class="fa-solid fa-database" style="margin-right: 3px;"></i><?php echo e($lead['source'] ?: 'Manual Entry'); ?>
                                </div>
                            </td>

                            <!-- Last Contact & Notes -->
                            <td>
                                <?php if (!empty($lead['last_contact_at'])): ?>
                                    <div style="font-size: 11.5px; color: var(--color-text-secondary);">
                                        <i class="fa-regular fa-clock" style="margin-right: 3px;"></i><?php echo timeAgo($lead['last_contact_at']); ?>
                                    </div>
                                <?php else: ?>
                                    <div style="font-size: 11.5px; color: #f59e0b; font-weight: 600;">
                                        <i class="fa-solid fa-bolt" style="margin-right: 3px;"></i>Fresh / Not Called Yet
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($lead['notes'])): ?>
                                    <div style="font-size: 11px; color: var(--color-text-muted); margin-top: 3px; max-width: 200px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?php echo e($lead['notes']); ?>">
                                        "<?php echo e($lead['notes']); ?>"
                                    </div>
                                <?php endif; ?>
                            </td>

                            <!-- Assigned Counsellor -->
                            <td>
                                <div style="font-size: 12.5px; font-weight: 600; color: var(--color-text-main);">
                                    <?php echo e($lead['assigned_to_name'] ?: 'Unassigned'); ?>
                                </div>
                            </td>

                            <!-- Action Shortcuts -->
                            <td style="text-align: right;">
                                <div style="display: inline-flex; gap: 6px; align-items: center; flex-wrap: nowrap;">
                                    <!-- 1-Click Call -->
                                    <a href="tel:<?php echo e($lead['phone']); ?>" class="btn btn-sm btn-outline-primary" style="padding: 4px 8px; font-size: 12px;" title="Direct Phone Call" onclick="logQuickCall(<?php echo $leadId; ?>)">
                                        <i class="fa-solid fa-phone"></i> Call
                                    </a>

                                    <!-- 1-Click WhatsApp with Template Dropdown -->
                                    <button type="button" class="btn btn-sm btn-outline-success" style="padding: 4px 8px; font-size: 12px; color: #25D366; border-color: rgba(37, 211, 102, 0.4);" title="Send WhatsApp message" onclick="openWhatsAppModal(<?php echo $leadId; ?>, '<?php echo addslashes(e($lead['name'])); ?>', '<?php echo e($lead['phone']); ?>', 2, '<?php echo addslashes(e($lead['course_service_name'] ?? 'UI/UX Design')); ?>')">
                                        <i class="fa-brands fa-whatsapp"></i> WhatsApp
                                    </button>

                                    <!-- 1-Click Convert to Course / Service Lead -->
                                    <button type="button" class="btn btn-sm btn-primary" style="padding: 4px 8px; font-size: 12px;" title="Convert to Course or Service Lead" onclick="openConvertModal(<?php echo $leadId; ?>, '<?php echo addslashes($lead['name']); ?>', '<?php echo addslashes($lead['graduation'] ?? ''); ?>')">
                                        <i class="fa-solid fa-arrow-right-arrow-left"></i> Convert
                                    </button>

                                    <!-- Update Status / Note -->
                                    <button type="button" class="btn btn-sm btn-secondary" style="padding: 4px 8px; font-size: 12px;" title="Update Status & Note" onclick="openStatusModal(<?php echo $leadId; ?>, '<?php echo $lead['status']; ?>')">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </button>

                                    <?php if ($baseRole !== ROLE_SALES_COUNSELLOR): ?>
                                        <!-- Reassign Lead (Founder / Manager / HR) -->
                                        <button type="button" class="btn btn-sm btn-outline-warning" style="padding: 4px 8px; font-size: 12px; color: #f59e0b; border-color: rgba(245, 158, 11, 0.4);" title="Reassign Prospect to Counsellor" onclick="openAssignModal(<?php echo $leadId; ?>, <?php echo (int)($lead['assigned_to'] ?? 0); ?>, '<?php echo addslashes($lead['name']); ?>')">
                                            <i class="fa-solid fa-user-gear"></i>
                                        </button>

                                        <!-- Delete Lead (Founder / Manager / HR) -->
                                        <button type="button" class="btn btn-sm btn-outline-danger" style="padding: 4px 8px; font-size: 12px; color: var(--color-danger); border-color: rgba(239, 68, 68, 0.4);" title="Delete Prospect" onclick="confirmDeleteSingleLead(<?php echo $leadId; ?>, '<?php echo addslashes($lead['name']); ?>')">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    <?php endif; ?>

                                    <!-- View Details Drawer -->
                                    <button type="button" class="btn btn-sm btn-secondary" style="padding: 4px 8px; font-size: 12px;" title="View Full Prospect History" onclick="openLeadDrawer(<?php echo $leadId; ?>)">
                                        <i class="fa-solid fa-eye"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
        <div class="card-footer" style="display: flex; justify-content: space-between; align-items: center; padding: var(--space-3) var(--space-4); border-top: 1px solid var(--color-border);">
            <div style="font-size: 13px; color: var(--color-text-secondary);">
                Showing <strong><?php echo ($page - 1) * $limit + 1; ?></strong> to <strong><?php echo min($totalLeads, $page * $limit); ?></strong> of <strong><?php echo $totalLeads; ?></strong> prospects
            </div>
            <div class="pagination" style="display: flex; gap: 4px;">
                <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                    <a href="?page=<?php echo $p; ?>&status=<?php echo urlencode($statusFilter); ?>&search=<?php echo urlencode($searchQuery); ?>&counsellor_id=<?php echo urlencode($counsellorFilter); ?>" 
                       class="btn btn-sm <?php echo $p === $page ? 'btn-primary' : 'btn-secondary'; ?>" style="padding: 4px 8px; font-size: 12px;">
                        <?php echo $p; ?>
                    </a>
                <?php endfor; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php if ($canAddLeads): ?>
<!-- ================= MODAL: ADD SINGLE MANUAL PROSPECT ================= -->
<div class="modal-overlay" id="modal-add-manual-lead">
    <div class="modal" style="max-width: 600px;">
        <div class="modal-header">
            <h3 class="modal-title" id="modal-add-prospect-title"><i class="fa-solid fa-user-plus" style="color: #0284c7; margin-right: 8px;"></i>Add Single Calling Prospect</h3>
            <button type="button" class="modal-close" onclick="closeAddManualLeadModal()">&times;</button>
        </div>
        <form id="form-add-manual-lead" onsubmit="submitAddManualLead(event)">
            <?php echo csrfField(); ?>
            <input type="hidden" name="action" value="add_lead">
            <input type="hidden" name="is_manual" value="1">
            <input type="hidden" name="source" value="Manual Entry">

            <div class="modal-body" style="padding: var(--space-5);">
                <!-- Target Category Selection: Courses vs IT Services -->
                <div class="form-group" style="margin-bottom: var(--space-4);">
                    <label class="form-label required">Target Category / Stream</label>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-3);">
                        <label id="manual-lead-cat-course-label" style="border: 2px solid var(--color-primary); padding: 10px 14px; border-radius: var(--radius-md); display: flex; align-items: center; gap: 8px; cursor: pointer; background: rgba(99, 102, 241, 0.08); transition: all 0.2s;">
                            <input type="radio" name="offering_type" value="course" id="manual-lead-cat-course" checked onchange="toggleManualLeadCategory('course')">
                            <div>
                                <div style="font-weight: 700; font-size: 13px; color: var(--color-text-main);"><i class="fa-solid fa-graduation-cap" style="color: var(--color-primary); margin-right: 4px;"></i>Course Program</div>
                                <div style="font-size: 11px; color: var(--color-text-secondary);">Student Training & Admissions</div>
                            </div>
                        </label>
                        <label id="manual-lead-cat-service-label" style="border: 2px solid var(--color-border); padding: 10px 14px; border-radius: var(--radius-md); display: flex; align-items: center; gap: 8px; cursor: pointer; background: var(--color-bg-card); transition: all 0.2s;">
                            <input type="radio" name="offering_type" value="service" id="manual-lead-cat-service" onchange="toggleManualLeadCategory('service')">
                            <div>
                                <div style="font-weight: 700; font-size: 13px; color: var(--color-text-main);"><i class="fa-solid fa-laptop-code" style="color: #f59e0b; margin-right: 4px;"></i>IT Services</div>
                                <div style="font-size: 11px; color: var(--color-text-secondary);">Client Software & Projects</div>
                            </div>
                        </label>
                    </div>
                </div>

                <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-3); margin-bottom: var(--space-3);">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label required" id="manual-lead-name-label">Candidate / Contact Name</label>
                        <input type="text" name="name" class="form-input" placeholder="e.g. Rahul Sharma" required autofocus>
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label required">Contact Phone No</label>
                        <input type="tel" name="phone" class="form-input" placeholder="e.g. 9876543210" required>
                    </div>
                </div>

                <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-3); margin-bottom: var(--space-3);">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" id="manual-lead-grad-label">Graduation / Degree</label>
                        <input type="text" name="graduation" id="manual-lead-grad-input" class="form-input" placeholder="e.g. B.Tech CSE (2024), BCA, B.Sc">
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">Email ID</label>
                        <input type="email" name="email" class="form-input" placeholder="e.g. rahul@example.com">
                    </div>
                </div>

                <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-3); margin-bottom: var(--space-3);">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">City / Location</label>
                        <input type="text" name="city" class="form-input" placeholder="e.g. Pune, Mumbai, Delhi">
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">Initial Calling Status</label>
                        <select name="status" class="form-select">
                            <option value="new_lead" selected>Fresh / Not Called</option>
                            <option value="interested">Interested / Follow-up</option>
                            <option value="uninterested">Not Interested / Dropped</option>
                        </select>
                    </div>
                </div>

                <?php if ($baseRole !== ROLE_SALES_COUNSELLOR): ?>
                    <div class="form-group" style="margin-bottom: var(--space-3);">
                        <label class="form-label">Assign To Counsellor / Staff</label>
                        <select name="assigned_to" class="form-select">
                            <?php foreach ($counsellors as $cn): ?>
                                <option value="<?php echo $cn['id']; ?>" <?php echo $cn['id'] == $userId ? 'selected' : ''; ?>>
                                    <?php echo e($cn['name']); ?> (<?php echo ucwords(str_replace('_', ' ', e($cn['role']))); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>

                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Calling Notes / Remarks</label>
                    <textarea name="notes" class="form-textarea" rows="3" placeholder="e.g. Inquired about Python Full Stack batch timings, requested syllabus on WhatsApp..."></textarea>
                </div>
            </div>

            <div class="modal-footer" style="padding: var(--space-4) var(--space-5);">
                <button type="button" class="btn btn-secondary" onclick="closeAddManualLeadModal()">Cancel</button>
                <button type="submit" class="btn btn-primary" id="btn-save-manual-lead">
                    <i class="fa-solid fa-floppy-disk"></i> Save Prospect
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ================= FLOATING BULK SELECTION ACTION BAR ================= -->
<div id="floating-bulk-bar" style="display: none; position: fixed; bottom: 24px; left: 50%; transform: translateX(-50%); background: #1e293b; color: #fff; padding: 12px 20px; border-radius: 50px; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.5), 0 8px 10px -6px rgba(0,0,0,0.5); z-index: 1000; align-items: center; gap: 16px; border: 1px solid rgba(255,255,255,0.15); animation: fadeInUp 0.25s ease;">
    <div style="display: flex; align-items: center; gap: 8px;">
        <span class="badge" style="background: var(--color-primary); color: #fff; font-size: 12px; font-weight: 700; border-radius: 20px; padding: 3px 10px;" id="selected-leads-count">0</span>
        <span style="font-size: 13.5px; font-weight: 600;">Prospects Selected</span>
    </div>
    
    <div style="height: 20px; width: 1px; background: rgba(255,255,255,0.2);"></div>

    <div style="display: flex; align-items: center; gap: 8px;">
        <!-- Counsellor Selector for Bulk Assign -->
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

        <button type="button" class="btn btn-sm btn-danger" onclick="submitBulkDeleteSelected()" style="border-radius: 20px; padding: 6px 12px; font-size: 12.5px; font-weight: 600;" title="Delete selected prospects">
            <i class="fa-solid fa-trash"></i>
        </button>

        <button type="button" class="btn btn-sm btn-secondary" onclick="clearSelectedLeads()" style="border-radius: 20px; padding: 6px 10px; font-size: 12.5px; background: rgba(255,255,255,0.1); color: #fff; border: none;" title="Deselect All">
            <i class="fa-solid fa-xmark"></i>
        </button>
    </div>
</div>

<!-- ================= MODAL: BULK IMPORT RAW CALLING DATA (WITH AUTO-DISTRIBUTION) ================= -->
<div class="modal-overlay" id="modal-bulk-import">
    <div class="modal" style="max-width: 720px;">
        <div class="modal-header">
            <h3 class="modal-title"><i class="fa-solid fa-file-import" style="color: #0284c7; margin-right: 8px;"></i>Bulk Import Calling Prospects</h3>
            <button type="button" class="modal-close" onclick="closeBulkImportModal()">&times;</button>
        </div>
        <form id="form-bulk-import" onsubmit="submitBulkImport(event)" enctype="multipart/form-data">
            <?php echo csrfField(); ?>
            <input type="hidden" name="action" value="import_raw_leads">

            <div class="modal-body" style="padding: var(--space-5); display: flex; flex-direction: column; gap: var(--space-3);">
                
                <!-- 3-Way Category Selection (Course vs Service vs Smart Auto-Filter) -->
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label required">Target Category / Pipeline Filter</label>
                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: var(--space-2);">
                        <label style="border: 2px solid var(--color-primary); padding: 10px; border-radius: var(--radius-md); display: flex; align-items: center; gap: 8px; cursor: pointer; background: rgba(99, 102, 241, 0.08); transition: all 0.2s;">
                            <input type="radio" name="offering_type" value="course" checked>
                            <div>
                                <div style="font-weight: 700; font-size: 12.5px; color: var(--color-text-main);"><i class="fa-solid fa-graduation-cap" style="color: var(--color-primary);"></i> Courses</div>
                                <div style="font-size: 10.5px; color: var(--color-text-secondary);">Student Training CRM</div>
                            </div>
                        </label>
                        <label style="border: 2px solid #f59e0b; padding: 10px; border-radius: var(--radius-md); display: flex; align-items: center; gap: 8px; cursor: pointer; background: rgba(245, 158, 11, 0.08); transition: all 0.2s;">
                            <input type="radio" name="offering_type" value="service">
                            <div>
                                <div style="font-weight: 700; font-size: 12.5px; color: var(--color-text-main);"><i class="fa-solid fa-laptop-code" style="color: #f59e0b;"></i> IT Services</div>
                                <div style="font-size: 10.5px; color: var(--color-text-secondary);">Software Client CRM</div>
                            </div>
                        </label>
                        <label style="border: 2px solid #10b981; padding: 10px; border-radius: var(--radius-md); display: flex; align-items: center; gap: 8px; cursor: pointer; background: rgba(16, 185, 129, 0.08); transition: all 0.2s;">
                            <input type="radio" name="offering_type" value="auto_detect">
                            <div>
                                <div style="font-weight: 700; font-size: 12.5px; color: #059669;"><i class="fa-solid fa-wand-magic-sparkles"></i> Auto-Filter</div>
                                <div style="font-size: 10.5px; color: var(--color-text-secondary);">Auto Course vs Service</div>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- File Upload / Drag & Drop Zone -->
                <div id="import-drop-zone" onclick="document.getElementById('import_file_input').click()" style="border: 2px dashed #0284c7; border-radius: var(--radius-md); padding: 16px; text-align: center; background: rgba(2, 132, 199, 0.04); cursor: pointer; transition: all 0.2s;">
                    <input type="file" id="import_file_input" accept=".csv,.txt,.tsv" style="display: none;" onchange="handleImportFileSelect(event)">
                    <i class="fa-solid fa-cloud-arrow-up" style="font-size: 26px; color: #0284c7; margin-bottom: 4px;"></i>
                    <div style="font-weight: 700; font-size: 13.5px; color: var(--color-text-main);">Click to Upload CSV / Text File or Drag & Drop Here</div>
                    <div style="font-size: 11.5px; color: var(--color-text-secondary); margin-top: 2px;">Supports .csv, .txt, or Google Sheets / Excel exports</div>
                </div>

                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <label class="form-label required" style="margin-bottom: 0;">Or Paste Raw Data Below</label>
                    <div style="display: flex; gap: 8px; align-items: center;">
                        <button type="button" class="btn btn-sm btn-link" onclick="insertImportSampleData()" style="font-size: 12px; padding: 0; color: #0284c7;">
                            <i class="fa-solid fa-wand-magic-sparkles"></i> Paste Sample Data
                        </button>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <textarea name="raw_data" id="import_raw_data_textarea" class="form-textarea" rows="6" placeholder="Paste data copied from Excel, Google Sheets, WhatsApp, or CSV (One candidate per line)...&#10;Example: Rahul Sharma, 9876543210, B.Tech CSE, rahul@gmail.com" required style="font-family: monospace; font-size: 12px; width: 100%;" oninput="updateImportLiveCounter()"></textarea>
                </div>

                <!-- Live Counter Banner -->
                <div id="import-live-counter" style="display: flex; align-items: center; justify-content: space-between; background: rgba(16, 185, 129, 0.08); border: 1px solid rgba(16, 185, 129, 0.25); border-radius: var(--radius-sm); padding: 8px 12px; font-size: 12.5px; color: #059669;">
                    <span><i class="fa-solid fa-list-check" style="margin-right: 6px;"></i><strong id="import-detected-count">0</strong> candidate lines ready to import</span>
                    <span style="font-size: 11px; color: var(--color-text-secondary);">Formats: Name, Phone, Degree, Email</span>
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Data Source / Batch Name</label>
                    <input type="text" name="source" class="form-input" value="Raw Calling List" placeholder="e.g. Pune College List, Job Fair 2024">
                </div>

                <?php if ($baseRole !== ROLE_SALES_COUNSELLOR): ?>
                    <!-- Assignment Mode Selection -->
                    <div class="form-group" style="margin-bottom: 0; background: var(--color-bg-secondary); padding: 12px; border-radius: var(--radius-md); border: 1px solid var(--color-border);">
                        <label class="form-label" style="font-weight: 700; margin-bottom: 8px;">
                            <i class="fa-solid fa-arrows-split-up-and-left" style="color: var(--color-primary); margin-right: 5px;"></i>Lead Assignment on Upload
                        </label>
                        
                        <div style="display: flex; gap: 10px; margin-bottom: 12px; flex-wrap: wrap;">
                            <label style="display: flex; align-items: center; gap: 6px; cursor: pointer; font-size: 13px; font-weight: 600;">
                                <input type="radio" name="assign_mode" value="auto_distribute" checked onchange="toggleImportAssignMode('auto_distribute')">
                                <span>🔀 Auto-Divide Equally (Round Robin)</span>
                            </label>
                            <label style="display: flex; align-items: center; gap: 6px; cursor: pointer; font-size: 13px; font-weight: 600;">
                                <input type="radio" name="assign_mode" value="single" onchange="toggleImportAssignMode('single')">
                                <span>👤 Single Person</span>
                            </label>
                            <label style="display: flex; align-items: center; gap: 6px; cursor: pointer; font-size: 13px; font-weight: 600;">
                                <input type="radio" name="assign_mode" value="unassigned" onchange="toggleImportAssignMode('unassigned')">
                                <span>📥 Leave Unassigned (Pool)</span>
                            </label>
                        </div>

                        <!-- 1. Auto-Distribute Counsellor Checkboxes -->
                        <div id="import-distribute-wrap">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                                <span style="font-size: 12px; color: var(--color-text-secondary);">Select team members to divide leads among:</span>
                                <button type="button" class="btn btn-sm btn-link" onclick="toggleAllImportCounsellors()" style="font-size: 11.5px; padding: 0;">Toggle Select All</button>
                            </div>
                            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 6px; max-height: 140px; overflow-y: auto; padding: 4px; background: var(--color-bg-card); border-radius: var(--radius-sm); border: 1px solid var(--color-border);">
                                <?php foreach ($counsellors as $cn): ?>
                                    <label style="display: flex; align-items: center; gap: 6px; font-size: 12px; padding: 4px 6px; border-radius: var(--radius-sm); cursor: pointer; background: rgba(99,102,241,0.04);">
                                        <input type="checkbox" name="distribute_counsellors[]" value="<?php echo $cn['id']; ?>" class="import-counsellor-cb" checked>
                                        <span style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?php echo e($cn['name']); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- 2. Single Counsellor Dropdown -->
                        <div id="import-single-wrap" style="display: none;">
                            <label class="form-label" style="font-size: 12px;">Assign All Leads To:</label>
                            <select name="assigned_to" class="form-select">
                                <?php foreach ($counsellors as $cn): ?>
                                    <option value="<?php echo $cn['id']; ?>" <?php echo $cn['id'] == $userId ? 'selected' : ''; ?>>
                                        <?php echo e($cn['name']); ?> (<?php echo ucwords(str_replace('_', ' ', e($cn['role']))); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <div class="modal-footer" style="padding: var(--space-4) var(--space-5);">
                <button type="button" class="btn btn-secondary" onclick="closeBulkImportModal()">Cancel</button>
                <button type="submit" class="btn btn-primary" id="btn-save-bulk">
                    <i class="fa-solid fa-upload"></i> Import & Distribute Leads
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- ================= MODAL: BULK ASSIGN LEADS BY COUNT (संख्यानुसार वाटप) ================= -->
<?php if ($baseRole !== ROLE_SALES_COUNSELLOR): ?>
<div class="modal-overlay" id="modal-bulk-assign-count">
    <div class="modal" style="max-width: 580px;">
        <div class="modal-header">
            <h3 class="modal-title">
                <i class="fa-solid fa-bolt" style="color: #f59e0b; margin-right: 8px;"></i>Bulk Assign Leads by Count
            </h3>
            <button type="button" class="modal-close" onclick="closeBulkAssignCountModal()">&times;</button>
        </div>
        <form id="form-bulk-assign-count" onsubmit="submitBulkAssignByCount(event)">
            <?php echo csrfField(); ?>
            <input type="hidden" name="action" value="bulk_assign_by_count">
            <input type="hidden" name="offering_type" value="manual">

            <div class="modal-body" style="padding: var(--space-5); display: flex; flex-direction: column; gap: var(--space-3);">
                <!-- Unassigned pool summary banner -->
                <div style="background: rgba(245, 158, 11, 0.1); border-left: 4px solid #f59e0b; padding: 12px 14px; border-radius: var(--radius-sm); font-size: 13px; display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <div style="font-weight: 700; color: #f59e0b;"><i class="fa-solid fa-database" style="margin-right: 5px;"></i>Calling Pool Available</div>
                        <div style="font-size: 12px; color: var(--color-text-secondary); margin-top: 2px;">
                            Total unassigned / calling records ready to distribute
                        </div>
                    </div>
                    <span class="badge" style="background: #f59e0b; color: #fff; font-size: 14px; font-weight: 700; padding: 4px 10px;">
                        <?php echo $salesMetrics['unassigned_leads'] ?? $salesMetrics['manual_new'] ?? 0; ?> Leads
                    </span>
                </div>

                <!-- Number of Leads Input -->
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label required" style="display: flex; justify-content: space-between; align-items: center;">
                        <span>How many leads to assign?</span>
                        <span style="font-size: 11.5px; color: var(--color-text-muted);">Click quick preset or type:</span>
                    </label>
                    <div style="display: flex; gap: 8px; margin-bottom: 8px;">
                        <input type="number" name="count" id="assign_lead_count_input" class="form-input" min="1" max="10000" placeholder="e.g. 50" required style="font-size: 16px; font-weight: 700; flex: 1;">
                    </div>
                    <div style="display: flex; gap: 6px; flex-wrap: wrap;">
                        <button type="button" class="btn btn-sm btn-outline" onclick="setCountPreset(10)" style="padding: 3px 9px; font-size: 12px;">+10</button>
                        <button type="button" class="btn btn-sm btn-outline" onclick="setCountPreset(25)" style="padding: 3px 9px; font-size: 12px;">+25</button>
                        <button type="button" class="btn btn-sm btn-outline" onclick="setCountPreset(50)" style="padding: 3px 9px; font-size: 12px;">+50</button>
                        <button type="button" class="btn btn-sm btn-outline" onclick="setCountPreset(100)" style="padding: 3px 9px; font-size: 12px;">+100</button>
                        <button type="button" class="btn btn-sm btn-outline" onclick="setCountPreset(200)" style="padding: 3px 9px; font-size: 12px;">+200</button>
                        <button type="button" class="btn btn-sm btn-outline" onclick="setCountPreset(<?php echo max(1, (int)($salesMetrics['unassigned_leads'] ?? $salesMetrics['manual_new'] ?? 50)); ?>)" style="padding: 3px 9px; font-size: 12px; color: #f59e0b;">All Available</button>
                    </div>
                </div>

                <!-- Source Pool Filter -->
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Source Calling Pool</label>
                    <select name="source_pool" class="form-select">
                        <option value="unassigned" selected>Unassigned Prospects Only (Recommended)</option>
                        <option value="fresh">All Fresh / Uncalled Prospects</option>
                    </select>
                </div>

                <!-- Distribution Target Mode -->
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

                    <!-- 1. Checkboxes for Equal Split -->
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

                    <!-- 2. Single Counsellor Dropdown -->
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
<?php endif; ?>

<!-- ================= MODAL: 1-CLICK CONVERT TO COURSE / SERVICE LEAD ================= -->
<div class="modal-overlay" id="modal-convert-lead">
    <div class="modal" style="max-width: 600px;">
        <div class="modal-header">
            <h3 class="modal-title"><i class="fa-solid fa-arrow-right-arrow-left" style="color: var(--color-primary); margin-right: 8px;"></i>Convert Prospect to Active Lead</h3>
            <button type="button" class="modal-close" onclick="closeConvertModal()">&times;</button>
        </div>
        <form id="form-convert-lead" onsubmit="submitConvertLead(event)">
            <?php echo csrfField(); ?>
            <input type="hidden" name="action" value="convert_raw_lead">
            <input type="hidden" name="lead_id" id="convert_lead_id" value="">

            <div class="modal-body" style="padding: var(--space-5); display: flex; flex-direction: column; gap: var(--space-3);">
                <div style="background: rgba(99, 102, 241, 0.08); border-left: 4px solid var(--color-primary); padding: 12px 14px; border-radius: var(--radius-sm); font-size: 13px;">
                    Converting Prospect: <strong id="convert_candidate_name" style="color: var(--color-primary);"></strong>
                    <div id="convert_candidate_grad" style="font-size: 11.5px; color: var(--color-text-secondary); margin-top: 2px;"></div>
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label required">Select Conversion Destination</label>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-3);">
                        <label style="border: 2px solid var(--color-primary); padding: 12px; border-radius: var(--radius-md); display: flex; align-items: center; gap: 8px; cursor: pointer; background: rgba(99, 102, 241, 0.05);">
                            <input type="radio" name="offering_type" value="course" checked onchange="toggleConvertOfferingList('course')">
                            <div>
                                <div style="font-weight: 700; font-size: 13.5px;"><i class="fa-solid fa-graduation-cap" style="color: var(--color-primary); margin-right: 5px;"></i>Course Lead</div>
                                <div style="font-size: 11px; color: var(--color-text-secondary);">Student Training & Admission</div>
                            </div>
                        </label>
                        <label style="border: 2px solid #f59e0b; padding: 12px; border-radius: var(--radius-md); display: flex; align-items: center; gap: 8px; cursor: pointer; background: rgba(245, 158, 11, 0.05);">
                            <input type="radio" name="offering_type" value="service" onchange="toggleConvertOfferingList('service')">
                            <div>
                                <div style="font-weight: 700; font-size: 13.5px;"><i class="fa-solid fa-laptop-code" style="color: #f59e0b; margin-right: 5px;"></i>Service Lead</div>
                                <div style="font-size: 11px; color: var(--color-text-secondary);">Software & IT Client</div>
                            </div>
                        </label>
                    </div>
                </div>

                <div id="convert-course-select-wrap" class="form-group" style="margin-bottom: 0;">
                    <label class="form-label required">Course / Training Program</label>
                    <select name="course_service_id" id="convert_course_select" class="form-select" onchange="syncConvertPrice(this)">
                        <option value="">-- Select Training Course --</option>
                        <?php foreach ($coursesList as $c): ?>
                            <option value="<?php echo $c['id']; ?>" data-price="<?php echo $c['price']; ?>" data-name="<?php echo e($c['name']); ?>">
                                <?php echo e($c['name']); ?> (<?php echo formatCurrency($c['price']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div id="convert-service-select-wrap" class="form-group" style="display: none; margin-bottom: 0;">
                    <label class="form-label required">Software / IT Service Package</label>
                    <select name="service_service_id" id="convert_service_select" class="form-select" onchange="syncConvertPrice(this)">
                        <option value="">-- Select IT Service Package --</option>
                        <?php foreach ($servicesList as $s): ?>
                            <option value="<?php echo $s['id']; ?>" data-price="<?php echo $s['price']; ?>" data-name="<?php echo e($s['name']); ?>">
                                <?php echo e($s['name']); ?> (<?php echo formatCurrency($s['price']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <input type="hidden" name="course_service_name" id="convert_course_service_name" value="">

                <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-3); margin-bottom: 0;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">Expected Deal Value (₹)</label>
                        <input type="number" step="0.01" name="expected_revenue" id="convert_revenue" class="form-input" placeholder="0.00">
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">Active Lead Status</label>
                        <select name="status" class="form-select">
                            <option value="interested" selected>Interested / Counseling</option>
                            <option value="enrolled">Enrolled / Payment Done</option>
                            <option value="new_lead">New Active Lead</option>
                        </select>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Counseling / Conversion Note</label>
                    <textarea name="notes" class="form-textarea" rows="2" placeholder="e.g. Candidate confirmed interest after calling discussion, scheduled demo session..."></textarea>
                </div>
            </div>

            <div class="modal-footer" style="padding: var(--space-4) var(--space-5);">
                <button type="button" class="btn btn-secondary" onclick="closeConvertModal()">Cancel</button>
                <button type="submit" class="btn btn-primary" id="btn-convert-submit">
                    <i class="fa-solid fa-arrow-right-arrow-left"></i> Confirm Conversion
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ================= MODAL: UPDATE STATUS & NOTE ================= -->
<div class="modal-overlay" id="modal-status">
    <div class="modal" style="max-width: 480px;">
        <div class="modal-header">
            <h3 class="modal-title"><i class="fa-solid fa-pen-to-square" style="color: var(--color-primary); margin-right: 8px;"></i>Update Calling Status</h3>
            <button type="button" class="modal-close" onclick="closeStatusModal()">&times;</button>
        </div>
        <form id="form-status" onsubmit="submitStatusUpdate(event)">
            <?php echo csrfField(); ?>
            <input type="hidden" name="action" value="update_status">
            <input type="hidden" name="lead_id" id="status_lead_id" value="">

            <div class="modal-body" style="padding: var(--space-5); display: flex; flex-direction: column; gap: var(--space-3);">
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label required">Calling Status</label>
                    <select name="status" id="status_select" class="form-select" required>
                        <option value="new_lead">Fresh / Uncalled</option>
                        <option value="interested">Interested / Call Follow-up</option>
                        <option value="enrolled">Converted / Enrolled</option>
                        <option value="uninterested">Not Interested / Dropped</option>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Next Follow-up Date & Time</label>
                    <input type="datetime-local" name="next_followup_date" id="status_next_followup" class="form-input">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label required">Call Notes / Remarks</label>
                    <textarea name="remarks" id="status_remarks" class="form-textarea" rows="3" placeholder="e.g. Called candidate, they were busy and asked to call back tomorrow at 4 PM..." required></textarea>
                </div>
            </div>

            <div class="modal-footer" style="padding: var(--space-4) var(--space-5);">
                <button type="button" class="btn btn-secondary" onclick="closeStatusModal()">Cancel</button>
                <button type="submit" class="btn btn-primary" id="btn-save-status">
                    <i class="fa-solid fa-check"></i> Save Call Log
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ================= MODAL: REASSIGN PROSPECT (Founder / Manager / HR) ================= -->
<div class="modal-overlay" id="modal-assign-lead">
    <div class="modal" style="max-width: 480px;">
        <div class="modal-header">
            <h3 class="modal-title"><i class="fa-solid fa-user-gear" style="color: #f59e0b; margin-right: 8px;"></i>Reassign Prospect</h3>
            <button type="button" class="modal-close" onclick="closeAssignModal()">&times;</button>
        </div>
        <form id="form-assign-lead" onsubmit="submitAssignLead(event)">
            <?php echo csrfField(); ?>
            <input type="hidden" name="action" value="assign_lead">
            <input type="hidden" name="lead_id" id="assign_lead_id" value="">

            <div class="modal-body" style="padding: var(--space-5); display: flex; flex-direction: column; gap: var(--space-3);">
                <div style="font-size: 13px;">
                    Reassigning Prospect: <strong id="assign_candidate_name" style="color: var(--color-primary);"></strong>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label required">Select Assigned Staff / Counsellor</label>
                    <select name="assigned_to" id="assign_user_select" class="form-select" required>
                        <option value="">-- Choose Team Member --</option>
                        <?php foreach ($counsellors as $cn): ?>
                            <option value="<?php echo $cn['id']; ?>">
                                <?php echo e($cn['name']); ?> (<?php echo ucwords(str_replace('_', ' ', e($cn['role']))); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="modal-footer" style="padding: var(--space-4) var(--space-5);">
                <button type="button" class="btn btn-secondary" onclick="closeAssignModal()">Cancel</button>
                <button type="submit" class="btn btn-primary" id="btn-save-assign">
                    <i class="fa-solid fa-check"></i> Assign Prospect
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ================= MODAL: LEAD DETAILS & ACTIVITY HISTORY ================= -->
<div class="modal-overlay" id="leadDrawerModal">
    <div class="modal" style="max-width: 680px;">
        <div class="modal-header">
            <div>
                <h3 class="modal-title" id="drawerLeadName">Prospect Details</h3>
                <div style="font-size: 12px; color: var(--color-text-secondary); margin-top: 2px;" id="drawerLeadMeta"></div>
            </div>
            <button type="button" class="modal-close" onclick="closeLeadDrawer()">&times;</button>
        </div>

        <div class="modal-body" id="drawerContent" style="padding: var(--space-5);">
            <!-- Populated via AJAX -->
            <div style="text-align: center; padding: 30px;"><i class="fa-solid fa-spinner fa-spin fa-2x" style="color: var(--color-primary);"></i></div>
        </div>
    </div>
</div>

<script>
const CSRF_TOKEN = '<?php echo csrfToken(); ?>';

// Import Modal Assignment Mode Toggles
function toggleImportAssignMode(mode) {
    const distWrap = document.getElementById('import-distribute-wrap');
    const singleWrap = document.getElementById('import-single-wrap');
    if (distWrap) distWrap.style.display = (mode === 'auto_distribute') ? 'block' : 'none';
    if (singleWrap) singleWrap.style.display = (mode === 'single') ? 'block' : 'none';
}

function toggleAllImportCounsellors() {
    const cbs = document.querySelectorAll('.import-counsellor-cb');
    const allChecked = Array.from(cbs).every(cb => cb.checked);
    cbs.forEach(cb => cb.checked = !allChecked);
}

// Bulk Assign by Count Modal Handlers
function openBulkAssignCountModal() {
    const modal = document.getElementById('modal-bulk-assign-count');
    if (modal) {
        if (modal.parentElement && modal.parentElement !== document.body) {
            document.body.appendChild(modal);
        }
        const input = document.getElementById('assign_lead_count_input');
        if (input) input.value = '25';
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
}
function closeBulkAssignCountModal() {
    const modal = document.getElementById('modal-bulk-assign-count');
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = '';
    }
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
    formData.append('csrf_token', CSRF_TOKEN);
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

async function submitBulkDeleteSelected() {
    const checkedBoxes = document.querySelectorAll('.lead-row-checkbox:checked');
    const leadIds = Array.from(checkedBoxes).map(cb => cb.value);

    if (leadIds.length === 0) {
        alert('Please select at least one lead to delete.');
        return;
    }

    if (!confirm(`Are you sure you want to delete ${leadIds.length} selected prospects? This action cannot be undone.`)) {
        return;
    }

    const formData = new FormData();
    formData.append('csrf_token', CSRF_TOKEN);
    formData.append('action', 'bulk_delete_leads');
    leadIds.forEach(id => formData.append('lead_ids[]', id));

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
            alert(data.message || 'Error deleting selected leads.');
        }
    } catch (err) {
        console.error(err);
        alert('An unexpected error occurred.');
    }
}

// Open / Close Modals
function toggleManualLeadCategory(type) {
    const courseRadio = document.getElementById('manual-lead-cat-course');
    const serviceRadio = document.getElementById('manual-lead-cat-service');
    const courseLabel = document.getElementById('manual-lead-cat-course-label');
    const serviceLabel = document.getElementById('manual-lead-cat-service-label');
    const nameLabel = document.getElementById('manual-lead-name-label');
    const gradLabel = document.getElementById('manual-lead-grad-label');
    const gradInput = document.getElementById('manual-lead-grad-input');
    const modalTitle = document.getElementById('modal-add-prospect-title');

    if (type === 'service') {
        if (serviceRadio) serviceRadio.checked = true;
        if (courseLabel) { courseLabel.style.border = '2px solid var(--color-border)'; courseLabel.style.background = 'var(--color-bg-card)'; }
        if (serviceLabel) { serviceLabel.style.border = '2px solid #f59e0b'; serviceLabel.style.background = 'rgba(245, 158, 11, 0.08)'; }
        if (nameLabel) nameLabel.textContent = 'Client / Contact Person Name *';
        if (gradLabel) gradLabel.textContent = 'Company / Business / Service Requirement';
        if (gradInput) gradInput.placeholder = 'e.g. ABC Tech Pvt Ltd, Website Redesign, Custom CRM';
        if (modalTitle) modalTitle.innerHTML = '<i class="fa-solid fa-laptop-code" style="color: #f59e0b; margin-right: 8px;"></i>Add IT Service Prospect';
    } else {
        if (courseRadio) courseRadio.checked = true;
        if (courseLabel) { courseLabel.style.border = '2px solid var(--color-primary)'; courseLabel.style.background = 'rgba(99, 102, 241, 0.08)'; }
        if (serviceLabel) { serviceLabel.style.border = '2px solid var(--color-border)'; serviceLabel.style.background = 'var(--color-bg-card)'; }
        if (nameLabel) nameLabel.textContent = 'Candidate Name *';
        if (gradLabel) gradLabel.textContent = 'Graduation / Degree';
        if (gradInput) gradInput.placeholder = 'e.g. B.Tech CSE (2024), BCA, B.Sc';
        if (modalTitle) modalTitle.innerHTML = '<i class="fa-solid fa-graduation-cap" style="color: var(--color-primary); margin-right: 8px;"></i>Add Course Prospect';
    }
}

function openAddManualLeadModal(defaultType = 'course') {
    const modal = document.getElementById('modal-add-manual-lead');
    if (modal) {
        if (modal.parentElement && modal.parentElement !== document.body) {
            document.body.appendChild(modal);
        }
        const form = document.getElementById('form-add-manual-lead');
        if (form) form.reset();
        toggleManualLeadCategory(defaultType);
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
        const nameInput = modal.querySelector('input[name="name"]');
        if (nameInput) setTimeout(() => nameInput.focus(), 50);
    }
}
function closeAddManualLeadModal() {
    const modal = document.getElementById('modal-add-manual-lead');
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = '';
    }
}

function openBulkImportModal() {
    const modal = document.getElementById('modal-bulk-import');
    if (modal) {
        if (modal.parentElement && modal.parentElement !== document.body) {
            document.body.appendChild(modal);
        }
        const form = document.getElementById('form-bulk-import');
        if (form) form.reset();
        toggleImportAssignMode('auto_distribute');
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
        setupImportDragAndDrop();
        updateImportLiveCounter();
        const textarea = modal.querySelector('textarea[name="raw_data"]');
        if (textarea) setTimeout(() => textarea.focus(), 50);
    }
}
function closeBulkImportModal() {
    const modal = document.getElementById('modal-bulk-import');
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = '';
    }
}

function updateImportLiveCounter() {
    const textarea = document.getElementById('import_raw_data_textarea');
    const counterElem = document.getElementById('import-detected-count');
    if (!textarea || !counterElem) return;

    const lines = textarea.value.split(/\r?\n/).map(l => l.trim()).filter(l => l.length > 0);
    let validCount = 0;

    for (const line of lines) {
        const lower = line.toLowerCase();
        // Skip obvious header rows
        if (/(name|mobile|phone|contact|candidate|degree|qualification|sr.?no|s.no)/i.test(lower) && !/\d{7,}/.test(line)) {
            continue;
        }
        // Check if line contains phone number
        if (/\d{7,}/.test(line)) {
            validCount++;
        }
    }

    counterElem.textContent = validCount;
}

function handleImportFileSelect(e) {
    const file = e.target.files[0];
    if (!file) return;

    const reader = new FileReader();
    reader.onload = function(evt) {
        const text = evt.target.result;
        const textarea = document.getElementById('import_raw_data_textarea');
        if (textarea) {
            textarea.value = text;
            updateImportLiveCounter();
        }
    };
    reader.readAsText(file);
}

function insertImportSampleData() {
    const textarea = document.getElementById('import_raw_data_textarea');
    if (!textarea) return;
    const sample = `Rahul Sharma, 9876543210, B.Tech CSE (2024), rahul@example.com
Pooja Verma, 9822334455, BCA, pooja@example.com
Amit Singh, 9811223344, MCA (Pune)
Kunal Jadhav 9898989898 B.Sc IT
Sneha Patil, 9123456780, BE Mechanical`;
    textarea.value = sample;
    updateImportLiveCounter();
}

function setupImportDragAndDrop() {
    const dropZone = document.getElementById('import-drop-zone');
    if (!dropZone || dropZone.dataset.dndReady) return;
    dropZone.dataset.dndReady = 'true';

    ['dragenter', 'dragover'].forEach(eventName => {
        dropZone.addEventListener(eventName, (e) => {
            e.preventDefault();
            e.stopPropagation();
            dropZone.style.background = 'rgba(2, 132, 199, 0.15)';
            dropZone.style.borderColor = '#0369a1';
        }, false);
    });

    ['dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, (e) => {
            e.preventDefault();
            e.stopPropagation();
            dropZone.style.background = 'rgba(2, 132, 199, 0.04)';
            dropZone.style.borderColor = '#0284c7';
        }, false);
    });

    dropZone.addEventListener('drop', (e) => {
        const dt = e.dataTransfer;
        const files = dt.files;
        if (files && files.length > 0) {
            const file = files[0];
            const reader = new FileReader();
            reader.onload = function(evt) {
                const text = evt.target.result;
                const textarea = document.getElementById('import_raw_data_textarea');
                if (textarea) {
                    textarea.value = text;
                    updateImportLiveCounter();
                }
            };
            reader.readAsText(file);
        }
    }, false);
}

function openStatusModal(leadId, currentStatus) {
    const modal = document.getElementById('modal-status');
    if (modal) {
        if (modal.parentElement && modal.parentElement !== document.body) {
            document.body.appendChild(modal);
        }
        document.getElementById('status_lead_id').value = leadId;
        document.getElementById('status_select').value = currentStatus || 'new_lead';
        document.getElementById('status_remarks').value = '';
        document.getElementById('status_next_followup').value = '';
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
}
function closeStatusModal() {
    const modal = document.getElementById('modal-status');
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = '';
    }
}

// Convert Modal
function openConvertModal(leadId, name, grad) {
    const modal = document.getElementById('modal-convert-lead');
    if (modal) {
        if (modal.parentElement && modal.parentElement !== document.body) {
            document.body.appendChild(modal);
        }
        document.getElementById('convert_lead_id').value = leadId;
        document.getElementById('convert_candidate_name').textContent = name;
        document.getElementById('convert_candidate_grad').textContent = grad ? 'Graduation: ' + grad : 'Graduation: Not specified';
        toggleConvertOfferingList('course');
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
}
function closeConvertModal() {
    const modal = document.getElementById('modal-convert-lead');
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = '';
    }
}

function toggleConvertOfferingList(type) {
    const courseWrap = document.getElementById('convert-course-select-wrap');
    const serviceWrap = document.getElementById('convert-service-select-wrap');
    if (type === 'course') {
        courseWrap.style.display = 'block';
        serviceWrap.style.display = 'none';
        syncConvertPrice(document.getElementById('convert_course_select'));
    } else {
        courseWrap.style.display = 'none';
        serviceWrap.style.display = 'block';
        syncConvertPrice(document.getElementById('convert_service_select'));
    }
}

function syncConvertPrice(selectElem) {
    const selectedOpt = selectElem.options[selectElem.selectedIndex];
    if (selectedOpt && selectedOpt.value) {
        const price = selectedOpt.getAttribute('data-price') || '0';
        const name = selectedOpt.getAttribute('data-name') || '';
        document.getElementById('convert_revenue').value = price;
        document.getElementById('convert_course_service_name').value = name;
    } else {
        document.getElementById('convert_revenue').value = '';
        document.getElementById('convert_course_service_name').value = '';
    }
}

// Submit Single Manual Lead
async function submitAddManualLead(e) {
    e.preventDefault();
    const form = e.target;
    const btn = document.getElementById('btn-save-manual-lead');
    const formData = new FormData(form);

    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';

    try {
        const res = await fetch('<?php echo BASE_URL; ?>/api/leads.php', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        if (data.success) {
            closeAddManualLeadModal();
            window.location.reload();
        } else {
            alert(data.message || 'Error creating prospect lead.');
        }
    } catch (err) {
        console.error(err);
        alert('An unexpected error occurred.');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Save Prospect';
    }
}

// Submit Bulk Import
async function submitBulkImport(e) {
    e.preventDefault();
    const form = e.target;
    const btn = document.getElementById('btn-save-bulk');
    const formData = new FormData(form);

    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Importing...';

    try {
        const res = await fetch('<?php echo BASE_URL; ?>/api/leads.php', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        if (data.success) {
            alert(data.message);
            closeBulkImportModal();
            window.location.reload();
        } else {
            alert(data.message || 'Error importing raw leads.');
        }
    } catch (err) {
        console.error(err);
        alert('An unexpected error occurred.');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-upload"></i> Import Candidates';
    }
}

// Submit Convert Lead
async function submitConvertLead(e) {
    e.preventDefault();
    const form = e.target;
    const btn = document.getElementById('btn-convert-submit');
    const formData = new FormData(form);

    const offeringType = form.querySelector('input[name="offering_type"]:checked').value;
    if (offeringType === 'course') {
        const select = document.getElementById('convert_course_select');
        formData.set('course_service_id', select.value);
        const opt = select.options[select.selectedIndex];
        formData.set('course_service_name', opt ? opt.getAttribute('data-name') : 'Course Program');
    } else {
        const select = document.getElementById('convert_service_select');
        formData.set('course_service_id', select.value);
        const opt = select.options[select.selectedIndex];
        formData.set('course_service_name', opt ? opt.getAttribute('data-name') : 'IT Service Project');
    }

    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Converting...';

    try {
        const res = await fetch('<?php echo BASE_URL; ?>/api/leads.php', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        if (data.success) {
            alert(data.message);
            closeConvertModal();
            window.location.reload();
        } else {
            alert(data.message || 'Error converting lead.');
        }
    } catch (err) {
        console.error(err);
        alert('An unexpected error occurred.');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-arrow-right-arrow-left"></i> Confirm Conversion';
    }
}

// Submit Status Update
async function submitStatusUpdate(e) {
    e.preventDefault();
    const form = e.target;
    const btn = document.getElementById('btn-save-status');
    const formData = new FormData(form);

    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';

    try {
        const res = await fetch('<?php echo BASE_URL; ?>/api/leads.php', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        if (data.success) {
            closeStatusModal();
            window.location.reload();
        } else {
            alert(data.message || 'Error updating status.');
        }
    } catch (err) {
        console.error(err);
        alert('An unexpected error occurred.');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-check"></i> Save Call Log';
    }
}

// Assign Modal Handlers (Founder / Manager / HR)
function openAssignModal(leadId, currentAssignedTo, candidateName) {
    const modal = document.getElementById('modal-assign-lead');
    if (modal) {
        if (modal.parentElement && modal.parentElement !== document.body) {
            document.body.appendChild(modal);
        }
        document.getElementById('assign_lead_id').value = leadId;
        document.getElementById('assign_candidate_name').textContent = candidateName || 'Prospect #' + leadId;
        document.getElementById('assign_user_select').value = currentAssignedTo || '';
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
}
function closeAssignModal() {
    const modal = document.getElementById('modal-assign-lead');
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = '';
    }
}

async function submitAssignLead(e) {
    e.preventDefault();
    const form = e.target;
    const btn = document.getElementById('btn-save-assign');
    const formData = new FormData(form);

    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Assigning...';

    try {
        const res = await fetch('<?php echo BASE_URL; ?>/api/leads.php', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        if (data.success) {
            alert(data.message);
            closeAssignModal();
            window.location.reload();
        } else {
            alert(data.message || 'Error reassigning prospect.');
        }
    } catch (err) {
        console.error(err);
        alert('An unexpected error occurred.');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-check"></i> Assign Prospect';
    }
}

// Delete Prospect (Founder / Manager / HR) - Safe modal with mandatory backup
function deleteProspect(leadId, candidateName) {
    confirmDeleteSingleLead(leadId, candidateName);
}

function submitBulkDeleteSelected() {
    confirmBulkDeleteLeads();
}

// Quick Log Outreach
function logQuickCall(leadId) {
    const formData = new FormData();
    formData.append('csrf_token', CSRF_TOKEN);
    formData.append('action', 'add_activity');
    formData.append('lead_id', leadId);
    formData.append('activity_type', 'call');
    formData.append('remarks', 'Initiated phone call to candidate via dialer.');
    fetch('<?php echo BASE_URL; ?>/api/leads.php', { method: 'POST', body: formData });
}

function logQuickWhatsApp(leadId) {
    const formData = new FormData();
    formData.append('csrf_token', CSRF_TOKEN);
    formData.append('action', 'add_activity');
    formData.append('lead_id', leadId);
    formData.append('activity_type', 'whatsapp');
    formData.append('remarks', 'Initiated WhatsApp counseling message outreach.');
    fetch('<?php echo BASE_URL; ?>/api/leads.php', { method: 'POST', body: formData });
}

// Drawer Details Modal
async function openLeadDrawer(leadId) {
    const modal = document.getElementById('leadDrawerModal');
    const body = document.getElementById('drawerContent');

    body.innerHTML = '<div style="text-align: center; padding: 40px;"><i class="fa-solid fa-spinner fa-spin fa-2x" style="color: var(--color-primary);"></i></div>';
    modal.classList.add('active');

    try {
        const res = await fetch(`<?php echo BASE_URL; ?>/api/leads.php?action=get_lead&lead_id=${leadId}`);
        const data = await res.json();

        if (!data.success || !data.lead) {
            body.innerHTML = `<div class="alert alert-danger">${data.message || 'Failed to load prospect details.'}</div>`;
            return;
        }

        const l = data.lead;
        document.getElementById('drawerLeadName').textContent = l.name;
        document.getElementById('drawerLeadMeta').textContent = (l.graduation ? l.graduation + ' • ' : '') + (l.source || 'Raw Calling Prospect');

        let activitiesHtml = '';
        if (l.activities && l.activities.length > 0) {
            activitiesHtml = l.activities.map(a => `
                <div style="padding: 10px 12px; background: var(--color-bg-secondary); border-radius: var(--radius-sm); border-left: 3px solid var(--color-primary); font-size: 12.5px;">
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

        body.innerHTML = `
            <div style="background: var(--color-bg-tertiary); padding: 14px; border-radius: var(--radius-md); display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 10px; margin-bottom: 16px;">
                <div>
                    <span style="font-size: 11px; color: var(--color-text-secondary); display: block;">Calling Status</span>
                    <strong>${l.status.toUpperCase()}</strong>
                </div>
                <div>
                    <span style="font-size: 11px; color: var(--color-text-secondary); display: block;">Phone Number</span>
                    <a href="tel:${l.phone}" style="font-weight: 600; text-decoration: none;">${l.phone}</a>
                </div>
                ${l.email ? `
                <div>
                    <span style="font-size: 11px; color: var(--color-text-secondary); display: block;">Email</span>
                    <a href="mailto:${l.email}" style="text-decoration: none;">${l.email}</a>
                </div>` : ''}
                ${l.graduation ? `
                <div>
                    <span style="font-size: 11px; color: var(--color-text-secondary); display: block;">Graduation</span>
                    <strong style="color: #0284c7;">${l.graduation}</strong>
                </div>` : ''}
            </div>

            <div>
                <h4 style="font-size: 13px; font-weight: 600; margin-bottom: 8px;"><i class="fa-solid fa-clock-rotate-left" style="margin-right: 5px;"></i>Outreach & Call History</h4>
                <div style="display: flex; flex-direction: column; gap: 8px;">
                    ${activitiesHtml}
                </div>
            </div>
        `;
    } catch (err) {
        body.innerHTML = '<div class="alert alert-danger">Error fetching details.</div>';
    }
}

function closeLeadDrawer() {
    document.getElementById('leadDrawerModal').classList.remove('active');
}

// Close modals when clicking outside
document.querySelectorAll('.modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.remove('active');
        }
    });
});

<?php if ($selectedLeadId > 0): ?>
document.addEventListener('DOMContentLoaded', () => {
    openLeadDrawer(<?php echo $selectedLeadId; ?>);
});
<?php endif; ?>
</script>

<?php 
require_once __DIR__ . '/../includes/lead_delete_modal.php';
include __DIR__ . '/../includes/footer.php'; 
?>
