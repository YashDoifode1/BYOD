<?php
function isLoggedIn() {
    return isset($_SESSION['user']) && !empty($_SESSION['user']);
}

function requireAdmin() {
    if (!isLoggedIn() || $_SESSION['user']['role'] != 'admin') {
        header('HTTP/1.0 403 Forbidden');
        die('Access denied. Admin privileges required.');
    }
}

function requireManager() {
    if (!isLoggedIn() || ($_SESSION['user']['role'] != 'manager' && $_SESSION['user']['role'] != 'admin')) {
        header('HTTP/1.0 403 Forbidden');
        die('Access denied. Manager privileges required.');
    }
}

?>