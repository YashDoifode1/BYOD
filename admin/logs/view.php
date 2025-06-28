<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/header.php';

// Database connection
require_once __DIR__ . '/../includes/config.php';

// Get task ID
$task_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get task details
$stmt = $pdo->prepare("
    SELECT t.*, 
           p.name as project_name,
           p.id as project_id,
           u.username as created_by_name
    FROM tasks t
    JOIN projects p ON t.project_id = p.id
    JOIN users u ON t.created_by = u.id
    WHERE t.id = ?
");
$stmt->execute([$task_id]);
$task = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$task) {
    $_SESSION['flash_message'] = [
        'type' => 'danger',
        'message' => 'Task not found!'
    ];
    header("Location: index.php");
    exit();
}

// Get assigned users
$assigned_users = $pdo->prepare("
    SELECT u.id, u.username, u.role
    FROM task_assignments ta
    JOIN users u ON ta.user_id = u.id
    WHERE ta.task_id = ?
");
$assigned_users->execute([$task_id]);
$assigned_users = $assigned_users->fetchAll(PDO::FETCH_ASSOC);

// Get task comments/messages
$messages = $pdo->prepare("
    SELECT m.*, u.username as sender_name
    FROM messages m
    JOIN users u ON m.sender_id = u.id
    WHERE m.project_id = ? AND m.message LIKE CONCAT('%', 'task #', ?, '%')
    ORDER BY m.sent_at DESC
");
$messages->execute([$task['project_id'], $task_id]);
$messages = $messages->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-tasks me-2"></i> Task: <?= htmlspecialchars($task['title']) ?>
        <span class="badge bg-<?= 
            $task['status'] == 'done' ? 'success' : 
            ($task['status'] == 'in_progress' ? 'info' : 'warning')
        ?>">
            <?= ucfirst(str_replace('_', ' ', $task['status'])) ?>
        </span>
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="edit.php?id=<?= $task_id ?>" class="btn btn-sm btn-primary">
                <i class="fas fa-edit me-1"></i> Edit
            </a>
            <a href="delete.php?id=<?= $task_id ?>" class="btn btn-sm btn-danger">
                <i class="fas fa-trash-alt me-1"></i> Delete
            </a>
        </div>
        <a href="index.php" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i> Back to Tasks
        </a>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-8">
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-info-circle me-1"></i> Task Details
                </h6>
                <span class="badge bg-<?= 
                    $task['priority'] == 'high' ? 'danger' : 
                    ($task['priority'] == 'medium' ? 'warning' : 'secondary')
                ?>">
                    <?= ucfirst($task['priority']) ?> Priority
                </span>
            </div>
            <div class="card-body">
                <div class="mb-4">
                    <h5>Description</h5>
                    <p><?= nl2br(htmlspecialchars($task['description'])) ?: '<span class="text-muted">No description provided</span>' ?></p>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Project:</strong> 
                            <a href="../projects/view.php?id=<?= $task['project_id'] ?>" class="badge bg-info text-decoration-none">
                                <?= htmlspecialchars($task['project_name']) ?>
                            </a>
                        </p>
                        <p><strong>Created By:</strong> <?= htmlspecialchars($task['created_by_name']) ?></p>
                        <p><strong>Created At:</strong> <?= date('M j, Y g:i a', strtotime($task['created_at'])) ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Due Date:</strong> 
                            <?= date('M j, Y', strtotime($task['due_date'])) ?>
                            <?php if (strtotime($task['due_date']) < time() && $task['status'] != 'done'): ?>
                                <span class="badge bg-danger ms-1">Overdue</span>
                            <?php endif; ?>
                        </p>
                        <p><strong>Last Updated:</strong> <?= $task['updated_at'] ? date('M j, Y g:i a', strtotime($task['updated_at'])) : 'Never' ?></p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-comments me-1"></i> Discussion
                </h6>
            </div>
            <div class="card-body">
                <?php if (empty($messages)): ?>
                    <div class="text-center text-muted py-4">
                        <i class="fas fa-comment-slash fa-2x mb-3"></i>
                        <p>No comments yet</p>
                    </div>
                <?php else: ?>
                    <div class="timeline">
                        <?php foreach ($messages as $message): ?>
                        <div class="timeline-item">
                            <div class="timeline-icon">
                                <i class="fas fa-comment-dots"></i>
                            </div>
                            <div class="timeline-content">
                                <div class="d-flex justify-content-between">
                                    <span class="font-weight-bold">
                                        <?= htmlspecialchars($message['sender_name']) ?>
                                    </span>
                                    <small class="text-muted">
                                        <?= date('M j, Y g:i a', strtotime($message['sent_at'])) ?>
                                    </small>
                                </div>
                                <p class="mb-1"><?= htmlspecialchars($message['message']) ?></p>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="../projects/add_comment.php" class="mt-4">
                    <input type="hidden" name="project_id" value="<?= $task['project_id'] ?>">
                    <input type="hidden" name="task_id" value="<?= $task_id ?>">
                    <div class="mb-3">
                        <label for="comment" class="form-label">Add Comment</label>
                        <textarea class="form-control" id="comment" name="message" rows="3" required placeholder="Mention 'task #<?= $task_id ?>' to link to this task"></textarea>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane me-1"></i> Post Comment
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-users me-1"></i> Assigned Team
                </h6>
            </div>
            <div class="card-body">
                <?php if (empty($assigned_users)): ?>
                    <div class="text-center text-muted py-4">
                        <i class="fas fa-user-times fa-2x mb-3"></i>
                        <p>No one assigned to this task</p>
                    </div>
                <?php else: ?>
                    <div class="list-group">
                        <?php foreach ($assigned_users as $user): ?>
                        <div class="list-group-item">
                            <div class="d-flex w-100 justify-content-between">
                                <h6 class="mb-1"><?= htmlspecialchars($user['username']) ?></h6>
                                <span class="badge bg-<?= 
                                    $user['role'] === 'admin' ? 'danger' : 
                                    ($user['role'] === 'manager' ? 'warning' : 'primary')
                                ?>">
                                    <?= ucfirst($user['role']) ?>
                                </span>
                            </div>
                            <small class="text-muted">ID: <?= $user['id'] ?></small>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-history me-1"></i> Task Activity
                </h6>
            </div>
            <div class="card-body">
                <?php
                $activity = $pdo->prepare("
                    SELECT al.*, u.username
                    FROM activity_logs al
                    LEFT JOIN users u ON al.user_id = u.id
                    WHERE al.project_id = ? AND al.description LIKE CONCAT('%', 'task #', ?, '%')
                    ORDER BY al.created_at DESC
                    LIMIT 5
                ");
                $activity->execute([$task['project_id'], $task_id]);
                $activity = $activity->fetchAll(PDO::FETCH_ASSOC);
                ?>
                
                <?php if (empty($activity)): ?>
                    <div class="text-center text-muted py-4">
                        <i class="fas fa-history fa-2x mb-3"></i>
                        <p>No activity recorded</p>
                    </div>
                <?php else: ?>
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
                                <p class="mb-1"><?= ucfirst(str_replace('_', ' ', $log['action'])) ?></p>
                                <small class="text-muted"><?= htmlspecialchars($log['description']) ?></small>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="text-center mt-3">
                        <a href="../activity/?project_id=<?= $task['project_id'] ?>&search=task #<?= $task_id ?>" class="btn btn-sm btn-outline-primary">
                            View Full Activity
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

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

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>