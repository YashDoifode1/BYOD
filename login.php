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
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        'inter': ['Inter', 'sans-serif'],
                    },
                    colors: {
                        'primary': {
                            50: '#eff6ff',
                            100: '#dbeafe',
                            500: '#3b82f6',
                            600: '#2563eb',
                            700: '#1d4ed8',
                        },
                        'dark': {
                            800: '#1f2937',
                            900: '#111827',
                        }
                    },
                    animation: {
                        'float': 'float 6s ease-in-out infinite',
                        'pulse-slow': 'pulse 3s cubic-bezier(0.4, 0, 0.6, 1) infinite',
                        'slide-up': 'slideUp 0.5s ease-out',
                        'fade-in': 'fadeIn 0.6s ease-out',
                    },
                    keyframes: {
                        float: {
                            '0%, 100%': { transform: 'translateY(0px)' },
                            '50%': { transform: 'translateY(-20px)' },
                        },
                        slideUp: {
                            '0%': { transform: 'translateY(30px)', opacity: '0' },
                            '100%': { transform: 'translateY(0)', opacity: '1' },
                        },
                        fadeIn: {
                            '0%': { opacity: '0' },
                            '100%': { opacity: '1' },
                        }
                    }
                }
            }
        }
    </script>
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
        .glass-effect {
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
        }
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .mesh-bg {
            background-image: 
                radial-gradient(at 40% 20%, hsla(228,100%,70%,1) 0px, transparent 50%),
                radial-gradient(at 80% 0%, hsla(189,100%,56%,1) 0px, transparent 50%),
                radial-gradient(at 0% 50%, hsla(355,100%,93%,1) 0px, transparent 50%),
                radial-gradient(at 80% 50%, hsla(340,100%,76%,1) 0px, transparent 50%),
                radial-gradient(at 0% 100%, hsla(22,100%,77%,1) 0px, transparent 50%),
                radial-gradient(at 80% 100%, hsla(242,100%,70%,1) 0px, transparent 50%),
                radial-gradient(at 0% 0%, hsla(343,100%,76%,1) 0px, transparent 50%);
        }
        .input-focus:focus {
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        .hover-lift:hover {
            transform: translateY(-2px);
        }
    </style>
</head>
<body class="min-h-screen mesh-bg flex items-center justify-center p-4">
    <!-- Background Elements -->
    <div class="absolute inset-0 overflow-hidden pointer-events-none">
        <div class="absolute top-20 left-20 w-72 h-72 bg-blue-300 rounded-full mix-blend-multiply filter blur-xl opacity-20 animate-float"></div>
        <div class="absolute top-40 right-20 w-72 h-72 bg-purple-300 rounded-full mix-blend-multiply filter blur-xl opacity-20 animate-float" style="animation-delay: 2s;"></div>
        <div class="absolute -bottom-32 left-40 w-72 h-72 bg-pink-300 rounded-full mix-blend-multiply filter blur-xl opacity-20 animate-float" style="animation-delay: 4s;"></div>
    </div>

    <!-- Main Container -->
    <div class="relative w-full max-w-md">
        <!-- Login Card -->
        <div class="bg-white/90 glass-effect rounded-3xl shadow-2xl p-8 border border-white/20 animate-slide-up">
            <!-- Logo & Header -->
            <div class="text-center mb-8 animate-fade-in">
                <div class="inline-flex items-center justify-center w-16 h-16 bg-gradient-to-br from-blue-500 to-purple-600 rounded-2xl mb-4 shadow-lg">
                    <i class="fas fa-rocket text-white text-2xl"></i>
                </div>
                <h1 class="text-3xl font-bold text-gray-900 mb-2">Welcome Back!</h1>
                <p class="text-gray-600 font-medium">Sign in to <?php echo htmlspecialchars(SITE_NAME); ?></p>
            </div>

            <!-- Error Messages -->
            <?php if (!empty($errors)): ?>
                <div class="mb-6 p-4 bg-red-50 border-l-4 border-red-400 rounded-lg animate-slide-up">
                    <?php foreach ($errors as $error): ?>
                        <div class="flex items-center text-red-700">
                            <i class="fas fa-exclamation-circle mr-2 text-red-500"></i>
                            <span class="text-sm font-medium"><?php echo htmlspecialchars($error); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Login Form -->
            <form action="login.php" method="POST" class="space-y-6" autocomplete="off">
                <!-- Email Input -->
                <div class="space-y-2">
                    <label for="email" class="block text-sm font-semibold text-gray-700">Email Address</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-envelope text-gray-400"></i>
                        </div>
                        <input 
                            type="email" 
                            id="email" 
                            name="email" 
                            required
                            value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                            placeholder="Enter your email"
                            class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200 input-focus bg-white/80"
                        >
                    </div>
                </div>

                <!-- Password Input -->
                <div class="space-y-2">
                    <label for="password" class="block text-sm font-semibold text-gray-700">Password</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-lock text-gray-400"></i>
                        </div>
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            required
                            placeholder="Enter your password"
                            class="w-full pl-10 pr-12 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200 input-focus bg-white/80"
                        >
                        <button type="button" onclick="togglePassword()" class="absolute inset-y-0 right-0 pr-3 flex items-center">
                            <i id="passwordToggle" class="fas fa-eye text-gray-400 hover:text-gray-600 transition-colors"></i>
                        </button>
                    </div>
                </div>

                <!-- Remember Me & Forgot Password -->
                <div class="flex items-center justify-between">
                    <label class="flex items-center cursor-pointer">
                        <input type="checkbox" name="remember" class="sr-only">
                        <div class="relative">
                            <div class="w-5 h-5 bg-white border-2 border-gray-300 rounded transition-all duration-200 checkbox-bg"></div>
                            <i class="fas fa-check absolute top-0.5 left-0.5 text-xs text-white opacity-0 checkbox-icon transition-opacity duration-200"></i>
                        </div>
                        <span class="ml-3 text-sm font-medium text-gray-700">Remember me</span>
                    </label>
                    <a href="forgot-password.php" class="text-sm font-semibold text-blue-600 hover:text-blue-700 transition-colors">
                        Forgot password?
                    </a>
                </div>

                <!-- Login Button -->
                <button 
                    type="submit" 
                    class="w-full bg-gradient-to-r from-blue-600 to-purple-600 text-white font-semibold py-3 px-6 rounded-xl hover:from-blue-700 hover:to-purple-700 focus:ring-4 focus:ring-blue-200 transition-all duration-200 transform hover-lift shadow-lg"
                >
                    <i class="fas fa-sign-in-alt mr-2"></i>
                    Sign In
                </button>

                <?php if (defined('ALLOW_SOCIAL_LOGIN') && ALLOW_SOCIAL_LOGIN): ?>
                    <!-- Divider -->
                    <div class="relative my-6">
                        <div class="absolute inset-0 flex items-center">
                            <div class="w-full border-t border-gray-300"></div>
                        </div>
                        <div class="relative flex justify-center text-sm">
                            <span class="px-4 bg-white text-gray-500 font-medium">Or continue with</span>
                        </div>
                    </div>

                    <!-- Social Login -->
                    <div class="grid grid-cols-3 gap-3">
                        <button type="button" class="flex justify-center items-center py-3 px-4 border border-gray-300 rounded-xl hover:bg-gray-50 transition-all duration-200 hover-lift group">
                            <i class="fab fa-google text-red-500 group-hover:scale-110 transition-transform"></i>
                        </button>
                        <button type="button" class="flex justify-center items-center py-3 px-4 border border-gray-300 rounded-xl hover:bg-gray-50 transition-all duration-200 hover-lift group">
                            <i class="fab fa-facebook-f text-blue-600 group-hover:scale-110 transition-transform"></i>
                        </button>
                        <button type="button" class="flex justify-center items-center py-3 px-4 border border-gray-300 rounded-xl hover:bg-gray-50 transition-all duration-200 hover-lift group">
                            <i class="fab fa-twitter text-blue-400 group-hover:scale-110 transition-transform"></i>
                        </button>
                    </div>
                <?php endif; ?>
            </form>

            <!-- Register Link -->
            <div class="mt-8 text-center">
                <p class="text-gray-600">
                    Don't have an account? 
                    <a href="register.php" class="font-semibold text-blue-600 hover:text-blue-700 transition-colors">
                        Sign up here
                    </a>
                </p>
            </div>
        </div>

        <!-- Footer -->
        <div class="text-center mt-6 text-white/80">
            <p class="text-sm">© 2024 <?php echo htmlspecialchars(SITE_NAME); ?>. Made with ❤️ for hackathons.</p>
        </div>
    </div>

    <script>
        // Enhanced animations and interactions
        document.addEventListener('DOMContentLoaded', function() {
            // Focus on email field
            document.getElementById('email').focus();
            
            // Custom checkbox handling
            const checkboxes = document.querySelectorAll('input[type="checkbox"]');
            checkboxes.forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    const bg = this.parentElement.querySelector('.checkbox-bg');
                    const icon = this.parentElement.querySelector('.checkbox-icon');
                    
                    if (this.checked) {
                        bg.classList.add('bg-blue-600', 'border-blue-600');
                        bg.classList.remove('bg-white', 'border-gray-300');
                        icon.classList.remove('opacity-0');
                        icon.classList.add('opacity-100');
                    } else {
                        bg.classList.remove('bg-blue-600', 'border-blue-600');
                        bg.classList.add('bg-white', 'border-gray-300');
                        icon.classList.add('opacity-0');
                        icon.classList.remove('opacity-100');
                    }
                });
            });
        });

        // Password toggle functionality
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.getElementById('passwordToggle');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }

        // Enhanced form interactions
        const inputs = document.querySelectorAll('input[type="email"], input[type="password"]');
        inputs.forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.classList.add('ring-2', 'ring-blue-500');
            });
            
            input.addEventListener('blur', function() {
                this.parentElement.classList.remove('ring-2', 'ring-blue-500');
            });
        });
    </script>
</body>
</html>