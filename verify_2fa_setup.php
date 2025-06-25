<?php
require_once 'includes/config.php';
require_once 'TwoFactorAuth.php';
require_once 'Database.php';

$db = new Database();
$twoFactorAuth = new TwoFactorAuth();

// Get current user
$userId = $_SESSION['user_id'];
$user = $db->getUserById($userId);

if (!$user || !$user['two_factor_secret']) {
    header('Location: enable_2fa.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = $_POST['code'];
    
    if ($twoFactorAuth->verifyCode($user['two_factor_secret'], $code)) {
        $_SESSION['2fa_verified'] = true;
        $_SESSION['2fa_setup_complete'] = true;
        header('Location: dashboard.php');
        exit;
    } else {
        $error = "Invalid verification code. Please try again.";
    }
}
?>
<h2>Verify Two-Factor Authentication Setup</h2>
<p>Enter the code from your authenticator app to verify your setup.</p>

<?php if (isset($error)): ?>
    <div style="color: red;"><?php echo $error; ?></div>
<?php endif; ?>

<form method="post">
    <input type="text" name="code" placeholder="6-digit code" required>
    <button type="submit">Verify</button>
</form>