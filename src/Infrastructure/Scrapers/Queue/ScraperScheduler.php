<?php

namespace YFEvents\Scrapers\Queue;

use YFEvents\Models\CalendarSourceModel;
use YFEvents\Utils\SystemLogger;
use PDO;
use Exception;

/**
 * Intelligent Scraper Scheduler
 * Implements smart scheduling based on source performance, reliability, and priority
 */
class ScraperScheduler
{
    private PDO $db;
    private QueueManager $queueManager;
    private CalendarSourceModel $sourceModel;
    private SystemLogger $logger;
    private RateLimiter $rateLimiter;

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->queueManager = new QueueManager($db);
        $this->sourceModel = new CalendarSourceModel($db);
        $this->logger = new SystemLogger($db, 'scraper_scheduler');
        $this->rateLimiter = new RateLimiter($db);
    }

    /**
     * Schedule sources based on intelligent analysis
     */
    public function scheduleIntelligent(): array
    {
        $this->logger->info("Starting intelligent scheduling");
        
        $sources = $this->sourceModel->getActiveSources();
        $scheduled = [];
        $skipped = [];
        
        foreach ($sources as $source) {
            try {
                $analysis = $this->analyzeSource($source);
                
                if ($analysis['should_schedule']) {
                    $jobId = $this->queueManager->enqueue([
                        'source_id' => $source['id'],
                        'job_type' => 'scrape_source',
                        'priority' => $analysis['priority'],
                        'scheduled_at' => $analysis['scheduled_at'],
                        'payload' => [
                            'source_name' => $source['name'],
                            'scrape_type' => $source['scrape_type'],
                            'analysis' => $analysis
                        ]
                    ]);
                    
                    $scheduled[] = [
                        'source_id' => $source['id'],
                        'job_id' => $jobId,
                        'priority' => $analysis['priority'],
                        'reason' => $analysis['reason'],
                        'scheduled_at' => $analysis['scheduled_at']
                    ];
                } else {
                    $skipped[] = [
                        'source_id' => $source['id'],
                        'reason' => $analysis['reason']
                    ];
                }
                
            } catch (Exception $e) {
                $this->logger->error("Failed to analyze source {$source['id']}", [
                    'source_name' => $source['name'],
                    'error' => $e->getMessage()
                ]);
                
                $skipped[] = [
                    'source_id' => $source['id'],
                    'reason' => 'Analysis failed: ' . $e->getMessage()
                ];
            }
        }
        
        $this->logger->info("Intelligent scheduling complete", [
            'scheduled' => count($scheduled),
            'skipped' => count($skipped)
        ]);
        
        return [
            'scheduled' => $scheduled,
            'skipped' => $skipped,
            'total_sources' => count($sources)
        ];
    }

    /**
     * Analyze source to determine scheduling priority and timing
     */
    private function analyzeSource(array $source): array
    {
        $sourceId = $source['id'];
        $analysis = [
            'should_schedule' => true,
            'priority' => QueueManager::PRIORITY_NORMAL,
            'scheduled_at' => date('Y-m-d H:i:s'),
            'reason' => 'Normal scheduling',
            'factors' => []
        ];
        
        // Factor 1: Time since last scrape
        $timeFactor = $this->analyzeTimeFactor($source);
        $analysis['factors']['time'] = $timeFactor;
        
        // Factor 2: Historical reliability
        $reliabilityFactor = $this->analyzeReliabilityFactor($source);
        $analysis['factors']['reliability'] = $reliabilityFactor;
        
        // Factor 3: Rate limiting
        $rateLimitFactor = $this->analyzeRateLimitFactor($source);
        $analysis['factors']['rate_limit'] = $rateLimitFactor;
        
        // Factor 4: Performance metrics
        $performanceFactor = $this->analyzePerformanceFactor($source);
        $analysis['factors']['performance'] = $performanceFactor;
        
        // Factor 5: Content freshness expectations
        $freshnessFactor = $this->analyzeFreshnessFactor($source);
        $analysis['factors']['freshness'] = $freshnessFactor;
        
        // Factor 6: Error patterns
        $errorFactor = $this->analyzeErrorFactor($source);
        $analysis['factors']['error'] = $errorFactor;
        
        // Combine factors to make scheduling decision
        return $this->combineFactors($analysis);
    }

    /**
     * Analyze time since last scrape
     */
    private function analyzeTimeFactor(array $source): array
    {
        $lastScraped = $source['last_scraped_at'];
        $factor = [
            'weight' => 0,
            'reason' => 'Never scraped',
            'urgency' => 'high'
        ];
        
        if ($lastScraped) {
            $hoursSince = (time() - strtotime($lastScraped)) / 3600;
            
            if ($hoursSince > 72) { // > 3 days
                $factor = ['weight' => 5, 'reason' => 'Very stale', 'urgency' => 'urgent'];
            } elseif ($hoursSince > 48) { // > 2 days
                $factor = ['weight' => 4, 'reason' => 'Stale', 'urgency' => 'high'];
            } elseif ($hoursSince > 24) { // > 1 day
                $factor = ['weight' => 3, 'reason' => 'Due for update', 'urgency' => 'medium'];
            } elseif ($hoursSince > 12) { // > 12 hours
                $factor = ['weight' => 2, 'reason' => 'Can be updated', 'urgency' => 'normal'];
            } elseif ($hoursSince > 6) { // > 6 hours
                $factor = ['weight' => 1, 'reason' => 'Recently scraped', 'urgency' => 'low'];
            } else {
                $factor = ['weight' => -2, 'reason' => 'Too recent', 'urgency' => 'skip'];
            }
        } else {
            $factor['weight'] = 5; // Never scraped = highest priority
        }
        
        return $factor;
    }

    /**
     * Analyze historical reliability
     */
    private function analyzeReliabilityFactor(array $source): array
    {
        $sourceId = $source['id'];
        
        // Get recent scraping attempts
        $sql = "
            SELECT 
                COUNT(*) as total_attempts,
                SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as successful_attempts,
                AVG(events_found) as avg_events,
                MAX(created_at) as last_attempt
            FROM scraping_logs 
            WHERE source_id = ? 
            AND created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$sourceId]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $factor = ['weight' => 0, 'reason' => 'No history', 'reliability' => 'unknown'];
        
        if ($stats['total_attempts'] > 0) {
            $successRate = $stats['successful_attempts'] / $stats['total_attempts'];
            $avgEvents = floatval($stats['avg_events']);
            
            if ($successRate >= 0.9 && $avgEvents > 5) {
                $factor = ['weight' => 2, 'reason' => 'Highly reliable', 'reliability' => 'excellent'];
            } elseif ($successRate >= 0.7 && $avgEvents > 2) {
                $factor = ['weight' => 1, 'reason' => 'Reliable', 'reliability' => 'good'];
            } elseif ($successRate >= 0.5) {
                $factor = ['weight' => 0, 'reason' => 'Moderately reliable', 'reliability' => 'fair'];
            } else {
                $factor = ['weight' => -1, 'reason' => 'Unreliable', 'reliability' => 'poor'];
            }
        }
        
        return $factor;
    }

    /**
     * Analyze rate limiting constraints
     */
    private function analyzeRateLimitFactor(array $source): array
    {
        $sourceId = $source['id'];
        $domain = parse_url($source['url'], PHP_URL_HOST);
        
        $sourceKey = "scraper_source_{$sourceId}";
        $domainKey = "scraper_domain_{$domain}";
        
        $sourceRemaining = $this->rateLimiter->getRemainingRequests($sourceKey, 5, 3600);
        $domainRemaining = $this->rateLimiter->getRemainingRequests($domainKey, 10, 3600);
        
        $factor = ['weight' => 0, 'reason' => 'Rate limit OK', 'delay' => 0];
        
        if ($sourceRemaining <= 0) {
            $resetTime = $this->rateLimiter->getResetTime($sourceKey, 3600);
            $factor = [
                'weight' => -10,
                'reason' => 'Source rate limited',
                'delay' => $resetTime,
                'skip' => true
            ];
        } elseif ($domainRemaining <= 0) {
            $resetTime = $this->rateLimiter->getResetTime($domainKey, 3600);
            $factor = [
                'weight' => -10,
                'reason' => 'Domain rate limited',
                'delay' => $resetTime,
                'skip' => true
            ];
        } elseif ($sourceRemaining <= 1) {
            $factor = ['weight' => -2, 'reason' => 'Low source quota', 'delay' => 0];
        } elseif ($domainRemaining <= 2) {
            $factor = ['weight' => -1, 'reason' => 'Low domain quota', 'delay' => 0];
        }
        
        return $factor;
    }

    /**
     * Analyze performance metrics
     */
    private function analyzePerformanceFactor(array $source): array
    {
        $sourceId = $source['id'];
        
        // Get recent performance data
        $sql = "
            SELECT 
                AVG(duration_ms) as avg_duration,
                AVG(events_found) as avg_events,
                COUNT(*) as attempt_count
            FROM scraping_logs 
            WHERE source_id = ? 
            AND status = 'success'
            AND created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$sourceId]);
        $perf = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $factor = ['weight' => 0, 'reason' => 'No performance data', 'performance' => 'unknown'];
        
        if ($perf['attempt_count'] > 0) {
            $duration = floatval($perf['avg_duration']);
            $events = floatval($perf['avg_events']);
            
            // Performance scoring based on speed and event yield
            if ($duration < 5000 && $events > 10) { // Fast and productive
                $factor = ['weight' => 2, 'reason' => 'High performance', 'performance' => 'excellent'];
            } elseif ($duration < 10000 && $events > 5) { // Good performance
                $factor = ['weight' => 1, 'reason' => 'Good performance', 'performance' => 'good'];
            } elseif ($duration < 30000 || $events > 2) { // Acceptable
                $factor = ['weight' => 0, 'reason' => 'Acceptable performance', 'performance' => 'fair'];
            } else { // Slow or low yield
                $factor = ['weight' => -1, 'reason' => 'Poor performance', 'performance' => 'poor'];
            }
        }
        
        return $factor;
    }

    /**
     * Analyze content freshness expectations
     */
    private function analyzeFreshnessFactor(array $source): array
    {
        $scrapeType = $source['scrape_type'];
        $name = strtolower($source['name']);
        
        $factor = ['weight' => 0, 'reason' => 'Standard freshness', 'update_frequency' => 'daily'];
        
        // Different source types have different freshness needs
        if ($scrapeType === 'ical') {
            $factor = ['weight' => 1, 'reason' => 'Calendar feeds need frequent updates', 'update_frequency' => 'frequent'];
        } elseif (strpos($name, 'news') !== false || strpos($name, 'blog') !== false) {
            $factor = ['weight' => 2, 'reason' => 'News/blog content changes frequently', 'update_frequency' => 'very_frequent'];
        } elseif (strpos($name, 'event') !== false) {
            $factor = ['weight' => 1, 'reason' => 'Event listings change regularly', 'update_frequency' => 'frequent'];
        } elseif (strpos($name, 'static') !== false || strpos($name, 'archive') !== false) {
            $factor = ['weight' => -1, 'reason' => 'Static content changes rarely', 'update_frequency' => 'infrequent'];
        }
        
        return $factor;
    }

    /**
     * Analyze error patterns
     */
    private function analyzeErrorFactor(array $source): array
    {
        $sourceId = $source['id'];
        
        // Get recent error patterns
        $sql = "
            SELECT 
                COUNT(*) as error_count,
                MAX(created_at) as last_error,
                error_message
            FROM scraping_logs 
            WHERE source_id = ? 
            AND status = 'failed'
            AND created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY error_message
            ORDER BY COUNT(*) DESC
            LIMIT 1
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$sourceId]);
        $error = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $factor = ['weight' => 0, 'reason' => 'No recent errors', 'error_pattern' => 'none'];
        
        if ($error && $error['error_count'] > 0) {
            $hoursSinceError = (time() - strtotime($error['last_error'])) / 3600;
            $errorMessage = strtolower($error['error_message']);
            
            // Classify error types
            if (strpos($errorMessage, 'timeout') !== false || strpos($errorMessage, 'connection') !== false) {
                if ($hoursSinceError < 6) {
                    $factor = ['weight' => -3, 'reason' => 'Recent connection issues', 'error_pattern' => 'connection'];
                } else {
                    $factor = ['weight' => -1, 'reason' => 'Past connection issues', 'error_pattern' => 'connection_resolved'];
                }
            } elseif (strpos($errorMessage, 'rate limit') !== false) {
                $factor = ['weight' => -5, 'reason' => 'Rate limiting issues', 'error_pattern' => 'rate_limit'];
            } elseif (strpos($errorMessage, '404') !== false || strpos($errorMessage, 'not found') !== false) {
                $factor = ['weight' => -2, 'reason' => 'Content not found', 'error_pattern' => 'not_found'];
            } elseif ($error['error_count'] > 5) {
                $factor = ['weight' => -2, 'reason' => 'Repeated failures', 'error_pattern' => 'repeated'];
            }
        }
        
        return $factor;
    }

    /**
     * Combine all factors to make final scheduling decision
     */
    private function combineFactors(array $analysis): array
    {
        $factors = $analysis['factors'];
        $totalWeight = 0;
        $reasons = [];
        $shouldSkip = false;
        $delay = 0;
        
        foreach ($factors as $factorName => $factor) {
            $totalWeight += $factor['weight'];
            $reasons[] = $factor['reason'];
            
            if (isset($factor['skip']) && $factor['skip']) {
                $shouldSkip = true;
            }
            
            if (isset($factor['delay']) && $factor['delay'] > $delay) {
                $delay = $factor['delay'];
            }
        }
        
        // Make scheduling decision
        if ($shouldSkip) {
            $analysis['should_schedule'] = false;
            $analysis['reason'] = 'Skipped: ' . implode(', ', $reasons);
        } else {
            $analysis['should_schedule'] = true;
            
            // Calculate priority based on total weight
            if ($totalWeight >= 8) {
                $analysis['priority'] = QueueManager::PRIORITY_URGENT;
            } elseif ($totalWeight >= 5) {
                $analysis['priority'] = QueueManager::PRIORITY_HIGH;
            } elseif ($totalWeight >= 2) {
                $analysis['priority'] = QueueManager::PRIORITY_NORMAL;
            } else {
                $analysis['priority'] = QueueManager::PRIORITY_LOW;
            }
            
            // Calculate scheduled time
            if ($delay > 0) {
                $analysis['scheduled_at'] = date('Y-m-d H:i:s', time() + $delay);
            } else {
                // Spread out scheduling to avoid overwhelming the system
                $spreadDelay = rand(0, 300); // 0-5 minutes random delay
                $analysis['scheduled_at'] = date('Y-m-d H:i:s', time() + $spreadDelay);
            }
            
            $analysis['reason'] = 'Scheduled with priority ' . $analysis['priority'] . ': ' . implode(', ', $reasons);
        }
        
        $analysis['total_weight'] = $totalWeight;
        
        return $analysis;
    }

    /**
     * Schedule maintenance tasks
     */
    public function scheduleMaintenance(): array
    {
        $scheduled = [];
        
        // Schedule optimization for underperforming sources
        $underperformingSources = $this->findUnderperformingSources();
        foreach ($underperformingSources as $source) {
            $jobId = $this->queueManager->enqueue([
                'source_id' => $source['id'],
                'job_type' => 'optimize_source',
                'priority' => QueueManager::PRIORITY_LOW,
                'payload' => [
                    'source_name' => $source['name'],
                    'reason' => 'Performance optimization'
                ]
            ]);
            
            $scheduled[] = [
                'source_id' => $source['id'],
                'job_id' => $jobId,
                'job_type' => 'optimize_source',
                'reason' => 'Underperforming source'
            ];
        }
        
        // Schedule health checks for failed sources
        $failedSources = $this->findFailedSources();
        foreach ($failedSources as $source) {
            $jobId = $this->queueManager->enqueue([
                'source_id' => $source['id'],
                'job_type' => 'test_source',
                'priority' => QueueManager::PRIORITY_LOW,
                'scheduled_at' => date('Y-m-d H:i:s', time() + 3600), // Delay 1 hour
                'payload' => [
                    'source_name' => $source['name'],
                    'reason' => 'Health check'
                ]
            ]);
            
            $scheduled[] = [
                'source_id' => $source['id'],
                'job_id' => $jobId,
                'job_type' => 'test_source',
                'reason' => 'Failed source health check'
            ];
        }
        
        return $scheduled;
    }

    /**
     * Find sources that are underperforming
     */
    private function findUnderperformingSources(): array
    {
        $sql = "
            SELECT cs.*, 
                   AVG(sl.events_found) as avg_events,
                   AVG(sl.duration_ms) as avg_duration,
                   COUNT(sl.id) as attempt_count
            FROM calendar_sources cs
            LEFT JOIN scraping_logs sl ON cs.id = sl.source_id 
                AND sl.created_at > DATE_SUB(NOW(), INTERVAL 14 DAY)
                AND sl.status = 'success'
            WHERE cs.is_active = 1
            GROUP BY cs.id
            HAVING attempt_count > 3 
            AND (avg_events < 2 OR avg_duration > 30000)
            ORDER BY avg_events ASC, avg_duration DESC
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Find sources that have been failing
     */
    private function findFailedSources(): array
    {
        $sql = "
            SELECT cs.*
            FROM calendar_sources cs
            WHERE cs.is_active = 1
            AND EXISTS (
                SELECT 1 FROM scraping_logs sl 
                WHERE sl.source_id = cs.id 
                AND sl.status = 'failed'
                AND sl.created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
                GROUP BY sl.source_id
                HAVING COUNT(*) >= 3
            )
            AND NOT EXISTS (
                SELECT 1 FROM scraping_logs sl2
                WHERE sl2.source_id = cs.id
                AND sl2.status = 'success'
                AND sl2.created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
            )
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get scheduler statistics
     */
    public function getStats(): array
    {
        $queueStats = $this->queueManager->getStats();
        $rateLimitStats = $this->rateLimiter->getStats();
        
        return [
            'queue' => $queueStats,
            'rate_limits' => $rateLimitStats,
            'scheduler' => [
                'active_sources' => $this->sourceModel->count(['is_active' => 1]),
                'underperforming_sources' => count($this->findUnderperformingSources()),
                'failed_sources' => count($this->findFailedSources())
            ]
        ];
    }
}