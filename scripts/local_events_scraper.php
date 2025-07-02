<?php
/**
 * Local Events Scraper for Yakima
 * ===============================
 * 
 * Multi-source event scraper that gathers events from various local sources
 * since major platforms like Eventbrite are blocking automated access.
 * 
 * Sources:
 * - Yakima Herald-Republic events
 * - Visit Yakima events
 * - City of Yakima events
 * - Local business websites
 * 
 * Usage:
 *   php local_events_scraper.php                    # Save to database
 *   php local_events_scraper.php --csv-only         # Export to CSV only
 *   php local_events_scraper.php --debug            # Enable verbose logging
 */

declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line.\n");
}

// Bootstrap application and load all dependencies
require_once __DIR__ . '/../config/app-root.php';

class LocalEventsScraper
{
    private array $events = [];
    private bool $debug = false;
    private bool $csvOnly = false;
    private ?PDO $pdo = null;
    
    private array $sources = [
        'yakima_herald' => [
            'name' => 'Yakima Herald-Republic',
            'url' => 'https://www.yakimaherald.com/events/',
            'method' => 'scrapeYakimaHerald'
        ],
        'visit_yakima' => [
            'name' => 'Visit Yakima',
            'url' => 'https://www.visityakima.com/events/',
            'method' => 'scrapeVisitYakima'
        ],
        'city_yakima' => [
            'name' => 'City of Yakima',
            'url' => 'https://www.yakimawa.gov/events/',
            'method' => 'scrapeCityYakima'
        ],
        'yakima_valley' => [
            'name' => 'Yakima Valley Tourism',
            'url' => 'https://www.visityakimavalley.com/events/',
            'method' => 'scrapeYakimaValley'
        ]
    ];
    
    public function __construct(array $options = [])
    {
        $this->debug = $options['debug'] ?? false;
        $this->csvOnly = $options['csv-only'] ?? false;
        
        if (!$this->csvOnly) {
            $this->setupDatabase();
        }
        
        $this->log("Local Events Scraper initialized");
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
        
        $logFile = dirname(__DIR__) . '/logs/local_events_scraper.log';
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
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                'Accept-Language: en-US,en;q=0.5',
                'Connection: keep-alive',
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
    
    private function scrapeYakimaHerald(string $url): array
    {
        $this->log("Scraping Yakima Herald-Republic events");
        
        $html = $this->makeRequest($url);
        if (!$html) {
            return [];
        }
        
        $events = [];
        $dom = new DOMDocument();
        @$dom->loadHTML($html);
        $xpath = new DOMXPath($dom);
        
        // Look for event items
        $eventNodes = $xpath->query('//*[contains(@class, "event") or contains(@class, "calendar-item")]');
        
        foreach ($eventNodes as $node) {
            $event = $this->extractEventFromNode($xpath, $node, $url);
            if ($event && !empty($event['title'])) {
                $event['source'] = 'Yakima Herald-Republic';
                $events[] = $event;
            }
        }
        
        return $events;
    }
    
    private function scrapeVisitYakima(string $url): array
    {
        $this->log("Scraping Visit Yakima events");
        
        $html = $this->makeRequest($url);
        if (!$html) {
            return [];
        }
        
        $events = [];
        $dom = new DOMDocument();
        @$dom->loadHTML($html);
        $xpath = new DOMXPath($dom);
        
        // Look for event listings
        $eventNodes = $xpath->query('//*[contains(@class, "event") or contains(@class, "listing")]');
        
        foreach ($eventNodes as $node) {
            $event = $this->extractEventFromNode($xpath, $node, $url);
            if ($event && !empty($event['title'])) {
                $event['source'] = 'Visit Yakima';
                $events[] = $event;
            }
        }
        
        return $events;
    }
    
    private function scrapeCityYakima(string $url): array
    {
        $this->log("Scraping City of Yakima events");
        
        $html = $this->makeRequest($url);
        if (!$html) {
            return [];
        }
        
        $events = [];
        $dom = new DOMDocument();
        @$dom->loadHTML($html);
        $xpath = new DOMXPath($dom);
        
        // Look for event items
        $eventNodes = $xpath->query('//*[contains(@class, "event") or contains(@class, "calendar")]');
        
        foreach ($eventNodes as $node) {
            $event = $this->extractEventFromNode($xpath, $node, $url);
            if ($event && !empty($event['title'])) {
                $event['source'] = 'City of Yakima';
                $events[] = $event;
            }
        }
        
        return $events;
    }
    
    private function scrapeYakimaValley(string $url): array
    {
        $this->log("Scraping Yakima Valley Tourism events");
        
        $html = $this->makeRequest($url);
        if (!$html) {
            return [];
        }
        
        $events = [];
        $dom = new DOMDocument();
        @$dom->loadHTML($html);
        $xpath = new DOMXPath($dom);
        
        // Look for event listings
        $eventNodes = $xpath->query('//*[contains(@class, "event") or contains(@class, "listing")]');
        
        foreach ($eventNodes as $node) {
            $event = $this->extractEventFromNode($xpath, $node, $url);
            if ($event && !empty($event['title'])) {
                $event['source'] = 'Yakima Valley Tourism';
                $events[] = $event;
            }
        }
        
        return $events;
    }
    
    private function extractEventFromNode(DOMXPath $xpath, DOMNode $node, string $baseUrl): ?array
    {
        $event = [];
        
        // Title
        $titleNodes = $xpath->query('.//h1 | .//h2 | .//h3 | .//h4 | .//*[contains(@class, "title")] | .//*[contains(@class, "name")]', $node);
        if ($titleNodes->length > 0) {
            $event['title'] = trim($titleNodes->item(0)->textContent);
        }
        
        // Date/Time
        $dateNodes = $xpath->query('.//*[@datetime] | .//*[contains(@class, "date")] | .//*[contains(@class, "time")]', $node);
        $dates = [];
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
        
        $event['start_date'] = $dates[0] ?? '';
        $event['end_date'] = $dates[1] ?? '';
        
        // Location
        $locationNodes = $xpath->query('.//*[contains(@class, "location")] | .//*[contains(@class, "venue")] | .//*[contains(@class, "address")]', $node);
        if ($locationNodes->length > 0) {
            $event['venue_name'] = trim($locationNodes->item(0)->textContent);
        } else {
            $event['venue_name'] = '';
        }
        
        // URL
        $linkNodes = $xpath->query('.//a[@href]', $node);
        if ($linkNodes->length > 0) {
            $href = $linkNodes->item(0)->getAttribute('href');
            if (strpos($href, 'http') !== 0) {
                $href = rtrim($baseUrl, '/') . '/' . ltrim($href, '/');
            }
            $event['url'] = $href;
        } else {
            $event['url'] = $baseUrl;
        }
        
        // Description
        $descNodes = $xpath->query('.//*[contains(@class, "description")] | .//*[contains(@class, "content")] | .//p', $node);
        if ($descNodes->length > 0) {
            $event['description'] = trim($descNodes->item(0)->textContent);
        } else {
            $event['description'] = '';
        }
        
        // Set defaults
        $event['venue_location'] = 'Yakima, WA';
        $event['organizer'] = $event['source'] ?? '';
        $event['image_url'] = '';
        
        return $event;
    }
    
    private function parseDate(string $dateText): ?string
    {
        // Common date patterns
        $patterns = [
            '/(\w+),?\s+(\w+)\s+(\d{1,2}),?\s+(\d{4})/', // Monday, January 15, 2024
            '/(\w+)\s+(\d{1,2}),?\s+(\d{4})/',           // January 15, 2024
            '/(\d{1,2})\/(\d{1,2})\/(\d{4})/',           // 1/15/2024
            '/(\d{4})-(\d{2})-(\d{2})/',                 // 2024-01-15
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $dateText, $matches)) {
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
            // Create unique external ID
            $externalId = 'local_' . md5($event['url'] . $event['title']);
            
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
                "Source: {$event['source']}\n\n{$event['description']}",
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
    
    public function saveToCsv(string $filename = 'yakima_local_events.csv'): void
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
        
        $headers = ['title', 'start_date', 'end_date', 'venue_name', 'venue_location', 'organizer', 'source', 'url', 'description'];
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
        $this->log("Starting local events scraper for Yakima");
        
        foreach ($this->sources as $sourceId => $source) {
            $this->log("Processing source: {$source['name']}");
            
            try {
                $method = $source['method'];
                $sourceEvents = $this->$method($source['url']);
                
                $this->log("Found " . count($sourceEvents) . " events from {$source['name']}");
                
                foreach ($sourceEvents as $event) {
                    $this->events[] = $event;
                    
                    if (!$this->csvOnly) {
                        $this->saveToDatabase($event);
                    }
                    
                    if ($this->debug) {
                        $this->log("  - {$event['title']}");
                    }
                }
                
                // Be polite between sources
                sleep(3);
                
            } catch (Exception $e) {
                $this->log("Error processing {$source['name']}: {$e->getMessage()}", 'ERROR');
            }
        }
        
        // Save to CSV
        $this->saveToCsv();
        
        $totalEvents = count($this->events);
        $this->log("Scraping complete! Found {$totalEvents} total events.", 'SUCCESS');
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
            echo "Local Events Scraper for Yakima\n\n";
            echo "Usage: php local_events_scraper.php [options]\n\n";
            echo "Options:\n";
            echo "  --csv-only    Export to CSV only (don't save to database)\n";
            echo "  --debug       Enable verbose debug logging\n";
            echo "  --help        Show this help message\n\n";
            echo "Sources:\n";
            echo "  - Yakima Herald-Republic\n";
            echo "  - Visit Yakima\n";
            echo "  - City of Yakima\n";
            echo "  - Yakima Valley Tourism\n\n";
            echo "Output:\n";
            echo "  - yakima_local_events.csv\n";
            echo "  - Events saved to database (unless --csv-only)\n";
            exit(0);
    }
}

// Run the scraper
try {
    $scraper = new LocalEventsScraper($options);
    $scraper->run();
} catch (Exception $e) {
    echo "Fatal error: " . $e->getMessage() . "\n";
    exit(1);
}
?>