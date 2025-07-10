<?php

namespace YakimaFinds\Scrapers\Queue;

use YakimaFinds\Scrapers\EventScraper;
use YakimaFinds\Models\CalendarSourceModel;
use YakimaFinds\Utils\SystemLogger;
use PDO;
use Exception;

/**
 * Scraper Worker for Concurrent Processing
 * Processes scraping jobs from the queue with rate limiting and error handling
 */
class ScraperWorker
{
    private PDO $db;
    private QueueManager $queueManager;
    private RateLimiter $rateLimiter;
    private EventScraper $eventScraper;
    private SystemLogger $logger;
    private string $workerId;
    private bool $running = false;
    private int $maxJobsPerWorker = 50;
    private int $heartbeatInterval = 30; // seconds
    private int $processedJobs = 0;

    public function __construct(PDO $db, array $config = [])
    {
        $this->db = $db;
        $this->queueManager = new QueueManager($db);
        $this->rateLimiter = new RateLimiter($db);
        $this->eventScraper = new EventScraper($db);
        $this->logger = new SystemLogger($db, 'scraper_worker');
        
        $this->workerId = $config['worker_id'] ?? $this->generateWorkerId();
        $this->maxJobsPerWorker = $config['max_jobs'] ?? 50;
        $this->heartbeatInterval = $config['heartbeat_interval'] ?? 30;
        
        // Register signal handlers for graceful shutdown
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGTERM, [$this, 'handleShutdown']);
            pcntl_signal(SIGINT, [$this, 'handleShutdown']);
        }
    }

    /**
     * Start the worker
     */
    public function start(): void
    {
        $this->running = true;
        $this->processedJobs = 0;
        
        $this->logger->info("Starting scraper worker", [
            'worker_id' => $this->workerId,
            'max_jobs' => $this->maxJobsPerWorker,
            'pid' => getmypid()
        ]);
        
        // Register with queue manager
        $capabilities = [
            'scraper_types' => ['ical', 'html', 'json', 'yakima_valley', 'intelligent', 'firecrawl_enhanced'],
            'max_concurrent' => 1,
            'rate_limit_aware' => true
        ];
        
        $this->queueManager->registerWorker($this->workerId, $capabilities);
        
        $lastHeartbeat = time();
        
        while ($this->running && $this->processedJobs < $this->maxJobsPerWorker) {
            try {
                // Send heartbeat
                if (time() - $lastHeartbeat >= $this->heartbeatInterval) {
                    $this->queueManager->heartbeat($this->workerId);
                    $lastHeartbeat = time();
                }
                
                // Get next job
                $job = $this->queueManager->dequeue($this->workerId);
                
                if (!$job) {
                    // No jobs available, wait a bit
                    sleep(5);
                    continue;
                }
                
                // Process the job
                $this->processJob($job);
                $this->processedJobs++;
                
                // Brief pause between jobs
                usleep(100000); // 0.1 seconds
                
            } catch (Exception $e) {
                $this->logger->error("Worker error", [
                    'worker_id' => $this->workerId,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                
                // Sleep on error to prevent tight error loops
                sleep(10);
            }
            
            // Check for signals
            if (function_exists('pcntl_signal_dispatch')) {
                pcntl_signal_dispatch();
            }
        }
        
        $this->shutdown();
    }

    /**
     * Process a single job
     */
    private function processJob(array $job): void
    {
        $jobId = $job['id'];
        $sourceId = $job['source_id'];
        
        $this->logger->info("Processing job", [
            'job_id' => $jobId,
            'source_id' => $sourceId,
            'job_type' => $job['job_type'],
            'worker_id' => $this->workerId
        ]);
        
        try {
            // Check rate limits
            $rateLimitKey = "scraper_source_{$sourceId}";
            if (!$this->rateLimiter->checkLimit($rateLimitKey, 5, 3600)) { // 5 requests per hour per source
                throw new Exception("Rate limit exceeded for source {$sourceId}");
            }
            
            // Update progress
            $this->queueManager->updateProgress($jobId, 10, ['status' => 'starting']);
            
            // Get source details
            $sourceModel = new CalendarSourceModel($this->db);
            $source = $sourceModel->getSourceById($sourceId);
            
            if (!$source) {
                throw new Exception("Source {$sourceId} not found");
            }
            
            $this->queueManager->updateProgress($jobId, 30, ['status' => 'fetching_source']);
            
            // Apply domain-level rate limiting
            $domain = parse_url($source['url'], PHP_URL_HOST);
            $domainRateLimitKey = "scraper_domain_{$domain}";
            
            if (!$this->rateLimiter->checkLimit($domainRateLimitKey, 10, 600)) { // 10 requests per 10 minutes per domain
                throw new Exception("Domain rate limit exceeded for {$domain}");
            }
            
            $this->queueManager->updateProgress($jobId, 50, ['status' => 'scraping']);
            
            // Process based on job type
            $result = [];
            switch ($job['job_type']) {
                case 'scrape_source':
                    $result = $this->eventScraper->scrapeSource($source);
                    break;
                    
                case 'optimize_source':
                    $result = $this->eventScraper->optimizeSource($sourceId, false);
                    break;
                    
                case 'test_source':
                    $result = $this->eventScraper->testAndOptimizeSource($sourceId, false);
                    break;
                    
                default:
                    throw new Exception("Unknown job type: {$job['job_type']}");
            }
            
            $this->queueManager->updateProgress($jobId, 90, ['status' => 'finalizing']);
            
            // Record rate limit usage
            $this->rateLimiter->recordUsage($rateLimitKey);
            $this->rateLimiter->recordUsage($domainRateLimitKey);
            
            // Complete the job
            $this->queueManager->completeJob($jobId, $result);
            
            $this->logger->info("Job completed successfully", [
                'job_id' => $jobId,
                'source_id' => $sourceId,
                'events_found' => $result['events_found'] ?? 0,
                'events_added' => $result['events_added'] ?? 0,
                'worker_id' => $this->workerId
            ]);
            
        } catch (Exception $e) {
            $this->logger->error("Job failed", [
                'job_id' => $jobId,
                'source_id' => $sourceId,
                'error' => $e->getMessage(),
                'worker_id' => $this->workerId
            ]);
            
            // Determine if this is a retryable error
            $retryable = $this->isRetryableError($e);
            $this->queueManager->failJob($jobId, $e->getMessage(), $retryable);
        }
    }

    /**
     * Determine if an error is retryable
     */
    private function isRetryableError(Exception $e): bool
    {
        $message = strtolower($e->getMessage());
        
        // Network/temporary errors are retryable
        $retryableErrors = [
            'timeout',
            'connection',
            'network',
            'temporarily unavailable',
            'service unavailable',
            'rate limit',
            'too many requests',
            'gateway timeout',
            'bad gateway'
        ];
        
        foreach ($retryableErrors as $error) {
            if (strpos($message, $error) !== false) {
                return true;
            }
        }
        
        // HTTP 5xx errors are typically retryable
        if (preg_match('/http.*5\d\d/', $message)) {
            return true;
        }
        
        return false;
    }

    /**
     * Handle shutdown signal
     */
    public function handleShutdown(): void
    {
        $this->logger->info("Received shutdown signal", [
            'worker_id' => $this->workerId,
            'processed_jobs' => $this->processedJobs
        ]);
        
        $this->running = false;
    }

    /**
     * Graceful shutdown
     */
    private function shutdown(): void
    {
        $this->logger->info("Shutting down worker", [
            'worker_id' => $this->workerId,
            'processed_jobs' => $this->processedJobs
        ]);
        
        // Unregister from queue manager
        $this->queueManager->unregisterWorker($this->workerId);
        
        $this->running = false;
    }

    /**
     * Generate unique worker ID
     */
    private function generateWorkerId(): string
    {
        $hostname = gethostname() ?: 'unknown';
        $pid = getmypid() ?: rand(1000, 9999);
        $timestamp = time();
        
        return "worker_{$hostname}_{$pid}_{$timestamp}";
    }

    /**
     * Get worker status
     */
    public function getStatus(): array
    {
        return [
            'worker_id' => $this->workerId,
            'running' => $this->running,
            'processed_jobs' => $this->processedJobs,
            'max_jobs' => $this->maxJobsPerWorker,
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true)
        ];
    }
}