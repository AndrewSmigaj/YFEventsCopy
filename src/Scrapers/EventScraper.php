<?php

namespace YakimaFinds\Scrapers;

use YakimaFinds\Models\EventModel;
use YakimaFinds\Models\CalendarSourceModel;
use YakimaFinds\Utils\GeocodeService;

class EventScraper
{
    private $db;
    private $eventModel;
    private $sourceModel;
    private $geocodeService;
    
    public function __construct($db)
    {
        $this->db = $db;
        $this->eventModel = new EventModel($db);
        $this->sourceModel = new CalendarSourceModel($db);
        $this->geocodeService = new GeocodeService();
    }
    
    /**
     * Scrape all active sources
     */
    public function scrapeAllSources()
    {
        $sources = $this->sourceModel->getActiveSources();
        $results = [];
        
        foreach ($sources as $source) {
            $results[$source['id']] = $this->scrapeSource($source);
        }
        
        return $results;
    }
    
    /**
     * Scrape a single source
     */
    public function scrapeSource($source)
    {
        $logId = $this->sourceModel->logScrapingStart($source['id']);
        $eventsFound = 0;
        $eventsAdded = 0;
        $errorMessage = null;
        
        try {
            $events = [];
            
            switch ($source['scrape_type']) {
                case 'ical':
                    $events = $this->scrapeICalSource($source);
                    break;
                case 'html':
                    $events = $this->scrapeHtmlSource($source);
                    break;
                case 'yakima_valley':
                    $events = $this->scrapeYakimaValleySource($source);
                    break;
                case 'json':
                    $events = $this->scrapeJsonSource($source);
                    break;
                case 'eventbrite':
                    $events = $this->scrapeEventbriteSource($source);
                    break;
                case 'facebook':
                    $events = $this->scrapeFacebookSource($source);
                    break;
                case 'intelligent':
                    $events = $this->scrapeIntelligentSource($source);
                    break;
                case 'firecrawl_enhanced':
                    $events = $this->scrapeFirecrawlEnhancedSource($source);
                    break;
                default:
                    throw new \Exception("Unsupported scrape type: {$source['scrape_type']}");
            }
            
            $eventsFound = count($events);
            
            // Process each event
            foreach ($events as $eventData) {
                if ($this->processEvent($eventData, $source['id'])) {
                    $eventsAdded++;
                }
            }
            
            // Update source last scraped time
            $this->sourceModel->updateLastScraped($source['id']);
            
            // Log success
            $this->sourceModel->logScrapingComplete($logId, $eventsFound, $eventsAdded, 'success');
            
            return [
                'success' => true,
                'events_found' => $eventsFound,
                'events_added' => $eventsAdded
            ];
            
        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();
            
            // Log error
            $this->sourceModel->logScrapingComplete($logId, $eventsFound, $eventsAdded, 'failed', $errorMessage);
            
            // Check if source should be deactivated
            $this->sourceModel->deactivateFailedSource($source['id']);
            
            return [
                'success' => false,
                'error' => $errorMessage,
                'events_found' => $eventsFound,
                'events_added' => $eventsAdded
            ];
        }
    }
    
    /**
     * Scrape iCal source
     */
    private function scrapeICalSource($source)
    {
        $content = $this->fetchContent($source['url']);
        if (!$content) {
            throw new \Exception('Failed to fetch iCal content');
        }
        
        return $this->parseICalContent($content);
    }
    
    /**
     * Parse iCal content
     */
    private function parseICalContent($content)
    {
        $events = [];
        $lines = explode("\n", $content);
        $currentEvent = null;
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            if ($line === 'BEGIN:VEVENT') {
                $currentEvent = [];
                continue;
            }
            
            if ($line === 'END:VEVENT' && $currentEvent) {
                $events[] = $this->processICalEvent($currentEvent);
                $currentEvent = null;
                continue;
            }
            
            if ($currentEvent !== null && strpos($line, ':') !== false) {
                list($property, $value) = explode(':', $line, 2);
                $currentEvent[$property] = $value;
            }
        }
        
        return $events;
    }
    
    /**
     * Process individual iCal event
     */
    private function processICalEvent($icalEvent)
    {
        return [
            'title' => $this->cleanICalValue($icalEvent['SUMMARY'] ?? ''),
            'description' => $this->cleanICalValue($icalEvent['DESCRIPTION'] ?? ''),
            'start_datetime' => $this->parseICalDateTime($icalEvent['DTSTART'] ?? ''),
            'end_datetime' => $this->parseICalDateTime($icalEvent['DTEND'] ?? ''),
            'location' => $this->cleanICalValue($icalEvent['LOCATION'] ?? ''),
            'external_url' => $this->cleanICalValue($icalEvent['URL'] ?? ''),
            'external_event_id' => $this->cleanICalValue($icalEvent['UID'] ?? '')
        ];
    }
    
    /**
     * Clean iCal value (remove escape characters, etc.)
     */
    private function cleanICalValue($value)
    {
        return str_replace(['\\,', '\\;', '\\n'], [',', ';', "\n"], $value);
    }
    
    /**
     * Parse iCal datetime
     */
    private function parseICalDateTime($datetime)
    {
        if (empty($datetime)) {
            return null;
        }
        
        // Remove timezone info for simplicity
        $datetime = preg_replace('/;.*$/', '', $datetime);
        
        // Convert from YYYYMMDDTHHMMSS format
        if (preg_match('/^(\d{4})(\d{2})(\d{2})T(\d{2})(\d{2})(\d{2})/', $datetime, $matches)) {
            return sprintf('%s-%s-%s %s:%s:%s', 
                $matches[1], $matches[2], $matches[3], 
                $matches[4], $matches[5], $matches[6]
            );
        }
        
        return null;
    }
    
    /**
     * Scrape HTML source
     */
    private function scrapeHtmlSource($source)
    {
        $content = $this->fetchContent($source['url']);
        if (!$content) {
            throw new \Exception('Failed to fetch HTML content');
        }
        
        $config = json_decode($source['scrape_config'], true);
        if (!$config || !isset($config['selectors'])) {
            throw new \Exception('Invalid HTML scrape configuration');
        }
        
        return $this->parseHtmlContent($content, $config);
    }
    
    /**
     * Parse HTML content using CSS selectors
     */
    private function parseHtmlContent($content, $config)
    {
        $dom = new \DOMDocument();
        @$dom->loadHTML($content);
        $xpath = new \DOMXPath($dom);
        
        $events = [];
        $selectors = $config['selectors'];
        
        // Find event containers
        $eventNodes = $xpath->query($selectors['event_container']);
        
        foreach ($eventNodes as $node) {
            $event = [];
            
            // Extract title
            if (isset($selectors['title'])) {
                $titleNode = $xpath->query($selectors['title'], $node)->item(0);
                $event['title'] = $titleNode ? trim($titleNode->textContent) : '';
            }
            
            // Extract description
            if (isset($selectors['description'])) {
                $descNode = $xpath->query($selectors['description'], $node)->item(0);
                $event['description'] = $descNode ? trim($descNode->textContent) : '';
            }
            
            // Extract datetime
            if (isset($selectors['datetime'])) {
                $dateNode = $xpath->query($selectors['datetime'], $node)->item(0);
                $dateText = $dateNode ? trim($dateNode->textContent) : '';
                $event['start_datetime'] = $this->parseDateTime($dateText);
            }
            
            // Extract location
            if (isset($selectors['location'])) {
                $locationNode = $xpath->query($selectors['location'], $node)->item(0);
                $event['location'] = $locationNode ? trim($locationNode->textContent) : '';
            }
            
            // Extract URL
            if (isset($selectors['url'])) {
                $urlNode = $xpath->query($selectors['url'], $node)->item(0);
                $event['external_url'] = $urlNode ? $urlNode->getAttribute('href') : '';
            }
            
            if (!empty($event['title'])) {
                $events[] = $event;
            }
        }
        
        return $events;
    }
    
    /**
     * Scrape Yakima Valley HTML source
     */
    private function scrapeYakimaValleySource($source)
    {
        require_once __DIR__ . '/YakimaValleyEventScraper.php';
        
        $content = $this->fetchContent($source['url']);
        if (!$content) {
            throw new \Exception('Failed to fetch content from Yakima Valley source');
        }
        
        $config = json_decode($source['scrape_config'], true) ?: [];
        $baseUrl = $config['base_url'] ?? $source['url'];
        $currentYear = $config['year'] ?? date('Y');
        
        $rawEvents = YakimaValleyEventScraper::parseEvents($content, $baseUrl, $currentYear);
        
        // Convert to our event format
        $events = [];
        foreach ($rawEvents as $rawEvent) {
            $event = [
                'title' => $rawEvent['title'],
                'description' => $rawEvent['description'] ?? '',
                'start_datetime' => $rawEvent['start_datetime'],
                'end_datetime' => $rawEvent['end_datetime'],
                'location' => $rawEvent['full_location'] ?? $rawEvent['location'] ?? '',
                'address' => $rawEvent['address'] ?? '',
                'external_url' => $rawEvent['external_url'] ?? '',
                'external_event_id' => md5($rawEvent['external_url'] ?? $rawEvent['title'])
            ];
            
            // Add categories if available
            if (!empty($rawEvent['categories'])) {
                $event['categories'] = $rawEvent['categories'];
            }
            
            $events[] = $event;
        }
        
        return $events;
    }
    
    /**
     * Scrape JSON source
     */
    private function scrapeJsonSource($source)
    {
        $content = $this->fetchContent($source['url']);
        if (!$content) {
            throw new \Exception('Failed to fetch JSON content');
        }
        
        $data = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Invalid JSON format');
        }
        
        $config = json_decode($source['scrape_config'], true);
        return $this->parseJsonContent($data, $config);
    }
    
    /**
     * Parse JSON content
     */
    private function parseJsonContent($data, $config)
    {
        $events = [];
        $eventsPath = $config['events_path'] ?? 'events';
        
        // Navigate to events array
        $eventsData = $this->getNestedValue($data, $eventsPath);
        if (!is_array($eventsData)) {
            return $events;
        }
        
        $mapping = $config['field_mapping'] ?? [];
        
        foreach ($eventsData as $eventData) {
            $event = [];
            
            foreach ($mapping as $ourField => $theirField) {
                $value = $this->getNestedValue($eventData, $theirField);
                
                if ($ourField === 'start_datetime' || $ourField === 'end_datetime') {
                    $event[$ourField] = $this->parseDateTime($value);
                } else {
                    $event[$ourField] = $value;
                }
            }
            
            if (!empty($event['title'])) {
                $events[] = $event;
            }
        }
        
        return $events;
    }
    
    /**
     * Scrape Eventbrite source (placeholder)
     */
    private function scrapeEventbriteSource($source)
    {
        // Would use Eventbrite API
        throw new \Exception('Eventbrite scraping not yet implemented');
    }
    
    /**
     * Scrape Facebook source (placeholder)
     */
    private function scrapeFacebookSource($source)
    {
        // Would use Facebook Graph API
        throw new \Exception('Facebook scraping not yet implemented');
    }
    
    /**
     * Scrape Intelligent source using LLM-generated method
     */
    private function scrapeIntelligentSource($source)
    {
        // Check if method is linked
        if (empty($source['intelligent_method_id'])) {
            throw new \Exception('No intelligent method linked to this source');
        }
        
        // Get the intelligent method
        $stmt = $this->db->prepare("
            SELECT * FROM intelligent_scraper_methods 
            WHERE id = ? AND active = 1
        ");
        $stmt->execute([$source['intelligent_method_id']]);
        $method = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$method) {
            throw new \Exception('Intelligent method not found or inactive');
        }
        
        // Use LLMScraper to apply the method
        require_once __DIR__ . '/Intelligent/LLMScraper.php';
        $llmScraper = new \YakimaFinds\Scrapers\Intelligent\LLMScraper($this->db);
        
        $result = $llmScraper->applyExistingMethod($source['url'], $method);
        
        if (!$result['success']) {
            throw new \Exception('Failed to apply intelligent method');
        }
        
        return $result['events'];
    }
    
    /**
     * Scrape Firecrawl Enhanced source with fallback
     */
    private function scrapeFirecrawlEnhancedSource($source)
    {
        require_once __DIR__ . '/FirecrawlEnhancedScraper.php';
        
        $config = json_decode($source['scrape_config'], true) ?: [];
        $scraper = new FirecrawlEnhancedScraper($config);
        
        return $scraper->scrapeEvents($source['url']);
    }
    
    /**
     * Process and save event
     */
    private function processEvent($eventData, $sourceId)
    {
        // Validate required fields
        if (empty($eventData['title']) || empty($eventData['start_datetime'])) {
            return false;
        }
        
        // Check for duplicates
        $duplicates = $this->eventModel->findDuplicates(
            $eventData['title'],
            $eventData['start_datetime'],
            $eventData['latitude'] ?? null,
            $eventData['longitude'] ?? null
        );
        
        if (!empty($duplicates)) {
            return false; // Skip duplicate
        }
        
        // Geocode address if needed
        if (!empty($eventData['location']) && empty($eventData['latitude'])) {
            $coordinates = $this->geocodeService->geocode($eventData['location']);
            if ($coordinates) {
                $eventData['latitude'] = $coordinates['lat'];
                $eventData['longitude'] = $coordinates['lng'];
            }
        }
        
        // Set source and status
        $eventData['source_id'] = $sourceId;
        $eventData['status'] = 'pending'; // Require approval for scraped events
        $eventData['scraped_at'] = date('Y-m-d H:i:s');
        
        // Create event
        return $this->eventModel->createEvent($eventData);
    }
    
    /**
     * Fetch content from URL
     */
    private function fetchContent($url)
    {
        $context = stream_context_create([
            'http' => [
                'timeout' => 30,
                'user_agent' => 'Yakima Finds Calendar Bot 1.0'
            ]
        ]);
        
        return @file_get_contents($url, false, $context);
    }
    
    /**
     * Parse various datetime formats
     */
    private function parseDateTime($dateString)
    {
        if (empty($dateString)) {
            return null;
        }
        
        // Try to parse the date
        $timestamp = strtotime($dateString);
        if ($timestamp !== false) {
            return date('Y-m-d H:i:s', $timestamp);
        }
        
        return null;
    }
    
    /**
     * Get nested value from array using dot notation
     */
    private function getNestedValue($array, $path)
    {
        $keys = explode('.', $path);
        $value = $array;
        
        foreach ($keys as $key) {
            if (!is_array($value) || !isset($value[$key])) {
                return null;
            }
            $value = $value[$key];
        }
        
        return $value;
    }
}