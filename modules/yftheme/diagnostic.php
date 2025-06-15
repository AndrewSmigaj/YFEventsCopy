<?php
/**
 * YFTheme Database Schema Diagnostic
 * Checks database schema compatibility
 */

require_once __DIR__ . '/../../config/database.php';

echo "<h1>🔍 YFTheme Database Schema Diagnostic</h1>\n";
echo "<style>
body { font-family: 'Segoe UI', Tahoma, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; }
.status-ok { color: #27ae60; font-weight: bold; }
.status-error { color: #e74c3c; font-weight: bold; }
.status-warning { color: #f39c12; font-weight: bold; }
table { width: 100%; border-collapse: collapse; margin: 15px 0; }
th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
th { background: #f4f4f4; }
.code { background: #f4f4f4; padding: 10px; border-radius: 4px; font-family: monospace; }
</style>\n";

try {
    // Check if theme tables exist
    $tables = ['theme_categories', 'theme_variables', 'theme_presets', 'theme_preset_variables'];
    
    echo "<h2>📋 Table Existence Check</h2>\n";
    echo "<table>\n";
    echo "<tr><th>Table Name</th><th>Status</th><th>Row Count</th></tr>\n";
    
    foreach ($tables as $table) {
        try {
            $stmt = $pdo->query("SELECT COUNT(*) FROM $table");
            $count = $stmt->fetchColumn();
            echo "<tr><td>$table</td><td class='status-ok'>✅ EXISTS</td><td>$count rows</td></tr>\n";
        } catch (Exception $e) {
            echo "<tr><td>$table</td><td class='status-error'>❌ MISSING</td><td>N/A</td></tr>\n";
        }
    }
    echo "</table>\n";
    
    // Check theme_variables table structure
    echo "<h2>🏗️ theme_variables Table Structure</h2>\n";
    try {
        $stmt = $pdo->query("DESCRIBE theme_variables");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<table>\n";
        echo "<tr><th>Column Name</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>\n";
        
        $hasTypeColumn = false;
        foreach ($columns as $column) {
            $field = $column['Field'];
            if ($field === 'type') {
                $hasTypeColumn = true;
            }
            echo "<tr>";
            echo "<td><strong>$field</strong></td>";
            echo "<td>{$column['Type']}</td>";
            echo "<td>{$column['Null']}</td>";
            echo "<td>{$column['Key']}</td>";
            echo "<td>{$column['Default']}</td>";
            echo "<td>{$column['Extra']}</td>";
            echo "</tr>\n";
        }
        echo "</table>\n";
        
        echo "<h3>🎯 Key Column Check</h3>\n";
        if ($hasTypeColumn) {
            echo "<p class='status-ok'>✅ 'type' column found - installation should work</p>\n";
        } else {
            echo "<p class='status-error'>❌ 'type' column missing - installation will fail</p>\n";
        }
        
    } catch (Exception $e) {
        echo "<p class='status-error'>❌ Could not describe theme_variables table: " . htmlspecialchars($e->getMessage()) . "</p>\n";
    }
    
    // Check for sample data
    echo "<h2>📊 Sample Data Check</h2>\n";
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM theme_variables");
        $varCount = $stmt->fetchColumn();
        
        $stmt = $pdo->query("SELECT COUNT(*) FROM theme_categories");
        $catCount = $stmt->fetchColumn();
        
        echo "<p><strong>Theme Variables:</strong> $varCount</p>\n";
        echo "<p><strong>Theme Categories:</strong> $catCount</p>\n";
        
        if ($varCount > 0) {
            echo "<h3>Sample Variables:</h3>\n";
            $stmt = $pdo->query("SELECT name, css_variable, type, default_value FROM theme_variables LIMIT 5");
            $samples = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo "<table>\n";
            echo "<tr><th>Name</th><th>CSS Variable</th><th>Type</th><th>Default Value</th></tr>\n";
            foreach ($samples as $sample) {
                echo "<tr>";
                echo "<td>{$sample['name']}</td>";
                echo "<td>{$sample['css_variable']}</td>";
                echo "<td>{$sample['type']}</td>";
                echo "<td>{$sample['default_value']}</td>";
                echo "</tr>\n";
            }
            echo "</table>\n";
        }
        
    } catch (Exception $e) {
        echo "<p class='status-warning'>⚠️ Could not check sample data: " . htmlspecialchars($e->getMessage()) . "</p>\n";
    }
    
    // Installation readiness check
    echo "<h2>🚀 Installation Readiness</h2>\n";
    
    $checks = [
        'Database Connection' => true,
        'theme_variables table exists' => false,
        'type column exists' => false,
        'Installation script compatible' => false
    ];
    
    try {
        $stmt = $pdo->query("SELECT 1 FROM theme_variables LIMIT 1");
        $checks['theme_variables table exists'] = true;
        
        $stmt = $pdo->query("SELECT type FROM theme_variables LIMIT 1");
        $checks['type column exists'] = true;
        $checks['Installation script compatible'] = true;
        
    } catch (Exception $e) {
        // Tables don't exist yet - that's okay for first install
    }
    
    echo "<table>\n";
    echo "<tr><th>Check</th><th>Status</th></tr>\n";
    foreach ($checks as $check => $status) {
        $statusText = $status ? "<span class='status-ok'>✅ PASS</span>" : "<span class='status-error'>❌ FAIL</span>";
        echo "<tr><td>$check</td><td>$statusText</td></tr>\n";
    }
    echo "</table>\n";
    
    // Recommendations
    echo "<h2>💡 Recommendations</h2>\n";
    
    if (!$checks['theme_variables table exists']) {
        echo "<p>✅ <strong>Ready for installation</strong> - Run the installation script to create tables</p>\n";
        echo "<div class='code'>Visit: /modules/yftheme/install.php</div>\n";
    } elseif ($checks['Installation script compatible']) {
        echo "<p>✅ <strong>Installation compatible</strong> - Theme system should work correctly</p>\n";
        echo "<div class='code'>Visit: /modules/yftheme/www/admin/theme-editor.php</div>\n";
    } else {
        echo "<p>❌ <strong>Schema mismatch detected</strong> - Manual intervention required</p>\n";
        echo "<p>The database schema doesn't match the installation script expectations.</p>\n";
    }
    
} catch (Exception $e) {
    echo "<h2>❌ Database Connection Error</h2>\n";
    echo "<p class='status-error'>Error: " . htmlspecialchars($e->getMessage()) . "</p>\n";
}

echo "<hr>\n";
echo "<p><strong>Actions:</strong></p>\n";
echo "<p><a href='/modules/yftheme/install.php'>🔧 Run Installation</a> | ";
echo "<a href='/modules/yftheme/www/admin/theme-editor.php'>🎨 Theme Editor</a> | ";
echo "<a href='/admin/system-status.php'>📊 System Status</a></p>\n";
?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto-refresh every 30 seconds if tables are missing
    const hasMissingTables = document.querySelector('.status-error');
    if (hasMissingTables) {
        setTimeout(() => {
            window.location.reload();
        }, 30000);
    }
});
</script>