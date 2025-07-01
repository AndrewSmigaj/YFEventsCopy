<?php

/**
 * Enhanced Scraper Debugging Script
 * This script tests calendar sources with detailed logging to identify issues
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';

use YFEvents\Scrapers\EventScraper;
use YFEvents\Models\CalendarSourceModel;

// ANSI color codes for output
$colors = [
    'reset' => "\033[0m",
    'red' => "\033[0;31m",
    'green' => "\033[0;32m",
    'yellow' => "\033[0;33m",
    'blue' => "\033[0;34m",
    'purple' => "\033[0;35m",
    'cyan' => "\033[0;36m"
];

function logMessage($message, $level = 'INFO', $color = null) {
    global $colors;
    
    $timestamp = date('Y-m-d H:i:s');
    $colorCode = $color ? $colors[$color] : '';
    $reset = $color ? $colors['reset'] : '';
    
    echo "[{$timestamp}] [{$level}] {$colorCode}{$message}{$reset}\n";
}

function testFetchUrl($url) {
    logMessage("Testing URL: $url", 'INFO', 'cyan');
    
    $context = stream_context_create([
        'http' => [
            'timeout' => 30,
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
            'follow_location' => true,
            'max_redirects' => 3,
            'header' => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                'Accept-Language: en-US,en;q=0.5',
                'Accept-Encoding: gzip, deflate',
                'Cache-Control: max-age=0'
            ]
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false
        ]
    ]);
    
    // Use curl for better error handling
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_NOBODY, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    $info = curl_getinfo($ch);
    
    curl_close($ch);
    
    if ($error) {
        logMessage("CURL Error: $error", 'ERROR', 'red');
        return false;
    }
    
    logMessage("HTTP Code: $httpCode", 'INFO');
    logMessage("Final URL: " . $info['url'], 'INFO');
    
    if ($httpCode >= 300 && $httpCode < 400) {
        logMessage("Redirect detected. Following to: " . $info['url'], 'WARN', 'yellow');
    }
    
    if ($httpCode !== 200) {
        logMessage("Failed to fetch URL. HTTP Code: $httpCode", 'ERROR', 'red');
        return false;
    }
    
    $headerSize = $info['header_size'];
    $body = substr($response, $headerSize);
    
    logMessage("Content size: " . strlen($body) . " bytes", 'INFO', 'green');
    
    return $body;
}

function analyzeYakimaValleyContent($content) {
    logMessage("\n=== Analyzing Yakima Valley Events Page ===", 'INFO', 'purple');
    
    $dom = new DOMDocument();
    @$dom->loadHTML($content, LIBXML_NOERROR);
    $xpath = new DOMXPath($dom);
    
    // Look for event-like structures
    $searches = [
        '//li[contains(@class, "event")]' => 'event class li elements',
        '//div[contains(@class, "event")]' => 'event class div elements',
        '//article[contains(@class, "event")]' => 'event class article elements',
        '//li[a[@href and h2 and h3]]' => 'li with link, h2 and h3 (original selector)',
        '//a[h2 and h3]' => 'links with h2 and h3',
        '//div[@class="calendar"]//li' => 'calendar div li elements',
        '//*[contains(text(), "2025")]' => 'elements containing 2025',
        '//h2[following-sibling::h3 or preceding-sibling::h3]' => 'h2 elements near h3',
        '//a[@href and contains(@href, "event")]' => 'links containing "event"'
    ];
    
    foreach ($searches as $query => $description) {
        try {
            $nodes = $xpath->query($query);
            $count = $nodes ? $nodes->length : 0;
            if ($count > 0) {
                logMessage("Found $count $description", 'SUCCESS', 'green');
                
                // Show first few examples
                for ($i = 0; $i < min(3, $count); $i++) {
                    $node = $nodes->item($i);
                    $text = trim($node->textContent);
                    if (strlen($text) > 100) {
                        $text = substr($text, 0, 100) . '...';
                    }
                    logMessage("  Example " . ($i + 1) . ": " . $text, 'INFO', 'cyan');
                }
            } else {
                logMessage("Found 0 $description", 'WARN', 'yellow');
            }
        } catch (Exception $e) {
            logMessage("Error with query '$query': " . $e->getMessage(), 'ERROR', 'red');
        }
    }
    
    // Check page structure
    logMessage("\n=== Page Structure Analysis ===", 'INFO', 'purple');
    
    // Look for common event date patterns
    $datePatterns = [
        '/\b(January|February|March|April|May|June|July|August|September|October|November|December)\s+\d{1,2}\b/i',
        '/\b\d{1,2}\/\d{1,2}\/\d{2,4}\b/',
        '/\b\d{4}-\d{2}-\d{2}\b/'
    ];
    
    foreach ($datePatterns as $pattern) {
        preg_match_all($pattern, $content, $matches);
        if (count($matches[0]) > 0) {
            logMessage("Found " . count($matches[0]) . " date patterns matching: " . $pattern, 'SUCCESS', 'green');
            logMessage("  First few: " . implode(', ', array_slice($matches[0], 0, 5)), 'INFO');
        }
    }
    
    // Save a sample of the page for manual inspection
    $sampleFile = __DIR__ . '/yakima_valley_sample.html';
    file_put_contents($sampleFile, $content);
    logMessage("Saved page sample to: $sampleFile", 'INFO', 'blue');
}

// Main execution
try {
    logMessage("=== Enhanced Calendar Source Scraper Debug ===", 'INFO', 'blue');
    
    $sourceModel = new CalendarSourceModel($db);
    $scraper = new EventScraper($db);
    
    // Get all sources
    $stmt = $db->query("SELECT * FROM calendar_sources WHERE active = 1 ORDER BY id");
    $sources = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    logMessage("Found " . count($sources) . " active sources\n", 'INFO', 'blue');
    
    foreach ($sources as $source) {
        logMessage("=== Testing Source: {$source['name']} ===", 'INFO', 'purple');
        logMessage("ID: {$source['id']}", 'INFO');
        logMessage("Type: {$source['scrape_type']}", 'INFO');
        logMessage("URL: {$source['url']}", 'INFO');
        
        // First test if we can fetch the URL
        $content = testFetchUrl($source['url']);
        
        if ($content === false) {
            logMessage("Failed to fetch content. Skipping scraping test.", 'ERROR', 'red');
            continue;
        }
        
        // For Yakima Valley scraper, do detailed analysis
        if ($source['scrape_type'] === 'yakima_valley') {
            analyzeYakimaValleyContent($content);
        }
        
        // Test actual scraping
        logMessage("\nTesting scraper...", 'INFO', 'cyan');
        $result = $scraper->scrapeSource($source);
        
        if ($result['success']) {
            logMessage("Scraping successful!", 'SUCCESS', 'green');
            logMessage("Events found: {$result['events_found']}", 'INFO', 'green');
            logMessage("Events added: {$result['events_added']}", 'INFO', 'green');
        } else {
            logMessage("Scraping failed: {$result['error']}", 'ERROR', 'red');
        }
        
        logMessage("\n" . str_repeat('-', 80) . "\n", 'INFO');
    }
    
} catch (Exception $e) {
    logMessage("Fatal error: " . $e->getMessage(), 'ERROR', 'red');
    logMessage("Stack trace:\n" . $e->getTraceAsString(), 'ERROR', 'red');
}

// Check recent scraping logs
logMessage("\n=== Recent Scraping Log Entries ===", 'INFO', 'blue');
$stmt = $db->query("
    SELECT sl.*, cs.name as source_name 
    FROM scraping_logs sl 
    JOIN calendar_sources cs ON sl.source_id = cs.id 
    ORDER BY sl.created_at DESC 
    LIMIT 10
");

$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($logs as $log) {
    $status = $log['status'] === 'success' ? 'SUCCESS' : 'FAILED';
    $color = $log['status'] === 'success' ? 'green' : 'red';
    logMessage("{$log['source_name']} - {$status} - {$log['events_found']} found, {$log['events_added']} added", $status, $color);
    if ($log['error_message']) {
        logMessage("  Error: {$log['error_message']}", 'ERROR', 'red');
    }
}