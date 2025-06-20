<?php ob_start(); ?>
<?php 
// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Define constants if not already defined
defined('APP_URL') or define('APP_URL', 'http://localhost/v2/admin');

// Basic security check - uncomment if you want to enforce login
/*
if (!isset($_SESSION['user_id'])) {
    header("Location: " . APP_URL . "/login.php");
    exit();
}
*/
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - <?= htmlspecialchars(basename($_SERVER['PHP_SELF'], '.php')) ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <style>
    :root {
        --sidebar-width: 280px;
        --topbar-height: 56px;
        --primary-color: #4e73df;
        --sidebar-bg: #343a40;
        --sidebar-active: rgba(255, 255, 255, 0.1);
    }

    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background-color: #f8f9fa;
    }

    /* Sidebar Styles - Enhanced but compatible */
    .sidebar {
        width: var(--sidebar-width);
        height: 100vh;
        position: fixed;
        top: 0;
        left: 0;
        background: var(--sidebar-bg);
        color: white;
        padding-top: var(--topbar-height);
        transition: all 0.3s;
        z-index: 1000;
    }

    .sidebar .nav-link {
        color: rgba(255, 255, 255, 0.8);
        padding: 0.75rem 1.5rem;
        margin-bottom: 0.2rem;
        border-left: 3px solid transparent;
        transition: all 0.2s;
        display: flex;
        align-items: center;
    }

    .sidebar .nav-link:hover {
        color: white;
        background: var(--sidebar-active);
    }

    .sidebar .nav-link.active {
        color: white;
        background: var(--sidebar-active);
        border-left: 3px solid var(--primary-color);
        font-weight: 500;
    }

    .sidebar .nav-link i {
        margin-right: 10px;
        width: 20px;
        text-align: center;
        font-size: 0.9rem;
    }

    /* Main Content - Compatible with existing */
    .main-content {
        margin-left: var(--sidebar-width);
        padding-top: var(--topbar-height);
    }

    /* Top Navigation - Enhanced but compatible */
    .navbar {
        height: var(--topbar-height);
        position: fixed;
        top: 0;
        right: 0;
        left: var(--sidebar-width);
        z-index: 1030;
        background: white;
        box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    }

    /* User dropdown improvements */
    .dropdown-menu {
        border: none;
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.1);
    }

    /* Badge styles - Added for roles */
    .badge-role {
        font-size: 0.7em;
        font-weight: 500;
        padding: 0.35em 0.65em;
    }
    .badge-admin { background-color: #dc3545; }
    .badge-manager { background-color: #ffc107; color: #212529; }
    .badge-user { background-color: #28a745; }

    /* Responsive adjustments - Preserved your existing */
    @media (max-width: 768px) {
        .sidebar {
            left: -var(--sidebar-width);
        }
        
        .sidebar.active {
            left: 0;
        }
        
        .main-content {
            margin-left: 0;
        }
        
        .navbar {
            left: 0;
        }
    }
    </style>
</head>
<body>
    <!-- Top Navigation - Enhanced but compatible -->
    <nav class="navbar navbar-expand navbar-light">
        <div class="container-fluid">
            <button class="btn btn-link d-md-none" type="button" id="sidebarToggle">
                <i class="fas fa-bars"></i>
            </button>
            
            <div class="navbar-collapse">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <?php if (isset($_SESSION['avatar']) && !empty($_SESSION['avatar'])): ?>
                                <img src="<?= htmlspecialchars($_SESSION['avatar']) ?>" class="rounded-circle me-2" width="32" height="32">
                            <?php else: ?>
                                <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 32px; height: 32px;">
                                    <i class="fas fa-user"></i>
                                </div>
                            <?php endif; ?>
                            <div class="d-flex flex-column">
                                <span><?= htmlspecialchars($_SESSION['username'] ?? 'User') ?></span>
                                <?php if (isset($_SESSION['role'])): ?>
                                    <small class="text-muted" style="font-size: 0.7em;">
                                        <span class="badge badge-role badge-<?= strtolower($_SESSION['role']) ?>">
                                            <?= ucfirst($_SESSION['role']) ?>
                                        </span>
                                    </small>
                                <?php endif; ?>
                            </div>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="<?= APP_URL ?>/profile.php"><i class="fas fa-user me-2"></i>Profile</a></li>
                            <?php if (isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'manager'])): ?>
                                <li><a class="dropdown-item" href="<?= APP_URL ?>/settings.php"><i class="fas fa-cog me-2"></i>Settings</a></li>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?= APP_URL ?>/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar - Enhanced but compatible -->
            <div class="sidebar col-md-3 col-lg-2 d-md-block">
                <div class="position-sticky pt-3">
                    <ul class="nav flex-column">
                        <div class="position-absolute bottom-0 start-0 p-3 w-100 text-center text-muted" style="font-size: 0.8em;">
                        Logged in as: 
                        <?php if (isset($_SESSION['role'])): ?>
                            <span class="badge badge-role badge-<?= strtolower($_SESSION['role']) ?>">
                                <?= ucfirst($_SESSION['role']) ?>
                            </span>
                        <?php endif; ?>
                    </div>
                        <li class="nav-item">
                            
                            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'index.php' ? 'active' : '' ?>" href="<?= APP_URL ?>/admin/index.php">
                                <i class="fas fa-tachometer-alt"></i>
                                Dashboard
                            </a>
                        </li>
                        
                        <?php if (isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'manager'])): ?>
                        <li class="nav-item">
                            <a class="nav-link <?= strpos($_SERVER['PHP_SELF'], '/users/') !== false ? 'active' : '' ?>" href="<?= APP_URL ?>/admin/users/index.php">
                                <i class="fas fa-users"></i>
                                User Management
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                        <li class="nav-item">
                            <a class="nav-link <?= strpos($_SERVER['PHP_SELF'], '/logs/') !== false ? 'active' : '' ?>" href="<?= APP_URL ?>/admin/logs/index.php">
                                <i class="fas fa-clipboard-list"></i>
                                Activity Logs
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <li class="nav-item">
                            <a class="nav-link <?= strpos($_SERVER['PHP_SELF'], '/projects/') !== false ? 'active' : '' ?>" href="<?= APP_URL ?>/admin/projects/index.php">
                                <i class="fas fa-project-diagram"></i>
                                Projects 
                            </a>
                        </li>
                        
                        <li class="nav-item">
                            <a class="nav-link <?= strpos($_SERVER['PHP_SELF'], '/tasks/') !== false ? 'active' : '' ?>" href="<?= APP_URL ?>/admin/tasks/index.php">
                                <i class="fas fa-tasks"></i>
                                Tasks 
                            </a>
                        </li>
                       
                        <li class="nav-item">
                            <a class="nav-link <?= strpos($_SERVER['PHP_SELF'], '/settings/') !== false ? 'active' : '' ?>" href="<?= APP_URL ?>/admin/settings/index.php">
                                <i class="fas fa-cog"></i>
                                Settings
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link " href="<?= APP_URL ?>/logout.php">
                                <i class="fas fa-cog"></i>
                                logout
                            </a>
                        </li>

                    </ul>
                    <br>
                    <!-- Sidebar footer with user role -->
                    <div class="position-absolute bottom-0 start-0 p-3 w-100 text-center text-muted" style="font-size: 0.8em;">
                        Logged in as: 
                        <?php if (isset($_SESSION['role'])): ?>
                            <span class="badge badge-role badge-<?= strtolower($_SESSION['role']) ?>">
                                <?= ucfirst($_SESSION['role']) ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Main Content - Unchanged structure -->
            <main class="main-content col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <?php ob_end_flush(); ?>