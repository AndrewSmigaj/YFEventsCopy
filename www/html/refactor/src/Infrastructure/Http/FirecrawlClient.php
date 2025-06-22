<?php

declare(strict_types=1);

namespace YFEvents\Infrastructure\Http;

use YFEvents\Infrastructure\Services\RateLimiter;
use Exception;

/**
 * Firecrawl API Client
 * 
 * Handles communication with Firecrawl API for JavaScript-rendered content scraping
 */
class FirecrawlClient
{
    private string $apiKey;
    private string $baseUrl;
    private int $timeout;
    private RateLimiter $rateLimiter;
    private array $defaultHeaders;
    
    public function __construct(
        string $apiKey,
        string $baseUrl = 'https://api.firecrawl.dev/v0',
        int $timeout = 60
    ) {
        $this->apiKey = $apiKey;
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->timeout = $timeout;
        $this->rateLimiter = new RateLimiter();
        
        $this->defaultHeaders = [
            'Authorization: Bearer ' . $this->apiKey,
            'Content-Type: application/json',
            'User-Agent: YFEvents/2.0 (https://yakimafinds.com)'
        ];
    }
    
    /**
     * Scrape a URL with Firecrawl
     * 
     * @param array $config Scraping configuration
     * @return array Scraped data
     * @throws Exception
     */
    public function scrape(array $config): array
    {
        // Apply rate limiting
        $this->rateLimiter->throttle('firecrawl', 2);
        
        $endpoint = '/scrape';
        $response = $this->request('POST', $endpoint, $config);
        
        if (!$response['success']) {
            throw new Exception('Firecrawl scraping failed: ' . ($response['error'] ?? 'Unknown error'));
        }
        
        // Return the data object which contains content, markdown, html, etc.
        return $response['data'] ?? [];
    }
    
    /**
     * Crawl multiple pages
     * 
     * @param array $config Crawling configuration
     * @return array Crawled data
     * @throws Exception
     */
    public function crawl(array $config): array
    {
        $this->rateLimiter->throttle('firecrawl', 2);
        
        $endpoint = '/crawl';
        $response = $this->request('POST', $endpoint, $config);
        
        if (!$response['success']) {
            throw new Exception('Firecrawl crawling failed: ' . ($response['error'] ?? 'Unknown error'));
        }
        
        // Poll for results if job ID is returned
        if (isset($response['jobId'])) {
            return $this->pollCrawlJob($response['jobId']);
        }
        
        return $response['data'] ?? [];
    }
    
    /**
     * Poll crawl job status
     * 
     * @param string $jobId Job identifier
     * @return array Crawl results
     * @throws Exception
     */
    private function pollCrawlJob(string $jobId): array
    {
        $maxAttempts = 30;
        $attempt = 0;
        
        while ($attempt < $maxAttempts) {
            sleep(2); // Wait before polling
            
            $endpoint = "/crawl/status/{$jobId}";
            $response = $this->request('GET', $endpoint);
            
            if ($response['status'] === 'completed') {
                return $response['data'] ?? [];
            }
            
            if ($response['status'] === 'failed') {
                throw new Exception('Crawl job failed: ' . ($response['error'] ?? 'Unknown error'));
            }
            
            $attempt++;
        }
        
        throw new Exception('Crawl job timed out');
    }
    
    /**
     * Make HTTP request to Firecrawl API
     * 
     * @param string $method HTTP method
     * @param string $endpoint API endpoint
     * @param array|null $data Request data
     * @return array Response data
     * @throws Exception
     */
    private function request(string $method, string $endpoint, ?array $data = null): array
    {
        $url = $this->baseUrl . $endpoint;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->defaultHeaders);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        
        if ($method === 'POST' && $data !== null) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception('Curl error: ' . $error);
        }
        
        // Handle rate limiting
        if ($httpCode === 429) {
            throw new Exception('Rate limit exceeded. Please wait before retrying.');
        }
        
        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON response from Firecrawl');
        }
        
        // Add HTTP status to response
        $decoded['httpCode'] = $httpCode;
        $decoded['success'] = $httpCode >= 200 && $httpCode < 300;
        
        return $decoded;
    }
    
    /**
     * Test API connection
     * 
     * @return bool True if API is accessible
     */
    public function testConnection(): bool
    {
        try {
            // Test with a minimal scrape request
            $testConfig = [
                'url' => 'https://example.com',
                'formats' => ['markdown']
            ];
            $response = $this->request('POST', '/scrape', $testConfig);
            return isset($response['success']) && $response['success'] === true;
        } catch (Exception $e) {
            error_log("Firecrawl connection test error: " . $e->getMessage());
            return false;
        }
    }
}