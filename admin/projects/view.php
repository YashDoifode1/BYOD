<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/config.php';

// Get project ID
$project_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get project details
$stmt = $pdo->prepare("
    SELECT p.*, u.username as created_by_name
    FROM projects p
    JOIN users u ON p.created_by = u.id
    WHERE p.id = ?
");
$stmt->execute([$project_id]);
$project = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$project) {
    header("Location: index.php");
    exit();
}

// Get project tasks
$tasks = $pdo->prepare("
    SELECT t.*, u.username as created_by_name
    FROM tasks t
    JOIN users u ON t.created_by = u.id
    WHERE t.project_id = ?
    ORDER BY t.due_date ASC
");
$tasks->execute([$project_id]);
$tasks = $tasks->fetchAll(PDO::FETCH_ASSOC);

// Get project members
$members = $pdo->prepare("
    SELECT u.id, u.username, u.email, u.role, pm.role as project_role
    FROM project_members pm
    JOIN users u ON pm.user_id = u.id
    WHERE pm.project_id = ?
");
$members->execute([$project_id]);
$members = $members->fetchAll(PDO::FETCH_ASSOC);

// Get project files
$files = $pdo->prepare("
    SELECT f.*, u.username as uploaded_by_name
    FROM files f
    JOIN users u ON f.uploaded_by = u.id
    WHERE f.project_id = ?
    ORDER BY f.uploaded_at DESC
");
$files->execute([$project_id]);
$files = $files->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-project-diagram me-2"></i> <?= htmlspecialchars($project['name']) ?>
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="edit.php?id=<?= $project['id'] ?>" class="btn btn-sm btn-primary">
                <i class="fas fa-edit me-1"></i> Edit
            </a>
            <a href="delete.php?id=<?= $project['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure?')">
                <i class="fas fa-trash me-1"></i> Delete
            </a>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-8">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-info-circle me-1"></i> Project Details
                </h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Description:</strong></p>
                        <p><?= nl2br(htmlspecialchars($project['description'])) ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Created By:</strong> <?= htmlspecialchars($project['created_by_name']) ?></p>
                        <p><strong>Start Date:</strong> <?= date('M d, Y', $project['start_date']) ?></p>
                        <p><strong>End Date:</strong> <?= date('M d, Y', $project['end_date']) ?></p>
                        <p><strong>Status:</strong> 
                            <span class="badge bg-<?= $project['end_date'] > time() ? 'success' : 'danger' ?>">
                                <?= $project['end_date'] > time() ? 'Active' : 'Completed' ?>
                            </span>
                        </p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-tasks me-1"></i> Tasks
                </h6>
                <a href="../tasks/create.php?project_id=<?= $project['id'] ?>" class="btn btn-sm btn-primary">
                    <i class="fas fa-plus me-1"></i> Add Task
                </a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Task</th>
                                <th>Status</th>
                                <th>Priority</th>
                                <th>Due Date</th>
                                <th>Created By</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tasks as $task): ?>
                            <tr>
                                <td><?= htmlspecialchars($task['title']) ?></td>
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
                                <td>
                                    <?= date('M d, Y', strtotime($task['due_date'])) ?>
                                    <?php if (strtotime($task['due_date']) < time() && $task['status'] != 'done'): ?>
                                        <span class="badge bg-danger ms-1">Overdue</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($task['created_by_name']) ?></td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="../tasks/view.php?id=<?= $task['id'] ?>" class="btn btn-info">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="../tasks/edit.php?id=<?= $task['id'] ?>" class="btn btn-primary">
                                            <i class="fas fa-edit"></i>
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
    
    <div class="col-md-4">
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-users me-1"></i> Team Members
                </h6>
                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addMemberModal">
                    <i class="fas fa-plus me-1"></i> Add
                </button>
            </div>
            <div class="card-body">
                <div class="list-group">
                    <?php foreach ($members as $member): ?>
                    <div class="list-group-item">
                        <div class="d-flex w-100 justify-content-between">
                            <h6 class="mb-1"><?= htmlspecialchars($member['username']) ?></h6>
                            <small class="text-muted"><?= ucfirst($member['project_role']) ?></small>
                        </div>
                        <p class="mb-1"><?= htmlspecialchars($member['email']) ?></p>
                        <small class="text-muted">
                            <span class="badge bg-<?= 
                                $member['role'] === 'admin' ? 'danger' : 
                                ($member['role'] === 'manager' ? 'warning' : 'primary')
                            ?>">
                                <?= ucfirst($member['role']) ?>
                            </span>
                        </small>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-file me-1"></i> Files
                </h6>
                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#uploadFileModal">
                    <i class="fas fa-upload me-1"></i> Upload
                </button>
            </div>
            <div class="card-body">
                <div class="list-group">
                    <?php foreach ($files as $file): ?>
                    <a href="<?= htmlspecialchars($file['path']) ?>" class="list-group-item list-group-item-action" download>
                        <div class="d-flex w-100 justify-content-between">
                            <h6 class="mb-1"><?= htmlspecialchars($file['name']) ?></h6>
                            <small><?= formatBytes($file['size']) ?></small>
                        </div>
                        <p class="mb-1"><?= htmlspecialchars($file['mime_type']) ?></p>
                        <small class="text-muted">Uploaded by <?= htmlspecialchars($file['uploaded_by_name']) ?></small>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Member Modal -->
<div class="modal fade" id="addMemberModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Team Member</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="add_member.php">
                <div class="modal-body">
                    <input type="hidden" name="project_id" value="<?= $project['id'] ?>">
                    <div class="mb-3">
                        <label class="form-label">Select User</label>
                        <select class="form-select" name="user_id" required>
                            <?php 
                            $all_users = $pdo->query("SELECT id, username FROM users WHERE id NOT IN (
                                SELECT user_id FROM project_members WHERE project_id = $project_id
                            )")->fetchAll(PDO::FETCH_ASSOC);
                            foreach ($all_users as $user): ?>
                            <option value="<?= $user['id'] ?>"><?= htmlspecialchars($user['username']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Role</label>
                        <select class="form-select" name="role" required>
                            <option value="member">Member</option>
                            <option value="owner">Owner</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Add Member</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Upload File Modal -->
<div class="modal fade" id="uploadFileModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Upload File</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="upload_file.php" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="project_id" value="<?= $project['id'] ?>">
                    <div class="mb-3">
                        <label class="form-label">File</label>
                        <input type="file" class="form-control" name="file" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Upload</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>