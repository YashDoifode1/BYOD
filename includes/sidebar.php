<?php ob_start();?>
<?php require_once  'validate.php'; ?>
<div class="col-md-3 col-lg-2 d-md-block sidebar collapse" style="background: linear-gradient(180deg, #1a252f 0%, #2d3e50 100%); min-height: 100vh;">
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
                <a class="nav-link d-flex align-items-center text-white py-2 px-3 rounded <?= strpos($_SERVER['REQUEST_URI'], '/device/') !== false ? 'active' : '' ?>" 
                   href="<?= APP_URL ?>/device/index.php" style="transition: all 0.3s;">
                    <i class="fa-solid fa-computer-classic"></i>
                    <span>Device Management</span>
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

    <!-- Inline CSS for Sidebar Styling -->
    <style>
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

        @media (max-width: 767.98px) {
            .sidebar {
                position: fixed;
                top: 0;
                left: 0;
                z-index: 1000;
                width: 250px;
                height: 100%;
                transform: translateX(-100%);
                transition: transform 0.3s ease-in-out;
            }

            .sidebar.show {
                transform: translateX(0);
            }
        }
    </style>
</div>
<?php ob_end_flush();?>