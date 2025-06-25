<?php
session_start();
require_once __DIR__.'/includes/config.php';
require_once __DIR__.'/includes/auth.php';
require_once 'Database.php';
require_once 'TwoFactorAuth.php';

// Redirect if not in 2FA process
if (!isset($_SESSION['2fa_user'])) {
    header('Location: login.php');
    exit();
}

$db = new Database();
$twoFactorAuth = new TwoFactorAuth();
$user = $_SESSION['2fa_user'];
$errors = [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = $_POST['code'] ?? '';
    
    // Trim and remove any spaces/dashes from the code
    $code = preg_replace('/[^0-9]/', '', $code);
    
    // Check backup codes first
    $backupCodes = json_decode($user['backup_codes'] ?? '[]', true) ?: [];
    if (in_array($code, $backupCodes)) {
        // Remove used backup code
        $updatedCodes = array_diff($backupCodes, [$code]);
        $db->updateUserBackupCodes($user['id'], $updatedCodes);
        
        // Complete login
        complete2FALogin($user, 'backup_code');
        exit();
    }
    
    // Check email code if available
    if (isset($_SESSION['2fa_email_code']) && isset($_SESSION['2fa_code_expires'])) {
        if ($code == $_SESSION['2fa_email_code']) {
            if (time() < $_SESSION['2fa_code_expires']) {
                complete2FALogin($user, 'email_code');
                exit();
            } else {
                $errors[] = "Verification code has expired. Please request a new one.";
            }
        }
    }
    
    // Check authenticator app code
    if (empty($errors) && $twoFactorAuth->verifyCode($user['two_factor_secret'], $code)) {
        complete2FALogin($user, 'authenticator_app');
        exit();
    }
    
    // If we get here, no valid code was provided
    if (empty($errors)) {
        $errors[] = "Invalid verification code. Please try again.";
        
        // Log failed attempt
        $db->logActivity(
            $user['id'],
            'failed_2fa',
            'Failed 2FA attempt',
            $_SERVER['REMOTE_ADDR'],
            $_SERVER['HTTP_USER_AGENT'] ?? ''
        );
    }
}

// Resend email code if requested
if (isset($_GET['resend'])) {
    $emailCode = rand(100000, 999999);
    $_SESSION['2fa_email_code'] = $emailCode;
    $_SESSION['2fa_code_expires'] = time() + 600; // 10 minutes
    
    require_once 'Mailer.php';
    $mailer = new Mailer();
    $mailer->send2FACode($user['email'], $user['first_name'], $emailCode);
    
    $_SESSION['success'] = "A new verification code has been sent to your email.";
    header('Location: verify-2fa.php');
    exit();
}

// Complete 2FA login process
function complete2FALogin($user, $method) {
    global $db;
    
    // Set user session
    $_SESSION['user'] = [
        'user_id' => $user['id'],
        'id' => $user['id'],
        'username' => $user['username'],
        'email' => $user['email'],
        'user_role' => $user['role'],
        'role' => $user['role'],
        'first_name' => $user['first_name'],
        'last_name' => $user['last_name']
    ];
    
    // Set standard session vars
    $_SESSION['id'] = $user['id'];
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['user_role'] = $user['role'];
    
    // Log successful login
    $db->logActivity(
        $user['id'],
        'login',
        "User logged in with 2FA ($method)",
        $_SERVER['REMOTE_ADDR'],
        $_SERVER['HTTP_USER_AGENT'] ?? ''
    );
    
    // Clear 2FA session data
    unset($_SESSION['2fa_user'], $_SESSION['2fa_email_code'], $_SESSION['2fa_code_expires']);
    
    // Redirect to dashboard
    header('Location: dashboard.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Two-Factor Authentication | <?php echo htmlspecialchars(SITE_NAME); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background-image: url('assets/images/auth-bg.jpg');
            background-size: cover;
        }
        .verify-container {
            background: rgba(255, 255, 255, 0.96);
            padding: 2.5rem;
            border-radius: 12px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.15);
            width: 100%;
            max-width: 450px;
            backdrop-filter: blur(5px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .verify-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        .verify-icon {
            font-size: 3.5rem;
            color: #4e73df;
            margin-bottom: 1.5rem;
        }
        .method-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border-left: 4px solid #4e73df;
        }
        .method-title {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
        }
        .method-title i {
            margin-right: 10px;
            font-size: 1.2rem;
        }
        .btn-verify {
            height: 50px;
            border-radius: 8px;
            font-weight: 500;
            letter-spacing: 0.5px;
        }
        .resend-link {
            text-align: center;
            margin-top: 1rem;
        }
    </style>
</head>
<body>
    <div class="verify-container">
        <div class="verify-header">
            <div class="verify-icon">
                <i class="fas fa-shield-alt"></i>
            </div>
            <h3>Two-Factor Verification</h3>
            <p class="text-muted">Enter your verification code to continue</p>
        </div>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <?php foreach ($errors as $error): ?>
                    <p class="mb-1"><i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <form action="verify-2fa.php" method="POST" autocomplete="off">
            <div class="mb-4">
                <label for="code" class="form-label">Verification Code</label>
                <input type="text" class="form-control form-control-lg" id="code" name="code" 
                       placeholder="Enter 6-digit code" required autofocus
                       pattern="[0-9]{6}" title="6-digit code">
                <small class="text-muted">Enter code from your preferred method below</small>
            </div>
            
            <button type="submit" class="btn btn-primary btn-verify w-100 mb-4">
                <i class="fas fa-check-circle me-2"></i> Verify & Continue
            </button>
            
            <div class="method-card">
                <div class="method-title">
                    <i class="fas fa-mobile-alt"></i>
                    Authenticator App
                </div>
                <p>Open your authenticator app (Google Authenticator, Authy, etc.) and enter the 6-digit code</p>
            </div>
            
            <div class="method-card">
                <div class="method-title">
                    <i class="fas fa-envelope"></i>
                    Email Verification
                </div>
                <p>We've sent a code to <?php echo htmlspecialchars($user['email']); ?></p>
                <div class="resend-link">
                    <a href="verify-2fa.php?resend=1" class="text-decoration-none">
                        <i class="fas fa-sync-alt me-1"></i> Resend Code
                    </a>
                </div>
            </div>
            
            <div class="method-card">
                <div class="method-title">
                    <i class="fas fa-key"></i>
                    Backup Code
                </div>
                <p>If you have a 10-character backup code, enter it in the field above</p>
            </div>
            
            <div class="text-center mt-4">
                <a href="logout.php" class="text-decoration-none">
                    <i class="fas fa-arrow-left me-1"></i> Back to Login
                </a>
            </div>
        </form>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-advance input fields
        document.getElementById('code').addEventListener('input', function(e) {
            if (this.value.length === 6) {
                this.form.submit();
            }
        });
        
        // Focus on code field
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('code').focus();
        });
    </script>
</body>
</html>