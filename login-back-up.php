<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'Database.php';
require_once 'Mailer.php';
require_once 'TwoFactorAuth.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit();
}

// Initialize classes
$db = new Database();
$mailer = new Mailer();
$twoFactorAuth = new TwoFactorAuth();

// Handle login
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $remember = isset($_POST['remember']);

    // Validate inputs
    if (empty($email) || empty($password)) {
        $errors[] = "Please fill in all fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address.";
    } else {
        // Get user from database
        $user = $db->getUserByEmail($email);

        if ($user && password_verify($password, $user['password_hash'])) {
            // Update last login time
            $db->updateLastLogin($user['id']);
            
            // Check if 2FA is enabled
            if (!empty($user['two_factor_secret'])) {
                // Store user data in session for 2FA verification
                $_SESSION['2fa_user'] = $user;
                
                // Generate both TOTP and email codes
                $emailCode = rand(100000, 999999); // 6-digit code
                $_SESSION['2fa_email_code'] = $emailCode;
                $_SESSION['2fa_code_expires'] = time() + 600; // 10 minutes
                
                // Send email with code
                $mailer->send2FACode($user['email'], $user['first_name'], $emailCode);
                
                // Redirect to 2FA verification
                header('Location: verify-2fa.php');
                exit();
            } else {
                // Set complete session variables
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
                $_SESSION['id'] = $user['id'];
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['user_role'] = $user['role'];

                // Set remember me cookie if selected
                if ($remember) {
                    $token = bin2hex(random_bytes(32));
                    $expiry = time() + 60 * 60 * 24 * 30; // 30 days
                    setcookie('remember_token', $token, $expiry, '/', '', true, true);
                    
                    // Store token in database
                    $db->query(
                        "INSERT INTO auth_tokens (user_id, token, expires_at) VALUES (?, ?, ?)",
                        [$user['id'], hash('sha256', $token), date('Y-m-d H:i:s', $expiry)]
                    );
                }

                // Log the login activity
                $db->logActivity(
                    $user['id'],
                    'login',
                    'User logged in',
                    $_SERVER['REMOTE_ADDR'],
                    $_SERVER['HTTP_USER_AGENT'] ?? ''
                );
                
                // Redirect to dashboard
                header('Location: dashboard.php');
                exit();
            }
        } else {
            $errors[] = "Invalid email or password.";
            
            // Log failed login attempt
            $db->logActivity(
                null,
                'failed_login',
                'Failed login attempt for email: ' . $email,
                $_SERVER['REMOTE_ADDR'],
                $_SERVER['HTTP_USER_AGENT'] ?? ''
            );
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | <?php echo htmlspecialchars(SITE_NAME); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/login.css">
    <style>
        body {
            background-color: #f8f9fa;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background-image: url('assets/images/auth-bg.jpg');
            background-size: cover;
            background-position: center;
        }
        .login-container {
            background: rgba(255, 255, 255, 0.95);
            padding: 2.5rem;
            border-radius: 12px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
            width: 100%;
            max-width: 420px;
            backdrop-filter: blur(5px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .login-logo {
            text-align: center;
            margin-bottom: 2rem;
        }
        .login-logo img {
            height: 70px;
            margin-bottom: 1rem;
        }
        .login-title {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 0.5rem;
        }
        .login-subtitle {
            color: #7f8c8d;
            margin-bottom: 2rem;
            font-size: 0.95rem;
        }
        .form-control {
            height: 50px;
            border-radius: 8px;
            padding: 0.5rem 1rem;
            border: 1px solid #ddd;
            transition: all 0.3s;
        }
        .form-control:focus {
            border-color: #4e73df;
            box-shadow: 0 0 0 0.25rem rgba(78, 115, 223, 0.25);
        }
        .input-group-text {
            background-color: #f8f9fa;
            border-right: none;
        }
        .form-control.with-icon {
            border-left: none;
        }
        .btn-login {
            height: 50px;
            background-color: #4e73df;
            border: none;
            width: 100%;
            border-radius: 8px;
            font-weight: 500;
            letter-spacing: 0.5px;
            transition: all 0.3s;
        }
        .btn-login:hover {
            background-color: #2e59d9;
            transform: translateY(-2px);
        }
        .form-check-input:checked {
            background-color: #4e73df;
            border-color: #4e73df;
        }
        .forgot-password {
            text-align: center;
            margin-top: 1.5rem;
        }
        .forgot-password a {
            color: #6c757d;
            text-decoration: none;
            transition: color 0.2s;
        }
        .forgot-password a:hover {
            color: #4e73df;
        }
        .error {
            color: #e74a3b;
            margin-bottom: 1.5rem;
            padding: 1rem;
            background-color: #f8d7da;
            border-radius: 8px;
            border-left: 4px solid #e74a3b;
        }
        .error-icon {
            margin-right: 8px;
        }
        .divider {
            display: flex;
            align-items: center;
            margin: 1.5rem 0;
            color: #6c757d;
            font-size: 0.85rem;
        }
        .divider::before, .divider::after {
            content: "";
            flex: 1;
            border-bottom: 1px solid #dee2e6;
        }
        .divider::before {
            margin-right: 1rem;
        }
        .divider::after {
            margin-left: 1rem;
        }
        .social-login {
            display: flex;
            justify-content: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        .social-btn {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            transition: transform 0.3s;
        }
        .social-btn:hover {
            transform: translateY(-3px);
        }
        .google-btn {
            background-color: #db4437;
        }
        .facebook-btn {
            background-color: #4267B2;
        }
        .twitter-btn {
            background-color: #1DA1F2;
        }
        .register-link {
            text-align: center;
            margin-top: 1.5rem;
            color: #6c757d;
        }
        .register-link a {
            color: #4e73df;
            font-weight: 500;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-logo">
            <i class="fas fa-project-diagram fa-3x text-primary mb-3"></i>
            <h3 class="login-title">Welcome to <?php echo htmlspecialchars(SITE_NAME); ?></h3>
            <p class="login-subtitle">Please sign in to continue</p>
        </div>
        
        <?php if (!empty($errors)): ?>
            <div class="error">
                <?php foreach ($errors as $error): ?>
                    <p class="mb-1"><i class="fas fa-exclamation-circle error-icon"></i><?php echo htmlspecialchars($error); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <form action="login.php" method="POST" autocomplete="off">
            <div class="mb-3">
                <label for="email" class="form-label">Email Address</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                    <input type="email" class="form-control with-icon" id="email" name="email" required 
                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                           placeholder="Enter your email address">
                </div>
            </div>
            
            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                    <input type="password" class="form-control with-icon" id="password" name="password" required
                           placeholder="Enter your password">
                </div>
            </div>
            
            <div class="mb-3 d-flex justify-content-between align-items-center">
                <div class="form-check">
                    <input type="checkbox" class="form-check-input" id="remember" name="remember">
                    <label class="form-check-label" for="remember">Remember me</label>
                </div>
                <div>
                    <a href="forgot-password.php" class="text-decoration-none small">Forgot password?</a>
                </div>
            </div>
            
            <button type="submit" class="btn btn-primary btn-login mb-3">
                <i class="fas fa-sign-in-alt me-2"></i>Login
            </button>
            
            <?php if (ALLOW_SOCIAL_LOGIN): ?>
                <div class="divider">OR</div>
                <div class="social-login">
                    <a href="#" class="social-btn google-btn">
                        <i class="fab fa-google"></i>
                    </a>
                    <a href="#" class="social-btn facebook-btn">
                        <i class="fab fa-facebook-f"></i>
                    </a>
                    <a href="#" class="social-btn twitter-btn">
                        <i class="fab fa-twitter"></i>
                    </a>
                </div>
            <?php endif; ?>
            
           <?php if (defined('ALLOW_SOCIAL_LOGIN') && ALLOW_SOCIAL_LOGIN): ?>
    <div class="divider">OR</div>
    <div class="social-login">
        <a href="#" class="social-btn google-btn">
            <i class="fab fa-google"></i>
        </a>
        <a href="#" class="social-btn facebook-btn">
            <i class="fab fa-facebook-f"></i>
        </a>
        <a href="#" class="social-btn twitter-btn">
            <i class="fab fa-twitter"></i>
        </a>
    </div>
<?php endif; ?>
        </form>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Simple animation on load
        document.addEventListener('DOMContentLoaded', function() {
            const loginContainer = document.querySelector('.login-container');
            loginContainer.style.opacity = '0';
            loginContainer.style.transform = 'translateY(20px)';
            
            setTimeout(() => {
                loginContainer.style.transition = 'all 0.4s ease-out';
                loginContainer.style.opacity = '1';
                loginContainer.style.transform = 'translateY(0)';
            }, 100);
            
            // Focus on email field by default
            document.getElementById('email').focus();
        });
    </script>
</body>
</html>