<?php

namespace YFEvents\Scrapers\Queue;

use PDO;
use Exception;

/**
 * Worker Manager for Concurrent Scraping
 * Manages multiple worker processes for optimal performance
 */
class WorkerManager
{
    private PDO $db;
    private QueueManager $queueManager;
    private array $workers = [];
    private bool $running = false;
    private int $maxWorkers;
    private int $checkInterval = 10; // seconds
    private string $logFile;

    public function __construct(PDO $db, array $config = [])
    {
        $this->db = $db;
        $this->queueManager = new QueueManager($db);
        $this->maxWorkers = $config['max_workers'] ?? $this->calculateOptimalWorkerCount();
        $this->logFile = $config['log_file'] ?? '/tmp/scraper_worker_manager.log';
        
        // Register signal handlers
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGTERM, [$this, 'handleShutdown']);
            pcntl_signal(SIGINT, [$this, 'handleShutdown']);
            pcntl_signal(SIGCHLD, [$this, 'handleChildExit']);
        }
    }

    /**
     * Start the worker manager
     */
    public function start(): void
    {
        $this->running = true;
        $this->log("Starting worker manager with max {$this->maxWorkers} workers");
        
        while ($this->running) {
            try {
                // Clean up dead workers
                $this->cleanupWorkers();
                
                // Check queue status and adjust worker count
                $this->adjustWorkerCount();
                
                // Monitor worker health
                $this->monitorWorkers();
                
                // Sleep before next check
                sleep($this->checkInterval);
                
                // Process signals
                if (function_exists('pcntl_signal_dispatch')) {
                    pcntl_signal_dispatch();
                }
                
            } catch (Exception $e) {
                $this->log("Manager error: " . $e->getMessage(), 'ERROR');
                sleep(30); // Wait longer on error
            }
        }
        
        $this->shutdown();
    }

    /**
     * Adjust worker count based on queue status
     */
    private function adjustWorkerCount(): void
    {
        $stats = $this->queueManager->getStats();
        $pendingJobs = $stats['jobs_by_status']['pending'] ?? 0;
        $processingJobs = $stats['jobs_by_status']['processing'] ?? 0;
        $activeWorkers = count($this->getActiveWorkers());
        
        $this->log("Queue status: {$pendingJobs} pending, {$processingJobs} processing, {$activeWorkers} workers");
        
        // Calculate needed workers
        $neededWorkers = min($this->maxWorkers, $this->calculateNeededWorkers($pendingJobs, $activeWorkers));
        
        // Start additional workers if needed
        while ($activeWorkers < $neededWorkers) {
            if ($this->spawnWorker()) {
                $activeWorkers++;
                $this->log("Spawned new worker (now {$activeWorkers}/{$neededWorkers})");
            } else {
                $this->log("Failed to spawn worker", 'ERROR');
                break;
            }
        }
        
        // Stop excess workers if queue is empty
        if ($pendingJobs == 0 && $processingJobs == 0 && $activeWorkers > 1) {
            $this->stopExcessWorkers($activeWorkers - 1);
        }
    }

    /**
     * Calculate needed workers based on queue status
     */
    private function calculateNeededWorkers(int $pendingJobs, int $activeWorkers): int
    {
        if ($pendingJobs == 0) {
            return max(1, $activeWorkers); // Keep at least one worker
        }
        
        // Start with one worker per 5 pending jobs
        $baseWorkers = ceil($pendingJobs / 5);
        
        // Scale down for very large queues to prevent resource exhaustion
        if ($pendingJobs > 50) {
            $baseWorkers = min($baseWorkers, 8);
        }
        
        // Gradually increase workers rather than spawning all at once
        $currentWorkers = $activeWorkers;
        $targetWorkers = min($baseWorkers, $this->maxWorkers);
        
        // Don't increase by more than 2 workers at a time
        return min($targetWorkers, $currentWorkers + 2);
    }

    /**
     * Spawn a new worker process
     */
    private function spawnWorker(): bool
    {
        if (!function_exists('pcntl_fork')) {
            $this->log("Process control not available, running single worker", 'WARNING');
            return $this->runSingleWorker();
        }
        
        $pid = pcntl_fork();
        
        if ($pid == -1) {
            $this->log("Failed to fork worker process", 'ERROR');
            return false;
        } elseif ($pid == 0) {
            // Child process - run worker
            try {
                $worker = new ScraperWorker($this->db, [
                    'max_jobs' => 50,
                    'worker_id' => $this->generateWorkerId()
                ]);
                $worker->start();
                exit(0);
            } catch (Exception $e) {
                $this->log("Worker process error: " . $e->getMessage(), 'ERROR');
                exit(1);
            }
        } else {
            // Parent process - track worker
            $this->workers[$pid] = [
                'pid' => $pid,
                'started_at' => time(),
                'status' => 'active'
            ];
            return true;
        }
    }

    /**
     * Run single worker for systems without process control
     */
    private function runSingleWorker(): bool
    {
        try {
            $worker = new ScraperWorker($this->db, [
                'max_jobs' => 10, // Fewer jobs for single worker
                'worker_id' => $this->generateWorkerId()
            ]);
            
            // Start worker in separate thread if possible
            if (class_exists('Thread')) {
                // pthreads extension (rarely available)
                $workerThread = new class($worker) extends \Thread {
                    private $worker;
                    public function __construct($worker) { $this->worker = $worker; }
                    public function run() { $this->worker->start(); }
                };
                $workerThread->start();
                return true;
            } else {
                // Fallback: run worker directly (blocking)
                $this->log("Running single blocking worker", 'WARNING');
                $worker->start();
                return true;
            }
        } catch (Exception $e) {
            $this->log("Single worker error: " . $e->getMessage(), 'ERROR');
            return false;
        }
    }

    /**
     * Stop excess workers gracefully
     */
    private function stopExcessWorkers(int $targetCount): void
    {
        $activeWorkers = $this->getActiveWorkers();
        $toStop = count($activeWorkers) - $targetCount;
        
        if ($toStop <= 0) {
            return;
        }
        
        $this->log("Stopping {$toStop} excess workers");
        
        // Stop oldest workers first
        usort($activeWorkers, function($a, $b) {
            return $a['started_at'] - $b['started_at'];
        });
        
        for ($i = 0; $i < $toStop; $i++) {
            $worker = $activeWorkers[$i];
            $this->stopWorker($worker['pid']);
        }
    }

    /**
     * Stop a specific worker
     */
    private function stopWorker(int $pid): void
    {
        if (!isset($this->workers[$pid])) {
            return;
        }
        
        // Send TERM signal for graceful shutdown
        if (function_exists('posix_kill')) {
            posix_kill($pid, SIGTERM);
            
            // Wait for graceful shutdown
            sleep(5);
            
            // Force kill if still running
            if (posix_kill($pid, 0)) {
                posix_kill($pid, SIGKILL);
            }
        }
        
        $this->workers[$pid]['status'] = 'stopped';
    }

    /**
     * Clean up dead/finished workers
     */
    private function cleanupWorkers(): void
    {
        foreach ($this->workers as $pid => $worker) {
            if ($worker['status'] !== 'active') {
                unset($this->workers[$pid]);
                continue;
            }
            
            // Check if process is still running
            if (function_exists('posix_kill') && !posix_kill($pid, 0)) {
                $this->log("Worker {$pid} has exited");
                unset($this->workers[$pid]);
            }
        }
    }

    /**
     * Get active workers
     */
    private function getActiveWorkers(): array
    {
        $active = [];
        foreach ($this->workers as $pid => $worker) {
            if ($worker['status'] === 'active') {
                $active[$pid] = $worker;
            }
        }
        return $active;
    }

    /**
     * Monitor worker health and performance
     */
    private function monitorWorkers(): void
    {
        $activeWorkers = $this->getActiveWorkers();
        
        foreach ($activeWorkers as $pid => $worker) {
            $runtime = time() - $worker['started_at'];
            
            // Restart workers that have been running too long (memory leaks, etc.)
            if ($runtime > 3600) { // 1 hour
                $this->log("Restarting long-running worker {$pid} (runtime: {$runtime}s)");
                $this->stopWorker($pid);
            }
        }
    }

    /**
     * Handle child process exit
     */
    public function handleChildExit(): void
    {
        if (!function_exists('pcntl_waitpid')) {
            return;
        }
        
        while (($pid = pcntl_waitpid(-1, $status, WNOHANG)) > 0) {
            if (isset($this->workers[$pid])) {
                $exitCode = pcntl_wexitstatus($status);
                $this->log("Worker {$pid} exited with code {$exitCode}");
                unset($this->workers[$pid]);
            }
        }
    }

    /**
     * Handle shutdown signal
     */
    public function handleShutdown(): void
    {
        $this->log("Received shutdown signal");
        $this->running = false;
    }

    /**
     * Graceful shutdown
     */
    private function shutdown(): void
    {
        $this->log("Shutting down worker manager");
        
        // Stop all workers
        foreach ($this->getActiveWorkers() as $pid => $worker) {
            $this->stopWorker($pid);
        }
        
        // Wait for workers to finish
        $timeout = 30; // seconds
        $start = time();
        
        while (!empty($this->getActiveWorkers()) && (time() - $start) < $timeout) {
            sleep(1);
            $this->cleanupWorkers();
            
            if (function_exists('pcntl_signal_dispatch')) {
                pcntl_signal_dispatch();
            }
        }
        
        // Force kill any remaining workers
        foreach ($this->getActiveWorkers() as $pid => $worker) {
            if (function_exists('posix_kill')) {
                posix_kill($pid, SIGKILL);
            }
        }
        
        $this->log("Worker manager shutdown complete");
    }

    /**
     * Calculate optimal worker count based on system resources
     */
    private function calculateOptimalWorkerCount(): int
    {
        // Default to 4 workers
        $workers = 4;
        
        // Adjust based on CPU cores if available
        if (function_exists('shell_exec')) {
            $cores = shell_exec('nproc 2>/dev/null') ?: shell_exec('sysctl -n hw.ncpu 2>/dev/null');
            if ($cores) {
                $workers = max(2, min(8, intval($cores)));
            }
        }
        
        // Adjust based on available memory
        $memoryMB = $this->getAvailableMemoryMB();
        if ($memoryMB < 512) {
            $workers = 1;
        } elseif ($memoryMB < 1024) {
            $workers = min($workers, 2);
        } elseif ($memoryMB < 2048) {
            $workers = min($workers, 4);
        }
        
        return $workers;
    }

    /**
     * Get available memory in MB
     */
    private function getAvailableMemoryMB(): int
    {
        $memoryLimit = ini_get('memory_limit');
        if ($memoryLimit === '-1') {
            return 2048; // Assume 2GB if unlimited
        }
        
        $value = intval($memoryLimit);
        $unit = strtoupper(substr($memoryLimit, -1));
        
        switch ($unit) {
            case 'G': return $value * 1024;
            case 'M': return $value;
            case 'K': return $value / 1024;
            default: return $value / (1024 * 1024);
        }
    }

    /**
     * Generate unique worker ID
     */
    private function generateWorkerId(): string
    {
        return 'manager_' . getmypid() . '_' . uniqid();
    }

    /**
     * Log message with timestamp
     */
    private function log(string $message, string $level = 'INFO'): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[{$timestamp}] [{$level}] {$message}\n";
        
        // Log to file
        file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
        
        // Also log to error_log for important messages
        if ($level === 'ERROR' || $level === 'WARNING') {
            error_log("WorkerManager [{$level}]: {$message}");
        }
    }

    /**
     * Get manager status
     */
    public function getStatus(): array
    {
        return [
            'running' => $this->running,
            'max_workers' => $this->maxWorkers,
            'active_workers' => count($this->getActiveWorkers()),
            'worker_pids' => array_keys($this->getActiveWorkers()),
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true)
        ];
    }
}