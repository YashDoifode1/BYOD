<?php
require_once '..\includes/config.php';
session_start();

if (!isset($_GET['task_id'])) {
    die('Task ID not provided');
}

$taskId = $_GET['task_id'];

try {
    // Get task details
    $stmt = $pdo->prepare("SELECT t.*, p.name as project_name, 
                          u.first_name as creator_first, u.last_name as creator_last,
                          a.first_name as assignee_first, a.last_name as assignee_last
                          FROM tasks t
                          JOIN projects p ON t.project_id = p.id
                          JOIN users u ON t.created_by = u.id
                          LEFT JOIN task_assignments ta ON t.id = ta.task_id
                          LEFT JOIN users a ON ta.user_id = a.id
                          WHERE t.id = ?");
    $stmt->execute([$taskId]);
    $task = $stmt->fetch();

    if (!$task) {
        die('Task not found');
    }

    // Get status history
    $stmt = $pdo->prepare("SELECT al.*, u.first_name, u.last_name 
                          FROM activity_logs al
                          LEFT JOIN users u ON al.user_id = u.id
                          WHERE al.project_id = ? AND al.action = 'status_update'
                          ORDER BY al.created_at DESC");
    $stmt->execute([$task['project_id']]);
    $statusHistory = $stmt->fetchAll();

    // Format assignee name
    $assigneeName = $task['assignee_first'] ? 
        $task['assignee_first'] . ' ' . $task['assignee_last'] : 'Unassigned';

    // Format creator name
    $creatorName = $task['creator_first'] . ' ' . $task['creator_last'];

    // Format due date
    $dueDate = $task['due_date'] ? date('Y-m-d', strtotime($task['due_date'])) : '';

    // Generate HTML
    echo '<form id="taskDetailsForm" data-task-id="' . $taskId . '">
        <div class="row">
            <div class="col-md-8">
                <div class="mb-3">
                    <label class="form-label">Title</label>
                    <input type="text" class="form-control" name="title" value="' . htmlspecialchars($task['title']) . '" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Description</label>
                    <textarea class="form-control" name="description" rows="5">' . htmlspecialchars($task['description']) . '</textarea>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card mb-3">
                    <div class="card-header">Task Details</div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status">
                                <option value="todo"' . ($task['status'] === 'todo' ? ' selected' : '') . '>To Do</option>
                                <option value="in_progress"' . ($task['status'] === 'in_progress' ? ' selected' : '') . '>In Progress</option>
                                <option value="done"' . ($task['status'] === 'done' ? ' selected' : '') . '>Done</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Priority</label>
                            <select class="form-select" name="priority">
                                <option value="low"' . ($task['priority'] === 'low' ? ' selected' : '') . '>Low</option>
                                <option value="medium"' . ($task['priority'] === 'medium' ? ' selected' : '') . '>Medium</option>
                                <option value="high"' . ($task['priority'] === 'high' ? ' selected' : '') . '>High</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Due Date</label>
                            <input type="date" class="form-control" name="due_date" value="' . $dueDate . '">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Assignee</label>
                            <select class="form-select" name="assignee_id">
                                <option value="">Unassigned</option>';
                                
                                // Get all users for assignee dropdown
                                $stmt = $pdo->query("SELECT id, first_name, last_name FROM users");
                                while ($user = $stmt->fetch()) {
                                    $selected = ($user['first_name'] === $task['assignee_first'] && 
                                                $user['last_name'] === $task['assignee_last']) ? ' selected' : '';
                                    echo '<option value="' . $user['id'] . '"' . $selected . '>' . 
                                         htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) . '</option>';
                                }
                                
                                echo '</select>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="card mb-3">
            <div class="card-header">Status History</div>
            <div class="card-body">
                <ul class="list-group list-group-flush">';
                
                if (empty($statusHistory)) {
                    echo '<li class="list-group-item">No status history available</li>';
                } else {
                    foreach ($statusHistory as $log) {
                        $userName = $log['first_name'] ? $log['first_name'] . ' ' . $log['last_name'] : 'System';
                        $date = date('M j, Y g:i a', strtotime($log['created_at']));
                        echo '<li class="list-group-item">
                            <div class="d-flex justify-content-between">
                                <span>' . htmlspecialchars($log['description']) . '</span>
                                <small class="text-muted">' . $date . ' by ' . htmlspecialchars($userName) . '</small>
                            </div>
                        </li>';
                    }
                }
                
                echo '</ul>
            </div>
        </div>
    </form>';
} catch (PDOException $e) {
    echo '<div class="alert alert-danger">Error loading task details: ' . htmlspecialchars($e->getMessage()) . '</div>';
}
?>