#!/usr/bin/env php
<?php

/**
 * Optimized Scraper Manager CLI
 * Command-line interface for managing the concurrent scraping system
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../vendor/autoload.php';

use YFEvents\Scrapers\Queue\QueueManager;
use YFEvents\Scrapers\Queue\WorkerManager;
use YFEvents\Scrapers\Queue\ScraperScheduler;
use YFEvents\Scrapers\Queue\ScraperWorker;

class ScraperManagerCLI
{
    private PDO $db;
    private QueueManager $queueManager;
    private ScraperScheduler $scheduler;
    
    public function __construct()
    {
        global $pdo;
        $this->db = $pdo;
        $this->queueManager = new QueueManager($this->db);
        $this->scheduler = new ScraperScheduler($this->db);
    }

    public function run(array $argv): void
    {
        $command = $argv[1] ?? 'help';
        
        switch ($command) {
            case 'worker':
                $this->runWorker($argv);
                break;
                
            case 'manager':
                $this->runManager($argv);
                break;
                
            case 'schedule':
                $this->scheduleJobs($argv);
                break;
                
            case 'status':
                $this->showStatus();
                break;
                
            case 'queue':
                $this->manageQueue($argv);
                break;
                
            case 'cleanup':
                $this->cleanup();
                break;
                
            case 'stats':
                $this->showStats();
                break;
                
            case 'help':
            default:
                $this->showHelp();
                break;
        }
    }

    private function runWorker(array $argv): void
    {
        $workerId = $argv[2] ?? null;
        $maxJobs = isset($argv[3]) ? intval($argv[3]) : 50;
        
        echo "Starting scraper worker...\n";
        echo "Worker ID: " . ($workerId ?: 'auto-generated') . "\n";
        echo "Max jobs: {$maxJobs}\n\n";
        
        $config = [
            'max_jobs' => $maxJobs,
            'heartbeat_interval' => 30
        ];
        
        if ($workerId) {
            $config['worker_id'] = $workerId;
        }
        
        $worker = new ScraperWorker($this->db, $config);
        
        // Handle shutdown signals
        $shutdown = function() use ($worker) {
            echo "\nReceived shutdown signal. Stopping worker gracefully...\n";
            $worker->handleShutdown();
        };
        
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGTERM, $shutdown);
            pcntl_signal(SIGINT, $shutdown);
        }
        
        try {
            $worker->start();
        } catch (Exception $e) {
            echo "Worker error: " . $e->getMessage() . "\n";
            exit(1);
        }
        
        echo "Worker finished.\n";
    }

    private function runManager(array $argv): void
    {
        $maxWorkers = isset($argv[2]) ? intval($argv[2]) : null;
        
        echo "Starting worker manager...\n";
        echo "Max workers: " . ($maxWorkers ?: 'auto-detected') . "\n\n";
        
        $config = [];
        if ($maxWorkers) {
            $config['max_workers'] = $maxWorkers;
        }
        
        $manager = new WorkerManager($this->db, $config);
        
        // Handle shutdown signals
        $shutdown = function() use ($manager) {
            echo "\nReceived shutdown signal. Stopping manager gracefully...\n";
            $manager->handleShutdown();
        };
        
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGTERM, $shutdown);
            pcntl_signal(SIGINT, $shutdown);
        }
        
        try {
            $manager->start();
        } catch (Exception $e) {
            echo "Manager error: " . $e->getMessage() . "\n";
            exit(1);
        }
        
        echo "Manager finished.\n";
    }

    private function scheduleJobs(array $argv): void
    {
        $type = $argv[2] ?? 'intelligent';
        
        echo "Scheduling jobs using '{$type}' strategy...\n";
        
        switch ($type) {
            case 'intelligent':
                $result = $this->scheduler->scheduleIntelligent();
                echo "Intelligent scheduling complete:\n";
                echo "- Scheduled: " . count($result['scheduled']) . " jobs\n";
                echo "- Skipped: " . count($result['skipped']) . " sources\n";
                
                if (!empty($result['scheduled'])) {
                    echo "\nScheduled jobs:\n";
                    foreach ($result['scheduled'] as $job) {
                        echo sprintf(
                            "  Source %d: Priority %d - %s\n",
                            $job['source_id'],
                            $job['priority'],
                            $job['reason']
                        );
                    }
                }
                break;
                
            case 'all':
                $priority = isset($argv[3]) ? intval($argv[3]) : QueueManager::PRIORITY_NORMAL;
                $scheduled = $this->queueManager->scheduleAllSources($priority);
                echo "Scheduled {$scheduled} sources with priority {$priority}\n";
                break;
                
            case 'maintenance':
                $result = $this->scheduler->scheduleMaintenance();
                echo "Maintenance scheduling complete:\n";
                echo "- Scheduled: " . count($result) . " maintenance jobs\n";
                
                foreach ($result as $job) {
                    echo sprintf(
                        "  Source %d: %s - %s\n",
                        $job['source_id'],
                        $job['job_type'],
                        $job['reason']
                    );
                }
                break;
                
            default:
                echo "Unknown scheduling type: {$type}\n";
                echo "Available types: intelligent, all, maintenance\n";
                exit(1);
        }
    }

    private function showStatus(): void
    {
        $stats = $this->queueManager->getStats();
        
        echo "=== Scraper System Status ===\n\n";
        
        echo "Queue Status:\n";
        foreach ($stats['jobs_by_status'] as $status => $count) {
            echo sprintf("  %-12s: %d\n", ucfirst($status), $count);
        }
        
        echo "\nWorkers:\n";
        echo sprintf("  Active: %d\n", $stats['active_workers']);
        echo sprintf("  Avg Processing Time: %.1fs\n", $stats['avg_processing_time_seconds']);
        
        if (!empty($stats['jobs_last_24h'])) {
            echo "\nLast 24 Hours:\n";
            foreach ($stats['jobs_last_24h'] as $status => $count) {
                echo sprintf("  %-12s: %d\n", ucfirst($status), $count);
            }
        }
        
        echo "\n";
    }

    private function manageQueue(array $argv): void
    {
        $action = $argv[2] ?? 'list';
        
        switch ($action) {
            case 'list':
                $this->listQueueJobs();
                break;
                
            case 'clear':
                $status = $argv[3] ?? null;
                $this->clearQueue($status);
                break;
                
            case 'retry':
                $jobId = isset($argv[3]) ? intval($argv[3]) : null;
                $this->retryJob($jobId);
                break;
                
            default:
                echo "Unknown queue action: {$action}\n";
                echo "Available actions: list, clear, retry\n";
                exit(1);
        }
    }

    private function listQueueJobs(): void
    {
        $sql = "
            SELECT id, source_id, job_type, status, priority, 
                   scheduled_at, created_at, error_message
            FROM scraper_queue 
            ORDER BY priority DESC, created_at ASC 
            LIMIT 20
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($jobs)) {
            echo "No jobs in queue.\n";
            return;
        }
        
        echo "=== Queue Jobs (Last 20) ===\n\n";
        echo sprintf("%-4s %-8s %-12s %-10s %-8s %-19s %s\n",
            'ID', 'Source', 'Type', 'Status', 'Priority', 'Scheduled', 'Error');
        echo str_repeat('-', 100) . "\n";
        
        foreach ($jobs as $job) {
            $error = $job['error_message'] ? substr($job['error_message'], 0, 30) . '...' : '';
            echo sprintf("%-4d %-8d %-12s %-10s %-8d %-19s %s\n",
                $job['id'],
                $job['source_id'],
                $job['job_type'],
                $job['status'],
                $job['priority'],
                $job['scheduled_at'],
                $error
            );
        }
        echo "\n";
    }

    private function clearQueue(?string $status): void
    {
        if ($status) {
            $sql = "DELETE FROM scraper_queue WHERE status = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$status]);
            $cleared = $stmt->rowCount();
            echo "Cleared {$cleared} jobs with status '{$status}'\n";
        } else {
            $sql = "DELETE FROM scraper_queue WHERE status IN ('completed', 'failed')";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $cleared = $stmt->rowCount();
            echo "Cleared {$cleared} completed/failed jobs\n";
        }
    }

    private function retryJob(?int $jobId): void
    {
        if (!$jobId) {
            echo "Job ID required for retry\n";
            exit(1);
        }
        
        $sql = "
            UPDATE scraper_queue 
            SET status = 'pending', error_message = NULL, retry_count = 0,
                scheduled_at = NOW()
            WHERE id = ?
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$jobId]);
        
        if ($stmt->rowCount() > 0) {
            echo "Job {$jobId} has been reset for retry\n";
        } else {
            echo "Job {$jobId} not found\n";
        }
    }

    private function cleanup(): void
    {
        echo "Running system cleanup...\n";
        
        $results = $this->queueManager->cleanup();
        
        echo "Cleanup complete:\n";
        echo "- Reset stale jobs: " . $results['stale_jobs_reset'] . "\n";
        echo "- Removed dead workers: " . $results['dead_workers_removed'] . "\n";
        echo "- Deleted old jobs: " . $results['old_completed_jobs_deleted'] . "\n";
    }

    private function showStats(): void
    {
        $stats = $this->scheduler->getStats();
        
        echo "=== Comprehensive System Statistics ===\n\n";
        
        // Queue stats
        echo "Queue Statistics:\n";
        foreach ($stats['queue']['jobs_by_status'] as $status => $count) {
            echo sprintf("  %-12s: %d\n", ucfirst($status), $count);
        }
        echo sprintf("  Active Workers: %d\n", $stats['queue']['active_workers']);
        echo sprintf("  Avg Process Time: %.1fs\n", $stats['queue']['avg_processing_time_seconds']);
        
        // Rate limit stats
        if (!empty($stats['rate_limits']['requests_by_type'])) {
            echo "\nRate Limit Usage:\n";
            foreach ($stats['rate_limits']['requests_by_type'] as $type) {
                echo sprintf("  %-15s: %d requests\n", $type['key_type'], $type['request_count']);
            }
            echo sprintf("  Total Requests: %d\n", $stats['rate_limits']['total_requests']);
        }
        
        // Scheduler stats
        echo "\nScheduler Statistics:\n";
        echo sprintf("  Active Sources: %d\n", $stats['scheduler']['active_sources']);
        echo sprintf("  Underperforming: %d\n", $stats['scheduler']['underperforming_sources']);
        echo sprintf("  Failed Sources: %d\n", $stats['scheduler']['failed_sources']);
        
        echo "\n";
    }

    private function showHelp(): void
    {
        echo "Optimized Scraper Manager CLI\n\n";
        echo "Usage: php scraper_manager.php <command> [options]\n\n";
        echo "Commands:\n";
        echo "  worker [worker_id] [max_jobs]  Start a worker process\n";
        echo "  manager [max_workers]          Start the worker manager\n";
        echo "  schedule <type>                Schedule jobs\n";
        echo "    - intelligent                Use intelligent scheduling\n";
        echo "    - all [priority]            Schedule all sources\n";
        echo "    - maintenance               Schedule maintenance jobs\n";
        echo "  status                         Show system status\n";
        echo "  queue <action>                 Manage queue\n";
        echo "    - list                      List recent jobs\n";
        echo "    - clear [status]            Clear completed/failed jobs\n";
        echo "    - retry <job_id>            Retry a failed job\n";
        echo "  cleanup                        Clean up old data\n";
        echo "  stats                          Show detailed statistics\n";
        echo "  help                           Show this help\n\n";
        echo "Examples:\n";
        echo "  php scraper_manager.php worker worker1 25\n";
        echo "  php scraper_manager.php manager 4\n";
        echo "  php scraper_manager.php schedule intelligent\n";
        echo "  php scraper_manager.php queue clear failed\n";
        echo "\n";
    }
}

// Run CLI
if (php_sapi_name() === 'cli') {
    $cli = new ScraperManagerCLI();
    $cli->run($argv);
} else {
    echo "This script can only be run from command line.\n";
    exit(1);
}