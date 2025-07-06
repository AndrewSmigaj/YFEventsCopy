#!/usr/bin/env php
<?php
/**
 * Database Rebuild Script for YFEvents
 * 
 * Usage:
 *   php rebuild_database.php                     # Rebuild structure only
 *   php rebuild_database.php --with-mock-data   # Rebuild with mock data
 *   php rebuild_database.php --force             # Skip confirmation
 */

// Configuration
$config = [
    'host' => 'localhost',
    'database' => 'yakima_finds',
    'username' => 'yfevents',
    'password' => 'yfevents_pass'
];

// Parse command line arguments
$options = getopt('', ['with-mock-data', 'force', 'help']);
$withMockData = isset($options['with-mock-data']);
$force = isset($options['force']);

if (isset($options['help'])) {
    echo "YFEvents Database Rebuild Script\n";
    echo "Usage: php rebuild_database.php [options]\n\n";
    echo "Options:\n";
    echo "  --with-mock-data    Generate realistic mock data after rebuild\n";
    echo "  --force            Skip confirmation prompt\n";
    echo "  --help             Show this help message\n";
    exit(0);
}

// Helpers
function info($message) {
    echo "\033[36m[INFO]\033[0m $message\n";
}

function success($message) {
    echo "\033[32m[SUCCESS]\033[0m $message\n";
}

function error($message) {
    echo "\033[31m[ERROR]\033[0m $message\n";
}

function warning($message) {
    echo "\033[33m[WARNING]\033[0m $message\n";
}

// Check PHP extensions
if (!extension_loaded('pdo_mysql')) {
    error("PDO MySQL extension is not installed");
    exit(1);
}

if ($withMockData && !extension_loaded('gd')) {
    error("GD extension is required for image generation");
    exit(1);
}

// Confirmation
if (!$force) {
    warning("This will DROP and RECREATE the entire database!");
    warning("All existing data will be lost.");
    echo "\nDatabase: {$config['database']}\n";
    echo "Mock data: " . ($withMockData ? "YES" : "NO") . "\n";
    echo "\nContinue? (yes/no): ";
    
    $confirm = trim(fgets(STDIN));
    if (strtolower($confirm) !== 'yes') {
        info("Operation cancelled");
        exit(0);
    }
}

// Connect to MySQL
try {
    $pdo = new PDO(
        "mysql:host={$config['host']};charset=utf8mb4",
        $config['username'],
        $config['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    success("Connected to MySQL");
} catch (PDOException $e) {
    error("Database connection failed: " . $e->getMessage());
    exit(1);
}

// Start rebuild
info("Starting database rebuild...");

try {
    // Drop and recreate database
    info("Dropping database if exists...");
    $pdo->exec("DROP DATABASE IF EXISTS {$config['database']}");
    
    info("Creating database...");
    $pdo->exec("CREATE DATABASE {$config['database']} 
                DEFAULT CHARACTER SET utf8mb4 
                DEFAULT COLLATE utf8mb4_unicode_ci");
    
    $pdo->exec("USE {$config['database']}");
    success("Database recreated");
    
    // Execute schema files in order
    $schemaFiles = [
        ['path' => 'database/calendar_schema.sql'],
        ['path' => 'database/modules_schema.sql'],
        ['path' => 'modules/yfauth/database/schema.sql'], 
        ['path' => 'modules/yfclaim/database/schema.sql'],
        ['path' => 'database/communication_schema_fixed.sql']
    ];
    
    foreach ($schemaFiles as $fileInfo) {
        $file = $fileInfo['path'];
        $path = __DIR__ . '/../' . $file;
        if (!file_exists($path)) {
            warning("Schema file not found: $file");
            continue;
        }
        
        info("Executing $file...");
        $sql = file_get_contents($path);
        
        // Split by delimiter and execute each statement
        $statements = array_filter(
            array_map('trim', 
            preg_split('/;\s*$/m', $sql)), 
            'strlen'
        );
        
        foreach ($statements as $statement) {
            if (!empty($statement) && stripos($statement, 'delimiter') === false) {
                $pdo->exec($statement);
            }
        }
        success("Executed $file");
    }
    
    // Generate mock data if requested
    if ($withMockData) {
        info("Generating mock data...");
        require_once __DIR__ . '/generate_mock_data.php';
        generateMockData($pdo);
        success("Mock data generated");
    }
    
    // Show summary
    echo "\n";
    success("Database rebuild complete!");
    
    // Show table count
    $stmt = $pdo->query("SELECT COUNT(*) FROM information_schema.tables 
                         WHERE table_schema = '{$config['database']}'");
    $tableCount = $stmt->fetchColumn();
    info("Total tables created: $tableCount");
    
    if ($withMockData) {
        // Show data counts
        $counts = [
            'events' => "SELECT COUNT(*) FROM events",
            'shops' => "SELECT COUNT(*) FROM local_shops",
            'estate sales' => "SELECT COUNT(*) FROM yfc_sales",
            'items' => "SELECT COUNT(*) FROM yfc_items",
            'users' => "SELECT COUNT(*) FROM yfa_auth_users"
        ];
        
        echo "\nMock data summary:\n";
        foreach ($counts as $label => $query) {
            try {
                $count = $pdo->query($query)->fetchColumn();
                echo "  - $label: $count\n";
            } catch (PDOException $e) {
                // Table might not exist
            }
        }
    }
    
} catch (PDOException $e) {
    error("Database operation failed: " . $e->getMessage());
    exit(1);
}

info("Done!");