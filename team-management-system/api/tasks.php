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
    if ($action === 'update_status' || $action === 'update_comment' || $action === 'save_comment') {
        $taskId = (int)($_POST['task_id'] ?? 0);
        
        if ($taskId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid task ID.']);
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
        
        // Permission check: User must be assignee, assigner, Founder, or Manager of the assignee
        $hasPermission = ($userRole === ROLE_FOUNDER) || ($task['assigned_to'] == $userId) || ($task['assigned_by'] == $userId);
        
        if (!$hasPermission && $userRole === ROLE_MANAGER) {
            $teamCheck = $db->prepare("SELECT id FROM users WHERE id = ? AND manager_id = ?");
            $teamCheck->execute([$task['assigned_to'], $userId]);
            if ($teamCheck->fetch()) {
                $hasPermission = true;
            }
        }
        
        if (!$hasPermission) {
            echo json_encode(['success' => false, 'message' => 'You do not have permission to update this task.']);
            exit;
        }
        
        $currentStatus = $task['status'];
        $currentCompletedAt = $task['completed_at'];
        $currentComments = $task['comments'];
        
        $newStatus = $currentStatus;
        $completedAt = $currentCompletedAt;
        $newComment = $currentComments;
        
        // Handle status update if provided
        if (isset($_POST['status']) && $_POST['status'] !== '') {
            $requestedStatus = $_POST['status'];
            $validStatuses = ['todo', 'in_progress', 'completed'];
            if (!in_array($requestedStatus, $validStatuses)) {
                echo json_encode(['success' => false, 'message' => 'Invalid status value.']);
                exit;
            }
            $newStatus = $requestedStatus;
            if ($newStatus === 'completed') {
                $completedAt = !empty($currentCompletedAt) ? $currentCompletedAt : date('Y-m-d H:i:s');
            } else {
                $completedAt = null;
            }
        }
        
        // Handle comments update if provided
        if (isset($_POST['comments'])) {
            $newComment = trim($_POST['comments']);
        }
        
        // Update task
        $updateStmt = $db->prepare("UPDATE tasks SET status = ?, comments = ?, completed_at = ?, updated_at = NOW() WHERE id = ?");
        $updateStmt->execute([$newStatus, $newComment, $completedAt, $taskId]);
        
        $statusMsg = ($newStatus !== $currentStatus) 
            ? 'Task status updated to "' . taskStatusLabel($newStatus) . '".' 
            : 'Task comment/note updated successfully.';
            
        echo json_encode([
            'success' => true,
            'message' => $statusMsg,
            'task_id' => $taskId,
            'status' => $newStatus,
            'status_label' => taskStatusLabel($newStatus),
            'badge_class' => taskStatusBadge($newStatus),
            'comments' => $newComment,
            'completed_at' => $completedAt,
            'completed_at_formatted' => $completedAt ? formatDate($completedAt) : null
        ]);
        
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action requested.']);
    }
    
} catch (PDOException $e) {
    error_log("Task API Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'A database error occurred. Please try again.']);
}
