<?php
/**
 * CSRF Protection Module
 * Generates and validates CSRF tokens
 */

// session_start();

// function generateCsrfToken() {
//     if (empty($_SESSION['csrf_token'])) {
//         $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
//     }
//     return $_SESSION['csrf_token'];
// }

// function validateCsrfToken($token) {
//     if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
//         // Log the CSRF attempt
//         error_log("CSRF token validation failed. Session token: " . ($_SESSION['csrf_token'] ?? 'none') . 
//                  " Submitted token: " . ($token ?? 'none'));
//         return false;
//     }
//     return true;
// }

// // Regenerate token after each use for important actions
// function regenerateCsrfToken() {
//     unset($_SESSION['csrf_token']);
//     return generateCsrfToken();
// }
?>
<!--  -->
  <?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Generate CSRF token if not already set
if (!isset($_SESSION['csrf_token'])) {
    try {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    } catch (Exception $e) {
        error_log('CSRF token generation error: ' . $e->getMessage());
        // Fallback to less secure method if random_bytes fails
        $_SESSION['csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32));
    }
    $_SESSION['csrf_token_time'] = time();
}

// Verify CSRF token
function verify_csrf_token($token) {
    if (!isset($_SESSION['csrf_token']) || !isset($_SESSION['csrf_token_time'])) {
        return false;
    }

    // Check if token matches and is not older than 1 hour
    $max_lifetime = 3600; // 1 hour in seconds
    if (hash_equals($_SESSION['csrf_token'], $token) && (time() - $_SESSION['csrf_token_time']) <= $max_lifetime) {
        // Regenerate token after successful verification to prevent reuse
        try {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        } catch (Exception $e) {
            error_log('CSRF token regeneration error: ' . $e->getMessage());
            $_SESSION['csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32));
        }
        $_SESSION['csrf_token_time'] = time();
        return true;
    }

    return false;
}
?>