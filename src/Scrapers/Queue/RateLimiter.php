<?php

namespace YakimaFinds\Scrapers\Queue;

use PDO;
use Exception;

/**
 * Rate Limiter for Scraping Operations
 * Implements token bucket algorithm with Redis support
 */
class RateLimiter
{
    private PDO $db;
    private ?object $redis = null;
    private bool $useRedis = false;
    private string $tableName = 'scraper_rate_limits';

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->initializeRedis();
        $this->ensureTableExists();
    }

    /**
     * Check if operation is within rate limit
     */
    public function checkLimit(string $key, int $maxRequests, int $windowSeconds): bool
    {
        try {
            if ($this->useRedis) {
                return $this->checkLimitRedis($key, $maxRequests, $windowSeconds);
            } else {
                return $this->checkLimitDatabase($key, $maxRequests, $windowSeconds);
            }
        } catch (Exception $e) {
            error_log("Rate limiter error: " . $e->getMessage());
            return true; // Fail open to avoid blocking all operations
        }
    }

    /**
     * Record usage of rate limit
     */
    public function recordUsage(string $key): void
    {
        try {
            if ($this->useRedis) {
                $this->recordUsageRedis($key);
            } else {
                $this->recordUsageDatabase($key);
            }
        } catch (Exception $e) {
            error_log("Rate limiter record error: " . $e->getMessage());
        }
    }

    /**
     * Get remaining requests for a key
     */
    public function getRemainingRequests(string $key, int $maxRequests, int $windowSeconds): int
    {
        try {
            if ($this->useRedis) {
                return $this->getRemainingRedis($key, $maxRequests, $windowSeconds);
            } else {
                return $this->getRemainingDatabase($key, $maxRequests, $windowSeconds);
            }
        } catch (Exception $e) {
            error_log("Rate limiter remaining error: " . $e->getMessage());
            return $maxRequests; // Fail open
        }
    }

    /**
     * Get time until rate limit resets
     */
    public function getResetTime(string $key, int $windowSeconds): int
    {
        try {
            if ($this->useRedis) {
                return $this->getResetTimeRedis($key, $windowSeconds);
            } else {
                return $this->getResetTimeDatabase($key, $windowSeconds);
            }
        } catch (Exception $e) {
            error_log("Rate limiter reset time error: " . $e->getMessage());
            return 0; // Fail open
        }
    }

    /**
     * Advanced rate limiting with multiple tiers
     */
    public function checkAdvancedLimit(string $key, array $limits): bool
    {
        foreach ($limits as $limit) {
            $maxRequests = $limit['max_requests'];
            $windowSeconds = $limit['window_seconds'];
            
            if (!$this->checkLimit($key, $maxRequests, $windowSeconds)) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * Get rate limiting configuration for different scraper types
     */
    public static function getDefaultLimits(): array
    {
        return [
            'source_limits' => [
                ['max_requests' => 5, 'window_seconds' => 3600], // 5 per hour
                ['max_requests' => 2, 'window_seconds' => 600],  // 2 per 10 minutes
                ['max_requests' => 1, 'window_seconds' => 300]   // 1 per 5 minutes
            ],
            'domain_limits' => [
                ['max_requests' => 20, 'window_seconds' => 3600], // 20 per hour per domain
                ['max_requests' => 10, 'window_seconds' => 600],  // 10 per 10 minutes
                ['max_requests' => 5, 'window_seconds' => 300]    // 5 per 5 minutes
            ],
            'worker_limits' => [
                ['max_requests' => 100, 'window_seconds' => 3600], // 100 per hour per worker
                ['max_requests' => 30, 'window_seconds' => 600],   // 30 per 10 minutes
                ['max_requests' => 10, 'window_seconds' => 300]    // 10 per 5 minutes
            ],
            'global_limits' => [
                ['max_requests' => 1000, 'window_seconds' => 3600], // 1000 per hour globally
                ['max_requests' => 200, 'window_seconds' => 600],   // 200 per 10 minutes
                ['max_requests' => 50, 'window_seconds' => 300]     // 50 per 5 minutes
            ]
        ];
    }

    /**
     * Redis-based rate limiting (faster, recommended for production)
     */
    private function checkLimitRedis(string $key, int $maxRequests, int $windowSeconds): bool
    {
        $now = time();
        $windowStart = $now - $windowSeconds;
        
        // Remove old entries
        $this->redis->zremrangebyscore($key, 0, $windowStart);
        
        // Count current requests
        $currentCount = $this->redis->zcard($key);
        
        if ($currentCount >= $maxRequests) {
            return false;
        }
        
        return true;
    }

    private function recordUsageRedis(string $key): void
    {
        $now = time();
        $this->redis->zadd($key, $now, $now . '_' . uniqid());
        $this->redis->expire($key, 3600); // Clean up after 1 hour
    }

    private function getRemainingRedis(string $key, int $maxRequests, int $windowSeconds): int
    {
        $now = time();
        $windowStart = $now - $windowSeconds;
        
        $this->redis->zremrangebyscore($key, 0, $windowStart);
        $currentCount = $this->redis->zcard($key);
        
        return max(0, $maxRequests - $currentCount);
    }

    private function getResetTimeRedis(string $key, int $windowSeconds): int
    {
        $oldestEntry = $this->redis->zrange($key, 0, 0, ['WITHSCORES' => true]);
        
        if (empty($oldestEntry)) {
            return 0;
        }
        
        $oldestTime = array_values($oldestEntry)[0];
        $resetTime = $oldestTime + $windowSeconds;
        
        return max(0, $resetTime - time());
    }

    /**
     * Database-based rate limiting (fallback)
     */
    private function checkLimitDatabase(string $key, int $maxRequests, int $windowSeconds): bool
    {
        $this->cleanupOldEntries();
        
        $windowStart = date('Y-m-d H:i:s', time() - $windowSeconds);
        
        $sql = "
            SELECT COUNT(*) FROM {$this->tableName}
            WHERE rate_key = ? AND created_at > ?
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$key, $windowStart]);
        $currentCount = $stmt->fetchColumn();
        
        return $currentCount < $maxRequests;
    }

    private function recordUsageDatabase(string $key): void
    {
        $sql = "
            INSERT INTO {$this->tableName} (rate_key, created_at)
            VALUES (?, NOW())
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$key]);
    }

    private function getRemainingDatabase(string $key, int $maxRequests, int $windowSeconds): int
    {
        $windowStart = date('Y-m-d H:i:s', time() - $windowSeconds);
        
        $sql = "
            SELECT COUNT(*) FROM {$this->tableName}
            WHERE rate_key = ? AND created_at > ?
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$key, $windowStart]);
        $currentCount = $stmt->fetchColumn();
        
        return max(0, $maxRequests - $currentCount);
    }

    private function getResetTimeDatabase(string $key, int $windowSeconds): int
    {
        $sql = "
            SELECT created_at FROM {$this->tableName}
            WHERE rate_key = ?
            ORDER BY created_at ASC
            LIMIT 1
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$key]);
        $oldestEntry = $stmt->fetchColumn();
        
        if (!$oldestEntry) {
            return 0;
        }
        
        $oldestTime = strtotime($oldestEntry);
        $resetTime = $oldestTime + $windowSeconds;
        
        return max(0, $resetTime - time());
    }

    /**
     * Initialize Redis connection if available
     */
    private function initializeRedis(): void
    {
        if (!extension_loaded('redis')) {
            return;
        }
        
        $redisHost = $_ENV['REDIS_HOST'] ?? null;
        if (!$redisHost) {
            return;
        }
        
        try {
            $this->redis = new \Redis();
            $this->redis->connect($redisHost, $_ENV['REDIS_PORT'] ?? 6379);
            
            if (!empty($_ENV['REDIS_PASSWORD'])) {
                $this->redis->auth($_ENV['REDIS_PASSWORD']);
            }
            
            // Test connection
            $this->redis->ping();
            $this->useRedis = true;
            
        } catch (Exception $e) {
            error_log("Redis connection failed: " . $e->getMessage());
            $this->useRedis = false;
        }
    }

    /**
     * Ensure database table exists
     */
    private function ensureTableExists(): void
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS {$this->tableName} (
                id INT AUTO_INCREMENT PRIMARY KEY,
                rate_key VARCHAR(255) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                
                INDEX idx_key_time (rate_key, created_at),
                INDEX idx_created (created_at)
            )
        ";
        
        $this->db->exec($sql);
    }

    /**
     * Clean up old database entries
     */
    private function cleanupOldEntries(): void
    {
        // Clean up entries older than 2 hours to prevent table bloat
        static $lastCleanup = 0;
        
        if (time() - $lastCleanup < 300) { // Only cleanup every 5 minutes
            return;
        }
        
        $sql = "
            DELETE FROM {$this->tableName}
            WHERE created_at < DATE_SUB(NOW(), INTERVAL 2 HOUR)
        ";
        
        $this->db->exec($sql);
        $lastCleanup = time();
    }

    /**
     * Get rate limiting statistics
     */
    public function getStats(int $hours = 24): array
    {
        try {
            $stats = [];
            
            if ($this->useRedis) {
                // Redis stats would require custom tracking
                $stats['method'] = 'redis';
                $stats['connection'] = 'active';
            } else {
                $stats['method'] = 'database';
                
                // Get request counts by key prefix
                $sql = "
                    SELECT 
                        SUBSTRING_INDEX(rate_key, '_', 2) as key_type,
                        COUNT(*) as request_count
                    FROM {$this->tableName}
                    WHERE created_at > DATE_SUB(NOW(), INTERVAL ? HOUR)
                    GROUP BY key_type
                    ORDER BY request_count DESC
                ";
                
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$hours]);
                $stats['requests_by_type'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Total requests
                $sql = "
                    SELECT COUNT(*) FROM {$this->tableName}
                    WHERE created_at > DATE_SUB(NOW(), INTERVAL ? HOUR)
                ";
                
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$hours]);
                $stats['total_requests'] = $stmt->fetchColumn();
            }
            
            return $stats;
            
        } catch (Exception $e) {
            error_log("Rate limiter stats error: " . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }
}