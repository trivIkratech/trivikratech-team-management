<?php
/**
 * Sales Counsellor — Courses & Services Reference Catalogue
 * 
 * Detailed pricing, curriculum highlights, and service package information
 * for quick reference during prospect counseling calls.
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/middleware.php';

ensureSalesTablesExist();
requireRole([ROLE_SALES_COUNSELLOR, ROLE_FOUNDER, ROLE_MANAGER, ROLE_HR]);

$courses = getCourseServicesList('course');
$services = getCourseServicesList('service');
$typeFilter = get('type', 'all');
$currentUser = getCurrentUser();
$userRole = $currentUser['role'] ?? '';

$pageTitle = 'Courses & Services Catalogue';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header" style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: var(--space-4); margin-bottom: var(--space-4);">
    <div>
        <h1 class="page-title"><i class="fa-solid fa-graduation-cap" style="color: var(--color-primary); margin-right: 8px;"></i>Courses & Services Catalogue</h1>
        <p class="page-subtitle">Pricing, curriculum details, and specifications for student & client counseling.</p>
    </div>
    <div style="display: flex; gap: var(--space-2); flex-wrap: wrap;">
        <?php if (in_array($userRole, ['founder', 'hr', 'manager'])): ?>
            <a href="<?php echo BASE_URL . ($userRole === 'founder' ? '/founder/revenue.php' : '/hr/revenue.php'); ?>" class="btn btn-warning" style="background: #f59e0b; color: #fff; border: none; font-weight: 600;">
                <i class="fa-solid fa-coins"></i> Revenue Model
            </a>
        <?php endif; ?>
        <a href="<?php echo BASE_URL; ?>/sales/leads.php" class="btn btn-primary">
            <i class="fa-solid fa-user-plus"></i> Go to Pipeline
        </a>
    </div>
</div>

<!-- Section Switcher Tabs -->
<div style="display: flex; gap: var(--space-2); margin-bottom: var(--space-6); flex-wrap: wrap;">
    <a href="<?php echo BASE_URL; ?>/sales/offerings.php?type=all" class="btn btn-sm <?php echo $typeFilter === 'all' ? 'btn-primary' : 'btn-secondary'; ?>" style="border-radius: var(--radius-full);">
        All Offerings (<?php echo count($courses) + count($services); ?>)
    </a>
    <a href="<?php echo BASE_URL; ?>/sales/offerings.php?type=courses" class="btn btn-sm <?php echo $typeFilter === 'courses' ? 'btn-primary' : 'btn-secondary'; ?>" style="border-radius: var(--radius-full);">
        <i class="fa-solid fa-graduation-cap"></i> Courses & Training (<?php echo count($courses); ?>)
    </a>
    <a href="<?php echo BASE_URL; ?>/sales/offerings.php?type=services" class="btn btn-sm <?php echo $typeFilter === 'services' ? 'btn-primary' : 'btn-secondary'; ?>" style="border-radius: var(--radius-full);">
        <i class="fa-solid fa-laptop-code"></i> IT & Software Services (<?php echo count($services); ?>)
    </a>
</div>

<?php if ($typeFilter === 'all' || $typeFilter === 'courses'): ?>
<!-- Courses Section -->
<div style="margin-bottom: var(--space-8);">
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: var(--space-4);">
        <div style="display: flex; align-items: center; gap: 8px;">
            <span style="display: inline-flex; width: 32px; height: 32px; border-radius: var(--radius-md); background: rgba(99, 102, 241, 0.15); color: var(--color-primary); align-items: center; justify-content: center; font-size: 16px;">
                <i class="fa-solid fa-book-open"></i>
            </span>
            <h2 style="margin: 0; font-size: 18px; font-weight: 700;">Official Training Courses (3 Specializations)</h2>
        </div>
        <a href="<?php echo BASE_URL; ?>/sales/leads.php?offering_type=course" class="btn btn-sm btn-outline-primary">
            View Course Leads &raquo;
        </a>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: var(--space-4);">
        <?php foreach ($courses as $c): ?>
            <div class="card fade-in" style="display: flex; flex-direction: column; justify-content: space-between; border-top: 4px solid var(--color-primary); position: relative;">
                <div>
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">
                        <span class="badge badge-primary" style="font-size: 11px; padding: 4px 8px;"><?php echo e($c['category'] ?? 'Course'); ?></span>
                        <span style="font-size: 12px; font-weight: 600; color: var(--color-text-secondary); background: var(--color-bg-tertiary); padding: 3px 8px; border-radius: var(--radius-sm);">
                            <i class="fa-regular fa-clock" style="color: var(--color-primary);"></i> <?php echo e($c['duration'] ?? 'Flexible'); ?>
                        </span>
                    </div>
                    <h3 style="margin: 0 0 8px 0; font-size: 16px; font-weight: 700; color: var(--color-text-main); line-height: 1.4;"><?php echo e($c['name']); ?></h3>
                    <p style="margin: 0 0 16px 0; font-size: 13px; color: var(--color-text-secondary); line-height: 1.5;">
                        <?php echo e($c['description'] ?? 'Comprehensive professional training curriculum with real-world project portfolios.'); ?>
                    </p>
                </div>
                <div style="border-top: 1px solid var(--color-border); padding-top: 14px; display: flex; justify-content: space-between; align-items: center; background: var(--color-bg-secondary); margin: 0 -20px -20px -20px; padding: 14px 20px; border-bottom-left-radius: var(--radius-lg); border-bottom-right-radius: var(--radius-lg);">
                    <div>
                        <div style="font-size: 11px; color: var(--color-text-muted); text-transform: uppercase; font-weight: 600; letter-spacing: 0.5px;">Standard Course Fee</div>
                        <div style="font-size: 20px; font-weight: 800; color: #10b981;">₹<?php echo number_format($c['price']); ?>/-</div>
                    </div>
                    <a href="<?php echo BASE_URL; ?>/sales/leads.php?search=<?php echo urlencode($c['name']); ?>" class="btn btn-sm btn-outline-primary">
                        <i class="fa-solid fa-users"></i> View Leads
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php if ($typeFilter === 'all' || $typeFilter === 'services'): ?>
<!-- Services Section -->
<div>
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: var(--space-4);">
        <div style="display: flex; align-items: center; gap: 8px;">
            <span style="display: inline-flex; width: 32px; height: 32px; border-radius: var(--radius-md); background: rgba(245, 158, 11, 0.15); color: #f59e0b; align-items: center; justify-content: center; font-size: 16px;">
                <i class="fa-solid fa-laptop-code"></i>
            </span>
            <h2 style="margin: 0; font-size: 18px; font-weight: 700;">Software & Digital IT Services (3 Core Offerings)</h2>
        </div>
        <a href="<?php echo BASE_URL; ?>/sales/leads.php?offering_type=service" class="btn btn-sm btn-outline-warning">
            View Service Leads &raquo;
        </a>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: var(--space-4);">
        <?php foreach ($services as $s): ?>
            <div class="card fade-in" style="display: flex; flex-direction: column; justify-content: space-between; border-top: 4px solid #f59e0b; position: relative;">
                <div>
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">
                        <span class="badge badge-warning" style="font-size: 11px; padding: 4px 8px;"><?php echo e($s['category'] ?? 'IT Service'); ?></span>
                        <span style="font-size: 12px; font-weight: 600; color: var(--color-text-secondary); background: var(--color-bg-tertiary); padding: 3px 8px; border-radius: var(--radius-sm);">
                            <i class="fa-solid fa-briefcase" style="color: #f59e0b;"></i> <?php echo e($s['duration'] ?? 'Project-based'); ?>
                        </span>
                    </div>
                    <h3 style="margin: 0 0 8px 0; font-size: 16px; font-weight: 700; color: var(--color-text-main); line-height: 1.4;"><?php echo e($s['name']); ?></h3>
                    <p style="margin: 0 0 16px 0; font-size: 13px; color: var(--color-text-secondary); line-height: 1.5;">
                        <?php echo e($s['description'] ?? 'Custom enterprise design, development, and digital scaling solutions.'); ?>
                    </p>
                </div>
                <div style="border-top: 1px solid var(--color-border); padding-top: 14px; display: flex; justify-content: space-between; align-items: center; background: var(--color-bg-secondary); margin: 0 -20px -20px -20px; padding: 14px 20px; border-bottom-left-radius: var(--radius-lg); border-bottom-right-radius: var(--radius-lg);">
                    <div>
                        <div style="font-size: 11px; color: var(--color-text-muted); text-transform: uppercase; font-weight: 600; letter-spacing: 0.5px;">Fee Structure</div>
                        <?php if ($s['price'] > 0): ?>
                            <div style="font-size: 18px; font-weight: 800; color: #10b981;">₹<?php echo number_format($s['price']); ?>/-</div>
                        <?php else: ?>
                            <div style="font-size: 13px; font-weight: 700; color: #f59e0b; display: inline-flex; align-items: center; gap: 4px; background: rgba(245, 158, 11, 0.12); padding: 4px 8px; border-radius: var(--radius-sm);">
                                <i class="fa-solid fa-file-signature"></i> Custom / Requirement-based
                            </div>
                        <?php endif; ?>
                    </div>
                    <a href="<?php echo BASE_URL; ?>/sales/leads.php?search=<?php echo urlencode($s['name']); ?>" class="btn btn-sm btn-outline-warning">
                        <i class="fa-solid fa-comments"></i> Inquiries
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
