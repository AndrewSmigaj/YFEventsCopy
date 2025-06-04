#!/usr/bin/env php
<?php

/**
 * Update configurations for sources that have proper HTML structure
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';

echo "Updating working source configurations...\n\n";

// Capitol Theatre - has gallery-item structure
$capitolConfig = [
    'selectors' => [
        'event_container' => '.gallery-item',
        'title' => 'h6',
        'description' => 'p',
        'datetime' => 'p', // Contains date range like "Fri May 16 - Fri Aug 08"
        'location' => '', // Will default to venue name
        'url' => 'a[href*="detail"]'
    ]
];

$stmt = $db->prepare("UPDATE calendar_sources SET scrape_config = ?, active = 1 WHERE id = 7");
$stmt->execute([json_encode($capitolConfig)]);
echo "✓ Updated Capitol Theatre configuration\n";

// City of Yakima - uses The Events Calendar plugin
$yakimaConfig = [
    'selectors' => [
        'event_container' => 'article.tribe-events-calendar-month__calendar-event',
        'title' => 'h3.tribe-events-calendar-month__calendar-event-title a',
        'description' => '.tribe-events-calendar-month__calendar-event-description',
        'datetime' => '.tribe-events-calendar-month__calendar-event-datetime time',
        'location' => '.tribe-events-calendar-month__calendar-event-venue',
        'url' => 'h3.tribe-events-calendar-month__calendar-event-title a'
    ]
];

$stmt = $db->prepare("UPDATE calendar_sources SET scrape_config = ?, active = 1 WHERE id = 10");
$stmt->execute([json_encode($yakimaConfig)]);
echo "✓ Updated City of Yakima configuration\n";

// Downtown Yakima - check if it has actual events or just navigation
echo "\nChecking Downtown Yakima events page...\n";
$content = @file_get_contents('https://downtownyakima.com/events/', false, stream_context_create([
    'http' => ['timeout' => 10, 'user_agent' => 'Mozilla/5.0']
]));

if ($content && (strpos($content, 'calendar') !== false || strpos($content, 'upcoming') !== false)) {
    echo "✓ Downtown Yakima appears to have event content\n";
    
    // Basic configuration for testing
    $downtownConfig = [
        'selectors' => [
            'event_container' => '.event-item, .calendar-item, article',
            'title' => 'h1, h2, h3, .title',
            'description' => '.description, .content, p',
            'datetime' => '.date, .when, time',
            'location' => '.location, .where',
            'url' => 'a[href]'
        ]
    ];
    
    $stmt = $db->prepare("UPDATE calendar_sources SET scrape_config = ? WHERE id = 4");
    $stmt->execute([json_encode($downtownConfig)]);
    echo "✓ Updated Downtown Yakima configuration (inactive for testing)\n";
} else {
    echo "⚠ Downtown Yakima may not have scrapable events\n";
}

echo "\nTesting updated configurations...\n\n";

// Test Capitol Theatre
echo "Testing Capitol Theatre...\n";
$output = shell_exec("php " . __DIR__ . "/../cron/scrape-events.php --source-id=7 2>&1");
if (strpos($output, 'SUCCESS') !== false && preg_match('/Found (\d+) events/', $output, $matches)) {
    echo "✓ Capitol Theatre: Found {$matches[1]} events\n";
} else {
    echo "✗ Capitol Theatre: No events found\n";
}

// Test City of Yakima
echo "Testing City of Yakima...\n";
$output = shell_exec("php " . __DIR__ . "/../cron/scrape-events.php --source-id=10 2>&1");
if (strpos($output, 'SUCCESS') !== false && preg_match('/Found (\d+) events/', $output, $matches)) {
    echo "✓ City of Yakima: Found {$matches[1]} events\n";
} else {
    echo "✗ City of Yakima: No events found\n";
}

echo "\n==== Current Active Sources ====\n";

$stmt = $db->query("
    SELECT id, name, active, 
           (SELECT COUNT(*) FROM events WHERE source_id = cs.id) as event_count
    FROM calendar_sources cs 
    WHERE active = 1 
    ORDER BY id
");

while ($source = $stmt->fetch()) {
    echo "#{$source['id']} {$source['name']} - {$source['event_count']} events\n";
}

echo "\n✓ Configuration update complete!\n";