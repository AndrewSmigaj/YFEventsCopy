<?php
/**
 * Application Root Locator
 * 
 * This file helps locate the application root from any directory depth.
 * Include this file from anywhere in the project to bootstrap the application.
 * 
 * Usage:
 *   require_once __DIR__ . '/../../config/app-root.php';
 * 
 * After including, these constants will be available:
 *   YF_ROOT    - Application root directory
 *   YF_CONFIG  - Configuration directory
 *   YF_VENDOR  - Vendor directory
 *   YF_PUBLIC  - Public directory
 *   YF_MODULES - Modules directory
 *   YF_SRC     - Source directory
 */

// Prevent multiple inclusions
if (defined('YF_ROOT')) {
    return;
}

// Find application root by looking for vendor/autoload.php
$depth = 0;
$root = __DIR__;
while (!file_exists($root . '/vendor/autoload.php') && $depth < 10) {
    $root = dirname($root);
    $depth++;
}

if ($depth >= 10) {
    die('Error: Could not find application root. Make sure vendor/autoload.php exists.');
}

// Define path constants
define('YF_ROOT', $root);
define('YF_CONFIG', YF_ROOT . '/config');
define('YF_VENDOR', YF_ROOT . '/vendor');
define('YF_PUBLIC', YF_ROOT . '/public');
define('YF_MODULES', YF_ROOT . '/modules');
define('YF_SRC', YF_ROOT . '/src');
define('YF_STORAGE', YF_ROOT . '/storage');
define('YF_DATABASE', YF_ROOT . '/database');

// Load composer autoloader
require_once YF_VENDOR . '/autoload.php';

// Load bootstrap if it exists and hasn't been loaded
// Note: Bootstrap loading is optional - only load if explicitly requested
if (!defined('YF_SKIP_BOOTSTRAP') && file_exists(YF_CONFIG . '/bootstrap.php') && !defined('YF_BOOTSTRAPPED')) {
    require_once YF_CONFIG . '/bootstrap.php';
}