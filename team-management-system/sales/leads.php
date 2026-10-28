<?php
/**
 * Sales Counsellor — My Leads & CRM Pipeline
 * 
 * Interactive lead pipeline with status filters, course/service breakdown,
 * lead details drawer, call notes activity log, and direct communication shortcuts.
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
$offeringFilter = get('offering_type', '');
$followupFilter = get('followup', '');
$counsellorFilter = get('counsellor_id', '');
$searchQuery = get('search', '');
$page = max(1, (int)get('page', 1));
$limit = 20;

$filters = [
    'status' => $statusFilter,
    'offering_type' => $offeringFilter,
    'followup_filter' => $followupFilter,
    'search' => $searchQuery
];

if ($baseRole === ROLE_SALES_COUNSELLOR) {
    $filters['assigned_to'] = $userId;
} elseif (!empty($counsellorFilter)) {
    $filters['assigned_to'] = (int)$counsellorFilter;
}

$leadsResult = getLeads($filters, $page, $limit);
$leads = $leadsResult['data'];
$totalLeads = $leadsResult['total'];
$totalPages = $leadsResult['total_pages'];

// Metrics for tabs count
$salesMetrics = getSalesMetrics($baseRole === ROLE_SALES_COUNSELLOR ? $userId : (!empty($counsellorFilter) ? (int)$counsellorFilter : null));

// Sales Counsellors list for assignment and filters
$counsellors = getSalesCounsellorsList();

// Offerings list for modal
$coursesList = getCourseServicesList('course');
$servicesList = getCourseServicesList('service');
$canAddLeads = in_array($baseRole, [ROLE_FOUNDER, ROLE_MANAGER, ROLE_HR, ROLE_SALES_COUNSELLOR]);

// Lead ID from URL if opened directly
$selectedLeadId = (int)get('lead_id', 0);

$pageTitle = ($offeringFilter === 'course') ? 'Course Leads & Admissions' : (($offeringFilter === 'service') ? 'Software & Digital Services Leads' : 'My Leads & CRM Pipeline');
include __DIR__ . '/../includes/header.php';
?>

<!-- Header -->
<div class="page-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: var(--space-4); margin-bottom: var(--space-4);">
    <div>
        <h1 class="page-title">
            <?php if ($offeringFilter === 'course'): ?>
                <i class="fa-solid fa-graduation-cap" style="color: var(--color-primary); margin-right: 8px;"></i>Course Leads & Student Admissions
            <?php elseif ($offeringFilter === 'service'): ?>
                <i class="fa-solid fa-laptop-code" style="color: #f59e0b; margin-right: 8px;"></i>Software & IT Services Leads
            <?php else: ?>
                <i class="fa-solid fa-users-rays" style="color: var(--color-primary); margin-right: 8px;"></i>My Leads & CRM Pipeline
            <?php endif; ?>
        </h1>
        <p class="page-subtitle">
            <?php if ($offeringFilter === 'course'): ?>
                Manage student inquiries, syllabus counseling, fee quotations, and batch enrollments.
            <?php elseif ($offeringFilter === 'service'): ?>
                Manage B2B client inquiries, software/website proposals, and project closures.
            <?php else: ?>
                Combined CRM stream across Training Courses and Software IT Services.
            <?php endif; ?>
        </p>
    </div>
    <div style="display: flex; gap: var(--space-2); flex-wrap: wrap;">
        <?php if ($baseRole !== ROLE_SALES_COUNSELLOR): ?>
            <button type="button" class="btn btn-warning" onclick="openBulkAssignCountModal()" style="background: #f59e0b; color: #fff; border: none; font-weight: 600;">
                <i class="fa-solid fa-bolt"></i> Bulk Assign by Count
            </button>
        <?php endif; ?>
        <?php if ($canAddLeads): ?>
            <?php if ($offeringFilter === 'course'): ?>
                <button type="button" class="btn btn-primary" onclick="openAddLeadModal('course')">
                    <i class="fa-solid fa-graduation-cap"></i> + Add Course Lead
                </button>
                <?php if ($baseRole !== ROLE_SALES_COUNSELLOR): ?>
                    <button type="button" class="btn btn-secondary" onclick="openAddLeadModal('course', 'bulk')">
                        <i class="fa-solid fa-file-import"></i> Upload Course Leads
                    </button>
                <?php endif; ?>
            <?php elseif ($offeringFilter === 'service'): ?>
                <button type="button" class="btn btn-warning" onclick="openAddLeadModal('service')" style="background: #f59e0b; color: #fff; border: none; font-weight: 600;">
                    <i class="fa-solid fa-laptop-code"></i> + Add Service Lead
                </button>
                <?php if ($baseRole !== ROLE_SALES_COUNSELLOR): ?>
                    <button type="button" class="btn btn-secondary" onclick="openAddLeadModal('service', 'bulk')">
                        <i class="fa-solid fa-file-import"></i> Upload Service Leads
                    </button>
                <?php endif; ?>
            <?php else: ?>
                <button type="button" class="btn btn-primary" onclick="openAddLeadModal('course')">
                    <i class="fa-solid fa-graduation-cap"></i> + Add Course Lead
                </button>
                <button type="button" class="btn btn-warning" onclick="openAddLeadModal('service')" style="background: #f59e0b; color: #fff; border: none; font-weight: 600;">
                    <i class="fa-solid fa-laptop-code"></i> + Add Service Lead
                </button>
                <?php if ($baseRole !== ROLE_SALES_COUNSELLOR): ?>
                    <button type="button" class="btn btn-secondary" onclick="openAddLeadModal('course', 'bulk')">
                        <i class="fa-solid fa-file-import"></i> Bulk Upload Leads
                    </button>
                <?php endif; ?>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- 4 DEDICATED SECTIONS SWITCHER CARDS (Master vs Courses vs Services vs Manual Calling) -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); gap: var(--space-3); margin-bottom: var(--space-5);">
    <!-- All Leads Tab -->
    <a href="<?php echo BASE_URL; ?>/sales/leads.php" style="text-decoration: none; display: flex; align-items: center; justify-content: space-between; padding: 14px 18px; border-radius: var(--radius-lg); background: <?php echo empty($offeringFilter) ? 'var(--color-bg-tertiary)' : 'var(--color-bg-card)'; ?>; border: 2px solid <?php echo empty($offeringFilter) ? 'var(--color-primary)' : 'var(--color-border)'; ?>; transition: all 0.2s;" class="fade-in">
        <div style="display: flex; align-items: center; gap: 12px;">
            <div style="width: 38px; height: 38px; border-radius: var(--radius-md); background: rgba(99, 102, 241, 0.15); color: var(--color-primary); display: flex; align-items: center; justify-content: center; font-size: 18px;">
                <i class="fa-solid fa-layer-group"></i>
            </div>
            <div>
                <div style="font-weight: 700; font-size: 14px; color: var(--color-text-main);">All Combined Leads</div>
                <div style="font-size: 11.5px; color: var(--color-text-secondary);">Master Pipeline</div>
            </div>
        </div>
        <span class="badge badge-secondary" style="font-size: 12px; font-weight: 700;"><?php echo $salesMetrics['total_leads']; ?></span>
    </a>

    <!-- Course Section Tab -->
    <a href="<?php echo BASE_URL; ?>/sales/leads.php?offering_type=course" style="text-decoration: none; display: flex; align-items: center; justify-content: space-between; padding: 14px 18px; border-radius: var(--radius-lg); background: <?php echo $offeringFilter === 'course' ? 'rgba(99, 102, 241, 0.12)' : 'var(--color-bg-card)'; ?>; border: 2px solid <?php echo $offeringFilter === 'course' ? 'var(--color-primary)' : 'var(--color-border)'; ?>; transition: all 0.2s;" class="fade-in">
        <div style="display: flex; align-items: center; gap: 12px;">
            <div style="width: 38px; height: 38px; border-radius: var(--radius-md); background: rgba(99, 102, 241, 0.2); color: var(--color-primary); display: flex; align-items: center; justify-content: center; font-size: 18px;">
                <i class="fa-solid fa-graduation-cap"></i>
            </div>
            <div>
                <div style="font-weight: 700; font-size: 14px; color: var(--color-text-main);">Courses Section</div>
                <div style="font-size: 11.5px; color: var(--color-text-secondary);">Students & Admissions</div>
            </div>
        </div>
        <span class="badge badge-primary" style="font-size: 12px; font-weight: 700;"><?php echo $salesMetrics['course_leads']; ?> Leads</span>
    </a>

    <!-- Service Section Tab -->
    <a href="<?php echo BASE_URL; ?>/sales/leads.php?offering_type=service" style="text-decoration: none; display: flex; align-items: center; justify-content: space-between; padding: 14px 18px; border-radius: var(--radius-lg); background: <?php echo $offeringFilter === 'service' ? 'rgba(245, 158, 11, 0.12)' : 'var(--color-bg-card)'; ?>; border: 2px solid <?php echo $offeringFilter === 'service' ? '#f59e0b' : 'var(--color-border)'; ?>; transition: all 0.2s;" class="fade-in">
        <div style="display: flex; align-items: center; gap: 12px;">
            <div style="width: 38px; height: 38px; border-radius: var(--radius-md); background: rgba(245, 158, 11, 0.2); color: #f59e0b; display: flex; align-items: center; justify-content: center; font-size: 18px;">
                <i class="fa-solid fa-laptop-code"></i>
            </div>
            <div>
                <div style="font-weight: 700; font-size: 14px; color: var(--color-text-main);">Services Section</div>
                <div style="font-size: 11.5px; color: var(--color-text-secondary);">Clients & IT Projects</div>
            </div>
        </div>
        <span class="badge badge-warning" style="font-size: 12px; font-weight: 700;"><?php echo $salesMetrics['service_leads']; ?> Leads</span>
    </a>

    <!-- Manual / Cold Calling Data Section Tab -->
    <a href="<?php echo BASE_URL; ?>/sales/raw_leads.php" style="text-decoration: none; display: flex; align-items: center; justify-content: space-between; padding: 14px 18px; border-radius: var(--radius-lg); background: var(--color-bg-card); border: 2px solid var(--color-border); transition: all 0.2s;" class="fade-in">
        <div style="display: flex; align-items: center; gap: 12px;">
            <div style="width: 38px; height: 38px; border-radius: var(--radius-md); background: rgba(2, 132, 199, 0.15); color: #0284c7; display: flex; align-items: center; justify-content: center; font-size: 18px;">
                <i class="fa-solid fa-address-book"></i>
            </div>
            <div>
                <div style="font-weight: 700; font-size: 14px; color: var(--color-text-main);">Manual / Cold Data</div>
                <div style="font-size: 11.5px; color: var(--color-text-secondary);">Candidate Calling Bank</div>
            </div>
        </div>
        <span class="badge" style="background: rgba(2, 132, 199, 0.15); color: #0284c7; font-size: 12px; font-weight: 700;"><?php echo $salesMetrics['manual_leads']; ?> Prospects</span>
    </a>
</div>

<!-- Status Filter Pills -->
<div style="display: flex; gap: var(--space-2); margin-bottom: var(--space-4); overflow-x: auto; padding-bottom: 4px; flex-wrap: wrap; align-items: center;">
    <a href="<?php echo BASE_URL; ?>/sales/leads.php?offering_type=<?php echo urlencode($offeringFilter); ?>" class="btn btn-sm <?php echo empty($statusFilter) && empty($followupFilter) ? 'btn-primary' : 'btn-secondary'; ?>" style="border-radius: var(--radius-full);">
        All Status (<?php echo $totalLeads; ?>)
    </a>
    <a href="<?php echo BASE_URL; ?>/sales/leads.php?status=new_lead&offering_type=<?php echo urlencode($offeringFilter); ?>" class="btn btn-sm <?php echo $statusFilter === 'new_lead' ? 'btn-primary' : 'btn-secondary'; ?>" style="border-radius: var(--radius-full);">
        <i class="fa-solid fa-user-plus" style="color: #38bdf8;"></i> New Leads
    </a>
    <a href="<?php echo BASE_URL; ?>/sales/leads.php?status=interested&offering_type=<?php echo urlencode($offeringFilter); ?>" class="btn btn-sm <?php echo $statusFilter === 'interested' ? 'btn-primary' : 'btn-secondary'; ?>" style="border-radius: var(--radius-full);">
        <i class="fa-solid fa-clock-rotate-left" style="color: #f59e0b;"></i> Interested
    </a>
    <a href="<?php echo BASE_URL; ?>/sales/leads.php?status=enrolled&offering_type=<?php echo urlencode($offeringFilter); ?>" class="btn btn-sm <?php echo $statusFilter === 'enrolled' ? 'btn-primary' : 'btn-secondary'; ?>" style="border-radius: var(--radius-full);">
        <i class="fa-solid fa-circle-check" style="color: #10b981;"></i> Enrolled
    </a>
    <a href="<?php echo BASE_URL; ?>/sales/leads.php?status=uninterested&offering_type=<?php echo urlencode($offeringFilter); ?>" class="btn btn-sm <?php echo $statusFilter === 'uninterested' ? 'btn-primary' : 'btn-secondary'; ?>" style="border-radius: var(--radius-full);">
        <i class="fa-solid fa-circle-xmark" style="color: #ef4444;"></i> Uninterested
    </a>
    <a href="<?php echo BASE_URL; ?>/sales/leads.php?followup=today&offering_type=<?php echo urlencode($offeringFilter); ?>" class="btn btn-sm <?php echo $followupFilter === 'today' ? 'btn-danger' : 'btn-outline-danger'; ?>" style="border-radius: var(--radius-full); margin-left: auto;">
        <i class="fa-solid fa-phone-volume"></i> Today's Follow-ups
    </a>
</div>

<!-- Filters & Search Toolbar -->
<form method="GET" action="<?php echo BASE_URL; ?>/sales/leads.php" class="filter-bar" style="display: flex; gap: var(--space-3); align-items: center; margin-bottom: var(--space-5);">
    <?php if (!empty($statusFilter)): ?>
        <input type="hidden" name="status" value="<?php echo e($statusFilter); ?>">
    <?php endif; ?>
    <?php if (!empty($followupFilter)): ?>
        <input type="hidden" name="followup" value="<?php echo e($followupFilter); ?>">
    <?php endif; ?>
    <?php if (!empty($offeringFilter)): ?>
        <input type="hidden" name="offering_type" value="<?php echo e($offeringFilter); ?>">
    <?php endif; ?>

    <!-- Search Input -->
    <div style="flex: 1; min-width: 240px;">
        <input type="text" name="search" class="form-input" placeholder="Search by prospect name, phone, email, course/service, city..." value="<?php echo e($searchQuery); ?>" style="width: 100%;">
    </div>

    <?php if ($baseRole !== ROLE_SALES_COUNSELLOR): ?>
        <!-- Counsellor Selector Filter -->
        <div style="min-width: 190px;">
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

    <button type="submit" class="btn btn-primary" style="white-space: nowrap;">
        <i class="fa-solid fa-magnifying-glass"></i> Search
    </button>

    <?php if (!empty($searchQuery) || !empty($offeringFilter) || !empty($statusFilter) || !empty($followupFilter) || !empty($counsellorFilter)): ?>
        <a href="<?php echo BASE_URL; ?>/sales/leads.php<?php echo !empty($offeringFilter) ? '?offering_type=' . urlencode($offeringFilter) : ''; ?>" class="btn btn-secondary" title="Reset Filters" style="white-space: nowrap;">
            <i class="fa-solid fa-rotate-left"></i> Reset All
        </a>
    <?php endif; ?>
</form>

<!-- Leads Data Table Container -->
<div class="data-table-container fade-in" style="background: var(--color-bg-card); border: 1px solid var(--color-border); border-radius: var(--radius-lg); overflow: hidden; margin-bottom: var(--space-6);">
    <?php if (empty($leads)): ?>
        <div style="text-align: center; padding: var(--space-10) var(--space-4); color: var(--color-text-muted);">
            <i class="fa-solid fa-filter-circle-xmark" style="font-size: 40px; margin-bottom: var(--space-3); color: var(--color-text-muted);"></i>
            <h3 style="font-size: 16px; margin: 0 0 6px 0; color: var(--color-text-main);">No leads found</h3>
            <p style="margin: 0; font-size: 13px;">No inquiries match your current filters.</p>
        </div>
    <?php else: ?>
        <!-- 1. DESKTOP VIEW (Visible on tablet landscape and desktop > 768px) -->
        <div class="leads-table-desktop">
            <div style="overflow-x: auto; width: 100%;">
                <table class="data-table" style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr>
                            <?php if ($baseRole !== ROLE_SALES_COUNSELLOR): ?>
                                <th style="padding: 12px 16px; width: 38px; text-align: center;">
                                    <input type="checkbox" id="selectAllLeads" onchange="toggleSelectAllLeads(this)" style="cursor: pointer; width: 16px; height: 16px;">
                                </th>
                            <?php endif; ?>
                            <th style="padding: 12px 16px; text-align: left; min-width: 200px;">Prospect</th>
                            <th style="padding: 12px 16px; text-align: left; min-width: 90px;">Type</th>
                            <th style="padding: 12px 16px; text-align: left; min-width: 220px;">Course / Service</th>
                            <th style="padding: 12px 16px; text-align: left; min-width: 110px;">Source</th>
                            <th style="padding: 12px 16px; text-align: left; min-width: 120px;">Status</th>
                            <th style="padding: 12px 16px; text-align: left; min-width: 130px;">Assigned To</th>
                            <th style="padding: 12px 16px; text-align: left; min-width: 140px;">Next Follow-up</th>
                            <th style="padding: 12px 16px; text-align: left; min-width: 100px;">Created</th>
                            <th style="padding: 12px 16px; text-align: right; min-width: 200px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($leads as $lead): 
                            $leadId = (int)$lead['id'];
                            $leadName = e($lead['name']);
                        ?>
                            <tr id="lead-row-<?php echo $leadId; ?>">
                                <?php if ($baseRole !== ROLE_SALES_COUNSELLOR): ?>
                                    <td style="padding: 12px 16px; text-align: center;">
                                        <input type="checkbox" class="lead-row-checkbox lead-checkbox-<?php echo $leadId; ?>" value="<?php echo $leadId; ?>" onchange="onLeadCheckboxToggle(this)" style="cursor: pointer; width: 16px; height: 16px;">
                                    </td>
                                <?php endif; ?>
                                <td style="padding: 12px 16px;">
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <div style="width: 32px; height: 32px; border-radius: 50%; background: rgba(99, 102, 241, 0.12); color: var(--color-primary-light); font-weight: 700; font-size: 11px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                            <?php echo strtoupper(substr($lead['name'] ?? 'P', 0, 2)); ?>
                                        </div>
                                        <div>
                                            <div style="font-weight: 600; color: var(--color-text-white); font-size: 13.5px; display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">
                                                <?php echo $leadName; ?>
                                                <?php if (!empty($lead['graduation'])): ?>
                                                    <span class="badge" style="font-size: 10px; padding: 1px 6px; background: rgba(99, 102, 241, 0.15); color: var(--color-primary-light); border: 1px solid rgba(99, 102, 241, 0.3);">
                                                        <i class="fa-solid fa-user-graduate" style="font-size: 9px; margin-right: 2px;"></i><?php echo e($lead['graduation']); ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            <div style="font-size: 11.5px; color: var(--color-text-secondary); margin-top: 2px; display: flex; align-items: center; gap: 6px; flex-wrap: wrap; font-family: monospace;">
                                                <span><i class="fa-solid fa-phone" style="font-size: 9px;"></i> <?php echo e($lead['phone']); ?></span>
                                                <?php if (!empty($lead['email'])): ?><span>• <?php echo e($lead['email']); ?></span><?php endif; ?>
                                                <?php if (!empty($lead['city'])): ?><span>• <i class="fa-solid fa-location-dot" style="font-size: 9px;"></i> <?php echo e($lead['city']); ?></span><?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td style="padding: 12px 16px;">
                                    <span class="badge <?php echo $lead['offering_type'] === 'course' ? 'badge-primary' : 'badge-warning'; ?>" style="font-size: 11px;">
                                        <?php echo ucfirst($lead['offering_type']); ?>
                                    </span>
                                </td>
                                <td style="padding: 12px 16px;">
                                    <div style="font-weight: 500; color: var(--color-text-white);">
                                        <?php echo e($lead['course_service_name']); ?>
                                    </div>
                                    <?php if ($lead['expected_revenue'] > 0): ?>
                                        <div style="font-size: 11.5px; color: var(--color-success); margin-top: 2px;">
                                            Value: ₹<?php echo number_format($lead['expected_revenue']); ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($lead['status'] === 'enrolled'): ?>
                                        <div style="margin-top: 4px;">
                                            <?php echo getPaymentStatusBadge($lead['payment_status'] ?? 'unpaid', (float)($lead['paid_amount'] ?? 0), (float)($lead['expected_revenue'] ?? 0), $lead['payment_plan'] ?? 'none', $lead['offering_type'] ?? 'course'); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 12px 16px;">
                                    <span style="font-size: 12.5px; color: var(--color-text-secondary);"><?php echo e($lead['source']); ?></span>
                                </td>
                                <td id="status-cell-<?php echo $leadId; ?>" data-lead-status-id="<?php echo $leadId; ?>" style="padding: 12px 16px;"><?php echo getLeadStatusBadge($lead['status']); ?></td>
                                <td style="padding: 12px 16px;">
                                    <div style="font-size: 12.5px; font-weight: 600; color: var(--color-text-main);">
                                        <?php echo e($lead['assigned_to_name'] ?: 'Unassigned'); ?>
                                    </div>
                                </td>
                                <td style="padding: 12px 16px;">
                                    <?php if (!empty($lead['next_followup_date'])): ?>
                                        <?php 
                                            $isPast = (strtotime($lead['next_followup_date']) < time() && $lead['status'] !== 'enrolled');
                                            $isToday = (date('Y-m-d', strtotime($lead['next_followup_date'])) === date('Y-m-d'));
                                        ?>
                                        <div style="font-size: 12px; <?php echo $isPast ? 'color: var(--color-danger); font-weight: 600;' : ($isToday ? 'color: var(--color-warning); font-weight: 600;' : 'color: var(--color-text-secondary);'); ?>">
                                            <i class="fa-regular fa-clock" style="font-size: 10px; margin-right: 3px;"></i><?php echo formatDate($lead['next_followup_date'], 'd M, h:i A'); ?>
                                        </div>
                                    <?php else: ?>
                                        <span style="font-size: 12px; color: var(--color-text-muted);">—</span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 12px 16px;">
                                    <span style="font-size: 12px; color: var(--color-text-muted);"><?php echo formatDate($lead['created_at'], 'd M Y'); ?></span>
                                </td>
                                <td style="padding: 12px 16px; text-align: right;">
                                    <div style="display: inline-flex; gap: 6px; align-items: center; justify-content: flex-end;">
                                        <a href="tel:<?php echo e($lead['phone']); ?>" class="btn btn-sm btn-ghost" title="Call Prospect" style="padding: 6px 8px; color: var(--color-primary);">
                                            <i class="fa-solid fa-phone"></i>
                                        </a>
                                        <button type="button" class="btn btn-sm btn-ghost text-success" title="WhatsApp Message" style="padding: 6px 8px; color: var(--color-success); border: none; background: none; cursor: pointer;" onclick="openWhatsAppModal(<?php echo (int)$lead['id']; ?>, '<?php echo addslashes(e($lead['name'])); ?>', '<?php echo e($lead['phone']); ?>', <?php echo ($lead['is_manual'] ? 2 : 1); ?>, '<?php echo addslashes(e($lead['course_service_name'] ?? 'UI/UX Design')); ?>')">
                                            <i class="fa-brands fa-whatsapp"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-primary" onclick="openUpdateStatusModal(<?php echo (int)$lead['id']; ?>, '<?php echo addslashes(e($lead['name'])); ?>', '<?php echo e($lead['status']); ?>', '<?php echo e($lead['offering_type']); ?>', <?php echo (int)($lead['course_service_id'] ?? 0); ?>, '<?php echo addslashes(e($lead['course_service_name'] ?? '')); ?>', <?php echo (float)($lead['expected_revenue'] ?? 0); ?>)" title="Update Status" style="padding: 6px 12px; font-size: 12px;">
                                            <i class="fa-solid fa-pen-to-square"></i> Status
                                        </button>

                                        <?php if ($lead['status'] === 'enrolled'): ?>
                                            <button type="button" class="btn btn-sm" onclick="openPaymentModal(<?php echo $leadId; ?>)" title="Manage Course Payments & Installments" style="padding: 6px 10px; font-size: 12px; background: rgba(16, 185, 129, 0.15); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.3);">
                                                <i class="fa-solid fa-credit-card"></i> Payments
                                            </button>
                                        <?php endif; ?>

                                        <?php if ($baseRole !== ROLE_SALES_COUNSELLOR): ?>
                                            <!-- Reassign Lead (Founder / Manager / HR) -->
                                            <button type="button" class="btn btn-sm btn-ghost" style="padding: 6px 8px; color: #f59e0b;" title="Reassign to Counsellor" onclick="openAssignModal(<?php echo $leadId; ?>, <?php echo (int)($lead['assigned_to'] ?? 0); ?>, '<?php echo addslashes($lead['name']); ?>')">
                                                <i class="fa-solid fa-user-gear"></i>
                                            </button>

                                            <!-- Delete Lead (Founder / Manager / HR) -->
                                            <button type="button" class="btn btn-sm btn-ghost text-danger" style="padding: 6px 8px; color: var(--color-danger);" title="Delete Lead" onclick="confirmDeleteSingleLead(<?php echo $leadId; ?>, '<?php echo addslashes($lead['name']); ?>')">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        <?php endif; ?>

                                        <button type="button" class="btn btn-sm btn-outline" onclick="viewLeadDrawer(<?php echo (int)$lead['id']; ?>)" title="History & Notes" style="padding: 6px 10px; font-size: 12px;">
                                            <i class="fa-solid fa-clock-rotate-left"></i> History (<?php echo (int)$lead['activity_count']; ?>)
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- 2. MOBILE CARDS VIEW (Visible on mobile screens <= 768px) -->
        <div class="leads-cards-mobile">
            <?php if ($baseRole !== ROLE_SALES_COUNSELLOR): ?>
                <div class="mobile-toolbar-header" style="border-radius: var(--radius-md); margin-bottom: 2px;">
                    <label style="display: inline-flex; align-items: center; gap: 8px; cursor: pointer; user-select: none; margin: 0;">
                        <input type="checkbox" id="selectAllLeadsMobile" onchange="toggleSelectAllLeads(this)" style="cursor: pointer; width: 18px; height: 18px;">
                        <span>Select All on Page</span>
                    </label>
                    <span style="font-size: 12px; color: var(--color-text-secondary);"><?php echo count($leads); ?> of <?php echo $totalLeads; ?></span>
                </div>
            <?php endif; ?>

            <?php foreach ($leads as $lead): 
                $leadId = (int)$lead['id'];
                $leadName = e($lead['name']);
                $isPast = (!empty($lead['next_followup_date']) && strtotime($lead['next_followup_date']) < time() && $lead['status'] !== 'enrolled');
                $isToday = (!empty($lead['next_followup_date']) && date('Y-m-d', strtotime($lead['next_followup_date'])) === date('Y-m-d'));
            ?>
                <div class="lead-mobile-card" id="lead-card-<?php echo $leadId; ?>">
                    <!-- Card Top: Checkbox, Avatar, Name & Status -->
                    <div class="lead-card-header">
                        <div class="lead-card-identity">
                            <?php if ($baseRole !== ROLE_SALES_COUNSELLOR): ?>
                                <input type="checkbox" class="lead-card-checkbox lead-checkbox-<?php echo $leadId; ?>" value="<?php echo $leadId; ?>" onchange="onLeadCheckboxToggle(this)" style="cursor: pointer; width: 18px; height: 18px; flex-shrink: 0;">
                            <?php endif; ?>
                            <div class="lead-card-avatar">
                                <?php echo strtoupper(substr($lead['name'] ?? 'P', 0, 2)); ?>
                            </div>
                            <div class="lead-card-name-wrap">
                                <div class="lead-card-name" onclick="viewLeadDrawer(<?php echo $leadId; ?>)">
                                    <?php echo $leadName; ?>
                                </div>
                                <?php if (!empty($lead['graduation'])): ?>
                                    <div class="lead-card-grad-badge">
                                        <i class="fa-solid fa-user-graduate"></i> <?php echo e($lead['graduation']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div id="status-card-<?php echo $leadId; ?>">
                            <?php echo getLeadStatusBadge($lead['status']); ?>
                        </div>
                    </div>

                    <!-- Offering & Deal Value Bar -->
                    <div class="lead-card-offering-bar">
                        <div class="lead-card-offering-title">
                            <span class="badge <?php echo $lead['offering_type'] === 'course' ? 'badge-primary' : 'badge-warning'; ?>" style="font-size: 10px; font-weight: 700; padding: 2px 6px;">
                                <i class="fa-solid <?php echo $lead['offering_type'] === 'course' ? 'fa-graduation-cap' : 'fa-laptop-code'; ?>" style="margin-right: 2px;"></i><?php echo ucfirst($lead['offering_type']); ?>
                            </span>
                            <span style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?php echo e($lead['course_service_name']); ?></span>
                        </div>
                        <?php if ($lead['expected_revenue'] > 0): ?>
                            <div class="lead-card-value">
                                ₹<?php echo number_format($lead['expected_revenue']); ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if ($lead['status'] === 'enrolled'): ?>
                        <div style="margin: 2px 0;">
                            <?php echo getPaymentStatusBadge($lead['payment_status'] ?? 'unpaid', (float)($lead['paid_amount'] ?? 0), (float)($lead['expected_revenue'] ?? 0), $lead['payment_plan'] ?? 'none', $lead['offering_type'] ?? 'course'); ?>
                        </div>
                    <?php endif; ?>

                    <!-- Direct Contact Links -->
                    <div class="lead-card-contacts">
                        <div class="lead-card-contact-item">
                            <i class="fa-solid fa-phone" style="color: var(--color-primary-light); width: 14px;"></i>
                            <a href="tel:<?php echo e($lead['phone']); ?>">
                                <?php echo e($lead['phone']); ?>
                            </a>
                        </div>
                        <?php if (!empty($lead['email'])): ?>
                            <div class="lead-card-contact-item">
                                <i class="fa-solid fa-envelope" style="color: var(--color-text-muted); width: 14px;"></i>
                                <span style="overflow: hidden; text-overflow: ellipsis;"><?php echo e($lead['email']); ?></span>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($lead['city'])): ?>
                            <div class="lead-card-contact-item">
                                <i class="fa-solid fa-location-dot" style="color: var(--color-text-muted); width: 14px;"></i>
                                <span><?php echo e($lead['city']); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Meta Details Grid -->
                    <div class="lead-card-meta-grid">
                        <div class="lead-card-meta-item">
                            <span>Assigned:</span>
                            <strong><?php echo e($lead['assigned_to_name'] ?: 'Unassigned'); ?></strong>
                        </div>
                        <div class="lead-card-meta-item">
                            <span>Source:</span>
                            <strong><?php echo e($lead['source']); ?></strong>
                        </div>
                        <?php if (!empty($lead['next_followup_date'])): ?>
                            <div class="lead-card-meta-item lead-card-meta-full">
                                <span>Next Call:</span>
                                <strong class="<?php echo $isPast ? 'text-danger' : ($isToday ? 'text-warning' : ''); ?>">
                                    <i class="fa-regular fa-clock" style="margin-right: 3px;"></i><?php echo formatDate($lead['next_followup_date'], 'd M, h:i A'); ?>
                                    <?php if ($isPast): ?><span class="badge badge-danger" style="font-size: 9px; padding: 1px 4px; margin-left: 4px;">Overdue</span><?php endif; ?>
                                    <?php if ($isToday): ?><span class="badge badge-warning" style="font-size: 9px; padding: 1px 4px; margin-left: 4px;">Today</span><?php endif; ?>
                                </strong>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Touch Actions Bar -->
                    <div class="lead-card-actions">
                        <a href="tel:<?php echo e($lead['phone']); ?>" class="btn btn-sm btn-outline-primary" title="Call directly">
                            <i class="fa-solid fa-phone"></i> Call
                        </a>
                        <button type="button" class="btn btn-sm btn-outline-success" title="WhatsApp Message" style="color: #25D366; border-color: rgba(37, 211, 102, 0.4);" onclick="openWhatsAppModal(<?php echo (int)$lead['id']; ?>, '<?php echo addslashes(e($lead['name'])); ?>', '<?php echo e($lead['phone']); ?>', <?php echo ($lead['is_manual'] ? 2 : 1); ?>, '<?php echo addslashes(e($lead['course_service_name'] ?? 'UI/UX Design')); ?>')">
                            <i class="fa-brands fa-whatsapp"></i> WhatsApp
                        </button>
                        <button type="button" class="btn btn-sm btn-primary" onclick="openUpdateStatusModal(<?php echo (int)$lead['id']; ?>, '<?php echo addslashes(e($lead['name'])); ?>', '<?php echo e($lead['status']); ?>', '<?php echo e($lead['offering_type']); ?>', <?php echo (int)($lead['course_service_id'] ?? 0); ?>, '<?php echo addslashes(e($lead['course_service_name'] ?? '')); ?>', <?php echo (float)($lead['expected_revenue'] ?? 0); ?>)" title="Update Status">
                            <i class="fa-solid fa-pen-to-square"></i> Status
                        </button>
                        <button type="button" class="btn btn-sm btn-secondary btn-icon-only" onclick="viewLeadDrawer(<?php echo (int)$lead['id']; ?>)" title="History & Notes">
                            <i class="fa-solid fa-clock-rotate-left"></i>
                        </button>
                        <?php if ($lead['status'] === 'enrolled'): ?>
                            <button type="button" class="btn btn-sm btn-icon-only" onclick="openPaymentModal(<?php echo $leadId; ?>)" title="Manage Payments" style="background: rgba(16, 185, 129, 0.15); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.3);">
                                <i class="fa-solid fa-credit-card"></i>
                            </button>
                        <?php endif; ?>
                        <?php if ($baseRole !== ROLE_SALES_COUNSELLOR): ?>
                            <button type="button" class="btn btn-sm btn-icon-only" style="color: #f59e0b; background: rgba(245, 158, 11, 0.1); border: 1px solid rgba(245, 158, 11, 0.25);" title="Reassign to Counsellor" onclick="openAssignModal(<?php echo $leadId; ?>, <?php echo (int)($lead['assigned_to'] ?? 0); ?>, '<?php echo addslashes($lead['name']); ?>')">
                                <i class="fa-solid fa-user-gear"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-icon-only text-danger" style="color: var(--color-danger); background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.25);" title="Delete Lead" onclick="confirmDeleteSingleLead(<?php echo $leadId; ?>, '<?php echo addslashes($lead['name']); ?>')">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div style="display: flex; justify-content: space-between; align-items: center; padding: 14px 18px; border-top: 1px solid var(--color-border); flex-wrap: wrap; gap: 8px;">
                <div style="font-size: 13px; color: var(--color-text-secondary);">
                    Showing page <?php echo $page; ?> of <?php echo $totalPages; ?> (<?php echo $totalLeads; ?> total leads)
                </div>
                <div style="display: flex; gap: var(--space-2);">
                    <?php if ($page > 1): ?>
                        <a href="<?php echo BASE_URL; ?>/sales/leads.php?page=<?php echo $page - 1; ?>&status=<?php echo urlencode($statusFilter); ?>&offering_type=<?php echo urlencode($offeringFilter); ?>&search=<?php echo urlencode($searchQuery); ?>" class="btn btn-sm btn-secondary">
                            &laquo; Previous
                        </a>
                    <?php endif; ?>
                    <?php if ($page < $totalPages): ?>
                        <a href="<?php echo BASE_URL; ?>/sales/leads.php?page=<?php echo $page + 1; ?>&status=<?php echo urlencode($statusFilter); ?>&offering_type=<?php echo urlencode($offeringFilter); ?>&search=<?php echo urlencode($searchQuery); ?>" class="btn btn-sm btn-secondary">
                            Next &raquo;
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<!-- ================= MODALS & DRAWERS ================= -->

<?php if ($canAddLeads): ?>
<!-- 1. ADD NEW LEAD / RAW DATA MODAL -->
<div class="modal-overlay" id="addLeadModal">
    <div class="modal" style="max-width: 620px;">
        <div class="modal-header">
            <h3 class="modal-title"><i class="fa-solid fa-user-plus" style="color: var(--color-primary); margin-right: 6px;"></i> Add Prospect / Raw Calling Data</h3>
            <button type="button" class="modal-close" onclick="closeAddLeadModal()">×</button>
        </div>

        <?php if ($baseRole !== ROLE_SALES_COUNSELLOR): ?>
        <!-- Mode Switcher Tabs (Single Entry vs Bulk Paste) -->
        <div style="display: flex; gap: 8px; padding: 12px 24px 0 24px; border-bottom: 1px solid var(--color-border); background: var(--color-bg-secondary);">
            <button type="button" id="tabSingleBtn" class="btn btn-sm btn-primary" onclick="switchAddLeadTab('single')" style="border-bottom-left-radius: 0; border-bottom-right-radius: 0;">
                <i class="fa-solid fa-user"></i> Single Candidate Entry
            </button>
            <button type="button" id="tabBulkBtn" class="btn btn-sm btn-ghost" onclick="switchAddLeadTab('bulk')" style="border-bottom-left-radius: 0; border-bottom-right-radius: 0;">
                <i class="fa-solid fa-file-import"></i> Bulk Paste Calling List
            </button>
        </div>
        <?php endif; ?>

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
                                <option value="Social Media">Instagram / Meta Ads</option>
                                <option value="Referral">Student Referral</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label">City / Location</label>
                            <input type="text" name="city" class="form-input" placeholder="e.g. Pune, Mumbai, Nagpur">
                        </div>
                    </div>

                    <?php if ($baseRole !== ROLE_SALES_COUNSELLOR): ?>
                        <div class="form-group" style="margin-bottom: var(--space-4);">
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

        <?php if ($baseRole !== ROLE_SALES_COUNSELLOR): ?>
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
                                <input type="radio" name="offering_type" value="course" <?php echo $offeringFilter === 'service' ? '' : 'checked'; ?>>
                                <div>
                                    <div style="font-weight: 700; font-size: 12px;"><i class="fa-solid fa-graduation-cap" style="color: var(--color-primary);"></i> Courses</div>
                                    <div style="font-size: 10px; color: var(--color-text-secondary);">Student CRM</div>
                                </div>
                            </label>
                            <label style="border: 2px solid #f59e0b; padding: 8px 10px; border-radius: var(--radius-md); display: flex; align-items: center; gap: 6px; cursor: pointer; background: rgba(245, 158, 11, 0.08);">
                                <input type="radio" name="offering_type" value="service" <?php echo $offeringFilter === 'service' ? 'checked' : ''; ?>>
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

                    <?php if ($baseRole !== ROLE_SALES_COUNSELLOR): ?>
                        <div class="form-group" style="margin-bottom: var(--space-3); background: var(--color-bg-secondary); padding: 12px; border-radius: var(--radius-md); border: 1px solid var(--color-border);">
                            <label class="form-label" style="font-weight: 700; margin-bottom: 8px;">
                                <i class="fa-solid fa-arrows-split-up-and-left" style="color: var(--color-primary); margin-right: 5px;"></i>Lead Assignment on Upload
                            </label>
                            
                            <div style="display: flex; gap: 10px; margin-bottom: 12px; flex-wrap: wrap;">
                                <label style="display: flex; align-items: center; gap: 6px; cursor: pointer; font-size: 13px; font-weight: 600;">
                                    <input type="radio" name="assign_mode" value="auto_distribute" checked onchange="toggleLeadsImportAssignMode('auto_distribute')">
                                    <span>🔀 Auto-Divide (Round Robin)</span>
                                </label>
                                <label style="display: flex; align-items: center; gap: 6px; cursor: pointer; font-size: 13px; font-weight: 600;">
                                    <input type="radio" name="assign_mode" value="single" onchange="toggleLeadsImportAssignMode('single')">
                                    <span>👤 Single Person</span>
                                </label>
                                <label style="display: flex; align-items: center; gap: 6px; cursor: pointer; font-size: 13px; font-weight: 600;">
                                    <input type="radio" name="assign_mode" value="unassigned" onchange="toggleLeadsImportAssignMode('unassigned')">
                                    <span>📥 Leave Unassigned</span>
                                </label>
                            </div>

                            <div id="leads-import-distribute-wrap">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                                    <span style="font-size: 12px; color: var(--color-text-secondary);">Divide equally among selected staff:</span>
                                    <button type="button" class="btn btn-sm btn-link" onclick="toggleAllLeadsImportCounsellors()" style="font-size: 11.5px; padding: 0;">Toggle Select All</button>
                                </div>
                                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(170px, 1fr)); gap: 6px; max-height: 120px; overflow-y: auto; padding: 4px; background: var(--color-bg-card); border-radius: var(--radius-sm); border: 1px solid var(--color-border);">
                                    <?php foreach ($counsellors as $cn): ?>
                                        <label style="display: flex; align-items: center; gap: 6px; font-size: 12px; padding: 4px 6px; border-radius: var(--radius-sm); cursor: pointer; background: rgba(99,102,241,0.04);">
                                            <input type="checkbox" name="distribute_counsellors[]" value="<?php echo $cn['id']; ?>" class="leads-import-counsellor-cb" checked>
                                            <span style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?php echo e($cn['name']); ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <div id="leads-import-single-wrap" style="display: none;">
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

                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">Paste Raw Data *</label>
                        <textarea name="raw_data" id="bulkRawData" class="form-textarea" rows="6" required style="font-family: monospace; font-size: 12px;" placeholder="Rahul Sharma, B.Tech CS, 9876543210, rahul@gmail.com&#10;Priya Nair, BCA, 9890123456, priya@gmail.com&#10;Amit Patel, MCA, 9823456789, amit@gmail.com"></textarea>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeAddLeadModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="bulkImportBtn">
                        <i class="fa-solid fa-file-import"></i> Import & Distribute
                    </button>
                </div>
            </form>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

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
                        <?php echo $salesMetrics['unassigned_leads'] ?? $salesMetrics['new_leads'] ?? 0; ?> Leads
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
                        <button type="button" class="btn btn-sm btn-outline" onclick="setCountPreset(<?php echo max(1, (int)($salesMetrics['unassigned_leads'] ?? $salesMetrics['new_leads'] ?? 50)); ?>)" style="padding: 3px 9px; font-size: 12px; color: #f59e0b;">All Available</button>
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
<?php endif; ?>

<!-- ================= REASSIGN LEAD MODAL (Founder / Manager / HR) ================= -->
<div class="modal-overlay" id="modal-assign-lead">
    <div class="modal" style="max-width: 480px;">
        <div class="modal-header">
            <h3 class="modal-title"><i class="fa-solid fa-user-gear" style="color: #f59e0b; margin-right: 8px;"></i>Reassign Lead</h3>
            <button type="button" class="modal-close" onclick="closeAssignModal()">&times;</button>
        </div>
        <form id="form-assign-lead" onsubmit="submitAssignLead(event)">
            <?php echo csrfField(); ?>
            <input type="hidden" name="action" value="assign_lead">
            <input type="hidden" name="lead_id" id="assign_lead_id" value="">

            <div class="modal-body" style="padding: var(--space-5); display: flex; flex-direction: column; gap: var(--space-3);">
                <div style="font-size: 13px;">
                    Reassigning Lead: <strong id="assign_candidate_name" style="color: var(--color-primary);"></strong>
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
                    <i class="fa-solid fa-check"></i> Assign Lead
                </button>
            </div>
        </form>
    </div>
</div>

<!-- 2. UPDATE STATUS MODAL -->
<div class="modal-overlay" id="updateStatusModal">
    <div class="modal" style="max-width: 520px;">
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
                <div class="form-group" style="margin-bottom: var(--space-3);">
                    <label class="form-label">Lead Status *</label>
                    <select name="status" id="statusModalSelect" class="form-select" onchange="toggleStatusEnrollmentPaymentSection()" required>
                        <option value="new_lead">New Lead (Not Contacted / In Queue)</option>
                        <option value="interested">Interested (In Discussion / Demo Scheduled)</option>
                        <option value="enrolled">Enrolled (Paid / Converted)</option>
                        <option value="uninterested">Uninterested (Lost / Dropped)</option>
                    </select>
                </div>

                <!-- Course / Service & Deal Value / Amount -->
                <div style="background: var(--color-bg-secondary); border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: 14px; margin-bottom: var(--space-3);">
                    <div style="font-weight: 700; font-size: 12.5px; color: var(--color-text-main); margin-bottom: 10px; display: flex; align-items: center; gap: 6px;">
                        <i class="fa-solid fa-graduation-cap" style="color: var(--color-primary);"></i> Program / Service & Deal Amount
                    </div>

                    <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 10px;">
                        <div>
                            <label class="form-label" style="font-size: 11.5px;">Offering Type</label>
                            <select name="offering_type" id="statusOfferingType" class="form-select" onchange="syncStatusOfferingDropdown()">
                                <option value="course">Training Courses</option>
                                <option value="service">IT Services</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label" style="font-size: 11.5px;">Deal Amount / Fee (₹)</label>
                            <input type="number" step="0.01" min="0" name="expected_revenue" id="statusExpRev" class="form-input" placeholder="e.g. 40000" oninput="renderStatusInstallmentInputs()">
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" style="font-size: 11.5px;">Select Course / IT Service Package</label>
                        <select id="statusOfferingSelect" class="form-select" onchange="syncStatusOfferingName(this)">
                            <option value="">-- Choose Course / Service --</option>
                        </select>
                        <input type="hidden" name="course_service_id" id="statusCsId" value="">
                        <input type="hidden" name="course_service_name" id="statusCsName" value="">
                    </div>
                </div>

                <!-- Enrolled Course / Service Payment Plan Setup (Shown if status == 'enrolled') -->
                <div id="statusEnrollmentPaymentSection" style="display: none; background: rgba(16, 185, 129, 0.08); border: 1px solid rgba(16, 185, 129, 0.3); border-radius: var(--radius-md); padding: 14px; margin-bottom: var(--space-3);">
                    <div style="font-weight: 700; font-size: 12.5px; color: #10b981; margin-bottom: 8px; display: flex; align-items: center; justify-content: space-between;">
                        <span id="statusPayPlanHeaderTitle"><i class="fa-solid fa-credit-card"></i> Payment Plan & Installments</span>
                        <label style="font-size: 11px; font-weight: normal; color: var(--color-text-secondary); cursor: pointer; display: flex; align-items: center; gap: 4px;">
                            <input type="checkbox" name="setup_payment_plan" id="statusSetupPayPlanCheck" value="1" checked onchange="toggleStatusPayPlanDetails()"> Setup Plan
                        </label>
                    </div>

                    <div id="statusPayPlanDetails">
                        <!-- Course Selector Options -->
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

                        <!-- Service Scope-based Presets (Only shown for Service) -->
                        <div id="statusServiceMilestonePresets" style="display: none; margin-bottom: 10px;">
                            <div style="font-size: 11px; color: var(--color-text-muted); margin-bottom: 4px;">Scope-Based Milestone Splits:</div>
                            <div style="display: flex; gap: 6px; flex-wrap: wrap;">
                                <button type="button" class="btn btn-sm btn-secondary" onclick="applyStatusServicePreset(1)" style="font-size: 11px; padding: 2px 8px;">1-Time (100%)</button>
                                <button type="button" class="btn btn-sm btn-secondary" onclick="applyStatusServicePreset(2)" style="font-size: 11px; padding: 2px 8px;">2 Milestones (50/50)</button>
                                <button type="button" class="btn btn-sm btn-secondary" onclick="applyStatusServicePreset(3)" style="font-size: 11px; padding: 2px 8px;">3 Milestones (40/30/30)</button>
                                <button type="button" class="btn btn-sm btn-secondary" onclick="applyStatusServicePreset(4)" style="font-size: 11px; padding: 2px 8px;">4 Milestones (30/30/30/10)</button>
                            </div>
                        </div>

                        <!-- Dynamic Installment / Milestone Rows Container -->
                        <div id="statusInstallmentsContainer" style="display: flex; flex-direction: column; gap: 8px; margin-bottom: 10px;">
                            <!-- Injected by JS -->
                        </div>

                        <div id="statusAddMilestoneBtnWrapper" style="display: none; text-align: right; margin-bottom: 8px;">
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="addStatusServiceMilestoneRow()" style="font-size: 11.5px; padding: 3px 8px;">
                                <i class="fa-solid fa-plus-circle"></i> Add Custom Milestone
                            </button>
                        </div>

                        <input type="hidden" name="installments_json" id="statusInstallmentsJson" value="">
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: var(--space-3);">
                    <label class="form-label">Next Follow-up Date & Time</label>
                    <input type="datetime-local" name="next_followup_date" id="statusModalFollowup" class="form-input">
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Call Discussion / Activity Remarks *</label>
                    <textarea name="remarks" id="statusModalRemarks" class="form-textarea" rows="2" required placeholder="What was discussed in this call/WhatsApp message?"></textarea>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeUpdateStatusModal()">Cancel</button>
                <button type="submit" class="btn btn-primary" id="updateStatusBtn">Save Update</button>
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

function syncStatusOfferingDropdown() {
    const type = document.getElementById('statusOfferingType').value;
    const select = document.getElementById('statusOfferingSelect');
    select.innerHTML = '<option value="">-- Choose ' + (type === 'course' ? 'Course' : 'IT Service') + ' --</option>';
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

function syncStatusOfferingName(selectEl) {
    const selectedOption = selectEl.options[selectEl.selectedIndex];
    if (selectedOption && selectedOption.dataset.name) {
        document.getElementById('statusCsId').value = selectedOption.value;
        document.getElementById('statusCsName').value = selectedOption.dataset.name;
        if (selectedOption.dataset.price && parseFloat(selectedOption.dataset.price) > 0) {
            document.getElementById('statusExpRev').value = selectedOption.dataset.price;
        }
    } else {
        document.getElementById('statusCsId').value = '';
        document.getElementById('statusCsName').value = '';
    }
}

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
    const singleContent = document.getElementById('tabSingleContent');
    const bulkContent = document.getElementById('tabBulkContent');
    const singleBtn = document.getElementById('tabSingleBtn');
    const bulkBtn = document.getElementById('tabBulkBtn');

    if (tab === 'single' || !bulkContent) {
        if (singleContent) singleContent.style.display = 'block';
        if (bulkContent) bulkContent.style.display = 'none';
        if (singleBtn) singleBtn.className = 'btn btn-sm btn-primary';
        if (bulkBtn) bulkBtn.className = 'btn btn-sm btn-ghost';
    } else {
        if (singleContent) singleContent.style.display = 'none';
        if (bulkContent) bulkContent.style.display = 'block';
        if (singleBtn) singleBtn.className = 'btn btn-sm btn-ghost';
        if (bulkBtn) bulkBtn.className = 'btn btn-sm btn-primary';
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
    const totalFee = parseFloat(document.getElementById('statusExpRev')?.value) || 0;
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

let statusServiceCustomMilestones = [];

function renderStatusInstallmentInputs() {
    const container = document.getElementById('statusInstallmentsContainer');
    const offeringType = document.getElementById('statusOfferingType')?.value || 'course';
    const isService = (offeringType === 'service');
    const totalFee = parseFloat(document.getElementById('statusExpRev').value) || 0;

    const courseSelectWrapper = document.getElementById('statusCoursePlanSelectWrapper');
    const servicePresetsWrapper = document.getElementById('statusServiceMilestonePresets');
    const addMilestoneBtnWrapper = document.getElementById('statusAddMilestoneBtnWrapper');
    const headerTitle = document.getElementById('statusPayPlanHeaderTitle');
    const collectedBox = document.getElementById('statusCollectedBox');

    if (!container) return;

    if (isService) {
        if (headerTitle) headerTitle.innerHTML = '<i class="fa-solid fa-diagram-project"></i> Project Scope-Based Milestones';
        if (courseSelectWrapper) courseSelectWrapper.style.display = 'none';
        if (servicePresetsWrapper) servicePresetsWrapper.style.display = 'block';
        if (addMilestoneBtnWrapper) addMilestoneBtnWrapper.style.display = 'block';

        if (statusServiceCustomMilestones.length === 0) {
            applyStatusServicePreset(3);
            return;
        }

        let html = '';
        statusServiceCustomMilestones.forEach((m, idx) => {
            const num = idx + 1;
            html += `
                <div class="status-service-ms-row" style="padding: 8px 10px; background: var(--color-bg-card); border: 1px solid var(--color-border); border-radius: var(--radius-md);">
                    <div style="display: grid; grid-template-columns: 2fr 1fr 1fr 1fr auto; gap: 6px; align-items: center;">
                        <div>
                            <label style="font-size: 10px; color: var(--color-text-muted); display: block;">Milestone ${num} Title *</label>
                            <input type="text" class="form-input status-ms-title" value="${m.title}" style="font-size: 11px; padding: 4px 6px;" required>
                        </div>
                        <div>
                            <label style="font-size: 10px; color: var(--color-text-muted); display: block;">Amount (₹) *</label>
                            <input type="number" step="any" min="0" class="form-input status-ms-amt" value="${m.amount}" style="font-size: 11px; padding: 4px 6px;" required>
                        </div>
                        <div>
                            <label style="font-size: 10px; color: var(--color-text-muted); display: block;">Target Date</label>
                            <input type="date" class="form-input status-ms-due" value="${m.due_date}" style="font-size: 11px; padding: 4px 6px;">
                        </div>
                        <div>
                            <label style="font-size: 10px; color: var(--color-text-muted); display: block;">Paid Now (₹)</label>
                            <input type="number" step="any" min="0" class="form-input status-ms-paid" value="${m.paid_amount || 0}" placeholder="0" style="font-size: 11px; padding: 4px 6px;">
                        </div>
                        <div style="padding-top: 14px;">
                            <button type="button" class="btn btn-sm btn-ghost text-danger" onclick="removeStatusServiceMilestoneRow(this)" style="padding: 4px 6px;">
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
                            <input type="number" step="any" min="0" id="planInstPaid_1" class="form-input" style="font-size: 11.5px; padding: 4px 8px;" placeholder="0.00" value="${initialPaid > 0 ? initialPaid : ''}">
                        </div>
                        <div>
                            <label style="font-size: 10.5px; color: var(--color-text-muted); display: block; margin-bottom: 2px;">Payment Method</label>
                            <select id="planInstMethod_1" class="form-select" style="font-size: 11.5px; padding: 4px 8px;">
                                <option value="upi">UPI / GPay / PhonePe</option>
                                <option value="cash">Cash</option>
                                <option value="bank_transfer">Bank Transfer / NEFT</option>
                                <option value="card">Card / POS</option>
                                <option value="cheque">Cheque</option>
                            </select>
                        </div>
                        <div>
                            <label style="font-size: 10.5px; color: var(--color-text-muted); display: block; margin-bottom: 2px;">Txn / UTR Ref</label>
                            <input type="text" id="planInstTxn_1" class="form-input" style="font-size: 11.5px; padding: 4px 8px;" placeholder="UTR # (optional)">
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
                                <input type="number" step="any" min="0" id="planInstAmt_${i}" class="form-input" style="font-size: 11.5px; padding: 4px 8px; font-weight: 600;" value="${instAmt}">
                            </div>
                            <div>
                                <label style="font-size: 10.5px; color: var(--color-text-muted); display: block; margin-bottom: 2px;">Due Date</label>
                                <input type="date" id="planInstDue_${i}" class="form-input" style="font-size: 11.5px; padding: 4px 8px;" value="${dueStr}">
                            </div>
                            <div>
                                <label style="font-size: 10.5px; color: var(--color-text-muted); display: block; margin-bottom: 2px;">Paid Now (₹)</label>
                                <input type="number" step="any" min="0" id="planInstPaid_${i}" class="form-input" style="font-size: 11.5px; padding: 4px 8px; font-weight: 600; color: #10b981;" placeholder="0" value="${paidNowVal}">
                            </div>
                            <div>
                                <label style="font-size: 10.5px; color: var(--color-text-muted); display: block; margin-bottom: 2px;">Method</label>
                                <select id="planInstMethod_${i}" class="form-select" style="font-size: 11.5px; padding: 4px 8px;">
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

function applyStatusServicePreset(count) {
    const totalFee = parseFloat(document.getElementById('statusExpRev').value) || 50000;
    statusServiceCustomMilestones = [];

    if (count === 1) {
        statusServiceCustomMilestones.push({
            title: '1-Time Full Project Advance',
            amount: totalFee,
            due_date: new Date().toISOString().split('T')[0],
            paid_amount: totalFee
        });
    } else if (count === 2) {
        const half = Math.round(totalFee / 2);
        const d2 = new Date(); d2.setDate(d2.getDate() + 30);
        statusServiceCustomMilestones.push(
            { title: 'Milestone 1: Project Kickoff & Advance (50%)', amount: half, due_date: new Date().toISOString().split('T')[0], paid_amount: half },
            { title: 'Milestone 2: Final Delivery & Handover (50%)', amount: totalFee - half, due_date: d2.toISOString().split('T')[0], paid_amount: 0 }
        );
    } else if (count === 3) {
        const p1 = Math.round(totalFee * 0.40);
        const p2 = Math.round(totalFee * 0.30);
        const p3 = totalFee - p1 - p2;
        const d2 = new Date(); d2.setDate(d2.getDate() + 20);
        const d3 = new Date(); d3.setDate(d3.getDate() + 45);
        statusServiceCustomMilestones.push(
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
        statusServiceCustomMilestones.push(
            { title: 'Milestone 1: Advance / Kickoff & Architecture (30%)', amount: p1, due_date: new Date().toISOString().split('T')[0], paid_amount: p1 },
            { title: 'Milestone 2: UI/UX & Backend Integration (30%)', amount: p2, due_date: d2.toISOString().split('T')[0], paid_amount: 0 },
            { title: 'Milestone 3: Beta Testing & Client Review (30%)', amount: p3, due_date: d3.toISOString().split('T')[0], paid_amount: 0 },
            { title: 'Milestone 4: Production Deployment & Handover (10%)', amount: p4, due_date: d4.toISOString().split('T')[0], paid_amount: 0 }
        );
    }

    renderStatusInstallmentInputs();
}

function addStatusServiceMilestoneRow() {
    const nextNum = statusServiceCustomMilestones.length + 1;
    const today = new Date();
    today.setDate(today.getDate() + (nextNum * 15));
    statusServiceCustomMilestones.push({
        title: `Milestone ${nextNum}: Deliverable Phase`,
        amount: 0,
        due_date: today.toISOString().split('T')[0],
        paid_amount: 0
    });
    renderStatusInstallmentInputs();
}

function removeStatusServiceMilestoneRow(btn) {
    const row = btn.closest('.status-service-ms-row');
    const container = document.getElementById('statusInstallmentsContainer');
    const allRows = container.querySelectorAll('.status-service-ms-row');
    if (allRows.length <= 1) {
        alert('At least one milestone is required.');
        return;
    }
    const idx = Array.from(allRows).indexOf(row);
    if (idx > -1) {
        statusServiceCustomMilestones.splice(idx, 1);
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
        jsonInput.value = '';
        return;
    }

    const totalFee = parseFloat(document.getElementById('statusExpRev').value) || 0;
    const installments = [];

    if (isService) {
        const rows = document.querySelectorAll('.status-service-ms-row');
        rows.forEach((row, idx) => {
            const title = row.querySelector('.status-ms-title')?.value.trim() || `Milestone ${idx + 1}`;
            const amt = parseFloat(row.querySelector('.status-ms-amt')?.value) || 0;
            const due = row.querySelector('.status-ms-due')?.value || new Date().toISOString().split('T')[0];
            const paid = parseFloat(row.querySelector('.status-ms-paid')?.value) || 0;

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
        const planType = document.getElementById('statusPayPlanType').value;
        if (planType === 'one_time') {
            const paid = parseFloat(document.getElementById('planInstPaid_1')?.value) || 0;
            const method = document.getElementById('planInstMethod_1')?.value || 'upi';
            const txn = document.getElementById('planInstTxn_1')?.value || '';
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
                const amt = parseFloat(document.getElementById(`planInstAmt_${i}`)?.value) || 0;
                const due = document.getElementById(`planInstDue_${i}`)?.value || new Date().toISOString().split('T')[0];
                const paid = parseFloat(document.getElementById(`planInstPaid_${i}`)?.value) || 0;
                const method = document.getElementById(`planInstMethod_${i}`)?.value || 'upi';
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

    jsonInput.value = JSON.stringify(installments);
}

function openUpdateStatusModal(leadId, leadName, currentStatus, offeringType = 'course', courseServiceId = 0, courseServiceName = '', expectedRevenue = 0) {
    document.getElementById('statusModalLeadId').value = leadId;
    document.getElementById('modalLeadName').textContent = leadName;
    document.getElementById('statusModalSelect').value = currentStatus;
    document.getElementById('statusModalRemarks').value = '';

    const typeSelect = document.getElementById('statusOfferingType');
    if (typeSelect) {
        typeSelect.value = (offeringType === 'service') ? 'service' : 'course';
        syncStatusOfferingDropdown();
    }

    const offeringSelect = document.getElementById('statusOfferingSelect');
    if (offeringSelect) {
        if (courseServiceId > 0) {
            offeringSelect.value = courseServiceId;
        } else {
            offeringSelect.value = '';
        }
    }
    document.getElementById('statusCsId').value = courseServiceId || '';
    document.getElementById('statusCsName').value = courseServiceName || '';
    document.getElementById('statusExpRev').value = expectedRevenue > 0 ? expectedRevenue : '';

    toggleStatusEnrollmentPaymentSection();
    document.getElementById('updateStatusModal').classList.add('active');
}

function closeUpdateStatusModal() {
    document.getElementById('updateStatusModal').classList.remove('active');
}

function viewLeadDrawer(leadId) {
    document.getElementById('leadDrawerModal').classList.add('active');
    document.getElementById('drawerContent').innerHTML = '<div style="text-align: center; padding: 40px;"><i class="fa-solid fa-spinner fa-spin fa-2x" style="color: var(--color-primary);"></i></div>';

    fetch('<?php echo BASE_URL; ?>/api/leads.php?action=get_payment_info&lead_id=' + leadId)
    .then(res => res.json())
    .then(payData => {
        return fetch('<?php echo BASE_URL; ?>/api/leads.php?action=get_lead&lead_id=' + leadId)
            .then(res => res.json())
            .then(data => ({ data, payData }));
    })
    .then(({ data, payData }) => {
        if (!data.success || !data.lead) {
            document.getElementById('drawerContent').innerHTML = '<div style="color: var(--color-danger); padding: 20px;">Could not load lead details.</div>';
            return;
        }

        const l = data.lead;
        const pSummary = payData.success ? payData.payment_summary : null;

        document.getElementById('drawerLeadName').textContent = l.name;
        document.getElementById('drawerLeadMeta').textContent = l.offering_type.toUpperCase() + ': ' + l.course_service_name + ' • ' + l.phone;

        let actHtml = '';
        if (l.activities && l.activities.length > 0) {
            l.activities.forEach(a => {
                let badge = '<span class="badge badge-neutral" style="font-size: 10px;">Note</span>';
                if (a.activity_type === 'call') badge = '<span class="badge badge-info" style="font-size: 10px;"><i class="fa-solid fa-phone"></i> Call</span>';
                if (a.activity_type === 'whatsapp') badge = '<span class="badge badge-success" style="font-size: 10px;"><i class="fa-brands fa-whatsapp"></i> WhatsApp</span>';
                if (a.activity_type === 'status_change') badge = '<span class="badge badge-warning" style="font-size: 10px;"><i class="fa-solid fa-shuffle"></i> Status Changed</span>';

                actHtml += `
                    <div style="padding: 12px; background: var(--color-bg-tertiary); border-radius: var(--radius-md); margin-bottom: 8px; border-left: 3px solid var(--color-primary);">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                            <div>${badge} <strong style="font-size: 12px; color: var(--color-text-main); margin-left: 4px;">${a.user_name}</strong></div>
                            <span style="font-size: 11px; color: var(--color-text-secondary);">${new Date(a.created_at).toLocaleString()}</span>
                        </div>
                        <div style="font-size: 13px; color: var(--color-text-main);">${a.remarks}</div>
                        ${a.next_followup ? `<div style="font-size: 11px; color: var(--color-primary); margin-top: 4px;"><i class="fa-regular fa-clock"></i> Next Follow-up: ${new Date(a.next_followup).toLocaleString()}</div>` : ''}
                    </div>
                `;
            });
        } else {
            actHtml = '<div style="font-size: 13px; color: var(--color-text-muted); padding: 10px 0;">No prior activity recorded.</div>';
        }

        const phoneClean = l.phone.replace(/[^0-9]/g, '');

        // Payments Card for Enrolled Leads
        let paymentBoxHtml = '';
        if (l.status === 'enrolled' || (pSummary && pSummary.paid_amount > 0)) {
            const totFee = pSummary ? pSummary.total_fee : parseFloat(l.expected_revenue) || 0;
            const totPaid = pSummary ? pSummary.paid_amount : parseFloat(l.paid_amount) || 0;
            const balDue = pSummary ? pSummary.balance_due : Math.max(0, totFee - totPaid);
            const planLabel = (pSummary && pSummary.payment_plan === 'installment') ? `${pSummary.total_installments} Installments Plan` : '1-Time Payment';

            let instListHtml = '';
            if (pSummary && pSummary.installments && pSummary.installments.length > 0) {
                pSummary.installments.forEach(inst => {
                    let stBadge = '<span class="badge badge-warning" style="font-size: 10px;">Pending</span>';
                    if (inst.status === 'paid') stBadge = '<span class="badge badge-success" style="font-size: 10px;"><i class="fa-solid fa-check"></i> Paid</span>';
                    if (inst.status === 'partially_paid') stBadge = '<span class="badge badge-warning" style="font-size: 10px;"><i class="fa-solid fa-hourglass-half"></i> Partial</span>';
                    if (inst.status === 'overdue') stBadge = '<span class="badge badge-danger" style="font-size: 10px;"><i class="fa-solid fa-triangle-exclamation"></i> Overdue</span>';

                    instListHtml += `
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 8px 10px; background: var(--color-bg-secondary); border-radius: var(--radius-sm); border: 1px solid var(--color-border); font-size: 12px; margin-bottom: 6px;">
                            <div>
                                <strong>${inst.title}</strong>
                                <div style="font-size: 11px; color: var(--color-text-muted);">Due: ${inst.due_date || 'N/A'} • ₹${parseFloat(inst.amount).toLocaleString()}</div>
                            </div>
                            <div style="text-align: right;">
                                <div>${stBadge}</div>
                                <div style="font-size: 11px; color: #10b981; font-weight: 600; margin-top: 2px;">Recv: ₹${parseFloat(inst.paid_amount).toLocaleString()}</div>
                            </div>
                        </div>
                    `;
                });
            }

            paymentBoxHtml = `
                <div style="background: rgba(16, 185, 129, 0.08); border: 1px solid rgba(16, 185, 129, 0.3); border-radius: var(--radius-md); padding: 14px; margin-bottom: 16px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; flex-wrap: wrap; gap: 8px;">
                        <div>
                            <span style="font-size: 13px; font-weight: 700; color: #10b981;"><i class="fa-solid fa-credit-card"></i> Student Fee & Payment Status</span>
                            <div style="font-size: 11px; color: var(--color-text-secondary);">${planLabel}</div>
                        </div>
                        <button type="button" class="btn btn-sm btn-success" onclick="closeLeadDrawer(); openPaymentModal(${l.id})" style="padding: 4px 10px; font-size: 11.5px;">
                            <i class="fa-solid fa-pen-to-square"></i> Record / Update Payment
                        </button>
                    </div>

                    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; margin-bottom: 10px;">
                        <div style="background: var(--color-bg-card); padding: 8px; border-radius: var(--radius-sm); border: 1px solid var(--color-border); text-align: center;">
                            <div style="font-size: 10px; color: var(--color-text-muted); text-transform: uppercase;">Total Fee</div>
                            <div style="font-size: 14px; font-weight: 700; color: var(--color-text-white);">₹${totFee.toLocaleString()}</div>
                        </div>
                        <div style="background: var(--color-bg-card); padding: 8px; border-radius: var(--radius-sm); border: 1px solid var(--color-border); text-align: center;">
                            <div style="font-size: 10px; color: var(--color-text-muted); text-transform: uppercase;">Collected</div>
                            <div style="font-size: 14px; font-weight: 700; color: #10b981;">₹${totPaid.toLocaleString()}</div>
                        </div>
                        <div style="background: var(--color-bg-card); padding: 8px; border-radius: var(--radius-sm); border: 1px solid var(--color-border); text-align: center;">
                            <div style="font-size: 10px; color: var(--color-text-muted); text-transform: uppercase;">Balance Due</div>
                            <div style="font-size: 14px; font-weight: 700; color: ${balDue > 0 ? '#ef4444' : '#10b981'};">₹${balDue.toLocaleString()}</div>
                        </div>
                    </div>

                    ${instListHtml}
                </div>
            `;
        }

        document.getElementById('drawerContent').innerHTML = `
            <!-- Quick Outreach Bar -->
            <div style="display: flex; gap: 10px; margin-bottom: 16px; padding: 12px 16px; background: var(--color-bg-secondary); border-radius: var(--radius-md); border: 1px solid var(--color-border); align-items: center; justify-content: space-between; flex-wrap: wrap;">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <span style="font-size: 12px; color: var(--color-text-muted); font-weight: 500;">Quick Outreach:</span>
                    <a href="tel:${l.phone}" class="btn btn-sm btn-outline-primary" style="display: inline-flex; align-items: center; gap: 5px;">
                        <i class="fa-solid fa-phone"></i> Call ${l.phone}
                    </a>
                    <button type="button" class="btn btn-sm btn-outline-success" style="display: inline-flex; align-items: center; gap: 5px; cursor: pointer;" onclick="openWhatsAppModal(${l.id}, '${l.name.replace(/'/g, "\\'")}', '${l.phone}', ${l.is_manual ? 2 : 1}, '${(l.course_service_name || 'UI/UX Design').replace(/'/g, "\\'")}')">
                        <i class="fa-brands fa-whatsapp"></i> Chat on WhatsApp
                    </button>
                </div>
                <div style="display: flex; gap: 6px;">
                    ${l.status === 'enrolled' ? `
                        <button type="button" class="btn btn-sm btn-success" onclick="closeLeadDrawer(); openPaymentModal(${l.id})" style="display: inline-flex; align-items: center; gap: 4px;">
                            <i class="fa-solid fa-credit-card"></i> Payments
                        </button>
                    ` : ''}
                    <button type="button" class="btn btn-sm btn-primary" onclick="closeLeadDrawer(); openUpdateStatusModal(${l.id}, '${l.name.replace(/'/g, "\\'")}', '${l.status}', '${l.offering_type || 'course'}', ${parseInt(l.course_service_id) || 0}, '${(l.course_service_name || '').replace(/'/g, "\\'")}', ${parseFloat(l.expected_revenue) || 0})">
                        <i class="fa-solid fa-pen-to-square"></i> Change Status
                    </button>
                </div>
            </div>

            <!-- Payment Summary Card (if Enrolled) -->
            ${paymentBoxHtml}

            <!-- Info Summary Grid -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 10px; margin-bottom: 16px; background: var(--color-bg-tertiary); padding: 14px; border-radius: var(--radius-md); border: 1px solid var(--color-border);">
                <div>
                    <strong style="font-size: 10px; color: var(--color-text-muted); text-transform: uppercase;">Graduation / Degree:</strong> 
                    <div style="font-size: 13px; font-weight: 600; color: var(--color-primary-light);">${l.graduation || '—'}</div>
                </div>
                <div>
                    <strong style="font-size: 10px; color: var(--color-text-muted); text-transform: uppercase;">Phone:</strong> 
                    <div style="font-size: 13px; font-weight: 600;"><a href="tel:${l.phone}" style="color: var(--color-text-white); text-decoration: none;">${l.phone}</a></div>
                </div>
                <div>
                    <strong style="font-size: 10px; color: var(--color-text-muted); text-transform: uppercase;">Email:</strong> 
                    <div style="font-size: 12px; color: var(--color-text-secondary);">${l.email || '—'}</div>
                </div>
                <div>
                    <strong style="font-size: 10px; color: var(--color-text-muted); text-transform: uppercase;">Source:</strong> 
                    <div style="font-size: 12px;">${l.source}</div>
                </div>
                <div>
                    <strong style="font-size: 10px; color: var(--color-text-muted); text-transform: uppercase;">Target Offering:</strong> 
                    <div style="font-size: 12px; font-weight: 600;">${l.course_service_name}</div>
                </div>
                <div>
                    <strong style="font-size: 10px; color: var(--color-text-muted); text-transform: uppercase;">Status:</strong> 
                    <div style="font-size: 12px; font-weight: 600; text-transform: capitalize;">${l.status.replace('_', ' ')}</div>
                </div>
            </div>

            <!-- Quick Log Note Box -->
            <div style="background: var(--color-bg-card); border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: 14px; margin-bottom: 20px;">
                <h4 style="margin: 0 0 10px 0; font-size: 13px; font-weight: 600;"><i class="fa-solid fa-plus-circle" style="color: var(--color-primary);"></i> Log Call / WhatsApp Conversation</h4>
                <form onsubmit="submitQuickActivity(event, ${l.id})">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 10px;">
                        <div>
                            <label class="form-label" style="font-size: 11px;">Interaction Channel</label>
                            <select name="activity_type" id="quickActType" class="form-select" style="font-size: 12px; padding: 6px 10px;">
                                <option value="call">Phone Call</option>
                                <option value="whatsapp">WhatsApp Message</option>
                                <option value="meeting">In-Person / Demo</option>
                                <option value="email">Email</option>
                                <option value="note">Internal Note</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label" style="font-size: 11px;">Next Follow-up Call (optional)</label>
                            <input type="datetime-local" name="next_followup_date" id="quickActFollowup" class="form-input" style="font-size: 12px; padding: 6px 10px;">
                        </div>
                    </div>
                    <div style="margin-bottom: 10px;">
                        <textarea name="remarks" id="quickActRemarks" class="form-textarea" rows="2" placeholder="What did the prospect say? Discussion summary..." required style="font-size: 12px; min-height: 60px;"></textarea>
                    </div>
                    <div style="display: flex; justify-content: flex-end; gap: 8px;">
                        <button type="submit" class="btn btn-sm btn-primary" id="quickActBtn">
                            <i class="fa-solid fa-paper-plane"></i> Save Call Log
                        </button>
                    </div>
                </form>
            </div>

            <!-- Activity Stream -->
            <h4 style="margin: 0 0 12px 0; font-size: 14px; font-weight: 600;"><i class="fa-solid fa-timeline" style="color: var(--color-primary);"></i> Call & Interaction Trail</h4>
            <div>${actHtml}</div>
        `;
    })
    .catch(err => {
        document.getElementById('drawerContent').innerHTML = '<div style="color: var(--color-danger); padding: 20px;">Error loading details.</div>';
    });
}

function closeLeadDrawer() {
    document.getElementById('leadDrawerModal').classList.remove('active');
}

// Import Assignment Mode Toggles
function toggleLeadsImportAssignMode(mode) {
    const distWrap = document.getElementById('leads-import-distribute-wrap');
    const singleWrap = document.getElementById('leads-import-single-wrap');
    if (distWrap) distWrap.style.display = (mode === 'auto_distribute') ? 'block' : 'none';
    if (singleWrap) singleWrap.style.display = (mode === 'single') ? 'block' : 'none';
}

function toggleAllLeadsImportCounsellors() {
    const cbs = document.querySelectorAll('.leads-import-counsellor-cb');
    const allChecked = Array.from(cbs).every(cb => cb.checked);
    cbs.forEach(cb => cb.checked = !allChecked);
}

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
            btn.innerHTML = '<i class="fa-solid fa-file-import"></i> Import & Distribute';
        }
    })
    .catch(err => {
        alert('Request failed.');
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-file-import"></i> Import & Distribute';
    });
}

function submitQuickActivity(e, leadId) {
    e.preventDefault();
    const btn = document.getElementById('quickActBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';

    const formData = new FormData();
    formData.append('action', 'add_activity');
    formData.append('lead_id', leadId);
    formData.append('activity_type', document.getElementById('quickActType').value);
    formData.append('remarks', document.getElementById('quickActRemarks').value);
    formData.append('next_followup_date', document.getElementById('quickActFollowup').value);
    formData.append('csrf_token', '<?php echo csrfToken(); ?>');

    fetch('<?php echo BASE_URL; ?>/api/leads.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            viewLeadDrawer(leadId);
        } else {
            alert(data.message || 'Error');
            btn.disabled = false;
            btn.innerHTML = 'Add Note';
        }
    })
    .catch(err => {
        alert('Request failed.');
        btn.disabled = false;
        btn.innerHTML = 'Add Note';
    });
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

let currentPaymentLeadId = null;

function openPaymentModal(leadId) {
    currentPaymentLeadId = leadId;
    const modal = document.getElementById('managePaymentsModal');
    const body = document.getElementById('payModalBody');
    const subtitle = document.getElementById('payModalSubtitle');
    
    modal.classList.add('active');
    body.innerHTML = '<div style="text-align: center; padding: 40px;"><i class="fa-solid fa-spinner fa-spin fa-2x" style="color: var(--color-primary);"></i><p style="margin-top: 10px; font-size: 13px; color: var(--color-text-secondary);">Loading student payment profile...</p></div>';

    fetch('<?php echo BASE_URL; ?>/api/leads.php?action=get_payment_info&lead_id=' + leadId)
    .then(res => res.json())
    .then(data => {
        if (!data.success || !data.lead) {
            body.innerHTML = `<div style="color: var(--color-danger); padding: 20px;">${data.message || 'Could not load payment information.'}</div>`;
            return;
        }

        const l = data.lead;
        const p = data.payment_summary;
        const canUpdate = data.can_update;

        subtitle.innerHTML = `<strong>${l.name}</strong> • ${l.offering_type.toUpperCase()}: ${l.course_service_name} • ${l.phone}`;

        const totFee = p ? p.total_fee : l.expected_revenue;
        const totPaid = p ? p.paid_amount : l.paid_amount;
        const balDue = p ? p.balance_due : Math.max(0, totFee - totPaid);
        const planType = p ? p.payment_plan : l.payment_plan;
        const installments = (p && p.installments) ? p.installments : [];

        let instCardsHtml = '';
        if (installments.length > 0) {
            installments.forEach((inst) => {
                let stBadge = '<span class="badge badge-warning" style="font-size: 11px;"><i class="fa-solid fa-clock"></i> Pending</span>';
                if (inst.status === 'paid') stBadge = '<span class="badge badge-success" style="font-size: 11px;"><i class="fa-solid fa-circle-check"></i> Paid</span>';
                if (inst.status === 'partially_paid') stBadge = '<span class="badge badge-warning" style="font-size: 11px;"><i class="fa-solid fa-hourglass-half"></i> Partially Paid</span>';
                if (inst.status === 'overdue') stBadge = '<span class="badge badge-danger" style="font-size: 11px;"><i class="fa-solid fa-triangle-exclamation"></i> Overdue</span>';

                instCardsHtml += `
                    <div style="background: var(--color-bg-card); border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: 14px; margin-bottom: 12px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; flex-wrap: wrap; gap: 8px;">
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <div style="width: 28px; height: 28px; border-radius: 50%; background: rgba(16, 185, 129, 0.15); color: #10b981; font-weight: 700; font-size: 12px; display: flex; align-items: center; justify-content: center;">
                                    ${inst.installment_no}
                                </div>
                                <div>
                                    <strong style="font-size: 13.5px; color: var(--color-text-white);">${inst.title}</strong>
                                    <span style="font-size: 11.5px; color: var(--color-text-muted); margin-left: 6px;">Due Date: ${inst.due_date || 'N/A'}</span>
                                </div>
                            </div>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                ${stBadge}
                                <span style="font-size: 13px; font-weight: 700; color: var(--color-text-white);">₹${parseFloat(inst.amount).toLocaleString()}</span>
                            </div>
                        </div>

                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 8px; font-size: 12px; background: var(--color-bg-tertiary); padding: 10px; border-radius: var(--radius-sm); margin-bottom: 10px;">
                            <div>
                                <span style="color: var(--color-text-muted); font-size: 11px;">Paid Amount:</span> 
                                <strong style="color: #10b981;">₹${parseFloat(inst.paid_amount).toLocaleString()}</strong>
                            </div>
                            <div>
                                <span style="color: var(--color-text-muted); font-size: 11px;">Payment Date:</span> 
                                <span>${inst.paid_at ? new Date(inst.paid_at).toLocaleDateString() : '—'}</span>
                            </div>
                            <div>
                                <span style="color: var(--color-text-muted); font-size: 11px;">Method:</span> 
                                <span style="text-transform: uppercase;">${inst.payment_method || '—'}</span>
                            </div>
                            <div>
                                <span style="color: var(--color-text-muted); font-size: 11px;">Ref / UTR:</span> 
                                <span>${inst.transaction_id || '—'}</span>
                            </div>
                        </div>

                        ${canUpdate ? `
                            <!-- Update Payment Form -->
                            <details style="margin-top: 8px;">
                                <summary style="font-size: 12px; font-weight: 600; color: var(--color-primary); cursor: pointer; user-select: none;">
                                    <i class="fa-solid fa-pen-to-square"></i> Record / Update Payment for ${inst.title}
                                </summary>
                                <form onsubmit="submitInstallmentPaymentRecord(event, ${l.id}, ${inst.id})" style="margin-top: 10px; padding-top: 10px; border-top: 1px dashed var(--color-border);">
                                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 8px; margin-bottom: 8px;">
                                        <div>
                                            <label class="form-label" style="font-size: 11px;">Paid Amount (₹) *</label>
                                            <input type="number" step="any" min="0" name="paid_amount" class="form-input" style="font-size: 12px; padding: 5px 8px;" value="${parseFloat(inst.paid_amount) || parseFloat(inst.amount)}" required>
                                        </div>
                                        <div>
                                            <label class="form-label" style="font-size: 11px;">Payment Date *</label>
                                            <input type="date" name="paid_at" class="form-input" style="font-size: 12px; padding: 5px 8px;" value="${inst.paid_at ? inst.paid_at.split(' ')[0] : new Date().toISOString().split('T')[0]}" required>
                                        </div>
                                        <div>
                                            <label class="form-label" style="font-size: 11px;">Method</label>
                                            <select name="payment_method" class="form-select" style="font-size: 12px; padding: 5px 8px;">
                                                <option value="upi" ${inst.payment_method === 'upi' ? 'selected' : ''}>UPI / GPay / PhonePe</option>
                                                <option value="cash" ${inst.payment_method === 'cash' ? 'selected' : ''}>Cash</option>
                                                <option value="bank_transfer" ${inst.payment_method === 'bank_transfer' ? 'selected' : ''}>Bank Transfer / NEFT</option>
                                                <option value="card" ${inst.payment_method === 'card' ? 'selected' : ''}>Card / POS</option>
                                                <option value="cheque" ${inst.payment_method === 'cheque' ? 'selected' : ''}>Cheque</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="form-label" style="font-size: 11px;">Status</label>
                                            <select name="status" class="form-select" style="font-size: 12px; padding: 5px 8px;">
                                                <option value="paid" ${inst.status === 'paid' ? 'selected' : ''}>Paid in Full</option>
                                                <option value="partially_paid" ${inst.status === 'partially_paid' ? 'selected' : ''}>Partially Paid</option>
                                                <option value="pending" ${inst.status === 'pending' ? 'selected' : ''}>Pending</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-bottom: 8px;">
                                        <div>
                                            <label class="form-label" style="font-size: 11px;">Transaction UTR / Receipt #</label>
                                            <input type="text" name="transaction_id" class="form-input" style="font-size: 12px; padding: 5px 8px;" placeholder="e.g. 329841298412" value="${inst.transaction_id || ''}">
                                        </div>
                                        <div>
                                            <label class="form-label" style="font-size: 11px;">Payment Note / Remarks</label>
                                            <input type="text" name="notes" class="form-input" style="font-size: 12px; padding: 5px 8px;" placeholder="e.g. Received by Counselor via scanner" value="${inst.notes || ''}">
                                        </div>
                                    </div>
                                    <div style="display: flex; justify-content: flex-end;">
                                        <button type="submit" class="btn btn-sm btn-primary" style="padding: 5px 12px; font-size: 11.5px;">
                                            <i class="fa-solid fa-save"></i> Save Installment Update
                                        </button>
                                    </div>
                                </form>
                            </details>
                        ` : ''}
                    </div>
                `;
            });
        }

        body.innerHTML = `
            <!-- Summary KPI Strip -->
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 16px;">
                <div style="background: var(--color-bg-card); padding: 12px; border-radius: var(--radius-md); border: 1px solid var(--color-border); text-align: center;">
                    <div style="font-size: 11px; color: var(--color-text-muted); text-transform: uppercase;">Total Course Fee</div>
                    <div style="font-size: 18px; font-weight: 700; color: var(--color-text-white); margin-top: 2px;">₹${totFee.toLocaleString()}</div>
                </div>
                <div style="background: var(--color-bg-card); padding: 12px; border-radius: var(--radius-md); border: 1px solid var(--color-border); text-align: center;">
                    <div style="font-size: 11px; color: var(--color-text-muted); text-transform: uppercase;">Total Received</div>
                    <div style="font-size: 18px; font-weight: 700; color: #10b981; margin-top: 2px;">₹${totPaid.toLocaleString()}</div>
                </div>
                <div style="background: var(--color-bg-card); padding: 12px; border-radius: var(--radius-md); border: 1px solid var(--color-border); text-align: center;">
                    <div style="font-size: 11px; color: var(--color-text-muted); text-transform: uppercase;">Balance Due</div>
                    <div style="font-size: 18px; font-weight: 700; color: ${balDue > 0 ? '#ef4444' : '#10b981'}; margin-top: 2px;">₹${balDue.toLocaleString()}</div>
                </div>
            </div>

            <!-- Installment Cards Section -->
            <div style="margin-bottom: 16px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                    <h4 style="margin: 0; font-size: 14px; font-weight: 600;">
                        <i class="fa-solid fa-list-ol" style="color: var(--color-primary); margin-right: 4px;"></i> 
                        ${planType === 'installment' ? `${installments.length} Course Installments Schedule` : '1-Time Payment Record'}
                    </h4>
                </div>
                ${instCardsHtml || '<div style="padding: 16px; background: var(--color-bg-card); border-radius: var(--radius-md); text-align: center; color: var(--color-text-muted);">No payment plan active yet. Use the setup form below.</div>'}
            </div>

            ${canUpdate ? `
                <!-- Reset / Change Plan Setup Box -->
                <details style="background: var(--color-bg-secondary); border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: 12px;">
                    <summary style="font-size: 12.5px; font-weight: 600; color: var(--color-text-main); cursor: pointer; user-select: none;">
                        <i class="fa-solid fa-gear" style="color: #f59e0b;"></i> Re-configure Payment Plan (1-Time or 2/3 Installments)
                    </summary>
                    <form onsubmit="submitReconfigurePaymentPlan(event, ${l.id})" style="margin-top: 12px; padding-top: 10px; border-top: 1px solid var(--color-border);">
                        <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 10px;">
                            <div>
                                <label class="form-label" style="font-size: 11.5px;">Payment Plan Type</label>
                                <select id="reconfigPlanType" class="form-select" onchange="renderReconfigPlanInputs(${totFee})">
                                    <option value="one_time">⚡ 1-Time Full Payment</option>
                                    <option value="installment" ${planType === 'installment' ? 'selected' : ''}>📅 Installments (Up to 3 Parts)</option>
                                </select>
                            </div>
                            <div id="reconfigNumWrapper" style="${planType === 'installment' ? '' : 'display: none;'}">
                                <label class="form-label" style="font-size: 11.5px;">No. of Installments</label>
                                <select id="reconfigNumInstallments" class="form-select" onchange="renderReconfigPlanInputs(${totFee})">
                                    <option value="2" ${installments.length === 2 ? 'selected' : ''}>2 Installments</option>
                                    <option value="3" ${installments.length === 3 || installments.length === 0 ? 'selected' : ''}>3 Installments</option>
                                </select>
                            </div>
                        </div>

                        <div id="reconfigInputsContainer" style="display: flex; flex-direction: column; gap: 8px; margin-bottom: 10px;">
                            <!-- Injected by renderReconfigPlanInputs -->
                        </div>

                        <div style="display: flex; justify-content: flex-end;">
                            <button type="submit" class="btn btn-sm btn-primary">
                                <i class="fa-solid fa-check"></i> Apply New Payment Plan
                            </button>
                        </div>
                    </form>
                </details>
            ` : ''}
        `;

        if (canUpdate) {
            renderReconfigPlanInputs(totFee);
        }
    })
    .catch(err => {
        body.innerHTML = '<div style="color: var(--color-danger); padding: 20px;">An error occurred while loading payment data.</div>';
    });
}

function closePaymentModal() {
    document.getElementById('managePaymentsModal').classList.remove('active');
}

function renderReconfigPlanInputs(totalFee) {
    const container = document.getElementById('reconfigInputsContainer');
    const planType = document.getElementById('reconfigPlanType')?.value || 'one_time';
    const numWrap = document.getElementById('reconfigNumWrapper');
    if (!container) return;

    if (planType === 'one_time') {
        if (numWrap) numWrap.style.display = 'none';
        container.innerHTML = `
            <div style="padding: 10px; background: var(--color-bg-card); border: 1px solid var(--color-border); border-radius: var(--radius-sm); font-size: 12px;">
                <div style="font-weight: 600; margin-bottom: 4px;">1-Time Full Payment (₹${totalFee.toLocaleString()})</div>
                <div style="font-size: 11px; color: var(--color-text-muted);">Student will pay the complete course fee in a single payment.</div>
            </div>
        `;
    } else {
        if (numWrap) numWrap.style.display = 'block';
        const numInst = parseInt(document.getElementById('reconfigNumInstallments')?.value) || 3;
        let splitAmts = [];
        if (numInst === 2) {
            const half = Math.round(totalFee / 2);
            splitAmts = [half, totalFee - half];
        } else {
            const part = Math.round(totalFee / 3);
            splitAmts = [part, part, totalFee - (part * 2)];
        }

        let html = '';
        const today = new Date();
        for (let i = 1; i <= numInst; i++) {
            const dueD = new Date();
            dueD.setDate(today.getDate() + (i - 1) * 30);
            const dueStr = dueD.toISOString().split('T')[0];
            const title = (i === 1) ? '1st Installment (At Admission)' : (i === 2 ? '2nd Installment (Month 1)' : '3rd Installment (Month 2)');

            html += `
                <div style="padding: 8px 10px; background: var(--color-bg-card); border: 1px solid var(--color-border); border-radius: var(--radius-sm); display: grid; grid-template-columns: 1.5fr 1fr 1fr; gap: 8px; align-items: center;">
                    <div style="font-size: 11.5px; font-weight: 600; color: #10b981;">${title}</div>
                    <div>
                        <input type="number" step="any" min="0" id="reconfAmt_${i}" class="form-input" style="font-size: 11.5px; padding: 4px 6px;" value="${splitAmts[i-1] || 0}">
                    </div>
                    <div>
                        <input type="date" id="reconfDue_${i}" class="form-input" style="font-size: 11.5px; padding: 4px 6px;" value="${dueStr}">
                    </div>
                </div>
            `;
        }
        container.innerHTML = html;
    }
}

async function submitInstallmentPaymentRecord(e, leadId, paymentId) {
    e.preventDefault();
    const form = e.target;
    const btn = form.querySelector('button[type="submit"]');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';

    const formData = new FormData(form);
    formData.append('action', 'record_payment');
    formData.append('lead_id', leadId);
    formData.append('payment_id', paymentId);
    formData.append('csrf_token', '<?php echo csrfToken(); ?>');

    try {
        const res = await fetch('<?php echo BASE_URL; ?>/api/leads.php', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        if (data.success) {
            alert(data.message);
            openPaymentModal(leadId); // Refresh modal
        } else {
            alert(data.message || 'Error recording payment.');
        }
    } catch (err) {
        console.error(err);
        alert('An unexpected error occurred.');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-save"></i> Save Installment Update';
    }
}

async function submitReconfigurePaymentPlan(e, leadId) {
    e.preventDefault();
    const planType = document.getElementById('reconfigPlanType').value;
    const totalFee = parseFloat(document.getElementById('statusExpRev')?.value || 0);
    const installments = [];

    if (planType === 'one_time') {
        installments.push({
            installment_no: 1,
            title: '1-Time Full Payment',
            amount: totalFee,
            due_date: new Date().toISOString().split('T')[0],
            paid_amount: 0,
            status: 'pending'
        });
    } else {
        const numInst = parseInt(document.getElementById('reconfigNumInstallments')?.value) || 3;
        for (let i = 1; i <= numInst; i++) {
            const amt = parseFloat(document.getElementById(`reconfAmt_${i}`)?.value) || 0;
            const due = document.getElementById(`reconfDue_${i}`)?.value || new Date().toISOString().split('T')[0];
            const title = (i === 1) ? '1st Installment (At Admission)' : (i === 2 ? '2nd Installment (Month 1)' : '3rd Installment (Month 2)');
            installments.push({
                installment_no: i,
                title: title,
                amount: amt,
                due_date: due,
                paid_amount: 0,
                status: 'pending'
            });
        }
    }

    const formData = new FormData();
    formData.append('action', 'save_payment_plan');
    formData.append('lead_id', leadId);
    formData.append('plan_type', planType);
    formData.append('installments_json', JSON.stringify(installments));
    formData.append('csrf_token', '<?php echo csrfToken(); ?>');

    try {
        const res = await fetch('<?php echo BASE_URL; ?>/api/leads.php', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        if (data.success) {
            alert(data.message);
            openPaymentModal(leadId);
        } else {
            alert(data.message || 'Error configuring payment plan.');
        }
    } catch (err) {
        console.error(err);
        alert('An unexpected error occurred.');
    }
}

// Assign Modal Handlers (Founder / Manager / HR)
function openAssignModal(leadId, currentAssignedTo, candidateName) {
    document.getElementById('assign_lead_id').value = leadId;
    document.getElementById('assign_candidate_name').textContent = candidateName || 'Lead #' + leadId;
    document.getElementById('assign_user_select').value = currentAssignedTo || '';
    document.getElementById('modal-assign-lead').classList.add('active');
}
function closeAssignModal() {
    document.getElementById('modal-assign-lead').classList.remove('active');
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
            alert(data.message || 'Error reassigning lead.');
        }
    } catch (err) {
        console.error(err);
        alert('An unexpected error occurred.');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-check"></i> Assign Lead';
    }
}

// Delete Lead Permanent (Founder / Manager / HR) - Safe modal with mandatory backup
function deleteLeadPermanent(leadId, candidateName) {
    confirmDeleteSingleLead(leadId, candidateName);
}

// Close modals when clicking backdrop
document.querySelectorAll('.modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.remove('active');
        }
    });
});

<?php if ($selectedLeadId > 0): ?>
document.addEventListener('DOMContentLoaded', () => {
    viewLeadDrawer(<?php echo $selectedLeadId; ?>);
});
<?php endif; ?>
</script>

<?php 
require_once __DIR__ . '/../includes/payment_modal.php';
require_once __DIR__ . '/../includes/lead_delete_modal.php';
include __DIR__ . '/../includes/footer.php'; 
?>
