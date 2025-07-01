#!/usr/bin/env php
<?php

/**
 * Improved test script for event scraping
 * Tests with actual database sources and finds working URLs
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';

use YFEvents\Scrapers\EventScraper;
use YFEvents\Models\CalendarSourceModel;

// Colors for output
define('GREEN', "\033[0;32m");
define('RED', "\033[0;31m");
define('YELLOW', "\033[0;33m");
define('BLUE', "\033[0;34m");
define('RESET', "\033[0m");

function printHeader($text) {
    echo "\n" . BLUE . "==== $text ====" . RESET . "\n\n";
}

function printSuccess($text) {
    echo GREEN . "✓ " . RESET . "$text\n";
}

function printError($text) {
    echo RED . "✗ " . RESET . "$text\n";
}

function printWarning($text) {
    echo YELLOW . "⚠ " . RESET . "$text\n";
}

function printInfo($text) {
    echo BLUE . "ℹ " . RESET . "$text\n";
}

// Initialize models
$scraper = new EventScraper($db);
$sourceModel = new CalendarSourceModel($db);

printHeader("Event Scraper Test - Improved Version");

// First, let's find the correct Visit Yakima URL
printHeader("Finding Correct Visit Yakima Events URL");

$visitYakimaUrls = [
    'https://www.visityakima.com/',
    'https://visityakima.com/',
    'https://www.visityakima.com/events',
    'https://www.visityakima.com/things-to-do',
    'https://www.visityakima.com/calendar',
    'https://www.visityakima.com/whats-happening',
    'https://www.visityakima.com/upcoming-events'
];

$workingUrl = null;

foreach ($visitYakimaUrls as $url) {
    echo "Checking: $url ... ";
    $headers = @get_headers($url);
    if ($headers && strpos($headers[0], '200')) {
        echo GREEN . "OK" . RESET . "\n";
        
        // Fetch the page to look for events section
        $context = stream_context_create([
            'http' => [
                'timeout' => 10,
                'user_agent' => 'Mozilla/5.0 (compatible; YakimaFinds/1.0)'
            ]
        ]);
        
        $content = @file_get_contents($url, false, $context);
        if ($content) {
            // Look for event-related links or sections
            if (preg_match_all('/<a[^>]+href=["\']([^"\']+)["\'][^>]*>.*?(event|calendar|happening)/i', $content, $matches)) {
                printInfo("  Found event-related links:");
                foreach (array_unique($matches[1]) as $link) {
                    if (strpos($link, 'http') !== 0) {
                        // Make absolute URL
                        $parsed = parse_url($url);
                        $link = $parsed['scheme'] . '://' . $parsed['host'] . '/' . ltrim($link, '/');
                    }
                    printInfo("  - $link");
                }
            }
            
            if (!$workingUrl) {
                $workingUrl = $url;
            }
        }
    } else {
        echo RED . "FAIL" . RESET;
        if ($headers) {
            echo " (" . $headers[0] . ")";
        }
        echo "\n";
    }
}

if ($workingUrl) {
    printSuccess("\nBase URL found: $workingUrl");
    printInfo("You should check the links above for the actual events page");
}

// Test with existing database sources
printHeader("Testing Existing Database Sources");

try {
    $sources = $sourceModel->getActiveSources();
    
    if (empty($sources)) {
        printWarning("No active sources found in database");
        
        // Create a test source
        printInfo("Creating a test iCal source...");
        
        $testSourceData = [
            'name' => 'US Holidays (Test)',
            'url' => 'https://calendar.google.com/calendar/ical/en.usa%23holiday%40group.v.calendar.google.com/public/basic.ics',
            'scrape_type' => 'ical',
            'scrape_config' => json_encode([]),
            'active' => 1
        ];
        
        $stmt = $db->prepare("
            INSERT INTO calendar_sources (name, url, scrape_type, scrape_config, active, created_at)
            VALUES (:name, :url, :scrape_type, :scrape_config, :active, NOW())
        ");
        
        $stmt->execute($testSourceData);
        $testSourceId = $db->lastInsertId();
        
        printSuccess("Created test source with ID: $testSourceId");
        
        // Fetch the created source
        $sources = [$sourceModel->getSourceById($testSourceId)];
    }
    
    foreach ($sources as $source) {
        printHeader("Testing: " . $source['name']);
        printInfo("URL: " . $source['url']);
        printInfo("Type: " . $source['scrape_type']);
        
        $startTime = microtime(true);
        
        try {
            $result = $scraper->scrapeSource($source);
            $elapsed = round(microtime(true) - $startTime, 2);
            
            if ($result['success']) {
                printSuccess("Scraping completed in {$elapsed}s");
                printInfo("Events found: " . $result['events_found']);
                printInfo("Events added: " . $result['events_added']);
                
                if ($result['events_found'] > 0) {
                    // Get last few events to show
                    $stmt = $db->prepare("
                        SELECT title, start_datetime, location 
                        FROM events 
                        WHERE source_id = ? 
                        ORDER BY created_at DESC 
                        LIMIT 3
                    ");
                    $stmt->execute([$source['id']]);
                    $sampleEvents = $stmt->fetchAll();
                    
                    if (!empty($sampleEvents)) {
                        printInfo("\nSample events added:");
                        foreach ($sampleEvents as $event) {
                            printInfo("  - " . $event['title']);
                            printInfo("    Date: " . $event['start_datetime']);
                            if ($event['location']) {
                                printInfo("    Location: " . $event['location']);
                            }
                        }
                    }
                }
            } else {
                printError("Scraping failed: " . $result['error']);
                
                // If it's a Visit Yakima source with wrong URL
                if ($source['scrape_type'] === 'yakima_valley' && strpos($result['error'], '404') !== false) {
                    printWarning("The URL appears to be incorrect");
                    printInfo("To fix this, update the source URL in the admin panel");
                    if ($workingUrl) {
                        printInfo("Suggested URL: $workingUrl");
                    }
                }
            }
            
        } catch (Exception $e) {
            printError("Exception: " . $e->getMessage());
        }
    }
    
} catch (Exception $e) {
    printError("Database error: " . $e->getMessage());
}

// Test creating a new HTML scraper for a local news site
printHeader("Testing HTML Scraper Configuration");

// Example: Yakima Herald events page
$testHtmlSource = [
    'id' => 9999, // Temporary ID
    'name' => 'Test HTML Scraper',
    'url' => 'https://www.yakimaherald.com/calendar/',
    'scrape_type' => 'html',
    'active' => 1,
    'scrape_config' => json_encode([
        'selectors' => [
            'event_container' => '.event-item, .calendar-event, article.event',
            'title' => 'h2, h3, .event-title, .title',
            'description' => '.description, .event-description, .summary',
            'datetime' => '.date, .event-date, time',
            'location' => '.location, .venue, .event-location',
            'url' => 'a[href]'
        ]
    ])
];

printInfo("Testing HTML scraper configuration...");
printInfo("URL: " . $testHtmlSource['url']);

$headers = @get_headers($testHtmlSource['url']);
if ($headers && strpos($headers[0], '200')) {
    printSuccess("URL is accessible");
    printInfo("You can add this as an HTML source through the admin panel");
    printInfo("Use these CSS selectors as a starting point and adjust based on the actual HTML structure");
} else {
    printWarning("URL not accessible or returned: " . ($headers ? $headers[0] : 'No response'));
}

printHeader("Scraper Test Summary");
printInfo("1. Check if Visit Yakima has changed their URL structure");
printInfo("2. Update existing sources through the admin panel at: /admin/calendar/sources.php");
printInfo("3. Consider adding new sources like local news sites or community calendars");
printInfo("4. The iCal scraper works well with Google Calendar and other standard feeds");
printInfo("5. HTML scrapers need custom CSS selectors for each site");

printHeader("Test Complete");