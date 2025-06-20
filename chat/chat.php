<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/auth.php';

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

// Verify database connection
if (!isset($pdo)) {
    die("Database connection not established. Please check your config.php file.");
}

// Check if project_id is provided
if (!isset($_GET['project_id']) || !is_numeric($_GET['project_id'])) {
    header('Location: dashboard.php');
    exit;
}

$project_id = (int)$_GET['project_id'];
$user = $_SESSION['user'];

// Verify user is a project member
try {
    $stmt = $pdo->prepare("
        SELECT 1
        FROM project_members pm
        WHERE pm.project_id = ? AND pm.user_id = ?
    ");
    $stmt->execute([$project_id, $user['id']]);
    if (!$stmt->fetch()) {
        header('Location: dashboard.php');
        exit;
    }

    // Get project details
    $stmt = $pdo->prepare("
        SELECT name
        FROM projects
        WHERE id = ?
    ");
    $stmt->execute([$project_id]);
    $project = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$project) {
        header('Location: dashboard.php');
        exit;
    }

    // Handle message submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message']) && trim($_POST['message']) !== '') {
        $message = trim($_POST['message']);
        $stmt = $pdo->prepare("
    INSERT INTO messages (project_id, sender_id, message, sent_at)
    VALUES (?, ?, ?, NOW())
");
$stmt->execute([$project_id, $user['id'], $message]);
        // $stmt->execute([$project_id, $user['id'], $message]);

        // Log activity
        $stmt = $pdo->prepare("
            INSERT INTO activity_logs (user_id, project_id, action, description, created_at)
            VALUES (?, ?, 'sent_message', ?, NOW())
        ");
        $stmt->execute([$user['id'], $project_id, "Sent a message in {$project['name']} chat"]);

        header("Location: chat.php?project_id=$project_id");
        exit;
    }

 $stmt = $pdo->prepare("
    SELECT m.*, u.username, u.first_name, u.last_name
    FROM messages m
    JOIN users u ON m.sender_id = u.id
    WHERE m.project_id = ?
    ORDER BY m.sent_at ASC
");
$stmt->execute([$project_id]);
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Project Chat - <?php echo htmlspecialchars($project['name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <style>
        .chat-container {
            height: 60vh;
            overflow-y: auto;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            background: #f8f9fa;
            margin-bottom: 20px;
        }
        .message {
            margin-bottom: 15px;
            padding: 10px;
            border-radius: 8px;
            max-width: 70%;
        }
        .message.sent {
            background: #007bff;
            color: white;
            margin-left: auto;
        }
        .message.received {
            background: #e9ecef;
            color: black;
            margin-right: auto;
        }
        .message .username {
            font-weight: bold;
            font-size: 0.9rem;
        }
        .message .timestamp {
            font-size: 0.8rem;
            opacity: 0.7;
        }
        .chat-input {
            position: sticky;
            bottom: 0;
            background: white;
            padding: 10px;
            border-top: 1px solid #dee2e6;
        }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
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
                               href="<?= APP_URL ?>/chat/chat.php" style="transition: all 0.3s;">
                                <i class="fas fa-comments me-3"></i>
                                <span>Team Chat</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link d-flex align-items-center text-white py-2 px-3 rounded <?= strpos($_SERVER['REQUEST_URI'], '/files/') !== false ? 'active' : '' ?>" 
                               href="<?= APP_URL ?>/files/files.php" style="transition: all 0.3s;">
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
                        $sidebar_projects = $pdo->prepare("
                            SELECT p.id, p.name 
                            FROM projects p
                            JOIN project_members pm ON p.id = pm.project_id
                            WHERE pm.user_id = :userId
                            ORDER BY p.name
                            LIMIT 5
                        ");
                        $sidebar_projects->execute([':userId' => $_SESSION['user']['id']]);

                        while ($proj = $sidebar_projects->fetch(PDO::FETCH_ASSOC)) {
                            echo '<li class="nav-item">
                                <a class="nav-link d-flex align-items-center text-white py-2 px-3 rounded '.(strpos($_SERVER['REQUEST_URI'], 'projects/view.php?id='.$proj['id']) !== false ? 'active' : '').'" 
                                   href="'.APP_URL.'/projects/view.php?id='.$proj['id'].'" style="transition: all 0.3s;">
                                    <i class="fas fa-project-diagram me-3"></i>
                                    <span>'.htmlspecialchars($proj['name']).'</span>
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
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Chat - <?php echo htmlspecialchars($project['name']); ?></h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="index.php" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left me-2"></i>Back to Dashboard</a>
                    </div>
                </div>

                 <!-- Chat Container -->
                <div class="chat-container" id="chat-container">
                    <?php if (empty($messages)): ?>
                        <div class="text-center py-5 text-muted">
                            <i class="fas fa-comments fa-3x mb-3"></i>
                            <h4>No messages yet</h4>
                            <p>Start the conversation by sending a message</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($messages as $message): ?>
                            <div class="message <?php echo $message['sender_id'] == $user['id'] ? 'sent' : 'received'; ?>">
                                <div class="username">
                                    <?php if ($message['sender_id'] != $user['id']): ?>
                                        <img src="https://ui-avatars.com/api/?name=<?= urlencode($message['first_name'].' '.$message['last_name']) ?>&background=random&size=80&rounded=true" 
                                             alt="<?= htmlspecialchars($message['username']) ?>" 
                                             style="width: 24px; height: 24px; border-radius: 50%; margin-right: 8px; object-fit: cover;">
                                    <?php endif; ?>
                                    <?php echo htmlspecialchars($message['first_name'] . ' ' . $message['last_name']); ?>

                                </div>
                                <p><?php echo htmlspecialchars($message['message']); ?></p>
                                <div class="timestamp">
                                    <i class="far fa-clock me-1"></i>
                                    <?php echo date('M j, g:i a', strtotime($message['sent_at'])); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Chat Input -->
                <form method="POST" class="chat-input">
                    <div class="input-group">
                        <textarea name="message" class="form-control" rows="1" placeholder="Type your message..." 
                                  style="flex-grow: 1;" required></textarea>
                        <button type="submit" class="btn btn-primary ms-2">
                            <i class="fas fa-paper-plane me-1"></i> Send
                        </button>
                    </div>
                    <div class="d-flex justify-content-between mt-2">
                        <small class="text-muted">Press Shift+Enter for a new line</small>
                        <small class="text-muted"><?php echo date('D, M j, g:i a'); ?></small>
                    </div>
                </form>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Scroll to the bottom of the chat container
        const chatContainer = document.getElementById('chat-container');
        chatContainer.scrollTop = chatContainer.scrollHeight;
        
        // Auto-resize textarea as user types
        const textarea = document.querySelector('textarea');
        textarea.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = (this.scrollHeight) + 'px';
        });
        
        // Allow Shift+Enter for new lines, Enter to submit
        textarea.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                this.form.dispatchEvent(new Event('submit', {cancelable: true}));
            }
        });
        
        // Polling for new messages with improved efficiency
        let lastMessageId = <?php echo !empty($messages) ? end($messages)['id'] : 0; ?>;
        
        function fetchMessages() {
            fetch(`fetch_messages.php?project_id=<?php echo $project_id; ?>&last_id=${lastMessageId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.messages && data.messages.length > 0) {
                        data.messages.forEach(message => {
                            if (message.id > lastMessageId) {
                                lastMessageId = message.id;
                                const messageClass = message.sender_id == <?php echo $user['id']; ?> ? 'sent' : 'received';
                                const messageHtml = `
                                    <div class="message ${messageClass}">
                                        <div class="username">
                                            ${message.sender_id != <?php echo $user['id']; ?> ? 
                                                `<img src="https://ui-avatars.com/api/?name=${encodeURIComponent(message.first_name + ' ' + message.last_name)}&background=random&size=80&rounded=true" 
                                                     alt="${message.username}" style="width: 24px; height: 24px; border-radius: 50%; margin-right: 8px; object-fit: cover;">` : ''}
                                            ${message.first_name} ${message.last_name}
                                        </div>
                                        <p>${message.message}</p>
                                        <div class="timestamp">
                                            <i class="far fa-clock me-1"></i>
                                            ${new Date(message.sent_at).toLocaleString('en-US', { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' })}
                                        </div>
                                    </div>
                                `;
                                chatContainer.innerHTML += messageHtml;
                            }
                        });
                        chatContainer.scrollTop = chatContainer.scrollHeight;
                    }
                })
                .catch(error => console.error('Error fetching messages:', error));
        }
        
        // Check for new messages every 3 seconds
        setInterval(fetchMessages, 3000);
        
        // Focus the textarea when page loads
        window.addEventListener('load', () => {
            textarea.focus();
        });
    </script>