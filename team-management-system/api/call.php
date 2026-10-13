<?php
/**
 * API — 100% Free WebRTC Internal Audio & Video Calling Signaling Engine
 * 
 * Handles:
 * - Call initiation (SDP offer)
 * - Incoming call checking & polling
 * - Call answering (SDP answer)
 * - ICE candidate exchange (NAT traversal)
 * - Call status checks (Ringing -> Connected -> Ended)
 * - Call rejection & Missed call handling
 * - Call history & Call duration logs
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
$action = post('action') ?: get('action');

/**
 * Ensure WebRTC calling signaling tables exist (Self-healing migration)
 */
function ensureCallingSchemaReady(PDO $db): void {
    static $ready = false;
    if ($ready) return;
    try {
        $db->exec("
            CREATE TABLE IF NOT EXISTS `call_sessions` (
              `id` int NOT NULL AUTO_INCREMENT,
              `caller_id` int NOT NULL,
              `receiver_id` int NOT NULL,
              `room_id` int DEFAULT NULL,
              `call_type` enum('audio','video') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'audio',
              `status` enum('ringing','connected','rejected','busy','missed','ended') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ringing',
              `sdp_offer` mediumtext COLLATE utf8mb4_unicode_ci,
              `sdp_answer` mediumtext COLLATE utf8mb4_unicode_ci,
              `started_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
              `connected_at` timestamp NULL DEFAULT NULL,
              `ended_at` timestamp NULL DEFAULT NULL,
              `duration_seconds` int DEFAULT '0',
              PRIMARY KEY (`id`),
              KEY `idx_caller_id` (`caller_id`),
              KEY `idx_receiver_id` (`receiver_id`),
              KEY `idx_status` (`status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
        $db->exec("
            CREATE TABLE IF NOT EXISTS `call_signals` (
              `id` int NOT NULL AUTO_INCREMENT,
              `call_id` int NOT NULL,
              `sender_id` int NOT NULL,
              `candidate` text COLLATE utf8mb4_unicode_ci NOT NULL,
              `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              KEY `idx_call_id` (`call_id`),
              KEY `idx_sender_id` (`sender_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
    } catch (Throwable $e) {}
    $ready = true;
}

ensureCallingSchemaReady($db);

// Clean up stale ringing calls older than 45 seconds automatically
try {
    $db->exec("
        UPDATE call_sessions 
        SET status = 'missed', ended_at = NOW() 
        WHERE status = 'ringing' AND started_at < (NOW() - INTERVAL 45 SECOND)
    ");
} catch (Exception $e) {
    // Ignore cleanup error
}

switch ($action) {

    /**
     * INITIATE CALL
     * Caller sends SDP Offer, call_type (audio/video), receiver_id, and optional room_id
     */
    case 'initiate':
        $receiverId = (int)post('receiver_id');
        $callType = post('call_type') === 'video' ? 'video' : 'audio';
        $roomId = (int)post('room_id') ?: null;
        $sdpOffer = post('sdp_offer');

        if (!$receiverId || $receiverId === $currentUserId) {
            echo json_encode(['success' => false, 'message' => 'Invalid receiver.']);
            exit;
        }

        if (empty($sdpOffer)) {
            echo json_encode(['success' => false, 'message' => 'SDP offer is required.']);
            exit;
        }

        // Check if receiver exists and is active
        $stmtUser = $db->prepare("SELECT id, name, role, designation FROM users WHERE id = ? AND status = 'active'");
        $stmtUser->execute([$receiverId]);
        $receiver = $stmtUser->fetch();
        if (!$receiver) {
            echo json_encode(['success' => false, 'message' => 'User not found or inactive.']);
            exit;
        }

        // Check if receiver is already in an active connected call
        $stmtCheck = $db->prepare("
            SELECT id FROM call_sessions 
            WHERE (caller_id = ? OR receiver_id = ?) 
              AND status = 'connected'
            LIMIT 1
        ");
        $stmtCheck->execute([$receiverId, $receiverId]);
        if ($stmtCheck->fetch()) {
            echo json_encode(['success' => false, 'message' => 'User is currently on another call.', 'is_busy' => true]);
            exit;
        }

        // Cancel any lingering ringing calls between these two users
        $stmtCancel = $db->prepare("
            UPDATE call_sessions 
            SET status = 'missed', ended_at = NOW() 
            WHERE caller_id = ? AND status = 'ringing'
        ");
        $stmtCancel->execute([$currentUserId]);

        // Insert new call session
        $stmtInsert = $db->prepare("
            INSERT INTO call_sessions 
            (caller_id, receiver_id, room_id, call_type, status, sdp_offer, started_at) 
            VALUES (?, ?, ?, ?, 'ringing', ?, NOW())
        ");
        $stmtInsert->execute([$currentUserId, $receiverId, $roomId, $callType, $sdpOffer]);
        $callId = (int)$db->lastInsertId();

        echo json_encode([
            'success' => true,
            'call_id' => $callId,
            'receiver' => [
                'id' => $receiver['id'],
                'name' => $receiver['name'],
                'role' => $receiver['role'],
                'designation' => $receiver['designation']
            ],
            'call_type' => $callType,
            'status' => 'ringing'
        ]);
        exit;

    /**
     * CHECK INCOMING CALLS
     * Callee polls to see if anyone is calling them
     */
    case 'check_incoming':
        $stmt = $db->prepare("
            SELECT cs.id AS call_id, cs.caller_id, cs.call_type, cs.status, cs.started_at, cs.sdp_offer,
                   u.name AS caller_name, u.role AS caller_role, u.designation AS caller_designation
            FROM call_sessions cs
            JOIN users u ON cs.caller_id = u.id
            WHERE cs.receiver_id = ? 
              AND cs.status = 'ringing'
              AND cs.started_at >= (NOW() - INTERVAL 45 SECOND)
            ORDER BY cs.id DESC 
            LIMIT 1
        ");
        $stmt->execute([$currentUserId]);
        $incoming = $stmt->fetch();

        if ($incoming) {
            echo json_encode([
                'success' => true,
                'has_call' => true,
                'call' => [
                    'call_id' => (int)$incoming['call_id'],
                    'caller_id' => (int)$incoming['caller_id'],
                    'caller_name' => $incoming['caller_name'],
                    'caller_role' => $incoming['caller_role'],
                    'caller_designation' => $incoming['caller_designation'] ?: ucfirst($incoming['caller_role']),
                    'call_type' => $incoming['call_type'],
                    'sdp_offer' => $incoming['sdp_offer'],
                    'started_at' => $incoming['started_at']
                ]
            ]);
        } else {
            echo json_encode(['success' => true, 'has_call' => false]);
        }
        exit;

    /**
     * ANSWER CALL
     * Callee accepts call and provides SDP Answer
     */
    case 'answer':
        $callId = (int)post('call_id');
        $sdpAnswer = post('sdp_answer');

        if (!$callId || empty($sdpAnswer)) {
            echo json_encode(['success' => false, 'message' => 'Missing call ID or SDP answer.']);
            exit;
        }

        $stmt = $db->prepare("
            UPDATE call_sessions 
            SET status = 'connected', sdp_answer = ?, connected_at = NOW() 
            WHERE id = ? AND receiver_id = ? AND status = 'ringing'
        ");
        $stmt->execute([$sdpAnswer, $callId, $currentUserId]);

        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true, 'call_id' => $callId, 'status' => 'connected']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Call was cancelled or already answered.']);
        }
        exit;

    /**
     * REJECT CALL
     * Callee or Caller rejects/cancels the call
     */
    case 'reject':
        $callId = (int)post('call_id');
        if (!$callId) {
            echo json_encode(['success' => false, 'message' => 'Invalid call ID.']);
            exit;
        }

        $stmt = $db->prepare("
            UPDATE call_sessions 
            SET status = 'rejected', ended_at = NOW() 
            WHERE id = ? AND (receiver_id = ? OR caller_id = ?) AND status = 'ringing'
        ");
        $stmt->execute([$callId, $currentUserId, $currentUserId]);

        echo json_encode(['success' => true, 'message' => 'Call rejected.']);
        exit;

    /**
     * SEND ICE CANDIDATE
     * Peer uploads ICE candidate for NAT traversal
     */
    case 'send_ice':
        $callId = (int)post('call_id');
        $candidate = post('candidate');

        if (!$callId || empty($candidate)) {
            echo json_encode(['success' => false, 'message' => 'Missing call ID or candidate.']);
            exit;
        }

        $stmt = $db->prepare("
            INSERT INTO call_signals (call_id, sender_id, candidate) 
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$callId, $currentUserId, $candidate]);

        echo json_encode(['success' => true]);
        exit;

    /**
     * GET REMOTE ICE CANDIDATES
     * Peer downloads remote ICE candidates from the other participant
     */
    case 'get_ice':
        $callId = (int)get('call_id') ?: (int)post('call_id');
        $lastSignalId = (int)get('last_signal_id') ?: (int)post('last_signal_id');

        if (!$callId) {
            echo json_encode(['success' => false, 'message' => 'Missing call ID.']);
            exit;
        }

        $stmt = $db->prepare("
            SELECT id, candidate, created_at 
            FROM call_signals 
            WHERE call_id = ? AND sender_id != ? AND id > ? 
            ORDER BY id ASC
        ");
        $stmt->execute([$callId, $currentUserId, $lastSignalId]);
        $signals = $stmt->fetchAll();

        echo json_encode([
            'success' => true,
            'signals' => $signals
        ]);
        exit;

    /**
     * GET CALL STATUS / POLL CALL
     * Checks if remote peer answered, rejected, or ended call
     */
    case 'get_status':
        $callId = (int)get('call_id') ?: (int)post('call_id');
        if (!$callId) {
            echo json_encode(['success' => false, 'message' => 'Missing call ID.']);
            exit;
        }

        $stmt = $db->prepare("
            SELECT id, caller_id, receiver_id, call_type, status, sdp_answer, media_state, started_at, connected_at, ended_at, duration_seconds
            FROM call_sessions 
            WHERE id = ? AND (caller_id = ? OR receiver_id = ?)
        ");
        $stmt->execute([$callId, $currentUserId, $currentUserId]);
        $call = $stmt->fetch();

        if (!$call) {
            echo json_encode(['success' => false, 'status' => 'ended', 'message' => 'Call not found.']);
            exit;
        }

        echo json_encode([
            'success' => true,
            'status' => $call['status'],
            'call_type' => $call['call_type'],
            'sdp_answer' => $call['sdp_answer'],
            'media_state' => $call['media_state'],
            'connected_at' => $call['connected_at'],
            'duration_seconds' => (int)$call['duration_seconds']
        ]);
        exit;

    /**
     * UPDATE MEDIA STATE (e.g. Screen sharing on/off)
     */
    case 'update_state':
        $callId = (int)post('call_id');
        $mediaState = post('media_state');

        if (!$callId) {
            echo json_encode(['success' => false, 'message' => 'Missing call ID.']);
            exit;
        }

        $stmt = $db->prepare("
            UPDATE call_sessions 
            SET media_state = ? 
            WHERE id = ? AND (caller_id = ? OR receiver_id = ?)
        ");
        $stmt->execute([$mediaState, $callId, $currentUserId, $currentUserId]);

        echo json_encode(['success' => true]);
        exit;

    /**
     * END CALL
     * Hangs up the call and computes duration
     */
    case 'end':
        $callId = (int)post('call_id');
        if (!$callId) {
            echo json_encode(['success' => false, 'message' => 'Invalid call ID.']);
            exit;
        }

        $stmt = $db->prepare("
            UPDATE call_sessions 
            SET status = 'ended', 
                ended_at = NOW(),
                duration_seconds = CASE 
                    WHEN connected_at IS NOT NULL THEN TIMESTAMPDIFF(SECOND, connected_at, NOW())
                    ELSE 0 
                END
            WHERE id = ? AND (caller_id = ? OR receiver_id = ?) AND status IN ('ringing', 'connected')
        ");
        $stmt->execute([$callId, $currentUserId, $currentUserId]);

        // Clean up ICE candidate signals for this call
        try {
            $stmtDel = $db->prepare("DELETE FROM call_signals WHERE call_id = ?");
            $stmtDel->execute([$callId]);
        } catch (Exception $e) {}

        echo json_encode(['success' => true, 'message' => 'Call ended.']);
        exit;

    /**
     * GET CALL HISTORY / LOGS
     */
    case 'get_history':
        $limit = (int)get('limit') ?: 20;
        $stmt = $db->prepare("
            SELECT cs.id, cs.caller_id, cs.receiver_id, cs.call_type, cs.status, cs.started_at, cs.connected_at, cs.ended_at, cs.duration_seconds,
                   u_caller.name AS caller_name, u_caller.role AS caller_role,
                   u_rec.name AS receiver_name, u_rec.role AS receiver_role
            FROM call_sessions cs
            JOIN users u_caller ON cs.caller_id = u_caller.id
            JOIN users u_rec ON cs.receiver_id = u_rec.id
            WHERE cs.caller_id = ? OR cs.receiver_id = ?
            ORDER BY cs.id DESC 
            LIMIT " . min($limit, 50) . "
        ");
        $stmt->execute([$currentUserId, $currentUserId]);
        $history = $stmt->fetchAll();

        echo json_encode(['success' => true, 'history' => $history]);
        exit;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action specified.']);
        exit;
}
