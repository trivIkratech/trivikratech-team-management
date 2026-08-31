<?php
/**
 * API — Real-Time Chat & File Sharing Engine
 * 
 * Handles room fetching, 1-on-1 DM creation, message sending,
 * URL auto-linking, file/image upload processing, and role-based user directory.
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
$currentUserId = $currentUser['id'];
$action = post('action') ?: get('action');

/**
 * Format clickable URLs inside text messages
 */
function formatChatMessage(?string $text): string {
    if (empty($text)) return '';
    $escaped = e($text);
    $pattern = '/(https?:\/\/[^\s<]+)/i';
    return preg_replace($pattern, '<a href="$1" target="_blank" rel="noopener" class="chat-link"><i class="fa-solid fa-arrow-up-right-from-square" style="margin-right: 3px; font-size: 10px;"></i>$1</a>', $escaped);
}

try {
    if ($action === 'get_rooms') {
        // Ensure user is in #General Team Chat (room 1)
        $db->prepare("INSERT IGNORE INTO chat_room_members (room_id, user_id) VALUES (1, ?)")->execute([$currentUserId]);
        
        // Fetch all rooms for current user
        $stmt = $db->prepare("
            SELECT r.id, r.name, r.type, r.created_at,
                   (SELECT COUNT(*) FROM chat_messages m WHERE m.room_id = r.id AND m.sender_id != ? AND m.is_read = 0) AS unread_count,
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
                $room['initials'] = '<i class="fa-solid fa-bullhorn"></i>';
            }
            $room['formatted_time'] = $room['last_time'] ? timeAgo($room['last_time']) : '';
        }
        
        // Fetch ALL specific persons categorized by role
        $uStmt = $db->prepare("
            SELECT u.id, u.name, u.role, u.designation,
                   (SELECT COUNT(*) 
                    FROM chat_messages m 
                    JOIN chat_rooms r ON m.room_id = r.id 
                    JOIN chat_room_members rm1 ON r.id = rm1.room_id AND rm1.user_id = u.id
                    JOIN chat_room_members rm2 ON r.id = rm2.room_id AND rm2.user_id = ?
                    WHERE r.type = 'direct' AND m.sender_id = u.id AND m.is_read = 0) AS unread_count
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
            'current_user_id' => $currentUserId
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
        if ($roomId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid room ID.']);
            exit;
        }
        
        // Mark room messages as read for current user
        $db->prepare("UPDATE chat_messages SET is_read = 1 WHERE room_id = ? AND sender_id != ?")->execute([$roomId, $currentUserId]);
        
        $stmt = $db->prepare("
            SELECT m.*, u.name AS sender_name, u.role AS sender_role 
            FROM chat_messages m
            JOIN users u ON m.sender_id = u.id
            WHERE m.room_id = ?
            ORDER BY m.id ASC
            LIMIT 100
        ");
        $stmt->execute([$roomId]);
        $messages = $stmt->fetchAll();
        
        foreach ($messages as &$msg) {
            $msg['formatted_html'] = formatChatMessage($msg['message']);
            $msg['time'] = date('h:i A', strtotime($msg['created_at']));
            $msg['initials'] = getInitials($msg['sender_name']);
            $msg['is_self'] = ((int)$msg['sender_id'] === $currentUserId);
            if ($msg['file_path']) {
                $msg['file_url'] = BASE_URL . '/' . $msg['file_path'];
                $msg['is_image'] = str_starts_with($msg['file_type'] ?? '', 'image/');
            }
        }
        
        echo json_encode([
            'success' => true,
            'messages' => $messages
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
        $msgId = $db->lastInsertId();
        
        // Notify room members
        $memStmt = $db->prepare("SELECT user_id FROM chat_room_members WHERE room_id = ? AND user_id != ?");
        $memStmt->execute([$roomId, $currentUserId]);
        $members = $memStmt->fetchAll(PDO::FETCH_COLUMN);
        
        $notifText = $filePath ? '<i class="fa-solid fa-paperclip"></i> Shared a file in chat' : substr($messageText, 0, 80);
        foreach ($members as $memId) {
            createNotification(
                (int)$memId, 
                '<i class="fa-solid fa-comments"></i> New Message from ' . $currentUser['name'], 
                $notifText, 
                BASE_URL . '/chat/index.php?room_id=' . $roomId, 
                'info'
            );
        }
        
        echo json_encode([
            'success' => true,
            'message_id' => $msgId
        ]);
        
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action.']);
    }
    
} catch (PDOException $e) {
    error_log("Chat API Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'A database error occurred in chat.']);
}
