<?php

/**
 * Script to fix and update calendar sources for better event discovery
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';

echo "=== Fixing Calendar Sources ===\n\n";

// Test URLs and update broken ones
$urlUpdates = [
    3 => 'https://www.yakimaherald.com/calendar/',  // Yakima Herald
    4 => 'https://www.downtownyakima.com/events/',   // Downtown Yakima - check if redirected
    5 => 'https://www.yakimavalleymuseum.org/visit/events/',  // Museum events page
    6 => 'https://www.yakimaconventioncenter.com/events',     // Convention Center
    8 => 'https://www.wineyakimavalley.org/events/',          // Wine Country
    9 => 'https://www.yakimavalleysports.com/events',         // Sports - try events instead of calendar
    10 => 'https://www.yakimawa.gov/media/calendar/',         // City - update to media calendar
    11 => 'https://tricities.wsu.edu/about/news-events/'      // WSU - try news-events
];

foreach ($urlUpdates as $sourceId => $newUrl) {
    echo "Testing URL for source $sourceId: $newUrl\n";
    
    // Test the URL
    $ch = curl_init($newUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; YakimaFindsBot/1.0)');
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_NOBODY, true); // HEAD request only
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    curl_close($ch);
    
    if ($httpCode === 200) {
        echo "  ✅ HTTP $httpCode - Updating URL\n";
        
        // Update the database
        $stmt = $db->prepare("UPDATE calendar_sources SET url = ? WHERE id = ?");
        $stmt->execute([$newUrl, $sourceId]);
        
        // Also activate the source if it was deactivated
        $stmt = $db->prepare("UPDATE calendar_sources SET active = 1 WHERE id = ?");
        $stmt->execute([$sourceId]);
        
    } else {
        echo "  ❌ HTTP $httpCode (Final URL: $finalUrl)\n";
    }
    echo "\n";
}

// Add configuration for HTML sources that need better selectors
$htmlConfigs = [
    7 => [ // Capitol Theatre - Based on actual HTML structure from capitoltheatre.org/events/
        'selectors' => [
            'event_container' => '.gallery-pic',
            'title' => 'img',  // Title is in the alt attribute of the image
            'url' => 'a',      // Link to event details
            'datetime' => '',  // Not available on listing page
            'location' => '',  // Not available on listing page
            'description' => ''
        ]
    ],
    3 => [ // Yakima Herald - Simplified selectors
        'selectors' => [
            'event_container' => '.event-item',
            'title' => 'h3',
            'datetime' => '.event-date', 
            'location' => '.event-location',
            'description' => '.event-description'
        ]
    ],
    10 => [ // City of Yakima
        'selectors' => [
            'event_container' => '.calendar-event',
            'title' => 'h3',
            'datetime' => '.event-date',
            'location' => '.event-location',
            'description' => '.event-description'
        ]
    ],
    11 => [ // WSU Tri-Cities
        'selectors' => [
            'event_container' => '.news-item',
            'title' => 'h3',
            'datetime' => '.date',
            'location' => '.location',
            'description' => '.summary'
        ]
    ]
];

foreach ($htmlConfigs as $sourceId => $config) {
    echo "Updating scrape configuration for source $sourceId\n";
    
    $configJson = json_encode($config);
    $stmt = $db->prepare("UPDATE calendar_sources SET scrape_config = ? WHERE id = ?");
    $stmt->execute([$configJson, $sourceId]);
    
    echo "  ✅ Configuration updated\n\n";
}

// Test a specific source to see if it's working better
echo "=== Testing Capitol Theatre Source ===\n";
$stmt = $db->prepare("SELECT * FROM calendar_sources WHERE id = 7");
$stmt->execute();
$source = $stmt->fetch(PDO::FETCH_ASSOC);

if ($source) {
    echo "Testing: {$source['name']}\n";
    echo "URL: {$source['url']}\n";
    echo "Type: {$source['scrape_type']}\n";
    echo "Active: " . ($source['active'] ? 'Yes' : 'No') . "\n\n";
    
    // Test the actual scraping
    try {
        $scraper = new \YFEvents\Scrapers\EventScraper($db);
        $result = $scraper->scrapeSource($source);
        
        if ($result['success']) {
            echo "✅ Scraping successful!\n";
            echo "Events found: {$result['events_found']}\n";
            echo "Events added: {$result['events_added']}\n";
        } else {
            echo "❌ Scraping failed: {$result['error']}\n";
        }
    } catch (Exception $e) {
        echo "❌ Exception: " . $e->getMessage() . "\n";
    }
}

echo "\n=== Summary ===\n";
echo "✅ Updated URLs for sources with redirects or better paths\n";
echo "✅ Added improved HTML selectors for major venues\n";
echo "✅ Activated sources that were previously disabled\n";
echo "\nNext steps:\n";
echo "1. Run full scraper test: php cron/scrape-events.php\n";
echo "2. Check individual sources: php scripts/test_all_sources.php\n";
echo "3. Monitor logs for any remaining issues\n";