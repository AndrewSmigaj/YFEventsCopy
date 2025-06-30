<?php

namespace YakimaFinds\Scrapers\Queue;

use PDO;
use Exception;

/**
 * Queue Manager for Optimized Scraping System
 * Handles job queuing, prioritization, and worker management
 */
class QueueManager
{
    private PDO $db;
    private string $queueTable = 'scraper_queue';
    private string $workerTable = 'scraper_workers';
    
    // Queue priorities
    const PRIORITY_LOW = 1;
    const PRIORITY_NORMAL = 5;
    const PRIORITY_HIGH = 10;
    const PRIORITY_URGENT = 15;
    
    // Job statuses
    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';
    const STATUS_RETRYING = 'retrying';
    
    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->ensureTablesExist();
    }

    /**
     * Add scraping job to queue
     */
    public function enqueue(array $jobData): int
    {
        $sql = "
            INSERT INTO {$this->queueTable} (
                source_id, job_type, payload, priority, scheduled_at, created_at
            ) VALUES (?, ?, ?, ?, ?, NOW())
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $jobData['source_id'],
            $jobData['job_type'] ?? 'scrape_source',
            json_encode($jobData['payload'] ?? []),
            $jobData['priority'] ?? self::PRIORITY_NORMAL,
            $jobData['scheduled_at'] ?? date('Y-m-d H:i:s')
        ]);
        
        return $this->db->lastInsertId();
    }

    /**
     * Schedule all active sources for scraping
     */
    public function scheduleAllSources(int $priority = self::PRIORITY_NORMAL): int
    {
        // Get all active sources
        $sql = "SELECT id, name, scrape_type, last_scraped_at FROM calendar_sources WHERE is_active = 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $sources = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $scheduled = 0;
        
        foreach ($sources as $source) {
            // Calculate dynamic priority based on last scrape time
            $dynamicPriority = $this->calculateDynamicPriority($source, $priority);
            
            $jobId = $this->enqueue([
                'source_id' => $source['id'],
                'job_type' => 'scrape_source',
                'priority' => $dynamicPriority,
                'payload' => [
                    'source_name' => $source['name'],
                    'scrape_type' => $source['scrape_type']
                ]
            ]);
            
            if ($jobId) {
                $scheduled++;
            }
        }
        
        return $scheduled;
    }

    /**
     * Get next job from queue for processing
     */
    public function dequeue(string $workerId): ?array
    {
        $this->db->beginTransaction();
        
        try {
            // Find highest priority pending job
            $sql = "
                SELECT * FROM {$this->queueTable}
                WHERE status = ? AND scheduled_at <= NOW()
                ORDER BY priority DESC, created_at ASC
                LIMIT 1 FOR UPDATE
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([self::STATUS_PENDING]);
            $job = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$job) {
                $this->db->rollback();
                return null;
            }
            
            // Mark job as processing
            $sql = "
                UPDATE {$this->queueTable}
                SET status = ?, worker_id = ?, started_at = NOW()
                WHERE id = ?
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([self::STATUS_PROCESSING, $workerId, $job['id']]);
            
            $this->db->commit();
            
            // Decode payload
            $job['payload'] = json_decode($job['payload'], true);
            
            return $job;
            
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Queue dequeue error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Mark job as completed
     */
    public function completeJob(int $jobId, array $result = []): bool
    {
        $sql = "
            UPDATE {$this->queueTable}
            SET status = ?, completed_at = NOW(), result = ?, progress = 100
            WHERE id = ?
        ";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            self::STATUS_COMPLETED,
            json_encode($result),
            $jobId
        ]);
    }

    /**
     * Mark job as failed
     */
    public function failJob(int $jobId, string $error, bool $retry = true): bool
    {
        $sql = "SELECT retry_count FROM {$this->queueTable} WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$jobId]);
        $retryCount = $stmt->fetchColumn() ?: 0;
        
        $maxRetries = 3;
        $newStatus = self::STATUS_FAILED;
        $scheduledAt = null;
        
        if ($retry && $retryCount < $maxRetries) {
            $newStatus = self::STATUS_RETRYING;
            // Exponential backoff: 2^retry_count minutes
            $delay = pow(2, $retryCount) * 60;
            $scheduledAt = date('Y-m-d H:i:s', time() + $delay);
        }
        
        $sql = "
            UPDATE {$this->queueTable}
            SET status = ?, error_message = ?, retry_count = retry_count + 1,
                failed_at = NOW(), scheduled_at = COALESCE(?, scheduled_at)
            WHERE id = ?
        ";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$newStatus, $error, $scheduledAt, $jobId]);
    }

    /**
     * Update job progress
     */
    public function updateProgress(int $jobId, int $progress, array $metadata = []): bool
    {
        $sql = "
            UPDATE {$this->queueTable}
            SET progress = ?, progress_metadata = ?, updated_at = NOW()
            WHERE id = ?
        ";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$progress, json_encode($metadata), $jobId]);
    }

    /**
     * Register worker
     */
    public function registerWorker(string $workerId, array $capabilities = []): bool
    {
        $sql = "
            INSERT INTO {$this->workerTable} (
                worker_id, capabilities, status, registered_at, last_heartbeat
            ) VALUES (?, ?, 'active', NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                capabilities = VALUES(capabilities),
                status = 'active',
                last_heartbeat = NOW()
        ";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$workerId, json_encode($capabilities)]);
    }

    /**
     * Update worker heartbeat
     */
    public function heartbeat(string $workerId): bool
    {
        $sql = "
            UPDATE {$this->workerTable}
            SET last_heartbeat = NOW(), status = 'active'
            WHERE worker_id = ?
        ";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$workerId]);
    }

    /**
     * Unregister worker
     */
    public function unregisterWorker(string $workerId): bool
    {
        $sql = "
            UPDATE {$this->workerTable}
            SET status = 'inactive', unregistered_at = NOW()
            WHERE worker_id = ?
        ";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$workerId]);
    }

    /**
     * Clean up stale jobs and workers
     */
    public function cleanup(): array
    {
        $results = [
            'stale_jobs_reset' => 0,
            'dead_workers_removed' => 0,
            'old_completed_jobs_deleted' => 0
        ];

        // Reset jobs from dead workers (no heartbeat for 5 minutes)
        $sql = "
            UPDATE {$this->queueTable} q
            JOIN {$this->workerTable} w ON q.worker_id = w.worker_id
            SET q.status = ?, q.worker_id = NULL, q.started_at = NULL
            WHERE q.status = ? AND w.last_heartbeat < DATE_SUB(NOW(), INTERVAL 5 MINUTE)
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([self::STATUS_PENDING, self::STATUS_PROCESSING]);
        $results['stale_jobs_reset'] = $stmt->rowCount();

        // Mark workers as dead if no heartbeat for 10 minutes
        $sql = "
            UPDATE {$this->workerTable}
            SET status = 'dead'
            WHERE status = 'active' AND last_heartbeat < DATE_SUB(NOW(), INTERVAL 10 MINUTE)
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $results['dead_workers_removed'] = $stmt->rowCount();

        // Delete completed jobs older than 7 days
        $sql = "
            DELETE FROM {$this->queueTable}
            WHERE status IN (?, ?) AND completed_at < DATE_SUB(NOW(), INTERVAL 7 DAY)
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([self::STATUS_COMPLETED, self::STATUS_FAILED]);
        $results['old_completed_jobs_deleted'] = $stmt->rowCount();

        return $results;
    }

    /**
     * Get queue statistics
     */
    public function getStats(): array
    {
        $stats = [];

        // Job counts by status
        $sql = "
            SELECT status, COUNT(*) as count
            FROM {$this->queueTable}
            GROUP BY status
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $stats['jobs_by_status'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        // Active workers
        $sql = "
            SELECT COUNT(*) FROM {$this->workerTable}
            WHERE status = 'active' AND last_heartbeat > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $stats['active_workers'] = $stmt->fetchColumn();

        // Average processing time
        $sql = "
            SELECT AVG(TIMESTAMPDIFF(SECOND, started_at, completed_at)) as avg_processing_time
            FROM {$this->queueTable}
            WHERE status = ? AND completed_at IS NOT NULL
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([self::STATUS_COMPLETED]);
        $stats['avg_processing_time_seconds'] = $stmt->fetchColumn() ?: 0;

        // Jobs in last 24 hours
        $sql = "
            SELECT status, COUNT(*) as count
            FROM {$this->queueTable}
            WHERE created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
            GROUP BY status
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $stats['jobs_last_24h'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        return $stats;
    }

    /**
     * Calculate dynamic priority based on source characteristics
     */
    private function calculateDynamicPriority(array $source, int $basePriority): int
    {
        $priority = $basePriority;
        
        // Increase priority if not scraped recently
        if (!empty($source['last_scraped_at'])) {
            $lastScraped = strtotime($source['last_scraped_at']);
            $hoursSinceLastScrape = (time() - $lastScraped) / 3600;
            
            if ($hoursSinceLastScrape > 48) {
                $priority += 3; // High priority for stale sources
            } elseif ($hoursSinceLastScrape > 24) {
                $priority += 1; // Medium priority
            }
        } else {
            $priority += 5; // Never scraped - very high priority
        }
        
        // Adjust based on scraper type (some are more reliable)
        switch ($source['scrape_type']) {
            case 'ical':
                $priority += 2; // iCal is usually reliable and fast
                break;
            case 'json':
                $priority += 1; // JSON APIs are usually fast
                break;
            case 'intelligent':
            case 'firecrawl_enhanced':
                $priority -= 1; // AI scrapers take longer, lower priority
                break;
        }
        
        return max(1, min(20, $priority)); // Clamp between 1-20
    }

    /**
     * Ensure required tables exist
     */
    private function ensureTablesExist(): void
    {
        // Create queue table
        $sql = "
            CREATE TABLE IF NOT EXISTS {$this->queueTable} (
                id INT AUTO_INCREMENT PRIMARY KEY,
                source_id INT NOT NULL,
                job_type VARCHAR(50) NOT NULL DEFAULT 'scrape_source',
                payload JSON,
                priority INT DEFAULT 5,
                status ENUM('pending', 'processing', 'completed', 'failed', 'retrying') DEFAULT 'pending',
                worker_id VARCHAR(100),
                progress INT DEFAULT 0,
                progress_metadata JSON,
                retry_count INT DEFAULT 0,
                error_message TEXT,
                result JSON,
                scheduled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                started_at TIMESTAMP NULL,
                completed_at TIMESTAMP NULL,
                failed_at TIMESTAMP NULL,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                
                INDEX idx_status_priority (status, priority),
                INDEX idx_scheduled (scheduled_at),
                INDEX idx_source (source_id),
                INDEX idx_worker (worker_id),
                INDEX idx_created (created_at)
            )
        ";
        $this->db->exec($sql);

        // Create workers table
        $sql = "
            CREATE TABLE IF NOT EXISTS {$this->workerTable} (
                worker_id VARCHAR(100) PRIMARY KEY,
                capabilities JSON,
                status ENUM('active', 'inactive', 'dead') DEFAULT 'active',
                registered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                unregistered_at TIMESTAMP NULL,
                last_heartbeat TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                
                INDEX idx_status (status),
                INDEX idx_heartbeat (last_heartbeat)
            )
        ";
        $this->db->exec($sql);
    }
}