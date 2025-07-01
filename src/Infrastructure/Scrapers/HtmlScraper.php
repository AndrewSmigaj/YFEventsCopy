<?php

declare(strict_types=1);

namespace YFEvents\Infrastructure\Scrapers;

use YFEvents\Domain\Scrapers\ScrapingResult;
use YFEvents\Domain\Scrapers\ScrapingSource;
use YFEvents\Domain\Events\Event;
use DOMDocument;
use DOMXPath;

class HtmlScraper extends AbstractScraper
{
    protected array $supportedTypes = ['html'];
    protected string $name = 'HTML Scraper';
    protected string $version = '2.0.0';

    public function getConfigurationSchema(): array
    {
        return [
            'selectors' => [
                'type' => 'array',
                'required' => true,
                'description' => 'CSS selectors or XPath expressions for extracting event data'
            ],
            'container' => [
                'type' => 'string',
                'required' => true,
                'description' => 'CSS selector for event container elements'
            ],
            'pagination' => [
                'type' => 'array',
                'required' => false,
                'description' => 'Pagination configuration for multi-page scraping'
            ],
            'wait_time' => [
                'type' => 'integer',
                'required' => false,
                'description' => 'Wait time between requests in milliseconds'
            ]
        ];
    }

    protected function performScrape(ScrapingSource $source): ScrapingResult
    {
        $config = $source->getConfiguration();
        $result = ScrapingResult::success($source->getType());
        
        $urls = $this->getUrlsToScrape($source);
        
        foreach ($urls as $url) {
            try {
                $pageResult = $this->scrapePage($url, $config);
                $result->merge($pageResult);
                
                // Add delay between pages if configured
                $waitTime = $config['wait_time'] ?? 1000;
                if ($waitTime > 0) {
                    usleep($waitTime * 1000);
                }
                
            } catch (\Exception $e) {
                $result->addError("Failed to scrape page {$url}: " . $e->getMessage());
            }
        }
        
        return $result;
    }

    /**
     * Scrape a single page
     */
    protected function scrapePage(string $url, array $config): ScrapingResult
    {
        $content = $this->fetchContent($url);
        $result = ScrapingResult::success('html');
        
        $document = $this->createDomDocument($content);
        $xpath = new DOMXPath($document);
        
        $containerSelector = $config['container'];
        $containers = $this->findElements($xpath, $containerSelector);
        
        if (empty($containers)) {
            $result->addWarning("No event containers found with selector: {$containerSelector}");
            return $result;
        }
        
        $selectors = $config['selectors'];
        
        foreach ($containers as $container) {
            try {
                $eventData = $this->extractEventData($xpath, $container, $selectors);
                
                if ($this->isValidEventData($eventData)) {
                    $event = $this->createEventFromData($eventData, $url);
                    $result->addEvent($event);
                } else {
                    $result->addWarning("Incomplete event data found, skipping");
                }
                
            } catch (\Exception $e) {
                $result->addError("Failed to extract event data: " . $e->getMessage());
            }
        }
        
        return $result;
    }

    /**
     * Extract event data from a container element
     */
    protected function extractEventData(DOMXPath $xpath, \DOMNode $container, array $selectors): array
    {
        $eventData = [];
        
        foreach ($selectors as $field => $selector) {
            if (is_array($selector)) {
                // Multiple selectors - try each until one works
                foreach ($selector as $singleSelector) {
                    $value = $this->extractFieldValue($xpath, $container, $singleSelector, $field);
                    if (!empty($value)) {
                        $eventData[$field] = $value;
                        break;
                    }
                }
            } else {
                // Single selector
                $value = $this->extractFieldValue($xpath, $container, $selector, $field);
                if (!empty($value)) {
                    $eventData[$field] = $value;
                }
            }
        }
        
        return $eventData;
    }

    /**
     * Extract field value using selector
     */
    protected function extractFieldValue(DOMXPath $xpath, \DOMNode $container, string $selector, string $field): ?string
    {
        $elements = $this->findElements($xpath, $selector, $container);
        
        if (empty($elements)) {
            return null;
        }
        
        $element = $elements[0];
        
        // Special handling for different field types
        switch ($field) {
            case 'image':
            case 'thumbnail':
                return $this->extractImageUrl($element);
            case 'link':
            case 'url':
                return $this->extractLink($element);
            case 'date':
            case 'start_date':
            case 'end_date':
                return $this->extractDateTime($element);
            default:
                return $this->cleanText($element->textContent ?? '');
        }
    }

    /**
     * Find elements using CSS selector or XPath
     */
    protected function findElements(DOMXPath $xpath, string $selector, \DOMNode $context = null): array
    {
        // Check if it's already an XPath expression
        if (str_starts_with($selector, '//') || str_starts_with($selector, './')) {
            $xpathExpr = $selector;
        } else {
            // Convert CSS selector to XPath
            $xpathExpr = $this->cssToXpath($selector);
        }
        
        try {
            $nodeList = $context 
                ? $xpath->query($xpathExpr, $context)
                : $xpath->query($xpathExpr);
            
            return iterator_to_array($nodeList);
        } catch (\Exception $e) {
            $this->logger->warning("Failed to execute selector", [
                'selector' => $selector,
                'xpath' => $xpathExpr,
                'error' => $e->getMessage()
            ]);
            
            return [];
        }
    }

    /**
     * Convert CSS selector to XPath
     */
    protected function cssToXpath(string $cssSelector): string
    {
        // Basic CSS to XPath conversion
        $xpath = './/' . $cssSelector;
        
        // Handle class selectors
        $xpath = preg_replace('/\.([a-zA-Z0-9_-]+)/', '*[contains(@class, "$1")]', $xpath);
        
        // Handle ID selectors
        $xpath = preg_replace('/#([a-zA-Z0-9_-]+)/', '*[@id="$1"]', $xpath);
        
        // Handle attribute selectors
        $xpath = preg_replace('/\[([a-zA-Z0-9_-]+)="([^"]+)"\]/', '[@$1="$2"]', $xpath);
        
        // Handle descendant combinator
        $xpath = str_replace(' ', '//', $xpath);
        
        // Clean up
        $xpath = str_replace('//.', '//*[contains(@class, ', $xpath);
        
        return $xpath;
    }

    /**
     * Extract image URL from element
     */
    protected function extractImageUrl(\DOMNode $element): ?string
    {
        if ($element->nodeName === 'img') {
            return $element->getAttribute('src') ?: $element->getAttribute('data-src');
        }
        
        // Look for image in style attribute
        $style = $element->getAttribute('style');
        if (preg_match('/background-image:\s*url\(["\']?([^"\']+)["\']?\)/', $style, $matches)) {
            return $matches[1];
        }
        
        return null;
    }

    /**
     * Extract link URL from element
     */
    protected function extractLink(\DOMNode $element): ?string
    {
        if ($element->nodeName === 'a') {
            return $element->getAttribute('href');
        }
        
        // Look for link in data attributes
        return $element->getAttribute('data-href') ?: $element->getAttribute('data-url');
    }

    /**
     * Extract datetime from element
     */
    protected function extractDateTime(\DOMNode $element): ?string
    {
        // Check for datetime attribute
        $datetime = $element->getAttribute('datetime');
        if (!empty($datetime)) {
            return $datetime;
        }
        
        // Check for data attributes
        $dataDate = $element->getAttribute('data-date') ?: $element->getAttribute('data-datetime');
        if (!empty($dataDate)) {
            return $dataDate;
        }
        
        // Use text content
        return $element->textContent;
    }

    /**
     * Create DOM document from HTML content
     */
    protected function createDomDocument(string $html): DOMDocument
    {
        $document = new DOMDocument();
        
        // Suppress errors for malformed HTML
        libxml_use_internal_errors(true);
        
        // Load HTML with UTF-8 encoding
        $document->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        
        // Clear errors
        libxml_clear_errors();
        
        return $document;
    }

    /**
     * Get URLs to scrape (including pagination)
     */
    protected function getUrlsToScrape(ScrapingSource $source): array
    {
        $urls = [$source->getUrl()];
        
        $config = $source->getConfiguration();
        $pagination = $config['pagination'] ?? null;
        
        if ($pagination && !empty($pagination['enabled'])) {
            $additionalUrls = $this->generatePaginationUrls($source, $pagination);
            $urls = array_merge($urls, $additionalUrls);
        }
        
        return $urls;
    }

    /**
     * Generate pagination URLs
     */
    protected function generatePaginationUrls(ScrapingSource $source, array $pagination): array
    {
        $urls = [];
        $baseUrl = $source->getUrl();
        
        $type = $pagination['type'] ?? 'query_param';
        $param = $pagination['param'] ?? 'page';
        $maxPages = $pagination['max_pages'] ?? 5;
        $startPage = $pagination['start_page'] ?? 2;
        
        for ($page = $startPage; $page <= $maxPages; $page++) {
            switch ($type) {
                case 'query_param':
                    $separator = strpos($baseUrl, '?') !== false ? '&' : '?';
                    $urls[] = $baseUrl . $separator . $param . '=' . $page;
                    break;
                    
                case 'path':
                    $urls[] = rtrim($baseUrl, '/') . '/' . $page;
                    break;
                    
                case 'template':
                    $template = $pagination['template'] ?? '';
                    $urls[] = str_replace('{page}', (string)$page, $template);
                    break;
            }
        }
        
        return $urls;
    }

    /**
     * Validate event data completeness
     */
    protected function isValidEventData(array $eventData): bool
    {
        $required = ['title'];
        
        foreach ($required as $field) {
            if (empty($eventData[$field])) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * Create Event entity from extracted data
     */
    protected function createEventFromData(array $eventData, string $sourceUrl): Event
    {
        // Parse dates
        $startDate = null;
        $endDate = null;
        
        if (!empty($eventData['start_date'])) {
            $startDate = $this->parseDate($eventData['start_date']);
        } elseif (!empty($eventData['date'])) {
            $startDate = $this->parseDate($eventData['date']);
        }
        
        if (!empty($eventData['end_date'])) {
            $endDate = $this->parseDate($eventData['end_date']);
        }
        
        return Event::fromArray([
            'title' => $this->cleanText($eventData['title']),
            'description' => $this->cleanText($eventData['description'] ?? ''),
            'start_datetime' => $startDate?->format('Y-m-d H:i:s'),
            'end_datetime' => $endDate?->format('Y-m-d H:i:s'),
            'location' => $this->cleanText($eventData['location'] ?? ''),
            'venue' => $this->cleanText($eventData['venue'] ?? ''),
            'address' => $this->cleanText($eventData['address'] ?? ''),
            'url' => $eventData['url'] ?? $eventData['link'] ?? null,
            'image_url' => $eventData['image'] ?? $eventData['thumbnail'] ?? null,
            'price' => $eventData['price'] ?? null,
            'contact_phone' => $eventData['phone'] ?? null,
            'contact_email' => $eventData['email'] ?? null,
            'source_url' => $sourceUrl,
            'status' => 'pending'
        ]);
    }
}