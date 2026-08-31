<?php
/**
 * API — Attendance (Check-In / Check-Out)
 * 
 * Handles AJAX requests for employee attendance.
 * Returns JSON responses.
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/middleware.php';

header('Content-Type: application/json');

// Must be logged in
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated.']);
    exit;
}

// Must be POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

// Validate CSRF
$csrfToken = $_POST['csrf_token'] ?? '';
if (empty($csrfToken) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrfToken)) {
    echo json_encode(['success' => false, 'message' => 'Invalid security token. Please refresh the page.']);
    exit;
}

$db = getDB();
$userId = getUserId();
$today = today();
$currentTime = currentTime();
$action = $_POST['action'] ?? '';

try {
    if ($action === 'check_in') {
        // Check if already checked in today
        $stmt = $db->prepare("SELECT id, check_in FROM attendance WHERE user_id = ? AND date = ?");
        $stmt->execute([$userId, $today]);
        $existing = $stmt->fetch();
        
        if ($existing && $existing['check_in']) {
            echo json_encode(['success' => false, 'message' => 'You have already checked in today.']);
            exit;
        }
        
        if ($existing) {
            // Update existing record (shouldn't normally happen)
            $stmt = $db->prepare("UPDATE attendance SET check_in = ?, status = 'present' WHERE id = ?");
            $stmt->execute([$currentTime, $existing['id']]);
        } else {
            // Create new attendance record
            $stmt = $db->prepare("INSERT INTO attendance (user_id, date, check_in, status) VALUES (?, ?, ?, 'present')");
            $stmt->execute([$userId, $today, $currentTime]);
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Checked in successfully at ' . formatTime($currentTime),
            'check_in' => formatTime($currentTime)
        ]);
        
    } elseif ($action === 'check_out') {
        // Get today's attendance
        $stmt = $db->prepare("SELECT id, check_in, check_out FROM attendance WHERE user_id = ? AND date = ?");
        $stmt->execute([$userId, $today]);
        $existing = $stmt->fetch();
        
        if (!$existing || !$existing['check_in']) {
            echo json_encode(['success' => false, 'message' => 'You must check in before checking out.']);
            exit;
        }
        
        if ($existing['check_out']) {
            echo json_encode(['success' => false, 'message' => 'You have already checked out today.']);
            exit;
        }
        
        // Calculate working time
        $workingTime = calculateWorkingTime($existing['check_in'], $currentTime);
        
        // Determine status (less than 4 hours = half-day)
        $checkInTime = new DateTime($existing['check_in']);
        $checkOutTime = new DateTime($currentTime);
        $diffHours = ($checkOutTime->getTimestamp() - $checkInTime->getTimestamp()) / 3600;
        $status = $diffHours < 4 ? 'half-day' : 'present';
        
        $stmt = $db->prepare("UPDATE attendance SET check_out = ?, total_working_time = ?, status = ? WHERE id = ?");
        $stmt->execute([$currentTime, $workingTime, $status, $existing['id']]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Checked out successfully at ' . formatTime($currentTime),
            'check_out' => formatTime($currentTime),
            'working_time' => $workingTime
        ]);
        
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action.']);
    }
    
} catch (PDOException $e) {
    error_log("Attendance API Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'A database error occurred. Please try again.']);
}
