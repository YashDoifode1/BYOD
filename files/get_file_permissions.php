<?php
session_start();
require_once '../includes/auth.php';
require_once '../includes/config.php';

header('Content-Type: application/json');

$file_id = filter_input(INPUT_GET, 'file_id', FILTER_VALIDATE_INT);
$csrf_token = filter_input(INPUT_GET, 'csrf_token', FILTER_SANITIZE_STRING);

if (!$file_id || $csrf_token !== $_SESSION['csrf_token']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

try {
    // Check if user has permission to view/modify permissions
    $stmt = $pdo->prepare("
        SELECT 1 FROM file_permissions 
        WHERE file_id = ? AND user_id = ? AND can_delete = 1
    ");
    $stmt->execute([$file_id, $_SESSION['user_id']]);
    $has_permission = $stmt->fetch();

    $stmt = $pdo->prepare("
        SELECT role FROM project_members 
        WHERE project_id = (SELECT project_id FROM files WHERE id = ?) 
        AND user_id = ?
    ");
    $stmt->execute([$file_id, $_SESSION['user_id']]);
    $project_role = $stmt->fetchColumn();

    if (!$has_permission && $project_role !== 'owner' && $_SESSION['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'No permission to view permissions']);
        exit;
    }

    // Fetch permissions for all users for this file
    $stmt = $pdo->prepare("
        SELECT user_id, can_view, can_download, can_delete 
        FROM file_permissions 
        WHERE file_id = ?
    ");
    $stmt->execute([$file_id]);
    $permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($permissions);
} catch (PDOException $e) {
    error_log("Error fetching permissions: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
?>