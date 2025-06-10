<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Testing basic admin functionality...<br>";

// Test 1: Session
session_start();
echo "✓ Session started<br>";

// Test 2: Authentication bypass
$isLoggedIn = (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) ||
              (isset($_GET['admin_bypass']) && $_GET['admin_bypass'] === 'YakFind2025');

echo "✓ Auth check: " . ($isLoggedIn ? 'PASSED' : 'FAILED') . "<br>";

if (!$isLoggedIn) {
    echo "❌ Not logged in. Add ?admin_bypass=YakFind2025 to URL<br>";
    exit;
}

echo "✓ Proceeding with admin functionality...<br>";

// Test 3: Database config
try {
    require_once dirname(__DIR__, 4) . '/config/database.php';
    echo "✓ Database config loaded<br>";
} catch (Exception $e) {
    echo "❌ Database config error: " . $e->getMessage() . "<br>";
    exit;
}

// Test 4: Autoloader
try {
    require_once dirname(__DIR__, 4) . '/vendor/autoload.php';
    echo "✓ Autoloader loaded<br>";
} catch (Exception $e) {
    echo "❌ Autoloader error: " . $e->getMessage() . "<br>";
    exit;
}

// Test 5: Basic query
try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM yfc_sellers");
    $result = $stmt->fetch();
    echo "✓ Database query works. Sellers count: " . $result['count'] . "<br>";
} catch (Exception $e) {
    echo "❌ Database query error: " . $e->getMessage() . "<br>";
    exit;
}

echo "<br><strong>All basic tests passed!</strong><br>";
echo "<a href='sellers.php?admin_bypass=YakFind2025'>Try Sellers Page</a><br>";
echo "<a href='sales.php?admin_bypass=YakFind2025'>Try Sales Page</a><br>";
?>