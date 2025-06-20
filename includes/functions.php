<?php
// Get appropriate icon for file type
function get_file_icon($mime_type) {
    $icons = [
        'image/' => 'fa-file-image',
        'application/pdf' => 'fa-file-pdf',
        'application/msword' => 'fa-file-word',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'fa-file-word',
        'application/vnd.ms-excel' => 'fa-file-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'fa-file-excel',
        'application/vnd.ms-powerpoint' => 'fa-file-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'fa-file-powerpoint',
        'text/' => 'fa-file-alt',
        'application/zip' => 'fa-file-archive',
        'application/x-rar-compressed' => 'fa-file-archive',
    ];
    
    foreach ($icons as $prefix => $icon) {
        if (strpos($mime_type, $prefix) === 0) {
            return $icon;
        }
    }
    
    return 'fa-file';
}

// Format file size in human readable format
function format_file_size($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } elseif ($bytes > 1) {
        return $bytes . ' bytes';
    } elseif ($bytes == 1) {
        return '1 byte';
    } else {
        return '0 bytes';
    }
}

// Check if user has access to a project
function has_project_access($user_id, $project_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM project_members 
        WHERE user_id = :user_id AND project_id = :project_id
    ");
    $stmt->execute([':user_id' => $user_id, ':project_id' => $project_id]);
    
    return $stmt->fetchColumn() > 0;
}

// Check user permissions
function has_permission($user_id, $module, $action) {
    global $pdo;
    
    // Get user role
    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = :user_id");
    $stmt->execute([':user_id' => $user_id]);
    $role = $stmt->fetchColumn();
    
    // Define permissions (in a real app, this would come from database)
    $permissions = [
        'admin' => [
            'files' => ['view', 'upload', 'delete', 'download'],
            // ... other modules
        ],
        'manager' => [
            'files' => ['view', 'upload', 'delete', 'download'],
            // ... other modules
        ],
        'user' => [
            'files' => ['view', 'upload', 'download'],
            // ... other modules
        ]
    ];
    
    return isset($permissions[$role][$module]) && in_array($action, $permissions[$role][$module]);
}

// Log activity
function log_activity($user_id, $project_id, $action, $description) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        INSERT INTO activity_logs (user_id, project_id, action, description, ip_address, user_agent)
        VALUES (:user_id, :project_id, :action, :description, :ip_address, :user_agent)
    ");
    
    $stmt->execute([
        ':user_id' => $user_id,
        ':project_id' => $project_id,
        ':action' => $action,
        ':description' => $description,
        ':ip_address' => $_SERVER['REMOTE_ADDR'],
        ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
    ]);
}