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
    header('Location: index.php');
    exit;
}

$project_id = (int)$_GET['project_id'];
$user = $_SESSION['user'];

// Verify user is a project member and project is not restricted
try {
    $stmt = $pdo->prepare("
        SELECT 1
        FROM project_members pm
        JOIN projects p ON pm.project_id = p.id
        WHERE pm.project_id = ? 
        AND pm.user_id = ?
        AND p.restriction_status != 'restricted'
    ");
    $stmt->execute([$project_id, $user['id']]);
    if (!$stmt->fetch()) {
        header('Location: index.php');
        exit;
    }

    // Get project details with restriction check
    $stmt = $pdo->prepare("
        SELECT name, restriction_status
        FROM projects
        WHERE id = ?
        AND restriction_status != 'restricted'
    ");
    $stmt->execute([$project_id]);
    $project = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$project) {
        header('Location: index.php');
        exit;
    }

    // Handle message submission if project is not restricted
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message']) && trim($_POST['message']) !== '') {
        if ($project['restriction_status'] === 'restricted') {
            $_SESSION['error'] = "Cannot send messages in a restricted project";
            header("Location: chat.php?project_id=$project_id");
            exit;
        }

        $message = trim($_POST['message']);
        $stmt = $pdo->prepare("
            INSERT INTO messages (project_id, sender_id, message, sent_at)
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$project_id, $user['id'], $message]);

        // Log activity
        $stmt = $pdo->prepare("
            INSERT INTO activity_logs (user_id, project_id, action, description, created_at)
            VALUES (?, ?, 'sent_message', ?, NOW())
        ");
        $stmt->execute([$user['id'], $project_id, "Sent a message in {$project['name']} chat"]);

        header("Location: chat.php?project_id=$project_id");
        exit;
    }

    // Get messages only if project is not restricted
    $stmt = $pdo->prepare("
        SELECT m.*, u.username, u.first_name, u.last_name
        FROM messages m
        JOIN users u ON m.sender_id = u.id
        JOIN projects p ON m.project_id = p.id
        WHERE m.project_id = ?
        AND p.restriction_status != 'restricted'
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
        .restricted-banner {
            background-color: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 5px solid #dc3545;
        }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-3 col-lg-2 d-md-block sidebar collapse" style="background: linear-gradient(180deg, #1a252f 0%, #2d3e50 100%); min-height: 100vh;">
               <?php require_once '../includes/sidebar.php'; ?>
                <!-- ... -->
            </div>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <?php if (!empty($project['name'])): ?>
    <h1 class="h2">Chat - <?php echo htmlspecialchars($project['name']); ?></h1>
<?php else: ?>
    <h1 class="h2">Chat - No Project Found</h1>
<?php endif; ?>

                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="index.php" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left me-2"></i>Back to Dashboard</a>
                    </div>
                </div>

                <?php if ($project['restriction_status'] === 'restricted'): ?>
                    <div class="restricted-banner">
                        <h4><i class="fas fa-lock me-2"></i>Project Restricted</h4>
                        <p class="mb-0">This project has been restricted. Chat functionality is disabled.</p>
                    </div>
                <?php endif; ?>

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

                <!-- Chat Input - Disabled if project is restricted -->
                <?php if ($project['restriction_status'] !== 'restricted'): ?>
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
                <?php else: ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Chat is disabled for this restricted project
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Scroll to the bottom of the chat container
        const chatContainer = document.getElementById('chat-container');
        if (chatContainer) {
            chatContainer.scrollTop = chatContainer.scrollHeight;
        }
        
        // Auto-resize textarea as user types
        const textarea = document.querySelector('textarea');
        if (textarea) {
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
        }
        
        <?php if ($project['restriction_status'] !== 'restricted'): ?>
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
            if (textarea) {
                textarea.focus();
            }
        });
        <?php endif; ?>
    </script>
</body>
</html>