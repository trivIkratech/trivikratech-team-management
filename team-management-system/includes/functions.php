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
 * Sanitize input string (trim + strip tags)
 */
function sanitize(string $input): string {
    return trim(strip_tags($input));
}

/**
 * Get POST value with sanitization
 */
function post(string $key, mixed $default = ''): string {
    return isset($_POST[$key]) ? sanitize($_POST[$key]) : (string)$default;
}

/**
 * Get GET value with sanitization
 */
function get(string $key, mixed $default = ''): string {
    return isset($_GET[$key]) ? sanitize($_GET[$key]) : (string)$default;
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
        default             => 'badge-secondary'
    };
}

/**
 * Accurately resolve attendance status for a record
 * 
 * Shift Rule: >= 6h (21600s) = present, >= 3h (10800s) = half-day, < 3h = absent.
 * If currently checked in (no checkout yet) = present.
 * If on approved leave = leave.
 * If not checked in and no leave = absent.
 */
function resolveAttendanceStatus(?string $checkIn, ?string $checkOut, ?string $totalWorkingTime, ?string $attStatus, ?int $leaveId = null): string {
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
function getInitials(string $name): string {
    $parts = explode(' ', trim($name));
    $initials = '';
    foreach (array_slice($parts, 0, 2) as $part) {
        $initials .= strtoupper(mb_substr($part, 0, 1));
    }
    return $initials ?: '?';
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
 * Get unread notifications for a user
 */
function getUnreadNotifications(int $userId, int $limit = 5): array {
    try {
        $db = getDB();
        $stmt = $db->prepare("
            SELECT * FROM notifications 
            WHERE user_id = ? 
            ORDER BY is_read ASC, created_at DESC 
            LIMIT ?
        ");
        $stmt->bindValue(1, $userId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Count unread notifications
 */
function countUnreadNotifications(int $userId): int {
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$userId]);
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
        $db = getDB();
        $stmt = $db->prepare("
            SELECT COUNT(*) 
            FROM chat_messages m
            JOIN chat_room_members rm ON m.room_id = rm.room_id
            WHERE rm.user_id = ? AND m.sender_id != ? AND m.is_read = 0
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
    $badgeText = $isWeekend ? 'Sunday · Weekly Off' : 'Mon – Sat · Working Day';
    $badgeClass = $isWeekend ? 'badge-warning' : 'badge-info';
    
    return '
    <div class="working-module-banner" style="background: var(--color-bg-secondary); border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: 12px 18px; max-width: 580px; margin: 0 auto 20px; font-size: 13px; text-align: left; box-shadow: 0 2px 6px rgba(0,0,0,0.04);">
        <div style="font-weight: 600; margin-bottom: 6px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
            <span style="display: flex; align-items: center; gap: 6px; color: var(--color-primary); font-size: 14px;">
                <i class="fa-solid fa-business-time"></i> Working Shift: 10:00 AM – 05:00 PM
            </span>
            <span class="badge ' . $badgeClass . '" style="font-size: 11px;">' . $badgeText . '</span>
        </div>
        <div style="display: flex; gap: 16px; color: var(--color-text-secondary); font-size: 12px; margin-top: 6px; flex-wrap: wrap; border-top: 1px dashed var(--color-border); padding-top: 8px;">
            <span><i class="fa-solid fa-calendar-days"></i> <strong>Mon – Sat</strong></span>
            <span><i class="fa-solid fa-clock"></i> <strong>7h Shift</strong></span>
            <span><i class="fa-solid fa-mug-hot"></i> <strong>1h Break (Excluded)</strong></span>
            <span><i class="fa-solid fa-laptop-code"></i> <strong>6h Net Work</strong></span>
            <span><i class="fa-solid fa-circle-check" style="color: var(--color-success);"></i> Full Day: <strong>≥ 6h</strong> (Half Day: <strong>≥ 3h</strong>)</span>
        </div>
    </div>';
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
              `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
              `description` text COLLATE utf8mb4_unicode_ci,
              `leader_id` int NOT NULL,
              `created_by` int NOT NULL,
              `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
              `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              KEY `idx_leader_id` (`leader_id`),
              KEY `idx_created_by` (`created_by`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        $db->exec("
            CREATE TABLE IF NOT EXISTS `team_members` (
              `id` int NOT NULL AUTO_INCREMENT,
              `team_id` int NOT NULL,
              `user_id` int NOT NULL,
              `role_in_team` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'member',
              `joined_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              UNIQUE KEY `uk_team_member` (`team_id`,`user_id`),
              KEY `idx_team_id` (`team_id`),
              KEY `idx_user_id` (`user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        // Check if team_id column exists in tasks table
        $stmt = $db->query("SHOW COLUMNS FROM `tasks` LIKE 'team_id'");
        if (!$stmt->fetch()) {
            $db->exec("ALTER TABLE `tasks` ADD COLUMN `team_id` INT NULL DEFAULT NULL AFTER `assigned_by`, ADD KEY `idx_team_id` (`team_id`)");
        }

        $provisioned = true;
    } catch (PDOException $e) {
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
        return $stmt->fetchAll();
    } catch (PDOException $e) {
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
        return $stmt->fetchAll();
    } catch (PDOException $e) {
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
    } catch (PDOException $e) {
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
        return $stmt->fetchAll();
    } catch (PDOException $e) {
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



