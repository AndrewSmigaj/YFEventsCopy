<?php

namespace YakimaFinds\Scrapers;

use YakimaFinds\Utils\FirecrawlService;

/**
 * Firecrawl Enhanced Scraper
 * 
 * A hybrid scraper that uses Firecrawl API for enhanced web scraping
 * with automatic fallback to existing scraper methods.
 * 
 * INTEGRATION NOTES:
 * - This class bridges Firecrawl functionality with YFEvents scraper system
 * - Automatically falls back to existing scrapers when Firecrawl fails
 * - Supports all existing scraper types as fallback options
 * 
 * REMOVAL INSTRUCTIONS:
 * 1. Delete this file: src/Scrapers/FirecrawlEnhancedScraper.php
 * 2. Remove 'firecrawl_enhanced' case from ScraperFactory.php
 * 3. Update any calendar sources using 'firecrawl_enhanced' type
 */
class FirecrawlEnhancedScraper
{
    private $firecrawlService;
    private $fallbackScraper;
    private $config;
    
    /**
     * Constructor
     */
    public function __construct($config = null)
    {
        $this->config = $config ?: [];
        $this->firecrawlService = new FirecrawlService();
        
        // Determine fallback scraper type
        $fallbackType = $this->config['fallback_type'] ?? 'html';
        $this->initializeFallbackScraper($fallbackType);
    }
    
    /**
     * Initialize fallback scraper based on type
     */
    private function initializeFallbackScraper($type)
    {
        switch ($type) {
            case 'yakima_valley':
                require_once __DIR__ . '/YakimaValleyEventScraper.php';
                $this->fallbackScraper = new YakimaValleyEventScraper($this->config);
                break;
                
            case 'html':
            default:
                // For HTML fallback, we'll use the parseHtmlContent method from EventScraper
                $this->fallbackScraper = null; // Will be handled in scrapeWithFallback
                break;
        }
    }
    
    /**
     * Scrape events from URL using Firecrawl with fallback
     * 
     * @param string $url URL to scrape
     * @return array Array of events
     */
    public function scrapeEvents($url)
    {
        $events = [];
        $firecrawlSuccess = false;
        
        // Try Firecrawl first if available
        if ($this->firecrawlService->isAvailable()) {
            $events = $this->scrapeWithFirecrawl($url);
            $firecrawlSuccess = !empty($events);
        }
        
        // Fallback to existing scraper if Firecrawl failed or unavailable
        if (!$firecrawlSuccess) {
            $events = $this->scrapeWithFallback($url);
        }
        
        return $events;
    }
    
    /**
     * Scrape using Firecrawl API
     */
    private function scrapeWithFirecrawl($url)
    {
        $events = [];
        $scrapeMethod = $this->config['firecrawl_method'] ?? 'structured';
        
        switch ($scrapeMethod) {
            case 'structured':
                $events = $this->scrapeStructured($url);
                break;
                
            case 'search':
                $events = $this->scrapeViaSearch($url);
                break;
                
            case 'basic':
            default:
                $events = $this->scrapeBasic($url);
                break;
        }
        
        return $events;
    }
    
    /**
     * Scrape with structured data extraction
     */
    private function scrapeStructured($url)
    {
        $schema = $this->firecrawlService->getEventExtractionSchema();
        $response = $this->firecrawlService->extract($url, $schema);
        
        if ($response) {
            return $this->firecrawlService->parseEventsFromResponse($response, $url);
        }
        
        return [];
    }
    
    /**
     * Scrape via search functionality
     */
    private function scrapeViaSearch($url)
    {
        // Extract search terms from config or URL
        $searchQuery = $this->config['search_query'] ?? '';
        if (empty($searchQuery)) {
            // Try to derive search query from URL domain
            $domain = parse_url($url, PHP_URL_HOST);
            $searchQuery = "events site:$domain";
        }
        
        $response = $this->firecrawlService->search($searchQuery, [
            'location' => true, // Add Yakima area filter
            'limit' => 20
        ]);
        
        if ($response) {
            return $this->firecrawlService->parseEventsFromResponse($response, $url);
        }
        
        return [];
    }
    
    /**
     * Basic scraping with markdown parsing
     */
    private function scrapeBasic($url)
    {
        $options = [
            'formats' => ['markdown'],
            'onlyMainContent' => true,
            'includeTags' => ['h1', 'h2', 'h3', 'p', 'a', 'div', 'time', 'span'],
            'excludeTags' => ['nav', 'footer', 'header', 'script', 'style', 'aside']
        ];
        
        $response = $this->firecrawlService->scrape($url, $options);
        
        if ($response) {
            return $this->firecrawlService->parseEventsFromResponse($response, $url);
        }
        
        return [];
    }
    
    /**
     * Fallback to existing scraper methods
     */
    private function scrapeWithFallback($url)
    {
        $fallbackType = $this->config['fallback_type'] ?? 'html';
        
        switch ($fallbackType) {
            case 'yakima_valley':
                return $this->fallbackToYakimaValley($url);
                
            case 'html':
                return $this->fallbackToHtml($url);
                
            case 'ical':
                return $this->fallbackToICal($url);
                
            case 'json':
                return $this->fallbackToJson($url);
                
            default:
                return [];
        }
    }
    
    /**
     * Fallback to Yakima Valley scraper
     */
    private function fallbackToYakimaValley($url)
    {
        try {
            $content = $this->fetchContent($url);
            if (!$content) {
                return [];
            }
            
            $baseUrl = $this->config['base_url'] ?? $url;
            $currentYear = $this->config['year'] ?? date('Y');
            
            return YakimaValleyEventScraper::parseEvents($content, $baseUrl, $currentYear);
        } catch (\Exception $e) {
            return [];
        }
    }
    
    /**
     * Fallback to HTML scraping
     */
    private function fallbackToHtml($url)
    {
        try {
            $content = $this->fetchContent($url);
            if (!$content || !isset($this->config['selectors'])) {
                return [];
            }
            
            return $this->parseHtmlContent($content, $this->config);
        } catch (\Exception $e) {
            return [];
        }
    }
    
    /**
     * Fallback to iCal parsing
     */
    private function fallbackToICal($url)
    {
        try {
            $content = $this->fetchContent($url);
            if (!$content) {
                return [];
            }
            
            return $this->parseICalContent($content);
        } catch (\Exception $e) {
            return [];
        }
    }
    
    /**
     * Fallback to JSON parsing
     */
    private function fallbackToJson($url)
    {
        try {
            $content = $this->fetchContent($url);
            if (!$content) {
                return [];
            }
            
            $data = json_decode($content, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return [];
            }
            
            return $this->parseJsonContent($data, $this->config);
        } catch (\Exception $e) {
            return [];
        }
    }
    
    /**
     * Simplified HTML parsing (borrowed from EventScraper)
     */
    private function parseHtmlContent($content, $config)
    {
        $dom = new \DOMDocument();
        @$dom->loadHTML($content);
        $xpath = new \DOMXPath($dom);
        
        $events = [];
        $selectors = $config['selectors'] ?? [];
        
        if (empty($selectors['event_container'])) {
            return $events;
        }
        
        $eventNodes = $xpath->query($selectors['event_container']);
        
        foreach ($eventNodes as $node) {
            $event = [];
            
            // Extract basic fields
            foreach (['title', 'description', 'location'] as $field) {
                if (isset($selectors[$field])) {
                    $fieldNode = $xpath->query($selectors[$field], $node)->item(0);
                    $event[$field] = $fieldNode ? trim($fieldNode->textContent) : '';
                }
            }
            
            // Extract datetime
            if (isset($selectors['datetime'])) {
                $dateNode = $xpath->query($selectors['datetime'], $node)->item(0);
                $dateText = $dateNode ? trim($dateNode->textContent) : '';
                $event['start_datetime'] = $this->parseDateTime($dateText);
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
     * Simplified iCal parsing
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
                $events[] = [
                    'title' => $this->cleanICalValue($currentEvent['SUMMARY'] ?? ''),
                    'description' => $this->cleanICalValue($currentEvent['DESCRIPTION'] ?? ''),
                    'start_datetime' => $this->parseICalDateTime($currentEvent['DTSTART'] ?? ''),
                    'end_datetime' => $this->parseICalDateTime($currentEvent['DTEND'] ?? ''),
                    'location' => $this->cleanICalValue($currentEvent['LOCATION'] ?? ''),
                    'external_url' => $this->cleanICalValue($currentEvent['URL'] ?? ''),
                ];
                $currentEvent = null;
                continue;
            }
            
            if ($currentEvent !== null && strpos($line, ':') !== false) {
                list($property, $value) = explode(':', $line, 2);
                $currentEvent[$property] = $value;
            }
        }
        
        return array_filter($events, function($event) {
            return !empty($event['title']);
        });
    }
    
    /**
     * Simplified JSON parsing
     */
    private function parseJsonContent($data, $config)
    {
        $events = [];
        $eventsPath = $config['events_path'] ?? 'events';
        $mapping = $config['field_mapping'] ?? [];
        
        // Navigate to events array
        $eventsData = $this->getNestedValue($data, $eventsPath);
        if (!is_array($eventsData)) {
            return $events;
        }
        
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
     * Utility methods (simplified versions from EventScraper)
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
    
    private function parseDateTime($dateString)
    {
        if (empty($dateString)) {
            return null;
        }
        
        $timestamp = strtotime($dateString);
        return $timestamp !== false ? date('Y-m-d H:i:s', $timestamp) : null;
    }
    
    private function parseICalDateTime($datetime)
    {
        if (empty($datetime)) {
            return null;
        }
        
        $datetime = preg_replace('/;.*$/', '', $datetime);
        
        if (preg_match('/^(\d{4})(\d{2})(\d{2})T(\d{2})(\d{2})(\d{2})/', $datetime, $matches)) {
            return sprintf('%s-%s-%s %s:%s:%s', 
                $matches[1], $matches[2], $matches[3], 
                $matches[4], $matches[5], $matches[6]
            );
        }
        
        return null;
    }
    
    private function cleanICalValue($value)
    {
        return str_replace(['\\,', '\\;', '\\n'], [',', ';', "\n"], $value);
    }
    
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
    
    /**
     * Get configuration template for this scraper
     */
    public static function getConfigurationTemplate()
    {
        return [
            'firecrawl_method' => 'structured', // structured, search, basic
            'fallback_type' => 'html', // html, yakima_valley, ical, json
            'search_query' => '', // For search method
            'selectors' => [ // For HTML fallback
                'event_container' => '.event-item',
                'title' => '.event-title',
                'datetime' => '.event-date',
                'location' => '.event-location',
                'description' => '.event-description'
            ]
        ];
    }
}