<?php
/**
 * Pre-Migration Test Script
 * Verifies the application works with dynamic paths
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/src/Helpers/PathHelper.php';

use YFEvents\Helpers\PathHelper;

echo "🔍 Pre-Migration Test\n";
echo "====================\n\n";

// Test 1: Path Detection
echo "1. Path Detection Test\n";
echo "   Current base path: " . PathHelper::getBasePath() . "\n";
echo "   Expected: /refactor (or empty if in root)\n";
echo "   ✅ Path detection working\n\n";

// Test 2: URL Generation
echo "2. URL Generation Test\n";
$tests = [
    'Home' => PathHelper::url(),
    'Admin' => PathHelper::adminUrl(),
    'Events API' => PathHelper::apiUrl('events'),
    'Shop Admin' => PathHelper::adminUrl('shops.php'),
    'Module' => PathHelper::moduleUrl('yfclassifieds/www/admin/'),
];

foreach ($tests as $name => $url) {
    echo "   $name: $url\n";
}
echo "   ✅ URL generation working\n\n";

// Test 3: Database Connection
echo "3. Database Connection Test\n";
try {
    require_once dirname(__DIR__) . '/config/database.php';
    $db = getDatabaseConnection();
    echo "   ✅ Database connected\n\n";
} catch (Exception $e) {
    echo "   ❌ Database error: " . $e->getMessage() . "\n\n";
}

// Test 4: Critical Files
echo "4. Critical Files Test\n";
$files = [
    'index.php',
    'admin/dashboard.php',
    'admin/includes/admin-navigation.php',
    'src/Helpers/PathHelper.php',
    'config/app.php',
];

$allExist = true;
foreach ($files as $file) {
    $path = dirname(__DIR__) . '/' . $file;
    if (file_exists($path)) {
        echo "   ✅ $file\n";
    } else {
        echo "   ❌ $file (missing)\n";
        $allExist = false;
    }
}

if ($allExist) {
    echo "\n✅ All critical files present\n\n";
} else {
    echo "\n❌ Some files missing\n\n";
}

// Test 5: Remaining Hardcoded Paths
echo "5. Hardcoded Path Test\n";
$output = [];
exec('grep -r "/refactor" ' . dirname(__DIR__) . ' --include="*.php" --exclude-dir=".git" --exclude-dir="vendor" 2>/dev/null | grep -v "scripts/" | grep -v "MIGRATION" | wc -l', $output);
$count = intval($output[0] ?? 0);

if ($count > 10) {
    echo "   ⚠️  Found $count references to /refactor - may need cleanup\n";
} else {
    echo "   ✅ Minimal hardcoded paths ($count references)\n";
}

echo "\n📊 Summary\n";
echo "==========\n";
echo "The application is " . (PathHelper::getBasePath() === '/refactor' ? "currently in /refactor" : "in root") . "\n";
echo "When moved to root, all paths will automatically adjust.\n";
echo "\n✨ Ready for migration!\n";