#!/usr/bin/env php
<?php

/**
 * Find better event sources to replace non-working ones
 */

$sources_to_test = [
    'Washington State University Tri-Cities' => [
        'base_url' => 'https://tricities.wsu.edu',
        'potential_feeds' => [
            'https://tricities.wsu.edu/events/',
            'https://tricities.wsu.edu/calendar/',
            'https://tricities.wsu.edu/events.ics',
            'https://tricities.wsu.edu/calendar.ics'
        ]
    ],
    'Heritage University' => [
        'base_url' => 'https://www.heritage.edu',
        'potential_feeds' => [
            'https://www.heritage.edu/events/',
            'https://www.heritage.edu/calendar/',
            'https://www.heritage.edu/events.ics'
        ]
    ],
    'Yakima Herald Events' => [
        'base_url' => 'https://www.yakimaherald.com',
        'potential_feeds' => [
            'https://www.yakimaherald.com/calendar/',
            'https://www.yakimaherald.com/events/',
            'https://www.yakimaherald.com/calendar.ics'
        ]
    ],
    'Central Washington University' => [
        'base_url' => 'https://www.cwu.edu',
        'potential_feeds' => [
            'https://www.cwu.edu/calendar/',
            'https://www.cwu.edu/events/',
            'https://www.cwu.edu/calendar.ics',
            'https://calendar.cwu.edu/events.ics'
        ]
    ],
    'Eventbrite Yakima' => [
        'base_url' => 'https://www.eventbrite.com',
        'potential_feeds' => [
            'https://www.eventbrite.com/d/wa--yakima/events/'
        ]
    ]
];

echo "Testing alternative event sources...\n\n";

foreach ($sources_to_test as $name => $config) {
    echo "=== Testing: $name ===\n";
    
    foreach ($config['potential_feeds'] as $url) {
        echo "  Checking: $url ... ";
        
        $headers = @get_headers($url);
        if ($headers && strpos($headers[0], '200')) {
            echo "✓ OK\n";
            
            // Check if it's an iCal feed
            if (strpos($url, '.ics') !== false) {
                $content = @file_get_contents($url, false, stream_context_create([
                    'http' => ['timeout' => 10]
                ]));
                
                if ($content && strpos($content, 'BEGIN:VCALENDAR') !== false) {
                    echo "    ✓ Valid iCal feed with events\n";
                    
                    // Count events
                    $event_count = substr_count($content, 'BEGIN:VEVENT');
                    echo "    Events found: $event_count\n";
                    
                    if ($event_count > 0) {
                        echo "    🎯 RECOMMENDED: This is a working iCal feed!\n";
                    }
                } else {
                    echo "    ✗ Not a valid iCal feed\n";
                }
            } else {
                // Check for HTML events page
                $content = @file_get_contents($url, false, stream_context_create([
                    'http' => ['timeout' => 10, 'user_agent' => 'Mozilla/5.0']
                ]));
                
                if ($content) {
                    $event_indicators = 0;
                    
                    // Look for event indicators
                    if (preg_match_all('/\b(event|calendar|show|concert|workshop|class|meeting)\b/i', $content)) {
                        $event_indicators++;
                    }
                    
                    if (preg_match_all('/\b(jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec)[a-z]* \d{1,2}/i', $content)) {
                        $event_indicators++;
                    }
                    
                    if (preg_match_all('/\d{1,2}:\d{2}\s*[ap]m/i', $content)) {
                        $event_indicators++;
                    }
                    
                    echo "    Event indicators: $event_indicators/3\n";
                    
                    if ($event_indicators >= 2) {
                        echo "    💡 Potential HTML scraping candidate\n";
                    }
                }
            }
        } else {
            echo "✗ " . ($headers ? $headers[0] : 'No response') . "\n";
        }
    }
    echo "\n";
}

echo "\n=== Checking for RSS/Atom feeds ===\n";

$rss_urls = [
    'https://tricities.wsu.edu/feed/',
    'https://www.heritage.edu/feed/',
    'https://www.yakimaherald.com/feed/',
    'https://www.cwu.edu/feed/'
];

foreach ($rss_urls as $url) {
    echo "Checking: $url ... ";
    $headers = @get_headers($url);
    if ($headers && strpos($headers[0], '200')) {
        echo "✓ OK\n";
        
        $content = @file_get_contents($url, false, stream_context_create([
            'http' => ['timeout' => 10]
        ]));
        
        if ($content && (strpos($content, '<rss') !== false || strpos($content, '<feed') !== false)) {
            echo "  ✓ Valid RSS/Atom feed\n";
            
            // Check for event-related content
            if (preg_match_all('/<title[^>]*>.*?(event|calendar|show).*?<\/title>/i', $content)) {
                echo "  🎯 Contains event-related content\n";
            }
        }
    } else {
        echo "✗ " . ($headers ? $headers[0] : 'No response') . "\n";
    }
}

echo "\n=== Recommendations ===\n";
echo "1. Look for iCal feeds first (they work best)\n";
echo "2. Check university calendars (they often have good feeds)\n";
echo "3. Consider RSS feeds for general events\n";
echo "4. Use Eventbrite API for broader event coverage\n\n";