<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/header.php';

// Check admin permissions
if ($_SESSION['user_role'] !== 'admin') {
    header("Location: /unauthorized.php");
    exit();
}

// Database connection
require_once __DIR__ . '/../../includes/config.php';

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Get user ID from URL
$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Fetch user data with prepared statement
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    $_SESSION['flash_message'] = [
        'type' => 'danger',
        'message' => "User not found"
    ];
    header("Location: index.php");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_user'])) {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("CSRF token validation failed");
    }

    // Get form data
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $role = $_POST['role'];
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    // Basic validation
    $errors = [];
    if (empty($username)) $errors[] = "Username is required";
    if (empty($email)) $errors[] = "Email is required";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Invalid email format";
    if (strlen($username) > 50) $errors[] = "Username must be 50 characters or less";
    if (strlen($email) > 100) $errors[] = "Email must be 100 characters or less";

    // Check for duplicate username/email (excluding current user)
    $stmt = $pdo->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
    $stmt->execute([$username, $email, $user_id]);
    if ($stmt->fetch()) {
        $errors[] = "Username or email already exists";
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            // Update user
            $stmt = $pdo->prepare("UPDATE users SET 
                username = ?, 
                email = ?, 
                first_name = ?, 
                last_name = ?, 
                role = ?, 
                is_active = ?,
                updated_at = NOW() 
                WHERE id = ?");
            $stmt->execute([$username, $email, $first_name, $last_name, $role, $is_active, $user_id]);

            // Log activity
            $log_stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, description) VALUES (?, ?, ?)");
            $log_stmt->execute([
                $_SESSION['user_id'],
                'user_update',
                'Updated user ID: ' . $user_id . ' (' . $username . ')'
            ]);

            $pdo->commit();
            
            $_SESSION['flash_message'] = [
                'type' => 'success',
                'message' => "User updated successfully"
            ];
            header("Location: index.php");
            exit();
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
}
?>

<div class="container-fluid">
    <div class="row">
        <main class="col-md-12 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">
                    <i class="fas fa-user-edit me-2"></i>Edit User
                    <small class="text-muted"><?= htmlspecialchars($user['username']) ?></small>
                </h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group">
                        <a href="index.php" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-arrow-left me-1"></i> Back to Users
                        </a>
                        <a href="view.php?id=<?= $user['id'] ?>" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-eye me-1"></i> View
                        </a>
                    </div>
                </div>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <h5 class="alert-heading"><i class="fas fa-exclamation-triangle me-2"></i>Error</h5>
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?= htmlspecialchars($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="row">
                <div class="col-lg-8">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <i class="fas fa-user-cog me-1"></i> User Information
                            </h6>
                        </div>
                        <div class="card-body">
                            <form method="POST" id="editUserForm">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="username" class="form-label">Username *</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-user"></i></span>
                                            <input type="text" class="form-control" id="username" name="username" 
                                                value="<?= htmlspecialchars($user['username']) ?>" required
                                                pattern="[a-zA-Z0-9_]+" title="Only letters, numbers and underscores">
                                        </div>
                                        <div class="form-text">Letters, numbers and underscores only</div>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="email" class="form-label">Email *</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                            <input type="email" class="form-control" id="email" name="email" 
                                                value="<?= htmlspecialchars($user['email']) ?>" required>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="first_name" class="form-label">First Name</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-signature"></i></span>
                                            <input type="text" class="form-control" id="first_name" name="first_name" 
                                                value="<?= htmlspecialchars($user['first_name']) ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="last_name" class="form-label">Last Name</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-signature"></i></span>
                                            <input type="text" class="form-control" id="last_name" name="last_name" 
                                                value="<?= htmlspecialchars($user['last_name']) ?>">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="role" class="form-label">Role *</label>
                                        <select class="form-select" id="role" name="role" required>
                                            <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                                            <option value="manager" <?= $user['role'] === 'manager' ? 'selected' : '' ?>>Manager</option>
                                            <option value="user" <?= $user['role'] === 'user' ? 'selected' : '' ?>>Regular User</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Status</label>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="is_active" name="is_active" 
                                                <?= (isset($user['is_active']) && $user['is_active']) ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="is_active">Active User</label>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="d-flex justify-content-between mt-4">
                                    <div>
                                        <button type="submit" name="update_user" class="btn btn-primary me-2">
                                            <i class="fas fa-save me-1"></i> Save Changes
                                        </button>
                                        <a href="index.php" class="btn btn-outline-secondary">
                                            <i class="fas fa-times me-1"></i> Cancel
                                        </a>
                                    </div>
                                    <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                    <div>
                                        <a href="reset_password.php?id=<?= $user['id'] ?>" class="btn btn-warning me-2">
                                            <i class="fas fa-key me-1"></i> Reset Password
                                        </a>
                                        <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal">
                                            <i class="fas fa-trash-alt me-1"></i> Delete User
                                        </button>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <i class="fas fa-info-circle me-1"></i> User Details
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <h6 class="text-muted">Account Created</h6>
                                <p><?= date('F j, Y \a\t g:i a', strtotime($user['created_at'])) ?></p>
                            </div>
                            
                            <div class="mb-3">
                                <h6 class="text-muted">Last Updated</h6>
                                <p><?= $user['updated_at'] ? date('F j, Y \a\t g:i a', strtotime($user['updated_at'])) : 'Never' ?></p>
                            </div>
                            
                            <div class="mb-3">
                                <h6 class="text-muted">Last Login</h6>
                                <p><?= $user['last_login'] ? date('F j, Y \a\t g:i a', strtotime($user['last_login'])) : 'Never' ?></p>
                            </div>
                            
                            <div class="mb-3">
                                <h6 class="text-muted">Two-Factor Authentication</h6>
                                <p>
                                    <span class="badge bg-<?= $user['two_factor_secret'] ? 'success' : 'secondary' ?>">
                                        <?= $user['two_factor_secret'] ? 'Enabled' : 'Disabled' ?>
                                    </span>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deleteModalLabel"><i class="fas fa-exclamation-triangle me-2"></i>Confirm Deletion</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this user account?</p>
                <p class="fw-bold">This action cannot be undone and will permanently remove:</p>
                <ul>
                    <li>User profile</li>
                    <li>All associated projects and tasks</li>
                    <li>Any uploaded files</li>
                    <li>All activity history</li>
                </ul>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form method="POST" action="delete.php" style="display:inline;">
                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <button type="submit" name="delete_user" class="btn btn-danger">
                        <i class="fas fa-trash-alt me-1"></i> Delete Permanently
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- Custom Scripts -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Form validation
    const form = document.getElementById('editUserForm');
    form.addEventListener('submit', function(event) {
        let valid = true;
        
        // Validate username
        const username = document.getElementById('username');
        if (!username.value.trim()) {
            valid = false;
            username.classList.add('is-invalid');
        } else {
            username.classList.remove('is-invalid');
        }
        
        // Validate email
        const email = document.getElementById('email');
        if (!email.value.trim() || !email.validity.valid) {
            valid = false;
            email.classList.add('is-invalid');
        } else {
            email.classList.remove('is-invalid');
        }
        
        if (!valid) {
            event.preventDefault();
            event.stopPropagation();
        }
    }, false);
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>