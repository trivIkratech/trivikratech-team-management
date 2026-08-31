<?php
/**
 * Header Component
 * 
 * Outputs <html>, <head>, top navigation bar.
 * Must be included at the top of every page after auth/middleware checks.
 * 
 * Required variables:
 *   $pageTitle — used in <title> tag
 */

$currentUser = getCurrentUser();
$userInitials = getInitials($currentUser['name'] ?? 'U');
$todayFormatted = date('l, d M Y');
$greeting = (date('H') < 12) ? 'Good Morning' : ((date('H') < 17) ? 'Good Afternoon' : 'Good Evening');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <meta name="csrf-token" content="<?php echo csrfToken(); ?>">
    <meta name="description" content="Team Management System — Manage employees, attendance, and tasks">
    <title><?php echo e($pageTitle ?? 'Dashboard'); ?> — <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
    <script>window.BASE_URL = '<?php echo BASE_URL; ?>';</script>
</head>
<body>
    <div class="app-layout">
        <!-- Sidebar Backdrop (Mobile) -->
        <div class="sidebar-backdrop"></div>

        <?php include __DIR__ . '/sidebar.php'; ?>

        <div class="main-wrapper">
            <!-- Top Header -->
            <header class="top-header">
                <div class="header-left">
                    <button class="mobile-menu-btn" aria-label="Toggle menu">☰</button>
                    <div class="header-greeting">
                        <?php echo $greeting; ?>, <strong><?php echo e($currentUser['name'] ?? 'User'); ?></strong>
                    </div>
                </div>
                <div class="header-right">
                    <span class="header-date">📅 <?php echo $todayFormatted; ?></span>
                    <div class="header-user-section">
                        <a href="<?php echo BASE_URL; ?>/profile.php" class="header-avatar" title="View Profile" style="text-decoration: none; color: inherit; display: flex; align-items: center; justify-content: center; font-weight: bold; background-color: var(--color-primary);"><?php echo e($userInitials); ?></a>
                        <a href="<?php echo BASE_URL; ?>/auth/logout.php" class="btn-logout">
                            <span>↗</span> Logout
                        </a>
                    </div>
                </div>
            </header>

            <!-- Main Content -->
            <main class="main-content fade-in">
                <?php echo renderFlash(); ?>
