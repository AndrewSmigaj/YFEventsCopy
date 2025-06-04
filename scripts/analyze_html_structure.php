#!/usr/bin/env php
<?php

/**
 * Analyze HTML structure of specific event pages to create proper selectors
 */

$urls = [
    'Capitol Theatre' => 'https://capitoltheatre.org/events/',
    'Downtown Yakima' => 'https://downtownyakima.com/events/',
    'City of Yakima' => 'https://www.yakimawa.gov/calendar/',
    'Yakima Valley College' => 'https://www.yvcc.edu/calendar.php'
];

foreach ($urls as $name => $url) {
    echo "\n==== Analyzing: $name ====\n";
    echo "URL: $url\n\n";
    
    $context = stream_context_create([
        'http' => [
            'timeout' => 15,
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
        ]
    ]);
    
    $html = @file_get_contents($url, false, $context);
    
    if (!$html) {
        echo "Failed to fetch content\n";
        continue;
    }
    
    echo "Content fetched (" . number_format(strlen($html)) . " bytes)\n\n";
    
    $dom = new DOMDocument();
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);
    
    // Look for event containers
    $containerSelectors = [
        '//article[contains(@class, "event")]',
        '//div[contains(@class, "event")]',
        '//li[contains(@class, "event")]',
        '//div[contains(@class, "calendar")]',
        '//article[contains(@class, "post")]',
        '//div[contains(@class, "entry")]'
    ];
    
    $foundContainers = [];
    
    foreach ($containerSelectors as $selector) {
        $nodes = $xpath->query($selector);
        if ($nodes->length > 0) {
            $foundContainers[$selector] = $nodes->length;
            echo "Found {$nodes->length} containers with: $selector\n";
            
            // Show first container's structure
            if ($nodes->length > 0) {
                $firstNode = $nodes->item(0);
                $html = $dom->saveHTML($firstNode);
                
                // Extract just the relevant parts
                if (strlen($html) > 500) {
                    $html = substr($html, 0, 500) . "...";
                }
                
                echo "Sample container HTML:\n";
                echo str_repeat("-", 40) . "\n";
                echo $html . "\n";
                echo str_repeat("-", 40) . "\n\n";
            }
        }
    }
    
    if (empty($foundContainers)) {
        echo "No obvious event containers found. Looking for other patterns...\n\n";
        
        // Look for date patterns
        if (preg_match_all('/(\w+\s+\d{1,2},?\s+\d{4}|\d{1,2}\/\d{1,2}\/\d{4})/i', $html, $matches)) {
            echo "Found date patterns: " . implode(', ', array_slice(array_unique($matches[0]), 0, 5)) . "\n";
        }
        
        // Look for time patterns
        if (preg_match_all('/\d{1,2}:\d{2}\s*[ap]m/i', $html, $matches)) {
            echo "Found time patterns: " . implode(', ', array_slice(array_unique($matches[0]), 0, 5)) . "\n";
        }
        
        // Look for table structure
        $tables = $xpath->query('//table');
        if ($tables->length > 0) {
            echo "Found {$tables->length} table(s) - might be calendar layout\n";
        }
    }
    
    echo "\n";
}

echo "\n==== Recommendations ====\n\n";
echo "Based on the analysis above:\n";
echo "1. Update CSS selectors to match the actual HTML structure\n";
echo "2. Some sites may use JavaScript to load events (not scrapable)\n";
echo "3. Consider checking if sites offer RSS feeds or API endpoints\n";
echo "4. Facebook integration might be easier for some venues\n\n";