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

// Get assigned users
$assigned_users = $pdo->prepare("
    SELECT user_id FROM task_assignments WHERE task_id = ?
");
$assigned_users->execute([$task_id]);
$assigned_users = $assigned_users->fetchAll(PDO::FETCH_COLUMN);

// Get projects and users for dropdowns
$projects = $pdo->query("SELECT id, name FROM projects ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$users = $pdo->query("SELECT id, username FROM users ORDER BY username")->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();
        
        // Update task
        $stmt = $pdo->prepare("
            UPDATE tasks SET
                project_id = ?,
                title = ?,
                description = ?,
                status = ?,
                priority = ?,
                due_date = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([
            $_POST['project_id'],
            $_POST['title'],
            $_POST['description'],
            $_POST['status'],
            $_POST['priority'],
            $_POST['due_date'],
            $task_id
        ]);
        
        // Update assigned users
        $pdo->prepare("DELETE FROM task_assignments WHERE task_id = ?")->execute([$task_id]);
        
        if (!empty($_POST['assigned_users'])) {
            $assign_stmt = $pdo->prepare("INSERT INTO task_assignments (task_id, user_id) VALUES (?, ?)");
            foreach ($_POST['assigned_users'] as $user_id) {
                $assign_stmt->execute([$task_id, $user_id]);
            }
        }
        
        // Log activity
        $log_stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, description) VALUES (?, ?, ?)");
        $log_stmt->execute([
            $_SESSION['user_id'],
            'task_update',
            'Updated task: ' . $_POST['title']
        ]);
        
        $pdo->commit();
        
        $_SESSION['flash_message'] = [
            'type' => 'success',
            'message' => 'Task updated successfully!'
        ];
        
        header("Location: view.php?id=$task_id");
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error updating task: " . $e->getMessage();
    }
}
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-edit me-2"></i> Edit Task
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
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">
            <i class="fas fa-tasks me-1"></i> Edit Task Details
        </h6>
    </div>
    <div class="card-body">
        <form method="POST">
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="title" class="form-label">Task Title *</label>
                    <input type="text" class="form-control" id="title" name="title" 
                           value="<?= htmlspecialchars($task['title']) ?>" required>
                </div>
                <div class="col-md-6">
                    <label for="project_id" class="form-label">Project *</label>
                    <select class="form-select" id="project_id" name="project_id" required>
                        <?php foreach ($projects as $project): ?>
                            <option value="<?= $project['id'] ?>" <?= $project['id'] == $task['project_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($project['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-12">
                    <label for="description" class="form-label">Description</label>
                    <textarea class="form-control" id="description" name="description" rows="3"><?= htmlspecialchars($task['description']) ?></textarea>
                </div>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-3">
                    <label for="status" class="form-label">Status *</label>
                    <select class="form-select" id="status" name="status" required>
                        <option value="todo" <?= $task['status'] == 'todo' ? 'selected' : '' ?>>To Do</option>
                        <option value="in_progress" <?= $task['status'] == 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                        <option value="done" <?= $task['status'] == 'done' ? 'selected' : '' ?>>Done</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="priority" class="form-label">Priority *</label>
                    <select class="form-select" id="priority" name="priority" required>
                        <option value="low" <?= $task['priority'] == 'low' ? 'selected' : '' ?>>Low</option>
                        <option value="medium" <?= $task['priority'] == 'medium' ? 'selected' : '' ?>>Medium</option>
                        <option value="high" <?= $task['priority'] == 'high' ? 'selected' : '' ?>>High</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="due_date" class="form-label">Due Date *</label>
                    <input type="date" class="form-control" id="due_date" name="due_date" 
                           value="<?= date('Y-m-d', strtotime($task['due_date'])) ?>" required>
                </div>
                <div class="col-md-3">
                    <label for="assigned_users" class="form-label">Assign To</label>
                    <select class="form-select" id="assigned_users" name="assigned_users[]" multiple>
                        <?php foreach ($users as $user): ?>
                            <option value="<?= $user['id'] ?>" <?= in_array($user['id'], $assigned_users) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($user['username']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="text-muted">Hold CTRL to select multiple</small>
                </div>
            </div>
            
            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                <button type="reset" class="btn btn-secondary me-md-2">
                    <i class="fas fa-undo me-1"></i> Reset
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save me-1"></i> Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php';