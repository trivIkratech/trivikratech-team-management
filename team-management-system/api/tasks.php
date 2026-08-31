<?php
/**
 * API — Tasks (Status Update)
 * 
 * Handles AJAX requests for task status updates.
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
$userRole = getUserRole();
$action = $_POST['action'] ?? '';

try {
    if ($action === 'update_status') {
        $taskId = (int)($_POST['task_id'] ?? 0);
        $newStatus = $_POST['status'] ?? '';
        
        // Validate status
        $validStatuses = ['todo', 'in_progress', 'completed'];
        if (!in_array($newStatus, $validStatuses)) {
            echo json_encode(['success' => false, 'message' => 'Invalid status.']);
            exit;
        }
        
        // Get the task
        $stmt = $db->prepare("SELECT * FROM tasks WHERE id = ?");
        $stmt->execute([$taskId]);
        $task = $stmt->fetch();
        
        if (!$task) {
            echo json_encode(['success' => false, 'message' => 'Task not found.']);
            exit;
        }
        
        // Permission check: employees can only update their own tasks
        if ($userRole === ROLE_EMPLOYEE && $task['assigned_to'] != $userId) {
            echo json_encode(['success' => false, 'message' => 'You can only update your own tasks.']);
            exit;
        }
        
        // Managers can only update tasks they assigned
        if ($userRole === ROLE_MANAGER && $task['assigned_by'] != $userId) {
            echo json_encode(['success' => false, 'message' => 'You can only update tasks you assigned.']);
            exit;
        }
        
        // Update task
        $completedAt = ($newStatus === 'completed') ? date('Y-m-d H:i:s') : null;
        $newComment = $_POST['comments'] ?? $task['comments'];
        
        $stmt = $db->prepare("UPDATE tasks SET status = ?, comments = ?, completed_at = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$newStatus, $newComment, $completedAt, $taskId]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Task status updated to "' . taskStatusLabel($newStatus) . '".',
            'status' => $newStatus,
            'status_label' => taskStatusLabel($newStatus),
            'badge_class' => taskStatusBadge($newStatus)
        ]);
        
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action.']);
    }
    
} catch (PDOException $e) {
    error_log("Task API Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'A database error occurred. Please try again.']);
}
