#!/usr/bin/env php
<?php

/**
 * Show status of all calendar sources
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';

// Colors
define('GREEN', "\033[0;32m");
define('RED', "\033[0;31m");
define('YELLOW', "\033[0;33m");
define('BLUE', "\033[0;34m");
define('RESET', "\033[0m");

echo BLUE . "==== Calendar Sources Status ====" . RESET . "\n\n";

// Get all sources with their latest scraping info
$stmt = $db->query("
    SELECT 
        cs.*,
        sl.start_time as last_scrape_time,
        sl.status as last_scrape_status,
        sl.events_found as last_events_found,
        sl.events_added as last_events_added,
        sl.error_message as last_error,
        (SELECT COUNT(*) FROM events WHERE source_id = cs.id) as total_events
    FROM calendar_sources cs
    LEFT JOIN (
        SELECT source_id, MAX(id) as max_id
        FROM scraping_logs
        GROUP BY source_id
    ) latest ON cs.id = latest.source_id
    LEFT JOIN scraping_logs sl ON latest.max_id = sl.id
    ORDER BY cs.active DESC, cs.id
");

$sources = $stmt->fetchAll();

printf("%-3s %-35s %-15s %-7s %-10s %-15s %s\n", 
    "ID", "Name", "Type", "Active", "Events", "Last Scrape", "Status");
echo str_repeat("-", 110) . "\n";

foreach ($sources as $source) {
    $activeColor = $source['active'] ? GREEN : YELLOW;
    $active = $source['active'] ? 'Yes' : 'No';
    
    $statusColor = RESET;
    $status = 'Never';
    
    if ($source['last_scrape_status']) {
        if ($source['last_scrape_status'] === 'success') {
            $statusColor = GREEN;
            $status = "OK ({$source['last_events_found']} found)";
        } else {
            $statusColor = RED;
            $status = "Failed";
            if ($source['last_error']) {
                $status .= ": " . substr($source['last_error'], 0, 30) . "...";
            }
        }
    }
    
    $lastScrape = $source['last_scrape_time'] ? date('Y-m-d H:i', strtotime($source['last_scrape_time'])) : 'Never';
    
    printf("%-3d %-35s %-15s %s%-7s%s %-10d %-15s %s%s%s\n",
        $source['id'],
        substr($source['name'], 0, 35),
        $source['scrape_type'],
        $activeColor,
        $active,
        RESET,
        $source['total_events'],
        $lastScrape,
        $statusColor,
        $status,
        RESET
    );
}

echo "\n" . BLUE . "==== Summary ====" . RESET . "\n";
$activeCount = count(array_filter($sources, fn($s) => $s['active']));
$totalEvents = array_sum(array_column($sources, 'total_events'));

echo "Total sources: " . count($sources) . "\n";
echo "Active sources: " . GREEN . $activeCount . RESET . "\n";
echo "Inactive sources: " . YELLOW . (count($sources) - $activeCount) . RESET . "\n";
echo "Total events in database: " . $totalEvents . "\n";

echo "\n" . BLUE . "==== Commands ====" . RESET . "\n";
echo "Test a specific source:  php cron/scrape-events.php --source-id=ID\n";
echo "Scrape all sources:      php cron/scrape-events.php\n";
echo "Manage sources:          http://137.184.245.149/admin/calendar/sources.php\n";

echo "\n";