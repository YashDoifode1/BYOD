<?php
/**
 * Project Authorization Functions
 * Requires db.php and auth.php to be included first
 */

/**
 * Check if user has access to a project
 * @param int $userId
 * @param int $projectId
 * @param string $minRole (member|owner)
 * @return bool
 */
function hasProjectAccess($userId, $projectId, $minRole = 'member') {
    global $pdo;
    
    // Validate inputs
    if (!is_numeric($userId)) return false;
    if (!is_numeric($projectId)) return false;
    
    // Admins have full access to all projects
    $userRole = getUserRole($userId);
    if ($userRole === 'admin') {
        return true;
    }

    // Check project membership and role
    $stmt = $pdo->prepare("
        SELECT role FROM project_members 
        WHERE user_id = :user_id AND project_id = :project_id
    ");
    $stmt->execute([
        ':user_id' => $userId,
        ':project_id' => $projectId
    ]);
    $role = $stmt->fetchColumn();
    
    // Define role hierarchy
    $roleHierarchy = [
        'member' => 1,
        'owner' => 2
    ];
    
    $requiredLevel = $roleHierarchy[$minRole] ?? 0;
    $userLevel = $roleHierarchy[$role] ?? 0;
    
    return ($userLevel >= $requiredLevel);
}

/**
 * Get user's system-wide role
 * @param int $userId
 * @return string|null
 */
function getUserRole($userId) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    return $stmt->fetchColumn();
}

/**
 * Check if user can edit project settings
 * @param int $userId
 * @param int $projectId
 * @return bool
 */
function canEditProject($userId, $projectId) {
    return hasProjectAccess($userId, $projectId, 'owner');
}

/**
 * Check if user can manage project members
 * @param int $userId
 * @param int $projectId
 * @return bool
 */
function canManageMembers($userId, $projectId) {
    return hasProjectAccess($userId, $projectId, 'owner');
}

/**
 * Check if user can create tasks in project
 * @param int $userId
 * @param int $projectId
 * @return bool
 */
function canCreateTasks($userId, $projectId) {
    return hasProjectAccess($userId, $projectId, 'member');
}