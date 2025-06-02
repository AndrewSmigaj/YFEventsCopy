#!/usr/bin/env php
<?php
/**
 * YFEvents Module Installer
 * 
 * Usage: php modules/install.php <module-name>
 */

// CLI only
if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line\n");
}

// Get module name from arguments
if ($argc < 2) {
    echo "Usage: php modules/install.php <module-name>\n";
    echo "Available modules:\n";
    
    $modulesDir = __DIR__;
    $modules = array_diff(scandir($modulesDir), ['.', '..', 'install.php', 'README.md']);
    foreach ($modules as $module) {
        if (is_dir("$modulesDir/$module") && file_exists("$modulesDir/$module/module.json")) {
            echo "  - $module\n";
        }
    }
    exit(1);
}

$moduleName = $argv[1];
$moduleDir = __DIR__ . "/$moduleName";

// Check if module exists
if (!is_dir($moduleDir)) {
    die("Error: Module '$moduleName' not found in modules directory\n");
}

if (!file_exists("$moduleDir/module.json")) {
    die("Error: Module '$moduleName' is missing module.json manifest\n");
}

// Load module manifest
$manifest = json_decode(file_get_contents("$moduleDir/module.json"), true);
if (!$manifest) {
    die("Error: Invalid module.json in '$moduleName'\n");
}

echo "Installing module: {$manifest['name']} v{$manifest['version']}\n";
echo "Description: {$manifest['description']}\n\n";

// Load database configuration
require_once __DIR__ . '/../config/database.php';

try {
    // Check requirements
    echo "Checking requirements...\n";
    
    // PHP version
    if (isset($manifest['requires']['php'])) {
        $requiredPhp = str_replace('>=', '', $manifest['requires']['php']);
        if (version_compare(PHP_VERSION, $requiredPhp, '<')) {
            die("Error: PHP {$manifest['requires']['php']} required, but " . PHP_VERSION . " found\n");
        }
    }
    
    // PHP extensions
    if (isset($manifest['requires']['extensions'])) {
        foreach ($manifest['requires']['extensions'] as $ext) {
            if (!extension_loaded($ext)) {
                die("Error: PHP extension '$ext' is required but not installed\n");
            }
        }
    }
    
    echo "✓ Requirements met\n\n";
    
    // Create modules table if it doesn't exist
    echo "Setting up module registry...\n";
    $db->exec("
        CREATE TABLE IF NOT EXISTS modules (
            id INT PRIMARY KEY AUTO_INCREMENT,
            name VARCHAR(50) UNIQUE NOT NULL,
            version VARCHAR(20) NOT NULL,
            status ENUM('active', 'inactive', 'error') DEFAULT 'active',
            installed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            config JSON
        )
    ");
    
    // Check if module is already installed
    $stmt = $db->prepare("SELECT * FROM modules WHERE name = ?");
    $stmt->execute([$manifest['name']]);
    if ($stmt->fetch()) {
        die("Error: Module '{$manifest['name']}' is already installed\n");
    }
    
    // Run database migrations
    if (is_dir("$moduleDir/database")) {
        echo "Running database migrations...\n";
        $sqlFiles = glob("$moduleDir/database/*.sql");
        foreach ($sqlFiles as $sqlFile) {
            echo "  - " . basename($sqlFile) . "\n";
            $sql = file_get_contents($sqlFile);
            
            // Split by semicolon but not within strings
            $statements = preg_split('/;(?=(?:[^\'"]|\'[^\']*\'|"[^"]*")*$)/', $sql);
            
            foreach ($statements as $statement) {
                $statement = trim($statement);
                if (!empty($statement)) {
                    $db->exec($statement);
                }
            }
        }
        echo "✓ Database migrations complete\n\n";
    }
    
    // Copy public files
    echo "Installing public files...\n";
    
    // Admin files
    if (is_dir("$moduleDir/www/admin")) {
        copyDirectory("$moduleDir/www/admin", __DIR__ . "/../www/html/admin/modules/$moduleName");
        echo "  ✓ Admin interface installed\n";
    }
    
    // API files
    if (is_dir("$moduleDir/www/api")) {
        copyDirectory("$moduleDir/www/api", __DIR__ . "/../www/html/api/modules/$moduleName");
        echo "  ✓ API endpoints installed\n";
    }
    
    // Assets
    if (is_dir("$moduleDir/www/assets")) {
        copyDirectory("$moduleDir/www/assets", __DIR__ . "/../www/html/modules/$moduleName");
        echo "  ✓ Assets installed\n";
    }
    
    // Templates
    if (is_dir("$moduleDir/www/templates")) {
        copyDirectory("$moduleDir/www/templates", __DIR__ . "/../www/html/templates/modules/$moduleName");
        echo "  ✓ Templates installed\n";
    }
    
    // Run module's install script if exists
    if (file_exists("$moduleDir/install.php")) {
        echo "\nRunning module installation script...\n";
        require "$moduleDir/install.php";
    }
    
    // Register module in database
    $stmt = $db->prepare("INSERT INTO modules (name, version, status, config) VALUES (?, ?, 'active', ?)");
    $stmt->execute([
        $manifest['name'],
        $manifest['version'],
        json_encode($manifest)
    ]);
    
    echo "\n✓ Module '{$manifest['name']}' installed successfully!\n";
    
    // Show post-installation notes
    if (isset($manifest['post_install_notes'])) {
        echo "\nPost-installation notes:\n";
        echo $manifest['post_install_notes'] . "\n";
    }
    
} catch (Exception $e) {
    die("Error during installation: " . $e->getMessage() . "\n");
}

/**
 * Recursively copy directory
 */
function copyDirectory($src, $dst) {
    if (!is_dir($dst)) {
        mkdir($dst, 0755, true);
    }
    
    $files = scandir($src);
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
        
        $srcPath = "$src/$file";
        $dstPath = "$dst/$file";
        
        if (is_dir($srcPath)) {
            copyDirectory($srcPath, $dstPath);
        } else {
            copy($srcPath, $dstPath);
        }
    }
}