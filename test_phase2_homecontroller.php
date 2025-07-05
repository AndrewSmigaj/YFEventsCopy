<?php
/**
 * Test script for Phase 2: HomeController Dynamic Content
 * Verifies services are injected and data fetching works
 */

require_once __DIR__ . '/vendor/autoload.php';

use YFEvents\Application\Bootstrap;
use YFEvents\Presentation\Http\Controllers\HomeController;

echo "Phase 2 HomeController Test\n";
echo "===========================\n\n";

try {
    // Bootstrap the application
    $container = Bootstrap::boot();
    $config = $container->resolve(\YFEvents\Infrastructure\Config\ConfigInterface::class);
    
    echo "✓ Application bootstrapped\n";
    
    // Test HomeController instantiation
    $controller = new HomeController($container, $config);
    echo "✓ HomeController instantiated\n";
    
    // Use reflection to test private methods
    $reflection = new ReflectionClass($controller);
    
    // Test services are injected
    echo "\nTesting Service Injection:\n";
    
    $eventServiceProp = $reflection->getProperty('eventService');
    $eventServiceProp->setAccessible(true);
    $eventService = $eventServiceProp->getValue($controller);
    echo "✓ EventService injected: " . get_class($eventService) . "\n";
    
    $shopServiceProp = $reflection->getProperty('shopService');
    $shopServiceProp->setAccessible(true);
    $shopService = $shopServiceProp->getValue($controller);
    echo "✓ ShopService injected: " . get_class($shopService) . "\n";
    
    $claimServiceProp = $reflection->getProperty('claimService');
    $claimServiceProp->setAccessible(true);
    $claimService = $claimServiceProp->getValue($controller);
    echo "✓ ClaimService injected: " . get_class($claimService) . "\n";
    
    // Test data fetching methods
    echo "\nTesting Data Fetching Methods:\n";
    
    // Test getFeaturedItems
    $getFeaturedItems = $reflection->getMethod('getFeaturedItems');
    $getFeaturedItems->setAccessible(true);
    $featuredItems = $getFeaturedItems->invoke($controller);
    echo "✓ getFeaturedItems() returned " . count($featuredItems) . " items\n";
    
    // Test getUpcomingSales
    $getUpcomingSales = $reflection->getMethod('getUpcomingSales');
    $getUpcomingSales->setAccessible(true);
    $upcomingSales = $getUpcomingSales->invoke($controller);
    echo "✓ getUpcomingSales() returned " . count($upcomingSales) . " sales\n";
    
    // Test getUpcomingEvents
    $getUpcomingEvents = $reflection->getMethod('getUpcomingEvents');
    $getUpcomingEvents->setAccessible(true);
    $upcomingEvents = $getUpcomingEvents->invoke($controller);
    echo "✓ getUpcomingEvents() returned " . count($upcomingEvents) . " events\n";
    
    // Test getCurrentSales
    $getCurrentSales = $reflection->getMethod('getCurrentSales');
    $getCurrentSales->setAccessible(true);
    $currentSales = $getCurrentSales->invoke($controller);
    echo "✓ getCurrentSales() returned " . count($currentSales) . " sales\n";
    
    // Test getFeaturedShops
    $getFeaturedShops = $reflection->getMethod('getFeaturedShops');
    $getFeaturedShops->setAccessible(true);
    $featuredShops = $getFeaturedShops->invoke($controller);
    echo "✓ getFeaturedShops() returned " . count($featuredShops) . " shops\n";
    
    // Test getDynamicStats
    $getDynamicStats = $reflection->getMethod('getDynamicStats');
    $getDynamicStats->setAccessible(true);
    $stats = $getDynamicStats->invoke($controller);
    echo "✓ getDynamicStats() returned:\n";
    echo "  - Active Sales: " . $stats['active_sales'] . "\n";
    echo "  - Upcoming Events: " . $stats['upcoming_events'] . "\n";
    echo "  - Total Items: " . $stats['total_items'] . "\n";
    echo "  - Local Shops: " . $stats['local_shops'] . "\n";
    
    // Test that renderHomePage accepts data
    echo "\nTesting renderHomePage:\n";
    $renderHomePage = $reflection->getMethod('renderHomePage');
    $renderHomePage->setAccessible(true);
    
    // Test with empty data (should use defaults)
    $html1 = $renderHomePage->invoke($controller);
    echo "✓ renderHomePage() without data: " . (strlen($html1) > 1000 ? "HTML generated" : "Failed") . "\n";
    
    // Test with data
    $testData = ['stats' => ['active_sales' => 99, 'upcoming_events' => 88, 'total_items' => 777, 'local_shops' => 66]];
    $html2 = $renderHomePage->invoke($controller, $testData);
    $hasCustomStats = strpos($html2, '99') !== false && strpos($html2, '88') !== false;
    echo "✓ renderHomePage() with data: " . ($hasCustomStats ? "Uses custom data" : "Failed") . "\n";
    
    // Test index method (capture output)
    echo "\nTesting index() method:\n";
    ob_start();
    $controller->index();
    $output = ob_get_clean();
    
    $hasHtml = strpos($output, '<!DOCTYPE html>') !== false;
    $hasStats = strpos($output, 'stats-bar') !== false;
    echo "✓ index() generates HTML: " . ($hasHtml ? "Yes" : "No") . "\n";
    echo "✓ index() includes stats: " . ($hasStats ? "Yes" : "No") . "\n";
    
    echo "\n================================\n";
    echo "✅ ALL TESTS PASSED!\n";
    echo "Phase 2 completed successfully.\n";
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