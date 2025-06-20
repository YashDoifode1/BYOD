<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/header.php';

// Check permissions
if (!in_array($_SESSION['user_role'], ['admin', 'manager'])) {
    header("Location: /unauthorized.php");
    exit();
}

// Database connection
require_once __DIR__ . '/../../includes/config.php';

// Get task ID
$task_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get task details
$stmt = $pdo->prepare("
    SELECT t.*, p.name as project_name
    FROM tasks t
    JOIN projects p ON t.project_id = p.id
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

// Handle deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();
        
        // Delete task assignments first
        $pdo->prepare("DELETE FROM task_assignments WHERE task_id = ?")->execute([$task_id]);
        
        // Then delete the task
        $pdo->prepare("DELETE FROM tasks WHERE id = ?")->execute([$task_id]);
        
        // Log activity
        $log_stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, description) VALUES (?, ?, ?)");
        $log_stmt->execute([
            $_SESSION['user_id'],
            'task_delete',
            'Deleted task: ' . $task['title']
        ]);
        
        $pdo->commit();
        
        $_SESSION['flash_message'] = [
            'type' => 'success',
            'message' => 'Task deleted successfully!'
        ];
        
        header("Location: index.php");
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error deleting task: " . $e->getMessage();
    }
}
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-trash-alt me-2"></i> Delete Task
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="view.php?id=<?= $task_id ?>" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i> Back to Task
        </a>
    </div>
</div>

<?php if (isset($error)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="card shadow mb-4">
    <div class="card-header py-3 bg-danger text-white">
        <h6 class="m-0 font-weight-bold">
            <i class="fas fa-exclamation-triangle me-1"></i> Confirm Deletion
        </h6>
    </div>
    <div class="card-body">
        <div class="alert alert-warning">
            <strong>Warning!</strong> This action cannot be undone. All task assignments will also be deleted.
        </div>
        
        <div class="mb-4">
            <h5>Task Details</h5>
            <div class="row">
                <div class="col-md-6">
                    <p><strong>Title:</strong> <?= htmlspecialchars($task['title']) ?></p>
                    <p><strong>Project:</strong> <?= htmlspecialchars($task['project_name']) ?></p>
                </div>
                <div class="col-md-6">
                    <p><strong>Status:</strong> <?= ucfirst(str_replace('_', ' ', $task['status'])) ?></p>
                    <p><strong>Due Date:</strong> <?= date('M j, Y', strtotime($task['due_date'])) ?></p>
                </div>
            </div>
        </div>
        
        <form method="POST">
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" id="confirmDelete" required>
                <label class="form-check-label" for="confirmDelete">
                    I understand that this action is permanent and cannot be undone
                </label>
            </div>
            
            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                <a href="view.php?id=<?= $task_id ?>" class="btn btn-secondary me-md-2">
                    <i class="fas fa-times me-1"></i> Cancel
                </a>
                <button type="submit" class="btn btn-danger">
                    <i class="fas fa-trash-alt me-1"></i> Delete Permanently
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php';