<?php
/**
 * Add new calendar sources from provided URLs
 * Run this script to add sources we don't already have
 */

require_once __DIR__ . '/../config/database.php';

// New sources to add
$newSources = [
    [
        'name' => 'Capitol Theatre Events',
        'url' => 'https://capitoltheatre.org/events/',
        'scrape_type' => 'html',
        'scrape_config' => json_encode([
            'selectors' => [
                'event_container' => '.event-item, .event-listing, .tribe-events-list-event-row',
                'title' => '.event-title, .tribe-event-title, h2 a, h3 a',
                'datetime' => '.event-date, .tribe-event-date-start, .event-time',
                'location' => '.event-venue, .tribe-venue, .venue-name',
                'description' => '.event-description, .tribe-event-description',
                'url' => 'a[href]'
            ],
            'geographic_area' => 'Yakima',
            'event_types' => 'Theater, Arts, Entertainment'
        ]),
        'active' => 1
    ],
    [
        'name' => 'Downtown Yakima Events',
        'url' => 'https://downtownyakima.com/events/',
        'scrape_type' => 'html',
        'scrape_config' => json_encode([
            'selectors' => [
                'event_container' => '.event-item, .event-listing, .wp-block-group',
                'title' => '.event-title, h2, h3, .entry-title',
                'datetime' => '.event-date, .event-time, .date',
                'location' => '.event-venue, .location, .venue',
                'description' => '.event-description, .entry-content, .description'
            ],
            'geographic_area' => 'Downtown Yakima',
            'event_types' => 'Community events, Business events'
        ]),
        'active' => 1
    ],
    [
        'name' => 'WSU Extension Yakima Events',
        'url' => 'https://extension.wsu.edu/yakima/events/',
        'scrape_type' => 'html',
        'scrape_config' => json_encode([
            'selectors' => [
                'event_container' => '.event-item, .event-listing, .field-content',
                'title' => '.event-title, h2, h3, .field-name-title',
                'datetime' => '.event-date, .date-display-single, .field-name-field-date',
                'location' => '.event-venue, .field-name-field-location',
                'description' => '.event-description, .field-name-body'
            ],
            'geographic_area' => 'Yakima',
            'event_types' => 'Educational, Agricultural, Extension programs'
        ]),
        'active' => 1
    ],
    [
        'name' => 'Visit Yakima Valley - Granger',
        'url' => 'https://visityakimavalley.org/yakima-valley-event-location/Granger',
        'scrape_type' => 'html',
        'scrape_config' => json_encode([
            'selectors' => [
                'event_container' => '.event-item, .event-listing, .post',
                'title' => '.event-title, h2, h3, .entry-title',
                'datetime' => '.event-date, .event-time, .date',
                'location' => '.event-venue, .location',
                'description' => '.event-description, .entry-content'
            ],
            'geographic_area' => 'Granger',
            'event_types' => 'Tourism, Local events'
        ]),
        'active' => 1
    ],
    [
        'name' => 'Central Washington Agricultural Museum',
        'url' => 'https://www.centralwaagmuseum.org/agricultural-museum-events',
        'scrape_type' => 'html',
        'scrape_config' => json_encode([
            'selectors' => [
                'event_container' => '.event-item, .event-listing, .post',
                'title' => '.event-title, h2, h3, .entry-title',
                'datetime' => '.event-date, .event-time, .date',
                'location' => '.event-venue, .location',
                'description' => '.event-description, .entry-content'
            ],
            'geographic_area' => 'Union Gap',
            'event_types' => 'Educational, Agricultural, Museum events'
        ]),
        'active' => 1
    ],
    [
        'name' => 'Eventbrite Yakima',
        'url' => 'https://www.eventbrite.com/d/wa--yakima/events/',
        'scrape_type' => 'eventbrite',
        'scrape_config' => json_encode([
            'api_endpoint' => 'https://www.eventbrite.com/api/v3/events/search/',
            'location' => 'Yakima, WA',
            'radius' => '25mi',
            'selectors' => [
                'event_container' => '.event-card, .eds-event-card',
                'title' => '.event-title, .eds-event-card__formatted-name',
                'datetime' => '.event-datetime, .eds-event-card__sub-title',
                'location' => '.event-location, .card-text--truncated',
                'description' => '.event-description, .eds-text-bs',
                'url' => 'a[href]'
            ],
            'geographic_area' => 'Yakima Valley',
            'event_types' => 'Various community events'
        ]),
        'active' => 1
    ],
    [
        'name' => 'United Way of Central Washington',
        'url' => 'https://www.uwcw.org/calendar',
        'scrape_type' => 'html',
        'scrape_config' => json_encode([
            'selectors' => [
                'event_container' => '.event-item, .calendar-event, .post',
                'title' => '.event-title, h2, h3, .entry-title',
                'datetime' => '.event-date, .event-time, .date',
                'location' => '.event-venue, .location',
                'description' => '.event-description, .entry-content'
            ],
            'geographic_area' => 'Central Washington',
            'event_types' => 'Nonprofit, Community service, Fundraising'
        ]),
        'active' => 1
    ],
    [
        'name' => 'Visit Yakima - Main Events',
        'url' => 'https://www.visityakima.com/yakima-valley-events',
        'scrape_type' => 'html',
        'scrape_config' => json_encode([
            'selectors' => [
                'event_container' => '.event-item, .event-listing, .post',
                'title' => '.event-title, h2, h3, .entry-title',
                'datetime' => '.event-date, .event-time, .date',
                'location' => '.event-venue, .location',
                'description' => '.event-description, .entry-content'
            ],
            'geographic_area' => 'Yakima Valley',
            'event_types' => 'Tourism, Recreation, Cultural events'
        ]),
        'active' => 1
    ],
    [
        'name' => 'Visit Yakima - Arts and Culture',
        'url' => 'https://www.visityakima.com/yakima-valley-events-category/Arts%20and%20Culture',
        'scrape_type' => 'html',
        'scrape_config' => json_encode([
            'selectors' => [
                'event_container' => '.event-item, .event-listing, .post',
                'title' => '.event-title, h2, h3, .entry-title',
                'datetime' => '.event-date, .event-time, .date',
                'location' => '.event-venue, .location',
                'description' => '.event-description, .entry-content'
            ],
            'geographic_area' => 'Yakima Valley',
            'event_types' => 'Arts, Culture, Museums, Galleries'
        ]),
        'active' => 1
    ],
    [
        'name' => 'Visit Yakima - Festivals',
        'url' => 'https://www.visityakima.com/yakima-valley-events-category/Festivals',
        'scrape_type' => 'html',
        'scrape_config' => json_encode([
            'selectors' => [
                'event_container' => '.event-item, .event-listing, .post',
                'title' => '.event-title, h2, h3, .entry-title',
                'datetime' => '.event-date, .event-time, .date',
                'location' => '.event-venue, .location',
                'description' => '.event-description, .entry-content'
            ],
            'geographic_area' => 'Yakima Valley',
            'event_types' => 'Festivals, Celebrations'
        ]),
        'active' => 1
    ],
    [
        'name' => 'Yakima County Events',
        'url' => 'https://www.yakimacounty.us/2926/Events',
        'scrape_type' => 'html',
        'scrape_config' => json_encode([
            'selectors' => [
                'event_container' => '.event-item, .calendar-event, .module',
                'title' => '.event-title, h2, h3, .module-title',
                'datetime' => '.event-date, .event-time, .date',
                'location' => '.event-venue, .location',
                'description' => '.event-description, .module-content'
            ],
            'geographic_area' => 'Yakima County',
            'event_types' => 'Government, Public meetings, Community events'
        ]),
        'active' => 1
    ],
    [
        'name' => 'Yakima County Calendar',
        'url' => 'https://www.yakimacounty.us/Calendar.aspx',
        'scrape_type' => 'html',
        'scrape_config' => json_encode([
            'selectors' => [
                'event_container' => '.calendar-event, .event-item, .rgRow',
                'title' => '.event-title, .calendar-title, td a',
                'datetime' => '.event-date, .calendar-date, .date',
                'location' => '.event-venue, .location',
                'description' => '.event-description, .calendar-description'
            ],
            'geographic_area' => 'Yakima County',
            'event_types' => 'Government meetings, Public events'
        ]),
        'active' => 1
    ],
    [
        'name' => 'Yakima Herald-Republic Calendar',
        'url' => 'https://www.yakimaherald.com/calendar/',
        'scrape_type' => 'html',
        'scrape_config' => json_encode([
            'selectors' => [
                'event_container' => '.event-item, .calendar-event, .asset',
                'title' => '.event-title, h2, h3, .asset-headline',
                'datetime' => '.event-date, .event-time, .date',
                'location' => '.event-venue, .location',
                'description' => '.event-description, .asset-summary'
            ],
            'geographic_area' => 'Yakima Valley',
            'event_types' => 'Community events, News events'
        ]),
        'active' => 1
    ],
    [
        'name' => 'City of Yakima Calendar',
        'url' => 'https://www.yakimawa.gov/media/calendar/',
        'scrape_type' => 'html',
        'scrape_config' => json_encode([
            'selectors' => [
                'event_container' => '.event-item, .calendar-event, .module',
                'title' => '.event-title, h2, h3, .module-title',
                'datetime' => '.event-date, .event-time, .date',
                'location' => '.event-venue, .location',
                'description' => '.event-description, .module-content'
            ],
            'geographic_area' => 'Yakima',
            'event_types' => 'City meetings, Municipal events'
        ]),
        'active' => 1
    ],
    [
        'name' => 'YVCOG Calendar',
        'url' => 'https://www.yvcog.us/Calendar.aspx',
        'scrape_type' => 'html',
        'scrape_config' => json_encode([
            'selectors' => [
                'event_container' => '.calendar-event, .event-item, .rgRow',
                'title' => '.event-title, .calendar-title, td a',
                'datetime' => '.event-date, .calendar-date, .date',
                'location' => '.event-venue, .location',
                'description' => '.event-description, .calendar-description'
            ],
            'geographic_area' => 'Yakima Valley',
            'event_types' => 'Government, Council meetings'
        ]),
        'active' => 1
    ],
    [
        'name' => 'Yakima County Community Events',
        'url' => 'https://yakimacounty.us/2887/Community-Events-Calendar',
        'scrape_type' => 'html',
        'scrape_config' => json_encode([
            'selectors' => [
                'event_container' => '.event-item, .calendar-event, .module',
                'title' => '.event-title, h2, h3, .module-title',
                'datetime' => '.event-date, .event-time, .date',
                'location' => '.event-venue, .location',
                'description' => '.event-description, .module-content'
            ],
            'geographic_area' => 'Yakima County',
            'event_types' => 'Community events, Public activities'
        ]),
        'active' => 1
    ],
    [
        'name' => 'Yakima Valley Libraries Events',
        'url' => 'https://yvl.libcal.com/',
        'scrape_type' => 'html',
        'scrape_config' => json_encode([
            'selectors' => [
                'event_container' => '.event-item, .s-lc-ea-event, .fc-event',
                'title' => '.event-title, .s-lc-ea-ttl, .fc-title',
                'datetime' => '.event-date, .s-lc-ea-dtm, .fc-time',
                'location' => '.event-venue, .s-lc-ea-loc, .location',
                'description' => '.event-description, .s-lc-ea-desc'
            ],
            'geographic_area' => 'Yakima Valley',
            'event_types' => 'Library events, Educational programs, Community activities'
        ]),
        'active' => 1
    ]
];

echo "Starting to add new calendar sources...\n\n";

try {
    // Get existing sources to avoid duplicates
    $stmt = $pdo->query("SELECT url FROM calendar_sources");
    $existingUrls = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $added = 0;
    $skipped = 0;
    
    foreach ($newSources as $source) {
        // Check if URL already exists
        if (in_array($source['url'], $existingUrls)) {
            echo "SKIP: {$source['name']} - URL already exists\n";
            $skipped++;
            continue;
        }
        
        // Insert new source
        $stmt = $pdo->prepare("
            INSERT INTO calendar_sources (name, url, scrape_type, scrape_config, active, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        
        $result = $stmt->execute([
            $source['name'],
            $source['url'], 
            $source['scrape_type'],
            $source['scrape_config'],
            $source['active']
        ]);
        
        if ($result) {
            echo "ADD: {$source['name']} - {$source['url']}\n";
            $added++;
        } else {
            echo "ERROR: Failed to add {$source['name']}\n";
        }
    }
    
    echo "\n=== SUMMARY ===\n";
    echo "Sources added: $added\n";
    echo "Sources skipped (already exist): $skipped\n";
    echo "Total sources processed: " . count($newSources) . "\n";
    
    // Show total source count
    $stmt = $pdo->query("SELECT COUNT(*) FROM calendar_sources");
    $totalSources = $stmt->fetchColumn();
    echo "Total sources in database: $totalSources\n\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

echo "Script completed successfully!\n";
echo "You can now test the new sources in the admin interface at:\n";
echo "- /admin/scrapers.php\n";
echo "- /admin/calendar/sources.php\n";
?>