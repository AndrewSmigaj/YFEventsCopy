<?php
/**
 * Meetup.com Event Scraper for Yakima
 * ===================================
 * 
 * Scrapes Meetup.com for events in the Yakima area
 * More permissive than Eventbrite for automated access
 * 
 * Usage:
 *   php meetup_scraper.php                    # Save to database
 *   php meetup_scraper.php --csv-only         # Export to CSV only
 *   php meetup_scraper.php --debug            # Enable verbose logging
 */

declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line.\n");
}

// Bootstrap application and load all dependencies
require_once __DIR__ . '/../config/app-root.php';

class MeetupScraper
{
    private array $events = [];
    private bool $debug = false;
    private bool $csvOnly = false;
    private ?PDO $pdo = null;
    
    private array $searchUrls = [
        'https://www.meetup.com/find/events/?allMeetups=false&keywords=&location=Yakima%2C+WA&radius=25',
        'https://www.meetup.com/find/?allMeetups=true&keywords=&location=Yakima%2C+WA&radius=50',
        'https://www.meetup.com/find/events/?location=Yakima%2C+Washington&distance=25',
    ];
    
    public function __construct(array $options = [])
    {
        $this->debug = $options['debug'] ?? false;
        $this->csvOnly = $options['csv-only'] ?? false;
        
        if (!$this->csvOnly) {
            $this->setupDatabase();
        }
        
        $this->log("Meetup Scraper initialized");
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
        
        $logFile = dirname(__DIR__) . '/logs/meetup_scraper.log';
        @file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
    }
    
    private function makeRequest(string $url): ?string
    {
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                'Accept-Language: en-US,en;q=0.5',
                'Accept-Encoding: gzip, deflate, br',
                'Connection: keep-alive',
                'Upgrade-Insecure-Requests: 1',
                'Sec-Fetch-Dest: document',
                'Sec-Fetch-Mode: navigate',
                'Sec-Fetch-Site: none',
            ],
            CURLOPT_ENCODING => '',
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
    
    private function extractJsonData(string $html): ?array
    {
        // Look for JSON-LD structured data
        if (preg_match('/<script type="application\/ld\+json"[^>]*>(.*?)<\/script>/s', $html, $matches)) {
            try {
                $jsonData = json_decode($matches[1], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    return $jsonData;
                }
            } catch (Exception $e) {
                $this->log("JSON-LD parse error: " . $e->getMessage(), 'WARNING');
            }
        }
        
        // Look for React/Next.js data
        if (preg_match('/window\.__INITIAL_STATE__\s*=\s*({.*?});/', $html, $matches)) {
            try {
                $jsonData = json_decode($matches[1], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    return $jsonData;
                }
            } catch (Exception $e) {
                $this->log("Initial state parse error: " . $e->getMessage(), 'WARNING');
            }
        }
        
        return null;
    }
    
    private function extractEventsFromHtml(string $html): array
    {
        $events = [];
        $dom = new DOMDocument();
        @$dom->loadHTML($html);
        $xpath = new DOMXPath($dom);
        
        // Look for event containers with various selectors
        $eventSelectors = [
            '//*[contains(@class, "event")]',
            '//*[contains(@class, "eventCard")]',
            '//*[contains(@class, "searchResult")]',
            '//*[contains(@data-testid, "event")]',
            '//*[contains(@class, "card") and .//time]'
        ];
        
        foreach ($eventSelectors as $selector) {
            $eventNodes = $xpath->query($selector);
            
            foreach ($eventNodes as $node) {
                $event = $this->extractEventFromNode($xpath, $node);
                if ($event && !empty($event['title'])) {
                    $events[] = $event;
                }
            }
        }
        
        return array_unique($events, SORT_REGULAR);
    }
    
    private function extractEventFromNode(DOMXPath $xpath, DOMElement $node): ?array
    {
        $event = [];
        
        // Title
        $titleSelectors = [
            './/h1', './/h2', './/h3', './/h4',
            './/*[contains(@class, "title")]',
            './/*[contains(@class, "name")]',
            './/*[contains(@class, "event")]//a'
        ];
        
        foreach ($titleSelectors as $selector) {
            $titleNodes = $xpath->query($selector, $node);
            if ($titleNodes->length > 0) {
                $title = trim($titleNodes->item(0)->textContent);
                if (!empty($title) && strlen($title) > 3) {
                    $event['title'] = $title;
                    break;
                }
            }
        }
        
        if (empty($event['title'])) {
            return null;
        }
        
        // Date/Time
        $dateSelectors = [
            './/*[@datetime]',
            './/time',
            './/*[contains(@class, "date")]',
            './/*[contains(@class, "time")]'
        ];
        
        $dates = [];
        foreach ($dateSelectors as $selector) {
            $dateNodes = $xpath->query($selector, $node);
            foreach ($dateNodes as $dateNode) {
                $datetime = $dateNode->getAttribute('datetime');
                if ($datetime) {
                    $dates[] = $this->formatDate($datetime);
                } else {
                    $dateText = trim($dateNode->textContent);
                    $parsedDate = $this->parseDate($dateText);
                    if ($parsedDate) {
                        $dates[] = $parsedDate;
                    }
                }
            }
        }
        
        $event['start_date'] = $dates[0] ?? '';
        $event['end_date'] = $dates[1] ?? '';
        
        // Location/Venue
        $locationSelectors = [
            './/*[contains(@class, "venue")]',
            './/*[contains(@class, "location")]',
            './/*[contains(@class, "address")]'
        ];
        
        foreach ($locationSelectors as $selector) {
            $locationNodes = $xpath->query($selector, $node);
            if ($locationNodes->length > 0) {
                $event['venue_name'] = trim($locationNodes->item(0)->textContent);
                break;
            }
        }
        $event['venue_name'] = $event['venue_name'] ?? '';
        $event['venue_location'] = 'Yakima, WA';
        
        // Organizer
        $organizerSelectors = [
            './/*[contains(@class, "organizer")]',
            './/*[contains(@class, "group")]',
            './/*[contains(@class, "host")]'
        ];
        
        foreach ($organizerSelectors as $selector) {
            $organizerNodes = $xpath->query($selector, $node);
            if ($organizerNodes->length > 0) {
                $event['organizer'] = trim($organizerNodes->item(0)->textContent);
                break;
            }
        }
        $event['organizer'] = $event['organizer'] ?? '';
        
        // URL
        $linkNodes = $xpath->query('.//a[@href]', $node);
        if ($linkNodes->length > 0) {
            $href = $linkNodes->item(0)->getAttribute('href');
            if (strpos($href, 'http') !== 0) {
                $href = 'https://www.meetup.com' . $href;
            }
            $event['url'] = $href;
        } else {
            $event['url'] = '';
        }
        
        // Description
        $descSelectors = [
            './/*[contains(@class, "description")]',
            './/*[contains(@class, "summary")]',
            './/p'
        ];
        
        foreach ($descSelectors as $selector) {
            $descNodes = $xpath->query($selector, $node);
            if ($descNodes->length > 0) {
                $event['description'] = trim($descNodes->item(0)->textContent);
                break;
            }
        }
        $event['description'] = $event['description'] ?? '';
        
        $event['image_url'] = '';
        
        return $event;
    }
    
    private function parseDate(string $dateText): ?string
    {
        if (empty($dateText)) {
            return null;
        }
        
        // Common patterns
        $patterns = [
            '/(\w+),?\s+(\w+)\s+(\d{1,2}),?\s+(\d{4})/', // Monday, January 15, 2024
            '/(\w+)\s+(\d{1,2}),?\s+(\d{4})/',           // January 15, 2024
            '/(\d{1,2})\/(\d{1,2})\/(\d{4})/',           // 1/15/2024
            '/(\d{4})-(\d{2})-(\d{2})/',                 // 2024-01-15
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $dateText)) {
                $timestamp = strtotime($dateText);
                if ($timestamp !== false) {
                    return date('Y-m-d H:i:s', $timestamp);
                }
            }
        }
        
        return null;
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
            $this->log("Date parse error: {$e->getMessage()}", 'WARNING');
            return $dateStr;
        }
    }
    
    public function saveToDatabase(array $event): bool
    {
        if (!$this->pdo) {
            return false;
        }
        
        try {
            $externalId = 'meetup_' . md5($event['url'] . $event['title']);
            
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
                "Organizer: {$event['organizer']}\n\n{$event['description']}\n\nSource: Meetup.com",
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
    
    public function saveToCsv(string $filename = 'yakima_meetup_events.csv'): void
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
        $this->log("Starting Meetup scraper for Yakima events");
        
        foreach ($this->searchUrls as $i => $url) {
            $this->log("Processing search URL " . ($i + 1) . "/" . count($this->searchUrls));
            $this->log("URL: {$url}");
            
            $html = $this->makeRequest($url);
            
            if (!$html) {
                $this->log("Failed to fetch search results", 'ERROR');
                continue;
            }
            
            if ($this->debug) {
                $filename = "meetup_search_" . ($i + 1) . ".html";
                file_put_contents($filename, $html);
                $this->log("Saved HTML to {$filename} for debugging");
            }
            
            // Extract JSON data first
            $jsonData = $this->extractJsonData($html);
            if ($jsonData && $this->debug) {
                $this->log("Found JSON data in page");
            }
            
            // Extract events from HTML
            $pageEvents = $this->extractEventsFromHtml($html);
            $this->log("Found " . count($pageEvents) . " events on this page");
            
            foreach ($pageEvents as $event) {
                $this->events[] = $event;
                
                if (!$this->csvOnly) {
                    $this->saveToDatabase($event);
                }
                
                if ($this->debug) {
                    $this->log("  - {$event['title']}", 'DEBUG');
                }
            }
            
            // Be polite between requests
            if ($i < count($this->searchUrls) - 1) {
                sleep(3);
            }
        }
        
        // Remove duplicates
        $uniqueEvents = [];
        foreach ($this->events as $event) {
            $key = $event['title'] . '|' . $event['start_date'];
            $uniqueEvents[$key] = $event;
        }
        $this->events = array_values($uniqueEvents);
        
        // Save to CSV
        $this->saveToCsv();
        
        $totalEvents = count($this->events);
        $this->log("Scraping complete! Found {$totalEvents} unique events.", 'SUCCESS');
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
            echo "Meetup.com Event Scraper for Yakima\n\n";
            echo "Usage: php meetup_scraper.php [options]\n\n";
            echo "Options:\n";
            echo "  --csv-only    Export to CSV only (don't save to database)\n";
            echo "  --debug       Enable verbose debug logging\n";
            echo "  --help        Show this help message\n\n";
            echo "Output:\n";
            echo "  - yakima_meetup_events.csv\n";
            echo "  - Events saved to database (unless --csv-only)\n";
            exit(0);
    }
}

// Run the scraper
try {
    $scraper = new MeetupScraper($options);
    $scraper->run();
} catch (Exception $e) {
    echo "Fatal error: " . $e->getMessage() . "\n";
    exit(1);
}
?>