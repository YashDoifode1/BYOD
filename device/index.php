<?php
/**
 * Next-Gen Device Management Dashboard
 * 
 * Enhanced UI/UX for managing trusted devices and active sessions
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

// Function to get risk level
function getRiskLevel($score) {
    if ($score >= 70) return 'high';
    if ($score >= 30) return 'medium';
    return 'low';
}
?>

<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Device Management | Cybersecurity Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #4F46E5;
            --primary-dark: #4338CA;
            --success: #10B981;
            --warning: #F59E0B;
            --danger: #EF4444;
            --dark: #1F2937;
            --light: #F9FAFB;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background-color: #F3F4F6;
        }
        
        .card {
            margin-left:50px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.02);
            transition: all 0.3s ease;
        }
        
        .card:hover {
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05), 0 4px 6px -2px rgba(0, 0, 0, 0.02);
            transform: translateY(-2px);
        }
        
        .card-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            border-top-left-radius: 12px;
            border-top-right-radius: 12px;
        }
        
        .badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
            line-height: 1;
        }
        
        .badge-trusted {
            background-color: rgba(16, 185, 129, 0.1);
            color: var(--success);
        }
        
        .badge-untrusted {
            background-color: rgba(239, 68, 68, 0.1);
            color: var(--danger);
        }
        
        .badge-pending {
            background-color: rgba(245, 158, 11, 0.1);
            color: var(--warning);
        }
        
        .risk-high {
            color: var(--danger);
        }
        
        .risk-medium {
            color: var(--warning);
        }
        
        .risk-low {
            color: var(--success);
        }
        
        .device-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background-color: rgba(79, 70, 229, 0.1);
            color: var(--primary);
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.2s ease;
        }
        
        .btn-primary {
            background-color: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background-color: var(--primary-dark);
        }
        
        .btn-outline {
            background-color: transparent;
            border: 1px solid #E5E7EB;
            color: var(--dark);
        }
        
        .btn-outline:hover {
            background-color: #F9FAFB;
        }
        
        .btn-danger {
            background-color: var(--danger);
            color: white;
        }
        
        .btn-danger:hover {
            background-color: #DC2626;
        }
        
        .toast {
            position: fixed;
            bottom: 1rem;
            right: 1rem;
            z-index: 50;
            display: flex;
            align-items: center;
            padding: 1rem;
            border-radius: 8px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            animation: slideIn 0.3s ease-out;
        }
        
        .toast-success {
            background-color: white;
            border-left: 4px solid var(--success);
        }
        
        .toast-error {
            background-color: white;
            border-left: 4px solid var(--danger);
        }
        
        @keyframes slideIn {
            from {
                transform: translateY(100%);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
        
        .tab-active {
            border-bottom: 2px solid var(--primary);
            color: var(--primary);
            font-weight: 500;
        }
        
        .security-score {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 1.25rem;
        }
        
        .security-score-high {
            background-color: rgba(239, 68, 68, 0.1);
            color: var(--danger);
        }
        
        .security-score-medium {
            background-color: rgba(245, 158, 11, 0.1);
            color: var(--warning);
        }
        
        .security-score-low {
            background-color: rgba(16, 185, 129, 0.1);
            color: var(--success);
        }
    </style>
</head>
<body class="h-full">
    <!-- Main Layout -->
    <div class="flex h-full">
        <!-- Sidebar -->
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="flex-1 flex flex-col overflow-hidden">
            <!-- Header -->
            <?php include __DIR__ . '/../includes/header.php'; ?>
            
            <!-- Main Content Area -->
            <main class="flex-1 overflow-y-auto p-6">
                <div class="max-w-7xl mx-auto">
                    <!-- Page Header -->
                    <div class="flex justify-between items-center mb-8">
                        <div>
                            <h1 style=margin:20px; class="text-2xl font-bold text-gray-900">Device Management</h1>
                            <p style=margin:20px; class="text-gray-500">Manage your trusted devices and active sessions</p>
                        </div>
                        <div>
                            <button class="btn btn-primary">
                                <i class="fas fa-plus mr-2"></i> Add New Device
                            </button>
                        </div>
                    </div>
                    
                    <!-- Toast Notifications -->
                    <?php if ($action_success): ?>
                        <div class="toast toast-success">
                            <i class="fas fa-check-circle text-green-500 mr-2"></i>
                            <span><?= htmlspecialchars($action_success) ?></span>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($errors)): ?>
                        <div class="toast toast-error">
                            <i class="fas fa-exclamation-circle text-red-500 mr-2"></i>
                            <div>
                                <p class="font-medium">Error occurred:</p>
                                <ul class="list-disc list-inside text-sm">
                                    <?php foreach ($errors as $error): ?>
                                        <li><?= htmlspecialchars($error) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Security Overview -->
                    <div  class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                        <!-- Current Device Card -->
                        <div class="card">
                            <div class="p-6">
                                <div class="flex items-center mb-4">
                                    <div class="device-icon mr-4">
                                        <i class="fas <?= getDeviceIcon($_SERVER['HTTP_USER_AGENT']) ?>"></i>
                                    </div>
                                    <div>
                                        <h3 class="font-medium text-gray-900">Current Device</h3>
                                        <p class="text-sm text-gray-500"><?= htmlspecialchars(parseUserAgent($_SERVER['HTTP_USER_AGENT'])) ?></p>
                                    </div>
                                </div>
                                <div class="space-y-3">
                                    <div>
                                        <p class="text-xs text-gray-500">IP Address</p>
                                        <p class="text-sm font-medium"><?= htmlspecialchars($_SERVER['REMOTE_ADDR']) ?></p>
                                    </div>
                                    <div>
                                        <p class="text-xs text-gray-500">Session Started</p>
                                        <p class="text-sm font-medium"><?= date('M j, g:i a', strtotime($_SESSION['login_time'])) ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Security Score Card -->
                        <div class="card">
                            <div class="p-6">
                                <h3 class="font-medium text-gray-900 mb-4">Account Security Score</h3>
                                <div class="flex items-center justify-between">
                                    <div class="security-score <?= count($devices) > 5 ? 'security-score-medium' : 'security-score-low' ?>">
                                        <?= count($devices) > 5 ? '75' : '92' ?>/100
                                    </div>
                                    <div class="text-sm">
                                        <p class="text-gray-500 mb-2"><?= count($devices) > 5 ? 'Medium security' : 'High security' ?></p>
                                        <ul class="list-disc list-inside text-gray-500">
                                            <li><?= count($active_sessions) ?> active sessions</li>
                                            <li><?= count($devices) ?> trusted devices</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Quick Actions Card -->
                        <div class="card">
                            <div class="p-6">
                                <h3 class="font-medium text-gray-900 mb-4">Quick Actions</h3>
                                <div class="space-y-3">
                                    <button class="btn btn-outline w-full">
                                        <i class="fas fa-sign-out-alt mr-2"></i> Logout All Sessions
                                    </button>
                                    <button class="btn btn-outline w-full">
                                        <i class="fas fa-shield-alt mr-2"></i> Enable 2FA
                                    </button>
                                    <button class="btn btn-outline w-full">
                                        <i class="fas fa-bell mr-2"></i> Set Up Alerts
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Tab Navigation -->
                    <div style=margin:20px; class="border-b border-gray-200 mb-6">
                        <nav class="-mb-px flex space-x-8">
                            <a href="#" class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm tab-active">
                                <i class="fas fa-laptop mr-2"></i> Devices (<?= count($devices) ?>)
                            </a>
                            <a href="#" class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300">
                                <i class="fas fa-plug mr-2"></i> Sessions (<?= count($active_sessions) ?>)
                            </a>
                            <a href="#" class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300">
                                <i class="fas fa-shield-alt mr-2"></i> Security Settings
                            </a>
                        </nav>
                    </div>
                    
                    <!-- Devices Table -->
                    <div class="card overflow-hidden mb-8">
                        <div class="card-header px-6 py-4">
                            <h2 class="text-lg font-semibold text-white">Trusted Devices</h2>
                            <p class="text-sm text-blue-100 mt-1">Manage devices that can access your account</p>
                        </div>
                        <div class="p-6">
                            <?php if (empty($devices)): ?>
                                <div class="text-center py-12">
                                    <i class="fas fa-laptop text-gray-300 text-4xl mb-4"></i>
                                    <h3 class="text-lg font-medium text-gray-900">No trusted devices</h3>
                                    <p class="text-gray-500 mt-1">You haven't added any trusted devices yet.</p>
                                    <button class="btn btn-primary mt-4">
                                        <i class="fas fa-plus mr-2"></i> Add Device
                                    </button>
                                </div>
                            <?php else: ?>
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Device</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">IP Address</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Risk</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Used</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            <?php foreach ($devices as $device): ?>
                                                <tr>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <div class="flex items-center">
                                                            <div class="device-icon mr-3">
                                                                <i class="fas <?= getDeviceIcon($device['user_agent']) ?>"></i>
                                                            </div>
                                                            <div>
                                                                <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars(parseUserAgent($device['user_agent'])) ?></div>
                                                                <div class="text-xs text-gray-500"><?= substr(htmlspecialchars($device['fingerprint']), 0, 8) ?>...</div>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <div class="text-sm text-gray-900"><?= htmlspecialchars($device['ip_address']) ?></div>
                                                        <?php if (!empty($device['ip_status'])): ?>
                                                            <div class="text-xs text-gray-500"><?= htmlspecialchars($device['ip_status']) ?></div>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <?php 
                                                        $badge_class = '';
                                                        if ($device['trust_status'] === 'trusted') {
                                                            $badge_class = 'badge-trusted';
                                                        } elseif ($device['trust_status'] === 'untrusted') {
                                                            $badge_class = 'badge-untrusted';
                                                        } else {
                                                            $badge_class = 'badge-pending';
                                                        }
                                                        ?>
                                                        <span class="<?= $badge_class ?>">
                                                            <i class="fas <?= $device['trust_status'] === 'trusted' ? 'fa-check-circle' : ($device['trust_status'] === 'untrusted' ? 'fa-times-circle' : 'fa-clock') ?> mr-1"></i>
                                                            <?= ucfirst($device['trust_status']) ?>
                                                        </span>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <?php $risk_level = getRiskLevel($device['ip_score'] ?? 0); ?>
                                                        <div class="text-sm font-medium <?= 'risk-' . $risk_level ?>">
                                                            <i class="fas <?= $risk_level === 'high' ? 'fa-exclamation-triangle' : ($risk_level === 'medium' ? 'fa-exclamation-circle' : 'fa-check-circle') ?> mr-1"></i>
                                                            <?= ucfirst($risk_level) ?> risk
                                                        </div>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        <?= date('M j, Y', strtotime($device['last_used'])) ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                        <div class="flex space-x-2">
                                                            <form method="post" class="inline">
                                                                <input type="hidden" name="device_id" value="<?= $device['id'] ?>">
                                                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                                                <?php if ($device['trust_status'] !== 'trusted'): ?>
                                                                    <button type="submit" name="action" value="trust" class="text-indigo-600 hover:text-indigo-900" title="Trust Device">
                                                                        <i class="fas fa-check"></i>
                                                                    </button>
                                                                <?php endif; ?>
                                                                <?php if ($device['trust_status'] !== 'untrusted'): ?>
                                                                    <button type="submit" name="action" value="untrust" class="text-yellow-600 hover:text-yellow-900" title="Untrust Device">
                                                                        <i class="fas fa-exclamation"></i>
                                                                    </button>
                                                                <?php endif; ?>
                                                                <button type="submit" name="action" value="remove" class="text-red-600 hover:text-red-900" title="Remove Device" onclick="return confirm('Are you sure you want to remove this device?')">
                                                                    <i class="fas fa-trash"></i>
                                                                </button>
                                                            </form>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Security Tips -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Best Practices -->
                        <div class="card">
                            <div class="card-header px-6 py-4">
                                <h2 class="text-lg font-semibold text-white">Security Best Practices</h2>
                            </div>
                            <div class="p-6">
                                <ul class="space-y-4">
                                    <li class="flex items-start">
                                        <div class="flex-shrink-0 h-5 w-5 text-green-500 mt-1">
                                            <i class="fas fa-check-circle"></i>
                                        </div>
                                        <div class="ml-3">
                                            <p class="text-sm font-medium text-gray-700">Use unique passwords</p>
                                            <p class="text-sm text-gray-500">Never reuse passwords across different services</p>
                                        </div>
                                    </li>
                                    <li class="flex items-start">
                                        <div class="flex-shrink-0 h-5 w-5 text-green-500 mt-1">
                                            <i class="fas fa-check-circle"></i>
                                        </div>
                                        <div class="ml-3">
                                            <p class="text-sm font-medium text-gray-700">Enable two-factor authentication</p>
                                            <p class="text-sm text-gray-500">Add an extra layer of security to your account</p>
                                        </div>
                                    </li>
                                    <li class="flex items-start">
                                        <div class="flex-shrink-0 h-5 w-5 text-green-500 mt-1">
                                            <i class="fas fa-check-circle"></i>
                                        </div>
                                        <div class="ml-3">
                                            <p class="text-sm font-medium text-gray-700">Review devices regularly</p>
                                            <p class="text-sm text-gray-500">Remove any devices you no longer use</p>
                                        </div>
                                    </li>
                                </ul>
                            </div>
                        </div>
                        
                        <!-- Recent Activity -->
                        <div class="card">
                            <div class="card-header px-6 py-4">
                                <h2 class="text-lg font-semibold text-white">Recent Security Activity</h2>
                            </div>
                            <div class="p-6">
                                <div class="space-y-4">
                                    <div class="flex items-start">
                                        <div class="flex-shrink-0 h-10 w-10 rounded-full bg-indigo-100 flex items-center justify-center">
                                            <i class="fas fa-laptop text-indigo-600"></i>
                                        </div>
                                        <div class="ml-4">
                                            <p class="text-sm font-medium text-gray-900">New device added</p>
                                            <p class="text-sm text-gray-500">iPhone from New York, US</p>
                                            <p class="text-xs text-gray-400 mt-1">2 hours ago</p>
                                        </div>
                                    </div>
                                    <div class="flex items-start">
                                        <div class="flex-shrink-0 h-10 w-10 rounded-full bg-green-100 flex items-center justify-center">
                                            <i class="fas fa-shield-alt text-green-600"></i>
                                        </div>
                                        <div class="ml-4">
                                            <p class="text-sm font-medium text-gray-900">Password changed</p>
                                            <p class="text-sm text-gray-500">Your account password was updated</p>
                                            <p class="text-xs text-gray-400 mt-1">1 day ago</p>
                                        </div>
                                    </div>
                                    <div class="flex items-start">
                                        <div class="flex-shrink-0 h-10 w-10 rounded-full bg-blue-100 flex items-center justify-center">
                                            <i class="fas fa-sign-in-alt text-blue-600"></i>
                                        </div>
                                        <div class="ml-4">
                                            <p class="text-sm font-medium text-gray-900">Login from new location</p>
                                            <p class="text-sm text-gray-500">London, UK</p>
                                            <p class="text-xs text-gray-400 mt-1">3 days ago</p>
                                        </div>
                                    </div>
                                </div>
                                <button class="btn btn-outline w-full mt-4">
                                    <i class="fas fa-history mr-2"></i> View Full Activity Log
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        // Toast auto-dismiss
        document.addEventListener('DOMContentLoaded', function() {
            const toasts = document.querySelectorAll('.toast');
            
            toasts.forEach(toast => {
                setTimeout(() => {
                    toast.style.transition = 'all 0.3s ease';
                    toast.style.transform = 'translateY(100%)';
                    toast.style.opacity = '0';
                    
                    setTimeout(() => {
                        toast.remove();
                    }, 300);
                }, 5000);
            });
            
            // Confirm before revoking sessions or removing devices
            function confirmAction(message) {
                return confirm(message || 'Are you sure you want to perform this action?');
            }
        });
    </script>
</body>
</html>