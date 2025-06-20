<?php
session_start();
require_once '..\includes/config.php';
require_once '..\includes/auth.php';

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

// Verify database connection
if (!isset($pdo)) {
    die("Database connection not established. Please check your config.php file.");
}

$user = $_SESSION['user'];

try {
    // Get user's projects
    $stmt = $pdo->prepare("
        SELECT p.id, p.name, p.description, COUNT(t.id) as task_count, 
               SUM(CASE WHEN t.status = 'done' THEN 1 ELSE 0 END) as completed_tasks
        FROM projects p
        JOIN project_members pm ON p.id = pm.project_id
        LEFT JOIN tasks t ON p.id = t.project_id
        WHERE pm.user_id = ?
        GROUP BY p.id, p.name, p.description
        ORDER BY p.created_at DESC
    ");
    $stmt->execute([$user['id']]);
    $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get recent activities
    $stmt = $pdo->prepare("
        SELECT al.*, u.username, p.name as project_name
        FROM activity_logs al
        LEFT JOIN users u ON al.user_id = u.id
        LEFT JOIN projects p ON al.project_id = p.id
        WHERE al.project_id IN (
            SELECT project_id FROM project_members WHERE user_id = ?
        )
        ORDER BY al.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$user['id']]);
    $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get task statistics
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_tasks,
            SUM(CASE WHEN status = 'todo' THEN 1 ELSE 0 END) as todo_tasks,
            SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_tasks,
            SUM(CASE WHEN status = 'done' THEN 1 ELSE 0 END) as done_tasks
        FROM tasks t
        JOIN project_members pm ON t.project_id = pm.project_id
        WHERE pm.user_id = ?
    ");
    $stmt->execute([$user['id']]);
    $task_stats = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
</head>
<body>
    <?php include '..\includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
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
                               href="<?= APP_URL ?>/chat/index.php" style="transition: all 0.3s;">
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
                               href="<?= APP_URL ?>/settings/profile.php" style="transition: all 0.3s;">
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
                        $sidebar_projects = $pdo->prepare("
                            SELECT p.id, p.name 
                            FROM projects p
                            JOIN project_members pm ON p.id = pm.project_id
                            WHERE pm.user_id = :userId
                            ORDER BY p.name
                            LIMIT 5
                        ");
                        $sidebar_projects->execute([':userId' => $_SESSION['user']['id']]);

                        while ($proj = $sidebar_projects->fetch(PDO::FETCH_ASSOC)) {
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
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Dashboard</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="projects.php" class="btn btn-sm btn-primary"><i class="fas fa-plus me-2"></i>New Project</a>
                    </div>
                </div>

                <!-- Task Statistics -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title"><i class="fas fa-tasks me-2"></i>Total Tasks</h5>
                                <p class="card-text display-6"><?php echo $task_stats['total_tasks']; ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title"><i class="fas fa-hourglass-start me-2"></i>To Do</h5>
                                <p class="card-text display-6"><?php echo $task_stats['todo_tasks']; ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title"><i class="fas fa-spinner me-2"></i>In Progress</h5>
                                <p class="card-text display-6"><?php echo $task_stats['in_progress_tasks']; ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title"><i class="fas fa-check-circle me-2"></i>Completed</h5>
                                <p class="card-text display-6"><?php echo $task_stats['done_tasks']; ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Projects List -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5><i class="fas fa-project-diagram me-2"></i>Your Projects</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Project Name</th>
                                        <th>Description</th>
                                        <th>Tasks</th>
                                        <th>Progress</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($projects as $project): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($project['name']); ?></td>
                                            <td><?php echo htmlspecialchars($project['description'] ?? 'No description'); ?></td>
                                            <td><?php echo $project['task_count']; ?></td>
                                            <td>
                                                <div class="progress">
                                                    <div class="progress-bar" style="width: <?php echo $project['task_count'] ? ($project['completed_tasks'] / $project['task_count'] * 100) : 0; ?>%">
                                                        <?php echo $project['task_count'] ? round($project['completed_tasks'] / $project['task_count'] * 100) : 0; ?>%
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <a href="file.php?project_id=<?php echo $project['id']; ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-comments"></i> Documents</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-history me-2"></i>Recent Activity</h5>
                    </div>
                    <div class="card-body">
                        <ul class="list-group">
                            <?php foreach ($activities as $activity): ?>
                                <li class="list-group-item">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1"><?php echo htmlspecialchars($activity['username'] ?? 'System'); ?> - <?php echo htmlspecialchars($activity['project_name'] ?? 'General'); ?></h6>
                                        <small><?php echo date('M d, H:i', strtotime($activity['created_at'])); ?></small>
                                    </div>
                                    <p class="mb-1"><?php echo htmlspecialchars($activity['description'] ?? $activity['action']); ?></p>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>

                <!-- Task Distribution Chart -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5><i class="fas fa-chart-pie me-2"></i>Task Distribution</h5>
                    </div>
                    <div class="card-body">
                        <script>
                        {
                            "type": "pie",
                            "data": {
                                "labels": ["To Do", "In Progress", "Completed"],
                                "datasets": [{
                                    "data": [<?php echo $task_stats['todo_tasks']; ?>, <?php echo $task_stats['in_progress_tasks']; ?>, <?php echo $task_stats['done_tasks']; ?>],
                                    "backgroundColor": ["#dc3545", "#ffc107", "#28a745"],
                                    "borderColor": ["#ffffff", "#ffffff", "#ffffff"],
                                    "borderWidth": 1
                                }]
                            },
                            "options": {
                                "responsive": true,
                                "plugins": {
                                    "legend": {
                                        "position": "top"
                                    },
                                    "title": {
                                        "display": true,
                                        "text": "Task Status Distribution"
                                    }
                                }
                            }
                        }
                        </script>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>