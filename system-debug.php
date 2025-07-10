<?php
/**
 * Comprehensive YFEvents System Debugger
 * Diagnoses current state and provides complete system analysis
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🔍 YFEvents System Debug & Analysis</h1>\n";
echo "<style>
body { font-family: 'Segoe UI', Tahoma, sans-serif; max-width: 1200px; margin: 0 auto; padding: 20px; }
.status-ok { color: #27ae60; font-weight: bold; }
.status-error { color: #e74c3c; font-weight: bold; }
.status-warning { color: #f39c12; font-weight: bold; }
.status-info { color: #3498db; font-weight: bold; }
table { width: 100%; border-collapse: collapse; margin: 15px 0; }
th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
th { background: #f4f4f4; }
.code { background: #f4f4f4; padding: 10px; border-radius: 4px; font-family: monospace; margin: 10px 0; }
.section { background: white; border: 1px solid #ddd; border-radius: 8px; padding: 20px; margin: 15px 0; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
.critical { border-left: 4px solid #e74c3c; background: #fdf2f2; }
.warning { border-left: 4px solid #f39c12; background: #fefbf3; }
.success { border-left: 4px solid #27ae60; background: #f2f9f2; }
.info { border-left: 4px solid #3498db; background: #f2f7fd; }
</style>\n";

$systemStatus = [
    'database' => false,
    'core_files' => false,
    'modules' => [],
    'theme_status' => 'unknown',
    'auth_status' => 'unknown',
    'scraping_status' => 'unknown',
    'errors' => [],
    'recommendations' => []
];

// Database Connection Test
echo "<div class='section info'>\n";
echo "<h2>💾 Database Connection Test</h2>\n";
try {
    require_once __DIR__ . '/config/database.php';
    
    if (isset($pdo) && $pdo instanceof PDO) {
        $stmt = $pdo->query("SELECT VERSION() as version, DATABASE() as database_name");
        $dbInfo = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo "<p class='status-ok'>✅ Database connection successful</p>\n";
        echo "<p><strong>MySQL Version:</strong> {$dbInfo['version']}</p>\n";
        echo "<p><strong>Database:</strong> {$dbInfo['database_name']}</p>\n";
        
        $systemStatus['database'] = true;
    } else {
        throw new Exception("PDO object not created");
    }
} catch (Exception $e) {
    echo "<p class='status-error'>❌ Database connection failed</p>\n";
    echo "<p>Error: " . htmlspecialchars($e->getMessage()) . "</p>\n";
    $systemStatus['errors'][] = "Database connection failed: " . $e->getMessage();
}
echo "</div>\n";

// Core File Structure Check
echo "<div class='section info'>\n";
echo "<h2>📁 Core File Structure</h2>\n";

$coreFiles = [
    'Main Calendar' => '/www/html/calendar.php',
    'Landing Page' => '/www/html/index.php',
    'Admin Panel' => '/admin/index.php',
    'Database Config' => '/config/database.php',
    'CSS Diagnostic' => '/css-diagnostic.php',
    'System Status' => '/admin/system-status.php'
];

echo "<table>\n";
echo "<tr><th>Component</th><th>File Path</th><th>Status</th><th>Size</th></tr>\n";

$coreFileCount = 0;
foreach ($coreFiles as $name => $path) {
    $fullPath = __DIR__ . $path;
    $exists = file_exists($fullPath);
    $size = $exists ? filesize($fullPath) : 0;
    
    echo "<tr>";
    echo "<td>$name</td>";
    echo "<td><code>$path</code></td>";
    echo "<td>" . ($exists ? "<span class='status-ok'>✅ EXISTS</span>" : "<span class='status-error'>❌ MISSING</span>") . "</td>";
    echo "<td>" . ($exists ? number_format($size) . " bytes" : "N/A") . "</td>";
    echo "</tr>\n";
    
    if ($exists) $coreFileCount++;
}
echo "</table>\n";

$systemStatus['core_files'] = $coreFileCount >= 4; // Minimum required files
echo "<p><strong>Core Files Status:</strong> $coreFileCount/" . count($coreFiles) . " found</p>\n";
echo "</div>\n";

// Database Tables Analysis
if ($systemStatus['database']) {
    echo "<div class='section info'>\n";
    echo "<h2>🗃️ Database Tables Analysis</h2>\n";
    
    try {
        // Get all tables
        $stmt = $pdo->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        echo "<h3>Core Tables</h3>\n";
        $coreTables = ['events', 'local_shops', 'calendar_sources', 'event_categories'];
        echo "<table>\n";
        echo "<tr><th>Table</th><th>Status</th><th>Row Count</th><th>Size</th></tr>\n";
        
        foreach ($coreTables as $table) {
            $exists = in_array($table, $tables);
            $rowCount = 0;
            $tableSize = 'N/A';
            
            if ($exists) {
                try {
                    $stmt = $pdo->query("SELECT COUNT(*) FROM `$table`");
                    $rowCount = $stmt->fetchColumn();
                    
                    $stmt = $pdo->query("SELECT ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size_mb FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = '$table'");
                    $size = $stmt->fetchColumn();
                    $tableSize = $size . " MB";
                } catch (Exception $e) {
                    $rowCount = "Error";
                    $tableSize = "Error";
                }
            }
            
            echo "<tr>";
            echo "<td>$table</td>";
            echo "<td>" . ($exists ? "<span class='status-ok'>✅ EXISTS</span>" : "<span class='status-error'>❌ MISSING</span>") . "</td>";
            echo "<td>" . number_format($rowCount) . "</td>";
            echo "<td>$tableSize</td>";
            echo "</tr>\n";
        }
        echo "</table>\n";
        
        // YFTheme Tables Analysis
        echo "<h3>YFTheme Module Tables</h3>\n";
        $themeTables = ['theme_categories', 'theme_variables', 'theme_presets', 'theme_preset_variables', 'theme_history'];
        $themeTablesFound = 0;
        
        echo "<table>\n";
        echo "<tr><th>Table</th><th>Status</th><th>Row Count</th><th>Notes</th></tr>\n";
        
        foreach ($themeTables as $table) {
            $exists = in_array($table, $tables);
            $rowCount = 0;
            $notes = '';
            
            if ($exists) {
                $themeTablesFound++;
                try {
                    $stmt = $pdo->query("SELECT COUNT(*) FROM `$table`");
                    $rowCount = $stmt->fetchColumn();
                    
                    // Special checks
                    if ($table === 'theme_variables' && $rowCount > 0) {
                        $stmt = $pdo->query("SELECT COUNT(DISTINCT type) FROM `$table`");
                        $typeCount = $stmt->fetchColumn();
                        $notes = "$typeCount different types";
                    }
                } catch (Exception $e) {
                    $rowCount = "Error";
                    $notes = $e->getMessage();
                }
            }
            
            echo "<tr>";
            echo "<td>$table</td>";
            echo "<td>" . ($exists ? "<span class='status-ok'>✅ EXISTS</span>" : "<span class='status-error'>❌ MISSING</span>") . "</td>";
            echo "<td>" . number_format($rowCount) . "</td>";
            echo "<td>$notes</td>";
            echo "</tr>\n";
        }
        echo "</table>\n";
        
        // Theme Status Assessment
        if ($themeTablesFound === 0) {
            $systemStatus['theme_status'] = 'not_installed';
            echo "<p class='status-error'>❌ YFTheme module not installed (0/5 tables found)</p>\n";
        } elseif ($themeTablesFound < count($themeTables)) {
            $systemStatus['theme_status'] = 'partial';
            echo "<p class='status-warning'>⚠️ YFTheme module partially installed ($themeTablesFound/5 tables found)</p>\n";
        } else {
            $systemStatus['theme_status'] = 'installed';
            echo "<p class='status-ok'>✅ YFTheme module fully installed (5/5 tables found)</p>\n";
        }
        
        // YFAuth Tables Analysis
        echo "<h3>YFAuth Module Tables</h3>\n";
        $authTables = ['yfa_auth_users', 'auth_users', 'yfa_users', 'auth_roles', 'auth_permissions'];
        $authTablesFound = array_filter($authTables, function($table) use ($tables) {
            return in_array($table, $tables);
        });
        
        if (empty($authTablesFound)) {
            $systemStatus['auth_status'] = 'not_installed';
            echo "<p class='status-warning'>⚠️ No enhanced auth tables found (using basic auth)</p>\n";
        } else {
            $systemStatus['auth_status'] = 'enhanced';
            echo "<p class='status-ok'>✅ Enhanced authentication detected</p>\n";
            foreach ($authTablesFound as $table) {
                try {
                    $stmt = $pdo->query("SELECT COUNT(*) FROM `$table`");
                    $count = $stmt->fetchColumn();
                    echo "<p>• $table: " . number_format($count) . " records</p>\n";
                } catch (Exception $e) {
                    echo "<p>• $table: Error reading</p>\n";
                }
            }
        }
        
    } catch (Exception $e) {
        echo "<p class='status-error'>❌ Error analyzing database: " . htmlspecialchars($e->getMessage()) . "</p>\n";
        $systemStatus['errors'][] = "Database analysis failed: " . $e->getMessage();
    }
    echo "</div>\n";
}

// Module Analysis
echo "<div class='section info'>\n";
echo "<h2>🧩 Module Analysis</h2>\n";

$moduleDir = __DIR__ . '/modules';
if (is_dir($moduleDir)) {
    $modules = array_filter(scandir($moduleDir), function($item) use ($moduleDir) {
        return $item !== '.' && $item !== '..' && is_dir($moduleDir . '/' . $item);
    });
    
    echo "<table>\n";
    echo "<tr><th>Module</th><th>Status</th><th>Version</th><th>Key Files</th><th>Issues</th></tr>\n";
    
    foreach ($modules as $module) {
        $modulePath = $moduleDir . '/' . $module;
        $manifestFile = $modulePath . '/module.json';
        $status = 'Installed';
        $version = '1.0.0';
        $keyFiles = [];
        $issues = [];
        
        // Check manifest
        if (file_exists($manifestFile)) {
            $manifest = json_decode(file_get_contents($manifestFile), true);
            $version = $manifest['version'] ?? '1.0.0';
            $status = 'Configured';
        } else {
            $issues[] = 'No manifest';
        }
        
        // Check key files based on module
        switch ($module) {
            case 'yftheme':
                $checkFiles = [
                    'database/schema.sql',
                    'install.php',
                    'www/admin/theme-editor.php',
                    'src/Services/SimpleThemeService.php'
                ];
                break;
            case 'yfauth':
                $checkFiles = [
                    'database/enhanced_auth_schema.sql',
                    'src/Models/User.php',
                    'src/Services/AuthenticationService.php'
                ];
                break;
            case 'yfclaim':
                $checkFiles = [
                    'database/schema.sql',
                    'www/index.php',
                    'www/admin/index.php'
                ];
                break;
            default:
                $checkFiles = ['module.json'];
        }
        
        foreach ($checkFiles as $file) {
            if (file_exists($modulePath . '/' . $file)) {
                $keyFiles[] = "✅ " . basename($file);
            } else {
                $keyFiles[] = "❌ " . basename($file);
                $issues[] = "Missing " . basename($file);
            }
        }
        
        echo "<tr>";
        echo "<td><strong>$module</strong></td>";
        echo "<td>$status</td>";
        echo "<td>$version</td>";
        echo "<td>" . implode("<br>", $keyFiles) . "</td>";
        echo "<td>" . (empty($issues) ? "<span class='status-ok'>None</span>" : "<span class='status-warning'>" . implode(", ", $issues) . "</span>") . "</td>";
        echo "</tr>\n";
        
        $systemStatus['modules'][] = [
            'name' => $module,
            'status' => $status,
            'issues' => $issues
        ];
    }
    echo "</table>\n";
} else {
    echo "<p class='status-warning'>⚠️ Modules directory not found</p>\n";
}
echo "</div>\n";

// Scraping System Analysis
echo "<div class='section info'>\n";
echo "<h2>🤖 Scraping System Analysis</h2>\n";

$scrapingFiles = [
    'QueueManager' => '/src/Scrapers/Queue/QueueManager.php',
    'ScraperWorker' => '/src/Scrapers/Queue/ScraperWorker.php', 
    'RateLimiter' => '/src/Scrapers/Queue/RateLimiter.php',
    'WorkerManager' => '/src/Scrapers/Queue/WorkerManager.php',
    'ScraperScheduler' => '/src/Scrapers/Queue/ScraperScheduler.php'
];

$scrapingFilesFound = 0;
echo "<table>\n";
echo "<tr><th>Component</th><th>File</th><th>Status</th></tr>\n";

foreach ($scrapingFiles as $name => $path) {
    $fullPath = __DIR__ . $path;
    $exists = file_exists($fullPath);
    if ($exists) $scrapingFilesFound++;
    
    echo "<tr>";
    echo "<td>$name</td>";
    echo "<td><code>$path</code></td>";
    echo "<td>" . ($exists ? "<span class='status-ok'>✅ EXISTS</span>" : "<span class='status-error'>❌ MISSING</span>") . "</td>";
    echo "</tr>\n";
}
echo "</table>\n";

if ($scrapingFilesFound === count($scrapingFiles)) {
    $systemStatus['scraping_status'] = 'optimized';
    echo "<p class='status-ok'>✅ Optimized scraping system fully implemented</p>\n";
} elseif ($scrapingFilesFound > 0) {
    $systemStatus['scraping_status'] = 'partial';
    echo "<p class='status-warning'>⚠️ Scraping system partially implemented ($scrapingFilesFound/" . count($scrapingFiles) . ")</p>\n";
} else {
    $systemStatus['scraping_status'] = 'basic';
    echo "<p class='status-warning'>⚠️ Using basic scraping system</p>\n";
}
echo "</div>\n";

// Error Log Analysis
echo "<div class='section warning'>\n";
echo "<h2>📋 Recent Error Analysis</h2>\n";

$errorSources = [
    'PHP Error Log' => ini_get('error_log'),
    'Apache Error Log' => '/var/log/apache2/error.log',
    'Nginx Error Log' => '/var/log/nginx/error.log',
    'MySQL Error Log' => '/var/log/mysql/error.log'
];

foreach ($errorSources as $source => $path) {
    if ($path && file_exists($path) && is_readable($path)) {
        echo "<h4>$source</h4>\n";
        $lines = array_slice(file($path), -10); // Last 10 lines
        echo "<div class='code'>";
        foreach ($lines as $line) {
            if (stripos($line, 'error') !== false || stripos($line, 'warning') !== false) {
                echo htmlspecialchars($line) . "<br>\n";
            }
        }
        echo "</div>\n";
        break; // Only show first available log
    }
}

echo "<p><em>Note: Only showing recent errors. Check server logs for complete error history.</em></p>\n";
echo "</div>\n";

// System Recommendations
echo "<div class='section success'>\n";
echo "<h2>💡 System Recommendations & Next Steps</h2>\n";

$recommendations = [];

// Theme module recommendations
switch ($systemStatus['theme_status']) {
    case 'not_installed':
        $recommendations[] = [
            'priority' => 'high',
            'title' => 'Install YFTheme Module',
            'description' => 'Run the theme installation to enable visual customization',
            'action' => 'Visit /modules/yftheme/install.php'
        ];
        break;
    case 'partial':
        $recommendations[] = [
            'priority' => 'high', 
            'title' => 'Fix YFTheme Installation',
            'description' => 'Theme installation is incomplete. Clean up and reinstall.',
            'action' => 'Run /modules/yftheme/cleanup.php then reinstall'
        ];
        break;
    case 'installed':
        $recommendations[] = [
            'priority' => 'low',
            'title' => 'Configure Themes',
            'description' => 'YFTheme is installed. Configure visual themes.',
            'action' => 'Visit /modules/yftheme/www/admin/theme-editor.php'
        ];
        break;
}

// Auth system recommendations
if ($systemStatus['auth_status'] === 'not_installed') {
    $recommendations[] = [
        'priority' => 'medium',
        'title' => 'Enhanced Authentication Available',
        'description' => 'Install YFAuth module for RBAC, MFA, and security features',
        'action' => 'Install YFAuth module'
    ];
}

// Scraping system recommendations
if ($systemStatus['scraping_status'] !== 'optimized') {
    $recommendations[] = [
        'priority' => 'medium',
        'title' => 'Optimize Scraping System',
        'description' => 'Enhanced scraping with queues and workers is available',
        'action' => 'Review scraping system implementation'
    ];
}

// Database improvements
$recommendations[] = [
    'priority' => 'medium',
    'title' => 'Apply Database Improvements',
    'description' => 'Security and performance improvements are available',
    'action' => 'Run /database/apply_all_improvements.sql'
];

// CSS diagnostic
if (!file_exists(__DIR__ . '/css-diagnostic.php')) {
    $recommendations[] = [
        'priority' => 'low',
        'title' => 'CSS Diagnostic Missing',
        'description' => 'CSS diagnostic tool not found',
        'action' => 'Reinstall CSS diagnostic tool'
    ];
}

// Sort by priority
usort($recommendations, function($a, $b) {
    $priorities = ['high' => 3, 'medium' => 2, 'low' => 1];
    return $priorities[$b['priority']] - $priorities[$a['priority']];
});

foreach ($recommendations as $rec) {
    $color = $rec['priority'] === 'high' ? 'error' : ($rec['priority'] === 'medium' ? 'warning' : 'info');
    echo "<div class='$color' style='margin: 10px 0; padding: 15px; border-radius: 4px;'>\n";
    echo "<h4>{$rec['title']} <small>({$rec['priority']} priority)</small></h4>\n";
    echo "<p>{$rec['description']}</p>\n";
    echo "<p><strong>Action:</strong> {$rec['action']}</p>\n";
    echo "</div>\n";
}

echo "</div>\n";

// Quick Action Panel
echo "<div class='section info'>\n";
echo "<h2>🚀 Quick Actions</h2>\n";
echo "<div style='display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;'>\n";

$actions = [
    'System Status' => '/admin/system-status.php',
    'YFTheme Diagnostic' => '/modules/yftheme/diagnostic.php',
    'YFTheme Install' => '/modules/yftheme/install.php',
    'YFTheme Cleanup' => '/modules/yftheme/cleanup.php',
    'CSS Diagnostic' => '/css-diagnostic.php',
    'Main Calendar' => '/calendar.php',
    'Admin Panel' => '/admin/',
    'Landing Page' => '/'
];

foreach ($actions as $name => $url) {
    $exists = file_exists(__DIR__ . $url) || file_exists(__DIR__ . '/www/html' . $url);
    $style = $exists ? 'background: #3498db; color: white;' : 'background: #95a5a6; color: white;';
    echo "<a href='$url' style='$style padding: 10px; text-decoration: none; border-radius: 4px; text-align: center; display: block;'>$name</a>\n";
}

echo "</div>\n";
echo "</div>\n";

echo "<div style='margin-top: 40px; text-align: center; color: #7f8c8d;'>\n";
echo "<p>YFEvents System Debug Report | Generated: " . date('Y-m-d H:i:s') . "</p>\n";
echo "<p>Run this tool again after making changes to track progress</p>\n";
echo "</div>\n";
?>

<script>
// Auto-refresh every 5 minutes if there are high-priority issues
document.addEventListener('DOMContentLoaded', function() {
    const highPriorityIssues = document.querySelectorAll('.error');
    if (highPriorityIssues.length > 0) {
        console.log('High priority issues detected. Page will auto-refresh in 5 minutes.');
        setTimeout(() => {
            if (confirm('Auto-refresh to check system status?')) {
                window.location.reload();
            }
        }, 300000);
    }
});
</script>