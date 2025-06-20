<?php
/**
 * Delete Project File
 *
 * Handles deletion of a project file by authorized users (managers or admins only).
 *
 * @author Your Name
 * @version 1.1.0
 */

session_start();
require_once '..\includes/auth.php';
require_once '..\includes/config.php';

// Validate CSRF token
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    http_response_code(400);
    die("Invalid CSRF token");
}

// Validate file ID and project ID
$file_id = filter_input(INPUT_POST, 'file_id', FILTER_VALIDATE_INT);
$project_id = filter_input(INPUT_POST, 'project_id', FILTER_VALIDATE_INT);
if (!$file_id || !$project_id) {
    http_response_code(400);
    die("Invalid file or project ID");
}

try {
    // Fetch file details and user role
    $stmt = $pdo->prepare("
        SELECT f.*, pm.role 
        FROM files f 
        JOIN project_members pm ON f.project_id = pm.project_id AND pm.user_id = ? 
        WHERE f.id = ? AND f.project_id = ?
    ");
    $stmt->execute([$_SESSION['user_id'], $file_id, $project_id]);
    $file = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$file) {
        http_response_code(403);
        die("File not found or you don't have permission to access it");
    }

    // Check if user is authorized to delete (manager or admin)
    if ($file['role'] !== 'manager' && $_SESSION['role'] !== 'admin') {
        http_response_code(403);
        die("Permission denied: Only managers or admins can delete files");
    }

    // Delete file from filesystem
    if (file_exists($file['path'])) {
        unlink($file['path']);
    }

    // Delete file from database
    $stmt = $pdo->prepare("DELETE FROM files WHERE id = ?");
    $stmt->execute([$file_id]);

    // Log activity
    $stmt = $pdo->prepare("
        INSERT INTO activity_logs 
        (user_id, project_id, action, description)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([
        $_SESSION['user_id'],
        $file['project_id'],
        'file_delete',
        "Deleted file: " . $file['name']
    ]);

    // Redirect back to file manager
    header("Location: file.php?project_id=$project_id");
    exit;

} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    http_response_code(500);
    die("An error occurred while deleting the file");
}
?>