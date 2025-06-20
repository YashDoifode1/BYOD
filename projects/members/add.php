<?php
session_start();

require_once __DIR__ . '../../../includes/header.php';
require_once __DIR__ . '../../../includes/auth.php';
require_once __DIR__ . '../../../includes/auth_project.php';
require_once __DIR__ . '../../../includes/config.php';

// Verify user is logged in and has permission
if (!isLoggedIn()) {
    header('Location: ../../login.php');
    exit();
}

$projectId = $_GET['project_id'] ?? 0;
$userId = $_SESSION['user']['id'];

// Only project owners or admins can add members
if (!hasProjectAccess($userId, $projectId, 'owner') && $_SESSION['user']['role'] !== 'admin') {
    header('HTTP/1.0 403 Forbidden');
    die('Only project owners can add members');
}

// Get project details for header
$projectStmt = $pdo->prepare("SELECT name FROM projects WHERE id = ?");
$projectStmt->execute([$projectId]);
$project = $projectStmt->fetch();

// Get available users (not already members)
$availableUsers = $pdo->prepare("
    SELECT u.id, u.username, CONCAT(u.first_name, ' ', u.last_name) as full_name, u.email
    FROM users u
    WHERE u.id NOT IN (
        SELECT user_id FROM project_members WHERE project_id = ?
    )
    AND u.id != ?  -- Don't show current user (already owner)
    ORDER BY u.first_name
");
$availableUsers->execute([$projectId, $userId]);
$users = $availableUsers->fetchAll(PDO::FETCH_ASSOC);

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $memberId = $_POST['user_id'] ?? 0;
    
    try {
        $pdo->beginTransaction();
        
        // Validate
        if (empty($memberId)) {
            throw new Exception('Please select a user');
        }
        
        // Check user exists
        $userCheck = $pdo->prepare("SELECT id FROM users WHERE id = ?");
        $userCheck->execute([$memberId]);
        if (!$userCheck->fetch()) {
            throw new Exception('Selected user does not exist');
        }
        
        // Add as member (default role)
        $pdo->prepare("
            INSERT INTO project_members (project_id, user_id, role)
            VALUES (?, ?, 'member')
        ")->execute([$projectId, $memberId]);
        
        // Log activity
        $pdo->prepare("
            INSERT INTO activity_logs (user_id, project_id, action, description)
            VALUES (?, ?, 'add_member', ?)
        ")->execute([
            $userId,
            $projectId,
            "Added user ID $memberId to project"
        ]);
        
        $pdo->commit();
        $success = 'Member added successfully!';
        
        // Refresh available users
        $availableUsers->execute([$projectId, $userId]);
        $users = $availableUsers->fetchAll(PDO::FETCH_ASSOC);
        
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
            <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-user-plus me-2"></i>
                        Add Team Member
                        <small class="text-muted"><?= htmlspecialchars($project['name'] ?? '') ?></small>
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="../view.php?id=<?= $projectId ?>" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-1"></i> Back to Project
                        </a>
                    </div>
                </div>

                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <?= htmlspecialchars($success) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <?= htmlspecialchars($error) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-users me-2"></i>
                            Available Team Members
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($users)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-user-slash fa-3x text-muted mb-3"></i>
                                <h5>No available users to add</h5>
                                <p class="text-muted">All users are already members of this project</p>
                            </div>
                        <?php else: ?>
                            <form method="POST">
                                <div class="table-responsive">
                                    <table class="table table-hover" id="usersTable">
                                        <thead>
                                            <tr>
                                                <th>Select</th>
                                                <th>Name</th>
                                                <th>Username</th>
                                                <th>Email</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($users as $user): ?>
                                                <tr>
                                                    <td>
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="radio" 
                                                                   name="user_id" id="user_<?= $user['id'] ?>" 
                                                                   value="<?= $user['id'] ?>" required>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <label for="user_<?= $user['id'] ?>">
                                                            <?= htmlspecialchars($user['full_name']) ?>
                                                        </label>
                                                    </td>
                                                    <td><?= htmlspecialchars($user['username']) ?></td>
                                                    <td><?= htmlspecialchars($user['email']) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <div class="text-end mt-3">
                                    <button type="submit" class="btn btn-primary px-4">
                                        <i class="fas fa-user-plus me-2"></i> Add Selected Member
                                    </button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <?php require_once __DIR__ . '../../../includes/footer.php'; ?>

    <style>
        .table-hover tbody tr:hover {
            background-color: rgba(0, 123, 255, 0.05);
            cursor: pointer;
        }
        .form-check-input {
            cursor: pointer;
        }
        label {
            cursor: pointer;
        }
    </style>

    <script>
    $(document).ready(function() {
        // Initialize DataTable
        $('#usersTable').DataTable({
            responsive: true,
            columnDefs: [
                { orderable: false, targets: 0 },
                { responsivePriority: 1, targets: 1 },
                { responsivePriority: 2, targets: -1 }
            ]
        });

        // Make entire row clickable for radio buttons
        $('#usersTable tbody tr').click(function(e) {
            if ($(e.target).is('input:radio')) return;
            $(this).find('input:radio').prop('checked', true);
        });
    });
    </script>
</body>
</html>