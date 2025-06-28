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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
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
            color: #4b5563;
        }
        .timeline:before {
            content: '';
            position: absolute;
            left: 15px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #e5e7eb;
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
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .table-responsive::-webkit-scrollbar {
            height: 8px;
        }
        .table-responsive::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }
        .table-responsive::-webkit-scrollbar-thumb {
            background: #6b7280;
            border-radius: 4px;
        }
        .table-responsive::-webkit-scrollbar-thumb:hover {
            background: #4b5563;
        }
        .fade-in {
            animation: fadeIn 0.3s ease-in;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body class="bg-gray-100 font-sans">
    <div class="container mx-auto px-4 py-8">
        <!-- Header -->
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold text-gray-800 flex items-center">
                <i class="fas fa-users-cog mr-2"></i> User Management Dashboard
            </h1>
            <div class="flex space-x-2">
                <a href="create.php" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition">
                    <i class="fas fa-user-plus mr-1"></i> Create User
                </a>
                <button class="bg-gray-200 text-gray-800 px-4 py-2 rounded-md hover:bg-gray-300 transition" data-bs-toggle="modal" data-bs-target="#exportModal">
                    <i class="fas fa-file-export mr-1"></i> Export
                </button>
                <button id="refreshBtn" class="bg-gray-200 text-gray-800 px-4 py-2 rounded-md hover:bg-gray-300 transition">
                    <i class="fas fa-sync-alt mr-1"></i> Refresh
                </button>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-4 mb-6">
            <div class="bg-white rounded-lg shadow-md p-4 fade-in">
                <div class="flex items-center">
                    <div class="flex-1">
                        <p class="text-sm font-semibold text-blue-600 uppercase">Total Users</p>
                        <p class="text-2xl font-bold text-gray-800"><?= $stats['total'] ?></p>
                    </div>
                    <i class="fas fa-users text-3xl text-gray-300"></i>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow-md p-4 fade-in">
                <div class="flex items-center">
                    <div class="flex-1">
                        <p class="text-sm font-semibold text-red-600 uppercase">Admins</p>
                        <p class="text-2xl font-bold text-gray-800"><?= $stats['admins'] ?></p>
                    </div>
                    <i class="fas fa-user-shield text-3xl text-gray-300"></i>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow-md p-4 fade-in">
                <div class="flex items-center">
                    <div class="flex-1">
                        <p class="text-sm font-semibold text-yellow-600 uppercase">Managers</p>
                        <p class="text-2xl font-bold text-gray-800"><?= $stats['managers'] ?></p>
                    </div>
                    <i class="fas fa-user-tie text-3xl text-gray-300"></i>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow-md p-4 fade-in">
                <div class="flex items-center">
                    <div class="flex-1">
                        <p class="text-sm font-semibold text-green-600 uppercase">Active Now</p>
                        <p class="text-2xl font-bold text-gray-800"><?= $stats['active'] ?></p>
                    </div>
                    <i class="fas fa-user-check text-3xl text-gray-300"></i>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow-md p-4 fade-in">
                <div class="flex items-center">
                    <div class="flex-1">
                        <p class="text-sm font-semibold text-teal-600 uppercase">2FA Enabled</p>
                        <p class="text-2xl font-bold text-gray-800"><?= $stats['with_2fa'] ?></p>
                    </div>
                    <i class="fas fa-lock text-3xl text-gray-300"></i>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow-md p-4 fade-in">
                <div class="flex items-center">
                    <div class="flex-1">
                        <p class="text-sm font-semibold text-gray-600 uppercase">Regular Users</p>
                        <p class="text-2xl font-bold text-gray-800"><?= $stats['regular'] ?></p>
                    </div>
                    <i class="fas fa-user text-3xl text-gray-300"></i>
                </div>
            </div>
        </div>

        <!-- Filters Card -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4"><i class="fas fa-filter mr-2"></i>Filters & Search</h2>
            <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label for="role" class="block text-sm font-medium text-gray-700">Role</label>
                    <select class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50" id="role" name="role">
                        <option value="">All Roles</option>
                        <option value="admin" <?= $filter_role === 'admin' ? 'selected' : '' ?>>Admin</option>
                        <option value="manager" <?= $filter_role === 'manager' ? 'selected' : '' ?> Manager 
                        <option value="user" <?= $filter_role === 'user' ? 'selected' : '' ?>>Regular User</option>
                    </select>
                </div>
                <div>
                    <label for="status" class="block text-sm font-medium text-gray-700">Status</label>
                    <select class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50" id="status" name="status">
                        <option value="">All Statuses</option>
                        <option value="active" <?= $filter_status === 'active' ? 'selected' : '' ?>>Active Now</option>
                    </select>
                </div>
                <div>
                    <label for="2fa" class="block text-sm font-medium text-gray-700">2FA</label>
                    <select class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50" id="2fa" name="2fa">
                        <option value="">All Users</option>
                        <option value="enabled" <?= $filter_2fa === 'enabled' ? 'selected' : '' ?>>Enabled</option>
                    </select>
                </div>
                <div>
                    <label for="search" class="block text-sm font-medium text-gray-700">Search</label>
                    <div class="flex space-x-2">
                        <input type="text" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50" id="search" name="search" placeholder="Search by name, username or email" value="<?= htmlspecialchars($search_query) ?>">
                        <button class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition" type="submit">
                            <i class="fas fa-search"></i>
                        </button>
                        <a href="index.php" class="bg-gray-200 text-gray-800 px-4 py-2 rounded-md hover:bg-gray-300 transition">
                            <i class="fas fa-undo"></i>
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Users Table Card -->
        <div class="bg-white rounded-lg shadow-md">
            <div class="p-6 flex justify-between items-center">
                <h2 class="text-lg font-semibold text-gray-800">
                    <i class="fas fa-table mr-2"></i> User Records
                    <span class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800"><?= $total_users ?> found</span>
                </h2>
                <div class="flex items-center space-x-2">
                    <span class="text-sm text-gray-600"><?= ($offset + 1) ?>-<?= min($offset + $limit, $total_users) ?> of <?= $total_users ?></span>
                    <div class="flex space-x-1">
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => max(1, $page - 1)])) ?>" 
                           class="bg-gray-200 text-gray-800 px-2 py-1 rounded-md hover:bg-gray-300 transition <?= $page <= 1 ? 'opacity-50 cursor-not-allowed' : '' ?>">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => min($total_pages, $page + 1)])) ?>" 
                           class="bg-gray-200 text-gray-800 px-2 py-1 rounded-md hover:bg-gray-300 transition <?= $page >= $total_pages ? 'opacity-50 cursor-not-allowed' : '' ?>">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    </div>
                </div>
            </div>
            <div class="p-6">
                <div class="overflow-x-auto">
                    <table id="usersTable" class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contact</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Activity</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($users as $user): ?>
                            <tr class="hover:bg-gray-50 transition">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= $user['id'] ?></td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="avatar-sm bg-<?= 
                                            $user['role'] === 'admin' ? 'red-500' : 
                                            ($user['role'] === 'manager' ? 'yellow-500' : 'blue-500')
                                        ?> text-white rounded-full flex items-center justify-center">
                                            <?= strtoupper(substr($user['username'], 0, 1)) ?>
                                        </div>
                                        <div class="ml-3">
                                            <p class="text-sm font-medium text-gray-900"><?= htmlspecialchars($user['username']) ?></p>
                                            <p class="text-xs text-gray-500"><?= htmlspecialchars(trim($user['first_name'] . ' ' . $user['last_name'])) ?: 'No name' ?></p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <p class="text-sm text-gray-900"><?= htmlspecialchars($user['email']) ?></p>
                                    <?php if ($user['two_factor_secret']): ?>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-teal-100 text-teal-800">2FA Enabled</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-<?= 
                                        $user['role'] === 'admin' ? 'red-100 text-red-800' : 
                                        ($user['role'] === 'manager' ? 'yellow-100 text-yellow-800' : 'blue-100 text-blue-800')
                                    ?>">
                                        <?= ucfirst($user['role']) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap" data-order="<?= $user['last_login'] ? strtotime($user['last_login']) : 0 ?>">
                                    <?php if ($user['last_login']): ?>
                                        <p class="text-sm text-gray-900"><?= date('M j, Y', strtotime($user['last_login'])) ?></p>
                                        <p class="text-xs text-gray-500"><?= date('g:i a', strtotime($user['last_login'])) ?></p>
                                    <?php else: ?>
                                        <span class="text-sm text-gray-500">Never</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-<?= 
                                        $user['last_login'] && strtotime($user['last_login']) > strtotime('-30 minutes') ? 'green-100 text-green-800' : 'gray-100 text-gray-800'
                                    ?>">
                                        <?= 
                                            $user['last_login'] && strtotime($user['last_login']) > strtotime('-30 minutes') ? 'Online' : 'Offline'
                                        ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex space-x-2">
                                        <a href="view.php?id=<?= $user['id'] ?>" class="bg-teal-600 text-white px-3 py-1 rounded-md hover:bg-teal-700 transition" title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="edit.php?id=<?= $user['id'] ?>" class="bg-blue-600 text-white px-3 py-1 rounded-md hover:bg-blue-700 transition" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <form method="POST" onsubmit="return confirm('Are you sure you want to delete this user?\nThis action cannot be undone.');">
                                            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                            <button type="submit" name="delete_user" class="bg-red-600 text-white px-3 py-1 rounded-md hover:bg-red-700 transition" title="Delete" <?= $user['id'] == $_SESSION['user_id'] ? 'disabled' : '' ?>>
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
                <nav aria-label="Page navigation" class="mt-6">
                    <ul class="flex justify-center space-x-2">
                        <li>
                            <a class="px-3 py-2 rounded-md <?= $page <= 1 ? 'bg-gray-200 text-gray-500 cursor-not-allowed' : 'bg-blue-600 text-white hover:bg-blue-700' ?>" 
                               href="?<?= http_build_query(array_merge($_GET, ['page' => 1])) ?>" 
                               aria-label="First">
                                <span aria-hidden="true">««</span>
                            </a>
                        </li>
                        <li>
                            <a class="px-3 py-2 rounded-md <?= $page <= 1 ? 'bg-gray-200 text-gray-500 cursor-not-allowed' : 'bg-blue-600 text-white hover:bg-blue-700' ?>" 
                               href="?<?= http_build_query(array_merge($_GET, ['page' => max(1, $page - 1)])) ?>" 
                               aria-label="Previous">
                                <span aria-hidden="true">«</span>
                            </a>
                        </li>
                        <?php 
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $page + 2);
                        
                        if ($start_page > 1) {
                            echo '<li><span class="px-3 py-2 rounded-md text-gray-500">...</span></li>';
                        }
                        
                        for ($i = $start_page; $i <= $end_page; $i++): ?>
                            <li>
                                <a class="px-3 py-2 rounded-md <?= $i == $page ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-800 hover:bg-gray-300' ?>" 
                                   href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>">
                                    <?= $i ?>
                                </a>
                            </li>
                        <?php endfor; 
                        
                        if ($end_page < $total_pages) {
                            echo '<li><span class="px-3 py-2 rounded-md text-gray-500">...</span></li>';
                        }
                        ?>
                        <li>
                            <a class="px-3 py-2 rounded-md <?= $page >= $total_pages ? 'bg-gray-200 text-gray-500 cursor-not-allowed' : 'bg-blue-600 text-white hover:bg-blue-700' ?>" 
                               href="?<?= http_build_query(array_merge($_GET, ['page' => min($total_pages, $page + 1)])) ?>" 
                               aria-label="Next">
                                <span aria-hidden="true">»</span>
                            </a>
                        </li>
                        <li>
                            <a class="px-3 py-2 rounded-md <?= $page >= $total_pages ? 'bg-gray-200 text-gray-500 cursor-not-allowed' : 'bg-blue-600 text-white hover:bg-blue-700' ?>" 
                               href="?<?= http_build_query(array_merge($_GET, ['page' => $total_pages])) ?>" 
                               aria-label="Last">
                                <span aria-hidden="true">»»</span>
                            </a>
                        </li>
                    </ul>
                </nav>
            </div>
        </div>

        <!-- Export Modal -->
        <div class="modal fade fixed top-0 left-0 hidden w-full h-full outline-none overflow-x-hidden overflow-y-auto" id="exportModal" tabindex="-1" aria-labelledby="exportModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg relative w-auto pointer-events-none">
                <div class="modal-content border-none shadow-lg relative flex flex-col w-full pointer-events-auto bg-white bg-clip-padding rounded-md outline-none">
                    <div class="modal-header flex items-center justify-between p-4 border-b border-gray-200">
                        <h5 class="text-lg font-semibold text-gray-800" id="exportModalLabel">Export Users</h5>
                        <button type="button" class="text-gray-500 hover:text-gray-700" data-bs-dismiss="modal" aria-label="Close">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div class="modal-body p-6">
                        <form id="exportForm" method="POST" action="export.php">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                <div>
                                    <label for="exportFormat" class="block text-sm font-medium text-gray-700">Format</label>
                                    <select class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50" id="exportFormat" name="format" required>
                                        <option value="csv">CSV (Comma Separated Values)</option>
                                        <option value="excel">Excel (XLSX)</option>
                                        <option value="pdf">PDF Document</option>
                                        <option value="json">JSON</option>
                                    </select>
                                </div>
                                <div>
                                    <label for="exportScope" class="block text-sm font-medium text-gray-700">Export Scope</label>
                                    <select class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50" id="exportScope" name="scope">
                                        <option value="current">Current Page (<?= count($users) ?> records)</option>
                                        <option value="all">All Filtered Results (<?= $total_users ?> records)</option>
                                        <option value="selected">Selected Records Only</option>
                                    </select>
                                </div>
                            </div>
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700">Columns to Export</label>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-2">
                                    <div>
                                        <div class="flex items-center">
                                            <input class="mr-2 rounded border-gray-300 focus:ring-blue-500" type="checkbox" id="exportId" name="columns[]" value="id" checked>
                                            <label class="text-sm text-gray-700" for="exportId">ID</label>
                                        </div>
                                        <div class="flex items-center">
                                            <input class="mr-2 rounded border-gray-300 focus:ring-blue-500" type="checkbox" id="exportUsername" name="columns[]" value="username" checked>
                                            <label class="text-sm text-gray-700" for="exportUsername">Username</label>
                                        </div>
                                        <div class="flex items-center">
                                            <input class="mr-2 rounded border-gray-300 focus:ring-blue-500" type="checkbox" id="exportName" name="columns[]" value="name" checked>
                                            <label class="text-sm text-gray-700" for="exportName">Full Name</label>
                                        </div>
                                    </div>
                                    <div>
                                        <div class="flex items-center">
                                            <input class="mr-2 rounded border-gray-300 focus:ring-blue-500" type="checkbox" id="exportEmail" name="columns[]" value="email" checked>
                                            <label class="text-sm text-gray-700" for="exportEmail">Email</label>
                                        </div>
                                        <div class="flex items-center">
                                            <input class="mr-2 rounded border-gray-300 focus:ring-blue-500" type="checkbox" id="exportRole" name="columns[]" value="role" checked>
                                            <label class="text-sm text-gray-700" for="exportRole">Role</label>
                                        </div>
                                        <div class="flex items-center">
                                            <input class="mr-2 rounded border-gray-300 focus:ring-blue-500" type="checkbox" id="exportStatus" name="columns[]" value="status" checked>
                                            <label class="text-sm text-gray-700" for="exportStatus">Status</label>
                                        </div>
                                    </div>
                                    <div>
                                        <div class="flex items-center">
                                            <input class="mr-2 rounded border-gray-300 focus:ring-blue-500" type="checkbox" id="exportLastLogin" name="columns[]" value="last_login" checked>
                                            <label class="text-sm text-gray-700" for="exportLastLogin">Last Login</label>
                                        </div>
                                        <div class="flex items-center">
                                            <input class="mr-2 rounded border-gray-300 focus:ring-blue-500" type="checkbox" id="export2FA" name="columns[]" value="2fa" checked>
                                            <label class="text-sm text-gray-700" for="export2FA">2FA Status</label>
                                        </div>
                                        <div class="flex items-center">
                                            <input class="mr-2 rounded border-gray-300 focus:ring-blue-500" type="checkbox" id="exportCreated" name="columns[]" value="created_at" checked>
                                            <label class="text-sm text-gray-700" for="exportCreated">Created At</label>
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
                    <div class="modal-footer flex justify-end p-4 border-t border-gray-200">
                        <button type="button" class="bg-gray-200 text-gray-800 px-4 py-2 rounded-md hover:bg-gray-300 transition mr-2" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" form="exportForm" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition">Export Data</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- User Status Modal -->
        <div class="modal fade fixed top-0 left-0 hidden w-full h-full outline-none overflow-x-hidden overflow-y-auto" id="userStatusModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog relative w-auto pointer-events-none">
                <div class="modal-content border-none shadow-lg relative flex flex-col w-full pointer-events-auto bg-white bg-clip-padding rounded-md outline-none">
                    <div class="modal-header flex items-center justify-between p-4 border-b border-gray-200">
                        <h5 class="text-lg font-semibold text-gray-800">User Status Details</h5>
                        <button type="button" class="text-gray-500 hover:text-gray-700" data-bs-dismiss="modal" aria-label="Close">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div class="modal-body p-6" id="userStatusContent">
                        Loading user status...
                    </div>
                    <div class="modal-footer flex justify-end p-4 border-t border-gray-200">
                        <button type="button" class="bg-gray-200 text-gray-800 px-4 py-2 rounded-md hover:bg-gray-300 transition" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript Libraries -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/select/1.3.4/js/dataTables.select.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.2.2/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.html5.min.js"></script>
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
        });
    </script>
    <?php require_once __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>