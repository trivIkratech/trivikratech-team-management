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
 * Format rich markdown messages, code blocks, bullet/numbered lists, blockquotes, 
 * URLs, @mentions, bold, italics, strikethrough, and line breaks inside chat messages.
 */
function formatChatMessage(?string $text, array $activeUserNames = []): string {
    if ($text === null || $text === '') return '';
    
    // 0. Escape HTML first to prevent XSS
    $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    
    // 1. Code blocks: ```[lang]\n[code]\n```
    $codeBlocks = [];
    $text = preg_replace_callback('/```([a-zA-Z0-9_\-\+]*)\r?\n?([\s\S]*?)```/m', function($m) use (&$codeBlocks) {
        $placeholder = '###CHAT_CODE_BLOCK_' . count($codeBlocks) . '###';
        $lang = !empty($m[1]) ? htmlspecialchars($m[1], ENT_QUOTES, 'UTF-8') : 'CODE';
        $code = trim($m[2]);
        $codeHtml = '<pre class="chat-code-block"><div class="chat-code-header"><span><i class="fa-solid fa-code" style="margin-right:4px;"></i>' . $lang . '</span><button type="button" class="chat-copy-btn" onclick="copyChatCode(this)"><i class="fa-regular fa-copy"></i> Copy</button></div><code>' . $code . '</code></pre>';
        $codeBlocks[$placeholder] = $codeHtml;
        return $placeholder;
    }, $text);
    
    // 2. Inline code: `code`
    $inlineCodes = [];
    $text = preg_replace_callback('/`([^`\r\n]+)`/', function($m) use (&$inlineCodes) {
        $placeholder = '###CHAT_INLINE_CODE_' . count($inlineCodes) . '###';
        $inlineCodes[$placeholder] = '<code class="chat-inline-code">' . $m[1] . '</code>';
        return $placeholder;
    }, $text);

    // 3. Markdown links: [Title](url)
    $text = preg_replace('/\[([^\]\r\n]+)\]\((https?:\/\/[^\)\s]+)\)/', '<a href="$2" target="_blank" rel="noopener" class="chat-link"><i class="fa-solid fa-link" style="font-size: 10px; margin-right: 3px;"></i>$1</a>', $text);

    // 4. Auto-link plain URLs
    $urlPattern = '/(?<!href=")(?<!href=\')(https?:\/\/[^\s<"\'\)]+)/i';
    $text = preg_replace($urlPattern, '<a href="$1" target="_blank" rel="noopener" class="chat-link"><i class="fa-solid fa-arrow-up-right-from-square" style="margin-right: 3px; font-size: 10px;"></i>$1</a>', $text);

    // 5. Format @mentions
    if (!empty($activeUserNames)) {
        usort($activeUserNames, fn($a, $b) => strlen($b['name']) <=> strlen($a['name']));
        foreach ($activeUserNames as $u) {
            $uName = htmlspecialchars($u['name'], ENT_QUOTES, 'UTF-8');
            $mentionPattern = '/@' . preg_quote($uName, '/') . '\b/i';
            $text = preg_replace($mentionPattern, '<span class="chat-mention" data-user-id="' . $u['id'] . '"><i class="fa-solid fa-at"></i>' . $uName . '</span>', $text);
        }
    } else {
        $text = preg_replace('/@([a-zA-Z0-9_\.\-]+)/', '<span class="chat-mention"><i class="fa-solid fa-at"></i>$1</span>', $text);
    }

    // 6. Bold **bold** or *bold*
    $text = preg_replace('/\*\*([^\*\r\n]+)\*\*/', '<strong>$1</strong>', $text);
    $text = preg_replace('/(?<=^|\s|[.,!?;:(\[])\*([^\*\r\n\s]+(?:\s+[^\*\r\n\s]+)*)\*(?=\s|$|[.,!?;:)\]])/', '<strong>$1</strong>', $text);

    // 7. Italic _italic_
    $text = preg_replace('/(?<=^|\s|[.,!?;:(\[])_([^_\r\n\s]+(?:\s+[^_\r\n\s]+)*)_(?=\s|$|[.,!?;:)\]])/', '<em>$1</em>', $text);

    // 8. Strikethrough ~~strike~~ or ~strike~
    $text = preg_replace('/~~([^~\r\n]+)~~/', '<del>$1</del>', $text);
    $text = preg_replace('/(?<=^|\s|[.,!?;:(\[])~([^~\r\n\s]+(?:\s+[^~\r\n\s]+)*)~(?=\s|$|[.,!?;:)\]])/', '<del>$1</del>', $text);

    // 9. Block elements (Blockquotes, Lists, Paragraphs)
    $rawLines = explode("\n", str_replace("\r\n", "\n", $text));
    $outBlocks = [];
    $inQuote = false;
    $quoteLines = [];
    $inList = false;
    $listType = 'ul';
    $listItems = [];

    $flushQuote = function() use (&$inQuote, &$quoteLines, &$outBlocks) {
        if ($inQuote) {
            while (!empty($quoteLines) && trim($quoteLines[0]) === '') {
                array_shift($quoteLines);
            }
            while (!empty($quoteLines) && trim(end($quoteLines)) === '') {
                array_pop($quoteLines);
            }
            if (!empty($quoteLines)) {
                $content = '';
                $prevWasEmpty = false;
                foreach ($quoteLines as $ql) {
                    $trimmed = trim($ql);
                    if ($trimmed === '') {
                        if (!$prevWasEmpty && !empty($content)) {
                            $content .= '<br><br>';
                            $prevWasEmpty = true;
                        }
                    } else {
                        if (!empty($content) && !$prevWasEmpty) {
                            $content .= '<br>';
                        }
                        $content .= $trimmed;
                        $prevWasEmpty = false;
                    }
                }
                $outBlocks[] = '<blockquote class="chat-blockquote">' . $content . '</blockquote>';
            }
            $inQuote = false;
            $quoteLines = [];
        }
    };

    $flushList = function() use (&$inList, &$listType, &$listItems, &$outBlocks) {
        if ($inList) {
            if (!empty($listItems)) {
                $outBlocks[] = '<' . $listType . ' class="chat-list">' . implode('', $listItems) . '</' . $listType . '>';
            }
            $inList = false;
            $listItems = [];
        }
    };

    foreach ($rawLines as $line) {
        if (preg_match('/^(?:&gt;|>)[ ]?(.*)$/', $line, $qm)) {
            $flushList();
            $inQuote = true;
            $quoteLines[] = $qm[1];
        } else {
            $flushQuote();
            if (preg_match('/^[ ]*[-*•▪▫◆✓➢–—][ ]+(.+)$/u', $line, $lm)) {
                if (!$inList || $listType !== 'ul') {
                    $flushList();
                    $inList = true;
                    $listType = 'ul';
                }
                $listItems[] = '<li>' . $lm[1] . '</li>';
            } elseif (preg_match('/^[ ]*(?:[0-9]+[.)]|\([0-9]+\)|[a-zA-Z][.)]|\([a-zA-Z]\))[ ]+(.+)$/u', $line, $lm)) {
                if (!$inList || $listType !== 'ol') {
                    $flushList();
                    $inList = true;
                    $listType = 'ol';
                }
                $listItems[] = '<li>' . $lm[1] . '</li>';
            } else {
                $flushList();
                $outBlocks[] = $line;
            }
        }
    }
    $flushQuote();
    $flushList();

    // 10. Assemble final text with line breaks between non-block lines
    $finalText = '';
    foreach ($outBlocks as $idx => $block) {
        $isBlockElem = str_starts_with($block, '<ul') || str_starts_with($block, '<ol') || str_starts_with($block, '<blockquote') || str_starts_with($block, '<pre');
        if ($isBlockElem) {
            $finalText .= $block;
        } else {
            $prevWasBlock = false;
            if ($idx > 0) {
                $prevBlock = $outBlocks[$idx - 1];
                $prevWasBlock = str_starts_with($prevBlock, '<ul') || str_starts_with($prevBlock, '<ol') || str_starts_with($prevBlock, '<blockquote') || str_starts_with($prevBlock, '<pre');
            }
            if ($idx > 0 && !empty($finalText) && !$prevWasBlock) {
                $finalText .= '<br>' . $block;
            } else {
                $finalText .= $block;
            }
        }
    }
    $text = $finalText;

    // 12. Restore code blocks & inline codes
    foreach ($codeBlocks as $placeholder => $codeHtml) {
        $text = str_replace($placeholder, $codeHtml, $text);
    }
    foreach ($inlineCodes as $placeholder => $inlineHtml) {
        $text = str_replace($placeholder, $inlineHtml, $text);
    }

    return $text;
}

// Ensure required chat columns and presence schema are ready (self-healing migration)
ensureChatSchemaReady($db);

// Auto update activity timestamp for logged in user (if presence columns exist)
try {
    if (hasUserPresenceColumns($db)) {
        $db->prepare("UPDATE users SET last_active_at = NOW() WHERE id = ?")->execute([$currentUserId]);
    }
} catch (Exception $e) {}

/**
 * Ensure all required chat tables and presence columns exist (Self-healing migration)
 */
function ensureChatSchemaReady(PDO $db): void {
    static $ready = false;
    if ($ready) return;

    // 1. Ensure presence & activity columns in `users`
    $userCols = [
        'presence_status'     => "ALTER TABLE `users` ADD COLUMN `presence_status` ENUM('automatic','dnd','away','offline') NOT NULL DEFAULT 'automatic'",
        'custom_status_text'  => "ALTER TABLE `users` ADD COLUMN `custom_status_text` VARCHAR(255) NULL DEFAULT NULL",
        'custom_status_emoji' => "ALTER TABLE `users` ADD COLUMN `custom_status_emoji` VARCHAR(50) NULL DEFAULT NULL",
        'last_active_at'      => "ALTER TABLE `users` ADD COLUMN `last_active_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
        'last_activity'       => "ALTER TABLE `users` ADD COLUMN `last_activity` TIMESTAMP NULL DEFAULT NULL"
    ];
    foreach ($userCols as $col => $sql) {
        try {
            $chk = $db->query("SHOW COLUMNS FROM `users` LIKE '$col'");
            if ($chk && !$chk->fetch()) {
                $db->exec($sql);
            }
        } catch (Throwable $e) {
            try { $db->exec($sql); } catch (Throwable $ignored) {}
        }
    }

    // 2. Ensure chat_room_members has last_read_message_id
    try {
        $chk = $db->query("SHOW COLUMNS FROM `chat_room_members` LIKE 'last_read_message_id'");
        if ($chk && !$chk->fetch()) {
            $db->exec("ALTER TABLE `chat_room_members` ADD COLUMN `last_read_message_id` INT NOT NULL DEFAULT 0");
        }
    } catch (Throwable $e) {
        try { $db->exec("ALTER TABLE `chat_room_members` ADD COLUMN `last_read_message_id` INT NOT NULL DEFAULT 0"); } catch (Throwable $ignored) {}
    }

    // 3. Ensure chat_messages has is_edited and edited_at
    $msgCols = [
        'is_edited' => "ALTER TABLE `chat_messages` ADD COLUMN `is_edited` TINYINT(1) DEFAULT 0",
        'edited_at' => "ALTER TABLE `chat_messages` ADD COLUMN `edited_at` TIMESTAMP NULL DEFAULT NULL"
    ];
    foreach ($msgCols as $col => $sql) {
        try {
            $chk = $db->query("SHOW COLUMNS FROM `chat_messages` LIKE '$col'");
            if ($chk && !$chk->fetch()) {
                $db->exec($sql);
            }
        } catch (Throwable $e) {
            try { $db->exec($sql); } catch (Throwable $ignored) {}
        }
    }

    // 4. Ensure #General Team Chat (Room 1) exists and active users are members
    try {
        $chkRoom = $db->query("SELECT id FROM `chat_rooms` WHERE id = 1")->fetch();
        if (!$chkRoom) {
            $db->exec("INSERT IGNORE INTO `chat_rooms` (`id`, `name`, `type`, `created_by`, `created_at`) VALUES (1, 'General Team Chat', 'group', 1, NOW())");
        }
        $db->exec("INSERT IGNORE INTO `chat_room_members` (`room_id`, `user_id`) SELECT 1, id FROM `users` WHERE status = 'active'");
    } catch (Throwable $e) {}

    $ready = true;
}

/**
 * Check if users table has presence columns
 */
function hasUserPresenceColumns(PDO $db): bool {
    static $hasCols = null;
    if ($hasCols !== null) return $hasCols;
    try {
        $chk = $db->query("SHOW COLUMNS FROM `users` LIKE 'presence_status'");
        $hasCols = ($chk && $chk->fetch()) ? true : false;
    } catch (Throwable $e) {
        $hasCols = false;
    }
    return $hasCols;
}

/**
 * Safely generate SELECT clause for user presence columns
 */
function getUserPresenceSelectFields(PDO $db, string $alias = ''): string {
    $prefix = $alias ?: '';
    if (hasUserPresenceColumns($db)) {
        return "{$prefix}presence_status, {$prefix}custom_status_text, {$prefix}custom_status_emoji, {$prefix}last_active_at";
    }
    return "'automatic' AS presence_status, NULL AS custom_status_text, NULL AS custom_status_emoji, NULL AS last_active_at";
}

/**
 * Compute user real-time presence (online, dnd, away, offline) and status text
 */
function computeUserPresence(array $user): array {
    $status = $user['presence_status'] ?? 'automatic';
    $lastActive = !empty($user['last_active_at']) ? strtotime($user['last_active_at']) : 0;
    $diffSeconds = time() - $lastActive;
    
    $computed = 'offline';
    $statusLabel = 'Offline';
    
    if ($status === 'dnd') {
        $computed = 'dnd';
        $statusLabel = 'Do not disturb';
    } elseif ($status === 'away') {
        $computed = 'away';
        $statusLabel = 'Set as away';
    } elseif ($status === 'offline') {
        $computed = 'offline';
        $statusLabel = 'Offline';
    } else { // automatic
        if ($diffSeconds <= 180) { // active within last 3 minutes
            $computed = 'online';
            $statusLabel = 'Active';
        } elseif ($diffSeconds <= 600) { // 3-10 mins idle
            $computed = 'away';
            $statusLabel = 'Away';
        } else {
            $computed = 'offline';
            $statusLabel = 'Offline';
        }
    }
    
    return [
        'presence_status' => $status,
        'computed_status' => $computed,
        'status_label' => $statusLabel,
        'custom_status_text' => $user['custom_status_text'] ?? '',
        'custom_status_emoji' => $user['custom_status_emoji'] ?? '',
        'last_active_at' => $user['last_active_at'] ?? null,
        'last_seen_formatted' => $lastActive > 0 ? timeAgo($user['last_active_at']) : 'Offline'
    ];
}

try {
    if ($action === 'get_rooms') {
        // Ensure all active users are enrolled in #General Team Chat (room 1)
        try {
            $db->exec("INSERT IGNORE INTO chat_room_members (room_id, user_id) SELECT 1, id FROM users WHERE status = 'active'");
        } catch (Exception $e) {}
        
        $myPresenceFields = getUserPresenceSelectFields($db, '');
        $uPresenceFields = getUserPresenceSelectFields($db, 'u.');

        // Fetch current user details with presence
        $stmtMe = $db->prepare("SELECT id, name, role, designation, {$myPresenceFields} FROM users WHERE id = ?");
        $stmtMe->execute([$currentUserId]);
        $meData = $stmtMe->fetch() ?: $currentUser;
        $myPresence = computeUserPresence($meData);

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
        
        // For direct rooms, format partner name, avatar, and presence
        foreach ($rooms as &$room) {
            if ($room['type'] === 'direct') {
                $pStmt = $db->prepare("
                    SELECT u.id, u.name, u.role, u.designation, {$uPresenceFields}
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
                    $room['presence'] = computeUserPresence($partner);
                }
            } else {
                $room['initials'] = ($room['id'] == 1) ? '<i class="fa-solid fa-bullhorn"></i>' : '<i class="fa-solid fa-users"></i>';
            }
            $room['formatted_time'] = $room['last_time'] ? timeAgo($room['last_time']) : '';
            $room['can_clear_history'] = ($room['type'] === 'direct' || $room['created_by'] == $currentUserId || $userRole === ROLE_FOUNDER);
        }
        
        // Fetch ALL specific persons categorized by role with presence and unread counts
        $uStmt = $db->prepare("
            SELECT u.id, u.name, u.role, u.designation, {$uPresenceFields},
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
            $u['presence'] = computeUserPresence($u);
        }
        
        echo json_encode([
            'success' => true,
            'rooms' => $rooms,
            'users' => $allUsers,
            'current_user' => [
                'id' => $currentUserId,
                'name' => $meData['name'],
                'role' => $meData['role'],
                'designation' => $meData['designation'] ?: ucfirst($meData['role']),
                'initials' => getInitials($meData['name']),
                'presence' => $myPresence
            ],
            'current_user_id' => $currentUserId,
            'current_user_role' => $userRole
        ]);
        exit;
        
    } elseif ($action === 'update_presence') {
        $presenceStatus = post('presence_status');
        $customText = post('custom_status_text');
        $customEmoji = post('custom_status_emoji');
        
        $validStatuses = ['automatic', 'dnd', 'away', 'offline'];
        if (!in_array($presenceStatus, $validStatuses, true)) {
            $presenceStatus = 'automatic';
        }

        if (hasUserPresenceColumns($db)) {
            $stmt = $db->prepare("
                UPDATE users 
                SET presence_status = ?,
                    custom_status_text = ?,
                    custom_status_emoji = ?,
                    last_active_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([
                $presenceStatus,
                $customText !== null ? trim($customText) : null,
                $customEmoji !== null ? trim($customEmoji) : null,
                $currentUserId
            ]);
        }

        $myPresenceFields = getUserPresenceSelectFields($db, '');
        $stmtMe = $db->prepare("SELECT id, name, role, designation, {$myPresenceFields} FROM users WHERE id = ?");
        $stmtMe->execute([$currentUserId]);
        $updatedUser = $stmtMe->fetch() ?: $currentUser;

        echo json_encode([
            'success' => true,
            'presence' => computeUserPresence($updatedUser),
            'message' => 'Status updated successfully.'
        ]);
        exit;
        
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
        
        // Get room type & partner read progress
        $rTypeStmt = $db->prepare("SELECT type FROM chat_rooms WHERE id = ?");
        $rTypeStmt->execute([$roomId]);
        $roomType = $rTypeStmt->fetchColumn() ?: 'group';

        $partnerLastReadId = 0;
        $pStmt = $db->prepare("SELECT COALESCE(MAX(last_read_message_id), 0) FROM chat_room_members WHERE room_id = ? AND user_id != ?");
        $pStmt->execute([$roomId, $currentUserId]);
        $partnerLastReadId = (int)$pStmt->fetchColumn();

        $maxReadSentStmt = $db->prepare("SELECT COALESCE(MAX(id), 0) FROM chat_messages WHERE room_id = ? AND sender_id = ? AND is_read = 1");
        $maxReadSentStmt->execute([$roomId, $currentUserId]);
        $maxReadSentId = (int)$maxReadSentStmt->fetchColumn();

        $effectivePartnerReadId = max($partnerLastReadId, $maxReadSentId);
        
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
            $msg['is_read'] = ((int)($msg['is_read'] ?? 0) === 1 || ($effectivePartnerReadId > 0 && (int)$msg['id'] <= $effectivePartnerReadId)) ? 1 : 0;
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
            'partner_last_read_id' => $effectivePartnerReadId,
            'room_type' => $roomType,
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
            'is_read' => 0,
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
        
        $allUsersStmt = $db->query("SELECT id, name FROM users WHERE status = 'active'");
        $activeUsers = $allUsersStmt->fetchAll();
        $formattedHtml = formatChatMessage($newMessage, $activeUsers);
        
        echo json_encode([
            'success' => true,
            'message' => 'Message updated successfully.',
            'message_id' => $messageId,
            'new_text' => $newMessage,
            'formatted_html' => $formattedHtml
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
