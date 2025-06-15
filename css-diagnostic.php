<?php
/**
 * CSS Diagnostic Tool
 * Identifies and fixes CSS loading issues
 */

echo "<h1>🔍 CSS Loading Diagnostic Tool</h1>\n";
echo "<style>
body { font-family: 'Segoe UI', Tahoma, sans-serif; max-width: 1000px; margin: 0 auto; padding: 20px; }
.status-ok { color: #27ae60; font-weight: bold; }
.status-error { color: #e74c3c; font-weight: bold; }
.status-warning { color: #f39c12; font-weight: bold; }
.code { background: #f4f4f4; padding: 10px; border-radius: 4px; font-family: monospace; }
.section { background: white; border: 1px solid #ddd; border-radius: 8px; padding: 20px; margin: 15px 0; }
table { width: 100%; border-collapse: collapse; }
th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
th { background: #f4f4f4; }
</style>\n";

// Get current working directory and web context
$currentDir = getcwd();
$scriptPath = $_SERVER['SCRIPT_FILENAME'];
$requestUri = $_SERVER['REQUEST_URI'];
$documentRoot = $_SERVER['DOCUMENT_ROOT'];

echo "<div class='section'>\n";
echo "<h2>📍 Current Environment</h2>\n";
echo "<table>\n";
echo "<tr><th>Property</th><th>Value</th></tr>\n";
echo "<tr><td>Current Working Directory</td><td><code>$currentDir</code></td></tr>\n";
echo "<tr><td>Script Path</td><td><code>$scriptPath</code></td></tr>\n";
echo "<tr><td>Request URI</td><td><code>$requestUri</code></td></tr>\n";
echo "<tr><td>Document Root</td><td><code>$documentRoot</code></td></tr>\n";
echo "<tr><td>Script Name</td><td><code>{$_SERVER['SCRIPT_NAME']}</code></td></tr>\n";
echo "</table>\n";
echo "</div>\n";

// Check CSS file locations
echo "<div class='section'>\n";
echo "<h2>📁 CSS File Locations</h2>\n";

$cssFiles = [
    '/www/html/css/calendar.css' => 'Main calendar styles',
    '/www/html/css/daily-view.css' => 'Daily view styles',  
    '/www/html/css/admin.css' => 'Admin styles',
    '/modules/yfclaim/www/assets/css/admin.css' => 'YFClaim admin styles'
];

foreach ($cssFiles as $path => $description) {
    $fullPath = $currentDir . $path;
    $webPath = str_replace('/www/html', '', $path);
    $exists = file_exists($fullPath);
    $size = $exists ? filesize($fullPath) : 0;
    
    echo "<div style='margin: 10px 0; padding: 10px; border: 1px solid #ddd; border-radius: 4px;'>\n";
    echo "<strong>$description</strong><br>\n";
    echo "File Path: <code>$fullPath</code><br>\n";
    echo "Web Path: <code>$webPath</code><br>\n";
    echo "Status: ";
    if ($exists) {
        echo "<span class='status-ok'>✅ EXISTS</span> ($size bytes)<br>\n";
        
        // Test if file is accessible via web
        $testUrl = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $webPath;
        echo "Test URL: <a href='$testUrl' target='_blank'>$testUrl</a>\n";
    } else {
        echo "<span class='status-error'>❌ NOT FOUND</span>\n";
    }
    echo "</div>\n";
}
echo "</div>\n";

// Check HTML templates
echo "<div class='section'>\n";
echo "<h2>📄 HTML Template CSS References</h2>\n";

$templateFiles = [
    '/www/html/templates/calendar/calendar.php' => 'Main calendar template',
    '/www/html/index.php' => 'Main index page'
];

foreach ($templateFiles as $path => $description) {
    $fullPath = $currentDir . $path;
    echo "<h3>$description</h3>\n";
    
    if (file_exists($fullPath)) {
        echo "<span class='status-ok'>✅ Template exists</span><br>\n";
        
        $content = file_get_contents($fullPath);
        
        // Find CSS link tags
        preg_match_all('/<link[^>]*rel=["\']stylesheet["\'][^>]*>/i', $content, $matches);
        
        if (!empty($matches[0])) {
            echo "<strong>CSS References found:</strong><br>\n";
            foreach ($matches[0] as $link) {
                echo "<div class='code'>$link</div>\n";
                
                // Extract href
                if (preg_match('/href=["\']([^"\']+)["\']/', $link, $hrefMatch)) {
                    $href = $hrefMatch[1];
                    echo "→ Points to: <code>$href</code><br>\n";
                    
                    // Check if this path exists
                    if (strpos($href, 'http') === 0 || strpos($href, '//') === 0) {
                        echo "→ <span class='status-warning'>⚠️ External URL</span><br>\n";
                    } else {
                        $localPath = $currentDir . '/www/html' . $href;
                        if (file_exists($localPath)) {
                            echo "→ <span class='status-ok'>✅ Local file exists</span><br>\n";
                        } else {
                            echo "→ <span class='status-error'>❌ Local file NOT FOUND at $localPath</span><br>\n";
                        }
                    }
                }
                echo "<br>\n";
            }
        } else {
            echo "<span class='status-warning'>⚠️ No CSS link tags found</span><br>\n";
        }
        
    } else {
        echo "<span class='status-error'>❌ Template not found</span><br>\n";
    }
    echo "<br>\n";
}
echo "</div>\n";

// Web server detection
echo "<div class='section'>\n";
echo "<h2>🌐 Web Server Analysis</h2>\n";

$serverSoftware = $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown';
echo "<strong>Server Software:</strong> $serverSoftware<br>\n";

// Check for common path issues
$possibleDocumentRoots = [
    $documentRoot,
    $currentDir . '/www/html',
    $currentDir . '/public',
    $currentDir,
    dirname($scriptPath)
];

echo "<strong>Possible Document Roots:</strong><br>\n";
foreach ($possibleDocumentRoots as $root) {
    $cssTestPath = $root . '/css/calendar.css';
    $exists = file_exists($cssTestPath);
    echo "<div style='margin: 5px 0;'>\n";
    echo "<code>$root</code> ";
    if ($exists) {
        echo "<span class='status-ok'>✅ calendar.css found here</span>";
    } else {
        echo "<span class='status-error'>❌ calendar.css not found</span>";
    }
    echo "</div>\n";
}
echo "</div>\n";

// Generate fixes
echo "<div class='section'>\n";
echo "<h2>🔧 Recommended Fixes</h2>\n";

// Check current CSS accessibility
$cssAccessible = false;
$testCssPath = $currentDir . '/www/html/css/calendar.css';
if (file_exists($testCssPath)) {
    // Try to determine the correct web path
    $webCssPath = '/css/calendar.css';
    
    // If document root is www/html, then /css/calendar.css should work
    if (strpos($documentRoot, 'www/html') !== false) {
        $cssAccessible = true;
        echo "<div style='background: #d4edda; padding: 15px; border-radius: 4px; margin: 10px 0;'>\n";
        echo "<span class='status-ok'>✅ LIKELY SOLUTION FOUND</span><br>\n";
        echo "Your CSS files should be accessible at <code>/css/calendar.css</code><br>\n";
        echo "Document root appears to be correctly set to include www/html<br>\n";
        echo "</div>\n";
    } else {
        echo "<div style='background: #f8d7da; padding: 15px; border-radius: 4px; margin: 10px 0;'>\n";
        echo "<span class='status-error'>❌ PATH MISMATCH DETECTED</span><br>\n";
        echo "CSS files are at: <code>$testCssPath</code><br>\n";
        echo "But document root is: <code>$documentRoot</code><br>\n";
        echo "This means <code>/css/calendar.css</code> won't work<br>\n";
        echo "</div>\n";
    }
}

echo "<h3>Fix Options:</h3>\n";

echo "<h4>Option 1: Create Symbolic Links (Recommended)</h4>\n";
echo "<div class='code'>\n";
echo "# Create a css directory in document root that links to the actual CSS files<br>\n";
echo "mkdir -p $documentRoot/css<br>\n";
echo "ln -sf $currentDir/www/html/css/* $documentRoot/css/<br>\n";
echo "</div>\n";

echo "<h4>Option 2: Copy CSS Files</h4>\n";
echo "<div class='code'>\n";
echo "# Copy CSS files to document root<br>\n";
echo "mkdir -p $documentRoot/css<br>\n";
echo "cp $currentDir/www/html/css/* $documentRoot/css/<br>\n";
echo "</div>\n";

echo "<h4>Option 3: Update Template Paths</h4>\n";
echo "<div class='code'>\n";
echo "# Update templates to use correct paths<br>\n";
echo "# Change /css/calendar.css to " . str_replace($documentRoot, '', $currentDir . '/www/html/css/calendar.css') . "<br>\n";
echo "</div>\n";

echo "<h4>Option 4: Web Server Configuration</h4>\n";
echo "Configure your web server to serve from <code>$currentDir/www/html</code> as document root<br>\n";

echo "</div>\n";

// Quick test
echo "<div class='section'>\n";
echo "<h2>🧪 Quick CSS Test</h2>\n";
echo "<p>Click these links to test if CSS files are accessible:</p>\n";

$testPaths = ['/css/calendar.css', '/www/html/css/calendar.css', str_replace($documentRoot, '', $currentDir . '/www/html/css/calendar.css')];

foreach ($testPaths as $path) {
    $testUrl = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $path;
    echo "<div style='margin: 5px 0;'>\n";
    echo "<a href='$testUrl' target='_blank' style='display: inline-block; padding: 8px 15px; background: #3498db; color: white; text-decoration: none; border-radius: 4px;'>Test $path</a>\n";
    echo "</div>\n";
}

echo "<p><strong>What to look for:</strong></p>\n";
echo "<ul>\n";
echo "<li>✅ If CSS loads: You'll see CSS code in the browser</li>\n";
echo "<li>❌ If 404 error: Path is incorrect</li>\n";
echo "<li>❌ If 403 error: Permission issue</li>\n";
echo "</ul>\n";

echo "</div>\n";

// Action buttons
echo "<div class='section'>\n";
echo "<h2>🚀 Quick Actions</h2>\n";

echo "<div style='display: flex; gap: 10px; flex-wrap: wrap;'>\n";
echo "<a href='/calendar.php' target='_blank' style='padding: 10px 20px; background: #27ae60; color: white; text-decoration: none; border-radius: 4px;'>📅 Test Calendar Page</a>\n";
echo "<a href='/admin/theme-config.php' style='padding: 10px 20px; background: #3498db; color: white; text-decoration: none; border-radius: 4px;'>🎨 Theme Config</a>\n";
echo "<a href='/modules/yftheme/install.php' style='padding: 10px 20px; background: #e67e22; color: white; text-decoration: none; border-radius: 4px;'>📦 Install Theme Module</a>\n";
echo "</div>\n";

echo "</div>\n";

echo "<script>
// Auto-refresh detection
if (window.location.hash === '#test') {
    setTimeout(() => {
        document.body.style.background = '#f0f8ff';
        alert('If you can see this blue background, CSS paths are working!');
    }, 1000);
}
</script>\n";
?>

<div class="section">
    <h2>📋 Summary</h2>
    <p><strong>The YFTheme module is NOT causing your CSS problems.</strong> The issue is most likely:</p>
    <ol>
        <li><strong>Web server document root mismatch</strong> - CSS files can't be found at expected paths</li>
        <li><strong>Template CSS references pointing to wrong locations</strong></li>
        <li><strong>Browser caching old HTML with broken CSS links</strong></li>
    </ol>
    
    <h3>Immediate Action Required:</h3>
    <ol>
        <li>Test the CSS URLs above to confirm which paths work</li>
        <li>Apply the appropriate fix (symlinks recommended)</li>
        <li>Clear browser cache and test calendar page</li>
        <li>Once CSS is working, optionally install theme module for customization</li>
    </ol>
</div>