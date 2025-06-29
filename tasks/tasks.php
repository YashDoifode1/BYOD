<?php
session_start();

require_once '../includes/config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// Get user role for permissions
$user_role = $_SESSION['role'] ?? 'user';

// Function to render a task card
function renderTaskCard($task) {
    $priorityClass = 'priority-' . $task['priority'];
    $priorityBadgeClass = $task['priority'] === 'high' ? 'bg-danger' : 
                         ($task['priority'] === 'medium' ? 'bg-warning text-dark' : 'bg-success');
    
    $dueDate = $task['due_date'] ? date('M j, Y', strtotime($task['due_date'])) : 'No due date';
    $assigneeName = $task['first_name'] ? $task['first_name'] . ' ' . $task['last_name'] : 'Unassigned';
    
    // Calculate days remaining
    $daysRemaining = '';
    if ($task['due_date']) {
        $dueDateTime = new DateTime($task['due_date']);
        $today = new DateTime();
        $interval = $today->diff($dueDateTime);
        $days = $interval->days;
        $daysRemaining = $interval->invert ? "Overdue by $days days" : "$days days remaining";
    }
    
    // Status options
    $statusOptions = [
        'todo' => 'To Do',
        'in_progress' => 'In Progress',
        'done' => 'Done'
    ];
    
    return '
    <div class="task-card card mb-3 ' . $priorityClass . '" data-task-id="' . $task['id'] . '" draggable="true">
        <div class="card-body p-3">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <h6 class="mb-0 fw-semibold">' . htmlspecialchars($task['title']) . '</h6>
                <div>
                    <span class="badge ' . $priorityBadgeClass . ' badge-priority me-1">' . ucfirst($task['priority']) . '</span>
                    <div class="dropdown d-inline-block">
                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" 
                                data-bs-toggle="dropdown" aria-expanded="false">
                            ' . $statusOptions[$task['status']] . '
                        </button>
                        <ul class="dropdown-menu status-dropdown">
                            <li><a class="dropdown-item status-option" href="#" data-status="todo">To Do</a></li>
                            <li><a class="dropdown-item status-option" href="#" data-status="in_progress">In Progress</a></li>
                            <li><a class="dropdown-item status-option" href="#" data-status="done">Done</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="#" data-bs-toggle="modal" 
                                  data-bs-target="#taskDetailsModal" data-task-id="' . $task['id'] . '">View Details</a></li>
                        </ul>
                    </div>
                </div>
            </div>
            <p class="small text-muted mb-2">' . htmlspecialchars(substr($task['description'], 0, 100)) . '</p>
            <div class="d-flex justify-content-between align-items-center mb-2">
                <div class="d-flex align-items-center">
                    <img src="../avatars/' . $task['created_by'] . '.jpg" alt="' . $assigneeName . '" 
                         class="assignee-avatar me-2" onerror="this.src=\'profile.jpeg\'">
                    <span class="small">' . $assigneeName . '</span>
                </div>
                <span class="due-date small text-muted"><i class="far fa-calendar-alt me-1"></i>' . $dueDate . '</span>
            </div>
            ' . ($daysRemaining ? '<div class="small mb-2"><span class="badge bg-light text-dark"><i class="far fa-clock me-1"></i>' . $daysRemaining . '</span></div>' : '') . '
            <div class="quick-status-buttons d-flex justify-content-between">
                <button class="btn btn-sm btn-outline-primary quick-status" 
                        data-status="todo" title="Mark as To Do">
                    <i class="fas fa-circle"></i>
                </button>
                <button class="btn btn-sm btn-outline-warning quick-status" 
                        data-status="in_progress" title="Mark as In Progress">
                    <i class="fas fa-spinner"></i>
                </button>
                <button class="btn btn-sm btn-outline-success quick-status" 
                        data-status="done" title="Mark as Done">
                    <i class="fas fa-check"></i>
                </button>
            </div>
        </div>
    </div>';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Task Board | Kuban</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    
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
        
        body {
            font-family: 'Nunito', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background-color: #f8f9fc;
        }
        
        .main-content {
            padding: 20px;
            transition: margin-left 0.3s;
            margin-left: 30px;
            width: calc(100% - 250px);
        }
        
        @media (max-width: 991.98px) {
            .main-content {
                margin-left: 0;
                width: 100%;
            }
        }
        
        .kanban-board {
            display: flex;
            gap: 20px;
            padding: 20px;
            overflow-x: auto;
            min-height: calc(100vh - 180px);
        }
        
        .kanban-column {
            flex: 1;
            min-width: 320px;
            background: white;
            border-radius: 0.35rem;
            padding: 15px;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1);
        }
        
        .kanban-column-header {
            font-weight: 600;
            margin-bottom: 15px;
            padding: 10px 15px;
            border-radius: 0.25rem;
            text-align: center;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .todo-header {
            background-color: #f8f9fa;
            color: var(--secondary-color);
            border-left: 4px solid var(--secondary-color);
        }
        
        .in-progress-header {
            background-color: rgba(246, 194, 62, 0.1);
            color: var(--warning-color);
            border-left: 4px solid var(--warning-color);
        }
        
        .done-header {
            background-color: rgba(28, 200, 138, 0.1);
            color: var(--success-color);
            border-left: 4px solid var(--success-color);
        }
        
        .task-card {
            border: none;
            border-radius: 0.35rem;
            margin-bottom: 15px;
            transition: all 0.3s ease;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }
        
        .task-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.1);
        }
        
        .priority-high {
            border-left: 4px solid var(--danger-color);
        }
        
        .priority-medium {
            border-left: 4px solid var(--warning-color);
        }
        
        .priority-low {
            border-left: 4px solid var(--success-color);
        }
        
        .assignee-avatar {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #fff;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }
        
        .due-date {
            font-size: 0.8rem;
            color: var(--secondary-color);
        }
        
        .badge-priority {
            font-size: 0.7rem;
            padding: 0.25em 0.4em;
            font-weight: 600;
            letter-spacing: 0.5px;
        }
        
        .dragging {
            opacity: 0.5;
            transform: rotate(2deg);
            background: #f8f9fa;
        }
        
        .kanban-column.dropzone {
            background-color: rgba(78, 115, 223, 0.05);
            transition: background-color 0.2s;
        }
        
        .quick-status-buttons .btn {
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
            border-radius: 0.25rem;
        }
        
        .alert-notification {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1100;
            min-width: 300px;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
        
        .card {
            border: none;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }
        
        .modal-header {
            background-color: var(--primary-color);
            color: white;
        }
        
        .modal-title {
            font-weight: 600;
        }
        
        .form-control, .form-select {
            border-radius: 0.35rem;
            padding: 0.5rem 0.75rem;
        }
        
        .btn {
            border-radius: 0.35rem;
            font-weight: 600;
            padding: 0.5rem 1rem;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-primary:hover {
            background-color: #2e59d9;
            border-color: #2e59d9;
        }
        
        .page-header {
            border-bottom: 1px solid #e3e6f0;
            padding-bottom: 1rem;
            margin-bottom: 2rem;
        }
        
        .dropdown-menu {
            border-radius: 0.35rem;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.1);
            border: none;
        }
        
        .dropdown-item {
            padding: 0.5rem 1rem;
            font-size: 0.85rem;
        }
        
        .dropdown-item:hover {
            background-color: #f8f9fc;
            color: var(--primary-color);
        }
        
        .status-badge {
            font-size: 0.75rem;
            padding: 0.35em 0.65em;
            font-weight: 600;
            border-radius: 0.25rem;
        }
        
        .badge-todo {
            background-color: rgba(108, 117, 125, 0.1);
            color: var(--secondary-color);
        }
        
        .badge-in-progress {
            background-color: rgba(246, 194, 62, 0.1);
            color: var(--warning-color);
        }
        
        .badge-done {
            background-color: rgba(28, 200, 138, 0.1);
            color: var(--success-color);
        }
        
        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }
        
        ::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 10px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: #a8a8a8;
        }
        
        /* Loading spinner */
        .spinner-container {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100px;
        }
        
        .spinner {
            width: 3rem;
            height: 3rem;
        }
        
        /* Progress bars */
        .progress {
            height: 0.5rem;
            border-radius: 0.25rem;
        }
        
        /* Kanban column height */
        .kanban-column-content {
            min-height: 60vh;
            max-height: 70vh;
            overflow-y: auto;
            padding-right: 5px;
        }
    </style>
</head>
<body>
    <div class="d-flex">
        <?php include '../includes/sidebar.php'; ?>
        
        <div class="main-content">
            <!-- Alert placeholder -->
            <div id="alertPlaceholder" class="alert-notification"></div>

            <div class="container-fluid">
                <div class="page-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h1 class="h3 mb-0 text-gray-800">Task Board</h1>
                            <p class="mb-0">Manage and track your project tasks</p>
                        </div>
                        <div>
                            <button class="btn btn-primary me-2" data-bs-toggle="modal" data-bs-target="#addTaskModal">
                                <i class="fas fa-plus me-2"></i>Add Task
                            </button>
                            <button class="btn btn-outline-secondary" id="refreshBoard">
                                <i class="fas fa-sync-alt me-2"></i>Refresh
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Stats Cards -->
                <div class="row mb-4">
                    <?php
                    // Get task statistics
                    $stmt = $pdo->prepare("SELECT 
                        SUM(CASE WHEN status = 'todo' THEN 1 ELSE 0 END) as todo_count,
                        SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_count,
                        SUM(CASE WHEN status = 'done' THEN 1 ELSE 0 END) as done_count,
                        COUNT(*) as total_count
                        FROM tasks 
                        WHERE project_id IN (
                            SELECT project_id 
                            FROM project_members 
                            WHERE user_id = :user_id
                        )");
                    $stmt->execute([':user_id' => $_SESSION['user_id']]);
                    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    $total = $stats['total_count'] ?: 1;
                    $todo_percent = round(($stats['todo_count'] / $total) * 100);
                    $in_progress_percent = round(($stats['in_progress_count'] / $total) * 100);
                    $done_percent = round(($stats['done_count'] / $total) * 100);
                    ?>
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-primary shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                            Total Tasks</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['total_count']; ?></div>
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
                                            To Do</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['todo_count']; ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-circle fa-2x text-gray-300"></i>
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
                                            In Progress</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['in_progress_count']; ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-spinner fa-2x text-gray-300"></i>
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
                                            Completed</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['done_count']; ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Progress Bar -->
                <div class="card shadow mb-4">
                    <div class="card-body">
                        <h4 class="small font-weight-bold">Task Completion <span class="float-right"><?php echo $done_percent; ?>%</span></h4>
                        <div class="progress mb-4">
                            <div class="progress-bar bg-success" role="progressbar" 
                                 style="width: <?php echo $done_percent; ?>%" 
                                 aria-valuenow="<?php echo $done_percent; ?>" 
                                 aria-valuemin="0" 
                                 aria-valuemax="100"></div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4">
                                <h4 class="small font-weight-bold">To Do <span class="float-right"><?php echo $todo_percent; ?>%</span></h4>
                                <div class="progress">
                                    <div class="progress-bar bg-info" role="progressbar" 
                                         style="width: <?php echo $todo_percent; ?>%" 
                                         aria-valuenow="<?php echo $todo_percent; ?>" 
                                         aria-valuemin="0" 
                                         aria-valuemax="100"></div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <h4 class="small font-weight-bold">In Progress <span class="float-right"><?php echo $in_progress_percent; ?>%</span></h4>
                                <div class="progress">
                                    <div class="progress-bar bg-warning" role="progressbar" 
                                         style="width: <?php echo $in_progress_percent; ?>%" 
                                         aria-valuenow="<?php echo $in_progress_percent; ?>" 
                                         aria-valuemin="0" 
                                         aria-valuemax="100"></div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <h4 class="small font-weight-bold">Completed <span class="float-right"><?php echo $done_percent; ?>%</span></h4>
                                <div class="progress">
                                    <div class="progress-bar bg-success" role="progressbar" 
                                         style="width: <?php echo $done_percent; ?>%" 
                                         aria-valuenow="<?php echo $done_percent; ?>" 
                                         aria-valuemin="0" 
                                         aria-valuemax="100"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="kanban-board" id="kanbanBoard">
                    <!-- To Do Column -->
                    <div class="kanban-column" data-status="todo">
                        <div class="kanban-column-header todo-header d-flex justify-content-between align-items-center">
                            <span>To Do</span>
                            <span class="badge bg-secondary"><?php echo $stats['todo_count']; ?></span>
                        </div>
                        <div class="kanban-column-content" id="todo-column">
                            <?php
                            $stmt = $pdo->prepare("SELECT t.*, u.first_name, u.last_name 
                                                  FROM tasks t 
                                                  LEFT JOIN users u ON t.created_by = u.id
                                                  LEFT JOIN task_assignments ta ON t.id = ta.task_id
                                                  WHERE t.status = 'todo'
                                                  AND t.project_id IN (
                                                      SELECT project_id 
                                                      FROM project_members 
                                                      WHERE user_id = :user_id
                                                  )
                                                  ORDER BY t.priority DESC, t.due_date ASC");
                            $stmt->execute([':user_id' => $_SESSION['user_id']]);
                            while ($task = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                echo renderTaskCard($task);
                            }
                            ?>
                        </div>
                    </div>

                    <!-- In Progress Column -->
                    <div class="kanban-column" data-status="in_progress">
                        <div class="kanban-column-header in-progress-header d-flex justify-content-between align-items-center">
                            <span>In Progress</span>
                            <span class="badge bg-secondary"><?php echo $stats['in_progress_count']; ?></span>
                        </div>
                        <div class="kanban-column-content" id="in-progress-column">
                            <?php
                            $stmt = $pdo->prepare("SELECT t.*, u.first_name, u.last_name 
                                                  FROM tasks t 
                                                  LEFT JOIN users u ON t.created_by = u.id
                                                  LEFT JOIN task_assignments ta ON t.id = ta.task_id
                                                  WHERE t.status = 'in_progress'
                                                  AND t.project_id IN (
                                                      SELECT project_id 
                                                      FROM project_members 
                                                      WHERE user_id = :user_id
                                                  )
                                                  ORDER BY t.priority DESC, t.due_date ASC");
                            $stmt->execute([':user_id' => $_SESSION['user_id']]);
                            while ($task = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                echo renderTaskCard($task);
                            }
                            ?>
                        </div>
                    </div>

                    <!-- Done Column -->
                    <div class="kanban-column" data-status="done">
                        <div class="kanban-column-header done-header d-flex justify-content-between align-items-center">
                            <span>Done</span>
                            <span class="badge bg-secondary"><?php echo $stats['done_count']; ?></span>
                        </div>
                        <div class="kanban-column-content" id="done-column">
                            <?php
                            $stmt = $pdo->prepare("SELECT t.*, u.first_name, u.last_name 
                                                  FROM tasks t 
                                                  LEFT JOIN users u ON t.created_by = u.id
                                                  LEFT JOIN task_assignments ta ON t.id = ta.task_id
                                                  WHERE t.status = 'done'
                                                  AND t.project_id IN (
                                                      SELECT project_id 
                                                      FROM project_members 
                                                      WHERE user_id = :user_id
                                                  )
                                                  ORDER BY t.updated_at DESC");
                            $stmt->execute([':user_id' => $_SESSION['user_id']]);
                            while ($task = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                echo renderTaskCard($task);
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Task Modal -->
    <div class="modal fade" id="addTaskModal" tabindex="-1" aria-labelledby="addTaskModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addTaskModalLabel">Add New Task</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="taskForm" action="../tasks/save_task.php" method="POST">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label for="taskTitle" class="form-label">Title *</label>
                                    <input type="text" class="form-control" id="taskTitle" name="title" required>
                                </div>
                                <div class="mb-3">
                                    <label for="taskDescription" class="form-label">Description</label>
                                    <textarea class="form-control" id="taskDescription" name="description" rows="5"></textarea>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card border-0 shadow-sm">
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label for="taskPriority" class="form-label">Priority</label>
                                            <select class="form-select" id="taskPriority" name="priority">
                                                <option value="low">Low</option>
                                                <option value="medium" selected>Medium</option>
                                                <option value="high">High</option>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label for="taskDueDate" class="form-label">Due Date</label>
                                            <input type="text" class="form-control flatpickr" id="taskDueDate" name="due_date" placeholder="Select date">
                                        </div>
                                        <div class="mb-3">
                                            <label for="taskProject" class="form-label">Project *</label>
                                            <select class="form-select" id="taskProject" name="project_id" required>
                                                <option value="">Select Project</option>
                                                <?php
                                                $stmt = $pdo->prepare("SELECT p.id, p.name 
                                                                      FROM projects p
                                                                      JOIN project_members pm ON p.id = pm.project_id
                                                                      WHERE pm.user_id = :user_id
                                                                      ORDER BY p.name");
                                                $stmt->execute([':user_id' => $_SESSION['user_id']]);
                                                while ($project = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                                    echo "<option value='{$project['id']}'>{$project['name']}</option>";
                                                }
                                                ?>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label for="taskAssignee" class="form-label">Assignee</label>
                                            <select class="form-select" id="taskAssignee" name="assignee_id">
                                                <option value="">Unassigned</option>
                                                <?php
                                                $stmt = $pdo->query("SELECT id, first_name, last_name FROM users ORDER BY first_name");
                                                while ($user = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                                    echo "<option value='{$user['id']}'>{$user['first_name']} {$user['last_name']}</option>";
                                                }
                                                ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Save Task</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Task Details Modal -->
    <div class="modal fade" id="taskDetailsModal" tabindex="-1" aria-labelledby="taskDetailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="taskDetailsModalLabel">Task Details</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="taskDetailsContent">
                    <div class="spinner-container">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="saveTaskChanges">Save Changes</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Initialize date picker
        flatpickr(".flatpickr", {
            dateFormat: "Y-m-d",
            allowInput: true,
            minDate: "today"
        });
        
        // Task management script would go here
        document.addEventListener('DOMContentLoaded', function() {
            // Your existing task.js functionality
            // Plus any additional enhancements
        });
    </script>
    <script src="task.js"></script>
</body>
</html>