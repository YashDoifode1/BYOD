<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/auth.php';

// Check authentication
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

    $completedTasks = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM task_assignments ta
        JOIN tasks t ON ta.task_id = t.id
        WHERE ta.user_id = :userId AND t.status = 'done'
    ");
    $completedTasks->execute([':userId' => $userId]);
    $completedTasksCount = $completedTasks->fetch(PDO::FETCH_ASSOC)['count'];

    // Task status distribution
    $taskStatusStats = $pdo->prepare("
        SELECT status, COUNT(*) as count 
        FROM tasks t
        JOIN task_assignments ta ON t.id = ta.task_id
        WHERE ta.user_id = :userId
        GROUP BY status
    ");
    $taskStatusStats->execute([':userId' => $userId]);
    $taskStats = $taskStatusStats->fetchAll(PDO::FETCH_ASSOC);

    // Recent activity
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

    // Upcoming deadlines
    $upcomingTasks = $pdo->prepare("
        SELECT t.id, t.title, t.due_date, p.name as project_name
        FROM tasks t
        JOIN task_assignments ta ON t.id = ta.task_id
        JOIN projects p ON t.project_id = p.id
        WHERE ta.user_id = :userId 
        AND t.status != 'done'
        AND t.due_date >= NOW()
        ORDER BY t.due_date ASC
        LIMIT 3
    ");
    $upcomingTasks->execute([':userId' => $userId]);
    $upcoming = $upcomingTasks->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

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

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">
    <meta name="theme-color" content="#563d7c">
    <title>Dashboard - Project Management System</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Custom CSS -->
    <style>
        :root {
            --primary-color: #4e73df;
            --secondary-color: #858796;
            --success-color: #1cc88a;
            --info-color: #36b9cc;
            --warning-color: #f6c23e;
            --danger-color: #e74a3b;
            --light-color: #f8f9fc;
            --dark-color: #5a5c69;
        }
        
        body.dashboard {
            background-color: #f8f9fc;
        }
        
        .sidebar {
            background: linear-gradient(180deg, var(--primary-color) 10%, #224abe 100%);
            min-height: 100vh;
        }
        
        .avatar {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }
        
        .stat-card {
            transition: transform 0.3s;
            border: none;
            border-radius: 10px;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-icon {
            font-size: 2rem;
            opacity: 0.7;
        }
        
        .progress-thin {
            height: 5px;
        }
        
        .task-priority-high {
            border-left: 4px solid #e74a3b;
        }
        
        .task-priority-medium {
            border-left: 4px solid #f6c23e;
        }
        
        .task-priority-low {
            border-left: 4px solid #1cc88a;
        }
        
        @media (max-width: 768px) {
            .card-body {
                padding: 1rem;
            }
            
            .stat-card .card-body {
                padding: 1rem 0.5rem;
            }
            
            .stat-card h2 {
                font-size: 1.5rem;
            }
            
            .avatar {
                width: 36px;
                height: 36px;
                font-size: 0.8rem;
            }
            
            .quick-actions .btn {
                font-size: 0.8rem;
                padding: 0.5rem;
            }
        }
    </style>
</head>
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
                            <button type="button" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-download"></i> Export
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-print"></i> Print
                            </button>
                        </div>
                        <div class="dropdown">
                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="timePeriodDropdown" data-bs-toggle="dropdown">
                                <i class="fas fa-calendar-alt"></i> This week
                            </button>
                            <ul class="dropdown-menu" aria-labelledby="timePeriodDropdown">
                                <li><a class="dropdown-item" href="#">Today</a></li>
                                <li><a class="dropdown-item" href="#">This week</a></li>
                                <li><a class="dropdown-item" href="#">This month</a></li>
                                <li><a class="dropdown-item" href="#">This year</a></li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Welcome Message -->
                <div class="alert alert-primary alert-dismissible fade show" role="alert">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-user-circle me-3 fa-2x"></i>
                        <div>
                            <h4 class="alert-heading mb-1">Welcome back, <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>!</h4>
                            <p class="mb-0">You have <?php echo $pendingTasksCount; ?> pending tasks to complete.</p>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>

                <!-- Stats Cards -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card border-left-primary shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                            Active Projects</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $activeProjectsCount; ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-project-diagram stat-icon text-primary"></i>
                                    </div>
                                </div>
                            </div>
                            <a href="projects.php" class="stretched-link"></a>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card border-left-warning shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                            Pending Tasks</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $pendingTasksCount; ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-tasks stat-icon text-warning"></i>
                                    </div>
                                </div>
                            </div>
                            <a href="tasks.php" class="stretched-link"></a>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card border-left-success shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                            Completed Tasks</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $completedTasksCount; ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-check-circle stat-icon text-success"></i>
                                    </div>
                                </div>
                            </div>
                            <a href="tasks.php?status=done" class="stretched-link"></a>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card border-left-info shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                            Task Completion</div>
                                        <div class="row no-gutters align-items-center">
                                            <div class="col-auto">
                                                <div class="h5 mb-0 mr-3 font-weight-bold text-gray-800">
                                                    <?php echo $activeProjectsCount > 0 ? round(($completedTasksCount / ($completedTasksCount + $pendingTasksCount)) * 100) : 0; ?>%
                                                </div>
                                            </div>
                                            <div class="col">
                                                <div class="progress progress-sm mr-2">
                                                    <div class="progress-bar bg-info" role="progressbar"
                                                        style="width: <?php echo $activeProjectsCount > 0 ? round(($completedTasksCount / ($completedTasksCount + $pendingTasksCount)) * 100) : 0; ?>%"
                                                        aria-valuenow="<?php echo $activeProjectsCount > 0 ? round(($completedTasksCount / ($completedTasksCount + $pendingTasksCount)) * 100) : 0; ?>"
                                                        aria-valuemin="0" aria-valuemax="100"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-clipboard-list stat-icon text-info"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Content Row -->
                <div class="row">
                    <!-- Task Status Chart -->
                    <div class="col-lg-6 mb-4">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                                <h6 class="m-0 font-weight-bold text-primary">Task Status Distribution</h6>
                                <div class="dropdown no-arrow">
                                    <a class="dropdown-toggle" href="#" role="button" id="dropdownMenuLink"
                                        data-bs-toggle="dropdown" aria-expanded="false">
                                        <i class="fas fa-ellipsis-v fa-sm fa-fw text-gray-400"></i>
                                    </a>
                                    <ul class="dropdown-menu dropdown-menu-end shadow animated--fade-in"
                                        aria-labelledby="dropdownMenuLink">
                                        <li><a class="dropdown-item" href="#">View Details</a></li>
                                        <li><a class="dropdown-item" href="#">Export Data</a></li>
                                        <li><a class="dropdown-item" href="#">Print Chart</a></li>
                                    </ul>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="chart-pie pt-4 pb-2">
                                    <canvas id="taskStatusChart"></canvas>
                                </div>
                                <div class="mt-4 text-center small">
                                    <?php foreach ($taskStats as $stat): ?>
                                        <span class="mr-2">
                                            <i class="fas fa-circle <?php 
                                                echo $stat['status'] == 'todo' ? 'text-danger' : 
                                                    ($stat['status'] == 'in_progress' ? 'text-primary' : 'text-success'); 
                                            ?>"></i> 
                                            <?php echo ucfirst(str_replace('_', ' ', $stat['status'])); ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Upcoming Deadlines -->
                    <div class="col-lg-6 mb-4">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                                <h6 class="m-0 font-weight-bold text-primary">Upcoming Deadlines</h6>
                                <a href="tasks.php" class="btn btn-sm btn-primary">View All</a>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($upcoming)): ?>
                                    <div class="list-group list-group-flush">
                                        <?php foreach ($upcoming as $task): ?>
                                            <div class="list-group-item list-group-item-action">
                                                <div class="d-flex w-100 justify-content-between">
                                                    <h6 class="mb-1"><?php echo htmlspecialchars($task['title']); ?></h6>
                                                    <small><?php echo date('M j', strtotime($task['due_date'])); ?></small>
                                                </div>
                                                <p class="mb-1"><?php echo htmlspecialchars($task['project_name']); ?></p>
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <small class="text-muted">Due in <?php echo time_elapsed_string($task['due_date']); ?></small>
                                                    <span class="badge bg-light text-dark">
                                                        <i class="fas fa-calendar-day me-1"></i> Deadline
                                                    </span>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-4 text-muted">
                                        <i class="fas fa-check-circle fa-2x mb-2"></i>
                                        <p>No upcoming deadlines</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card shadow">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">Quick Actions</h6>
                            </div>
                            <div class="card-body">
                                <div class="row g-2">
                                    <div class="col-6 col-md-3">
                                        <a href="tasks/tasks.php?new=true" class="btn btn-outline-primary w-100 py-3">
                                            <i class="fas fa-plus-circle me-2"></i> New Task
                                        </a>
                                    </div>
                                    <div class="col-6 col-md-3">
                                        <a href="projects/projects.php?new=true" class="btn btn-outline-success w-100 py-3">
                                            <i class="fas fa-project-diagram me-2"></i> New Project
                                        </a>
                                    </div>
                                    <div class="col-6 col-md-3">
                                        <a href="chat/index.php" class="btn btn-outline-info w-100 py-3">
                                            <i class="fas fa-comments me-2"></i> Messages
                                        </a>
                                    </div>
                                    <div class="col-6 col-md-3">
                                        <a href="files/manage.php" class="btn btn-outline-secondary w-100 py-3">
                                            <i class="fas fa-file-upload me-2"></i> Upload File
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="row">
                    <div class="col-12">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                                <h6 class="m-0 font-weight-bold text-primary">Recent Activity</h6>
                                <div class="dropdown no-arrow">
                                    <a class="dropdown-toggle" href="#" role="button" id="dropdownMenuLink"
                                        data-bs-toggle="dropdown" aria-expanded="false">
                                        <i class="fas fa-ellipsis-v fa-sm fa-fw text-gray-400"></i>
                                    </a>
                                    <ul class="dropdown-menu dropdown-menu-end shadow animated--fade-in"
                                        aria-labelledby="dropdownMenuLink">
                                        <li><a class="dropdown-item" href="#">Filter</a></li>
                                        <li><a class="dropdown-item" href="#">Export</a></li>
                                        <li><a class="dropdown-item" href="#">Refresh</a></li>
                                    </ul>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="list-group list-group-flush">
                                    <?php foreach ($activities as $activity): ?>
                                    <div class="list-group-item list-group-item-action">
                                        <div class="d-flex w-100 justify-content-between">
                                            <div class="d-flex">
                                                <div class="me-3">
                                                    <div class="avatar bg-primary text-white rounded-circle p-2">
                                                        <?php echo strtoupper(substr($activity['action'], 0, 1)); ?>
                                                    </div>
                                                </div>
                                                <div>
                                                    <h6 class="mb-1"><?php echo htmlspecialchars(ucfirst($activity['action'])); ?></h6>
                                                    <p class="mb-1"><?php echo htmlspecialchars($activity['description']); ?></p>
                                                    <small class="text-muted">
                                                        <?php if (!empty($activity['project_name'])): ?>
                                                        <span class="badge bg-light text-dark me-2">
                                                            <?php echo htmlspecialchars($activity['project_name']); ?>
                                                        </span>
                                                        <?php endif; ?>
                                                        <?php echo time_elapsed_string($activity['created_at']); ?>
                                                    </small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                    <?php if (empty($activities)): ?>
                                    <div class="list-group-item text-center py-4 text-muted">
                                        <i class="fas fa-history fa-2x mb-2"></i>
                                        <p>No recent activity found</p>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom Scripts -->
    <script>
        // Initialize charts
        document.addEventListener('DOMContentLoaded', function() {
            // Task Status Chart
            const ctx = document.getElementById('taskStatusChart').getContext('2d');
            const taskStatusChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: <?php echo json_encode(array_map(function($stat) { 
                        return ucfirst(str_replace('_', ' ', $stat['status'])); 
                    }, $taskStats)); ?>,
                    datasets: [{
                        data: <?php echo json_encode(array_column($taskStats, 'count')); ?>,
                        backgroundColor: [
                            '#e74a3b', // todo
                            '#4e73df', // in_progress
                            '#1cc88a'  // done
                        ],
                        hoverBackgroundColor: [
                            '#e02d1b',
                            '#2e59d9',
                            '#17a673'
                        ],
                        hoverBorderColor: "rgba(234, 236, 244, 1)",
                    }],
                },
                options: {
                    maintainAspectRatio: false,
                    tooltips: {
                        backgroundColor: "rgb(255,255,255)",
                        bodyFontColor: "#858796",
                        borderColor: '#dddfeb',
                        borderWidth: 1,
                        xPadding: 15,
                        yPadding: 15,
                        displayColors: false,
                        caretPadding: 10,
                    },
                    legend: {
                        display: false
                    },
                    cutoutPercentage: 80,
                },
            });

            // Refresh activity button
            document.getElementById('refreshActivity').addEventListener('click', function() {
                location.reload();
            });

            // Tooltip initialization
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });
    </script>
</body>
</html>