<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

echo "<h1>Debug Sellers Page</h1>";

try {
    echo "Step 1: Starting session...<br>";
    session_start();
    echo "✓ Session started<br>";

    echo "Step 2: Checking auth bypass...<br>";
    $isLoggedIn = (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) ||
                  (isset($_GET['admin_bypass']) && $_GET['admin_bypass'] === 'YakFind2025');
    
    if (!$isLoggedIn) {
        echo "❌ Not logged in - add ?admin_bypass=YakFind2025<br>";
        exit;
    }
    echo "✓ Auth check passed<br>";

    echo "Step 3: Loading database config...<br>";
    require_once dirname(__DIR__, 4) . '/config/database.php';
    echo "✓ Database config loaded<br>";

    echo "Step 4: Loading autoloader...<br>";
    require_once dirname(__DIR__, 4) . '/vendor/autoload.php';
    echo "✓ Autoloader loaded<br>";

    echo "Step 5: Importing model classes...<br>";
    use YFEvents\Modules\YFClaim\Models\SellerModel;
    use YFEvents\Modules\YFClaim\Models\SaleModel;
    echo "✓ Model classes imported<br>";

    echo "Step 6: Creating model instances...<br>";
    $sellerModel = new SellerModel($pdo);
    $saleModel = new SaleModel($pdo);
    echo "✓ Models created<br>";

    echo "Step 7: Testing basic seller query...<br>";
    $sellers = $sellerModel->getAllSellers(5, 0);
    echo "✓ Query successful. Found " . count($sellers) . " sellers<br>";

    echo "Step 8: Testing seller stats...<br>";
    if (!empty($sellers)) {
        $firstSeller = $sellers[0];
        echo "Testing stats for seller ID: " . $firstSeller['id'] . "<br>";
        $stats = $sellerModel->getStats($firstSeller['id']);
        echo "✓ Stats query successful<br>";
        echo "Stats: " . json_encode($stats) . "<br>";
    }

    echo "<br><strong>All tests passed! The sellers functionality should work.</strong><br>";

} catch (Exception $e) {
    echo "<br>❌ Exception: " . htmlspecialchars($e->getMessage()) . "<br>";
    echo "File: " . $e->getFile() . " Line: " . $e->getLine() . "<br>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
} catch (Error $e) {
    echo "<br>❌ Fatal Error: " . htmlspecialchars($e->getMessage()) . "<br>";
    echo "File: " . $e->getFile() . " Line: " . $e->getLine() . "<br>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}
?>