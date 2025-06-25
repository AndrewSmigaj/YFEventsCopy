<?php
/**
 * Application Configuration
 * This file centralizes all path and URL configurations
 */

// Load environment variables
require_once dirname(__DIR__) . '/src/Utils/EnvLoader.php';
use YFEvents\Utils\EnvLoader;
EnvLoader::load(dirname(__DIR__));

// Determine if we're in a subdirectory (like /refactor) or root
$scriptPath = dirname($_SERVER['SCRIPT_NAME'] ?? '');
$isSubdirectory = $scriptPath !== '/' && $scriptPath !== '' && $scriptPath !== '.';

// Dynamic base path detection
$basePath = $isSubdirectory ? $scriptPath : '';

// Remove trailing slash
$basePath = rtrim($basePath, '/');

return [
    // Base path for URLs (empty for root, '/refactor' for subdirectory)
    'base_path' => $basePath,
    
    // Full base URL
    'base_url' => 'https://' . ($_SERVER['HTTP_HOST'] ?? 'backoffice.yakimafinds.com') . $basePath,
    
    // Admin path (relative to base)
    'admin_path' => '/admin',
    
    // API path (relative to base)
    'api_path' => '/api',
    
    // Assets path (relative to base)
    'assets_path' => '/assets',
    
    // Modules path (relative to site root)
    'modules_path' => '/modules',
    
    // Communication path (relative to site root)
    'communication_path' => '/communication',
    
    // Database configuration
    'database' => [
        'host' => 'localhost',
        'name' => 'yakima_finds',
        'user' => 'yfevents',
        'password' => EnvLoader::get('DB_PASS', '')
    ]
];