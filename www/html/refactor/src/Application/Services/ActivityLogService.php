<?php

declare(strict_types=1);

namespace YFEvents\Application\Services;

use YFEvents\Infrastructure\Database\Connection;
use YFEvents\Application\DTOs\PaginatedResult;
use DateTime;

class ActivityLogService
{
    private const LOG_LEVELS = ['info', 'warning', 'error', 'critical'];
    
    public function __construct(
        private readonly Connection $connection
    ) {}

    /**
     * Log user activity
     */
    public function logUserActivity(
        int $userId,
        string $action,
        string $resource = '',
        array $data = [],
        string $level = 'info',
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): void {
        $this->log('user_activity', [
            'user_id' => $userId,
            'action' => $action,
            'resource' => $resource,
            'data' => $data,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent
        ], $level);
    }

    /**
     * Log admin activity
     */
    public function logAdminActivity(
        int $adminId,
        string $action,
        string $resource = '',
        array $data = [],
        string $level = 'info',
        ?string $ipAddress = null
    ): void {
        $this->log('admin_activity', [
            'admin_id' => $adminId,
            'action' => $action,
            'resource' => $resource,
            'data' => $data,
            'ip_address' => $ipAddress
        ], $level);
    }

    /**
     * Log system events
     */
    public function logSystemEvent(
        string $event,
        array $data = [],
        string $level = 'info'
    ): void {
        $this->log('system_event', [
            'event' => $event,
            'data' => $data
        ], $level);
    }

    /**
     * Log security events
     */
    public function logSecurityEvent(
        string $event,
        array $data = [],
        string $level = 'warning',
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): void {
        $this->log('security_event', [
            'event' => $event,
            'data' => $data,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent
        ], $level);
    }

    /**
     * Log API activity
     */
    public function logApiActivity(
        string $endpoint,
        string $method,
        int $statusCode,
        array $data = [],
        ?int $userId = null,
        ?string $apiKey = null,
        ?string $ipAddress = null
    ): void {
        $this->log('api_activity', [
            'endpoint' => $endpoint,
            'method' => $method,
            'status_code' => $statusCode,
            'user_id' => $userId,
            'api_key' => $apiKey,
            'data' => $data,
            'ip_address' => $ipAddress
        ], $statusCode >= 400 ? 'warning' : 'info');
    }

    /**
     * Log data changes
     */
    public function logDataChange(
        string $table,
        int $recordId,
        string $operation,
        array $oldData = [],
        array $newData = [],
        ?int $userId = null
    ): void {
        $this->log('data_change', [
            'table' => $table,
            'record_id' => $recordId,
            'operation' => $operation,
            'old_data' => $oldData,
            'new_data' => $newData,
            'user_id' => $userId,
            'changes' => $this->calculateChanges($oldData, $newData)
        ], 'info');
    }

    /**
     * Get activity logs with pagination
     */
    public function getActivityLogs(
        int $page = 1,
        int $perPage = 50,
        array $filters = []
    ): PaginatedResult {
        $offset = ($page - 1) * $perPage;
        $pdo = $this->connection->getPdo();
        
        $whereClause = $this->buildWhereClause($filters);
        $params = $this->buildParams($filters);
        
        // Get logs
        $sql = "SELECT * FROM activity_logs {$whereClause} ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
        $stmt = $pdo->prepare($sql);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $perPage, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();
        
        $logs = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        // Parse JSON data
        foreach ($logs as &$log) {
            $log['data'] = json_decode($log['data'], true) ?: [];
        }
        
        // Get total count
        $countSql = "SELECT COUNT(*) FROM activity_logs {$whereClause}";
        $countStmt = $pdo->prepare($countSql);
        
        foreach ($params as $key => $value) {
            $countStmt->bindValue($key, $value);
        }
        $countStmt->execute();
        $total = (int)$countStmt->fetchColumn();
        
        return new PaginatedResult(
            items: $logs,
            total: $total,
            page: $page,
            perPage: $perPage
        );
    }

    /**
     * Get user activity
     */
    public function getUserActivity(int $userId, int $limit = 50): array
    {
        $pdo = $this->connection->getPdo();
        
        $stmt = $pdo->prepare(
            "SELECT * FROM activity_logs 
             WHERE JSON_EXTRACT(data, '$.user_id') = :user_id 
                OR JSON_EXTRACT(data, '$.admin_id') = :user_id
             ORDER BY created_at DESC 
             LIMIT :limit"
        );
        
        $stmt->bindValue(':user_id', $userId, \PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        
        $logs = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        foreach ($logs as &$log) {
            $log['data'] = json_decode($log['data'], true) ?: [];
        }
        
        return $logs;
    }

    /**
     * Get security incidents
     */
    public function getSecurityIncidents(int $hours = 24): array
    {
        $pdo = $this->connection->getPdo();
        
        $stmt = $pdo->prepare(
            "SELECT * FROM activity_logs 
             WHERE type = 'security_event' 
               AND level IN ('warning', 'error', 'critical')
               AND created_at >= DATE_SUB(NOW(), INTERVAL :hours HOUR)
             ORDER BY created_at DESC"
        );
        
        $stmt->bindValue(':hours', $hours, \PDO::PARAM_INT);
        $stmt->execute();
        
        $logs = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        foreach ($logs as &$log) {
            $log['data'] = json_decode($log['data'], true) ?: [];
        }
        
        return $logs;
    }

    /**
     * Get API usage statistics
     */
    public function getApiUsageStats(int $days = 7): array
    {
        $pdo = $this->connection->getPdo();
        
        $stmt = $pdo->prepare(
            "SELECT 
                DATE(created_at) as date,
                COUNT(*) as total_requests,
                COUNT(CASE WHEN JSON_EXTRACT(data, '$.status_code') < 400 THEN 1 END) as successful_requests,
                COUNT(CASE WHEN JSON_EXTRACT(data, '$.status_code') >= 400 THEN 1 END) as failed_requests,
                AVG(JSON_EXTRACT(data, '$.response_time')) as avg_response_time
             FROM activity_logs 
             WHERE type = 'api_activity' 
               AND created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
             GROUP BY DATE(created_at)
             ORDER BY date DESC"
        );
        
        $stmt->bindValue(':days', $days, \PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get system health metrics
     */
    public function getSystemHealthMetrics(): array
    {
        $pdo = $this->connection->getPdo();
        
        // Error rate in last hour
        $errorStmt = $pdo->prepare(
            "SELECT 
                COUNT(CASE WHEN level IN ('error', 'critical') THEN 1 END) as errors,
                COUNT(*) as total
             FROM activity_logs 
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)"
        );
        $errorStmt->execute();
        $errorStats = $errorStmt->fetch(\PDO::FETCH_ASSOC);
        
        // Failed login attempts
        $loginStmt = $pdo->prepare(
            "SELECT COUNT(*) as failed_logins
             FROM activity_logs 
             WHERE type = 'security_event'
               AND JSON_EXTRACT(data, '$.event') = 'login_failed'
               AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)"
        );
        $loginStmt->execute();
        $failedLogins = $loginStmt->fetchColumn();
        
        // API error rate
        $apiStmt = $pdo->prepare(
            "SELECT 
                COUNT(CASE WHEN JSON_EXTRACT(data, '$.status_code') >= 400 THEN 1 END) as api_errors,
                COUNT(*) as total_api_requests
             FROM activity_logs 
             WHERE type = 'api_activity'
               AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)"
        );
        $apiStmt->execute();
        $apiStats = $apiStmt->fetch(\PDO::FETCH_ASSOC);
        
        return [
            'error_rate' => $errorStats['total'] > 0 ? round(($errorStats['errors'] / $errorStats['total']) * 100, 2) : 0,
            'failed_logins_last_hour' => (int)$failedLogins,
            'api_error_rate' => $apiStats['total_api_requests'] > 0 ? round(($apiStats['api_errors'] / $apiStats['total_api_requests']) * 100, 2) : 0,
            'total_logs_last_hour' => (int)$errorStats['total']
        ];
    }

    /**
     * Export activity logs
     */
    public function exportLogs(array $filters = [], string $format = 'csv'): array
    {
        $pdo = $this->connection->getPdo();
        
        $whereClause = $this->buildWhereClause($filters);
        $params = $this->buildParams($filters);
        
        $sql = "SELECT * FROM activity_logs {$whereClause} ORDER BY created_at DESC";
        $stmt = $pdo->prepare($sql);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        
        $logs = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        switch ($format) {
            case 'csv':
                return $this->exportToCsv($logs);
            case 'json':
                return $this->exportToJson($logs);
            default:
                throw new \InvalidArgumentException("Unsupported export format: $format");
        }
    }

    /**
     * Clean old logs
     */
    public function cleanOldLogs(int $daysToKeep = 90): int
    {
        $pdo = $this->connection->getPdo();
        
        $stmt = $pdo->prepare(
            "DELETE FROM activity_logs 
             WHERE created_at < DATE_SUB(NOW(), INTERVAL :days DAY)"
        );
        
        $stmt->bindValue(':days', $daysToKeep, \PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->rowCount();
    }

    /**
     * Analyze suspicious activity
     */
    public function analyzeSuspiciousActivity(): array
    {
        $pdo = $this->connection->getPdo();
        
        $suspicious = [];
        
        // Multiple failed logins from same IP
        $stmt = $pdo->prepare(
            "SELECT JSON_EXTRACT(data, '$.ip_address') as ip,
                    COUNT(*) as attempts
             FROM activity_logs 
             WHERE type = 'security_event'
               AND JSON_EXTRACT(data, '$.event') = 'login_failed'
               AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
             GROUP BY JSON_EXTRACT(data, '$.ip_address')
             HAVING attempts >= 5"
        );
        $stmt->execute();
        $suspicious['multiple_failed_logins'] = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        // Unusual API usage patterns
        $stmt = $pdo->prepare(
            "SELECT JSON_EXTRACT(data, '$.ip_address') as ip,
                    COUNT(*) as requests
             FROM activity_logs 
             WHERE type = 'api_activity'
               AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
             GROUP BY JSON_EXTRACT(data, '$.ip_address')
             HAVING requests > 1000"
        );
        $stmt->execute();
        $suspicious['high_api_usage'] = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        // Admin actions outside business hours
        $stmt = $pdo->prepare(
            "SELECT * FROM activity_logs 
             WHERE type = 'admin_activity'
               AND (HOUR(created_at) < 6 OR HOUR(created_at) > 22)
               AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)"
        );
        $stmt->execute();
        $suspicious['after_hours_admin'] = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        return $suspicious;
    }

    private function log(string $type, array $data, string $level): void
    {
        if (!in_array($level, self::LOG_LEVELS)) {
            $level = 'info';
        }
        
        $pdo = $this->connection->getPdo();
        
        $stmt = $pdo->prepare(
            "INSERT INTO activity_logs (type, level, data, created_at) 
             VALUES (:type, :level, :data, NOW())"
        );
        
        $stmt->execute([
            'type' => $type,
            'level' => $level,
            'data' => json_encode($data)
        ]);
    }

    private function buildWhereClause(array $filters): string
    {
        $conditions = [];
        
        if (!empty($filters['type'])) {
            $conditions[] = 'type = :type';
        }
        
        if (!empty($filters['level'])) {
            $conditions[] = 'level = :level';
        }
        
        if (!empty($filters['user_id'])) {
            $conditions[] = '(JSON_EXTRACT(data, "$.user_id") = :user_id OR JSON_EXTRACT(data, "$.admin_id") = :user_id)';
        }
        
        if (!empty($filters['ip_address'])) {
            $conditions[] = 'JSON_EXTRACT(data, "$.ip_address") = :ip_address';
        }
        
        if (!empty($filters['date_from'])) {
            $conditions[] = 'created_at >= :date_from';
        }
        
        if (!empty($filters['date_to'])) {
            $conditions[] = 'created_at <= :date_to';
        }
        
        return empty($conditions) ? '' : 'WHERE ' . implode(' AND ', $conditions);
    }

    private function buildParams(array $filters): array
    {
        $params = [];
        
        foreach (['type', 'level', 'user_id', 'ip_address', 'date_from', 'date_to'] as $key) {
            if (!empty($filters[$key])) {
                $params[":$key"] = $filters[$key];
            }
        }
        
        return $params;
    }

    private function calculateChanges(array $oldData, array $newData): array
    {
        $changes = [];
        
        foreach ($newData as $key => $newValue) {
            $oldValue = $oldData[$key] ?? null;
            
            if ($oldValue !== $newValue) {
                $changes[$key] = [
                    'old' => $oldValue,
                    'new' => $newValue
                ];
            }
        }
        
        return $changes;
    }

    private function exportToCsv(array $logs): array
    {
        $csv = "ID,Type,Level,Data,Created At\n";
        
        foreach ($logs as $log) {
            $csv .= sprintf(
                "%d,%s,%s,\"%s\",%s\n",
                $log['id'],
                $log['type'],
                $log['level'],
                str_replace('"', '""', $log['data']),
                $log['created_at']
            );
        }
        
        return [
            'content' => $csv,
            'filename' => 'activity_logs_' . date('Y-m-d') . '.csv',
            'mime_type' => 'text/csv'
        ];
    }

    private function exportToJson(array $logs): array
    {
        foreach ($logs as &$log) {
            $log['data'] = json_decode($log['data'], true) ?: [];
        }
        
        return [
            'content' => json_encode($logs, JSON_PRETTY_PRINT),
            'filename' => 'activity_logs_' . date('Y-m-d') . '.json',
            'mime_type' => 'application/json'
        ];
    }
}