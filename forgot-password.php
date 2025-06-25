<?php
session_start();
require_once __DIR__.'/includes/config.php';
require_once __DIR__.'/includes/auth.php';
require_once 'Database.php';
require_once 'Mailer.php';

$db = new Database();
$errors = [];
$success = false;
function customTimeWithTZ(string $timezone = 'UTC', int $offsetSeconds = 0): int {
    $date = new DateTime('now', new DateTimeZone($timezone));
    if ($offsetSeconds != 0) {
        $date->modify("$offsetSeconds seconds");
    }
    return $date->getTimestamp();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    
    // Validate email
    if (empty($email)) {
        $errors[] = "Email address is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address";
    }
    
    // If no errors, process password reset
    if (empty($errors)) {
        $user = $db->getUserByEmail($email);
        
        if ($user) {
            // Generate a unique token
            $token = bin2hex(random_bytes(32));
            $expires = customTimeWithTZ('Asia/Dhaka', 36000);
            
            // Store token in database
            $db->storePasswordResetToken($user['id'], $token, $expires);
            
            // Send reset email
            $mailer = new Mailer();
            $resetLink = SITE_URL . '/reset-password.php?token=' . $token;
            
            $mailer->sendPasswordResetEmail(
                $user['email'],
                $user['first_name'],
                $resetLink
            );
            
            $success = true;
        } else {
            // Don't reveal whether email exists for security
            $success = true;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password | <?php echo htmlspecialchars(SITE_NAME); ?></title>
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
        .forgot-container {
            background: rgba(255, 255, 255, 0.96);
            padding: 2.5rem;
            border-radius: 12px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.15);
            width: 100%;
            max-width: 450px;
            backdrop-filter: blur(5px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .forgot-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        .forgot-icon {
            font-size: 3.5rem;
            color: #4e73df;
            margin-bottom: 1.5rem;
        }
        .btn-reset {
            height: 50px;
            border-radius: 8px;
            font-weight: 500;
            letter-spacing: 0.5px;
        }
        .login-link {
            text-align: center;
            margin-top: 1.5rem;
        }
    </style>
</head>
<body>
    <div class="forgot-container">
        <div class="forgot-header">
            <div class="forgot-icon">
                <i class="fas fa-key"></i>
            </div>
            <h3>Forgot Password</h3>
            <p class="text-muted">
                <?php if ($success): ?>
                    If an account exists with this email, you'll receive a password reset link.
                <?php else: ?>
                    Enter your email to receive a password reset link
                <?php endif; ?>
            </p>
        </div>
        
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <?php foreach ($errors as $error): ?>
                    <p class="mb-1"><i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle me-2"></i>
                Password reset instructions have been sent to your email if it exists in our system.
            </div>
            <div class="text-center mt-4">
                <a href="login.php" class="text-decoration-none">
                    <i class="fas fa-arrow-left me-1"></i> Back to Login
                </a>
            </div>
        <?php else: ?>
            <form action="forgot-password.php" method="POST" autocomplete="off">
                <div class="mb-4">
                    <label for="email" class="form-label">Email Address</label>
                    <input type="email" class="form-control form-control-lg" id="email" name="email" 
                           placeholder="Enter your email address" required autofocus>
                </div>
                
                <button type="submit" class="btn btn-primary btn-reset w-100 mb-4">
                    <i class="fas fa-paper-plane me-2"></i> Send Reset Link
                </button>
                
                <div class="login-link">
                    <a href="login.php" class="text-decoration-none">
                        <i class="fas fa-arrow-left me-1"></i> Remember your password? Login
                    </a>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Focus on email field
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('email').focus();
        });
    </script>
</body>
</html>