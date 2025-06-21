<?php
// Test database and create communication tables

try {
    echo "Connecting to database...\n";
    $pdo = new PDO(
        "mysql:host=localhost;dbname=yakima_finds;charset=utf8mb4",
        'yfevents',
        'yfevents_pass',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    echo "Connected successfully.\n\n";
    
    // Check existing tables
    echo "Checking existing tables...\n";
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Total tables in database: " . count($tables) . "\n";
    
    // Check for required tables
    $requiredTables = ['users', 'events', 'local_shops'];
    foreach ($requiredTables as $table) {
        if (in_array($table, $tables)) {
            echo "✓ Found required table: $table\n";
        } else {
            echo "✗ Missing required table: $table\n";
        }
    }
    
    // Check communication tables
    echo "\nChecking communication tables...\n";
    $commTables = array_filter($tables, function($t) { return strpos($t, 'communication_') === 0; });
    echo "Communication tables found: " . count($commTables) . "\n";
    
    if (count($commTables) == 0) {
        echo "\nCreating communication tables...\n";
        
        // Read the fixed schema file
        $schemaFile = __DIR__ . '/../database/communication_schema_fixed.sql';
        if (!file_exists($schemaFile)) {
            die("Schema file not found: $schemaFile\n");
        }
        
        $schema = file_get_contents($schemaFile);
        
        // Execute the entire schema at once
        try {
            $pdo->exec($schema);
            echo "Schema executed successfully!\n";
        } catch (PDOException $e) {
            echo "Error executing schema: " . $e->getMessage() . "\n";
            
            // Try executing statement by statement
            echo "\nTrying statement by statement...\n";
            $statements = preg_split('/;\s*$/m', $schema);
            
            foreach ($statements as $i => $statement) {
                $statement = trim($statement);
                if (empty($statement)) continue;
                
                try {
                    $pdo->exec($statement);
                    echo ".";
                } catch (PDOException $e) {
                    echo "\nStatement " . ($i + 1) . " failed: " . $e->getMessage() . "\n";
                    echo "Statement: " . substr($statement, 0, 100) . "...\n";
                }
            }
        }
        
        // Check again
        echo "\n\nRechecking communication tables...\n";
        $stmt = $pdo->query("SHOW TABLES LIKE 'communication_%'");
        $commTables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        echo "Communication tables now: " . count($commTables) . "\n";
        foreach ($commTables as $table) {
            echo "✓ $table\n";
        }
    } else {
        echo "\nExisting communication tables:\n";
        foreach ($commTables as $table) {
            echo "✓ $table\n";
        }
    }
    
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
    echo "Error code: " . $e->getCode() . "\n";
}