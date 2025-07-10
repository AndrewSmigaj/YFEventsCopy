<?php

namespace YakimaFinds\Scrapers;

use PDO;

class IntelligentScraper
{
    private $db;
    private $debugMode;
    
    public function __construct($db, $debugMode = false)
    {
        $this->db = $db;
        $this->debugMode = $debugMode;
    }
    
    /**
     * Intelligently analyze and optimize a new source
     */
    public function analyzeAndOptimizeSource($sourceId)
    {
        $this->log("Starting intelligent analysis for source ID: $sourceId");
        
        // Get source details
        $stmt = $this->db->prepare("SELECT * FROM calendar_sources WHERE id = ?");
        $stmt->execute([$sourceId]);
        $source = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$source) {
            throw new \Exception("Source not found: $sourceId");
        }
        
        $this->log("Analyzing source: " . $source['name']);
        $this->log("URL: " . $source['url']);
        $this->log("Type: " . $source['scrape_type']);
        
        switch ($source['scrape_type']) {
            case 'html':
                return $this->optimizeHtmlSource($source);
            case 'ical':
                return $this->optimizeIcalSource($source);
            case 'json':
                return $this->optimizeJsonSource($source);
            default:
                return $this->analyzeUnknownSource($source);
        }
    }
    
    /**
     * Optimize HTML source by testing multiple selector strategies
     */
    private function optimizeHtmlSource($source)
    {
        $this->log("Optimizing HTML source...");
        
        // Fetch the page content
        $html = $this->fetchWithCurl($source['url']);
        if (!$html) {
            throw new \Exception("Failed to fetch HTML content");
        }
        
        $this->log("Fetched " . strlen($html) . " bytes of HTML");
        
        // Create DOM parser
        $dom = new \DOMDocument();
        @$dom->loadHTML($html, LIBXML_NOERROR);
        $xpath = new \DOMXPath($dom);
        
        // Test various selector strategies
        $strategies = [
            'schema_org' => '//*[@itemtype="http://schema.org/Event"]',
            'event_classes' => '//*[contains(@class, "event")]',
            'article_events' => '//article[contains(@class, "event")]',
            'event_items' => '//li[contains(@class, "event")]',
            'calendar_events' => '//*[contains(@class, "calendar")]//li',
            'structured_lists' => '//ul//li[a and (h1 or h2 or h3)]',
            'date_containers' => '//*[contains(@class, "date") or contains(@class, "time")]/..',
            'event_title_links' => '//a[contains(@href, "event")]/..',
            'generic_containers' => '//div[h2 or h3][descendant::a]',
        ];
        
        $bestStrategy = null;
        $maxEvents = 0;
        $results = [];
        
        foreach ($strategies as $name => $selector) {
            $this->log("Testing strategy: $name");
            
            try {
                $nodes = $xpath->query($selector);
                $eventCount = $nodes ? $nodes->length : 0;
                
                if ($eventCount > 0) {
                    $this->log("  Found $eventCount potential event nodes");
                    
                    // Sample first few nodes to extract data
                    $sampleEvents = $this->extractSampleEvents($xpath, $nodes, 3);
                    $validEvents = count(array_filter($sampleEvents, function($event) {
                        return !empty($event['title']) && !empty($event['date']);
                    }));
                    
                    $score = $validEvents * 10 + min($eventCount, 50); // Prefer quality over quantity
                    
                    $results[$name] = [
                        'selector' => $selector,
                        'node_count' => $eventCount,
                        'valid_events' => $validEvents,
                        'score' => $score,
                        'sample_events' => $sampleEvents
                    ];
                    
                    $this->log("  Valid events: $validEvents, Score: $score");
                    
                    if ($score > $maxEvents) {
                        $maxEvents = $score;
                        $bestStrategy = $name;
                    }
                }
            } catch (\Exception $e) {
                $this->log("  Error with strategy $name: " . $e->getMessage());
            }
        }
        
        if (!$bestStrategy) {
            throw new \Exception("No viable scraping strategy found");
        }
        
        $this->log("Best strategy: $bestStrategy (score: {$results[$bestStrategy]['score']})");
        
        // Generate optimized configuration
        $config = $this->generateHtmlConfig($results[$bestStrategy], $xpath, $dom);
        
        // Update source configuration
        $this->updateSourceConfig($source['id'], $config);
        
        return [
            'success' => true,
            'strategy' => $bestStrategy,
            'config' => $config,
            'results' => $results[$bestStrategy]
        ];
    }
    
    /**
     * Extract sample events from nodes for validation
     */
    private function extractSampleEvents($xpath, $nodes, $limit = 3)
    {
        $events = [];
        $count = 0;
        
        foreach ($nodes as $node) {
            if ($count >= $limit) break;
            
            $event = [
                'title' => '',
                'date' => '',
                'location' => '',
                'description' => ''
            ];
            
            // Extract title (try multiple approaches)
            $titleSelectors = [
                './/h1', './/h2', './/h3', './/h4',
                './/*[contains(@class, "title")]',
                './/*[contains(@class, "name")]',
                './/a[contains(@href, "event")]',
                './/strong', './/b'
            ];
            
            foreach ($titleSelectors as $sel) {
                $titleNodes = $xpath->query($sel, $node);
                if ($titleNodes && $titleNodes->length > 0) {
                    $event['title'] = trim($titleNodes->item(0)->textContent);
                    if ($event['title']) break;
                }
            }
            
            // Extract date/time information
            $dateSelectors = [
                './/*[contains(@class, "date")]',
                './/*[contains(@class, "time")]',
                './/*[contains(@class, "when")]',
                './/*[@datetime]'
            ];
            
            foreach ($dateSelectors as $sel) {
                $dateNodes = $xpath->query($sel, $node);
                if ($dateNodes && $dateNodes->length > 0) {
                    $event['date'] = trim($dateNodes->item(0)->textContent);
                    if ($event['date']) break;
                }
            }
            
            // Extract location
            $locationSelectors = [
                './/*[contains(@class, "location")]',
                './/*[contains(@class, "venue")]',
                './/*[contains(@class, "place")]',
                './/*[contains(@class, "where")]'
            ];
            
            foreach ($locationSelectors as $sel) {
                $locationNodes = $xpath->query($sel, $node);
                if ($locationNodes && $locationNodes->length > 0) {
                    $event['location'] = trim($locationNodes->item(0)->textContent);
                    if ($event['location']) break;
                }
            }
            
            if ($event['title']) {
                $events[] = $event;
                $count++;
            }
        }
        
        return $events;
    }
    
    /**
     * Generate optimized HTML configuration
     */
    private function generateHtmlConfig($bestResult, $xpath, $dom)
    {
        $config = [
            'selectors' => [
                'container' => $bestResult['selector'],
                'title' => 'h1|h2|h3|.title|.name|a[href*="event"]',
                'date' => '.date|.time|.when|[datetime]',
                'location' => '.location|.venue|.place|.where',
                'description' => '.description|.summary|.excerpt|p',
                'link' => 'a[href]'
            ],
            'base_url' => $this->getBaseUrl($bestResult['selector']),
            'date_format' => 'auto',
            'timezone' => 'America/Los_Angeles',
            'intelligent_time' => true
        ];
        
        return $config;
    }
    
    /**
     * Optimize iCal source
     */
    private function optimizeIcalSource($source)
    {
        $this->log("Optimizing iCal source...");
        
        $content = $this->fetchWithCurl($source['url']);
        if (!$content) {
            throw new \Exception("Failed to fetch iCal content");
        }
        
        // Validate iCal format
        if (strpos($content, 'BEGIN:VCALENDAR') === false) {
            throw new \Exception("Invalid iCal format - missing VCALENDAR");
        }
        
        if (strpos($content, 'BEGIN:VEVENT') === false) {
            throw new \Exception("No events found in iCal feed");
        }
        
        $eventCount = substr_count($content, 'BEGIN:VEVENT');
        $this->log("Found $eventCount events in iCal feed");
        
        $config = [
            'format' => 'ical',
            'timezone' => 'America/Los_Angeles',
            'intelligent_time' => true
        ];
        
        $this->updateSourceConfig($source['id'], $config);
        
        return [
            'success' => true,
            'event_count' => $eventCount,
            'config' => $config
        ];
    }
    
    /**
     * Optimize JSON source
     */
    private function optimizeJsonSource($source)
    {
        $this->log("Optimizing JSON source...");
        
        $content = $this->fetchWithCurl($source['url']);
        if (!$content) {
            throw new \Exception("Failed to fetch JSON content");
        }
        
        $data = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception("Invalid JSON format: " . json_last_error_msg());
        }
        
        // Analyze JSON structure to find events
        $eventPaths = $this->findJsonEventPaths($data);
        
        if (empty($eventPaths)) {
            throw new \Exception("No event data structure found in JSON");
        }
        
        $bestPath = $eventPaths[0]; // Use the path with most events
        $this->log("Best JSON path: " . $bestPath['path'] . " ({$bestPath['count']} events)");
        
        $config = [
            'format' => 'json',
            'event_path' => $bestPath['path'],
            'field_mapping' => $bestPath['fields'],
            'timezone' => 'America/Los_Angeles',
            'intelligent_time' => true
        ];
        
        $this->updateSourceConfig($source['id'], $config);
        
        return [
            'success' => true,
            'event_count' => $bestPath['count'],
            'config' => $config
        ];
    }
    
    /**
     * Analyze unknown source type
     */
    private function analyzeUnknownSource($source)
    {
        $this->log("Analyzing unknown source type...");
        
        $content = $this->fetchWithCurl($source['url']);
        if (!$content) {
            throw new \Exception("Failed to fetch content");
        }
        
        // Detect content type
        if (strpos($content, 'BEGIN:VCALENDAR') !== false) {
            $this->log("Detected iCal format");
            $this->updateSourceType($source['id'], 'ical');
            return $this->optimizeIcalSource(array_merge($source, ['scrape_type' => 'ical']));
        }
        
        $jsonData = json_decode($content, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $this->log("Detected JSON format");
            $this->updateSourceType($source['id'], 'json');
            return $this->optimizeJsonSource(array_merge($source, ['scrape_type' => 'json']));
        }
        
        if (strpos($content, '<html') !== false || strpos($content, '<!DOCTYPE') !== false) {
            $this->log("Detected HTML format");
            $this->updateSourceType($source['id'], 'html');
            return $this->optimizeHtmlSource(array_merge($source, ['scrape_type' => 'html']));
        }
        
        throw new \Exception("Unable to detect source format");
    }
    
    /**
     * Find JSON paths that contain event arrays
     */
    private function findJsonEventPaths($data, $path = '', $results = [])
    {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $currentPath = $path ? "$path.$key" : $key;
                
                if (is_array($value)) {
                    // Check if this looks like an event array
                    if ($this->looksLikeEventArray($value)) {
                        $fields = $this->analyzeEventFields($value);
                        $results[] = [
                            'path' => $currentPath,
                            'count' => count($value),
                            'fields' => $fields
                        ];
                    }
                    
                    // Recurse deeper
                    $results = $this->findJsonEventPaths($value, $currentPath, $results);
                }
            }
        }
        
        // Sort by event count descending
        usort($results, function($a, $b) {
            return $b['count'] - $a['count'];
        });
        
        return $results;
    }
    
    /**
     * Check if array looks like events
     */
    private function looksLikeEventArray($array)
    {
        if (!is_array($array) || empty($array)) return false;
        
        $first = reset($array);
        if (!is_array($first)) return false;
        
        // Look for common event fields
        $eventFields = ['title', 'name', 'event', 'date', 'time', 'start', 'when'];
        $fieldCount = 0;
        
        foreach ($eventFields as $field) {
            if (isset($first[$field])) {
                $fieldCount++;
            }
        }
        
        return $fieldCount >= 2; // At least 2 event-like fields
    }
    
    /**
     * Analyze event fields in JSON array
     */
    private function analyzeEventFields($events)
    {
        if (empty($events)) return [];
        
        $first = reset($events);
        $mapping = [];
        
        // Common field mappings
        $fieldMappings = [
            'title' => ['title', 'name', 'event_name', 'summary'],
            'start_datetime' => ['start', 'start_date', 'date', 'datetime', 'when'],
            'end_datetime' => ['end', 'end_date', 'end_time'],
            'location' => ['location', 'venue', 'place', 'where'],
            'description' => ['description', 'summary', 'details', 'content']
        ];
        
        foreach ($fieldMappings as $standardField => $possibilities) {
            foreach ($possibilities as $field) {
                if (isset($first[$field])) {
                    $mapping[$standardField] = $field;
                    break;
                }
            }
        }
        
        return $mapping;
    }
    
    /**
     * Fetch content with cURL and improved error handling
     */
    private function fetchWithCurl($url)
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; YFEvents/1.0; +https://yakimafinds.com)',
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ]);
        
        $content = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($content === false || !empty($error)) {
            throw new \Exception("cURL error: $error");
        }
        
        if ($httpCode >= 400) {
            throw new \Exception("HTTP error: $httpCode");
        }
        
        return $content;
    }
    
    /**
     * Get base URL from source URL
     */
    private function getBaseUrl($url)
    {
        $parsed = parse_url($url);
        return $parsed['scheme'] . '://' . $parsed['host'];
    }
    
    /**
     * Update source configuration
     */
    private function updateSourceConfig($sourceId, $config)
    {
        $stmt = $this->db->prepare("
            UPDATE calendar_sources 
            SET scrape_config = ?, updated_at = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([json_encode($config), $sourceId]);
    }
    
    /**
     * Update source type
     */
    private function updateSourceType($sourceId, $type)
    {
        $stmt = $this->db->prepare("
            UPDATE calendar_sources 
            SET scrape_type = ?, updated_at = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$type, $sourceId]);
    }
    
    /**
     * Log messages
     */
    private function log($message)
    {
        $timestamp = date('Y-m-d H:i:s');
        if ($this->debugMode) {
            echo "[{$timestamp}] [IntelligentScraper] {$message}\n";
        }
        error_log("[{$timestamp}] [IntelligentScraper] {$message}");
    }
}