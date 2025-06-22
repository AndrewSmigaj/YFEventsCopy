<?php

namespace YFEvents\Scrapers\Intelligent;

use YFEvents\Models\EventModel;
use YFEvents\Utils\GeocodeService;

class LLMScraper
{
    private $db;
    private $api;
    private $eventModel;
    private $geocodeService;
    private $sessionId;
    
    public function __construct($db)
    {
        $this->db = $db;
        
        // Load API key from config
        $configFile = __DIR__ . '/../../../config/api_keys.php';
        if (file_exists($configFile)) {
            require_once $configFile;
        }
        
        $apiKey = defined('SEGMIND_API_KEY') ? SEGMIND_API_KEY : null;
        $this->api = new SegmindAPI($apiKey);
        $this->eventModel = new EventModel($db);
        $this->geocodeService = new GeocodeService();
    }
    
    /**
     * Start a new scraping session
     */
    public function startSession($url, $userId = null)
    {
        $stmt = $this->db->prepare("
            INSERT INTO intelligent_scraper_sessions (url, created_by, status)
            VALUES (?, ?, 'analyzing')
        ");
        $stmt->execute([$url, $userId]);
        $this->sessionId = $this->db->lastInsertId();
        
        return $this->sessionId;
    }
    
    /**
     * Analyze a URL to find events
     */
    public function analyzeUrl($url)
    {
        try {
            // Check if we have an existing method for this domain
            $existingMethod = $this->findExistingMethod($url);
            if ($existingMethod) {
                return $this->applyExistingMethod($url, $existingMethod);
            }
            
            // Fetch the webpage content
            $htmlContent = $this->fetchWebpage($url);
            if (!$htmlContent) {
                throw new \Exception('Failed to fetch webpage content');
            }
            
            // Store the content in session
            $this->updateSession(['page_content' => $htmlContent]);
            
            // Analyze with LLM to find event patterns
            $analysis = $this->analyzeWithLLM($htmlContent, $url);
            $this->updateSession(['llm_analysis' => json_encode($analysis)]);
            
            if (!$analysis || !$analysis['has_events']) {
                $this->updateSession(['status' => 'no_events']);
                return [
                    'success' => false,
                    'message' => 'No events found on this page',
                    'analysis' => $analysis
                ];
            }
            
            // Extract events based on analysis
            $events = $this->extractEvents($htmlContent, $analysis, $url);
            
            // If we found event links instead of events, follow them
            if (empty($events) && !empty($analysis['event_links'])) {
                $events = $this->followEventLinks($analysis['event_links'], $url);
            }
            
            if (empty($events)) {
                $this->updateSession(['status' => 'no_events']);
                return [
                    'success' => false,
                    'message' => 'Could not extract event details',
                    'analysis' => $analysis
                ];
            }
            
            // Save events to database
            $savedCount = $this->saveEventsToDatabase($events, $url);
            
            // Generate extraction method for future use
            $method = $this->generateMethod($htmlContent, $events, $url, $analysis);
            
            $this->updateSession([
                'found_events' => json_encode($events),
                'status' => 'events_found'
            ]);
            
            return [
                'success' => true,
                'events' => $events,
                'method' => $method,
                'analysis' => $analysis,
                'session_id' => $this->sessionId,
                'events_saved' => $savedCount
            ];
            
        } catch (\Exception $e) {
            $this->updateSession([
                'status' => 'error',
                'error_message' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Analyze HTML with LLM
     */
    private function analyzeWithLLM($htmlContent, $url)
    {
        $response = $this->api->findEventPatterns($htmlContent, $url);
        return $this->api->parseJSONResponse($response);
    }
    
    /**
     * Extract events from HTML using LLM analysis
     */
    private function extractEvents($htmlContent, $analysis, $baseUrl)
    {
        $events = [];
        
        // If LLM already found events, use them
        if (!empty($analysis['events_found'])) {
            foreach ($analysis['events_found'] as $event) {
                $events[] = $this->normalizeEvent($event, $baseUrl);
            }
            return $events;
        }
        
        // Otherwise, use selectors to extract
        if (!empty($analysis['selectors'])) {
            $dom = new \DOMDocument();
            @$dom->loadHTML($htmlContent);
            $xpath = new \DOMXPath($dom);
            
            $selectors = $analysis['selectors'];
            
            // Convert CSS selectors to XPath if needed
            $containerXpath = $this->cssToXpath($selectors['event_container'] ?? '');
            if (!$containerXpath) {
                return $events;
            }
            
            $eventNodes = $xpath->query($containerXpath);
            
            foreach ($eventNodes as $node) {
                $event = [];
                
                // Extract each field
                foreach (['title', 'date', 'location', 'description', 'link'] as $field) {
                    if (!empty($selectors[$field])) {
                        $fieldXpath = $this->cssToXpath($selectors[$field]);
                        $fieldNode = $xpath->query($fieldXpath, $node)->item(0);
                        
                        if ($fieldNode) {
                            if ($field === 'link') {
                                $event['url'] = $this->resolveUrl($fieldNode->getAttribute('href'), $baseUrl);
                            } else {
                                $event[$field] = trim($fieldNode->textContent);
                            }
                        }
                    }
                }
                
                if (!empty($event['title'])) {
                    $events[] = $this->normalizeEvent($event, $baseUrl);
                }
            }
        }
        
        return $events;
    }
    
    /**
     * Follow event links to get full details
     */
    private function followEventLinks($links, $baseUrl)
    {
        $events = [];
        $maxLinks = 5; // Limit to prevent too many requests
        
        foreach (array_slice($links, 0, $maxLinks) as $link) {
            $fullUrl = $this->resolveUrl($link, $baseUrl);
            $eventHtml = $this->fetchWebpage($fullUrl);
            
            if ($eventHtml) {
                // Analyze individual event page
                $prompt = "Extract event details from this event page. Return JSON with: title, date, start_time, end_time, location, address, description, contact_info, ticket_url";
                
                $response = $this->api->analyzeHTMLContent($eventHtml, $prompt);
                $eventData = $this->api->parseJSONResponse($response);
                
                if ($eventData) {
                    $eventData['url'] = $fullUrl;
                    $events[] = $this->normalizeEvent($eventData, $baseUrl);
                }
            }
        }
        
        return $events;
    }
    
    /**
     * Normalize event data
     */
    private function normalizeEvent($eventData, $baseUrl)
    {
        $event = [
            'title' => $eventData['title'] ?? '',
            'description' => $eventData['description'] ?? '',
            'location' => $eventData['location'] ?? '',
            'address' => $eventData['address'] ?? '',
            'external_url' => $eventData['url'] ?? $eventData['link'] ?? '',
            'contact_info' => []
        ];
        
        // Parse date and time
        if (!empty($eventData['date'])) {
            $dateStr = $eventData['date'];
            if (!empty($eventData['start_time'])) {
                $dateStr .= ' ' . $eventData['start_time'];
            }
            $event['start_datetime'] = $this->parseDateTime($dateStr);
            
            if (!empty($eventData['end_time'])) {
                $endStr = $eventData['date'] . ' ' . $eventData['end_time'];
                $event['end_datetime'] = $this->parseDateTime($endStr);
            }
        }
        
        // Handle various datetime formats
        if (empty($event['start_datetime']) && !empty($eventData['datetime'])) {
            $event['start_datetime'] = $this->parseDateTime($eventData['datetime']);
        }
        if (empty($event['start_datetime']) && !empty($eventData['start_datetime'])) {
            $event['start_datetime'] = $this->parseDateTime($eventData['start_datetime']);
        }
        if (empty($event['end_datetime']) && !empty($eventData['end_datetime'])) {
            $event['end_datetime'] = $this->parseDateTime($eventData['end_datetime']);
        }
        
        // Contact info
        if (!empty($eventData['contact_info'])) {
            if (is_array($eventData['contact_info'])) {
                $event['contact_info'] = $eventData['contact_info'];
            } else {
                $event['contact_info']['info'] = $eventData['contact_info'];
            }
        }
        if (!empty($eventData['phone'])) {
            $event['contact_info']['phone'] = $eventData['phone'];
        }
        if (!empty($eventData['email'])) {
            $event['contact_info']['email'] = $eventData['email'];
        }
        
        // Resolve relative URLs
        if (!empty($event['external_url']) && !filter_var($event['external_url'], FILTER_VALIDATE_URL)) {
            $event['external_url'] = $this->resolveUrl($event['external_url'], $baseUrl);
        }
        
        return $event;
    }
    
    /**
     * Generate reusable extraction method
     */
    private function generateMethod($htmlContent, $events, $url, $analysis)
    {
        $response = $this->api->generateExtractionMethod($htmlContent, $events, $url);
        $method = $this->api->parseJSONResponse($response);
        
        if (!$method) {
            // Create a basic method from analysis
            $method = [
                'selectors' => $analysis['selectors'] ?? [],
                'patterns' => $analysis['patterns'] ?? [],
                'type' => $analysis['event_type'] ?? 'list'
            ];
        }
        
        // Add metadata
        $domain = parse_url($url, PHP_URL_HOST);
        $method['domain'] = $domain;
        $method['url_pattern'] = $this->generateUrlPattern($url);
        $method['confidence'] = 0.8; // Default confidence
        
        return $method;
    }
    
    /**
     * Save approved method
     */
    public function approveMethod($sessionId, $userId = null)
    {
        // Get session data
        $stmt = $this->db->prepare("
            SELECT * FROM intelligent_scraper_sessions WHERE id = ?
        ");
        $stmt->execute([$sessionId]);
        $session = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$session || $session['status'] !== 'events_found') {
            throw new \Exception('Invalid session or no events found');
        }
        
        $analysis = json_decode($session['llm_analysis'], true);
        $events = json_decode($session['found_events'], true);
        
        // Generate method if not already done
        $method = $this->generateMethod($session['page_content'], $events, $session['url'], $analysis);
        
        // Save method
        $stmt = $this->db->prepare("
            INSERT INTO intelligent_scraper_methods 
            (name, domain, url_pattern, method_type, extraction_rules, selector_mappings, 
             post_processing, llm_model, confidence_score, test_results, approved_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $domain = $method['domain'];
        $name = "Auto-generated method for " . $domain;
        
        $stmt->execute([
            $name,
            $domain,
            $method['url_pattern'],
            $method['type'] ?? 'list',
            json_encode($method),
            json_encode($method['selectors'] ?? []),
            json_encode($method['patterns'] ?? []),
            SegmindAPI::MODEL_CLAUDE_SONNET,
            $method['confidence'],
            json_encode(['events_found' => count($events)]),
            $userId
        ]);
        
        $methodId = $this->db->lastInsertId();
        
        // Update session
        $stmt = $this->db->prepare("
            UPDATE intelligent_scraper_sessions 
            SET method_id = ?, status = 'approved', completed_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$methodId, $sessionId]);
        
        // Create calendar source
        $stmt = $this->db->prepare("
            INSERT INTO calendar_sources 
            (name, url, scrape_type, intelligent_method_id, active, created_by)
            VALUES (?, ?, 'intelligent', ?, 1, ?)
        ");
        $stmt->execute([
            $name,
            $session['url'],
            $methodId,
            $userId
        ]);
        
        return [
            'method_id' => $methodId,
            'source_id' => $this->db->lastInsertId()
        ];
    }
    
    /**
     * Find existing method for URL
     */
    private function findExistingMethod($url)
    {
        $domain = parse_url($url, PHP_URL_HOST);
        
        $stmt = $this->db->prepare("
            SELECT * FROM intelligent_scraper_methods
            WHERE domain = ? AND active = 1
            ORDER BY confidence_score DESC, success_rate DESC
            LIMIT 1
        ");
        $stmt->execute([$domain]);
        
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Apply existing method
     */
    public function applyExistingMethod($url, $method)
    {
        $htmlContent = $this->fetchWebpage($url);
        if (!$htmlContent) {
            throw new \Exception('Failed to fetch webpage');
        }
        
        $rules = json_decode($method['extraction_rules'], true);
        $analysis = [
            'has_events' => true,
            'event_type' => $method['method_type'],
            'selectors' => json_decode($method['selector_mappings'], true),
            'patterns' => json_decode($method['post_processing'], true)
        ];
        
        $events = $this->extractEvents($htmlContent, $analysis, $url);
        
        // Update method usage stats
        $this->updateMethodStats($method['id'], !empty($events));
        
        return [
            'success' => !empty($events),
            'events' => $events,
            'method' => $method,
            'used_existing' => true
        ];
    }
    
    /**
     * Update method statistics
     */
    private function updateMethodStats($methodId, $success)
    {
        $stmt = $this->db->prepare("
            UPDATE intelligent_scraper_methods
            SET usage_count = usage_count + 1,
                last_used = NOW(),
                success_rate = ((success_rate * usage_count) + ?) / (usage_count + 1)
            WHERE id = ?
        ");
        $stmt->execute([$success ? 100 : 0, $methodId]);
    }
    
    /**
     * Update session data
     */
    private function updateSession($data)
    {
        if (!$this->sessionId) {
            return;
        }
        
        $fields = [];
        $values = [];
        
        foreach ($data as $field => $value) {
            $fields[] = "$field = ?";
            $values[] = $value;
        }
        $values[] = $this->sessionId;
        
        $sql = "UPDATE intelligent_scraper_sessions SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($values);
    }
    
    /**
     * Fetch webpage content
     */
    private function fetchWebpage($url)
    {
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => 45,  // Increased timeout
            CURLOPT_CONNECTTIMEOUT => 15,  // Increased connection timeout
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_ENCODING => 'gzip, deflate',
            CURLOPT_DNS_CACHE_TIMEOUT => 300,  // Cache DNS for 5 minutes
            CURLOPT_FRESH_CONNECT => false,  // Reuse connections
            CURLOPT_HTTPHEADER => [
                'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8',
                'Accept-Language: en-US,en;q=0.9',
                'Accept-Encoding: gzip, deflate, br',
                'Cache-Control: no-cache',
                'Pragma: no-cache',
                'Sec-Fetch-Dest: document',
                'Sec-Fetch-Mode: navigate',
                'Sec-Fetch-Site: none',
                'Upgrade-Insecure-Requests: 1'
            ]
        ]);
        
        $content = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            error_log('Error fetching URL ' . $url . ': ' . $error);
            
            // Try alternate URL strategies for common issues
            if (strpos($error, 'Could not resolve host') !== false) {
                // Try with www prefix/removal
                $altUrl = $this->tryAlternateUrl($url);
                if ($altUrl && $altUrl !== $url) {
                    error_log('Retrying with alternate URL: ' . $altUrl);
                    return $this->fetchWebpage($altUrl);
                }
            }
            
            throw new \Exception('Failed to fetch webpage: ' . $error);
        }
        
        if ($httpCode !== 200) {
            error_log('HTTP error ' . $httpCode . ' fetching URL: ' . $url);
            
            // Handle common redirects
            if ($httpCode >= 300 && $httpCode < 400) {
                $redirectUrl = curl_getinfo($ch, CURLINFO_REDIRECT_URL);
                if ($redirectUrl) {
                    error_log('Following redirect to: ' . $redirectUrl);
                    curl_close($ch);
                    return $this->fetchWebpage($redirectUrl);
                }
            }
            
            throw new \Exception('HTTP error ' . $httpCode . ' fetching webpage');
        }
        
        return $content;
    }
    
    /**
     * Try alternate URL for DNS resolution issues
     */
    private function tryAlternateUrl($url)
    {
        $parsed = parse_url($url);
        if (!$parsed || !isset($parsed['host'])) {
            return null;
        }
        
        $host = $parsed['host'];
        $altHost = null;
        
        // Try adding/removing www prefix
        if (strpos($host, 'www.') === 0) {
            $altHost = substr($host, 4);  // Remove www
        } else {
            $altHost = 'www.' . $host;  // Add www
        }
        
        if ($altHost) {
            $parsed['host'] = $altHost;
            return $this->buildUrl($parsed);
        }
        
        return null;
    }
    
    /**
     * Build URL from parsed components
     */
    private function buildUrl($parsed)
    {
        $scheme = isset($parsed['scheme']) ? $parsed['scheme'] . '://' : 'https://';
        $host = isset($parsed['host']) ? $parsed['host'] : '';
        $port = isset($parsed['port']) ? ':' . $parsed['port'] : '';
        $path = isset($parsed['path']) ? $parsed['path'] : '/';
        $query = isset($parsed['query']) ? '?' . $parsed['query'] : '';
        $fragment = isset($parsed['fragment']) ? '#' . $parsed['fragment'] : '';
        
        return $scheme . $host . $port . $path . $query . $fragment;
    }
    
    /**
     * Save events to database
     */
    private function saveEventsToDatabase($events, $sourceUrl)
    {
        $savedCount = 0;
        
        foreach ($events as $event) {
            try {
                // Check if event already exists
                $existingCheck = $this->db->prepare("
                    SELECT id FROM events 
                    WHERE title = ? AND start_datetime = ? AND location = ?
                    LIMIT 1
                ");
                
                $startDatetime = $this->parseEventDateTime($event);
                $existingCheck->execute([
                    $event['title'] ?? 'Untitled Event',
                    $startDatetime,
                    $event['location'] ?? ''
                ]);
                
                if ($existingCheck->fetchColumn()) {
                    continue; // Skip existing event
                }
                
                // Prepare event data
                $eventData = [
                    'title' => $event['title'] ?? 'Untitled Event',
                    'description' => $event['description'] ?? '',
                    'start_datetime' => $startDatetime,
                    'end_datetime' => null, // Could be parsed from event data
                    'location' => $event['location'] ?? '',
                    'address' => $event['address'] ?? $event['location'] ?? '',
                    'latitude' => null,
                    'longitude' => null,
                    'contact_info' => json_encode([
                        'source' => 'AI Scraper',
                        'url' => $sourceUrl
                    ]),
                    'external_url' => $event['link'] ?? $event['external_url'] ?? $sourceUrl,
                    'source_id' => null, // Could link to intelligent_scraper_sessions
                    'status' => 'pending', // All AI scraped events start as pending
                    'scraped_at' => date('Y-m-d H:i:s')
                ];
                
                // Geocode if we have an address
                if (!empty($eventData['address']) && $this->geocodeService) {
                    $coords = $this->geocodeService->geocode($eventData['address']);
                    if ($coords) {
                        $eventData['latitude'] = $coords['lat'];
                        $eventData['longitude'] = $coords['lng'];
                    }
                }
                
                // Insert event
                $stmt = $this->db->prepare("
                    INSERT INTO events (
                        title, description, start_datetime, end_datetime,
                        location, address, latitude, longitude,
                        contact_info, external_url, source_id, status, scraped_at
                    ) VALUES (
                        :title, :description, :start_datetime, :end_datetime,
                        :location, :address, :latitude, :longitude,
                        :contact_info, :external_url, :source_id, :status, :scraped_at
                    )
                ");
                
                $stmt->execute($eventData);
                $savedCount++;
                
            } catch (\Exception $e) {
                error_log('Error saving event: ' . $e->getMessage());
                // Continue with next event
            }
        }
        
        return $savedCount;
    }
    
    /**
     * Parse event date/time into MySQL format
     */
    private function parseEventDateTime($event)
    {
        $dateStr = $event['date'] ?? '';
        $timeStr = $event['time'] ?? '';
        
        // Combine date and time
        $fullDateStr = trim($dateStr . ' ' . $timeStr);
        
        if (empty($fullDateStr)) {
            return date('Y-m-d H:i:s'); // Default to now
        }
        
        // Try to parse the date
        $timestamp = strtotime($fullDateStr);
        if ($timestamp === false) {
            // Try some common formats
            $formats = [
                'F j, Y g:i A',
                'M d, Y H:i',
                'Y-m-d H:i:s',
                'd/m/Y H:i',
                'm/d/Y g:i A'
            ];
            
            foreach ($formats as $format) {
                $date = \DateTime::createFromFormat($format, $fullDateStr);
                if ($date !== false) {
                    return $date->format('Y-m-d H:i:s');
                }
            }
            
            // Last resort - try to extract any date
            $timestamp = strtotime($dateStr);
            if ($timestamp !== false) {
                return date('Y-m-d 00:00:00', $timestamp);
            }
        }
        
        return $timestamp !== false ? date('Y-m-d H:i:s', $timestamp) : date('Y-m-d H:i:s');
    }
    
    /**
     * Convert CSS selector to XPath
     */
    private function cssToXpath($css)
    {
        // Basic CSS to XPath conversion
        $css = trim($css);
        if (empty($css)) {
            return '';
        }
        
        // Handle basic selectors
        $css = str_replace('#', '//*[@id="', $css);
        $css = str_replace('.', '//*[contains(@class, "', $css);
        
        // Close attribute selectors
        if (strpos($css, '[@id="') !== false) {
            $css .= '"]';
        }
        if (strpos($css, '[@class, "') !== false) {
            $css .= '")]';
        }
        
        // Handle tag selectors
        if (!strpos($css, '/') && !strpos($css, '[')) {
            $css = '//' . $css;
        }
        
        return $css;
    }
    
    /**
     * Resolve relative URL
     */
    private function resolveUrl($url, $baseUrl)
    {
        if (filter_var($url, FILTER_VALIDATE_URL)) {
            return $url;
        }
        
        $base = parse_url($baseUrl);
        
        if (strpos($url, '/') === 0) {
            return $base['scheme'] . '://' . $base['host'] . $url;
        }
        
        $path = isset($base['path']) ? dirname($base['path']) : '';
        return $base['scheme'] . '://' . $base['host'] . $path . '/' . $url;
    }
    
    /**
     * Parse datetime string
     */
    private function parseDateTime($dateString)
    {
        if (empty($dateString)) {
            return null;
        }
        
        // Try various formats
        $formats = [
            'Y-m-d H:i:s',
            'Y-m-d H:i',
            'Y-m-d',
            'm/d/Y H:i:s',
            'm/d/Y H:i',
            'm/d/Y',
            'd/m/Y H:i:s',
            'd/m/Y H:i',
            'd/m/Y',
            'F j, Y g:i A',
            'F j, Y',
            'M j, Y g:i A',
            'M j, Y'
        ];
        
        foreach ($formats as $format) {
            $date = \DateTime::createFromFormat($format, $dateString);
            if ($date !== false) {
                return $date->format('Y-m-d H:i:s');
            }
        }
        
        // Try strtotime as fallback
        $timestamp = strtotime($dateString);
        if ($timestamp !== false) {
            return date('Y-m-d H:i:s', $timestamp);
        }
        
        return null;
    }
    
    /**
     * Generate URL pattern from example URL
     */
    private function generateUrlPattern($url)
    {
        $parsed = parse_url($url);
        $pattern = $parsed['scheme'] . '://' . $parsed['host'];
        
        if (isset($parsed['path'])) {
            // Replace numbers with wildcards
            $path = preg_replace('/\d+/', '*', $parsed['path']);
            $pattern .= $path;
        }
        
        return $pattern;
    }
}