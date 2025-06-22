<?php

declare(strict_types=1);

namespace YFEvents\Infrastructure\Services;

/**
 * Rate Limiter Service
 * 
 * Implements throttling and rate limiting for API requests and scraping
 */
class RateLimiter
{
    private array $lastRequests = [];
    private array $requestWindows = [];
    
    /**
     * Throttle requests with a minimum delay between calls
     * 
     * @param string $key Unique identifier for the rate limit
     * @param float $delaySeconds Minimum seconds between requests
     */
    public function throttle(string $key, float $delaySeconds = 2.0): void
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
    
    /**
     * Check if request is within rate limit
     * 
     * @param string $key Unique identifier for the rate limit
     * @param int $maxRequests Maximum requests allowed
     * @param int $windowSeconds Time window in seconds
     * @return bool True if within limit, false if exceeded
     */
    public function checkLimit(string $key, int $maxRequests, int $windowSeconds): bool
    {
        $now = time();
        $window = $now - $windowSeconds;
        
        // Initialize window if not exists
        if (!isset($this->requestWindows[$key])) {
            $this->requestWindows[$key] = [];
        }
        
        // Clean old entries
        $this->requestWindows[$key] = array_filter(
            $this->requestWindows[$key],
            fn($timestamp) => $timestamp > $window
        );
        
        // Check if under limit
        $count = count($this->requestWindows[$key]);
        return $count < $maxRequests;
    }
    
    /**
     * Record a request for rate limiting
     * 
     * @param string $key Unique identifier for the rate limit
     */
    public function recordRequest(string $key): void
    {
        if (!isset($this->requestWindows[$key])) {
            $this->requestWindows[$key] = [];
        }
        
        $this->requestWindows[$key][] = time();
    }
    
    /**
     * Get remaining requests in current window
     * 
     * @param string $key Unique identifier for the rate limit
     * @param int $maxRequests Maximum requests allowed
     * @param int $windowSeconds Time window in seconds
     * @return int Number of remaining requests
     */
    public function getRemainingRequests(string $key, int $maxRequests, int $windowSeconds): int
    {
        $now = time();
        $window = $now - $windowSeconds;
        
        if (!isset($this->requestWindows[$key])) {
            return $maxRequests;
        }
        
        // Count requests in current window
        $count = count(array_filter(
            $this->requestWindows[$key],
            fn($timestamp) => $timestamp > $window
        ));
        
        return max(0, $maxRequests - $count);
    }
    
    /**
     * Reset rate limit for a key
     * 
     * @param string $key Unique identifier to reset
     */
    public function reset(string $key): void
    {
        unset($this->lastRequests[$key]);
        unset($this->requestWindows[$key]);
    }
    
    /**
     * Get time until next request is allowed
     * 
     * @param string $key Unique identifier for the rate limit
     * @param float $delaySeconds Required delay between requests
     * @return float Seconds until next request (0 if allowed now)
     */
    public function getWaitTime(string $key, float $delaySeconds = 2.0): float
    {
        if (!isset($this->lastRequests[$key])) {
            return 0;
        }
        
        $elapsed = microtime(true) - $this->lastRequests[$key];
        return max(0, $delaySeconds - $elapsed);
    }
    
    /**
     * Apply exponential backoff for failed requests
     * 
     * @param string $key Unique identifier
     * @param int $attempt Current attempt number
     * @param float $baseDelay Base delay in seconds
     * @param float $maxDelay Maximum delay in seconds
     */
    public function exponentialBackoff(
        string $key, 
        int $attempt, 
        float $baseDelay = 1.0, 
        float $maxDelay = 60.0
    ): void {
        // Calculate delay: baseDelay * 2^(attempt - 1)
        $delay = min($baseDelay * pow(2, $attempt - 1), $maxDelay);
        
        // Add jitter to prevent thundering herd
        $jitter = $delay * 0.1 * (mt_rand() / mt_getrandmax());
        $delay += $jitter;
        
        $this->throttle($key, $delay);
    }
}