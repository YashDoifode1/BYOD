<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/config.php';

// Verify admin access
if (!isAdmin()) {
    header('HTTP/1.0 403 Forbidden');
    exit('Access denied');
}

if (!isset($_GET['id'])) {
    header('HTTP/1.0 400 Bad Request');
    exit('Device ID required');
}

$deviceId = intval($_GET['id']);

try {
    // Get device basic info
    $stmt = $pdo->prepare("
        SELECT df.*, u.username, u.email, u.role,
               ip.status as ip_status, ip.score as ip_score
        FROM device_fingerprints df
        JOIN users u ON df.user_id = u.id
        LEFT JOIN ip_reputation_cache ip ON df.ip_address = ip.ip_address
        WHERE df.id = ?
    ");
    $stmt->execute([$deviceId]);
    $device = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$device) {
        header('HTTP/1.0 404 Not Found');
        exit('Device not found');
    }

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

    // Check if IP is blacklisted
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
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Device Info Card -->
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4 flex items-center">
                <i class="fas <?= $deviceIcon ?> mr-2 text-blue-600"></i>
                Device Information
            </h3>
            
            <div class="space-y-4">
                <div>
                    <p class="text-sm text-gray-500">Device Fingerprint</p>
                    <p class="mt-1 font-mono text-sm text-gray-900 break-all"><?= htmlspecialchars($device['fingerprint']) ?></p>
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <p class="text-sm text-gray-500">Trust Status</p>
                        <span class="mt-1 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                            <?= $device['trust_status'] === 'trusted' ? 'bg-green-100 text-green-800' : 
                               ($device['trust_status'] === 'pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') ?>">
                            <?= ucfirst($device['trust_status']) ?>
                        </span>
                    </div>
                    
                    <div>
                        <p class="text-sm text-gray-500">Risk Score</p>
                        <div class="mt-1 flex items-center">
                            <div class="w-full bg-gray-200 rounded-full h-2.5">
                                <div class="bg-<?= $device['risk_score'] > 70 ? 'red' : ($device['risk_score'] > 30 ? 'yellow' : 'green') ?>-600 h-2.5 rounded-full" 
                                     style="width: <?= $device['risk_score'] ?>%"></div>
                            </div>
                            <span class="ml-2 text-xs font-medium text-gray-500"><?= $device['risk_score'] ?>%</span>
                        </div>
                    </div>
                </div>
                
                <div>
                    <p class="text-sm text-gray-500">User Agent</p>
                    <p class="mt-1 text-sm text-gray-900"><?= htmlspecialchars($device['user_agent']) ?></p>
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <p class="text-sm text-gray-500">Created</p>
                        <p class="mt-1 text-sm text-gray-900">
                            <?= $createdAt->format('M j, Y g:i A') ?>
                        </p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Last Used</p>
                        <p class="mt-1 text-sm text-gray-900">
                            <?= $lastUsed->format('M j, Y g:i A') ?>
                            <span class="text-xs text-gray-500">(<?= timeElapsedString($device['last_used']) ?>)</span>
                        </p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Network Info Card -->
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">
                <i class="fas fa-network-wired mr-2 text-blue-600"></i>
                Network Information
            </h3>
            
            <div class="space-y-4">
                <div>
                    <p class="text-sm text-gray-500">IP Address</p>
                    <div class="mt-1 flex items-center">
                        <p class="font-mono text-sm text-gray-900"><?= htmlspecialchars($device['ip_address']) ?></p>
                        <?php if ($ipBlacklisted): ?>
                            <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800">
                                <i class="fas fa-ban mr-1"></i> Blacklisted
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div>
                    <p class="text-sm text-gray-500">IP Reputation</p>
                    <?php if ($device['ip_status']): ?>
                        <div class="mt-1 flex items-center">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                <?= $device['ip_score'] > 50 ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800' ?>">
                                <?= htmlspecialchars($device['ip_status']) ?> (Score: <?= (int)$device['ip_score'] ?>)
                            </span>
                            <span class="ml-2 text-xs text-gray-500">
                                Last checked: <?= timeElapsedString($device['last_checked']) ?>
                            </span>
                        </div>
                    <?php else: ?>
                        <p class="mt-1 text-sm text-gray-500">No reputation data available</p>
                    <?php endif; ?>
                </div>
                
                <?php if (!empty($relatedDevices)): ?>
                <div>
                    <p class="text-sm text-gray-500">Other Devices from this IP</p>
                    <div class="mt-1 space-y-2">
                        <?php foreach ($relatedDevices as $related): ?>
                            <div class="flex items-center justify-between text-sm">
                                <a href="#" onclick="openDeviceModal('<?= $related['id'] ?>'); return false;" 
                                   class="text-blue-600 hover:underline">
                                    <?= htmlspecialchars($related['username']) ?>
                                </a>
                                <span class="text-xs text-gray-500">
                                    <?= timeElapsedString($related['last_used']) ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Active Sessions Card -->
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">
                <i class="fas fa-user-clock mr-2 text-blue-600"></i>
                Active Sessions (<?= count($sessions) ?>)
            </h3>
            
            <?php if (!empty($sessions)): ?>
                <div class="space-y-3">
                    <?php foreach ($sessions as $session): ?>
                        <div class="flex items-center justify-between text-sm p-2 hover:bg-gray-50 rounded">
                            <div>
                                <p class="font-medium">Session <?= substr($session['session_id'], 0, 8) ?>...</p>
                                <p class="text-xs text-gray-500">
                                    Last activity: <?= timeElapsedString($session['last_activity']) ?>
                                </p>
                            </div>
                            <form method="post" action="/admin/devices.php" class="inline">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <input type="hidden" name="session_id" value="<?= $session['session_id'] ?>">
                                <button type="submit" name="revoke_session" 
                                    class="text-red-600 hover:text-red-900 text-xs"
                                    onclick="return confirm('Revoke this session?')">
                                    <i class="fas fa-sign-out-alt"></i> Revoke
                                </button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-sm text-gray-500">No active sessions for this device</p>
            <?php endif; ?>
        </div>
        
        <!-- Security Logs Card -->
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">
                <i class="fas fa-shield-alt mr-2 text-blue-600"></i>
                Recent Security Events
            </h3>
            
            <?php if (!empty($logs)): ?>
                <div class="space-y-3">
                    <?php foreach ($logs as $log): ?>
                        <div class="text-sm p-2 hover:bg-gray-50 rounded">
                            <div class="flex justify-between">
                                <span class="font-medium"><?= htmlspecialchars($log['action']) ?></span>
                                <span class="text-xs text-gray-500">
                                    <?= timeElapsedString($log['created_at']) ?>
                                </span>
                            </div>
                            <p class="text-xs text-gray-600 mt-1"><?= htmlspecialchars($log['description']) ?></p>
                            <?php if ($log['user_name']): ?>
                                <p class="text-xs text-gray-500 mt-1">
                                    By: <?= htmlspecialchars($log['user_name']) ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-sm text-gray-500">No security events found for this device</p>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="mt-6 bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-medium text-gray-900 mb-4">
            <i class="fas fa-cogs mr-2 text-blue-600"></i>
            Device Actions
        </h3>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <!-- Change Trust Status -->
            <form method="post" action="/admin/devices.php" class="space-y-2">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="device_id" value="<?= $deviceId ?>">
                
                <label class="block text-sm font-medium text-gray-700">Change Trust Status</label>
                <div class="flex space-x-2">
                    <select name="trust_status" class="flex-1 rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                        <option value="trusted" <?= $device['trust_status'] === 'trusted' ? 'selected' : '' ?>>Trusted</option>
                        <option value="pending" <?= $device['trust_status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="untrusted" <?= $device['trust_status'] === 'untrusted' ? 'selected' : '' ?>>Untrusted</option>
                    </select>
                    <button type="submit" name="change_trust_status" 
                        class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        Update
                    </button>
                </div>
            </form>
            
            <!-- Revoke All Sessions -->
            <form method="post" action="/admin/devices.php" class="space-y-2">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="device_id" value="<?= $deviceId ?>">
                
                <label class="block text-sm font-medium text-gray-700">Session Control</label>
                <button type="submit" name="revoke_device" 
                    class="w-full inline-flex justify-center items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md shadow-sm text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500"
                    onclick="return confirm('Revoke all sessions for this device?')">
                    <i class="fas fa-sign-out-alt mr-2"></i> Revoke All Sessions
                </button>
            </form>
            
            <!-- IP Management -->
            <div class="space-y-2">
                <label class="block text-sm font-medium text-gray-700">IP Address Management</label>
                <?php if ($ipBlacklisted): ?>
                    <form method="post" action="/admin/devices.php" class="inline-flex w-full">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <input type="hidden" name="ip_address" value="<?= htmlspecialchars($device['ip_address']) ?>">
                        <button type="submit" name="remove_blacklist" 
                            class="w-full inline-flex justify-center items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md shadow-sm text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                            <i class="fas fa-check-circle mr-2"></i> Remove from Blacklist
                        </button>
                    </form>
                <?php else: ?>
                    <form method="post" action="/admin/devices.php" class="inline-flex w-full">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <input type="hidden" name="ip_address" value="<?= htmlspecialchars($device['ip_address']) ?>">
                        <input type="hidden" name="reason" value="Suspicious device activity">
                        <button type="submit" name="blacklist_ip" 
                            class="w-full inline-flex justify-center items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md shadow-sm text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500"
                            onclick="return confirm('Blacklist this IP address?')">
                            <i class="fas fa-ban mr-2"></i> Blacklist IP
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
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
    if (empty($userAgent)) return 'fa-laptop';
    
    $userAgent = strtolower($userAgent);
    if (strpos($userAgent, 'windows') !== false) return 'fa-windows';
    if (strpos($userAgent, 'mac') !== false || strpos($userAgent, 'iphone') !== false || strpos($userAgent, 'ipad') !== false) return 'fa-apple';
    if (strpos($userAgent, 'linux') !== false) return 'fa-linux';
    if (strpos($userAgent, 'android') !== false) return 'fa-android';
    if (strpos($userAgent, 'mobile') !== false) return 'fa-mobile-alt';
    
    return 'fa-laptop';
}
?>