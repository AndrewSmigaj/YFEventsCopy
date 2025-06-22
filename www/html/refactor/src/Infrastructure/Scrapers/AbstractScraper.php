<?php

declare(strict_types=1);

namespace YFEvents\Infrastructure\Scrapers;

use YFEvents\Domain\Scrapers\ScraperInterface;
use YFEvents\Domain\Scrapers\ScrapingResult;
use YFEvents\Domain\Scrapers\ScrapingSource;
use YFEvents\Infrastructure\Services\HttpClientInterface;
use Psr\Log\LoggerInterface;

abstract class AbstractScraper implements ScraperInterface
{
    protected array $supportedTypes = [];
    protected string $name = '';
    protected string $version = '1.0.0';

    public function __construct(
        protected HttpClientInterface $httpClient,
        protected LoggerInterface $logger
    ) {}

    public function getName(): string
    {
        return $this->name ?: static::class;
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function canHandle(ScrapingSource $source): bool
    {
        return in_array($source->getType(), $this->supportedTypes);
    }

    public function scrape(ScrapingSource $source): ScrapingResult
    {
        $startTime = microtime(true);
        
        try {
            $this->validateSource($source);
            
            $result = $this->performScrape($source);
            $result->setDuration(microtime(true) - $startTime);
            
            $this->logger->info("Scraping completed", [
                'source_id' => $source->getId(),
                'source_name' => $source->getName(),
                'events_found' => $result->getEventCount(),
                'duration' => $result->getDuration()
            ]);
            
            return $result;
            
        } catch (\Exception $e) {
            $duration = microtime(true) - $startTime;
            
            $this->logger->error("Scraping failed", [
                'source_id' => $source->getId(),
                'source_name' => $source->getName(),
                'error' => $e->getMessage(),
                'duration' => $duration
            ]);
            
            $result = ScrapingResult::failure($source->getType(), $e->getMessage());
            $result->setDuration($duration);
            
            return $result;
        }
    }

    public function testSource(ScrapingSource $source): bool
    {
        try {
            $response = $this->httpClient->get($source->getUrl(), [
                'timeout' => 10,
                'headers' => $this->getDefaultHeaders()
            ]);
            
            return $response->getStatusCode() === 200;
        } catch (\Exception $e) {
            $this->logger->warning("Source test failed", [
                'source_id' => $source->getId(),
                'url' => $source->getUrl(),
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }

    public function validateConfiguration(array $config): array
    {
        $errors = [];
        $schema = $this->getConfigurationSchema();
        
        foreach ($schema as $field => $rules) {
            if (!empty($rules['required']) && !isset($config[$field])) {
                $errors[$field] = "Field '{$field}' is required";
                continue;
            }
            
            if (isset($config[$field]) && isset($rules['type'])) {
                $value = $config[$field];
                $expectedType = $rules['type'];
                
                switch ($expectedType) {
                    case 'string':
                        if (!is_string($value)) {
                            $errors[$field] = "Field '{$field}' must be a string";
                        }
                        break;
                    case 'array':
                        if (!is_array($value)) {
                            $errors[$field] = "Field '{$field}' must be an array";
                        }
                        break;
                    case 'integer':
                        if (!is_int($value)) {
                            $errors[$field] = "Field '{$field}' must be an integer";
                        }
                        break;
                    case 'boolean':
                        if (!is_bool($value)) {
                            $errors[$field] = "Field '{$field}' must be a boolean";
                        }
                        break;
                }
            }
        }
        
        return $errors;
    }

    /**
     * Perform the actual scraping - to be implemented by concrete scrapers
     */
    abstract protected function performScrape(ScrapingSource $source): ScrapingResult;

    /**
     * Validate source before scraping
     */
    protected function validateSource(ScrapingSource $source): void
    {
        if (!$source->isActive()) {
            throw new \RuntimeException("Source is not active");
        }
        
        if (!$this->canHandle($source)) {
            throw new \RuntimeException("Scraper cannot handle source type: " . $source->getType());
        }
        
        $configErrors = $this->validateConfiguration($source->getConfiguration());
        if (!empty($configErrors)) {
            throw new \RuntimeException("Invalid configuration: " . implode(', ', $configErrors));
        }
    }

    /**
     * Get default HTTP headers
     */
    protected function getDefaultHeaders(): array
    {
        return [
            'User-Agent' => 'YakimaFinds Event Scraper/' . $this->getVersion(),
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language' => 'en-US,en;q=0.5',
            'Accept-Encoding' => 'gzip, deflate',
            'Connection' => 'keep-alive',
            'Upgrade-Insecure-Requests' => '1'
        ];
    }

    /**
     * Fetch content from URL
     */
    protected function fetchContent(string $url, array $options = []): string
    {
        $defaultOptions = [
            'timeout' => 30,
            'headers' => $this->getDefaultHeaders()
        ];
        
        $options = array_merge($defaultOptions, $options);
        
        $response = $this->httpClient->get($url, $options);
        
        if ($response->getStatusCode() !== 200) {
            throw new \RuntimeException("HTTP {$response->getStatusCode()}: Failed to fetch content from {$url}");
        }
        
        return $response->getBody();
    }

    /**
     * Parse date string with fallback handling
     */
    protected function parseDate(string $dateString, string $timezone = 'America/Los_Angeles'): ?\DateTime
    {
        if (empty($dateString)) {
            return null;
        }
        
        $dateString = trim($dateString);
        
        // Common date formats to try
        $formats = [
            'Y-m-d H:i:s',
            'Y-m-d\TH:i:s',
            'Y-m-d\TH:i:s\Z',
            'Y-m-d',
            'm/d/Y H:i:s',
            'm/d/Y',
            'M j, Y H:i:s',
            'M j, Y',
            'F j, Y',
            'l, F j, Y'
        ];
        
        foreach ($formats as $format) {
            try {
                $date = \DateTime::createFromFormat($format, $dateString, new \DateTimeZone($timezone));
                if ($date !== false) {
                    return $date;
                }
            } catch (\Exception $e) {
                continue;
            }
        }
        
        // Fallback to strtotime
        try {
            $timestamp = strtotime($dateString);
            if ($timestamp !== false) {
                $date = new \DateTime();
                $date->setTimestamp($timestamp);
                $date->setTimezone(new \DateTimeZone($timezone));
                return $date;
            }
        } catch (\Exception $e) {
            // Fall through to null
        }
        
        $this->logger->warning("Failed to parse date", [
            'date_string' => $dateString,
            'scraper' => $this->getName()
        ]);
        
        return null;
    }

    /**
     * Clean and normalize text content
     */
    protected function cleanText(string $text): string
    {
        // Remove HTML tags
        $text = strip_tags($text);
        
        // Decode HTML entities
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        // Normalize whitespace
        $text = preg_replace('/\s+/', ' ', $text);
        
        // Trim
        $text = trim($text);
        
        return $text;
    }

    /**
     * Extract coordinates from address or location string
     */
    protected function extractCoordinates(string $location): ?array
    {
        // Look for coordinate patterns like "47.123, -120.456"
        if (preg_match('/(-?\d+\.?\d*),\s*(-?\d+\.?\d*)/', $location, $matches)) {
            $lat = (float)$matches[1];
            $lng = (float)$matches[2];
            
            // Validate coordinates are reasonable for our area
            if ($lat >= 45 && $lat <= 49 && $lng >= -125 && $lng <= -115) {
                return ['latitude' => $lat, 'longitude' => $lng];
            }
        }
        
        return null;
    }

    /**
     * Generate a unique hash for an event to detect duplicates
     */
    protected function generateEventHash(array $eventData): string
    {
        $hashData = [
            'title' => $this->cleanText($eventData['title'] ?? ''),
            'date' => $eventData['start_datetime'] ?? '',
            'location' => $this->cleanText($eventData['location'] ?? '')
        ];
        
        return md5(serialize($hashData));
    }
}