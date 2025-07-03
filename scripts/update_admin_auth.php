<?php
/**
 * Update all admin pages to use new auth system
 */

$adminDir = __DIR__ . '/../www/html/admin/';
$files = glob($adminDir . '*.php');

$skipFiles = ['error_handler.php', 'error_500.php', 'logout.php', 'login_test.php'];

foreach ($files as $file) {
    $filename = basename($file);
    
    // Skip certain files
    if (in_array($filename, $skipFiles)) {
        echo "Skipping: $filename\n";
        continue;
    }
    
    $content = file_get_contents($file);
    
    // Check if it has old auth check
    if (strpos($content, "session_start()") !== false && 
        strpos($content, "\$_SESSION['admin_logged_in']") !== false) {
        
        // Pattern to match old auth check
        $pattern = '/\/\/ Authentication check\s*\n\s*session_start\(\);\s*\n\s*\n\s*if\s*\(!isset\(\$_SESSION\[\'admin_logged_in\'\]\)\s*\|\|\s*\$_SESSION\[\'admin_logged_in\'\]\s*!==\s*true\)\s*{\s*\n\s*header\(\'Location:\s*\/admin\/login\.php\'\);\s*\n\s*exit;\s*\n\s*}/';
        
        // Replace with new auth
        $replacement = "// Authentication check\nrequire_once dirname(__DIR__, 3) . '/includes/admin_auth_required.php';";
        
        $newContent = preg_replace($pattern, $replacement, $content);
        
        if ($newContent !== $content) {
            file_put_contents($file, $newContent);
            echo "Updated: $filename\n";
        } else {
            // Try simpler pattern
            $lines = explode("\n", $content);
            $newLines = [];
            $skipNext = 0;
            $updated = false;
            
            for ($i = 0; $i < count($lines); $i++) {
                if ($skipNext > 0) {
                    $skipNext--;
                    continue;
                }
                
                if (strpos($lines[$i], "// Authentication check") !== false ||
                    strpos($lines[$i], "session_start()") !== false && isset($lines[$i+2]) && strpos($lines[$i+2], "\$_SESSION['admin_logged_in']") !== false) {
                    $newLines[] = "// Authentication check";
                    $newLines[] = "require_once dirname(__DIR__, 3) . '/includes/admin_auth_required.php';";
                    
                    // Skip the old auth block
                    $j = $i + 1;
                    while ($j < count($lines) && trim($lines[$j]) !== '}' && $j < $i + 10) {
                        $j++;
                    }
                    $skipNext = $j - $i;
                    $updated = true;
                } else {
                    $newLines[] = $lines[$i];
                }
            }
            
            if ($updated) {
                file_put_contents($file, implode("\n", $newLines));
                echo "Updated: $filename\n";
            } else {
                echo "No auth found in: $filename\n";
            }
        }
    } else {
        echo "No session auth in: $filename\n";
    }
}