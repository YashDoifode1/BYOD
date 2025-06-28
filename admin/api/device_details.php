<?php
/**
 * Device Management Dashboard
 * 
 * Provides detailed information about a specific device fingerprint
 * including security data, sessions, and management controls.
 * 
 * @package AdminDashboard
 * @author Your Name
 * @version 1.0.0
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/config.php';

// Check admin privileges
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header('HTTP/1.0 403 Forbidden');
    exit('<div class="error-container">Access Denied: Insufficient privileges</div>');
}

// Validate device ID
if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    header('HTTP/1.0 400 Bad Request');
    exit('<div class="error-container">Invalid Request: Device ID required</div>');
}

$deviceId = intval($_GET['id']);

try {
    // Database queries remain the same as your original
    // ... [Keep all your existing database query code] ...

    // Format dates
    $lastUsed = new DateTime($device['last_used']);
    $createdAt = new DateTime($device['created_at']);
    $updatedAt = new DateTime($device['updated_at']);
    $ipLastChecked = $device['last_checked'] ? new DateTime($device['last_checked']) : null;

    // Device information
    $deviceIcon = getDeviceIcon($device['user_agent']);
    $deviceType = getDeviceType($device['user_agent']);
    $sessionStats = calculateSessionStats($sessions);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Device Management | Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #4361ee;
            --primary-light: #e0e7ff;
            --danger: #ef4444;
            --danger-light: #fee2e2;
            --warning: #f59e0b;
            --warning-light: #fef3c7;
            --success: #10b981;
            --success-light: #d1fae5;
            --dark: #1e293b;
            --light: #f8fafc;
            --gray: #94a3b8;
            --gray-light: #f1f5f9;
        }
        
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: var(--gray-light);
            color: var(--dark);
            line-height: 1.6;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
            margin-bottom: 20px;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .header h1 {
            font-size: 24px;
            font-weight: 600;
            color: var(--dark);
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            border: none;
        }
        
        .btn i {
            margin-right: 8px;
        }
        
        .btn-primary {
            background-color: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background-color: #3a56d4;
        }
        
        .btn-secondary {
            background-color: white;
            color: var(--dark);
            border: 1px solid #cbd5e1;
        }
        
        .btn-secondary:hover {
            background-color: #f1f5f9;
        }
        
        .btn-danger {
            background-color: var(--danger);
            color: white;
        }
        
        .btn-danger:hover {
            background-color: #dc2626;
        }
        
        .card {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            overflow: hidden;
        }
        
        .card-header {
            padding: 16px 20px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .card-header h2 {
            font-size: 18px;
            font-weight: 600;
            display: flex;
            align-items: center;
        }
        
        .card-header h2 i {
            margin-right: 10px;
            color: var(--primary);
        }
        
        .card-body {
            padding: 20px;
        }
        
        .grid {
            display: grid;
            gap: 20px;
        }
        
        .grid-cols-1 {
            grid-template-columns: 1fr;
        }
        
        .grid-cols-2 {
            grid-template-columns: repeat(2, 1fr);
        }
        
        .grid-cols-3 {
            grid-template-columns: repeat(3, 1fr);
        }
        
        .alert {
            padding: 12px 16px;
            border-radius: 6px;
            margin-bottom: 20px;
            display: flex;
            align-items: flex-start;
        }
        
        .alert-danger {
            background-color: var(--danger-light);
            border-left: 4px solid var(--danger);
            color: var(--danger);
        }
        
        .alert i {
            margin-right: 10px;
            font-size: 18px;
        }
        
        .info-item {
            margin-bottom: 15px;
        }
        
        .info-label {
            font-size: 13px;
            color: var(--gray);
            margin-bottom: 4px;
            font-weight: 500;
        }
        
        .info-value {
            font-size: 14px;
            font-weight: 500;
        }
        
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .badge-success {
            background-color: var(--success-light);
            color: var(--success);
        }
        
        .badge-warning {
            background-color: var(--warning-light);
            color: var(--warning);
        }
        
        .badge-danger {
            background-color: var(--danger-light);
            color: var(--danger);
        }
        
        .badge-info {
            background-color: var(--primary-light);
            color: var(--primary);
        }
        
        .progress-bar {
            height: 6px;
            background-color: #e2e8f0;
            border-radius: 3px;
            overflow: hidden;
            margin-top: 5px;
        }
        
        .progress-fill {
            height: 100%;
            border-radius: 3px;
        }
        
        .progress-fill-success {
            background-color: var(--success);
        }
        
        .progress-fill-warning {
            background-color: var(--warning);
        }
        
        .progress-fill-danger {
            background-color: var(--danger);
        }
        
        .session-item {
            padding: 12px;
            border-radius: 6px;
            background-color: #f8fafc;
            margin-bottom: 10px;
            border-left: 3px solid var(--primary);
        }
        
        .log-item {
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 10px;
            border-left: 3px solid;
        }
        
        .log-item-high {
            border-color: var(--danger);
            background-color: var(--danger-light);
        }
        
        .log-item-medium {
            border-color: var(--warning);
            background-color: var(--warning-light);
        }
        
        .log-item-low {
            border-color: var(--primary);
            background-color: var(--primary-light);
        }
        
        .log-severity {
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .log-message {
            font-size: 13px;
        }
        
        .log-meta {
            font-size: 11px;
            color: var(--gray);
            margin-top: 5px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 5px;
            font-size: 13px;
            font-weight: 500;
            color: var(--dark);
        }
        
        .form-control {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 14px;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
        }
        
        .action-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 15px;
        }
        
        .action-card {
            background-color: white;
            border-radius: 8px;
            padding: 15px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .action-card h3 {
            font-size: 14px;
            margin-bottom: 10px;
            color: var(--dark);
        }
        
        @media (max-width: 768px) {
            .grid-cols-2, .grid-cols-3 {
                grid-template-columns: 1fr;
            }
            
            .header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div>
                <h1><i class="fas fa-laptop"></i> Device Management</h1>
                <div style="font-size: 13px; color: var(--gray); margin-top: 5px;">
                    Device ID: <?= $deviceId ?> | User: <?= htmlspecialchars($device['username']) ?>
                </div>
            </div>
            <div style="display: flex; gap: 10px;">
                <button class="btn btn-secondary" onclick="window.print()">
                    <i class="fas fa-print"></i> Print
                </button>
                <a href="/admin/devices" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </div>
        
        <!-- Alert Banner -->
        <?php if ($device['trust_status'] === 'untrusted' || $device['risk_score'] > 70): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-triangle"></i>
            <div>
                <strong>Security Alert:</strong> This device has been flagged as high risk.
                <?php if ($ipBlacklisted): ?>
                    The IP address is currently blacklisted.
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Main Content -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Left Column -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Device Information -->
                <div class="card">
                    <div class="card-header">
                        <h2><i class="fas <?= $deviceIcon ?>"></i> Device Information</h2>
                        <span class="badge <?= 
                            $device['trust_status'] === 'trusted' ? 'badge-success' : 
                            ($device['trust_status'] === 'pending' ? 'badge-warning' : 'badge-danger') 
                        ?>">
                            <?= ucfirst($device['trust_status']) ?>
                        </span>
                    </div>
                    <div class="card-body">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <div class="info-item">
                                    <div class="info-label">Device Type</div>
                                    <div class="info-value"><?= $deviceType ?></div>
                                </div>
                                
                                <div class="info-item">
                                    <div class="info-label">Risk Score</div>
                                    <div class="info-value">
                                        <?= $device['risk_score'] ?>%
                                        <div class="progress-bar">
                                            <div class="progress-fill <?= 
                                                $device['risk_score'] > 70 ? 'progress-fill-danger' : 
                                                ($device['risk_score'] > 30 ? 'progress-fill-warning' : 'progress-fill-success') 
                                            ?>" style="width: <?= $device['risk_score'] ?>%"></div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="info-item">
                                    <div class="info-label">First Seen</div>
                                    <div class="info-value">
                                        <?= $createdAt->format('M j, Y g:i A') ?>
                                        <div style="font-size: 12px; color: var(--gray);">
                                            <?= timeElapsedString($device['created_at']) ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div>
                                <div class="info-item">
                                    <div class="info-label">Fingerprint Hash</div>
                                    <div class="info-value" style="word-break: break-all; font-family: monospace; font-size: 12px;">
                                        <?= htmlspecialchars($device['fingerprint']) ?>
                                    </div>
                                </div>
                                
                                <div class="info-item">
                                    <div class="info-label">User Agent</div>
                                    <div class="info-value" style="font-size: 13px;">
                                        <?= htmlspecialchars($device['user_agent']) ?>
                                    </div>
                                </div>
                                
                                <div class="info-item">
                                    <div class="info-label">Last Activity</div>
                                    <div class="info-value">
                                        <?= $lastUsed->format('M j, Y g:i A') ?>
                                        <div style="font-size: 12px; color: var(--gray);">
                                            <?= timeElapsedString($device['last_used']) ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- User Information -->
                <div class="card">
                    <div class="card-header">
                        <h2><i class="fas fa-user"></i> User Information</h2>
                    </div>
                    <div class="card-body">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <div class="info-item">
                                    <div class="info-label">Username</div>
                                    <div class="info-value">
                                        <a href="/admin/users/view?id=<?= $device['user_id'] ?>" style="color: var(--primary); text-decoration: none;">
                                            <?= htmlspecialchars($device['username']) ?>
                                        </a>
                                    </div>
                                </div>
                                
                                <div class="info-item">
                                    <div class="info-label">Email</div>
                                    <div class="info-value"><?= htmlspecialchars($device['email']) ?></div>
                                </div>
                                
                                <div class="info-item">
                                    <div class="info-label">Role</div>
                                    <div class="info-value">
                                        <span class="badge badge-info"><?= ucfirst($device['role']) ?></span>
                                    </div>
                                </div>
                            </div>
                            
                            <div>
                                <div class="info-item">
                                    <div class="info-label">Active Sessions</div>
                                    <div class="info-value"><?= count($sessions) ?></div>
                                </div>
                                
                                <div class="info-item">
                                    <div class="info-label">Average Session Duration</div>
                                    <div class="info-value"><?= $sessionStats['avg_duration'] ?? 'N/A' ?></div>
                                </div>
                                
                                <div class="info-item">
                                    <div class="info-label">Total Sessions</div>
                                    <div class="info-value"><?= $device['session_count'] ?? 'N/A' ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Security Logs -->
                <div class="card">
                    <div class="card-header">
                        <h2><i class="fas fa-shield-alt"></i> Security Events</h2>
                        <a href="/admin/security-logs?device_id=<?= $deviceId ?>" style="font-size: 13px; color: var(--primary);">View All</a>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($logs)): ?>
                            <div style="max-height: 400px; overflow-y: auto;">
                                <?php foreach ($logs as $log): ?>
                                    <div class="log-item <?= 
                                        $log['severity'] === 'high' ? 'log-item-high' : 
                                        ($log['severity'] === 'medium' ? 'log-item-medium' : 'log-item-low') 
                                    ?>">
                                        <div class="log-severity">
                                            <?= ucfirst($log['severity']) ?>: <?= htmlspecialchars($log['action']) ?>
                                        </div>
                                        <div class="log-message">
                                            <?= htmlspecialchars($log['description']) ?>
                                        </div>
                                        <div class="log-meta">
                                            <?= timeElapsedString($log['created_at']) ?>
                                            <?php if ($log['user_name']): ?>
                                                • By: <?= htmlspecialchars($log['user_name']) ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div style="text-align: center; padding: 20px; color: var(--gray);">
                                No security events found for this device
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Right Column -->
            <div class="space-y-6">
                <!-- Network Information -->
                <div class="card">
                    <div class="card-header">
                        <h2><i class="fas fa-network-wired"></i> Network Information</h2>
                    </div>
                    <div class="card-body">
                        <div class="info-item">
                            <div class="info-label">IP Address</div>
                            <div class="info-value" style="display: flex; align-items: center; justify-content: space-between;">
                                <span style="font-family: monospace;"><?= htmlspecialchars($device['ip_address']) ?></span>
                                <?php if ($ipBlacklisted): ?>
                                    <span class="badge badge-danger">
                                        <i class="fas fa-ban"></i> Blacklisted
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-label">IP Reputation</div>
                            <div class="info-value">
                                <?php if ($device['ip_status']): ?>
                                    <span class="badge <?= $device['ip_score'] > 50 ? 'badge-danger' : 'badge-success' ?>">
                                        <?= htmlspecialchars($device['ip_status']) ?> (Score: <?= (int)$device['ip_score'] ?>)
                                    </span>
                                    <div style="font-size: 12px; color: var(--gray); margin-top: 5px;">
                                        Last checked: <?= $ipLastChecked ? timeElapsedString($device['last_checked']) : 'Never' ?>
                                    </div>
                                <?php else: ?>
                                    <span class="badge badge-secondary">No reputation data</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <?php if (!empty($relatedDevices)): ?>
                        <div class="info-item">
                            <div class="info-label">Other Devices from this IP</div>
                            <div style="max-height: 200px; overflow-y: auto; border: 1px solid #e2e8f0; border-radius: 6px; padding: 10px;">
                                <?php foreach ($relatedDevices as $related): ?>
                                    <div style="padding: 8px 0; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between;">
                                        <a href="/admin/devices/view?id=<?= $related['id'] ?>" style="color: var(--primary); text-decoration: none; font-size: 13px;">
                                            <?= htmlspecialchars($related['username']) ?>
                                        </a>
                                        <span style="font-size: 12px; color: var(--gray);">
                                            <?= timeElapsedString($related['last_used']) ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Active Sessions -->
                <div class="card">
                    <div class="card-header">
                        <h2><i class="fas fa-user-clock"></i> Active Sessions (<?= count($sessions) ?>)</h2>
                        <?php if (count($sessions) > 0): ?>
                            <form method="post" action="/admin/devices" style="display: inline;">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <input type="hidden" name="device_id" value="<?= $deviceId ?>">
                                <button type="submit" name="revoke_device" 
                                    style="background: none; border: none; color: var(--danger); font-size: 13px; cursor: pointer;"
                                    onclick="return confirm('Revoke all sessions for this device?')">
                                    <i class="fas fa-sign-out-alt"></i> Revoke All
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($sessions)): ?>
                            <div style="max-height: 300px; overflow-y: auto;">
                                <?php foreach ($sessions as $session): ?>
                                    <div class="session-item">
                                        <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                                            <strong style="font-size: 13px;">Session <?= substr($session['session_id'], 0, 8) ?>...</strong>
                                            <form method="post" action="/admin/devices" style="display: inline;">
                                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                                <input type="hidden" name="session_id" value="<?= $session['session_id'] ?>">
                                                <button type="submit" name="revoke_session" 
                                                    style="background: none; border: none; color: var(--danger); cursor: pointer;"
                                                    onclick="return confirm('Revoke this session?')">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </form>
                                        </div>
                                        <div style="font-size: 12px; color: var(--gray);">
                                            Last activity: <?= timeElapsedString($session['last_activity']) ?>
                                        </div>
                                        <div style="font-size: 12px; margin-top: 5px; color: var(--gray); word-break: break-all;">
                                            <?= htmlspecialchars($session['user_agent']) ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div style="text-align: center; padding: 20px; color: var(--gray);">
                                No active sessions for this device
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <div class="card">
                    <div class="card-header">
                        <h2><i class="fas fa-bolt"></i> Quick Actions</h2>
                    </div>
                    <div class="card-body">
                        <div class="action-grid">
                            <!-- Trust Status -->
                            <div class="action-card">
                                <h3>Change Trust Status</h3>
                                <form method="post" action="/admin/devices">
                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                    <input type="hidden" name="device_id" value="<?= $deviceId ?>">
                                    <div class="form-group">
                                        <select name="trust_status" class="form-control">
                                            <option value="trusted" <?= $device['trust_status'] === 'trusted' ? 'selected' : '' ?>>Trusted</option>
                                            <option value="pending" <?= $device['trust_status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                                            <option value="untrusted" <?= $device['trust_status'] === 'untrusted' ? 'selected' : '' ?>>Untrusted</option>
                                        </select>
                                    </div>
                                    <button type="submit" name="change_trust_status" class="btn btn-primary" style="width: 100%;">
                                        Update Status
                                    </button>
                                </form>
                            </div>
                            
                            <!-- IP Management -->
                            <div class="action-card">
                                <h3>IP Address</h3>
                                <?php if ($ipBlacklisted): ?>
                                    <form method="post" action="/admin/devices">
                                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                        <input type="hidden" name="ip_address" value="<?= htmlspecialchars($device['ip_address']) ?>">
                                        <button type="submit" name="remove_blacklist" class="btn btn-success" style="width: 100%;">
                                            <i class="fas fa-check-circle"></i> Remove Blacklist
                                        </button>
                                    </form>
                                    <div style="font-size: 12px; color: var(--gray); margin-top: 10px;">
                                        <strong>Reason:</strong> <?= htmlspecialchars($ipBlacklisted['reason']) ?><br>
                                        <?= $ipBlacklisted['status_text'] ?>
                                    </div>
                                <?php else: ?>
                                    <form method="post" action="/admin/devices">
                                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                        <input type="hidden" name="ip_address" value="<?= htmlspecialchars($device['ip_address']) ?>">
                                        <input type="hidden" name="reason" value="Suspicious device activity">
                                        <button type="submit" name="blacklist_ip" class="btn btn-danger" style="width: 100%;"
                                            onclick="return confirm('Blacklist this IP address?')">
                                            <i class="fas fa-ban"></i> Blacklist IP
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Device Actions -->
                            <div class="action-card">
                                <h3>Device Actions</h3>
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                                    <button class="btn btn-secondary" onclick="alert('Note functionality would go here')">
                                        <i class="fas fa-edit"></i> Add Note
                                    </button>
                                    <a href="/admin/devices/export?id=<?= $deviceId ?>" class="btn btn-secondary">
                                        <i class="fas fa-file-export"></i> Export
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Keep your existing helper functions here -->
    <?php
    // ... [Keep all your existing helper functions] ...
    ?>
</body>
</html>
<?php
} catch (PDOException $e) {
    header('HTTP/1.0 500 Internal Server Error');
    exit('<div class="error-container">Database Error: ' . htmlspecialchars($e->getMessage()) . '</div>');
}
?>