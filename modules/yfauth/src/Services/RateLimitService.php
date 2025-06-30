<?php

namespace YFEvents\Modules\YFAuth\Services;

use PDO;
use Exception;

/**
 * Rate Limiting Service
 * Implements token bucket algorithm for rate limiting
 */
class RateLimitService
{
    private PDO $db;
    private string $tablePrefix;

    public function __construct(PDO $db, string $tablePrefix = 'auth_')
    {
        $this->db = $db;
        $this->tablePrefix = $tablePrefix;
        $this->ensureRateLimitTable();
    }

    /**
     * Attempt an action with rate limiting
     * 
     * @param string $key Unique identifier for the rate limit (e.g., "login:192.168.1.1")
     * @param int $maxAttempts Maximum number of attempts allowed
     * @param int $windowSeconds Time window in seconds
     * @return bool True if attempt is allowed, false if rate limited
     */
    public function attempt(string $key, int $maxAttempts = 5, int $windowSeconds = 300): bool
    {
        try {
            $now = time();
            $windowStart = $now - $windowSeconds;

            // Clean up old entries first
            $this->cleanup();

            // Get current count for this key within the window
            $sql = "
                SELECT COUNT(*) as count
                FROM {$this->tablePrefix}rate_limits
                WHERE rate_key = ? AND created_at > ?
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$key, date('Y-m-d H:i:s', $windowStart)]);
            $currentCount = $stmt->fetchColumn();

            if ($currentCount >= $maxAttempts) {
                return false; // Rate limit exceeded
            }

            // Record this attempt
            $sql = "
                INSERT INTO {$this->tablePrefix}rate_limits (rate_key, created_at)
                VALUES (?, ?)
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$key, date('Y-m-d H:i:s', $now)]);

            return true;

        } catch (Exception $e) {
            error_log("Rate limit error: " . $e->getMessage());
            return true; // Fail open - allow request if rate limiting fails
        }
    }

    /**
     * Check if key has too many attempts without recording a new attempt
     */
    public function tooManyAttempts(string $key, int $maxAttempts = 5, int $windowSeconds = 300): bool
    {
        try {
            $windowStart = time() - $windowSeconds;

            $sql = "
                SELECT COUNT(*) as count
                FROM {$this->tablePrefix}rate_limits
                WHERE rate_key = ? AND created_at > ?
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$key, date('Y-m-d H:i:s', $windowStart)]);
            $currentCount = $stmt->fetchColumn();

            return $currentCount >= $maxAttempts;

        } catch (Exception $e) {
            error_log("Rate limit check error: " . $e->getMessage());
            return false; // Fail open
        }
    }

    /**
     * Get remaining attempts for a key
     */
    public function remainingAttempts(string $key, int $maxAttempts = 5, int $windowSeconds = 300): int
    {
        try {
            $windowStart = time() - $windowSeconds;

            $sql = "
                SELECT COUNT(*) as count
                FROM {$this->tablePrefix}rate_limits
                WHERE rate_key = ? AND created_at > ?
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$key, date('Y-m-d H:i:s', $windowStart)]);
            $currentCount = $stmt->fetchColumn();

            return max(0, $maxAttempts - $currentCount);

        } catch (Exception $e) {
            error_log("Rate limit remaining check error: " . $e->getMessage());
            return $maxAttempts; // Fail open
        }
    }

    /**
     * Get time until rate limit resets
     */
    public function timeUntilReset(string $key, int $windowSeconds = 300): int
    {
        try {
            $sql = "
                SELECT created_at
                FROM {$this->tablePrefix}rate_limits
                WHERE rate_key = ?
                ORDER BY created_at ASC
                LIMIT 1
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$key]);
            $oldestAttempt = $stmt->fetchColumn();

            if (!$oldestAttempt) {
                return 0; // No attempts recorded
            }

            $oldestTime = strtotime($oldestAttempt);
            $resetTime = $oldestTime + $windowSeconds;
            $now = time();

            return max(0, $resetTime - $now);

        } catch (Exception $e) {
            error_log("Rate limit reset time error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Clear all attempts for a key (useful after successful authentication)
     */
    public function clear(string $key): void
    {
        try {
            $sql = "DELETE FROM {$this->tablePrefix}rate_limits WHERE rate_key = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$key]);
        } catch (Exception $e) {
            error_log("Rate limit clear error: " . $e->getMessage());
        }
    }

    /**
     * Advanced rate limiting with different limits for different actions
     */
    public function attemptWithConfig(string $key, array $config): bool
    {
        $maxAttempts = $config['max_attempts'] ?? 5;
        $windowSeconds = $config['window_seconds'] ?? 300;
        $burstLimit = $config['burst_limit'] ?? null;
        $burstWindowSeconds = $config['burst_window_seconds'] ?? 60;

        // Check standard rate limit
        if (!$this->attempt($key, $maxAttempts, $windowSeconds)) {
            return false;
        }

        // Check burst limit if configured
        if ($burstLimit !== null) {
            if ($this->tooManyAttempts($key, $burstLimit, $burstWindowSeconds)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get rate limit configuration for different actions
     */
    public static function getDefaultConfigs(): array
    {
        return [
            'login' => [
                'max_attempts' => 5,
                'window_seconds' => 300, // 5 minutes
                'burst_limit' => 3,
                'burst_window_seconds' => 60 // 1 minute
            ],
            'register' => [
                'max_attempts' => 3,
                'window_seconds' => 3600, // 1 hour
                'burst_limit' => 1,
                'burst_window_seconds' => 60
            ],
            'password_reset' => [
                'max_attempts' => 3,
                'window_seconds' => 3600, // 1 hour
                'burst_limit' => 1,
                'burst_window_seconds' => 300 // 5 minutes
            ],
            'api_request' => [
                'max_attempts' => 100,
                'window_seconds' => 3600, // 1 hour
                'burst_limit' => 20,
                'burst_window_seconds' => 60
            ],
            'email_verification' => [
                'max_attempts' => 5,
                'window_seconds' => 3600, // 1 hour
                'burst_limit' => 2,
                'burst_window_seconds' => 300
            ]
        ];
    }

    /**
     * Batch check multiple keys (useful for checking both IP and user limits)
     */
    public function batchAttempt(array $keys, array $config): bool
    {
        foreach ($keys as $key) {
            if (!$this->attemptWithConfig($key, $config)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Get rate limit statistics
     */
    public function getStatistics(int $hours = 24): array
    {
        try {
            $stats = [];

            // Most rate limited keys
            $sql = "
                SELECT 
                    rate_key,
                    COUNT(*) as attempt_count,
                    MIN(created_at) as first_attempt,
                    MAX(created_at) as last_attempt
                FROM {$this->tablePrefix}rate_limits
                WHERE created_at > DATE_SUB(NOW(), INTERVAL ? HOUR)
                GROUP BY rate_key
                ORDER BY attempt_count DESC
                LIMIT 20
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$hours]);
            $stats['top_limited_keys'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Rate limit events by hour
            $sql = "
                SELECT 
                    DATE_FORMAT(created_at, '%Y-%m-%d %H:00:00') as hour,
                    COUNT(*) as attempts
                FROM {$this->tablePrefix}rate_limits
                WHERE created_at > DATE_SUB(NOW(), INTERVAL ? HOUR)
                GROUP BY hour
                ORDER BY hour
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$hours]);
            $stats['attempts_by_hour'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Total attempts in time period
            $sql = "
                SELECT COUNT(*) as total_attempts
                FROM {$this->tablePrefix}rate_limits
                WHERE created_at > DATE_SUB(NOW(), INTERVAL ? HOUR)
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$hours]);
            $stats['total_attempts'] = $stmt->fetchColumn();

            return $stats;

        } catch (Exception $e) {
            error_log("Rate limit statistics error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Cleanup old rate limit entries
     */
    private function cleanup(): void
    {
        try {
            // Clean up entries older than 24 hours
            $sql = "
                DELETE FROM {$this->tablePrefix}rate_limits 
                WHERE created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ";
            $this->db->exec($sql);
        } catch (Exception $e) {
            error_log("Rate limit cleanup error: " . $e->getMessage());
        }
    }

    /**
     * Ensure rate limit table exists
     */
    private function ensureRateLimitTable(): void
    {
        try {
            $sql = "
                CREATE TABLE IF NOT EXISTS {$this->tablePrefix}rate_limits (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    rate_key VARCHAR(255) NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_key_time (rate_key, created_at),
                    INDEX idx_created (created_at)
                )
            ";
            $this->db->exec($sql);
        } catch (Exception $e) {
            error_log("Failed to create rate limit table: " . $e->getMessage());
        }
    }
}