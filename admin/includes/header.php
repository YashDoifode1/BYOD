<?php
/**
 * Admin Panel Template
 * 
 * @category Admin
 * @package  AdminPanel
 * @author   Your Name <your.email@example.com>
 * @license  MIT License
 * @version  1.0.0
 * @link     https://example.com
 */

// Strict types for better type safety
declare(strict_types=1);

// Output buffering for clean output
ob_start();

// Security headers
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");

// Ensure session is started securely
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 86400,
        'path' => '/',
        'domain' => $_SERVER['HTTP_HOST'],
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
    session_start();
}

// Define application constants
defined('APP_URL') || define('APP_URL', 'https://' . $_SERVER['HTTP_HOST'] . '/v2/admin');
defined('APP_NAME') || define('APP_NAME', 'Admin Panel');
defined('APP_VERSION') || define('APP_VERSION', '1.0.0');

// Authentication check
function requireAuth(): void
{
    if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
        header("Location: " . APP_URL . "/login.php?redirect=" . urlencode($_SERVER['REQUEST_URI']));
        exit();
    }
    
    // Add additional security checks here (e.g., IP validation, session regeneration)
}

// Uncomment to enforce authentication
// requireAuth();

// CSRF protection
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Current page detection
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$pageTitle = ucwords(str_replace(['_', '-'], ' ', $currentPage));
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= APP_NAME ?> Administration Panel">
    <meta name="author" content="Your Company">
    
    <title><?= htmlspecialchars($pageTitle) ?> | <?= APP_NAME ?></title>
    
    <!-- Favicon -->
    <link rel="icon" href="<?= APP_URL ?>/assets/img/favicon.ico" type="image/x-icon">
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
    
    <!-- Custom Admin CSS -->
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/admin.min.css">
    
    <!-- Page-specific CSS -->
    <style>
    :root {
        --sidebar-width: 280px;
        --topbar-height: 60px;
        --primary-color: #4e73df;
        --secondary-color: #858796;
        --success-color: #1cc88a;
        --info-color: #36b9cc;
        --warning-color: #f6c23e;
        --danger-color: #e74a3b;
        --light-color: #f8f9fc;
        --dark-color: #5a5c69;
        --sidebar-bg: #2c3e50;
        --sidebar-active: rgba(255, 255, 255, 0.1);
        --transition-speed: 0.3s;
    }

    body {
        font-family: 'Nunito', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 
                     'Helvetica Neue', Arial, sans-serif;
        background-color: var(--light-color);
        color: var(--dark-color);
        min-height: 100vh;
    }

    /* Sidebar Styles */
    .sidebar {
        width: var(--sidebar-width);
        min-height: 100vh;
        position: fixed;
        top: 0;
        left: 0;
        background: var(--sidebar-bg);
        color: white;
        padding-top: var(--topbar-height);
        transition: all var(--transition-speed);
        z-index: 1000;
        box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
    }

    .sidebar .nav-link {
        color: rgba(255, 255, 255, 0.8);
        padding: 0.75rem 1.5rem;
        margin-bottom: 0.2rem;
        border-left: 3px solid transparent;
        transition: all var(--transition-speed);
        display: flex;
        align-items: center;
        font-weight: 400;
    }

    .sidebar .nav-link:hover {
        color: white;
        background: var(--sidebar-active);
    }

    .sidebar .nav-link.active {
        color: white;
        background: var(--sidebar-active);
        border-left: 3px solid var(--primary-color);
        font-weight: 600;
    }

    .sidebar .nav-link i {
        margin-right: 10px;
        width: 20px;
        text-align: center;
        font-size: 0.9rem;
    }

    /* Main Content */
    .main-content {
        margin-left: var(--sidebar-width);
        padding-top: var(--topbar-height);
        min-height: calc(100vh - var(--topbar-height));
    }

    /* Top Navigation */
    .topbar {
        height: var(--topbar-height);
        position: fixed;
        top: 0;
        right: 0;
        left: var(--sidebar-width);
        z-index: 1030;
        background: white;
        box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        transition: all var(--transition-speed);
    }

    /* Responsive adjustments */
    @media (max-width: 992px) {
        .sidebar {
            left: -var(--sidebar-width);
        }
        
        .sidebar.active {
            left: 0;
        }
        
        .main-content {
            margin-left: 0;
        }
        
        .topbar {
            left: 0;
        }
    }

    /* Breadcrumb */
    .breadcrumb {
        background-color: transparent;
        padding: 0.75rem 0;
        margin-bottom: 1.5rem;
    }

    /* Card enhancements */
    .card {
        border: none;
        box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1);
        margin-bottom: 1.5rem;
    }

    .card-header {
        background-color: #f8f9fc;
        border-bottom: 1px solid #e3e6f0;
    }
    </style>
</head>
<body>
    <!-- Top Navigation -->
    <nav class="topbar navbar navbar-expand navbar-light shadow-sm">
        <div class="container-fluid px-4">
            <!-- Sidebar Toggle -->
            <button class="btn btn-link d-md-none" type="button" id="sidebarToggle">
                <i class="fas fa-bars fa-lg"></i>
            </button>
            
            <!-- Breadcrumb -->
            <nav aria-label="breadcrumb" class="d-none d-md-block">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="<?= APP_URL ?>"><i class="fas fa-home"></i></a></li>
                    <li class="breadcrumb-item active" aria-current="page"><?= $pageTitle ?></li>
                </ol>
            </nav>
            
            <!-- User Menu -->
            <ul class="navbar-nav ms-auto">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="userDropdown" 
                       role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <div class="avatar-container me-2">
                            <?php if (isset($_SESSION['avatar']) && !empty($_SESSION['avatar'])): ?>
                                <img src="<?= htmlspecialchars($_SESSION['avatar']) ?>" 
                                     class="rounded-circle shadow-sm" width="32" height="32" 
                                     alt="User Avatar">
                            <?php else: ?>
                                <div class="avatar-placeholder bg-primary text-white rounded-circle 
                                            d-flex align-items-center justify-content-center" 
                                     style="width: 32px; height: 32px;">
                                    <i class="fas fa-user"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="d-flex flex-column">
                            <span class="fw-semibold"><?= htmlspecialchars($_SESSION['username'] ?? 'User') ?></span>
                            <?php if (isset($_SESSION['role'])): ?>
                                <small class="text-muted" style="font-size: 0.7em;">
                                    <span class="badge rounded-pill bg-<?= strtolower($_SESSION['role']) ?>">
                                        <?= ucfirst($_SESSION['role']) ?>
                                    </span>
                                </small>
                            <?php endif; ?>
                        </div>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm" aria-labelledby="userDropdown">
                        <li><h6 class="dropdown-header">User Menu</h6></li>
                        <li><a class="dropdown-item" href="<?= APP_URL ?>/profile.php">
                            <i class="fas fa-user me-2"></i>Profile</a></li>
                        <li><a class="dropdown-item" href="<?= APP_URL ?>/settings/account.php">
                            <i class="fas fa-cog me-2"></i>Account Settings</a></li>
                        <?php if (isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'manager'])): ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?= APP_URL ?>/admin/settings.php">
                                <i class="fas fa-sliders me-2"></i>System Settings</a></li>
                        <?php endif; ?>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="<?= APP_URL ?>/logout.php">
                            <i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </nav>
    
    <!-- Sidebar -->
    <div class="sidebar bg-gradient-primary">
        <div class="sidebar-brand d-flex align-items-center justify-content-center 
                     py-4 mb-2">
            <i class="fas fa-shield-alt fa-2x me-2"></i>
            <h1 class="h4 mb-0"><?= APP_NAME ?></h1>
        </div>
        
        <div class="sidebar-content">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'index' ? 'active' : '' ?>" 
                       href="<?= APP_URL ?>/admin/">
                        <i class="fas fa-fw fa-tachometer-alt"></i>
                        Dashboard
                    </a>
                </li>
                
                <?php if (isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'manager'])): ?>
                <li class="nav-item">
                    <a class="nav-link <?= str_contains($_SERVER['PHP_SELF'], '/users/') ? 'active' : '' ?>" 
                       href="<?= APP_URL ?>/admin/users/">
                        <i class="fas fa-fw fa-users-cog"></i>
                        User Management
                    </a>
                </li>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                <li class="nav-item">
                    <a class="nav-link <?= str_contains($_SERVER['PHP_SELF'], '/logs/') ? 'active' : '' ?>" 
                       href="<?= APP_URL ?>/admin/logs/">
                        <i class="fas fa-fw fa-clipboard-list"></i>
                        Activity Logs
                    </a>
                </li>
                
                <li class="nav-item">
                    <a class="nav-link <?= str_contains($_SERVER['PHP_SELF'], '/system/') ? 'active' : '' ?>" 
                       href="<?= APP_URL ?>/dashboard.php">
                        <i class="fas fa-fw fa-server"></i>
                         Web Application
                    </a>
                </li>
                <?php endif; ?>
                
                <li class="nav-item">
                    <a class="nav-link <?= str_contains($_SERVER['PHP_SELF'], '/projects/') ? 'active' : '' ?>" 
                       href="<?= APP_URL ?>/admin/projects/">
                        <i class="fas fa-fw fa-project-diagram"></i>
                        Projects
                    </a>
                </li>
                
                <li class="nav-item">
                    <a class="nav-link <?= str_contains($_SERVER['PHP_SELF'], '/tasks/') ? 'active' : '' ?>" 
                       href="<?= APP_URL ?>/admin/tasks/">
                        <i class="fas fa-fw fa-tasks"></i>
                        Tasks
                    </a>
                </li>
                
                <li class="nav-item">
                    <a class="nav-link <?= str_contains($_SERVER['PHP_SELF'], '/devices/') ? 'active' : '' ?>" 
                       href="<?= APP_URL ?>/admin/device/">
                        <i class="fas fa-fw fa-microchip"></i>
                        Device Management
                    </a>
                </li>
            </ul>
            
            <div class="sidebar-footer mt-auto px-3 py-2">
                <div class="text-center small text-white-50">
                    <div class="mb-1">Logged in as:</div>
                    <?php if (isset($_SESSION['role'])): ?>
                        <span class="badge rounded-pill bg-<?= strtolower($_SESSION['role']) ?>">
                            <?= ucfirst($_SESSION['role']) ?>
                        </span>
                    <?php endif; ?>
                    <div class="mt-2"><?= APP_NAME ?> v<?= APP_VERSION ?></div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Main Content -->
    <main class="main-content">
        <div class="container-fluid px-4">
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 mb-0 text-gray-800"><?= $pageTitle ?></h1>
                
                <?php if (in_array($currentPage, ['index', 'dashboard'])): ?>
                    <div class="d-none d-sm-inline-block">
                        <div class="input-group input-group-sm">
                            <input type="text" class="form-control bg-light border-0 small" 
                                   placeholder="Search..." aria-label="Search">
                            <button class="btn btn-primary" type="button">
                                <i class="fas fa-search fa-sm"></i>
                            </button>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Flash Messages -->
            <?php if (isset($_SESSION['flash_messages'])): ?>
                <?php foreach ($_SESSION['flash_messages'] as $message): ?>
                    <div class="alert alert-<?= $message['type'] ?> alert-dismissible fade show" role="alert">
                        <?= htmlspecialchars($message['text']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endforeach; ?>
                <?php unset($_SESSION['flash_messages']); ?>
            <?php endif; ?>
            
            <!-- Page Content -->
            <div class="row">
                <div class="col-12">
                    <?php ob_end_flush(); ?>