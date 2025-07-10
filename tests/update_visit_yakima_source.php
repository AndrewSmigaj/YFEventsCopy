#!/usr/bin/env php
<?php

/**
 * Update Visit Yakima source with correct URL
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';

use YakimaFinds\Scrapers\EventScraper;
use YakimaFinds\Models\CalendarSourceModel;

echo "Updating Visit Yakima source URL...\n";

// Update the source
$stmt = $db->prepare("
    UPDATE calendar_sources 
    SET url = 'https://www.visityakima.com/yakima-valley-events',
        scrape_config = :config
    WHERE scrape_type = 'yakima_valley' 
    AND (url LIKE '%visityakima.com%' OR name LIKE '%yakima%valley%')
");

$config = json_encode([
    'base_url' => 'https://www.visityakima.com',
    'year' => date('Y')
]);

$stmt->execute(['config' => $config]);

$rowsUpdated = $stmt->rowCount();
echo "Updated $rowsUpdated source(s)\n";

// Now test the updated source
$sourceModel = new CalendarSourceModel($db);
$scraper = new EventScraper($db);

$sources = $sourceModel->getActiveSources();
foreach ($sources as $source) {
    if ($source['scrape_type'] === 'yakima_valley') {
        echo "\nTesting updated source: {$source['name']}\n";
        echo "URL: {$source['url']}\n";
        
        try {
            $result = $scraper->scrapeSource($source);
            
            if ($result['success']) {
                echo "✓ Scraping successful!\n";
                echo "  Events found: {$result['events_found']}\n";
                echo "  Events added: {$result['events_added']}\n";
                
                // Show some sample events
                if ($result['events_found'] > 0) {
                    $stmt = $db->prepare("
                        SELECT title, start_datetime, location, description
                        FROM events 
                        WHERE source_id = ? 
                        AND scraped_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
                        ORDER BY start_datetime 
                        LIMIT 5
                    ");
                    $stmt->execute([$source['id']]);
                    $events = $stmt->fetchAll();
                    
                    if (!empty($events)) {
                        echo "\nSample events:\n";
                        foreach ($events as $event) {
                            echo "  - {$event['title']}\n";
                            echo "    Date: {$event['start_datetime']}\n";
                            if ($event['location']) {
                                echo "    Location: {$event['location']}\n";
                            }
                            if ($event['description']) {
                                echo "    Description: " . substr($event['description'], 0, 100) . "...\n";
                            }
                            echo "\n";
                        }
                    }
                }
            } else {
                echo "✗ Scraping failed: {$result['error']}\n";
            }
        } catch (Exception $e) {
            echo "✗ Exception: {$e->getMessage()}\n";
        }
    }
}

echo "\nDone! The Visit Yakima source has been updated.\n";