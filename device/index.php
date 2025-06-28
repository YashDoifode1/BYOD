<?php
/**
 * Device Management Page
 * 
 * Allows users to view and manage their trusted devices and active sessions
 */

session_start();
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/config.php';

// Check authentication
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Handle device management actions
$action_success = false;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = "Invalid CSRF token";
    } else {
        // Validate required fields
        if (empty($_POST['device_id'])) {
            $errors[] = "Device ID is required";
        }
        
        if (empty($_POST['action'])) {
            $errors[] = "Action is required";
        }

        // Process action if no errors
        if (empty($errors)) {
            try {
                $device_id = (int)$_POST['device_id'];
                $action = $_POST['action'];
                
                // Check if device belongs to current user
                $stmt = $pdo->prepare("SELECT id FROM device_fingerprints WHERE id = ? AND user_id = ?");
                $stmt->execute([$device_id, $_SESSION['user_id']]);
                
                if (!$stmt->fetch()) {
                    throw new Exception("Device not found or doesn't belong to you");
                }
                
                // Perform the requested action
                switch ($action) {
                    case 'trust':
                        $stmt = $pdo->prepare("UPDATE device_fingerprints SET trust_status = 'trusted', updated_at = NOW() WHERE id = ?");
                        $stmt->execute([$device_id]);
                        $action_success = "Device marked as trusted";
                        break;
                        
                    case 'untrust':
                        $stmt = $pdo->prepare("UPDATE device_fingerprints SET trust_status = 'untrusted', updated_at = NOW() WHERE id = ?");
                        $stmt->execute([$device_id]);
                        $action_success = "Device marked as untrusted";
                        break;
                        
                    case 'remove':
                        $stmt = $pdo->prepare("DELETE FROM device_fingerprints WHERE id = ?");
                        $stmt->execute([$device_id]);
                        $action_success = "Device removed successfully";
                        break;
                        
                    default:
                        throw new Exception("Invalid action");
                }
                
            } catch (Exception $e) {
                $errors[] = $e->getMessage();
            }
        }
    }
}

// Handle session revocation
if (isset($_GET['revoke_session'])) {
    $session_id = $_GET['revoke_session'];
    
    try {
        // Check if session belongs to current user
        $stmt = $pdo->prepare("DELETE FROM sessions WHERE session_id = ? AND user_id = ?");
        $stmt->execute([$session_id, $_SESSION['user_id']]);
        
        if ($stmt->rowCount() > 0) {
            $action_success = "Session revoked successfully";
        } else {
            $errors[] = "Session not found or doesn't belong to you";
        }
        
    } catch (Exception $e) {
        $errors[] = "Error revoking session: " . $e->getMessage();
    }
}

// Get device information
$devices = [];
$active_sessions = [];
try {
    // Get trusted devices
    $stmt = $pdo->prepare("
        SELECT df.*, ip.status as ip_status, ip.score as ip_score 
        FROM device_fingerprints df
        LEFT JOIN ip_reputation_cache ip ON df.ip_address = ip.ip_address
        WHERE df.user_id = ? 
        ORDER BY df.last_used DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $devices = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get active sessions with device info
    $stmt = $pdo->prepare("
        SELECT s.*, df.fingerprint, df.trust_status, df.user_agent as device_user_agent
        FROM sessions s
        JOIN device_fingerprints df ON s.device_fingerprint_id = df.id
        WHERE s.user_id = ?
        ORDER BY s.last_activity DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $active_sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching device information: " . $e->getMessage());
    $errors[] = "Could not load device information";
}

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Function to parse user agent
function parseUserAgent($ua) {
    if (empty($ua)) return 'Unknown Device';
    
    // Simple parsing - in a real app you might use a library like whichbrowser/parser
    if (strpos($ua, 'Windows') !== false) return 'Windows PC';
    if (strpos($ua, 'Macintosh') !== false) return 'Mac';
    if (strpos($ua, 'Linux') !== false) return 'Linux PC';
    if (strpos($ua, 'iPhone') !== false) return 'iPhone';
    if (strpos($ua, 'iPad') !== false) return 'iPad';
    if (strpos($ua, 'Android') !== false) return 'Android Device';
    
    return 'Unknown Device';
}

// Function to get device icon
function getDeviceIcon($ua) {
    if (empty($ua)) return 'fa-question-circle';
    
    if (strpos($ua, 'Windows') !== false) return 'fa-windows';
    if (strpos($ua, 'Macintosh') !== false || strpos($ua, 'iPhone') !== false || strpos($ua, 'iPad') !== false) return 'fa-apple';
    if (strpos($ua, 'Linux') !== false) return 'fa-linux';
    if (strpos($ua, 'Android') !== false) return 'fa-android';
    
    return 'fa-laptop';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Device Management | Cybersecurity Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .trusted-badge {
            background-color: #10B981;
        }
        .untrusted-badge {
            background-color: #EF4444;
        }
        .pending-badge {
            background-color: #F59E0B;
        }
        .security-header {
            background: linear-gradient(135deg, #1a365d 0%, #2c5282 100%);
        }
        .hover-scale {
            transition: transform 0.2s ease-in-out;
        }
        .hover-scale:hover {
            transform: scale(1.02);
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Header -->
    <?php include __DIR__ . '/../includes/header.php'; ?>
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    
    <div class="container mx-auto px-4 py-8 lg:ml-64">
        <!-- Success/Error Messages -->
        <?php if ($action_success): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded flex items-center">
                <i class="fas fa-check-circle mr-2 text-lg"></i>
                <p class="font-medium"><?= htmlspecialchars($action_success) ?></p>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($errors)): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-circle mr-2 text-lg"></i>
                    <p class="font-medium">Error: Please fix the following issues:</p>
                </div>
                <ul class="mt-2 ml-6 list-disc">
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <div class="max-w-6xl mx-auto">
            <!-- Page Header -->
            <div class="mb-6">
                <h1 class="text-2xl font-bold text-gray-800">
                    <i class="fas fa-laptop mr-2"></i>Device Management
                </h1>
                <p class="text-gray-600">Manage your trusted devices and active sessions</p>
            </div>
            
            <!-- Current Device Info -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden mb-8 hover-scale">
                <div class="security-header px-6 py-4">
                    <h2 class="text-lg font-semibold text-white">
                        <i class="fas fa-info-circle mr-2"></i>Current Device Information
                    </h2>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <h3 class="text-sm font-medium text-gray-500">Device Fingerprint</h3>
                            <p class="mt-1 text-sm font-mono text-gray-800 break-all">
                               <?= htmlspecialchars(substr($_SESSION['device_fingerprint'] ?? 'Not available', 0, 24)) ?>...

                            </p>
                        </div>
                        <div>
                            <h3 class="text-sm font-medium text-gray-500">IP Address</h3>
                            <p class="mt-1 text-sm text-gray-800">
                                <?= htmlspecialchars($_SERVER['REMOTE_ADDR']) ?>
                            </p>
                        </div>
                        <div>
                            <h3 class="text-sm font-medium text-gray-500">Device Type</h3>
                            <p class="mt-1 text-sm text-gray-800">
                                <i class="fas <?= getDeviceIcon($_SERVER['HTTP_USER_AGENT']) ?> mr-1"></i>
                                <?= htmlspecialchars(parseUserAgent($_SERVER['HTTP_USER_AGENT'])) ?>
                            </p>
                        </div>
                        <div>
                            <h3 class="text-sm font-medium text-gray-500">Current Session</h3>
                            <p class="mt-1 text-sm text-gray-800">
                                Active since <?= date('M j, g:i a', strtotime($_SESSION['login_time'])) ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Active Sessions -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden mb-8 hover-scale">
                <div class="security-header px-6 py-4">
                    <h2 class="text-lg font-semibold text-white">
                        <i class="fas fa-plug mr-2"></i>Active Sessions (<?= count($active_sessions) ?>)
                    </h2>
                    <p class="text-sm text-blue-200 mt-1">Manage all devices currently logged into your account</p>
                </div>
                
                <div class="p-6">
                    <?php if (empty($active_sessions)): ?>
                        <p class="text-sm text-gray-500">No active sessions found.</p>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Device</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">IP Address</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Activity</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($active_sessions as $session): ?>
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="flex items-center">
                                                    <div class="flex-shrink-0 h-10 w-10 rounded-full bg-gray-200 flex items-center justify-center">
                                                        <i class="fas <?= getDeviceIcon($session['device_user_agent']) ?> text-gray-500"></i>
                                                    </div>
                                                    <div class="ml-4">
                                                        <div class="text-sm font-medium text-gray-900">
                                                            <?= htmlspecialchars(parseUserAgent($session['device_user_agent'])) ?>
                                                            <?php if ($session['session_id'] === session_id()): ?>
                                                                <span class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                                    Current
                                                                </span>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="text-sm text-gray-500"><?= substr(htmlspecialchars($session['fingerprint']), 0, 8) ?>...</div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900"><?= htmlspecialchars($session['ip_address']) ?></div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <?php 
                                                $badge_class = '';
                                                if ($session['trust_status'] === 'trusted') {
                                                    $badge_class = 'trusted-badge';
                                                } elseif ($session['trust_status'] === 'untrusted') {
                                                    $badge_class = 'untrusted-badge';
                                                } else {
                                                    $badge_class = 'pending-badge';
                                                }
                                                ?>
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full text-white <?= $badge_class ?>">
                                                    <?= ucfirst($session['trust_status']) ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?= date('M j, g:i a', strtotime($session['last_activity'])) ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <?php if ($session['session_id'] !== session_id()): ?>
                                                    <a href="?revoke_session=<?= htmlspecialchars($session['session_id']) ?>" class="text-red-600 hover:text-red-900" onclick="return confirm('Are you sure you want to revoke this session?')">
                                                        <i class="fas fa-sign-out-alt mr-1"></i>Revoke
                                                    </a>
                                                <?php else: ?>
                                                    <span class="text-gray-400">Current session</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Trusted Devices -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden hover-scale">
                <div class="security-header px-6 py-4">
                    <h2 class="text-lg font-semibold text-white">
                        <i class="fas fa-shield-alt mr-2"></i>Trusted Devices (<?= count($devices) ?>)
                    </h2>
                    <p class="text-sm text-blue-200 mt-1">Manage devices that can access your account without additional verification</p>
                </div>
                
                <div class="p-6">
                    <?php if (empty($devices)): ?>
                        <p class="text-sm text-gray-500">No trusted devices found.</p>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Device</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">IP Address</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Used</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($devices as $device): ?>
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="flex items-center">
                                                    <div class="flex-shrink-0 h-10 w-10 rounded-full bg-gray-200 flex items-center justify-center">
                                                        <i class="fas <?= getDeviceIcon($device['user_agent']) ?> text-gray-500"></i>
                                                    </div>
                                                    <div class="ml-4">
                                                        <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars(parseUserAgent($device['user_agent'])) ?></div>
                                                        <div class="text-sm text-gray-500"><?= substr(htmlspecialchars($device['fingerprint']), 0, 8) ?>...</div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900"><?= htmlspecialchars($device['ip_address']) ?></div>
                                                <?php if (!empty($device['ip_status'])): ?>
                                                    <div class="text-xs text-gray-500"><?= htmlspecialchars($device['ip_status']) ?> (Score: <?= (int)$device['ip_score'] ?>)</div>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <?php 
                                                $badge_class = '';
                                                if ($device['trust_status'] === 'trusted') {
                                                    $badge_class = 'trusted-badge';
                                                } elseif ($device['trust_status'] === 'untrusted') {
                                                    $badge_class = 'untrusted-badge';
                                                } else {
                                                    $badge_class = 'pending-badge';
                                                }
                                                ?>
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full text-white <?= $badge_class ?>">
                                                    <?= ucfirst($device['trust_status']) ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?= date('M j, Y', strtotime($device['last_used'])) ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <form method="post" class="inline">
                                                    <input type="hidden" name="device_id" value="<?= $device['id'] ?>">
                                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                                    <?php if ($device['trust_status'] !== 'trusted'): ?>
                                                        <button type="submit" name="action" value="trust" class="text-green-600 hover:text-green-900 mr-3">
                                                            <i class="fas fa-check-circle mr-1"></i>Trust
                                                        </button>
                                                    <?php endif; ?>
                                                    <?php if ($device['trust_status'] !== 'untrusted'): ?>
                                                        <button type="submit" name="action" value="untrust" class="text-yellow-600 hover:text-yellow-900 mr-3">
                                                            <i class="fas fa-exclamation-triangle mr-1"></i>Untrust
                                                        </button>
                                                    <?php endif; ?>
                                                    <button type="submit" name="action" value="remove" class="text-red-600 hover:text-red-900" onclick="return confirm('Are you sure you want to remove this device?')">
                                                        <i class="fas fa-trash-alt mr-1"></i>Remove
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Security Recommendations -->
            <div class="mt-8 bg-white rounded-lg shadow-md overflow-hidden hover-scale">
                <div class="security-header px-6 py-4">
                    <h2 class="text-lg font-semibold text-white">
                        <i class="fas fa-lightbulb mr-2"></i>Security Recommendations
                    </h2>
                </div>
                <div class="p-6">
                    <ul class="space-y-4">
                        <li class="flex items-start">
                            <div class="flex-shrink-0 h-5 w-5 text-green-500 mt-1">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm font-medium text-gray-700">Regularly review your trusted devices</p>
                                <p class="text-sm text-gray-500">Remove any devices you no longer use or don't recognize</p>
                            </div>
                        </li>
                        <li class="flex items-start">
                            <div class="flex-shrink-0 h-5 w-5 text-green-500 mt-1">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm font-medium text-gray-700">Enable Two-Factor Authentication</p>
                                <p class="text-sm text-gray-500">Add an extra layer of security to your account</p>
                            </div>
                        </li>
                        <li class="flex items-start">
                            <div class="flex-shrink-0 h-5 w-5 text-green-500 mt-1">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm font-medium text-gray-700">Revoke sessions from public computers</p>
                                <p class="text-sm text-gray-500">Always log out when using shared devices</p>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Confirm before revoking sessions or removing devices
        function confirmAction(message) {
            return confirm(message || 'Are you sure you want to perform this action?');
        }
    </script>
</body>
</html>