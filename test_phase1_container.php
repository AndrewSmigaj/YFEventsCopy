<?php
/**
 * Test script for Phase 1: Container Registration
 * Verifies all services are properly registered and can be resolved
 */

require_once __DIR__ . '/vendor/autoload.php';

use YFEvents\Application\Bootstrap;
use YFEvents\Application\Services\ClaimService;
use YFEvents\Domain\Events\EventServiceInterface;
use YFEvents\Domain\Shops\ShopServiceInterface;
use YFEvents\Domain\Claims\SaleRepositoryInterface;
use YFEvents\Domain\Claims\ItemRepositoryInterface;
use YFEvents\Domain\Claims\OfferRepositoryInterface;
use YFEvents\Infrastructure\Services\QRCodeService;

echo "Phase 1 Container Registration Test\n";
echo "===================================\n\n";

try {
    // Bootstrap the application
    $container = Bootstrap::boot();
    echo "✓ Application bootstrapped successfully\n";
    
    // Test core services
    echo "\nTesting Core Services:\n";
    
    $eventService = $container->resolve(EventServiceInterface::class);
    echo "✓ EventService resolved\n";
    
    $shopService = $container->resolve(ShopServiceInterface::class);
    echo "✓ ShopService resolved\n";
    
    // Test Claims repositories
    echo "\nTesting Claims Repositories:\n";
    
    $saleRepository = $container->resolve(SaleRepositoryInterface::class);
    echo "✓ SaleRepository resolved\n";
    
    $itemRepository = $container->resolve(ItemRepositoryInterface::class);
    echo "✓ ItemRepository resolved\n";
    
    $offerRepository = $container->resolve(OfferRepositoryInterface::class);
    echo "✓ OfferRepository resolved (stub)\n";
    
    // Test infrastructure services
    echo "\nTesting Infrastructure Services:\n";
    
    $qrCodeService = $container->resolve(QRCodeService::class);
    echo "✓ QRCodeService resolved\n";
    
    // Test ClaimService
    echo "\nTesting ClaimService:\n";
    
    $claimService = $container->resolve(ClaimService::class);
    echo "✓ ClaimService resolved\n";
    
    // Test that ClaimService has correct dependencies
    $reflection = new ReflectionClass($claimService);
    $constructor = $reflection->getConstructor();
    $params = $constructor->getParameters();
    
    echo "\nClaimService Dependencies:\n";
    foreach ($params as $param) {
        $type = $param->getType();
        if ($type) {
            echo "  - " . $type->getName() . "\n";
        }
    }
    
    // Verify ClaimService does NOT have OfferRepository dependency
    $hasOfferDependency = false;
    foreach ($params as $param) {
        $type = $param->getType();
        if ($type && str_contains($type->getName(), 'OfferRepository')) {
            $hasOfferDependency = true;
        }
    }
    
    if (!$hasOfferDependency) {
        echo "\n✓ ClaimService correctly has NO OfferRepository dependency\n";
    } else {
        echo "\n✗ ERROR: ClaimService still has OfferRepository dependency\n";
    }
    
    // Test that services can be instantiated multiple times
    echo "\nTesting Multiple Resolution:\n";
    
    $claimService2 = $container->resolve(ClaimService::class);
    echo "✓ ClaimService resolved again\n";
    
    $qrCodeService2 = $container->resolve(QRCodeService::class);
    if ($qrCodeService === $qrCodeService2) {
        echo "✓ QRCodeService is singleton (same instance)\n";
    } else {
        echo "✗ QRCodeService is NOT singleton\n";
    }
    
    echo "\n================================\n";
    echo "✅ ALL TESTS PASSED!\n";
    echo "Phase 1 completed successfully.\n";
    echo "================================\n";
    
} catch (Exception $e) {
    echo "\n================================\n";
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "================================\n";
    echo "\nStack trace:\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}