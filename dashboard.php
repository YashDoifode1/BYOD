<?php
session_start();
require_once 'includes/config.php'; // Database configuration
require_once 'includes/auth.php'; // Authentication functions

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

// Get current user data
$user = $_SESSION['user'];
$userId = $user['id'];

// Database connection
try {
    $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Fetch user stats
    $activeProjects = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM project_members 
        WHERE user_id = :userId
    ");
    $activeProjects->execute([':userId' => $userId]);
    $activeProjectsCount = $activeProjects->fetch(PDO::FETCH_ASSOC)['count'];

    $pendingTasks = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM task_assignments ta
        JOIN tasks t ON ta.task_id = t.id
        WHERE ta.user_id = :userId AND t.status != 'done'
    ");
    $pendingTasks->execute([':userId' => $userId]);
    $pendingTasksCount = $pendingTasks->fetch(PDO::FETCH_ASSOC)['count'];

    $recentActivity = $pdo->prepare("
        SELECT a.action, a.description, a.created_at, p.name as project_name
        FROM activity_logs a
        LEFT JOIN projects p ON a.project_id = p.id
        WHERE a.user_id = :userId OR a.project_id IN (
            SELECT project_id FROM project_members WHERE user_id = :userId
        )
        ORDER BY a.created_at DESC
        LIMIT 5
    ");
    $recentActivity->execute([':userId' => $userId]);
    $activities = $recentActivity->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>

<?php include 'includes/header.php'; ?>

<body class="dashboard">
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar Navigation -->
            <?php include 'includes/sidebar.php'; ?>

            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Dashboard</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary">Share</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary">Export</button>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle">
                            <span data-feather="calendar"></span>
                            This week
                        </button>
                    </div>
                </div>

                <!-- Welcome Message -->
                <div class="alert alert-primary" role="alert">
                    <h4 class="alert-heading">Welcome back, <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>!</h4>
                    <p>Here's what's happening with your projects today.</p>
                </div>

                <!-- Stats Cards -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="card text-white bg-primary mb-3">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h5 class="card-title">Active Projects</h5>
                                        <h2 class="mb-0"><?php echo $activeProjectsCount; ?></h2>
                                    </div>
                                    <i class="fas fa-project-diagram fa-3x"></i>
                                </div>
                                <a href="projects.php" class="text-white stretched-link"></a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card text-white bg-warning mb-3">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h5 class="card-title">Pending Tasks</h5>
                                        <h2 class="mb-0"><?php echo $pendingTasksCount; ?></h2>
                                    </div>
                                    <i class="fas fa-tasks fa-3x"></i>
                                </div>
                                <a href="tasks.php" class="text-white stretched-link"></a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card text-white bg-success mb-3">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h5 class="card-title">Completed Tasks</h5>
                                        <h2 class="mb-0">24</h2>
                                    </div>
                                    <i class="fas fa-check-circle fa-3x"></i>
                                </div>
                                <a href="tasks.php?status=done" class="text-white stretched-link"></a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5>Recent Activity</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Action</th>
                                        <th>Description</th>
                                        <th>Project</th>
                                        <th>Time</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($activities as $activity): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($activity['action']); ?></td>
                                        <td><?php echo htmlspecialchars($activity['description']); ?></td>
                                        <td><?php echo htmlspecialchars($activity['project_name'] ?? 'System'); ?></td>
                                        <td><?php echo time_elapsed_string($activity['created_at']); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($activities)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center">No recent activity found</td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>
</body>
</html>

<?php
// Helper function to format time
function time_elapsed_string($datetime, $full = false) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    $diff->w = floor($diff->d / 7);
    $diff->d -= $diff->w * 7;

    $string = array(
        'y' => 'year',
        'm' => 'month',
        'w' => 'week',
        'd' => 'day',
        'h' => 'hour',
        'i' => 'minute',
        's' => 'second',
    );
    
    foreach ($string as $k => &$v) {
        if ($diff->$k) {
            $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
        } else {
            unset($string[$k]);
        }
    }

    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? implode(', ', $string) . ' ago' : 'just now';
}
?>