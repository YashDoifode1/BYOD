<?php
function isLoggedIn() {
    return isset($_SESSION['user']) && !empty($_SESSION['user']);
}

function requireAdmin() {
    if (!isLoggedIn() || $_SESSION['user']['role'] != 'admin') {
        header('HTTP/1.0 403 Forbidden');
        die('Access denied. Admin privileges required.');
    }
}

function requireManager() {
    if (!isLoggedIn() || ($_SESSION['user']['role'] != 'manager' && $_SESSION['user']['role'] != 'admin')) {
        header('HTTP/1.0 403 Forbidden');
        die('Access denied. Manager privileges required.');
    }
}
// function hasProjectAccess($userId, $projectId, $minRole = 'member') {
//     global $pdo;
    
//     // Admins have full access
//     $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
//     $stmt->execute([$userId]);
//     $userRole = $stmt->fetchColumn();
    
//     if ($userRole === 'admin') {
//         return true;
//     }

//     // Check project membership
//     $stmt = $pdo->prepare("
//         SELECT role FROM project_members 
//         WHERE user_id = ? AND project_id = ?
//     ");
//     $stmt->execute([$userId, $projectId]);
//     $role = $stmt->fetchColumn();
    
//     $roleHierarchy = ['member' => 1, 'owner' => 2];
//     $requiredLevel = $roleHierarchy[$minRole] ?? 0;
    
//     return ($role && ($roleHierarchy[$role] >= $requiredLevel));
// }
?>