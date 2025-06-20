<?php
session_start();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/auth_project.php';
require_once __DIR__ . '/../includes/config.php';
// require_once __DIR__ . '/../includes/db.php';

// Verify user is logged in and has permission
if (!isLoggedIn() || !in_array($_SESSION['user']['role'], ['admin', 'manager'])) {
    header('HTTP/1.0 403 Forbidden');
    die('Only admins and managers can create projects');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();
        
        // Validate and sanitize input
        $name = trim(htmlspecialchars($_POST['name'] ?? ''));
        $description = trim(htmlspecialchars($_POST['description'] ?? ''));
        $startDate = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
        $endDate = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
        
        // Validation
        if (empty($name)) {
            throw new Exception('Project name is required');
        }

        if (strlen($name) > 100) {
            throw new Exception('Project name must be less than 100 characters');
        }

        // Check if project name already exists
        $checkStmt = $pdo->prepare("SELECT id FROM projects WHERE name = ?");
        $checkStmt->execute([$name]);
        if ($checkStmt->fetch()) {
            throw new Exception('A project with this name already exists');
        }

        // Insert project
        $stmt = $pdo->prepare("
            INSERT INTO projects (name, description, start_date, end_date, created_by)
            VALUES (:name, :description, :start_date, :end_date, :user_id)
        ");
        $stmt->execute([
            ':name' => $name,
            ':description' => $description,
            ':start_date' => $startDate,
            ':end_date' => $endDate,
            ':user_id' => $_SESSION['user']['id']
        ]);
        
        $projectId = $pdo->lastInsertId();
        
        // Auto-add creator as owner
        $pdo->prepare("
            INSERT INTO project_members (project_id, user_id, role)
            VALUES (?, ?, 'owner')
        ")->execute([$projectId, $_SESSION['user']['id']]);
        
        // Log activity
        $logStmt = $pdo->prepare("
            INSERT INTO activity_logs (user_id, project_id, action, description)
            VALUES (?, ?, 'create_project', ?)
        ");
        $logStmt->execute([
            $_SESSION['user']['id'],
            $projectId,
            "Created project: " . substr($name, 0, 50)
        ]);
        
        $pdo->commit();
        
        // Redirect to new project
        header("Location: view.php?id=$projectId");
        exit;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = $e->getMessage();
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
                    <h1 class="h2">
                        <i class="fas fa-plus-circle me-2"></i>
                        Create New Project
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="index.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-1"></i> Back to Projects
                        </a>
                    </div>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <?= htmlspecialchars($error) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-info-circle me-2"></i>
                            Project Details
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="createProjectForm">
                            <div class="row mb-3">
                                <div class="col-md-8">
                                    <label for="projectName" class="form-label">Project Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="projectName" name="name" 
                                           value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" 
                                           required maxlength="100">
                                    <div class="form-text">Maximum 100 characters</div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Created By</label>
                                    <input type="text" class="form-control" 
                                           value="<?= htmlspecialchars($_SESSION['user']['first_name'] . ' ' . $_SESSION['user']['last_name']) ?>" 
                                           readonly>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="projectDescription" class="form-label">Description</label>
                                <textarea class="form-control" id="projectDescription" name="description" 
                                          rows="4"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                            </div>

                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <label for="startDate" class="form-label">Start Date</label>
                                    <input type="date" class="form-control" id="startDate" name="start_date"
                                           value="<?= htmlspecialchars($_POST['start_date'] ?? '') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label for="endDate" class="form-label">End Date</label>
                                    <input type="date" class="form-control" id="endDate" name="end_date"
                                           value="<?= htmlspecialchars($_POST['end_date'] ?? '') ?>">
                                </div>
                            </div>

                            <div class="d-flex justify-content-end">
                                <button type="reset" class="btn btn-outline-secondary me-2">
                                    <i class="fas fa-undo me-1"></i> Reset
                                </button>
                                <button type="submit" class="btn btn-primary px-4">
                                    <i class="fas fa-save me-1"></i> Create Project
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card shadow-sm">
                    <div class="card-header bg-light">
                        <h5 class="mb-0">
                            <i class="fas fa-lightbulb me-2"></i>
                            Quick Tips
                        </h5>
                    </div>
                    <div class="card-body">
                        <ul class="mb-0">
                            <li>Give your project a clear, descriptive name</li>
                            <li>Add detailed descriptions to help team members understand the project</li>
                            <li>Set realistic dates to help with planning</li>
                            <li>You'll be able to add team members after creation</li>
                        </ul>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <?php require_once __DIR__ . '/../includes/footer.php'; ?>

    <script>
    $(document).ready(function() {
        // Date validation
        $('#createProjectForm').submit(function() {
            const startDate = $('#startDate').val();
            const endDate = $('#endDate').val();
            
            if (startDate && endDate && new Date(startDate) > new Date(endDate)) {
                alert('End date must be after start date');
                return false;
            }
            return true;
        });

        // Set minimum end date based on start date
        $('#startDate').change(function() {
            if (this.value) {
                $('#endDate').attr('min', this.value);
            }
        });
    });
    </script>
</body>
</html>