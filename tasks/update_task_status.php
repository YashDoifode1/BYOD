<?php
require_once '..\includes/config.php';
session_start();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$response = ['success' => false, 'message' => ''];

try {
    // Validate input
    if (empty($_POST['task_id']) || empty($_POST['status'])) {
        throw new Exception('Missing required fields');
    }
    
    $taskId = (int)$_POST['task_id'];
    $newStatus = $_POST['status'];
    
    // Validate status
    $validStatuses = ['todo', 'in_progress', 'done'];
    if (!in_array($newStatus, $validStatuses)) {
        throw new Exception('Invalid status value');
    }
    
    // Start transaction
    $pdo->beginTransaction();
    
    // Get current task status and project ID
    $stmt = $pdo->prepare("SELECT status, project_id, title FROM tasks WHERE id = ?");
    $stmt->execute([$taskId]);
    $task = $stmt->fetch();
    
    if (!$task) {
        throw new Exception('Task not found');
    }
    
    // Only update if status is changing
    if ($task['status'] !== $newStatus) {
        // Update task status
        $stmt = $pdo->prepare("UPDATE tasks SET status = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$newStatus, $taskId]);
        
        // Log status change
        $stmt = $pdo->prepare("INSERT INTO activity_logs 
                              (user_id, project_id, action, description) 
                              VALUES (?, ?, 'status_update', ?)");
        $stmt->execute([
            $_SESSION['user_id'],
            $task['project_id'],
            "Changed task status from {$task['status']} to {$newStatus}: {$task['title']}"
        ]);
    }
    
    $pdo->commit();
    $response['success'] = true;
} catch (Exception $e) {
    $pdo->rollBack();
    $response['message'] = $e->getMessage();
    error_log("Task status update error: " . $e->getMessage());
}

echo json_encode($response);
?>