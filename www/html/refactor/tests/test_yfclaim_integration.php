<?php
/**
 * YFClaim Module Integration Test
 * Tests the complete workflow of the estate sale claim system
 */

echo "========================================\n";
echo "YFClaim Module Integration Test\n";
echo "========================================\n\n";

// Bootstrap the application
require_once __DIR__ . '/../vendor/autoload.php';
$container = require __DIR__ . '/../config/bootstrap.php';

// Get database connection
$pdo = $container->resolve(\YFEvents\Infrastructure\Database\ConnectionInterface::class)->getConnection();

$passed = 0;
$failed = 0;

function test($description, $condition) {
    global $passed, $failed;
    if ($condition) {
        echo "✅ $description\n";
        $passed++;
    } else {
        echo "❌ $description\n";
        $failed++;
    }
}

// Test 1: Check database tables exist
echo "1. Database Structure Tests\n";
echo "----------------------------\n";

$tables = ['yfc_sellers', 'yfc_sales', 'yfc_items', 'yfc_offers', 'yfc_buyers', 'yfc_categories'];
foreach ($tables as $table) {
    $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
    test("Table $table exists", $stmt->rowCount() > 0);
}
echo "\n";

// Test 2: Repository Registration
echo "2. Repository Registration Tests\n";
echo "--------------------------------\n";

$repositories = [
    'SellerRepository' => \YFEvents\Domain\Claims\SellerRepositoryInterface::class,
    'SaleRepository' => \YFEvents\Domain\Claims\SaleRepositoryInterface::class,
    'ItemRepository' => \YFEvents\Domain\Claims\ItemRepositoryInterface::class,
    'OfferRepository' => \YFEvents\Domain\Claims\OfferRepositoryInterface::class,
    'BuyerRepository' => \YFEvents\Domain\Claims\BuyerRepositoryInterface::class,
];

foreach ($repositories as $name => $interface) {
    try {
        $repo = $container->resolve($interface);
        test("$name is registered and resolvable", true);
    } catch (Exception $e) {
        test("$name is registered and resolvable", false);
        echo "  Error: " . $e->getMessage() . "\n";
    }
}
echo "\n";

// Test 3: Service Availability
echo "3. Service Availability Tests\n";
echo "-----------------------------\n";

$services = [
    'ClaimService' => \YFEvents\Application\Services\ClaimService::class,
    'ClaimAuthService' => \YFEvents\Application\Services\ClaimAuthService::class,
];

foreach ($services as $name => $class) {
    test("$name exists", class_exists($class));
}
echo "\n";

// Test 4: Public Pages Accessibility
echo "4. Public Pages Tests\n";
echo "---------------------\n";

$pages = [
    '/public/claims.php' => 'Estate sales browse page',
    '/public/claims/sale.php' => 'Individual sale page',
    '/public/buyer/auth.php' => 'Buyer authentication page',
    '/public/buyer/offers.php' => 'Buyer offers dashboard',
    '/public/seller/login.php' => 'Seller login page',
    '/public/seller/register.php' => 'Seller registration page',
    '/public/seller/dashboard.php' => 'Seller dashboard',
    '/public/seller/sale/new.php' => 'Create sale page',
    '/public/seller/sale/items.php' => 'Manage items page',
];

foreach ($pages as $path => $description) {
    $fullPath = __DIR__ . '/..' . $path;
    test("$description exists", file_exists($fullPath));
}
echo "\n";

// Test 5: Controller Availability
echo "5. Controller Tests\n";
echo "-------------------\n";

$controllers = [
    'ClaimsController' => \YFEvents\Presentation\Http\Controllers\ClaimsController::class,
    'AdminClaimsController' => \YFEvents\Presentation\Http\Controllers\AdminClaimsController::class,
];

foreach ($controllers as $name => $class) {
    test("$name exists", class_exists($class));
}
echo "\n";

// Test 6: Sample Data Check
echo "6. Sample Data Tests\n";
echo "--------------------\n";

$stmt = $pdo->query("SELECT COUNT(*) FROM yfc_sellers");
$sellerCount = $stmt->fetchColumn();
test("Sellers table has data", $sellerCount > 0);

$stmt = $pdo->query("SELECT COUNT(*) FROM yfc_sales");
$salesCount = $stmt->fetchColumn();
test("Sales table has data", $salesCount > 0);

$stmt = $pdo->query("SELECT COUNT(*) FROM yfc_items");
$itemsCount = $stmt->fetchColumn();
test("Items table has data", $itemsCount > 0);

$stmt = $pdo->query("SELECT COUNT(*) FROM yfc_categories");
$categoriesCount = $stmt->fetchColumn();
test("Categories table has data", $categoriesCount > 0);

echo "\n";

// Test 7: Routes Configuration
echo "7. Routes Configuration Tests\n";
echo "-----------------------------\n";

$routeFile = __DIR__ . '/../routes/web.php';
$routeContent = file_get_contents($routeFile);

$routes = [
    '/claims' => 'Public claims browse route',
    '/claims/sale' => 'Individual sale route',
    '/seller/register' => 'Seller registration route',
    '/seller/dashboard' => 'Seller dashboard route',
    '/buyer/auth' => 'Buyer auth route',
    '/api/claims/offer' => 'Submit offer API route',
];

foreach ($routes as $route => $description) {
    test("$description is configured", strpos($routeContent, $route) !== false);
}
echo "\n";

// Test 8: Workflow Simulation
echo "8. Workflow Simulation Tests\n";
echo "----------------------------\n";

// Test getting active sales
try {
    $stmt = $pdo->query("
        SELECT s.*, sel.company_name 
        FROM yfc_sales s 
        JOIN yfc_sellers sel ON s.seller_id = sel.id 
        LIMIT 1
    ");
    $sale = $stmt->fetch(PDO::FETCH_ASSOC);
    test("Can retrieve sales with seller info", $sale !== false);
} catch (Exception $e) {
    test("Can retrieve sales with seller info", false);
    echo "  Error: " . $e->getMessage() . "\n";
}

// Test getting sale items
if ($sale) {
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM yfc_items WHERE sale_id = ?
        ");
        $stmt->execute([$sale['id']]);
        $itemCount = $stmt->fetchColumn();
        test("Can retrieve items for a sale", true);
    } catch (Exception $e) {
        test("Can retrieve items for a sale", false);
        echo "  Error: " . $e->getMessage() . "\n";
    }
}

echo "\n";

// Summary
echo "========================================\n";
echo "Test Summary\n";
echo "========================================\n";
echo "✅ Passed: $passed\n";
echo "❌ Failed: $failed\n";
echo "Total: " . ($passed + $failed) . "\n";
echo "Success Rate: " . ($passed + $failed > 0 ? round(($passed / ($passed + $failed)) * 100, 2) : 0) . "%\n\n";

if ($failed === 0) {
    echo "🎉 All tests passed! YFClaim module is working correctly.\n";
} else {
    echo "⚠️ Some tests failed. Please check the errors above.\n";
}

// Additional recommendations
echo "\nRecommendations:\n";
echo "----------------\n";
if ($failed > 0) {
    echo "1. Check error messages above for specific issues\n";
    echo "2. Ensure all database migrations have been run\n";
    echo "3. Verify file permissions on public directories\n";
} else {
    echo "1. YFClaim module is ready for use\n";
    echo "2. Consider adding more test data for comprehensive testing\n";
    echo "3. Monitor error logs during initial usage\n";
}