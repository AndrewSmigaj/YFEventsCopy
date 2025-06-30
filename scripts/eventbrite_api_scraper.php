<?php
/**
 * Eventbrite Location-Based Event Scraper
 * ========================================
 * 
 * Scrapes Eventbrite events by location using their search interface
 * Works by mimicking browser requests to their search API endpoints
 * 
 * Usage:
 *   php eventbrite_api_scraper.php                                # Default: Yakima
 *   php eventbrite_api_scraper.php --location="Yakima, WA"        # Custom location
 *   php eventbrite_api_scraper.php --pages=5                      # Limit pages (default: 10)
 *   php eventbrite_api_scraper.php --csv-only                     # CSV only
 *   php eventbrite_api_scraper.php --debug                        # Debug mode
 */

declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line.\n");
}

require_once dirname(__DIR__) . '/www/html/refactor/vendor/autoload.php';

class EventbriteApiScraper
{
    private string $baseUrl = 'https://www.eventbrite.com';
    private string $location = 'Yakima';
    private int $maxPages = 10;
    private array $events = [];
    private bool $debug = false;
    private bool $csvOnly = false;
    private ?PDO $pdo = null;
    
    // Session cookies and headers for maintaining state
    private array $cookies = [];
    private string $csrfToken = '';
    
    public function __construct(array $options = [])
    {
        $this->location = $options['location'] ?? 'Yakima';
        $this->maxPages = $options['pages'] ?? 10;
        $this->debug = $options['debug'] ?? false;
        $this->csvOnly = $options['csv-only'] ?? false;
        
        if (!$this->csvOnly) {
            $this->setupDatabase();
        }
        
        $this->log("Eventbrite API Scraper initialized for location: {$this->location}");
    }
    
    private function setupDatabase(): void
    {
        try {
            $config = require dirname(__DIR__) . '/www/html/refactor/config/database.php';
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
        
        $logFile = dirname(__DIR__) . '/logs/eventbrite_api_scraper.log';
        @file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
    }
    
    private function makeRequest(string $url, array $options = []): ?array
    {
        $ch = curl_init();
        
        $defaultHeaders = [
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Accept: application/json, text/javascript, */*; q=0.01',
            'Accept-Language: en-US,en;q=0.9',
            'Accept-Encoding: gzip, deflate, br',
            'Connection: keep-alive',
            'Sec-Fetch-Dest: empty',
            'Sec-Fetch-Mode: cors',
            'Sec-Fetch-Site: same-origin',
            'X-Requested-With: XMLHttpRequest'
        ];
        
        if (!empty($this->cookies)) {
            $cookieString = http_build_query($this->cookies, '', '; ');
            $defaultHeaders[] = 'Cookie: ' . $cookieString;
        }
        
        if (!empty($this->csrfToken)) {
            $defaultHeaders[] = 'X-CSRFToken: ' . $this->csrfToken;
        }
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $options['timeout'] ?? 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_HTTPHEADER => array_merge($defaultHeaders, $options['headers'] ?? []),
            CURLOPT_ENCODING => '',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HEADERFUNCTION => [$this, 'parseHeader']
        ]);
        
        if (!empty($options['post_data'])) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $options['post_data']);
        }
        
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
            if ($this->debug) {
                $this->log("Response: " . substr($response, 0, 500), 'DEBUG');
            }
            return null;
        }
        
        // Try to decode JSON response
        $jsonData = json_decode($response, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $jsonData;
        }
        
        // Return raw response for non-JSON
        return ['raw' => $response, 'html' => $response];
    }
    
    private function parseHeader($ch, $header): int
    {
        if (strpos($header, 'Set-Cookie:') === 0) {
            $cookie = substr($header, 11);
            $parts = explode(';', $cookie);
            $cookiePair = explode('=', $parts[0], 2);
            if (count($cookiePair) === 2) {
                $this->cookies[trim($cookiePair[0])] = trim($cookiePair[1]);
            }
        }
        
        return strlen($header);
    }
    
    private function initializeSession(): bool
    {
        $this->log("Initializing Eventbrite session");
        
        // First, get the main page to establish session
        $response = $this->makeRequest($this->baseUrl);
        if (!$response) {
            return false;
        }
        
        // Look for CSRF token in HTML
        if (isset($response['html'])) {
            if (preg_match('/csrfToken["\']:\s*["\']([^"\']+)["\']/', $response['html'], $matches)) {
                $this->csrfToken = $matches[1];
                $this->log("Found CSRF token: " . substr($this->csrfToken, 0, 10) . "...");
            }
        }
        
        return true;
    }
    
    private function searchEvents(int $page = 1): ?array
    {
        $this->log("Searching events - Page {$page}");
        
        // Try different API endpoints
        $endpoints = [
            // Modern search API
            "/api/v3/destination/search/?expand=event_sales_status,image,primary_venue,saves,ticket_availability,primary_organizer&location.address={$this->location}&page={$page}",
            
            // Legacy search endpoint
            "/json/search?q=&location.address={$this->location}&start_date=all&page={$page}",
            
            // Alternative endpoint
            "/api/v1/events/search/?location.address={$this->location}&page={$page}&expand=venue,organizer"
        ];
        
        foreach ($endpoints as $endpoint) {
            $url = $this->baseUrl . $endpoint;
            $this->log("Trying endpoint: {$endpoint}", 'DEBUG');
            
            $response = $this->makeRequest($url);
            
            if ($response && is_array($response)) {
                // Check for events in various response formats
                if (isset($response['events'])) {
                    $this->log("Found events array with " . count($response['events']) . " events");
                    return $response;
                }
                
                if (isset($response['data']['events'])) {
                    $this->log("Found nested events array with " . count($response['data']['events']) . " events");
                    return $response;
                }
                
                if (isset($response['results'])) {
                    $this->log("Found results array with " . count($response['results']) . " events");
                    return ['events' => $response['results']];
                }
            }
            
            // Small delay between endpoint attempts
            sleep(1);
        }
        
        return null;
    }
    
    private function parseEventData(array $eventData): ?array
    {
        try {
            $event = [];
            
            // Handle different response formats
            $event['title'] = $eventData['name']['text'] ?? $eventData['name'] ?? $eventData['title'] ?? '';
            
            // Dates
            $start = $eventData['start'] ?? [];
            $end = $eventData['end'] ?? [];
            
            if (is_array($start)) {
                $event['start_date'] = $this->formatDate($start['utc'] ?? $start['local'] ?? '');
            } else {
                $event['start_date'] = $this->formatDate($start);
            }
            
            if (is_array($end)) {
                $event['end_date'] = $this->formatDate($end['utc'] ?? $end['local'] ?? '');
            } else {
                $event['end_date'] = $this->formatDate($end);
            }
            
            // Venue
            $venue = $eventData['venue'] ?? [];
            if (is_array($venue)) {
                $event['venue_name'] = $venue['name'] ?? '';
                $address = $venue['address'] ?? [];
                if (is_array($address)) {
                    $city = $address['city'] ?? '';
                    $region = $address['region'] ?? '';
                    $event['venue_location'] = trim($city . ', ' . $region, ', ');
                } else {
                    $event['venue_location'] = (string)$address;
                }
            } else {
                $event['venue_name'] = (string)$venue;
                $event['venue_location'] = $this->location;
            }
            
            // Organizer
            $organizer = $eventData['organizer'] ?? [];
            if (is_array($organizer)) {
                $event['organizer'] = $organizer['name'] ?? '';
            } else {
                $event['organizer'] = (string)$organizer;
            }
            
            // URL
            $event['url'] = $eventData['url'] ?? '';
            if (empty($event['url']) && isset($eventData['id'])) {
                $event['url'] = $this->baseUrl . '/e/' . $eventData['id'];
            }
            
            // Description
            $description = $eventData['description'] ?? [];
            if (is_array($description)) {
                $event['description'] = $description['text'] ?? '';
            } else {
                $event['description'] = (string)$description;
            }
            
            // Image
            $logo = $eventData['logo'] ?? [];
            if (is_array($logo)) {
                $event['image_url'] = $logo['url'] ?? '';
            } else {
                $event['image_url'] = (string)$logo;
            }
            
            // Validate required fields
            if (empty($event['title'])) {
                return null;
            }
            
            return $event;
            
        } catch (Exception $e) {
            $this->log("Error parsing event data: " . $e->getMessage(), 'ERROR');
            return null;
        }
    }
    
    private function formatDate(string $dateStr): string
    {
        if (empty($dateStr)) {
            return '';
        }
        
        try {
            $dt = new DateTime($dateStr);
            return $dt->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            $this->log("Date parse error for '{$dateStr}': " . $e->getMessage(), 'WARNING');
            return $dateStr;
        }
    }
    
    public function saveToDatabase(array $event): bool
    {
        if (!$this->pdo) {
            return false;
        }
        
        try {
            $externalId = 'eventbrite_' . md5($event['url']);
            
            // Check if exists
            $stmt = $this->pdo->prepare("SELECT id FROM events WHERE external_event_id = ?");
            $stmt->execute([$externalId]);
            
            if ($stmt->fetch()) {
                $this->log("Event already exists: {$event['title']}", 'INFO');
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
                "Organizer: {$event['organizer']}\n\n{$event['description']}\n\nSource: Eventbrite",
                $externalId,
                $event['url']
            ]);
            
            if ($result) {
                $this->log("✅ Saved to database: {$event['title']}", 'SUCCESS');
                return true;
            }
            
        } catch (Exception $e) {
            $this->log("Database error: {$e->getMessage()}", 'ERROR');
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
            $this->log("Could not open CSV file: {$filename}", 'ERROR');
            return;
        }
        
        $headers = ['title', 'start_date', 'end_date', 'venue_name', 'venue_location', 'organizer', 'url', 'image_url', 'description'];
        fputcsv($handle, $headers);
        
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
        $this->log("Starting Eventbrite scraper for location: {$this->location}");
        
        // Initialize session
        if (!$this->initializeSession()) {
            $this->log("Failed to initialize session", 'ERROR');
            return;
        }
        
        // Search through pages
        $totalEvents = 0;
        $emptyPages = 0;
        
        for ($page = 1; $page <= $this->maxPages; $page++) {
            $this->log("Processing page {$page}/{$this->maxPages}");
            
            $searchResults = $this->searchEvents($page);
            
            if (!$searchResults || empty($searchResults['events'])) {
                $emptyPages++;
                $this->log("No events found on page {$page}");
                
                // Stop if we get 3 consecutive empty pages
                if ($emptyPages >= 3) {
                    $this->log("Stopping after 3 consecutive empty pages");
                    break;
                }
                
                continue;
            }
            
            $emptyPages = 0; // Reset counter
            $pageEvents = $searchResults['events'];
            $this->log("Found " . count($pageEvents) . " events on page {$page}");
            
            foreach ($pageEvents as $eventData) {
                $event = $this->parseEventData($eventData);
                
                if ($event) {
                    $this->events[] = $event;
                    $totalEvents++;
                    
                    if (!$this->csvOnly) {
                        $this->saveToDatabase($event);
                    }
                    
                    if ($this->debug) {
                        $this->log("  ✅ {$event['title']}", 'DEBUG');
                    }
                } else {
                    if ($this->debug) {
                        $this->log("  ❌ Failed to parse event", 'DEBUG');
                    }
                }
            }
            
            // Be polite - pause between pages
            if ($page < $this->maxPages) {
                sleep(2);
            }
        }
        
        // Save to CSV
        $this->saveToCsv();
        
        $this->log("Scraping complete! Found {$totalEvents} events across " . min($page - 1, $this->maxPages) . " pages.", 'SUCCESS');
    }
}

// Parse command line arguments
$options = [];
for ($i = 1; $i < $argc; $i++) {
    if (strpos($argv[$i], '--location=') === 0) {
        $options['location'] = substr($argv[$i], 11);
    } elseif (strpos($argv[$i], '--pages=') === 0) {
        $options['pages'] = (int)substr($argv[$i], 8);
    } elseif ($argv[$i] === '--csv-only') {
        $options['csv-only'] = true;
    } elseif ($argv[$i] === '--debug') {
        $options['debug'] = true;
    } elseif ($argv[$i] === '--help') {
        echo "Eventbrite Location-Based Event Scraper\n\n";
        echo "Usage: php eventbrite_api_scraper.php [options]\n\n";
        echo "Options:\n";
        echo "  --location=\"City, State\"   Location to search (default: Yakima)\n";
        echo "  --pages=N                 Max pages to scrape (default: 10)\n";
        echo "  --csv-only                Export to CSV only (don't save to database)\n";
        echo "  --debug                   Enable verbose debug logging\n";
        echo "  --help                    Show this help message\n\n";
        echo "Examples:\n";
        echo "  php eventbrite_api_scraper.php\n";
        echo "  php eventbrite_api_scraper.php --location=\"Yakima, WA\" --pages=5\n";
        echo "  php eventbrite_api_scraper.php --debug --csv-only\n\n";
        echo "Output:\n";
        echo "  - yakima_eventbrite_events.csv\n";
        echo "  - Events saved to database (unless --csv-only)\n";
        exit(0);
    }
}

// Run the scraper
try {
    $scraper = new EventbriteApiScraper($options);
    $scraper->run();
} catch (Exception $e) {
    echo "Fatal error: " . $e->getMessage() . "\n";
    exit(1);
}
?>