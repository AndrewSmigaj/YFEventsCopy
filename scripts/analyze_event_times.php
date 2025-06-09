<?php

/**
 * Script to analyze event HTML structure and extract time information
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';

// Fetch a sample page
$url = 'https://www.visityakima.com/yakima-valley-events';
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$html = curl_exec($ch);
curl_close($ch);

// Parse HTML
$dom = new DOMDocument();
@$dom->loadHTML($html, LIBXML_NOERROR);
$xpath = new DOMXPath($dom);

echo "=== Analyzing Visit Yakima Event Structure ===\n\n";

// Find event nodes
$eventNodes = $xpath->query('//li[a[@href and h2 and h3]]');
echo "Found " . $eventNodes->length . " event nodes\n\n";

// Analyze first few events in detail
for ($i = 0; $i < min(3, $eventNodes->length); $i++) {
    $node = $eventNodes->item($i);
    echo "=== Event " . ($i + 1) . " ===\n";
    
    // Get the link element
    $linkNode = $xpath->query('.//a[@href]', $node)->item(0);
    if (!$linkNode) continue;
    
    // Extract title
    $titleNode = $xpath->query('.//h2', $linkNode)->item(0);
    if ($titleNode) {
        echo "Title: " . trim($titleNode->textContent) . "\n";
    }
    
    // Extract date from h3
    $dateNode = $xpath->query('.//h3', $linkNode)->item(0);
    if ($dateNode) {
        echo "Date (h3): " . trim($dateNode->textContent) . "\n";
    }
    
    // Look for any time-related information
    $allText = trim($linkNode->textContent);
    echo "\nFull text content:\n" . $allText . "\n";
    
    // Search for time patterns
    $timePatterns = [
        '/\b(\d{1,2}):(\d{2})\s*(am|pm|AM|PM)\b/i',
        '/\b(\d{1,2})\s*(am|pm|AM|PM)\b/i',
        '/\b(\d{1,2})[:.](\d{2})\s*-\s*(\d{1,2})[:.](\d{2})\s*(am|pm|AM|PM)?\b/i',
        '/\b(morning|afternoon|evening|night)\b/i',
        '/\b(breakfast|lunch|dinner)\b/i',
        '/\b(all day|all-day)\b/i'
    ];
    
    echo "\nSearching for time patterns:\n";
    foreach ($timePatterns as $pattern) {
        if (preg_match_all($pattern, $allText, $matches)) {
            echo "Found pattern: " . $pattern . "\n";
            echo "Matches: " . print_r($matches[0], true) . "\n";
        }
    }
    
    // Look for p tags that might contain time
    $pNodes = $xpath->query('.//p', $linkNode);
    foreach ($pNodes as $pNode) {
        $text = trim($pNode->textContent);
        if ($text) {
            echo "P tag: " . $text . "\n";
            // Check if this contains time info
            foreach ($timePatterns as $pattern) {
                if (preg_match($pattern, $text)) {
                    echo "  -> Contains time info!\n";
                }
            }
        }
    }
    
    echo "\n" . str_repeat('-', 50) . "\n";
}

// Try to fetch and analyze individual event pages
echo "\n=== Analyzing Individual Event Pages ===\n";

// Get first event URL
$firstEventNode = $eventNodes->item(0);
if ($firstEventNode) {
    $linkNode = $xpath->query('.//a[@href]', $firstEventNode)->item(0);
    if ($linkNode) {
        $eventUrl = $linkNode->getAttribute('href');
        if (strpos($eventUrl, 'http') !== 0) {
            $eventUrl = 'https://www.visityakima.com' . $eventUrl;
        }
        
        echo "Fetching event page: $eventUrl\n";
        
        // Fetch event page
        $ch = curl_init($eventUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $eventHtml = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode == 200 && $eventHtml) {
            $eventDom = new DOMDocument();
            @$eventDom->loadHTML($eventHtml, LIBXML_NOERROR);
            $eventXpath = new DOMXPath($eventDom);
            
            // Look for time information on event page
            $searches = [
                '//time' => 'time elements',
                '//*[contains(@class, "time")]' => 'elements with time class',
                '//*[contains(@class, "event-time")]' => 'elements with event-time class',
                '//*[contains(@class, "when")]' => 'elements with when class',
                '//*[contains(@itemprop, "startDate")]' => 'schema.org startDate',
                '//*[contains(@itemprop, "endDate")]' => 'schema.org endDate',
                '//dl/dt[contains(text(), "Time")]/following-sibling::dd[1]' => 'definition list time',
                '//div[contains(@class, "event-details")]' => 'event details div'
            ];
            
            foreach ($searches as $query => $desc) {
                $nodes = $eventXpath->query($query);
                if ($nodes && $nodes->length > 0) {
                    echo "\nFound $desc (" . $nodes->length . " elements):\n";
                    for ($j = 0; $j < min(3, $nodes->length); $j++) {
                        echo "  - " . trim($nodes->item($j)->textContent) . "\n";
                    }
                }
            }
            
            // Search entire page for time patterns
            $pageText = strip_tags($eventHtml);
            echo "\nSearching entire page for time patterns:\n";
            foreach ($timePatterns as $pattern) {
                if (preg_match_all($pattern, $pageText, $matches)) {
                    echo "Found: " . implode(', ', array_unique($matches[0])) . "\n";
                }
            }
        } else {
            echo "Failed to fetch event page (HTTP $httpCode)\n";
        }
    }
}