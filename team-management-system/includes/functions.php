<?php
/**
 * Shared Utility Functions
 * 
 * CSRF protection, input sanitization, formatting, and helpers.
 */

// =============================================
// CSRF Protection
// =============================================

/**
 * Generate or retrieve CSRF token
 */
function csrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Output a hidden CSRF input field
 */
function csrfField(): string {
    return '<input type="hidden" name="csrf_token" value="' . csrfToken() . '">';
}

/**
 * Validate CSRF token from POST request
 */
function validateCsrf(): bool {
    if (empty($_POST['csrf_token']) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
}

/**
 * Validate CSRF and die with error if invalid
 */
function requireCsrf(): void {
    if (!validateCsrf()) {
        http_response_code(403);
        die('Invalid security token. Please refresh the page and try again.');
    }
}

// =============================================
// Input Sanitization
// =============================================

/**
 * Sanitize string output for HTML display (XSS prevention)
 */
function e(mixed $value): string {
    if ($value === null) return '';
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/**
 * Sanitize input string or array (trim + strip tags recursively)
 */
function sanitize(mixed $input): mixed {
    if (is_array($input)) {
        return array_map('sanitize', $input);
    }
    if ($input === null) {
        return '';
    }
    return trim(strip_tags((string)$input));
}

/**
 * Get POST value with sanitization (supports strings and arrays)
 */
function post(string $key, mixed $default = ''): mixed {
    return isset($_POST[$key]) ? sanitize($_POST[$key]) : $default;
}

/**
 * Get GET value with sanitization (supports strings and arrays)
 */
function get(string $key, mixed $default = ''): mixed {
    return isset($_GET[$key]) ? sanitize($_GET[$key]) : $default;
}

// =============================================
// Date & Time Formatting
// =============================================

/**
 * Format date for display
 */
function formatDate(?string $date, string $format = 'd M Y'): string {
    if (empty($date)) return '—';
    return date($format, strtotime($date));
}

/**
 * Format time for display (12-hour)
 */
function formatTime(?string $time): string {
    if (empty($time)) return '—';
    return date('h:i A', strtotime($time));
}

/**
 * Format datetime for display
 */
function formatDateTime(?string $datetime): string {
    if (empty($datetime)) return '—';
    return date('d M Y, h:i A', strtotime($datetime));
}

/**
 * Format currency amount (INR)
 */
function formatCurrency($amount): string {
    if ($amount === null || $amount === '') return '₹0.00';
    return '₹' . number_format((float)$amount, 2);
}

/**
 * Calculate working time between check-in and check-out
 */
function calculateWorkingTime(string $checkIn, string $checkOut): string {
    $in = new DateTime($checkIn);
    $out = new DateTime($checkOut);
    $diff = $in->diff($out);
    return sprintf('%02d:%02d:%02d', $diff->h, $diff->i, $diff->s);
}

/**
 * Get today's date
 */
function today(): string {
    return date('Y-m-d');
}

/**
 * Get current time
 */
function currentTime(): string {
    return date('H:i:s');
}

// =============================================
// Status & Badge Helpers
// =============================================

/**
 * Get CSS class for task status badge
 */
function taskStatusBadge(string $status): string {
    return match($status) {
        TASK_TODO        => 'badge-secondary',
        TASK_IN_PROGRESS => 'badge-primary',
        TASK_COMPLETED   => 'badge-success',
        default          => 'badge-secondary'
    };
}

/**
 * Get human-readable task status label
 */
function taskStatusLabel(string $status): string {
    return match($status) {
        TASK_TODO        => 'To Do',
        TASK_IN_PROGRESS => 'In Progress',
        TASK_COMPLETED   => 'Completed',
        default          => ucfirst($status)
    };
}

/**
 * Get CSS class for priority badge
 */
function priorityBadge(string $priority): string {
    return match($priority) {
        PRIORITY_LOW    => 'badge-info',
        PRIORITY_MEDIUM => 'badge-warning',
        PRIORITY_HIGH   => 'badge-danger',
        default         => 'badge-secondary'
    };
}

/**
 * Get human-readable priority label
 */
function priorityLabel(string $priority): string {
    return ucfirst($priority);
}

/**
 * Retrieve all roles from the database (system + custom)
 */
function getAllRoles(): array {
    static $rolesCache = null;
    if ($rolesCache !== null) return $rolesCache;
    try {
        $db = getDB();
        $rolesCache = $db->query("SELECT * FROM roles ORDER BY is_system DESC, name ASC")->fetchAll();
    } catch (PDOException $e) {
        $rolesCache = [
            ['id' => 1, 'name' => 'Founder', 'slug' => 'founder', 'base_role' => 'founder', 'is_system' => 1],
            ['id' => 2, 'name' => 'Manager', 'slug' => 'manager', 'base_role' => 'manager', 'is_system' => 1],
            ['id' => 3, 'name' => 'HR', 'slug' => 'hr', 'base_role' => 'hr', 'is_system' => 1],
            ['id' => 4, 'name' => 'Employee', 'slug' => 'employee', 'base_role' => 'employee', 'is_system' => 1],
        ];
    }
    return $rolesCache;
}

/**
 * Get base role for a given role slug
 */
function getRoleBaseType(string $slug): string {
    $roles = getAllRoles();
    foreach ($roles as $r) {
        if ($r['slug'] === $slug) {
            return $r['base_role'] ?? 'employee';
        }
    }
    return $slug;
}

/**
 * Get role display name by slug
 */
function getRoleDisplayName(string $slug): string {
    $roles = getAllRoles();
    foreach ($roles as $r) {
        if ($r['slug'] === $slug) {
            return $r['name'];
        }
    }
    return ucfirst(str_replace('_', ' ', $slug));
}

/**
 * Get CSS class for user role badge
 */
function roleBadge(string $role): string {
    return match($role) {
        ROLE_FOUNDER  => 'badge-purple',
        ROLE_MANAGER  => 'badge-warning',
        ROLE_HR       => 'badge-primary',
        ROLE_EMPLOYEE => 'badge-info',
        default       => 'badge-role-' . $role
    };
}

/**
 * Get CSS class for attendance status badge
 */
function attendanceStatusBadge(string $status): string {
    return match($status) {
        ATTENDANCE_PRESENT  => 'badge-success',
        ATTENDANCE_ABSENT   => 'badge-danger',
        ATTENDANCE_HALF_DAY => 'badge-warning',
        'weekly_off'        => 'badge-warning',
        default             => 'badge-secondary'
    };
}

/**
 * Accurately resolve attendance status for a record
 * 
 * Shift Rule: >= 6h (21600s) = present, >= 3h (10800s) = half-day, < 3h = absent.
 * If currently checked in (no checkout yet) = present.
 * If on approved leave = leave.
 * If Sunday and not checked in and no leave = weekly_off.
 * If not checked in on working day and no leave = absent.
 */
function resolveAttendanceStatus(?string $checkIn, ?string $checkOut, ?string $totalWorkingTime, ?string $attStatus, ?int $leaveId = null, ?string $date = null): string {
    if (!empty($leaveId)) {
        return 'leave';
    }

    if (!empty($checkIn)) {
        // If checked out and has working time calculated
        if (!empty($totalWorkingTime)) {
            $parts = explode(':', $totalWorkingTime);
            $secs = ((int)($parts[0] ?? 0) * 3600) + ((int)($parts[1] ?? 0) * 60) + ((int)($parts[2] ?? 0));
            if ($secs >= 21600) {
                return 'present';
            } elseif ($secs >= 10800) {
                return 'half-day';
            } else {
                if ($attStatus === 'present' || $attStatus === 'half-day') {
                    return $attStatus;
                }
                return 'absent';
            }
        }
        
        // If checked in and checked out without totalWorkingTime, calculate from timestamps
        if (!empty($checkOut)) {
            $in = strtotime($checkIn);
            $out = strtotime($checkOut);
            if ($out > $in) {
                $secs = $out - $in;
                if ($secs >= 21600) return 'present';
                if ($secs >= 10800) return 'half-day';
                return ($attStatus === 'present' || $attStatus === 'half-day') ? $attStatus : 'absent';
            }
        }

        // If checked in with no checkout yet, they are currently on shift (present)
        if ($attStatus === 'half-day') return 'half-day';
        return 'present';
    }

    // If no check-in and status was manually set to present or half-day
    if ($attStatus === 'present' || $attStatus === 'half-day') {
        return $attStatus;
    }

    // If date is provided and it is a Sunday, and status was not explicitly forced to absent
    if (!empty($date)) {
        $dayOfWeek = date('l', strtotime($date));
        if ($dayOfWeek === 'Sunday' && $attStatus !== 'absent') {
            return 'weekly_off';
        }
    }

    return 'absent';
}

/**
 * Render visual attendance status badge with icons
 */
function renderAttendanceBadge(string $resolvedStatus, ?string $leaveType = null, ?string $leaveReason = null): string {
    if ($resolvedStatus === 'leave' || !empty($leaveType)) {
        $lt = strtolower($leaveType ?: 'casual');
        if ($lt === 'sick') {
            return '<span class="badge badge-purple" title="' . e($leaveReason ?? '') . '"><i class="fa-solid fa-notes-medical"></i> Sick Leave</span>';
        } elseif ($lt === 'paid') {
            return '<span class="badge badge-info" title="' . e($leaveReason ?? '') . '"><i class="fa-solid fa-award"></i> Paid Leave</span>';
        } elseif ($lt === 'casual' || $lt === 'planned') {
            return '<span class="badge badge-primary" title="' . e($leaveReason ?? '') . '"><i class="fa-solid fa-calendar-check"></i> Planned Leave</span>';
        } else {
            return '<span class="badge badge-info" title="' . e($leaveReason ?? '') . '"><i class="fa-solid fa-umbrella-beach"></i> ' . ucfirst(e($lt)) . ' Leave</span>';
        }
    } elseif ($resolvedStatus === 'weekly_off') {
        return '<span class="badge badge-warning" style="background: rgba(234, 179, 8, 0.15); color: #ca8a04; border: 1px solid rgba(234, 179, 8, 0.3);"><i class="fa-solid fa-mug-hot"></i> Sunday · Weekly Off</span>';
    } elseif ($resolvedStatus === 'present') {
        return '<span class="badge badge-success"><i class="fa-solid fa-circle-check"></i> Present</span>';
    } elseif ($resolvedStatus === 'half-day') {
        return '<span class="badge badge-warning"><i class="fa-solid fa-hourglass-half"></i> Half-Day</span>';
    } else {
        return '<span class="badge badge-danger"><i class="fa-solid fa-circle-xmark"></i> Absent</span>';
    }
}

/**
 * Check if employee has already joined by a given date
 */
function isEmployeeJoinedOnDate(string $targetDate, ?string $joiningDate, ?string $createdAt = null): bool {
    $effectiveJoining = $joiningDate ?: (!empty($createdAt) ? date('Y-m-d', strtotime($createdAt)) : '2000-01-01');
    return strtotime($targetDate) >= strtotime($effectiveJoining);
}

/**
 * Get CSS class for user status badge
 */
function userStatusBadge(string $status): string {
    return match($status) {
        USER_ACTIVE   => 'badge-success',
        USER_INACTIVE => 'badge-danger',
        default       => 'badge-secondary'
    };
}

// =============================================
// Overdue Check
// =============================================

/**
 * Check if a task is overdue
 */
function isOverdue(?string $deadline, string $status): bool {
    if (empty($deadline) || $status === TASK_COMPLETED) return false;
    return strtotime($deadline) < strtotime(today());
}

// =============================================
// Flash Messages
// =============================================

/**
 * Set a flash message
 */
function setFlash(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

/**
 * Get and clear flash message
 */
function getFlash(): ?array {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Render flash message HTML
 */
function renderFlash(): string {
    $flash = getFlash();
    if (!$flash) return '';
    
    $type = e($flash['type']);
    $message = e($flash['message']);
    
    return '<div class="alert alert-' . $type . '" id="flash-message">
                <span>' . $message . '</span>
                <button class="alert-close" onclick="this.parentElement.remove()">×</button>
            </div>';
}

// =============================================
// Pagination Helper
// =============================================

/**
 * Calculate pagination values
 */
function paginate(int $totalRecords, int $currentPage = 1, int $perPage = RECORDS_PER_PAGE): array {
    $totalPages = max(1, ceil($totalRecords / $perPage));
    $currentPage = max(1, min($currentPage, $totalPages));
    $offset = ($currentPage - 1) * $perPage;
    
    return [
        'total' => $totalRecords,
        'per_page' => $perPage,
        'current_page' => $currentPage,
        'total_pages' => $totalPages,
        'offset' => $offset,
        'has_prev' => $currentPage > 1,
        'has_next' => $currentPage < $totalPages
    ];
}

/**
 * Render pagination HTML
 */
function renderPagination(array $pagination, string $baseUrl): string {
    if ($pagination['total_pages'] <= 1) return '';
    
    $html = '<div class="pagination">';
    
    // Previous button
    if ($pagination['has_prev']) {
        $html .= '<a href="' . $baseUrl . '&page=' . ($pagination['current_page'] - 1) . '" class="pagination-btn">← Prev</a>';
    }
    
    // Page numbers
    $start = max(1, $pagination['current_page'] - 2);
    $end = min($pagination['total_pages'], $pagination['current_page'] + 2);
    
    for ($i = $start; $i <= $end; $i++) {
        $active = $i === $pagination['current_page'] ? ' active' : '';
        $html .= '<a href="' . $baseUrl . '&page=' . $i . '" class="pagination-btn' . $active . '">' . $i . '</a>';
    }
    
    // Next button
    if ($pagination['has_next']) {
        $html .= '<a href="' . $baseUrl . '&page=' . ($pagination['current_page'] + 1) . '" class="pagination-btn">Next →</a>';
    }
    
    $html .= '</div>';
    
    return $html;
}

// =============================================
// Misc Helpers
// =============================================

/**
 * Get user initials from name (for avatar)
 */
function getInitials(?string $name = ''): string {
    $cleanName = trim($name ?? '');
    if ($cleanName === '') {
        return 'U';
    }
    $parts = explode(' ', $cleanName);
    $initials = '';
    foreach (array_slice($parts, 0, 2) as $part) {
        $initials .= strtoupper(mb_substr($part, 0, 1));
    }
    return $initials ?: 'U';
}

/**
 * Time ago helper
 */
function timeAgo(string $datetime): string {
    if (empty($datetime)) {
        return '';
    }
    $time = strtotime($datetime);
    if (!$time) {
        return '';
    }
    $diff = time() - $time;
    
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return max(1, (int)floor($diff / 60)) . 'm ago';
    if ($diff < 86400) return (int)floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return (int)floor($diff / 86400) . 'd ago';
    
    return formatDate($datetime);
}

// =============================================
// In-App Notification System
// =============================================

/**
 * Automatically reset / mark as read notifications older than today.
 * Ensures notifications are reset every day.
 */
function resetDailyNotifications(): void {
    try {
        $db = getDB();
        $today = date('Y-m-d');
        // Mark all notifications from previous days as read so every day starts fresh with 0 unread
        $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE DATE(created_at) < ? AND is_read = 0");
        $stmt->execute([$today]);
    } catch (Exception $e) {
        // Silently fail if table not available
    }
}

/**
 * Add an in-app notification for a specific user
 */
function createNotification(int $userId, string $title, string $message, ?string $link = null, string $type = 'info'): bool {
    try {
        $db = getDB();
        $now = date('Y-m-d H:i:s');
        $stmt = $db->prepare("
            INSERT INTO notifications (user_id, title, message, link, type, is_read, created_at) 
            VALUES (?, ?, ?, ?, ?, 0, ?)
        ");
        return $stmt->execute([$userId, $title, $message, $link, $type, $now]);
    } catch (PDOException $e) {
        error_log("Error creating notification: " . $e->getMessage());
        return false;
    }
}

/**
 * Get unread/today's notifications for a user
 */
function getUnreadNotifications(int $userId, int $limit = 6): array {
    try {
        resetDailyNotifications();
        $db = getDB();
        $today = date('Y-m-d');
        $stmt = $db->prepare("
            SELECT * FROM notifications 
            WHERE user_id = ? AND DATE(created_at) = ?
            ORDER BY is_read ASC, created_at DESC 
            LIMIT ?
        ");
        $stmt->bindValue(1, $userId, PDO::PARAM_INT);
        $stmt->bindValue(2, $today, PDO::PARAM_STR);
        $stmt->bindValue(3, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Count unread notifications for today (resets every day)
 */
function countUnreadNotifications(int $userId): int {
    try {
        resetDailyNotifications();
        $db = getDB();
        $today = date('Y-m-d');
        $stmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0 AND DATE(created_at) = ?");
        $stmt->execute([$userId, $today]);
        return (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}

/**
 * Count unread chat messages for a specific user across all rooms they are a member of
 */
function countUnreadChatMessages(int $userId): int {
    try {
        if ($userId <= 0) return 0;
        $db = getDB();
        
        static $checkedColumn = false;
        if (!$checkedColumn) {
            try {
                $db->exec("ALTER TABLE chat_room_members ADD COLUMN last_read_message_id INT NOT NULL DEFAULT 0");
            } catch (Exception $e) {}
            try {
                $db->exec("INSERT IGNORE INTO chat_room_members (room_id, user_id) SELECT 1, id FROM users WHERE status = 'active'");
            } catch (Exception $e) {}
            $checkedColumn = true;
        }

        $stmt = $db->prepare("
            SELECT COUNT(*) 
            FROM chat_messages m
            JOIN chat_room_members rm ON m.room_id = rm.room_id
            WHERE rm.user_id = ? 
              AND m.sender_id != ? 
              AND m.id > COALESCE(rm.last_read_message_id, 0)
        ");
        $stmt->execute([$userId, $userId]);
        return (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}

/**
 * Render standard Working Module & Shift Information Banner
 */
function renderWorkingModuleBanner(): string {
    $todayDay = date('l');
    $isWeekend = ($todayDay === 'Sunday');
    $badgeText = $isWeekend ? 'Sunday · Weekly Off (Paid)' : 'Mon – Sat · Working Day';
    $badgeClass = $isWeekend ? 'badge-warning' : 'badge-info';
    
    return '
    <div class="working-module-banner" style="background: var(--color-bg-secondary); border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: 12px 18px; max-width: 580px; margin: 0 auto 20px; font-size: 13px; text-align: left; box-shadow: 0 2px 6px rgba(0,0,0,0.04);">
        <div style="font-weight: 600; margin-bottom: 6px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
            <span style="display: flex; align-items: center; gap: 6px; color: var(--color-primary); font-size: 14px;">
                <i class="fa-solid fa-business-time"></i> Working Shift: 10:00 AM – 05:00 PM
            </span>
            <span class="badge ' . $badgeClass . '" style="font-size: 11px;">' . $badgeText . '</span>
        </div>
        <div style="display: flex; gap: 14px; color: var(--color-text-secondary); font-size: 12px; margin-top: 6px; flex-wrap: wrap; border-top: 1px dashed var(--color-border); padding-top: 8px;">
            <span><i class="fa-solid fa-calendar-days"></i> <strong>Mon – Sat (Working)</strong></span>
            <span><i class="fa-solid fa-mug-hot"></i> <strong>Sun (Weekly Off)</strong></span>
            <span><i class="fa-solid fa-clock"></i> <strong>7h Shift</strong></span>
            <span><i class="fa-solid fa-laptop-code"></i> <strong>6h Net Work</strong></span>
            <span><i class="fa-solid fa-circle-check" style="color: var(--color-success);"></i> Full Day: <strong>≥ 6h</strong> (Half: <strong>≥ 3h</strong>)</span>
        </div>
    </div>';
}

/**
 * Backward compatibility alias for renderWorkingModuleBanner
 */
function renderShiftScheduleCard(): string {
    return renderWorkingModuleBanner();
}

/**
 * Safe HTTP Redirect Helper
 */
function redirect(string $url): void {
    header('Location: ' . $url);
    exit;
}

// =============================================
// Teams & Squads Management System
// =============================================

/**
 * Ensure teams and team_members tables exist and tasks has team_id column
 */
function ensureTeamsTablesExist(): void {
    static $provisioned = false;
    if ($provisioned) return;
    
    try {
        $db = getDB();
        
        $db->exec("
            CREATE TABLE IF NOT EXISTS `teams` (
              `id` int NOT NULL AUTO_INCREMENT,
              `name` varchar(100) NOT NULL,
              `description` text,
              `leader_id` int NOT NULL,
              `created_by` int NOT NULL,
              `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
              `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              KEY `idx_leader_id` (`leader_id`),
              KEY `idx_created_by` (`created_by`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        $db->exec("
            CREATE TABLE IF NOT EXISTS `team_members` (
              `id` int NOT NULL AUTO_INCREMENT,
              `team_id` int NOT NULL,
              `user_id` int NOT NULL,
              `role_in_team` varchar(50) DEFAULT 'member',
              `joined_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              UNIQUE KEY `uk_team_member` (`team_id`,`user_id`),
              KEY `idx_team_id` (`team_id`),
              KEY `idx_user_id` (`user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        // Check if team_id column exists in tasks table
        try {
            $stmt = $db->query("SHOW COLUMNS FROM `tasks` LIKE 'team_id'");
            if ($stmt && !$stmt->fetch()) {
                $db->exec("ALTER TABLE `tasks` ADD COLUMN `team_id` INT NULL DEFAULT NULL AFTER `assigned_by`");
            }
        } catch (Throwable $e) {
            // Ignore if column already exists
        }

        $provisioned = true;
    } catch (Throwable $e) {
        error_log("Teams table provisioning error: " . $e->getMessage());
    }
}

/**
 * Get all teams with leader info and member count
 */
function getAllTeams(): array {
    ensureTeamsTablesExist();
    try {
        $db = getDB();
        $stmt = $db->query("
            SELECT t.*, 
                   u.name as leader_name, u.role as leader_role, u.email as leader_email,
                   cb.name as creator_name,
                   (SELECT COUNT(*) FROM team_members tm JOIN users mu ON tm.user_id = mu.id WHERE tm.team_id = t.id AND mu.status = 'active') as member_count,
                   (SELECT COUNT(*) FROM tasks k WHERE k.team_id = t.id) as task_count,
                   (SELECT COUNT(*) FROM tasks kc WHERE kc.team_id = t.id AND kc.status = 'completed') as completed_task_count
            FROM teams t
            JOIN users u ON t.leader_id = u.id
            LEFT JOIN users cb ON t.created_by = cb.id
            ORDER BY t.name ASC
        ");
        return $stmt ? $stmt->fetchAll() : [];
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * Get teams accessible/managed by a specific manager
 */
function getManagerTeams(int $managerId): array {
    ensureTeamsTablesExist();
    try {
        $db = getDB();
        $stmt = $db->prepare("
            SELECT t.*, 
                   u.name as leader_name, u.role as leader_role, u.email as leader_email,
                   cb.name as creator_name,
                   (SELECT COUNT(*) FROM team_members tm JOIN users mu ON tm.user_id = mu.id WHERE tm.team_id = t.id AND mu.status = 'active') as member_count,
                   (SELECT COUNT(*) FROM tasks k WHERE k.team_id = t.id) as task_count,
                   (SELECT COUNT(*) FROM tasks kc WHERE kc.team_id = t.id AND kc.status = 'completed') as completed_task_count
            FROM teams t
            JOIN users u ON t.leader_id = u.id
            LEFT JOIN users cb ON t.created_by = cb.id
            WHERE t.leader_id = ? OR t.created_by = ?
            ORDER BY t.name ASC
        ");
        $stmt->execute([$managerId, $managerId]);
        return $stmt ? $stmt->fetchAll() : [];
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * Get single team by ID
 */
function getTeamById(int $teamId): ?array {
    ensureTeamsTablesExist();
    try {
        $db = getDB();
        $stmt = $db->prepare("
            SELECT t.*, u.name as leader_name, u.role as leader_role, u.email as leader_email
            FROM teams t
            JOIN users u ON t.leader_id = u.id
            WHERE t.id = ?
        ");
        $stmt->execute([$teamId]);
        $team = $stmt->fetch();
        return $team ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

/**
 * Get all members of a specific team
 */
function getTeamMembers(int $teamId): array {
    ensureTeamsTablesExist();
    try {
        $db = getDB();
        $stmt = $db->prepare("
            SELECT u.id, u.employee_id, u.name, u.email, u.role, u.designation, u.status, tm.role_in_team, tm.joined_at
            FROM team_members tm
            JOIN users u ON tm.user_id = u.id
            WHERE tm.team_id = ?
            ORDER BY u.name ASC
        ");
        $stmt->execute([$teamId]);
        return $stmt ? $stmt->fetchAll() : [];
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * Create a new team with designated leader and member array
 */
function createTeam(string $name, ?string $description, int $leaderId, int $createdBy, array $memberIds = []): int {
    ensureTeamsTablesExist();
    $db = getDB();
    
    $stmt = $db->prepare("INSERT INTO teams (name, description, leader_id, created_by, created_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt->execute([trim($name), $description ? trim($description) : null, $leaderId, $createdBy]);
    $teamId = (int)$db->lastInsertId();
    
    // Always include leader as a member if not already in array
    $allMembers = array_unique(array_filter(array_map('intval', array_merge([$leaderId], $memberIds))));
    
    if (!empty($allMembers)) {
        $mStmt = $db->prepare("INSERT IGNORE INTO team_members (team_id, user_id, role_in_team, joined_at) VALUES (?, ?, ?, NOW())");
        foreach ($allMembers as $mId) {
            $roleInTeam = ($mId === $leaderId) ? 'leader' : 'member';
            $mStmt->execute([$teamId, $mId, $roleInTeam]);
            
            // Send in-app notification to member
            if ($mId !== $createdBy) {
                createNotification(
                    $mId,
                    '👥 Added to Team: ' . $name,
                    'You have been added to the team "' . $name . '".',
                    BASE_URL . '/employee/tasks.php',
                    'info'
                );
            }
        }
    }
    
    return $teamId;
}

/**
 * Update an existing team and sync member roster
 */
function updateTeam(int $teamId, string $name, ?string $description, int $leaderId, array $memberIds = []): bool {
    ensureTeamsTablesExist();
    $db = getDB();
    
    $stmt = $db->prepare("UPDATE teams SET name = ?, description = ?, leader_id = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([trim($name), $description ? trim($description) : null, $leaderId, $teamId]);
    
    // Sync members
    $allMembers = array_unique(array_filter(array_map('intval', array_merge([$leaderId], $memberIds))));
    
    // Fetch existing member IDs
    $curStmt = $db->prepare("SELECT user_id FROM team_members WHERE team_id = ?");
    $curStmt->execute([$teamId]);
    $existingMembers = $curStmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Remove members no longer in list
    $toRemove = array_diff($existingMembers, $allMembers);
    if (!empty($toRemove)) {
        $inClause = implode(',', array_fill(0, count($toRemove), '?'));
        $delStmt = $db->prepare("DELETE FROM team_members WHERE team_id = ? AND user_id IN ($inClause)");
        $delStmt->execute(array_merge([$teamId], array_values($toRemove)));
    }
    
    // Add new members
    $toAdd = array_diff($allMembers, $existingMembers);
    if (!empty($toAdd)) {
        $insStmt = $db->prepare("INSERT IGNORE INTO team_members (team_id, user_id, role_in_team, joined_at) VALUES (?, ?, ?, NOW())");
        foreach ($toAdd as $mId) {
            $roleInTeam = ($mId === $leaderId) ? 'leader' : 'member';
            $insStmt->execute([$teamId, $mId, $roleInTeam]);
            
            createNotification(
                $mId,
                '👥 Added to Team: ' . $name,
                'You have been added to the team "' . $name . '".',
                BASE_URL . '/employee/tasks.php',
                'info'
            );
        }
    }
    
    // Update leader role tag
    $db->prepare("UPDATE team_members SET role_in_team = 'member' WHERE team_id = ?")->execute([$teamId]);
    $db->prepare("UPDATE team_members SET role_in_team = 'leader' WHERE team_id = ? AND user_id = ?")->execute([$teamId, $leaderId]);
    
    return true;
}

/**
 * Delete team
 */
function deleteTeam(int $teamId): bool {
    ensureTeamsTablesExist();
    $db = getDB();
    $stmt = $db->prepare("DELETE FROM teams WHERE id = ?");
    return $stmt->execute([$teamId]);
}

// =============================================
// Sales CRM & Lead Management System
// =============================================

/**
 * Ensure sales tables (course_services, leads, lead_activities) and sales_counsellor role exist
 */
function ensureSalesTablesExist(): void {
    static $salesProvisioned = false;
    if ($salesProvisioned) return;
    
    try {
        $db = getDB();
        
        // 1. Ensure sales_counsellor role in roles table
        try {
            $rStmt = $db->prepare("SELECT id FROM roles WHERE slug = 'sales_counsellor'");
            $rStmt->execute();
            if (!$rStmt->fetch()) {
                $db->prepare("INSERT INTO roles (name, slug, description, base_role, is_system) VALUES (?, ?, ?, ?, 1)")
                   ->execute(['Sales Counsellor', 'sales_counsellor', 'Dedicated sales counsellor for courses and services lead conversions.', 'sales_counsellor']);
            }
        } catch (Throwable $e) {
            // Ignore if roles table missing
        }

        // 2. Course & Services Catalogue
        $db->exec("
            CREATE TABLE IF NOT EXISTS `course_services` (
              `id` int NOT NULL AUTO_INCREMENT,
              `name` varchar(150) NOT NULL,
              `type` enum('course','service') NOT NULL DEFAULT 'course',
              `category` varchar(100) DEFAULT 'General',
              `price` decimal(10,2) NOT NULL DEFAULT '0.00',
              `duration` varchar(50) DEFAULT NULL,
              `description` text,
              `status` enum('active','inactive') NOT NULL DEFAULT 'active',
              `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
              `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              KEY `idx_type` (`type`),
              KEY `idx_status` (`status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        // Seed & Synchronize official 3 Courses & 3 Services
        $defaultOfferings = [
            ['UI/UX Design with Gen AI & Agentic AI', 'course', 'Design & AI', 40000.00, '3 Months', 'Master modern UI/UX design workflows powered by Generative AI & Agentic AI automation, Figma, Design Systems, and LLM-driven UX research.'],
            ['Graphic Design with AI', 'course', 'Design & AI', 30000.00, '2.5 Months', 'Next-generation graphic design, branding, visual identity, Midjourney, Photoshop AI, and AI-accelerated creative production.'],
            ['Web-Development with AI', 'course', 'Development & AI', 40000.00, '4 Months', 'Modern full-stack web development with Next.js, React, Backend APIs, Cloud deployment, and AI coding agents.'],
            ['UI UX Design', 'service', 'Design Solutions', 0.00, 'Project-based', 'Custom digital product design, UX audits, mobile & web UI design, design systems, and clickable prototypes.'],
            ['Development', 'service', 'Engineering & IT', 0.00, 'Project-based', 'End-to-end custom software, web platforms, mobile apps, SaaS applications, and enterprise system engineering.'],
            ['AI & Business Automation', 'service', 'AI & Automation', 0.00, 'Project-based', 'Custom AI agents, workflow automation, CRM integrations, chatbot solutions, and business process automation.']
        ];

        $officialNames = array_column($defaultOfferings, 0);
        foreach ($defaultOfferings as $off) {
            $chk = $db->prepare("SELECT id FROM `course_services` WHERE `name` = ? LIMIT 1");
            $chk->execute([$off[0]]);
            $existing = $chk->fetch();
            if ($existing) {
                $upd = $db->prepare("UPDATE `course_services` SET `type` = ?, `category` = ?, `price` = ?, `duration` = ?, `description` = ?, `status` = 'active' WHERE `id` = ?");
                $upd->execute([$off[1], $off[2], $off[3], $off[4], $off[5], $existing['id']]);
            } else {
                $ins = $db->prepare("INSERT INTO `course_services` (name, type, category, price, duration, description, status) VALUES (?, ?, ?, ?, ?, ?, 'active')");
                $ins->execute([$off[0], $off[1], $off[2], $off[3], $off[4], $off[5]]);
            }
        }

        // Deactivate any legacy offerings not in the official 6
        $placeholders = implode(',', array_fill(0, count($officialNames), '?'));
        $deactStmt = $db->prepare("UPDATE `course_services` SET `status` = 'inactive' WHERE `name` NOT IN ($placeholders)");
        $deactStmt->execute($officialNames);

        // Migrate legacy lead references to official names
        $db->exec("UPDATE `leads` SET `course_service_name` = 'UI/UX Design with Gen AI & Agentic AI', `expected_revenue` = 40000.00 WHERE `course_service_name` IN ('UI/UX Design Masterclass', 'Artificial Intelligence & Machine Learning')");
        $db->exec("UPDATE `leads` SET `course_service_name` = 'Graphic Design with AI', `expected_revenue` = 30000.00 WHERE `course_service_name` = 'Digital Marketing & Growth Hacking'");
        $db->exec("UPDATE `leads` SET `course_service_name` = 'Web-Development with AI', `expected_revenue` = 40000.00 WHERE `course_service_name` = 'Full Stack Web Development (MERN/PHP)'");
        $db->exec("UPDATE `leads` SET `course_service_name` = 'Development' WHERE `course_service_name` IN ('Custom Web & Portal Development', 'Mobile App Development (Android/iOS)', 'Cloud Migration & DevOps Setup')");
        $db->exec("UPDATE `leads` SET `course_service_name` = 'UI UX Design' WHERE `course_service_name` = 'Digital Branding & SEO Package'");

        // 3. Leads Table
        $db->exec("
            CREATE TABLE IF NOT EXISTS `leads` (
              `id` int NOT NULL AUTO_INCREMENT,
              `name` varchar(150) NOT NULL,
              `email` varchar(150) DEFAULT NULL,
              `graduation` varchar(150) DEFAULT NULL,
              `phone` varchar(30) NOT NULL,
              `alternate_phone` varchar(30) DEFAULT NULL,
              `city` varchar(100) DEFAULT NULL,
              `offering_type` enum('course','service') NOT NULL DEFAULT 'course',
              `course_service_id` int DEFAULT NULL,
              `course_service_name` varchar(150) NOT NULL DEFAULT 'General Inquiry',
              `source` varchar(100) DEFAULT 'Raw Calling Data',
              `status` enum('new_lead','interested','enrolled','uninterested') NOT NULL DEFAULT 'new_lead',
              `expected_revenue` decimal(10,2) NOT NULL DEFAULT '0.00',
              `assigned_to` int DEFAULT NULL,
              `assigned_by` int DEFAULT NULL,
              `next_followup_date` datetime DEFAULT NULL,
              `notes` text,
              `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
              `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              KEY `idx_assigned_to` (`assigned_to`),
              KEY `idx_status` (`status`),
              KEY `idx_offering_type` (`offering_type`),
              KEY `idx_next_followup` (`next_followup_date`),
              KEY `idx_phone` (`phone`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        // Ensure graduation column exists if table was previously created
        try {
            $colCheck = $db->query("SHOW COLUMNS FROM `leads` LIKE 'graduation'");
            if ($colCheck && !$colCheck->fetch()) {
                $db->exec("ALTER TABLE `leads` ADD COLUMN `graduation` varchar(150) DEFAULT NULL AFTER `email`");
            }

            // Check if payment columns exist in leads
            $colPayPlan = $db->query("SHOW COLUMNS FROM `leads` LIKE 'payment_plan'");
            if ($colPayPlan && !$colPayPlan->fetch()) {
                $db->exec("ALTER TABLE `leads` ADD COLUMN `payment_plan` enum('none','one_time','installment') NOT NULL DEFAULT 'none' AFTER `expected_revenue`");
            }
            $colTotInst = $db->query("SHOW COLUMNS FROM `leads` LIKE 'total_installments'");
            if ($colTotInst && !$colTotInst->fetch()) {
                $db->exec("ALTER TABLE `leads` ADD COLUMN `total_installments` tinyint NOT NULL DEFAULT 1 AFTER `payment_plan`");
            }
            $colPaidAmt = $db->query("SHOW COLUMNS FROM `leads` LIKE 'paid_amount'");
            if ($colPaidAmt && !$colPaidAmt->fetch()) {
                $db->exec("ALTER TABLE `leads` ADD COLUMN `paid_amount` decimal(10,2) NOT NULL DEFAULT '0.00' AFTER `total_installments`");
            }
            $colPayStat = $db->query("SHOW COLUMNS FROM `leads` LIKE 'payment_status'");
            if ($colPayStat && !$colPayStat->fetch()) {
                $db->exec("ALTER TABLE `leads` ADD COLUMN `payment_status` enum('unpaid','partially_paid','paid') NOT NULL DEFAULT 'unpaid' AFTER `paid_amount`");
            }
        } catch (Throwable $e) {}

        // 4. Lead Activities / Interaction History
        $db->exec("
            CREATE TABLE IF NOT EXISTS `lead_activities` (
              `id` int NOT NULL AUTO_INCREMENT,
              `lead_id` int NOT NULL,
              `user_id` int NOT NULL,
              `activity_type` enum('call','whatsapp','email','meeting','status_change','note') NOT NULL DEFAULT 'note',
              `status_from` varchar(50) DEFAULT NULL,
              `status_to` varchar(50) DEFAULT NULL,
              `remarks` text NOT NULL,
              `next_followup` datetime DEFAULT NULL,
              `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              KEY `idx_lead_id` (`lead_id`),
              KEY `idx_user_id` (`user_id`),
              CONSTRAINT `fk_lead_act_lead` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        // 5. Lead Payments / Installments Table
        $db->exec("
            CREATE TABLE IF NOT EXISTS `lead_payments` (
              `id` int NOT NULL AUTO_INCREMENT,
              `lead_id` int NOT NULL,
              `installment_no` tinyint NOT NULL DEFAULT 1,
              `title` varchar(100) NOT NULL,
              `amount` decimal(10,2) NOT NULL DEFAULT '0.00',
              `due_date` date DEFAULT NULL,
              `paid_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
              `paid_at` datetime DEFAULT NULL,
              `payment_method` varchar(50) DEFAULT NULL,
              `transaction_id` varchar(100) DEFAULT NULL,
              `status` enum('pending','paid','partially_paid','overdue') NOT NULL DEFAULT 'pending',
              `recorded_by` int DEFAULT NULL,
              `notes` text,
              `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
              `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              KEY `idx_lead_id` (`lead_id`),
              KEY `idx_status` (`status`),
              KEY `idx_due_date` (`due_date`),
              CONSTRAINT `fk_lead_pay_lead` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        // 6. Sales Targets Table
        $db->exec("
            CREATE TABLE IF NOT EXISTS `sales_targets` (
              `id` int NOT NULL AUTO_INCREMENT,
              `user_id` int NOT NULL,
              `target_month` varchar(7) NOT NULL,
              `target_revenue` decimal(12,2) NOT NULL DEFAULT 0.00,
              `target_enrollments` int NOT NULL DEFAULT 0,
              `target_leads` int NOT NULL DEFAULT 0,
              `notes` text DEFAULT NULL,
              `set_by` int DEFAULT NULL,
              `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
              `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              UNIQUE KEY `idx_user_month` (`user_id`, `target_month`),
              KEY `idx_month` (`target_month`),
              KEY `idx_set_by` (`set_by`),
              CONSTRAINT `fk_sales_target_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        // 7. Monthly Commission Refresh Logs Table (Founder Audit & Refresh)
        $db->exec("
            CREATE TABLE IF NOT EXISTS `commission_refresh_logs` (
              `id` int NOT NULL AUTO_INCREMENT,
              `target_month` varchar(7) NOT NULL,
              `refreshed_by` int DEFAULT NULL,
              `total_counsellors` int NOT NULL DEFAULT 0,
              `total_admissions` int NOT NULL DEFAULT 0,
              `total_commission` decimal(12,2) NOT NULL DEFAULT 0.00,
              `total_collected_revenue` decimal(12,2) NOT NULL DEFAULT 0.00,
              `notes` varchar(255) DEFAULT NULL,
              `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              KEY `idx_refresh_month` (`target_month`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        // Ensure enrolled_at column exists in leads
        try {
            $colEnrAt = $db->query("SHOW COLUMNS FROM `leads` LIKE 'enrolled_at'");
            if ($colEnrAt && !$colEnrAt->fetch()) {
                $db->exec("ALTER TABLE `leads` ADD COLUMN `enrolled_at` datetime DEFAULT NULL AFTER `status`");
                $db->exec("UPDATE `leads` SET `enrolled_at` = COALESCE(`updated_at`, `created_at`) WHERE `status` = 'enrolled' AND `enrolled_at` IS NULL");
            }
        } catch (Throwable $e) {}

        $salesProvisioned = true;
    } catch (Throwable $e) {
        error_log("Sales table provisioning error: " . $e->getMessage());
    }
}

/**
 * Get active Courses and Services catalogue
 */
function getCourseServicesList(?string $type = null): array {
    ensureSalesTablesExist();
    try {
        $db = getDB();
        if ($type) {
            $stmt = $db->prepare("SELECT * FROM course_services WHERE type = ? AND status = 'active' ORDER BY name ASC");
            $stmt->execute([$type]);
        } else {
            $stmt = $db->query("SELECT * FROM course_services WHERE status = 'active' ORDER BY type ASC, name ASC");
        }
        return $stmt ? $stmt->fetchAll() : [];
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * Get list of sales counsellors (users with role 'sales_counsellor' or active staff)
 */
function getSalesCounsellorsList(): array {
    try {
        $db = getDB();
        $stmt = $db->query("
            SELECT id, employee_id, name, email, role, designation, status 
            FROM users 
            WHERE role IN ('sales_counsellor', 'employee', 'manager', 'hr', 'founder') 
              AND status = 'active' 
            ORDER BY (role = 'sales_counsellor') DESC, (role = 'manager') DESC, name ASC
        ");
        return $stmt ? $stmt->fetchAll() : [];
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * Save or Update Monthly Sales Target for a Counsellor
 */
function saveSalesTarget(int $userId, string $month, float $revenue, int $enrollments, int $leads = 0, ?string $notes = null, ?int $setBy = null): bool {
    ensureSalesTablesExist();
    try {
        $db = getDB();
        $stmt = $db->prepare("
            INSERT INTO sales_targets (user_id, target_month, target_revenue, target_enrollments, target_leads, notes, set_by)
            VALUES (?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                target_revenue = VALUES(target_revenue),
                target_enrollments = VALUES(target_enrollments),
                target_leads = VALUES(target_leads),
                notes = VALUES(notes),
                set_by = VALUES(set_by),
                updated_at = CURRENT_TIMESTAMP
        ");
        return $stmt->execute([$userId, $month, $revenue, $enrollments, $leads, $notes ?: null, $setBy]);
    } catch (Throwable $e) {
        error_log("saveSalesTarget error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get Monthly Sales Target and Detailed Progress Metrics for a Counsellor
 */
function getMonthlySalesTargetAndProgress(int $userId, ?string $month = null): array {
    ensureSalesTablesExist();
    $db = getDB();
    if (!$month || !preg_match('/^\d{4}-\d{2}$/', $month)) {
        $month = date('Y-m');
    }

    $monthTime = strtotime($month . '-01');
    $totalDays = (int)date('t', $monthTime);
    $isCurrentMonth = ($month === date('Y-m'));
    $currentDay = $isCurrentMonth ? (int)date('j') : $totalDays;
    $daysRemaining = $isCurrentMonth ? max(0, $totalDays - $currentDay) : 0;
    $monthLabel = date('F Y', $monthTime);

    $result = [
        'has_target' => false,
        'target_id' => null,
        'user_id' => $userId,
        'month' => $month,
        'month_label' => $monthLabel,
        'target_revenue' => 0.0,
        'target_enrollments' => 0,
        'target_leads' => 0,
        'notes' => '',
        'set_by_name' => '',
        'set_by_role' => '',
        'updated_at' => null,
        'achieved_revenue' => 0.0,
        'collected_revenue' => 0.0,
        'achieved_enrollments' => 0,
        'achieved_leads' => 0,
        'revenue_pct' => 0.0,
        'enrollments_pct' => 0.0,
        'leads_pct' => 0.0,
        'revenue_gap' => 0.0,
        'enrollments_gap' => 0,
        'total_days_in_month' => $totalDays,
        'current_day' => $currentDay,
        'days_remaining' => $daysRemaining,
        'is_target_met' => false,
        'status_badge' => 'No Target'
    ];

    try {
        // Fetch target record
        $targetStmt = $db->prepare("
            SELECT st.*, u.name as set_by_name, u.role as set_by_role 
            FROM sales_targets st 
            LEFT JOIN users u ON u.id = st.set_by 
            WHERE st.user_id = ? AND st.target_month = ? 
            LIMIT 1
        ");
        $targetStmt->execute([$userId, $month]);
        $target = $targetStmt->fetch();

        if ($target) {
            $result['has_target'] = true;
            $result['target_id'] = (int)$target['id'];
            $result['target_revenue'] = (float)$target['target_revenue'];
            $result['target_enrollments'] = (int)$target['target_enrollments'];
            $result['target_leads'] = (int)$target['target_leads'];
            $result['notes'] = $target['notes'] ?? '';
            $result['set_by_name'] = $target['set_by_name'] ?? '';
            $result['set_by_role'] = $target['set_by_role'] ?? '';
            $result['updated_at'] = $target['updated_at'] ?? $target['created_at'];
        }

        // 1. Enrolled admissions count in this month (and booked deal value for reference)
        $enrStmt = $db->prepare("
            SELECT 
                COUNT(*) as enrolled_count,
                COALESCE(SUM(expected_revenue), 0) as booked_deal_value
            FROM leads 
            WHERE assigned_to = ? 
              AND status = 'enrolled' 
              AND (DATE_FORMAT(updated_at, '%Y-%m') = ? OR DATE_FORMAT(created_at, '%Y-%m') = ?)
        ");
        $enrStmt->execute([$userId, $month, $month]);
        $enrData = $enrStmt->fetch();
        if ($enrData) {
            $result['achieved_enrollments'] = (int)$enrData['enrolled_count'];
            $result['booked_deal_value'] = (float)$enrData['booked_deal_value'];
        }

        // 2. ACTUAL COLLECTED REVENUE IN THIS MONTH:
        // A) Actual installment collections paid in this specific month
        $instStmt = $db->prepare("
            SELECT COALESCE(SUM(lp.paid_amount), 0) as total_inst_collected
            FROM lead_payments lp
            JOIN leads l ON l.id = lp.lead_id
            WHERE l.assigned_to = ?
              AND lp.paid_at IS NOT NULL
              AND lp.paid_amount > 0
              AND DATE_FORMAT(lp.paid_at, '%Y-%m') = ?
        ");
        $instStmt->execute([$userId, $month]);
        $installmentCollected = (float)($instStmt->fetchColumn() ?: 0.0);

        // B) Direct / One-time payments for enrolled leads not having records in lead_payments
        $directStmt = $db->prepare("
            SELECT COALESCE(SUM(l.paid_amount), 0) as total_direct_collected
            FROM leads l
            WHERE l.assigned_to = ?
              AND l.status = 'enrolled'
              AND l.paid_amount > 0
              AND l.id NOT IN (SELECT DISTINCT lead_id FROM lead_payments)
              AND (DATE_FORMAT(l.updated_at, '%Y-%m') = ? OR DATE_FORMAT(l.created_at, '%Y-%m') = ?)
        ");
        $directStmt->execute([$userId, $month, $month]);
        $directCollected = (float)($directStmt->fetchColumn() ?: 0.0);

        $totalMonthCollected = $installmentCollected + $directCollected;
        
        // Count toward revenue target calculation strictly based on month's actual collections
        $result['collected_revenue'] = $totalMonthCollected;
        $result['achieved_revenue'] = $totalMonthCollected;

        // 3. Leads contacted / processed this month
        $actStmt = $db->prepare("
            SELECT COUNT(DISTINCT lead_id)
            FROM lead_activities
            WHERE user_id = ?
              AND DATE_FORMAT(created_at, '%Y-%m') = ?
        ");
        $actStmt->execute([$userId, $month]);
        $leadsContacted = (int)($actStmt->fetchColumn() ?: 0);

        // 4. Leads assigned in this month
        $assStmt = $db->prepare("
            SELECT COUNT(*) 
            FROM leads 
            WHERE assigned_to = ? 
              AND (DATE_FORMAT(created_at, '%Y-%m') = ? OR DATE_FORMAT(updated_at, '%Y-%m') = ?)
        ");
        $assStmt->execute([$userId, $month, $month]);
        $leadsAssigned = (int)($assStmt->fetchColumn() ?: 0);

        $result['achieved_leads'] = max($leadsContacted, $leadsAssigned);

        // Commission Calculations (₹5,000 per admission)
        $commissionRate = defined('COMMISSION_PER_ADMISSION') ? (float)COMMISSION_PER_ADMISSION : 5000.00;
        $result['commission_rate'] = $commissionRate;
        $result['earned_commission'] = $result['achieved_enrollments'] * $commissionRate;
        $result['target_potential_commission'] = $result['target_enrollments'] * $commissionRate;

        // Lifetime Admissions & Commission for this Counsellor
        $lifeStmt = $db->prepare("SELECT COUNT(*) FROM leads WHERE assigned_to = ? AND status = 'enrolled'");
        $lifeStmt->execute([$userId]);
        $result['lifetime_admissions'] = (int)($lifeStmt->fetchColumn() ?: 0);
        $result['lifetime_commission'] = $result['lifetime_admissions'] * $commissionRate;

        // Progress Calculations
        if ($result['target_revenue'] > 0) {
            $result['revenue_pct'] = round(($result['achieved_revenue'] / $result['target_revenue']) * 100, 1);
            $result['revenue_gap'] = max(0, $result['target_revenue'] - $result['achieved_revenue']);
        }
        if ($result['target_enrollments'] > 0) {
            $result['enrollments_pct'] = round(($result['achieved_enrollments'] / $result['target_enrollments']) * 100, 1);
            $result['enrollments_gap'] = max(0, $result['target_enrollments'] - $result['achieved_enrollments']);
        }
        if ($result['target_leads'] > 0) {
            $result['leads_pct'] = round(($result['achieved_leads'] / $result['target_leads']) * 100, 1);
        }

        // Target Status Assessment
        if ($result['has_target']) {
            if (($result['target_revenue'] > 0 && $result['achieved_revenue'] >= $result['target_revenue']) || 
                ($result['target_enrollments'] > 0 && $result['achieved_enrollments'] >= $result['target_enrollments'])) {
                $result['is_target_met'] = true;
                $result['status_badge'] = 'Achieved 🎉';
            } elseif ($result['revenue_pct'] >= 75 || $result['enrollments_pct'] >= 75) {
                $result['status_badge'] = 'Almost There 🚀';
            } elseif ($result['revenue_pct'] >= 40 || $result['enrollments_pct'] >= 40) {
                $result['status_badge'] = 'In Progress ⚡';
            } else {
                $result['status_badge'] = 'Needs Focus 🎯';
            }
        }

    } catch (Throwable $e) {
        error_log("getMonthlySalesTargetAndProgress error: " . $e->getMessage());
    }

    return $result;
}

/**
 * Perform Founder Refresh of Monthly Commission and Performance Metrics
 */
function refreshMonthlyCommissions(string $month, ?int $refreshedBy = null): array {
    ensureSalesTablesExist();
    $db = getDB();
    if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
        $month = date('Y-m');
    }

    $counsellors = getSalesCounsellorsList();
    $totalCounsellors = count($counsellors);
    $totalAdmissions = 0;
    $totalCommission = 0.0;
    $totalCollectedRevenue = 0.0;
    $targetsCount = 0;
    $details = [];

    foreach ($counsellors as $c) {
        $prog = getMonthlySalesTargetAndProgress((int)$c['id'], $month);
        $totalAdmissions += $prog['achieved_enrollments'];
        $totalCommission += $prog['earned_commission'];
        $totalCollectedRevenue += $prog['achieved_revenue'];
        if ($prog['has_target']) {
            $targetsCount++;
        }
        $details[] = [
            'user_id' => $c['id'],
            'name' => $c['name'],
            'admissions' => $prog['achieved_enrollments'],
            'commission' => $prog['earned_commission'],
            'target_set' => $prog['has_target'],
            'target_revenue' => $prog['target_revenue']
        ];
    }

    // Record refresh log
    try {
        $logStmt = $db->prepare("
            INSERT INTO commission_refresh_logs 
            (target_month, refreshed_by, total_counsellors, total_admissions, total_commission, total_collected_revenue, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $logStmt->execute([
            $month,
            $refreshedBy,
            $totalCounsellors,
            $totalAdmissions,
            $totalCommission,
            $totalCollectedRevenue,
            "Refreshed for {$month}: {$totalAdmissions} admissions, ₹" . number_format($totalCommission) . " commission."
        ]);
    } catch (Throwable $e) {}

    return [
        'month' => $month,
        'month_label' => date('F Y', strtotime($month . '-01')),
        'total_counsellors' => $totalCounsellors,
        'targets_set_count' => $targetsCount,
        'total_admissions' => $totalAdmissions,
        'total_commission' => $totalCommission,
        'total_collected_revenue' => $totalCollectedRevenue,
        'refreshed_at' => date('Y-m-d H:i:s'),
        'details' => $details
    ];
}

/**
 * Get Last Commission Refresh Log for a given month
 */
function getLastCommissionRefresh(string $month): ?array {
    ensureSalesTablesExist();
    try {
        $db = getDB();
        $stmt = $db->prepare("
            SELECT crl.*, u.name as refreshed_by_name 
            FROM commission_refresh_logs crl
            LEFT JOIN users u ON u.id = crl.refreshed_by
            WHERE crl.target_month = ?
            ORDER BY crl.id DESC
            LIMIT 1
        ");
        $stmt->execute([$month]);
        $row = $stmt->fetch();
        return $row ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

/**
 * Get Monthly Targets and Progress for All Counsellors (for Founder / Manager monitoring)
 */
function getAllCounsellorsMonthlyTargets(?string $month = null): array {
    ensureSalesTablesExist();
    if (!$month) $month = date('Y-m');
    $counsellors = getSalesCounsellorsList();
    $list = [];
    foreach ($counsellors as $c) {
        $cId = (int)$c['id'];
        $progress = getMonthlySalesTargetAndProgress($cId, $month);
        $progress['counsellor_name'] = $c['name'];
        $progress['counsellor_email'] = $c['email'];
        $progress['counsellor_role'] = $c['role'];
        $progress['designation'] = $c['designation'] ?? '';
        $list[] = $progress;
    }
    return $list;
}

/**
 * Calculate Sales & Conversion Metrics
 */
function getSalesMetrics(?int $counsellorId = null): array {
    ensureSalesTablesExist();
    $metrics = [
        'total_leads' => 0,
        'new_leads' => 0,
        'interested' => 0,
        'enrolled' => 0,
        'uninterested' => 0,
        'course_leads' => 0,
        'service_leads' => 0,
        'manual_leads' => 0,
        'manual_new' => 0,
        'manual_interested' => 0,
        'manual_enrolled' => 0,
        'total_enrolled_value' => 0.0,
        'conversion_rate' => 0.0,
        'today_followups_count' => 0,
        'overdue_followups_count' => 0
    ];

    try {
        $db = getDB();
        $where = $counsellorId ? " WHERE assigned_to = " . (int)$counsellorId : "";
        $whereAnd = $counsellorId ? " AND assigned_to = " . (int)$counsellorId : "";

        // Status counts & totals
        $stmt = $db->query("
            SELECT 
                COUNT(*) as total_leads,
                SUM(CASE WHEN assigned_to IS NULL OR assigned_to = 0 THEN 1 ELSE 0 END) as unassigned_leads,
                SUM(CASE WHEN status = 'new_lead' THEN 1 ELSE 0 END) as new_leads,
                SUM(CASE WHEN status = 'interested' THEN 1 ELSE 0 END) as interested,
                SUM(CASE WHEN status = 'enrolled' THEN 1 ELSE 0 END) as enrolled,
                SUM(CASE WHEN status = 'uninterested' THEN 1 ELSE 0 END) as uninterested,
                SUM(CASE WHEN offering_type = 'course' AND (is_manual = 0 OR is_manual IS NULL) THEN 1 ELSE 0 END) as course_leads,
                SUM(CASE WHEN offering_type = 'service' AND (is_manual = 0 OR is_manual IS NULL) THEN 1 ELSE 0 END) as service_leads,
                SUM(CASE WHEN is_manual = 1 OR source IN ('Manual Entry', 'Raw Calling Data', 'Raw Calling List', 'College Database', 'Cold Outreach', 'Cold Calling', 'Manual Import') THEN 1 ELSE 0 END) as manual_leads,
                SUM(CASE WHEN (is_manual = 1 OR source IN ('Manual Entry', 'Raw Calling Data', 'Raw Calling List', 'College Database', 'Cold Outreach', 'Cold Calling', 'Manual Import')) AND status = 'new_lead' THEN 1 ELSE 0 END) as manual_new,
                SUM(CASE WHEN (is_manual = 1 OR source IN ('Manual Entry', 'Raw Calling Data', 'Raw Calling List', 'College Database', 'Cold Outreach', 'Cold Calling', 'Manual Import')) AND status = 'interested' THEN 1 ELSE 0 END) as manual_interested,
                SUM(CASE WHEN (is_manual = 1 OR source IN ('Manual Entry', 'Raw Calling Data', 'Raw Calling List', 'College Database', 'Cold Outreach', 'Cold Calling', 'Manual Import')) AND status = 'enrolled' THEN 1 ELSE 0 END) as manual_enrolled,
                SUM(CASE WHEN status = 'enrolled' THEN expected_revenue ELSE 0 END) as total_enrolled_value
            FROM leads
            $where
        ");
        $row = $stmt ? $stmt->fetch() : null;
        if ($row) {
            $metrics['total_leads'] = (int)($row['total_leads'] ?? 0);
            $metrics['unassigned_leads'] = (int)($row['unassigned_leads'] ?? 0);
            $metrics['new_leads'] = (int)($row['new_leads'] ?? 0);
            $metrics['interested'] = (int)($row['interested'] ?? 0);
            $metrics['enrolled'] = (int)($row['enrolled'] ?? 0);
            $metrics['uninterested'] = (int)($row['uninterested'] ?? 0);
            $metrics['course_leads'] = (int)($row['course_leads'] ?? 0);
            $metrics['service_leads'] = (int)($row['service_leads'] ?? 0);
            $metrics['manual_leads'] = (int)($row['manual_leads'] ?? 0);
            $metrics['manual_new'] = (int)($row['manual_new'] ?? 0);
            $metrics['manual_interested'] = (int)($row['manual_interested'] ?? 0);
            $metrics['manual_enrolled'] = (int)($row['manual_enrolled'] ?? 0);
            $metrics['total_enrolled_value'] = (float)($row['total_enrolled_value'] ?? 0.0);
            
            if ($metrics['total_leads'] > 0) {
                $metrics['conversion_rate'] = round(($metrics['enrolled'] / $metrics['total_leads']) * 100, 1);
            }
        }

        // Today's Followups
        $fStmt = $db->query("
            SELECT COUNT(*) 
            FROM leads 
            WHERE DATE(next_followup_date) = CURDATE() 
              AND status IN ('new_lead', 'interested')
              $whereAnd
        ");
        $metrics['today_followups_count'] = (int)($fStmt ? $fStmt->fetchColumn() : 0);

        // Overdue Followups
        $oStmt = $db->query("
            SELECT COUNT(*) 
            FROM leads 
            WHERE next_followup_date < NOW() 
              AND status IN ('new_lead', 'interested')
              $whereAnd
        ");
        $metrics['overdue_followups_count'] = (int)($oStmt ? $oStmt->fetchColumn() : 0);

    } catch (Throwable $e) {
        error_log("getSalesMetrics error: " . $e->getMessage());
    }

    return $metrics;
}

/**
 * Get Comprehensive Course & Service Revenue Analytics
 * 
 * Provides detailed financial modeling metrics, course-wise breakdowns,
 * counsellor performance leaderboard, and real-time admissions ledger.
 */
function getCourseRevenueAnalytics(): array {
    ensureSalesTablesExist();
    $db = getDB();

    $analytics = [
        'summary' => [
            'total_course_realized_revenue' => 0.0,
            'total_course_booked_revenue' => 0.0,
            'total_course_pending_balance' => 0.0,
            'total_course_pipeline_revenue' => 0.0,
            'total_course_admissions' => 0,
            'total_course_inquiries' => 0,
            'avg_course_ticket' => 0.0,
            'course_conversion_rate' => 0.0,
            'total_services_realized_revenue' => 0.0,
            'total_services_booked_revenue' => 0.0,
            'total_services_pending_balance' => 0.0,
            'total_services_pipeline_revenue' => 0.0,
            'total_services_deals' => 0,
            'total_services_inquiries' => 0,
            'grand_realized_revenue' => 0.0,
            'grand_booked_revenue' => 0.0,
            'grand_pending_balance' => 0.0,
            'grand_pipeline_revenue' => 0.0,
            'this_month_admissions' => 0,
            'this_month_realized_revenue' => 0.0,
            'this_month_booked_revenue' => 0.0,
            'this_quarter_admissions' => 0,
            'this_quarter_realized_revenue' => 0.0,
            'this_quarter_booked_revenue' => 0.0,
            'this_year_admissions' => 0,
            'this_year_realized_revenue' => 0.0,
            'this_year_booked_revenue' => 0.0,
        ],
        'courses' => [],
        'services' => [],
        'counsellors' => [],
        'recent_admissions' => []
    ];

    try {
        // 1. Fetch Courses
        $coursesStmt = $db->query("SELECT * FROM course_services WHERE type = 'course' AND status = 'active' ORDER BY price DESC, name ASC");
        $courses = $coursesStmt ? $coursesStmt->fetchAll() : [];

        foreach ($courses as $c) {
            $cId = (int)$c['id'];
            $cName = $c['name'];
            $cPrice = (float)$c['price'];

            // Query lead stats for this course
            $lStmt = $db->prepare("
                SELECT 
                    COUNT(*) as total_inquiries,
                    SUM(CASE WHEN status = 'new_lead' THEN 1 ELSE 0 END) as new_count,
                    SUM(CASE WHEN status = 'interested' THEN 1 ELSE 0 END) as interested_count,
                    SUM(CASE WHEN status = 'enrolled' THEN 1 ELSE 0 END) as enrolled_count,
                    SUM(CASE WHEN status = 'uninterested' THEN 1 ELSE 0 END) as uninterested_count,
                    SUM(CASE WHEN status = 'enrolled' THEN (CASE WHEN paid_amount > 0 THEN paid_amount WHEN expected_revenue > 0 THEN expected_revenue ELSE ? END) ELSE 0 END) as collected_revenue,
                    SUM(CASE WHEN status = 'enrolled' THEN (CASE WHEN expected_revenue > 0 THEN expected_revenue ELSE ? END) ELSE 0 END) as booked_revenue,
                    SUM(CASE WHEN status = 'interested' THEN (CASE WHEN expected_revenue > 0 THEN expected_revenue ELSE ? END) ELSE 0 END) as pipeline_revenue
                FROM leads
                WHERE offering_type = 'course' 
                  AND (course_service_id = ? OR course_service_name = ?)
            ");
            $lStmt->execute([$cPrice, $cPrice, $cPrice, $cId, $cName]);
            $stats = $lStmt->fetch();

            $totalInq = (int)($stats['total_inquiries'] ?? 0);
            $enrolled = (int)($stats['enrolled_count'] ?? 0);
            $interested = (int)($stats['interested_count'] ?? 0);
            $newLead = (int)($stats['new_count'] ?? 0);
            $uninterested = (int)($stats['uninterested_count'] ?? 0);
            $collectedRev = (float)($stats['collected_revenue'] ?? 0.0);
            $bookedRev = (float)($stats['booked_revenue'] ?? 0.0);
            $pipelineRev = (float)($stats['pipeline_revenue'] ?? 0.0);

            // Fallback if no specific expected_revenue entered
            if ($bookedRev == 0 && $enrolled > 0) {
                $bookedRev = $enrolled * $cPrice;
            }
            if ($collectedRev == 0 && $enrolled > 0) {
                $collectedRev = $bookedRev;
            }
            if ($pipelineRev == 0 && $interested > 0) {
                $pipelineRev = $interested * $cPrice;
            }

            $pendingBalance = max(0.0, $bookedRev - $collectedRev);
            $convRate = $totalInq > 0 ? round(($enrolled / $totalInq) * 100, 1) : 0.0;

            $courseData = [
                'id' => $cId,
                'name' => $cName,
                'category' => $c['category'] ?? 'Design & AI',
                'price' => $cPrice,
                'duration' => $c['duration'] ?? '3 Months',
                'description' => $c['description'] ?? '',
                'total_inquiries' => $totalInq,
                'new_count' => $newLead,
                'interested_count' => $interested,
                'enrolled_count' => $enrolled,
                'uninterested_count' => $uninterested,
                'realized_revenue' => $collectedRev,
                'collected_revenue' => $collectedRev,
                'booked_revenue' => $bookedRev,
                'pending_balance' => $pendingBalance,
                'pipeline_revenue' => $pipelineRev,
                'conversion_rate' => $convRate
            ];

            $analytics['courses'][] = $courseData;
            $analytics['summary']['total_course_realized_revenue'] += $collectedRev;
            $analytics['summary']['total_course_booked_revenue'] += $bookedRev;
            $analytics['summary']['total_course_pending_balance'] += $pendingBalance;
            $analytics['summary']['total_course_pipeline_revenue'] += $pipelineRev;
            $analytics['summary']['total_course_admissions'] += $enrolled;
            $analytics['summary']['total_course_inquiries'] += $totalInq;
        }

        if ($analytics['summary']['total_course_admissions'] > 0) {
            $analytics['summary']['avg_course_ticket'] = round(
                $analytics['summary']['total_course_booked_revenue'] / $analytics['summary']['total_course_admissions'],
                2
            );
        } else {
            // Default weighted benchmark ticket
            $analytics['summary']['avg_course_ticket'] = 36667.0;
        }

        if ($analytics['summary']['total_course_inquiries'] > 0) {
            $analytics['summary']['course_conversion_rate'] = round(
                ($analytics['summary']['total_course_admissions'] / $analytics['summary']['total_course_inquiries']) * 100,
                1
            );
        }

        // 2. Fetch Services
        $servicesStmt = $db->query("SELECT * FROM course_services WHERE type = 'service' AND status = 'active' ORDER BY name ASC");
        $services = $servicesStmt ? $servicesStmt->fetchAll() : [];

        foreach ($services as $s) {
            $sId = (int)$s['id'];
            $sName = $s['name'];

            $sLeadsStmt = $db->prepare("
                SELECT 
                    COUNT(*) as total_inquiries,
                    SUM(CASE WHEN status = 'new_lead' THEN 1 ELSE 0 END) as new_count,
                    SUM(CASE WHEN status = 'interested' THEN 1 ELSE 0 END) as interested_count,
                    SUM(CASE WHEN status = 'enrolled' THEN 1 ELSE 0 END) as won_count,
                    SUM(CASE WHEN status = 'enrolled' THEN (CASE WHEN paid_amount > 0 THEN paid_amount ELSE expected_revenue END) ELSE 0 END) as collected_revenue,
                    SUM(CASE WHEN status = 'enrolled' THEN expected_revenue ELSE 0 END) as booked_revenue,
                    SUM(CASE WHEN status = 'interested' THEN expected_revenue ELSE 0 END) as pipeline_revenue
                FROM leads
                WHERE offering_type = 'service' 
                  AND (course_service_id = ? OR course_service_name = ?)
            ");
            $sLeadsStmt->execute([$sId, $sName]);
            $sStats = $sLeadsStmt->fetch();

            $sInq = (int)($sStats['total_inquiries'] ?? 0);
            $sWon = (int)($sStats['won_count'] ?? 0);
            $sInt = (int)($sStats['interested_count'] ?? 0);
            $sNew = (int)($sStats['new_count'] ?? 0);
            $sCollected = (float)($sStats['collected_revenue'] ?? 0.0);
            $sBooked = (float)($sStats['booked_revenue'] ?? 0.0);
            $sPipeline = (float)($sStats['pipeline_revenue'] ?? 0.0);
            $sPending = max(0.0, $sBooked - $sCollected);

            $serviceData = [
                'id' => $sId,
                'name' => $sName,
                'category' => $s['category'] ?? 'Custom Solutions',
                'duration' => $s['duration'] ?? 'Project-based',
                'description' => $s['description'] ?? '',
                'total_inquiries' => $sInq,
                'new_count' => $sNew,
                'interested_count' => $sInt,
                'won_count' => $sWon,
                'realized_revenue' => $sCollected,
                'collected_revenue' => $sCollected,
                'booked_revenue' => $sBooked,
                'pending_balance' => $sPending,
                'pipeline_revenue' => $sPipeline
            ];

            $analytics['services'][] = $serviceData;
            $analytics['summary']['total_services_realized_revenue'] += $sCollected;
            $analytics['summary']['total_services_booked_revenue'] += $sBooked;
            $analytics['summary']['total_services_pending_balance'] += $sPending;
            $analytics['summary']['total_services_pipeline_revenue'] += $sPipeline;
            $analytics['summary']['total_services_deals'] += $sWon;
            $analytics['summary']['total_services_inquiries'] += $sInq;
        }

        // Grand Totals
        $analytics['summary']['grand_realized_revenue'] = $analytics['summary']['total_course_realized_revenue'] + $analytics['summary']['total_services_realized_revenue'];
        $analytics['summary']['grand_booked_revenue'] = $analytics['summary']['total_course_booked_revenue'] + $analytics['summary']['total_services_booked_revenue'];
        $analytics['summary']['grand_pending_balance'] = $analytics['summary']['total_course_pending_balance'] + $analytics['summary']['total_services_pending_balance'];
        $analytics['summary']['grand_pipeline_revenue'] = $analytics['summary']['total_course_pipeline_revenue'] + $analytics['summary']['total_services_pipeline_revenue'];

        // Period-specific actuals
        $monthStmt = $db->query("
            SELECT 
                COUNT(*) as this_month_admissions,
                SUM(CASE WHEN paid_amount > 0 THEN paid_amount WHEN expected_revenue > 0 THEN expected_revenue ELSE 36667 END) as this_month_realized_revenue,
                SUM(CASE WHEN expected_revenue > 0 THEN expected_revenue ELSE 36667 END) as this_month_booked_revenue
            FROM leads
            WHERE status = 'enrolled' 
              AND YEAR(updated_at) = YEAR(CURDATE()) 
              AND MONTH(updated_at) = MONTH(CURDATE())
        ");
        $mRow = $monthStmt ? $monthStmt->fetch() : [];
        $analytics['summary']['this_month_admissions'] = (int)($mRow['this_month_admissions'] ?? 0);
        $analytics['summary']['this_month_realized_revenue'] = (float)($mRow['this_month_realized_revenue'] ?? 0.0);
        $analytics['summary']['this_month_booked_revenue'] = (float)($mRow['this_month_booked_revenue'] ?? 0.0);

        $quarterStmt = $db->query("
            SELECT 
                COUNT(*) as this_quarter_admissions,
                SUM(CASE WHEN paid_amount > 0 THEN paid_amount WHEN expected_revenue > 0 THEN expected_revenue ELSE 36667 END) as this_quarter_realized_revenue,
                SUM(CASE WHEN expected_revenue > 0 THEN expected_revenue ELSE 36667 END) as this_quarter_booked_revenue
            FROM leads
            WHERE status = 'enrolled' 
              AND YEAR(updated_at) = YEAR(CURDATE()) 
              AND QUARTER(updated_at) = QUARTER(CURDATE())
        ");
        $qRow = $quarterStmt ? $quarterStmt->fetch() : [];
        $analytics['summary']['this_quarter_admissions'] = (int)($qRow['this_quarter_admissions'] ?? 0);
        $analytics['summary']['this_quarter_realized_revenue'] = (float)($qRow['this_quarter_realized_revenue'] ?? 0.0);
        $analytics['summary']['this_quarter_booked_revenue'] = (float)($qRow['this_quarter_booked_revenue'] ?? 0.0);

        $yearStmt = $db->query("
            SELECT 
                COUNT(*) as this_year_admissions,
                SUM(CASE WHEN paid_amount > 0 THEN paid_amount WHEN expected_revenue > 0 THEN expected_revenue ELSE 36667 END) as this_year_realized_revenue,
                SUM(CASE WHEN expected_revenue > 0 THEN expected_revenue ELSE 36667 END) as this_year_booked_revenue
            FROM leads
            WHERE status = 'enrolled' 
              AND YEAR(updated_at) = YEAR(CURDATE())
        ");
        $yRow = $yearStmt ? $yearStmt->fetch() : [];
        $analytics['summary']['this_year_admissions'] = (int)($yRow['this_year_admissions'] ?? 0);
        $analytics['summary']['this_year_realized_revenue'] = (float)($yRow['this_year_realized_revenue'] ?? 0.0);
        $analytics['summary']['this_year_booked_revenue'] = (float)($yRow['this_year_booked_revenue'] ?? 0.0);

        // 3. Counsellor Revenue Breakdown
        $cPerfStmt = $db->query("
            SELECT 
                u.id, u.name, u.email, u.role, u.employee_id,
                COUNT(l.id) as total_assigned,
                SUM(CASE WHEN l.offering_type = 'course' AND l.status = 'enrolled' THEN 1 ELSE 0 END) as course_admissions,
                SUM(CASE WHEN l.offering_type = 'course' AND l.status = 'enrolled' THEN (CASE WHEN l.paid_amount > 0 THEN l.paid_amount WHEN l.expected_revenue > 0 THEN l.expected_revenue ELSE 35000 END) ELSE 0 END) as course_revenue,
                SUM(CASE WHEN l.offering_type = 'course' AND l.status = 'enrolled' THEN (CASE WHEN l.expected_revenue > 0 THEN l.expected_revenue ELSE 35000 END) ELSE 0 END) as course_booked_revenue,
                SUM(CASE WHEN l.offering_type = 'service' AND l.status = 'enrolled' THEN 1 ELSE 0 END) as service_deals,
                SUM(CASE WHEN l.offering_type = 'service' AND l.status = 'enrolled' THEN (CASE WHEN l.paid_amount > 0 THEN l.paid_amount ELSE l.expected_revenue END) ELSE 0 END) as service_revenue,
                SUM(CASE WHEN l.offering_type = 'service' AND l.status = 'enrolled' THEN l.expected_revenue ELSE 0 END) as service_booked_revenue,
                SUM(CASE WHEN l.status = 'enrolled' THEN (CASE WHEN l.paid_amount > 0 THEN l.paid_amount WHEN l.expected_revenue > 0 THEN l.expected_revenue ELSE 35000 END) ELSE 0 END) as total_revenue,
                SUM(CASE WHEN l.status = 'enrolled' THEN (CASE WHEN l.expected_revenue > 0 THEN l.expected_revenue ELSE 35000 END) ELSE 0 END) as total_booked_revenue,
                SUM(CASE WHEN l.status = 'interested' THEN (CASE WHEN l.expected_revenue > 0 THEN l.expected_revenue ELSE 35000 END) ELSE 0 END) as pipeline_revenue
            FROM users u
            LEFT JOIN leads l ON l.assigned_to = u.id
            WHERE u.role IN ('sales_counsellor', 'employee', 'manager', 'hr', 'founder') 
              AND u.status = 'active'
            GROUP BY u.id, u.name, u.email, u.role, u.employee_id
            HAVING total_assigned > 0 OR u.role = 'sales_counsellor'
            ORDER BY total_revenue DESC, course_admissions DESC, total_assigned DESC
        ");
        $analytics['counsellors'] = $cPerfStmt ? $cPerfStmt->fetchAll() : [];

        // 4. Real-time Enrolled Admissions Ledger (latest 50)
        $admStmt = $db->query("
            SELECT 
                l.id, l.name, l.email, l.phone, l.city, l.graduation,
                l.offering_type, l.course_service_name, l.expected_revenue,
                l.paid_amount, l.payment_plan, l.total_installments, l.payment_status,
                l.created_at, l.updated_at,
                u.name as counsellor_name, u.email as counsellor_email
            FROM leads l
            LEFT JOIN users u ON l.assigned_to = u.id
            WHERE l.status = 'enrolled'
            ORDER BY l.updated_at DESC, l.id DESC
            LIMIT 50
        ");
        $analytics['recent_admissions'] = $admStmt ? $admStmt->fetchAll() : [];

        // 5. Monthly Trend (last 6 months actual revenue & admissions)
        $trendStmt = $db->query("
            SELECT 
                DATE_FORMAT(updated_at, '%Y-%m') as ym,
                DATE_FORMAT(updated_at, '%b %Y') as month_label,
                COUNT(id) as admissions_count,
                SUM(CASE WHEN paid_amount > 0 THEN paid_amount WHEN expected_revenue > 0 THEN expected_revenue ELSE 36667 END) as monthly_revenue,
                SUM(CASE WHEN expected_revenue > 0 THEN expected_revenue ELSE 36667 END) as monthly_booked_revenue
            FROM leads
            WHERE status = 'enrolled'
              AND updated_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
            GROUP BY DATE_FORMAT(updated_at, '%Y-%m'), DATE_FORMAT(updated_at, '%b %Y')
            ORDER BY ym ASC
        ");
        $analytics['monthly_trend'] = $trendStmt ? $trendStmt->fetchAll() : [];

    } catch (Throwable $e) {
        error_log("getCourseRevenueAnalytics error: " . $e->getMessage());
    }

    return $analytics;
}

/**
 * Fetch leads with advanced filtering and pagination
 */
function getLeads(array $filters = [], int $page = 1, int $limit = 50): array {
    ensureSalesTablesExist();
    try {
        $db = getDB();
        $conditions = ["1=1"];
        $params = [];

        if (isset($filters['assigned_to']) && $filters['assigned_to'] !== '') {
            if ($filters['assigned_to'] === 'unassigned' || $filters['assigned_to'] === 0 || $filters['assigned_to'] === '0') {
                $conditions[] = "(l.assigned_to IS NULL OR l.assigned_to = 0)";
            } else {
                $conditions[] = "l.assigned_to = ?";
                $params[] = (int)$filters['assigned_to'];
            }
        }

        if (!empty($filters['status'])) {
            $conditions[] = "l.status = ?";
            $params[] = $filters['status'];
        }

        if (isset($filters['is_manual'])) {
            if ($filters['is_manual'] === 1 || $filters['is_manual'] === '1' || $filters['is_manual'] === true) {
                $conditions[] = "(l.is_manual = 1 OR l.source IN ('Manual Entry', 'Raw Calling Data', 'Raw Calling List', 'College Database', 'Cold Outreach', 'Cold Calling', 'Manual Import'))";
            } elseif ($filters['is_manual'] === 0 || $filters['is_manual'] === '0' || $filters['is_manual'] === false) {
                $conditions[] = "(l.is_manual = 0 AND l.source NOT IN ('Manual Entry', 'Raw Calling Data', 'Raw Calling List', 'College Database', 'Cold Outreach', 'Cold Calling', 'Manual Import'))";
            }
        }

        if (!empty($filters['offering_type'])) {
            if ($filters['offering_type'] === 'manual' || $filters['offering_type'] === 'raw_data') {
                $conditions[] = "(l.is_manual = 1 OR l.source IN ('Manual Entry', 'Raw Calling Data', 'Raw Calling List', 'College Database', 'Cold Outreach', 'Cold Calling', 'Manual Import'))";
            } else {
                $conditions[] = "l.offering_type = ?";
                $params[] = $filters['offering_type'];
            }
        }

        if (!empty($filters['source'])) {
            $conditions[] = "l.source = ?";
            $params[] = $filters['source'];
        }

        if (!empty($filters['followup_filter'])) {
            if ($filters['followup_filter'] === 'today') {
                $conditions[] = "DATE(l.next_followup_date) = CURDATE() AND l.status IN ('new_lead', 'interested')";
            } elseif ($filters['followup_filter'] === 'overdue') {
                $conditions[] = "l.next_followup_date < NOW() AND l.status IN ('new_lead', 'interested')";
            } elseif ($filters['followup_filter'] === 'upcoming') {
                $conditions[] = "l.next_followup_date > NOW() AND l.status IN ('new_lead', 'interested')";
            }
        }

        if (!empty($filters['search'])) {
            $searchTerm = '%' . trim($filters['search']) . '%';
            $conditions[] = "(l.name LIKE ? OR l.phone LIKE ? OR l.email LIKE ? OR l.graduation LIKE ? OR l.course_service_name LIKE ? OR l.city LIKE ?)";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        $whereClause = implode(' AND ', $conditions);

        // Count Total
        $countStmt = $db->prepare("SELECT COUNT(*) FROM leads l WHERE $whereClause");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        // Data Query
        $offset = max(0, ($page - 1) * $limit);
        $sql = "
            SELECT l.*, 
                   u.name as assigned_to_name, u.email as assigned_to_email,
                   cb.name as assigned_by_name,
                   (SELECT COUNT(*) FROM lead_activities la WHERE la.lead_id = l.id) as activity_count,
                   (SELECT la.created_at FROM lead_activities la WHERE la.lead_id = l.id ORDER BY la.id DESC LIMIT 1) as last_contact_at
            FROM leads l
            LEFT JOIN users u ON l.assigned_to = u.id
            LEFT JOIN users cb ON l.assigned_by = cb.id
            WHERE $whereClause
            ORDER BY 
                CASE 
                    WHEN l.next_followup_date IS NOT NULL AND DATE(l.next_followup_date) = CURDATE() THEN 1
                    WHEN l.next_followup_date IS NOT NULL AND l.next_followup_date < NOW() THEN 2
                    WHEN l.status = 'new_lead' THEN 3
                    WHEN l.status = 'interested' THEN 4
                    ELSE 5
                END,
                l.updated_at DESC
            LIMIT $limit OFFSET $offset
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $records = $stmt->fetchAll();

        return [
            'data' => $records ?: [],
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => ceil($total / max(1, $limit))
        ];
    } catch (Throwable $e) {
        error_log("getLeads error: " . $e->getMessage());
        return ['data' => [], 'total' => 0, 'page' => 1, 'limit' => $limit, 'total_pages' => 1];
    }
}

/**
 * Get single lead details with full activity history
 */
function getLeadById(int $leadId): ?array {
    ensureSalesTablesExist();
    try {
        $db = getDB();
        $stmt = $db->prepare("
            SELECT l.*, 
                   u.name as assigned_to_name, u.email as assigned_to_email, u.contact_no as counsellor_phone,
                   cb.name as assigned_by_name
            FROM leads l
            LEFT JOIN users u ON l.assigned_to = u.id
            LEFT JOIN users cb ON l.assigned_by = cb.id
            WHERE l.id = ?
        ");
        $stmt->execute([$leadId]);
        $lead = $stmt->fetch();
        if (!$lead) return null;

        // Fetch activity trail
        $actStmt = $db->prepare("
            SELECT la.*, u.name as user_name, u.role as user_role
            FROM lead_activities la
            JOIN users u ON la.user_id = u.id
            WHERE la.lead_id = ?
            ORDER BY la.created_at DESC, la.id DESC
        ");
        $actStmt->execute([$leadId]);
        $lead['activities'] = $actStmt->fetchAll() ?: [];

        return $lead;
    } catch (Throwable $e) {
        return null;
    }
}

/**
 * Create a new lead and log initial activity
 */
function addLead(array $data, int $createdBy): int {
    ensureSalesTablesExist();
    $db = getDB();

    $name = trim($data['name'] ?? '');
    $phone = trim($data['phone'] ?? '');
    $email = !empty($data['email']) ? trim($data['email']) : null;
    $graduation = !empty($data['graduation']) ? trim($data['graduation']) : null;
    $altPhone = !empty($data['alternate_phone']) ? trim($data['alternate_phone']) : null;
    $city = !empty($data['city']) ? trim($data['city']) : null;
    $offeringType = in_array($data['offering_type'] ?? '', ['course', 'service']) ? $data['offering_type'] : 'course';
    $csId = !empty($data['course_service_id']) ? (int)$data['course_service_id'] : null;
    $csName = trim($data['course_service_name'] ?? 'General Inquiry');
    $source = trim($data['source'] ?? 'Raw Calling Data');
    $status = in_array($data['status'] ?? '', ['new_lead', 'interested', 'enrolled', 'uninterested']) ? $data['status'] : 'new_lead';
    $revenue = !empty($data['expected_revenue']) ? (float)$data['expected_revenue'] : 0.00;
    $assignedTo = !empty($data['assigned_to']) ? (int)$data['assigned_to'] : $createdBy;
    $nextFollowup = !empty($data['next_followup_date']) ? $data['next_followup_date'] : null;
    $notes = !empty($data['notes']) ? trim($data['notes']) : null;
    $isManual = isset($data['is_manual']) ? (int)(bool)$data['is_manual'] : (in_array($source, ['Manual Entry', 'Raw Calling Data', 'Raw Calling List', 'College Database', 'Cold Outreach', 'Cold Calling', 'Manual Import']) ? 1 : 0);

    $stmt = $db->prepare("
        INSERT INTO leads 
        (name, email, graduation, is_manual, phone, alternate_phone, city, offering_type, course_service_id, course_service_name, source, status, expected_revenue, assigned_to, assigned_by, next_followup_date, notes, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([
        $name, $email, $graduation, $isManual, $phone, $altPhone, $city, $offeringType, $csId, $csName, $source, $status, $revenue, $assignedTo, $createdBy, $nextFollowup, $notes
    ]);

    $leadId = (int)$db->lastInsertId();

    // Log initial activity note
    $initRemark = $isManual ? 'Manual / Raw Calling prospect registered' : 'Lead created';
    if ($graduation) {
        $initRemark .= ' [Graduation: ' . $graduation . ']';
    }
    $initRemark .= ' for ' . ucfirst($offeringType) . ' (' . $csName . ') via ' . $source . '.';
    if ($notes) {
        $initRemark .= ' Initial note: ' . $notes;
    }
    addLeadActivity($leadId, $createdBy, 'note', $initRemark, null, $status, $nextFollowup);

    // If assigned to another user, send notification
    if ($assignedTo && $assignedTo !== $createdBy) {
        createNotification(
            $assignedTo,
            '🎯 New Lead Assigned: ' . $name,
            'You have been assigned a new lead: ' . $name . ' (' . ($graduation ? $graduation . ' • ' : '') . $phone . ').',
            BASE_URL . '/sales/leads.php?lead_id=' . $leadId,
            'info'
        );
    }

    return $leadId;
}

/**
 * Convert a raw manual prospect into an active Course or Service lead
 */
function convertRawLead(int $leadId, string $offeringType, ?int $csId, string $csName, float $revenue, string $status, string $notes, int $updatedBy): bool {
    ensureSalesTablesExist();
    try {
        $db = getDB();
        $lead = getLeadById($leadId);
        if (!$lead) return false;

        $stmt = $db->prepare("
            UPDATE leads 
            SET offering_type = ?,
                course_service_id = ?,
                course_service_name = ?,
                expected_revenue = ?,
                status = ?,
                is_manual = 0,
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$offeringType, $csId ?: null, $csName, $revenue, $status, $leadId]);

        $remark = "Converted to " . ucfirst($offeringType) . " Lead: " . $csName . ($revenue > 0 ? " (Est. Revenue: " . formatCurrency($revenue) . ")" : "") . ($notes ? ". " . $notes : "");
        addLeadActivity($leadId, $updatedBy, 'status_change', $remark, $lead['status'], $status);

        return true;
    } catch (Throwable $e) {
        error_log("convertRawLead error: " . $e->getMessage());
        return false;
    }
}

/**
 * Update lead core information
 */
function updateLead(int $leadId, array $data): bool {
    ensureSalesTablesExist();
    $db = getDB();

    $stmt = $db->prepare("
        UPDATE leads SET
            name = ?,
            email = ?,
            graduation = ?,
            phone = ?,
            alternate_phone = ?,
            city = ?,
            offering_type = ?,
            course_service_id = ?,
            course_service_name = ?,
            source = ?,
            expected_revenue = ?,
            notes = ?,
            updated_at = NOW()
        WHERE id = ?
    ");
    return $stmt->execute([
        trim($data['name']),
        !empty($data['email']) ? trim($data['email']) : null,
        !empty($data['graduation']) ? trim($data['graduation']) : null,
        trim($data['phone']),
        !empty($data['alternate_phone']) ? trim($data['alternate_phone']) : null,
        !empty($data['city']) ? trim($data['city']) : null,
        $data['offering_type'],
        !empty($data['course_service_id']) ? (int)$data['course_service_id'] : null,
        trim($data['course_service_name']),
        trim($data['source'] ?? 'Raw Calling Data'),
        (float)($data['expected_revenue'] ?? 0),
        !empty($data['notes']) ? trim($data['notes']) : null,
        $leadId
    ]);
}

/**
 * Update Lead Status atomically with remark, next follow-up date, and optional offering/revenue details
 */
function updateLeadStatus(int $leadId, string $newStatus, string $remarks, ?string $nextFollowup, int $userId, ?string $offeringType = null, ?int $courseServiceId = null, ?string $courseServiceName = null, ?float $expectedRevenue = null): bool {
    ensureSalesTablesExist();
    $db = getDB();

    // Fetch current status
    $stmt = $db->prepare("SELECT status, name, assigned_to, offering_type, course_service_name, expected_revenue FROM leads WHERE id = ?");
    $stmt->execute([$leadId]);
    $lead = $stmt->fetch();
    if (!$lead) return false;

    $oldStatus = $lead['status'];

    $fields = ["status = ?", "next_followup_date = ?", "updated_at = NOW()"];
    $params = [$newStatus, $nextFollowup ?: null];

    if ($offeringType !== null && in_array($offeringType, ['course', 'service'])) {
        $fields[] = "offering_type = ?";
        $params[] = $offeringType;
    }
    if ($courseServiceId !== null) {
        $fields[] = "course_service_id = ?";
        $params[] = $courseServiceId > 0 ? $courseServiceId : null;
    }
    if ($courseServiceName !== null && trim($courseServiceName) !== '') {
        $fields[] = "course_service_name = ?";
        $params[] = trim($courseServiceName);
    }
    if ($expectedRevenue !== null) {
        $fields[] = "expected_revenue = ?";
        $params[] = max(0, $expectedRevenue);
    }

    $params[] = $leadId;
    $setClause = implode(', ', $fields);

    $uStmt = $db->prepare("UPDATE leads SET $setClause WHERE id = ?");
    $uStmt->execute($params);

    // Log Activity
    addLeadActivity($leadId, $userId, 'status_change', $remarks, $oldStatus, $newStatus, $nextFollowup);

    return true;
}

/**
 * Add an interaction / call log / note to a lead
 */
function addLeadActivity(int $leadId, int $userId, string $type, string $remarks, ?string $statusFrom = null, ?string $statusTo = null, ?string $nextFollowup = null): int {
    ensureSalesTablesExist();
    $db = getDB();

    $stmt = $db->prepare("
        INSERT INTO lead_activities (lead_id, user_id, activity_type, status_from, status_to, remarks, next_followup, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$leadId, $userId, $type, $statusFrom, $statusTo, trim($remarks), $nextFollowup ?: null]);
    
    // Update lead next followup if provided
    if ($nextFollowup) {
        $db->prepare("UPDATE leads SET next_followup_date = ?, updated_at = NOW() WHERE id = ?")->execute([$nextFollowup, $leadId]);
    } else {
        $db->prepare("UPDATE leads SET updated_at = NOW() WHERE id = ?")->execute([$leadId]);
    }

    return (int)$db->lastInsertId();
}

/**
 * Assign or reassign lead to a counsellor
 */
function assignLead(int $leadId, int $assignedTo, int $assignedBy): bool {
    ensureSalesTablesExist();
    $db = getDB();

    $stmt = $db->prepare("SELECT name, assigned_to FROM leads WHERE id = ?");
    $stmt->execute([$leadId]);
    $lead = $stmt->fetch();
    if (!$lead) return false;

    $uStmt = $db->prepare("UPDATE leads SET assigned_to = ?, assigned_by = ?, updated_at = NOW() WHERE id = ?");
    $uStmt->execute([$assignedTo, $assignedBy, $leadId]);

    // Fetch new assignee name
    $aStmt = $db->prepare("SELECT name FROM users WHERE id = ?");
    $aStmt->execute([$assignedTo]);
    $assigneeName = $aStmt->fetchColumn() ?: 'Counsellor #' . $assignedTo;

    addLeadActivity($leadId, $assignedBy, 'note', 'Lead reassigned to ' . $assigneeName . '.');

    // Send Notification
    createNotification(
        $assignedTo,
        '🎯 Lead Assigned: ' . $lead['name'],
        'You have been assigned the lead "' . $lead['name'] . '".',
        BASE_URL . '/sales/leads.php?lead_id=' . $leadId,
        'info'
    );

    return true;
}

/**
 * Bulk Assign Leads by Count (Quantity)
 * Can assign to a single counsellor OR auto-distribute equally (round-robin) among multiple counsellors
 */
function bulkAssignLeadsByCount(int $count, ?int $assignedTo, ?array $distributeCounsellorIds, int $assignedBy, array $filterConditions = []): array {
    ensureSalesTablesExist();
    $db = getDB();

    if ($count <= 0) {
        return ['success' => false, 'count' => 0, 'message' => 'Please specify a valid number of leads to assign.'];
    }

    // Determine target assignee list
    $counsellorIds = [];
    if (!empty($distributeCounsellorIds)) {
        $counsellorIds = array_values(array_unique(array_filter(array_map('intval', $distributeCounsellorIds), function($id) { return $id > 0; })));
    } elseif (!empty($assignedTo) && $assignedTo > 0) {
        $counsellorIds = [(int)$assignedTo];
    }

    if (empty($counsellorIds)) {
        return ['success' => false, 'count' => 0, 'message' => 'Please select at least one counsellor / team member to assign to.'];
    }

    // Build query to find matching leads
    $conditions = ["1=1"];
    $params = [];

    // Source pool filter
    $sourcePool = $filterConditions['source_pool'] ?? 'unassigned';
    if ($sourcePool === 'unassigned') {
        $conditions[] = "(assigned_to IS NULL OR assigned_to = 0)";
    } elseif ($sourcePool === 'fresh') {
        $conditions[] = "status = 'new_lead'";
    } elseif ($sourcePool === 'counsellor' && !empty($filterConditions['current_counsellor_id'])) {
        $conditions[] = "assigned_to = ?";
        $params[] = (int)$filterConditions['current_counsellor_id'];
    }

    // Offering / Type filter
    if (!empty($filterConditions['offering_type'])) {
        if ($filterConditions['offering_type'] === 'course') {
            $conditions[] = "offering_type = 'course' AND (is_manual = 0 OR is_manual IS NULL)";
        } elseif ($filterConditions['offering_type'] === 'service') {
            $conditions[] = "offering_type = 'service' AND (is_manual = 0 OR is_manual IS NULL)";
        } elseif ($filterConditions['offering_type'] === 'manual' || $filterConditions['offering_type'] === 'raw_data') {
            $conditions[] = "(is_manual = 1 OR source IN ('Manual Entry', 'Raw Calling Data', 'Raw Calling List', 'College Database', 'Cold Outreach', 'Cold Calling', 'Manual Import'))";
        }
    }

    // Status filter if provided
    if (!empty($filterConditions['status'])) {
        $conditions[] = "status = ?";
        $params[] = $filterConditions['status'];
    }

    $whereClause = implode(' AND ', $conditions);

    // Fetch matching lead IDs
    $stmt = $db->prepare("SELECT id, name FROM leads WHERE $whereClause ORDER BY id ASC LIMIT " . (int)$count);
    $stmt->execute($params);
    $leadsToAssign = $stmt->fetchAll();

    if (empty($leadsToAssign)) {
        return ['success' => false, 'count' => 0, 'message' => 'No matching leads found to assign with selected filters.'];
    }

    // Fetch counsellor names for notifications & activity
    $counsellorNames = [];
    $inPlaceholders = implode(',', array_fill(0, count($counsellorIds), '?'));
    $nameStmt = $db->prepare("SELECT id, name FROM users WHERE id IN ($inPlaceholders)");
    $nameStmt->execute($counsellorIds);
    while ($cRow = $nameStmt->fetch()) {
        $counsellorNames[(int)$cRow['id']] = $cRow['name'];
    }

    $updateStmt = $db->prepare("UPDATE leads SET assigned_to = ?, assigned_by = ?, updated_at = NOW() WHERE id = ?");
    $actStmt = $db->prepare("INSERT INTO lead_activities (lead_id, user_id, activity_type, remarks, created_at) VALUES (?, ?, 'note', ?, NOW())");

    $numCounsellors = count($counsellorIds);
    $assignedBreakdown = [];
    foreach ($counsellorIds as $cid) {
        $assignedBreakdown[$cid] = 0;
    }

    $assignedTotal = 0;
    foreach ($leadsToAssign as $idx => $lead) {
        $targetCounsellorId = $counsellorIds[$idx % $numCounsellors];
        $targetName = $counsellorNames[$targetCounsellorId] ?? ('Counsellor #' . $targetCounsellorId);

        $updateStmt->execute([$targetCounsellorId, $assignedBy, $lead['id']]);
        $actStmt->execute([$lead['id'], $assignedBy, 'Bulk assigned to ' . $targetName . '.']);
        
        $assignedBreakdown[$targetCounsellorId]++;
        $assignedTotal++;
    }

    // Send notifications to each assigned counsellor
    foreach ($assignedBreakdown as $cId => $cCount) {
        if ($cCount > 0) {
            $cName = $counsellorNames[$cId] ?? 'Staff';
            createNotification(
                $cId,
                "🎯 {$cCount} Leads Assigned",
                "You have been assigned {$cCount} new leads by management.",
                BASE_URL . '/sales/leads.php',
                'info'
            );
        }
    }

    return [
        'success' => true,
        'count' => $assignedTotal,
        'breakdown' => $assignedBreakdown,
        'message' => count($counsellorIds) > 1 
            ? "Successfully distributed {$assignedTotal} leads equally among " . count($counsellorIds) . " team members!" 
            : "Successfully assigned {$assignedTotal} leads to " . ($counsellorNames[$counsellorIds[0]] ?? 'selected counsellor') . "!"
    ];
}

/**
 * Bulk Assign Specific Selected Leads
 */
function bulkAssignSelectedLeads(array $leadIds, ?int $assignedTo, ?array $distributeCounsellorIds, int $assignedBy): array {
    ensureSalesTablesExist();
    $db = getDB();

    $cleanIds = array_values(array_unique(array_filter(array_map('intval', $leadIds), function($id) { return $id > 0; })));
    if (empty($cleanIds)) {
        return ['success' => false, 'count' => 0, 'message' => 'No valid leads selected.'];
    }

    // Determine target assignee list
    $counsellorIds = [];
    if (!empty($distributeCounsellorIds)) {
        $counsellorIds = array_values(array_unique(array_filter(array_map('intval', $distributeCounsellorIds), function($id) { return $id > 0; })));
    } elseif (!empty($assignedTo) && $assignedTo > 0) {
        $counsellorIds = [(int)$assignedTo];
    }

    if (empty($counsellorIds)) {
        return ['success' => false, 'count' => 0, 'message' => 'Please select at least one counsellor / team member to assign to.'];
    }

    // Fetch counsellor names
    $counsellorNames = [];
    $inPlaceholders = implode(',', array_fill(0, count($counsellorIds), '?'));
    $nameStmt = $db->prepare("SELECT id, name FROM users WHERE id IN ($inPlaceholders)");
    $nameStmt->execute($counsellorIds);
    while ($cRow = $nameStmt->fetch()) {
        $counsellorNames[(int)$cRow['id']] = $cRow['name'];
    }

    $updateStmt = $db->prepare("UPDATE leads SET assigned_to = ?, assigned_by = ?, updated_at = NOW() WHERE id = ?");
    $actStmt = $db->prepare("INSERT INTO lead_activities (lead_id, user_id, activity_type, remarks, created_at) VALUES (?, ?, 'note', ?, NOW())");

    $numCounsellors = count($counsellorIds);
    $assignedBreakdown = [];
    foreach ($counsellorIds as $cid) {
        $assignedBreakdown[$cid] = 0;
    }

    $assignedTotal = 0;
    foreach ($cleanIds as $idx => $leadId) {
        $targetCounsellorId = $counsellorIds[$idx % $numCounsellors];
        $targetName = $counsellorNames[$targetCounsellorId] ?? ('Counsellor #' . $targetCounsellorId);

        $updateStmt->execute([$targetCounsellorId, $assignedBy, $leadId]);
        $actStmt->execute([$leadId, $assignedBy, 'Bulk assigned to ' . $targetName . '.']);
        
        $assignedBreakdown[$targetCounsellorId]++;
        $assignedTotal++;
    }

    // Send notifications
    foreach ($assignedBreakdown as $cId => $cCount) {
        if ($cCount > 0) {
            createNotification(
                $cId,
                "🎯 {$cCount} Leads Assigned",
                "You have been assigned {$cCount} new leads.",
                BASE_URL . '/sales/leads.php',
                'info'
            );
        }
    }

    return [
        'success' => true,
        'count' => $assignedTotal,
        'breakdown' => $assignedBreakdown,
        'message' => count($counsellorIds) > 1 
            ? "Successfully distributed {$assignedTotal} leads equally among " . count($counsellorIds) . " team members!" 
            : "Successfully assigned {$assignedTotal} leads to " . ($counsellorNames[$counsellorIds[0]] ?? 'selected counsellor') . "!"
    ];
}

/**
 * Fetch full lead details by list of IDs (for backup export)
 */
function getLeadsByIds(array $leadIds): array {
    ensureSalesTablesExist();
    $db = getDB();

    $cleanIds = array_values(array_unique(array_filter(array_map('intval', $leadIds), function($id) { return $id > 0; })));
    if (empty($cleanIds)) return [];

    $inClause = implode(',', $cleanIds);
    $stmt = $db->query("
        SELECT l.*, 
               u.name as assigned_to_name, u.email as assigned_to_email,
               (SELECT la.created_at FROM lead_activities la WHERE la.lead_id = l.id ORDER BY la.id DESC LIMIT 1) as last_contact_at
        FROM leads l
        LEFT JOIN users u ON l.assigned_to = u.id
        WHERE l.id IN ($inClause)
        ORDER BY l.id ASC
    ");
    return $stmt ? $stmt->fetchAll() : [];
}

/**
 * Bulk Delete Leads
 */
function bulkDeleteLeads(array $leadIds): int {
    ensureSalesTablesExist();
    $db = getDB();

    $cleanIds = array_values(array_unique(array_filter(array_map('intval', $leadIds), function($id) { return $id > 0; })));
    if (empty($cleanIds)) return 0;

    $inClause = implode(',', $cleanIds);
    // Remove associated lead payments & activities first
    try {
        $db->exec("DELETE FROM lead_payments WHERE lead_id IN ($inClause)");
    } catch (Throwable $e) {
        error_log("Error deleting lead payments: " . $e->getMessage());
    }
    try {
        $db->exec("DELETE FROM lead_activities WHERE lead_id IN ($inClause)");
    } catch (Throwable $e) {
        error_log("Error deleting lead activities: " . $e->getMessage());
    }

    $stmt = $db->prepare("DELETE FROM leads WHERE id IN ($inClause)");
    $stmt->execute();
    return $stmt->rowCount();
}

/**
 * Delete All Leads (Entire database or matching filter scope)
 */
function deleteAllLeads(array $filters = []): int {
    ensureSalesTablesExist();
    $db = getDB();

    $hasFilter = !empty($filters['offering_type']) || !empty($filters['status']) || !empty($filters['assigned_to']) || isset($filters['is_manual']) || !empty($filters['search']);

    if (!$hasFilter) {
        try {
            $db->exec("DELETE FROM lead_payments");
        } catch (Throwable $e) {}
        try {
            $db->exec("DELETE FROM lead_activities");
        } catch (Throwable $e) {}
        $stmt = $db->prepare("DELETE FROM leads");
        $stmt->execute();
        return $stmt->rowCount();
    }

    $leadsRes = getLeads($filters, 1, 100000);
    $ids = array_column($leadsRes['data'] ?? [], 'id');
    if (empty($ids)) return 0;

    return bulkDeleteLeads($ids);
}

/**
 * Delete a lead
 */
function deleteLead(int $leadId): bool {
    ensureSalesTablesExist();
    $db = getDB();
    try {
        $db->prepare("DELETE FROM lead_payments WHERE lead_id = ?")->execute([$leadId]);
    } catch (Throwable $e) {
        error_log("Error deleting lead payments: " . $e->getMessage());
    }
    try {
        $db->prepare("DELETE FROM lead_activities WHERE lead_id = ?")->execute([$leadId]);
    } catch (Throwable $e) {
        error_log("Error deleting lead activities: " . $e->getMessage());
    }
    $stmt = $db->prepare("DELETE FROM leads WHERE id = ?");
    return $stmt->execute([$leadId]);
}

/**
 * Helper to render lead status badge HTML
 */
function getLeadStatusBadge(string $status): string {
    return match($status) {
        'new_lead'     => '<span class="badge badge-info" style="font-size: 11px; display: inline-flex; align-items: center; gap: 4px;"><i class="fa-solid fa-user-plus"></i> New Lead</span>',
        'interested'   => '<span class="badge badge-warning" style="font-size: 11px; display: inline-flex; align-items: center; gap: 4px;"><i class="fa-solid fa-clock-rotate-left"></i> Interested</span>',
        'enrolled'     => '<span class="badge badge-success" style="font-size: 11px; display: inline-flex; align-items: center; gap: 4px;"><i class="fa-solid fa-circle-check"></i> Enrolled</span>',
        'uninterested' => '<span class="badge badge-danger" style="font-size: 11px; display: inline-flex; align-items: center; gap: 4px;"><i class="fa-solid fa-circle-xmark"></i> Uninterested</span>',
        default        => '<span class="badge badge-neutral">' . ucfirst(e($status)) . '</span>'
    };
}

/**
 * Get all payment / installment records for a lead
 */
function getLeadPayments(int $leadId): array {
    ensureSalesTablesExist();
    try {
        $db = getDB();
        $stmt = $db->prepare("
            SELECT lp.*, u.name as recorded_by_name
            FROM lead_payments lp
            LEFT JOIN users u ON lp.recorded_by = u.id
            WHERE lp.lead_id = ?
            ORDER BY lp.installment_no ASC, lp.id ASC
        ");
        $stmt->execute([$leadId]);
        return $stmt->fetchAll() ?: [];
    } catch (Throwable $e) {
        error_log("getLeadPayments error: " . $e->getMessage());
        return [];
    }
}

/**
 * Get complete payment summary for a lead (Total Fee, Paid, Balance, Plan, Installments)
 */
function getLeadPaymentSummary(int $leadId): array {
    ensureSalesTablesExist();
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT id, name, status, offering_type, course_service_name, expected_revenue, payment_plan, total_installments, paid_amount, payment_status FROM leads WHERE id = ?");
        $stmt->execute([$leadId]);
        $lead = $stmt->fetch();
        if (!$lead) return [];

        $payments = getLeadPayments($leadId);
        $totalFee = (float)$lead['expected_revenue'];
        
        // Sum calculated from actual payments if present
        $totalPaid = 0.0;
        foreach ($payments as $p) {
            $totalPaid += (float)$p['paid_amount'];
        }
        if (empty($payments)) {
            $totalPaid = (float)$lead['paid_amount'];
        }

        $balance = max(0.0, $totalFee - $totalPaid);
        $paymentStatus = $lead['payment_status'] ?: 'unpaid';
        if ($totalFee > 0) {
            if ($totalPaid >= $totalFee) {
                $paymentStatus = 'paid';
            } elseif ($totalPaid > 0) {
                $paymentStatus = 'partially_paid';
            } else {
                $paymentStatus = 'unpaid';
            }
        }

        return [
            'lead_id' => $leadId,
            'lead_name' => $lead['name'],
            'course_service_name' => $lead['course_service_name'],
            'offering_type' => $lead['offering_type'],
            'status' => $lead['status'],
            'total_fee' => $totalFee,
            'paid_amount' => $totalPaid,
            'balance_due' => $balance,
            'balance' => $balance,
            'payment_plan' => $lead['payment_plan'] ?: 'none',
            'total_installments' => (int)($lead['total_installments'] ?: (count($payments) ?: 1)),
            'payment_status' => $paymentStatus,
            'installments' => $payments
        ];
    } catch (Throwable $e) {
        error_log("getLeadPaymentSummary error: " . $e->getMessage());
        return [];
    }
}

/**
 * Save / Configure Payment Plan (1-Time or Installments) for a lead
 */
function saveLeadPaymentPlan(int $leadId, string $planType, array $installments, int $userId): bool {
    ensureSalesTablesExist();
    $db = getDB();

    $lead = getLeadById($leadId);
    if (!$lead) return false;

    $totalFee = (float)$lead['expected_revenue'];
    $validPlanType = in_array($planType, ['one_time', 'installment']) ? $planType : 'one_time';
    $numInstallments = max(1, count($installments));

    // Begin atomic transaction
    $db->beginTransaction();
    try {
        // Clear previous payments
        $delStmt = $db->prepare("DELETE FROM lead_payments WHERE lead_id = ?");
        $delStmt->execute([$leadId]);

        $totalPaidSum = 0.0;
        $allPaid = true;
        $anyPaid = false;

        $insStmt = $db->prepare("
            INSERT INTO lead_payments 
            (lead_id, installment_no, title, amount, due_date, paid_amount, paid_at, payment_method, transaction_id, status, recorded_by, notes, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        $instNo = 1;
        foreach ($installments as $inst) {
            $title = trim($inst['title'] ?? ($validPlanType === 'one_time' ? '1-Time Full Payment' : "Installment {$instNo}"));
            $amt = max(0.0, (float)($inst['amount'] ?? 0));
            $dueDate = !empty($inst['due_date']) ? $inst['due_date'] : null;
            $paidAmt = max(0.0, (float)($inst['paid_amount'] ?? 0));
            $paidAt = !empty($inst['paid_at']) ? $inst['paid_at'] : ($paidAmt > 0 ? date('Y-m-d H:i:s') : null);
            $method = !empty($inst['payment_method']) ? trim($inst['payment_method']) : null;
            $txnId = !empty($inst['transaction_id']) ? trim($inst['transaction_id']) : null;
            
            // Derive installment status
            $status = $inst['status'] ?? 'pending';
            if ($paidAmt >= $amt && $amt > 0) {
                $status = 'paid';
            } elseif ($paidAmt > 0) {
                $status = 'partially_paid';
            } elseif ($dueDate && strtotime($dueDate) < strtotime(date('Y-m-d'))) {
                $status = 'overdue';
            } else {
                $status = 'pending';
            }

            $notes = !empty($inst['notes']) ? trim($inst['notes']) : null;

            $insStmt->execute([
                $leadId, $instNo, $title, $amt, $dueDate, $paidAmt, $paidAt, $method, $txnId, $status, $userId, $notes
            ]);

            $totalPaidSum += $paidAmt;
            if ($status === 'paid') {
                $anyPaid = true;
            } else {
                $allPaid = false;
            }
            if ($paidAmt > 0) {
                $anyPaid = true;
            }

            $instNo++;
        }

        // Determine overall payment status
        $overallStatus = 'unpaid';
        if ($totalPaidSum >= $totalFee && $totalFee > 0) {
            $overallStatus = 'paid';
        } elseif ($totalPaidSum > 0) {
            $overallStatus = 'partially_paid';
        }

        // Update leads master table
        $updLead = $db->prepare("
            UPDATE leads 
            SET payment_plan = ?, total_installments = ?, paid_amount = ?, payment_status = ?, updated_at = NOW() 
            WHERE id = ?
        ");
        $updLead->execute([$validPlanType, $numInstallments, $totalPaidSum, $overallStatus, $leadId]);

        // Add activity log
        $planLabel = ($validPlanType === 'one_time') ? '1-Time Full Payment' : "{$numInstallments} Installments Plan";
        addLeadActivity($leadId, $userId, 'note', "💳 Payment Plan Setup: {$planLabel} (Total Fee: ₹" . number_format($totalFee) . ", Initial Paid: ₹" . number_format($totalPaidSum) . ")");

        $db->commit();
        return true;
    } catch (Throwable $e) {
        $db->rollBack();
        error_log("saveLeadPaymentPlan error: " . $e->getMessage());
        return false;
    }
}

/**
 * Record or Update an Individual Installment Payment by Sales Person
 */
function recordLeadPayment(int $leadId, int $paymentId, float $paidAmount, ?string $paidAt, ?string $method, ?string $txnId, string $status, ?string $notes, int $userId): bool {
    ensureSalesTablesExist();
    $db = getDB();

    $lead = getLeadById($leadId);
    if (!$lead) return false;

    $payStmt = $db->prepare("SELECT * FROM lead_payments WHERE id = ? AND lead_id = ?");
    $payStmt->execute([$paymentId, $leadId]);
    $payment = $payStmt->fetch();
    if (!$payment) return false;

    $instAmount = (float)$payment['amount'];
    $cleanPaidAmt = max(0.0, $paidAmount);
    $cleanPaidAt = !empty($paidAt) ? $paidAt : ($cleanPaidAmt > 0 ? date('Y-m-d H:i:s') : null);

    // Auto-derive status if not forced
    if ($status === 'auto' || empty($status)) {
        if ($cleanPaidAmt >= $instAmount && $instAmount > 0) {
            $status = 'paid';
        } elseif ($cleanPaidAmt > 0) {
            $status = 'partially_paid';
        } else {
            $status = 'pending';
        }
    }

    $db->beginTransaction();
    try {
        $updPay = $db->prepare("
            UPDATE lead_payments 
            SET paid_amount = ?, paid_at = ?, payment_method = ?, transaction_id = ?, status = ?, recorded_by = ?, notes = ?, updated_at = NOW()
            WHERE id = ? AND lead_id = ?
        ");
        $updPay->execute([
            $cleanPaidAmt, $cleanPaidAt, $method ?: null, $txnId ?: null, $status, $userId, $notes ?: null, $paymentId, $leadId
        ]);

        // Recalculate total paid amount for this lead
        $sumStmt = $db->prepare("SELECT SUM(paid_amount) as total_paid FROM lead_payments WHERE lead_id = ?");
        $sumStmt->execute([$leadId]);
        $totalPaidSum = (float)($sumStmt->fetchColumn() ?: 0.0);

        $totalFee = (float)$lead['expected_revenue'];
        $overallStatus = 'unpaid';
        if ($totalPaidSum >= $totalFee && $totalFee > 0) {
            $overallStatus = 'paid';
        } elseif ($totalPaidSum > 0) {
            $overallStatus = 'partially_paid';
        }

        $updLead = $db->prepare("UPDATE leads SET paid_amount = ?, payment_status = ?, updated_at = NOW() WHERE id = ?");
        $updLead->execute([$totalPaidSum, $overallStatus, $leadId]);

        // Log payment activity
        $methodText = !empty($method) ? strtoupper($method) : 'Direct';
        $txnText = !empty($txnId) ? " (Ref: {$txnId})" : '';
        $logRemarks = "💰 Payment Received for {$payment['title']}: ₹" . number_format($cleanPaidAmt) . " via {$methodText}{$txnText}. Status: " . ucfirst(str_replace('_', ' ', $status));
        if (!empty($notes)) {
            $logRemarks .= " | Note: " . trim($notes);
        }
        addLeadActivity($leadId, $userId, 'note', $logRemarks);

        $db->commit();
        return true;
    } catch (Throwable $e) {
        $db->rollBack();
        error_log("recordLeadPayment error: " . $e->getMessage());
        return false;
    }
}

/**
 * Helper to render Payment Status Badge HTML
 */
function getPaymentStatusBadge(string $paymentStatus, float $paidAmount, float $totalAmount, string $planType = 'none', string $offeringType = 'course'): string {
    $isService = ($offeringType === 'service');
    if ($isService) {
        $planIcon = ($planType === 'installment' || $planType === 'milestone') ? '<i class="fa-solid fa-diagram-project" style="font-size: 10px;"></i>' : '<i class="fa-solid fa-bolt" style="font-size: 10px;"></i>';
    } else {
        $planIcon = ($planType === 'installment') ? '<i class="fa-solid fa-calendar-days" style="font-size: 10px;"></i>' : '<i class="fa-solid fa-bolt" style="font-size: 10px;"></i>';
    }
    
    if ($totalAmount > 0 && $paidAmount >= $totalAmount) {
        return '<span class="badge badge-success" style="font-size: 11px; display: inline-flex; align-items: center; gap: 4px;" title="Full payment received"><i class="fa-solid fa-circle-check"></i> Paid ₹' . number_format($paidAmount) . '</span>';
    } elseif ($paidAmount > 0) {
        $bal = max(0, $totalAmount - $paidAmount);
        $titlePrefix = $isService ? 'Milestones Paid' : 'Paid';
        return '<span class="badge badge-warning" style="font-size: 11px; display: inline-flex; align-items: center; gap: 4px;" title="' . $titlePrefix . ': ₹' . number_format($paidAmount) . ' | Due: ₹' . number_format($bal) . '"><i class="fa-solid fa-hourglass-half"></i> ' . $planIcon . ' ₹' . number_format($paidAmount) . ' / ₹' . number_format($totalAmount) . '</span>';
    } else {
        return '<span class="badge badge-danger" style="font-size: 11px; display: inline-flex; align-items: center; gap: 4px;" title="No payment received yet"><i class="fa-solid fa-clock"></i> ' . $planIcon . ' Due ₹' . number_format($totalAmount) . '</span>';
    }
}

/**
 * Generate formatted WhatsApp outreach message for leads/prospects
 * Dynamically tailored for Website Inflow, UI/UX Design inquiries, and Services.
 */
function getWhatsAppLeadMessage(string $leadName = '', ?string $counsellorName = null, ?string $courseName = null, ?string $source = null, ?string $offeringType = null): string {
    $counsellor = $counsellorName ?: (function_exists('getUserName') ? (getUserName() ?: '') : '');
    if (empty($counsellor) && isset($_SESSION['user_name'])) {
        $counsellor = $_SESSION['user_name'];
    }
    if (empty($counsellor)) {
        $counsellor = 'Team';
    }
    
    $name = trim($leadName);
    $displayName = !empty($name) ? $name : 'there';
    
    // Check if this is an IT Service lead
    if ($offeringType === 'service') {
        $serviceTitle = !empty($courseName) ? $courseName : 'IT Services & Software Development';
        return "Hi " . $displayName . " 👋\n\n"
            . "I’m " . $counsellor . " from *Trivikra Tech*.\n\n"
            . "Thank you for reaching out to us regarding *" . $serviceTitle . "*.\n\n"
            . "We’d love to understand your project requirements and share our portfolio & solution architecture.\n\n"
            . "When would be a convenient time for a quick 10-minute discovery call? 🙂";
    }

    // Default & Website Inflow Course Leads (UI/UX Design + Gen-AI Program)
    $courseTitle = !empty($courseName) ? $courseName : 'UI/UX Design';
    
    return "Hi " . $displayName . " 👋\n\n"
        . "I’m " . $counsellor . " from *Trivikra Tech*.\n\n"
        . "You had earlier shown interest in *" . $courseTitle . "* with us. We’re reconnecting with our previous students/leads because we’ve recently updated our program with *Gen-AI & Agentic-AI tools*.\n\n"
        . "🎨 UI/UX Design + Gen-AI + Agentic-AI\n"
        . "💼 Industry Projects & Portfolio Building\n"
        . "🤖 Latest AI Tools & Workflows\n"
        . "🚀 Internship & Career Support\n\n"
        . "Are you currently looking to *start or upgrade your career in " . $courseTitle . "?*\n\n"
        . "Just reply *YES*, and I’ll share the updated course details with you. 🙂";
}

/**
 * Build WhatsApp click-to-chat URL
 */
function getWhatsAppLeadUrl(string $phone, string $leadName = '', ?string $counsellorName = null, ?string $courseName = null, ?string $source = null, ?string $offeringType = null): string {
    $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
    if (strlen($cleanPhone) === 10) {
        $cleanPhone = '91' . $cleanPhone;
    }
    $message = getWhatsAppLeadMessage($leadName, $counsellorName, $courseName, $source, $offeringType);
    return 'https://api.whatsapp.com/send?phone=' . $cleanPhone . '&text=' . rawurlencode($message);
}

