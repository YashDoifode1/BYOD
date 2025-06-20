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

// Handle registration
$errors = [];
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = filter_var($_POST['first_name'], FILTER_SANITIZE_STRING);
    $last_name = filter_var($_POST['last_name'], FILTER_SANITIZE_STRING);
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Server-side validation
    if (empty($first_name) || empty($last_name) || empty($email) || empty($password) || empty($confirm_password)) {
        $errors[] = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    } elseif (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters long.";
    } elseif ($password !== $confirm_password) {
        $errors[] = "Passwords do not match.";
    } else {
        // Check if email or username already exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
        $username = strtolower(trim($first_name . '.' . $last_name)); // Generate username
        $stmt->bind_param("ss", $email, $username);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $errors[] = "Email or username already exists.";
        } else {
            // Hash password and insert user
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $insert_stmt = $conn->prepare("INSERT INTO users (username, email, password_hash, first_name, last_name, role) VALUES (?, ?, ?, ?, ?, 'user')");
            $insert_stmt->bind_param("sssss", $username, $email, $password_hash, $first_name, $last_name);
            if ($insert_stmt->execute()) {
                $success = "Registration successful! Please <a href='login.php'>login</a>.";
            } else {
                $errors[] = "Registration failed. Please try again.";
            }
            $insert_stmt->close();
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
    <title>Register - Cybersecurity Consultancy</title>
    <link rel="stylesheet" href="auth.css">
</head>
<body>
    <div class="login-container">
        <h2>Register</h2>
        <?php if (!empty($errors)): ?>
            <div class="error">
                <?php foreach ($errors as $error): ?>
                    <p><?php echo htmlspecialchars($error); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="success">
                <p><?php echo $success; ?></p>
            </div>
        <?php endif; ?>
        <form action="register.php" method="POST">
            <div class="form-group">
                <label for="first_name">First Name:</label>
                <input type="text" id="first_name" name="first_name" value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>" required>
            </div>
            <div class="form-group">
                <label for="last_name">Last Name:</label>
                <input type="text" id="last_name" name="last_name" value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>" required>
            </div>
            <div class="form-group">
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
            </div>
            <div class="form-group">
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required>
            </div>
            <div class="form-group">
                <label for="confirm_password">Confirm Password:</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>
            <button type="submit">Register</button>
            <p class="forgot-password">
                Already have an account? <a href="login.php">Login here</a>
            </p>
        </form>
    </div>
</body>
</html>