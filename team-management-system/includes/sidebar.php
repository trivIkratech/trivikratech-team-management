<?php
/**
 * Sidebar Component — Role-Aware Navigation
 * 
 * Uses Font Awesome 6 vector icons for clean rendering across retina displays.
 * Includes Team Chat link for all roles.
 */

$currentUser = getCurrentUser();
$userRole = $currentUser['role'] ?? '';
$baseRole = function_exists('getRoleBaseType') ? getRoleBaseType($userRole) : $userRole;
$userInitials = getInitials($currentUser['name'] ?? 'U');
$sidebarChatUnread = isLoggedIn() ? countUnreadChatMessages((int)($currentUser['id'] ?? 0)) : 0;

// Determine current page for active state
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$currentDir = basename(dirname($_SERVER['PHP_SELF']));

function isActive(string $dir, string $page): string {
    global $currentDir, $currentPage;
    return ($currentDir === $dir && $currentPage === $page) ? ' active' : '';
}
?>
<aside class="sidebar" id="sidebar">
    <!-- Brand -->
    <div class="sidebar-brand">
        <div class="sidebar-brand-icon"><i class="fa-solid fa-layer-group"></i></div>
        <div class="sidebar-brand-text">
            Team Manager
            <span><?php echo ucfirst(e($userRole)); ?> Panel</span>
        </div>
        <button type="button" class="sidebar-close-btn" id="sidebar-close-btn" onclick="closeSidebarNav(event)" title="Close / Collapse Sidebar" aria-label="Close sidebar" style="display: inline-flex; align-items: center; justify-content: center; cursor: pointer; width: 32px; height: 32px; border-radius: var(--radius-md); margin-left: auto; border: none; background: transparent; color: var(--color-text-muted); font-size: 16px; transition: color 0.15s, background 0.15s;">
            <i class="fa-solid fa-xmark"></i>
        </button>
    </div>

    <!-- Navigation -->
    <nav class="sidebar-nav">

        <?php if ($baseRole === ROLE_FOUNDER): ?>
            <!-- ===== FOUNDER NAVIGATION ===== -->
            <div class="sidebar-nav-label">Main</div>
            <a href="<?php echo BASE_URL; ?>/founder/dashboard.php" class="sidebar-nav-item<?php echo isActive('founder', 'dashboard'); ?>">
                <span class="nav-icon"><i class="fa-solid fa-chart-pie"></i></span> Dashboard
            </a>
            <a href="<?php echo BASE_URL; ?>/chat/index.php" class="sidebar-nav-item<?php echo isActive('chat', 'index'); ?>">
                <span class="nav-icon"><i class="fa-solid fa-comments"></i></span> Team Chat
                <span class="sidebar-chat-badge" style="display: <?php echo $sidebarChatUnread > 0 ? 'inline-flex' : 'none'; ?>;"><?php echo $sidebarChatUnread > 99 ? '99+' : $sidebarChatUnread; ?></span>
            </a>

            <div class="sidebar-nav-label">People</div>
            <a href="<?php echo BASE_URL; ?>/founder/user-management.php" class="sidebar-nav-item<?php echo isActive('founder', 'user-management'); ?>">
                <span class="nav-icon"><i class="fa-solid fa-gears"></i></span> User Management
            </a>
            <a href="<?php echo BASE_URL; ?>/founder/roles.php" class="sidebar-nav-item<?php echo isActive('founder', 'roles'); ?>">
                <span class="nav-icon"><i class="fa-solid fa-user-shield"></i></span> Roles & Permissions
            </a>

            <div class="sidebar-nav-label">Modules</div>
            <a href="<?php echo BASE_URL; ?>/founder/attendance.php" class="sidebar-nav-item<?php echo isActive('founder', 'attendance'); ?>">
                <span class="nav-icon"><i class="fa-solid fa-clipboard-user"></i></span> Attendance
            </a>
            <a href="<?php echo BASE_URL; ?>/founder/tasks.php" class="sidebar-nav-item<?php echo isActive('founder', 'tasks'); ?>">
                <span class="nav-icon"><i class="fa-solid fa-list-check"></i></span> Tasks
            </a>
            <a href="<?php echo BASE_URL; ?>/founder/meetings.php" class="sidebar-nav-item<?php echo isActive('founder', 'meetings'); ?>">
                <span class="nav-icon"><i class="fa-solid fa-calendar-days"></i></span> Meetings
            </a>

            <div class="sidebar-nav-label">Insights</div>
            <a href="<?php echo BASE_URL; ?>/founder/reports.php" class="sidebar-nav-item<?php echo isActive('founder', 'reports'); ?>">
                <span class="nav-icon"><i class="fa-solid fa-chart-line"></i></span> Reports
            </a>
            <a href="<?php echo BASE_URL; ?>/founder/tickets.php" class="sidebar-nav-item<?php echo isActive('founder', 'tickets'); ?>">
                <span class="nav-icon"><i class="fa-solid fa-headset"></i></span> Support Tickets
            </a>
            <a href="<?php echo BASE_URL; ?>/founder/leaves.php" class="sidebar-nav-item<?php echo isActive('founder', 'leaves'); ?>">
                <span class="nav-icon"><i class="fa-solid fa-umbrella-beach"></i></span> Leave Requests
            </a>
            <a href="<?php echo BASE_URL; ?>/founder/announcements.php" class="sidebar-nav-item<?php echo isActive('founder', 'announcements'); ?>">
                <span class="nav-icon"><i class="fa-solid fa-bullhorn"></i></span> Announcements
            </a>

        <?php elseif ($baseRole === ROLE_MANAGER): ?>
            <!-- ===== MANAGER NAVIGATION ===== -->
            <div class="sidebar-nav-label">Main</div>
            <a href="<?php echo BASE_URL; ?>/manager/dashboard.php" class="sidebar-nav-item<?php echo isActive('manager', 'dashboard'); ?>">
                <span class="nav-icon"><i class="fa-solid fa-chart-pie"></i></span> Dashboard
            </a>
            <a href="<?php echo BASE_URL; ?>/chat/index.php" class="sidebar-nav-item<?php echo isActive('chat', 'index'); ?>">
                <span class="nav-icon"><i class="fa-solid fa-comments"></i></span> Team Chat
                <span class="sidebar-chat-badge" style="display: <?php echo $sidebarChatUnread > 0 ? 'inline-flex' : 'none'; ?>;"><?php echo $sidebarChatUnread > 99 ? '99+' : $sidebarChatUnread; ?></span>
            </a>

            <div class="sidebar-nav-label">Management</div>
            <a href="<?php echo BASE_URL; ?>/manager/employees.php" class="sidebar-nav-item<?php echo isActive('manager', 'employees'); ?>">
                <span class="nav-icon"><i class="fa-solid fa-users"></i></span> Manage Employees
            </a>
            <a href="<?php echo BASE_URL; ?>/manager/attendance.php" class="sidebar-nav-item<?php echo isActive('manager', 'attendance'); ?>">
                <span class="nav-icon"><i class="fa-solid fa-clipboard-user"></i></span> Attendance
            </a>
            <a href="<?php echo BASE_URL; ?>/manager/tasks.php" class="sidebar-nav-item<?php echo isActive('manager', 'tasks'); ?>">
                <span class="nav-icon"><i class="fa-solid fa-list-check"></i></span> Tasks
            </a>
            <a href="<?php echo BASE_URL; ?>/manager/meetings.php" class="sidebar-nav-item<?php echo isActive('manager', 'meetings'); ?>">
                <span class="nav-icon"><i class="fa-solid fa-calendar-days"></i></span> Meetings
            </a>
            <a href="<?php echo BASE_URL; ?>/manager/tickets.php" class="sidebar-nav-item<?php echo isActive('manager', 'tickets'); ?>">
                <span class="nav-icon"><i class="fa-solid fa-headset"></i></span> Support & Help
            </a>
            <a href="<?php echo BASE_URL; ?>/manager/leaves.php" class="sidebar-nav-item<?php echo isActive('manager', 'leaves'); ?>">
                <span class="nav-icon"><i class="fa-solid fa-umbrella-beach"></i></span> Leaves
            </a>
            <a href="<?php echo BASE_URL; ?>/manager/announcements.php" class="sidebar-nav-item<?php echo isActive('manager', 'announcements'); ?>">
                <span class="nav-icon"><i class="fa-solid fa-bullhorn"></i></span> Announcements
            </a>

        <?php elseif ($baseRole === ROLE_EMPLOYEE): ?>
            <!-- ===== EMPLOYEE NAVIGATION ===== -->
            <div class="sidebar-nav-label">Main</div>
            <a href="<?php echo BASE_URL; ?>/employee/dashboard.php" class="sidebar-nav-item<?php echo isActive('employee', 'dashboard'); ?>">
                <span class="nav-icon"><i class="fa-solid fa-house"></i></span> Dashboard
            </a>
            <a href="<?php echo BASE_URL; ?>/chat/index.php" class="sidebar-nav-item<?php echo isActive('chat', 'index'); ?>">
                <span class="nav-icon"><i class="fa-solid fa-comments"></i></span> Team Chat
                <span class="sidebar-chat-badge" style="display: <?php echo $sidebarChatUnread > 0 ? 'inline-flex' : 'none'; ?>;"><?php echo $sidebarChatUnread > 99 ? '99+' : $sidebarChatUnread; ?></span>
            </a>

            <div class="sidebar-nav-label">My Workspace</div>
            <a href="<?php echo BASE_URL; ?>/employee/attendance.php" class="sidebar-nav-item<?php echo isActive('employee', 'attendance'); ?>">
                <span class="nav-icon"><i class="fa-solid fa-clipboard-user"></i></span> My Attendance
            </a>
            <a href="<?php echo BASE_URL; ?>/employee/tasks.php" class="sidebar-nav-item<?php echo isActive('employee', 'tasks'); ?>">
                <span class="nav-icon"><i class="fa-solid fa-list-check"></i></span> My Tasks
            </a>
            <a href="<?php echo BASE_URL; ?>/employee/meetings.php" class="sidebar-nav-item<?php echo isActive('employee', 'meetings'); ?>">
                <span class="nav-icon"><i class="fa-solid fa-calendar-days"></i></span> Meetings
            </a>
            <a href="<?php echo BASE_URL; ?>/employee/leaves.php" class="sidebar-nav-item<?php echo isActive('employee', 'leaves'); ?>">
                <span class="nav-icon"><i class="fa-solid fa-umbrella-beach"></i></span> Apply Leave
            </a>
            <a href="<?php echo BASE_URL; ?>/employee/announcements.php" class="sidebar-nav-item<?php echo isActive('employee', 'announcements'); ?>">
                <span class="nav-icon"><i class="fa-solid fa-bullhorn"></i></span> Announcements
            </a>
            <a href="<?php echo BASE_URL; ?>/employee/team.php" class="sidebar-nav-item<?php echo isActive('employee', 'team'); ?>">
                <span class="nav-icon"><i class="fa-solid fa-users"></i></span> My Team
            </a>

            <div class="sidebar-nav-label">Help Desk</div>
            <a href="<?php echo BASE_URL; ?>/employee/support.php" class="sidebar-nav-item<?php echo isActive('employee', 'support'); ?>">
                <span class="nav-icon"><i class="fa-solid fa-headset"></i></span> Support & Help
            </a>

        <?php elseif ($baseRole === ROLE_HR): ?>
            <!-- ===== HR NAVIGATION ===== -->
            <div class="sidebar-nav-label">Main</div>
            <a href="<?php echo BASE_URL; ?>/hr/dashboard.php" class="sidebar-nav-item<?php echo isActive('hr', 'dashboard'); ?>">
                <span class="nav-icon"><i class="fa-solid fa-chart-pie"></i></span> Dashboard
            </a>
            <a href="<?php echo BASE_URL; ?>/chat/index.php" class="sidebar-nav-item<?php echo isActive('chat', 'index'); ?>">
                <span class="nav-icon"><i class="fa-solid fa-comments"></i></span> Team Chat
                <span class="sidebar-chat-badge" style="display: <?php echo $sidebarChatUnread > 0 ? 'inline-flex' : 'none'; ?>;"><?php echo $sidebarChatUnread > 99 ? '99+' : $sidebarChatUnread; ?></span>
            </a>
            <a href="<?php echo BASE_URL; ?>/profile.php" class="sidebar-nav-item<?php echo isActive('', 'profile'); ?>">
                <span class="nav-icon"><i class="fa-solid fa-circle-user"></i></span> My Profile
            </a>

            <div class="sidebar-nav-label">People & Workforce</div>
            <a href="<?php echo BASE_URL; ?>/hr/employees.php" class="sidebar-nav-item<?php echo isActive('hr', 'employees'); ?>">
                <span class="nav-icon"><i class="fa-solid fa-users"></i></span> Employees
            </a>
            <a href="<?php echo BASE_URL; ?>/hr/tasks.php" class="sidebar-nav-item<?php echo isActive('hr', 'tasks'); ?>">
                <span class="nav-icon"><i class="fa-solid fa-list-check"></i></span> Tasks
            </a>
            <a href="<?php echo BASE_URL; ?>/hr/attendance.php" class="sidebar-nav-item<?php echo isActive('hr', 'attendance'); ?>">
                <span class="nav-icon"><i class="fa-solid fa-clipboard-user"></i></span> Attendance
            </a>
            <a href="<?php echo BASE_URL; ?>/hr/leaves.php" class="sidebar-nav-item<?php echo isActive('hr', 'leaves'); ?>">
                <span class="nav-icon"><i class="fa-solid fa-umbrella-beach"></i></span> Leave Management
            </a>
            <a href="<?php echo BASE_URL; ?>/hr/payroll.php" class="sidebar-nav-item<?php echo isActive('hr', 'payroll'); ?>">
                <span class="nav-icon"><i class="fa-solid fa-file-invoice-dollar"></i></span> Payroll & Salary
            </a>

            <div class="sidebar-nav-label">Services & Engagement</div>
            <a href="<?php echo BASE_URL; ?>/hr/support.php" class="sidebar-nav-item<?php echo isActive('hr', 'support'); ?>">
                <span class="nav-icon"><i class="fa-solid fa-headset"></i></span> HR Support
            </a>
            <a href="<?php echo BASE_URL; ?>/hr/meetings.php" class="sidebar-nav-item<?php echo isActive('hr', 'meetings'); ?>">
                <span class="nav-icon"><i class="fa-solid fa-calendar-days"></i></span> Meetings
            </a>
            <a href="<?php echo BASE_URL; ?>/hr/team.php" class="sidebar-nav-item<?php echo isActive('hr', 'team'); ?>">
                <span class="nav-icon"><i class="fa-solid fa-bullhorn"></i></span> Team & Communication
            </a>

        <?php endif; ?>
    </nav>

    <!-- Sidebar Footer — User Info -->
    <div class="sidebar-footer">
        <a href="<?php echo BASE_URL; ?>/profile.php" class="sidebar-user" style="text-decoration: none; display: flex; align-items: center; width: 100%; border-radius: var(--radius-sm); transition: background-color var(--transition-base); padding: var(--space-2);" onmouseover="this.style.backgroundColor='var(--color-bg-tertiary)'" onmouseout="this.style.backgroundColor='transparent'">
            <div class="sidebar-user-avatar"><?php echo e($userInitials); ?></div>
            <div class="sidebar-user-info">
                <div class="sidebar-user-name"><?php echo e($currentUser['name'] ?? 'User'); ?></div>
                <div class="sidebar-user-role"><?php echo ucfirst(e($userRole)); ?></div>
            </div>
        </a>
    </div>
</aside>
