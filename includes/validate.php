<?php
/**
 * Session Validation and Access Control
 * 
 * Validates sessions, checks for blacklisted IPs, and manages device trust status
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

class AccessValidator {
    private $pdo;
    private $currentIp;
    private $currentUserAgent;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->currentIp = $_SERVER['REMOTE_ADDR'] ?? '';
        $this->currentUserAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    }
    
    /**
     * Validate current session and device
     * @throws Exception If validation fails
     */
    public function validateAccess() {
        // Check if user is logged in
        if (!isset($_SESSION['user_id'])) {
            throw new Exception('Unauthorized access', 401);
        }
        
        // Check IP blacklist
        $this->checkIpBlacklist();
        
        // Check session validity
        $this->validateSession();
        
        // Check device trust status
        $this->checkDeviceTrustStatus();
        
        // Update last activity
        $this->updateLastActivity();
    }
    
    /**
     * Check if current IP is blacklisted
     * @throws Exception If IP is blacklisted
     */
    private function checkIpBlacklist() {
        $stmt = $this->pdo->prepare("
            SELECT id FROM ip_blacklist 
            WHERE ip_address = ? AND (expires_at IS NULL OR expires_at > NOW())
        ");
        $stmt->execute([$this->currentIp]);
        
        if ($stmt->fetch()) {
            // Log this attempt
            $this->logSecurityEvent('blacklisted_ip_attempt', "Attempted access from blacklisted IP: {$this->currentIp}");
            throw new Exception('Access restricted from this IP address', 403);
        }
    }
    
    /**
     * Validate current session
     * @throws Exception If session is invalid or revoked
     */
    private function validateSession() {
        if (!isset($_SESSION['session_id'])) {
            throw new Exception('Invalid session', 401);
        }
        
        $stmt = $this->pdo->prepare("
            SELECT s.*, df.trust_status 
            FROM sessions s
            JOIN device_fingerprints df ON s.device_fingerprint_id = df.id
            WHERE s.session_id = ? AND s.user_id = ?
        ");
        $stmt->execute([$_SESSION['session_id'], $_SESSION['user_id']]);
        $session = $stmt->fetch();
        
        if (!$session) {
            throw new Exception('Session revoked or not found', 401);
        }
        
        // Check session expiration (30 minutes inactivity)
        $lastActivity = strtotime($session['last_activity']);
        if (time() - $lastActivity > 1800) {
            $this->revokeSession($_SESSION['session_id']);
            throw new Exception('Session expired', 401);
        }
        
        // Verify IP hasn't changed
        if ($session['ip_address'] !== $this->currentIp) {
            $this->logSecurityEvent('ip_mismatch', 
                "IP changed from {$session['ip_address']} to {$this->currentIp}");
            
            // For sensitive operations, you might want to reject the request
            // throw new Exception('Session IP mismatch', 401);
        }
        
        // Verify user agent hasn't changed significantly
        $this->verifyUserAgent($session['user_agent']);
    }
    
    /**
     * Check device trust status
     * @throws Exception If device is untrusted
     */
    private function checkDeviceTrustStatus() {
        if (!isset($_SESSION['device_fingerprint_id'])) {
            throw new Exception('Device not recognized', 403);
        }
        
        $stmt = $this->pdo->prepare("
            SELECT trust_status FROM device_fingerprints 
            WHERE id = ? AND user_id = ?
        ");
        $stmt->execute([$_SESSION['device_fingerprint_id'], $_SESSION['user_id']]);
        $device = $stmt->fetch();
        
        if (!$device) {
            throw new Exception('Device not found', 403);
        }
        
        if ($device['trust_status'] === 'untrusted') {
            $this->logSecurityEvent('untrusted_device_attempt', 
                "Attempted access from untrusted device: {$_SESSION['device_fingerprint_id']}");
            throw new Exception('Access from this device is restricted', 403);
        }
    }
    
    /**
     * Verify user agent consistency
     */
    private function verifyUserAgent($storedUserAgent) {
        // Simple verification - in production you might want more sophisticated checks
        if (empty($storedUserAgent)) {
            return;
        }
        
        $currentBrowser = get_browser($this->currentUserAgent, true);
        $storedBrowser = get_browser($storedUserAgent, true);
        
        if ($currentBrowser['browser'] !== $storedBrowser['browser'] || 
            $currentBrowser['platform'] !== $storedBrowser['platform']) {
            $this->logSecurityEvent('user_agent_mismatch', 
                "User agent changed from {$storedUserAgent} to {$this->currentUserAgent}");
            
            // For sensitive operations, you might want to reject the request
            // throw new Exception('User agent mismatch', 401);
        }
    }
    
    /**
     * Update last activity timestamp
     */
    private function updateLastActivity() {
        if (isset($_SESSION['session_id'])) {
            $this->pdo->prepare("
                UPDATE sessions SET last_activity = NOW() 
                WHERE session_id = ?
            ")->execute([$_SESSION['session_id']]);
        }
    }
    
    /**
     * Revoke a session
     */
    public function revokeSession($sessionId) {
        $this->pdo->prepare("
            DELETE FROM sessions WHERE session_id = ?
        ")->execute([$sessionId]);
        
        $this->logSecurityEvent('session_revoked', "Session revoked: {$sessionId}");
    }
    
    /**
     * Log security events
     */
    private function logSecurityEvent($action, $description) {
        $this->pdo->prepare("
            INSERT INTO security_logs (user_id, action, description, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?)
        ")->execute([
            $_SESSION['user_id'] ?? null,
            $action,
            $description,
            $this->currentIp,
            $this->currentUserAgent
        ]);
    }
    
    /**
     * Check if current request should be allowed
     */
    public static function secureRequest($pdo) {
        try {
            $validator = new self($pdo);
            $validator->validateAccess();
            return true;
        } catch (Exception $e) {
            // Handle different error types appropriately
            switch ($e->getCode()) {
                case 401:
                    // Session related errors - redirect to login
                    header('Location: /login.php?error=' . urlencode($e->getMessage()));
                    exit;
                case 403:
                    // Access denied errors - show restricted page
                    header('Location: /restricted.php');
                    exit;
                default:
                    // Other errors - show error page
                    error_log('Access validation error: ' . $e->getMessage());
                    header('Location: /error.php');
                    exit;
            }
        }
    }
}

class SessionValidator {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Validate current session and check if revoked
     * Redirects to login page if session is invalid or revoked
     */
    public function validateSession() {
        // Check if session variables exist
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['session_id'])) {
            $this->redirectToLogin();
        }
        
        // Check if session exists in database
        $stmt = $this->pdo->prepare("
            SELECT s.* 
            FROM sessions s
            WHERE s.session_id = ? AND s.user_id = ?
        ");
        $stmt->execute([$_SESSION['session_id'], $_SESSION['user_id']]);
        $session = $stmt->fetch();
        
        // If session not found, it has been revoked
        if (!$session) {
            $this->cleanSessionData();
            $this->redirectToLogin('Session has been revoked');
        }
        
        // Check session expiration (30 minutes inactivity)
        $lastActivity = strtotime($session['last_activity']);
        if (time() - $lastActivity > 1800) {
            $this->revokeSession($_SESSION['session_id']);
            $this->cleanSessionData();
            $this->redirectToLogin('Session expired due to inactivity');
        }
        
        // Update last activity
        $this->updateLastActivity();
    }
    
    /**
     * Revoke a session by removing it from the database
     */
    public function revokeSession($sessionId) {
        $this->pdo->prepare("
            DELETE FROM sessions WHERE session_id = ?
        ")->execute([$sessionId]);
        
        $this->logSecurityEvent('session_revoked', "Session revoked: $sessionId");
    }
    
    /**
     * Update last activity timestamp for current session
     */
    private function updateLastActivity() {
        $this->pdo->prepare("
            UPDATE sessions SET last_activity = NOW() 
            WHERE session_id = ?
        ")->execute([$_SESSION['session_id']]);
    }
    
    /**
     * Clean session data
     */
    private function cleanSessionData() {
        $_SESSION = array();
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
    }
    
    /**
     * Redirect to login page with optional message
     */
    private function redirectToLogin($message = '') {
        $url = '/login.php';
        if (!empty($message)) {
            $url .= '?error=' . urlencode($message);
        }
        header("Location: $url");
        exit;
    }
    
    /**
     * Log security events
     */
    private function logSecurityEvent($action, $description) {
        $this->pdo->prepare("
            INSERT INTO security_logs (user_id, action, description, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?)
        ")->execute([
            $_SESSION['user_id'] ?? null,
            $action,
            $description,
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
    }
}

// Usage in your protected pages:
// At the very top of each protected PHP file:
// require_once __DIR__ . '/includes/validate.php';
// AccessValidator::secureRequest($pdo);