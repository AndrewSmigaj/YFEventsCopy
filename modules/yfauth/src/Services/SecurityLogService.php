<?php

namespace YFEvents\Modules\YFAuth\Services;

use PDO;
use Exception;

/**
 * Security Logging Service
 * Implements comprehensive security monitoring and audit trails
 */
class SecurityLogService
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Log login attempt
     */
    public function logLoginAttempt(
        ?int $userId,
        string $username,
        string $ipAddress,
        string $userAgent,
        string $result,
        ?string $failureReason = null,
        ?string $sessionId = null
    ): void {
        try {
            $sql = "
                INSERT INTO auth_login_logs 
                (user_id, username, ip_address, user_agent, login_result, failure_reason, session_id)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $userId,
                $username,
                $ipAddress,
                substr($userAgent, 0, 1000), // Truncate to fit column
                $result,
                $failureReason,
                $sessionId
            ]);

            // Trigger security analysis after logging
            $this->analyzeLoginPatterns($ipAddress, $userId);

        } catch (Exception $e) {
            error_log("Failed to log login attempt: " . $e->getMessage());
        }
    }

    /**
     * Log security event
     */
    public function logSecurityEvent(
        ?int $userId,
        string $eventType,
        string $severity,
        string $description,
        array $metadata = [],
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): void {
        try {
            $sql = "
                INSERT INTO auth_security_events 
                (user_id, event_type, severity, description, metadata, ip_address, user_agent)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $userId,
                $eventType,
                $severity,
                $description,
                json_encode($metadata),
                $ipAddress ?: ($_SERVER['REMOTE_ADDR'] ?? null),
                $userAgent ? substr($userAgent, 0, 1000) : substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 1000)
            ]);

            // Send alert for high/critical severity events
            if (in_array($severity, ['high', 'critical'])) {
                $this->sendSecurityAlert($eventType, $severity, $description, $metadata);
            }

        } catch (Exception $e) {
            error_log("Failed to log security event: " . $e->getMessage());
        }
    }

    /**
     * Analyze login patterns for suspicious activity
     */
    private function analyzeLoginPatterns(string $ipAddress, ?int $userId): void
    {
        try {
            // Check for excessive failed attempts from IP
            $sql = "
                SELECT COUNT(*) as failed_count
                FROM auth_login_logs
                WHERE ip_address = ? 
                AND login_result != 'success'
                AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$ipAddress]);
            $failedCount = $stmt->fetchColumn();

            if ($failedCount >= 10) {
                $this->logSecurityEvent(
                    null,
                    'suspicious_login',
                    'high',
                    "Excessive failed login attempts from IP: {$ipAddress}",
                    [
                        'ip_address' => $ipAddress,
                        'failed_attempts' => $failedCount,
                        'time_window' => '1 hour'
                    ]
                );
            }

            // Check for brute force patterns (many different usernames from same IP)
            if ($userId === null) { // Failed login
                $sql = "
                    SELECT COUNT(DISTINCT username) as unique_usernames
                    FROM auth_login_logs
                    WHERE ip_address = ? 
                    AND login_result != 'success'
                    AND created_at > DATE_SUB(NOW(), INTERVAL 30 MINUTE)
                ";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$ipAddress]);
                $uniqueUsernames = $stmt->fetchColumn();

                if ($uniqueUsernames >= 5) {
                    $this->logSecurityEvent(
                        null,
                        'suspicious_login',
                        'critical',
                        "Potential brute force attack from IP: {$ipAddress}",
                        [
                            'ip_address' => $ipAddress,
                            'unique_usernames_attempted' => $uniqueUsernames,
                            'time_window' => '30 minutes'
                        ]
                    );
                }
            }

            // Check for impossible travel (same user from different locations too quickly)
            if ($userId) {
                $sql = "
                    SELECT ip_address, created_at
                    FROM auth_login_logs
                    WHERE user_id = ? 
                    AND login_result = 'success'
                    AND created_at > DATE_SUB(NOW(), INTERVAL 2 HOUR)
                    ORDER BY created_at DESC
                    LIMIT 2
                ";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$userId]);
                $recentLogins = $stmt->fetchAll(PDO::FETCH_ASSOC);

                if (count($recentLogins) >= 2) {
                    $currentIp = $recentLogins[0]['ip_address'];
                    $previousIp = $recentLogins[1]['ip_address'];

                    // Simple check for different IP addresses (could be enhanced with geolocation)
                    if ($currentIp !== $previousIp && $this->areIpAddressesDistant($currentIp, $previousIp)) {
                        $timeDiff = strtotime($recentLogins[0]['created_at']) - strtotime($recentLogins[1]['created_at']);
                        
                        if ($timeDiff < 1800) { // Less than 30 minutes
                            $this->logSecurityEvent(
                                $userId,
                                'suspicious_login',
                                'high',
                                "Potential impossible travel detected for user ID: {$userId}",
                                [
                                    'current_ip' => $currentIp,
                                    'previous_ip' => $previousIp,
                                    'time_difference_seconds' => $timeDiff
                                ]
                            );
                        }
                    }
                }
            }

        } catch (Exception $e) {
            error_log("Error analyzing login patterns: " . $e->getMessage());
        }
    }

    /**
     * Simple check for geographically distant IP addresses
     * This is a basic implementation - in production, use a proper geolocation service
     */
    private function areIpAddressesDistant(string $ip1, string $ip2): bool
    {
        // Basic check - different first two octets suggests different regions
        $parts1 = explode('.', $ip1);
        $parts2 = explode('.', $ip2);

        if (count($parts1) !== 4 || count($parts2) !== 4) {
            return false; // Invalid IP format
        }

        return $parts1[0] !== $parts2[0] || $parts1[1] !== $parts2[1];
    }

    /**
     * Send security alert to administrators
     */
    private function sendSecurityAlert(string $eventType, string $severity, string $description, array $metadata): void
    {
        try {
            // In a real implementation, this would send emails, Slack notifications, etc.
            $alertData = [
                'timestamp' => date('Y-m-d H:i:s'),
                'event_type' => $eventType,
                'severity' => $severity,
                'description' => $description,
                'metadata' => $metadata,
                'server' => $_SERVER['HTTP_HOST'] ?? 'unknown'
            ];

            // Log to system log for now (replace with actual notification system)
            error_log("SECURITY ALERT [{$severity}]: {$description} - " . json_encode($alertData));

            // You could integrate with services like:
            // - Email notifications
            // - Slack webhooks
            // - PagerDuty
            // - SMS alerts
            // - Discord webhooks

        } catch (Exception $e) {
            error_log("Failed to send security alert: " . $e->getMessage());
        }
    }

    /**
     * Get login history for user
     */
    public function getLoginHistory(int $userId, int $limit = 50): array
    {
        $sql = "
            SELECT 
                ip_address,
                user_agent,
                login_result,
                failure_reason,
                created_at
            FROM auth_login_logs
            WHERE user_id = ?
            ORDER BY created_at DESC
            LIMIT ?
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get security events for user
     */
    public function getSecurityEvents(int $userId, int $limit = 50): array
    {
        $sql = "
            SELECT 
                event_type,
                severity,
                description,
                metadata,
                ip_address,
                resolved,
                created_at
            FROM auth_security_events
            WHERE user_id = ?
            ORDER BY created_at DESC
            LIMIT ?
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get system-wide security summary
     */
    public function getSecuritySummary(int $hours = 24): array
    {
        try {
            $summary = [];

            // Failed login attempts
            $sql = "
                SELECT COUNT(*) as count
                FROM auth_login_logs
                WHERE login_result != 'success'
                AND created_at > DATE_SUB(NOW(), INTERVAL ? HOUR)
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$hours]);
            $summary['failed_logins'] = $stmt->fetchColumn();

            // Successful logins
            $sql = "
                SELECT COUNT(*) as count
                FROM auth_login_logs
                WHERE login_result = 'success'
                AND created_at > DATE_SUB(NOW(), INTERVAL ? HOUR)
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$hours]);
            $summary['successful_logins'] = $stmt->fetchColumn();

            // Security events by severity
            $sql = "
                SELECT severity, COUNT(*) as count
                FROM auth_security_events
                WHERE created_at > DATE_SUB(NOW(), INTERVAL ? HOUR)
                GROUP BY severity
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$hours]);
            $severityCounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $summary['security_events'] = [];
            foreach ($severityCounts as $row) {
                $summary['security_events'][$row['severity']] = $row['count'];
            }

            // Top IP addresses with failed attempts
            $sql = "
                SELECT ip_address, COUNT(*) as failed_count
                FROM auth_login_logs
                WHERE login_result != 'success'
                AND created_at > DATE_SUB(NOW(), INTERVAL ? HOUR)
                GROUP BY ip_address
                ORDER BY failed_count DESC
                LIMIT 10
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$hours]);
            $summary['top_failed_ips'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Recent security events (high/critical only)
            $sql = "
                SELECT event_type, severity, description, created_at
                FROM auth_security_events
                WHERE severity IN ('high', 'critical')
                AND created_at > DATE_SUB(NOW(), INTERVAL ? HOUR)
                ORDER BY created_at DESC
                LIMIT 20
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$hours]);
            $summary['recent_critical_events'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $summary;

        } catch (Exception $e) {
            error_log("Error generating security summary: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Clean up old logs (for GDPR compliance and storage management)
     */
    public function cleanupOldLogs(int $daysToKeep = 90): int
    {
        try {
            $this->db->beginTransaction();

            // Clean up login logs
            $sql = "DELETE FROM auth_login_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$daysToKeep]);
            $deletedLogs = $stmt->rowCount();

            // Clean up resolved security events (keep unresolved ones longer)
            $sql = "
                DELETE FROM auth_security_events 
                WHERE resolved = 1 
                AND created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$daysToKeep]);
            $deletedEvents = $stmt->rowCount();

            $this->db->commit();

            $this->logSecurityEvent(
                null,
                'log_cleanup',
                'low',
                "Cleaned up old security logs",
                [
                    'deleted_login_logs' => $deletedLogs,
                    'deleted_security_events' => $deletedEvents,
                    'retention_days' => $daysToKeep
                ]
            );

            return $deletedLogs + $deletedEvents;

        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Error cleaning up logs: " . $e->getMessage());
            return 0;
        }
    }
}