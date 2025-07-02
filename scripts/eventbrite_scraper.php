<?php
/**
 * Eventbrite Scraper for Yakima Events
 * ====================================
 * 
 * Scrapes Eventbrite for Yakima events and can save directly to the database
 * or export to CSV. Uses cURL and DOMDocument for robust scraping.
 * 
 * Usage:
 *   php eventbrite_scraper.php                    # Save to database
 *   php eventbrite_scraper.php --csv-only         # Export to CSV only
 *   php eventbrite_scraper.php --debug            # Enable verbose logging
 */

declare(strict_types=1);

// Check if running from command line
if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line.\n");
}

// Bootstrap application and load all dependencies
require_once __DIR__ . '/../config/app-root.php';

class EventbriteScraper
{
    private string $baseUrl = 'https://www.eventbrite.com';
    private string $searchUrl = 'https://www.eventbrite.com/d/wa--yakima/events/';
    private array $events = [];
    private bool $debug = false;
    private bool $csvOnly = false;
    private ?PDO $pdo = null;
    
    public function __construct(array $options = [])
    {
        $this->debug = $options['debug'] ?? false;
        $this->csvOnly = $options['csv-only'] ?? false;
        
        // Setup database connection if not CSV-only
        if (!$this->csvOnly) {
            $this->setupDatabase();
        }
        
        $this->log("Eventbrite Scraper initialized");
    }
    
    private function setupDatabase(): void
    {
        try {
            $config = require dirname(__DIR__) . '/config/database.php';
            $dbConfig = $config['database'];
            
            $dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['name']};charset=utf8mb4";
            $this->pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            
            $this->log("Database connection established");
        } catch (Exception $e) {
            $this->log("Database connection failed: " . $e->getMessage(), 'ERROR');
            $this->csvOnly = true;
        }
    }
    
    private function log(string $message, string $level = 'INFO'): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] [{$level}] {$message}\n";
        
        echo $logMessage;
        
        // Also log to file
        $logFile = dirname(__DIR__) . '/logs/eventbrite_scraper.log';
        @file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
    }
    
    private function makeRequest(string $url, int $timeout = 30): ?string
    {
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (YakimaFinds Event Calendar Bot) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                'Accept-Language: en-US,en;q=0.5',
                'Accept-Encoding: gzip, deflate',
                'Connection: keep-alive',
                'Upgrade-Insecure-Requests: 1',
            ],
            CURLOPT_ENCODING => '', // Enable all supported encodings
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        curl_close($ch);
        
        if ($response === false) {
            $this->log("cURL error for {$url}: {$error}", 'ERROR');
            return null;
        }
        
        if ($httpCode !== 200) {
            $this->log("HTTP {$httpCode} for {$url}", 'WARNING');
            return null;
        }
        
        return $response;
    }
    
    public function scrapeSearchResults(): array
    {
        $this->log("Fetching search results from: {$this->searchUrl}");
        
        $html = $this->makeRequest($this->searchUrl);
        if (!$html) {
            $this->log("Failed to fetch search results", 'ERROR');
            return [];
        }
        
        // Save debug HTML if debug mode
        if ($this->debug) {
            file_put_contents('debug_eventbrite_search.html', $html);
            $this->log("Saved search page HTML for debugging");
        }
        
        $dom = new DOMDocument();
        @$dom->loadHTML($html);
        $xpath = new DOMXPath($dom);
        
        $eventLinks = [];
        
        // Multiple XPath queries to find event links
        $xpathQueries = [
            '//a[contains(@href, "/e/")]/@href',
            '//a[@data-event-id]/@href',
            '//*[@class="event-card"]//a/@href',
            '//*[contains(@class, "search-event-card")]//a/@href',
            '//*[@data-testid="event-card"]//a/@href'
        ];
        
        foreach ($xpathQueries as $query) {
            $nodes = $xpath->query($query);
            foreach ($nodes as $node) {
                $href = $node->nodeValue;
                if ($href && strpos($href, '/e/') !== false) {
                    // Convert relative URLs to absolute
                    if (strpos($href, 'http') !== 0) {
                        $href = $this->baseUrl . $href;
                    }
                    
                    // Clean URL (remove query parameters)
                    $cleanUrl = strtok($href, '?');
                    $eventLinks[$cleanUrl] = true;
                }
            }
        }
        
        $eventLinks = array_keys($eventLinks);
        $this->log("Found " . count($eventLinks) . " unique event links");
        
        if ($this->debug && $eventLinks) {
            $this->log("Sample URLs found:");
            foreach (array_slice($eventLinks, 0, 3) as $url) {
                $this->log("  - {$url}");
            }
        }
        
        return $eventLinks;
    }
    
    private function extractJsonLd(DOMDocument $dom): ?array
    {
        $xpath = new DOMXPath($dom);
        $scripts = $xpath->query('//script[@type="application/ld+json"]');
        
        foreach ($scripts as $script) {
            $jsonContent = $script->textContent;
            
            try {
                $data = json_decode($jsonContent, true);
                
                if (is_array($data)) {
                    // Handle arrays of structured data
                    foreach ($data as $item) {
                        if (isset($item['@type']) && $item['@type'] === 'Event') {
                            return $this->parseJsonLdEvent($item);
                        }
                    }
                } elseif (isset($data['@type']) && $data['@type'] === 'Event') {
                    return $this->parseJsonLdEvent($data);
                }
            } catch (Exception $e) {
                $this->log("JSON-LD parse error: " . $e->getMessage(), 'WARNING');
                continue;
            }
        }
        
        return null;
    }
    
    private function parseJsonLdEvent(array $data): array
    {
        $event = [];
        
        // Basic info
        $event['title'] = $data['name'] ?? '';
        $event['description'] = $data['description'] ?? '';
        $event['url'] = $data['url'] ?? '';
        
        // Dates
        $event['start_date'] = $this->formatDate($data['startDate'] ?? '');
        $event['end_date'] = $this->formatDate($data['endDate'] ?? '');
        
        // Location
        $location = $data['location'] ?? [];
        if (is_array($location)) {
            $event['venue_name'] = $location['name'] ?? '';
            $address = $location['address'] ?? [];
            if (is_array($address)) {
                $city = $address['addressLocality'] ?? '';
                $state = $address['addressRegion'] ?? '';
                $event['venue_location'] = trim($city . ', ' . $state, ', ');
            } else {
                $event['venue_location'] = (string)$address;
            }
        } else {
            $event['venue_name'] = (string)$location;
            $event['venue_location'] = '';
        }
        
        // Organizer
        $organizer = $data['organizer'] ?? [];
        if (is_array($organizer)) {
            $event['organizer'] = $organizer['name'] ?? '';
        } else {
            $event['organizer'] = (string)$organizer;
        }
        
        // Image
        $image = $data['image'] ?? '';
        if (is_array($image)) {
            $image = $image[0] ?? '';
        }
        if (is_array($image)) {
            $image = $image['url'] ?? '';
        }
        $event['image_url'] = (string)$image;
        
        return $event;
    }
    
    private function extractHtmlFallback(DOMDocument $dom, string $url): array
    {
        $xpath = new DOMXPath($dom);
        $event = ['url' => $url];
        
        // Title
        $titleQueries = [
            '//h1[contains(@class, "listing-hero-title")]',
            '//h1[@data-automation="event-title"]',
            '//*[@class="event-title"]//h1',
            '//h1'
        ];
        
        foreach ($titleQueries as $query) {
            $nodes = $xpath->query($query);
            if ($nodes->length > 0) {
                $event['title'] = trim($nodes->item(0)->textContent);
                break;
            }
        }
        $event['title'] = $event['title'] ?? '';
        
        // Dates - look for datetime attributes
        $dateQueries = [
            '//*[@datetime]/@datetime',
            '//*[contains(@class, "date")]/@datetime',
            '//*[contains(@class, "time")]/@datetime'
        ];
        
        $dates = [];
        foreach ($dateQueries as $query) {
            $nodes = $xpath->query($query);
            foreach ($nodes as $node) {
                $dateValue = $this->formatDate($node->nodeValue);
                if ($dateValue) {
                    $dates[] = $dateValue;
                }
            }
        }
        
        $event['start_date'] = $dates[0] ?? '';
        $event['end_date'] = $dates[1] ?? '';
        
        // Venue
        $venueQueries = [
            '//*[contains(@class, "venue-name")]',
            '//*[@data-automation="venue-name"]',
            '//*[contains(@class, "location")]/*[contains(@class, "name")]'
        ];
        
        foreach ($venueQueries as $query) {
            $nodes = $xpath->query($query);
            if ($nodes->length > 0) {
                $event['venue_name'] = trim($nodes->item(0)->textContent);
                break;
            }
        }
        $event['venue_name'] = $event['venue_name'] ?? '';
        
        // Location
        $locationQueries = [
            '//*[contains(@class, "venue-address")]',
            '//*[@data-automation="venue-address"]',
            '//*[contains(@class, "location")]/*[contains(@class, "address")]'
        ];
        
        foreach ($locationQueries as $query) {
            $nodes = $xpath->query($query);
            if ($nodes->length > 0) {
                $event['venue_location'] = trim($nodes->item(0)->textContent);
                break;
            }
        }
        $event['venue_location'] = $event['venue_location'] ?? '';
        
        // Organizer
        $organizerQueries = [
            '//*[contains(@class, "organizer-name")]',
            '//*[@data-automation="organizer-name"]'
        ];
        
        foreach ($organizerQueries as $query) {
            $nodes = $xpath->query($query);
            if ($nodes->length > 0) {
                $event['organizer'] = trim($nodes->item(0)->textContent);
                break;
            }
        }
        $event['organizer'] = $event['organizer'] ?? '';
        
        // Image
        $imgQueries = [
            '//*[contains(@class, "event-hero-image")]//img/@src',
            '//*[contains(@class, "listing-hero-image")]//img/@src',
            '//*[contains(@class, "event-image")]//img/@src'
        ];
        
        foreach ($imgQueries as $query) {
            $nodes = $xpath->query($query);
            if ($nodes->length > 0) {
                $src = $nodes->item(0)->nodeValue;
                if (strpos($src, 'http') !== 0) {
                    $src = $this->baseUrl . $src;
                }
                $event['image_url'] = $src;
                break;
            }
        }
        $event['image_url'] = $event['image_url'] ?? '';
        
        return $event;
    }
    
    private function formatDate(string $dateStr): string
    {
        if (empty($dateStr)) {
            return '';
        }
        
        try {
            // Handle ISO format
            if (strpos($dateStr, 'T') !== false) {
                $dt = new DateTime($dateStr);
                return $dt->format('Y-m-d H:i:s');
            }
            
            // Try to parse various formats
            $formats = [
                'Y-m-d H:i:s',
                'Y-m-d',
                'm/d/Y H:i',
                'm/d/Y',
                'M j, Y g:i A',
                'M j, Y'
            ];
            
            foreach ($formats as $format) {
                $dt = DateTime::createFromFormat($format, $dateStr);
                if ($dt !== false) {
                    return $dt->format('Y-m-d H:i:s');
                }
            }
            
            // Last resort - strtotime
            $timestamp = strtotime($dateStr);
            if ($timestamp !== false) {
                return date('Y-m-d H:i:s', $timestamp);
            }
            
        } catch (Exception $e) {
            $this->log("Date parse error for '{$dateStr}': " . $e->getMessage(), 'WARNING');
        }
        
        return $dateStr;
    }
    
    public function scrapeEventPage(string $url): ?array
    {
        $this->log("Scraping event: {$url}");
        
        $html = $this->makeRequest($url);
        if (!$html) {
            return null;
        }
        
        $dom = new DOMDocument();
        @$dom->loadHTML($html);
        
        // Try JSON-LD first
        $event = $this->extractJsonLd($dom);
        
        // Fall back to HTML parsing
        if (!$event || empty($event['title'])) {
            if ($this->debug) {
                $this->log("JSON-LD not found for {$url}, using HTML fallback");
            }
            $event = $this->extractHtmlFallback($dom, $url);
        }
        
        // Ensure URL is set
        $event['url'] = $url;
        
        // Clean and validate data
        $event['title'] = trim(strip_tags($event['title'] ?? ''));
        $event['venue_name'] = trim(strip_tags($event['venue_name'] ?? ''));
        $event['venue_location'] = trim(strip_tags($event['venue_location'] ?? ''));
        $event['organizer'] = trim(strip_tags($event['organizer'] ?? ''));
        
        // Skip events with no title
        if (empty($event['title'])) {
            $this->log("Skipping event with no title: {$url}", 'WARNING');
            return null;
        }
        
        return $event;
    }
    
    public function saveToDatabase(array $event): bool
    {
        if (!$this->pdo) {
            return false;
        }
        
        try {
            // Check if event already exists by URL
            $stmt = $this->pdo->prepare("SELECT id FROM events WHERE external_event_id = ?");
            $externalId = 'eventbrite_' . md5($event['url']);
            $stmt->execute([$externalId]);
            
            if ($stmt->fetch()) {
                $this->log("Event already exists in database: {$event['title']}", 'INFO');
                return true;
            }
            
            // Insert new event
            $sql = "INSERT INTO events (
                title, start_datetime, end_datetime, location, description, 
                status, external_event_id, source_url, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, 'pending', ?, ?, NOW(), NOW())";
            
            $stmt = $this->pdo->prepare($sql);
            $result = $stmt->execute([
                $event['title'],
                $event['start_date'] ?: null,
                $event['end_date'] ?: null,
                $event['venue_name'] . ($event['venue_location'] ? ', ' . $event['venue_location'] : ''),
                "Organizer: {$event['organizer']}\n\nEvent from Eventbrite.",
                $externalId,
                $event['url']
            ]);
            
            if ($result) {
                $this->log("✅ Saved to database: {$event['title']}", 'SUCCESS');
                return true;
            }
            
        } catch (Exception $e) {
            $this->log("Database error for {$event['title']}: " . $e->getMessage(), 'ERROR');
        }
        
        return false;
    }
    
    public function saveToCsv(string $filename = 'yakima_eventbrite_events.csv'): void
    {
        if (empty($this->events)) {
            $this->log("No events to save to CSV", 'WARNING');
            return;
        }
        
        $handle = fopen($filename, 'w');
        if (!$handle) {
            $this->log("Could not open CSV file for writing: {$filename}", 'ERROR');
            return;
        }
        
        // Write header
        $headers = ['title', 'start_date', 'end_date', 'venue_name', 'venue_location', 'organizer', 'url', 'image_url'];
        fputcsv($handle, $headers);
        
        // Write events
        foreach ($this->events as $event) {
            $row = [];
            foreach ($headers as $header) {
                $row[] = $event[$header] ?? '';
            }
            fputcsv($handle, $row);
        }
        
        fclose($handle);
        $this->log("✅ Saved " . count($this->events) . " events to {$filename}", 'SUCCESS');
    }
    
    public function run(): void
    {
        $this->log("Starting Eventbrite scraper for Yakima events");
        
        // Get event links
        $eventLinks = $this->scrapeSearchResults();
        
        if (empty($eventLinks)) {
            $this->log("No event links found. Exiting.", 'ERROR');
            return;
        }
        
        // Scrape each event
        $successCount = 0;
        $total = count($eventLinks);
        
        foreach ($eventLinks as $i => $url) {
            $this->log("Processing event " . ($i + 1) . "/{$total}");
            
            $event = $this->scrapeEventPage($url);
            
            if ($event) {
                $this->events[] = $event;
                
                // Save to database if not CSV-only
                if (!$this->csvOnly) {
                    $this->saveToDatabase($event);
                }
                
                $this->log("✅ Scraped: {$event['title']}");
                $successCount++;
            } else {
                $this->log("❌ Failed to scrape: {$url}", 'WARNING');
            }
            
            // Be polite - pause between requests
            if ($i < $total - 1) {
                sleep(2);
            }
        }
        
        // Save to CSV
        $this->saveToCsv();
        
        $this->log("Scraping complete! Successfully processed {$successCount}/{$total} events.", 'SUCCESS');
    }
}

// Parse command line arguments
$options = [];
for ($i = 1; $i < $argc; $i++) {
    switch ($argv[$i]) {
        case '--csv-only':
            $options['csv-only'] = true;
            break;
        case '--debug':
            $options['debug'] = true;
            break;
        case '--help':
            echo "Eventbrite Scraper for Yakima Events\n\n";
            echo "Usage: php eventbrite_scraper.php [options]\n\n";
            echo "Options:\n";
            echo "  --csv-only    Export to CSV only (don't save to database)\n";
            echo "  --debug       Enable verbose debug logging\n";
            echo "  --help        Show this help message\n\n";
            echo "Output:\n";
            echo "  - yakima_eventbrite_events.csv (always created)\n";
            echo "  - Events saved to database (unless --csv-only)\n";
            echo "  - Log file: logs/eventbrite_scraper.log\n";
            exit(0);
    }
}

// Run the scraper
try {
    $scraper = new EventbriteScraper($options);
    $scraper->run();
} catch (Exception $e) {
    echo "Fatal error: " . $e->getMessage() . "\n";
    exit(1);
}
?>