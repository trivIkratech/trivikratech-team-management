<?php
/**
 * Sidebar Component — Role-Aware Navigation
 * 
 * Dynamically renders sidebar links based on the logged-in user's role.
 * Highlights the active page.
 */

$currentUser = getCurrentUser();
$userRole = $currentUser['role'] ?? '';
$userInitials = getInitials($currentUser['name'] ?? 'U');

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
        <div class="sidebar-brand-icon">TM</div>
        <div class="sidebar-brand-text">
            Team Manager
            <span><?php echo ucfirst(e($userRole)); ?> Panel</span>
        </div>
    </div>

    <!-- Navigation -->
    <nav class="sidebar-nav">

        <?php if ($userRole === ROLE_FOUNDER): ?>
            <!-- ===== FOUNDER NAVIGATION ===== -->
            <div class="sidebar-nav-label">Main</div>
            <a href="<?php echo BASE_URL; ?>/founder/dashboard.php" class="sidebar-nav-item<?php echo isActive('founder', 'dashboard'); ?>">
                <span class="nav-icon">📊</span> Dashboard
            </a>

            <div class="sidebar-nav-label">People</div>
            <a href="<?php echo BASE_URL; ?>/founder/managers.php" class="sidebar-nav-item<?php echo isActive('founder', 'managers'); ?>">
                <span class="nav-icon">👔</span> Managers
            </a>
            <a href="<?php echo BASE_URL; ?>/founder/employees.php" class="sidebar-nav-item<?php echo isActive('founder', 'employees'); ?>">
                <span class="nav-icon">👥</span> Employees
            </a>
            <a href="<?php echo BASE_URL; ?>/founder/user-management.php" class="sidebar-nav-item<?php echo isActive('founder', 'user-management'); ?>">
                <span class="nav-icon">⚙️</span> User Management
            </a>

            <div class="sidebar-nav-label">Modules</div>
            <a href="<?php echo BASE_URL; ?>/founder/attendance.php" class="sidebar-nav-item<?php echo isActive('founder', 'attendance'); ?>">
                <span class="nav-icon">📋</span> Attendance
            </a>
            <a href="<?php echo BASE_URL; ?>/founder/tasks.php" class="sidebar-nav-item<?php echo isActive('founder', 'tasks'); ?>">
                <span class="nav-icon">✅</span> Tasks
            </a>
            <a href="<?php echo BASE_URL; ?>/founder/meetings.php" class="sidebar-nav-item<?php echo isActive('founder', 'meetings'); ?>">
                <span class="nav-icon">📅</span> Meetings
            </a>

            <div class="sidebar-nav-label">Insights</div>
            <a href="<?php echo BASE_URL; ?>/founder/reports.php" class="sidebar-nav-item<?php echo isActive('founder', 'reports'); ?>">
                <span class="nav-icon">📈</span> Reports
            </a>
            <a href="<?php echo BASE_URL; ?>/founder/tickets.php" class="sidebar-nav-item<?php echo isActive('founder', 'tickets'); ?>">
                <span class="nav-icon">🎟️</span> Support Tickets
            </a>
            <a href="<?php echo BASE_URL; ?>/founder/leaves.php" class="sidebar-nav-item<?php echo isActive('founder', 'leaves'); ?>">
                <span class="nav-icon">🌴</span> Leave Requests
            </a>
            <a href="<?php echo BASE_URL; ?>/founder/announcements.php" class="sidebar-nav-item<?php echo isActive('founder', 'announcements'); ?>">
                <span class="nav-icon">📢</span> Announcements
            </a>

        <?php elseif ($userRole === ROLE_MANAGER): ?>
            <!-- ===== MANAGER NAVIGATION ===== -->
            <div class="sidebar-nav-label">Main</div>
            <a href="<?php echo BASE_URL; ?>/manager/dashboard.php" class="sidebar-nav-item<?php echo isActive('manager', 'dashboard'); ?>">
                <span class="nav-icon">📊</span> Dashboard
            </a>

            <div class="sidebar-nav-label">Management</div>
            <a href="<?php echo BASE_URL; ?>/manager/employees.php" class="sidebar-nav-item<?php echo isActive('manager', 'employees'); ?>">
                <span class="nav-icon">👥</span> Manage Employees
            </a>
            <a href="<?php echo BASE_URL; ?>/manager/attendance.php" class="sidebar-nav-item<?php echo isActive('manager', 'attendance'); ?>">
                <span class="nav-icon">📋</span> Attendance
            </a>
            <a href="<?php echo BASE_URL; ?>/manager/tasks.php" class="sidebar-nav-item<?php echo isActive('manager', 'tasks'); ?>">
                <span class="nav-icon">✅</span> Tasks
            </a>
            <a href="<?php echo BASE_URL; ?>/manager/meetings.php" class="sidebar-nav-item<?php echo isActive('manager', 'meetings'); ?>">
                <span class="nav-icon">📅</span> Meetings
            </a>
            <a href="<?php echo BASE_URL; ?>/manager/tickets.php" class="sidebar-nav-item<?php echo isActive('manager', 'tickets'); ?>">
                <span class="nav-icon">🎟️</span> Team Tickets
            </a>
            <a href="<?php echo BASE_URL; ?>/manager/leaves.php" class="sidebar-nav-item<?php echo isActive('manager', 'leaves'); ?>">
                <span class="nav-icon">🌴</span> Leaves
            </a>
            <a href="<?php echo BASE_URL; ?>/manager/announcements.php" class="sidebar-nav-item<?php echo isActive('manager', 'announcements'); ?>">
                <span class="nav-icon">📢</span> Announcements
            </a>

        <?php elseif ($userRole === ROLE_EMPLOYEE): ?>
            <!-- ===== EMPLOYEE NAVIGATION ===== -->
            <div class="sidebar-nav-label">Main</div>
            <a href="<?php echo BASE_URL; ?>/employee/dashboard.php" class="sidebar-nav-item<?php echo isActive('employee', 'dashboard'); ?>">
                <span class="nav-icon">🏠</span> Dashboard
            </a>

            <div class="sidebar-nav-label">My Workspace</div>
            <a href="<?php echo BASE_URL; ?>/employee/attendance.php" class="sidebar-nav-item<?php echo isActive('employee', 'attendance'); ?>">
                <span class="nav-icon">📋</span> My Attendance
            </a>
            <a href="<?php echo BASE_URL; ?>/employee/tasks.php" class="sidebar-nav-item<?php echo isActive('employee', 'tasks'); ?>">
                <span class="nav-icon">✅</span> My Tasks
            </a>
            <a href="<?php echo BASE_URL; ?>/employee/meetings.php" class="sidebar-nav-item<?php echo isActive('employee', 'meetings'); ?>">
                <span class="nav-icon">📅</span> Meetings
            </a>
            <a href="<?php echo BASE_URL; ?>/employee/leaves.php" class="sidebar-nav-item<?php echo isActive('employee', 'leaves'); ?>">
                <span class="nav-icon">🌴</span> Apply Leave
            </a>
            <a href="<?php echo BASE_URL; ?>/employee/team.php" class="sidebar-nav-item<?php echo isActive('employee', 'team'); ?>">
                <span class="nav-icon">👥</span> My Team
            </a>

            <div class="sidebar-nav-label">Help Desk</div>
            <a href="<?php echo BASE_URL; ?>/employee/support.php" class="sidebar-nav-item<?php echo isActive('employee', 'support'); ?>">
                <span class="nav-icon">🤝</span> Support & Help
            </a>

        <?php elseif ($userRole === ROLE_HR): ?>
            <!-- ===== HR NAVIGATION ===== -->
            <div class="sidebar-nav-label">Main</div>
            <a href="<?php echo BASE_URL; ?>/hr/dashboard.php" class="sidebar-nav-item<?php echo isActive('hr', 'dashboard'); ?>">
                <span class="nav-icon">📊</span> Dashboard
            </a>

            <div class="sidebar-nav-label">HR Workspace</div>
            <a href="<?php echo BASE_URL; ?>/hr/leaves.php" class="sidebar-nav-item<?php echo isActive('hr', 'leaves'); ?>">
                <span class="nav-icon">🌴</span> Leaves & Approvals
            </a>
            <a href="<?php echo BASE_URL; ?>/hr/employees.php" class="sidebar-nav-item<?php echo isActive('hr', 'employees'); ?>">
                <span class="nav-icon">👥</span> Manage Employees
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
