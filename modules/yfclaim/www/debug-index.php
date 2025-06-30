<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Debug YFClaim Index Page</h1>";

try {
    echo "Step 1: Loading database config...<br>";
    require_once __DIR__ . '/../../../config/database.php';
    echo "✓ Database config loaded<br>";

    echo "Step 2: Loading autoloader...<br>";
    require_once __DIR__ . '/../../../vendor/autoload.php';
    echo "✓ Autoloader loaded<br>";

    echo "Step 3: Importing model classes...<br>";
    use YFEvents\Modules\YFClaim\Models\SaleModel;
    use YFEvents\Modules\YFClaim\Models\ItemModel;
    use YFEvents\Modules\YFClaim\Models\SellerModel;
    echo "✓ Model classes imported<br>";

    echo "Step 4: Creating model instances...<br>";
    $saleModel = new SaleModel($pdo);
    $itemModel = new ItemModel($pdo);
    $sellerModel = new SellerModel($pdo);
    echo "✓ Models created<br>";

    echo "Step 5: Testing getCurrent method...<br>";
    $currentSales = $saleModel->getCurrent();
    echo "✓ getCurrent() works - found " . count($currentSales) . " sales<br>";

    echo "Step 6: Testing getUpcoming method...<br>";
    $upcomingSales = $saleModel->getUpcoming();
    echo "✓ getUpcoming() works - found " . count($upcomingSales) . " sales<br>";

    echo "<br><strong>All tests passed! Index page should work.</strong><br>";

} catch (Exception $e) {
    echo "<br>❌ Error: " . htmlspecialchars($e->getMessage()) . "<br>";
    echo "File: " . $e->getFile() . " Line: " . $e->getLine() . "<br>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
} catch (Error $e) {
    echo "<br>❌ Fatal Error: " . htmlspecialchars($e->getMessage()) . "<br>";
    echo "File: " . $e->getFile() . " Line: " . $e->getLine() . "<br>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}
?>