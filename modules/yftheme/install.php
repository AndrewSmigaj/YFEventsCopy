<?php
/**
 * YFTheme Module Installation Script
 * This script properly installs the theme system without breaking existing CSS
 */

require_once __DIR__ . '/../../config/database.php';

// Enable error reporting for installation
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>YFTheme Module Installation</h1>\n";

try {
    // Start transaction for safe installation
    $pdo->beginTransaction();
    
    // Step 1: Create database tables
    echo "<h2>Step 1: Creating database tables...</h2>\n";
    
    $schemaFile = __DIR__ . '/database/schema.sql';
    if (!file_exists($schemaFile)) {
        throw new Exception("Schema file not found: $schemaFile");
    }
    
    $sql = file_get_contents($schemaFile);
    
    // Execute schema in chunks to handle foreign key constraints properly
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    
    foreach ($statements as $statement) {
        if (!empty($statement)) {
            try {
                $pdo->exec($statement);
            } catch (Exception $e) {
                echo "⚠️ Warning executing statement: " . htmlspecialchars($e->getMessage()) . "<br>\n";
                echo "Statement: " . htmlspecialchars(substr($statement, 0, 100)) . "...<br>\n";
                // Continue with other statements unless it's a critical error
                if (strpos($e->getMessage(), 'syntax error') !== false) {
                    throw $e; // Re-throw syntax errors
                }
            }
        }
    }
    
    echo "✅ Database tables created successfully<br>\n";
    
    // Step 2: Insert default theme variables based on existing CSS
    echo "<h2>Step 2: Importing existing CSS variables...</h2>\n";
    
    // Read the existing calendar.css to extract CSS variables
    $calendarCssFile = __DIR__ . '/../../www/html/css/calendar.css';
    if (file_exists($calendarCssFile)) {
        $cssContent = file_get_contents($calendarCssFile);
        
        // Extract CSS variables from :root section
        if (preg_match('/:root\s*{([^}]+)}/s', $cssContent, $matches)) {
            $rootVariables = $matches[1];
            
            // Parse individual variables
            preg_match_all('/--([^:]+):\s*([^;]+);/m', $rootVariables, $variableMatches, PREG_SET_ORDER);
            
            foreach ($variableMatches as $match) {
                $varName = trim($match[1]);
                $varValue = trim($match[2]);
                
                // Determine variable type based on schema ENUM values
                $varType = 'color';  // Default to color since most CSS vars are colors
                $category = 'Colors';
                $displayName = ucwords(str_replace('-', ' ', $varName));
                
                if (strpos($varName, 'color') !== false) {
                    $varType = 'color';
                    $category = 'Colors';
                } elseif (strpos($varName, 'radius') !== false || strpos($varName, 'size') !== false) {
                    $varType = 'size';
                    $category = 'Layout';
                    $displayName .= ' (CSS value)';
                } elseif (strpos($varName, 'shadow') !== false) {
                    $varType = 'shadow';
                    $category = 'Effects';
                    $displayName .= ' (CSS value)';
                } elseif (strpos($varName, 'transition') !== false) {
                    $varType = 'number';
                    $category = 'Effects';
                    $displayName .= ' (CSS value)';
                } elseif (strpos($varName, 'spacing') !== false || strpos($varName, 'padding') !== false || strpos($varName, 'margin') !== false) {
                    $varType = 'spacing';
                    $category = 'Layout';
                } elseif (strpos($varName, 'font') !== false) {
                    $varType = 'font';
                    $category = 'Typography';
                } elseif (strpos($varName, 'border') !== false) {
                    $varType = 'border';
                    $category = 'Components';
                }
                
                // Get or create category
                $stmt = $pdo->prepare("SELECT id FROM theme_categories WHERE name = ?");
                $stmt->execute([$category]);
                $categoryId = $stmt->fetchColumn();
                
                if (!$categoryId) {
                    $stmt = $pdo->prepare("INSERT INTO theme_categories (name, display_name, description) VALUES (?, ?, ?)");
                    $stmt->execute([$category, $category, "Auto-imported $category settings"]);
                    $categoryId = $pdo->lastInsertId();
                }
                
                // Insert variable
                $stmt = $pdo->prepare("
                    INSERT INTO theme_variables (category_id, name, css_variable, display_name, type, default_value, current_value, description) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE default_value = VALUES(default_value), current_value = VALUES(current_value)
                ");
                $stmt->execute([
                    $categoryId,
                    $varName,
                    '--' . $varName,
                    $displayName,
                    $varType,
                    $varValue,
                    $varValue,
                    "Imported from existing calendar.css"
                ]);
                
                echo "✅ Imported variable: --$varName = $varValue<br>\n";
            }
        }
    }
    
    // Step 3: Create default theme preset
    echo "<h2>Step 3: Creating default theme preset...</h2>\n";
    
    $stmt = $pdo->prepare("
        INSERT INTO theme_presets (name, display_name, description, is_default) 
        VALUES ('Default YFEvents Theme', 'Default YFEvents Theme', 'Original theme based on existing calendar.css', 1)
        ON DUPLICATE KEY UPDATE description = VALUES(description)
    ");
    $stmt->execute();
    $defaultPresetId = $pdo->lastInsertId() ?: $pdo->query("SELECT id FROM theme_presets WHERE name = 'Default YFEvents Theme'")->fetchColumn();
    
    // Link all current variables to the default preset
    $stmt = $pdo->prepare("
        INSERT INTO theme_preset_variables (preset_id, variable_id, value)
        SELECT ?, id, current_value FROM theme_variables
        ON DUPLICATE KEY UPDATE value = VALUES(value)
    ");
    $stmt->execute([$defaultPresetId]);
    
    echo "✅ Default theme preset created<br>\n";
    
    // Step 4: Create additional presets
    echo "<h2>Step 4: Creating additional theme presets...</h2>\n";
    
    $additionalPresets = [
        [
            'name' => 'Dark Mode',
            'description' => 'Dark theme for evening viewing',
            'variables' => [
                '--primary-color' => '#ffffff',
                '--secondary-color' => '#64b5f6',
                '--light-gray' => '#424242',
                '--medium-gray' => '#757575',
                '--dark-gray' => '#e0e0e0'
            ]
        ],
        [
            'name' => 'High Contrast',
            'description' => 'High contrast theme for accessibility',
            'variables' => [
                '--primary-color' => '#000000',
                '--secondary-color' => '#0066cc',
                '--accent-color' => '#cc0000',
                '--success-color' => '#006600',
                '--warning-color' => '#cc6600'
            ]
        ],
        [
            'name' => 'Minimal',
            'description' => 'Clean, minimal theme with subtle colors',
            'variables' => [
                '--primary-color' => '#333333',
                '--secondary-color' => '#6c757d',
                '--light-gray' => '#f8f9fa',
                '--border-radius' => '4px',
                '--shadow' => '0 1px 3px rgba(0,0,0,0.1)'
            ]
        ]
    ];
    
    foreach ($additionalPresets as $presetData) {
        $stmt = $pdo->prepare("
            INSERT INTO theme_presets (name, display_name, description, is_default) 
            VALUES (?, ?, ?, 0)
            ON DUPLICATE KEY UPDATE description = VALUES(description)
        ");
        $stmt->execute([$presetData['name'], $presetData['name'], $presetData['description']]);
        $presetId = $pdo->lastInsertId() ?: $pdo->query("SELECT id FROM theme_presets WHERE name = " . $pdo->quote($presetData['name']))->fetchColumn();
        
        foreach ($presetData['variables'] as $cssVar => $value) {
            $stmt = $pdo->prepare("
                INSERT INTO theme_preset_variables (preset_id, variable_id, value)
                SELECT ?, id, ? FROM theme_variables WHERE css_variable = ?
                ON DUPLICATE KEY UPDATE value = VALUES(value)
            ");
            $stmt->execute([$presetId, $value, $cssVar]);
        }
        
        echo "✅ Created preset: {$presetData['name']}<br>\n";
    }
    
    // Step 5: Create CSS file that preserves existing styles
    echo "<h2>Step 5: Creating theme integration CSS...</h2>\n";
    
    $themeIntegrationCSS = <<<CSS
/* YFTheme Integration CSS */
/* This file loads after the main calendar.css to provide theme customization */

/* Import the current theme variables */
/* This will be dynamically generated by ThemeService */

/* Preserve existing styles while allowing theme customization */
body.theme-enabled {
    /* Theme variables will override the :root variables in calendar.css */
}

/* Theme-specific overrides can be added here */
.theme-dark {
    background-color: #1a1a1a;
    color: #ffffff;
}

.theme-dark .calendar-container {
    background-color: #1a1a1a;
}

.theme-dark .theme-section {
    background-color: #2d2d2d;
    color: #ffffff;
}

/* High contrast theme */
.theme-high-contrast {
    filter: contrast(150%);
}

/* Print styles preservation */
@media print {
    /* Ensure themes don't interfere with printing */
    * {
        background: white !important;
        color: black !important;
    }
}
CSS;
    
    $themeDir = __DIR__ . '/www/css';
    if (!is_dir($themeDir)) {
        mkdir($themeDir, 0755, true);
    }
    
    file_put_contents($themeDir . '/theme-integration.css', $themeIntegrationCSS);
    echo "✅ Theme integration CSS created<br>\n";
    
    // Step 6: Create basic theme CSS file
    echo "<h2>Step 6: Creating basic theme CSS...</h2>\n";
    
    // Create basic theme CSS with current variables
    $stmt = $pdo->query("SELECT css_variable, current_value FROM theme_variables WHERE current_value IS NOT NULL");
    $variables = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    $basicCSS = ":root {\n";
    foreach ($variables as $cssVar => $value) {
        $basicCSS .= "    $cssVar: $value;\n";
    }
    $basicCSS .= "}\n";
    
    file_put_contents($themeDir . '/current-theme.css', $basicCSS);
    echo "✅ Basic theme CSS generated<br>\n";
    
    // Step 7: Create admin menu integration
    echo "<h2>Step 7: Creating admin menu integration...</h2>\n";
    
    $adminIntegrationFile = __DIR__ . '/../../admin/theme-config.php';
    $adminIntegrationContent = <<<PHP
<?php
// Theme Configuration Admin Page
header('Location: /modules/yftheme/www/admin/theme-editor.php');
exit;
?>
PHP;
    
    file_put_contents($adminIntegrationFile, $adminIntegrationContent);
    echo "✅ Admin menu integration created<br>\n";
    
    echo "<h2>✅ Installation Complete!</h2>\n";
    echo "<p><strong>The YFTheme module has been successfully installed.</strong></p>\n";
    
    // Commit the transaction
    $pdo->commit();
    echo "<p><em>All database changes have been committed successfully.</em></p>\n";
    echo "<p><strong>IMPORTANT:</strong> Your existing CSS styling has been preserved. The theme system works as an overlay.</p>\n";
    
    echo "<h3>Next Steps:</h3>\n";
    echo "<ul>\n";
    echo "<li><a href='/modules/yftheme/www/admin/theme-editor.php'>Open Theme Editor</a> - Configure themes</li>\n";
    echo "<li><a href='/calendar.php'>View Calendar</a> - See the themed calendar</li>\n";
    echo "<li><a href='/admin/theme-config.php'>Admin Theme Config</a> - Quick admin access</li>\n";
    echo "</ul>\n";
    
    echo "<h3>How to Enable Themes:</h3>\n";
    echo "<p>Add this line to your calendar template's &lt;head&gt; section to enable theme support:</p>\n";
    echo "<pre>&lt;link rel=\"stylesheet\" href=\"/modules/yftheme/api/theme\"&gt;</pre>\n";
    
    echo "<h3>Theme Files Created:</h3>\n";
    echo "<ul>\n";
    echo "<li>/modules/yftheme/www/css/theme-integration.css</li>\n";
    echo "<li>/modules/yftheme/www/css/current-theme.css</li>\n";
    echo "<li>/admin/theme-config.php (redirects to theme editor)</li>\n";
    echo "</ul>\n";
    
} catch (Exception $e) {
    // Rollback transaction on any error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
        echo "<p><em>Database transaction rolled back due to error.</em></p>\n";
    }
    
    echo "<h2>❌ Installation Failed</h2>\n";
    echo "<p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>\n";
    echo "<p><strong>File:</strong> " . htmlspecialchars($e->getFile()) . "</p>\n";
    echo "<p><strong>Line:</strong> " . htmlspecialchars($e->getLine()) . "</p>\n";
    
    if ($e->getPrevious()) {
        echo "<p><strong>Previous error:</strong> " . htmlspecialchars($e->getPrevious()->getMessage()) . "</p>\n";
    }
    
    echo "<h3>🔧 Troubleshooting:</h3>\n";
    echo "<ul>\n";
    echo "<li><strong>Check database permissions:</strong> Ensure the database user has CREATE, DROP, and ALTER privileges</li>\n";
    echo "<li><strong>Run diagnostic:</strong> <a href='/modules/yftheme/diagnostic.php'>YFTheme Diagnostic Tool</a></li>\n";
    echo "<li><strong>Manual cleanup:</strong> If needed, manually drop theme tables and retry</li>\n";
    echo "<li><strong>Check logs:</strong> Review database error logs for more details</li>\n";
    echo "</ul>\n";
}
?>

<style>
body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    max-width: 800px;
    margin: 0 auto;
    padding: 20px;
    line-height: 1.6;
}

h1, h2, h3 {
    color: #2c3e50;
}

pre {
    background: #f4f4f4;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 10px;
    overflow-x: auto;
}

ul {
    margin-left: 20px;
}

a {
    color: #3498db;
    text-decoration: none;
}

a:hover {
    text-decoration: underline;
}
</style>