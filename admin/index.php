<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/header.php';

// Check admin permissions
if ($_SESSION['user_role'] !== 'admin') {
    header("Location: /unauthorized.php");
    exit();
}

// Database connection
require_once __DIR__ . '/includes/config.php';

// Get stats for dashboard
try {
    $users_count = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $projects_count = $pdo->query("SELECT COUNT(*) FROM projects")->fetchColumn();
    $active_tasks = $pdo->query("SELECT COUNT(*) FROM tasks WHERE status != 'done'")->fetchColumn();
    
    // Get recent activities with usernames using a prepared statement
    $stmt = $pdo->prepare("
        SELECT a.*, u.username 
        FROM activity_logs a
        LEFT JOIN users u ON a.user_id = u.id
        ORDER BY a.created_at DESC 
        LIMIT 5
    ");
    $stmt->execute();
    $recent_activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    // Set default values if query fails
    $users_count = 0;
    $projects_count = 0;
    $active_tasks = 0;
    $recent_activities = [];
}
?>

<div class="container-fluid">
    <div class="row">
        <!-- Main Content (sidebar is already included in header.php) -->
        <main class="col-md-12 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Dashboard Overview</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <button type="button" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-sync-alt"></i> Refresh
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-download"></i> Export
                        </button>
                    </div>
                    <div class="btn-group">
                        <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle">
                            <i class="fas fa-calendar-alt"></i> This week
                        </button>
                    </div>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="row mb-4">
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-primary shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                        Total Users</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $users_count ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-users fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-success shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                        Active Projects</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $projects_count ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-project-diagram fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-warning shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                        Pending Tasks</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $active_tasks ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-tasks fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-info shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                        System Health</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">100%</div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-heartbeat fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Content Row -->
            <div class="row">
                <!-- Recent Activity -->
                <div class="col-lg-8 mb-4">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                            <h6 class="m-0 font-weight-bold text-primary">Recent Activity</h6>
                            <div class="dropdown no-arrow">
                                <a class="dropdown-toggle" href="#" role="button" id="dropdownMenuLink"
                                    data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                    <i class="fas fa-ellipsis-v fa-sm fa-fw text-gray-400"></i>
                                </a>
                                <div class="dropdown-menu dropdown-menu-right shadow animated--fade-in"
                                    aria-labelledby="dropdownMenuLink">
                                    <div class="dropdown-header">Activity Options:</div>
                                    <a class="dropdown-item" href="#">View All</a>
                                    <a class="dropdown-item" href="#">Filter</a>
                                    <div class="dropdown-divider"></div>
                                    <a class="dropdown-item" href="#">Export</a>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead class="thead-light">
                                        <tr>
                                            <th>Time</th>
                                            <th>User</th>
                                            <th>Action</th>
                                            <th>Details</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_activities as $activity): ?>
                                        <tr>
                                            <td><?= date('M j, Y g:i a', strtotime($activity['created_at'])) ?></td>
                                            <td><?= $activity['username'] ?? 'System' ?></td>
                                            <td><span class="badge badge-<?= 
                                                strpos($activity['action'], 'create') !== false ? 'success' : 
                                                (strpos($activity['action'], 'delete') !== false ? 'danger' : 'info')
                                            ?>"><?= htmlspecialchars($activity['action']) ?></span></td>
                                            <td><?= htmlspecialchars($activity['description']) ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                        <?php if (empty($recent_activities)): ?>
                                        <tr>
                                            <td colspan="4" class="text-center">No recent activities found</td>
                                        </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Stats -->
                <div class="col-lg-4 mb-4">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Quick Stats</h6>
                        </div>
                        <div class="card-body">
                            <div class="chart-pie pt-4 pb-2">
                                <canvas id="myPieChart"></canvas>
                            </div>
                            <div class="mt-4 text-center small">
                                <span class="mr-2">
                                    <i class="fas fa-circle text-primary"></i> Users
                                </span>
                                <span class="mr-2">
                                    <i class="fas fa-circle text-success"></i> Projects
                                </span>
                                <span class="mr-2">
                                    <i class="fas fa-circle text-info"></i> Tasks
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- System Info -->
                    <div class="card shadow">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">System Information</h6>
                        </div>
                        <div class="card-body">
                            <p><i class="fas fa-server mr-2"></i> <strong>Server:</strong> <?= $_SERVER['SERVER_SOFTWARE'] ?></p>
                            <p><i class="fas fa-database mr-2"></i> <strong>PHP:</strong> <?= phpversion() ?></p>
                            <p><i class="fas fa-clock mr-2"></i> <strong>Last Updated:</strong> <?= date('Y-m-d H:i:s') ?></p>
                            <hr>
                            <div class="text-center">
                                <a href="#" class="btn btn-sm btn-primary">View Details</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Bootstrap core JavaScript-->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<!-- Custom scripts -->
<script>
// Pie Chart Example
$(document).ready(function() {
    var ctx = document.getElementById("myPieChart");
    var myPieChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ["Users", "Projects", "Tasks"],
            datasets: [{
                data: [<?= $users_count ?>, <?= $projects_count ?>, <?= $active_tasks ?>],
                backgroundColor: ['#4e73df', '#1cc88a', '#36b9cc'],
                hoverBackgroundColor: ['#2e59d9', '#17a673', '#2c9faf'],
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
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>