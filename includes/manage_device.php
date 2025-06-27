<?php
/**
 * Device Management Handler
 */

session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

// Check authentication
if (!isLoggedIn()) {
    header('HTTP/1.1 403 Forbidden');
    exit;
}

// Validate CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    $_SESSION['error'] = "Invalid CSRF token";
    header('Location: ..\device/index.php.php');
    exit;
}

// Validate required fields
if (empty($_POST['device_id']) || empty($_POST['action'])) {
    $_SESSION['error'] = "Missing required fields";
    header('Location: ..\device/index.php.php');
    exit;
}

$device_id = (int)$_POST['device_id'];
$action = $_POST['action'];

try {
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
            $_SESSION['success'] = "Device marked as trusted";
            break;
            
        case 'untrust':
            $stmt = $pdo->prepare("UPDATE device_fingerprints SET trust_status = 'untrusted', updated_at = NOW() WHERE id = ?");
            $stmt->execute([$device_id]);
            $_SESSION['success'] = "Device marked as untrusted";
            break;
            
        case 'remove':
            $stmt = $pdo->prepare("DELETE FROM device_fingerprints WHERE id = ?");
            $stmt->execute([$device_id]);
            $_SESSION['success'] = "Device removed successfully";
            break;
            
        default:
            throw new Exception("Invalid action");
    }
    
} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
}

header('Location: ..\device/index.php.php');
exit;