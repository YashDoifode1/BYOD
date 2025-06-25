<?php
session_start();
require_once '../includes/auth.php';
require_once '../includes/config.php';

// Validate inputs
$file_id = filter_input(INPUT_POST, 'file_id', FILTER_VALIDATE_INT);
$project_id = filter_input(INPUT_POST, 'project_id', FILTER_VALIDATE_INT);
$csrf_token = filter_input(INPUT_POST, 'csrf_token', FILTER_SANITIZE_STRING);

if (!$file_id || !$project_id || !$csrf_token || $csrf_token !== $_SESSION['csrf_token']) {
    http_response_code(403);
    die("Invalid request");
}

try {
    // Get file info with permission check
    $stmt = $pdo->prepare("
        SELECT f.*, 
               MAX(fp.can_delete) as can_delete,
               (f.uploaded_by = ?) as is_uploader
        FROM files f
        LEFT JOIN file_permissions fp ON (
            f.id = fp.file_id AND 
            fp.user_id = ?
        )
        WHERE f.id = ? AND f.project_id = ?
        GROUP BY f.id
    ");
    $stmt->execute([$_SESSION['user_id'], $_SESSION['user_id'], $file_id, $project_id]);
    $file = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$file) {
        http_response_code(404);
        die("File not found");
    }

    // Check if user has permission to delete
    $is_admin = ($_SESSION['role'] === 'admin');
    $is_owner = false;
    
    // Check if user is project owner
    $stmt = $pdo->prepare("
        SELECT 1 FROM project_members 
        WHERE project_id = ? AND user_id = ? AND role = 'owner'
    ");
    $stmt->execute([$project_id, $_SESSION['user_id']]);
    $is_owner = $stmt->fetch();

    if (!$file['is_uploader'] && !$is_admin && !$is_owner && !$file['can_delete']) {
        http_response_code(403);
        die("You don't have permission to delete this file");
    }

    // Check if file exists
    if (!file_exists($file['path'])) {
        http_response_code(404);
        die("File not found on server");
    }

    // Delete the file
    if (!unlink($file['path'])) {
        http_response_code(500);
        die("Failed to delete file");
    }

    // Delete from database
    $pdo->beginTransaction();
    
    try {
        // First delete permissions
        $stmt = $pdo->prepare("DELETE FROM file_permissions WHERE file_id = ?");
        $stmt->execute([$file_id]);
        
        // Then delete the file record
        $stmt = $pdo->prepare("DELETE FROM files WHERE id = ?");
        $stmt->execute([$file_id]);
        
        // Log deletion activity
        $stmt = $pdo->prepare("
            INSERT INTO activity_logs 
            (user_id, project_id, action, description) 
            VALUES (?, ?, 'file_delete', ?)
        ");
        $stmt->execute([
            $_SESSION['user_id'],
            $project_id,
            "Deleted file: " . $file['name']
        ]);
        
        $pdo->commit();
        
        // Redirect back to file manager
        header("Location: manage.php?project_id=$project_id");
        exit;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }

} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    http_response_code(500);
    die("An error occurred while processing your request.");
}
?>