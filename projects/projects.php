<?php
// Start session at the VERY TOP of the file
session_start();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/auth_project.php';
require_once __DIR__ . '/../includes/config.php';
// require_once __DIR__ . '/../includes/db.php';

// Verify user is logged in
if (!isLoggedIn()) {
    header('Location: ../login.php');
    exit();
}

// Get current user info
$userId = $_SESSION['user']['id'];
$userRole = $_SESSION['user']['role'];
$isAdmin = ($userRole === 'admin');
$isManager = ($userRole === 'manager');

// Get all projects for the current user
$projectsQuery = "
    SELECT p.*, 
           COUNT(t.id) as task_count,
           SUM(CASE WHEN t.status = 'done' THEN 1 ELSE 0 END) as completed_tasks,
           COUNT(DISTINCT pm.user_id) as member_count,
           u.username as owner_username
    FROM projects p
    JOIN project_members pm ON p.id = pm.project_id
    LEFT JOIN tasks t ON p.id = t.project_id
    JOIN users u ON p.created_by = u.id
    WHERE pm.user_id = :user_id
    GROUP BY p.id
    ORDER BY p.created_at DESC
";

$stmt = $pdo->prepare($projectsQuery);
$stmt->execute([':user_id' => $userId]);
$projects2 = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="row">
    <!-- Sidebar Column -->
    <div class="col-md-3 col-lg-2 d-md-block sidebar collapse" style="background: linear-gradient(180deg, #1a252f 0%, #2d3e50 100%); min-height: 100vh;">
        <div class="sidebar-sticky pt-4 px-3">
            <!-- User Profile Section -->
            <div class="user-profile text-center mb-4 pb-3 border-bottom border-secondary">
                <div class="user-avatar mb-3 position-relative">
                    <img src="https://ui-avatars.com/api/?name=<?= urlencode($_SESSION['user']['first_name'].' '.$_SESSION['user']['last_name']) ?>&background=random&size=80&rounded=true" 
                         alt="User Avatar" class="rounded-circle shadow-sm" style="width: 80px; height: 80px; object-fit: cover;">
                    <span class="status-indicator" style="position: absolute; bottom: 0; right: 0; width: 12px; height: 12px; background: #28a745; border: 2px solid #fff; border-radius: 50%;"></span>
                </div>
                <div class="user-info text-white">
                    <h5 class="mb-1 font-weight-bold"><?= htmlspecialchars($_SESSION['user']['first_name'].' '.$_SESSION['user']['last_name']) ?></h5>
                    <small class="text-light opacity-75">@<?= htmlspecialchars($_SESSION['user']['username']) ?></small>
                    <div class="user-role mt-2">
                        <span class="badge px-3 py-1 <?= $_SESSION['user']['role'] === 'admin' ? 'bg-primary' : 'bg-secondary' ?>" 
                              style="font-size: 0.9rem; border-radius: 12px;">
                            <?= ucfirst(htmlspecialchars($_SESSION['user']['role'])) ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Main Navigation -->
            <ul class="nav flex-column mb-4">
                <li class="nav-item">
                    <a class="nav-link d-flex align-items-center text-white py-2 px-3 rounded <?= strpos($_SERVER['REQUEST_URI'], 'dashboard.php') !== false ? 'active' : '' ?>" 
                       href="<?= APP_URL ?>/dashboard.php" style="transition: all 0.3s;">
                        <i class="fas fa-tachometer-alt me-3"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <?php if ($_SESSION['user']['role'] === 'admin'): ?>
                <li class="nav-item">
                    <a class="nav-link d-flex align-items-center text-white py-2 px-3 rounded <?= strpos($_SERVER['REQUEST_URI'], '/admin/') !== false ? 'active' : '' ?>" 
                       href="<?= APP_URL ?>/admin/index.php" style="transition: all 0.3s;">
                        <i class="fas fa-lock me-3"></i>
                        <span>Admin Panel</span>
                    </a>
                </li>
                <?php endif; ?>
                <li class="nav-item">
                    <a class="nav-link d-flex align-items-center text-white py-2 px-3 rounded <?= strpos($_SERVER['REQUEST_URI'], '/tasks/') !== false ? 'active' : '' ?>" 
                       href="<?= APP_URL ?>/tasks/tasks.php" style="transition: all 0.3s;">
                        <i class="fas fa-tasks me-3"></i>
                        <span>My Tasks</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link d-flex align-items-center text-white py-2 px-3 rounded <?= strpos($_SERVER['REQUEST_URI'], '/chat/') !== false ? 'active' : '' ?>" 
                       href="<?= APP_URL ?>/chat/chat.php" style="transition: all 0.3s;">
                        <i class="fas fa-comments me-3"></i>
                        <span>Team Chat</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link d-flex align-items-center text-white py-2 px-3 rounded <?= strpos($_SERVER['REQUEST_URI'], '/files/') !== false ? 'active' : '' ?>" 
                       href="<?= APP_URL ?>/files/files.php" style="transition: all 0.3s;">
                        <i class="fas fa-folder me-3"></i>
                        <span>Files</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link d-flex align-items-center text-white py-2 px-3 rounded <?= strpos($_SERVER['REQUEST_URI'], 'settings.php') !== false ? 'active' : '' ?>" 
                       href="<?= APP_URL ?>/settings.php" style="transition: all 0.3s;">
                        <i class="fas fa-cog me-3"></i>
                        <span>Settings</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link d-flex align-items-center text-white py-2 px-3 rounded" 
                       href="<?= APP_URL ?>/logout.php" style="transition: all 0.3s;">
                        <i class="fas fa-sign-out-alt me-3"></i>
                        <span>Logout</span>
                    </a>
                </li>
            </ul>

            <!-- Projects Section -->
            <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-2 text-light" style="font-size: 0.9rem; opacity: 0.7;">
                <span>Projects</span>
                <a class="d-flex align-items-center text-light" href="<?= APP_URL ?>/projects/create.php" aria-label="Add a new project" style="transition: all 0.3s;">
                    <i class="fas fa-plus"></i>
                </a>
            </h6>

            <ul class="nav flex-column mb-2">
                <?php
                $projects = $pdo->prepare("
                    SELECT p.id, p.name 
                    FROM projects p
                    JOIN project_members pm ON p.id = pm.project_id
                    WHERE pm.user_id = :userId
                    ORDER BY p.name
                    LIMIT 5
                ");
                $projects->execute([':userId' => $_SESSION['user']['id']]);

                while ($proj = $projects->fetch(PDO::FETCH_ASSOC)) {
                    echo '<li class="nav-item">
                        <a class="nav-link d-flex align-items-center text-white py-2 px-3 rounded '.(strpos($_SERVER['REQUEST_URI'], 'projects/view.php?id='.$proj['id']) !== false ? 'active' : '').'" 
                           href="'.APP_URL.'/projects/view.php?id='.$proj['id'].'" style="transition: all 0.3s;">
                            <i class="fas fa-project-diagram me-3"></i>
                            <span>'.htmlspecialchars($proj['name']).'</span>
                        </a>
                    </li>';
                }
                ?>
                <li class="nav-item">
                    <a class="nav-link d-flex align-items-center text-white py-2 px-3 rounded <?= strpos($_SERVER['REQUEST_URI'], '/projects/projects.php') !== false ? 'active' : '' ?>" 
                       href="<?= APP_URL ?>/projects/projects.php" style="transition: all 0.3s;">
                        <i class="fas fa-ellipsis-h me-3"></i>
                        <span>View all projects</span>
                    </a>
                </li>
            </ul>
        </div>

        <!-- Inline CSS for Sidebar Styling -->
        <style>
            .sidebar {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, 'Open Sans', 'Helvetica Neue', sans-serif;
                box-shadow: 2px 0 8px rgba(0, 0, 0, 0.2);
            }

            .sidebar .nav-link {
                font-size: 0.95rem;
                font-weight: 500;
                margin-bottom: 0.5rem;
                border-radius: 8px;
            }

            .sidebar .nav-link:hover {
                background: rgba(255, 255, 255, 0.1);
                color: #fff !important;
            }

            .sidebar .nav-link.active {
                background: #007bff;
                color: #fff !important;
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
            }

            .sidebar .nav-link i {
                width: 20px;
                text-align: center;
            }

            .sidebar-heading {
                font-size: 0.85rem;
                text-transform: uppercase;
                letter-spacing: 0.05em;
            }

            .sidebar-heading a:hover {
                color: #007bff !important;
            }

            .user-profile img {
                transition: transform 0.3s;
            }

            .user-profile img:hover {
                transform: scale(1.1);
            }

            .badge.bg-primary {
                background: linear-gradient(45deg, #007bff, #00b7eb) !important;
            }

            .badge.bg-secondary {
                background: linear-gradient(45deg, #6c757d, #adb5bd) !important;
            }

            @media (max-width: 767.98px) {
                .sidebar {
                    position: fixed;
                    top: 0;
                    left: 0;
                    z-index: 1000;
                    width: 250px;
                    height: 100%;
                    transform: translateX(-100%);
                    transition: transform 0.3s ease-in-out;
                }

                .sidebar.show {
                    transform: translateX(0);
                }
            }
        </style>
    </div>

    <!-- Main Content Column -->
    <div class="col-md-9 col-lg-10 ms-sm-auto px-4">
        <div class="container-fluid px-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="mt-4">My Projects</h1>
                <?php if ($isAdmin || $isManager): ?>
                    <a href="create.php" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>New Project
                    </a>
                <?php endif; ?>
            </div>

            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-project-diagram me-1"></i>
                    Active Projects
                </div>
                <div class="card-body">
                    <?php if (empty($projects)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-folder-open fa-4x text-muted mb-3"></i>
                            <h5>No projects found</h5>
                            <p class="text-muted">You haven't been added to any projects yet</p>
                            <?php if ($isAdmin || $isManager): ?>
                                <a href="create.php" class="btn btn-primary mt-3">
                                    Create Your First Project
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover" id="projectsTable">
                                <thead>
                                    <tr>
                                        <th>Project Name</th>
                                        <th>Progress</th>
                                        <th>Tasks</th>
                                        <th>Members</th>
                                        <th>Owner</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($projects2 as $project): ?>
                                        <?php
                                        $progress = ($project['task_count'] > 0) 
                                            ? round(($project['completed_tasks'] / $project['task_count']) * 100) 
                                            : 0;
                                        ?>
                                        <tr>
                                            <td>
                                                <strong><?= htmlspecialchars($project['name']) ?></strong>
                                                <div class="text-muted small">
                                                    <?= htmlspecialchars(substr($project['description'], 0, 50)) ?>
                                                    <?= strlen($project['description']) > 50 ? '...' : '' ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="progress" style="height: 20px;">
                                                    <div class="progress-bar bg-success" 
                                                         role="progressbar" 
                                                         style="width: <?= $progress ?>%" 
                                                         aria-valuenow="<?= $progress ?>" 
                                                         aria-valuemin="0" 
                                                         aria-valuemax="100">
                                                        <?= $progress ?>%
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <?= $project['completed_tasks'] ?> / <?= $project['task_count'] ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-primary rounded-pill">
                                                    <?= $project['member_count'] ?>
                                                </span>
                                            </td>
                                            <td><?= htmlspecialchars($project['owner_username']) ?></td>
                                            <td>
                                                <?= date('M j, Y', strtotime($project['created_at'])) ?>
                                            </td>
                                            <td>
                                                <div class="d-flex gap-2">
                                                    <a href="view.php?id=<?= $project['id'] ?>" 
                                                       class="btn btn-sm btn-outline-primary"
                                                       title="View Project">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <?php if (hasProjectAccess($userId, $project['id'], 'owner') || $isAdmin): ?>
                                                        <a href="edit.php?id=<?= $project['id'] ?>" 
                                                           class="btn btn-sm btn-outline-secondary"
                                                           title="Edit Project">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<!-- Initialize DataTables -->
<script>
$(document).ready(function() {
    $('#projectsTable').DataTable({
        responsive: true,
        columnDefs: [
            { responsivePriority: 1, targets: 0 }, // Project name
            { responsivePriority: 2, targets: -1 } // Actions
        ]
    });
});
</script>