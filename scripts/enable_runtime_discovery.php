#!/usr/bin/env php
<?php

/**
 * Script to enable runtime discovery logging
 */

declare(strict_types=1);

// Define base path
define('BASE_PATH', dirname(__DIR__));

// Load database configuration
require_once BASE_PATH . '/config/database.php';

try {
    echo "Runtime Discovery Setup\n";
    echo "======================\n\n";
    
    // Check if .env file exists
    $envFile = BASE_PATH . '/.env';
    $envExists = file_exists($envFile);
    
    if ($envExists) {
        echo "✓ Found .env file\n";
        
        // Read current .env
        $envContent = file_get_contents($envFile);
        
        // Check if ENABLE_RUNTIME_DISCOVERY already exists
        if (strpos($envContent, 'ENABLE_RUNTIME_DISCOVERY') === false) {
            // Add the setting
            $envContent .= "\n# Runtime Discovery\nENABLE_RUNTIME_DISCOVERY=true\n";
            file_put_contents($envFile, $envContent);
            echo "✓ Added ENABLE_RUNTIME_DISCOVERY=true to .env\n";
        } else {
            // Update the setting
            $envContent = preg_replace('/ENABLE_RUNTIME_DISCOVERY=.*/', 'ENABLE_RUNTIME_DISCOVERY=true', $envContent);
            file_put_contents($envFile, $envContent);
            echo "✓ Updated ENABLE_RUNTIME_DISCOVERY=true in .env\n";
        }
    } else {
        // Create .env file
        $envContent = "# Runtime Discovery\nENABLE_RUNTIME_DISCOVERY=true\n";
        file_put_contents($envFile, $envContent);
        echo "✓ Created .env file with ENABLE_RUNTIME_DISCOVERY=true\n";
    }
    
    // Clear existing logs
    echo "\nClearing existing logs...\n";
    
    // Clear log files
    $logDir = BASE_PATH . '/logs';
    if (is_dir($logDir)) {
        $logFiles = glob($logDir . '/system-*.log');
        foreach ($logFiles as $file) {
            unlink($file);
        }
        echo "✓ Cleared " . count($logFiles) . " log files\n";
    }
    
    // Clear database logs
    try {
        $pdo = new PDO(
            sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_NAME),
            DB_USER,
            DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        
        // Truncate system_logs table
        $pdo->exec("TRUNCATE TABLE IF EXISTS system_logs");
        echo "✓ Cleared system_logs database table\n";
    } catch (\Exception $e) {
        echo "⚠ Could not clear database logs: " . $e->getMessage() . "\n";
    }
    
    echo "\n✅ Runtime discovery is now enabled!\n\n";
    echo "Instructions:\n";
    echo "1. Visit your site and navigate through all working pages\n";
    echo "2. The system will log all execution paths\n";
    echo "3. Run the analysis script to see what was discovered\n";
    
} catch (\Exception $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}