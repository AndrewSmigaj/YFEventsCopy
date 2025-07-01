#!/usr/bin/env php
<?php

/**
 * Add local event sources to the calendar_sources table
 * Based on test data and known local event providers
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';

use YFEvents\Models\CalendarSourceModel;

// Colors for output
define('GREEN', "\033[0;32m");
define('RED', "\033[0;31m");
define('YELLOW', "\033[0;33m");
define('BLUE', "\033[0;34m");
define('RESET', "\033[0m");

function printSuccess($text) {
    echo GREEN . "✓ " . RESET . "$text\n";
}

function printError($text) {
    echo RED . "✗ " . RESET . "$text\n";
}

function printInfo($text) {
    echo BLUE . "ℹ " . RESET . "$text\n";
}

echo BLUE . "==== Adding Local Event Sources ====" . RESET . "\n\n";

// Define local event sources
$localSources = [
    // iCal Sources
    [
        'name' => 'US Holidays Calendar',
        'url' => 'https://calendar.google.com/calendar/ical/en.usa%23holiday%40group.v.calendar.google.com/public/basic.ics',
        'scrape_type' => 'ical',
        'scrape_config' => json_encode([]),
        'description' => 'Official US holidays from Google Calendar',
        'active' => 1
    ],
    [
        'name' => 'Washington State Events',
        'url' => 'https://calendar.google.com/calendar/ical/en.usa%23holiday%40group.v.calendar.google.com/public/basic.ics',
        'scrape_type' => 'ical', 
        'scrape_config' => json_encode([]),
        'description' => 'Washington state official events',
        'active' => 1
    ],
    
    // HTML Sources - Local News Sites
    [
        'name' => 'Yakima Herald Calendar',
        'url' => 'https://www.yakimaherald.com/calendar/',
        'scrape_type' => 'html',
        'scrape_config' => json_encode([
            'selectors' => [
                'event_container' => '.event-item, .calendar-event, article.event',
                'title' => 'h2, h3, .event-title, .title',
                'description' => '.description, .event-description, .summary',
                'datetime' => '.date, .event-date, time',
                'location' => '.location, .venue, .event-location',
                'url' => 'a[href]'
            ]
        ]),
        'description' => 'Local news events from Yakima Herald',
        'active' => 0 // Disabled by default until selectors are verified
    ],
    
    // Yakima Valley Specific Sources
    [
        'name' => 'Downtown Yakima Events',
        'url' => 'https://www.downtownyakima.com/events',
        'scrape_type' => 'html',
        'scrape_config' => json_encode([
            'selectors' => [
                'event_container' => '.event, .event-listing',
                'title' => '.event-title, h3',
                'description' => '.event-description',
                'datetime' => '.event-date',
                'location' => '.event-location',
                'url' => 'a.event-link'
            ]
        ]),
        'description' => 'Downtown Yakima Association events',
        'active' => 0 // Disabled until URL and selectors verified
    ],
    
    [
        'name' => 'Yakima Valley Museum',
        'url' => 'https://www.yakimavalleymuseum.org/events',
        'scrape_type' => 'html',
        'scrape_config' => json_encode([
            'selectors' => [
                'event_container' => '.event-item',
                'title' => '.event-name',
                'description' => '.event-desc',
                'datetime' => '.event-date',
                'location' => '.event-location',
                'url' => 'a[href*="event"]'
            ]
        ]),
        'description' => 'Yakima Valley Museum events and exhibitions',
        'active' => 0
    ],
    
    [
        'name' => 'Yakima Convention Center',
        'url' => 'https://www.yakimaconventioncenter.com/events',
        'scrape_type' => 'html',
        'scrape_config' => json_encode([
            'selectors' => [
                'event_container' => '.event-wrapper',
                'title' => '.event-title',
                'description' => '.event-description',
                'datetime' => '.event-date-time',
                'location' => '.event-venue',
                'url' => 'a.event-url'
            ]
        ]),
        'description' => 'Yakima Convention Center events',
        'active' => 0
    ],
    
    [
        'name' => 'Capitol Theatre Yakima',
        'url' => 'https://www.capitoltheatre.org/events',
        'scrape_type' => 'html',
        'scrape_config' => json_encode([
            'selectors' => [
                'event_container' => '.show-item',
                'title' => '.show-title',
                'description' => '.show-description',
                'datetime' => '.show-date',
                'location' => '.venue-name',
                'url' => 'a.show-link'
            ]
        ]),
        'description' => 'Capitol Theatre performances and shows',
        'active' => 0
    ],
    
    // Wine Country Events
    [
        'name' => 'Yakima Valley Wine Country',
        'url' => 'https://www.wineyakimavalley.org/events',
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
        ]),
        'description' => 'Wine tasting and vineyard events',
        'active' => 0
    ],
    
    // Sports and Recreation
    [
        'name' => 'Yakima Valley Sports',
        'url' => 'https://www.yakimavalleysports.com/calendar',
        'scrape_type' => 'html',
        'scrape_config' => json_encode([
            'selectors' => [
                'event_container' => '.game-item',
                'title' => '.game-title',
                'description' => '.game-info',
                'datetime' => '.game-date',
                'location' => '.venue',
                'url' => 'a[href*="game"]'
            ]
        ]),
        'description' => 'Local sports events and games',
        'active' => 0
    ],
    
    // Community Calendars
    [
        'name' => 'City of Yakima Events',
        'url' => 'https://www.yakimawa.gov/events/',
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
        ]),
        'description' => 'Official City of Yakima events',
        'active' => 0
    ],
    
    // Educational Events
    [
        'name' => 'Yakima Valley College',
        'url' => 'https://www.yvcc.edu/events',
        'scrape_type' => 'html',
        'scrape_config' => json_encode([
            'selectors' => [
                'event_container' => '.event-listing',
                'title' => '.event-title',
                'description' => '.event-summary',
                'datetime' => '.event-date',
                'location' => '.event-location',
                'url' => 'a.event-details'
            ]
        ]),
        'description' => 'Yakima Valley College events',
        'active' => 0
    ]
];

// Add sources to database
$sourceModel = new CalendarSourceModel($db);
$addedCount = 0;
$skippedCount = 0;
$errorCount = 0;

foreach ($localSources as $source) {
    printInfo("Processing: " . $source['name']);
    
    // Check if source already exists
    $stmt = $db->prepare("
        SELECT id FROM calendar_sources 
        WHERE url = ? OR name = ?
    ");
    $stmt->execute([$source['url'], $source['name']]);
    
    if ($stmt->fetch()) {
        printInfo("  Source already exists, skipping");
        $skippedCount++;
        continue;
    }
    
    // Add the source
    try {
        $stmt = $db->prepare("
            INSERT INTO calendar_sources 
            (name, url, scrape_type, scrape_config, active, created_at) 
            VALUES 
            (:name, :url, :scrape_type, :scrape_config, :active, NOW())
        ");
        
        $stmt->execute([
            'name' => $source['name'],
            'url' => $source['url'],
            'scrape_type' => $source['scrape_type'],
            'scrape_config' => $source['scrape_config'],
            'active' => $source['active']
        ]);
        
        printSuccess("  Added successfully (ID: " . $db->lastInsertId() . ")");
        if (!$source['active']) {
            printInfo("  Note: Source is disabled by default. Enable in admin panel after verifying configuration.");
        }
        $addedCount++;
        
    } catch (Exception $e) {
        printError("  Failed to add: " . $e->getMessage());
        $errorCount++;
    }
}

echo "\n" . BLUE . "==== Summary ====" . RESET . "\n";
printSuccess("Added: $addedCount sources");
if ($skippedCount > 0) {
    printInfo("Skipped: $skippedCount (already exist)");
}
if ($errorCount > 0) {
    printError("Errors: $errorCount");
}

// Show current sources
echo "\n" . BLUE . "==== Current Active Sources ====" . RESET . "\n";
$sources = $sourceModel->getActiveSources();
foreach ($sources as $source) {
    printInfo($source['name'] . " (" . $source['scrape_type'] . ")");
}

echo "\n" . BLUE . "==== Next Steps ====" . RESET . "\n";
printInfo("1. Visit the admin panel to review and configure sources:");
printInfo("   http://137.184.245.149/admin/calendar/sources.php");
printInfo("2. Test each source individually before enabling");
printInfo("3. Update CSS selectors for HTML sources based on actual page structure");
printInfo("4. Run 'php cron/scrape-events.php' to test scraping");
printInfo("5. Enable sources that work correctly");

echo "\n";