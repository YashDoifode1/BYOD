<?php
/**
 * Enhanced Admin Device Management Panel
 */

// Verify admin access
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/config.php';

// CSRF Protection
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/**
 * Logs an admin action to the activity_logs table
 * @param int|null $userId The ID of the admin performing the action
 * @param string $action The type of action performed
 * @param string $description A description of the action
 * @return bool Returns true on success, false on failure
 */
function logAdminAction(?int $userId, string $action, string $description): bool {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            INSERT INTO activity_logs (
                user_id, action, description, ip_address, user_agent, device_fingerprint, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? NULL;
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? NULL;
        $deviceFingerprint = $_SESSION['device_fingerprint'] ?? NULL;
        
        $stmt->execute([
            $userId,
            $action,
            $description,
            $ipAddress,
            $userAgent,
            $deviceFingerprint
        ]);
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        error_log("Error logging admin action: " . $e->getMessage());
        return false;
    }
}

// Handle actions
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Invalid CSRF token";
    } else {
        try {
            if (isset($_POST['revoke_device'])) {
                // Revoke all sessions for a device
                $stmt = $pdo->prepare("
                    DELETE s FROM sessions s
                    WHERE s.device_fingerprint_id = ?
                ");
                $stmt->execute([$_POST['device_id']]);
                $message = "All sessions for device revoked successfully";
                
                // Log this action
                logAdminAction($_SESSION['user_id'], 'device_revoke', "Revoked all sessions for device ID: {$_POST['device_id']}");
                
            } elseif (isset($_POST['change_trust_status'])) {
                // Update device trust status
                $stmt = $pdo->prepare("
                    UPDATE device_fingerprints 
                    SET trust_status = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$_POST['trust_status'], $_POST['device_id']]);
                $message = "Device trust status updated successfully";
                
                // Log this action
                logAdminAction($_SESSION['user_id'], 'device_trust_change', "Changed device ID {$_POST['device_id']} to {$_POST['trust_status']}");
                
            } elseif (isset($_POST['blacklist_ip'])) {
                // Add IP to blacklist
                $expires = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null;
                $stmt = $pdo->prepare("
                    INSERT INTO ip_blacklist 
                    (ip_address, reason, expires_at, created_by)
                    VALUES (?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE 
                    reason = VALUES(reason), 
                    expires_at = VALUES(expires_at)
                ");
                $stmt->execute([
                    $_POST['ip_address'],
                    $_POST['reason'],
                    $expires,
                    $_SESSION['user_id']
                ]);
                $message = "IP address added to blacklist";
                
                // Log this action
                logAdminAction($_SESSION['user_id'], 'ip_blacklist_add', "Blacklisted IP: {$_POST['ip_address']}");
                
            } elseif (isset($_POST['remove_blacklist'])) {
                // Remove IP from blacklist
                $stmt = $pdo->prepare("
                    DELETE FROM ip_blacklist WHERE ip_address = ?
                ");
                $stmt->execute([$_POST['ip_address']]);
                $message = "IP address removed from blacklist";
                
                // Log this action
                logAdminAction($_SESSION['user_id'], 'ip_blacklist_remove', "Removed IP from blacklist: {$_POST['ip_address']}");
            }
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}

// Get filter parameters
$filter_status = $_GET['status'] ?? 'all';
$filter_user = $_GET['user'] ?? '';
$filter_ip = $_GET['ip'] ?? '';

// Build device query with filters
$device_query = "
    SELECT df.*, u.username, u.email, u.role,
           COUNT(s.session_id) as active_sessions,
           ip.status as ip_status, ip.score as ip_score
    FROM device_fingerprints df
    JOIN users u ON df.user_id = u.id
    LEFT JOIN sessions s ON df.id = s.device_fingerprint_id
    LEFT JOIN ip_reputation_cache ip ON df.ip_address = ip.ip_address
    WHERE 1=1
";

$params = [];
$types = '';

// Apply filters
if ($filter_status !== 'all') {
    $device_query .= " AND df.trust_status = ?";
    $params[] = $filter_status;
    $types .= 's';
}

if (!empty($filter_user)) {
    $device_query .= " AND (u.username LIKE ? OR u.email LIKE ?)";
    $params[] = "%$filter_user%";
    $params[] = "%$filter_user%";
    $types .= 'ss';
}

if (!empty($filter_ip)) {
    $device_query .= " AND df.ip_address LIKE ?";
    $params[] = "%$filter_ip%";
    $types .= 's';
}

$device_query .= " GROUP BY df.id ORDER BY df.last_used DESC";

// Get all devices with user info
$devices = [];
try {
    $stmt = $pdo->prepare($device_query);
    $stmt->execute($params);
    $devices = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error fetching devices: " . $e->getMessage();
}

// Get IP blacklist
$blacklist = [];
try {
    $stmt = $pdo->prepare("
        SELECT b.*, u.username as created_by_name
        FROM ip_blacklist b
        LEFT JOIN users u ON b.created_by = u.id
        WHERE b.expires_at IS NULL OR b.expires_at > NOW()
        ORDER BY b.created_at DESC
    ");
    $stmt->execute();
    $blacklist = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error fetching blacklist: " . $e->getMessage();
}

// Get device stats for charts
$device_stats = [];
try {
    $stmt = $pdo->prepare("
        SELECT 
            trust_status, 
            COUNT(*) as count,
            COUNT(CASE WHEN last_used > DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as active_7days,
            COUNT(CASE WHEN last_used > DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as active_30days
        FROM device_fingerprints
        GROUP BY trust_status
    ");
    $stmt->execute();
    $device_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching device stats: " . $e->getMessage());
}

// Function to get device icon
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

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Device Management | Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.css">
    <style>
        :root {
            --color-primary: #3b82f6;
            --color-success: #10b981;
            --color-warning: #f59e0b;
            --color-danger: #ef4444;
        }
        .admin-header {
            background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 100%);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .card {
            transition: all 0.3s ease;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }
        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px rgba(0, 0, 0, 0.1);
        }
        .badge-trusted { background-color: var(--color-success); }
        .badge-pending { background-color: var(--color-warning); }
        .badge-untrusted { background-color: var(--color-danger); }
        .nav-link {
            transition: all 0.2s ease;
            border-bottom: 2px solid transparent;
        }
        .nav-link:hover {
            color: var(--color-primary);
            border-bottom-color: var(--color-primary);
        }
        .nav-link.active {
            color: var(--color-primary);
            border-bottom-color: var(--color-primary);
            font-weight: 600;
        }
        .table-row:hover {
            background-color: #f8fafc;
        }
    </style>
</head>
<body class="bg-gray-50 font-sans antialiased">
    <!-- Admin Navigation -->
    <div class="flex">
        <!-- Sidebar -->
        <?php include __DIR__ . '/../includes/header.php'; ?>
        
        <!-- Main Content -->
        <div class="flex-1 p-8">
            <div class="max-w-7xl mx-auto">
                <!-- Page Header -->
                <div class="mb-8">
                    <h1 class="text-2xl font-bold text-gray-800 flex items-center">
                        <i class="fas fa-laptop mr-3 text-blue-600"></i>
                        Device Management
                    </h1>
                    <p class="text-gray-600 mt-2">Manage and monitor all registered devices and IP restrictions</p>
                </div>
                
                <!-- Messages -->
                <?php if ($message): ?>
                    <div class="bg-green-50 border-l-4 border-green-500 p-4 mb-6 rounded-md shadow-sm">
                        <div class="flex items-center">
                            <i class="fas fa-check-circle text-green-500 mr-3"></i>
                            <div>
                                <p class="font-medium text-green-800"><?= htmlspecialchars($message) ?></p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6 rounded-md shadow-sm">
                        <div class="flex items-center">
                            <i class="fas fa-exclamation-circle text-red-500 mr-3"></i>
                            <div>
                                <p class="font-medium text-red-800"><?= htmlspecialchars($error) ?></p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Stats Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <div class="bg-white rounded-lg shadow card p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-500">Total Devices</p>
                                <h3 class="text-2xl font-bold text-gray-800 mt-1"><?= count($devices) ?></h3>
                            </div>
                            <div class="bg-blue-100 p-3 rounded-full">
                                <i class="fas fa-laptop text-blue-600 text-xl"></i>
                            </div>
                        </div>
                        <div class="mt-4">
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-gray-500">Active (7d)</span>
                                <span class="font-medium">
                                    <?= array_sum(array_column($device_stats, 'active_7days')) ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-lg shadow card p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-500">Trusted Devices</p>
                                <h3 class="text-2xl font-bold text-green-600 mt-1">
                                    <?= count(array_filter($devices, fn($d) => $d['trust_status'] === 'trusted')) ?>
                                </h3>
                            </div>
                            <div class="bg-green-100 p-3 rounded-full">
                                <i class="fas fa-shield-alt text-green-600 text-xl"></i>
                            </div>
                        </div>
                        <div class="mt-4">
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-gray-500">Active (30d)</span>
                                <span class="font-medium">
                                    <?= array_sum(array_column($device_stats, 'active_30days')) ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-lg shadow card p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-500">Pending Review</p>
                                <h3 class="text-2xl font-bold text-yellow-500 mt-1">
                                    <?= count(array_filter($devices, fn($d) => $d['trust_status'] === 'pending')) ?>
                                </h3>
                            </div>
                            <div class="bg-yellow-100 p-3 rounded-full">
                                <i class="fas fa-clock text-yellow-500 text-xl"></i>
                            </div>
                        </div>
                        <div class="mt-4">
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-gray-500">New (7d)</span>
                                <span class="font-medium">
                                    <?= count(array_filter($devices, fn($d) => strtotime($d['created_at']) > strtotime('-7 days'))) ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-lg shadow card p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-500">Blacklisted IPs</p>
                                <h3 class="text-2xl font-bold text-red-600 mt-1"><?= count($blacklist) ?></h3>
                            </div>
                            <div class="bg-red-100 p-3 rounded-full">
                                <i class="fas fa-ban text-red-600 text-xl"></i>
                            </div>
                        </div>
                        <div class="mt-4">
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-gray-500">Permanent</span>
                                <span class="font-medium">
                                    <?= count(array_filter($blacklist, fn($b) => empty($b['expires_at']))) ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Device Status Chart -->
                <div class="bg-white rounded-lg shadow card p-6 mb-8">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-lg font-semibold text-gray-800">Device Status Distribution</h2>
                        <div class="flex space-x-2">
                            <button class="text-xs bg-blue-50 text-blue-600 px-3 py-1 rounded-full">All Time</button>
                            <button class="text-xs bg-gray-100 text-gray-600 px-3 py-1 rounded-full">30 Days</button>
                            <button class="text-xs bg-gray-100 text-gray-600 px-3 py-1 rounded-full">7 Days</button>
                        </div>
                    </div>
                    <div class="h-64">
                        <canvas id="deviceStatusChart"></canvas>
                    </div>
                </div>
                
                <!-- Device Management Section -->
                <div class="bg-white rounded-lg shadow card overflow-hidden mb-8">
                    <div class="border-b border-gray-200 px-6 py-4 flex items-center justify-between">
                        <div>
                            <h2 class="text-lg font-semibold text-gray-800">
                                <i class="fas fa-laptop-medical mr-2 text-blue-600"></i>
                                Registered Devices
                            </h2>
                            <p class="text-sm text-gray-500 mt-1">Manage all devices that have accessed the system</p>
                        </div>
                        <div class="flex space-x-3">
                            <a href="#" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                                <i class="fas fa-file-export mr-1"></i> Export
                            </a>
                        </div>
                    </div>
                    
                    <!-- Filters -->
                    <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                        <form method="get" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                                <select name="status" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                                    <option value="all" <?= $filter_status === 'all' ? 'selected' : '' ?>>All Statuses</option>
                                    <option value="trusted" <?= $filter_status === 'trusted' ? 'selected' : '' ?>>Trusted</option>
                                    <option value="pending" <?= $filter_status === 'pending' ? 'selected' : '' ?>>Pending</option>
                                    <option value="untrusted" <?= $filter_status === 'untrusted' ? 'selected' : '' ?>>Untrusted</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">User</label>
                                <input type="text" name="user" value="<?= htmlspecialchars($filter_user) ?>" placeholder="Username or email" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">IP Address</label>
                                <input type="text" name="ip" value="<?= htmlspecialchars($filter_ip) ?>" placeholder="Filter by IP" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                            </div>
                            <div class="flex items-end">
                                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 text-sm">
                                    <i class="fas fa-filter mr-1"></i> Apply Filters
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Devices Table -->
<div class="overflow-x-auto">
    <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
            <tr>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Device</th>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Network</th>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Risk Score</th>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">IP Reputation</th>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Activity</th>
                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
            </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">
            <?php foreach ($devices as $device): ?>
            <tr class="table-row">
                <td class="px-6 py-4 whitespace-nowrap">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 h-10 w-10 rounded-full bg-gray-200 flex items-center justify-center">
                            <i class="fas fa-user text-gray-500"></i>
                        </div>
                        <div class="ml-4">
                            <div class="text-sm font-medium text-gray-900">
                                <?= htmlspecialchars($device['username']) ?>
                            </div>
                            <div class="text-xs text-gray-500">
                                <?= htmlspecialchars($device['email']) ?>
                            </div>
                        </div>
                    </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 h-10 w-10 rounded-full bg-gray-100 flex items-center justify-center">
                            <i class="fas <?= getDeviceIcon($device['user_agent']) ?> text-gray-600"></i>
                        </div>
                        <div class="ml-4">
                            <div class="text-sm font-mono text-gray-900">
                                <?= htmlspecialchars(substr($device['fingerprint'], 0, 8)) ?>...
                            </div>
                            <div class="text-xs text-gray-500 truncate" style="max-width: 200px;">
                                <?= htmlspecialchars($device['user_agent']) ?>
                            </div>
                        </div>
                    </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm text-gray-900 font-mono">
                        <?= htmlspecialchars($device['ip_address']) ?>
                    </div>
                    <?php if (!empty($device['ip_status'])): ?>
                    <div class="text-xs text-gray-500">
                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium 
                            <?= $device['ip_score'] > 50 ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800' ?>">
                            <?= htmlspecialchars($device['ip_status']) ?> (<?= (int)$device['ip_score'] ?>)
                        </span>
                    </div>
                    <?php endif; ?>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm text-gray-900">
                        <?= htmlspecialchars($device['risk_score']) ?>
                    </div>
                    <div class="text-xs text-gray-500">
                        <?php
                        // Color-code risk score
                        $risk_color = $device['risk_score'] > 80 ? 'text-red-600' : ($device['risk_score'] > 50 ? 'text-yellow-600' : 'text-green-600');
                        ?>
                        <span class="<?= $risk_color ?>">
                            <?= $device['risk_score'] > 80 ? 'High' : ($device['risk_score'] > 50 ? 'Medium' : 'Low') ?>
                        </span>
                    </div>
                </td>
                <td class="px-6 py-4">
                    <div class="text-sm text-gray-900 truncate" style="max-width: 200px;">
                        <?= htmlspecialchars($device['ip_reputation']) ?>
                    </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <span class="px-2.5 py-0.5 inline-flex text-xs leading-5 font-semibold rounded-full 
                        <?= $device['trust_status'] === 'trusted' ? 'badge-trusted' : 
                           ($device['trust_status'] === 'pending' ? 'badge-pending' : 'badge-untrusted') ?>">
                        <?= ucfirst($device['trust_status']) ?>
                    </span>
                    <div class="text-xs text-gray-500 mt-1">
                        <?= $device['active_sessions'] ?> active session<?= $device['active_sessions'] != 1 ? 's' : '' ?>
                    </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm text-gray-900">
                        <?= date('M j, Y', strtotime($device['last_used'])) ?>
                    </div>
                    <div class="text-xs text-gray-500">
                        <?= date('g:i A', strtotime($device['last_used'])) ?>
                    </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                    <div class="flex justify-end space-x-2">
                        <form method="post" class="inline">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <input type="hidden" name="device_id" value="<?= $device['id'] ?>">
                            <select name="trust_status" onchange="this.form.submit()" 
                                class="text-xs rounded border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 py-1 pr-7">
                                <option value="trusted" <?= $device['trust_status'] === 'trusted' ? 'selected' : '' ?>>Trusted</option>
                                <option value="pending" <?= $device['trust_status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                                <option value="untrusted" <?= $device['trust_status'] === 'untrusted' ? 'selected' : '' ?>>Untrusted</option>
                            </select>
                            <input type="hidden" name="change_trust_status">
                        </form>
                        <button onclick="openDeviceModal('<?= $device['id'] ?>')" 
                            class="text-blue-600 hover:text-blue-900 text-sm">
                            <i class="fas fa-eye"></i>
                        </button>
                        <form method="post" class="inline">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <input type="hidden" name="device_id" value="<?= $device['id'] ?>">
                            <button type="submit" name="revoke_device" 
                                class="text-red-600 hover:text-red-900 text-sm"
                                onclick="return confirm('Revoke all sessions for this device?')">
                                <i class="fas fa-sign-out-alt"></i>
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
                    <div class="bg-gray-50 px-6 py-3 flex items-center justify-between border-t border-gray-200">
                        <div class="flex-1 flex justify-between sm:hidden">
                            <a href="#" class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                Previous
                            </a>
                            <a href="#" class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                Next
                            </a>
                        </div>
                        <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                            <div>
                                <p class="text-sm text-gray-700">
                                    Showing <span class="font-medium">1</span> to <span class="font-medium">10</span> of <span class="font-medium"><?= count($devices) ?></span> results
                                </p>
                            </div>
                            <div>
                                <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                                    <a href="#" class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                        <span class="sr-only">Previous</span>
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                    <a href="#" aria-current="page" class="z-10 bg-blue-50 border-blue-500 text-blue-600 relative inline-flex items-center px-4 py-2 border text-sm font-medium">
                                        1
                                    </a>
                                    <a href="#" class="bg-white border-gray-300 text-gray-500 hover:bg-gray-50 relative inline-flex items-center px-4 py-2 border text-sm font-medium">
                                        2
                                    </a>
                                    <a href="#" class="bg-white border-gray-300 text-gray-500 hover:bg-gray-50 relative inline-flex items-center px-4 py-2 border text-sm font-medium">
                                        3
                                    </a>
                                    <a href="#" class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                        <span class="sr-only">Next</span>
                                        <i class="fas fa-chevron-right"></i>
                                    </a>
                                </nav>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- IP Blacklist Management -->
                <div class="bg-white rounded-lg shadow card overflow-hidden">
                    <div class="border-b border-gray-200 px-6 py-4">
                        <h2 class="text-lg font-semibold text-gray-800">
                            <i class="fas fa-ban mr-2 text-red-600"></i>
                            IP Address Restrictions
                        </h2>
                        <p class="text-sm text-gray-500 mt-1">Manage blocked IP addresses and network restrictions</p>
                    </div>
                    
                    <!-- Add IP Form -->
                    <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                        <form method="post" class="space-y-4 sm:space-y-0 sm:grid sm:grid-cols-4 sm:gap-4">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <div>
                                <label for="ip_address" class="block text-sm font-medium text-gray-700">IP Address</label>
                                <input type="text" name="ip_address" id="ip_address" required 
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                    placeholder="192.168.1.1">
                            </div>
                            <div>
                                <label for="reason" class="block text-sm font-medium text-gray-700">Reason</label>
                                <input type="text" name="reason" id="reason" 
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                    placeholder="Suspicious activity">
                            </div>
                            <div>
                                <label for="expiry_date" class="block text-sm font-medium text-gray-700">Expiry (optional)</label>
                                <input type="datetime-local" name="expiry_date" id="expiry_date" 
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                            </div>
                            <div class="flex items-end">
                                <button type="submit" name="blacklist_ip" 
                                    class="inline-flex justify-center w-full py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                                    <i class="fas fa-plus-circle mr-2"></i> Add to Blacklist
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Blacklist Table -->
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">IP Address</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reason</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Expires</th>
                                    <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($blacklist as $item): ?>
                                <tr class="table-row">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-mono text-gray-900">
                                            <?= htmlspecialchars($item['ip_address']) ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm text-gray-900">
                                            <?= htmlspecialchars($item['reason'] ?? 'No reason specified') ?>
                                        </div>
                                        <div class="text-xs text-gray-500">
                                            By: <?= htmlspecialchars($item['created_by_name'] ?? 'System') ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">
                                            <?= date('M j, Y', strtotime($item['created_at'])) ?>
                                        </div>
                                        <div class="text-xs text-gray-500">
                                            <?= date('g:i A', strtotime($item['created_at'])) ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm <?= empty($item['expires_at']) ? 'text-gray-900' : 'text-gray-900' ?>">
                                            <?= $item['expires_at'] ? date('M j, Y', strtotime($item['expires_at'])) : 'Permanent' ?>
                                        </div>
                                        <?php if ($item['expires_at']): ?>
                                        <div class="text-xs text-gray-500">
                                            <?= date('g:i A', strtotime($item['expires_at'])) ?>
                                        </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <form method="post" class="inline">
                                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                            <input type="hidden" name="ip_address" value="<?= $item['ip_address'] ?>">
                                            <button type="submit" name="remove_blacklist" 
                                                class="text-green-600 hover:text-green-900"
                                                onclick="return confirm('Remove this IP from blacklist?')">
                                                <i class="fas fa-check-circle mr-1"></i> Remove
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Device Detail Modal -->
    <div id="deviceModal" class="fixed z-10 inset-0 overflow-y-auto hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true"></span>
            <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <!-- <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-blue-100 sm:mx-0 sm:h-10 sm:w-10">
                            <i class="fas fa-laptop text-blue-600"></i>
                        </div> -->
                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                            <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">
                                Device Details
                            </h3>
                            <div class="mt-2">
                                <!-- Device Details Content -->
                                <div id="deviceModalContent" class="text-sm text-gray-500">
                                    <!-- Content will be loaded via AJAX -->
                                    <div class="animate-pulse flex space-x-4">
                                        <div class="flex-1 space-y-4 py-1">
                                            <div class="h-4 bg-gray-200 rounded w-3/4"></div>
                                            <div class="space-y-2">
                                                <div class="h-4 bg-gray-200 rounded"></div>
                                                <div class="h-4 bg-gray-200 rounded w-5/6"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="button" onclick="closeDeviceModal()" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript Libraries -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    
    <script>
        // Device Status Chart
        const ctx = document.getElementById('deviceStatusChart').getContext('2d');
        const deviceStatusChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: [
                    <?php foreach ($device_stats as $stat): ?>
                        '<?= ucfirst($stat['trust_status']) ?>',
                    <?php endforeach; ?>
                ],
                datasets: [{
                    data: [
                        <?php foreach ($device_stats as $stat): ?>
                            <?= $stat['count'] ?>,
                        <?php endforeach; ?>
                    ],
                    backgroundColor: [
                        '#10B981', // trusted - green
                        '#F59E0B', // pending - yellow
                        '#EF4444'  // untrusted - red
                    ],
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                    },
                    tooltip: {
                        callbacks: {
                            afterLabel: function(context) {
                                const index = context.dataIndex;
                                const active7days = [
                                    <?php foreach ($device_stats as $stat): ?>
                                        <?= $stat['active_7days'] ?>,
                                    <?php endforeach; ?>
                                ][index];
                                const active30days = [
                                    <?php foreach ($device_stats as $stat): ?>
                                        <?= $stat['active_30days'] ?>,
                                    <?php endforeach; ?>
                                ][index];
                                
                                return [
                                    `Active (7d): ${active7days}`,
                                    `Active (30d): ${active30days}`
                                ];
                            }
                        }
                    }
                }
            }
        });

        // Device Modal Functions
        function openDeviceModal(deviceId) {
            const modal = document.getElementById('deviceModal');
            modal.classList.remove('hidden');
            
            // Load device details via AJAX
            axios.get(`device_details.php?id=${deviceId}`)
                .then(response => {
                    document.getElementById('deviceModalContent').innerHTML = response.data;
                })
                .catch(error => {
                    document.getElementById('deviceModalContent').innerHTML = `
                        <div class="bg-red-50 border-l-4 border-red-500 p-4">
                            <div class="flex items-center">
                                <i class="fas fa-exclamation-circle text-red-500 mr-3"></i>
                                <div>
                                    <p class="font-medium text-red-800">Error loading device details</p>
                                    <p class="text-xs text-red-600 mt-1">${error.message}</p>
                                </div>
                            </div>
                        </div>
                    `;
                });
        }

        function closeDeviceModal() {
            document.getElementById('deviceModal').classList.add('hidden');
        }

        // Close modal when clicking outside
        document.getElementById('deviceModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeDeviceModal();
            }
        });

        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeDeviceModal();
            }
        });
    </script>
</body>
</html>