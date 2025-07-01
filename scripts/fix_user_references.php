#!/usr/bin/env php
<?php
/**
 * Script to update all SQL files to use yfa_auth_users consistently
 * and create proper import order
 */

$baseDir = dirname(__DIR__);

// Files that need user reference updates
$filesToUpdate = [
    'database/yfchat_schema.sql',
    'database/communication_schema.sql',
    'database/communication_schema_fixed.sql',
];

// Backup original files
echo "Creating backups...\n";
$backupDir = $baseDir . '/database/backups_' . date('Y-m-d_H-i-s');
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0755, true);
}

foreach ($filesToUpdate as $file) {
    $fullPath = $baseDir . '/' . $file;
    if (file_exists($fullPath)) {
        $backupPath = $backupDir . '/' . basename($file);
        copy($fullPath, $backupPath);
        echo "Backed up: $file -> $backupPath\n";
    }
}

// Update references
echo "\nUpdating user references...\n";
foreach ($filesToUpdate as $file) {
    $fullPath = $baseDir . '/' . $file;
    if (file_exists($fullPath)) {
        $content = file_get_contents($fullPath);
        $originalContent = $content;
        
        // Count replacements
        $replacements = 0;
        
        // Replace foreign key references (handles various quote styles)
        $patterns = [
            // FOREIGN KEY references
            '/REFERENCES\s+[`\'"]?users[`\'"]?\s*\(/i' => 'REFERENCES `yfa_auth_users`(',
            
            // FROM clauses
            '/FROM\s+[`\'"]?users[`\'"]?\s+/i' => 'FROM `yfa_auth_users` ',
            '/FROM\s+[`\'"]?users[`\'"]?$/im' => 'FROM `yfa_auth_users`',
            
            // JOIN clauses
            '/JOIN\s+[`\'"]?users[`\'"]?\s+/i' => 'JOIN `yfa_auth_users` ',
            
            // INSERT/UPDATE clauses
            '/INTO\s+[`\'"]?users[`\'"]?\s+/i' => 'INTO `yfa_auth_users` ',
            '/UPDATE\s+[`\'"]?users[`\'"]?\s+/i' => 'UPDATE `yfa_auth_users` ',
            
            // WHERE clauses with users table
            '/[`\'"]?users[`\'"]?\.[`\'"]?/i' => '`yfa_auth_users`.',
            
            // Specific cases in stored procedures/triggers
            '/SELECT\s+(\w+)\s+FROM\s+users\s+WHERE/i' => 'SELECT $1 FROM yfa_auth_users WHERE',
        ];
        
        foreach ($patterns as $pattern => $replacement) {
            $content = preg_replace($pattern, $replacement, $content, -1, $count);
            $replacements += $count;
        }
        
        // Handle the specific INSERT statement in yfchat_schema.sql
        $content = str_replace(
            "INSERT INTO chat_user_settings (user_id)\nSELECT id FROM users",
            "INSERT INTO chat_user_settings (user_id)\nSELECT id FROM yfa_auth_users",
            $content
        );
        
        if ($content !== $originalContent) {
            file_put_contents($fullPath, $content);
            echo "Updated: $file ($replacements replacements)\n";
        } else {
            echo "No changes needed: $file\n";
        }
    }
}

// Check for calendar_sources table creation
echo "\nChecking for calendar_sources table...\n";
$calendarSchemaPath = $baseDir . '/database/calendar_schema.sql';
if (file_exists($calendarSchemaPath)) {
    $content = file_get_contents($calendarSchemaPath);
    if (strpos($content, 'CREATE TABLE') !== false && strpos($content, 'calendar_sources') !== false) {
        echo "✓ calendar_sources table found in calendar_schema.sql\n";
    } else {
        echo "⚠ Warning: calendar_sources table not found in calendar_schema.sql\n";
        echo "  intelligent_scraper_schema.sql may fail to import\n";
    }
}

// Create import order script
$importScript = <<<'SQL'
-- Database Import Script with Correct Order
-- Generated on: <?php echo date('Y-m-d H:i:s'); ?>

-- Run this script to import all schemas in the correct dependency order

-- Step 1: Create database if not exists
CREATE DATABASE IF NOT EXISTS yakima_finds;
USE yakima_finds;

-- Step 2: Import auth module first (creates yfa_auth_users)
SOURCE modules/yfauth/database/schema.sql;

-- Step 3: Import base schemas without user dependencies
SOURCE database/calendar_schema.sql;
SOURCE database/modules_schema.sql;

-- Step 4: Import other modules
SOURCE modules/yfclaim/database/schema.sql;
SOURCE modules/yfclassifieds/database/schema.sql;
SOURCE modules/yftheme/database/schema.sql;

-- Step 5: Import schemas with user dependencies (now updated to use yfa_auth_users)
SOURCE database/yfchat_schema.sql;
SOURCE database/communication_schema_fixed.sql;
SOURCE database/shop_claim_system.sql;

-- Step 6: Import audit and optimization schemas
SOURCE database/audit_logging.sql;
SOURCE database/performance_optimization.sql;
SOURCE database/security_improvements.sql;

-- Step 7: Import remaining schemas
SOURCE database/batch_processing_schema.sql;
SOURCE database/intelligent_scraper_schema.sql;

-- Step 8: Show results
SELECT 'Import complete!' as Status;
SELECT COUNT(*) as 'Total Tables' FROM information_schema.tables WHERE table_schema = DATABASE();
SHOW TABLES;
SQL;

// Replace the PHP date placeholder
$importScript = str_replace('<?php echo date(\'Y-m-d H:i:s\'); ?>', date('Y-m-d H:i:s'), $importScript);

file_put_contents($baseDir . '/database/import_all_schemas.sql', $importScript);
echo "\nCreated import script: database/import_all_schemas.sql\n";

// Create a simple test script
$testScript = <<<'BASH'
#!/bin/bash
# Test script to verify the import worked

echo "Testing database import..."
mysql -u yfevents -pyfevents_pass yakima_finds -e "
SELECT 
    'yfa_auth_users' as 'Table',
    COUNT(*) as 'Count',
    'Main user authentication table' as 'Description'
FROM information_schema.tables 
WHERE table_schema = DATABASE() AND table_name = 'yfa_auth_users'
UNION ALL
SELECT 
    'Total Tables' as 'Table',
    COUNT(*) as 'Count',
    'All tables in database' as 'Description'
FROM information_schema.tables 
WHERE table_schema = DATABASE();"
BASH;

file_put_contents($baseDir . '/scripts/test_db_import.sh', $testScript);
chmod($baseDir . '/scripts/test_db_import.sh', 0755);

echo "\nDone! Next steps:\n";
echo "1. Review the changes in the updated SQL files\n";
echo "2. Run: mysql -u yfevents -pyfevents_pass < database/import_all_schemas.sql\n";
echo "3. Test with: ./scripts/test_db_import.sh\n";
echo "4. Create synthetic test data\n";
echo "\nBackups saved in: $backupDir\n";