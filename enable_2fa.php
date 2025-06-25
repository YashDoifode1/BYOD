<?php
session_start();
require_once __DIR__.'/includes/config.php';
require_once __DIR__.'/includes/auth.php';
require_once 'Database.php';
require_once 'TwoFactorAuth.php';

// Redirect if not logged in
if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$db = new Database();
$twoFactorAuth = new TwoFactorAuth();
$user = $_SESSION['user'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'enable') {
        // Generate new secret and backup codes
        $secret = $twoFactorAuth->generateSecret();
        $backupCodes = $twoFactorAuth->generateBackupCodes();
        
        // Store in database
        $db->updateUser2FASecret($user['id'], $secret);
        $db->updateUserBackupCodes($user['id'], $backupCodes);
        
        // Generate QR code
        $qrCode = $twoFactorAuth->generateQRCode(SITE_NAME, $user['email'], $secret);
        
        // Show to user
        $_SESSION['2fa_setup_data'] = [
            'secret' => $secret,
            'qrCode' => $qrCode,
            'backupCodes' => $backupCodes
        ];
        
        header('Location: verify_2fa_setup.php');
        exit();
    }
    elseif ($action === 'disable') {
        // Disable 2FA
        $db->updateUser2FASecret($user['id'], null);
        $db->updateUserBackupCodes($user['id'], null);
        
        $_SESSION['success'] = "Two-factor authentication has been disabled.";
        header('Location: settings/profile.php');
        exit();
    }
}

// Check current 2FA status
$userData = $db->getUserById($user['id']);
$is2FAEnabled = !empty($userData['two_factor_secret']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enable 2FA | <?php echo htmlspecialchars(SITE_NAME); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .main-content {
            margin-left: 400px;
            padding: 30px;
            transition: margin-left 0.3s;
        }
        @media (max-width: 991.98px) {
            .main-content {
                margin-left: 0;
            }
        }
        .container {
            max-width: 800px;
        }
        .card {
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .qr-code-container {
            text-align: center;
            padding: 20px;
            background: white;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .backup-codes {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            font-family: monospace;
        }
        .step {
            margin-bottom: 25px;
        }
        .step-number {
            display: inline-block;
            width: 30px;
            height: 30px;
            background: #4e73df;
            color: white;
            text-align: center;
            border-radius: 50%;
            margin-right: 10px;
            line-height: 30px;
        }
        
        /* Sidebar styling */
        .sidebar {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, 'Open Sans', 'Helvetica Neue', sans-serif;
            box-shadow: 2px 0 8px rgba(0, 0, 0, 0.2);
        }
        .sidebar .nav-link {
            font-size: 0.95rem;
            font-weight: 500;
            margin-bottom: 0.5rem;
            border-radius: 8px;
        }
        .sidebar .nav-link:hover {
            background: rgba(255, 255, 255, 0.1);
            color: #fff !important;
        }
        .sidebar .nav-link.active {
            background: #007bff;
            color: #fff !important;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }
        .sidebar .nav-link i {
            width: 20px;
            text-align: center;
        }
        .sidebar-heading {
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .sidebar-heading a:hover {
            color: #007bff !important;
        }
        .user-profile img {
            transition: transform 0.3s;
        }
        .user-profile img:hover {
            transform: scale(1.1);
        }
        .badge.bg-primary {
            background: linear-gradient(45deg, #007bff, #00b7eb) !important;
        }
        .badge.bg-secondary {
            background: linear-gradient(45deg, #6c757d, #adb5bd) !important;
        }
    </style>
</head>
<body>
    <div class="d-flex">
        <!-- Sidebar -->
        <div class="col-md-3 col-lg-2 d-md-block sidebar" style="background: linear-gradient(180deg, #1a252f 0%, #2d3e50 100%); min-height: 100vh; position: fixed;">
            <div class="sidebar-sticky pt-4 px-3">
                <!-- User Profile Section -->
                <div class="user-profile text-center mb-4 pb-3 border-bottom border-secondary">
                    <div class="user-avatar mb-3 position-relative">
                        <img src="https://ui-avatars.com/api/?name=<?= urlencode($_SESSION['user']['first_name'].' '.$_SESSION['user']['last_name']) ?>&background=random&size=80&rounded=true" 
                             alt="User Avatar" class="rounded-circle shadow-sm" style="width: 80px; height: 80px; object-fit: cover;">
                        <span class="status-indicator" style="position: absolute; bottom: 0; right: 0; width: 12px; height: 12px; background: #28a745; border: 2px solid #fff; border-radius: 50%;"></span>
                    </div>
                    <div class="user-info text-white">
                        <h5 class="mb-1 font-weight-bold"><?= htmlspecialchars($_SESSION['user']['first_name'].' '.$_SESSION['user']['last_name']) ?></h5>
                        <small class="text-light opacity-75">@<?= htmlspecialchars($_SESSION['user']['username']) ?></small>
                        <div class="user-role mt-2">
                            <span class="badge px-3 py-1 <?= $_SESSION['user']['role'] === 'admin' ? 'bg-primary' : 'bg-secondary' ?>" 
                                  style="font-size: 0.9rem; border-radius: 12px;">
                                <?= ucfirst(htmlspecialchars($_SESSION['user']['role'])) ?>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Main Navigation -->
                <ul class="nav flex-column mb-4">
                    <li class="nav-item">
                        <a class="nav-link d-flex align-items-center text-white py-2 px-3 rounded <?= strpos($_SERVER['REQUEST_URI'], 'dashboard.php') !== false ? 'active' : '' ?>" 
                           href="<?= APP_URL ?>/dashboard.php" style="transition: all 0.3s;">
                            <i class="fas fa-tachometer-alt me-3"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <?php if ($_SESSION['user']['role'] === 'admin'): ?>
                    <li class="nav-item">
                        <a class="nav-link d-flex align-items-center text-white py-2 px-3 rounded <?= strpos($_SERVER['REQUEST_URI'], '/admin/') !== false ? 'active' : '' ?>" 
                           href="<?= APP_URL ?>/admin/index.php" style="transition: all 0.3s;">
                            <i class="fas fa-lock me-3"></i>
                            <span>Admin Panel</span>
                        </a>
                    </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a class="nav-link d-flex align-items-center text-white py-2 px-3 rounded <?= strpos($_SERVER['REQUEST_URI'], '/tasks/') !== false ? 'active' : '' ?>" 
                           href="<?= APP_URL ?>/tasks/tasks.php" style="transition: all 0.3s;">
                            <i class="fas fa-tasks me-3"></i>
                            <span>My Tasks</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link d-flex align-items-center text-white py-2 px-3 rounded <?= strpos($_SERVER['REQUEST_URI'], '/chat/') !== false ? 'active' : '' ?>" 
                           href="<?= APP_URL ?>/chat/index.php" style="transition: all 0.3s;">
                            <i class="fas fa-comments me-3"></i>
                            <span>Team Chat</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link d-flex align-items-center text-white py-2 px-3 rounded <?= strpos($_SERVER['REQUEST_URI'], '/files/') !== false ? 'active' : '' ?>" 
                           href="<?= APP_URL ?>/files/index.php" style="transition: all 0.3s;">
                            <i class="fas fa-folder me-3"></i>
                            <span>Files</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link d-flex align-items-center text-white py-2 px-3 rounded <?= strpos($_SERVER['REQUEST_URI'], 'settings.php') !== false ? 'active' : '' ?>" 
                           href="<?= APP_URL ?>/settings/profile.php" style="transition: all 0.3s;">
                            <i class="fas fa-cog me-3"></i>
                            <span>Settings</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link d-flex align-items-center text-white py-2 px-3 rounded" 
                           href="<?= APP_URL ?>/logout.php" style="transition: all 0.3s;">
                            <i class="fas fa-sign-out-alt me-3"></i>
                            <span>Logout</span>
                        </a>
                    </li>
                </ul>

                <!-- Projects Section -->
                <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-2 text-light" style="font-size: 0.9rem; opacity: 0.7;">
                    <span>Projects</span>
                    <a class="d-flex align-items-center text-light" href="<?= APP_URL ?>/projects/create.php" aria-label="Add a new project" style="transition: all 0.3s;">
                        <i class="fas fa-plus"></i>
                    </a>
                </h6>

                <ul class="nav flex-column mb-2">
                    <?php
                    $projects = $pdo->prepare("
                        SELECT p.id, p.name 
                        FROM projects p
                        JOIN project_members pm ON p.id = pm.project_id
                        WHERE pm.user_id = :userId
                        ORDER BY p.name
                        LIMIT 5
                    ");
                    $projects->execute([':userId' => $_SESSION['user']['id']]);

                    while ($project = $projects->fetch(PDO::FETCH_ASSOC)) {
                        echo '<li class="nav-item">
                            <a class="nav-link d-flex align-items-center text-white py-2 px-3 rounded '.(strpos($_SERVER['REQUEST_URI'], 'projects/view.php?id='.$project['id']) !== false ? 'active' : '').'" 
                               href="'.APP_URL.'/projects/view.php?id='.$project['id'].'" style="transition: all 0.3s;">
                                <i class="fas fa-project-diagram me-3"></i>
                                <span>'.htmlspecialchars($project['name']).'</span>
                            </a>
                        </li>';
                    }
                    ?>
                    <li class="nav-item">
                        <a class="nav-link d-flex align-items-center text-white py-2 px-3 rounded <?= strpos($_SERVER['REQUEST_URI'], '/projects/projects.php') !== false ? 'active' : '' ?>" 
                           href="<?= APP_URL ?>/projects/projects.php" style="transition: all 0.3s;">
                            <i class="fas fa-ellipsis-h me-3"></i>
                            <span>View all projects</span>
                        </a>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <div class="container py-5">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">Two-Factor Authentication</h4>
                    </div>
                    <div class="card-body">
                        <?php if (isset($_SESSION['error'])): ?>
                            <div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
                        <?php endif; ?>
                        
                        <?php if ($is2FAEnabled): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle me-2"></i>
                                Two-factor authentication is currently <strong>enabled</strong> for your account.
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#disableModal">
                                    <i class="fas fa-lock-open me-2"></i>Disable 2FA
                                </button>
                                <a href="settings/profile.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left me-2"></i>Back to Settings
                                </a>
                            </div>
                            
                            <!-- Disable Confirmation Modal -->
                            <div class="modal fade" id="disableModal" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <form method="post">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Disable Two-Factor Authentication</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <p>Are you sure you want to disable two-factor authentication? This will make your account less secure.</p>
                                                <input type="hidden" name="action" value="disable">
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-danger">Disable 2FA</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="step">
                                <h5><span class="step-number">1</span> Install an Authenticator App</h5>
                                <p>Install one of these apps on your mobile device:</p>
                                <div class="d-flex justify-content-around mb-3">
                                    <a href="https://play.google.com/store/apps/details?id=com.google.android.apps.authenticator2" target="_blank" class="btn btn-outline-primary">
                                        <i class="fab fa-google me-2"></i>Google Authenticator
                                    </a>
                                    <a href="https://authy.com/download/" target="_blank" class="btn btn-outline-primary">
                                        <i class="fas fa-mobile-alt me-2"></i>Authy
                                    </a>
                                </div>
                            </div>
                            
                            <div class="step">
                                <h5><span class="step-number">2</span> Scan QR Code</h5>
                                <p>Open your authenticator app and scan this QR code:</p>
                                <div class="qr-code-container" id="qrCodeContainer">
                                    <!-- QR code will be inserted here via JavaScript -->
                                </div>
                                <p class="text-muted">Or enter this secret key manually: <code id="secretKey"></code></p>
                            </div>
                            
                            <div class="step">
                                <h5><span class="step-number">3</span> Backup Codes</h5>
                                <p>Save these backup codes in a safe place. You can use them to access your account if you lose your device.</p>
                                <div class="backup-codes" id="backupCodes">
                                    <!-- Backup codes will be inserted here via JavaScript -->
                                </div>
                                <div class="mt-2">
                                    <button class="btn btn-sm btn-outline-secondary" onclick="printBackupCodes()">
                                        <i class="fas fa-print me-1"></i>Print Codes
                                    </button>
                                    <button class="btn btn-sm btn-outline-secondary" onclick="downloadBackupCodes()">
                                        <i class="fas fa-download me-1"></i>Download
                                    </button>
                                </div>
                            </div>
                            
                            <form method="post">
                                <input type="hidden" name="action" value="enable">
                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-lock me-2"></i>Enable Two-Factor Authentication
                                    </button>
                                    <a href="settings/profile.php" class="btn btn-secondary">
                                        <i class="fas fa-arrow-left me-2"></i>Cancel
                                    </a>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Only run this if we're enabling 2FA
        <?php if (!$is2FAEnabled): ?>
        document.addEventListener('DOMContentLoaded', function() {
            // Generate 2FA data client-side to avoid exposing secrets prematurely
            fetch('generate_2fa_data.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    userId: <?php echo $user['id']; ?>,
                    email: '<?php echo $user['email']; ?>'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.qrCode) {
                    document.getElementById('qrCodeContainer').innerHTML = 
                        `<img src="${data.qrCode}" alt="QR Code" class="img-fluid">`;
                    document.getElementById('secretKey').textContent = data.secret;
                    
                    let backupCodesHTML = '';
                    data.backupCodes.forEach(code => {
                        backupCodesHTML += `<div>${code}</div>`;
                    });
                    document.getElementById('backupCodes').innerHTML = backupCodesHTML;
                }
            });
        });

        function printBackupCodes() {
            const printContent = `
                <h2><?php echo SITE_NAME; ?> - Backup Codes</h2>
                <p>Account: <?php echo $user['email']; ?></p>
                <p>Generated: ${new Date().toLocaleString()}</p>
                <hr>
                <h3>Your Backup Codes:</h3>
                ${document.getElementById('backupCodes').innerHTML}
                <hr>
                <p><strong>Important:</strong> Store these codes in a safe place. Each code can be used only once.</p>
            `;
            
            const win = window.open('', '', 'width=600,height=600');
            win.document.write(printContent);
            win.document.close();
            win.focus();
            setTimeout(() => win.print(), 500);
        }

        function downloadBackupCodes() {
            const codes = Array.from(document.querySelectorAll('#backupCodes div'))
                .map(div => div.textContent)
                .join('\n');
            
            const blob = new Blob([
                `Backup Codes for <?php echo SITE_NAME; ?>\n` +
                `Account: <?php echo $user['email']; ?>\n` +
                `Generated: ${new Date().toLocaleString()}\n\n` +
                codes +
                `\n\nImportant: Store these codes in a safe place. Each code can be used only once.`
            ], { type: 'text/plain' });
            
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'backup_codes.txt';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        }
        <?php endif; ?>
    </script>
</body>
</html>