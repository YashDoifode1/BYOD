<?php
// Start session at the very top
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

$userId = $_SESSION['user']['id'];
$projectId = $_GET['id'] ?? 0;

// Verify user has permission to view this project
if (!hasProjectAccess($userId, $projectId)) {
    header('HTTP/1.0 403 Forbidden');
    die('You do not have permission to view this project');
}

// Fetch project details
$projectStmt = $pdo->prepare("
    SELECT p.*, 
           CONCAT(u.first_name, ' ', u.last_name) as owner_name,
           u.email as owner_email
    FROM projects p
    JOIN users u ON p.created_by = u.id
    WHERE p.id = ?
");
$projectStmt->execute([$projectId]);
$project2 = $projectStmt->fetch();

// Add proper validation before using the project data
if (!$project2) {
    header('HTTP/1.0 404 Not Found');
    die('Project not found or you don\'t have permission to view it');
}


// Get project statistics
$statsStmt = $pdo->prepare("
    SELECT 
        COUNT(t.id) as total_tasks,
        SUM(CASE WHEN t.status = 'todo' THEN 1 ELSE 0 END) as todo_tasks,
        SUM(CASE WHEN t.status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_tasks,
        SUM(CASE WHEN t.status = 'done' THEN 1 ELSE 0 END) as completed_tasks,
        COUNT(DISTINCT pm.user_id) as team_members
    FROM projects p
    LEFT JOIN tasks t ON p.id = t.project_id
    LEFT JOIN project_members pm ON p.id = pm.project_id
    WHERE p.id = ?
");
$statsStmt->execute([$projectId]);
$stats = $statsStmt->fetch();

// Get recent activity
$activityStmt = $pdo->prepare("
    SELECT a.*, CONCAT(u.first_name, ' ', u.last_name) as user_name
    FROM activity_logs a
    LEFT JOIN users u ON a.user_id = u.id
    WHERE a.project_id = ?
    ORDER BY a.created_at DESC
    LIMIT 5
");
$activityStmt->execute([$projectId]);
$activities = $activityStmt->fetchAll();

// Get project members
$membersStmt = $pdo->prepare("
    SELECT u.id, u.username, u.email, 
           CONCAT(u.first_name, ' ', u.last_name) as full_name,
           pm.role, pm.joined_at
    FROM project_members pm
    JOIN users u ON pm.user_id = u.id
    WHERE pm.project_id = ?
    ORDER BY 
        CASE pm.role WHEN 'owner' THEN 1 ELSE 2 END,
        pm.joined_at
");
$membersStmt->execute([$projectId]);
$members = $membersStmt->fetchAll();
?>

<body class="dashboard">
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar Navigation -->
            <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><?= htmlspecialchars($project2['name']) ?></h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <?php if (hasProjectAccess($userId, $projectId, 'owner') || $_SESSION['user']['role'] === 'admin'): ?>
                            <a href="edit.php?id=<?= $projectId ?>" class="btn btn-outline-primary me-2">
                                <i class="fas fa-edit me-1"></i> Edit Project
                            </a>
                        <?php endif; ?>
                        <a href="../tasks/tasks.php?project_id=<?= $projectId ?>" class="btn btn-primary">
                            <i class="fas fa-plus me-1"></i> New Task
                        </a>
                    </div>
                </div>

                <!-- Project Overview -->
                <div class="row mb-4">
                    <div class="col-md-8">
                        <div class="card mb-4">
                            <div class="card-header">
                                <i class="fas fa-info-circle me-1"></i> Project Details
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <h5>Description</h5>
                                    <p><?= nl2br(htmlspecialchars($project2['description'] ?? 'No description provided')) ?></p>
                                </div>
                                
                                <div class="row">
    <div class="col-md-6">
        <h5>Project Information</h5>
        <ul class="list-unstyled">
            <li><strong>Owner:</strong> <?= isset($project2['owner_name']) ? htmlspecialchars($project2['owner_name']) : 'Unknown' ?></li>
            <li><strong>Created:</strong> <?= isset($project2['created_at']) ? date('M j, Y', strtotime($project2['created_at'])) : 'Unknown' ?></li>
            <?php if (!empty($project2['start_date'])): ?>
                <li><strong>Start Date:</strong> <?= date('M j, Y', strtotime($project2['start_date'])) ?></li>
            <?php endif; ?>
            <?php if (!empty($project2['end_date'])): ?>
                <li><strong>End Date:</strong> <?= date('M j, Y', strtotime($project2['end_date'])) ?></li>
            <?php endif; ?>
        </ul>
    </div>
    <div class="col-md-6">
        <h5>Quick Stats</h5>
        <ul class="list-unstyled">
            <li><strong>Total Tasks:</strong> <?= $stats['total_tasks'] ?? 0 ?></li>
            <li><strong>Completed:</strong> <?= $stats['completed_tasks'] ?? 0 ?></li>
            <li><strong>In Progress:</strong> <?= $stats['in_progress_tasks'] ?? 0 ?></li>
            <li><strong>Team Members:</strong> <?= $stats['team_members'] ?? 0 ?></li>
        </ul>
    </div>
</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card mb-4">
                            <div class="card-header">
                                <i class="fas fa-tasks me-1"></i> Task Progress
                            </div>
                            <div class="card-body">
                                <?php if ($stats['total_tasks'] > 0): ?>
                                    <?php 
                                    $progress = round(($stats['completed_tasks'] / $stats['total_tasks']) * 100);
                                    $remaining = 100 - $progress;
                                    ?>
                                    <div class="text-center mb-3">
                                        <div class="progress-circle" data-value="<?= $progress ?>">
                                            <svg viewBox="0 0 36 36" class="circular-chart">
                                                <path class="circle-bg"
                                                    d="M18 2.0845
                                                    a 15.9155 15.9155 0 0 1 0 31.831
                                                    a 15.9155 15.9155 0 0 1 0 -31.831"
                                                />
                                                <path class="circle"
                                                    stroke-dasharray="<?= $progress ?>, 100"
                                                    d="M18 2.0845
                                                    a 15.9155 15.9155 0 0 1 0 31.831
                                                    a 15.9155 15.9155 0 0 1 0 -31.831"
                                                />
                                                <text x="18" y="20.35" class="percentage"><?= $progress ?>%</text>
                                            </svg>
                                        </div>
                                        <h5>Project Completion</h5>
                                    </div>
                                    <div class="progress-legend">
                                        <div><span class="legend-color completed"></span> Completed: <?= $stats['completed_tasks'] ?></div>
                                        <div><span class="legend-color in-progress"></span> In Progress: <?= $stats['in_progress_tasks'] ?></div>
                                        <div><span class="legend-color todo"></span> To Do: <?= $stats['todo_tasks'] ?></div>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-3">
                                        <i class="fas fa-tasks fa-3x text-muted mb-3"></i>
                                        <p>No tasks yet</p>
                                        <a href="../tasks/create.php?project_id=<?= $projectId ?>" class="btn btn-sm btn-primary">
                                            Create First Task
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Team Members -->
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-users me-1"></i> Team Members
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Role</th>
                                        <th>Joined</th>
                                        <?php if (hasProjectAccess($userId, $projectId, 'owner')): ?>
                                            <th>Actions</th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($members as $member): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="avatar me-2">
                                                        <?= strtoupper(substr($member['full_name'], 0, 1)) ?>
                                                    </div>
                                                    <?= htmlspecialchars($member['full_name']) ?>
                                                </div>
                                            </td>
                                            <td><?= htmlspecialchars($member['email']) ?></td>
                                            <td>
                                                <span class="badge <?= $member['role'] === 'owner' ? 'bg-primary' : 'bg-secondary' ?>">
                                                    <?= ucfirst($member['role']) ?>
                                                </span>
                                            </td>
                                            <td><?= date('M j, Y', strtotime($member['joined_at'])) ?></td>
                                            <?php if (hasProjectAccess($userId, $projectId, 'owner')): ?>
                                                <td>
                                                    <?php if ($member['role'] !== 'owner'): ?>
                                                        <button class="btn btn-sm btn-outline-danger remove-member" 
                                                                data-user-id="<?= $member['id'] ?>">
                                                            <i class="fas fa-user-minus"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </td>
                                            <?php endif; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php if (hasProjectAccess($userId, $projectId, 'owner')): ?>
                            <div class="text-end mt-3">
                                <a href="members/add.php?project_id=<?= $projectId ?>" class="btn btn-primary">
                                    <i class="fas fa-user-plus me-1"></i> Add Member
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-history me-1"></i> Recent Activity
                    </div>
                    <div class="card-body">
                        <?php if (!empty($activities)): ?>
                            <div class="activity-feed">
                                <?php foreach ($activities as $activity): ?>
                                    <div class="activity-item">
                                        <div class="activity-icon">
                                            <?php 
                                            $icon = 'fa-info-circle';
                                            if (strpos($activity['action'], 'task') !== false) $icon = 'fa-tasks';
                                            if (strpos($activity['action'], 'member') !== false) $icon = 'fa-user';
                                            ?>
                                            <i class="fas <?= $icon ?>"></i>
                                        </div>
                                        <div class="activity-content">
                                            <div class="activity-header">
                                                <strong><?= htmlspecialchars($activity['user_name']) ?></strong>
                                                <span class="text-muted float-end">
                                                    <?= date('M j, g:i a', strtotime($activity['created_at'])) ?>
                                                </span>
                                            </div>
                                            <p><?= htmlspecialchars($activity['description']) ?></p>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="text-end mt-3">
                                <a href="activity.php?id=<?= $projectId ?>" class="btn btn-outline-primary">
                                    View All Activity
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-3">
                                <i class="fas fa-history fa-3x text-muted mb-3"></i>
                                <p>No recent activity</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <?php require_once __DIR__ . '/../includes/footer.php'; ?>

    <style>
    /* Progress circle styles */
    .progress-circle {
        width: 120px;
        height: 120px;
        margin: 0 auto 15px;
    }
    .circular-chart {
        display: block;
        margin: 10px auto;
        max-width: 80%;
        max-height: 120px;
    }
    .circle-bg {
        fill: none;
        stroke: #eee;
        stroke-width: 3;
    }
    .circle {
        fill: none;
        stroke-width: 3;
        stroke-linecap: round;
        stroke: #4CAF50;
        animation: progress 1s ease-out forwards;
    }
    @keyframes progress {
        0% { stroke-dasharray: 0, 100; }
    }
    .percentage {
        fill: #666;
        font-size: 0.5em;
        text-anchor: middle;
    }
    .progress-legend {
        margin-top: 15px;
    }
    .legend-color {
        display: inline-block;
        width: 12px;
        height: 12px;
        border-radius: 2px;
        margin-right: 5px;
    }
    .legend-color.completed { background-color: #4CAF50; }
    .legend-color.in-progress { background-color: #2196F3; }
    .legend-color.todo { background-color: #9E9E9E; }
    
    /* Activity feed styles */
    .activity-item {
        display: flex;
        padding: 10px 0;
        border-bottom: 1px solid #eee;
    }
    .activity-item:last-child {
        border-bottom: none;
    }
    .activity-icon {
        width: 40px;
        height: 40px;
        background-color: #f8f9fa;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 15px;
        color: #6c757d;
    }
    .activity-content {
        flex: 1;
    }
    .activity-header {
        margin-bottom: 5px;
    }
    
    /* Avatar styles */
    .avatar {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        background-color: #4e73df;
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
    }
    </style>

    <script>
    $(document).ready(function() {
        // Handle member removal
        $('.remove-member').click(function() {
            const userId = $(this).data('user-id');
            const projectId = <?= $projectId ?>;
            
            if (confirm('Are you sure you want to remove this member from the project?')) {
                $.post('members/remove.php', {
                    project_id: projectId,
                    user_id: userId
                }, function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + response.message);
                    }
                }).fail(function() {
                    alert('Error removing member');
                });
            }
        });
    });
    </script>
</body>
</html>