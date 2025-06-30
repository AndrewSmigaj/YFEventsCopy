#!/usr/bin/env php
<?php

/**
 * Debug Yakima Valley event scraper to check for time information
 */

require_once __DIR__ . '/../vendor/autoload.php';

$url = 'https://www.visityakima.com/yakima-valley-events';

echo "Fetching content from: $url\n\n";

$context = stream_context_create([
    'http' => [
        'timeout' => 30,
        'user_agent' => 'Mozilla/5.0 (compatible; YakimaFinds/1.0)'
    ]
]);

$content = file_get_contents($url, false, $context);

if (!$content) {
    die("Failed to fetch content\n");
}

echo "Content fetched successfully (" . strlen($content) . " bytes)\n\n";

// Look for event containers
$dom = new DOMDocument();
@$dom->loadHTML($content);
$xpath = new DOMXPath($dom);

// Try different selectors to find events
$selectors = [
    '//div[@class="event"]',
    '//article[@class="event"]',
    '//div[contains(@class, "event-item")]',
    '//div[contains(@class, "event-card")]',
    '//li[contains(@class, "event")]',
    '//div[@class="listing"]',
    '//div[contains(@class, "listing")]'
];

foreach ($selectors as $selector) {
    $nodes = $xpath->query($selector);
    if ($nodes->length > 0) {
        echo "Found {$nodes->length} nodes with selector: $selector\n";
        
        // Show first event's HTML
        if ($nodes->length > 0) {
            $firstNode = $nodes->item(0);
            echo "\nFirst event HTML:\n";
            echo "================\n";
            $html = $dom->saveHTML($firstNode);
            // Pretty print
            $html = str_replace('><', ">\n<", $html);
            echo $html . "\n\n";
            
            // Look for time information
            $timeSelectors = [
                './/time',
                './/*[contains(@class, "time")]',
                './/*[contains(@class, "when")]',
                './/*[contains(@class, "hours")]',
                './/*[contains(text(), ":")]',
                './/*[contains(text(), "am")]',
                './/*[contains(text(), "pm")]',
                './/*[contains(text(), "AM")]',
                './/*[contains(text(), "PM")]'
            ];
            
            echo "Looking for time information:\n";
            foreach ($timeSelectors as $timeSelector) {
                $timeNodes = $xpath->query($timeSelector, $firstNode);
                if ($timeNodes->length > 0) {
                    echo "  Found with $timeSelector: ";
                    for ($i = 0; $i < min(3, $timeNodes->length); $i++) {
                        echo trim($timeNodes->item($i)->textContent) . " | ";
                    }
                    echo "\n";
                }
            }
        }
        break;
    }
}

// Also check the raw HTML for time patterns
echo "\n\nSearching raw HTML for time patterns:\n";
echo "=====================================\n";

// Look for time patterns in the HTML
if (preg_match_all('/(\d{1,2}:\d{2}\s*[ap]m)/i', $content, $matches)) {
    echo "Found time patterns: " . implode(', ', array_unique($matches[0])) . "\n";
} else {
    echo "No time patterns (HH:MM am/pm) found in HTML\n";
}

// Check for event dates with times
if (preg_match_all('/(Monday|Tuesday|Wednesday|Thursday|Friday|Saturday|Sunday)[^<]{0,50}(\d{1,2}:\d{2})/i', $content, $matches)) {
    echo "\nFound day + time patterns:\n";
    for ($i = 0; $i < min(5, count($matches[0])); $i++) {
        echo "  - " . $matches[0][$i] . "\n";
    }
}