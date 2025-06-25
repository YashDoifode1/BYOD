<?php
require_once 'includes/config.php';
require_once 'Database.php';
require_once 'TwoFactorAuth.php';

header('Content-Type: application/json');

session_start();
if (!isset($_SESSION['user'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$userId = $input['userId'] ?? null;
$email = $input['email'] ?? null;

if ($userId != $_SESSION['user']['id']) {
    echo json_encode(['error' => 'Invalid request']);
    exit();
}

$db = new Database();
$twoFactorAuth = new TwoFactorAuth();

// Generate new secret (not saved yet)
$secret = $twoFactorAuth->generateSecret();
$backupCodes = $twoFactorAuth->generateBackupCodes();
$qrCode = $twoFactorAuth->generateQRCode(SITE_NAME, $email, $secret);

echo json_encode([
    'secret' => $secret,
    'qrCode' => $qrCode,
    'backupCodes' => $backupCodes
]);