<?php
// Minimal test to isolate the issue
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Step 1: Basic PHP working...<br>";

echo "Step 2: Testing session...<br>";
session_start();

echo "Step 3: Testing auth...<br>";
if (!isset($_GET['admin_bypass']) || $_GET['admin_bypass'] !== 'YakFind2025') {
    echo "❌ Need bypass parameter<br>";
    exit;
}

echo "Step 4: Testing file paths...<br>";
$configPath = dirname(__DIR__, 4) . '/config/database.php';
echo "Config path: $configPath<br>";
if (!file_exists($configPath)) {
    echo "❌ Config file missing<br>";
    exit;
}

echo "Step 5: Loading config...<br>";
try {
    require_once $configPath;
    echo "✓ Config loaded<br>";
} catch (Exception $e) {
    echo "❌ Config error: " . $e->getMessage() . "<br>";
    exit;
}

echo "Step 6: Testing database connection...<br>";
try {
    $stmt = $pdo->query("SELECT 1");
    echo "✓ Database connected<br>";
} catch (Exception $e) {
    echo "❌ Database error: " . $e->getMessage() . "<br>";
    exit;
}

echo "Step 7: Testing autoloader...<br>";
$autoloadPath = dirname(__DIR__, 4) . '/vendor/autoload.php';
echo "Autoload path: $autoloadPath<br>";
if (!file_exists($autoloadPath)) {
    echo "❌ Autoload file missing<br>";
    exit;
}

try {
    require_once $autoloadPath;
    echo "✓ Autoloader loaded<br>";
} catch (Exception $e) {
    echo "❌ Autoloader error: " . $e->getMessage() . "<br>";
    exit;
}

echo "Step 8: Testing class loading...<br>";
try {
    if (!class_exists('YFEvents\\Modules\\YFClaim\\Models\\SellerModel')) {
        echo "❌ SellerModel class not found<br>";
        exit;
    }
    echo "✓ SellerModel class exists<br>";
} catch (Exception $e) {
    echo "❌ Class loading error: " . $e->getMessage() . "<br>";
    exit;
}

echo "<br><strong>All basic tests passed!</strong><br>";
echo "<a href='sellers.php?admin_bypass=YakFind2025'>Try Sellers</a> | ";
echo "<a href='sales.php?admin_bypass=YakFind2025'>Try Sales</a> | ";
echo "<a href='buyers.php?admin_bypass=YakFind2025'>Try Buyers</a><br>";
?>