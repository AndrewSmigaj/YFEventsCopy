#!/usr/bin/env php
<?php

/**
 * Test all calendar sources, especially the newly added ones
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';

use YFEvents\Models\CalendarSourceModel;

// Colors for output
define('GREEN', "\033[0;32m");
define('RED', "\033[0;31m");
define('YELLOW', "\033[0;33m");
define('BLUE', "\033[0;34m");
define('RESET', "\033[0m");

function printHeader($text) {
    echo "\n" . BLUE . "==== $text ====" . RESET . "\n\n";
}

function printSuccess($text) {
    echo GREEN . "✓ " . RESET . "$text\n";
}

function printError($text) {
    echo RED . "✗ " . RESET . "$text\n";
}

function printWarning($text) {
    echo YELLOW . "⚠ " . RESET . "$text\n";
}

function printInfo($text) {
    echo BLUE . "ℹ " . RESET . "$text\n";
}

function testUrl($url) {
    $headers = @get_headers($url);
    if ($headers && strpos($headers[0], '200')) {
        return ['success' => true, 'status' => $headers[0]];
    } elseif ($headers) {
        return ['success' => false, 'status' => $headers[0]];
    } else {
        return ['success' => false, 'status' => 'No response'];
    }
}

function findEventContent($url) {
    $context = stream_context_create([
        'http' => [
            'timeout' => 10,
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
        ]
    ]);
    
    $content = @file_get_contents($url, false, $context);
    if (!$content) {
        return null;
    }
    
    // Look for common event-related keywords
    $keywords = ['event', 'calendar', 'show', 'performance', 'exhibit', 'class', 'workshop'];
    $found = [];
    
    foreach ($keywords as $keyword) {
        if (stripos($content, $keyword) !== false) {
            $found[] = $keyword;
        }
    }
    
    return [
        'size' => strlen($content),
        'keywords' => $found,
        'has_events' => count($found) > 0
    ];
}

printHeader("Testing All Calendar Sources");

$sourceModel = new CalendarSourceModel($db);

// Get all sources
$stmt = $db->query("SELECT * FROM calendar_sources ORDER BY id");
$sources = $stmt->fetchAll();

printInfo("Total sources: " . count($sources) . "\n");

// Test each source
foreach ($sources as $source) {
    printHeader("Testing: " . $source['name']);
    printInfo("ID: " . $source['id']);
    printInfo("Type: " . $source['scrape_type']);
    printInfo("URL: " . $source['url']);
    printInfo("Active: " . ($source['active'] ? 'Yes' : 'No'));
    
    // Test URL accessibility
    echo "\nTesting URL accessibility... ";
    $urlTest = testUrl($source['url']);
    
    if ($urlTest['success']) {
        printSuccess("URL is accessible (" . $urlTest['status'] . ")");
        
        // For HTML sources, check content
        if ($source['scrape_type'] === 'html') {
            echo "Analyzing page content... ";
            $content = findEventContent($source['url']);
            
            if ($content) {
                printSuccess("Page loaded (" . number_format($content['size']) . " bytes)");
                if ($content['has_events']) {
                    printSuccess("Found event-related content: " . implode(', ', $content['keywords']));
                    
                    // Test the configured selectors
                    $config = json_decode($source['scrape_config'], true);
                    if ($config && isset($config['selectors'])) {
                        echo "\nTesting CSS selectors:\n";
                        
                        $dom = new DOMDocument();
                        @$dom->loadHTML(file_get_contents($source['url'], false, stream_context_create([
                            'http' => ['timeout' => 10, 'user_agent' => 'Mozilla/5.0']
                        ])));
                        $xpath = new DOMXPath($dom);
                        
                        foreach ($config['selectors'] as $key => $selector) {
                            // Convert CSS to XPath (basic conversion)
                            $xpathSelector = str_replace('.', '//*[@class="', $selector);
                            $xpathSelector = str_replace(', ', '"] | //*[@class="', $xpathSelector);
                            if (strpos($selector, '.') === 0) {
                                $xpathSelector .= '"]';
                            }
                            
                            $nodes = $xpath->query($xpathSelector);
                            if ($nodes && $nodes->length > 0) {
                                printSuccess("  $key selector found {$nodes->length} elements");
                            } else {
                                printWarning("  $key selector found 0 elements");
                            }
                        }
                    }
                } else {
                    printWarning("No obvious event-related content found");
                }
            } else {
                printError("Failed to analyze content");
            }
        }
        
        // Actually try scraping if it's active or if we want to test
        if ($source['active'] || true) { // Test all sources
            echo "\nAttempting to scrape... ";
            
            // Temporarily activate the source for testing
            $wasActive = $source['active'];
            if (!$wasActive) {
                $db->exec("UPDATE calendar_sources SET active = 1 WHERE id = " . $source['id']);
            }
            
            // Run scraper
            $cmd = "php " . __DIR__ . "/../cron/scrape-events.php --source-id=" . $source['id'] . " 2>&1";
            $output = shell_exec($cmd);
            
            // Parse output for results
            if (strpos($output, 'SUCCESS') !== false) {
                if (preg_match('/Found (\d+) events, added (\d+)/', $output, $matches)) {
                    if ($matches[1] > 0) {
                        printSuccess("Scraping successful! Found {$matches[1]} events, added {$matches[2]}");
                    } else {
                        printWarning("Scraping completed but found 0 events");
                    }
                } else {
                    printSuccess("Scraping completed");
                }
            } else {
                printError("Scraping failed");
                if (preg_match('/\[ERROR\](.+)/', $output, $matches)) {
                    printError("  Error: " . trim($matches[1]));
                }
            }
            
            // Restore original state
            if (!$wasActive) {
                $db->exec("UPDATE calendar_sources SET active = 0 WHERE id = " . $source['id']);
            }
        }
        
    } else {
        printError("URL is NOT accessible (" . $urlTest['status'] . ")");
        printWarning("This source needs a new URL or is temporarily down");
    }
    
    echo "\n";
}

printHeader("Summary Recommendations");

// Get test results
$stmt = $db->query("
    SELECT 
        cs.*,
        (SELECT COUNT(*) FROM events WHERE source_id = cs.id) as event_count,
        (SELECT MAX(start_time) FROM scraping_logs WHERE source_id = cs.id) as last_test
    FROM calendar_sources cs
    ORDER BY cs.id
");
$results = $stmt->fetchAll();

$working = [];
$needsWork = [];
$broken = [];

foreach ($results as $source) {
    if ($source['event_count'] > 0) {
        $working[] = $source;
    } elseif ($source['last_test']) {
        $needsWork[] = $source;
    } else {
        $broken[] = $source;
    }
}

if (!empty($working)) {
    printSuccess("Working sources (" . count($working) . "):");
    foreach ($working as $s) {
        echo "  - {$s['name']} ({$s['event_count']} events)\n";
    }
}

if (!empty($needsWork)) {
    echo "\n";
    printWarning("Sources that need configuration (" . count($needsWork) . "):");
    foreach ($needsWork as $s) {
        echo "  - {$s['name']} (check selectors or URL)\n";
    }
}

if (!empty($broken)) {
    echo "\n";
    printError("Broken sources (" . count($broken) . "):");
    foreach ($broken as $s) {
        echo "  - {$s['name']} (invalid URL or configuration)\n";
    }
}

printHeader("Next Steps");
printInfo("1. Fix broken URLs by finding the correct event pages");
printInfo("2. Update HTML selectors based on actual page structure");
printInfo("3. Enable sources that are working correctly");
printInfo("4. Consider using browser developer tools to find correct CSS selectors");
printInfo("5. Some sites may require more complex scraping strategies");

echo "\n";