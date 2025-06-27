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
    
    // Status options
    $statusOptions = [
        'todo' => 'To Do',
        'in_progress' => 'In Progress',
        'done' => 'Done'
    ];
    
    return '
    <div class="task-card ' . $priorityClass . '" data-task-id="' . $task['id'] . '" draggable="true">
        <div class="d-flex justify-content-between align-items-start mb-2">
            <h6 class="mb-0">' . htmlspecialchars($task['title']) . '</h6>
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
        <div class="d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center">
                <img src="../avatars/' . $task['created_by'] . '.jpg" alt="' . $assigneeName . '" 
                     class="assignee-avatar me-2" onerror="this.src=\'profile.jpeg\'">
                <span class="small">' . $assigneeName . '</span>
            </div>
            <span class="due-date small">' . $dueDate . '</span>
        </div>
        <div class="quick-status-buttons mt-2 d-flex justify-content-between">
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
    </div>';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Task Board</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- <link rel="stylesheet" href="../assets/css/main.css"> -->
    <style>
        .main-content {
            padding: 20px;
            transition: margin-left 0.3s;
        }
        
        @media (max-width: 991.98px) {
            .main-content {
                margin-left: 0;
            }
        }
        
        .kanban-board {
            display: flex;
            gap: 15px;
            padding: 20px;
            overflow-x: auto;
            min-height: 70vh;
        }
        .kanban-column {
            flex: 1;
            min-width: 300px;
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
        }
        .kanban-column-header {
            font-weight: bold;
            margin-bottom: 15px;
            padding: 10px;
            border-radius: 4px;
            text-align: center;
        }
        .todo-header {
            background-color: #e9ecef;
            color: #495057;
        }
        .in-progress-header {
            background-color: #fff3cd;
            color: #856404;
        }
        .done-header {
            background-color: #d4edda;
            color: #155724;
        }
        .task-card {
            background: white;
            border-radius: 6px;
            padding: 12px;
            margin-bottom: 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            cursor: grab;
        }
        .task-card:hover {
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        .priority-high {
            border-left: 4px solid #dc3545;
        }
        .priority-medium {
            border-left: 4px solid #ffc107;
        }
        .priority-low {
            border-left: 4px solid #28a745;
        }
        .assignee-avatar {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            object-fit: cover;
        }
        .due-date {
            font-size: 0.85rem;
            color: #6c757d;
        }
        .badge-priority {
            font-size: 0.75rem;
            padding: 0.25em 0.4em;
        }
        .dragging {
            opacity: 0.5;
            transform: rotate(2deg);
            background: #f8f9fa;
        }
        .kanban-column.dropzone {
            background-color: rgba(0,0,0,0.05);
            transition: background-color 0.2s;
        }
        .quick-status-buttons .btn {
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
        }
        .alert-notification {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1100;
            min-width: 300px;
        }
    </style>
</head>
<body>
    <div class="d-flex">
        <?php include '../includes/sidebar.php'; ?>
        
        <div class="main-content w-100">
            <!-- Alert placeholder -->
            <div id="alertPlaceholder" class="alert-notification"></div>

            <div class="container-fluid mt-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1>Task Board</h1>
                    <div>
                        <button class="btn btn-primary me-2" data-bs-toggle="modal" data-bs-target="#addTaskModal">
                            <i class="fas fa-plus"></i> Add Task
                        </button>
                        <button class="btn btn-outline-secondary" id="refreshBoard">
                            <i class="fas fa-sync-alt"></i> Refresh
                        </button>
                    </div>
                </div>

                <div class="kanban-board" id="kanbanBoard">
                    <!-- To Do Column -->
                    <div class="kanban-column" data-status="todo">
                        <div class="kanban-column-header todo-header">To Do</div>
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
                        <div class="kanban-column-header in-progress-header">In Progress</div>
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
                        <div class="kanban-column-header done-header">Done</div>
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
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addTaskModalLabel">Add New Task</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="taskForm" action="../tasks/save_task.php" method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="taskTitle" class="form-label">Title *</label>
                            <input type="text" class="form-control" id="taskTitle" name="title" required>
                        </div>
                        <div class="mb-3">
                            <label for="taskDescription" class="form-label">Description</label>
                            <textarea class="form-control" id="taskDescription" name="description" rows="3"></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="taskPriority" class="form-label">Priority</label>
                                <select class="form-select" id="taskPriority" name="priority">
                                    <option value="low">Low</option>
                                    <option value="medium" selected>Medium</option>
                                    <option value="high">High</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="taskDueDate" class="form-label">Due Date</label>
                                <input type="date" class="form-control" id="taskDueDate" name="due_date">
                            </div>
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
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="taskDetailsContent">
                    <div class="text-center my-5">
                        <div class="spinner-border" role="status">
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
    <script src="task.js"></script>
</body>
</html>