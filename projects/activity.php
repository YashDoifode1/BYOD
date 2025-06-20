<?php
session_start();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/auth_project.php';
require_once __DIR__ . '/../includes/config.php';
// Verify user is logged in
if (!isLoggedIn()) {
    header('Location: ../../login.php');
    exit();
}

$projectId = $_GET['id'] ?? 0;
$userId = $_SESSION['user']['id'];

// Verify user has permission to view this project
if (!hasProjectAccess($userId, $projectId)) {
    header('HTTP/1.0 403 Forbidden');
    die('You do not have permission to view this project');
}

// Get project details for header
$projectStmt = $pdo->prepare("SELECT name FROM projects WHERE id = ?");
$projectStmt->execute([$projectId]);
$project = $projectStmt->fetch();

// Pagination setup
$perPage = 20;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $perPage;

// Get total count of activities
$countStmt = $pdo->prepare("
    SELECT COUNT(*) as total 
    FROM activity_logs 
    WHERE project_id = ?
");
$countStmt->execute([$projectId]);
$totalActivities = $countStmt->fetch()['total'];
$totalPages = ceil($totalActivities / $perPage);

// Get activities with pagination
$activityStmt = $pdo->prepare("
    SELECT a.*, 
           CONCAT(u.first_name, ' ', u.last_name) as user_name,
           u.email as user_email
    FROM activity_logs a
    LEFT JOIN users u ON a.user_id = u.id
    WHERE a.project_id = ?
    ORDER BY a.created_at DESC
    LIMIT ? OFFSET ?
");
$activityStmt->execute([$projectId, $perPage, $offset]);
$activities = $activityStmt->fetchAll();

// Get filter values if any
$filterAction = $_GET['action'] ?? '';
$filterUser = $_GET['user_id'] ?? '';
?>

<body class="dashboard">
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar Navigation -->
            <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-history me-2"></i>
                        Activity Log
                        <small class="text-muted"><?= htmlspecialchars($project['name'] ?? '') ?></small>
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="../view.php?id=<?= $projectId ?>" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-1"></i> Back to Project
                        </a>
                    </div>
                </div>

                <!-- Filters -->
                <div class="card mb-4 shadow-sm">
                    <div class="card-header bg-light">
                        <h5 class="mb-0">
                            <i class="fas fa-filter me-2"></i>
                            Filters
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="get" action="activity.php">
                            <input type="hidden" name="id" value="<?= $projectId ?>">
                            <div class="row">
                                <div class="col-md-4">
                                    <label for="action" class="form-label">Action Type</label>
                                    <select class="form-select" id="action" name="action">
                                        <option value="">All Actions</option>
                                        <option value="create" <?= $filterAction === 'create' ? 'selected' : '' ?>>Created</option>
                                        <option value="update" <?= $filterAction === 'update' ? 'selected' : '' ?>>Updated</option>
                                        <option value="delete" <?= $filterAction === 'delete' ? 'selected' : '' ?>>Deleted</option>
                                        <option value="add_member" <?= $filterAction === 'add_member' ? 'selected' : '' ?>>Member Added</option>
                                        <option value="remove_member" <?= $filterAction === 'remove_member' ? 'selected' : '' ?>>Member Removed</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label for="user_id" class="form-label">User</label>
                                    <select class="form-select" id="user_id" name="user_id">
                                        <option value="">All Users</option>
                                        <?php
                                        $usersStmt = $pdo->prepare("
                                            SELECT DISTINCT u.id, CONCAT(u.first_name, ' ', u.last_name) as name
                                            FROM activity_logs a
                                            JOIN users u ON a.user_id = u.id
                                            WHERE a.project_id = ?
                                            ORDER BY name
                                        ");
                                        $usersStmt->execute([$projectId]);
                                        while ($user = $usersStmt->fetch()):
                                        ?>
                                            <option value="<?= $user['id'] ?>" <?= $filterUser == $user['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($user['name']) ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <div class="col-md-4 d-flex align-items-end">
                                    <button type="submit" class="btn btn-primary me-2">
                                        <i class="fas fa-search me-1"></i> Apply Filters
                                    </button>
                                    <a href="activity.php?id=<?= $projectId ?>" class="btn btn-outline-secondary">
                                        <i class="fas fa-times me-1"></i> Clear
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Activity Log -->
                <div class="card shadow-sm">
                    <div class="card-header bg-light">
                        <h5 class="mb-0">
                            <i class="fas fa-list me-2"></i>
                            Recent Activities
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($activities)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-history fa-3x text-muted mb-3"></i>
                                <h5>No activities found</h5>
                                <p class="text-muted">There are no recorded activities for this project</p>
                            </div>
                        <?php else: ?>
                            <div class="activity-feed">
                                <?php foreach ($activities as $activity): ?>
                                    <div class="activity-item mb-3 p-3 border-bottom">
                                        <div class="d-flex">
                                            <div class="activity-icon me-3">
                                                <?php 
                                                $icon = 'fa-info-circle';
                                                $color = 'text-primary';
                                                if (strpos($activity['action'], 'task') !== false) {
                                                    $icon = 'fa-tasks';
                                                    $color = 'text-success';
                                                } 
                                                if (strpos($activity['action'], 'member') !== false) {
                                                    $icon = 'fa-user';
                                                    $color = 'text-info';
                                                }
                                                if (strpos($activity['action'], 'delete') !== false) {
                                                    $color = 'text-danger';
                                                }
                                                ?>
                                                <i class="fas <?= $icon ?> fa-lg <?= $color ?>"></i>
                                            </div>
                                            <div class="flex-grow-1">
                                                <div class="d-flex justify-content-between">
                                                    <div>
                                                        <strong class="me-2"><?= htmlspecialchars($activity['user_name'] ?? 'System') ?></strong>
                                                        <span class="badge bg-secondary"><?= formatAction($activity['action']) ?></span>
                                                    </div>
                                                    <small class="text-muted">
                                                        <?= date('M j, Y g:i a', strtotime($activity['created_at'])) ?>
                                                    </small>
                                                </div>
                                                <div class="mt-2">
                                                    <?= htmlspecialchars($activity['description']) ?>
                                                </div>
                                                <?php if (!empty($activity['ip_address'])): ?>
                                                    <div class="mt-1">
                                                        <small class="text-muted">
                                                            <i class="fas fa-globe me-1"></i>
                                                            <?= htmlspecialchars($activity['ip_address']) ?>
                                                            <?php if (!empty($activity['user_agent'])): ?>
                                                                • <?= htmlspecialchars(substr($activity['user_agent'], 0, 50)) ?>...
                                                            <?php endif; ?>
                                                        </small>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <!-- Pagination -->
                            <nav aria-label="Activity pagination">
                                <ul class="pagination justify-content-center mt-4">
                                    <?php if ($page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?id=<?= $projectId ?>&page=<?= $page - 1 ?><?= $filterAction ? '&action='.$filterAction : '' ?><?= $filterUser ? '&user_id='.$filterUser : '' ?>">
                                                <i class="fas fa-chevron-left"></i> Previous
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                    
                                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                            <a class="page-link" href="?id=<?= $projectId ?>&page=<?= $i ?><?= $filterAction ? '&action='.$filterAction : '' ?><?= $filterUser ? '&user_id='.$filterUser : '' ?>">
                                                <?= $i ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <?php if ($page < $totalPages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?id=<?= $projectId ?>&page=<?= $page + 1 ?><?= $filterAction ? '&action='.$filterAction : '' ?><?= $filterUser ? '&user_id='.$filterUser : '' ?>">
                                                Next <i class="fas fa-chevron-right"></i>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <?php require_once __DIR__ . '/../../includes/footer.php'; ?>

    <style>
        .activity-item {
            transition: background-color 0.2s;
        }
        .activity-item:hover {
            background-color: rgba(0, 0, 0, 0.02);
        }
        .activity-icon {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .badge {
            font-weight: 500;
        }
        .page-item.active .page-link {
            background-color: #4e73df;
            border-color: #4e73df;
        }
        .page-link {
            color: #4e73df;
        }
    </style>

    <script>
    $(document).ready(function() {
        // Initialize tooltips
        $('[data-bs-toggle="tooltip"]').tooltip();
    });
    </script>
</body>
</html>

<?php
// Helper function to format action types
function formatAction($action) {
    $actions = [
        'create' => 'Created',
        'update' => 'Updated',
        'delete' => 'Deleted',
        'add_member' => 'Member Added',
        'remove_member' => 'Member Removed',
        'login' => 'Login',
        'logout' => 'Logout'
    ];
    
    return $actions[$action] ?? ucfirst(str_replace('_', ' ', $action));
}
?>