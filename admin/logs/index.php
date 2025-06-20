<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/header.php';

// Check admin permissions
if ($_SESSION['user_role'] !== 'admin') {
    header("Location: /unauthorized.php");
    exit();
}

// Database connection
require_once '../../includes/config.php';

// Get filter parameters
$filter_user = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
$filter_action = isset($_GET['action']) ? $_GET['action'] : null;
$filter_date_from = isset($_GET['date_from']) ? $_GET['date_from'] : null;
$filter_date_to = isset($_GET['date_to']) ? $_GET['date_to'] : null;

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

$query .= " ORDER BY al.created_at DESC";

// Get logs
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get distinct actions for filter dropdown
$actions = $pdo->query("SELECT DISTINCT action FROM activity_logs ORDER BY action")->fetchAll(PDO::FETCH_COLUMN);

// Get users for filter dropdown
$users = $pdo->query("SELECT id, username FROM users ORDER BY username")->fetchAll(PDO::FETCH_ASSOC);

// Get statistics for dashboard cards
$total_logs = $pdo->query("SELECT COUNT(*) FROM activity_logs")->fetchColumn();
$today_logs = $pdo->query("SELECT COUNT(*) FROM activity_logs WHERE DATE(created_at) = CURDATE()")->fetchColumn();
$unique_users = $pdo->query("SELECT COUNT(DISTINCT user_id) FROM activity_logs WHERE user_id IS NOT NULL")->fetchColumn();
$top_action = $pdo->query("SELECT action, COUNT(*) as count FROM activity_logs GROUP BY action ORDER BY count DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
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
                    <i class="fas fa-clipboard-list fa-2x"></i>
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
                    <i class="fas fa-calendar-day fa-2x"></i>
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
                    <i class="fas fa-users fa-2x"></i>
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
                    <i class="fas fa-chart-bar fa-2x"></i>
                </div>
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
                <input type="date" class="form-control" id="date_from" name="date_from" 
                       value="<?= htmlspecialchars($filter_date_from) ?>">
            </div>
            <div class="col-md-3">
                <label for="date_to" class="form-label">To Date</label>
                <input type="date" class="form-control" id="date_to" name="date_to" 
                       value="<?= htmlspecialchars($filter_date_to) ?>">
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
        <span class="badge bg-primary"><?= count($logs) ?> records found</span>
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
                        <td><?= $log['id'] ?></td>
                        <td data-order="<?= strtotime($log['created_at']) ?>">
                            <?= date('M j, Y g:i a', strtotime($log['created_at'])) ?>
                        </td>
                        <td>
                            <?php if ($log['username']): ?>
                                <span class="badge bg-info"><?= htmlspecialchars($log['username']) ?></span>
                            <?php else: ?>
                                <span class="badge bg-secondary">System</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge bg-<?= 
                                strpos(strtolower($log['action']), 'error') !== false ? 'danger' : 
                                (strpos(strtolower($log['action']), 'login') !== false ? 'success' : 'primary') 
                            ?>">
                                <?= htmlspecialchars($log['action']) ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars($log['description']) ?></td>
                        <td><?= htmlspecialchars($log['ip_address']) ?></td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary view-details" 
                                    data-id="<?= $log['id'] ?>" 
                                    data-bs-toggle="modal" 
                                    data-bs-target="#logDetailsModal">
                                <i class="fas fa-eye"></i>
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
            <div class="modal-header">
                <h5 class="modal-title" id="logDetailsModalLabel">Log Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="logDetailsContent">
                Loading details...
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Export Modal -->
<div class="modal fade" id="exportModal" tabindex="-1" aria-labelledby="exportModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exportModalLabel">Export Options</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="exportForm" method="GET">
                    <div class="mb-3">
                        <label for="exportFormat" class="form-label">Format</label>
                        <select class="form-select" id="exportFormat" name="export">
                            <option value="csv">CSV (Comma Separated Values)</option>
                            <option value="excel">Excel</option>
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
                    <input type="hidden" name="user_id" value="<?= $filter_user ?>">
                    <input type="hidden" name="action" value="<?= $filter_action ?>">
                    <input type="hidden" name="date_from" value="<?= $filter_date_from ?>">
                    <input type="hidden" name="date_to" value="<?= $filter_date_to ?>">
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="exportSubmit">Export</button>
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
<link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.2.2/css/buttons.bootstrap5.min.css">

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
        }
        
        $('#date_from').val(date_from);
        $('#date_to').val(date_to);
        $('form').submit();
    });

    // View details button
    $('.view-details').click(function() {
        var logId = $(this).data('id');
        $.get('get_log_details.php?id=' + logId, function(data) {
            $('#logDetailsContent').html(data);
        });
    });

    // Export button
    $('#exportSubmit').click(function() {
        $('#exportForm').submit();
    });

    // Refresh button
    $('#refreshBtn').click(function() {
        location.reload();
    });

    // Initialize multi-select for export columns
    $('#exportColumns').select2({
        theme: 'bootstrap-5',
        width: '100%',
        placeholder: 'Select columns to export',
        closeOnSelect: false
    });

    // Toggle sidebar for mobile
    $('#sidebarToggle').click(function() {
        $('.sidebar').toggleClass('active');
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>