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
    $time = strtotime($datetime);
    $diff = time() - $time;
    
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    
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
        $stmt = $db->prepare("
            INSERT INTO notifications (user_id, title, message, link, type, is_read, created_at) 
            VALUES (?, ?, ?, ?, ?, 0, NOW())
        ");
        return $stmt->execute([$userId, $title, $message, $link, $type]);
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


