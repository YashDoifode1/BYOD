<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'byod');
define('DB_USER', 'root');
define('DB_PASS', '');

// Other configuration
define('SITE_NAME', 'Project Dashboard');
define('APP_URL', 'http://localhost/v2'); // Change to your actual base URL

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