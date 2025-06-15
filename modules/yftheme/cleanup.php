<?php
/**
 * YFTheme Module Cleanup Script
 * Safely removes all theme tables for clean reinstallation
 */

require_once __DIR__ . '/../../config/database.php';

echo "<h1>🧹 YFTheme Module Cleanup</h1>\n";
echo "<style>
body { font-family: 'Segoe UI', Tahoma, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; }
.status-ok { color: #27ae60; font-weight: bold; }
.status-error { color: #e74c3c; font-weight: bold; }
.status-warning { color: #f39c12; font-weight: bold; }
.code { background: #f4f4f4; padding: 10px; border-radius: 4px; font-family: monospace; }
</style>\n";

// Check if user confirmed cleanup
$confirmed = isset($_GET['confirm']) && $_GET['confirm'] === 'yes';

if (!$confirmed) {
    echo "<div style='background: #fff3cd; border: 1px solid #ffeaa7; padding: 20px; border-radius: 8px; margin: 20px 0;'>\n";
    echo "<h2>⚠️ Warning: This will remove all theme data</h2>\n";
    echo "<p>This script will permanently delete:</p>\n";
    echo "<ul>\n";
    echo "<li>All theme variables and settings</li>\n";
    echo "<li>Theme history and change logs</li>\n";
    echo "<li>Custom theme presets</li>\n";
    echo "<li>All theme-related database tables</li>\n";
    echo "</ul>\n";
    echo "<p><strong>This action cannot be undone!</strong></p>\n";
    echo "<p><a href='?confirm=yes' style='background: #e74c3c; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px;'>⚠️ Yes, Delete Everything</a></p>\n";
    echo "<p><a href='/admin/system-status.php' style='background: #95a5a6; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px;'>← Cancel & Go Back</a></p>\n";
    echo "</div>\n";
    exit;
}

try {
    echo "<h2>🗑️ Removing YFTheme Tables</h2>\n";
    
    // Start transaction for safety
    $pdo->beginTransaction();
    
    // Disable foreign key checks for clean removal
    $pdo->exec("SET foreign_key_checks = 0");
    
    // List of tables to remove (in correct order)
    $tables = [
        'theme_history',
        'theme_variable_overrides', 
        'theme_preset_variables',
        'theme_variables',
        'theme_presets',
        'theme_categories'
    ];
    
    $removed = 0;
    $skipped = 0;
    
    foreach ($tables as $table) {
        try {
            // Check if table exists first
            $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
            if ($stmt->rowCount() > 0) {
                $pdo->exec("DROP TABLE $table");
                echo "✅ Removed table: <code>$table</code><br>\n";
                $removed++;
            } else {
                echo "⚪ Table <code>$table</code> doesn't exist (skipped)<br>\n";
                $skipped++;
            }
        } catch (Exception $e) {
            echo "❌ Error removing <code>$table</code>: " . htmlspecialchars($e->getMessage()) . "<br>\n";
        }
    }
    
    // Re-enable foreign key checks
    $pdo->exec("SET foreign_key_checks = 1");
    
    // Commit transaction
    $pdo->commit();
    
    echo "<h2>✅ Cleanup Complete</h2>\n";
    echo "<p><strong>Summary:</strong></p>\n";
    echo "<ul>\n";
    echo "<li>Tables removed: $removed</li>\n";
    echo "<li>Tables skipped: $skipped</li>\n";
    echo "</ul>\n";
    
    // Clean up CSS files too
    echo "<h3>🧹 Cleaning up generated files</h3>\n";
    
    $cssFiles = [
        __DIR__ . '/www/css/current-theme.css',
        __DIR__ . '/www/css/theme-integration.css'
    ];
    
    foreach ($cssFiles as $file) {
        if (file_exists($file)) {
            unlink($file);
            echo "✅ Removed file: <code>" . basename($file) . "</code><br>\n";
        }
    }
    
    echo "<h3>🚀 Next Steps</h3>\n";
    echo "<p>The YFTheme module has been completely removed. You can now:</p>\n";
    echo "<ul>\n";
    echo "<li><a href='/modules/yftheme/install.php'>🔧 Reinstall YFTheme Module</a></li>\n";
    echo "<li><a href='/admin/system-status.php'>📊 Check System Status</a></li>\n";
    echo "<li><a href='/css-diagnostic.php'>🩺 Run CSS Diagnostic</a></li>\n";
    echo "</ul>\n";
    
} catch (Exception $e) {
    // Rollback on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    echo "<h2>❌ Cleanup Failed</h2>\n";
    echo "<p class='status-error'>Error: " . htmlspecialchars($e->getMessage()) . "</p>\n";
    echo "<p><strong>File:</strong> " . htmlspecialchars($e->getFile()) . "</p>\n";
    echo "<p><strong>Line:</strong> " . htmlspecialchars($e->getLine()) . "</p>\n";
    
    echo "<h3>🔧 Manual Cleanup</h3>\n";
    echo "<p>If automatic cleanup failed, you can manually run these SQL commands:</p>\n";
    echo "<div class='code'>\n";
    echo "SET foreign_key_checks = 0;<br>\n";
    echo "DROP TABLE IF EXISTS theme_history;<br>\n";
    echo "DROP TABLE IF EXISTS theme_variable_overrides;<br>\n";
    echo "DROP TABLE IF EXISTS theme_preset_variables;<br>\n";
    echo "DROP TABLE IF EXISTS theme_variables;<br>\n";
    echo "DROP TABLE IF EXISTS theme_presets;<br>\n";
    echo "DROP TABLE IF EXISTS theme_categories;<br>\n";
    echo "SET foreign_key_checks = 1;<br>\n";
    echo "</div>\n";
}
?>

<div style="margin-top: 40px; text-align: center; color: #7f8c8d;">
    <p>YFTheme Cleanup Tool | <a href="/admin/">← Back to Admin</a></p>
</div>