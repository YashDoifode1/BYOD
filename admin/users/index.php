<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/header.php';

// Check admin permissions
if ($_SESSION['user_role'] !== 'admin') {
    header("Location: /unauthorized.php");
    exit();
}

// Database connection
require_once __DIR__ . '/../includes/config.php';

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle user deletion
if (isset($_POST['delete_user']) && isset($_POST['user_id'])) {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['flash_message'] = "Invalid CSRF token";
        header("Location: index.php");
        exit();
    }

    try {
        $pdo->beginTransaction();
        
        // Prevent self-deletion
        if ($_POST['user_id'] == $_SESSION['user_id']) {
            throw new Exception("You cannot delete your own account");
        }

        // Delete user from all tables with foreign key constraints
        $tables = [
            'task_assignments', 
            'project_members', 
            'tasks', 
            'projects', 
            'files', 
            'messages', 
            'activity_logs', 
            'sessions'
        ];
        
        foreach ($tables as $table) {
            $pdo->prepare("DELETE FROM $table WHERE user_id = ?")->execute([$_POST['user_id']]);
        }
        
        // Finally delete the user
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$_POST['user_id']]);
        
        // Log activity
        $log_stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, description) VALUES (?, ?, ?)");
        $log_stmt->execute([
            $_SESSION['user_id'],
            'user_delete',
            'Deleted user ID: ' . $_POST['user_id']
        ]);
        
        $pdo->commit();
        $_SESSION['flash_message'] = [
            'type' => 'success',
            'message' => "User deleted successfully"
        ];
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['flash_message'] = [
            'type' => 'danger',
            'message' => "Error: " . $e->getMessage()
        ];
    }
    
    header("Location: index.php");
    exit();
}

// Get filter parameters
$filter_role = isset($_GET['role']) && in_array($_GET['role'], ['admin', 'manager', 'user']) ? $_GET['role'] : null;
$filter_status = isset($_GET['status']) && $_GET['status'] === 'active' ? 'active' : null;
$filter_2fa = isset($_GET['2fa']) && $_GET['2fa'] === 'enabled' ? 'enabled' : null;
$search_query = isset($_GET['search']) ? trim($_GET['search']) : null;

// Build base query
$query = "SELECT * FROM users WHERE 1=1";
$params = [];

// Apply filters
if ($filter_role) {
    $query .= " AND role = ?";
    $params[] = $filter_role;
}

if ($filter_status === 'active') {
    $query .= " AND last_login IS NOT NULL AND last_login > DATE_SUB(NOW(), INTERVAL 30 MINUTE)";
}

if ($filter_2fa === 'enabled') {
    $query .= " AND two_factor_secret IS NOT NULL";
}

if ($search_query) {
    $query .= " AND (username LIKE ? OR email LIKE ? OR first_name LIKE ? OR last_name LIKE ?)";
    $search_param = "%$search_query%";
    $params = array_merge($params, array_fill(0, 4, $search_param));
}

// Get total count
$count_stmt = $pdo->prepare(str_replace('*', 'COUNT(*)', $query));
$count_stmt->execute($params);
$total_users = $count_stmt->fetchColumn();

// Pagination
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;
$total_pages = ceil($total_users / $limit);

// Complete query with pagination
$query .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";

// Get users for current page
$stmt = $pdo->prepare($query);
// Bind parameters, ensuring LIMIT and OFFSET are integers
$param_count = count($params);
for ($i = 0; $i < $param_count; $i++) {
    $stmt->bindValue($i + 1, $params[$i], PDO::PARAM_STR);
}
$stmt->bindValue($param_count + 1, (int)$limit, PDO::PARAM_INT);
$stmt->bindValue($param_count + 2, (int)$offset, PDO::PARAM_INT);
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get user statistics
$stats = [
    'total' => $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
    'admins' => $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn(),
    'managers' => $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'manager'")->fetchColumn(),
    'regular' => $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetchColumn(),
    'active' => $pdo->query("SELECT COUNT(*) FROM users WHERE last_login IS NOT NULL AND last_login > DATE_SUB(NOW(), INTERVAL 30 MINUTE)")->fetchColumn(),
    'with_2fa' => $pdo->query("SELECT COUNT(*) FROM users WHERE two_factor_secret IS NOT NULL")->fetchColumn(),
];

// Data for Pie Chart (User Role Distribution)
$role_data = [
    'labels' => ['Admins', 'Managers', 'Regular Users'],
    'data' => [$stats['admins'], $stats['managers'], $stats['regular']],
    'colors' => ['#dc3545', '#ffc107', '#007bff']
];

// Data for Bar Chart (Active Users Last 7 Days)
$activity_data = [];
$activity_labels = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE last_login IS NOT NULL AND DATE(last_login) = ?");
    $stmt->execute([$date]);
    $count = $stmt->fetchColumn();
    $activity_data[] = $count;
    $activity_labels[] = date('M d', strtotime($date));
}
?>

<!-- Main Content -->
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-users-cog me-2"></i>User Management Dashboard
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="create.php" class="btn btn-sm btn-primary">
            <i class="fas fa-user-plus me-1"></i> Create User
        </a>
        <div class="btn-group ms-2">
            <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#exportModal">
                <i class="fas fa-file-export me-1"></i> Export
            </button>
            <button class="btn btn-sm btn-outline-secondary" id="refreshBtn">
                <i class="fas fa-sync-alt me-1"></i> Refresh
            </button>
        </div>
    </div>
</div>

<!-- Stats Cards -->
<div class="row mb-4">
    <div class="col-xl-2 col-md-4 mb-4">
        <div class="card border-left-primary shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                            Total Users</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $stats['total'] ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-users fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-2 col-md-4 mb-4">
        <div class="card border-left-danger shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                            Admins</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $stats['admins'] ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-user-shield fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-2 col-md-4 mb-4">
        <div class="card border-left-warning shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                            Managers</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $stats['managers'] ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-user-tie fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-2 col-md-4 mb-4">
        <div class="card border-left-success shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                            Active Now</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $stats['active'] ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-user-check fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-2 col-md-4 mb-4">
        <div class="card border-left-info shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                            2FA Enabled</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $stats['with_2fa'] ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-lock fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-2 col-md-4 mb-4">
        <div class="card border-left-secondary shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-secondary text-uppercase mb-1">
                            Regular Users</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $stats['regular'] ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-user fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Charts Card -->
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">
            <i class="fas fa-chart-pie me-1"></i> User Analytics
        </h6>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-lg-6 mb-4">
                <h6 class="text-center">User Role Distribution</h6>
                <canvas id="rolePieChart" height="200"></canvas>
            </div>
            <div class="col-lg-6 mb-4">
                <h6 class="text-center">Active Users (Last 7 Days)</h6>
                <canvas id="activityBarChart" height="200"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Filters Card -->
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">
            <i class="fas fa-filter me-1"></i> Filters & Search
        </h6>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label for="role" class="form-label">Role</label>
                <select class="form-select" id="role" name="role">
                    <option value="">All Roles</option>
                    <option value="admin" <?= $filter_role === 'admin' ? 'selected' : '' ?>>Admin</option>
                    <option value="manager" <?= $filter_role === 'manager' ? 'selected' : '' ?>>Manager</option>
                    <option value="user" <?= $filter_role === 'user' ? 'selected' : '' ?>>Regular User</option>
                </select>
            </div>
            <div class="col-md-2">
                <label for="status" class="form-label">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="">All Statuses</option>
                    <option value="active" <?= $filter_status === 'active' ? 'selected' : '' ?>>Active Now</option>
                </select>
            </div>
            <div class="col-md-2">
                <label for="2fa" class="form-label">2FA</label>
                <select class="form-select" id="2fa" name="2fa">
                    <option value="">All Users</option>
                    <option value="enabled" <?= $filter_2fa === 'enabled' ? 'selected' : '' ?>>Enabled</option>
                </select>
            </div>
            <div class="col-md-5">
                <label for="search" class="form-label">Search</label>
                <div class="input-group">
                    <input type="text" class="form-control" id="search" name="search" 
                           placeholder="Search by name, username or email" value="<?= htmlspecialchars($search_query) ?>">
                    <button class="btn btn-primary" type="submit">
                        <i class="fas fa-search"></i>
                    </button>
                    <a href="index.php" class="btn btn-outline-secondary">
                        <i class="fas fa-undo"></i>
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Users Table Card -->
<div class="card shadow mb-4">
    <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-primary">
            <i class="fas fa-table me-1"></i> User Records
            <span class="badge bg-primary ms-1"><?= $total_users ?> found</span>
        </h6>
        <div>
            <span class="me-2"><?= ($offset + 1) ?>-<?= min($offset + $limit, $total_users) ?> of <?= $total_users ?></span>
            <div class="btn-group">
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => max(1, $page - 1)])) ?>" 
                   class="btn btn-sm btn-outline-secondary <?= $page <= 1 ? 'disabled' : '' ?>">
                    <i class="fas fa-chevron-left"></i>
                </a>
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => min($total_pages, $page + 1)])) ?>" 
                   class="btn btn-sm btn-outline-secondary <?= $page >= $total_pages ? 'disabled' : '' ?>">
                    <i class="fas fa-chevron-right"></i>
                </a>
            </div>
        </div>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table id="usersTable" class="table table-striped table-hover" style="width:100%">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>User</th>
                        <th>Contact</th>
                        <th>Role</th>
                        <th>Last Activity</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?= $user['id'] ?></td>
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="flex-shrink-0">
                                    <div class="avatar-sm bg-<?= 
                                        $user['role'] === 'admin' ? 'danger' : 
                                        ($user['role'] === 'manager' ? 'warning' : 'primary')
                                    ?> text-white rounded-circle d-flex align-items-center justify-content-center">
                                        <?= strtoupper(substr($user['username'], 0, 1)) ?>
                                    </div>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <h6 class="mb-0"><?= htmlspecialchars($user['username']) ?></h6>
                                    <small class="text-muted"><?= htmlspecialchars(trim($user['first_name'] . ' ' . $user['last_name'])) ?: 'No name' ?></small>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div><?= htmlspecialchars($user['email']) ?></div>
                            <?php if ($user['two_factor_secret']): ?>
                                <span class="badge bg-info">2FA Enabled</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge bg-<?= 
                                $user['role'] === 'admin' ? 'danger' : 
                                ($user['role'] === 'manager' ? 'warning' : 'primary')
                            ?>">
                                <?= ucfirst($user['role']) ?>
                            </span>
                        </td>
                        <td data-order="<?= $user['last_login'] ? strtotime($user['last_login']) : 0 ?>">
                            <?php if ($user['last_login']): ?>
                                <div><?= date('M j, Y', strtotime($user['last_login'])) ?></div>
                                <small class="text-muted"><?= date('g:i a', strtotime($user['last_login'])) ?></small>
                            <?php else: ?>
                                <span class="text-muted">Never</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge bg-<?= 
                                $user['last_login'] && strtotime($user['last_login']) > strtotime('-30 minutes') ? 'success' : 'secondary'
                            ?>">
                                <?= 
                                    $user['last_login'] && strtotime($user['last_login']) > strtotime('-30 minutes') ? 'Online' : 'Offline'
                                ?>
                            </span>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm" role="group">
                                <a href="view.php?id=<?= $user['id'] ?>" class="btn btn-info" title="View">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="edit.php?id=<?= $user['id'] ?>" class="btn btn-primary" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this user?\nThis action cannot be undone.');">
                                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                    <button type="submit" name="delete_user" class="btn btn-danger" title="Delete" <?= $user['id'] == $_SESSION['user_id'] ? 'disabled' : '' ?>>
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <nav aria-label="Page navigation">
            <ul class="pagination justify-content-center mt-4">
                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => 1])) ?>" aria-label="First">
                        <span aria-hidden="true">««</span>
                    </a>
                </li>
                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => max(1, $page - 1)])) ?>" aria-label="Previous">
                        <span aria-hidden="true">«</span>
                    </a>
                </li>
                
                <?php 
                // Show limited pagination links
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                
                if ($start_page > 1) {
                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                }
                
                for ($i = $start_page; $i <= $end_page; $i++): ?>
                    <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
                    </li>
                <?php endfor; 
                
                if ($end_page < $total_pages) {
                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                }
                ?>
                
                <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => min($total_pages, $page + 1)])) ?>" aria-label="Next">
                        <span aria-hidden="true">»</span>
                    </a>
                </li>
                <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $total_pages])) ?>" aria-label="Last">
                        <span aria-hidden="true">»»</span>
                    </a>
                </li>
            </ul>
        </nav>
    </div>
</div>

<!-- Export Modal -->
<div class="modal fade" id="exportModal" tabindex="-1" aria-labelledby="exportModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exportModalLabel">Export Users</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="exportForm" method="POST" action="export.php">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="exportFormat" class="form-label">Format</label>
                            <select class="form-select" id="exportFormat" name="format" required>
                                <option value="csv">CSV (Comma Separated Values)</option>
                                <option value="excel">Excel (XLSX)</option>
                                <option value="pdf">PDF Document</option>
                                <option value="json">JSON</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="exportScope" class="form-label">Export Scope</label>
                            <select class="form-select" id="exportScope" name="scope">
                                <option value="current">Current Page (<?= count($users) ?> records)</option>
                                <option value="all">All Filtered Results (<?= $total_users ?> records)</option>
                                <option value="selected">Selected Records Only</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Columns to Export</label>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="exportId" name="columns[]" value="id" checked>
                                    <label class="form-check-label" for="exportId">ID</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="exportUsername" name="columns[]" value="username" checked>
                                    <label class="form-check-label" for="exportUsername">Username</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="exportName" name="columns[]" value="name" checked>
                                    <label class="form-check-label" for="exportName">Full Name</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="exportEmail" name="columns[]" value="email" checked>
                                    <label class="form-check-label" for="exportEmail">Email</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="exportRole" name="columns[]" value="role" checked>
                                    <label class="form-check-label" for="exportRole">Role</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="exportStatus" name="columns[]" value="status" checked>
                                    <label class="form-check-label" for="exportStatus">Status</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="exportLastLogin" name="columns[]" value="last_login" checked>
                                    <label class="form-check-label" for="exportLastLogin">Last Login</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="export2FA" name="columns[]" value="2fa" checked>
                                    <label class="form-check-label" for="export2FA">2FA Status</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="exportCreated" name="columns[]" value="created_at" checked>
                                    <label class="form-check-label" for="exportCreated">Created At</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <input type="hidden" name="role" value="<?= $filter_role ?>">
                    <input type="hidden" name="status" value="<?= $filter_status ?>">
                    <input type="hidden" name="2fa" value="<?= $filter_2fa ?>">
                    <input type="hidden" name="search" value="<?= htmlspecialchars($search_query) ?>">
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="exportForm" class="btn btn-primary">Export Data</button>
            </div>
        </div>
    </div>
</div>

<!-- User Status Modal -->
<div class="modal fade" id="userStatusModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">User Status Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="userStatusContent">
                Loading user status...
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- CSS -->
<style>
.avatar-sm {
    width: 36px;
    height: 36px;
    font-size: 1.1rem;
    font-weight: bold;
}

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

/* Custom scrollbar for table */
.table-responsive::-webkit-scrollbar {
    height: 8px;
}
.table-responsive::-webkit-scrollbar-track {
    background: #f1f1f1;
}
.table-responsive::-webkit-scrollbar-thumb {
    background: #888;
    border-radius: 4px;
}
.table-responsive::-webkit-scrollbar-thumb:hover {
    background: #555;
}

/* Chart styling */
.chart-container {
    position: relative;
    margin: auto;
    height: 100px;
    width: 50;
}

canvas {
    max-width: 100%;
}
</style>

<!-- JavaScript Libraries -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/select/1.3.4/js/dataTables.select.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.2.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<script>
$(document).ready(function() {
    // Initialize DataTable with enhanced features
    var table = $('#usersTable').DataTable({
        responsive: true,
        dom: '<"top"f>rt<"bottom"lip><"clear">',
        paging: false, // Disable DataTables pagination since we have custom pagination
        info: false,   // Disable showing entries info
        ordering: true,
        order: [[0, 'desc']],
        columnDefs: [
            { orderable: false, targets: [6] }, // Make actions column non-orderable
            { visible: false, targets: [0] }    // Hide ID column by default
        ],
        language: {
            search: "_INPUT_",
            searchPlaceholder: "Search users...",
        },
        select: {
            style: 'multi',
            selector: 'td:not(:last-child)'
        }
    });
    
    // Refresh button
    $('#refreshBtn').click(function() {
        location.reload();
    });
    
    // View user status details
    $('.view-status').click(function() {
        var userId = $(this).data('id');
        $.get('user_status.php?id=' + userId, function(data) {
            $('#userStatusContent').html(data);
        });
    });
    
    // Export modal submit
    $('#exportSubmit').click(function() {
        if ($('#exportScope').val() === 'selected') {
            var selectedIds = table.rows({selected: true}).data().pluck(0).toArray();
            if (selectedIds.length === 0) {
                alert('Please select at least one user to export');
                return false;
            }
            $('#exportForm').append('<input type="hidden" name="selected_ids" value="' + selectedIds.join(',') + '">');
        }
        return true;
    });

    // Pie Chart for User Role Distribution
    const rolePieChart = new Chart(document.getElementById('rolePieChart'), {
        type: 'pie',
        data: {
            labels: <?= json_encode($role_data['labels']) ?>,
            datasets: [{
                data: <?= json_encode($role_data['data']) ?>,
                backgroundColor: <?= json_encode($role_data['colors']) ?>,
                hoverOffset: 20
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'bottom'
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            let label = context.label || '';
                            let value = context.parsed || 0;
                            let total = context.dataset.data.reduce((a, b) => a + b, 0);
                            let percentage = ((value / total) * 100).toFixed(1);
                            return `${label}: ${value} (${percentage}%)`;
                        }
                    }
                }
            }
        }
    });

    // Bar Chart for Active Users
    const activityBarChart = new Chart(document.getElementById('activityBarChart'), {
        type: 'bar',
        data: {
            labels: <?= json_encode($activity_labels) ?>,
            datasets: [{
                label: 'Active Users',
                data: <?= json_encode($activity_data) ?>,
                backgroundColor: '#007bff',
                borderColor: '#0056b3',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Number of Users'
                    }
                },
                x: {
                    title: {
                        display: true,
                        text: 'Date'
                    }
                }
            },
            plugins: {
                legend: {
                    display: false
                }
            }
        }
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>