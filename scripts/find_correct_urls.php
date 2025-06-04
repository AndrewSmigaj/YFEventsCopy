#!/usr/bin/env php
<?php

/**
 * Find correct URLs for local event sources
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';

// Test various URL patterns for each venue
$venues = [
    'Downtown Yakima' => [
        'base' => 'downtownyakima.com',
        'patterns' => [
            'https://www.downtownyakima.com',
            'https://downtownyakima.com',
            'https://www.downtownyakima.com/calendar',
            'https://www.downtownyakima.com/events-calendar',
            'https://www.downtownyakima.org',
            'https://downtownyakima.org'
        ]
    ],
    'Yakima Valley Museum' => [
        'base' => 'yakimavalleymuseum.org',
        'patterns' => [
            'https://www.yakimavalleymuseum.org',
            'https://yakimavalleymuseum.org',
            'https://www.yakimavalleymuseum.org/events-calendar',
            'https://www.yakimavalleymuseum.org/calendar',
            'https://www.yakimavalleymuseum.org/visit/events'
        ]
    ],
    'Yakima Convention Center' => [
        'base' => 'yakimaconventioncenter.com',
        'patterns' => [
            'https://www.yakimaconventioncenter.com',
            'https://yakimaconventioncenter.com',
            'https://www.yakimaconventioncenter.com/calendar',
            'https://www.yakimaconventioncenter.com/events-calendar',
            'https://www.sunshinecountryconventioncenter.com',
            'https://www.yakimaconventioncenter.org'
        ]
    ],
    'Capitol Theatre' => [
        'base' => 'capitoltheatre.org',
        'patterns' => [
            'https://www.capitoltheatre.org',
            'https://capitoltheatre.org',
            'https://www.capitoltheatreyakima.org',
            'https://www.capitoltheatre.org/calendar',
            'https://www.capitoltheatre.org/shows',
            'https://www.capitoltheatre.org/events-tickets'
        ]
    ],
    'Wine Yakima Valley' => [
        'base' => 'wineyakimavalley.org',
        'patterns' => [
            'https://www.wineyakimavalley.org',
            'https://wineyakimavalley.org',
            'https://www.yakimavalleywine.com',
            'https://www.wineyakimavalley.org/events-calendar',
            'https://www.wineyakimavalley.org/calendar'
        ]
    ],
    'City of Yakima' => [
        'base' => 'yakimawa.gov',
        'patterns' => [
            'https://www.yakimawa.gov',
            'https://yakimawa.gov',
            'https://www.yakimawa.gov/calendar',
            'https://www.yakimawa.gov/events-calendar',
            'https://www.yakimawa.gov/community/events'
        ]
    ],
    'Yakima Valley College' => [
        'base' => 'yvcc.edu',
        'patterns' => [
            'https://www.yvcc.edu',
            'https://yvcc.edu',
            'https://www.yvcc.edu/calendar',
            'https://www.yvcc.edu/events-calendar',
            'https://www.yvcc.edu/student-life/events'
        ]
    ]
];

echo "\n==== Finding Correct URLs ====\n\n";

$updates = [];

foreach ($venues as $name => $config) {
    echo "Testing $name...\n";
    $foundUrl = null;
    
    foreach ($config['patterns'] as $url) {
        echo "  Trying: $url ... ";
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $content = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        curl_close($ch);
        
        if ($httpCode == 200) {
            echo "✓ Found! (Final URL: $finalUrl)\n";
            
            // Check if it has event-related content
            if (preg_match('/(event|calendar|show|performance|exhibit)/i', $content)) {
                echo "    Contains event-related content ✓\n";
                $foundUrl = $finalUrl;
                
                // Try to find event listings
                if (preg_match_all('/<a[^>]+href=["\']([^"\']+)["\'][^>]*>.*?(event|calendar|show|ticket)/i', $content, $matches)) {
                    echo "    Found event-related links:\n";
                    $eventLinks = array_unique($matches[1]);
                    foreach (array_slice($eventLinks, 0, 3) as $link) {
                        if (strpos($link, 'http') !== 0) {
                            $parsed = parse_url($finalUrl);
                            $link = $parsed['scheme'] . '://' . $parsed['host'] . '/' . ltrim($link, '/');
                        }
                        echo "      - $link\n";
                    }
                }
                
                $updates[$name] = $foundUrl;
                break;
            } else {
                echo "    No obvious event content\n";
            }
        } else {
            echo "Failed (HTTP $httpCode)\n";
        }
    }
    
    if (!$foundUrl) {
        echo "  ✗ No working URL found\n";
    }
    
    echo "\n";
}

echo "\n==== URL Updates Needed ====\n\n";

if (empty($updates)) {
    echo "No working URLs found. Manual investigation needed.\n";
} else {
    foreach ($updates as $name => $url) {
        echo "$name: $url\n";
        
        // Find the source ID
        $stmt = $db->prepare("SELECT id FROM calendar_sources WHERE name LIKE ?");
        $stmt->execute(['%' . explode(' ', $name)[0] . '%']);
        $source = $stmt->fetch();
        
        if ($source) {
            // Update the URL
            $stmt = $db->prepare("UPDATE calendar_sources SET url = ? WHERE id = ?");
            $stmt->execute([$url, $source['id']]);
            echo "  ✓ Updated source ID {$source['id']}\n";
        }
    }
}

echo "\n==== Manual Investigation Needed ====\n\n";
echo "Some venues may:\n";
echo "- Use Facebook Events instead of their own calendar\n";
echo "- Require JavaScript to load events (not scrapable with simple HTML)\n";
echo "- Use ticketing platforms like Ticketmaster\n";
echo "- Have changed their domain or closed\n";
echo "\nConsider searching for:\n";
echo "- '{venue name} events yakima'\n";
echo "- '{venue name} calendar'\n";
echo "- '{venue name} facebook'\n\n";