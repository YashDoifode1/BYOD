<?php
// Start session at the very top
session_start();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/auth_project.php';
require_once __DIR__ . '/../includes/config.php';
// require_once __DIR__ . '/../includes/db.php';

// Verify user is logged in
if (!isLoggedIn()) {
    header('Location: ../login.php');
    exit();
}

$userId = $_SESSION['user']['id'];
$projectId = $_GET['id'] ?? 0;

// Verify user has permission to edit this project
if (!hasProjectAccess($userId, $projectId, 'owner') && $_SESSION['user']['role'] !== 'admin') {
    header('HTTP/1.0 403 Forbidden');
    die('You do not have permission to edit this project');
}

// Fetch project details
$stmt = $pdo->prepare("SELECT * FROM projects WHERE id = ?");
$stmt->execute([$projectId]);
$project = $stmt->fetch();

// Handle form submission
$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $startDate = $_POST['start_date'] ?? null;
    $endDate = $_POST['end_date'] ?? null;

    // Validate inputs
    if (empty($name)) {
        $errors[] = 'Project name is required';
    }

    if (strlen($name) > 100) {
        $errors[] = 'Project name must be less than 100 characters';
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            $updateStmt = $pdo->prepare("
                UPDATE projects 
                SET name = ?, description = ?, start_date = ?, end_date = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $updateStmt->execute([$name, $description, $startDate, $endDate, $projectId]);

            // Log activity
            $logStmt = $pdo->prepare("
                INSERT INTO activity_logs (user_id, project_id, action, description)
                VALUES (?, ?, 'update_project', ?)
            ");
            $logStmt->execute([
                $userId,
                $projectId,
                "Updated project details: " . substr($name, 0, 50)
            ]);

            $pdo->commit();
            $success = 'Project updated successfully!';
            $project['name'] = $name;
            $project['description'] = $description;
            $project['start_date'] = $startDate;
            $project['end_date'] = $endDate;
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
}
?>

<body class="dashboard">
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar Navigation -->
            <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Edit Project</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="view.php?id=<?= $projectId ?>" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-1"></i> Back to Project
                        </a>
                    </div>
                </div>

                <?php if ($success): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                <?php endif; ?>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <h5>Error(s) occurred:</h5>
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?= htmlspecialchars($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Project Details</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="name" class="form-label">Project Name *</label>
                                    <input type="text" class="form-control" id="name" name="name" 
                                           value="<?= htmlspecialchars($project['name'] ?? '') ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Project ID</label>
                                    <input type="text" class="form-control" value="<?= $projectId ?>" readonly>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="3"><?= htmlspecialchars($project['description'] ?? '') ?></textarea>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="start_date" class="form-label">Start Date</label>
                                    <input type="date" class="form-control" id="start_date" name="start_date" 
                                           value="<?= htmlspecialchars($project['start_date'] ?? '') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label for="end_date" class="form-label">End Date</label>
                                    <input type="date" class="form-control" id="end_date" name="end_date" 
                                           value="<?= htmlspecialchars($project['end_date'] ?? '') ?>">
                                </div>
                            </div>

                            <div class="d-flex justify-content-end">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i> Save Changes
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <?php require_once __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>