<?php

namespace YFEvents\Utils;

use PDO;

class SystemCheckup
{
    private $db;
    private $logger;
    private $llmApiKey;
    private $llmEndpoint;
    
    public function __construct($db, $llmApiKey = null)
    {
        $this->db = $db;
        $this->logger = new SystemLogger($db, 'system_checkup');
        $this->llmApiKey = $llmApiKey ?: $_ENV['SEGMIND_API_KEY'] ?? null;
        $this->llmEndpoint = 'https://api.segmind.com/v1/llama3-8b-instruct';
    }
    
    /**
     * Run complete system checkup
     */
    public function runCheckup($generateRecommendations = true)
    {
        $this->logger->info("Starting system checkup");
        $startTime = microtime(true);
        
        // Get last checkup time
        $lastCheckup = $this->getLastCheckupTime();
        $this->logger->info("Last checkup was at", ['last_checkup' => $lastCheckup]);
        
        // Create checkup record
        $checkupId = $this->createCheckupRecord();
        
        try {
            $results = [
                'checkup_id' => $checkupId,
                'started_at' => date('Y-m-d H:i:s'),
                'last_checkup' => $lastCheckup,
                'components_checked' => [],
                'errors_found' => [],
                'warnings_found' => [],
                'recommendations' => [],
                'performance_metrics' => []
            ];
            
            // 1. Check database health
            $dbHealth = $this->checkDatabaseHealth();
            $results['components_checked']['database'] = $dbHealth;
            
            // 2. Check file system
            $fsHealth = $this->checkFileSystemHealth();
            $results['components_checked']['filesystem'] = $fsHealth;
            
            // 3. Check scraping system
            $scrapingHealth = $this->checkScrapingSystem($lastCheckup);
            $results['components_checked']['scraping'] = $scrapingHealth;
            
            // 4. Check API endpoints
            $apiHealth = $this->checkApiHealth();
            $results['components_checked']['api'] = $apiHealth;
            
            // 5. Analyze logs for errors
            $logAnalysis = $this->analyzeSystemLogs($lastCheckup);
            $results['errors_found'] = $logAnalysis['errors'];
            $results['warnings_found'] = $logAnalysis['warnings'];
            $results['performance_metrics'] = $logAnalysis['performance'];
            
            // 6. Generate LLM recommendations if enabled
            if ($generateRecommendations && !empty($results['errors_found'])) {
                $recommendations = $this->generateLLMRecommendations($results['errors_found'], $logAnalysis['detailed_logs']);
                $results['recommendations'] = $recommendations;
            }
            
            $duration = round((microtime(true) - $startTime) * 1000);
            $results['completed_at'] = date('Y-m-d H:i:s');
            $results['duration_ms'] = $duration;
            
            // Update checkup record
            $this->updateCheckupRecord($checkupId, $results);
            
            $this->logger->performance('system_checkup', $duration, [
                'errors_found' => count($results['errors_found']),
                'warnings_found' => count($results['warnings_found']),
                'recommendations_generated' => count($results['recommendations'])
            ]);
            
            return $results;
            
        } catch (\Exception $e) {
            $this->logger->error("System checkup failed", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            $this->updateCheckupRecord($checkupId, [
                'status' => 'failed',
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }
    
    /**
     * Check database health
     */
    private function checkDatabaseHealth()
    {
        $health = [
            'status' => 'healthy',
            'issues' => [],
            'metrics' => []
        ];
        
        try {
            // Check connection
            $startTime = microtime(true);
            $this->db->query("SELECT 1");
            $connectionTime = round((microtime(true) - $startTime) * 1000);
            $health['metrics']['connection_time_ms'] = $connectionTime;
            
            if ($connectionTime > 1000) {
                $health['issues'][] = "Slow database connection ({$connectionTime}ms)";
                $health['status'] = 'warning';
            }
            
            // Check table sizes
            $stmt = $this->db->query("
                SELECT table_name, table_rows, 
                       ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size_mb
                FROM information_schema.tables 
                WHERE table_schema = DATABASE()
                ORDER BY (data_length + index_length) DESC
            ");
            $tables = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $health['metrics']['tables'] = $tables;
            
            // Check for large tables that might need optimization
            foreach ($tables as $table) {
                if ($table['size_mb'] > 100) {
                    $health['issues'][] = "Large table: {$table['table_name']} ({$table['size_mb']} MB)";
                    $health['status'] = 'warning';
                }
            }
            
            // Check for failed queries in recent logs
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as error_count 
                FROM system_logs 
                WHERE component = 'database' 
                AND level IN ('error', 'critical') 
                AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ");
            $stmt->execute();
            $errorCount = $stmt->fetchColumn();
            
            if ($errorCount > 0) {
                $health['issues'][] = "{$errorCount} database errors in last 24 hours";
                $health['status'] = 'error';
            }
            
        } catch (\Exception $e) {
            $health['status'] = 'error';
            $health['issues'][] = "Database connection failed: " . $e->getMessage();
        }
        
        return $health;
    }
    
    /**
     * Check file system health
     */
    private function checkFileSystemHealth()
    {
        $health = [
            'status' => 'healthy',
            'issues' => [],
            'metrics' => []
        ];
        
        $directories = [
            'logs' => __DIR__ . '/../../logs',
            'cache' => __DIR__ . '/../../cache',
            'uploads' => __DIR__ . '/../../www/html/uploads'
        ];
        
        foreach ($directories as $name => $path) {
            if (!is_dir($path)) {
                $health['issues'][] = "Missing directory: {$name} ({$path})";
                $health['status'] = 'warning';
                continue;
            }
            
            if (!is_writable($path)) {
                $health['issues'][] = "Directory not writable: {$name}";
                $health['status'] = 'error';
            }
            
            // Check disk space
            $freeBytes = disk_free_space($path);
            $totalBytes = disk_total_space($path);
            $freePercent = round(($freeBytes / $totalBytes) * 100, 2);
            
            $health['metrics'][$name] = [
                'free_space_mb' => round($freeBytes / 1024 / 1024),
                'free_percent' => $freePercent
            ];
            
            if ($freePercent < 10) {
                $health['issues'][] = "Low disk space: {$freePercent}% free";
                $health['status'] = 'error';
            } elseif ($freePercent < 20) {
                $health['issues'][] = "Disk space warning: {$freePercent}% free";
                $health['status'] = 'warning';
            }
        }
        
        return $health;
    }
    
    /**
     * Check scraping system health
     */
    private function checkScrapingSystem($since)
    {
        $health = [
            'status' => 'healthy',
            'issues' => [],
            'metrics' => []
        ];
        
        try {
            // Check recent scraping activity
            $stmt = $this->db->prepare("
                SELECT 
                    COUNT(*) as total_scrapes,
                    SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as successful,
                    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                    AVG(events_found) as avg_events_found,
                    AVG(events_added) as avg_events_added
                FROM scraping_logs 
                WHERE start_time >= ?
            ");
            $stmt->execute([$since ?: date('Y-m-d H:i:s', strtotime('-24 hours'))]);
            $scrapingStats = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $health['metrics'] = $scrapingStats;
            
            if ($scrapingStats['total_scrapes'] == 0) {
                $health['issues'][] = "No scraping activity since last checkup";
                $health['status'] = 'warning';
            } else {
                $successRate = round(($scrapingStats['successful'] / $scrapingStats['total_scrapes']) * 100, 2);
                $health['metrics']['success_rate'] = $successRate;
                
                if ($successRate < 50) {
                    $health['issues'][] = "Low scraping success rate: {$successRate}%";
                    $health['status'] = 'error';
                } elseif ($successRate < 80) {
                    $health['issues'][] = "Moderate scraping success rate: {$successRate}%";
                    $health['status'] = 'warning';
                }
            }
            
            // Check for inactive sources
            $stmt = $this->db->query("
                SELECT COUNT(*) as inactive_sources
                FROM calendar_sources 
                WHERE active = 1 
                AND (last_scraped IS NULL OR last_scraped < DATE_SUB(NOW(), INTERVAL 48 HOUR))
            ");
            $inactiveSources = $stmt->fetchColumn();
            
            if ($inactiveSources > 0) {
                $health['issues'][] = "{$inactiveSources} sources haven't been scraped in 48+ hours";
                $health['status'] = 'warning';
            }
            
        } catch (\Exception $e) {
            $health['status'] = 'error';
            $health['issues'][] = "Failed to check scraping system: " . $e->getMessage();
        }
        
        return $health;
    }
    
    /**
     * Check API health
     */
    private function checkApiHealth()
    {
        $health = [
            'status' => 'healthy',
            'issues' => [],
            'metrics' => []
        ];
        
        $endpoints = [
            '/api/events/',
            '/api/shops/',
            '/admin/calendar/ajax/test-source.php'
        ];
        
        foreach ($endpoints as $endpoint) {
            try {
                $startTime = microtime(true);
                $url = 'http://localhost' . $endpoint;
                
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                curl_setopt($ch, CURLOPT_HEADER, true);
                curl_setopt($ch, CURLOPT_NOBODY, true);
                
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $responseTime = round((microtime(true) - $startTime) * 1000);
                curl_close($ch);
                
                $health['metrics'][$endpoint] = [
                    'status_code' => $httpCode,
                    'response_time_ms' => $responseTime
                ];
                
                if ($httpCode >= 500) {
                    $health['issues'][] = "API endpoint error: {$endpoint} returned {$httpCode}";
                    $health['status'] = 'error';
                } elseif ($httpCode >= 400) {
                    $health['issues'][] = "API endpoint warning: {$endpoint} returned {$httpCode}";
                    $health['status'] = 'warning';
                }
                
                if ($responseTime > 5000) {
                    $health['issues'][] = "Slow API response: {$endpoint} took {$responseTime}ms";
                    $health['status'] = 'warning';
                }
                
            } catch (\Exception $e) {
                $health['status'] = 'error';
                $health['issues'][] = "Failed to check {$endpoint}: " . $e->getMessage();
            }
        }
        
        return $health;
    }
    
    /**
     * Analyze system logs for patterns
     */
    private function analyzeSystemLogs($since)
    {
        $analysis = [
            'errors' => [],
            'warnings' => [],
            'performance' => [],
            'detailed_logs' => []
        ];
        
        $logger = new SystemLogger($this->db);
        
        // Get error summary
        $errorSummary = $logger->getErrorSummary($since);
        foreach ($errorSummary as $error) {
            if ($error['level'] === 'error' || $error['level'] === 'critical') {
                $analysis['errors'][] = [
                    'component' => $error['component'],
                    'level' => $error['level'],
                    'count' => $error['count'],
                    'last_occurrence' => $error['last_occurrence'],
                    'sample_messages' => $error['sample_messages']
                ];
            } elseif ($error['level'] === 'warning') {
                $analysis['warnings'][] = [
                    'component' => $error['component'],
                    'count' => $error['count'],
                    'last_occurrence' => $error['last_occurrence'],
                    'sample_messages' => $error['sample_messages']
                ];
            }
        }
        
        // Get detailed logs for LLM analysis
        if (!empty($analysis['errors'])) {
            $detailedLogs = $logger->getLogs($since, null, null, 500);
            $analysis['detailed_logs'] = array_filter($detailedLogs, function($log) {
                return in_array($log['level'], ['error', 'critical', 'warning']);
            });
        }
        
        // Analyze performance
        $performanceLogs = $logger->getLogs($since, 'performance', null, 100);
        foreach ($performanceLogs as $log) {
            $context = json_decode($log['context'], true);
            if (isset($context['duration_ms']) && $context['duration_ms'] > 5000) {
                $analysis['performance'][] = [
                    'operation' => $context['operation'] ?? 'unknown',
                    'duration_ms' => $context['duration_ms'],
                    'timestamp' => $log['created_at']
                ];
            }
        }
        
        return $analysis;
    }
    
    /**
     * Generate LLM-powered recommendations
     */
    private function generateLLMRecommendations($errors, $detailedLogs)
    {
        if (!$this->llmApiKey) {
            $this->logger->warning("No LLM API key configured, skipping recommendations");
            return [];
        }
        
        $recommendations = [];
        
        try {
            $systemContext = $this->buildSystemContext();
            $errorContext = $this->buildErrorContext($errors, $detailedLogs);
            
            $prompt = $this->buildLLMPrompt($systemContext, $errorContext);
            
            $llmResponse = $this->callLLM($prompt);
            $recommendations = $this->parseLLMResponse($llmResponse);
            
            $this->logger->info("Generated LLM recommendations", [
                'recommendation_count' => count($recommendations),
                'errors_analyzed' => count($errors)
            ]);
            
        } catch (\Exception $e) {
            $this->logger->error("Failed to generate LLM recommendations", [
                'error' => $e->getMessage()
            ]);
        }
        
        return $recommendations;
    }
    
    /**
     * Build system context for LLM
     */
    private function buildSystemContext()
    {
        return [
            'system_type' => 'Event Calendar Management System (YFEvents)',
            'components' => [
                'event_scraping' => 'Scrapes events from multiple sources (iCal, HTML, JSON)',
                'calendar_sources' => 'Manages event source configurations',
                'duplicate_detection' => 'Prevents duplicate events using multi-strategy detection',
                'geocoding' => 'Converts addresses to coordinates using Google Maps API',
                'admin_interface' => 'Web-based administration for event approval and management',
                'api_endpoints' => 'REST API for events and shop data',
                'intelligent_scraper' => 'LLM-powered scraper optimization'
            ],
            'expected_behavior' => [
                'scraping_success_rate' => 'Should be >80%',
                'api_response_time' => 'Should be <2 seconds',
                'duplicate_rate' => 'Should be <5%',
                'database_connection_time' => 'Should be <500ms',
                'error_rate' => 'Should be <1% of operations'
            ]
        ];
    }
    
    /**
     * Build error context for LLM
     */
    private function buildErrorContext($errors, $detailedLogs)
    {
        return [
            'error_summary' => $errors,
            'recent_logs' => array_slice($detailedLogs, 0, 20), // Last 20 error logs
            'analysis_period' => 'Since last system checkup'
        ];
    }
    
    /**
     * Build LLM prompt
     */
    private function buildLLMPrompt($systemContext, $errorContext)
    {
        $prompt = "You are analyzing a PHP-based Event Calendar Management System for errors and issues.\n\n";
        
        $prompt .= "SYSTEM CONTEXT:\n";
        $prompt .= json_encode($systemContext, JSON_PRETTY_PRINT) . "\n\n";
        
        $prompt .= "ERRORS FOUND:\n";
        $prompt .= json_encode($errorContext, JSON_PRETTY_PRINT) . "\n\n";
        
        $prompt .= "Please analyze these errors and generate specific, actionable recommendations.\n";
        $prompt .= "For each recommendation, provide:\n";
        $prompt .= "1. A clear title describing the issue\n";
        $prompt .= "2. A detailed description of the problem\n";
        $prompt .= "3. Step-by-step instructions for a Claude Code agent to fix it\n";
        $prompt .= "4. Priority level (high/medium/low)\n";
        $prompt .= "5. Estimated complexity (simple/moderate/complex)\n\n";
        
        $prompt .= "Focus on:\n";
        $prompt .= "- Performance issues that slow down the system\n";
        $prompt .= "- Scraping failures that prevent event discovery\n";
        $prompt .= "- Database errors that affect data integrity\n";
        $prompt .= "- API failures that break user functionality\n";
        $prompt .= "- Configuration issues that cause system instability\n\n";
        
        $prompt .= "Format your response as a JSON array of recommendation objects.";
        
        return $prompt;
    }
    
    /**
     * Call LLM API
     */
    private function callLLM($prompt)
    {
        $data = [
            'inputs' => $prompt,
            'parameters' => [
                'max_new_tokens' => 2000,
                'temperature' => 0.3,
                'top_p' => 0.9
            ]
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->llmEndpoint);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'x-api-key: ' . $this->llmApiKey
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new \Exception("LLM API call failed with status {$httpCode}: {$response}");
        }
        
        $responseData = json_decode($response, true);
        return $responseData[0]['generated_text'] ?? $response;
    }
    
    /**
     * Parse LLM response into structured recommendations
     */
    private function parseLLMResponse($response)
    {
        // Try to extract JSON from the response
        if (preg_match('/\[.*\]/s', $response, $matches)) {
            $jsonStr = $matches[0];
            $recommendations = json_decode($jsonStr, true);
            
            if (json_last_error() === JSON_ERROR_NONE && is_array($recommendations)) {
                return $recommendations;
            }
        }
        
        // Fallback: parse as plain text
        return [
            [
                'title' => 'LLM Analysis Available',
                'description' => $response,
                'instructions' => 'Review the LLM analysis and implement fixes manually',
                'priority' => 'medium',
                'complexity' => 'moderate'
            ]
        ];
    }
    
    /**
     * Get last checkup time
     */
    private function getLastCheckupTime()
    {
        $this->ensureCheckupTableExists();
        
        $stmt = $this->db->query("
            SELECT MAX(created_at) as last_checkup 
            FROM system_checkups 
            WHERE status = 'completed'
        ");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result['last_checkup'] ?: date('Y-m-d H:i:s', strtotime('-24 hours'));
    }
    
    /**
     * Create checkup record
     */
    private function createCheckupRecord()
    {
        $this->ensureCheckupTableExists();
        
        $stmt = $this->db->prepare("
            INSERT INTO system_checkups (status, created_at) 
            VALUES ('running', NOW())
        ");
        $stmt->execute();
        
        return $this->db->lastInsertId();
    }
    
    /**
     * Update checkup record
     */
    private function updateCheckupRecord($checkupId, $results)
    {
        $stmt = $this->db->prepare("
            UPDATE system_checkups 
            SET status = 'completed', 
                results = ?, 
                completed_at = NOW(),
                errors_found = ?,
                warnings_found = ?,
                recommendations_count = ?
            WHERE id = ?
        ");
        
        $status = isset($results['status']) ? $results['status'] : 'completed';
        $errorCount = isset($results['errors_found']) ? count($results['errors_found']) : 0;
        $warningCount = isset($results['warnings_found']) ? count($results['warnings_found']) : 0;
        $recommendationCount = isset($results['recommendations']) ? count($results['recommendations']) : 0;
        
        $stmt->execute([
            json_encode($results),
            $errorCount,
            $warningCount,
            $recommendationCount,
            $checkupId
        ]);
    }
    
    /**
     * Ensure checkup table exists
     */
    private function ensureCheckupTableExists()
    {
        $sql = "CREATE TABLE IF NOT EXISTS system_checkups (
            id INT AUTO_INCREMENT PRIMARY KEY,
            status ENUM('running', 'completed', 'failed') NOT NULL DEFAULT 'running',
            results JSON NULL,
            errors_found INT DEFAULT 0,
            warnings_found INT DEFAULT 0,
            recommendations_count INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            completed_at TIMESTAMP NULL,
            INDEX idx_status (status),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        
        $this->db->exec($sql);
    }
}