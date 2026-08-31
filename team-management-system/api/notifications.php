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
        
    } elseif ($action === 'get_unread') {
        $unreadCount = countUnreadNotifications($userId);
        $notifs = getUnreadNotifications($userId, 7);
        echo json_encode([
            'success' => true,
            'count' => $unreadCount,
            'notifications' => $notifs
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action.']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error.']);
}
