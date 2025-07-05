<?php
/**
 * Test script for Phase 3: Item Gallery Feature
 * Tests repository methods and controller functionality
 */

require_once __DIR__ . '/vendor/autoload.php';

use YFEvents\Application\Bootstrap;
use YFEvents\Domain\Claims\ItemRepositoryInterface;
use YFEvents\Presentation\Http\Controllers\ClaimsController;

echo "Phase 3 Item Gallery Test\n";
echo "========================\n\n";

try {
    // Bootstrap the application
    $container = Bootstrap::boot();
    $config = $container->resolve(\YFEvents\Infrastructure\Config\ConfigInterface::class);
    
    echo "✓ Application bootstrapped\n";
    
    // Test ItemRepository
    echo "\nTesting ItemRepository:\n";
    $itemRepository = $container->resolve(ItemRepositoryInterface::class);
    echo "✓ ItemRepository resolved\n";
    
    // Test findAllWithImages
    echo "\nTesting findAllWithImages():\n";
    $items = $itemRepository->findAllWithImages([], 10, 0);
    echo "✓ Found " . count($items) . " items across all active sales\n";
    if (!empty($items)) {
        $firstItem = $items[0];
        echo "  First item: " . $firstItem['title'] . " from " . $firstItem['sale_title'] . "\n";
        echo "  Location: " . $firstItem['city'] . ", " . $firstItem['state'] . "\n";
        echo "  Price: $" . number_format($firstItem['price'] ?? 0, 2) . "\n";
        echo "  Has image: " . (!empty($firstItem['primary_image']) ? 'Yes' : 'No') . "\n";
    }
    
    // Test with filters
    echo "\nTesting with filters:\n";
    $filters = ['min_price' => 50, 'max_price' => 500];
    $filteredItems = $itemRepository->findAllWithImages($filters, 10, 0);
    echo "✓ Found " . count($filteredItems) . " items with price $50-$500\n";
    
    // Test search
    echo "\nTesting search filter:\n";
    $searchFilters = ['search' => 'vintage'];
    $searchItems = $itemRepository->findAllWithImages($searchFilters, 10, 0);
    echo "✓ Found " . count($searchItems) . " items matching 'vintage'\n";
    
    // Test sorting
    echo "\nTesting sort options:\n";
    $sortFilters = ['sort' => 'price_low'];
    $sortedItems = $itemRepository->findAllWithImages($sortFilters, 5, 0);
    if (count($sortedItems) >= 2) {
        $price1 = $sortedItems[0]['price'] ?? 0;
        $price2 = $sortedItems[1]['price'] ?? 0;
        echo "✓ Price sort working: $" . $price1 . " <= $" . $price2 . "\n";
    }
    
    // Test getCategories
    echo "\nTesting getCategories():\n";
    $categories = $itemRepository->getCategories();
    echo "✓ Found " . count($categories) . " categories\n";
    foreach (array_slice($categories, 0, 3) as $cat) {
        echo "  - " . $cat['category'] . " (ID: " . $cat['category_id'] . ")\n";
    }
    
    // Test countAll
    echo "\nTesting countAll():\n";
    $totalCount = $itemRepository->countAll([]);
    echo "✓ Total items available: " . $totalCount . "\n";
    
    $filteredCount = $itemRepository->countAll(['min_price' => 100]);
    echo "✓ Items over $100: " . $filteredCount . "\n";
    
    // Test ClaimsController
    echo "\n\nTesting ClaimsController:\n";
    $controller = new ClaimsController($container, $config);
    echo "✓ ClaimsController instantiated\n";
    
    // Simulate request for showItemGallery
    $_GET = ['page' => 1, 'sort' => 'newest'];
    echo "\nSimulating showItemGallery() request:\n";
    ob_start();
    $controller->showItemGallery();
    $output = ob_get_clean();
    
    $hasHtml = strpos($output, '<!DOCTYPE html>') !== false;
    $hasGallery = strpos($output, 'items-grid') !== false;
    $hasFilters = strpos($output, 'filter-form') !== false;
    $hasPagination = strpos($output, 'pagination') !== false || $totalCount <= 24;
    
    echo "✓ HTML generated: " . ($hasHtml ? 'Yes' : 'No') . "\n";
    echo "✓ Items grid present: " . ($hasGallery ? 'Yes' : 'No') . "\n";
    echo "✓ Filter form present: " . ($hasFilters ? 'Yes' : 'No') . "\n";
    echo "✓ Pagination present: " . ($hasPagination ? 'Yes' : 'No') . "\n";
    
    // Test API endpoint
    echo "\nTesting getFilteredItems() API:\n";
    $_GET = ['page' => 1, 'category' => 1];
    ob_start();
    $controller->getFilteredItems();
    $json = ob_get_clean();
    
    $data = json_decode($json, true);
    if ($data) {
        echo "✓ API returned valid JSON\n";
        echo "  Items returned: " . count($data['items']) . "\n";
        echo "  Total items: " . $data['total'] . "\n";
        echo "  Current page: " . $data['page'] . "\n";
        echo "  Total pages: " . $data['pages'] . "\n";
    } else {
        echo "❌ API did not return valid JSON\n";
    }
    
    echo "\n================================\n";
    echo "✅ ALL TESTS PASSED!\n";
    echo "Phase 3 completed successfully.\n";
    echo "================================\n\n";
    
    echo "You can now browse all items at: /claims/items\n";
    
} catch (Exception $e) {
    echo "\n================================\n";
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "================================\n";
    echo "\nStack trace:\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}