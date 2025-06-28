<?php
session_start();
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/helpers.php'; // New helper file for utility functions

// Check admin permissions
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: /unauthorized.php");
    exit();
}

// CSRF token generation
$csrf_token = generateCsrfToken();

// Input validation and sanitization
$filter_user = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT) ?: null;
$filter_action = filter_input(INPUT_GET, 'action', FILTER_SANITIZE_STRING) ?: null;
$filter_date_from = validateDate(filter_input(INPUT_GET, 'date_from', FILTER_SANITIZE_STRING)) ?: null;
$filter_date_to = validateDate(filter_input(INPUT_GET, 'date_to', FILTER_SANITIZE_STRING)) ?: null;

// Build query
$query = "SELECT al.*, u.username 
          FROM activity_logs al 
          LEFT JOIN users u ON al.user_id = u.id 
          WHERE 1=1";
$params = [];

if ($filter_user) {
    $query .= " AND al.user_id = ?";
    $params[] = $filter_user;
}

if ($filter_action) {
    $query .= " AND al.action = ?";
    $params[] = $filter_action;
}

if ($filter_date_from) {
    $query .= " AND al.created_at >= ?";
    $params[] = $filter_date_from . ' 00:00:00';
}

if ($filter_date_to) {
    $query .= " AND al.created_at <= ?";
    $params[] = $filter_date_to . ' 23:59:59';
}

$query .= " ORDER BY al.created_at DESC LIMIT 1000"; // Limit to prevent overload

try {
    // Get logs
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get distinct actions for filter dropdown
    $actions = $pdo->query("SELECT DISTINCT action FROM activity_logs ORDER BY action")->fetchAll(PDO::FETCH_COLUMN);

    // Get users for filter dropdown
    $users = $pdo->query("SELECT id, username FROM users ORDER BY username")->fetchAll(PDO::FETCH_ASSOC);

    // Get statistics for dashboard cards (optimized with a single query)
    $stats_query = "
        SELECT 
            COUNT(*) as total_logs,
            SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as today_logs,
            COUNT(DISTINCT user_id) as unique_users
        FROM activity_logs WHERE user_id IS NOT NULL";
    $stats = $pdo->query($stats_query)->fetch(PDO::FETCH_ASSOC);
    $total_logs = $stats['total_logs'];
    $today_logs = $stats['today_logs'];
    $unique_users = $stats['unique_users'];

    $top_action = $pdo->query("SELECT action, COUNT(*) as count FROM activity_logs GROUP BY action ORDER BY count DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);

    // Get data for charts
    $actions_by_day = $pdo->query("
        SELECT DATE(created_at) as day, COUNT(*) as count 
        FROM activity_logs 
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY day 
        ORDER BY day
    ")->fetchAll(PDO::FETCH_ASSOC);

    $actions_by_type = $pdo->query("
        SELECT action, COUNT(*) as count 
        FROM activity_logs 
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY action 
        ORDER BY count DESC 
        LIMIT 10
    ")->fetchAll(PDO::FETCH_ASSOC);

    $users_activity = $pdo->query("
        SELECT u.username, COUNT(al.id) as count 
        FROM activity_logs al
        LEFT JOIN users u ON al.user_id = u.id
        WHERE al.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY u.username
        ORDER BY count DESC
        LIMIT 10
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Activity by hour (real data)
    $activity_by_hour = $pdo->query("
        SELECT HOUR(created_at) as hour, COUNT(*) as count 
        FROM activity_logs 
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY hour 
        ORDER BY hour
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Format activity by hour for chart
    $hourly_counts = array_fill(0, 24, 0);
    foreach ($activity_by_hour as $row) {
        $hourly_counts[(int)$row['hour']] = (int)$row['count'];
    }
} catch (PDOException $e) {
    logError($e->getMessage());
    die("Database error. Please try again later.");
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Activity Log Dashboard</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#exportModal">
                <i class="fas fa-download me-1"></i> Export
            </button>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="refreshBtn">
                <i class="fas fa-sync-alt me-1"></i> Refresh
            </button>
        </div>
        <div class="dropdown">
            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="timeRangeDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="fas fa-calendar-alt me-1"></i> Quick Filters
            </button>
            <ul class="dropdown-menu" aria-labelledby="timeRangeDropdown">
                <li><a class="dropdown-item" href="#" data-range="today">Today</a></li>
                <li><a class="dropdown-item" href="#" data-range="yesterday">Yesterday</a></li>
                <li><a class="dropdown-item" href="#" data-range="week">This Week</a></li>
                <li><a class="dropdown-item" href="#" data-range="month">This Month</a></li>
                <li><a class="dropdown-item" href="#" data-range="year">This Year</a></li>
            </ul>
        </div>
    </div>
</div>

<!-- Summary Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card text-white bg-primary mb-3">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-title">Total Logs</h6>
                        <h2 class="mb-0"><?= number_format($total_logs) ?></h2>
                    </div>
                    <i class="fas fa-clipboard-list fa-3x opacity-50"></i>
                </div>
                <div class="mt-2">
                    <small class="opacity-75">All time activity</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white bg-success mb-3">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-title">Today's Logs</h6>
                        <h2 class="mb-0"><?= number_format($today_logs) ?></h2>
                    </div>
                    <i class="fas fa-calendar-day fa-3x opacity-50"></i>
                </div>
                <div class="mt-2">
                    <small class="opacity-75"><?= date('M j, Y') ?></small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white bg-info mb-3">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-title">Unique Users</h6>
                        <h2 class="mb-0"><?= number_format($unique_users) ?></h2>
                    </div>
                    <i class="fas fa-users fa-3x opacity-50"></i>
                </div>
                <div class="mt-2">
                    <small class="opacity-75">Active in system</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white bg-warning mb-3">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-title">Top Action</h6>
                        <h4 class="mb-0"><?= htmlspecialchars($top_action['action'] ?? 'N/A') ?></h4>
                        <small><?= number_format($top_action['count'] ?? 0) ?> times</small>
                    </div>
                    <i class="fas fa-chart-bar fa-3x opacity-50"></i>
                </div>
                <div class="mt-2">
                    <small class="opacity-75">Most frequent</small>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Charts Section -->
<div class="row mb-4">
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-chart-line me-2"></i>Activity Trend (Last 30 Days)</h5>
                <div class="dropdown">
                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="chartRangeDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-calendar-alt me-1"></i> Range
                    </button>
                    <ul class="dropdown-menu" aria-labelledby="chartRangeDropdown">
                        <li><a class="dropdown-item" href="#" data-chart-range="7">Last 7 Days</a></li>
                        <li><a class="dropdown-item" href="#" data-chart-range="30">Last 30 Days</a></li>
                        <li><a class="dropdown-item" href="#" data-chart-range="90">Last 90 Days</a></li>
                    </ul>
                </div>
            </div>
            <div class="card-body">
                <canvas id="activityTrendChart" height="250"></canvas>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Action Distribution</h5>
            </div>
            <div class="card-body">
                <canvas id="actionDistributionChart" height="250"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-user-chart me-2"></i>Top Active Users</h5>
            </div>
            <div class="card-body">
                <canvas id="topUsersChart" height="250"></canvas>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-clock me-2"></i>Activity by Hour</h5>
            </div>
            <div class="card-body">
                <canvas id="activityByHourChart" height="250"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Filters</h5>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <div class="col-md-3">
                <label for="user_id" class="form-label">User</label>
                <select class="form-select" id="user_id" name="user_id">
                    <option value="">All Users</option>
                    <?php foreach ($users as $user): ?>
                        <option value="<?= $user['id'] ?>" <?= $filter_user == $user['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($user['username']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label for="action" class="form-label">Action</label>
                <select class="form-select" id="action" name="action">
                    <option value="">All Actions</option>
                    <?php foreach ($actions as $action): ?>
                        <option value="<?= htmlspecialchars($action) ?>" <?= $filter_action == $action ? 'selected' : '' ?>>
                            <?= ucfirst(htmlspecialchars($action)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label for="date_from" class="form-label">From Date</label>
                <div class="input-group">
                    <input type="date" class="form-control" id="date_from" name="date_from" 
                           value="<?= htmlspecialchars($filter_date_from) ?>">
                    <span class="input-group-text"><i class="fas fa-calendar"></i></span>
                </div>
            </div>
            <div class="col-md-3">
                <label for="date_to" class="form-label">To Date</label>
                <div class="input-group">
                    <input type="date" class="form-control" id="date_to" name="date_to" 
                           value="<?= htmlspecialchars($filter_date_to) ?>">
                    <span class="input-group-text"><i class="fas fa-calendar"></i></span>
                </div>
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-filter me-1"></i> Apply Filters
                </button>
                <a href="index.php" class="btn btn-outline-secondary">
                    <i class="fas fa-undo me-1"></i> Reset
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Logs Table -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="fas fa-list me-2"></i>Activity Logs</h5>
        <div>
            <span class="badge bg-primary rounded-pill"><?= count($logs) ?> records found</span>
            <button class="btn btn-sm btn-outline-secondary ms-2" id="toggleSearchBtn">
                <i class="fas fa-search"></i>
            </button>
        </div>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table id="logsTable" class="table table-striped table-hover table-bordered" style="width:100%">
                <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>Timestamp</th>
                        <th>User</th>
                        <th>Action</th>
                        <th>Description</th>
                        <th>IP Address</th>
                        <th>Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                    <tr>
                        <td><?= htmlspecialchars($log['id']) ?></td>
                        <td data-order="<?= strtotime($log['created_at']) ?>">
                            <div class="d-flex flex-column">
                                <span><?= date('M j, Y', strtotime($log['created_at'])) ?></span>
                                <small class="text-muted"><?= date('g:i a', strtotime($log['created_at'])) ?></small>
                            </div>
                        </td>
                        <td>
                            <?php if ($log['username']): ?>
                                <span class="badge bg-info rounded-pill">
                                    <i class="fas fa-user me-1"></i><?= htmlspecialchars($log['username']) ?>
                                </span>
                            <?php else: ?>
                                <span class="badge bg-secondary rounded-pill">
                                    <i class="fas fa-robot me-1"></i>System
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge bg-<?= 
                                strpos(strtolower($log['action']), 'error') !== false ? 'danger' : 
                                (strpos(strtolower($log['action']), 'login') !== false ? 'success' : 'primary') 
                            ?> rounded-pill">
                                <i class="fas fa-<?= 
                                    strpos(strtolower($log['action']), 'login') !== false ? 'sign-in-alt' : 
                                    (strpos(strtolower($log['action']), 'create') !== false ? 'plus' : 
                                    (strpos(strtolower($log['action']), 'delete') !== false ? 'trash' : 'bolt'))
                                ?> me-1"></i>
                                <?= htmlspecialchars($log['action']) ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars($log['description']) ?></td>
                        <td>
                            <span class="badge bg-light text-dark">
                                <i class="fas fa-network-wired me-1"></i><?= htmlspecialchars($log['ip_address']) ?>
                            </span>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary view-details" 
                                    data-id="<?= htmlspecialchars($log['id']) ?>" 
                                    data-bs-toggle="modal" 
                                    data-bs-target="#logDetailsModal">
                                <i class="fas fa-eye me-1"></i> View
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Log Details Modal -->
<div class="modal fade" id="logDetailsModal" tabindex="-1" aria-labelledby="logDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="logDetailsModalLabel">
                    <i class="fas fa-info-circle me-2"></i>Log Details
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="logDetailsContent">
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Loading details...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i> Close
                </button>
                <button type="button" class="btn btn-primary" id="copyDetailsBtn">
                    <i class="fas fa-copy me-1"></i> Copy Details
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Export Modal -->
<div class="modal fade" id="exportModal" tabindex="-1" aria-labelledby="exportModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="exportModalLabel">
                    <i class="fas fa-file-export me-2"></i>Export Options
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="exportForm" action="export_logs.php" method="POST">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                    <div class="mb-3">
                        <label for="exportFormat" class="form-label">Format</label>
                        <select class="form-select" id="exportFormat" name="export">
                            <option value="csv">CSV (Comma Separated Values)</option>
                            <option value="json">JSON</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="exportColumns" class="form-label">Columns to Export</label>
                        <select class="form-select" id="exportColumns" name="columns[]" multiple>
                            <option value="id" selected>ID</option>
                            <option value="created_at" selected>Timestamp</option>
                            <option value="username" selected>User</option>
                            <option value="action" selected>Action</option>
                            <option value="description" selected>Description</option>
                            <option value="ip_address" selected>IP Address</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="exportTimeRange" class="form-label">Time Range</label>
                        <select class="form-select" id="exportTimeRange" name="time_range">
                            <option value="current">Current Filter Results</option>
                            <option value="today">Today</option>
                            <option value="week">This Week</option>
                            <option value="month">This Month</option>
                            <option value="year">This Year</option>
                            <option value="all">All Time</option>
                        </select>
                    </div>
                    <input type="hidden" name="user_id" value="<?= htmlspecialchars($filter_user) ?>">
                    <input type="hidden" name="action" value="<?= htmlspecialchars($filter_action) ?>">
                    <input type="hidden" name="date_from" value="<?= htmlspecialchars($filter_date_from) ?>">
                    <input type="hidden" name="date_to" value="<?= htmlspecialchars($filter_date_to) ?>">
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i> Cancel
                </button>
                <button type="button" class="btn btn-primary" id="exportSubmit">
                    <i class="fas fa-download me-1"></i> Export
                </button>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript Dependencies -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.2.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/plug-ins/1.11.5/dataRender/datetime.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/moment"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-moment"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.2.2/css/buttons.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css">

<script>
$(document).ready(function() {
    // Initialize DataTable
    var table = $('#logsTable').DataTable({
        responsive: true,
        order: [[0, 'desc']],
        pageLength: 25,
        dom: '<"top"Bf>rt<"bottom"lip><"clear">',
        buttons: [
            {
                extend: 'colvis',
                text: '<i class="fas fa-columns"></i> Columns',
                className: 'btn btn-sm btn-outline-secondary',
                columns: ':not(.no-export)'
            },
            {
                extend: 'copy',
                text: '<i class="fas fa-copy"></i> Copy',
                className: 'btn btn-sm btn-outline-secondary'
            },
            {
                extend: 'excel',
                text: '<i class="fas fa-file-excel"></i> Excel',
                className: 'btn btn-sm btn-outline-secondary'
            },
            {
                extend: 'pdf',
                text: '<i class="fas fa-file-pdf"></i> PDF',
                className: 'btn btn-sm btn-outline-secondary'
            },
            {
                extend: 'print',
                text: '<i class="fas fa-print"></i> Print',
                className: 'btn btn-sm btn-outline-secondary'
            }
        ],
        columnDefs: [
            {
                targets: [6],
                orderable: false,
                searchable: false
            },
            {
                targets: [1],
                render: $.fn.dataTable.render.moment('MMM D, YYYY h:mm a')
            }
        ],
        language: {
            search: "_INPUT_",
            searchPlaceholder: "Search logs...",
            lengthMenu: "Show _MENU_ logs per page",
            zeroRecords: "No matching logs found",
            info: "Showing _START_ to _END_ of _TOTAL_ logs",
            infoEmpty: "No logs available",
            infoFiltered: "(filtered from _MAX_ total logs)"
        }
    });

    // Initialize charts
    var activityTrendCtx = document.getElementById('activityTrendChart').getContext('2d');
    var activityTrendChart = new Chart(activityTrendCtx, {
        type: 'line',
        data: {
            labels: <?= json_encode(array_column($actions_by_day, 'day')) ?>,
            datasets: [{
                label: 'Activities',
                data: <?= json_encode(array_column($actions_by_day, 'count')) ?>,
                backgroundColor: 'rgba(54, 162, 235, 0.2)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 2,
                tension: 0.3,
                fill: true
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'top',
                },
                tooltip: {
                    mode: 'index',
                    intersect: false,
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Number of Activities'
                    }
                },
                x: {
                    type: 'time',
                    time: {
                        unit: 'day'
                    },
                    title: {
                        display: true,
                        text: 'Date'
                    }
                }
            }
        }
    });

    var actionDistributionCtx = document.getElementById('actionDistributionChart').getContext('2d');
    var actionDistributionChart = new Chart(actionDistributionCtx, {
        type: 'doughnut',
        data: {
            labels: <?= json_encode(array_column($actions_by_type, 'action')) ?>,
            datasets: [{
                data: <?= json_encode(array_column($actions_by_type, 'count')) ?>,
                backgroundColor: [
                    'rgba(255, 99, 132, 0.7)',
                    'rgba(54, 162, 235, 0.7)',
                    'rgba(255, 206, 86, 0.7)',
                    'rgba(75, 192, 192, 0.7)',
                    'rgba(153, 102, 255, 0.7)',
                    'rgba(255, 159, 64, 0.7)',
                    'rgba(199, 199, 199, 0.7)',
                    'rgba(83, 102, 255, 0.7)',
                    'rgba(40, 159, 64, 0.7)',
                    'rgba(210, 99, 132, 0.7)'
                ],
                borderColor: [
                    'rgba(255, 99, 132, 1)',
                    'rgba(54, 162, 235, 1)',
                    'rgba(255, 206, 86, 1)',
                    'rgba(75, 192, 192, 1)',
                    'rgba(153, 102, 255, 1)',
                    'rgba(255, 159, 64, 1)',
                    'rgba(199, 199, 199, 1)',
                    'rgba(83, 102, 255, 1)',
                    'rgba(40, 159, 64, 1)',
                    'rgba(210, 99, 132, 1)'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'right',
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            var label = context.label || '';
                            var value = context.raw || 0;
                            var total = context.dataset.data.reduce((a, b) => a + b, 0);
                            var percentage = Math.round((value / total) * 100);
                            return `${label}: ${value} (${percentage}%)`;
                        }
                    }
                }
            }
        }
    });

    var topUsersCtx = document.getElementById('topUsersChart').getContext('2d');
    var topUsersChart = new Chart(topUsersCtx, {
        type: 'bar',
        data: {
            labels: <?= json_encode(array_column($users_activity, 'username')) ?>,
            datasets: [{
                label: 'Activities',
                data: <?= json_encode(array_column($users_activity, 'count')) ?>,
                backgroundColor: 'rgba(75, 192, 192, 0.7)',
                borderColor: 'rgba(75, 192, 192, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Number of Activities'
                    }
                },
                x: {
                    title: {
                        display: true,
                        text: 'User'
                    }
                }
            }
        }
    });

    var activityByHourCtx = document.getElementById('activityByHourChart').getContext('2d');
    var activityByHourChart = new Chart(activityByHourCtx, {
        type: 'bar',
        data: {
            labels: Array.from({length: 24}, (_, i) => i + ':00'),
            datasets: [{
                label: 'Activities',
                data: <?= json_encode(array_values($hourly_counts)) ?>,
                backgroundColor: 'rgba(153, 102, 255, 0.7)',
                borderColor: 'rgba(153, 102, 255, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Number of Activities'
                    }
                },
                x: {
                    title: {
                        display: true,
                        text: 'Hour of Day'
                    }
                }
            }
        }
    });

    // Quick filter buttons
    $('[data-range]').click(function(e) {
        e.preventDefault();
        var range = $(this).data('range');
        var date_from = '', date_to = '';
        
        var today = new Date();
        
        switch(range) {
            case 'today':
                date_from = date_to = today.toISOString().split('T')[0];
                break;
            case 'yesterday':
                var yesterday = new Date(today);
                yesterday.setDate(yesterday.getDate() - 1);
                date_from = date_to = yesterday.toISOString().split('T')[0];
                break;
            case 'week':
                var firstDay = new Date(today.setDate(today.getDate() - today.getDay()));
                var lastDay = new Date(today.setDate(today.getDate() - today.getDay() + 6));
                date_from = firstDay.toISOString().split('T')[0];
                date_to = lastDay.toISOString().split('T')[0];
                break;
            case 'month':
                var firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
                var lastDay = new Date(today.getFullYear(), today.getMonth() + 1, 0);
                date_from = firstDay.toISOString().split('T')[0];
                date_to = lastDay.toISOString().split('T')[0];
                break;
            case 'year':
                var firstDay = new Date(today.getFullYear(), 0, 1);
                var lastDay = new Date(today.getFullYear(), 11, 31);
                date_from = firstDay.toISOString().split('T')[0];
                date_to = lastDay.toISOString().split('T')[0];
                break;
        }
        
        $('#date_from').val(date_from);
        $('#date_to').val(date_to);
        $('form').submit();
    });

    // View details button
    $('.view-details').click(function() {
        var logId = $(this).data('id');
        $.ajax({
            url: 'get_log_details.php',
            type: 'GET',
            data: { id: logId, csrf_token: '<?= htmlspecialchars($csrf_token) ?>' },
            success: function(data) {
                $('#logDetailsContent').html(data);
            },
            error: function() {
                $('#logDetailsContent').html('<div class="alert alert-danger">Failed to load details. Please try again.</div>');
            }
        });
    });

    // Copy details button
    $('#copyDetailsBtn').click(function() {
        var detailsText = $('#logDetailsContent').text();
        navigator.clipboard.writeText(detailsText).then(function() {
            var originalText = $(this).html();
            $(this).html('<i class="fas fa-check me-1"></i> Copied!');
            setTimeout(function() {
                $('#copyDetailsBtn').html('<i class="fas fa-copy me-1"></i> Copy Details');
            }, 2000);
        }.bind(this)).catch(function(err) {
            console.error('Could not copy text: ', err);
        });
    });

    // Export button
    $('#exportSubmit').click(function() {
        $('#exportForm').submit();
    });

    // Refresh button
    $('#refreshBtn').click(function() {
        var $btn = $(this);
        $btn.prop('disabled', true);
        $btn.html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Refreshing...');
        location.reload();
    });

    // Toggle search button
    $('#toggleSearchBtn').click(function() {
        $('.dataTables_filter').toggle();
    });

    // Initialize multi-select for export columns
    $('#exportColumns').select2({
        theme: 'bootstrap-5',
        width: '100%',
        placeholder: 'Select columns to export',
        closeOnSelect: false
    });

    // Chart range selector
    $('[data-chart-range]').click(function(e) {
        e.preventDefault();
        var range = $(this).data('chart-range');
        $.ajax({
            url: 'get_chart_data.php',
            type: 'GET',
            data: { range: range, csrf_token: '<?= htmlspecialchars($csrf_token) ?>' },
            success: function(data) {
                activityTrendChart.data.labels = data.days;
                activityTrendChart.data.datasets[0].data = data.counts;
                activityTrendChart.update();
                showToast('Chart range updated to last ' + range + ' days');
            },
            error: function() {
                showToast('Failed to update chart data');
            }
        });
    });

    // Show toast notification
    function showToast(message) {
        var toast = `<div class="position-fixed bottom-0 end-0 p-3" style="z-index: 11">
            <div class="toast show" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="toast-header">
                    <strong class="me-auto">Notification</strong>
                    <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
                <div class="toast-body">
                    ${message}
                </div>
            </div>
        </div>`;
        
        $('body').append(toast);
        setTimeout(function() {
            $('.toast').remove();
        }, 3000);
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>