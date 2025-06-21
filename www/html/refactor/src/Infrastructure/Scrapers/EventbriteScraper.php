<?php

declare(strict_types=1);

namespace YakimaFinds\Infrastructure\Scrapers;

use YakimaFinds\Domain\Events\Event;
use YakimaFinds\Infrastructure\Http\FirecrawlClient;
use YakimaFinds\Infrastructure\Services\RateLimiter;
use DateTime;
use Exception;

/**
 * Eventbrite Event Scraper using Firecrawl
 * 
 * Scrapes public Eventbrite events using Firecrawl for JavaScript rendering
 */
class EventbriteScraper implements ScraperInterface
{
    private FirecrawlClient $client;
    private RateLimiter $rateLimiter;
    private array $config;
    
    public function __construct(FirecrawlClient $client, array $config = [])
    {
        $this->client = $client;
        $this->rateLimiter = new RateLimiter();
        $this->config = array_merge([
            'max_events' => 50,
            'rate_limit_delay' => 2,
            'timeout' => 30,
            'state' => 'wa'
        ], $config);
    }
    
    /**
     * Scrape events for a specific location
     * 
     * @param string $location City name
     * @param array $options Additional options
     * @return array Array of Event objects
     */
    public function scrapeLocation(string $location, array $options = []): array
    {
        $url = $this->buildEventbriteUrl($location, $options);
        
        // Check robots.txt compliance
        if (!$this->checkRobotsTxt($url)) {
            throw new Exception("Scraping not allowed by robots.txt for: $url");
        }
        
        $scraperConfig = [
            'url' => $url,
            'formats' => ['markdown', 'html']
        ];
        
        try {
            $response = $this->client->scrape($scraperConfig);
            return $this->parseEvents($response, $location);
        } catch (Exception $e) {
            error_log("Eventbrite scraping error for $location: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Build Eventbrite URL for location
     * 
     * @param string $location City name
     * @param array $options Additional options
     * @return string Eventbrite URL
     */
    private function buildEventbriteUrl(string $location, array $options): string
    {
        $baseUrl = 'https://www.eventbrite.com/d';
        $state = $options['state'] ?? $this->config['state'];
        $city = urlencode(str_replace(' ', '-', strtolower($location)));
        
        // Support different URL patterns
        if (!empty($options['category'])) {
            $category = urlencode($options['category']);
            return "{$baseUrl}/{$state}--{$city}/{$category}-events/";
        }
        
        return "{$baseUrl}/{$state}--{$city}/events/";
    }
    
    /**
     * Parse scraped data into Event objects
     * 
     * @param array $response Firecrawl response
     * @param string $location Default location
     * @return array Array of Event objects
     */
    private function parseEvents(array $response, string $location): array
    {
        $events = [];
        
        // Parse markdown content for events
        if (!isset($response['markdown'])) {
            return $events;
        }
        
        $markdown = $response['markdown'];
        
        // Extract event blocks from markdown
        // Look for patterns like event titles with links
        $pattern = '/\[([^\]]+)\]\((https:\/\/www\.eventbrite\.[^)]+\/e\/[^)]+)\)/i';
        preg_match_all($pattern, $markdown, $matches, PREG_SET_ORDER);
        
        foreach ($matches as $match) {
            $title = trim($match[1]);
            $url = $match[2];
            
            // Skip navigation links
            if (stripos($title, 'popular in') !== false || 
                stripos($title, 'online events') !== false ||
                stripos($title, 'this weekend') !== false ||
                stripos($title, 'eventbrite') !== false) {
                continue;
            }
            
            // Create a basic event from the title and URL
            try {
                $event = $this->createBasicEvent($title, $url, $location);
                if ($event) {
                    $events[] = $event;
                }
            } catch (Exception $e) {
                error_log("Error creating event: " . $e->getMessage());
            }
            
            // Limit events
            if (count($events) >= $this->config['max_events']) {
                break;
            }
        }
        
        return $events;
    }
    
    /**
     * Create Event object from scraped data
     * 
     * @param array $data Scraped event data
     * @param string $defaultLocation Default location
     * @return Event|null
     */
    private function createEventFromData(array $data, string $defaultLocation): ?Event
    {
        // Skip if missing required fields
        if (empty($data['title']) || empty($data['date'])) {
            return null;
        }
        
        // Parse date
        $dateTime = $this->parseEventDate($data['date']);
        if (!$dateTime) {
            return null;
        }
        
        // Clean and validate URL
        $url = $this->cleanEventUrl($data['url'] ?? '');
        if (!$url) {
            return null;
        }
        
        // Extract location details
        $locationParts = $this->parseLocation($data['location'] ?? $defaultLocation);
        
        return new Event([
            'title' => $this->cleanText($data['title']),
            'description' => $this->generateDescription($data),
            'start_datetime' => $dateTime->format('Y-m-d H:i:s'),
            'end_datetime' => $dateTime->modify('+3 hours')->format('Y-m-d H:i:s'),
            'venue_name' => $locationParts['venue'] ?? 'TBD',
            'address' => $locationParts['address'] ?? '',
            'city' => $locationParts['city'] ?? $defaultLocation,
            'state' => $locationParts['state'] ?? $this->config['state'],
            'url' => $url,
            'image_url' => $this->cleanImageUrl($data['image'] ?? ''),
            'source' => 'Eventbrite',
            'source_id' => $this->extractEventId($url),
            'price' => $this->parsePrice($data['price'] ?? ''),
            'organizer' => $data['organizer'] ?? '',
            'status' => 'pending' // Always pending until approved
        ]);
    }
    
    /**
     * Parse event date string
     * 
     * @param string $dateStr Date string from Eventbrite
     * @return DateTime|null
     */
    private function parseEventDate(string $dateStr): ?DateTime
    {
        // Common Eventbrite date formats
        $formats = [
            'D, M j, Y \a\t g:i A T',
            'l, F j, Y \a\t g:i A',
            'M j \a\t g:i A',
            'D, M j',
            'Y-m-d H:i:s'
        ];
        
        foreach ($formats as $format) {
            $date = DateTime::createFromFormat($format, $dateStr);
            if ($date !== false) {
                return $date;
            }
        }
        
        // Try PHP's strtotime as fallback
        $timestamp = strtotime($dateStr);
        if ($timestamp !== false) {
            return new DateTime('@' . $timestamp);
        }
        
        return null;
    }
    
    /**
     * Parse location string into components
     * 
     * @param string $locationStr Location string
     * @return array Location components
     */
    private function parseLocation(string $locationStr): array
    {
        $parts = [
            'venue' => '',
            'address' => '',
            'city' => '',
            'state' => $this->config['state']
        ];
        
        // Parse "Venue Name • City, State" format
        if (strpos($locationStr, '•') !== false) {
            [$venue, $cityState] = explode('•', $locationStr, 2);
            $parts['venue'] = trim($venue);
            
            if (strpos($cityState, ',') !== false) {
                [$city, $state] = explode(',', $cityState, 2);
                $parts['city'] = trim($city);
                $parts['state'] = trim($state);
            }
        } else {
            $parts['address'] = $locationStr;
        }
        
        return $parts;
    }
    
    /**
     * Clean event URL
     * 
     * @param string $url Raw URL
     * @return string|null Clean URL or null if invalid
     */
    private function cleanEventUrl(string $url): ?string
    {
        if (empty($url)) {
            return null;
        }
        
        // Handle relative URLs
        if (strpos($url, '/') === 0) {
            $url = 'https://www.eventbrite.com' . $url;
        }
        
        // Remove query parameters
        $parsed = parse_url($url);
        if (!$parsed || !isset($parsed['host'])) {
            return null;
        }
        
        // Ensure it's an Eventbrite URL
        if (strpos($parsed['host'], 'eventbrite.com') === false) {
            return null;
        }
        
        return $parsed['scheme'] . '://' . $parsed['host'] . $parsed['path'];
    }
    
    /**
     * Extract event ID from URL
     * 
     * @param string $url Event URL
     * @return string Event ID
     */
    private function extractEventId(string $url): string
    {
        // Extract numeric ID from URL
        if (preg_match('/(\d+)(?:\?|$)/', $url, $matches)) {
            return 'eb_' . $matches[1];
        }
        
        return 'eb_' . md5($url);
    }
    
    /**
     * Parse price string
     * 
     * @param string $priceStr Price string
     * @return string Clean price
     */
    private function parsePrice(string $priceStr): string
    {
        if (empty($priceStr)) {
            return 'Free';
        }
        
        // Common free indicators
        if (stripos($priceStr, 'free') !== false) {
            return 'Free';
        }
        
        return $this->cleanText($priceStr);
    }
    
    /**
     * Generate event description
     * 
     * @param array $data Event data
     * @return string Description
     */
    private function generateDescription(array $data): string
    {
        $parts = [];
        
        if (!empty($data['organizer'])) {
            $parts[] = "Organized by: " . $data['organizer'];
        }
        
        if (!empty($data['price']) && $data['price'] !== 'Free') {
            $parts[] = "Price: " . $data['price'];
        }
        
        $parts[] = "Source: Eventbrite";
        
        return implode("\n", $parts);
    }
    
    /**
     * Clean image URL
     * 
     * @param string $url Image URL
     * @return string Clean URL
     */
    private function cleanImageUrl(string $url): string
    {
        if (empty($url)) {
            return '';
        }
        
        // Handle protocol-relative URLs
        if (strpos($url, '//') === 0) {
            $url = 'https:' . $url;
        }
        
        return $url;
    }
    
    /**
     * Clean text content
     * 
     * @param string $text Raw text
     * @return string Clean text
     */
    private function cleanText(string $text): string
    {
        // Remove extra whitespace
        $text = preg_replace('/\s+/', ' ', $text);
        
        // Trim and decode HTML entities
        return html_entity_decode(trim($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    
    /**
     * Check robots.txt compliance
     * 
     * @param string $url URL to check
     * @return bool True if allowed
     */
    private function checkRobotsTxt(string $url): bool
    {
        // For now, return true but log warning
        // In production, implement proper robots.txt checking
        error_log("Warning: robots.txt check not fully implemented for: $url");
        return true;
    }
    
    /**
     * Create a basic event from title and URL
     * 
     * @param string $title Event title
     * @param string $url Event URL
     * @param string $location Default location
     * @return Event|null
     */
    private function createBasicEvent(string $title, string $url, string $location): ?Event
    {
        // Extract event ID from URL
        if (!preg_match('/\/e\/[^\/]+-(\d+)/', $url, $idMatch)) {
            return null;
        }
        
        $eventId = 'eb_' . $idMatch[1];
        
        // Create basic event with constructor parameters
        return new Event(
            id: null,
            title: $this->cleanText($title),
            description: "Event from Eventbrite. Visit the event page for full details.",
            startDateTime: new DateTime('+1 week'), // Placeholder
            endDateTime: new DateTime('+1 week +2 hours'), // Placeholder
            location: $location,
            address: null,
            latitude: null,
            longitude: null,
            contactInfo: null,
            externalUrl: $this->cleanEventUrl($url),
            sourceId: null,
            cmsUserId: null,
            status: 'pending',
            featured: false,
            externalEventId: $eventId
        );
    }
}