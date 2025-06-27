<?php
/**
 * User Profile Management
 * 
 * Displays and allows editing of user profile information with professional styling
 * and device management
 */

session_start();
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/config.php';

// Check authentication
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Handle form submission
$update_success = false;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = "Invalid CSRF token";
    } else {
        // Sanitize input
        $first_name = filter_input(INPUT_POST, 'first_name', FILTER_SANITIZE_STRING);
        $last_name = filter_input(INPUT_POST, 'last_name', FILTER_SANITIZE_STRING);
        $company_name = filter_input(INPUT_POST, 'company_name', FILTER_SANITIZE_STRING);
        $job_title = filter_input(INPUT_POST, 'job_title', FILTER_SANITIZE_STRING);
        $phone = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING);
        $address = filter_input(INPUT_POST, 'address', FILTER_SANITIZE_STRING);
        $bio = filter_input(INPUT_POST, 'bio', FILTER_SANITIZE_STRING);

        // Validate email if changed
        $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
        if (!$email) {
            $errors[] = "Invalid email address";
        }

        // Handle profile picture upload
        $profile_picture = null;
        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            $file_info = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = finfo_file($file_info, $_FILES['profile_picture']['tmp_name']);
            
            if (in_array($mime_type, $allowed_types)) {
                $upload_dir = __DIR__ . '/../uploads/profiles/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                $file_ext = pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION);
                $filename = 'profile_' . $_SESSION['user_id'] . '_' . time() . '.' . $file_ext;
                $target_path = $upload_dir . $filename;
                
                if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $target_path)) {
                    $profile_picture = 'uploads/profiles/' . $filename;
                } else {
                    $errors[] = "Failed to upload profile picture";
                }
            } else {
                $errors[] = "Invalid file type for profile picture. Only JPG, PNG, and GIF are allowed.";
            }
        }

        // Update database if no errors
        if (empty($errors)) {
            try {
                // Start transaction
                $pdo->beginTransaction();
                
                // Check if email exists (if changed)
                if ($email !== $_SESSION['email']) {
                    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                    $stmt->execute([$email, $_SESSION['user_id']]);
                    if ($stmt->fetch()) {
                        throw new Exception("Email address already in use");
                    }
                }
                
                // Build update query
                $update_fields = [
                    'first_name' => $first_name,
                    'last_name' => $last_name,
                    'email' => $email,
                    'company_name' => $company_name,
                    'job_title' => $job_title,
                    'phone' => $phone,
                    'address' => $address,
                    'bio' => $bio,
                    'updated_at' => date('Y-m-d H:i:s')
                ];
                
                if ($profile_picture) {
                    $update_fields['profile_picture'] = $profile_picture;
                }
                
                $set_clause = implode(', ', array_map(function($field) {
                    return "$field = :$field";
                }, array_keys($update_fields)));
                
                $stmt = $pdo->prepare("UPDATE users SET $set_clause WHERE id = :id");
                $update_fields['id'] = $_SESSION['user_id'];
                $stmt->execute($update_fields);
                
                // Update session data
                $_SESSION['user_email'] = $email;
                $_SESSION['user_name'] = "$first_name $last_name";
                
                $pdo->commit();
                $update_success = true;
                
            } catch (Exception $e) {
                $pdo->rollBack();
                $errors[] = $e->getMessage();
            }
        }
    }
}

// Get current user data
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        throw new Exception("User not found");
    }
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    die("An error occurred while fetching your profile data.");
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile | Cybersecurity Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .profile-header {
            background: linear-gradient(135deg, #1a365d 0%, #2c5282 100%);
        }
        .profile-picture {
            border: 4px solid white;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .security-badge {
            background: linear-gradient(135deg, #4b5563 0%, #1f2937 100%);
        }
        .hover-scale {
            transition: transform 0.2s ease-in-out;
        }
        .hover-scale:hover {
            transform: scale(1.02);
        }
        .trusted-badge {
            background-color: #10B981;
        }
        .untrusted-badge {
            background-color: #EF4444;
        }
        .pending-badge {
            background-color: #F59E0B;
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Header -->
    <?php include __DIR__ . '/../includes/header.php'; ?>
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    
    <div class="container mx-auto px-4 py-8 lg:ml-64">
        <!-- Success/Error Messages -->
        <?php if ($update_success): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded flex items-center">
                <i class="fas fa-check-circle mr-2 text-lg"></i>
                <p class="font-medium">Your profile has been updated successfully!</p>
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
        
        <div class="max-w-6xl mx-auto grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Left Column - Profile Card -->
            <div class="lg:col-span-1 space-y-6">
                <!-- Profile Card -->
                <div class="bg-white rounded-lg shadow-md overflow-hidden hover-scale">
                    <div class="profile-header p-6 text-center">
                        <div class="relative mx-auto w-32 h-32">
                            <img src="../<?= htmlspecialchars($user['profile_picture'] ?? '../assets/default-profile.png') ?>" 
                                 alt="Profile Picture" 
                                 class="profile-picture w-full h-full rounded-full object-cover">
                            <label for="profile-upload" class="absolute bottom-0 right-0 bg-blue-500 text-white rounded-full p-2 cursor-pointer hover:bg-blue-600 transition-colors">
                                <i class="fas fa-camera text-sm"></i>
                                <input type="file" id="profile-upload" name="profile_picture" class="hidden" form="profile-form">
                            </label>
                        </div>
                        <h1 class="text-xl font-bold text-white mt-4"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></h1>
                        <p class="text-blue-200"><?= htmlspecialchars($user['job_title'] ?? 'Cybersecurity Professional') ?></p>
                    </div>
                    
                    <div class="p-6">
                        <div class="space-y-4">
                            <div>
                                <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Contact</h3>
                                <p class="mt-1 text-sm text-gray-600">
                                    <i class="fas fa-envelope mr-2 text-blue-500"></i>
                                    <?= htmlspecialchars($user['email']) ?>
                                </p>
                                <?php if (!empty($user['phone'])): ?>
                                <p class="mt-1 text-sm text-gray-600">
                                    <i class="fas fa-phone mr-2 text-blue-500"></i>
                                    <?= htmlspecialchars($user['phone']) ?>
                                </p>
                                <?php endif; ?>
                            </div>
                            
                            <div>
                                <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Organization</h3>
                                <p class="mt-1 text-sm text-gray-600">
                                    <i class="fas fa-building mr-2 text-blue-500"></i>
                                    <?= htmlspecialchars($user['company_name'] ?? 'Not specified') ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Security Settings Card -->
                <div class="bg-white rounded-lg shadow-md overflow-hidden hover-scale">
                    <div class="security-badge px-6 py-4 border-b border-gray-200">
                        <h2 class="text-lg font-semibold text-white">
                            <i class="fas fa-shield-alt mr-2"></i>Security Settings
                        </h2>
                    </div>
                    
                    <div class="p-6 space-y-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-sm font-medium text-gray-700">Two-Factor Authentication</h3>
                                <p class="text-xs text-gray-500">Enhanced account security</p>
                            </div>
                            <a href="..\enable_2fa.php" class="inline-flex items-center px-3 py-1 border border-transparent text-xs font-medium rounded shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                <?= (empty($user['two_factor_secret']) ? 'Enable' : 'Manage') ?>
                            </a>
                        </div>
                        
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-sm font-medium text-gray-700">Password</h3>
                                <p class="text-xs text-gray-500">Last changed <?= !empty($user['password_changed_at']) ? date('M j, Y', strtotime($user['password_changed_at'])) : 'unknown' ?></p>
                            </div>
                            <a href="..\forgot-password.php" class="inline-flex items-center px-3 py-1 border border-gray-300 text-xs font-medium rounded shadow-sm text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                Reset
                            </a>
                        </div>
                        
                        <div class="pt-4 border-t border-gray-200">
                            <h3 class="text-sm font-medium text-gray-700">Active Sessions (<?= count($active_sessions) ?>)</h3>
                            <p class="text-xs text-gray-500 mt-1">
                                <i class="fas fa-circle text-green-500 mr-1"></i>
                                Current session active since <?= date('M j, g:i a', strtotime($_SESSION['login_time'])) ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Right Column - Profile Form and Device Management -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Profile Form -->
                <div class="bg-white rounded-lg shadow-md overflow-hidden">
                    <div class="border-b border-gray-200 px-6 py-4">
                        <h2 class="text-lg font-semibold text-gray-800">
                            <i class="fas fa-user-edit mr-2 text-blue-500"></i>
                            Edit Profile Information
                        </h2>
                    </div>
                    
                    <form id="profile-form" method="post" enctype="multipart/form-data" class="p-6 space-y-6">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Personal Information -->
                            <div class="space-y-4">
                                <h3 class="text-md font-medium text-gray-700 border-b pb-2">
                                    Personal Details
                                </h3>
                                
                                <div>
                                    <label for="first_name" class="block text-sm font-medium text-gray-700">First Name *</label>
                                    <input type="text" id="first_name" name="first_name" value="<?= htmlspecialchars($user['first_name'] ?? '') ?>" required
                                           class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                </div>
                                
                                <div>
                                    <label for="last_name" class="block text-sm font-medium text-gray-700">Last Name *</label>
                                    <input type="text" id="last_name" name="last_name" value="<?= htmlspecialchars($user['last_name'] ?? '') ?>" required
                                           class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                </div>
                                
                                <div>
                                    <label for="email" class="block text-sm font-medium text-gray-700">Email *</label>
                                    <input type="email" id="email" name="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>" required
                                           class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                </div>
                                
                                <div>
                                    <label for="phone" class="block text-sm font-medium text-gray-700">Phone</label>
                                    <input type="text" id="phone" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" 
                                           class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                    <p class="mt-1 text-xs text-gray-500">Format: +1234567890</p>
                                </div>
                            </div>
                            
                            <!-- Professional Information -->
                            <div class="space-y-4">
                                <h3 class="text-md font-medium text-gray-700 border-b pb-2">
                                    Professional Information
                                </h3>
                                
                                <div>
                                    <label for="company_name" class="block text-sm font-medium text-gray-700">Company</label>
                                    <input type="text" id="company_name" name="company_name" value="<?= htmlspecialchars($user['company_name'] ?? '') ?>" 
                                           class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                </div>
                                
                                <div>
                                    <label for="job_title" class="block text-sm font-medium text-gray-700">Job Title</label>
                                    <input type="text" id="job_title" name="job_title" value="<?= htmlspecialchars($user['job_title'] ?? '') ?>" 
                                           class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                </div>
                                
                                <div>
                                    <label for="address" class="block text-sm font-medium text-gray-700">Address</label>
                                    <textarea id="address" name="address" rows="2" 
                                              class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500"><?= htmlspecialchars($user['address'] ?? '') ?></textarea>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Bio Section -->
                        <div>
                            <label for="bio" class="block text-sm font-medium text-gray-700">Professional Bio</label>
                            <textarea id="bio" name="bio" rows="4" 
                                      class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500"><?= htmlspecialchars($user['bio'] ?? '') ?></textarea>
                            <p class="mt-1 text-xs text-gray-500">Brief description about yourself and your expertise (max 500 characters).</p>
                        </div>
                        
                        <!-- Form Actions -->
                        <div class="flex justify-between items-center pt-6 border-t">
                            <div class="text-sm text-gray-500">
                                Last updated: <?= !empty($user['updated_at']) ? date('F j, Y \a\t g:i a', strtotime($user['updated_at'])) : 'Never' ?>
                            </div>
                            <div class="flex space-x-3">
                                <a href="..\dashboard.php" class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                    <i class="fas fa-arrow-left mr-2"></i> Back to Dashboard
                                </a>
                                <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                    <i class="fas fa-save mr-2"></i> Save Changes
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
                
                Device Management Section
                <div class="bg-white rounded-lg shadow-md overflow-hidden">
                    <div class="border-b border-gray-200 px-6 py-4">
                        <h2 class="text-lg font-semibold text-gray-800">
                            <i class="fas fa-laptop mr-2 text-blue-500"></i>
                            Device Management
                        </h2>
                        <p class="text-sm text-gray-500 mt-1">Manage trusted devices and active sessions</p>
                    </div>
                    
                    <div class="p-6">
                        <!-- Active Sessions -->
                        <div class="mb-8">
                            <h3 class="text-md font-medium text-gray-700 border-b pb-2 mb-4">
                                Active Sessions (<?= count($active_sessions) ?>)
                            </h3>
                            
                            <?php if (empty($active_sessions)): ?>
                                <p class="text-sm text-gray-500">No active sessions found.</p>
                            <?php else: ?>
                                <div class="space-y-4">
                                    <?php foreach ($active_sessions as $session): ?>
                                        <div class="border border-gray-200 rounded-lg p-4 hover:bg-gray-50">
                                            <div class="flex items-start justify-between">
                                                <div>
                                                    <div class="flex items-center">
                                                        <?php if ($session['session_id'] === session_id()): ?>
                                                            <span class="inline-block w-3 h-3 rounded-full bg-green-500 mr-2"></span>
                                                            <span class="font-medium text-gray-800">Current Session</span>
                                                        <?php else: ?>
                                                            <span class="inline-block w-3 h-3 rounded-full bg-blue-500 mr-2"></span>
                                                            <span class="font-medium text-gray-800">Active Session</span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <p class="text-sm text-gray-600 mt-1">
                                                        <i class="fas fa-globe mr-1"></i> <?= htmlspecialchars($session['ip_address'] ?? 'Unknown IP') ?>
                                                    </p>
                                                    <p class="text-sm text-gray-600">
                                                        <i class="fas fa-desktop mr-1"></i> <?= htmlspecialchars(parseUserAgent($session['device_user_agent'])) ?>
                                                    </p>
                                                    <p class="text-xs text-gray-500 mt-1">
                                                        Last activity: <?= date('M j, g:i a', strtotime($session['last_activity'])) ?>
                                                    </p>
                                                </div>
                                                <?php if ($session['session_id'] !== session_id()): ?>
                                                    <form method="post" action="../includes/revoke_session.php">
                                                        <input type="hidden" name="session_id" value="<?= htmlspecialchars($session['session_id']) ?>">
                                                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                                        <button type="submit" class="text-xs text-red-600 hover:text-red-800">
                                                            <i class="fas fa-sign-out-alt mr-1"></i> Revoke
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Trusted Devices -->
                        <div>
                            <h3 class="text-md font-medium text-gray-700 border-b pb-2 mb-4">
                                Trusted Devices (<?= count($devices) ?>)
                            </h3>
                            
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
                                                                <i class="fas fa-laptop text-gray-500"></i>
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
                                                        <form method="post" action="../includes/manage_device.php" class="inline">
                                                            <input type="hidden" name="device_id" value="<?= $device['id'] ?>">
                                                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                                            <?php if ($device['trust_status'] !== 'trusted'): ?>
                                                                <button type="submit" name="action" value="trust" class="text-green-600 hover:text-green-900 mr-3">Trust</button>
                                                            <?php endif; ?>
                                                            <?php if ($device['trust_status'] !== 'untrusted'): ?>
                                                                <button type="submit" name="action" value="untrust" class="text-yellow-600 hover:text-yellow-900 mr-3">Untrust</button>
                                                            <?php endif; ?>
                                                            <button type="submit" name="action" value="remove" class="text-red-600 hover:text-red-900">Remove</button>
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
                </div>
            </div>
        </div>
    </div>

    <script>
        // Preview profile picture before upload
        document.getElementById('profile-upload').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(event) {
                    document.querySelector('.profile-picture').src = event.target.result;
                };
                reader.readAsDataURL(file);
            }
        });
    </script>
</body>
</html>