#!/usr/bin/env php
<?php
/**
 * YFEvents Health Check Script
 * 
 * Verifies that the deployment was successful and all components are working.
 * 
 * Usage: php health-check.php
 */

// Color codes for output
define('GREEN', "\033[0;32m");
define('RED', "\033[0;31m");
define('YELLOW', "\033[1;33m");
define('BLUE', "\033[0;34m");
define('NC', "\033[0m"); // No Color

// Change to project root
$rootDir = dirname(dirname(__DIR__));
chdir($rootDir);

$errors = [];
$warnings = [];
$successes = [];

echo BLUE . "YFEvents Health Check\n";
echo "====================\n\n" . NC;

// 1. Check environment file
echo "Checking environment configuration...\n";
if (file_exists('.env')) {
    $successes[] = ".env file exists";
    $env = parse_ini_file('.env');
    
    if ($env) {
        // Check critical settings
        if (empty($env['DB_PASSWORD'])) {
            $errors[] = "Database password not set in .env";
        }
        
        if ($env['APP_ENV'] !== 'production') {
            $warnings[] = "APP_ENV is not set to 'production'";
        }
        
        if ($env['APP_DEBUG'] === 'true' || $env['APP_DEBUG'] === true) {
            $warnings[] = "APP_DEBUG is enabled (should be false in production)";
        }
        
        if (empty($env['GOOGLE_MAPS_API_KEY'])) {
            $warnings[] = "Google Maps API key not configured";
        }
    } else {
        $errors[] = "Could not parse .env file";
    }
} else {
    $errors[] = ".env file not found";
}

// 2. Check database connection
echo "\nChecking database connection...\n";
if (!empty($env)) {
    try {
        $pdo = new PDO(
            "mysql:host={$env['DB_HOST']};dbname={$env['DB_DATABASE']};charset=utf8mb4",
            $env['DB_USERNAME'],
            $env['DB_PASSWORD'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        $successes[] = "Database connection successful";
        
        // Check key tables
        $tables = [
            'events' => 'Core events table',
            'local_shops' => 'Shops directory table',
            'yfa_auth_users' => 'YFAuth users table',
            'yfa_auth_roles' => 'YFAuth roles table',
            'yfc_sellers' => 'YFClaim sellers table'
        ];
        
        foreach ($tables as $table => $description) {
            try {
                $stmt = $pdo->query("SELECT 1 FROM $table LIMIT 1");
                $successes[] = "$description exists";
            } catch (PDOException $e) {
                $warnings[] = "$description missing (may not be needed)";
            }
        }
        
        // Check for admin users
        try {
            $stmt = $pdo->query("
                SELECT COUNT(*) 
                FROM yfa_auth_users u 
                JOIN yfa_auth_user_roles ur ON u.id = ur.user_id 
                JOIN yfa_auth_roles r ON ur.role_id = r.id 
                WHERE r.name = 'super_admin'
            ");
            $adminCount = $stmt->fetchColumn();
            
            if ($adminCount > 0) {
                $successes[] = "$adminCount admin user(s) found";
            } else {
                $errors[] = "No admin users found - run create-admin.php";
            }
        } catch (PDOException $e) {
            $warnings[] = "Could not check admin users";
        }
        
    } catch (PDOException $e) {
        $errors[] = "Database connection failed: " . $e->getMessage();
    }
}

// 3. Check directory permissions
echo "\nChecking directory permissions...\n";
$directories = [
    'cache' => 'Cache directory',
    'logs' => 'Logs directory',
    'storage' => 'Storage directory',
    'public/uploads' => 'Uploads directory'
];

foreach ($directories as $dir => $description) {
    if (is_dir($dir)) {
        if (is_writable($dir)) {
            $successes[] = "$description is writable";
        } else {
            $errors[] = "$description is not writable";
        }
    } else {
        $warnings[] = "$description does not exist";
    }
}

// 4. Check required PHP extensions
echo "\nChecking PHP extensions...\n";
$extensions = [
    'pdo' => 'PDO',
    'pdo_mysql' => 'PDO MySQL',
    'curl' => 'cURL',
    'mbstring' => 'Multibyte String',
    'json' => 'JSON',
    'xml' => 'XML'
];

foreach ($extensions as $ext => $name) {
    if (extension_loaded($ext)) {
        $successes[] = "$name extension loaded";
    } else {
        $errors[] = "$name extension not loaded";
    }
}

// 5. Check web server accessibility
echo "\nChecking web server configuration...\n";
if (file_exists('public/index.php')) {
    $successes[] = "Public index.php exists";
} else {
    $errors[] = "Public index.php not found";
}

if (file_exists('public/.htaccess')) {
    $successes[] = "Apache .htaccess file exists";
} else {
    $warnings[] = "Apache .htaccess file not found";
}

// 6. Check cron setup
echo "\nChecking cron configuration...\n";
if (file_exists('cron/scrape-events.php')) {
    if (is_executable('cron/scrape-events.php')) {
        $successes[] = "Event scraper is executable";
    } else {
        $warnings[] = "Event scraper exists but is not executable";
    }
} else {
    $errors[] = "Event scraper script not found";
}

// 7. Check Composer dependencies
echo "\nChecking Composer dependencies...\n";
if (file_exists('vendor/autoload.php')) {
    $successes[] = "Composer dependencies installed";
} else {
    $errors[] = "Composer dependencies not installed";
}

// Display results
echo "\n" . str_repeat("=", 50) . "\n";
echo "Health Check Results\n";
echo str_repeat("=", 50) . "\n\n";

// Successes
if (!empty($successes)) {
    echo GREEN . "✓ Passed Checks (" . count($successes) . "):\n" . NC;
    foreach ($successes as $success) {
        echo "  • $success\n";
    }
    echo "\n";
}

// Warnings
if (!empty($warnings)) {
    echo YELLOW . "⚠ Warnings (" . count($warnings) . "):\n" . NC;
    foreach ($warnings as $warning) {
        echo "  • $warning\n";
    }
    echo "\n";
}

// Errors
if (!empty($errors)) {
    echo RED . "✗ Errors (" . count($errors) . "):\n" . NC;
    foreach ($errors as $error) {
        echo "  • $error\n";
    }
    echo "\n";
}

// Overall status
echo str_repeat("=", 50) . "\n";
if (empty($errors)) {
    echo GREEN . "Overall Status: HEALTHY\n" . NC;
    echo "Your YFEvents installation appears to be working correctly.\n";
    
    if (!empty($warnings)) {
        echo "\nYou may want to address the warnings above for optimal performance.\n";
    }
    exit(0);
} else {
    echo RED . "Overall Status: UNHEALTHY\n" . NC;
    echo "Please fix the errors listed above before using the application.\n";
    exit(1);
}