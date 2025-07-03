<?php
/**
 * Simple authentication check for admin pages
 * 
 * Usage:
 *   require_once __DIR__ . '/../includes/auth_required.php';
 *   
 * Or with specific role:
 *   $required_role = 'admin';
 *   require_once __DIR__ . '/../includes/auth_required.php';
 */

// Ensure autoloader is loaded
require_once __DIR__ . '/../vendor/autoload.php';

use YFEvents\Application\Services\AuthService;
use YFEvents\Infrastructure\Utils\EnvLoader;

// Load environment
EnvLoader::load(__DIR__ . '/../');

// Load database config
$configData = require __DIR__ . '/../config/database.php';
$dbConfig = $configData['database'];

// Create PDO connection
$dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['name']};charset={$dbConfig['charset']}";
$pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $dbConfig['options']);

// Initialize auth service
$authService = new AuthService($pdo);

// Check if a specific role is required (can be set before including this file)
if (!isset($required_role)) {
    $required_role = null; // Just require authentication, any role
}

// Check authentication
if (!$authService->isAuthenticated()) {
    header('Location: /admin/login');
    exit;
}

// Check role if specified
if ($required_role !== null) {
    if (!$authService->hasAnyRole((array)$required_role)) {
        http_response_code(403);
        echo <<<HTML
<!DOCTYPE html>
<html>
<head>
    <title>Access Denied</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f5f5;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
        }
        .error-container {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
            max-width: 400px;
        }
        h1 {
            color: #dc3545;
            margin-bottom: 20px;
        }
        p {
            color: #666;
            margin-bottom: 30px;
        }
        a {
            display: inline-block;
            padding: 10px 20px;
            background: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 5px;
        }
        a:hover {
            background: #0056b3;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <h1>Access Denied</h1>
        <p>You do not have permission to access this page.</p>
        <p>Required role: <strong>$required_role</strong></p>
        <a href="/admin/dashboard">Back to Dashboard</a>
    </div>
</body>
</html>
HTML;
        exit;
    }
}

// Make auth service and current user available to the including page
$currentUser = $authService->getCurrentUser();