<?php
// Check if communication tables were created
require_once __DIR__ . '/../config/database.php';

try {
    $pdo = new PDO(
        "mysql:host=localhost;dbname=yakima_finds;charset=utf8mb4",
        'yfevents',
        'yfevents_pass',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    $query = "SELECT table_name FROM information_schema.tables 
              WHERE table_schema = 'yakima_finds' 
              AND table_name LIKE 'communication_%'
              ORDER BY table_name";
    
    $stmt = $pdo->query($query);
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "Communication tables found: " . count($tables) . "\n";
    
    if (count($tables) > 0) {
        echo "\nTables:\n";
        foreach ($tables as $table) {
            echo "- $table\n";
        }
    } else {
        echo "\nNo communication tables found. Running schema...\n";
        
        // Read and execute schema
        $schema = file_get_contents(__DIR__ . '/../database/communication_schema.sql');
        
        // Split by semicolons and execute each statement
        $statements = array_filter(array_map('trim', explode(';', $schema)));
        
        foreach ($statements as $statement) {
            if (!empty($statement) && !preg_match('/^--/', $statement)) {
                try {
                    $pdo->exec($statement);
                    echo ".";
                } catch (PDOException $e) {
                    echo "\nError: " . $e->getMessage() . "\n";
                }
            }
        }
        
        echo "\nSchema execution complete. Checking tables again...\n";
        
        $stmt = $pdo->query($query);
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        echo "\nCommunication tables now: " . count($tables) . "\n";
        foreach ($tables as $table) {
            echo "- $table\n";
        }
    }
    
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
}