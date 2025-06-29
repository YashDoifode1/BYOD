<?php
session_start();
require_once 'includes/functions.php';

// Database configuration
define('DB_DSN', 'mysql:host=localhost;dbname=byod');
define('DB_USER', 'root');
define('DB_PASSWORD', '');

// Initialize database connection
function get_db_connection() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $pdo = new PDO(DB_DSN, DB_USER, DB_PASSWORD);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            die("Database connection error. Please try again later.");
        }
    }
    
    return $pdo;
}

// Get appropriate icon for file type
// function get_file_icon($mime_type) {
//     $icons = [
//         'image/' => 'fa-file-image',
//         'application/pdf' => 'fa-file-pdf',
//         // ... rest of your icon mappings
//     ];
    
//     foreach ($icons as $prefix => $icon) {
//         if (strpos($mime_type, $prefix) === 0) {
//             return $icon;
//         }
//     }
    
//     return 'fa-file';
// }

// Check if user has access to a project (with restriction check)
// function has_project_access($user_id, $project_id, $action_type = 'view') {
//     $pdo = get_db_connection();
    
//     // First check project restrictions
//     $restriction = check_project_restriction($project_id);
//     if ($restriction['status'] === 'frozen') {
//         return false;
//     }
//     if ($restriction['status'] === 'read_only' && $action_type === 'modify') {
//         return false;
//     }
    
//     // Then check if user is member
//     $stmt = $pdo->prepare("
//         SELECT COUNT(*) 
//         FROM project_members 
//         WHERE user_id = :user_id AND project_id = :project_id
//     ");
//     $stmt->execute([':user_id' => $user_id, ':project_id' => $project_id]);
    
//     return $stmt->fetchColumn() > 0;
// }

// All other functions should be similarly updated to use get_db_connection()
// instead of relying on global $pdo

// Example of updated function:
// function check_project_restriction($project_id) {
//     $pdo = get_db_connection();
    
//     $stmt = $pdo->prepare("SELECT restriction_status FROM projects WHERE id = ?");
//     $stmt->execute([$project_id]);
//     $status = $stmt->fetchColumn();
    
//     return [
//         'status' => $status ?: 'none',
//         'message' => get_restriction_message($status)
//     ];
// }

// require_once 'includes/auth_check.php'; // Your authentication system

// Only allow admins to access this page
if ($_SESSION['role'] !== 'admin') {
    header('Location: index.php');
    exit();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $project_id = intval($_POST['project_id']);
    $action = $_POST['action'];
    $reason = trim($_POST['reason']);
    $expires = !empty($_POST['expires']) ? $_POST['expires'] : null;
    
    if ($action === 'restrict') {
        $type = $_POST['restriction_type'];
        if (apply_project_restriction($project_id, $type, $_SESSION['user_id'], $reason, $expires)) {
            $message = "Project restriction applied successfully";
        } else {
            $error = "Failed to apply restriction";
        }
    } elseif ($action === 'unrestrict') {
        if (remove_project_restriction($project_id, $_SESSION['user_id'])) {
            $message = "Project restriction removed successfully";
        } else {
            $error = "Failed to remove restriction";
        }
    }
}

// Get all projects
global $pdo;
$projects = $pdo->query("SELECT id, name, restriction_status FROM projects ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Get restriction history
$restriction_history = $pdo->query("
    SELECT pr.*, u.username, p.name as project_name 
    FROM project_restrictions pr
    JOIN users u ON pr.restricted_by = u.id
    JOIN projects p ON pr.project_id = p.id
    ORDER BY pr.created_at DESC
    LIMIT 50
")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Project Restriction Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .restriction-badge {
            font-size: 0.8rem;
        }
        .history-item {
            border-left: 4px solid #dee2e6;
            padding-left: 1rem;
            margin-bottom: 1rem;
        }
        .history-item.frozen {
            border-left-color: #dc3545;
        }
        .history-item.read_only {
            border-left-color: #ffc107;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container mt-4">
        <div class="row">
            <div class="col-md-8">
                <h2><i class="bi bi-lock"></i> Project Restriction Management</h2>
                
                <?php if (isset($message)): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
                <?php endif; ?>
                
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Restrict a Project</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="restrict">
                            
                            <div class="mb-3">
                                <label for="project_id" class="form-label">Select Project</label>
                                <select class="form-select" id="project_id" name="project_id" required>
                                    <option value="">-- Select Project --</option>
                                    <?php foreach ($projects as $project): ?>
                                        <option value="<?= $project['id'] ?>">
                                            <?= htmlspecialchars($project['name']) ?>
                                            <?php if ($project['restriction_status'] !== 'none'): ?>
                                                (Current: <?= ucfirst(str_replace('_', ' ', $project['restriction_status'])) ?>)
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="restriction_type" class="form-label">Restriction Type</label>
                                <select class="form-select" id="restriction_type" name="restriction_type" required>
                                    <option value="read_only">Read Only (allow viewing but no changes)</option>
                                    <option value="frozen">Frozen (complete lock, no access)</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="reason" class="form-label">Reason (optional)</label>
                                <textarea class="form-control" id="reason" name="reason" rows="2"></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label for="expires" class="form-label">Expiration Date (optional)</label>
                                <input type="datetime-local" class="form-control" id="expires" name="expires">
                            </div>
                            
                            <button type="submit" class="btn btn-danger">
                                <i class="bi bi-lock-fill"></i> Apply Restriction
                            </button>
                        </form>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Remove Restrictions</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="unrestrict">
                            
                            <div class="mb-3">
                                <label for="unrestrict_project_id" class="form-label">Select Project</label>
                                <select class="form-select" id="unrestrict_project_id" name="project_id" required>
                                    <option value="">-- Select Project --</option>
                                    <?php foreach ($projects as $project): ?>
                                        <?php if ($project['restriction_status'] !== 'none'): ?>
                                            <option value="<?= $project['id'] ?>">
                                                <?= htmlspecialchars($project['name']) ?>
                                                (<?= ucfirst(str_replace('_', ' ', $project['restriction_status'])) ?>)
                                            </option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <button type="submit" class="btn btn-success">
                                <i class="bi bi-unlock-fill"></i> Remove Restrictions
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Restriction History</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($restriction_history)): ?>
                            <p class="text-muted">No restriction history found</p>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($restriction_history as $history): ?>
                                    <div class="history-item <?= $history['restriction_type'] ?>">
                                        <div class="d-flex justify-content-between">
                                            <strong><?= htmlspecialchars($history['project_name']) ?></strong>
                                            <span class="badge bg-<?= $history['restriction_type'] === 'frozen' ? 'danger' : 'warning' ?>">
                                                <?= ucfirst(str_replace('_', ' ', $history['restriction_type'])) ?>
                                            </span>
                                        </div>
                                        <small class="text-muted">
                                            By <?= htmlspecialchars($history['username']) ?> 
                                            on <?= date('M j, Y g:i a', strtotime($history['created_at'])) ?>
                                        </small>
                                        <?php if ($history['reason']): ?>
                                            <div class="mt-1"><?= htmlspecialchars($history['reason']) ?></div>
                                        <?php endif; ?>
                                        <?php if ($history['expires_at']): ?>
                                            <small class="text-muted">
                                                Expires: <?= date('M j, Y g:i a', strtotime($history['expires_at'])) ?>
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Update restriction type dropdown based on selected project
        document.getElementById('project_id').addEventListener('change', function() {
            const projectId = this.value;
            const restrictionTypeSelect = document.getElementById('restriction_type');
            
            if (!projectId) return;
            
            // In a real app, you might fetch current status via AJAX
            const projects = <?= json_encode(array_column($projects, 'restriction_status', 'id')) ?>;
            const currentStatus = projects[projectId];
            
            if (currentStatus === 'frozen') {
                restrictionTypeSelect.value = 'frozen';
            } else if (currentStatus === 'read_only') {
                restrictionTypeSelect.value = 'read_only';
            }
        });
    </script>
</body>
</html>