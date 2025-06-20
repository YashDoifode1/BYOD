<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/auth.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit();
}

// Database connection using PDO (as in your dashboard)
try {
    $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Handle login
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];

    // Validate inputs
    if (empty($email) || empty($password)) {
        $errors[] = "Please fill in all fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address.";
    } else {
        // Prepare and execute query using PDO
        $stmt = $pdo->prepare("SELECT id, username, email, password_hash, role, first_name, last_name, two_factor_secret FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password_hash'])) {
            // Update last login time
            $update_stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $update_stmt->execute([$user['id']]);
            
            // Check if 2FA is enabled
            if (!empty($user['two_factor_secret'])) {
                // Store user data in session for 2FA verification
                $_SESSION['2fa_user'] = $user;
                $_SESSION['2fa_required'] = true;
                
                // Redirect to 2FA verification
                header('Location: verify-2fa.php');
                exit();
            } else {
                // Set complete session variables (matches your dashboard)
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

                // Log the login activity
                $log_stmt = $pdo->prepare("
                    INSERT INTO activity_logs (user_id, action, description, ip_address, user_agent)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $log_stmt->execute([
                    $user['id'],
                    'login',
                    'User logged in',
                    $_SERVER['REMOTE_ADDR'],
                    $_SERVER['HTTP_USER_AGENT'] ?? ''
                ]);
                
                // Redirect to dashboard
                header('Location: dashboard.php');
                exit();
            }
        } else {
            $errors[] = "Invalid email or password.";
            
            // Log failed login attempt
            $log_stmt = $pdo->prepare("
                INSERT INTO activity_logs (action, description, ip_address, user_agent)
                VALUES (?, ?, ?, ?)
            ");
            $log_stmt->execute([
                'failed_login',
                'Failed login attempt for email: ' . $email,
                $_SERVER['REMOTE_ADDR'],
                $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | <?php echo SITE_NAME; ?></title>
    <!-- Use the same style framework as your dashboard -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .login-container {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
        }
        .login-logo {
            text-align: center;
            margin-bottom: 1.5rem;
        }
        .login-logo img {
            height: 60px;
        }
        .form-control {
            height: 45px;
            margin-bottom: 1rem;
        }
        .btn-login {
            height: 45px;
            background-color: #4e73df;
            border: none;
            width: 100%;
        }
        .btn-login:hover {
            background-color: #2e59d9;
        }
        .forgot-password {
            text-align: center;
            margin-top: 1rem;
        }
        .error {
            color: #e74a3b;
            margin-bottom: 1rem;
            padding: 0.5rem;
            background-color: #f8d7da;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-logo">
            <i class="fas fa-project-diagram fa-3x text-primary mb-3"></i>
            <h4 class="mb-4"><?php echo SITE_NAME; ?></h4>
        </div>
        
        <?php if (!empty($errors)): ?>
            <div class="error">
                <?php foreach ($errors as $error): ?>
                    <p class="mb-1"><i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <form action="login.php" method="POST">
            <div class="mb-3">
                <label for="email" class="form-label">Email Address</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                    <input type="email" class="form-control" id="email" name="email" required 
                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                </div>
            </div>
            
            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>
            </div>
            
            <div class="mb-3 form-check">
                <input type="checkbox" class="form-check-input" id="remember" name="remember">
                <label class="form-check-label" for="remember">Remember me</label>
            </div>
            
            <button type="submit" class="btn btn-primary btn-login">
                <i class="fas fa-sign-in-alt me-2"></i>Login
            </button>
            
            <div class="forgot-password">
                <a href="forgot-password.php" class="text-decoration-none">Forgot Password?</a>
            </div>
        </form>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>