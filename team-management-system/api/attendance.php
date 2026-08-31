<?php
/**
 * API — Attendance (Check-In, Start Break, End Break, Check-Out)
 * 
 * Handles AJAX requests for employee attendance & break management.
 * 7-Hour Shift Rule: 6 Working Hours + 1 Hour Break.
 * Status: >=6h = present, >=3h = half-day, <3h = absent.
 * Founder role is exempt from time limits.
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$csrfToken = $_POST['csrf_token'] ?? '';
if (empty($csrfToken) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrfToken)) {
    echo json_encode(['success' => false, 'message' => 'Invalid security token. Please refresh the page.']);
    exit;
}

$db = getDB();
$user = getCurrentUser();
$userId = $user['id'];
$userRole = $user['role'];
$today = today();
$currentTime = currentTime();
$action = $_POST['action'] ?? '';

try {
    if ($action === 'check_in') {
        $stmt = $db->prepare("SELECT id, check_in FROM attendance WHERE user_id = ? AND date = ?");
        $stmt->execute([$userId, $today]);
        $existing = $stmt->fetch();
        
        if ($existing && $existing['check_in']) {
            echo json_encode(['success' => false, 'message' => 'You have already logged in / checked in today.']);
            exit;
        }
        
        if ($existing) {
            $stmt = $db->prepare("UPDATE attendance SET check_in = ?, status = 'absent' WHERE id = ?");
            $stmt->execute([$currentTime, $existing['id']]);
        } else {
            $stmt = $db->prepare("INSERT INTO attendance (user_id, date, check_in, status) VALUES (?, ?, ?, 'absent')");
            $stmt->execute([$userId, $today, $currentTime]);
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Logged in successfully at ' . formatTime($currentTime),
            'check_in' => formatTime($currentTime)
        ]);
        
    } elseif ($action === 'start_break') {
        $stmt = $db->prepare("SELECT id, check_in, check_out, break_start FROM attendance WHERE user_id = ? AND date = ?");
        $stmt->execute([$userId, $today]);
        $existing = $stmt->fetch();
        
        if (!$existing || !$existing['check_in']) {
            echo json_encode(['success' => false, 'message' => 'You must log in before starting a break.']);
            exit;
        }
        
        if ($existing['check_out']) {
            echo json_encode(['success' => false, 'message' => 'You have already logged out for today.']);
            exit;
        }
        
        if ($existing['break_start']) {
            echo json_encode(['success' => false, 'message' => 'Break has already been started.']);
            exit;
        }
        
        $stmt = $db->prepare("UPDATE attendance SET break_start = ? WHERE id = ?");
        $stmt->execute([$currentTime, $existing['id']]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Break started at ' . formatTime($currentTime),
            'break_start' => formatTime($currentTime)
        ]);
        
    } elseif ($action === 'end_break') {
        $stmt = $db->prepare("SELECT id, break_start, break_end FROM attendance WHERE user_id = ? AND date = ?");
        $stmt->execute([$userId, $today]);
        $existing = $stmt->fetch();
        
        if (!$existing || !$existing['break_start']) {
            echo json_encode(['success' => false, 'message' => 'No active break to end.']);
            exit;
        }
        
        if ($existing['break_end']) {
            echo json_encode(['success' => false, 'message' => 'Break has already ended.']);
            exit;
        }
        
        $breakDuration = calculateWorkingTime($existing['break_start'], $currentTime);
        
        $stmt = $db->prepare("UPDATE attendance SET break_end = ?, total_break_time = ? WHERE id = ?");
        $stmt->execute([$currentTime, $breakDuration, $existing['id']]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Break ended at ' . formatTime($currentTime),
            'break_end' => formatTime($currentTime),
            'total_break_time' => $breakDuration
        ]);
        
    } elseif ($action === 'check_out') {
        $stmt = $db->prepare("SELECT * FROM attendance WHERE user_id = ? AND date = ?");
        $stmt->execute([$userId, $today]);
        $existing = $stmt->fetch();
        
        if (!$existing || !$existing['check_in']) {
            echo json_encode(['success' => false, 'message' => 'You must log in before logging out.']);
            exit;
        }
        
        if ($existing['check_out']) {
            echo json_encode(['success' => false, 'message' => 'You have already logged out today.']);
            exit;
        }
        
        $breakStart = $existing['break_start'];
        $breakEnd = $existing['break_end'];
        $totalBreakSeconds = 0;
        
        // Auto end break if still active on checkout
        if ($breakStart && !$breakEnd) {
            $breakEnd = $currentTime;
            $breakDuration = calculateWorkingTime($breakStart, $breakEnd);
            $stmt = $db->prepare("UPDATE attendance SET break_end = ?, total_break_time = ? WHERE id = ?");
            $stmt->execute([$breakEnd, $breakDuration, $existing['id']]);
            $existing['total_break_time'] = $breakDuration;
        }
        
        if (!empty($existing['total_break_time'])) {
            $bParts = explode(':', $existing['total_break_time']);
            if (count($bParts) >= 2) {
                $totalBreakSeconds = ((int)$bParts[0] * 3600) + ((int)$bParts[1] * 60) + ((int)($bParts[2] ?? 0));
            }
        }
        
        // Total elapsed time in seconds
        $inTime = new DateTime($existing['check_in']);
        $outTime = new DateTime($currentTime);
        $totalSpanSeconds = $outTime->getTimestamp() - $inTime->getTimestamp();
        
        // Net Working Time = Total Span - Break Time
        $netWorkingSeconds = max(0, $totalSpanSeconds - $totalBreakSeconds);
        
        $wHours = floor($netWorkingSeconds / 3600);
        $wMins = floor(($netWorkingSeconds % 3600) / 60);
        $wSecs = $netWorkingSeconds % 60;
        $workingTimeStr = sprintf('%02d:%02d:%02d', $wHours, $wMins, $wSecs);
        
        // Determine status based on 7-Hour Shift rule (6h Working + 1h Break)
        if ($userRole === ROLE_FOUNDER) {
            $status = 'present'; // Founder exempt from time limit
        } elseif ($netWorkingSeconds >= 21600) { // 6 hours or more
            $status = 'present';
        } elseif ($netWorkingSeconds >= 10800) { // 3 hours to 5.9 hours
            $status = 'half-day';
        } else { // Less than 3 hours
            $status = 'absent';
        }
        
        $stmt = $db->prepare("UPDATE attendance SET check_out = ?, total_working_time = ?, status = ? WHERE id = ?");
        $stmt->execute([$currentTime, $workingTimeStr, $status, $existing['id']]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Logged out successfully at ' . formatTime($currentTime) . '. Net Working Time: ' . sprintf('%dh %02dm', $wHours, $wMins),
            'check_out' => formatTime($currentTime),
            'working_time' => $workingTimeStr,
            'status' => $status
        ]);
        
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action.']);
    }
    
} catch (PDOException $e) {
    error_log("Attendance API Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'A database error occurred.']);
}
