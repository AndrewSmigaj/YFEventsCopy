# Firecrawl Integration for YFEvents

## Overview

This document outlines the integration of Firecrawl for event scraping in YFEvents, focusing on Eventbrite as the primary target due to API limitations and legal considerations.

## Current Event Scraping Landscape (2025)

### Facebook Events ❌
- **Status**: Graph API Events endpoint discontinued September 2023
- **Recommendation**: Avoid scraping due to technical and legal risks
- **Alternative**: Focus on other platforms or manual submission

### Eventbrite Events 🟡
- **Status**: Public search API removed December 2019, limited to owned events
- **Recommendation**: Implement careful scraping with Firecrawl
- **Considerations**: Respect rate limits and ToS

## Firecrawl Implementation Strategy

### Why Firecrawl for Eventbrite

1. **Technical Capabilities**:
   - Handles JavaScript-rendered content
   - Manages anti-bot mechanisms
   - Supports custom actions (scroll, wait, click)
   - Built-in proxy support

2. **Success Evidence**:
   - Multiple existing Eventbrite scrapers prove feasibility
   - Less aggressive anti-bot measures than Facebook
   - Public events designed for discovery

### Ethical Scraping Guidelines

1. **Always check robots.txt** before scraping
2. **Implement rate limiting** (2-5 seconds between requests)
3. **Use descriptive user agents**
4. **Focus on public data only**
5. **Monitor for 429 (rate limit) responses**

## Technical Implementation

### Firecrawl Configuration

```php
// config/scrapers/firecrawl.php
return [
    'api_key' => env('FIRECRAWL_API_KEY'),
    'base_url' => 'https://api.firecrawl.dev/v0',
    'timeout' => 30,
    'rate_limit' => [
        'delay' => 2, // seconds between requests
        'max_concurrent' => 1
    ],
    'user_agent' => 'YFEvents/2.0 (https://yakimafinds.com; contact@yakimafinds.com)'
];
```

### Eventbrite Scraper Class

```php
namespace YakimaFinds\Infrastructure\Scrapers;

use YakimaFinds\Domain\Events\Event;
use YakimaFinds\Infrastructure\Http\FirecrawlClient;

class EventbriteScraper implements ScraperInterface
{
    private FirecrawlClient $client;
    private array $config;
    
    public function __construct(FirecrawlClient $client, array $config)
    {
        $this->client = $client;
        $this->config = $config;
    }
    
    public function scrapeLocation(string $location, array $options = []): array
    {
        $url = $this->buildEventbriteUrl($location, $options);
        
        $scraperConfig = [
            'url' => $url,
            'actions' => [
                ['type' => 'wait', 'milliseconds' => 2000],
                ['type' => 'scroll', 'direction' => 'down', 'amount' => 3],
                ['type' => 'wait', 'milliseconds' => 1000]
            ],
            'extractSchema' => [
                'events' => [
                    'selector' => '[data-testid="event-card"]',
                    'fields' => [
                        'title' => '[data-testid="event-title"]',
                        'date' => 'time',
                        'location' => '[data-testid="event-location"]',
                        'price' => '[data-testid="event-price"]',
                        'url' => ['selector' => 'a', 'attribute' => 'href'],
                        'image' => ['selector' => 'img', 'attribute' => 'src']
                    ]
                ]
            ]
        ];
        
        return $this->client->scrape($scraperConfig);
    }
    
    private function buildEventbriteUrl(string $location, array $options): string
    {
        $baseUrl = 'https://www.eventbrite.com/d';
        $state = $options['state'] ?? 'wa';
        $city = urlencode(strtolower($location));
        
        return "{$baseUrl}/{$state}--{$city}/events/";
    }
}
```

### Rate Limiting Implementation

```php
namespace YakimaFinds\Infrastructure\Services;

class RateLimiter
{
    private array $lastRequests = [];
    
    public function throttle(string $key, int $delaySeconds = 2): void
    {
        $now = microtime(true);
        $lastRequest = $this->lastRequests[$key] ?? 0;
        
        $elapsed = $now - $lastRequest;
        if ($elapsed < $delaySeconds) {
            $sleepTime = (int)(($delaySeconds - $elapsed) * 1000000);
            usleep($sleepTime);
        }
        
        $this->lastRequests[$key] = microtime(true);
    }
    
    public function checkLimit(string $key, int $maxRequests, int $windowSeconds): bool
    {
        // Implement sliding window rate limiting
        $now = time();
        $window = $now - $windowSeconds;
        
        // Clean old entries
        $this->cleanOldEntries($window);
        
        // Count requests in window
        $count = $this->countRequests($key, $window);
        
        return $count < $maxRequests;
    }
}
```

## Database Schema Updates

```sql
-- Add Firecrawl source type
ALTER TABLE calendar_sources 
ADD COLUMN scraper_type ENUM('traditional', 'intelligent', 'firecrawl') 
DEFAULT 'traditional' AFTER source_type;

-- Add rate limiting configuration
ALTER TABLE calendar_sources
ADD COLUMN rate_limit_config JSON DEFAULT NULL COMMENT 'Rate limiting settings';

-- Add scraping metrics
CREATE TABLE IF NOT EXISTS scraping_metrics (
    id INT PRIMARY KEY AUTO_INCREMENT,
    source_id INT,
    scrape_date DATETIME,
    events_found INT DEFAULT 0,
    duration_seconds FLOAT,
    status ENUM('success', 'rate_limited', 'error') DEFAULT 'success',
    error_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (source_id) REFERENCES calendar_sources(id),
    INDEX idx_source_date (source_id, scrape_date)
);
```

## Admin Interface Updates

### Scraper Configuration Page

Add Firecrawl option to `/admin/scrapers.php`:

```php
<div class="scraper-type-options">
    <label>
        <input type="radio" name="scraper_type" value="traditional" checked>
        Traditional (HTML/iCal parsing)
    </label>
    <label>
        <input type="radio" name="scraper_type" value="intelligent">
        Intelligent (LLM-powered)
    </label>
    <label>
        <input type="radio" name="scraper_type" value="firecrawl">
        Firecrawl (JavaScript rendering)
    </label>
</div>

<div id="firecrawl-options" style="display:none;">
    <h4>Firecrawl Configuration</h4>
    <div class="form-group">
        <label>Rate Limit Delay (seconds)</label>
        <input type="number" name="rate_limit_delay" min="1" max="10" value="2">
    </div>
    <div class="form-group">
        <label>Max Events per Scrape</label>
        <input type="number" name="max_events" min="10" max="100" value="50">
    </div>
</div>
```

## Monitoring and Compliance

### Robots.txt Checker

```php
class RobotsTxtChecker
{
    public function canScrape(string $url): bool
    {
        $parsedUrl = parse_url($url);
        $robotsUrl = $parsedUrl['scheme'] . '://' . $parsedUrl['host'] . '/robots.txt';
        
        $robots = file_get_contents($robotsUrl);
        // Parse and check robots.txt rules
        
        return $this->isAllowed($robots, $url);
    }
}
```

### Scraping Metrics Dashboard

Add to admin dashboard:
- Total events scraped by source
- Success/failure rates
- Average scraping duration
- Rate limit violations

## Security Considerations

1. **API Key Storage**:
   - Store Firecrawl API key in `.env` file
   - Never commit API keys to version control

2. **Error Handling**:
   - Log errors without exposing sensitive data
   - Implement exponential backoff for failures

3. **Data Validation**:
   - Sanitize all scraped content
   - Validate URLs and dates
   - Check for duplicate events

## Deployment Checklist

- [ ] Add FIRECRAWL_API_KEY to `.env`
- [ ] Update database schema
- [ ] Deploy scraper classes
- [ ] Configure rate limiting
- [ ] Test with small batches first
- [ ] Monitor for rate limit responses
- [ ] Set up error alerting

## Legal Compliance

**Important**: Before deploying Firecrawl scrapers:

1. Review current Eventbrite Terms of Service
2. Check robots.txt for each domain
3. Implement user agent identification
4. Respect rate limits and crawl delays
5. Only scrape publicly available data
6. Consider reaching out to Eventbrite for permission

## Alternative Approaches

If Firecrawl scraping becomes problematic:

1. **Partner with event platforms** for API access
2. **Use RSS feeds** where available
3. **Implement user submission** forms
4. **Work with local event organizers** directly

---

*Last updated: June 18, 2025*
*Version: 1.0.0*