<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: /login.php");
    exit();
}

// Database connection
require_once __DIR__ . '/../../includes/config.php';

// Check if user exists and is active
$stmt = $pdo->prepare("SELECT id, username, role FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user) {
    session_destroy();
    header("Location: /login.php");
    exit();
}

// Update session with current user data
$_SESSION['user_id'] = $user['id'];
$_SESSION['username'] = $user['username'];
$_SESSION['user_role'] = $user['role'];