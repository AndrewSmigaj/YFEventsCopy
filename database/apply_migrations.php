<?php
// Script to apply database migrations
require_once __DIR__ . '/../config/database.php';

echo "Applying intelligent scraper database migration...\n";

try {
    // Read and execute both SQL files
    $sql1 = file_get_contents(__DIR__ . '/intelligent_scraper_schema.sql');
    $sql2 = file_get_contents(__DIR__ . '/batch_processing_schema.sql');
    $sql = $sql1 . "\n\n" . $sql2;
    
    // Split by semicolons but be careful with stored procedures/functions
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    
    foreach ($statements as $statement) {
        if (!empty($statement)) {
            try {
                $pdo->exec($statement);
                echo ".";
            } catch (PDOException $e) {
                // Check if it's a duplicate key/table exists error
                if (strpos($e->getMessage(), 'already exists') !== false || 
                    strpos($e->getMessage(), 'Duplicate') !== false) {
                    echo "S"; // Skip
                } else {
                    throw $e;
                }
            }
        }
    }
    
    echo "\nMigration completed successfully!\n";
} catch (Exception $e) {
    echo "\nError applying migration: " . $e->getMessage() . "\n";
    exit(1);
}