<?php
require_once '..\includes/config.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = ['success' => false, 'message' => ''];
    
    try {
        // Validate input
        if (empty($_POST['title']) || empty($_POST['project_id'])) {
            throw new Exception('Title and Project are required');
        }
        
        // Start transaction
        $pdo->beginTransaction();
        
        // Insert task
        $stmt = $pdo->prepare("INSERT INTO tasks 
                              (project_id, title, description, status, priority, due_date, created_by) 
                              VALUES (?, ?, ?, 'todo', ?, ?, ?)");
        $stmt->execute([
            $_POST['project_id'],
            $_POST['title'],
            $_POST['description'] ?? null,
            $_POST['priority'] ?? 'medium',
            $_POST['due_date'] ?? null,
            $_SESSION['user_id']
        ]);
        
        $taskId = $pdo->lastInsertId();
        
        // Assign task if assignee selected
        if (!empty($_POST['assignee_id'])) {
            $stmt = $pdo->prepare("INSERT INTO task_assignments (task_id, user_id) VALUES (?, ?)");
            $stmt->execute([$taskId, $_POST['assignee_id']]);
        }
        
        // Log activity
        $stmt = $pdo->prepare("INSERT INTO activity_logs 
                              (user_id, project_id, action, description) 
                              VALUES (?, ?, 'create', ?)");
        $stmt->execute([
            $_SESSION['user_id'],
            $_POST['project_id'],
            "Created task: {$_POST['title']}"
        ]);
        
        $pdo->commit();
        $response['success'] = true;
    } catch (Exception $e) {
        $pdo->rollBack();
        $response['message'] = $e->getMessage();
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

header("Location: tasks.php");
?>