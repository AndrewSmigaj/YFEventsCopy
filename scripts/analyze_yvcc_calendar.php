#!/usr/bin/env php
<?php

/**
 * Analyze YVCC calendar structure to create proper selectors
 */

$url = 'https://www.yvcc.edu/calendar.php';

echo "Analyzing YVCC Calendar: $url\n\n";

$context = stream_context_create([
    'http' => [
        'timeout' => 15,
        'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
    ]
]);

$html = @file_get_contents($url, false, $context);

if (!$html) {
    die("Failed to fetch content\n");
}

echo "Content fetched (" . number_format(strlen($html)) . " bytes)\n\n";

// Look for JavaScript-loaded calendar
if (strpos($html, 'calendar') !== false || strpos($html, 'fullcalendar') !== false) {
    echo "Found calendar references in HTML\n";
}

// Look for AJAX endpoints or data sources
if (preg_match_all('/["\']([^"\']*(?:calendar|event)[^"\']*\.(?:php|json|ajax))["\']/', $html, $matches)) {
    echo "Found potential AJAX endpoints:\n";
    foreach (array_unique($matches[1]) as $endpoint) {
        echo "  - $endpoint\n";
    }
    echo "\n";
}

// Look for FullCalendar or similar
if (preg_match_all('/(fullcalendar|eventSources?|events:\s*["\']([^"\']+))/i', $html, $matches)) {
    echo "Found calendar framework references:\n";
    foreach (array_unique($matches[0]) as $match) {
        echo "  - $match\n";
    }
    echo "\n";
}

// Check for embedded calendar iframe
if (preg_match_all('/<iframe[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $html, $matches)) {
    echo "Found iframe calendars:\n";
    foreach ($matches[1] as $iframe) {
        echo "  - $iframe\n";
    }
    echo "\n";
}

// Look for event data in JSON
if (preg_match_all('/(?:events|calendar).*?(\{[^}]+\}|\[[^\]]+\])/i', $html, $matches)) {
    echo "Found potential event data structures:\n";
    foreach (array_slice($matches[0], 0, 3) as $match) {
        echo "  - " . substr($match, 0, 100) . "...\n";
    }
    echo "\n";
}

// Look for specific calendar divs or elements
$dom = new DOMDocument();
@$dom->loadHTML($html);
$xpath = new DOMXPath($dom);

// Common calendar container selectors
$selectors = [
    '//div[contains(@id, "calendar")]',
    '//div[contains(@class, "calendar")]',
    '//div[contains(@class, "event")]',
    '//div[contains(@id, "event")]',
    '//table[contains(@class, "calendar")]',
    '//script[contains(text(), "calendar")]'
];

foreach ($selectors as $selector) {
    $nodes = $xpath->query($selector);
    if ($nodes->length > 0) {
        echo "Found {$nodes->length} elements with selector: $selector\n";
        
        if ($nodes->length > 0 && strpos($selector, 'script') !== false) {
            $script = $nodes->item(0)->textContent;
            if (strlen($script) > 200) {
                echo "  Script content preview: " . substr(trim($script), 0, 200) . "...\n";
            }
        }
    }
}

// Final recommendations
echo "\n==== Recommendations ====\n";
echo "1. Check if this is a JavaScript-loaded calendar (like FullCalendar)\n";
echo "2. Look for AJAX endpoints that serve event data\n";
echo "3. Check if there's an iCal feed available\n";
echo "4. Consider if this requires headless browser scraping\n";
echo "5. Check the page source in a browser's developer tools\n\n";