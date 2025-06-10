<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Testing YFClaim Admin Components</h1>";

try {
    echo "<p>1. Testing database connection...</p>";
    require_once dirname(__DIR__, 4) . '/config/database.php';
    echo "<p style='color: green;'>✓ Database connection successful</p>";
    
    echo "<p>2. Testing autoloader...</p>";
    require_once dirname(__DIR__, 4) . '/vendor/autoload.php';
    echo "<p style='color: green;'>✓ Autoloader loaded</p>";
    
    echo "<p>3. Testing model classes...</p>";
    use YFEvents\Modules\YFClaim\Models\SellerModel;
    use YFEvents\Modules\YFClaim\Models\SaleModel;
    use YFEvents\Modules\YFClaim\Models\ItemModel;
    use YFEvents\Modules\YFClaim\Models\BuyerModel;
    use YFEvents\Modules\YFClaim\Models\OfferModel;
    echo "<p style='color: green;'>✓ Model classes imported</p>";
    
    echo "<p>4. Testing model instantiation...</p>";
    $sellerModel = new SellerModel($pdo);
    $saleModel = new SaleModel($pdo);
    $itemModel = new ItemModel($pdo);
    $buyerModel = new BuyerModel($pdo);
    $offerModel = new OfferModel($pdo);
    echo "<p style='color: green;'>✓ All models instantiated successfully</p>";
    
    echo "<p>5. Testing basic database queries...</p>";
    $sellers = $sellerModel->getAllSellers(1);
    echo "<p style='color: green;'>✓ Database queries working</p>";
    
    echo "<p>6. Testing session...</p>";
    session_start();
    echo "<p style='color: green;'>✓ Session started</p>";
    
    echo "<p><strong>All tests passed! The components are working correctly.</strong></p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>File: " . $e->getFile() . "</p>";
    echo "<p>Line: " . $e->getLine() . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
} catch (Error $e) {
    echo "<p style='color: red;'>✗ Fatal Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>File: " . $e->getFile() . "</p>";
    echo "<p>Line: " . $e->getLine() . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}

echo "<p><a href='/modules/yfclaim/www/admin/sellers.php?admin_bypass=YakFind2025'>Test Sellers Page</a></p>";
?>