<?php
// Database connection test
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>YFEvents Database Test</h1>";

// Check if config exists
$configFile = dirname(dirname(__DIR__)) . '/config/database.php';
if (!file_exists($configFile)) {
    die("ERROR: Database config file not found at: $configFile");
}

echo "<p>✓ Config file found</p>";

// Check if .env exists
$envFile = dirname(dirname(__DIR__)) . '/.env';
if (!file_exists($envFile)) {
    die("ERROR: .env file not found at: $envFile");
}

echo "<p>✓ .env file found</p>";

// Try to include the config
try {
    require_once $configFile;
    echo "<p>✓ Database config loaded</p>";
    
    // Test connection
    if (isset($pdo) && $pdo instanceof PDO) {
        echo "<p>✓ Database connection successful</p>";
        
        // Test query
        $result = $pdo->query("SELECT COUNT(*) as count FROM information_schema.tables WHERE table_schema = 'yakima_finds'");
        $row = $result->fetch();
        echo "<p>✓ Found {$row['count']} tables in yakima_finds database</p>";
        
        // List tables
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        echo "<p>Tables: " . implode(', ', $tables) . "</p>";
    } else {
        echo "<p>✗ PDO connection not established</p>";
    }
} catch (Exception $e) {
    echo "<p>✗ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<hr>";
echo "<p><a href='/'>Home</a> | <a href='/calendar.php'>Calendar</a></p>";
?>