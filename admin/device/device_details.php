<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/config.php';
define('ABUSEIPDB_API_KEY', '9dd9a70ede1f6328f3a520c80a304016789a485bf4cf27dd4ee9a90a480d9b419fb35361a020225a');
define('IPQS_API_KEY', 'UIBQsNrKKJy9yOjGx4JLNPSJSE6XGxQy');

function isAdmin(): bool {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

if (!isAdmin()) {
    header('HTTP/1.0 403 Forbidden');
    exit('Access denied');
}

if (!isset($_GET['id'])) {
    header('HTTP/1.0 400 Bad Request');
    exit('Device ID required');
}

$deviceId = intval($_GET['id']);

// Function to fetch IP reputation from IPQualityScore
function getIPQualityScoreData($ip) {
    if (!defined('IPQS_API_KEY') || empty(IPQS_API_KEY)) {
        return null;
    }

    $url = "https://www.ipqualityscore.com/api/json/ip/" . IPQS_API_KEY . "/{$ip}";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    return json_decode($response, true);
}

// Function to fetch IP data from AbuseIPDB
function getAbuseIPDBData($ip) {
    if (!defined('ABUSEIPDB_API_KEY') || empty(ABUSEIPDB_API_KEY)) {
        return null;
    }

    $url = "https://api.abuseipdb.com/api/v2/check?ipAddress={$ip}";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Key: ' . ABUSEIPDB_API_KEY,
        'Accept: application/json'
    ]);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    return json_decode($response, true);
}

try {
    // Get device basic info
    $stmt = $pdo->prepare("
        SELECT df.*, u.username, u.email, u.role
        FROM device_fingerprints df
        JOIN users u ON df.user_id = u.id
        WHERE df.id = ?
    ");
    $stmt->execute([$deviceId]);
    $device = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$device) {
        header('HTTP/1.0 404 Not Found');
        exit('Device not found');
    }

    // Fetch IP data from APIs
    $ipQualityScoreData = getIPQualityScoreData($device['ip_address']);
    $abuseIPDBData = getAbuseIPDBData($device['ip_address']);

    // Get active sessions for this device
    $stmt = $pdo->prepare("
        SELECT session_id, created_at, last_activity
        FROM sessions
        WHERE device_fingerprint_id = ?
        ORDER BY last_activity DESC
    ");
    $stmt->execute([$deviceId]);
    $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get security logs related to this device
    $stmt = $pdo->prepare("
        SELECT l.*, u.username as user_name
        FROM security_logs l
        LEFT JOIN users u ON l.user_id = u.id
        WHERE (l.ip_address = ? OR l.description LIKE ?)
        ORDER BY l.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([
        $device['ip_address'],
        '%' . $device['fingerprint'] . '%'
    ]);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Check if IP is blacklisted in our database
    $stmt = $pdo->prepare("
        SELECT * FROM ip_blacklist 
        WHERE ip_address = ? 
        AND (expires_at IS NULL OR expires_at > NOW())
    ");
    $stmt->execute([$device['ip_address']]);
    $ipBlacklisted = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get similar devices from same IP
    $stmt = $pdo->prepare("
        SELECT df.id, df.trust_status, df.last_used, u.username, u.email
        FROM device_fingerprints df
        JOIN users u ON df.user_id = u.id
        WHERE df.ip_address = ? AND df.id != ?
        ORDER BY df.last_used DESC
        LIMIT 5
    ");
    $stmt->execute([$device['ip_address'], $deviceId]);
    $relatedDevices = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format dates for display
    $lastUsed = new DateTime($device['last_used']);
    $createdAt = new DateTime($device['created_at']);
    $updatedAt = new DateTime($device['updated_at']);

    // Determine device type icon
    $deviceIcon = getDeviceIcon($device['user_agent']);

    // Output HTML
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Device Details</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                background-color: #f4f4f4;
                margin: 0;
                padding: 20px;
                display: flex;
                justify-content: center;
                align-items: center;
                min-height: 100vh;
            }
            .container {
                max-width: 1500px;
                width: 100%;
                margin: 0 auto;
            }
            h1 {
                text-align: center;
                color: #333;
                font-size: 24px;
                margin-bottom: 10px;
            }
            h2 {
                color: #333;
                font-size: 18px;
                margin-bottom: 15px;
                text-align: left;
                border-bottom: 2px solid #007bff;
                padding-bottom: 5px;
            }
            p.subtitle {
                text-align: center;
                color: #666;
                font-size: 14px;
                margin-bottom: 20px;
            }
            table {
                width: 100%;
                border-collapse: collapse;
                background-color: #fff;
                border: 1px solid #ddd;
                margin-bottom: 20px;
                box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            }
            th, td {
                padding: 12px;
                text-align: left;
                border-bottom: 1px solid #ddd;
                font-size: 14px;
            }
            th {
                background-color: #f8f9fa;
                color: #333;
                font-weight: bold;
                width: 30%;
            }
            td {
                color: #555;
            }
            .status-trusted, .status-low {
                background-color: #d4edda;
                color: #155724;
                padding: 5px 10px;
                border-radius: 4px;
                display: inline-block;
            }
            .status-pending, .status-medium {
                background-color: #fff3cd;
                color: #856404;
                padding: 5px 10px;
                border-radius: 4px;
                display: inline-block;
            }
            .status-untrusted, .status-high {
                background-color: #f8d7da;
                color: #721c24;
                padding: 5px 10px;
                border-radius: 4px;
                display: inline-block;
            }
            .blacklisted {
                background-color: #f8d7da;
                color: #721c24;
                padding: 5px 10px;
                border-radius: 4px;
                display: inline-block;
            }
            .progress-bar {
                background-color: #e9ecef;
                border-radius: 4px;
                height: 10px;
                width: 100%;
                display: inline-block;
            }
            .progress-fill {
                height: 100%;
                border-radius: 4px;
            }
            .progress-fill.low { background-color: #28a745; }
            .progress-fill.medium { background-color: #ffc107; }
            .progress-fill.high { background-color: #dc3545; }
            a {
                color: #007bff;
                text-decoration: none;
            }
            a:hover {
                text-decoration: underline;
            }
            button, select {
                padding: 8px 12px;
                border: none;
                border-radius: 4px;
                cursor: pointer;
                font-size: 14px;
                margin: 5px;
            }
            button.blue {
                background-color: #007bff;
                color: #fff;
            }
            button.blue:hover {
                background-color: #0056b3;
            }
            button.red {
                background-color: #dc3545;
                color: #fff;
            }
            button.red:hover {
                background-color: #b02a37;
            }
            button.green {
                background-color: #28a745;
                color: #fff;
            }
            button.green:hover {
                background-color: #218838;
            }
            select {
                border: 1px solid #ddd;
                background-color: #fff;
            }
            .section {
                margin-bottom: 30px;
            }
            .icon {
                margin-right: 5px;
            }
            @media (max-width: 768px) {
                th, td {
                    font-size: 12px;
                    padding: 8px;
                }
                h1 {
                    font-size: 20px;
                }
                h2 {
                    font-size: 16px;
                }
                button, select {
                    font-size: 12px;
                    padding: 6px 10px;
                }
            }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>Device Details</h1>
            <p class="subtitle">Detailed information about device ID: <?= $deviceId ?></p>

            <div class="section">
                <h2>Device Information</h2>
                <table>
                    <tr>
                        <th>Device Fingerprint</th>
                        <td><span class="icon">🖥️</span><?= htmlspecialchars($device['fingerprint']) ?></td>
                    </tr>
                    <tr>
                        <th>Trust Status</th>
                        <td>
                            <span class="status-<?= $device['trust_status'] ?>">
                                <?= ucfirst($device['trust_status']) ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <th>Risk Score</th>
                        <td>
                            <div class="progress-bar">
                                <div class="progress-fill <?= $device['risk_score'] > 70 ? 'high' : ($device['risk_score'] > 30 ? 'medium' : 'low') ?>" 
                                     style="width: <?= $device['risk_score'] ?>%"></div>
                            </div>
                            <span><?= $device['risk_score'] ?>%</span>
                        </td>
                    </tr>
                    <tr>
                        <th>User Agent</th>
                        <td><?= htmlspecialchars($device['user_agent']) ?></td>
                    </tr>
                    <tr>
                        <th>Created</th>
                        <td><?= $createdAt->format('M j, Y g:i A') ?></td>
                    </tr>
                    <tr>
                        <th>Last Used</th>
                        <td><?= $lastUsed->format('M j, Y g:i A') ?> (<?= timeElapsedString($device['last_used']) ?>)</td>
                    </tr>
                </table>
            </div>

            <div class="section">
                <h2>Network Information</h2>
                <table>
                    <tr>
                        <th>IP Address</th>
                        <td>
                            <?= htmlspecialchars($device['ip_address']) ?>
                            <?php if ($ipBlacklisted): ?>
                                <span class="blacklisted">Blacklisted</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php if ($ipQualityScoreData && !isset($ipQualityScoreData['error'])): ?>
                        <tr>
                            <th>IPQualityScore Report</th>
                            <td>
                                <span class="status-<?= $ipQualityScoreData['fraud_score'] > 70 ? 'high' : ($ipQualityScoreData['fraud_score'] > 30 ? 'medium' : 'low') ?>">
                                    Fraud Score: <?= (int)$ipQualityScoreData['fraud_score'] ?> / 100
                                </span>
                                <table class="inner-table">
                                    <tr><td>Proxy/VPN:</td><td><?= $ipQualityScoreData['proxy'] ? '⚠️ Yes' : '✓ No' ?></td></tr>
                                    <tr><td>Tor:</td><td><?= $ipQualityScoreData['tor'] ? '⚠️ Yes' : '✓ No' ?></td></tr>
                                    <tr><td>Crawler:</td><td><?= $ipQualityScoreData['is_crawler'] ? '⚠️ Yes' : '✓ No' ?></td></tr>
                                    <tr><td>Bot:</td><td><?= $ipQualityScoreData['bot_status'] ? '⚠️ Yes' : '✓ No' ?></td></tr>
                                    <?php if (!empty($ipQualityScoreData['country_code'])): ?>
                                        <tr><td>Country:</td><td><?= htmlspecialchars($ipQualityScoreData['country_code']) ?></td></tr>
                                    <?php endif; ?>
                                    <?php if (!empty($ipQualityScoreData['region'])): ?>
                                        <tr><td>Region:</td><td><?= htmlspecialchars($ipQualityScoreData['region']) ?></td></tr>
                                    <?php endif; ?>
                                    <?php if (!empty($ipQualityScoreData['city'])): ?>
                                        <tr><td>City:</td><td><?= htmlspecialchars($ipQualityScoreData['city']) ?></td></tr>
                                    <?php endif; ?>
                                    <?php if (!empty($ipQualityScoreData['ISP'])): ?>
                                        <tr><td>ISP:</td><td><?= htmlspecialchars($ipQualityScoreData['ISP']) ?></td></tr>
                                    <?php endif; ?>
                                </table>
                            </td>
                        </tr>
                    <?php endif; ?>
                    <?php if ($abuseIPDBData && !isset($abuseIPDBData['errors'])): ?>
                        <?php $abuseData = $abuseIPDBData['data']; ?>
                        <tr>
                            <th>AbuseIPDB Report</th>
                            <td>
                                <span class="status-<?= $abuseData['abuseConfidenceScore'] > 70 ? 'high' : ($abuseData['abuseConfidenceScore'] > 30 ? 'medium' : 'low') ?>">
                                    Abuse Confidence: <?= (int)$abuseData['abuseConfidenceScore'] ?> / 100
                                </span>
                                <span>Reports: <?= (int)$abuseData['totalReports'] ?></span>
                                <table class="inner-table">
                                    <tr><td>ISP:</td><td><?= htmlspecialchars($abuseData['isp']) ?></td></tr>
                                    <tr><td>Domain:</td><td><?= !empty($abuseData['domain']) ? htmlspecialchars($abuseData['domain']) : 'N/A' ?></td></tr>
                                    <tr><td>Usage Type:</td><td><?= !empty($abuseData['usageType']) ? htmlspecialchars($abuseData['usageType']) : 'N/A' ?></td></tr>
                                    <tr><td>Last Reported:</td><td><?= !empty($abuseData['lastReportedAt']) ? htmlspecialchars($abuseData['lastReportedAt']) : 'Never' ?></td></tr>
                                    <?php if (!empty($abuseData['countryCode'])): ?>
                                        <tr><td>Location:</td><td><?= htmlspecialchars($abuseData['countryName']) ?> (<?= htmlspecialchars($abuseData['countryCode']) ?>)</td></tr>
                                    <?php endif; ?>
                                </table>
                            </td>
                        </tr>
                    <?php endif; ?>
                    <?php if (!empty($relatedDevices)): ?>
                        <tr>
                            <th>Other Devices from this IP</th>
                            <td>
                                <table class="inner-table">
                                    <?php foreach ($relatedDevices as $related): ?>
                                        <tr>
                                            <td>
                                                <a href="#" onclick="openDeviceModal('<?= $related['id'] ?>'); return false;">
                                                    <?= htmlspecialchars($related['username']) ?>
                                                </a>
                                            </td>
                                            <td><?= timeElapsedString($related['last_used']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </table>
                            </td>
                        </tr>
                    <?php endif; ?>
                </table>
            </div>

            <div class="section">
                <h2>Active Sessions (<?= count($sessions) ?>)</h2>
                <table>
                    <tr>
                        <th>Session ID</th>
                        <th>Last Activity</th>
                        <th>Action</th>
                    </tr>
                    <?php if (!empty($sessions)): ?>
                        <?php foreach ($sessions as $session): ?>
                            <tr>
                                <td><?= substr($session['session_id'], 0, 8) ?>...</td>
                                <td><?= timeElapsedString($session['last_activity']) ?></td>
                                <td>
                                    <form method="post" action="/admin/devices.php" style="display:inline;">
                                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                        <input type="hidden" name="session_id" value="<?= $session['session_id'] ?>">
                                        <button type="submit" name="revoke_session" class="red"
                                                onclick="return confirm('Revoke this session?')">
                                            Revoke
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="3">No active sessions for this device</td>
                        </tr>
                    <?php endif; ?>
                </table>
            </div>

            <div class="section">
                <h2>Recent Security Events</h2>
                <table>
                    <tr>
                        <th>Action</th>
                        <th>Time</th>
                        <th>Description</th>
                        <th>User</th>
                    </tr>
                    <?php if (!empty($logs)): ?>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><?= htmlspecialchars($log['action']) ?></td>
                                <td><?= timeElapsedString($log['created_at']) ?></td>
                                <td><?= htmlspecialchars($log['description']) ?></td>
                                <td><?= $log['user_name'] ? htmlspecialchars($log['user_name']) : 'N/A' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4">No security events found for this device</td>
                        </tr>
                    <?php endif; ?>
                </table>
            </div>

            <div class="section">
                <h2>Device Actions</h2>
                <table>
                    <tr>
                        <th>Action</th>
                        <td>
                            <form method="post" action="/admin/devices.php" style="display:inline-block; margin-right:10px;">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <input type="hidden" name="device_id" value="<?= $deviceId ?>">
                                <label style="display:block; margin-bottom:5px;">Change Trust Status</label>
                                <select name="trust_status">
                                    <option value="trusted" <?= $device['trust_status'] === 'trusted' ? 'selected' : '' ?>>Trusted</option>
                                    <option value="pending" <?= $device['trust_status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                                    <option value="untrusted" <?= $device['trust_status'] === 'untrusted' ? 'selected' : '' ?>>Untrusted</option>
                                </select>
                                <button type="submit" name="change_trust_status" class="blue">Update</button>
                            </form>
                        </td>
                    </tr>
                    <tr>
                        <th>Session Control</th>
                        <td>
                            <form method="post" action="/admin/devices.php" style="display:inline-block;">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <input type="hidden" name="device_id" value="<?= $deviceId ?>">
                                <button type="submit" name="revoke_device" class="red"
                                        onclick="return confirm('Revoke all sessions for this device?')">
                                    Revoke All Sessions
                                </button>
                            </form>
                        </td>
                    </tr>
                    <tr>
                        <th>IP Address Management</th>
                        <td>
                            <?php if ($ipBlacklisted): ?>
                                <form method="post" action="/admin/devices.php" style="display:inline-block;">
                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                    <input type="hidden" name="ip_address" value="<?= htmlspecialchars($device['ip_address']) ?>">
                                    <button type="submit" name="remove_blacklist" class="green">
                                        Remove from Blacklist
                                    </button>
                                </form>
                            <?php else: ?>
                                <form method="post" action="/admin/devices.php" style="display:inline-block;">
                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                    <input type="hidden" name="ip_address" value="<?= htmlspecialchars($device['ip_address']) ?>">
                                    <input type="hidden" name="reason" value="Suspicious device activity">
                                    <button type="submit" name="blacklist_ip" class="red"
                                            onclick="return confirm('Blacklist this IP address?')">
                                        Blacklist IP
                                    </button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
    </body>
    </html>
    <?php
} catch (PDOException $e) {
    header('HTTP/1.0 500 Internal Server Error');
    exit('Database error: ' . $e->getMessage());
}

// Helper function to display time elapsed
function timeElapsedString($datetime) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    $diff->w = floor($diff->d / 7);
    $diff->d -= $diff->w * 7;

    $string = array(
        'y' => 'year',
        'm' => 'month',
        'w' => 'week',
        'd' => 'day',
        'h' => 'hour',
        'i' => 'minute',
        's' => 'second',
    );
    
    foreach ($string as $k => &$v) {
        if ($diff->$k) {
            $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
        } else {
            unset($string[$k]);
        }
    }
    
    return $string ? implode(', ', $string) . ' ago' : 'just now';
}

// Helper function to get device icon
function getDeviceIcon($userAgent) {
    if (empty($userAgent)) return '🖥️';
    
    $userAgent = strtolower($userAgent);
    if (strpos($userAgent, 'windows') !== false) return '🖼️';
    if (strpos($userAgent, 'mac') !== false || strpos($userAgent, 'iphone') !== false || strpos($userAgent, 'ipad') !== false) return '🍎';
    if (strpos($userAgent, 'linux') !== false) return '🐧';
    if (strpos($userAgent, 'android') !== false) return '🤖';
    if (strpos($userAgent, 'mobile') !== false) return '📱';
    
    return '🖥️';
}
?>