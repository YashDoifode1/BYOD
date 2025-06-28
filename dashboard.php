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
    <title>Welcome | <?php echo htmlspecialchars(SITE_NAME); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        'inter': ['Inter', 'sans-serif'],
                        'space': ['Space Grotesk', 'sans-serif'],
                    },
                    colors: {
                        'primary': {
                            50: '#f0f9ff',
                            100: '#e0f2fe',
                            500: '#0ea5e9',
                            600: '#0284c7',
                            700: '#0369a1',
                            800: '#075985',
                            900: '#0c4a6e',
                        },
                        'accent': {
                            500: '#8b5cf6',
                            600: '#7c3aed',
                        },
                        'gray': {
                            50: '#f9fafb',
                            900: '#111827',
                        }
                    },
                    animation: {
                        'float': 'float 6s ease-in-out infinite',
                        'glow': 'glow 2s ease-in-out infinite alternate',
                        'slide-in': 'slideIn 0.6s ease-out',
                        'fade-in': 'fadeIn 0.8s ease-out',
                        'bounce-subtle': 'bounceSubtle 2s infinite',
                        'gradient': 'gradient 15s ease infinite',
                    },
                    keyframes: {
                        float: {
                            '0%, 100%': { transform: 'translateY(0px) rotate(0deg)' },
                            '33%': { transform: 'translateY(-10px) rotate(1deg)' },
                            '66%': { transform: 'translateY(10px) rotate(-1deg)' },
                        },
                        glow: {
                            '0%': { boxShadow: '0 0 20px rgba(14, 165, 233, 0.5)' },
                            '100%': { boxShadow: '0 0 30px rgba(14, 165, 233, 0.8)' },
                        },
                        slideIn: {
                            '0%': { transform: 'translateY(40px)', opacity: '0' },
                            '100%': { transform: 'translateY(0)', opacity: '1' },
                        },
                        fadeIn: {
                            '0%': { opacity: '0' },
                            '100%': { opacity: '1' },
                        },
                        bounceSubtle: {
                            '0%, 100%': { transform: 'translateY(0px)' },
                            '50%': { transform: 'translateY(-5px)' },
                        },
                        gradient: {
                            '0%, 100%': { backgroundPosition: '0% 50%' },
                            '50%': { backgroundPosition: '100% 50%' },
                        }
                    },
                    backgroundSize: {
                        '400%': '400% 400%',
                    }
                }
            }
        }
    </script>
    
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
        
        .glass-morphism {
            background: rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.15);
        }
        
        .gradient-bg {
            background: linear-gradient(-45deg, #667eea, #764ba2, #f093fb, #f5576c);
            background-size: 400% 400%;
            animation: gradient 15s ease infinite;
        }
        
        .neo-shadow {
            box-shadow: 
                20px 20px 60px rgba(14, 165, 233, 0.15),
                -20px -20px 60px rgba(139, 92, 246, 0.15);
        }
        
        .input-glow:focus {
            box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.1);
            transform: translateY(-1px);
        }
        
        .btn-hover {
            transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        
        .btn-hover:hover {
            transform: translateY(-3px) scale(1.02);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
        }
        
        .floating-shapes {
            position: absolute;
            width: 100%;
            height: 100%;
            overflow: hidden;
            pointer-events: none;
        }
        
        .shape {
            position: absolute;
            opacity: 0.1;
        }
        
        .shape-1 {
            top: 20%;
            left: 10%;
            width: 80px;
            height: 80px;
            background: linear-gradient(45deg, #667eea, #764ba2);
            border-radius: 50%;
            animation: float 8s ease-in-out infinite;
        }
        
        .shape-2 {
            top: 60%;
            right: 10%;
            width: 120px;
            height: 60px;
            background: linear-gradient(45deg, #f093fb, #f5576c);
            border-radius: 30px;
            animation: float 10s ease-in-out infinite reverse;
        }
        
        .shape-3 {
            bottom: 20%;
            left: 20%;
            width: 60px;
            height: 60px;
            background: linear-gradient(45deg, #0ea5e9, #8b5cf6);
            transform: rotate(45deg);
            animation: float 12s ease-in-out infinite;
        }
    </style>
</head>

<body class="min-h-screen gradient-bg relative">
    <!-- Floating Background Shapes -->
    <div class="floating-shapes">
        <div class="shape shape-1"></div>
        <div class="shape shape-2"></div>
        <div class="shape shape-3"></div>
    </div>

    <!-- Main Container -->
    <div class="min-h-screen flex">
        <!-- Left Side - Branding & Features -->
        <div class="hidden lg:flex lg:w-1/2 flex-col justify-center px-12 relative">
            <div class="max-w-lg animate-fade-in">
                <!-- Logo -->
                <div class="flex items-center mb-8">
                    <div class="w-12 h-12 bg-white/20 rounded-2xl flex items-center justify-center mr-4 glass-morphism">
                        <i class="fas fa-rocket text-2xl text-white"></i>
                    </div>
                    <h1 class="text-3xl font-bold text-white font-space">
                        <?php echo htmlspecialchars(SITE_NAME); ?>
                    </h1>
                </div>
                
                <!-- Hero Text -->
                <h2 class="text-5xl font-bold text-white mb-6 leading-tight">
                    Build the
                    <span class="bg-gradient-to-r from-yellow-400 to-pink-400 bg-clip-text text-transparent">
                        Future
                    </span>
                    Together
                </h2>
                
                <p class="text-xl text-white/80 mb-12 leading-relaxed">
                    Join thousands of innovators creating breakthrough solutions at hackathons worldwide. 
                    Your next big idea starts here.
                </p>
                
                <!-- Feature List -->
                <div class="space-y-6">
                    <div class="flex items-center">
                        <div class="w-10 h-10 bg-white/20 rounded-full flex items-center justify-center mr-4 glass-morphism">
                            <i class="fas fa-users text-white"></i>
                        </div>
                        <div>
                            <h3 class="text-white font-semibold">Connect with Teams</h3>
                            <p class="text-white/70 text-sm">Find like-minded developers and designers</p>
                        </div>
                    </div>
                    
                    <div class="flex items-center">
                        <div class="w-10 h-10 bg-white/20 rounded-full flex items-center justify-center mr-4 glass-morphism">
                            <i class="fas fa-trophy text-white"></i>
                        </div>
                        <div>
                            <h3 class="text-white font-semibold">Win Prizes</h3>
                            <p class="text-white/70 text-sm">Compete for amazing rewards and recognition</p>
                        </div>
                    </div>
                    
                    <div class="flex items-center">
                        <div class="w-10 h-10 bg-white/20 rounded-full flex items-center justify-center mr-4 glass-morphism">
                            <i class="fas fa-lightbulb text-white"></i>
                        </div>
                        <div>
                            <h3 class="text-white font-semibold">Learn & Grow</h3>
                            <p class="text-white/70 text-sm">Expand your skills with expert mentorship</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Side - Login Form -->
        <div class="w-full lg:w-1/2 flex items-center justify-center p-8">
            <div class="w-full max-w-md">
                <!-- Login Card -->
                <div class="glass-morphism rounded-3xl p-8 neo-shadow animate-slide-in">
                    <!-- Header -->
                    <div class="text-center mb-8">
                        <div class="inline-flex items-center justify-center w-16 h-16 bg-gradient-to-br from-primary-500 to-accent-500 rounded-2xl mb-4 animate-glow">
                            <i class="fas fa-sign-in-alt text-white text-2xl"></i>
                        </div>
                        <h2 class="text-3xl font-bold text-white mb-2 font-space">Welcome Back</h2>
                        <p class="text-white/80">Ready to build something amazing?</p>
                    </div>

                    <!-- Error Messages -->
                    <?php if (!empty($errors)): ?>
                        <div class="mb-6 p-4 bg-red-500/20 border border-red-500/30 rounded-xl backdrop-blur-sm">
                            <?php foreach ($errors as $error): ?>
                                <div class="flex items-center text-red-200">
                                    <i class="fas fa-exclamation-triangle mr-2 text-red-300"></i>
                                    <span class="text-sm"><?php echo htmlspecialchars($error); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Login Form -->
                    <form method="POST" class="space-y-6">
                        <!-- Email Input -->
                        <div class="space-y-2">
                            <label for="email" class="block text-sm font-semibold text-white/90">
                                Email Address
                            </label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                    <i class="fas fa-envelope text-white/50"></i>
                                </div>
                                <input 
                                    type="email" 
                                    id="email" 
                                    name="email" 
                                    required
                                    value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                                    placeholder="your@email.com"
                                    class="w-full pl-12 pr-4 py-4 bg-white/10 border border-white/20 rounded-xl text-white placeholder-white/50 focus:bg-white/20 focus:border-primary-400 transition-all duration-300 input-glow backdrop-blur-sm"
                                >
                            </div>
                        </div>

                        <!-- Password Input -->
                        <div class="space-y-2">
                            <label for="password" class="block text-sm font-semibold text-white/90">
                                Password
                            </label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                    <i class="fas fa-lock text-white/50"></i>
                                </div>
                                <input 
                                    type="password" 
                                    id="password" 
                                    name="password" 
                                    required
                                    placeholder="Enter your password"
                                    class="w-full pl-12 pr-12 py-4 bg-white/10 border border-white/20 rounded-xl text-white placeholder-white/50 focus:bg-white/20 focus:border-primary-400 transition-all duration-300 input-glow backdrop-blur-sm"
                                >
                                <button 
                                    type="button" 
                                    onclick="togglePassword()" 
                                    class="absolute inset-y-0 right-0 pr-4 flex items-center"
                                >
                                    <i id="passwordToggle" class="fas fa-eye text-white/50 hover:text-white/80 transition-colors"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Remember Me & Forgot Password -->
                        <div class="flex items-center justify-between">
                            <label class="flex items-center cursor-pointer group">
                                <input type="checkbox" name="remember" class="sr-only">
                                <div class="relative">
                                    <div class="w-5 h-5 bg-white/20 border border-white/30 rounded transition-all duration-200 group-hover:border-primary-400"></div>
                                    <i class="fas fa-check absolute top-0.5 left-0.5 text-xs text-primary-400 opacity-0 transition-opacity duration-200"></i>
                                </div>
                                <span class="ml-3 text-sm text-white/80 group-hover:text-white transition-colors">
                                    Remember me
                                </span>
                            </label>
                            <a href="#" class="text-sm text-primary-300 hover:text-primary-200 transition-colors">
                                Forgot password?
                            </a>
                        </div>

                        <!-- Login Button -->
                        <button 
                            type="submit" 
                            class="w-full bg-gradient-to-r from-primary-500 to-accent-500 text-white font-bold py-4 px-6 rounded-xl hover:from-primary-600 hover:to-accent-600 focus:ring-4 focus:ring-primary-200/20 transition-all duration-300 btn-hover shadow-lg"
                        >
                            <i class="fas fa-rocket mr-2"></i>
                            Launch Into Dashboard
                        </button>

                        <!-- Social Login -->
                        <div class="relative my-8">
                            <div class="absolute inset-0 flex items-center">
                                <div class="w-full border-t border-white/20"></div>
                            </div>
                            <div class="relative flex justify-center text-sm">
                                <span class="px-4 bg-transparent text-white/60">Or continue with</span>
                            </div>
                        </div>

                        <div class="grid grid-cols-3 gap-4">
                            <button type="button" class="flex justify-center items-center py-3 px-4 bg-white/10 border border-white/20 rounded-xl hover:bg-white/20 transition-all duration-200 group backdrop-blur-sm">
                                <i class="fab fa-google text-red-400 group-hover:scale-110 transition-transform"></i>
                            </button>
                            <button type="button" class="flex justify-center items-center py-3 px-4 bg-white/10 border border-white/20 rounded-xl hover:bg-white/20 transition-all duration-200 group backdrop-blur-sm">
                                <i class="fab fa-github text-white group-hover:scale-110 transition-transform"></i>
                            </button>
                            <button type="button" class="flex justify-center items-center py-3 px-4 bg-white/10 border border-white/20 rounded-xl hover:bg-white/20 transition-all duration-200 group backdrop-blur-sm">
                                <i class="fab fa-discord text-indigo-400 group-hover:scale-110 transition-transform"></i>
                            </button>
                        </div>
                    </form>

                    <!-- Register Link -->
                    <div class="mt-8 text-center">
                        <p class="text-white/70">
                            New to hackathons? 
                            <a href="register.php" class="font-semibold text-primary-300 hover:text-primary-200 transition-colors">
                                Join the movement →
                            </a>
                        </p>
                    </div>
                </div>

                <!-- Stats -->
                <div class="mt-8 grid grid-cols-3 gap-4 text-center">
                    <div class="glass-morphism rounded-xl p-4">
                        <div class="text-2xl font-bold text-white">50K+</div>
                        <div class="text-white/60 text-xs">Developers</div>
                    </div>
                    <div class="glass-morphism rounded-xl p-4">
                        <div class="text-2xl font-bold text-white">1.2K</div>
                        <div class="text-white/60 text-xs">Hackathons</div>
                    </div>
                    <div class="glass-morphism rounded-xl p-4">
                        <div class="text-2xl font-bold text-white">$2M+</div>
                        <div class="text-white/60 text-xs">In Prizes</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Enhanced interactions
        document.addEventListener('DOMContentLoaded', function() {
            // Focus on email field
            document.getElementById('email').focus();
            
            // Custom checkbox handling
            const checkbox = document.querySelector('input[type="checkbox"]');
            const checkIcon = checkbox.parentElement.querySelector('.fa-check');
            const checkBg = checkbox.parentElement.querySelector('div div');
            
            checkbox.addEventListener('change', function() {
                if (this.checked) {
                    checkIcon.style.opacity = '1';
                    checkBg.style.backgroundColor = 'rgba(14, 165, 233, 0.8)';
                    checkBg.style.borderColor = 'rgb(14, 165, 233)';
                } else {
                    checkIcon.style.opacity = '0';
                    checkBg.style.backgroundColor = 'rgba(255, 255, 255, 0.2)';
                    checkBg.style.borderColor = 'rgba(255, 255, 255, 0.3)';
                }
            });
            
            // Input animations
            const inputs = document.querySelectorAll('input[type="email"], input[type="password"]');
            inputs.forEach(input => {
                input.addEventListener('focus', function() {
                    this.parentElement.style.transform = 'scale(1.02)';
                });
                
                input.addEventListener('blur', function() {
                    this.parentElement.style.transform = 'scale(1)';
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

        // Add loading state to form submission
        document.querySelector('form').addEventListener('submit', function() {
            const button = this.querySelector('button[type="submit"]');
            button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Launching...';
            button.disabled = true;
        });
    </script>
</body>
</html>