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

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --secondary: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --info: #3b82f6;
            --dark: #1e293b;
            --light: #f8fafc;
            --gray: #94a3b8;
            --card-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f1f5f9;
            color: var(--dark);
        }
        
        .glass-card {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(10px);
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: var(--transition);
        }
        
        .glass-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--card-shadow);
        }
        
        .dashboard-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            border-radius: 0 0 20px 20px;
            padding: 2rem 0;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
        
        .stat-card {
            position: relative;
            overflow: hidden;
            border-radius: 12px;
            transition: var(--transition);
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: var(--primary);
        }
        
        .stat-card.primary::before { background: var(--primary); }
        .stat-card.success::before { background: var(--secondary); }
        .stat-card.warning::before { background: var(--warning); }
        .stat-card.danger::before { background: var(--danger); }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--card-shadow);
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
        }
        
        .activity-item {
            border-left: 3px solid var(--primary);
            position: relative;
            padding-left: 1.5rem;
            margin-bottom: 1.5rem;
            transition: var(--transition);
        }
        
        .activity-item:hover {
            transform: translateX(5px);
        }
        
        .activity-item::before {
            content: '';
            position: absolute;
            left: -8px;
            top: 5px;
            width: 14px;
            height: 14px;
            border-radius: 50%;
            background: var(--primary);
        }
        
        .badge-pill {
            border-radius: 50px;
            padding: 0.35em 0.65em;
            font-size: 0.75em;
            font-weight: 500;
        }
        
        .chart-container {
    position: relative;
    height: 300px; /* Increased from 250px */
}
        
        .nav-tabs .nav-link.active {
            border-bottom: 3px solid var(--primary);
            font-weight: 500;
        }
        
        .progress-thin {
            height: 6px;
        }
        
        .avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="dashboard-header">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="text-white mb-1">Dashboard Overview</h2>
                    <p class="text-white-50 mb-0">Welcome back, <?= htmlspecialchars($_SESSION['username']) ?></p>
                </div>
                <div class="d-flex">
                    <button class="btn btn-light btn-sm me-2">
                        <i class="fas fa-sync-alt me-1"></i> Refresh
                    </button>
                    <div class="dropdown">
                        <button class="btn btn-outline-light btn-sm dropdown-toggle" type="button" id="timeRangeDropdown" data-bs-toggle="dropdown">
                            <i class="fas fa-calendar-alt me-1"></i> This Week
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="#">Today</a></li>
                            <li><a class="dropdown-item" href="#">This Week</a></li>
                            <li><a class="dropdown-item" href="#">This Month</a></li>
                            <li><a class="dropdown-item" href="#">This Year</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    
        <!-- Stats Cards -->
        <div class="row mb-4 g-4">
            <div class="col-md-6 col-lg-3">
                <div class="stat-card primary glass-card p-4 h-100">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-uppercase text-muted mb-2">Total Users</h6>
                            <h3 class="mb-0"><?= $users_count ?></h3>
                            <small class="text-success"><i class="fas fa-caret-up me-1"></i> 12% from last month</small>
                        </div>
                        <div class="stat-icon" style="background-color: var(--primary);">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6 col-lg-3">
                <div class="stat-card success glass-card p-4 h-100">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-uppercase text-muted mb-2">Active Projects</h6>
                            <h3 class="mb-0"><?= $projects_count ?></h3>
                            <small class="text-success"><i class="fas fa-caret-up me-1"></i> 8% from last month</small>
                        </div>
                        <div class="stat-icon" style="background-color: var(--secondary);">
                            <i class="fas fa-project-diagram"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6 col-lg-3">
                <div class="stat-card warning glass-card p-4 h-100">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-uppercase text-muted mb-2">Pending Tasks</h6>
                            <h3 class="mb-0"><?= $active_tasks ?></h3>
                            <small class="text-danger"><i class="fas fa-caret-down me-1"></i> 3% from last week</small>
                        </div>
                        <div class="stat-icon" style="background-color: var(--warning);">
                            <i class="fas fa-tasks"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6 col-lg-3">
                <div class="stat-card danger glass-card p-4 h-100">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-uppercase text-muted mb-2">System Health</h6>
                            <h3 class="mb-0">100%</h3>
                            <small class="text-success"><i class="fas fa-check-circle me-1"></i> All systems operational</small>
                        </div>
                        <div class="stat-icon" style="background-color: var(--danger);">
                            <i class="fas fa-heartbeat"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <!-- Recent Activity -->
            <div class="col-lg-8">
                <div class="glass-card p-4 h-100">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h5 class="mb-0">Recent Activity</h5>
                        <div>
                            <button class="btn btn-sm btn-outline-primary me-2">
                                <i class="fas fa-list me-1"></i> View All
                            </button>
                            <button class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-download me-1"></i> Export
                            </button>
                        </div>
                    </div>
                    
                    <div class="activity-feed">
                        <?php foreach ($recent_activities as $activity): ?>
                        <div class="activity-item">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="mb-1">
    <span class="badge bg-<?= 
        strpos($activity['action'], 'create') !== false ? 'success' : 
        (strpos($activity['action'], 'delete') !== false ? 'danger' : 'primary') 
    ?> me-2">
        <?= htmlspecialchars($activity['action']) ?>
    </span>
    <?= htmlspecialchars($activity['username'] ?? 'System') ?>
</h6>

                                    <p class="mb-1 text-muted"><?= htmlspecialchars($activity['description']) ?></p>
                                    <small class="text-muted"><i class="far fa-clock me-1"></i> <?= date('M j, Y g:i a', strtotime($activity['created_at'])) ?></small>
                                </div>
                                <div class="avatar">
                                    <?= strtoupper(substr($activity['username'] ?? 'S', 0, 1)) ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php if (empty($recent_activities)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No recent activities found</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Right Sidebar -->
            <div class="col-lg-4">
                <!-- Stats Chart -->
                <div class="glass-card p-4 mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0">Project Distribution</h5>
                        <div class="dropdown">
                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="chartDropdown" data-bs-toggle="dropdown">
                                <i class="fas fa-ellipsis-v"></i>
                            </button>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="#">Refresh</a></li>
                                <li><a class="dropdown-item" href="#">Export</a></li>
                            </ul>
                        </div>
                    </div>
                    <div class="chart-container">
                        <canvas id="statsChart"></canvas>
                    </div>
                    <div class="mt-3 d-flex justify-content-around text-center">
                        <div>
                            <span class="d-block text-primary"><i class="fas fa-circle"></i> Users</span>
                            <span class="fw-bold"><?= $users_count ?></span>
                        </div>
                        <div>
                            <span class="d-block text-success"><i class="fas fa-circle"></i> Projects</span>
                            <span class="fw-bold"><?= $projects_count ?></span>
                        </div>
                        <div>
                            <span class="d-block text-warning"><i class="fas fa-circle"></i> Tasks</span>
                            <span class="fw-bold"><?= $active_tasks ?></span>
                        </div>
                    </div>
                </div>
                
                <!-- System Info -->
                <div class="glass-card p-4">
                    <h5 class="mb-3">System Information</h5>
                    <ul class="list-unstyled">
                        <li class="mb-3 d-flex">
                            <div class="me-3 text-primary">
                                <i class="fas fa-server"></i>
                            </div>
                            <div>
                                <small class="text-muted d-block">Server</small>
                                <span><?= $_SERVER['SERVER_SOFTWARE'] ?></span>
                            </div>
                        </li>
                        <li class="mb-3 d-flex">
                            <div class="me-3 text-success">
                                <i class="fas fa-database"></i>
                            </div>
                            <div>
                                <small class="text-muted d-block">PHP Version</small>
                                <span><?= phpversion() ?></span>
                            </div>
                        </li>
                        <li class="mb-3 d-flex">
                            <div class="me-3 text-info">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div>
                                <small class="text-muted d-block">Last Updated</small>
                                <span><?= date('Y-m-d H:i:s') ?></span>
                            </div>
                        </li>
                    </ul>
                    <div class="d-grid">
                        <button class="btn btn-primary">
                            <i class="fas fa-cog me-1"></i> System Settings
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap core JavaScript-->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <!-- Custom scripts -->
    <script>
   $(document).ready(function() {
    // Stats Chart
    var ctx = document.getElementById('statsChart').getContext('2d');
    var statsChart = new Chart(ctx, {
        type: 'bar', // Changed from 'doughnut' to 'bar'
        data: {
            labels: ['Users', 'Projects', 'Tasks'],
            datasets: [{
                label: 'Count',
                data: [<?= $users_count ?>, <?= $projects_count ?>, <?= $active_tasks ?>],
                backgroundColor: [
                    'rgba(99, 102, 241, 0.8)',
                    'rgba(16, 185, 129, 0.8)',
                    'rgba(245, 158, 11, 0.8)'
                ],
                borderColor: [
                    'rgba(99, 102, 241, 1)',
                    'rgba(16, 185, 129, 1)',
                    'rgba(245, 158, 11, 1)'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true, // Show legend for clarity
                    position: 'top' // Position the legend at the top
                }
            },
            scales: {
                y: {
                    beginAtZero: true, // Start y-axis at 0
                    title: {
                        display: true,
                        text: 'Count'
                    }
                },
                x: {
                    title: {
                        display: true,
                        text: 'Categories'
                    }
                }
            },
            animation: {
                animateScale: true,
                animateRotate: true
            }
        }
    });
    
    // Add animation to cards on scroll
    $(window).scroll(function() {
        $('.glass-card').each(function() {
            var cardPosition = $(this).offset().top;
            var scrollPosition = $(window).scrollTop() + $(window).height();
            
            if (scrollPosition > cardPosition) {
                $(this).addClass('animate__animated animate__fadeInUp');
            }
        });
    }).scroll();
});
    </script>

    <?php require_once __DIR__ . '/includes/footer.php'; ?>
</body>
</html>