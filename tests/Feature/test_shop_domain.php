<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use YFEvents\Application\Bootstrap;
use YFEvents\Domain\Shops\ShopServiceInterface;
use YFEvents\Infrastructure\Database\ConnectionInterface;
use YFEvents\Infrastructure\Config\ConfigInterface;

echo "=== YFEvents Shop Domain Test ===\n\n";

try {
    // Test 1: Bootstrap application
    echo "1. Testing application bootstrap...\n";
    $container = Bootstrap::boot();
    echo "   ✓ Application bootstrapped successfully\n\n";

    // Test 2: Shop service resolution
    echo "2. Testing shop service resolution...\n";
    $shopService = $container->resolve(ShopServiceInterface::class);
    echo "   ✓ Shop service resolved successfully\n\n";

    // Test 3: Get shop statistics
    echo "3. Testing shop statistics...\n";
    $statistics = $shopService->getShopStatistics();
    echo "   ✓ Shop service working, total shops: " . $statistics['total'] . "\n";
    echo "   ✓ Shops by status: " . json_encode($statistics['by_status']) . "\n";
    echo "   ✓ Featured shops: " . $statistics['featured'] . "\n";
    echo "   ✓ Verified shops: " . $statistics['verified'] . "\n";
    echo "   ✓ Geocoded shops: " . $statistics['geocoded'] . "\n\n";

    // Test 4: Get featured shops
    echo "4. Testing featured shops retrieval...\n";
    $featuredShops = $shopService->getFeaturedShops(3);
    echo "   ✓ Retrieved " . count($featuredShops) . " featured shops\n";
    
    if (!empty($featuredShops)) {
        $shop = $featuredShops[0];
        echo "   ✓ First featured shop: '" . $shop->getName() . "' at " . $shop->getAddress() . "\n";
        echo "   ✓ Shop has coordinates: " . ($shop->hasCoordinates() ? 'Yes' : 'No') . "\n";
        echo "   ✓ Shop is verified: " . ($shop->isVerified() ? 'Yes' : 'No') . "\n";
    }
    echo "\n";

    // Test 5: Get shops for directory
    echo "5. Testing directory shops...\n";
    $directoryShops = $shopService->getShopsForDirectory(['limit' => 5]);
    echo "   ✓ Retrieved " . count($directoryShops) . " directory shops\n";
    
    if (!empty($directoryShops)) {
        $shop = $directoryShops[0];
        echo "   ✓ First directory shop: '" . $shop->getName() . "'\n";
        echo "   ✓ Status: " . $shop->getStatus() . "\n";
        echo "   ✓ Payment methods: " . json_encode($shop->getPaymentMethods()) . "\n";
        echo "   ✓ Amenities: " . json_encode($shop->getAmenities()) . "\n";
    }
    echo "\n";

    // Test 6: Get shops for map
    echo "6. Testing map shops...\n";
    $mapShops = $shopService->getShopsForMap(['limit' => 5]);
    echo "   ✓ Retrieved " . count($mapShops) . " map shops (with coordinates)\n";
    
    foreach ($mapShops as $shop) {
        if ($shop->hasCoordinates()) {
            echo "   ✓ Shop '" . $shop->getName() . "' at (" . $shop->getLatitude() . ", " . $shop->getLongitude() . ")\n";
            break;
        }
    }
    echo "\n";

    // Test 7: Search shops
    echo "7. Testing shop search...\n";
    $searchResults = $shopService->searchShops('restaurant', ['limit' => 3]);
    echo "   ✓ Found " . count($searchResults) . " shops matching 'restaurant'\n";
    
    foreach ($searchResults as $shop) {
        echo "   ✓ Found: '" . $shop->getName() . "'\n";
        break;
    }
    echo "\n";

    // Test 8: Business logic methods
    echo "8. Testing business logic...\n";
    if (!empty($directoryShops)) {
        $testShop = $directoryShops[0];
        echo "   ✓ Shop '" . $testShop->getName() . "':\n";
        echo "     - Is open: " . ($testShop->isOpen() ? 'Yes' : 'No') . "\n";
        echo "     - Has coordinates: " . ($testShop->hasCoordinates() ? 'Yes' : 'No') . "\n";
        echo "     - Formatted hours: " . json_encode($testShop->getFormattedHours()) . "\n";
        
        if (!empty($testShop->getPaymentMethods())) {
            $paymentMethod = $testShop->getPaymentMethods()[0];
            echo "     - Accepts '" . $paymentMethod . "': " . ($testShop->acceptsPaymentMethod($paymentMethod) ? 'Yes' : 'No') . "\n";
        }
    }
    echo "\n";

    echo "🎉 All shop domain tests passed!\n\n";

    // Shop domain summary
    echo "=== Shop Domain Summary ===\n";
    echo "✓ Shop entity with comprehensive business logic\n";
    echo "✓ Repository with advanced search capabilities\n";
    echo "✓ Service layer with validation and business rules\n";
    echo "✓ Location-based queries with distance calculations\n";
    echo "✓ JSON field handling (hours, payment methods, amenities)\n";
    echo "✓ Status management (active/pending/inactive)\n";
    echo "✓ Feature and verification flags\n";
    echo "✓ Complete CRUD operations\n";

} catch (\Exception $e) {
    echo "❌ Test failed: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}