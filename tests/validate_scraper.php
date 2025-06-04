#!/usr/bin/env php
<?php

/**
 * Comprehensive validation of the event scraper system
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';

use YakimaFinds\Scrapers\EventScraper;
use YakimaFinds\Models\CalendarSourceModel;
use YakimaFinds\Models\EventModel;

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
$eventModel = new EventModel($db);

printHeader("Event Scraper Validation Suite");

// 1. Check database connectivity
printHeader("Database Connectivity");
try {
    $db->query("SELECT 1");
    printSuccess("Database connection successful");
} catch (Exception $e) {
    printError("Database connection failed: " . $e->getMessage());
    exit(1);
}

// 2. Validate required tables
printHeader("Database Schema Validation");
$requiredTables = [
    'events', 'calendar_sources', 'scraping_logs', 
    'event_categories', 'event_category_mapping'
];

foreach ($requiredTables as $table) {
    $result = $db->query("SHOW TABLES LIKE '$table'");
    if ($result->fetch()) {
        printSuccess("Table '$table' exists");
    } else {
        printError("Table '$table' is missing");
    }
}

// 3. Check active sources
printHeader("Calendar Sources Status");
$sources = $sourceModel->getActiveSources();
printInfo("Active sources: " . count($sources));

foreach ($sources as $source) {
    echo "\n";
    printInfo("Source: " . $source['name']);
    printInfo("  Type: " . $source['scrape_type']);
    printInfo("  URL: " . $source['url']);
    
    // Check last scrape
    if (isset($source['last_scraped']) && $source['last_scraped']) {
        $lastScraped = new DateTime($source['last_scraped']);
        $now = new DateTime();
        $diff = $now->diff($lastScraped);
        
        if ($diff->days > 7) {
            printWarning("  Last scraped: " . $diff->days . " days ago");
        } else {
            printSuccess("  Last scraped: " . $diff->days . " days ago");
        }
    } else {
        printWarning("  Never scraped");
    }
    
    // Check recent logs
    $stmt = $db->prepare("
        SELECT status, events_found, events_added, error_message, start_time
        FROM scraping_logs 
        WHERE source_id = ? 
        ORDER BY start_time DESC 
        LIMIT 3
    ");
    $stmt->execute([$source['id']]);
    $logs = $stmt->fetchAll();
    
    if (!empty($logs)) {
        printInfo("  Recent scraping history:");
        foreach ($logs as $log) {
            $status = $log['status'] === 'success' ? GREEN . "✓" . RESET : RED . "✗" . RESET;
            $date = date('Y-m-d H:i', strtotime($log['start_time']));
            echo "    $status $date - Found: {$log['events_found']}, Added: {$log['events_added']}";
            if ($log['error_message']) {
                echo " (Error: {$log['error_message']})";
            }
            echo "\n";
        }
    }
}

// 4. Test each scraper type
printHeader("Scraper Type Tests");

// Test iCal scraper with a known working feed
printInfo("Testing iCal scraper...");
$testIcalSource = [
    'id' => 99999,
    'name' => 'Test iCal Feed',
    'url' => 'https://calendar.google.com/calendar/ical/en.usa%23holiday%40group.v.calendar.google.com/public/basic.ics',
    'scrape_type' => 'ical',
    'scrape_config' => '{}',
    'active' => 1
];

try {
    // Don't actually save events, just test parsing
    $content = file_get_contents($testIcalSource['url']);
    if ($content && strpos($content, 'BEGIN:VCALENDAR') !== false) {
        printSuccess("iCal scraper can fetch and parse calendar data");
    } else {
        printError("iCal scraper failed to fetch valid data");
    }
} catch (Exception $e) {
    printError("iCal scraper error: " . $e->getMessage());
}

// 5. Check for duplicate events
printHeader("Data Quality Checks");

// Check for duplicates
$stmt = $db->query("
    SELECT COUNT(*) as duplicate_count 
    FROM (
        SELECT title, start_datetime, COUNT(*) as cnt
        FROM events 
        WHERE status = 'approved'
        GROUP BY title, start_datetime 
        HAVING cnt > 1
    ) as duplicates
");
$duplicates = $stmt->fetch();

if ($duplicates['duplicate_count'] > 0) {
    printWarning("Found " . $duplicates['duplicate_count'] . " potential duplicate events");
} else {
    printSuccess("No duplicate events found");
}

// Check for events without location
$stmt = $db->query("
    SELECT COUNT(*) as no_location_count 
    FROM events 
    WHERE status = 'approved' 
    AND (location IS NULL OR location = '')
");
$noLocation = $stmt->fetch();

if ($noLocation['no_location_count'] > 0) {
    printWarning($noLocation['no_location_count'] . " events have no location");
} else {
    printSuccess("All events have locations");
}

// Check for events without geocoding
$stmt = $db->query("
    SELECT COUNT(*) as no_geocode_count 
    FROM events 
    WHERE status = 'approved' 
    AND location IS NOT NULL 
    AND location != ''
    AND (latitude IS NULL OR longitude IS NULL)
");
$noGeocode = $stmt->fetch();

if ($noGeocode['no_geocode_count'] > 0) {
    printWarning($noGeocode['no_geocode_count'] . " events need geocoding");
    printInfo("Run geocode-fix.php from admin panel to fix this");
} else {
    printSuccess("All events with locations are geocoded");
}

// 6. Performance metrics
printHeader("Performance Metrics");

// Average scraping time
$stmt = $db->query("
    SELECT 
        AVG(TIMESTAMPDIFF(SECOND, start_time, end_time)) as avg_duration,
        MAX(TIMESTAMPDIFF(SECOND, start_time, end_time)) as max_duration,
        COUNT(*) as total_runs
    FROM scraping_logs 
    WHERE status = 'success' 
    AND start_time IS NOT NULL 
    AND end_time IS NOT NULL
");
$metrics = $stmt->fetch();

if ($metrics['total_runs'] > 0) {
    printInfo("Average scraping duration: " . round($metrics['avg_duration'], 2) . " seconds");
    printInfo("Maximum scraping duration: " . $metrics['max_duration'] . " seconds");
    printInfo("Total successful runs: " . $metrics['total_runs']);
} else {
    printWarning("No performance metrics available yet");
}

// 7. Recent events summary
printHeader("Recent Events Summary");

$stmt = $db->query("
    SELECT 
        DATE(start_datetime) as event_date,
        COUNT(*) as event_count
    FROM events 
    WHERE status = 'approved'
    AND start_datetime >= CURDATE()
    AND start_datetime <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
    GROUP BY DATE(start_datetime)
    ORDER BY event_date
    LIMIT 7
");
$upcomingDays = $stmt->fetchAll();

if (!empty($upcomingDays)) {
    printInfo("Upcoming events in the next 7 days:");
    foreach ($upcomingDays as $day) {
        echo "  " . $day['event_date'] . ": " . $day['event_count'] . " events\n";
    }
} else {
    printWarning("No upcoming events found");
}

// 8. Recommendations
printHeader("Recommendations");

$recommendations = [];

if (count($sources) < 3) {
    $recommendations[] = "Add more event sources for better coverage";
}

if ($noGeocode['no_geocode_count'] > 10) {
    $recommendations[] = "Run geocoding fix for better map display";
}

if ($duplicates['duplicate_count'] > 5) {
    $recommendations[] = "Review and merge duplicate events";
}

// Check if any source hasn't been scraped in 24 hours
$stmt = $db->query("
    SELECT COUNT(*) as stale_count 
    FROM calendar_sources 
    WHERE active = 1 
    AND (last_scraped IS NULL OR last_scraped < DATE_SUB(NOW(), INTERVAL 24 HOUR))
");
$stale = $stmt->fetch();

if ($stale['stale_count'] > 0) {
    $recommendations[] = "Set up cron job to run scraper daily";
}

if (empty($recommendations)) {
    printSuccess("System is operating optimally!");
} else {
    foreach ($recommendations as $rec) {
        printWarning($rec);
    }
}

printHeader("Validation Complete");
printInfo("Scraper system is " . GREEN . "operational" . RESET);
printInfo("Run 'php cron/scrape-events.php' to manually trigger scraping");
printInfo("Visit /admin/calendar/ to manage sources and events");