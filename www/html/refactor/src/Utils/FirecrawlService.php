<?php

namespace YakimaFinds\Utils;

/**
 * Firecrawl Service Wrapper
 * 
 * Provides a clean interface to Firecrawl API for web scraping
 * with fallback mechanisms and error handling.
 * 
 * INTEGRATION NOTES:
 * - This class is self-contained and can be easily removed
 * - All Firecrawl-related functionality is isolated here
 * - Requires FIRECRAWL_API_KEY in config/api_keys.php
 * - Falls back gracefully when API is unavailable
 * 
 * REMOVAL INSTRUCTIONS:
 * 1. Delete this file: src/Utils/FirecrawlService.php
 * 2. Remove 'firecrawl_enhanced' from ScraperFactory.php
 * 3. Remove Firecrawl configuration from api_keys.php
 * 4. Remove any 'firecrawl_enhanced' calendar sources from database
 */
class FirecrawlService
{
    private $apiKey;
    private $baseUrl;
    private $timeout;
    private $maxRetries;
    
    /**
     * Constructor
     */
    public function __construct()
    {
        // Load API key from config
        $this->apiKey = defined('FIRECRAWL_API_KEY') ? FIRECRAWL_API_KEY : null;
        
        // Default to cloud endpoint
        $this->baseUrl = 'https://api.firecrawl.dev/v1';
        
        // Configuration
        $this->timeout = 30;
        $this->maxRetries = 2;
    }
    
    /**
     * Check if Firecrawl is available and configured
     */
    public function isAvailable()
    {
        return !empty($this->apiKey) && $this->apiKey !== 'YOUR_FIRECRAWL_API_KEY_HERE';
    }
    
    /**
     * Scrape a single URL
     * 
     * @param string $url URL to scrape
     * @param array $options Scraping options
     * @return array|false Result array or false on failure
     */
    public function scrape($url, $options = [])
    {
        if (!$this->isAvailable()) {
            return false;
        }
        
        $payload = [
            'url' => $url,
            'formats' => $options['formats'] ?? ['markdown'],
            'onlyMainContent' => $options['onlyMainContent'] ?? true,
            'includeTags' => $options['includeTags'] ?? ['h1', 'h2', 'h3', 'p', 'a', 'div', 'time'],
            'excludeTags' => $options['excludeTags'] ?? ['nav', 'footer', 'header', 'script', 'style'],
        ];
        
        // Add extract schema if provided
        if (!empty($options['extractSchema'])) {
            $payload['extract'] = [
                'schema' => $options['extractSchema']
            ];
        }
        
        return $this->makeRequest('/scrape', $payload);
    }
    
    /**
     * Batch scrape multiple URLs
     * 
     * @param array $urls Array of URLs to scrape
     * @param array $options Scraping options
     * @return array|false Result array or false on failure
     */
    public function batchScrape($urls, $options = [])
    {
        if (!$this->isAvailable()) {
            return false;
        }
        
        $payload = [
            'urls' => $urls,
            'formats' => $options['formats'] ?? ['markdown'],
            'onlyMainContent' => $options['onlyMainContent'] ?? true,
            'concurrency' => $options['concurrency'] ?? 5
        ];
        
        return $this->makeRequest('/batch/scrape', $payload);
    }
    
    /**
     * Search for events across the web
     * 
     * @param string $query Search query
     * @param array $options Search options
     * @return array|false Result array or false on failure
     */
    public function search($query, $options = [])
    {
        if (!$this->isAvailable()) {
            return false;
        }
        
        $payload = [
            'query' => $query,
            'limit' => $options['limit'] ?? 10,
            'format' => $options['format'] ?? 'markdown'
        ];
        
        // Add location filter for Yakima area events
        if (!empty($options['location'])) {
            $payload['query'] .= ' site:yakima OR site:selah OR site:uniongap';
        }
        
        return $this->makeRequest('/search', $payload);
    }
    
    /**
     * Extract structured data from a URL
     * 
     * @param string $url URL to extract from
     * @param array $schema Extraction schema
     * @return array|false Result array or false on failure
     */
    public function extract($url, $schema)
    {
        if (!$this->isAvailable()) {
            return false;
        }
        
        $payload = [
            'url' => $url,
            'extract' => [
                'schema' => $schema
            ]
        ];
        
        return $this->makeRequest('/extract', $payload);
    }
    
    /**
     * Get event extraction schema for structured event data
     * 
     * @return array Event extraction schema
     */
    public function getEventExtractionSchema()
    {
        return [
            'events' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'title' => ['type' => 'string'],
                        'description' => ['type' => 'string'],
                        'start_date' => ['type' => 'string'],
                        'end_date' => ['type' => 'string'],
                        'start_time' => ['type' => 'string'],
                        'end_time' => ['type' => 'string'],
                        'location' => ['type' => 'string'],
                        'address' => ['type' => 'string'],
                        'venue' => ['type' => 'string'],
                        'url' => ['type' => 'string'],
                        'price' => ['type' => 'string'],
                        'categories' => [
                            'type' => 'array',
                            'items' => ['type' => 'string']
                        ]
                    ],
                    'required' => ['title', 'start_date']
                ]
            ]
        ];
    }
    
    /**
     * Parse Firecrawl response into YFEvents format
     * 
     * @param array $response Firecrawl response
     * @param string $sourceUrl Original source URL
     * @return array Array of events in YFEvents format
     */
    public function parseEventsFromResponse($response, $sourceUrl = '')
    {
        $events = [];
        
        if (!$response || !isset($response['success']) || !$response['success']) {
            return $events;
        }
        
        // Handle extract response (structured data)
        if (isset($response['data']['extract']['events'])) {
            foreach ($response['data']['extract']['events'] as $eventData) {
                $event = $this->normalizeEventData($eventData, $sourceUrl);
                if ($event) {
                    $events[] = $event;
                }
            }
        }
        
        // Handle search response
        elseif (isset($response['data']) && is_array($response['data'])) {
            foreach ($response['data'] as $item) {
                // Try to extract event data from search results
                if (isset($item['markdown']) || isset($item['content'])) {
                    $content = $item['markdown'] ?? $item['content'];
                    $eventData = $this->parseEventFromContent($content, $item['url'] ?? $sourceUrl);
                    if ($eventData) {
                        $events[] = $eventData;
                    }
                }
            }
        }
        
        // Handle scrape response with markdown content
        elseif (isset($response['data']['markdown'])) {
            $events = $this->parseEventsFromMarkdown($response['data']['markdown'], $sourceUrl);
        }
        
        return $events;
    }
    
    /**
     * Normalize event data to YFEvents format
     */
    private function normalizeEventData($eventData, $sourceUrl = '')
    {
        if (empty($eventData['title'])) {
            return null;
        }
        
        // Combine date and time
        $startDateTime = $this->combineDateTime(
            $eventData['start_date'] ?? '',
            $eventData['start_time'] ?? '00:00'
        );
        
        $endDateTime = $this->combineDateTime(
            $eventData['end_date'] ?? $eventData['start_date'] ?? '',
            $eventData['end_time'] ?? '23:59'
        );
        
        if (!$startDateTime) {
            return null;
        }
        
        return [
            'title' => trim($eventData['title']),
            'description' => trim($eventData['description'] ?? ''),
            'start_datetime' => $startDateTime,
            'end_datetime' => $endDateTime,
            'location' => trim($eventData['location'] ?? ''),
            'address' => trim($eventData['address'] ?? $eventData['location'] ?? ''),
            'venue' => trim($eventData['venue'] ?? ''),
            'external_url' => $eventData['url'] ?? $sourceUrl,
            'external_event_id' => md5(($eventData['url'] ?? $sourceUrl) . $eventData['title']),
            'categories' => $eventData['categories'] ?? [],
            'price' => $eventData['price'] ?? null
        ];
    }
    
    /**
     * Parse single event from content text
     */
    private function parseEventFromContent($content, $url)
    {
        // This is a simplified parser - could be enhanced with more sophisticated NLP
        $lines = explode("\n", $content);
        $event = ['external_url' => $url];
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            // Look for title (usually in # headers)
            if (preg_match('/^#+\s*(.+)/', $line, $matches)) {
                $event['title'] = $matches[1];
            }
            
            // Look for dates
            if (preg_match('/\b(\d{1,2}\/\d{1,2}\/\d{4}|\d{4}-\d{2}-\d{2}|\w+\s+\d{1,2},?\s+\d{4})\b/', $line, $matches)) {
                if (!isset($event['start_datetime'])) {
                    $event['start_datetime'] = $this->parseDateTime($matches[1]);
                }
            }
        }
        
        return !empty($event['title']) && !empty($event['start_datetime']) ? $event : null;
    }
    
    /**
     * Parse events from markdown content
     */
    private function parseEventsFromMarkdown($markdown, $sourceUrl)
    {
        // This would need sophisticated parsing based on the site structure
        // For now, return empty array - can be enhanced per site
        return [];
    }
    
    /**
     * Combine date and time strings
     */
    private function combineDateTime($date, $time)
    {
        if (empty($date)) {
            return null;
        }
        
        $dateTime = trim($date . ' ' . $time);
        $timestamp = strtotime($dateTime);
        
        return $timestamp ? date('Y-m-d H:i:s', $timestamp) : null;
    }
    
    /**
     * Parse datetime string
     */
    private function parseDateTime($dateString)
    {
        $timestamp = strtotime($dateString);
        return $timestamp ? date('Y-m-d H:i:s', $timestamp) : null;
    }
    
    /**
     * Make HTTP request to Firecrawl API
     */
    private function makeRequest($endpoint, $payload)
    {
        if (!$this->isAvailable()) {
            return false;
        }
        
        $url = $this->baseUrl . $endpoint;
        
        $options = [
            'http' => [
                'method' => 'POST',
                'header' => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $this->apiKey,
                    'User-Agent: YakimaFinds/1.0'
                ],
                'content' => json_encode($payload),
                'timeout' => $this->timeout
            ]
        ];
        
        $context = stream_context_create($options);
        
        for ($attempt = 0; $attempt < $this->maxRetries; $attempt++) {
            $response = @file_get_contents($url, false, $context);
            
            if ($response !== false) {
                $data = json_decode($response, true);
                
                if (json_last_error() === JSON_ERROR_NONE) {
                    return $data;
                }
            }
            
            // Wait before retry
            if ($attempt < $this->maxRetries - 1) {
                sleep(1);
            }
        }
        
        return false;
    }
    
    /**
     * Get API usage information
     */
    public function getUsage()
    {
        if (!$this->isAvailable()) {
            return false;
        }
        
        return $this->makeRequest('/usage', []);
    }
}