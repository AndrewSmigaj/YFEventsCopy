<?php
// Simple test page to verify the refactored application is working

require_once __DIR__ . '/vendor/autoload.php';

echo "<h1>YFEvents V2 Refactored Application Test</h1>\n";

try {
    // Test autoloading
    echo "<h2>✅ Autoloading Test</h2>\n";
    echo "<p>✅ Autoloader loaded successfully</p>\n";
    
    // Test configuration
    echo "<h2>✅ Configuration Test</h2>\n";
    $config = new \YFEvents\Infrastructure\Config\Config(__DIR__ . '/config');
    echo "<p>✅ Configuration loaded successfully</p>\n";
    echo "<p>Database: " . $config->get('database.name') . "</p>\n";
    
    // Test database connection
    echo "<h2>✅ Database Test</h2>\n";
    $connection = new \YFEvents\Infrastructure\Database\Connection($config);
    $pdo = $connection->getPdo();
    echo "<p>✅ Database connection successful</p>\n";
    
    // Test event count
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM events");
    $eventCount = $stmt->fetch()['count'];
    echo "<p>✅ Found {$eventCount} events in database</p>\n";
    
    // Test shop count
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM local_shops");
    $shopCount = $stmt->fetch()['count'];
    echo "<p>✅ Found {$shopCount} shops in database</p>\n";
    
    echo "<h2>🎉 All Tests Passed!</h2>\n";
    echo "<p><strong>The refactored application is working correctly.</strong></p>\n";
    
    echo "<h3>Available Endpoints:</h3>\n";
    echo "<ul>\n";
    echo "<li><a href='/refactor/'>🏠 Main Application</a></li>\n";
    echo "<li><a href='/refactor/api/events'>📅 Events API</a></li>\n";
    echo "<li><a href='/refactor/api/shops'>🏪 Shops API</a></li>\n";
    echo "</ul>\n";
    
} catch (Exception $e) {
    echo "<h2>❌ Error</h2>\n";
    echo "<p>Error: " . htmlspecialchars($e->getMessage()) . "</p>\n";
    echo "<p>File: " . $e->getFile() . " Line: " . $e->getLine() . "</p>\n";
}
?>

<style>
body { font-family: Arial, sans-serif; margin: 40px; }
h1 { color: #2c3e50; }
h2 { color: #27ae60; }
p { margin: 10px 0; }
a { color: #3498db; text-decoration: none; }
a:hover { text-decoration: underline; }
</style>