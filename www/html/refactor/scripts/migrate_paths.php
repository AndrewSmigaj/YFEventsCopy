<?php
/**
 * Path Migration Script
 * Updates all hardcoded paths to use dynamic path generation
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/src/Helpers/PathHelper.php';

use YFEvents\Helpers\PathHelper;

$rootPath = dirname(__DIR__);
$updates = 0;
$errors = 0;

echo "🚀 Starting path migration...\n\n";

// Files to update
$filesToUpdate = [
    // Admin navigation - special handling
    'admin/includes/admin-navigation.php' => 'navigation',
    
    // Admin pages
    'admin/dashboard.php' => 'admin',
    'admin/events.php' => 'admin',
    'admin/shops.php' => 'admin',
    'admin/users.php' => 'admin',
    'admin/theme.php' => 'admin',
    'admin/settings.php' => 'admin',
    'admin/scrapers.php' => 'admin',
    'admin/claims.php' => 'admin',
    'admin/email-events.php' => 'admin',
    'admin/email-config.php' => 'admin',
    
    // Auth pages
    'login.php' => 'auth',
    'register.php' => 'auth',
    'admin/auth_check.php' => 'auth',
    
    // Public pages
    'index.php' => 'public',
    'test.php' => 'public',
];

// Process each file
foreach ($filesToUpdate as $file => $type) {
    $filePath = $rootPath . '/' . $file;
    
    if (!file_exists($filePath)) {
        echo "⚠️  File not found: $file\n";
        $errors++;
        continue;
    }
    
    $content = file_get_contents($filePath);
    $originalContent = $content;
    
    // Add PathHelper import if PHP file and not already present
    if (pathinfo($file, PATHINFO_EXTENSION) === 'php' && 
        strpos($content, 'use YFEvents\Helpers\PathHelper;') === false &&
        strpos($content, '<?php') !== false) {
        
        // Add after opening PHP tag
        $content = preg_replace(
            '/(<\?php\s*\n)/',
            "$1require_once __DIR__ . '/../vendor/autoload.php';\nuse YFEvents\Helpers\PathHelper;\n\n",
            $content,
            1
        );
    }
    
    // Replace patterns based on file type
    switch ($type) {
        case 'navigation':
            // Navigation needs special handling - keep absolute paths
            $content = str_replace('/refactor/admin/dashboard', '<?= PathHelper::adminUrl(\'dashboard\') ?>', $content);
            $content = str_replace('/refactor/admin/events.php', '<?= PathHelper::adminUrl(\'events.php\') ?>', $content);
            $content = str_replace('/refactor/admin/shops.php', '<?= PathHelper::adminUrl(\'shops.php\') ?>', $content);
            $content = str_replace('/refactor/admin/users.php', '<?= PathHelper::adminUrl(\'users.php\') ?>', $content);
            $content = str_replace('/refactor/admin/settings.php', '<?= PathHelper::adminUrl(\'settings.php\') ?>', $content);
            $content = str_replace('/refactor/admin/theme.php', '<?= PathHelper::adminUrl(\'theme.php\') ?>', $content);
            $content = str_replace('/refactor/admin/scrapers.php', '<?= PathHelper::adminUrl(\'scrapers.php\') ?>', $content);
            $content = str_replace('/refactor/admin/claims.php', '<?= PathHelper::adminUrl(\'claims.php\') ?>', $content);
            $content = str_replace('/refactor/admin/email-events.php', '<?= PathHelper::adminUrl(\'email-events.php\') ?>', $content);
            $content = str_replace('/refactor/admin/email-config.php', '<?= PathHelper::adminUrl(\'email-config.php\') ?>', $content);
            $content = str_replace('/refactor/admin/modules.php', '<?= PathHelper::adminUrl(\'modules.php\') ?>', $content);
            $content = str_replace('/refactor/admin/browser-scrapers.php', '<?= PathHelper::adminUrl(\'browser-scrapers.php\') ?>', $content);
            $content = str_replace('/refactor/', '<?= PathHelper::url() ?>/', $content);
            break;
            
        case 'admin':
            // Update base path assignments
            $content = preg_replace('/\$basePath\s*=\s*[\'"]\/refactor[\'"];/', '$basePath = PathHelper::getBasePath();', $content);
            
            // Update JavaScript base paths
            $content = str_replace("|| '/refactor'", "|| PathHelper::getBasePath()", $content);
            
            // Update links
            $content = str_replace('href="/refactor/admin/', 'href="<?= PathHelper::adminUrl(\'', $content);
            $content = str_replace('href="/refactor/', 'href="<?= PathHelper::url(\'', $content);
            
            // Fix the closing quotes
            $content = preg_replace('/href="<\?= PathHelper::([^>]+)\(\'([^\']+)\'/', 'href="<?= PathHelper::$1(\'$2\') ?>', $content);
            break;
            
        case 'auth':
            // Update redirect paths
            $content = str_replace('Location: /refactor/login.php', 'Location: \' . PathHelper::url(\'login.php\')', $content);
            $content = str_replace('href="/refactor/login.php"', 'href="<?= PathHelper::url(\'login.php\') ?>"', $content);
            $content = str_replace('href="/refactor/register"', 'href="<?= PathHelper::url(\'register\') ?>"', $content);
            $content = str_replace('href="/refactor/"', 'href="<?= PathHelper::url() ?>"', $content);
            break;
            
        case 'public':
            // Update public links
            $content = str_replace("href='/refactor/'", "href='<?= PathHelper::url() ?>'", $content);
            $content = str_replace("href='/refactor/api/", "href='<?= PathHelper::apiUrl('", $content);
            $content = str_replace('href="/refactor/', 'href="<?= PathHelper::url(\'', $content);
            $content = str_replace('href="/refactor/admin/"', 'href="<?= PathHelper::adminUrl() ?>"', $content);
            break;
    }
    
    // Clean up any broken PHP tags
    $content = preg_replace('/<\?= PathHelper::([^>]+)\) \?>\'/', '<?= PathHelper::$1\') ?>', $content);
    
    if ($content !== $originalContent) {
        file_put_contents($filePath, $content);
        echo "✅ Updated: $file\n";
        $updates++;
    } else {
        echo "ℹ️  No changes: $file\n";
    }
}

echo "\n📊 Migration Summary:\n";
echo "- Files updated: $updates\n";
echo "- Errors: $errors\n";

// Create bootstrap file update
echo "\n📝 Creating bootstrap file...\n";

$bootstrapContent = '<?php
/**
 * Bootstrap file for dynamic paths
 * Include this at the top of each entry point
 */

require_once __DIR__ . \'/vendor/autoload.php\';
require_once __DIR__ . \'/src/Helpers/PathHelper.php\';

use YFEvents\Helpers\PathHelper;

// Set global base path for legacy code
$GLOBALS[\'basePath\'] = PathHelper::getBasePath();
define(\'BASE_PATH\', PathHelper::getBasePath());
define(\'ADMIN_PATH\', PathHelper::adminUrl());
define(\'API_PATH\', PathHelper::apiUrl());
';

file_put_contents($rootPath . '/bootstrap_paths.php', $bootstrapContent);
echo "✅ Created bootstrap_paths.php\n";

echo "\n✨ Migration preparation complete!\n";
echo "\n💡 Next steps:\n";
echo "1. Test the application thoroughly\n";
echo "2. Update any remaining hardcoded paths manually\n";
echo "3. Include bootstrap_paths.php in entry points\n";
echo "4. Remove /refactor/ from server configuration\n";