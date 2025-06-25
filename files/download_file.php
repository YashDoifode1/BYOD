<?php
session_start();
require_once '../includes/auth.php';
require_once '../includes/config.php';

// Validate inputs
$file_id = filter_input(INPUT_GET, 'file_id', FILTER_VALIDATE_INT);
$csrf_token = filter_input(INPUT_GET, 'csrf_token', FILTER_SANITIZE_STRING);

if (!$file_id || !$csrf_token || $csrf_token !== $_SESSION['csrf_token']) {
    http_response_code(403);
    die("Invalid request");
}

try {
    // Get file info with permission check
    $stmt = $pdo->prepare("
        SELECT f.*, 
               MAX(fp.can_download) as can_download,
               (f.uploaded_by = ?) as is_uploader
        FROM files f
        LEFT JOIN file_permissions fp ON (
            f.id = fp.file_id AND 
            fp.user_id = ?
        )
        WHERE f.id = ?
        GROUP BY f.id
    ");
    $stmt->execute([$_SESSION['user_id'], $_SESSION['user_id'], $file_id]);
    $file = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$file) {
        http_response_code(404);
        die("File not found");
    }

    // Check if user has permission to download
    $is_admin = ($_SESSION['role'] === 'admin');
    $is_owner = false;
    
    // Check if user is project owner
    $stmt = $pdo->prepare("
        SELECT 1 FROM project_members 
        WHERE project_id = ? AND user_id = ? AND role = 'owner'
    ");
    $stmt->execute([$file['project_id'], $_SESSION['user_id']]);
    $is_owner = $stmt->fetch();

    if (!$file['is_uploader'] && !$is_admin && !$is_owner && !$file['can_download']) {
        http_response_code(403);
        die("You don't have permission to download this file");
    }

    // Check if file exists
    if (!file_exists($file['path'])) {
        http_response_code(404);
        die("File not found on server");
    }

    // Log download activity
    $stmt = $pdo->prepare("
        INSERT INTO activity_logs 
        (user_id, project_id, action, description) 
        VALUES (?, ?, 'file_download', ?)
    ");
    $stmt->execute([
        $_SESSION['user_id'],
        $file['project_id'],
        "Downloaded file: " . $file['name']
    ]);

    // Set headers for file download
    header('Content-Description: File Transfer');
    header('Content-Type: ' . $file['mime_type']);
    header('Content-Disposition: attachment; filename="' . basename($file['name']) . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($file['path']));
    
    // Clear output buffer
    ob_clean();
    flush();
    
    // Read the file and output it to the browser
    readfile($file['path']);
    exit;

} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    http_response_code(500);
    die("An error occurred while processing your request.");
}
?>