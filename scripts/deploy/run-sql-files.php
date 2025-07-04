#!/usr/bin/env php
<?php
/**
 * YFEvents SQL Schema Executor
 * 
 * This script properly executes SQL files in the correct order.
 * It fixes the limitation in installer.php where executeSqlFile() doesn't actually run SQL.
 * 
 * Usage: php run-sql-files.php [--force]
 */

// Color codes for output
define('GREEN', "\033[0;32m");
define('RED', "\033[0;31m");
define('YELLOW', "\033[1;33m");
define('NC', "\033[0m"); // No Color

// Get command line options
$options = getopt('', ['force']);
$force = isset($options['force']);

// Change to project root
$rootDir = dirname(dirname(__DIR__));
chdir($rootDir);

// Load environment configuration
if (!file_exists('.env')) {
    die(RED . "Error: .env file not found. Run installer.php first.\n" . NC);
}

// Parse .env file
$env = parse_ini_file('.env');
if (!$env) {
    die(RED . "Error: Could not parse .env file\n" . NC);
}

// Database configuration
$dbHost = $env['DB_HOST'] ?? 'localhost';
$dbName = $env['DB_DATABASE'] ?? 'yakima_finds';
$dbUser = $env['DB_USERNAME'] ?? 'yfevents';
$dbPass = $env['DB_PASSWORD'] ?? '';

echo "YFEvents SQL Schema Executor\n";
echo "============================\n\n";

// Connect to database
try {
    $pdo = new PDO(
        "mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4",
        $dbUser,
        $dbPass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
    echo GREEN . "[✓] Connected to database\n" . NC;
} catch (PDOException $e) {
    die(RED . "[✗] Database connection failed: " . $e->getMessage() . "\n" . NC);
}

// Define SQL files in the correct order (from INSTALL_ORDER.md)
$sqlFiles = [
    // Core tables (required)
    'database/calendar_schema.sql' => 'Calendar and events system',
    'database/shop_claim_system.sql' => 'Shop system',
    'database/modules_schema.sql' => 'Module support system',
    
    // Communication systems (choose one)
    // 'database/communication_schema_fixed.sql' => 'Communication system',
    // 'database/yfchat_schema.sql' => 'Full chat system',
    'database/yfchat_subset.sql' => 'Admin-seller chat (simplified)',
    
    // System features
    'database/batch_processing_schema.sql' => 'Batch processing',
    'database/intelligent_scraper_schema.sql' => 'Intelligent scraper',
    
    // Module schemas
    'modules/yfauth/database/schema.sql' => 'YFAuth authentication',
    'modules/yfclaim/database/schema.sql' => 'YFClaim estate sales',
    'modules/yftheme/database/schema.sql' => 'YFTheme customization',
    
    // Optional improvements
    'database/performance_optimization.sql' => 'Performance optimizations',
    'database/security_improvements.sql' => 'Security improvements',
    'database/audit_logging.sql' => 'Audit logging'
];

// Function to check if a table exists
function tableExists($pdo, $tableName) {
    try {
        $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$tableName]);
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        return false;
    }
}

// Function to execute SQL file
function executeSqlFile($pdo, $filePath, $description) {
    global $force;
    
    if (!file_exists($filePath)) {
        echo YELLOW . "[!] File not found: $filePath - Skipping\n" . NC;
        return true;
    }
    
    // Check if we should skip (based on existing tables)
    if (!$force) {
        // Simple check: look for CREATE TABLE statements and see if any table exists
        $sql = file_get_contents($filePath);
        preg_match_all('/CREATE TABLE IF NOT EXISTS (\w+)/i', $sql, $matches);
        
        if (!empty($matches[1])) {
            foreach ($matches[1] as $table) {
                if (tableExists($pdo, $table)) {
                    echo YELLOW . "[!] Tables from $filePath already exist - Skipping (use --force to override)\n" . NC;
                    return true;
                }
            }
        }
    }
    
    echo "Executing: $description ($filePath)...\n";
    
    try {
        // Read SQL file
        $sql = file_get_contents($filePath);
        
        // Split by semicolon but be careful about strings
        $statements = preg_split('/;(?=(?:[^\'"]|\'[^\']*\'|"[^"]*")*$)/', $sql);
        
        $pdo->beginTransaction();
        
        foreach ($statements as $statement) {
            $statement = trim($statement);
            if (!empty($statement)) {
                $pdo->exec($statement);
            }
        }
        
        $pdo->commit();
        echo GREEN . "[✓] Success: $description\n\n" . NC;
        return true;
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        echo RED . "[✗] Failed: " . $e->getMessage() . "\n\n" . NC;
        return false;
    }
}

// Execute each SQL file
$failed = false;
foreach ($sqlFiles as $file => $description) {
    if (!executeSqlFile($pdo, $file, $description)) {
        $failed = true;
        // Don't continue if a required file fails
        if (strpos($file, 'database/') === 0 && 
            in_array(basename($file), ['calendar_schema.sql', 'modules_schema.sql'])) {
            echo RED . "Critical schema failed. Stopping execution.\n" . NC;
            break;
        }
    }
}

if (!$failed) {
    echo GREEN . "\n========================================\n";
    echo "All schemas executed successfully!\n";
    echo "========================================\n" . NC;
    
    // Show some statistics
    try {
        $stmt = $pdo->query("SHOW TABLES");
        $tableCount = $stmt->rowCount();
        echo "\nDatabase statistics:\n";
        echo "- Total tables created: $tableCount\n";
        
        // Check for admin roles
        if (tableExists($pdo, 'yfa_auth_roles')) {
            $stmt = $pdo->query("SELECT COUNT(*) FROM yfa_auth_roles");
            $roleCount = $stmt->fetchColumn();
            echo "- Roles configured: $roleCount\n";
        }
        
        echo "\nNext step: Run create-admin.php to create your first admin user.\n";
        
    } catch (PDOException $e) {
        // Ignore stats errors
    }
} else {
    echo RED . "\nSome schemas failed to execute.\n" . NC;
    echo "Fix the errors and run again.\n";
    exit(1);
}