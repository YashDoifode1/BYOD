<?php
session_start();

// Database connection
$host = 'localhost';
$db = 'byod';
$user = 'root';
$pass = '';

$conn = new mysqli($host, $user, $pass, $db);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle password reset request
$errors = [];
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);

    // Validate input
    if (empty($email)) {
        $errors[] = "Email is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    } else {
        // Check if email exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            // Generate unique token
            $token = bin2hex(random_bytes(32));
            $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour')); // Token expires in 1 hour

            // Store token in password_resets table
            $insert_stmt = $conn->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
            $insert_stmt->bind_param("sss", $email, $token, $expires_at);
            if ($insert_stmt->execute()) {
                // Send reset email (simplified; use PHPMailer in production)
                $reset_link = "http://yourdomain.com/auth/reset-password.php?token=$token";
                $subject = "Password Reset Request";
                $message = "Click the following link to reset your password: $reset_link\nThis link expires in 1 hour.";
                $headers = "From: no-reply@yourdomain.com";

                if (mail($email, $subject, $message, $headers)) {
                    $success = "A password reset link has been sent to your email.";
                } else {
                    $errors[] = "Failed to send reset email. Please try again.";
                }
            } else {
                $errors[] = "Failed to process request. Please try again.";
            }
            $insert_stmt->close();
        } else {
            $errors[] = "No account found with this email.";
        }
        $stmt->close();
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Cybersecurity Consultancy</title>
    <link rel="stylesheet" href="auth.css">
</head>
<body>
    <div class="login-container">
        <h2>Forgot Password</h2>
        <p>Enter your email address to receive a password reset link.</p>
        <?php if (!empty($errors)): ?>
            <div class="error">
                <?php foreach ($errors as $error): ?>
                    <p><?php echo htmlspecialchars($error); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="success">
                <p><?php echo htmlspecialchars($success); ?></p>
            </div>
        <?php endif; ?>
        <form action="forgot-password.php" method="POST">
            <div class="form-group">
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
            </div>
            <button type="submit">Send Reset Link</button>
            <p class="forgot-password">
                Remember your password? <a href="login.php">Login here</a>
            </p>
        </form>
    </div>
</body>
</html>