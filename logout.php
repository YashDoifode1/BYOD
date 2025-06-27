<?php


// Load required files
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
// Initialize session securely
if (session_status() === PHP_SESSION_NONE) {
    $domain = parse_url(SITE_URL);
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => is_string($domain) ? $domain : '',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
    session_start();
}

// Initialize response
$response = [
    'success' => false,
    'message' => 'Logout initialization failed',
    'session_id' => null
];

// Helper function for client IP
if (!function_exists('getClientIp')) {
    function getClientIp(): string {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) 
            ? $ip 
            : 'local';
    }
}

try {
    // Verify CSRF token if this is a POST request
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token'])) {
        if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
            throw new Exception('Invalid CSRF token');
        }
    }

    // Get current session ID safely
    $currentSessionId = $_SESSION['session_id'] ?? null;
    $response['session_id'] = $currentSessionId;

    // Remove session from database if exists
    if (!empty($currentSessionId)) {
        $deleteStmt = $pdo->prepare("DELETE FROM sessions WHERE session_id = ?");
        $deleteStmt->execute([$currentSessionId]);
        
        if ($deleteStmt->rowCount() === 0) {
            error_log("Warning: Session not found in database: $currentSessionId");
        }
    }

    // Log the logout action if user was authenticated
    if (isset($_SESSION['user_id'])) {
        try {
            $logStmt = $pdo->prepare("
                INSERT INTO security_logs 
                (user_id, action, description, ip_address, user_agent, device_fingerprint) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $logStmt->execute([
                $_SESSION['user_id'],
                'logout',
                'Session terminated',
                getClientIp(),
                $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
                $_SESSION['device_fingerprint'] ?? null
            ]);
        } catch (PDOException $e) {
            error_log("Failed to log logout action: " . $e->getMessage());
        }
    }

    // Clear all session data
    $_SESSION = [];

    // Remove session cookie
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 86400,
            $params['path'],
            is_string($params['domain']) ? $params['domain'] : '',
            $params['secure'],
            $params['httponly']
        );
    }

    // Remove remember me cookie if exists
    if (isset($_COOKIE['remember_token'])) {
        $domain = parse_url(SITE_URL);
        setcookie(
            'remember_token',
            '',
            time() - 86400,
            '/',
            is_string($domain) ? $domain : '',
            true,
            true
        );
    }

    // Destroy the session
    session_destroy();

    // Success response
    $response = [
        'success' => true,
        'message' => 'Logged out successfully',
        'redirect' => 'login.php?logout=success'
    ];

} catch (Exception $e) {
    error_log("Logout Error: " . $e->getMessage());
    $response['error'] = $e->getMessage();
    $response['message'] = 'An error occurred during logout';
}

// Handle response
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    header('Content-Type: application/json');
    echo json_encode($response);
} else {
    $redirect = $response['success'] 
        ? ($response['redirect'] ?? 'login.php?logout=success') 
        : 'login.php?logout=error&message=' . urlencode($response['message']);
    header("Location: $redirect");
}

exit;

