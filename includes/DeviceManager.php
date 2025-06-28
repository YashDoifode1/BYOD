<?php
require_once 'includes/config.php';
require_once 'Database.php';
define('ABUSEIPDB_API_KEY', '9dd9a70ede1f6328f3a520c80a304016789a485bf4cf27dd4ee9a90a480d9b419fb35361a020225a');
define('IPQS_API_KEY', 'UIBQsNrKKJy9yOjGx4JLNPSJSE6XGxQy');

class DeviceManager {
    private $db;
    private $deviceTTL = 7776000; // 90 days in seconds
    private $maxDevicesPerUser = 10; // Maximum allowed devices per user
    private $abuseIpdbApiKey; // AbuseIPDB API key
    private $ipqsApiKey; // IPQualityScore API key

    public function __construct(Database $db) {
        $this->db = $db;
        $this->abuseIpdbApiKey = defined('ABUSEIPDB_API_KEY') ? ABUSEIPDB_API_KEY : null;
        $this->ipqsApiKey = defined('IPQS_API_KEY') ? IPQS_API_KEY : null;
    }

    /**
     * Generates a unique and consistent device fingerprint based on stable client information
     * @return string
     */
    public function generateDeviceFingerprint(): string {
        // Get device info from form submission and client
        $deviceInfo = json_decode($_POST['device_info'] ?? '{}', true);

        // Use only stable characteristics for fingerprinting
        $fingerprintData = [
            'userAgent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'timezone' => $_COOKIE['client_timezone'] ?? 'unknown',
            'screen' => [
                'width' => isset($deviceInfo['screen']['width']) ? (int)$deviceInfo['screen']['width'] : 0,
                'height' => isset($deviceInfo['screen']['height']) ? (int)$deviceInfo['screen']['height'] : 0,
                'colorDepth' => isset($deviceInfo['screen']['colorDepth']) ? (int)$deviceInfo['screen']['colorDepth'] : 0,
                'pixelRatio' => isset($deviceInfo['screen']['pixelRatio']) ? (float)$deviceInfo['screen']['pixelRatio'] : 1.0
            ],
            'navigator' => [
                'platform' => $deviceInfo['navigator']['platform'] ?? 'unknown',
                'language' => $deviceInfo['navigator']['language'] ?? 'unknown',
                'hardwareConcurrency' => isset($deviceInfo['navigator']['hardwareConcurrency']) ? (int)$deviceInfo['navigator']['hardwareConcurrency'] : 0,
                'maxTouchPoints' => isset($deviceInfo['navigator']['maxTouchPoints']) ? (int)$deviceInfo['navigator']['maxTouchPoints'] : 0,
                'deviceMemory' => isset($deviceInfo['navigator']['deviceMemory']) ? (int)$deviceInfo['navigator']['deviceMemory'] : 0
            ],
            'webgl' => $this->normalizeWebGLInfo($deviceInfo['webgl'] ?? [])
        ];

        // Normalize and sort data to ensure consistency
        ksort($fingerprintData);
        foreach (['screen', 'navigator', 'webgl'] as $key) {
            if (is_array($fingerprintData[$key])) {
                ksort($fingerprintData[$key]);
            }
        }

        // Generate a stable hash
        $hash = hash('sha256', json_encode($fingerprintData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        // Store only the hash in session
        $_SESSION['device_fingerprint'] = $hash;

        return $hash;
    }

    /**
     * Normalizes WebGL information for consistent fingerprinting
     */
    private function normalizeWebGLInfo(array $webglInfo): array {
        if (empty($webglInfo)) return [];
        
        $normalized = [
            'vendor' => $webglInfo['vendor'] ?? 'unknown',
            'renderer' => $webglInfo['renderer'] ?? 'unknown',
            'unmaskedVendor' => $webglInfo['unmaskedVendor'] ?? 'unknown',
            'unmaskedRenderer' => $webglInfo['unmaskedRenderer'] ?? 'unknown'
        ];
        ksort($normalized);
        return $normalized;
    }

    /**
     * Checks if a device is trusted for a user
     * @param int $userId
     * @param string $deviceFingerprint
     * @return bool
     */
    public function isTrustedDevice(int $userId, string $deviceFingerprint): bool {
        $query = "SELECT COUNT(*) FROM device_fingerprints 
                 WHERE user_id = ? 
                 AND fingerprint = ? 
                 AND trust_status = 'trusted' 
                 AND last_used > DATE_SUB(NOW(), INTERVAL ? SECOND)";
        
        $count = $this->db->query($query, [
            $userId,
            $deviceFingerprint,
            $this->deviceTTL
        ])->fetchColumn();

        return $count > 0;
    }

    /**
     * Registers a new device for a user
     * @param int $userId
     * @param string $deviceFingerprint
     * @return bool
     */
    public function registerDevice(int $userId, string $deviceFingerprint): bool {
        // Get client IP address
        $ipAddress = $this->getClientIp();
        
        // Check if user has exceeded maximum devices
        $deviceCount = $this->getDeviceCount($userId);
        if ($deviceCount >= $this->maxDevicesPerUser) {
            $this->removeOldestDevice($userId);
        }

        // Check if fingerprint already exists
        $existing = $this->db->query(
            "SELECT id FROM device_fingerprints WHERE fingerprint = ?",
            [$deviceFingerprint]
        )->fetch();

        if ($existing) {
            // Update existing device
            $query = "UPDATE device_fingerprints 
                     SET user_id = ?, user_agent = ?, ip_address = ?, last_used = NOW() 
                     WHERE fingerprint = ?";
            return $this->db->query($query, [
                $userId,
                $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                $ipAddress,
                $deviceFingerprint
            ])->rowCount() > 0;
        }

        // Check IP reputation
        $ipReputation = $this->checkIpReputation($ipAddress);
        
        // Insert new device fingerprint
        $query = "INSERT INTO device_fingerprints (
                    user_id, fingerprint, user_agent, ip_address, ip_reputation, trust_status, last_used
                  ) VALUES (?, ?, ?, ?, ?, 'pending', NOW())";
        
        $result = $this->db->query($query, [
            $userId,
            $deviceFingerprint,
            $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            $ipAddress,
            $ipReputation['status']
        ])->rowCount() > 0;

        if ($result) { 
            // Get the new device_fingerprint_id
            $deviceFingerprintId = $this->db->query(
                "SELECT id FROM device_fingerprints WHERE fingerprint = ?",
                [$deviceFingerprint]
            )->fetchColumn();

            // Insert new session
            $sessionQuery = "INSERT INTO sessions (
                session_id, user_id, user_agent, ip_address, device_fingerprint_id, last_activity
            ) VALUES (?, ?, ?, ?, ?, NOW())";

            $sessionId = bin2hex(random_bytes(32));

            // Start the session if it's not already started
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }

            // Set the session ID in the $_SESSION superglobal
            $_SESSION['session_id'] = $sessionId;

            return $this->db->query($sessionQuery, [
                $sessionId,
                $userId,
                $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                $ipAddress,
                $deviceFingerprintId
            ])->rowCount() > 0;
        }

        return false;
    }

    /**
     * Assesses the risk level of a device
     * @param string $deviceFingerprint
     * @param int $userId
     * @return string
     */
    public function assessDeviceRisk(string $deviceFingerprint, int $userId): string {
        // Check if device is known
        if ($this->isTrustedDevice($userId, $deviceFingerprint)) {
            return 'low';
        }

        // Get recent failed login attempts
        $failedAttempts = $this->getRecentFailedAttempts($userId);
        
        // Get IP address and reputation
        $ipAddress = $this->getClientIp();
        $ipReputation = $this->checkIpReputation($ipAddress);

        // Simple risk scoring
        $riskScore = 0;
        $riskScore += $failedAttempts > 3 ? 40 : 0;
        $riskScore += $ipReputation['status'] === 'suspicious' ? 20 : 0;
        $riskScore += $this->isUnusualUserAgent() ? 20 : 0;
        $riskScore += $this->isUnusualTime() ? 10 : 0;
        $riskScore += $this->hasInconsistentDeviceInfo() ? 30 : 0;

        // Update device_fingerprints with risk score and IP info
        $this->db->query(
            "UPDATE device_fingerprints SET 
                risk_score = ?, 
                ip_reputation = ?,
                ip_address = ?
             WHERE fingerprint = ?",
            [$riskScore, $ipReputation['status'], $ipAddress, $deviceFingerprint]
        );

        if ($riskScore >= 60) {
            return 'high';
        } elseif ($riskScore >= 30) {
            return 'medium';
        }
        return 'low';
    }

    /**
     * Checks IP reputation using AbuseIPDB and IPQualityScore
     * @param string $ipAddress
     * @return array
     */
    private function checkIpReputation(string $ipAddress): array {
        $result = ['status' => 'normal', 'score' => 0];

        // Skip localhost or private IPs
        if (filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return $result;
        }

        // AbuseIPDB check
        if ($this->abuseIpdbApiKey) {
            $url = "https://api.abuseipdb.com/api/v2/check?ipAddress=" . urlencode($ipAddress);
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Key: ' . $this->abuseIpdbApiKey,
                'Accept: application/json'
            ]);
            $response = curl_exec($ch);
            curl_close($ch);

            $data = json_decode($response, true);
            if ($data && isset($data['data']['abuseConfidenceScore'])) {
                $result['score'] = max($result['score'], $data['data']['abuseConfidenceScore']);
                if ($data['data']['abuseConfidenceScore'] > 75) {
                    $result['status'] = 'suspicious';
                }
            }
        }

        // IPQualityScore check
        if ($this->ipqsApiKey) {
            $url = "https://ipqualityscore.com/api/json/ip/" . $this->ipqsApiKey . "/" . urlencode($ipAddress);
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $response = curl_exec($ch);
            curl_close($ch);

            $data = json_decode($response, true);
            if ($data && isset($data['fraud_score'])) {
                $result['score'] = max($result['score'], $data['fraud_score']);
                if ($data['fraud_score'] > 75 || $data['proxy'] || $data['vpn'] || $data['tor']) {
                    $result['status'] = 'suspicious';
                }
            }
        }

        return $result;
    }

    /**
     * Gets the client's IP address
     * @return string
     */
    private function getClientIp(): string {
        $ip = 'unknown';
        
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        
        // Handle multiple IPs in X_FORWARDED_FOR
        if (strpos($ip, ',') !== false) {
            $ips = explode(',', $ip);
            $ip = trim($ips[0]);
        }
        
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : 'unknown';
    }

    /**
     * Checks for inconsistent device information
     * @return bool
     */
    private function hasInconsistentDeviceInfo(): bool {
        $deviceInfo = json_decode($_POST['device_info'] ?? '{}', true);
        
        // Check for mismatches between user agent and platform
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $platform = $deviceInfo['navigator']['platform'] ?? '';
        
        if (stripos($userAgent, 'Windows') !== false && stripos($platform, 'Win') === false) {
            return true;
        }
        if (stripos($userAgent, 'Mac') !== false && stripos($platform, 'Mac') === false) {
            return true;
        }
        if (stripos($userAgent, 'Linux') !== false && stripos($platform, 'Linux') === false) {
            return true;
        }
        
        // Check for unusual screen dimensions
        $width = $deviceInfo['screen']['width'] ?? 0;
        $height = $deviceInfo['screen']['height'] ?? 0;
        if ($width <= 0 || $height <= 0 || $width > 10000 || $height > 10000) {
            return true;
        }
        
        // Check for suspicious WebGL renderer
        $webglRenderer = $deviceInfo['webgl']['renderer'] ?? '';
        if (strpos($webglRenderer, 'Google SwiftShader') !== false || strpos($webglRenderer, 'ANGLE') !== false) {
            return true;
        }
        
        return false;
    }

    /**
     * Gets the number of active devices for a user
     * @param int $userId
     * @return int
     */
    private function getDeviceCount(int $userId): int {
        $query = "SELECT COUNT(DISTINCT fingerprint) 
                 FROM device_fingerprints 
                 WHERE user_id = ? 
                 AND last_used > DATE_SUB(NOW(), INTERVAL ? SECOND)";
        
        return $this->db->query($query, [$userId, $this->deviceTTL])->fetchColumn();
    }

    /**
     * Removes the oldest device for a user
     * @param int $userId
     * @return bool
     */
    private function removeOldestDevice(int $userId): bool {
        $query = "DELETE FROM device_fingerprints 
                 WHERE user_id = ? 
                 AND last_used = (
                     SELECT MIN(last_used) 
                     FROM device_fingerprints 
                     WHERE user_id = ?
                 ) LIMIT 1";
        
        return $this->db->query($query, [$userId, $userId])->rowCount() > 0;
    }

    /**
     * Gets recent failed login attempts for a user
     * @param int $userId
     * @return int
     */
    private function getRecentFailedAttempts(int $userId): int {
        $query = "SELECT COUNT(*) 
                 FROM activity_logs 
                 WHERE user_id = ? 
                 AND action = 'failed_login' 
                 AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)";
        
        return $this->db->query($query, [$userId])->fetchColumn();
    }

    /**
     * Checks if the user agent is unusual
     * @return bool
     */
    private function isUnusualUserAgent(): bool {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $commonBrowsers = ['Chrome', 'Firefox', 'Safari', 'Edge', 'Opera'];
        foreach ($commonBrowsers as $browser) {
            if (stripos($userAgent, $browser) !== false) {
                return false;
            }
        }
        return true;
    }

    /**
     * Checks if login time is unusual
     * @return bool
     */
    private function isUnusualTime(): bool {
        $hour = (int) date('H');
        return $hour >= 1 && $hour <= 5;
    }

    /**
     * Updates device last activity
     * @param int $userId
     * @param string $deviceFingerprint
     * @return bool
     */
    public function updateDeviceActivity(int $userId, string $deviceFingerprint): bool {
        $ipAddress = $this->getClientIp();
        $ipReputation = $this->checkIpReputation($ipAddress);
        
        $query = "UPDATE device_fingerprints 
                 SET last_used = NOW(), 
                     user_agent = ?, 
                     ip_address = ?,
                     ip_reputation = ?,
                     risk_score = ? 
                 WHERE user_id = ? 
                 AND fingerprint = ?";
        
        $riskScore = $this->assessDeviceRisk($deviceFingerprint, $userId) === 'high' ? 60 : 
                    ($this->assessDeviceRisk($deviceFingerprint, $userId) === 'medium' ? 30 : 0);
        
        return $this->db->query($query, [
            $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            $ipAddress,
            $ipReputation['status'],
            $riskScore,
            $userId,
            $deviceFingerprint
        ])->rowCount() > 0;
    }

    /**
     * Removes a specific device
     * @param int $userId
     * @param string $deviceFingerprint
     * @return bool
     */
    public function removeDevice(int $userId, string $deviceFingerprint): bool {
        $query = "DELETE FROM device_fingerprints 
                 WHERE user_id = ? 
                 AND fingerprint = ?";
        
        return $this->db->query($query, [$userId, $deviceFingerprint])->rowCount() > 0;
    }

    /**
     * Gets all devices for a user
     * @param int $userId
     * @return array
     */
    public function getUserDevices(int $userId): array {
        $query = "SELECT fingerprint, user_agent, ip_address, trust_status, risk_score, last_used 
                 FROM device_fingerprints 
                 WHERE user_id = ? 
                 AND last_used > DATE_SUB(NOW(), INTERVAL ? SECOND)";
        
        return $this->db->query($query, [$userId, $this->deviceTTL])->fetchAll();
    }

    /**
     * Marks a device as trusted after verification
     * @param int $userId
     * @param string $deviceFingerprint
     * @return bool
     */
    public function markDeviceAsTrusted(int $userId, string $deviceFingerprint): bool {
        $query = "UPDATE device_fingerprints 
                 SET trust_status = 'trusted' 
                 WHERE user_id = ? 
                 AND fingerprint = ?";
        
        return $this->db->query($query, [$userId, $deviceFingerprint])->rowCount() > 0;
    }

    /**
     * Logs device-related activity
     * @param int|null $userId
     * @param string $action
     * @param string $description
     * @param string $deviceFingerprint
     * @return bool
     */
    public function logDeviceActivity(?int $userId, string $action, string $description, string $deviceFingerprint): bool {
        return $this->db->logActivity(
            $userId,
            $action,
            $description,
            $this->getClientIp(),
            $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            $deviceFingerprint
        );
    }
}