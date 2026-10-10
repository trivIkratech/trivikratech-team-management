<?php
/**
 * Founder — Real-Time Revenue Model & Financial Command Center
 * 
 * 100% actual financial metrics, course admissions collections, counsellor sales performance,
 * and custom IT service deal tracking. (No hypothetical simulator).
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/middleware.php';

ensureSalesTablesExist();
requireRole([ROLE_FOUNDER]);

$analytics = getCourseRevenueAnalytics();
$summary = $analytics['summary'];
$courses = $analytics['courses'];
$services = $analytics['services'];
$counsellors = $analytics['counsellors'];
$recentAdmissions = $analytics['recent_admissions'];
$monthlyTrend = $analytics['monthly_trend'] ?? [];

// Monthly Target Setting (Default: ₹10,00,000)
$monthlyTarget = 1000000.0;
$thisMonthRealized = (float)($summary['this_month_realized_revenue'] ?? 0.0);
$targetProgress = $monthlyTarget > 0 ? min(100, round(($thisMonthRealized / $monthlyTarget) * 100, 1)) : 0;

$pageTitle = 'Revenue Model & Financial Command Center';
include __DIR__ . '/../includes/header.php';
?>

<!-- Header -->
<div class="page-header" style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: var(--space-4); margin-bottom: var(--space-5);">
    <div>
        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px;">
            <span class="badge badge-primary" style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px;">Executive Suite</span>
            <span style="font-size: 12px; color: var(--color-text-secondary);"><i class="fa-solid fa-circle-check" style="color: #10b981;"></i> Actual Realized Data</span>
        </div>
        <h1 class="page-title" style="margin: 0; font-size: 24px; font-weight: 800;">
            <i class="fa-solid fa-vault" style="color: #f59e0b; margin-right: 8px;"></i>Revenue Model & Financial Command Center
        </h1>
        <p class="page-subtitle" style="margin-top: 4px; color: var(--color-text-secondary); font-size: 14px;">
            Actual realized course tuition collections, counsellor sales ledger, and requirement-based service deals.
        </p>
    </div>
    <div style="display: flex; gap: var(--space-2); flex-wrap: wrap;">
        <a href="<?php echo BASE_URL; ?>/founder/sales.php" class="btn btn-secondary">
            <i class="fa-solid fa-funnel-dollar"></i> Sales CRM Pipeline
        </a>
        <a href="<?php echo BASE_URL; ?>/sales/raw_leads.php" class="btn btn-secondary">
            <i class="fa-solid fa-address-book"></i> Raw Prospects Bank
        </a>
        <a href="<?php echo BASE_URL; ?>/sales/offerings.php" class="btn btn-outline">
            <i class="fa-solid fa-graduation-cap"></i> Course Catalogue
        </a>
    </div>
</div>

<!-- ========================================== -->
<!-- 1. EXECUTIVE PERIOD-WISE FINANCIAL CARDS   -->
<!-- ========================================== -->
<div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: var(--space-4); margin-bottom: var(--space-6);">
    <!-- This Month Actual Revenue -->
    <div class="card stat-card fade-in" style="border-left: 4px solid #10b981;">
        <div class="stat-icon" style="background: rgba(16, 185, 129, 0.15); color: #10b981;">
            <i class="fa-solid fa-hand-holding-dollar"></i>
        </div>
        <div class="stat-info">
            <div class="stat-value" style="color: #10b981; font-size: 22px;">₹<?php echo number_format($thisMonthRealized); ?></div>
            <div class="stat-label">This Month Realized (Cash)</div>
            <div style="font-size: 11px; color: var(--color-text-muted); margin-top: 2px;">
                <strong>₹<?php echo number_format((float)($summary['this_month_booked_revenue'] ?? 0)); ?></strong> booked (<?php echo $summary['this_month_admissions']; ?> admissions)
            </div>
        </div>
    </div>

    <!-- This Quarter Actual Revenue -->
    <div class="card stat-card fade-in" style="border-left: 4px solid #6366f1;">
        <div class="stat-icon" style="background: rgba(99, 102, 241, 0.15); color: #6366f1;">
            <i class="fa-solid fa-chart-column"></i>
        </div>
        <div class="stat-info">
            <div class="stat-value" style="color: #6366f1; font-size: 22px;">₹<?php echo number_format((float)($summary['this_quarter_realized_revenue'] ?? 0)); ?></div>
            <div class="stat-label">This Quarter Realized</div>
            <div style="font-size: 11px; color: var(--color-text-muted); margin-top: 2px;">
                <strong>₹<?php echo number_format((float)($summary['this_quarter_booked_revenue'] ?? 0)); ?></strong> booked (<?php echo $summary['this_quarter_admissions'] ?? 0; ?> admissions)
            </div>
        </div>
    </div>

    <!-- All-Time Realized Course Revenue -->
    <div class="card stat-card fade-in" style="border-left: 4px solid #8b5cf6;">
        <div class="stat-icon" style="background: rgba(139, 92, 246, 0.15); color: #8b5cf6;">
            <i class="fa-solid fa-sack-dollar"></i>
        </div>
        <div class="stat-info">
            <div class="stat-value" style="color: #8b5cf6; font-size: 22px;">₹<?php echo number_format($summary['total_course_realized_revenue']); ?></div>
            <div class="stat-label">Total Course Collections</div>
            <div style="font-size: 11px; color: var(--color-text-muted); margin-top: 2px;">
                <strong><?php echo $summary['total_course_admissions']; ?></strong> seats &bull; ₹<?php echo number_format((float)($summary['total_course_booked_revenue'] ?? 0)); ?> total booked
            </div>
        </div>
    </div>

    <!-- Outstanding Receivables & Pipeline -->
    <div class="card stat-card fade-in" style="border-left: 4px solid #f59e0b;">
        <div class="stat-icon" style="background: rgba(245, 158, 11, 0.15); color: #f59e0b;">
            <i class="fa-solid fa-clock-rotate-left"></i>
        </div>
        <div class="stat-info">
            <div class="stat-value" style="color: #f59e0b; font-size: 22px;">₹<?php echo number_format($summary['total_course_pending_balance'] ?? 0); ?></div>
            <div class="stat-label">Pending Receivables</div>
            <div style="font-size: 11px; color: var(--color-text-muted); margin-top: 2px;">
                + ₹<?php echo number_format($summary['total_course_pipeline_revenue']); ?> warm leads pipeline
            </div>
        </div>
    </div>

    <!-- Average Student Ticket -->
    <div class="card stat-card fade-in" style="border-left: 4px solid #ec4899;">
        <div class="stat-icon" style="background: rgba(236, 72, 153, 0.15); color: #ec4899;">
            <i class="fa-solid fa-receipt"></i>
        </div>
        <div class="stat-info">
            <div class="stat-value" style="font-size: 22px;">₹<?php echo number_format($summary['avg_course_ticket']); ?></div>
            <div class="stat-label">Average Ticket Size</div>
            <div style="font-size: 11px; color: var(--color-text-muted); margin-top: 2px;">
                Conversion Rate: <strong><?php echo $summary['course_conversion_rate']; ?>%</strong>
            </div>
        </div>
    </div>

    <!-- Custom IT Deals Won -->
    <div class="card stat-card fade-in" style="border-left: 4px solid #06b6d4;">
        <div class="stat-icon" style="background: rgba(6, 182, 212, 0.15); color: #06b6d4;">
            <i class="fa-solid fa-laptop-code"></i>
        </div>
        <div class="stat-info">
            <div class="stat-value" style="font-size: 22px;">₹<?php echo number_format($summary['total_services_realized_revenue']); ?></div>
            <div class="stat-label">Custom IT Deals Won</div>
            <div style="font-size: 11px; color: var(--color-text-muted); margin-top: 2px;">
                <strong><?php echo $summary['total_services_deals']; ?></strong> closed software contracts
            </div>
        </div>
    </div>
</div>

<!-- ========================================== -->
<!-- 2. MONTHLY TARGET & ACTUAL PROGRESS BAR    -->
<!-- ========================================== -->
<div class="card fade-in" style="margin-bottom: var(--space-8); background: var(--color-bg-card); border: 1px solid var(--color-border); padding: var(--space-5);">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: var(--space-3); margin-bottom: 12px;">
        <div style="display: flex; align-items: center; gap: 8px;">
            <span style="display: inline-flex; width: 32px; height: 32px; border-radius: var(--radius-md); background: rgba(16, 185, 129, 0.15); color: #10b981; align-items: center; justify-content: center; font-size: 16px;">
                <i class="fa-solid fa-bullseye"></i>
            </span>
            <div>
                <h2 style="margin: 0; font-size: 16px; font-weight: 700; color: var(--color-text-main);">Monthly Revenue Target Tracking</h2>
                <div style="font-size: 12px; color: var(--color-text-secondary);">Realized Revenue vs Current Month Operating Target (₹<?php echo number_format($monthlyTarget); ?>)</div>
            </div>
        </div>
        <div style="display: flex; align-items: baseline; gap: 6px;">
            <span style="font-size: 22px; font-weight: 800; color: #10b981;">₹<?php echo number_format($thisMonthRealized); ?></span>
            <span style="font-size: 14px; color: var(--color-text-muted);">/ ₹<?php echo number_format($monthlyTarget); ?></span>
            <span class="badge <?php echo $targetProgress >= 100 ? 'badge-success' : 'badge-warning'; ?>" style="font-size: 12px; margin-left: 6px;">
                <?php echo $targetProgress; ?>% Achieved
            </span>
        </div>
    </div>

    <!-- Progress bar -->
    <div style="width: 100%; height: 10px; background: var(--color-bg-secondary); border-radius: var(--radius-full); overflow: hidden; border: 1px solid var(--color-border);">
        <div style="width: <?php echo min(100, $targetProgress); ?>%; height: 100%; background: linear-gradient(90deg, #10b981, #059669); border-radius: var(--radius-full); transition: width 0.5s ease;"></div>
    </div>
    
    <div style="display: flex; justify-content: space-between; font-size: 11px; color: var(--color-text-muted); margin-top: 6px;">
        <span>₹0 (Start of Month)</span>
        <?php if ($thisMonthRealized < $monthlyTarget): ?>
            <span style="color: #f59e0b; font-weight: 600;">
                <i class="fa-solid fa-hourglass-half"></i> ₹<?php echo number_format($monthlyTarget - $thisMonthRealized); ?> remaining to achieve monthly target
            </span>
        <?php else: ?>
            <span style="color: #10b981; font-weight: 600;">
                <i class="fa-solid fa-circle-check"></i> Monthly target exceeded by ₹<?php echo number_format($thisMonthRealized - $monthlyTarget); ?>!
            </span>
        <?php endif; ?>
        <span>Target: ₹<?php echo number_format($monthlyTarget); ?></span>
    </div>
</div>

<!-- ========================================== -->
<!-- 3. OFFICIAL COURSES UNIT ECONOMICS         -->
<!-- ========================================== -->
<div style="margin-bottom: var(--space-8);">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--space-4);">
        <div style="display: flex; align-items: center; gap: 8px;">
            <span style="display: inline-flex; width: 32px; height: 32px; border-radius: var(--radius-md); background: rgba(99, 102, 241, 0.15); color: var(--color-primary); align-items: center; justify-content: center; font-size: 16px;">
                <i class="fa-solid fa-graduation-cap"></i>
            </span>
            <h2 style="margin: 0; font-size: 18px; font-weight: 700; color: var(--color-text-main);">Official Training Courses (Real Financial Yield)</h2>
        </div>
        <a href="<?php echo BASE_URL; ?>/sales/leads.php?offering_type=course" class="btn btn-sm btn-outline-primary">
            View All Course Leads &raquo;
        </a>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: var(--space-4);">
        <?php 
        $courseThemes = [
            0 => ['border' => '#6366f1', 'icon' => 'fa-wand-magic-sparkles', 'badge_bg' => 'rgba(99, 102, 241, 0.12)', 'badge_color' => '#6366f1'],
            1 => ['border' => '#8b5cf6', 'icon' => 'fa-palette', 'badge_bg' => 'rgba(139, 92, 246, 0.12)', 'badge_color' => '#8b5cf6'],
            2 => ['border' => '#10b981', 'icon' => 'fa-code', 'badge_bg' => 'rgba(16, 185, 129, 0.12)', 'badge_color' => '#10b981']
        ];
        foreach ($courses as $idx => $c): 
            $theme = $courseThemes[$idx % 3];
        ?>
            <div class="card fade-in" style="border-top: 4px solid <?php echo $theme['border']; ?>; display: flex; flex-direction: column; justify-content: space-between;">
                <div>
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">
                        <span style="display: inline-flex; align-items: center; gap: 5px; font-size: 11px; font-weight: 700; padding: 3px 8px; border-radius: var(--radius-sm); background: <?php echo $theme['badge_bg']; ?>; color: <?php echo $theme['badge_color']; ?>;">
                            <i class="fa-solid <?php echo $theme['icon']; ?>"></i> <?php echo e($c['category']); ?>
                        </span>
                        <span style="font-size: 12px; color: var(--color-text-secondary); background: var(--color-bg-secondary); padding: 2px 8px; border-radius: var(--radius-sm);">
                            <i class="fa-regular fa-clock"></i> <?php echo e($c['duration']); ?>
                        </span>
                    </div>

                    <h3 style="margin: 0 0 6px 0; font-size: 16px; font-weight: 700; color: var(--color-text-main); line-height: 1.4;">
                        <?php echo e($c['name']); ?>
                    </h3>
                    <p style="margin: 0 0 14px 0; font-size: 12px; color: var(--color-text-secondary); line-height: 1.5;">
                        <?php echo e($c['description']); ?>
                    </p>

                    <!-- Real Financial Performance Matrix -->
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-bottom: 14px;">
                        <div style="background: var(--color-bg-secondary); padding: 8px 12px; border-radius: var(--radius-md);">
                            <div style="font-size: 10px; color: var(--color-text-muted); text-transform: uppercase; font-weight: 600;">Fixed Fee</div>
                            <div style="font-size: 16px; font-weight: 800; color: #10b981;">₹<?php echo number_format($c['price']); ?>/-</div>
                        </div>
                        <div style="background: var(--color-bg-secondary); padding: 8px 12px; border-radius: var(--radius-md);">
                            <div style="font-size: 10px; color: var(--color-text-muted); text-transform: uppercase; font-weight: 600;">Enrolled Seats</div>
                            <div style="font-size: 16px; font-weight: 800; color: var(--color-text-main);"><?php echo $c['enrolled_count']; ?> students</div>
                        </div>
                        <div style="background: rgba(16, 185, 129, 0.08); border: 1px solid rgba(16, 185, 129, 0.2); padding: 8px 12px; border-radius: var(--radius-md);">
                            <div style="font-size: 10px; color: #10b981; text-transform: uppercase; font-weight: 600;">Realized Cash Collected</div>
                            <div style="font-size: 16px; font-weight: 800; color: #10b981;">₹<?php echo number_format($c['collected_revenue']); ?></div>
                            <div style="font-size: 10px; color: var(--color-text-muted);">of ₹<?php echo number_format($c['booked_revenue']); ?> booked</div>
                        </div>
                        <div style="background: rgba(245, 158, 11, 0.08); border: 1px solid rgba(245, 158, 11, 0.2); padding: 8px 12px; border-radius: var(--radius-md);">
                            <div style="font-size: 10px; color: #f59e0b; text-transform: uppercase; font-weight: 600;">Pending Balance</div>
                            <div style="font-size: 16px; font-weight: 800; color: #f59e0b;">₹<?php echo number_format($c['pending_balance']); ?></div>
                            <div style="font-size: 10px; color: var(--color-text-muted);">+ ₹<?php echo number_format($c['pipeline_revenue']); ?> pipeline</div>
                        </div>
                    </div>
                </div>

                <!-- Conversion Progress Bar & Link -->
                <div style="border-top: 1px solid var(--color-border); padding-top: 10px;">
                    <div style="display: flex; justify-content: space-between; font-size: 11px; margin-bottom: 4px; color: var(--color-text-secondary);">
                        <span>Conversion Rate (<?php echo $c['total_inquiries']; ?> inq.)</span>
                        <strong style="color: var(--color-text-main);"><?php echo $c['conversion_rate']; ?>%</strong>
                    </div>
                    <div style="width: 100%; height: 6px; background: var(--color-bg-tertiary); border-radius: var(--radius-full); overflow: hidden; margin-bottom: 10px;">
                        <div style="width: <?php echo min(100, $c['conversion_rate']); ?>%; height: 100%; background: <?php echo $theme['border']; ?>; border-radius: var(--radius-full);"></div>
                    </div>
                    <a href="<?php echo BASE_URL; ?>/founder/sales.php?search=<?php echo urlencode($c['name']); ?>" class="btn btn-xs btn-outline" style="width: 100%; justify-content: center;">
                        <i class="fa-solid fa-users"></i> View Leads for this Course
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- ========================================== -->
<!-- 4. CUSTOM IT SERVICES PIPELINE OVERVIEW    -->
<!-- ========================================== -->
<div style="margin-bottom: var(--space-8);">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--space-4);">
        <div style="display: flex; align-items: center; gap: 8px;">
            <span style="display: inline-flex; width: 32px; height: 32px; border-radius: var(--radius-md); background: rgba(245, 158, 11, 0.15); color: #f59e0b; align-items: center; justify-content: center; font-size: 16px;">
                <i class="fa-solid fa-laptop-code"></i>
            </span>
            <h2 style="margin: 0; font-size: 18px; font-weight: 700; color: var(--color-text-main);">Custom Software & Digital Services (Requirement-based)</h2>
        </div>
        <a href="<?php echo BASE_URL; ?>/sales/leads.php?offering_type=service" class="btn btn-sm btn-outline-warning">
            View Service Inquiries &raquo;
        </a>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: var(--space-4);">
        <?php foreach ($services as $s): ?>
            <div class="card fade-in" style="border-top: 4px solid #f59e0b; display: flex; flex-direction: column; justify-content: space-between;">
                <div>
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">
                        <span class="badge badge-warning" style="font-size: 11px; padding: 3px 8px;"><?php echo e($s['category']); ?></span>
                        <span style="font-size: 11px; font-weight: 700; color: #f59e0b; background: rgba(245, 158, 11, 0.12); padding: 3px 8px; border-radius: var(--radius-sm);">
                            <i class="fa-solid fa-file-signature"></i> Custom Scope
                        </span>
                    </div>
                    <h3 style="margin: 0 0 6px 0; font-size: 16px; font-weight: 700; color: var(--color-text-main); line-height: 1.4;">
                        <?php echo e($s['name']); ?>
                    </h3>
                    <p style="margin: 0 0 14px 0; font-size: 12px; color: var(--color-text-secondary); line-height: 1.5;">
                        <?php echo e($s['description']); ?>
                    </p>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-bottom: 14px;">
                        <div style="background: var(--color-bg-secondary); padding: 8px 12px; border-radius: var(--radius-md);">
                            <div style="font-size: 10px; color: var(--color-text-muted); text-transform: uppercase; font-weight: 600;">Total Inquiries</div>
                            <div style="font-size: 16px; font-weight: 800; color: var(--color-text-main);"><?php echo $s['total_inquiries']; ?></div>
                        </div>
                        <div style="background: var(--color-bg-secondary); padding: 8px 12px; border-radius: var(--radius-md);">
                            <div style="font-size: 10px; color: var(--color-text-muted); text-transform: uppercase; font-weight: 600;">Deals Won / Closed</div>
                            <div style="font-size: 16px; font-weight: 800; color: #10b981;"><?php echo $s['won_count']; ?></div>
                        </div>
                        <div style="background: var(--color-bg-secondary); padding: 8px 12px; border-radius: var(--radius-md);">
                            <div style="font-size: 10px; color: var(--color-text-muted); text-transform: uppercase; font-weight: 600;">Closed Deal Value</div>
                            <div style="font-size: 16px; font-weight: 800; color: #10b981;">₹<?php echo number_format($s['realized_revenue']); ?></div>
                        </div>
                        <div style="background: var(--color-bg-secondary); padding: 8px 12px; border-radius: var(--radius-md);">
                            <div style="font-size: 10px; color: var(--color-text-muted); text-transform: uppercase; font-weight: 600;">Warm Proposal Pipeline</div>
                            <div style="font-size: 16px; font-weight: 800; color: #f59e0b;">₹<?php echo number_format($s['pipeline_revenue']); ?></div>
                        </div>
                    </div>
                </div>

                <div style="border-top: 1px solid var(--color-border); padding-top: 10px;">
                    <a href="<?php echo BASE_URL; ?>/founder/sales.php?search=<?php echo urlencode($s['name']); ?>" class="btn btn-xs btn-outline-warning" style="width: 100%; justify-content: center;">
                        <i class="fa-solid fa-briefcase"></i> View Service Inquiries
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- ========================================== -->
<!-- 5. COUNSELLOR REVENUE LEADERBOARD          -->
<!-- ========================================== -->
<div class="card fade-in" style="margin-bottom: var(--space-8);">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--space-4); flex-wrap: wrap; gap: var(--space-2);">
        <div style="display: flex; align-items: center; gap: 8px;">
            <span style="display: inline-flex; width: 32px; height: 32px; border-radius: var(--radius-md); background: rgba(16, 185, 129, 0.15); color: #10b981; align-items: center; justify-content: center; font-size: 16px;">
                <i class="fa-solid fa-trophy"></i>
            </span>
            <h2 style="margin: 0; font-size: 18px; font-weight: 700; color: var(--color-text-main);">Counsellor Revenue Leaderboard</h2>
        </div>
        <span style="font-size: 12px; color: var(--color-text-secondary);">Direct admissions closed & revenue generated by team members</span>
    </div>

    <?php if (empty($counsellors)): ?>
        <div style="text-align: center; padding: var(--space-6); color: var(--color-text-secondary);">
            <i class="fa-solid fa-users-slash" style="font-size: 32px; margin-bottom: 8px; opacity: 0.5;"></i>
            <p style="margin: 0;">No sales counsellors or conversions recorded yet.</p>
        </div>
    <?php else: ?>
        <div class="table-container" style="overflow-x: auto;">
            <table class="table" style="width: 100%; font-size: 13px;">
                <thead>
                    <tr>
                        <th style="width: 50px; text-align: center;">Rank</th>
                        <th>Counsellor</th>
                        <th>Role</th>
                        <th style="text-align: center;">Total Assigned</th>
                        <th style="text-align: center;">Course Admissions</th>
                        <th style="text-align: right;">Course Revenue</th>
                        <th style="text-align: center;">Service Deals</th>
                        <th style="text-align: right;">Total Realized Revenue</th>
                        <th style="text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $rank = 1;
                    foreach ($counsellors as $c): 
                        $rankBadge = '';
                        if ($rank === 1) $rankBadge = '<span style="color: #f59e0b; font-size: 15px;"><i class="fa-solid fa-crown"></i> 1</span>';
                        elseif ($rank === 2) $rankBadge = '<span style="color: #94a3b8; font-size: 14px;"><i class="fa-solid fa-medal"></i> 2</span>';
                        elseif ($rank === 3) $rankBadge = '<span style="color: #b45309; font-size: 14px;"><i class="fa-solid fa-medal"></i> 3</span>';
                        else $rankBadge = '<span style="color: var(--color-text-muted); font-weight: 600;">' . $rank . '</span>';
                    ?>
                        <tr>
                            <td style="text-align: center;"><?php echo $rankBadge; ?></td>
                            <td>
                                <div style="font-weight: 700; color: var(--color-text-main);"><?php echo e($c['name']); ?></div>
                                <div style="font-size: 11px; color: var(--color-text-muted);"><?php echo e($c['email']); ?></div>
                            </td>
                            <td>
                                <span class="badge badge-primary" style="font-size: 10px;"><?php echo ucwords(str_replace('_', ' ', $c['role'])); ?></span>
                            </td>
                            <td style="text-align: center; font-weight: 600;"><?php echo (int)$c['total_assigned']; ?></td>
                            <td style="text-align: center;">
                                <span class="badge badge-success" style="font-size: 11px;"><?php echo (int)$c['course_admissions']; ?> seats</span>
                            </td>
                            <td style="text-align: right; font-weight: 700; color: #10b981;">
                                ₹<?php echo number_format((float)$c['course_revenue']); ?>
                            </td>
                            <td style="text-align: center; font-weight: 600;"><?php echo (int)$c['service_deals']; ?></td>
                            <td style="text-align: right; font-weight: 800; font-size: 14px; color: #10b981;">
                                ₹<?php echo number_format((float)$c['total_revenue']); ?>
                            </td>
                            <td style="text-align: center;">
                                <a href="<?php echo BASE_URL; ?>/founder/sales.php?counsellor_id=<?php echo $c['id']; ?>" class="btn btn-xs btn-outline-primary" title="View counsellor leads">
                                    <i class="fa-solid fa-filter"></i> Leads
                                </a>
                            </td>
                        </tr>
                    <?php 
                        $rank++;
                    endforeach; 
                    ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- ========================================== -->
<!-- 6. REAL-TIME ENROLLED ADMISSIONS LEDGER    -->
<!-- ========================================== -->
<div class="card fade-in">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--space-4); flex-wrap: wrap; gap: var(--space-2);">
        <div style="display: flex; align-items: center; gap: 8px;">
            <span style="display: inline-flex; width: 32px; height: 32px; border-radius: var(--radius-md); background: rgba(99, 102, 241, 0.15); color: var(--color-primary); align-items: center; justify-content: center; font-size: 16px;">
                <i class="fa-solid fa-list-check"></i>
            </span>
            <h2 style="margin: 0; font-size: 18px; font-weight: 700; color: var(--color-text-main);">Recent Enrolled Admissions Ledger</h2>
        </div>
        <span style="font-size: 12px; color: var(--color-text-secondary);">Real-time converted student admissions</span>
    </div>

    <?php if (empty($recentAdmissions)): ?>
        <div style="text-align: center; padding: var(--space-6); color: var(--color-text-secondary);">
            <i class="fa-solid fa-user-graduate" style="font-size: 32px; margin-bottom: 8px; opacity: 0.5;"></i>
            <p style="margin: 0;">No enrolled student admissions recorded yet. Admissions will appear here automatically when leads are marked as 'Enrolled'.</p>
        </div>
    <?php else: ?>
        <div class="table-container" style="overflow-x: auto;">
            <table class="table" style="width: 100%; font-size: 13px;">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Student / Client Name</th>
                        <th>Contact</th>
                        <th>Course / Offering</th>
                        <th style="text-align: right;">Total Fee</th>
                        <th style="text-align: right;">Collected (₹)</th>
                        <th style="text-align: right;">Remaining Balance</th>
                        <th style="text-align: center;">Payment Plan</th>
                        <th>Assigned Counsellor</th>
                        <th>Enrolment Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $idx = 1;
                    foreach ($recentAdmissions as $adm): 
                        $totalFee = (float)$adm['expected_revenue'];
                        $paidAmt = (float)($adm['paid_amount'] > 0 ? $adm['paid_amount'] : ($adm['payment_status'] === 'paid' ? $totalFee : $totalFee));
                        $balDue = max(0.0, $totalFee - $paidAmt);
                        $plan = $adm['payment_plan'] ?: 'none';
                        $planLabel = ($plan === 'one_time') ? '1-Time' : (($plan === 'installment') ? "{$adm['total_installments']} Inst." : 'Standard');
                    ?>
                        <tr>
                            <td style="color: var(--color-text-muted); font-weight: 600;"><?php echo $idx++; ?></td>
                            <td>
                                <div style="font-weight: 700; color: var(--color-text-main);"><?php echo e($adm['name']); ?></div>
                                <?php if (!empty($adm['city']) || !empty($adm['graduation'])): ?>
                                    <div style="font-size: 11px; color: var(--color-text-muted);">
                                        <?php echo e(implode(' &bull; ', array_filter([$adm['city'], $adm['graduation']]))); ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="font-size: 12px;"><i class="fa-solid fa-phone" style="font-size: 10px; color: var(--color-text-muted);"></i> <?php echo e($adm['phone']); ?></div>
                                <?php if (!empty($adm['email'])): ?>
                                    <div style="font-size: 11px; color: var(--color-text-muted);"><?php echo e($adm['email']); ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge <?php echo ($adm['offering_type'] === 'course') ? 'badge-primary' : 'badge-warning'; ?>" style="font-size: 11px;">
                                    <?php echo e($adm['course_service_name']); ?>
                                </span>
                            </td>
                            <td style="text-align: right; font-weight: 700; color: var(--color-text-main); font-size: 13px;">
                                ₹<?php echo number_format($totalFee); ?>
                            </td>
                            <td style="text-align: right; font-weight: 800; color: #10b981; font-size: 13.5px;">
                                <span style="background: rgba(16, 185, 129, 0.12); padding: 3px 8px; border-radius: 6px; border: 1px solid rgba(16, 185, 129, 0.25);">
                                    ₹<?php echo number_format($paidAmt); ?>
                                </span>
                            </td>
                            <td style="text-align: right; font-weight: 700; font-size: 13px;">
                                <?php if ($balDue > 0): ?>
                                    <span style="color: #f59e0b; background: rgba(245, 158, 11, 0.12); padding: 3px 8px; border-radius: 6px; border: 1px solid rgba(245, 158, 11, 0.25);">
                                        ₹<?php echo number_format($balDue); ?>
                                    </span>
                                <?php else: ?>
                                    <span style="color: #10b981; font-size: 11px; font-weight: 600;">
                                        <i class="fa-solid fa-circle-check"></i> Paid in Full
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: center;">
                                <span class="badge badge-secondary" style="font-size: 10.5px;">
                                    <?php echo e($planLabel); ?>
                                </span>
                            </td>
                            <td>
                                <div style="font-weight: 600; color: var(--color-text-main);"><?php echo e($adm['counsellor_name'] ?? 'Unassigned'); ?></div>
                            </td>
                            <td style="font-size: 12px; color: var(--color-text-secondary);">
                                <?php echo date('d M Y, h:i A', strtotime($adm['updated_at'] ?? $adm['created_at'])); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
