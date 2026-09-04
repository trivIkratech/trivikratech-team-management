<?php
/**
 * Header Component
 * 
 * Outputs <html>, <head>, Font Awesome 6 Icons, zero-flicker Theme Switcher (Dark/Light mode), 
 * and top navigation bar with notification bell.
 */

$currentUser = getCurrentUser();
$userInitials = getInitials($currentUser['name'] ?? 'U');
$todayFormatted = date('l, d M Y');
$greeting = (date('H') < 12) ? 'Good Morning' : ((date('H') < 17) ? 'Good Afternoon' : 'Good Evening');

$unreadNotifCount = isLoggedIn() ? countUnreadNotifications(getUserId()) : 0;
$unreadNotifs = isLoggedIn() ? getUnreadNotifications(getUserId(), 6) : [];
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <meta name="csrf-token" content="<?php echo csrfToken(); ?>">
    <meta name="description" content="Team Management System — Manage employees, attendance, tasks, and chat">
    <title><?php echo e($pageTitle ?? 'Dashboard'); ?> — <?php echo APP_NAME; ?></title>
    <!-- Zero-Flicker Theme & Sidebar Initializer -->
    <script>
    (function() {
        const savedTheme = localStorage.getItem('app_theme') || 'dark';
        document.documentElement.setAttribute('data-theme', savedTheme);
        if (localStorage.getItem('sidebar_collapsed') === 'true' && window.innerWidth > 1024) {
            document.documentElement.classList.add('sidebar-collapsed');
        }
    })();
    </script>
    <!-- Font Awesome 6 Icons CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css?v=<?php echo @filemtime(__DIR__ . '/../assets/css/style.css') ?: time(); ?>">
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
                    <button type="button" class="sidebar-toggle-btn mobile-menu-btn" id="sidebar-toggle-btn" title="Toggle Sidebar" aria-label="Toggle sidebar">
                        <i class="fa-solid fa-bars-staggered"></i>
                    </button>
                    <div class="header-greeting">
                        <?php echo $greeting; ?>, <strong><?php echo e($currentUser['name'] ?? 'User'); ?></strong>
                    </div>
                </div>
                <div class="header-right" style="display: flex; align-items: center; gap: 14px;">
                    <span class="header-date"><i class="fa-regular fa-calendar-days" style="margin-right: 6px; color: var(--color-primary);"></i> <?php echo $todayFormatted; ?></span>
                    
                    <!-- Theme Mode Switcher Toggle (Dark / Light) -->
                    <button type="button" id="theme-toggle-btn" onclick="toggleAppTheme()" style="cursor: pointer; position: relative; display: flex; align-items: center; justify-content: center; width: 36px; height: 36px; border-radius: 50%; background: var(--color-bg-tertiary); border: 1px solid var(--color-border); color: var(--color-text-main); font-size: 15px;" title="Switch Light / Dark Mode">
                        <i class="fa-solid fa-moon" id="theme-toggle-icon"></i>
                    </button>

                    <!-- Notification Bell Dropdown -->
                    <details class="notif-dropdown" style="position: relative;">
                        <summary style="cursor: pointer; position: relative; display: flex; align-items: center; justify-content: center; width: 36px; height: 36px; border-radius: 50%; background: var(--color-bg-tertiary); border: 1px solid var(--color-border); list-style: none; user-select: none;" title="Notifications">
                            <i class="fa-regular fa-bell" style="font-size: 16px; color: var(--color-text-main);"></i>
                            <?php if ($unreadNotifCount > 0): ?>
                                <span style="position: absolute; top: -2px; right: -2px; background: #ef4444; color: #fff; font-size: 10px; font-weight: bold; width: 18px; height: 18px; border-radius: 50%; display: flex; align-items: center; justify-content: center; border: 2px solid var(--color-bg-card);"><?php echo $unreadNotifCount; ?></span>
                            <?php endif; ?>
                        </summary>

                        <div class="notif-dropdown-content" style="position: absolute; top: 125%; right: 0; background: var(--color-bg-card); border: 1px solid var(--color-border); border-radius: var(--radius-lg); box-shadow: 0 12px 36px rgba(0,0,0,0.5); z-index: 9999; width: min(320px, calc(100vw - 32px)); overflow: hidden;">
                            <div style="padding: 12px 16px; border-bottom: 1px solid var(--color-border); display: flex; justify-content: space-between; align-items: center; background: var(--color-bg-secondary);">
                                <strong style="font-size: 13px;"><i class="fa-solid fa-bell" style="margin-right: 6px; color: var(--color-primary);"></i> Notifications (<?php echo $unreadNotifCount; ?>)</strong>
                                <?php if ($unreadNotifCount > 0): ?>
                                    <button type="button" onclick="markAllNotificationsRead()" style="background: none; border: none; color: var(--color-primary); cursor: pointer; font-size: 11px; font-weight: 600;">Mark all read</button>
                                <?php endif; ?>
                            </div>
                            
                            <div style="max-height: 300px; overflow-y: auto;">
                                <?php if (empty($unreadNotifs)): ?>
                                    <div style="padding: 20px; text-align: center; color: var(--color-text-muted); font-size: 13px;">
                                        <i class="fa-regular fa-circle-check" style="font-size: 24px; display: block; margin-bottom: 8px; color: var(--color-success);"></i> No unread notifications
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($unreadNotifs as $n): ?>
                                        <?php
                                        $rawTitle = $n['title'];
                                        $iconClass = 'fa-solid fa-bell';
                                        if (preg_match('/class=["\']([^"\']+)["\']/', $rawTitle, $matches)) {
                                            $iconClass = $matches[1];
                                        } elseif (stripos($rawTitle, 'message') !== false || stripos($rawTitle, 'chat') !== false) {
                                            $iconClass = 'fa-solid fa-comments';
                                        } elseif (stripos($rawTitle, 'task') !== false) {
                                            $iconClass = 'fa-solid fa-list-check';
                                        } elseif (stripos($rawTitle, 'ticket') !== false || stripos($rawTitle, 'support') !== false) {
                                            $iconClass = 'fa-solid fa-headset';
                                        } elseif (stripos($rawTitle, 'meeting') !== false) {
                                            $iconClass = 'fa-solid fa-video';
                                        } elseif (stripos($rawTitle, 'leave') !== false) {
                                            $iconClass = 'fa-solid fa-calendar-minus';
                                        }
                                        $cleanTitle = trim(strip_tags($rawTitle));
                                        $cleanMessage = trim(strip_tags($n['message']));
                                        ?>
                                        <div style="display: flex; align-items: flex-start; gap: 10px; padding: 12px 16px; border-bottom: 1px solid var(--color-border); font-size: 12px; cursor: pointer; background: <?php echo $n['is_read'] ? 'transparent' : 'rgba(79, 110, 247, 0.08)'; ?>; transition: background 0.15s ease;" onclick="markNotifRead(<?php echo $n['id']; ?>, '<?php echo e($n['link'] ?: ''); ?>')">
                                            <div style="width: 30px; height: 30px; border-radius: 50%; background: rgba(59, 130, 246, 0.15); color: var(--color-primary); display: flex; align-items: center; justify-content: center; flex-shrink: 0; margin-top: 2px;">
                                                <i class="<?php echo e($iconClass); ?>" style="font-size: 13px;"></i>
                                            </div>
                                            <div style="flex: 1; min-width: 0;">
                                                <div style="font-weight: 600; color: var(--color-text-main); margin-bottom: 2px; line-height: 1.3;"><?php echo e($cleanTitle); ?></div>
                                                <div style="color: var(--color-text-secondary); margin-bottom: 4px; line-height: 1.3; font-size: 11px;"><?php echo e($cleanMessage); ?></div>
                                                <small style="color: var(--color-text-muted); font-size: 10px;"><?php echo timeAgo($n['created_at']); ?></small>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </details>

                    <div class="header-user-section">
                        <a href="<?php echo BASE_URL; ?>/profile.php" class="header-avatar" title="View Profile" style="text-decoration: none; color: #fff; display: flex; align-items: center; justify-content: center; font-weight: bold; background-color: var(--color-primary);"><?php echo e($userInitials); ?></a>
                        <a href="<?php echo BASE_URL; ?>/auth/logout.php" class="btn-logout" title="Logout">
                            <i class="fa-solid fa-right-from-bracket"></i> <span class="btn-logout-text">Logout</span>
                        </a>
                    </div>
                </div>
            </header>

            <script>
            function toggleAppTheme() {
                const currentTheme = document.documentElement.getAttribute('data-theme') || 'dark';
                const newTheme = (currentTheme === 'dark') ? 'light' : 'dark';
                document.documentElement.setAttribute('data-theme', newTheme);
                if (document.body) document.body.setAttribute('data-theme', newTheme);
                localStorage.setItem('app_theme', newTheme);
                updateThemeIcon(newTheme);

                // Sync live to floating chat iframe if open/loaded
                const chatIframe = document.getElementById('floating-chat-iframe');
                if (chatIframe && chatIframe.contentDocument) {
                    chatIframe.contentDocument.documentElement.setAttribute('data-theme', newTheme);
                    if (chatIframe.contentDocument.body) chatIframe.contentDocument.body.setAttribute('data-theme', newTheme);
                    if (chatIframe.contentWindow) {
                        chatIframe.contentWindow.postMessage({ theme: newTheme }, '*');
                    }
                }
            }

            function updateThemeIcon(theme) {
                const icon = document.getElementById('theme-toggle-icon');
                if (icon) {
                    if (theme === 'light') {
                        icon.className = 'fa-solid fa-sun';
                        icon.style.color = '#f59e0b';
                    } else {
                        icon.className = 'fa-solid fa-moon';
                        icon.style.color = 'var(--color-text-main)';
                    }
                }
            }

            document.addEventListener('DOMContentLoaded', function() {
                const theme = localStorage.getItem('app_theme') || 'dark';
                document.documentElement.setAttribute('data-theme', theme);
                if (document.body) document.body.setAttribute('data-theme', theme);
                updateThemeIcon(theme);
            });

            function markNotifRead(id, link) {
                const token = document.querySelector('meta[name="csrf-token"]')?.content || '';
                fetch(window.BASE_URL + '/api/notifications.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'action=mark_read&id=' + id + '&csrf_token=' + token
                }).then(() => {
                    if (link) window.location.href = link;
                    else location.reload();
                });
            }

            function markAllNotificationsRead() {
                const token = document.querySelector('meta[name="csrf-token"]')?.content || '';
                fetch(window.BASE_URL + '/api/notifications.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'action=mark_all_read&csrf_token=' + token
                }).then(() => location.reload());
            }
            </script>

            <!-- Main Content -->
            <main class="main-content fade-in">
                <?php echo renderFlash(); ?>
