<?php

/**
 * Script to fetch and analyze individual event pages for time information
 */

require_once __DIR__ . '/../vendor/autoload.php';

// Test a few different event URLs
$eventUrls = [
    'https://www.visityakima.com/events/49736-A-Night-of-Flights-and-Bites-at-State-Fair-Park/',
    'https://www.visityakima.com/events/49737-Burgers-Brews-and-Bingo-at-The-Public-House-of-Yakima-East/',
    'https://www.visityakima.com/events/49666-Trivia-at-Single-Hill-Brewing-Co/'
];

foreach ($eventUrls as $url) {
    echo "\n=== Fetching: $url ===\n";
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $html = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        echo "Failed to fetch (HTTP $httpCode)\n";
        continue;
    }
    
    $dom = new DOMDocument();
    @$dom->loadHTML($html, LIBXML_NOERROR);
    $xpath = new DOMXPath($dom);
    
    // Look for various time indicators
    $searches = [
        '//div[@class="eventInfo"]' => 'Event info div',
        '//div[contains(@class, "event-details")]' => 'Event details',
        '//div[contains(@class, "eventDate")]' => 'Event date div',
        '//p[contains(text(), "pm") or contains(text(), "am") or contains(text(), "PM") or contains(text(), "AM")]' => 'Paragraphs with am/pm',
        '//div[@id="eventDetails"]//p' => 'Event details paragraphs',
        '//div[@class="content"]//p[position() <= 5]' => 'First 5 content paragraphs',
        '//*[contains(text(), ":") and (contains(text(), "am") or contains(text(), "pm") or contains(text(), "AM") or contains(text(), "PM"))]' => 'Any element with time',
        '//meta[@property="event:start_time"]' => 'Meta event start time',
        '//script[@type="application/ld+json"]' => 'JSON-LD structured data'
    ];
    
    foreach ($searches as $query => $desc) {
        $nodes = $xpath->query($query);
        if ($nodes && $nodes->length > 0) {
            echo "\n$desc (found " . $nodes->length . "):\n";
            foreach ($nodes as $node) {
                $text = trim($node->textContent);
                if (strlen($text) > 200) {
                    $text = substr($text, 0, 200) . '...';
                }
                echo "  → " . str_replace(["\n", "\r", "\t"], ' ', $text) . "\n";
                
                // If it's JSON-LD, try to parse it
                if ($node->nodeName === 'script' && strpos($text, '"@type"') !== false) {
                    $json = json_decode($text, true);
                    if ($json) {
                        echo "    Parsed JSON-LD:\n";
                        if (isset($json['startDate'])) echo "      startDate: " . $json['startDate'] . "\n";
                        if (isset($json['endDate'])) echo "      endDate: " . $json['endDate'] . "\n";
                        if (isset($json['doorTime'])) echo "      doorTime: " . $json['doorTime'] . "\n";
                    }
                }
            }
        }
    }
    
    // Extract main content and look for time patterns
    $mainContent = $xpath->query('//div[@class="content" or @id="content" or contains(@class, "main-content")]')->item(0);
    if ($mainContent) {
        $contentText = $mainContent->textContent;
        
        // Time patterns to search for
        $patterns = [
            '/(\d{1,2}):(\d{2})\s*(am|pm|AM|PM)/i' => 'Standard time format',
            '/(\d{1,2})\s*(am|pm|AM|PM)/i' => 'Hour only format',
            '/from\s+(\d{1,2}(?::\d{2})?)\s*(am|pm|AM|PM)?\s*(?:to|until|-)\s*(\d{1,2}(?::\d{2})?)\s*(am|pm|AM|PM)?/i' => 'Time range',
            '/starts?\s+at\s+(\d{1,2}(?::\d{2})?)\s*(am|pm|AM|PM)?/i' => 'Starts at pattern',
            '/doors?\s+(?:open)?\s*at\s+(\d{1,2}(?::\d{2})?)\s*(am|pm|AM|PM)?/i' => 'Doors open pattern'
        ];
        
        echo "\nTime patterns found in content:\n";
        foreach ($patterns as $pattern => $desc) {
            if (preg_match_all($pattern, $contentText, $matches)) {
                echo "  $desc: " . implode(', ', array_unique($matches[0])) . "\n";
            }
        }
    }
    
    echo "\n" . str_repeat('=', 80) . "\n";
}