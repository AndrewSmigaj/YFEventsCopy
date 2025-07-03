<?php
/**
 * Database Connection Compatibility Layer
 * 
 * This file provides backward compatibility for legacy files that expect
 * a $pdo variable to exist after including the database configuration.
 * 
 * Usage:
 *   require_once dirname(__DIR__, 2) . '/config/db_connection.php';
 *   // Now $pdo is available for use
 */

// First, we need to find and load the autoloader
$root = dirname(__DIR__);
$autoloadPath = $root . '/vendor/autoload.php';

if (!file_exists($autoloadPath)) {
    die('Error: Vendor autoloader not found. Please run composer install.');
}

require_once $autoloadPath;

// Now load the database configuration
$configPath = __DIR__ . '/database.php';
if (!file_exists($configPath)) {
    die('Error: Database configuration not found.');
}

$config = require $configPath;

// Create the PDO connection
try {
    if (!isset($config['database'])) {
        throw new Exception('Database configuration is missing database key');
    }
    
    $dbConfig = $config['database'];
    
    // Build DSN
    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=%s',
        $dbConfig['host'] ?? 'localhost',
        $dbConfig['name'] ?? 'yakima_finds',
        $dbConfig['charset'] ?? 'utf8mb4'
    );
    
    // Create PDO instance
    $pdo = new PDO(
        $dsn,
        $dbConfig['username'] ?? 'yfevents',
        $dbConfig['password'] ?? 'yfevents_pass',
        $dbConfig['options'] ?? [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
    
    // Also make the database config available
    $db_config = $config;
    
} catch (Exception $e) {
    // In development, show the error
    if (isset($_ENV['APP_ENV']) && $_ENV['APP_ENV'] !== 'production') {
        die('Database connection failed: ' . $e->getMessage());
    } else {
        // In production, log it and show generic error
        error_log('Database connection failed: ' . $e->getMessage());
        die('Database connection error. Please try again later.');
    }
}