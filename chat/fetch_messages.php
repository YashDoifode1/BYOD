<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/auth.php';

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Verify database connection
if (!isset($pdo)) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

// Check if project_id is provided
if (!isset($_GET['project_id']) || !is_numeric($_GET['project_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid project ID']);
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
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        exit;
    }

    // Fetch messages
    $stmt = $pdo->prepare("
        SELECT m.id, m.project_id, m.sender_id, m.message, m.sent_at, u.username, u.first_name, u.last_name
        FROM messages m
        JOIN users u ON m.sender_id = u.id
        WHERE m.project_id = ?
        ORDER BY m.sent_at ASC
    ");
    $stmt->execute([$project_id]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Return messages as JSON
    header('Content-Type: application/json');
    echo json_encode(['messages' => $messages]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    exit;
}
?>