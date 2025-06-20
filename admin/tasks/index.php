<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/header.php';

// Database connection
require_once __DIR__ . '/../includes/config.php';

// Check permissions
if (!in_array($_SESSION['user_role'], ['admin', 'manager', 'user'])) {
    header("Location: /unauthorized.php");
    exit();
}

// Get filter parameters
$filter_project = isset($_GET['project_id']) ? (int)$_GET['project_id'] : null;
$filter_status = isset($_GET['status']) ? $_GET['status'] : null;
$filter_priority = isset($_GET['priority']) ? $_GET['priority'] : null;
$filter_due_date = isset($_GET['due_date']) ? $_GET['due_date'] : null;
$filter_assigned_to = isset($_GET['assigned_to']) ? (int)$_GET['assigned_to'] : null;

// Build base query
$query = "
    SELECT t.*, 
           p.name as project_name, 
           p.id as project_id,
           u.username as created_by_name,
           GROUP_CONCAT(DISTINCT au.username ORDER BY au.username SEPARATOR ', ') as assigned_to_names
    FROM tasks t
    JOIN projects p ON t.project_id = p.id
    JOIN users u ON t.created_by = u.id
    LEFT JOIN task_assignments ta ON t.id = ta.task_id
    LEFT JOIN users au ON ta.user_id = au.id
    WHERE 1=1
";

$params = [];

// Apply filters
if ($filter_project) {
    $query .= " AND t.project_id = ?";
    $params[] = $filter_project;
}

if ($filter_status && in_array($filter_status, ['todo', 'in_progress', 'done'])) {
    $query .= " AND t.status = ?";
    $params[] = $filter_status;
}

if ($filter_priority && in_array($filter_priority, ['low', 'medium', 'high'])) {
    $query .= " AND t.priority = ?";
    $params[] = $filter_priority;
}

if ($filter_due_date) {
    switch ($filter_due_date) {
        case 'today':
            $query .= " AND DATE(t.due_date) = CURDATE()";
            break;
        case 'week':
            $query .= " AND t.due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)";
            break;
        case 'overdue':
            $query .= " AND t.due_date < CURDATE() AND t.status != 'done'";
            break;
    }
}

if ($filter_assigned_to) {
    $query .= " AND ta.user_id = ?";
    $params[] = $filter_assigned_to;
}

// Complete query
$query .= " GROUP BY t.id ORDER BY t.due_date ASC";

// Execute query
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get projects for filter dropdown
$projects = $pdo->query("SELECT id, name FROM projects ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Get users for filter dropdown
$users = $pdo->query("SELECT id, username FROM users ORDER BY username")->fetchAll(PDO::FETCH_ASSOC);

// Get statistics for dashboard cards
$stats_query = "SELECT 
    COUNT(*) as total_tasks,
    SUM(CASE WHEN status = 'todo' THEN 1 ELSE 0 END) as todo_tasks,
    SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_tasks,
    SUM(CASE WHEN status = 'done' THEN 1 ELSE 0 END) as done_tasks,
    SUM(CASE WHEN due_date < CURDATE() AND status != 'done' THEN 1 ELSE 0 END) as overdue_tasks,
    SUM(CASE WHEN priority = 'high' THEN 1 ELSE 0 END) as high_priority_tasks
FROM tasks";
$stats = $pdo->query($stats_query)->fetch(PDO::FETCH_ASSOC);

// Get tasks by status for chart
$status_data = $pdo->query("SELECT status, COUNT(*) as count FROM tasks GROUP BY status")->fetchAll(PDO::FETCH_ASSOC);

// Get tasks by priority for chart
$priority_data = $pdo->query("SELECT priority, COUNT(*) as count FROM tasks GROUP BY priority")->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- Main Content -->
<div class="container-fluid px-4">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center py-3 mb-4">
        <h1 class="h3 mb-0">
            <i class="fas fa-tasks me-2"></i>Task Dashboard
        </h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <div class="btn-group me-2">
                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#quickTaskModal">
                    <i class="fas fa-plus me-1"></i> Quick Task
                </button>
                <a href="create.php" class="btn btn-sm btn-outline-primary">
                    <i class="fas fa-plus-circle me-1"></i> Detailed Task
                </a>
            </div>
            <div class="btn-group me-2">
                <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#exportModal">
                    <i class="fas fa-download me-1"></i> Export
                </button>
                <button class="btn btn-sm btn-outline-secondary" id="refreshTasks">
                    <i class="fas fa-sync-alt me-1"></i> Refresh
                </button>
            </div>
            <div class="dropdown">
                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="dashboardActions" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-cog me-1"></i> Actions
                </button>
                <ul class="dropdown-menu" aria-labelledby="dashboardActions">
                    <li><a class="dropdown-item" href="#"><i class="fas fa-columns me-2"></i> Customize View</a></li>
                    <li><a class="dropdown-item" href="#"><i class="fas fa-sliders-h me-2"></i> Settings</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="#"><i class="fas fa-question-circle me-2"></i> Help</a></li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Dashboard Stats Cards -->
    <div class="row mb-4">
        <div class="col-xl-2 col-md-4 col-sm-6 mb-4">
            <div class="card border-start border-primary border-4 shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col me-2">
                            <div class="text-xs fw-bold text-primary text-uppercase mb-1">
                                Total Tasks</div>
                            <div class="h5 mb-0 fw-bold text-gray-800"><?= $stats['total_tasks'] ?></div>
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
                                To Do</div>
                            <div class="h5 mb-0 fw-bold text-gray-800"><?= $stats['todo_tasks'] ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-clipboard-list fa-2x text-gray-300"></i>
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
                                In Progress</div>
                            <div class="h5 mb-0 fw-bold text-gray-800"><?= $stats['in_progress_tasks'] ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-spinner fa-2x text-gray-300"></i>
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
                                Completed</div>
                            <div class="h5 mb-0 fw-bold text-gray-800"><?= $stats['done_tasks'] ?></div>
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
                                Overdue</div>
                            <div class="h5 mb-0 fw-bold text-gray-800"><?= $stats['overdue_tasks'] ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-exclamation-triangle fa-2x text-gray-300"></i>
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
                                High Priority</div>
                            <div class="h5 mb-0 fw-bold text-gray-800"><?= $stats['high_priority_tasks'] ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-flag fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="row mb-4">
        <div class="col-xl-6 col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">Tasks by Status</h6>
                    <div class="dropdown no-arrow">
                        <a class="dropdown-toggle" href="#" role="button" id="dropdownMenuLink" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-ellipsis-v fa-sm fa-fw text-gray-400"></i>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end shadow" aria-labelledby="dropdownMenuLink">
                            <li><a class="dropdown-item" href="#">View Details</a></li>
                            <li><a class="dropdown-item" href="#">Export Data</a></li>
                        </ul>
                    </div>
                </div>
                <div class="card-body">
                    <div class="chart-pie pt-4 pb-2">
                        <canvas id="statusChart" height="300"></canvas>
                    </div>
                    <div class="mt-4 text-center small">
                        <?php foreach ($status_data as $status): ?>
                            <span class="me-3">
                                <i class="fas fa-circle me-1" style="color: <?= 
                                    $status['status'] == 'done' ? '#1cc88a' : 
                                    ($status['status'] == 'in_progress' ? '#36b9cc' : '#f6c23e')
                                ?>"></i>
                                <?= ucfirst(str_replace('_', ' ', $status['status'])) ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-6 col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">Tasks by Priority</h6>
                    <div class="dropdown no-arrow">
                        <a class="dropdown-toggle" href="#" role="button" id="dropdownMenuLink2" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-ellipsis-v fa-sm fa-fw text-gray-400"></i>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end shadow" aria-labelledby="dropdownMenuLink2">
                            <li><a class="dropdown-item" href="#">View Details</a></li>
                            <li><a class="dropdown-item" href="#">Export Data</a></li>
                        </ul>
                    </div>
                </div>
                <div class="card-body">
                    <div class="chart-bar pt-4 pb-2">
                        <canvas id="priorityChart" height="300"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters Card -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-filter me-1"></i> Task Filters
            </h6>
            <div class="dropdown no-arrow">
                <a class="dropdown-toggle" href="#" role="button" id="filterDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-ellipsis-v fa-sm fa-fw text-gray-400"></i>
                </a>
                <ul class="dropdown-menu dropdown-menu-end shadow" aria-labelledby="filterDropdown">
                    <li><a class="dropdown-item" href="#" id="saveFilterPreset">Save current filter</a></li>
                    <li><a class="dropdown-item" href="#" id="loadFilterPreset">Load saved filter</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="index.php">Reset all filters</a></li>
                </ul>
            </div>
        </div>
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label for="project_id" class="form-label">Project</label>
                    <select class="form-select" id="project_id" name="project_id">
                        <option value="">All Projects</option>
                        <?php foreach ($projects as $project): ?>
                            <option value="<?= $project['id'] ?>" <?= $filter_project == $project['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($project['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">All Statuses</option>
                        <option value="todo" <?= $filter_status == 'todo' ? 'selected' : '' ?>>To Do</option>
                        <option value="in_progress" <?= $filter_status == 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                        <option value="done" <?= $filter_status == 'done' ? 'selected' : '' ?>>Done</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="priority" class="form-label">Priority</label>
                    <select class="form-select" id="priority" name="priority">
                        <option value="">All Priorities</option>
                        <option value="low" <?= $filter_priority == 'low' ? 'selected' : '' ?>>Low</option>
                        <option value="medium" <?= $filter_priority == 'medium' ? 'selected' : '' ?>>Medium</option>
                        <option value="high" <?= $filter_priority == 'high' ? 'selected' : '' ?>>High</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="due_date" class="form-label">Due Date</label>
                    <select class="form-select" id="due_date" name="due_date">
                        <option value="">All Dates</option>
                        <option value="today" <?= $filter_due_date == 'today' ? 'selected' : '' ?>>Due Today</option>
                        <option value="week" <?= $filter_due_date == 'week' ? 'selected' : '' ?>>Due This Week</option>
                        <option value="overdue" <?= $filter_due_date == 'overdue' ? 'selected' : '' ?>>Overdue</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="assigned_to" class="form-label">Assigned To</label>
                    <select class="form-select" id="assigned_to" name="assigned_to">
                        <option value="">All Users</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?= $user['id'] ?>" <?= $filter_assigned_to == $user['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($user['username']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter me-1"></i> Apply Filters
                    </button>
                    <a href="index.php" class="btn btn-outline-secondary">
                        <i class="fas fa-undo me-1"></i> Reset
                    </a>
                    <button type="button" class="btn btn-outline-info float-end" data-bs-toggle="collapse" data-bs-target="#advancedFilters">
                        <i class="fas fa-sliders-h me-1"></i> Advanced Filters
                    </button>
                </div>
                
                <!-- Advanced Filters (Collapsed by default) -->
                <div class="collapse mt-3" id="advancedFilters">
                    <div class="card card-body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label for="created_date" class="form-label">Created Date</label>
                                <select class="form-select" id="created_date" name="created_date">
                                    <option value="">Any time</option>
                                    <option value="today">Today</option>
                                    <option value="week">This week</option>
                                    <option value="month">This month</option>
                                    <option value="year">This year</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="updated_date" class="form-label">Last Updated</label>
                                <select class="form-select" id="updated_date" name="updated_date">
                                    <option value="">Any time</option>
                                    <option value="today">Today</option>
                                    <option value="week">This week</option>
                                    <option value="month">This month</option>
                                    <option value="year">This year</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="sort_by" class="form-label">Sort By</label>
                                <select class="form-select" id="sort_by" name="sort_by">
                                    <option value="due_date_asc">Due Date (Ascending)</option>
                                    <option value="due_date_desc">Due Date (Descending)</option>
                                    <option value="priority_asc">Priority (Low to High)</option>
                                    <option value="priority_desc">Priority (High to Low)</option>
                                    <option value="created_date_asc">Created Date (Oldest First)</option>
                                    <option value="created_date_desc">Created Date (Newest First)</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Tasks Table -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
            <h6 class="m-0 font-weight-bold text-primary">Task List</h6>
            <div class="dropdown no-arrow">
                <a class="dropdown-toggle" href="#" role="button" id="tableActions" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-ellipsis-v fa-sm fa-fw text-gray-400"></i>
                </a>
                <ul class="dropdown-menu dropdown-menu-end shadow" aria-labelledby="tableActions">
                    <li><a class="dropdown-item" href="#" id="bulkEdit">Bulk Edit</a></li>
                    <li><a class="dropdown-item" href="#" id="bulkAssign">Bulk Assign</a></li>
                    <li><a class="dropdown-item" href="#" id="bulkChangeStatus">Bulk Change Status</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="#" id="printTasks">Print Tasks</a></li>
                </ul>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table id="tasksTable" class="table table-striped table-hover" style="width:100%">
                    <thead>
                        <tr>
                            <th width="3%">
                                <input type="checkbox" id="selectAllTasks">
                            </th>
                            <th width="25%">Task</th>
                            <th width="15%">Project</th>
                            <th width="10%">Status</th>
                            <th width="10%">Priority</th>
                            <th width="12%">Due Date</th>
                            <th width="15%">Assigned To</th>
                            <th width="10%">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tasks as $task): ?>
                        <tr>
                            <td>
                                <input type="checkbox" class="task-checkbox" value="<?= $task['id'] ?>">
                            </td>
                            <td>
                                <a href="view.php?id=<?= $task['id'] ?>" class="text-dark fw-bold">
                                    <?= htmlspecialchars($task['title']) ?>
                                </a>
                                <?php if (strtotime($task['due_date']) < time() && $task['status'] != 'done'): ?>
                                    <span class="badge bg-danger ms-1">Overdue</span>
                                <?php endif; ?>
                                <div class="text-muted small mt-1">
                                    <?= substr(htmlspecialchars($task['description']), 0, 50) ?>...
                                </div>
                            </td>
                            <td>
                                <a href="../projects/view.php?id=<?= $task['project_id'] ?>" class="badge bg-info text-decoration-none">
                                    <?= htmlspecialchars($task['project_name']) ?>
                                </a>
                            </td>
                            <td>
                                <span class="badge bg-<?= 
                                    $task['status'] == 'done' ? 'success' : 
                                    ($task['status'] == 'in_progress' ? 'info' : 'warning')
                                ?>">
                                    <?= ucfirst(str_replace('_', ' ', $task['status'])) ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-<?= 
                                    $task['priority'] == 'high' ? 'danger' : 
                                    ($task['priority'] == 'medium' ? 'warning' : 'secondary')
                                ?>">
                                    <?= ucfirst($task['priority']) ?>
                                </span>
                            </td>
                            <td data-order="<?= strtotime($task['due_date']) ?>">
                                <?= date('M j, Y', strtotime($task['due_date'])) ?>
                                <div class="text-muted small">
                                    <?= $task['status'] == 'done' ? 'Completed' : 
                                        (strtotime($task['due_date']) < time() ? 'Overdue' : 
                                        (strtotime($task['due_date']) < strtotime('+1 week') ? 'Due soon' : '')) ?>
                                </div>
                            </td>
                            <td>
                                <?php if ($task['assigned_to_names']): ?>
                                    <?= htmlspecialchars($task['assigned_to_names']) ?>
                                <?php else: ?>
                                    <span class="text-muted">Unassigned</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm" role="group">
                                    <a href="view.php?id=<?= $task['id'] ?>" class="btn btn-info" title="View" data-bs-toggle="tooltip">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="edit.php?id=<?= $task['id'] ?>" class="btn btn-primary" title="Edit" data-bs-toggle="tooltip">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="delete.php?id=<?= $task['id'] ?>" class="btn btn-danger" title="Delete" data-bs-toggle="tooltip" onclick="return confirm('Are you sure you want to delete this task?')">
                                        <i class="fas fa-trash-alt"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Quick Task Modal -->
<div class="modal fade" id="quickTaskModal" tabindex="-1" aria-labelledby="quickTaskModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="quickTaskModalLabel">Create Quick Task</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="quick_create.php" method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="quickTaskTitle" class="form-label">Title</label>
                        <input type="text" class="form-control" id="quickTaskTitle" name="title" required>
                    </div>
                    <div class="mb-3">
                        <label for="quickTaskProject" class="form-label">Project</label>
                        <select class="form-select" id="quickTaskProject" name="project_id" required>
                            <option value="">Select Project</option>
                            <?php foreach ($projects as $project): ?>
                                <option value="<?= $project['id'] ?>"><?= htmlspecialchars($project['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="quickTaskPriority" class="form-label">Priority</label>
                            <select class="form-select" id="quickTaskPriority" name="priority">
                                <option value="medium">Medium</option>
                                <option value="high">High</option>
                                <option value="low">Low</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="quickTaskDueDate" class="form-label">Due Date</label>
                            <input type="date" class="form-control" id="quickTaskDueDate" name="due_date" value="<?= date('Y-m-d', strtotime('+3 days')) ?>">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="quickTaskAssignees" class="form-label">Assign To</label>
                        <select class="form-select" id="quickTaskAssignees" name="assignees[]" multiple>
                            <?php foreach ($users as $user): ?>
                                <option value="<?= $user['id'] ?>"><?= htmlspecialchars($user['username']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Task</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Export Modal -->
<div class="modal fade" id="exportModal" tabindex="-1" aria-labelledby="exportModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exportModalLabel">Export Tasks</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="exportForm" method="POST" action="export.php">
                    <div class="mb-3">
                        <label for="exportFormat" class="form-label">Format</label>
                        <select class="form-select" id="exportFormat" name="format" required>
                            <option value="csv">CSV</option>
                            <option value="excel">Excel</option>
                            <option value="pdf">PDF</option>
                            <option value="json">JSON</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Columns</label>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="exportTitle" name="columns[]" value="title" checked>
                                    <label class="form-check-label" for="exportTitle">Title</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="exportProject" name="columns[]" value="project_name" checked>
                                    <label class="form-check-label" for="exportProject">Project</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="exportStatus" name="columns[]" value="status" checked>
                                    <label class="form-check-label" for="exportStatus">Status</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="exportPriority" name="columns[]" value="priority" checked>
                                    <label class="form-check-label" for="exportPriority">Priority</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="exportDueDate" name="columns[]" value="due_date" checked>
                                    <label class="form-check-label" for="exportDueDate">Due Date</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="exportAssigned" name="columns[]" value="assigned_to_names" checked>
                                    <label class="form-check-label" for="exportAssigned">Assigned To</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <input type="hidden" name="project_id" value="<?= $filter_project ?>">
                    <input type="hidden" name="status" value="<?= $filter_status ?>">
                    <input type="hidden" name="priority" value="<?= $filter_priority ?>">
                    <input type="hidden" name="due_date" value="<?= $filter_due_date ?>">
                    <input type="hidden" name="assigned_to" value="<?= $filter_assigned_to ?>">
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="submit" form="exportForm" class="btn btn-primary">Export</button>
            </div>
        </div>
    </div>
</div>

<!-- Include necessary CSS and JS -->
<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.2.2/css/buttons.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/select/1.3.4/css/select.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css">

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.2.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/select/1.3.4/js/dataTables.select.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>


<script>
$(document).ready(function() {
    // Initialize Select2 for multiple assignees
    $('#quickTaskAssignees').select2({
        theme: 'bootstrap-5',
        placeholder: 'Select assignees',
        allowClear: true,
        width: '100%'
    });

    // Initialize DataTables
    const tasksTable = $('#tasksTable').DataTable({
        responsive: true,
        select: {
            style: 'multi',
            selector: 'td:first-child input[type="checkbox"]'
        },
        order: [[5, 'asc']], // Default sort by due date
        pageLength: 25,
        language: {
            search: "_INPUT_",
            searchPlaceholder: "Search tasks..."
        },
        columnDefs: [
            {
                targets: 0,
                orderable: false,
                searchable: false
            },
            {
                targets: 7,
                orderable: false,
                searchable: false
            }
        ],
        dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
             '<"row"<"col-sm-12"tr>>' +
             '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>'
    });

    // Select all checkbox functionality
    $('#selectAllTasks').on('click', function() {
        const rows = tasksTable.rows({ search: 'applied' }).nodes();
        $('input[type="checkbox"]', rows).prop('checked', this.checked);
        tasksTable.rows().select(this.checked);
    });

    // Individual checkbox handling
    $('#tasksTable tbody').on('click', 'input.task-checkbox', function() {
        $(this).closest('tr').toggleClass('selected', this.checked);
        if (!this.checked) {
            $('#selectAllTasks').prop('checked', false);
        }
    });

    // Status Chart
    const statusChart = new Chart(document.getElementById('statusChart'), {
        type: 'doughnut',
        data: {
            labels: [<?php echo "'" . implode("','", array_column($status_data, 'status')) . "'"; ?>],
            datasets: [{
                data: [<?php echo implode(',', array_column($status_data, 'count')); ?>],
                backgroundColor: [
                    '#f6c23e', // To Do
                    '#36b9cc', // In Progress
                    '#1cc88a'  // Done
                ],
                hoverOffset: 4
            }]
        },
        options: {
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            }
        }
    });

    // Priority Chart
    const priorityChart = new Chart(document.getElementById('priorityChart'), {
        type: 'bar',
        data: {
            labels: [<?php echo "'" . implode("','", array_column($priority_data, 'priority')) . "'"; ?>],
            datasets: [{
                label: 'Tasks by Priority',
                data: [<?php echo implode(',', array_column($priority_data, 'count')); ?>],
                backgroundColor: [
                    '#858796', // Low
                    '#f6c23e', // Medium
                    '#e74a3b'  // High
                ],
                borderColor: [
                    '#858796',
                    '#f6c23e',
                    '#e74a3b'
                ],
                borderWidth: 1
            }]
        },
        options: {
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            },
            plugins: {
                legend: {
                    display: false
                }
            }
        }
    });

    // Refresh button functionality
    $('#refreshTasks').on('click', function() {
        window.location.reload();
    });

    // Bulk edit functionality
    $('#bulkEdit').on('click', function(e) {
        e.preventDefault();
        const selectedIds = tasksTable.rows('.selected').data().toArray()
            .map(row => $(row[0]).find('.task-checkbox').val());
        if (selectedIds.length === 0) {
            alert('Please select at least one task');
            return;
        }
        // Implement bulk edit logic here (e.g., redirect to bulk edit page)
        window.location.href = 'bulk_edit.php?ids=' + selectedIds.join(',');
    });

    // Bulk assign functionality
    $('#bulkAssign').on('click', function(e) {
        e.preventDefault();
        const selectedIds = tasksTable.rows('.selected').data().toArray()
            .map(row => $(row[0]).find('.task-checkbox').val());
        if (selectedIds.length === 0) {
            alert('Please select at least one task');
            return;
        }
        // Implement bulk assign logic here (e.g., show modal)
        // For example, open a modal with assignee selection
    });

    // Bulk status change functionality
    $('#bulkChangeStatus').on('click', function(e) {
        e.preventDefault();
        const selectedIds = tasksTable.rows('.selected').data().toArray()
            .map(row => $(row[0]).find('.task-checkbox').val());
        if (selectedIds.length === 0) {
            alert('Please select at least one task');
            return;
        }
        // Implement bulk status change logic here (e.g., show modal)
        // For example, open a modal with status selection
    });

    // Print tasks functionality
    $('#printTasks').on('click', function(e) {
        e.preventDefault();
        window.print();
    });

    // Initialize tooltips
    $('[data-bs-toggle="tooltip"]').tooltip();

    // Save filter preset (example implementation)
    $('#saveFilterPreset').on('click', function(e) {
        e.preventDefault();
        const filters = {
            project_id: $('#project_id').val(),
            status: $('#status').val(),
            priority: $('#priority').val(),
            due_date: $('#due_date').val(),
            assigned_to: $('#assigned_to').val(),
            created_date: $('#created_date').val(),
            updated_date: $('#updated_date').val(),
            sort_by: $('#sort_by').val()
        };
        // Implement save filter logic (e.g., via AJAX to save to user preferences)
        console.log('Saving filters:', filters);
    });

    // Load filter preset (example implementation)
    $('#loadFilterPreset').on('click', function(e) {
        e.preventDefault();
        // Implement load filter logic (e.g., via AJAX to fetch saved filters)
        console.log('Loading filter preset');
    });
});
</script>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>