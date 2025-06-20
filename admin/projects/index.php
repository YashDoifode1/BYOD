<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/header.php';

// Database connection
require_once __DIR__ . '/../../includes/config.php';

// Check permissions
if (!in_array($_SESSION['user_role'], ['admin', 'manager'])) {
    header("Location: /unauthorized.php");
    exit();
}

// Get dashboard statistics
$stats = [
    'total_projects' => $pdo->query("SELECT COUNT(*) FROM projects")->fetchColumn(),
    'active_projects' => $pdo->query("SELECT COUNT(*) FROM projects WHERE end_date > NOW()")->fetchColumn(),
    'total_tasks' => $pdo->query("SELECT COUNT(*) FROM tasks")->fetchColumn(),
    'completed_tasks' => $pdo->query("SELECT COUNT(*) FROM tasks WHERE status = 'done'")->fetchColumn(),
    'overdue_tasks' => $pdo->query("SELECT COUNT(*) FROM tasks WHERE due_date < NOW() AND status != 'done'")->fetchColumn(),
    'team_members' => $pdo->query("SELECT COUNT(DISTINCT user_id) FROM project_members")->fetchColumn(),
];

// Get recent activity
$activity = $pdo->query("
    SELECT al.*, u.username, p.name as project_name 
    FROM activity_logs al
    LEFT JOIN users u ON al.user_id = u.id
    LEFT JOIN projects p ON al.project_id = p.id
    ORDER BY al.created_at DESC 
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

// Get projects summary
$projects = $pdo->query("
    SELECT p.*, 
           COUNT(t.id) as task_count,
           COUNT(CASE WHEN t.status = 'done' THEN 1 END) as completed_tasks,
           u.username as created_by_name
    FROM projects p
    LEFT JOIN tasks t ON p.id = t.project_id
    LEFT JOIN users u ON p.created_by = u.id
    GROUP BY p.id
    ORDER BY p.end_date ASC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

// Get tasks summary
$tasks = $pdo->query("
    SELECT t.*, p.name as project_name, u.username as created_by_name
    FROM tasks t
    JOIN projects p ON t.project_id = p.id
    JOIN users u ON t.created_by = u.id
    WHERE t.due_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)
    AND t.status != 'done'
    ORDER BY t.due_date ASC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- Main Content -->
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-tachometer-alt me-2"></i>Project Dashboard
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <button class="btn btn-sm btn-outline-secondary" id="refreshDashboard">
                <i class="fas fa-sync-alt me-1"></i> Refresh
            </button>
            <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#exportModal">
                <i class="fas fa-download me-1"></i> Export
            </button>
        </div>
        <div class="dropdown">
            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="timeRangeDropdown" data-bs-toggle="dropdown">
                <i class="fas fa-calendar-alt me-1"></i> Time Range
            </button>
            <ul class="dropdown-menu" aria-labelledby="timeRangeDropdown">
                <li><a class="dropdown-item" href="#" data-range="today">Today</a></li>
                <li><a class="dropdown-item" href="#" data-range="week">This Week</a></li>
                <li><a class="dropdown-item" href="#" data-range="month">This Month</a></li>
            </ul>
        </div>
    </div>
</div>

<!-- Stats Cards -->
<div class="row mb-4">
    <div class="col-xl-2 col-md-4 mb-4">
        <div class="card border-left-primary shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                            Total Projects</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $stats['total_projects'] ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-project-diagram fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-2 col-md-4 mb-4">
        <div class="card border-left-success shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                            Active Projects</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $stats['active_projects'] ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-rocket fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-2 col-md-4 mb-4">
        <div class="card border-left-info shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                            Total Tasks</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $stats['total_tasks'] ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-tasks fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-2 col-md-4 mb-4">
        <div class="card border-left-warning shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                            Completed Tasks</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $stats['completed_tasks'] ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-2 col-md-4 mb-4">
        <div class="card border-left-danger shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                            Overdue Tasks</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $stats['overdue_tasks'] ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-exclamation-triangle fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-2 col-md-4 mb-4">
        <div class="card border-left-secondary shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-secondary text-uppercase mb-1">
                            Team Members</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $stats['team_members'] ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-users fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Projects Overview -->
<div class="row mb-4">
    <div class="col-lg-8">
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-project-diagram me-1"></i> Projects Overview
                </h6>
                <a href="projects/" class="btn btn-sm btn-primary">View All</a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Project</th>
                                <th>Created By</th>
                                <th>Start Date</th>
                                <th>End Date</th>
                                <th>Progress</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($projects as $project): ?>
                            <tr>
                                <td>
                                    <a href="projects/view.php?id=<?= $project['id'] ?>">
                                        <?= htmlspecialchars($project['name']) ?>
                                    </a>
                                </td>
                                <td><?= htmlspecialchars($project['created_by_name']) ?></td>
                                <td><?= date('M d, Y', $project['start_date']) ?></td>
                                <td><?= date('M d, Y', $project['end_date']) ?></td>
                                <td>
                                    <div class="progress">
                                        <?php $progress = $project['task_count'] > 0 ? round(($project['completed_tasks'] / $project['task_count']) * 100) : 0; ?>
                                        <div class="progress-bar <?= $progress >= 80 ? 'bg-success' : ($progress >= 50 ? 'bg-info' : 'bg-warning') ?>" 
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
                                    <a href="view.php?id=<?= $project['id'] ?>" class="btn btn-sm btn-info">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-tasks me-1"></i> Upcoming Tasks
                </h6>
                <a href="tasks/" class="btn btn-sm btn-primary">View All</a>
            </div>
            <div class="card-body">
                <div class="list-group">
                    <?php foreach ($tasks as $task): ?>
                    <a href="tasks/view.php?id=<?= $task['id'] ?>" class="list-group-item list-group-item-action">
                        <div class="d-flex w-100 justify-content-between">
                            <h6 class="mb-1"><?= htmlspecialchars($task['title']) ?></h6>
                            <small class="text-<?= 
                                $task['status'] == 'done' ? 'success' : 
                                ($task['status'] == 'in_progress' ? 'info' : 'warning')
                            ?>">
                                <?= ucfirst(str_replace('_', ' ', $task['status'])) ?>
                            </small>
                        </div>
                        <p class="mb-1"><?= htmlspecialchars($task['project_name']) ?></p>
                        <small class="text-muted">
                            Due: <?= date('M d, Y', strtotime($task['due_date'])) ?>
                        </small>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Recent Activity -->
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">
            <i class="fas fa-history me-1"></i> Recent Activity
        </h6>
    </div>
    <div class="card-body">
        <div class="timeline">
            <?php foreach ($activity as $log): ?>
            <div class="timeline-item">
                <div class="timeline-icon">
                    <i class="fas fa-<?= 
                        strpos($log['action'], 'create') !== false ? 'plus-circle text-success' : 
                        (strpos($log['action'], 'update') !== false ? 'edit text-info' : 'trash-alt text-danger')
                    ?>"></i>
                </div>
                <div class="timeline-content">
                    <div class="d-flex justify-content-between">
                        <span class="font-weight-bold">
                            <?= $log['username'] ? htmlspecialchars($log['username']) : 'System' ?>
                        </span>
                        <small class="text-muted">
                            <?= date('M j, Y g:i a', strtotime($log['created_at'])) ?>
                        </small>
                    </div>
                    <p class="mb-1">
                        <?= ucfirst(str_replace('_', ' ', $log['action'])) ?>
                        <?php if ($log['project_name']): ?>
                            in <strong><?= htmlspecialchars($log['project_name']) ?></strong>
                        <?php endif; ?>
                    </p>
                    <small class="text-muted">
                        <?= htmlspecialchars($log['description']) ?>
                    </small>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Export Modal -->
<div class="modal fade" id="exportModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Export Dashboard Data</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="exportForm">
                    <div class="mb-3">
                        <label class="form-label">Export Format</label>
                        <select class="form-select" name="format">
                            <option value="csv">CSV</option>
                            <option value="excel">Excel</option>
                            <option value="pdf">PDF</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Data to Export</label>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="data[]" value="projects" checked>
                            <label class="form-check-label">Projects</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="data[]" value="tasks" checked>
                            <label class="form-check-label">Tasks</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="data[]" value="activity" checked>
                            <label class="form-check-label">Activity</label>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="exportData">Export</button>
            </div>
        </div>
    </div>
</div>

<!-- CSS for Timeline -->
<style>
.timeline {
    position: relative;
    padding-left: 1rem;
    margin: 0 0 0 30px;
    color: #5a5c69;
}
.timeline:before {
    content: '';
    position: absolute;
    left: 15px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #dddfeb;
}
.timeline-item {
    position: relative;
    padding-bottom: 1.5rem;
}
.timeline-icon {
    position: absolute;
    left: -30px;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    background: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
}
.timeline-content {
    padding: 0.5rem 1rem;
    background-color: #f8f9fc;
    border-radius: 0.35rem;
    margin-bottom: 1rem;
}
</style>

<!-- JavaScript -->
<script>
$(document).ready(function() {
    // Refresh dashboard
    $('#refreshDashboard').click(function() {
        location.reload();
    });
    
    // Time range filter
    $('[data-range]').click(function(e) {
        e.preventDefault();
        var range = $(this).data('range');
        // Implement time range filtering logic
        console.log('Filter by:', range);
    });
    
    // Export data
    $('#exportData').click(function() {
        var formData = $('#exportForm').serialize();
        // Implement export logic
        console.log('Exporting:', formData);
        $('#exportModal').modal('hide');
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>