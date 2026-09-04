<?php
/**
 * API — Real-Time Chat & Group Messaging Engine
 * 
 * Handles:
 * - Room directory & Custom Group creation
 * - 1-on-1 Direct Messaging
 * - Sending messages & file uploads
 * - Editing sent messages
 * - Deleting individual messages & clearing full room chat history
 * - @Person mentions & notifications
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
$currentUser = getCurrentUser();
$currentUserId = (int)$currentUser['id'];
$userRole = $currentUser['role'] ?? '';
$action = post('action') ?: get('action');

/**
 * Format clickable URLs and @mentions inside text messages
 */
function formatChatMessage(?string $text, array $activeUserNames = []): string {
    if (empty($text)) return '';
    $escaped = e($text);
    
    // 1. Auto-link URLs
    $urlPattern = '/(https?:\/\/[^\s<]+)/i';
    $formatted = preg_replace($urlPattern, '<a href="$1" target="_blank" rel="noopener" class="chat-link"><i class="fa-solid fa-arrow-up-right-from-square" style="margin-right: 3px; font-size: 10px;"></i>$1</a>', $escaped);
    
    // 2. Format @mentions
    if (!empty($activeUserNames)) {
        // Sort names by length descending so longer full names match first
        usort($activeUserNames, fn($a, $b) => strlen($b['name']) <=> strlen($a['name']));
        foreach ($activeUserNames as $u) {
            $uName = e($u['name']);
            // Case-insensitive replacement for @Name
            $mentionPattern = '/@' . preg_quote($uName, '/') . '\b/i';
            $formatted = preg_replace($mentionPattern, '<span class="chat-mention" data-user-id="' . $u['id'] . '"><i class="fa-solid fa-at"></i>' . $uName . '</span>', $formatted);
        }
    } else {
        // Generic fallback for @word
        $formatted = preg_replace('/@([a-zA-Z0-9_\.\-]+)/', '<span class="chat-mention"><i class="fa-solid fa-at"></i>$1</span>', $formatted);
    }
    
    return $formatted;
}

try {
    if ($action === 'get_rooms') {
        // Ensure all active users are enrolled in #General Team Chat (room 1)
        try {
            $db->exec("ALTER TABLE chat_room_members ADD COLUMN last_read_message_id INT NOT NULL DEFAULT 0");
        } catch (Exception $e) {}
        try {
            $db->exec("INSERT IGNORE INTO chat_room_members (room_id, user_id) SELECT 1, id FROM users WHERE status = 'active'");
        } catch (Exception $e) {}
        
        // Fetch all rooms for current user with accurate per-user unread counters
        $stmt = $db->prepare("
            SELECT r.id, r.name, r.type, r.created_by, r.created_at,
                   (SELECT COUNT(*) FROM chat_room_members crm WHERE crm.room_id = r.id) AS member_count,
                   (SELECT COUNT(*) FROM chat_messages m WHERE m.room_id = r.id AND m.sender_id != ? AND m.id > COALESCE(rm.last_read_message_id, 0)) AS unread_count,
                   (SELECT message FROM chat_messages m WHERE m.room_id = r.id ORDER BY m.id DESC LIMIT 1) AS last_message,
                   (SELECT created_at FROM chat_messages m WHERE m.room_id = r.id ORDER BY m.id DESC LIMIT 1) AS last_time
            FROM chat_rooms r
            JOIN chat_room_members rm ON r.id = rm.room_id
            WHERE rm.user_id = ?
            ORDER BY last_time DESC, r.id ASC
        ");
        $stmt->execute([$currentUserId, $currentUserId]);
        $rooms = $stmt->fetchAll();
        
        // For direct rooms, format partner name and avatar
        foreach ($rooms as &$room) {
            if ($room['type'] === 'direct') {
                $pStmt = $db->prepare("
                    SELECT u.id, u.name, u.role, u.designation 
                    FROM chat_room_members rm 
                    JOIN users u ON rm.user_id = u.id 
                    WHERE rm.room_id = ? AND rm.user_id != ? 
                    LIMIT 1
                ");
                $pStmt->execute([$room['id'], $currentUserId]);
                $partner = $pStmt->fetch();
                if ($partner) {
                    $room['name'] = $partner['name'];
                    $room['role'] = $partner['role'];
                    $room['designation'] = $partner['designation'];
                    $room['partner_id'] = $partner['id'];
                    $room['initials'] = getInitials($partner['name']);
                }
            } else {
                $room['initials'] = ($room['id'] == 1) ? '<i class="fa-solid fa-bullhorn"></i>' : '<i class="fa-solid fa-users"></i>';
            }
            $room['formatted_time'] = $room['last_time'] ? timeAgo($room['last_time']) : '';
            $room['can_clear_history'] = ($room['type'] === 'direct' || $room['created_by'] == $currentUserId || $userRole === ROLE_FOUNDER);
        }
        
        // Fetch ALL specific persons categorized by role with accurate per-user unread counts
        $uStmt = $db->prepare("
            SELECT u.id, u.name, u.role, u.designation,
                   (SELECT COUNT(*) 
                    FROM chat_messages m 
                    JOIN chat_rooms r ON m.room_id = r.id 
                    JOIN chat_room_members rm1 ON r.id = rm1.room_id AND rm1.user_id = u.id
                    JOIN chat_room_members rm2 ON r.id = rm2.room_id AND rm2.user_id = ?
                    WHERE r.type = 'direct' AND m.sender_id = u.id AND m.id > COALESCE(rm2.last_read_message_id, 0)) AS unread_count
            FROM users u 
            WHERE u.status = 'active' AND u.id != ?
            ORDER BY FIELD(u.role, 'founder', 'manager', 'hr', 'employee'), u.name ASC
        ");
        $uStmt->execute([$currentUserId, $currentUserId]);
        $allUsers = $uStmt->fetchAll();

        foreach ($allUsers as &$u) {
            $u['initials'] = getInitials($u['name']);
        }
        
        echo json_encode([
            'success' => true,
            'rooms' => $rooms,
            'users' => $allUsers,
            'current_user_id' => $currentUserId,
            'current_user_role' => $userRole
        ]);
        
    } elseif ($action === 'create_group') {
        $groupName = trim(post('group_name'));
        $memberIds = post('members'); // array or json
        
        if (empty($groupName)) {
            echo json_encode(['success' => false, 'message' => 'Group name is required.']);
            exit;
        }
        
        if (is_string($memberIds)) {
            $memberIds = json_decode($memberIds, true) ?: [];
        }
        if (!is_array($memberIds)) {
            $memberIds = [];
        }
        
        // Always include creator
        $memberIds[] = $currentUserId;
        $memberIds = array_unique(array_map('intval', array_filter($memberIds)));
        
        if (count($memberIds) < 2) {
            echo json_encode(['success' => false, 'message' => 'Please select at least 1 other member to form a group.']);
            exit;
        }
        
        $db->beginTransaction();
        
        $cStmt = $db->prepare("INSERT INTO chat_rooms (name, type, created_by, created_at) VALUES (?, 'group', ?, NOW())");
        $cStmt->execute([$groupName, $currentUserId]);
        $newRoomId = (int)$db->lastInsertId();
        
        $mStmt = $db->prepare("INSERT INTO chat_room_members (room_id, user_id, joined_at) VALUES (?, ?, NOW())");
        foreach ($memberIds as $mid) {
            $mStmt->execute([$newRoomId, $mid]);
            if ($mid !== $currentUserId) {
                createNotification(
                    $mid,
                    'Added to Group: ' . $groupName,
                    $currentUser['name'] . ' added you to a new team group chat.',
                    BASE_URL . '/chat/index.php?room_id=' . $newRoomId,
                    'info'
                );
            }
        }
        
        // Add initial system message
        $sysMsg = $db->prepare("INSERT INTO chat_messages (room_id, sender_id, message, is_read, created_at) VALUES (?, ?, ?, 1, NOW())");
        $sysMsg->execute([$newRoomId, $currentUserId, "Group \"{$groupName}\" created."]);
        
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Group created successfully.',
            'room_id' => $newRoomId
        ]);
        
    } elseif ($action === 'get_or_create_dm') {
        $partnerId = (int)post('partner_id');
        if ($partnerId <= 0 || $partnerId === $currentUserId) {
            echo json_encode(['success' => false, 'message' => 'Invalid partner user ID.']);
            exit;
        }
        
        // Check if a direct room already exists
        $stmt = $db->prepare("
            SELECT r.id 
            FROM chat_rooms r
            JOIN chat_room_members rm1 ON r.id = rm1.room_id AND rm1.user_id = ?
            JOIN chat_room_members rm2 ON r.id = rm2.room_id AND rm2.user_id = ?
            WHERE r.type = 'direct'
            LIMIT 1
        ");
        $stmt->execute([$currentUserId, $partnerId]);
        $existingRoom = $stmt->fetchColumn();
        
        if ($existingRoom) {
            echo json_encode(['success' => true, 'room_id' => (int)$existingRoom]);
        } else {
            // Create new direct room
            $db->beginTransaction();
            $cStmt = $db->prepare("INSERT INTO chat_rooms (type, created_by) VALUES ('direct', ?)");
            $cStmt->execute([$currentUserId]);
            $newRoomId = $db->lastInsertId();
            
            $mStmt = $db->prepare("INSERT INTO chat_room_members (room_id, user_id) VALUES (?, ?), (?, ?)");
            $mStmt->execute([$newRoomId, $currentUserId, $newRoomId, $partnerId]);
            $db->commit();
            
            echo json_encode(['success' => true, 'room_id' => (int)$newRoomId]);
        }
        
    } elseif ($action === 'get_messages') {
        $roomId = (int)get('room_id');
        $sinceId = (int)get('since_id');
        if ($roomId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid room ID.']);
            exit;
        }
        
        // Ensure user is member of room
        $checkMem = $db->prepare("SELECT COUNT(*) FROM chat_room_members WHERE room_id = ? AND user_id = ?");
        $checkMem->execute([$roomId, $currentUserId]);
        if ((int)$checkMem->fetchColumn() === 0 && $roomId !== 1) {
            // If room exists and is public general, add user, otherwise deny
            echo json_encode(['success' => false, 'message' => 'Access denied to this chat room.']);
            exit;
        }
        
        // Mark room messages as read for current user
        try {
            $db->prepare("
                UPDATE chat_room_members 
                SET last_read_message_id = (SELECT COALESCE(MAX(id), 0) FROM chat_messages WHERE room_id = ?) 
                WHERE room_id = ? AND user_id = ?
            ")->execute([$roomId, $roomId, $currentUserId]);
        } catch (Exception $e) {}
        $db->prepare("UPDATE chat_messages SET is_read = 1 WHERE room_id = ? AND sender_id != ?")->execute([$roomId, $currentUserId]);
        
        // Get active users for mention formatting
        $allUsersStmt = $db->query("SELECT id, name FROM users WHERE status = 'active'");
        $activeUsers = $allUsersStmt->fetchAll();
        
        if ($sinceId > 0) {
            $stmt = $db->prepare("
                SELECT m.*, u.name AS sender_name, u.role AS sender_role 
                FROM chat_messages m
                JOIN users u ON m.sender_id = u.id
                WHERE m.room_id = ? AND m.id > ?
                ORDER BY m.id ASC
                LIMIT 100
            ");
            $stmt->execute([$roomId, $sinceId]);
        } else {
            $stmt = $db->prepare("
                SELECT m.*, u.name AS sender_name, u.role AS sender_role 
                FROM chat_messages m
                JOIN users u ON m.sender_id = u.id
                WHERE m.room_id = ?
                ORDER BY m.id ASC
                LIMIT 150
            ");
            $stmt->execute([$roomId]);
        }
        $messages = $stmt->fetchAll();
        $maxId = $sinceId;
        
        foreach ($messages as &$msg) {
            $msg['formatted_html'] = formatChatMessage($msg['message'], $activeUsers);
            $msg['time'] = date('h:i A', strtotime($msg['created_at']));
            $msg['initials'] = getInitials($msg['sender_name']);
            $msg['is_self'] = ((int)$msg['sender_id'] === $currentUserId);
            $msg['can_edit'] = ($msg['is_self'] && empty($msg['file_path']));
            $msg['can_delete'] = ($msg['is_self'] || $userRole === ROLE_FOUNDER);
            if ($msg['file_path']) {
                $msg['file_url'] = BASE_URL . '/' . $msg['file_path'];
                $msg['is_image'] = str_starts_with($msg['file_type'] ?? '', 'image/');
            }
            if ((int)$msg['id'] > $maxId) {
                $maxId = (int)$msg['id'];
            }
        }
        
        echo json_encode([
            'success' => true,
            'messages' => $messages,
            'max_id' => $maxId,
            'is_incremental' => ($sinceId > 0)
        ]);
        
    } elseif ($action === 'send_message') {
        $roomId = (int)post('room_id');
        $messageText = trim(post('message'));
        
        if ($roomId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid room.']);
            exit;
        }
        
        // Handle optional file upload
        $filePath = null;
        $fileName = null;
        $fileType = null;
        
        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['attachment'];
            $maxSize = 25 * 1024 * 1024;
            if ($file['size'] > $maxSize) {
                echo json_encode(['success' => false, 'message' => 'File size exceeds 25MB limit.']);
                exit;
            }
            
            $allowedTypes = [
                'image/jpeg', 'image/png', 'image/gif', 'image/webp',
                'application/pdf', 'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/zip', 'application/x-zip-compressed', 'text/plain'
            ];
            
            $detectedType = mime_content_type($file['tmp_name']);
            if (!in_array($detectedType, $allowedTypes)) {
                echo json_encode(['success' => false, 'message' => 'File format not allowed. Only images, PDFs, Word docs, TXT, and ZIP files allowed.']);
                exit;
            }
            
            $uploadDir = __DIR__ . '/../uploads/chat/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $safeName = time() . '_' . uniqid() . '.' . strtolower($ext);
            $targetPath = $uploadDir . $safeName;
            
            if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                $filePath = 'uploads/chat/' . $safeName;
                $fileName = basename($file['name']);
                $fileType = $detectedType;
            }
        }
        
        if (empty($messageText) && empty($filePath)) {
            echo json_encode(['success' => false, 'message' => 'Message or file attachment is required.']);
            exit;
        }
        
        $stmt = $db->prepare("
            INSERT INTO chat_messages (room_id, sender_id, message, file_path, file_name, file_type, is_read, created_at)
            VALUES (?, ?, ?, ?, ?, ?, 0, NOW())
        ");
        $stmt->execute([$roomId, $currentUserId, $messageText ?: null, $filePath, $fileName, $fileType]);
        $msgId = (int)$db->lastInsertId();
        
        // 1. Detect and notify @mentioned users
        $mentionedUserIds = [];
        $allUsersStmt = $db->query("SELECT id, name FROM users WHERE status = 'active'");
        $activeUsers = $allUsersStmt->fetchAll();

        if (!empty($messageText)) {
            foreach ($activeUsers as $u) {
                if ($u['id'] == $currentUserId) continue;
                $uName = $u['name'];
                if (stripos($messageText, '@' . $uName) !== false) {
                    $mentionedUserIds[] = (int)$u['id'];
                    createNotification(
                        (int)$u['id'],
                        'Tagged by ' . $currentUser['name'] . ' in Chat',
                        '"' . substr($messageText, 0, 100) . '"',
                        BASE_URL . '/chat/index.php?room_id=' . $roomId,
                        'warning'
                    );
                }
            }
        }
        
        // 2. Notify other room members (who were not already notified as mentioned)
        if ($roomId === 1) {
            try {
                $db->exec("INSERT IGNORE INTO chat_room_members (room_id, user_id) SELECT 1, id FROM users WHERE status = 'active'");
            } catch(Exception $e) {}
        }
        $memStmt = $db->prepare("SELECT user_id FROM chat_room_members WHERE room_id = ? AND user_id != ?");
        $memStmt->execute([$roomId, $currentUserId]);
        $members = $memStmt->fetchAll(PDO::FETCH_COLUMN);
        
        $notifText = $filePath ? '<i class="fa-solid fa-paperclip"></i> Shared a file in chat' : substr($messageText, 0, 80);
        foreach ($members as $memId) {
            if (!in_array((int)$memId, $mentionedUserIds)) {
                createNotification(
                    (int)$memId, 
                    'New Message from ' . $currentUser['name'], 
                    $notifText, 
                    BASE_URL . '/chat/index.php?room_id=' . $roomId, 
                    'info'
                );
            }
        }

        // Construct fully formatted message payload for instant frontend sync
        $newMsgObj = [
            'id' => $msgId,
            'room_id' => $roomId,
            'sender_id' => $currentUserId,
            'sender_name' => $currentUser['name'],
            'sender_role' => $userRole,
            'message' => $messageText ?: '',
            'file_path' => $filePath,
            'file_name' => $fileName,
            'file_type' => $fileType,
            'created_at' => date('Y-m-d H:i:s'),
            'formatted_html' => formatChatMessage($messageText ?: '', $activeUsers),
            'time' => date('h:i A'),
            'initials' => getInitials($currentUser['name']),
            'is_self' => true,
            'can_edit' => empty($filePath),
            'can_delete' => true,
            'is_edited' => 0
        ];
        if ($filePath) {
            $newMsgObj['file_url'] = BASE_URL . '/' . $filePath;
            $newMsgObj['is_image'] = str_starts_with($fileType ?? '', 'image/');
        }
        
        echo json_encode([
            'success' => true,
            'message_id' => $msgId,
            'message' => $newMsgObj
        ]);
        
    } elseif ($action === 'edit_message') {
        $messageId = (int)post('message_id');
        $newMessage = trim(post('message'));
        
        if ($messageId <= 0 || empty($newMessage)) {
            echo json_encode(['success' => false, 'message' => 'Message ID and text are required.']);
            exit;
        }
        
        // Check ownership
        $stmt = $db->prepare("SELECT id, sender_id, room_id FROM chat_messages WHERE id = ?");
        $stmt->execute([$messageId]);
        $msg = $stmt->fetch();
        
        if (!$msg) {
            echo json_encode(['success' => false, 'message' => 'Message not found.']);
            exit;
        }
        
        if ((int)$msg['sender_id'] !== $currentUserId && $userRole !== ROLE_FOUNDER) {
            echo json_encode(['success' => false, 'message' => 'Permission denied. You can only edit your own messages.']);
            exit;
        }
        
        $updateStmt = $db->prepare("
            UPDATE chat_messages 
            SET message = ?, is_edited = 1, edited_at = NOW() 
            WHERE id = ?
        ");
        $updateStmt->execute([$newMessage, $messageId]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Message updated successfully.'
        ]);
        
    } elseif ($action === 'delete_message') {
        $messageId = (int)post('message_id');
        if ($messageId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid message ID.']);
            exit;
        }
        
        $stmt = $db->prepare("SELECT id, sender_id, file_path FROM chat_messages WHERE id = ?");
        $stmt->execute([$messageId]);
        $msg = $stmt->fetch();
        
        if (!$msg) {
            echo json_encode(['success' => false, 'message' => 'Message not found.']);
            exit;
        }
        
        if ((int)$msg['sender_id'] !== $currentUserId && $userRole !== ROLE_FOUNDER) {
            echo json_encode(['success' => false, 'message' => 'Permission denied to delete this message.']);
            exit;
        }
        
        // Delete attached file from server if present
        if (!empty($msg['file_path'])) {
            $absPath = __DIR__ . '/../' . $msg['file_path'];
            if (file_exists($absPath)) {
                @unlink($absPath);
            }
        }
        
        $delStmt = $db->prepare("DELETE FROM chat_messages WHERE id = ?");
        $delStmt->execute([$messageId]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Message deleted permanently.'
        ]);
        
    } elseif ($action === 'delete_room_history') {
        $roomId = (int)post('room_id');
        if ($roomId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid room ID.']);
            exit;
        }
        
        // Check if user is in room
        $stmt = $db->prepare("SELECT r.id, r.type, r.created_by FROM chat_rooms r JOIN chat_room_members rm ON r.id = rm.room_id WHERE r.id = ? AND rm.user_id = ?");
        $stmt->execute([$roomId, $currentUserId]);
        $room = $stmt->fetch();
        
        if (!$room && $userRole !== ROLE_FOUNDER) {
            echo json_encode(['success' => false, 'message' => 'Access denied or room not found.']);
            exit;
        }
        
        // Remove all physical files in this room
        $fileStmt = $db->prepare("SELECT file_path FROM chat_messages WHERE room_id = ? AND file_path IS NOT NULL");
        $fileStmt->execute([$roomId]);
        $files = $fileStmt->fetchAll(PDO::FETCH_COLUMN);
        foreach ($files as $fPath) {
            if ($fPath) {
                $abs = __DIR__ . '/../' . $fPath;
                if (file_exists($abs)) @unlink($abs);
            }
        }
        
        // Wipe all messages from database
        $delStmt = $db->prepare("DELETE FROM chat_messages WHERE room_id = ?");
        $delStmt->execute([$roomId]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Chat history completely deleted from database.'
        ]);
        
    } elseif ($action === 'get_group_members') {
        $roomId = (int)get('room_id');
        if ($roomId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid room ID.']);
            exit;
        }
        
        $rStmt = $db->prepare("SELECT id, name, type, created_by FROM chat_rooms WHERE id = ?");
        $rStmt->execute([$roomId]);
        $room = $rStmt->fetch();
        
        if (!$room || $room['type'] !== 'group') {
            echo json_encode(['success' => false, 'message' => 'Group room not found.']);
            exit;
        }
        
        $mStmt = $db->prepare("
            SELECT u.id, u.name, u.role, u.designation, u.email, rm.joined_at,
                   (u.id = ?) AS is_creator
            FROM chat_room_members rm
            JOIN users u ON rm.user_id = u.id
            WHERE rm.room_id = ?
            ORDER BY is_creator DESC, FIELD(u.role, 'founder', 'manager', 'hr', 'employee'), u.name ASC
        ");
        $mStmt->execute([$room['created_by'], $roomId]);
        $members = $mStmt->fetchAll();
        
        foreach ($members as &$m) {
            $m['initials'] = getInitials($m['name']);
            $m['joined_formatted'] = $m['joined_at'] ? formatDate($m['joined_at']) : '';
        }
        
        $canDeleteGroup = ($room['id'] != 1 && ($room['created_by'] == $currentUserId || $userRole === ROLE_FOUNDER));
        $isAdminOrFounder = ($room['created_by'] == $currentUserId || $userRole === ROLE_FOUNDER);
        
        // Get non-members to allow adding
        $currentMemberIds = array_column($members, 'id');
        $placeholders = implode(',', array_fill(0, count($currentMemberIds), '?'));
        $nonMemStmt = $db->prepare("SELECT id, name, role, designation FROM users WHERE status = 'active' AND id NOT IN ({$placeholders}) ORDER BY name ASC");
        $nonMemStmt->execute($currentMemberIds);
        $availableUsers = $nonMemStmt->fetchAll();
        
        echo json_encode([
            'success' => true,
            'group_name' => $room['name'],
            'room_id' => (int)$room['id'],
            'created_by' => (int)$room['created_by'],
            'can_delete_group' => $canDeleteGroup,
            'is_admin_or_founder' => $isAdminOrFounder,
            'members' => $members,
            'available_users' => $availableUsers
        ]);
        
    } elseif ($action === 'remove_group_member') {
        $roomId = (int)post('room_id');
        $targetUserId = (int)post('user_id');
        
        if ($roomId <= 1 || $targetUserId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid room or user ID.']);
            exit;
        }
        
        $rStmt = $db->prepare("SELECT id, name, type, created_by FROM chat_rooms WHERE id = ?");
        $rStmt->execute([$roomId]);
        $room = $rStmt->fetch();
        
        if (!$room || $room['type'] !== 'group') {
            echo json_encode(['success' => false, 'message' => 'Group not found.']);
            exit;
        }
        
        if ($room['created_by'] != $currentUserId && $userRole !== ROLE_FOUNDER) {
            echo json_encode(['success' => false, 'message' => 'Permission denied. Only group creator or Founder can remove members.']);
            exit;
        }
        
        if ($targetUserId === (int)$room['created_by']) {
            echo json_encode(['success' => false, 'message' => 'Cannot remove the group creator. Delete the group instead.']);
            exit;
        }
        
        // Fetch target user's name
        $uStmt = $db->prepare("SELECT name FROM users WHERE id = ?");
        $uStmt->execute([$targetUserId]);
        $targetName = $uStmt->fetchColumn() ?: 'Member';
        
        // Remove from group
        $delStmt = $db->prepare("DELETE FROM chat_room_members WHERE room_id = ? AND user_id = ?");
        $delStmt->execute([$roomId, $targetUserId]);
        
        // Add system log message
        $sysStmt = $db->prepare("INSERT INTO chat_messages (room_id, sender_id, message, is_read, created_at) VALUES (?, ?, ?, 1, NOW())");
        $sysStmt->execute([$roomId, $currentUserId, "{$targetName} was removed from the group."]);
        
        echo json_encode([
            'success' => true,
            'message' => "{$targetName} removed from the group successfully."
        ]);
        
    } elseif ($action === 'add_group_member') {
        $roomId = (int)post('room_id');
        $targetUserId = (int)post('user_id');
        
        if ($roomId <= 1 || $targetUserId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid room or user ID.']);
            exit;
        }
        
        $rStmt = $db->prepare("SELECT id, name, type, created_by FROM chat_rooms WHERE id = ?");
        $rStmt->execute([$roomId]);
        $room = $rStmt->fetch();
        
        if (!$room || $room['type'] !== 'group') {
            echo json_encode(['success' => false, 'message' => 'Group not found.']);
            exit;
        }
        
        if ($room['created_by'] != $currentUserId && $userRole !== ROLE_FOUNDER) {
            echo json_encode(['success' => false, 'message' => 'Permission denied. Only group creator or Founder can add members.']);
            exit;
        }
        
        // Add member
        $insStmt = $db->prepare("INSERT IGNORE INTO chat_room_members (room_id, user_id, joined_at) VALUES (?, ?, NOW())");
        $insStmt->execute([$roomId, $targetUserId]);
        
        // Fetch target user's name
        $uStmt = $db->prepare("SELECT name FROM users WHERE id = ?");
        $uStmt->execute([$targetUserId]);
        $targetName = $uStmt->fetchColumn() ?: 'Member';
        
        // Add system log message
        $sysStmt = $db->prepare("INSERT INTO chat_messages (room_id, sender_id, message, is_read, created_at) VALUES (?, ?, ?, 1, NOW())");
        $sysStmt->execute([$roomId, $currentUserId, "{$targetName} was added to the group."]);
        
        createNotification(
            $targetUserId,
            'Added to Group: ' . $room['name'],
            $currentUser['name'] . ' added you to group chat.',
            BASE_URL . '/chat/index.php?room_id=' . $roomId,
            'info'
        );
        
        echo json_encode([
            'success' => true,
            'message' => "{$targetName} added to the group successfully."
        ]);
        
    } elseif ($action === 'delete_group') {
        $roomId = (int)post('room_id');
        if ($roomId <= 1) { // Room 1 is #General, protected
            echo json_encode(['success' => false, 'message' => 'This group cannot be deleted.']);
            exit;
        }
        
        $rStmt = $db->prepare("SELECT id, name, type, created_by FROM chat_rooms WHERE id = ?");
        $rStmt->execute([$roomId]);
        $room = $rStmt->fetch();
        
        if (!$room || $room['type'] !== 'group') {
            echo json_encode(['success' => false, 'message' => 'Group not found.']);
            exit;
        }
        
        if ($room['created_by'] != $currentUserId && $userRole !== ROLE_FOUNDER) {
            echo json_encode(['success' => false, 'message' => 'Permission denied. Only the group creator or Founder can delete this group.']);
            exit;
        }
        
        // Remove physical files
        $fileStmt = $db->prepare("SELECT file_path FROM chat_messages WHERE room_id = ? AND file_path IS NOT NULL");
        $fileStmt->execute([$roomId]);
        $files = $fileStmt->fetchAll(PDO::FETCH_COLUMN);
        foreach ($files as $fPath) {
            if ($fPath) {
                $abs = __DIR__ . '/../' . $fPath;
                if (file_exists($abs)) @unlink($abs);
            }
        }
        
        $db->beginTransaction();
        $db->prepare("DELETE FROM chat_messages WHERE room_id = ?")->execute([$roomId]);
        $db->prepare("DELETE FROM chat_room_members WHERE room_id = ?")->execute([$roomId]);
        $db->prepare("DELETE FROM chat_rooms WHERE id = ?")->execute([$roomId]);
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Group deleted successfully.'
        ]);
        
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action.']);
    }
    
} catch (PDOException $e) {
    error_log("Chat API Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'A database error occurred in chat: ' . $e->getMessage()]);
}
