<?php
// Check communication tables
require_once dirname(__DIR__) . '/config/database.php';

echo "Checking communication tables...\n\n";

try {
    // Check for communication tables
    $stmt = $pdo->query("SHOW TABLES LIKE 'communication_%'");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($tables)) {
        echo "❌ No communication tables found!\n";
        echo "You need to import the schema from:\n";
        echo "  - database/communication_schema.sql\n";
        echo "  - database/communication_schema_fixed.sql\n";
    } else {
        echo "✅ Found " . count($tables) . " communication tables:\n";
        foreach ($tables as $table) {
            echo "  - $table\n";
            
            // Count records in each table
            $countStmt = $pdo->query("SELECT COUNT(*) FROM `$table`");
            $count = $countStmt->fetchColumn();
            echo "    Records: $count\n";
        }
    }
    
    echo "\n";
    
    // Check for required auth tables
    echo "Checking auth tables...\n";
    $authTables = ['yfa_auth_users', 'yfa_auth_sessions', 'yfa_auth_roles'];
    foreach ($authTables as $table) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($stmt->fetchColumn()) {
            $countStmt = $pdo->query("SELECT COUNT(*) FROM `$table`");
            $count = $countStmt->fetchColumn();
            echo "✅ $table exists (Records: $count)\n";
        } else {
            echo "❌ $table missing!\n";
        }
    }
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}