#!/usr/bin/env php
<?php

/**
 * Optimized Scraper Cron Job
 * Replaces the old scrape-events.php with intelligent scheduling and concurrency
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../vendor/autoload.php';

use YakimaFinds\Scrapers\Queue\QueueManager;
use YakimaFinds\Scrapers\Queue\ScraperScheduler;
use YakimaFinds\Scrapers\Queue\WorkerManager;
use YakimaFinds\Utils\SystemLogger;

class OptimizedScraperCron
{
    private PDO $db;
    private QueueManager $queueManager;
    private ScraperScheduler $scheduler;
    private SystemLogger $logger;
    private string $mode;
    private array $config;

    public function __construct(string $mode = 'auto')
    {
        global $pdo;
        $this->db = $pdo;
        $this->queueManager = new QueueManager($this->db);
        $this->scheduler = new ScraperScheduler($this->db);
        $this->logger = new SystemLogger($this->db, 'optimized_scraper_cron');
        $this->mode = $mode;
        $this->config = $this->loadConfig();
    }

    public function run(): void
    {
        $this->logger->info("Starting optimized scraper cron", [
            'mode' => $this->mode,
            'pid' => getmypid(),
            'memory_limit' => ini_get('memory_limit')
        ]);

        try {
            // Step 1: Cleanup old data
            $this->cleanup();
            
            // Step 2: Schedule new jobs intelligently
            $this->scheduleJobs();
            
            // Step 3: Process jobs based on mode
            switch ($this->mode) {
                case 'schedule_only':
                    $this->logger->info("Schedule-only mode - jobs queued for workers");
                    break;
                    
                case 'single_worker':
                    $this->runSingleWorker();
                    break;
                    
                case 'auto':
                default:
                    $this->runAutoMode();
                    break;
            }
            
            // Step 4: Final status report
            $this->reportStatus();
            
        } catch (Exception $e) {
            $this->logger->error("Cron job failed", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw $e;
        }
    }

    private function cleanup(): void
    {
        $this->logger->info("Running cleanup");
        
        $results = $this->queueManager->cleanup();
        
        $this->logger->info("Cleanup complete", [
            'stale_jobs_reset' => $results['stale_jobs_reset'],
            'dead_workers_removed' => $results['dead_workers_removed'],
            'old_jobs_deleted' => $results['old_completed_jobs_deleted']
        ]);
    }

    private function scheduleJobs(): void
    {
        $this->logger->info("Starting intelligent job scheduling");
        
        // Schedule regular scraping jobs
        $scheduleResult = $this->scheduler->scheduleIntelligent();
        
        $this->logger->info("Intelligent scheduling complete", [
            'scheduled' => count($scheduleResult['scheduled']),
            'skipped' => count($scheduleResult['skipped']),
            'total_sources' => $scheduleResult['total_sources']
        ]);
        
        // Schedule maintenance jobs if needed
        $maintenanceResult = $this->scheduler->scheduleMaintenance();
        
        if (!empty($maintenanceResult)) {
            $this->logger->info("Maintenance jobs scheduled", [
                'maintenance_jobs' => count($maintenanceResult)
            ]);
        }
    }

    private function runSingleWorker(): void
    {
        $this->logger->info("Running in single worker mode");
        
        $worker = new \YakimaFinds\Scrapers\Queue\ScraperWorker($this->db, [
            'worker_id' => 'cron_worker_' . getmypid(),
            'max_jobs' => $this->config['single_worker_max_jobs'],
            'heartbeat_interval' => 60
        ]);
        
        // Set time limit for cron execution
        set_time_limit($this->config['max_execution_time']);
        
        $startTime = time();
        $maxRunTime = $this->config['max_execution_time'] - 30; // Leave 30s buffer
        
        // Override worker to respect time limit
        $originalStart = $worker->start();
        
        $worker->start = function() use ($worker, $startTime, $maxRunTime) {
            while ($worker->isRunning() && (time() - $startTime) < $maxRunTime) {
                // Process one job at a time with time checks
                $job = $this->queueManager->dequeue($worker->getWorkerId());
                
                if (!$job) {
                    break; // No more jobs
                }
                
                $worker->processJob($job);
                
                // Check if we have time for another job
                if ((time() - $startTime) > ($maxRunTime - 60)) {
                    $this->logger->info("Approaching time limit, stopping worker");
                    break;
                }
            }
        };
        
        $worker->start();
        
        $this->logger->info("Single worker completed", [
            'runtime_seconds' => time() - $startTime,
            'processed_jobs' => $worker->getProcessedJobs()
        ]);
    }

    private function runAutoMode(): void
    {
        $stats = $this->queueManager->getStats();
        $pendingJobs = $stats['jobs_by_status']['pending'] ?? 0;
        $activeWorkers = $stats['active_workers'];
        
        $this->logger->info("Auto mode analysis", [
            'pending_jobs' => $pendingJobs,
            'active_workers' => $activeWorkers,
            'system_load' => $this->getSystemLoad()
        ]);
        
        if ($pendingJobs == 0) {
            $this->logger->info("No pending jobs, nothing to process");
            return;
        }
        
        if ($activeWorkers > 0 && $pendingJobs <= $activeWorkers * 2) {
            $this->logger->info("Sufficient workers already running, letting them handle the queue");
            return;
        }
        
        // Decide whether to run workers or manager
        if ($this->shouldRunManager($pendingJobs, $activeWorkers)) {
            $this->runLimitedManager();
        } else {
            $this->runSingleWorker();
        }
    }

    private function shouldRunManager(int $pendingJobs, int $activeWorkers): bool
    {
        // Run manager if:
        // 1. Many jobs and no active workers
        // 2. Process control is available
        // 3. System has enough resources
        
        if (!function_exists('pcntl_fork')) {
            return false; // Can't fork workers
        }
        
        if ($pendingJobs < 5) {
            return false; // Too few jobs for manager overhead
        }
        
        if ($activeWorkers > 0 && $pendingJobs < 20) {
            return false; // Let existing workers handle it
        }
        
        $systemLoad = $this->getSystemLoad();
        if ($systemLoad > 2.0) {
            return false; // System too busy
        }
        
        return true;
    }

    private function runLimitedManager(): void
    {
        $this->logger->info("Running limited worker manager for cron");
        
        $maxWorkers = min($this->config['max_workers'], 3); // Limit for cron
        $manager = new WorkerManager($this->db, [
            'max_workers' => $maxWorkers,
            'log_file' => '/tmp/cron_worker_manager.log'
        ]);
        
        // Set time limit
        set_time_limit($this->config['max_execution_time']);
        
        $startTime = time();
        $maxRunTime = $this->config['max_execution_time'] - 60; // Leave 1 minute buffer
        
        // Run manager with time limit
        $pid = pcntl_fork();
        
        if ($pid == 0) {
            // Child process - run manager
            $manager->start();
            exit(0);
        } elseif ($pid > 0) {
            // Parent process - monitor time limit
            while ((time() - $startTime) < $maxRunTime) {
                sleep(10);
                
                // Check if manager is still running
                if (!posix_kill($pid, 0)) {
                    break; // Manager finished
                }
                
                // Check queue status
                $stats = $this->queueManager->getStats();
                $pendingJobs = $stats['jobs_by_status']['pending'] ?? 0;
                
                if ($pendingJobs == 0) {
                    $this->logger->info("All jobs processed, stopping manager");
                    posix_kill($pid, SIGTERM);
                    sleep(5);
                    if (posix_kill($pid, 0)) {
                        posix_kill($pid, SIGKILL);
                    }
                    break;
                }
            }
            
            // Ensure manager is stopped
            if (posix_kill($pid, 0)) {
                $this->logger->info("Time limit reached, stopping manager");
                posix_kill($pid, SIGTERM);
                sleep(10);
                if (posix_kill($pid, 0)) {
                    posix_kill($pid, SIGKILL);
                }
            }
            
            pcntl_waitpid($pid, $status);
        }
        
        $this->logger->info("Limited manager completed", [
            'runtime_seconds' => time() - $startTime
        ]);
    }

    private function reportStatus(): void
    {
        $stats = $this->queueManager->getStats();
        
        $this->logger->info("Cron job complete - final status", [
            'queue_status' => $stats['jobs_by_status'],
            'active_workers' => $stats['active_workers'],
            'avg_processing_time' => $stats['avg_processing_time_seconds'],
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true)
        ]);
        
        // Log summary to stdout for cron logs
        echo "Optimized scraper cron completed successfully\n";
        echo "Queue status: " . json_encode($stats['jobs_by_status']) . "\n";
        echo "Active workers: " . $stats['active_workers'] . "\n";
        echo "Memory usage: " . round(memory_get_usage(true) / 1024 / 1024, 1) . "MB\n";
    }

    private function getSystemLoad(): float
    {
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            return $load[0]; // 1-minute load average
        }
        
        // Fallback for systems without sys_getloadavg
        if (is_readable('/proc/loadavg')) {
            $loadavg = file_get_contents('/proc/loadavg');
            $load = explode(' ', $loadavg);
            return floatval($load[0]);
        }
        
        return 0.0; // Unknown load
    }

    private function loadConfig(): array
    {
        $config = [
            'max_execution_time' => 1800, // 30 minutes
            'max_workers' => 4,
            'single_worker_max_jobs' => 20,
            'memory_limit' => '512M'
        ];
        
        // Load from environment or config file if available
        if (file_exists(__DIR__ . '/../config/scraper_config.php')) {
            $fileConfig = include __DIR__ . '/../config/scraper_config.php';
            $config = array_merge($config, $fileConfig);
        }
        
        // Environment overrides
        if (getenv('SCRAPER_MAX_EXECUTION_TIME')) {
            $config['max_execution_time'] = intval(getenv('SCRAPER_MAX_EXECUTION_TIME'));
        }
        
        if (getenv('SCRAPER_MAX_WORKERS')) {
            $config['max_workers'] = intval(getenv('SCRAPER_MAX_WORKERS'));
        }
        
        return $config;
    }
}

// Parse command line arguments
$mode = 'auto';
$validModes = ['auto', 'single_worker', 'schedule_only'];

if (isset($argv[1]) && in_array($argv[1], $validModes)) {
    $mode = $argv[1];
} elseif (isset($argv[1])) {
    echo "Invalid mode: {$argv[1]}\n";
    echo "Valid modes: " . implode(', ', $validModes) . "\n";
    exit(1);
}

// Run the cron job
try {
    $cron = new OptimizedScraperCron($mode);
    $cron->run();
    exit(0);
} catch (Exception $e) {
    echo "Cron job failed: " . $e->getMessage() . "\n";
    error_log("Optimized scraper cron failed: " . $e->getMessage());
    exit(1);
}