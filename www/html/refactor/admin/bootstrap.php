<?php
/**
 * Admin Bootstrap File
 * Initializes the application environment for admin pages
 */

declare(strict_types=1);

// Enable error reporting for development
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load autoloader
require_once dirname(__DIR__) . '/vendor/autoload.php';

// Bootstrap the application
use YFEvents\Application\Bootstrap;

try {
    // Initialize the application container
    $container = Bootstrap::boot();
    
    // Get database connection from container
    $db = $container->resolve(\YFEvents\Infrastructure\Database\ConnectionInterface::class);
    
    // Make database connection available globally for legacy admin pages
    $GLOBALS['db'] = $db->getConnection();
    
} catch (\Exception $e) {
    // Handle bootstrap errors
    error_log('Admin bootstrap error: ' . $e->getMessage());
    die('Application initialization failed. Please check the error logs.');
}

// Include auth check
require_once __DIR__ . '/auth_check.php';