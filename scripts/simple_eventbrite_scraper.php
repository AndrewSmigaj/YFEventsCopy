<?php
/**
 * Simple Eventbrite Web Scraper
 * =============================
 * 
 * Simple approach that mimics manual browsing to get around API restrictions
 * Uses the actual search page URL format that works in browsers
 * 
 * Usage:
 *   php simple_eventbrite_scraper.php
 */

declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line.\n");
}

require_once dirname(__DIR__) . '/www/html/refactor/vendor/autoload.php';

class SimpleEventbriteScraper
{
    private bool $debug = true;
    
    public function __construct()
    {
        $this->log("Simple Eventbrite Scraper initialized");
    }
    
    private function log(string $message, string $level = 'INFO'): void
    {
        $timestamp = date('Y-m-d H:i:s');
        echo "[{$timestamp}] [{$level}] {$message}\n";
    }
    
    private function makeRequest(string $url): ?string
    {
        $this->log("Fetching: {$url}");
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language: en-US,en;q=0.5',
                'Accept-Encoding: gzip, deflate',
                'Connection: keep-alive',
                'Upgrade-Insecure-Requests: 1',
            ],
            CURLOPT_ENCODING => '',
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($response === false || $httpCode !== 200) {
            $this->log("Failed to fetch {$url} (HTTP {$httpCode})", 'ERROR');
            return null;
        }
        
        $this->log("Successfully fetched " . strlen($response) . " bytes", 'SUCCESS');
        return $response;
    }
    
    public function testUrls(): void
    {
        $this->log("Testing various Eventbrite URL formats");
        
        $testUrls = [
            // Format 1: Direct search
            'https://www.eventbrite.com/d/wa--yakima/events/',
            
            // Format 2: Search with location parameter
            'https://www.eventbrite.com/d/events/location?q=yakima',
            
            // Format 3: Alternative format
            'https://www.eventbrite.com/d/online/yakima/',
            
            // Format 4: Browse by location
            'https://www.eventbrite.com/d/wa--yakima/',
            
            // Format 5: Events in area
            'https://www.eventbrite.com/e/events/local/yakima-wa',
            
            // Format 6: Browse events
            'https://www.eventbrite.com/browse/yakima-wa',
        ];
        
        foreach ($testUrls as $i => $url) {
            $this->log("Test " . ($i + 1) . ": {$url}");
            
            $html = $this->makeRequest($url);
            
            if ($html) {
                // Save the response for inspection
                $filename = "eventbrite_test_" . ($i + 1) . ".html";
                file_put_contents($filename, $html);
                
                // Look for event indicators
                $eventCount = substr_count(strtolower($html), 'event');
                $this->log("  Saved to {$filename} - Contains 'event' {$eventCount} times");
                
                // Look for specific patterns
                if (strpos($html, 'data-event-id') !== false) {
                    $this->log("  ✅ Found data-event-id attributes!", 'SUCCESS');
                }
                
                if (preg_match_all('/\/e\/[^"\'>\s]+/', $html, $matches)) {
                    $this->log("  ✅ Found " . count($matches[0]) . " event URLs!", 'SUCCESS');
                    
                    // Show first few URLs found
                    foreach (array_slice($matches[0], 0, 3) as $eventUrl) {
                        $this->log("    - https://www.eventbrite.com{$eventUrl}");
                    }
                }
                
                if (strpos($html, 'No events found') !== false) {
                    $this->log("  ❌ Page says 'No events found'", 'WARNING');
                }
                
                if (strpos($html, 'Access Denied') !== false || strpos($html, 'cloudflare') !== false) {
                    $this->log("  ❌ Access denied or Cloudflare blocking", 'ERROR');
                }
                
                $this->log("  Response size: " . number_format(strlen($html)) . " bytes");
                
            } else {
                $this->log("  ❌ Failed to fetch", 'ERROR');
            }
            
            $this->log("");
            sleep(2); // Be polite
        }
        
        $this->log("URL testing complete. Check the HTML files to see which format works best.");
    }
}

// Run the test
$scraper = new SimpleEventbriteScraper();
$scraper->testUrls();
?>