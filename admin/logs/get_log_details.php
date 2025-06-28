<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/helpers.php';

// CSRF validation
$csrf_token = filter_input(INPUT_GET, 'csrf_token', FILTER_SANITIZE_STRING);
if (!$csrf_token || !validateCsrfToken($csrf_token)) {
    http_response_code(403);
    echo '<div class="alert alert-danger">Invalid CSRF token</div>';
    exit();
}

// Check admin permissions
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    http_response_code(403);
    echo '<div class="alert alert-danger">Unauthorized access</div>';
    exit();
}

// Validate log ID
$log_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$log_id) {
    http_response_code(400);
    echo '<div class="alert alert-danger">Invalid log ID</div>';
    exit();
}

try {
    // Fetch log details with related data
    $query = "
        SELECT al.*, u.username, p.name as project_name, df.fingerprint, df.trust_status, df.risk_score
        FROM activity_logs al
        LEFT JOIN users u ON al.user_id = u.id
        LEFT JOIN projects p ON al.project_id = p.id
        LEFT JOIN sessions s ON al.user_id = s.user_id AND al.created_at = s.last_activity
        LEFT JOIN device_fingerprints df ON s.device_fingerprint_id = df.id
        WHERE al.id = ?
    ";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$log_id]);
    $log = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$log) {
        http_response_code(404);
        echo '<div class="alert alert-warning">Log not found</div>';
        exit();
    }

    // Format the output
    $output = '<dl class="row">';
    $output .= '<dt class="col-sm-3">Log ID</dt><dd class="col-sm-9">' . htmlspecialchars($log['id']) . '</dd>';
    $output .= '<dt class="col-sm-3">Timestamp</dt><dd class="col-sm-9">' . date('M j, Y g:i a', strtotime($log['created_at'])) . '</dd>';
    $output .= '<dt class="col-sm-3">User</dt><dd class="col-sm-9">' . (htmlspecialchars($log['username'] ?? 'System')) . '</dd>';
    $output .= '<dt class="col-sm-3">Action</dt><dd class="col-sm-9">' . htmlspecialchars($log['action']) . '</dd>';
    $output .= '<dt class="col-sm-3">Description</dt><dd class="col-sm-9">' . htmlspecialchars($log['description'] ?? 'N/A') . '</dd>';
    $output .= '<dt class="col-sm-3">IP Address</dt><dd class="col-sm-9">' . htmlspecialchars($log['ip_address'] ?? 'N/A') . '</dd>';
    $output .= '<dt class="col-sm-3">User Agent</dt><dd class="col-sm-9">' . htmlspecialchars($log['user_agent'] ?? 'N/A') . '</dd>';
    $output .= '<dt class="col-sm-3">Project</dt><dd class="col-sm-9">' . htmlspecialchars($log['project_name'] ?? 'N/A') . '</dd>';
    $output .= '<dt class="col-sm-3">Device Fingerprint</dt><dd class="col-sm-9">' . htmlspecialchars($log['fingerprint'] ?? 'N/A') . '</dd>';
    $output .= '<dt class="col-sm-3">Trust Status</dt><dd class="col-sm-9">' . htmlspecialchars($log['trust_status'] ?? 'N/A') . '</dd>';
    $output .= '<dt class="col-sm-3">Risk Score</dt><dd class="col-sm-9">' . htmlspecialchars($log['risk_score'] ?? 'N/A') . '</dd>';
    $output .= '</dl>';

    echo $output;
} catch (PDOException $e) {
    logError($e->getMessage());
    http_response_code(500);
    echo '<div class="alert alert-danger">Failed to load details. Please try again.</div>';
}