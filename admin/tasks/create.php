<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/header.php';

// Check permissions
if (!in_array($_SESSION['user_role'], ['admin', 'manager'])) {
    header("Location: /unauthorized.php");
    exit();
}

// Database connection
require_once __DIR__ . '/../includes/config.php';

// Get projects and users for dropdowns
$projects = $pdo->query("SELECT id, name FROM projects ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$users = $pdo->query("SELECT id, username FROM users ORDER BY username")->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();
        
        // Insert task
        $stmt = $pdo->prepare("
            INSERT INTO tasks (project_id, title, description, status, priority, due_date, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $_POST['project_id'],
            $_POST['title'],
            $_POST['description'],
            $_POST['status'],
            $_POST['priority'],
            $_POST['due_date'],
            $_SESSION['user_id']
        ]);
        
        $task_id = $pdo->lastInsertId();
        
        // Assign users to task
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
            'task_create',
            'Created task: ' . $_POST['title']
        ]);
        
        $pdo->commit();
        
        $_SESSION['flash_message'] = [
            'type' => 'success',
            'message' => 'Task created successfully!'
        ];
        
        header("Location: view.php?id=$task_id");
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error creating task: " . $e->getMessage();
    }
}
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-plus-circle me-2"></i> Create New Task
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="index.php" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i> Back to Tasks
        </a>
    </div>
</div>

<?php if (isset($error)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">
            <i class="fas fa-tasks me-1"></i> Task Details
        </h6>
    </div>
    <div class="card-body">
        <form method="POST">
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="title" class="form-label">Task Title *</label>
                    <input type="text" class="form-control" id="title" name="title" required>
                </div>
                <div class="col-md-6">
                    <label for="project_id" class="form-label">Project *</label>
                    <select class="form-select" id="project_id" name="project_id" required>
                        <option value="">Select Project</option>
                        <?php foreach ($projects as $project): ?>
                            <option value="<?= $project['id'] ?>"><?= htmlspecialchars($project['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-12">
                    <label for="description" class="form-label">Description</label>
                    <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                </div>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-3">
                    <label for="status" class="form-label">Status *</label>
                    <select class="form-select" id="status" name="status" required>
                        <option value="todo">To Do</option>
                        <option value="in_progress">In Progress</option>
                        <option value="done">Done</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="priority" class="form-label">Priority *</label>
                    <select class="form-select" id="priority" name="priority" required>
                        <option value="low">Low</option>
                        <option value="medium" selected>Medium</option>
                        <option value="high">High</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="due_date" class="form-label">Due Date *</label>
                    <input type="date" class="form-control" id="due_date" name="due_date" required>
                </div>
                <div class="col-md-3">
                    <label for="assigned_users" class="form-label">Assign To</label>
                    <select class="form-select" id="assigned_users" name="assigned_users[]" multiple>
                        <?php foreach ($users as $user): ?>
                            <option value="<?= $user['id'] ?>"><?= htmlspecialchars($user['username']) ?></option>
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
                    <i class="fas fa-save me-1"></i> Create Task
                </button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('due_date').valueAsDate = new Date();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>