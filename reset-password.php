<?php
session_start();
require_once __DIR__.'/includes/config.php';
require_once __DIR__.'/includes/auth.php';
require_once 'Database.php';

$db = new Database();
$errors = [];
$validToken = false;
$userEmail = '';

// Check if token is valid
if (isset($_GET['token'])) {
    $token = $_GET['token'];
    $tokenData = $db->validatePasswordResetToken($token);
    
    if ($tokenData) {
        $validToken = true;
        $userEmail = $tokenData['email'];
        $_SESSION['reset_token'] = $token; // Store for verification
    } else {
        $errors[] = "Invalid or expired password reset link. Please request a new one.";
    }
} else {
    header('Location: forgot-password.php');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $validToken) {
    $password = trim($_POST['password'] ?? '');
    $confirmPassword = trim($_POST['confirm_password'] ?? '');
    
    // Validate inputs
    if (empty($password)) {
        $errors[] = "Password is required";
    } elseif (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters";
    } elseif ($password !== $confirmPassword) {
        $errors[] = "Passwords do not match";
    }
    
    // If no errors, update password
    if (empty($errors)) {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $db->updateUserPassword($tokenData['user_id'], $hashedPassword);
        
        // Delete the used token
        $db->deletePasswordResetToken($_SESSION['reset_token']);
        unset($_SESSION['reset_token']);
        
        // Set success message
        $_SESSION['password_reset_success'] = true;
        header('Location: login.php');
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password | <?php echo htmlspecialchars(SITE_NAME); ?></title>
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
        .reset-container {
            background: rgba(255, 255, 255, 0.96);
            padding: 2.5rem;
            border-radius: 12px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.15);
            width: 100%;
            max-width: 450px;
            backdrop-filter: blur(5px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .reset-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        .reset-icon {
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
        .password-strength {
            height: 5px;
            background: #e9ecef;
            margin-top: 5px;
            border-radius: 3px;
            overflow: hidden;
        }
        .password-strength-bar {
            height: 100%;
            width: 0%;
            background: #dc3545;
            transition: all 0.3s ease;
        }
    </style>
</head>
<body>
    <div class="reset-container">
        <div class="reset-header">
            <div class="reset-icon">
                <i class="fas fa-key"></i>
            </div>
            <h3>Reset Your Password</h3>
            <p class="text-muted">Create a new password for <?php echo htmlspecialchars($userEmail); ?></p>
        </div>
        
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <?php foreach ($errors as $error): ?>
                    <p class="mb-1"><i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($validToken): ?>
            <form action="reset-password.php?token=<?php echo htmlspecialchars($_GET['token']); ?>" method="POST" autocomplete="off">
                <div class="mb-4">
                    <label for="password" class="form-label">New Password</label>
                    <input type="password" class="form-control form-control-lg" id="password" name="password" 
                           placeholder="Enter new password" required>
                    <div class="password-strength">
                        <div class="password-strength-bar" id="password-strength-bar"></div>
                    </div>
                    <small class="text-muted">Minimum 8 characters</small>
                </div>
                
                <div class="mb-4">
                    <label for="confirm_password" class="form-label">Confirm Password</label>
                    <input type="password" class="form-control form-control-lg" id="confirm_password" name="confirm_password" 
                           placeholder="Confirm new password" required>
                </div>
                
                <button type="submit" class="btn btn-primary btn-reset w-100 mb-4">
                    <i class="fas fa-save me-2"></i> Update Password
                </button>
                
                <div class="text-center">
                    <a href="login.php" class="text-decoration-none">
                        <i class="fas fa-arrow-left me-1"></i> Back to Login
                    </a>
                </div>
            </form>
        <?php else: ?>
            <div class="text-center">
                <a href="forgot-password.php" class="btn btn-primary">
                    <i class="fas fa-redo me-1"></i> Request New Reset Link
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Password strength indicator
        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;
            const strengthBar = document.getElementById('password-strength-bar');
            let strength = 0;
            
            if (password.length >= 8) strength += 1;
            if (password.match(/[a-z]+/)) strength += 1;
            if (password.match(/[A-Z]+/)) strength += 1;
            if (password.match(/[0-9]+/)) strength += 1;
            if (password.match(/[$@#&!]+/)) strength += 1;
            
            // Update strength bar
            switch(strength) {
                case 0:
                    strengthBar.style.width = '0%';
                    strengthBar.style.backgroundColor = '#dc3545';
                    break;
                case 1:
                    strengthBar.style.width = '20%';
                    strengthBar.style.backgroundColor = '#dc3545';
                    break;
                case 2:
                    strengthBar.style.width = '40%';
                    strengthBar.style.backgroundColor = '#fd7e14';
                    break;
                case 3:
                    strengthBar.style.width = '60%';
                    strengthBar.style.backgroundColor = '#ffc107';
                    break;
                case 4:
                    strengthBar.style.width = '80%';
                    strengthBar.style.backgroundColor = '#28a745';
                    break;
                case 5:
                    strengthBar.style.width = '100%';
                    strengthBar.style.backgroundColor = '#28a745';
                    break;
            }
        });
        
        // Focus on password field
        document.addEventListener('DOMContentLoaded', function() {
            if (document.getElementById('password')) {
                document.getElementById('password').focus();
            }
        });
    </script>
</body>
</html>