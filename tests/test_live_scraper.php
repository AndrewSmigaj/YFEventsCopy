#!/usr/bin/env php
<?php

/**
 * Test script for live event scraping
 * This script tests the scraper with actual event sources
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

printHeader("Live Event Scraper Test");

// Test sources to scrape
$testSources = [
    [
        'name' => 'Yakima Valley Visitors',
        'url' => 'https://www.visityakima.com/events/',
        'scrape_type' => 'yakima_valley',
        'scrape_config' => json_encode([
            'base_url' => 'https://www.visityakima.com',
            'year' => date('Y')
        ])
    ],
    [
        'name' => 'Test iCal Feed',
        'url' => 'https://calendar.google.com/calendar/ical/en.usa%23holiday%40group.v.calendar.google.com/public/basic.ics',
        'scrape_type' => 'ical',
        'scrape_config' => json_encode([])
    ],
    [
        'name' => 'Test HTML Source',
        'url' => 'https://example.com/events',
        'scrape_type' => 'html',
        'scrape_config' => json_encode([
            'selectors' => [
                'event_container' => '.event-item',
                'title' => '.event-title',
                'description' => '.event-description',
                'datetime' => '.event-date',
                'location' => '.event-location',
                'url' => 'a.event-link'
            ]
        ])
    ]
];

// Test each source
foreach ($testSources as $index => $source) {
    printHeader("Testing Source #" . ($index + 1) . ": " . $source['name']);
    printInfo("URL: " . $source['url']);
    printInfo("Type: " . $source['scrape_type']);
    
    // Create temporary source for testing
    $source['id'] = 999 + $index;
    $source['active'] = 1;
    
    $startTime = microtime(true);
    
    try {
        $result = $scraper->scrapeSource($source);
        $elapsed = round(microtime(true) - $startTime, 2);
        
        if ($result['success']) {
            printSuccess("Scraping completed in {$elapsed}s");
            printInfo("Events found: " . $result['events_found']);
            printInfo("Events added: " . $result['events_added']);
            
            // Show sample event if found
            if ($result['events_found'] > 0) {
                printInfo("\nSample event data:");
                // We'd need to modify scrapeSource to return sample data
                // For now, just show counts
            }
        } else {
            printError("Scraping failed: " . $result['error']);
        }
        
    } catch (Exception $e) {
        printError("Exception: " . $e->getMessage());
        if ($e->getMessage() === 'Failed to fetch content from Yakima Valley source') {
            printWarning("This might be due to an incorrect URL or the site being down");
            printInfo("Checking URL accessibility...");
            
            // Test URL accessibility
            $headers = @get_headers($source['url']);
            if ($headers && strpos($headers[0], '200')) {
                printSuccess("URL is accessible (HTTP 200)");
            } elseif ($headers) {
                printError("URL returned: " . $headers[0]);
            } else {
                printError("URL is not accessible");
            }
        }
    }
    
    echo "\n";
}

// Check existing sources in database
printHeader("Checking Existing Calendar Sources");

try {
    $sources = $sourceModel->getActiveSources();
    
    if (empty($sources)) {
        printWarning("No active sources found in database");
        printInfo("You may want to add some sources through the admin panel");
    } else {
        printSuccess("Found " . count($sources) . " active source(s)");
        
        foreach ($sources as $source) {
            echo "\n";
            printInfo("Source: " . $source['name']);
            printInfo("  - URL: " . $source['url']);
            printInfo("  - Type: " . $source['scrape_type']);
            printInfo("  - Last scraped: " . ($source['last_scraped_at'] ?? 'Never'));
            
            if ($source['scrape_type'] === 'yakima_valley' && strpos($source['url'], 'visityakima.com') !== false) {
                printWarning("  - This source may need URL update if returning 404");
            }
        }
    }
} catch (Exception $e) {
    printError("Failed to check existing sources: " . $e->getMessage());
}

// Test specific Yakima Valley scraper
printHeader("Testing Yakima Valley Scraper Directly");

try {
    // Try different possible URLs for Visit Yakima
    $possibleUrls = [
        'https://www.visityakima.com/events/',
        'https://www.visityakima.com/things-to-do/events/',
        'https://www.visityakima.com/calendar/',
        'https://visityakima.com/events/'
    ];
    
    printInfo("Testing various possible URLs for Visit Yakima...\n");
    
    foreach ($possibleUrls as $url) {
        echo "Testing: $url ... ";
        $headers = @get_headers($url);
        if ($headers && strpos($headers[0], '200')) {
            printSuccess("Found working URL!");
            printInfo("You should update the calendar source to use: $url");
            
            // Try to fetch some content
            $context = stream_context_create([
                'http' => [
                    'timeout' => 10,
                    'user_agent' => 'Yakima Finds Calendar Bot 1.0'
                ]
            ]);
            
            $content = @file_get_contents($url, false, $context);
            if ($content) {
                printSuccess("Successfully fetched content (" . strlen($content) . " bytes)");
                
                // Check if it looks like an events page
                if (stripos($content, 'event') !== false || stripos($content, 'calendar') !== false) {
                    printSuccess("Content appears to contain event-related data");
                } else {
                    printWarning("Content may not be an events page");
                }
            }
            break;
        } elseif ($headers) {
            echo $headers[0] . "\n";
        } else {
            echo "Failed to connect\n";
        }
    }
    
} catch (Exception $e) {
    printError("Error testing URLs: " . $e->getMessage());
}

printHeader("Test Complete");
printInfo("Check the results above and update any failing sources through the admin panel");