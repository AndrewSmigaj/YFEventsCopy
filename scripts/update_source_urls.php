#!/usr/bin/env php
<?php

/**
 * Update calendar source URLs with correct ones
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';

$updates = [
    // ID => new URL
    4 => 'https://downtownyakima.com/events/',  // Downtown Yakima Events
    5 => 'https://yvmuseum.org/',  // Yakima Valley Museum
    6 => null, // Yakima Convention Center - domain for sale, remove
    7 => 'https://capitoltheatre.org/events/',  // Capitol Theatre
    8 => null, // Wine Yakima Valley - site down
    9 => null, // Yakima Valley Sports - doesn't exist
    10 => 'https://www.yakimawa.gov/calendar/', // City of Yakima
    11 => 'https://www.yvcc.edu/calendar.php'  // Yakima Valley College
];

echo "Updating calendar source URLs...\n\n";

foreach ($updates as $id => $url) {
    $stmt = $db->prepare("SELECT name FROM calendar_sources WHERE id = ?");
    $stmt->execute([$id]);
    $source = $stmt->fetch();
    
    if (!$source) {
        continue;
    }
    
    echo "Source #{$id}: {$source['name']}\n";
    
    if ($url === null) {
        // Disable sources that don't have working URLs
        $stmt = $db->prepare("UPDATE calendar_sources SET active = 0 WHERE id = ?");
        $stmt->execute([$id]);
        echo "  ✗ No working URL found - disabled\n";
    } else {
        // Update URL
        $stmt = $db->prepare("UPDATE calendar_sources SET url = ? WHERE id = ?");
        $stmt->execute([$url, $id]);
        echo "  ✓ Updated URL to: $url\n";
        
        // Update configuration for specific sources
        if ($id == 7) { // Capitol Theatre
            $config = [
                'selectors' => [
                    'event_container' => 'article.tribe-events-calendar-list__event',
                    'title' => 'h3.tribe-events-calendar-list__event-title',
                    'description' => '.tribe-events-calendar-list__event-description',
                    'datetime' => 'time.tribe-events-calendar-list__event-datetime',
                    'location' => '.tribe-events-calendar-list__event-venue',
                    'url' => 'a.tribe-events-calendar-list__event-title-link'
                ]
            ];
            $stmt = $db->prepare("UPDATE calendar_sources SET scrape_config = ? WHERE id = ?");
            $stmt->execute([json_encode($config), $id]);
            echo "  ✓ Updated selectors for events calendar plugin\n";
        }
        
        if ($id == 10) { // City of Yakima
            $config = [
                'selectors' => [
                    'event_container' => '.calendar-item, .event-item',
                    'title' => '.event-title, h3, .title',
                    'description' => '.event-description, .description',
                    'datetime' => '.event-date, .date, time',
                    'location' => '.event-location, .location',
                    'url' => 'a[href*="event"]'
                ]
            ];
            $stmt = $db->prepare("UPDATE calendar_sources SET scrape_config = ? WHERE id = ?");
            $stmt->execute([json_encode($config), $id]);
        }
    }
    echo "\n";
}

// Show updated status
echo "\n==== Updated Source Status ====\n\n";

$stmt = $db->query("
    SELECT id, name, url, scrape_type, active 
    FROM calendar_sources 
    WHERE id >= 3 
    ORDER BY id
");

while ($source = $stmt->fetch()) {
    $status = $source['active'] ? '✓ Active' : '✗ Disabled';
    echo "#{$source['id']} {$source['name']}\n";
    echo "   URL: {$source['url']}\n";
    echo "   Status: $status\n\n";
}

echo "Next steps:\n";
echo "1. Test the updated sources with: php scripts/test_all_sources.php\n";
echo "2. Enable working sources in the admin panel\n";
echo "3. Update CSS selectors based on actual page structure\n";