<?php
session_start();
require_once '../includes/auth.php';
require_once '../includes/config.php';

header('Content-Type: application/json');

// Validate input
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['file_id'], $input['permissions'], $input['csrf_token']) || 
    $input['csrf_token'] !== $_SESSION['csrf_token']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$file_id = filter_var($input['file_id'], FILTER_VALIDATE_INT);
$permissions = $input['permissions'];

if (!$file_id || !is_array($permissions)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit;
}

try {
    // First check if user has permission to modify permissions
    $stmt = $pdo->prepare("
        SELECT f.uploaded_by, p.role 
        FROM files f
        LEFT JOIN project_members p ON (
            p.project_id = f.project_id AND 
            p.user_id = ?
        )
        WHERE f.id = ?
    ");
    $stmt->execute([$_SESSION['user_id'], $file_id]);
    $file_info = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$file_info) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'File not found']);
        exit;
    }

    // Check permissions:
    // - User is admin OR
    // - User is project owner OR
    // - User is uploader OR
    // - User has can_delete permission for this file
    $is_admin = ($_SESSION['role'] === 'admin');
    $is_owner = ($file_info['role'] === 'owner');
    $is_uploader = ($file_info['uploaded_by'] == $_SESSION['user_id']);
    
    if (!$is_admin && !$is_owner && !$is_uploader) {
        $stmt = $pdo->prepare("
            SELECT 1 FROM file_permissions 
            WHERE file_id = ? AND user_id = ? AND can_delete = 1
        ");
        $stmt->execute([$file_id, $_SESSION['user_id']]);
        $has_permission = $stmt->fetch();
        
        if (!$has_permission) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'No permission to modify permissions']);
            exit;
        }
    }

    $pdo->beginTransaction();

    // Delete existing permissions except for the uploader
    $stmt = $pdo->prepare("
        DELETE FROM file_permissions 
        WHERE file_id = ? AND user_id != (SELECT uploaded_by FROM files WHERE id = ?)
    ");
    $stmt->execute([$file_id, $file_id]);

    // Insert or update new permissions
    $stmt = $pdo->prepare("
        INSERT INTO file_permissions 
        (file_id, user_id, can_view, can_download, can_delete, granted_by) 
        VALUES (?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE 
        can_view = VALUES(can_view), 
        can_download = VALUES(can_download), 
        can_delete = VALUES(can_delete), 
        granted_by = VALUES(granted_by), 
        granted_at = CURRENT_TIMESTAMP
    ");

    foreach ($permissions as $perm) {
        $user_id = filter_var($perm['user_id'], FILTER_VALIDATE_INT);
        if (!$user_id) continue; // Skip invalid user IDs
        
        // Skip if trying to modify uploader's permissions
        if ($user_id == $file_info['uploaded_by']) continue;
        
        $stmt->execute([
            $file_id,
            $user_id,
            isset($perm['can_view']) && $perm['can_view'] ? 1 : 0,
            isset($perm['can_download']) && $perm['can_download'] ? 1 : 0,
            isset($perm['can_delete']) && $perm['can_delete'] ? 1 : 0,
            $_SESSION['user_id']
        ]);
    }

    // Log activity
    $stmt = $pdo->prepare("
        INSERT INTO activity_logs 
        (user_id, project_id, action, description) 
        VALUES (?, (SELECT project_id FROM files WHERE id = ?), 'file_permission_update', ?)
    ");
    $stmt->execute([
        $_SESSION['user_id'],
        $file_id,
        "Updated permissions for file ID $file_id"
    ]);

    $pdo->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Error updating permissions: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
?>