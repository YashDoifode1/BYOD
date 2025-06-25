<?php
/**
 * File Viewer
 *
 * Serves files for viewing in an iframe with headers to discourage downloading.
 *
 * @author Your Name
 * @version 1.0.0
 */

session_start();
require_once '..\includes/auth.php';
require_once '..\includes/config.php';

// Validate CSRF token
if (!isset($_GET['csrf_token']) || $_GET['csrf_token'] !== $_SESSION['csrf_token']) {
    http_response_code(400);
    die("Invalid CSRF token");
}

// Validate file ID
$file_id = filter_input(INPUT_GET, 'file_id', FILTER_VALIDATE_INT);
if (!$file_id) {
    http_response_code(400);
    die("Invalid file ID");
}

try {
    // Fetch file details
    $stmt = $pdo->prepare("
        SELECT f.*, pm.project_id 
        FROM files f 
        JOIN project_members pm ON f.project_id = pm.project_id 
        WHERE f.id = ? AND pm.user_id = ?
    ");
    $stmt->execute([$file_id, $_SESSION['user_id']]);
    $file = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$file) {
        http_response_code(403);
        die("File not found or you don't have permission to view it");
    }

    // Check if file exists
    if (!file_exists($file['path'])) {
        http_response_code(404);
        die("File not found on server");
    }

    // Set headers to display file inline
    header('Content-Type: ' . $file['mime_type']);
    header('Content-Disposition: inline; filename="' . htmlspecialchars($file['name']) . '"');
    header('Content-Length: ' . $file['size']);
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');

    // Read and output file
    readfile($file['path']);
    exit;

} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    http_response_code(500);
    die("An error occurred while retrieving the file");
}
?>