<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/header.php';

// Check permissions
// if (!has_permission('manager')) {
//     header("Location: /unauthorized.php");
//     exit();
// }

// Database connection
require_once __DIR__ . '/../../includes/config.php';

// Date range setup (last 30 days by default)
$end_date = date('Y-m-d');
$start_date = date('Y-m-d', strtotime('-30 days'));

// Override with user selection if provided
if (isset($_GET['date_range'])) {
    $dates = explode(' - ', $_GET['date_range']);
    if (count($dates) === 2) {
        $start_date = date('Y-m-d', strtotime(trim($dates[0])));
        $end_date = date('Y-m-d', strtotime(trim($dates[1])));
    }
}

// Get summary statistics
$summary = $pdo->query("
    SELECT 
        (SELECT COUNT(*) FROM users) as total_users,
        (SELECT COUNT(*) FROM projects) as total_projects,
        (SELECT COUNT(*) FROM tasks) as total_tasks,
        (SELECT COUNT(*) FROM tasks WHERE status = 'done') as completed_tasks,
        (SELECT COUNT(*) FROM files) as total_files
")->fetch(PDO::FETCH_ASSOC);

// Get user growth data for chart
$user_growth = $pdo->prepare("
    SELECT 
        DATE(created_at) as date,
        COUNT(*) as count,
        SUM(COUNT(*)) OVER (ORDER BY DATE(created_at)) as cumulative
    FROM users
    WHERE created_at BETWEEN ? AND ?
    GROUP BY DATE(created_at)
    ORDER BY DATE(created_at)
");
$user_growth->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
$user_growth_data = $user_growth->fetchAll(PDO::FETCH_ASSOC);

// Get project status distribution
$project_status = $pdo->query("
    SELECT 
        CASE 
            WHEN EXISTS (SELECT 1 FROM tasks t WHERE t.project_id = p.id AND t.status != 'done') THEN 'Active'
            ELSE 'Completed'
        END as status,
        COUNT(*) as count
    FROM projects p
    GROUP BY status
")->fetchAll(PDO::FETCH_ASSOC);

// Get task distribution by status
$task_status = $pdo->query("
    SELECT 
        status,
        COUNT(*) as count
    FROM tasks
    GROUP BY status
")->fetchAll(PDO::FETCH_ASSOC);

// Get task completion rate
$completion_rate = $pdo->query("
    SELECT 
        ROUND((SUM(CASE WHEN status = 'done' THEN 1 ELSE 0 END) / COUNT(*)) * 100, 1) as rate
    FROM tasks
")->fetchColumn();

// Get recent activities
$recent_activities = $pdo->query("
    SELECT a.*, u.username, p.name as project_name
    FROM activity_logs a
    LEFT JOIN users u ON a.user_id = u.id
    LEFT JOIN projects p ON a.project_id = p.id
    ORDER BY a.created_at DESC
    LIMIT 8
")->fetchAll(PDO::FETCH_ASSOC);

// Prepare data for charts
$user_growth_dates = array_column($user_growth_data, 'date');
$user_growth_counts = array_column($user_growth_data, 'count');
$user_growth_cumulative = array_column($user_growth_data, 'cumulative');

$project_status_labels = array_map(function($item) {
    return $item['status'];
}, $project_status);
$project_status_values = array_map(function($item) {
    return $item['count'];
}, $project_status);

$task_status_labels = array_map(function($item) {
    return ucfirst(str_replace('_', ' ', $item['status']));
}, $task_status);
$task_status_values = array_map(function($item) {
    return $item['count'];
}, $task_status);
?>

<!-- Rest of your HTML/CSS/JavaScript remains the same -->
<style>/* Analytics Dashboard Specific Styles */
.chart-area {
    position: relative;
    height: 300px;
    width: 100%;
}

.chart-pie {
    position: relative;
    height: 250px;
    width: 100%;
}

.card {
    transition: all 0.3s;
}

.card:hover {
    transform: translateY(-5px);
    box-shadow: 0 0.5rem 1.5rem rgba(0, 0, 0, 0.15);
}

.activity-feed {
    max-height: 500px;
    overflow-y: auto;
}

.activity-item {
    padding-bottom: 1rem;
    border-bottom: 1px solid #eee;
}

.activity-item:last-child {
    border-bottom: none;
}

/* Custom scrollbar for activity feed */
.activity-feed::-webkit-scrollbar {
    width: 6px;
}

.activity-feed::-webkit-scrollbar-track {
    background: #f1f1f1;
}

.activity-feed::-webkit-scrollbar-thumb {
    background: #888;
    border-radius: 3px;
}

.activity-feed::-webkit-scrollbar-thumb:hover {
    background: #555;
}

/* KPI Card Styles */
.card-border-left {
    border-left: 0.25rem solid;
}

.border-primary {
    border-color: #4e73df !important;
}

.border-success {
    border-color: #1cc88a !important;
}

.border-info {
    border-color: #36b9cc !important;
}

.border-warning {
    border-color: #f6c23e !important;
}

.border-danger {
    border-color: #e74a3b !important;
}

.text-gray-800 {
    color: #5a5c69 !important;
}

.text-gray-300 {
    color: #dddfeb !important;
}</style>
<div class="container-fluid">
    <div class="row">
       
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Analytics Dashboard</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="input-group input-group-sm me-2" style="width: 250px;">
                        <span class="input-group-text"><i class="fas fa-calendar-alt"></i></span>
                        <input type="text" class="form-control" id="dateRangePicker" value="<?= date('m/d/Y', strtotime($start_date)) ?> - <?= date('m/d/Y', strtotime($end_date)) ?>">
                        <button class="btn btn-outline-secondary" type="button" id="applyDateRange">
                            <i class="fas fa-check"></i>
                        </button>
                    </div>
                    <div class="btn-group me-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary">Export</button>
                    </div>
                </div>
            </div>

            <!-- Summary Cards -->
            <div class="row mb-4">
                <div class="col-xl-2 col-md-4 col-sm-6 mb-4">
                    <div class="card border-start border-primary border-4 shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col me-2">
                                    <div class="text-xs fw-bold text-primary text-uppercase mb-1">
                                        Total Users</div>
                                    <div class="h5 mb-0 fw-bold text-gray-800"><?= $summary['total_users'] ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-users fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-2 col-md-4 col-sm-6 mb-4">
                    <div class="card border-start border-success border-4 shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col me-2">
                                    <div class="text-xs fw-bold text-success text-uppercase mb-1">
                                        Total Projects</div>
                                    <div class="h5 mb-0 fw-bold text-gray-800"><?= $summary['total_projects'] ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-project-diagram fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-2 col-md-4 col-sm-6 mb-4">
                    <div class="card border-start border-info border-4 shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col me-2">
                                    <div class="text-xs fw-bold text-info text-uppercase mb-1">
                                        Total Tasks</div>
                                    <div class="h5 mb-0 fw-bold text-gray-800"><?= $summary['total_tasks'] ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-tasks fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-2 col-md-4 col-sm-6 mb-4">
                    <div class="card border-start border-warning border-4 shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col me-2">
                                    <div class="text-xs fw-bold text-warning text-uppercase mb-1">
                                        Completed Tasks</div>
                                    <div class="h5 mb-0 fw-bold text-gray-800"><?= $summary['completed_tasks'] ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-2 col-md-4 col-sm-6 mb-4">
                    <div class="card border-start border-danger border-4 shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col me-2">
                                    <div class="text-xs fw-bold text-danger text-uppercase mb-1">
                                        Completion Rate</div>
                                    <div class="h5 mb-0 fw-bold text-gray-800"><?= $completion_rate ?>%</div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-percent fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-2 col-md-4 col-sm-6 mb-4">
                    <div class="card border-start border-secondary border-4 shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col me-2">
                                    <div class="text-xs fw-bold text-secondary text-uppercase mb-1">
                                        Total Files</div>
                                    <div class="h5 mb-0 fw-bold text-gray-800"><?= $summary['total_files'] ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-file-alt fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Row 1 -->
            <div class="row mb-4">
                <!-- User Growth Chart -->
                <div class="col-xl-8 col-lg-7">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                            <h6 class="m-0 fw-bold text-primary">User Growth</h6>
                            <div class="dropdown no-arrow">
                                <a class="dropdown-toggle" href="#" role="button" id="dropdownMenuLink" data-bs-toggle="dropdown">
                                    <i class="fas fa-ellipsis-v fa-sm fa-fw text-gray-400"></i>
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end shadow">
                                    <li><a class="dropdown-item" href="#">Export Data</a></li>
                                    <li><a class="dropdown-item" href="#">Print Chart</a></li>
                                </ul>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="chart-area">
                                <canvas id="userGrowthChart" height="300"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Project Status Chart -->
                <div class="col-xl-4 col-lg-5">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                            <h6 class="m-0 fw-bold text-primary">Project Status</h6>
                        </div>
                        <div class="card-body">
                            <div class="chart-pie pt-4 pb-2">
                                <canvas id="projectStatusChart" height="250"></canvas>
                            </div>
                            <div class="mt-4 text-center small">
                                <?php foreach ($project_status as $status): ?>
                                    <span class="me-3">
                                        <i class="fas fa-circle <?= $status['status'] === 'Active' ? 'text-primary' : 'text-success' ?>"></i>
                                        <?= $status['status'] ?> (<?= $status['count'] ?>)
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Row 2 -->
            <div class="row">
                <!-- Task Status Chart -->
                <div class="col-xl-4 col-lg-5">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                            <h6 class="m-0 fw-bold text-primary">Task Status Distribution</h6>
                        </div>
                        <div class="card-body">
                            <div class="chart-pie pt-4 pb-2">
                                <canvas id="taskStatusChart" height="250"></canvas>
                            </div>
                            <div class="mt-4 text-center small">
                                <?php foreach ($task_status as $status): ?>
                                    <span class="me-2 d-block d-sm-inline-block mb-1">
                                        <i class="fas fa-circle 
                                            <?= $status['status'] === 'done' ? 'text-success' : 
                                              ($status['status'] === 'in_progress' ? 'text-warning' : 'text-secondary') ?>"></i>
                                        <?= ucfirst(str_replace('_', ' ', $status['status'])) ?> (<?= $status['count'] ?>)
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="col-xl-8 col-lg-7">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                            <h6 class="m-0 fw-bold text-primary">Recent Activity</h6>
                            <a href="../logs/index.php" class="btn btn-sm btn-link">View All</a>
                        </div>
                        <div class="card-body">
                            <div class="activity-feed">
                                <?php foreach ($recent_activities as $activity): ?>
                                <div class="activity-item mb-3 pb-3 border-bottom">
                                    <div class="d-flex justify-content-between small text-muted mb-1">
                                        <span>
                                            <strong><?= htmlspecialchars($activity['username'] ?? 'System') ?></strong>
                                            <?= $activity['project_name'] ? ' in ' . htmlspecialchars($activity['project_name']) : '' ?>
                                        </span>
                                        <span><?= date('M j, g:i a', strtotime($activity['created_at'])) ?></span>
                                    </div>
                                    <div class="fw-bold text-primary"><?= ucfirst(htmlspecialchars($activity['action'])) ?></div>
                                    <div class="small"><?= htmlspecialchars($activity['description']) ?></div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<!-- Date Range Picker -->
<script type="text/javascript" src="https://cdn.jsdelivr.net/momentjs/latest/moment.min.js"></script>
<script type="text/javascript" src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>
<link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css" />

<script>
// Initialize date range picker
$(document).ready(function() {
    $('#dateRangePicker').daterangepicker({
        opens: 'left',
        ranges: {
            'Today': [moment(), moment()],
            'Yesterday': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
            'Last 7 Days': [moment().subtract(6, 'days'), moment()],
            'Last 30 Days': [moment().subtract(29, 'days'), moment()],
            'This Month': [moment().startOf('month'), moment().endOf('month')],
            'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')]
        },
        locale: {
            format: 'MM/DD/YYYY'
        }
    });

    $('#applyDateRange').click(function() {
        const dates = $('#dateRangePicker').val().split(' - ');
        if (dates.length === 2) {
            window.location.href = 'index.php?date_range=' + encodeURIComponent($('#dateRangePicker').val());
        }
    });
});

// User Growth Chart
const userGrowthCtx = document.getElementById('userGrowthChart').getContext('2d');
const userGrowthChart = new Chart(userGrowthCtx, {
    type: 'line',
    data: {
        labels: <?= json_encode($user_growth_dates) ?>,
        datasets: [{
            label: 'New Users',
            data: <?= json_encode($user_growth_counts) ?>,
            backgroundColor: 'rgba(78, 115, 223, 0.05)',
            borderColor: 'rgba(78, 115, 223, 1)',
            pointBackgroundColor: 'rgba(78, 115, 223, 1)',
            pointBorderColor: '#fff',
            pointHoverBackgroundColor: '#fff',
            pointHoverBorderColor: 'rgba(78, 115, 223, 1)',
            fill: 'origin'
        }, {
            label: 'Total Users',
            data: <?= json_encode($user_growth_cumulative) ?>,
            backgroundColor: 'rgba(28, 200, 138, 0.05)',
            borderColor: 'rgba(28, 200, 138, 1)',
            pointBackgroundColor: 'rgba(28, 200, 138, 1)',
            pointBorderColor: '#fff',
            pointHoverBackgroundColor: '#fff',
            pointHoverBorderColor: 'rgba(28, 200, 138, 1)',
            fill: 'origin'
        }]
    },
    options: {
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'top',
            },
            tooltip: {
                mode: 'index',
                intersect: false,
            }
        },
        scales: {
            x: {
                grid: {
                    display: false
                }
            },
            y: {
                beginAtZero: true,
                ticks: {
                    precision: 0
                }
            }
        }
    }
});

// Project Status Chart
const projectStatusCtx = document.getElementById('projectStatusChart').getContext('2d');
const projectStatusChart = new Chart(projectStatusCtx, {
    type: 'doughnut',
    data: {
        labels: <?= json_encode($project_status_labels) ?>,
        datasets: [{
            data: <?= json_encode($project_status_values) ?>,
            backgroundColor: ['#4e73df', '#1cc88a'],
            hoverBackgroundColor: ['#2e59d9', '#17a673'],
            hoverBorderColor: "rgba(234, 236, 244, 1)",
        }],
    },
    options: {
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        const label = context.label || '';
                        const value = context.raw || 0;
                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                        const percentage = Math.round((value / total) * 100);
                        return `${label}: ${value} (${percentage}%)`;
                    }
                }
            }
        },
        cutout: '70%',
    },
});

// Task Status Chart
const taskStatusCtx = document.getElementById('taskStatusChart').getContext('2d');
const taskStatusChart = new Chart(taskStatusCtx, {
    type: 'doughnut',
    data: {
        labels: <?= json_encode($task_status_labels) ?>,
        datasets: [{
            data: <?= json_encode($task_status_values) ?>,
            backgroundColor: ['#6c757d', '#f6c23e', '#1cc88a'],
            hoverBackgroundColor: ['#5a6268', '#dda20a', '#17a673'],
            hoverBorderColor: "rgba(234, 236, 244, 1)",
        }],
    },
    options: {
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        const label = context.label || '';
                        const value = context.raw || 0;
                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                        const percentage = Math.round((value / total) * 100);
                        return `${label}: ${value} (${percentage}%)`;
                    }
                }
            }
        },
        cutout: '70%',
    },
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>