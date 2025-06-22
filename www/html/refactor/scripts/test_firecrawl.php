<?php

/**
 * Test Firecrawl Integration
 * 
 * Script to test Firecrawl API connection and Eventbrite scraping
 */

require_once __DIR__ . '/../vendor/autoload.php';

use YFEvents\Infrastructure\Http\FirecrawlClient;
use YFEvents\Infrastructure\Scrapers\EventbriteScraper;
use YFEvents\Utils\EnvLoader;

// Load environment variables
EnvLoader::load(__DIR__ . '/../.env');

// Check for API key
$apiKey = $_ENV['FIRECRAWL_API_KEY'] ?? '';
if (empty($apiKey)) {
    echo "❌ Error: FIRECRAWL_API_KEY not found in .env file\n";
    echo "Please add your Firecrawl API key to the .env file\n";
    exit(1);
}

echo "🔥 Testing Firecrawl Integration\n";
echo "================================\n\n";

// Test 1: API Connection
echo "1. Testing API Connection...\n";
try {
    $client = new FirecrawlClient($apiKey);
    if ($client->testConnection()) {
        echo "✅ API connection successful\n\n";
    } else {
        echo "❌ API connection failed\n";
        exit(1);
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 2: Eventbrite Scraping
echo "2. Testing Eventbrite Scraper...\n";
echo "Location: Yakima, WA\n";

try {
    $config = require __DIR__ . '/../config/firecrawl.php';
    $scraper = new EventbriteScraper($client, $config['eventbrite']);
    
    echo "Scraping events (this may take a moment)...\n";
    $events = $scraper->scrapeLocation('yakima', ['state' => 'wa']);
    
    if (empty($events)) {
        echo "⚠️  No events found. This could be normal if there are no events listed.\n";
    } else {
        echo "✅ Found " . count($events) . " events\n\n";
        
        // Display first 3 events
        echo "Sample Events:\n";
        echo "--------------\n";
        foreach (array_slice($events, 0, 3) as $i => $event) {
            echo ($i + 1) . ". " . $event->getTitle() . "\n";
            echo "   Date: " . $event->getStartDateTime() . "\n";
            echo "   Location: " . $event->getVenueName() . ", " . $event->getCity() . "\n";
            echo "   URL: " . $event->getUrl() . "\n\n";
        }
    }
} catch (Exception $e) {
    echo "❌ Scraping error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

// Test 3: Rate Limiting
echo "3. Testing Rate Limiting...\n";
echo "Making 3 requests with 2-second delays...\n";

$start = microtime(true);
for ($i = 1; $i <= 3; $i++) {
    echo "Request $i... ";
    try {
        $scraper->scrapeLocation('seattle', ['state' => 'wa', 'max_events' => 5]);
        echo "✅\n";
    } catch (Exception $e) {
        echo "❌ " . $e->getMessage() . "\n";
    }
}
$duration = microtime(true) - $start;
echo "Total time: " . number_format($duration, 2) . " seconds\n";
echo "Expected minimum: ~4 seconds (with rate limiting)\n";

if ($duration >= 4) {
    echo "✅ Rate limiting is working correctly\n";
} else {
    echo "⚠️  Rate limiting may not be working properly\n";
}

echo "\n🎉 Firecrawl integration test complete!\n";