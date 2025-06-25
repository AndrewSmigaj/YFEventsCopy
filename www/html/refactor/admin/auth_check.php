<?php
require_once __DIR__ . '/../vendor/autoload.php';
use YFEvents\Helpers\PathHelper;

/**
 * Simple admin authentication check
 * For production, integrate with proper authentication system
 */

// Only start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    // Redirect to login page with return URL
    header('Location: ' . PathHelper::url('login.php')?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

// For backward compatibility, set admin_logged_in flag
$_SESSION['admin_logged_in'] = true;
$_SESSION['admin_username'] = $_SESSION['user_email'] ?? $_SESSION['user_name'] ?? 'Admin';
?>