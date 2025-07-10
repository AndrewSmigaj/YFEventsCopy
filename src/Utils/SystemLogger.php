<?php

namespace YakimaFinds\Utils;

use PDO;

class SystemLogger
{
    private $db;
    private $component;
    private $logToFile;
    private $logToDatabase;
    
    public function __construct($db, $component = 'system', $logToFile = true, $logToDatabase = true)
    {
        $this->db = $db;
        $this->component = $component;
        $this->logToFile = $logToFile;
        $this->logToDatabase = $logToDatabase;
    }
    
    /**
     * Log an info message
     */
    public function info($message, $context = [])
    {
        $this->log('info', $message, $context);
    }
    
    /**
     * Log a warning message
     */
    public function warning($message, $context = [])
    {
        $this->log('warning', $message, $context);
    }
    
    /**
     * Log an error message
     */
    public function error($message, $context = [])
    {
        $this->log('error', $message, $context);
    }
    
    /**
     * Log a debug message
     */
    public function debug($message, $context = [])
    {
        $this->log('debug', $message, $context);
    }
    
    /**
     * Log a critical error
     */
    public function critical($message, $context = [])
    {
        $this->log('critical', $message, $context);
    }
    
    /**
     * Log performance metrics
     */
    public function performance($operation, $duration, $context = [])
    {
        $context['duration_ms'] = $duration;
        $context['operation'] = $operation;
        $this->log('performance', "Operation '{$operation}' completed in {$duration}ms", $context);
    }
    
    /**
     * Log database operations
     */
    public function database($operation, $table, $context = [])
    {
        $context['table'] = $table;
        $context['operation'] = $operation;
        $this->log('database', "Database {$operation} on {$table}", $context);
    }
    
    /**
     * Log API calls
     */
    public function api($endpoint, $method, $status, $context = [])
    {
        $context['endpoint'] = $endpoint;
        $context['method'] = $method;
        $context['status'] = $status;
        $this->log('api', "API {$method} {$endpoint} returned {$status}", $context);
    }
    
    /**
     * Log scraping activities
     */
    public function scraping($source, $action, $result, $context = [])
    {
        $context['source'] = $source;
        $context['action'] = $action;
        $context['result'] = $result;
        $this->log('scraping', "Scraping {$action} for {$source}: {$result}", $context);
    }
    
    /**
     * Core logging method
     */
    private function log($level, $message, $context = [])
    {
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? json_encode($context) : null;
        
        // Log to file
        if ($this->logToFile) {
            $this->logToFileSystem($level, $message, $context, $timestamp);
        }
        
        // Log to database
        if ($this->logToDatabase && $this->db) {
            $this->logToDatabase($level, $message, $contextStr, $timestamp);
        }
        
        // Also use PHP error_log for critical/error levels
        if (in_array($level, ['error', 'critical'])) {
            error_log("[{$this->component}] [{$level}] {$message}");
        }
    }
    
    /**
     * Log to file system
     */
    private function logToFileSystem($level, $message, $context, $timestamp)
    {
        $logDir = __DIR__ . '/../../logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $logFile = $logDir . '/system-' . date('Y-m-d') . '.log';
        $contextStr = !empty($context) ? ' ' . json_encode($context) : '';
        $logEntry = "[{$timestamp}] [{$this->component}] [{$level}] {$message}{$contextStr}\n";
        
        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Log to database
     */
    private function logToDatabase($level, $message, $context, $timestamp)
    {
        try {
            // Create table if not exists
            $this->ensureLogTableExists();
            
            $stmt = $this->db->prepare("
                INSERT INTO system_logs (component, level, message, context, created_at) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$this->component, $level, $message, $context, $timestamp]);
        } catch (\Exception $e) {
            // If database logging fails, fall back to file only
            error_log("Failed to log to database: " . $e->getMessage());
        }
    }
    
    /**
     * Ensure log table exists
     */
    private function ensureLogTableExists()
    {
        $sql = "CREATE TABLE IF NOT EXISTS system_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            component VARCHAR(100) NOT NULL,
            level ENUM('debug', 'info', 'warning', 'error', 'critical', 'performance', 'database', 'api', 'scraping') NOT NULL,
            message TEXT NOT NULL,
            context JSON NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_component_level (component, level),
            INDEX idx_created_at (created_at),
            INDEX idx_level (level)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        
        $this->db->exec($sql);
    }
    
    /**
     * Get logs for analysis
     */
    public function getLogs($since = null, $level = null, $component = null, $limit = 1000)
    {
        $sql = "SELECT * FROM system_logs WHERE 1=1";
        $params = [];
        
        if ($since) {
            $sql .= " AND created_at >= ?";
            $params[] = $since;
        }
        
        if ($level) {
            $sql .= " AND level = ?";
            $params[] = $level;
        }
        
        if ($component) {
            $sql .= " AND component = ?";
            $params[] = $component;
        }
        
        $sql .= " ORDER BY created_at DESC LIMIT ?";
        $params[] = $limit;
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get error summary for system checkup
     */
    public function getErrorSummary($since = null)
    {
        $sql = "SELECT 
                    component,
                    level,
                    COUNT(*) as count,
                    MAX(created_at) as last_occurrence,
                    GROUP_CONCAT(DISTINCT LEFT(message, 100) SEPARATOR ' | ') as sample_messages
                FROM system_logs 
                WHERE level IN ('error', 'critical', 'warning')";
        
        $params = [];
        if ($since) {
            $sql .= " AND created_at >= ?";
            $params[] = $since;
        }
        
        $sql .= " GROUP BY component, level ORDER BY count DESC, last_occurrence DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Create a logger instance for a specific component
     */
    public static function create($db, $component)
    {
        return new self($db, $component);
    }
}