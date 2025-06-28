<?php
// Helper functions for validation and CSRF protection

function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function validateDate($date, $format = 'Y-m-d') {
    if (!$date) return null;
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date ? $date : null;
}

function logError($message) {
    // Implement your error logging mechanism here
    error_log($message, 3, __DIR__ . '/../logs/error.log');
}