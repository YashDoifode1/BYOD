<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'byod');
define('DB_USER', 'root');
define('DB_PASS', '');

// Other configuration
define('SITE_NAME', 'Project Dashboard');
define('APP_URL', 'http://localhost/v2'); // Change to your actual base URL
define('SITE_URL', 'http://localhost/v2');
// PHPMailer configuration
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'yashdoifode1439@gmail.com' );
define('SMTP_PASSWORD', 'mvub juzg shso fhpa');
define('SMTP_FROM', 'noreply@example.com');
define('SMTP_FROM_NAME', 'Secure shell');
// Feature flags
define('ALLOW_SOCIAL_LOGIN', false);  // Set to true if you want social login buttons
define('ALLOW_REGISTRATION', false);  // Set to true if you want registration link

// Security
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_TIMEOUT', 300); // 5 minutes in seconds

// Add to config.php
define('GOOGLE_CLIENT_ID', '');
define('GOOGLE_CLIENT_SECRET', '');
define('FACEBOOK_APP_ID', '');
define('FACEBOOK_APP_SECRET', '');
// Email settings
define('EMAIL_FROM', 'noreply@yourdomain.com');
define('EMAIL_REPLY_TO', 'support@yourdomain.com');
?>

<?php
// config.php
$host = 'localhost';
$dbname = 'byod';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}