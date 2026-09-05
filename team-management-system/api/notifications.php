<?php
/**
 * API — Notifications
 * 
 * Handles AJAX requests for fetching & marking notifications as read.
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/middleware.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated.']);
    exit;
}

$db = getDB();
$userId = getUserId();
$action = post('action') ?: get('action');

// Automatically reset / clean notifications older than today
resetDailyNotifications();

try {
    if ($action === 'mark_read') {
        $notifId = (int)post('id');
        $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
        $stmt->execute([$notifId, $userId]);
        echo json_encode(['success' => true]);
        
    } elseif ($action === 'mark_all_read') {
        $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
        $stmt->execute([$userId]);
        echo json_encode(['success' => true]);
        
    } elseif ($action === 'poll') {
        $lastId = (int)($_GET['last_id'] ?? 0);
        $isInit = (bool)($_GET['init'] ?? false);
        
        $unreadCount = countUnreadNotifications($userId);
        $chatUnreadCount = countUnreadChatMessages($userId);
        
        // Get max notification id for today
        $stmtMax = $db->prepare("SELECT MAX(id) FROM notifications WHERE user_id = ? AND DATE(created_at) = CURDATE()");
        $stmtMax->execute([$userId]);
        $maxId = (int)$stmtMax->fetchColumn();
        
        if ($isInit || $lastId <= 0) {
            echo json_encode([
                'success' => true,
                'max_id' => $maxId,
                'unread_count' => $unreadCount,
                'chat_unread_count' => $chatUnreadCount,
                'new_notifications' => []
            ]);
            exit;
        }
        
        // Fetch newly arrived notifications since last_id (strictly today's)
        $stmt = $db->prepare("
            SELECT * FROM notifications 
            WHERE user_id = ? AND id > ? AND DATE(created_at) = CURDATE()
            ORDER BY id ASC
            LIMIT 15
        ");
        $stmt->execute([$userId, $lastId]);
        $rows = $stmt->fetchAll();
        
        $newNotifs = [];
        foreach ($rows as $r) {
            $rawTitle = $r['title'];
            $icon = 'fa-solid fa-bell';
            $category = $r['type'] ?: 'info';
            
            // Smart icon & category detection
            if (stripos($rawTitle, 'chat') !== false || stripos($rawTitle, 'message') !== false || stripos($rawTitle, 'group') !== false || stripos($rawTitle, 'dm') !== false) {
                $icon = 'fa-solid fa-comments';
                $category = 'chat';
            } elseif (stripos($rawTitle, 'meeting') !== false) {
                $icon = 'fa-solid fa-calendar-check';
                $category = 'meeting';
            } elseif (stripos($rawTitle, 'ticket') !== false || stripos($rawTitle, 'support') !== false) {
                $icon = 'fa-solid fa-headset';
                $category = 'ticket';
            } elseif (stripos($rawTitle, 'leave') !== false) {
                $icon = 'fa-solid fa-plane-departure';
                $category = 'leave';
            } elseif (stripos($rawTitle, 'announcement') !== false || stripos($rawTitle, 'broadcast') !== false) {
                $icon = 'fa-solid fa-bullhorn';
                $category = 'announcement';
            } elseif (stripos($rawTitle, 'login') !== false || stripos($rawTitle, 'check-in') !== false || stripos($rawTitle, 'signed in') !== false) {
                $icon = 'fa-solid fa-right-to-bracket';
                $category = 'auth';
            } elseif (stripos($rawTitle, 'logout') !== false || stripos($rawTitle, 'check-out') !== false) {
                $icon = 'fa-solid fa-arrow-right-from-bracket';
                $category = 'auth';
            } elseif (stripos($rawTitle, 'task') !== false) {
                $icon = 'fa-solid fa-list-check';
                $category = 'task';
            }
            
            $newNotifs[] = [
                'id' => (int)$r['id'],
                'title' => trim(strip_tags($r['title'])),
                'message' => trim(strip_tags($r['message'])),
                'link' => $r['link'] ?: '',
                'type' => $r['type'],
                'category' => $category,
                'icon' => $icon,
                'time_ago' => timeAgo($r['created_at']),
                'created_at' => $r['created_at']
            ];
        }
        
        echo json_encode([
            'success' => true,
            'max_id' => $maxId > $lastId ? $maxId : $lastId,
            'unread_count' => $unreadCount,
            'chat_unread_count' => $chatUnreadCount,
            'new_notifications' => $newNotifs
        ]);
        exit;
    } elseif ($action === 'get_unread') {
        $unreadCount = countUnreadNotifications($userId);
        $chatUnreadCount = countUnreadChatMessages($userId);
        $notifs = getUnreadNotifications($userId, 7);
        echo json_encode([
            'success' => true,
            'count' => $unreadCount,
            'chat_unread_count' => $chatUnreadCount,
            'notifications' => $notifs
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action.']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error.']);
}
