<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';
require_once 'Database.php';
require_once 'Mailer.php';
require_once 'TwoFactorAuth.php';
require_once 'includes/DeviceManager.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit();
}

// Initialize classes
$db = new Database();
$deviceManager = new DeviceManager($db);
$mailer = new Mailer();
$twoFactorAuth = new TwoFactorAuth();

// Handle login
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $remember = isset($_POST['remember']);

    try {
        // Generate device fingerprint (will throw DeviceBlockedException if untrusted)
        $fingerprint = $deviceManager->generateDeviceFingerprint();
        
        // Authenticate user
        $user = $db->getUserByEmail($email);
        $userId = $user && password_verify($password, $user['password_hash']) ? $user['id'] : null;

        if (!$userId) {
            $errors[] = "Invalid email or password.";
            $db->logActivity(
                null,
                'failed_login',
                'Failed login attempt for email: ' . $email,
                $_SERVER['REMOTE_ADDR'],
                $_SERVER['HTTP_USER_AGENT'] ?? '',
                $fingerprint
            );
            sleep(min(countFailedAttempts($email), 5)); // Max 5 second delay
        } elseif ($deviceManager->registerDevice($userId, $fingerprint)) {
            // Check device risk level
            $riskLevel = $deviceManager->assessDeviceRisk($fingerprint, $userId);
            
            // Update last login time
            $db->updateLastLogin($userId);
            
            // Check if 2FA is required
            $require2FA = !empty($user['two_factor_secret']) || $riskLevel === 'high';
            
            if ($require2FA) {
                // Store user data in session for 2FA verification
                $_SESSION['2fa_user'] = $user;
                $_SESSION['2fa_device_fingerprint'] = $fingerprint;
                $_SESSION['2fa_remember'] = $remember;
                $_SESSION['2fa_risk_level'] = $riskLevel;
                
                // Generate and send 2FA code
                $emailCode = rand(100000, 999999);
                $_SESSION['2fa_email_code'] = $emailCode;
                $_SESSION['2fa_code_expires'] = time() + 600;
                
                $mailer->send2FACode($user['email'], $user['first_name'], $emailCode);
                
                // Log 2FA initiation
                $db->logActivity(
                    $userId,
                    '2fa_initiated',
                    '2FA initiated for login from ' . ($riskLevel === 'high' ? 'high-risk' : 'medium-risk') . ' device',
                    $_SERVER['REMOTE_ADDR'],
                    $_SERVER['HTTP_USER_AGENT'] ?? '',
                    $fingerprint
                );
                
                header('Location: verify-2fa.php');
                exit();
            } else {
                // Set session variables
                $_SESSION['user'] = [
                    'user_id' => $user['id'],
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'email' => $user['email'],
                    'user_role' => $user['role'],
                    'role' => $user['role'],
                    'first_name' => $user['first_name'],
                    'last_name' => $user['last_name'],
                    'device_fingerprint' => $fingerprint,
                    'device_trusted' => true,
                    'last_login' => time()
                ];
                
                // Set standard session vars
                $_SESSION['id'] = $user['id'];
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['login_time'] = time();

                // Set remember me cookie
                if ($remember) {
                    $token = bin2hex(random_bytes(32));
                    $expiry = time() + 60 * 60 * 24 * 30;
                    
                    setcookie('remember_token', $token, [
                        'expires' => $expiry,
                        'path' => '/',
                        'domain' => parse_url(SITE_URL, PHP_URL_HOST),
                        'secure' => true,
                        'httponly' => true,
                        'samesite' => 'Strict'
                    ]);
                    
                    $db->query(
                        "INSERT INTO auth_tokens (user_id, token, device_fingerprint, expires_at) 
                         VALUES (?, ?, ?, ?)",
                        [$user['id'], hash('sha256', $token), $fingerprint, date('Y-m-d H:i:s', $expiry)]
                    );
                }

                // Log successful login
                $db->logActivity(
                    $userId,
                    'login',
                    'User logged in from trusted device',
                    $_SERVER['REMOTE_ADDR'],
                    $_SERVER['HTTP_USER_AGENT'] ?? '',
                    $fingerprint
                );
                
                // Set device context cookie
                setcookie('device_context', $fingerprint, [
                    'expires' => time() + 60 * 60 * 24 * 90,
                    'path' => '/',
                    'domain' => parse_url(SITE_URL, PHP_URL_HOST),
                    'secure' => true,
                    'httponly' => false,
                    'samesite' => 'Lax'
                ]);
                
                header('Location: dashboard.php');
                exit();
            }
        }
    } catch (DeviceBlockedException $e) {
        $e->showErrorPage();
        exit();
    } catch (Exception $e) {
        error_log('Login error: ' . $e->getMessage());
        $errors[] = "An error occurred during login. Please try again.";
    }
}

// Helper function to count recent failed attempts
function countFailedAttempts(string $email): int {
    global $db;
    $oneHourAgo = date('Y-m-d H:i:s', time() - 3600);
    return $db->query(
        "SELECT COUNT(*) FROM activity_logs 
         WHERE description LIKE ? 
         AND created_at > ?",
        ["Failed login attempt for email: $email%", $oneHourAgo]
    )->fetchColumn();
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
        .security-badge {
            font-size: 0.8rem;
            color: #28a745;
            margin-top: 1rem;
            text-align: center;
        }
        .security-badge i {
            margin-right: 5px;
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
        
        <form action="login.php" method="POST" autocomplete="off" id="loginForm">
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
            
            <div class="security-badge">
                <i class="fas fa-shield-alt"></i> Secure BYOD Login System
            </div>
            
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
        // Enhanced device fingerprint collection
        async function collectDeviceInfo() {
            const canvas = document.createElement('canvas');
            const gl = canvas.getContext('webgl') || canvas.getContext('experimental-webgl');
            
            let webglInfo = {};
            if (gl) {
                const debugInfo = gl.getExtension('WEBGL_debug_renderer_info');
                webglInfo = {
                    vendor: gl.getParameter(debugInfo ? debugInfo.UNMASKED_VENDOR_WEBGL : gl.VENDOR),
                    renderer: gl.getParameter(debugInfo ? debugInfo.UNMASKED_RENDERER_WEBGL : gl.RENDERER),
                    parameters: {
                        antialiasing: gl.getContextAttributes().antialias,
                        depth: gl.getContextAttributes().depth,
                        stencil: gl.getContextAttributes().stencil
                    }
                };
            }

            let audioContext = {};
            try {
                const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
                const oscillator = audioCtx.createOscillator();
                const analyser = audioCtx.createAnalyser();
                const gainNode = audioCtx.createGain();
                const scriptProcessor = audioCtx.createScriptProcessor(4096, 1, 1);
                
                oscillator.type = 'triangle';
                oscillator.connect(analyser);
                analyser.connect(scriptProcessor);
                scriptProcessor.connect(gainNode);
                gainNode.connect(audioCtx.destination);
                
                oscillator.start(0);
                
                audioContext = {
                    sampleRate: audioCtx.sampleRate,
                    channelCount: audioCtx.destination.channelCount,
                    maxChannelCount: audioCtx.destination.maxChannelCount || 'unknown'
                };
                
                oscillator.stop();
                audioCtx.close();
            } catch (e) {
                audioContext = { error: e.message };
            }

            let batteryInfo = {};
            if ('getBattery' in navigator) {
                try {
                    const battery = await navigator.getBattery();
                    batteryInfo = {
                        charging: battery.charging,
                        chargingTime: battery.chargingTime,
                        dischargingTime: battery.dischargingTime,
                        level: battery.level
                    };
                } catch (e) {
                    batteryInfo = { error: e.message };
                }
            }

            let mediaDevices = [];
            try {
                const devices = await navigator.mediaDevices.enumerateDevices();
                mediaDevices = devices.map(d => ({
                    kind: d.kind,
                    type: d.type,
                    groupId: d.groupId
                }));
            } catch (e) {
                mediaDevices = [{ error: e.message }];
            }

            let storageInfo = {};
            if ('storage' in navigator) {
                try {
                    const storage = await navigator.storage.estimate();
                    storageInfo = {
                        quota: storage.quota,
                        usage: storage.usage,
                        usageDetails: storage.usageDetails || {}
                    };
                } catch (e) {
                    storageInfo = { error: e.message };
                }
            }

            let gpuInfo = {};
            if ('gpu' in navigator) {
                try {
                    const gpu = await navigator.gpu.requestAdapter();
                    if (gpu) {
                        gpuInfo = {
                            vendor: gpu.vendor,
                            architecture: gpu.architecture,
                            description: gpu.description
                        };
                    }
                } catch (e) {
                    gpuInfo = { error: e.message };
                }
            }

            return {
                screen: {
                    width: screen.width,
                    height: screen.height,
                    colorDepth: screen.colorDepth,
                    pixelRatio: window.devicePixelRatio || 1,
                    orientation: window.screen.orientation ? window.screen.orientation.type : 'unknown'
                },
                navigator: {
                    userAgent: navigator.userAgent,
                    platform: navigator.platform,
                    language: navigator.language,
                    languages: navigator.languages,
                    hardwareConcurrency: navigator.hardwareConcurrency || 'unknown',
                    maxTouchPoints: navigator.maxTouchPoints || 0,
                    deviceMemory: navigator.deviceMemory || 'unknown',
                    cpuClass: navigator.cpuClass || 'unknown'
                },
                timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
                webgl: webglInfo,
                fonts: (() => {
                    try {
                        return Array.from(document.fonts).map(f => f.family);
                    } catch (e) {
                        return [];
                    }
                })(),
                audioContext: audioContext,
                batteryInfo: batteryInfo,
                mediaDevices: mediaDevices,
                storage: storageInfo,
                gpuInfo: gpuInfo,
                performance: {
                    memory: window.performance?.memory ? {
                        jsHeapSizeLimit: window.performance.memory.jsHeapSizeLimit,
                        totalJSHeapSize: window.performance.memory.totalJSHeapSize,
                        usedJSHeapSize: window.performance.memory.usedJSHeapSize
                    } : null,
                    timing: window.performance?.timing ? {
                        navigationStart: window.performance.timing.navigationStart
                    } : null
                }
            };
        }

        document.addEventListener('DOMContentLoaded', async function() {
            const deviceInfo = await collectDeviceInfo();
            localStorage.setItem('deviceInfo', JSON.stringify(deviceInfo));
            
            document.cookie = `client_timezone=${encodeURIComponent(Intl.DateTimeFormat().resolvedOptions().timeZone)}; path=/; max-age=${60*60*24*365}; SameSite=Lax`;
            
            const loginContainer = document.querySelector('.login-container');
            loginContainer.style.opacity = '0';
            loginContainer.style.transform = 'translateY(20px)';
            
            setTimeout(() => {
                loginContainer.style.transition = 'all 0.4s ease-out';
                loginContainer.style.opacity = '1';
                loginContainer.style.transform = 'translateY(0)';
            }, 100);
            
            document.getElementById('email').focus();
            
            const form = document.getElementById('loginForm');
            form.addEventListener('submit', function() {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'device_info';
                input.value = JSON.stringify(deviceInfo);
                form.appendChild(input);
            });
        });
    </script>
</body>
</html>