<?php
// Start session at the VERY TOP of the file
// session_start();
// In config.php
define('BASE_URL', 'http://localhost/v2/');

// In auth.php
function redirect($path) {
    header('Location: ' . BASE_URL . ltrim($path, '/'));
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Project Dashboard</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <style>
        body {
            padding-top: 0;
        }
        
        .dashboard {
            background-color: #f8f9fa;
        }
        
        .sidebar {
            position: fixed;
            top: 0;
            bottom: 0;
            left: 0;
            z-index: 100;
            padding: 48px 0 0;
            box-shadow: inset -1px 0 0 rgba(0, 0, 0, .1);
            background-color: #343a40;
            color: white;
        }
        
        .sidebar-sticky {
            position: relative;
            top: 0;
            height: calc(100vh - 48px);
            padding-top: .5rem;
            overflow-x: hidden;
            overflow-y: auto;
        }
        
        .sidebar .nav-link {
            font-weight: 500;
            color: rgba(255, 255, 255, .75);
            padding: .75rem 1rem;
        }
        
        .sidebar .nav-link.active {
            color: #fff;
            background-color: rgba(255, 255, 255, .1);
        }
        
        .sidebar .nav-link:hover {
            color: #fff;
            background-color: rgba(255, 255, 255, .1);
        }
        
        .sidebar-heading {
            font-size: .75rem;
            text-transform: uppercase;
            padding: 1rem 1rem .5rem;
            color: rgba(255, 255, 255, .5);
            font-weight: bold;
        }
        
        main {
            padding-top: 1.5rem;
        }
        
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }
        
        .card:hover {
            transform: translateY(-5px);
        }
        
        @media (max-width: 767.98px) {
            .sidebar {
                position: static;
                height: auto;
            }
            
            main {
                padding-top: 1rem;
            }
        }
    </style>
</head>