<?php
/**
 * Admin Panel Helper Functions
 */

/**
 * Log activity to the database
 */
function log_activity($user_id, $action, $description, $project_id = null) {
    global $pdo;
    
    $stmt = $pdo->prepare("INSERT INTO activity_logs 
                          (user_id, project_id, action, description, ip_address, user_agent) 
                          VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $user_id,
        $project_id,
        $action,
        $description,
        $_SERVER['REMOTE_ADDR'],
        $_SERVER['HTTP_USER_AGENT'] ?? ''
    ]);
}

/**
 * Check if user has permission to access a resource
 */
function has_permission($required_role) {
    $user_role = $_SESSION['user_role'] ?? 'user';
    
    $roles = ['user', 'manager', 'admin'];
    $user_level = array_search($user_role, $roles);
    $required_level = array_search($required_role, $roles);
    
    return $user_level >= $required_level;
}

/**
 * Export data to CSV
 */
function export_to_csv($data, $filename = 'export.csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    
    // Add headers
    if (!empty($data)) {
        fputcsv($output, array_keys($data[0]));
    }
    
    // Add data
    foreach ($data as $row) {
        fputcsv($output, $row);
    }
    
    fclose($output);
    exit();
}

/**
 * Generate a random password
 */
function generate_password($length = 12) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()_-=+;:,.?';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $password;
}