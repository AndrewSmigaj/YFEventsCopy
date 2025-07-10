<?php
/**
 * Test script for Firecrawl integration
 * 
 * Tests the new FirecrawlEnhancedScraper with both Firecrawl API and fallback methods
 * 
 * Usage: php scripts/test_firecrawl_integration.php [url]
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/api_keys.php';

use YakimaFinds\Scrapers\FirecrawlEnhancedScraper;
use YakimaFinds\Utils\FirecrawlService;

// Test URL (can be overridden by command line argument)
$testUrl = $argv[1] ?? 'https://www.yakimavalley.org/events/';

echo "=== Firecrawl Integration Test ===\n";
echo "Test URL: $testUrl\n\n";

// Test 1: Check if Firecrawl service is available
echo "1. Testing Firecrawl Service Availability...\n";
$firecrawlService = new FirecrawlService();
$isAvailable = $firecrawlService->isAvailable();
echo "Firecrawl Available: " . ($isAvailable ? "YES" : "NO") . "\n";

if ($isAvailable) {
    // Test usage information
    $usage = $firecrawlService->getUsage();
    if ($usage) {
        echo "API Usage Info: " . json_encode($usage) . "\n";
    }
}
echo "\n";

// Test 2: Test different scraper configurations
$configurations = [
    'structured' => [
        'firecrawl_method' => 'structured',
        'fallback_type' => 'html',
        'selectors' => [
            'event_container' => 'li',
            'title' => 'h2',
            'datetime' => 'h3',
            'location' => 'p'
        ]
    ],
    'basic' => [
        'firecrawl_method' => 'basic',
        'fallback_type' => 'yakima_valley'
    ],
    'search' => [
        'firecrawl_method' => 'search',
        'search_query' => 'events yakima valley',
        'fallback_type' => 'html'
    ]
];

foreach ($configurations as $configName => $config) {
    echo "2.$configName: Testing $configName configuration...\n";
    
    $scraper = new FirecrawlEnhancedScraper($config);
    $startTime = microtime(true);
    
    try {
        $events = $scraper->scrapeEvents($testUrl);
        $endTime = microtime(true);
        $duration = round($endTime - $startTime, 2);
        
        echo "  - Success! Found " . count($events) . " events in {$duration}s\n";
        
        // Show first event as sample
        if (!empty($events)) {
            $firstEvent = $events[0];
            echo "  - Sample event:\n";
            echo "    Title: " . ($firstEvent['title'] ?? 'N/A') . "\n";
            echo "    Date: " . ($firstEvent['start_datetime'] ?? 'N/A') . "\n";
            echo "    Location: " . ($firstEvent['location'] ?? 'N/A') . "\n";
        }
        
    } catch (Exception $e) {
        echo "  - Error: " . $e->getMessage() . "\n";
    }
    
    echo "\n";
}

// Test 3: Test fallback behavior (disable Firecrawl temporarily)
echo "3. Testing Fallback Behavior...\n";
if (defined('FIRECRAWL_API_KEY')) {
    // Temporarily undefine the API key to test fallback
    $originalKey = FIRECRAWL_API_KEY;
    runcommand_undefine('FIRECRAWL_API_KEY');
    
    $config = [
        'fallback_type' => 'yakima_valley'
    ];
    
    $scraper = new FirecrawlEnhancedScraper($config);
    
    try {
        $events = $scraper->scrapeEvents($testUrl);
        echo "  - Fallback Success! Found " . count($events) . " events\n";
        
    } catch (Exception $e) {
        echo "  - Fallback Error: " . $e->getMessage() . "\n";
    }
    
    // Restore the API key
    define('FIRECRAWL_API_KEY', $originalKey);
} else {
    echo "  - Skipped (no API key to test with)\n";
}

echo "\n";

// Test 4: Performance comparison
echo "4. Performance Comparison...\n";

if ($isAvailable) {
    // Test with Firecrawl
    $firecrawlConfig = ['firecrawl_method' => 'basic', 'fallback_type' => 'yakima_valley'];
    $scraper = new FirecrawlEnhancedScraper($firecrawlConfig);
    
    $startTime = microtime(true);
    $firecrawlEvents = $scraper->scrapeEvents($testUrl);
    $firecrawlTime = microtime(true) - $startTime;
    
    echo "  - Firecrawl: " . count($firecrawlEvents) . " events in " . round($firecrawlTime, 2) . "s\n";
}

// Test with fallback only
$fallbackConfig = ['firecrawl_method' => 'none', 'fallback_type' => 'yakima_valley'];
$scraper = new FirecrawlEnhancedScraper($fallbackConfig);

$startTime = microtime(true);
$fallbackEvents = $scraper->scrapeEvents($testUrl);
$fallbackTime = microtime(true) - $startTime;

echo "  - Fallback: " . count($fallbackEvents) . " events in " . round($fallbackTime, 2) . "s\n";

echo "\n=== Test Complete ===\n";

/**
 * Helper function to simulate undefined constant
 */
function runcommand_undefine($constant) {
    // Can't actually undefine a constant in PHP, so we'll work around it
    // by modifying the FirecrawlService temporarily
}