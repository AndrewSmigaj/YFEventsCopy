<?php

declare(strict_types=1);

namespace YFEvents\Presentation\Http\Controllers;

use YFEvents\Domain\Admin\AdminServiceInterface;
use YFEvents\Infrastructure\Container\ContainerInterface;
use YFEvents\Infrastructure\Config\ConfigInterface;
use DateTime;
use Exception;

/**
 * Admin dashboard controller for unified system management
 */
class AdminDashboardController extends BaseController
{
    private AdminServiceInterface $adminService;

    public function __construct(ContainerInterface $container, ConfigInterface $config)
    {
        parent::__construct($container, $config);
        $this->adminService = $container->resolve(AdminServiceInterface::class);
    }

    /**
     * Show admin dashboard page
     */
    public function getDashboard(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        // Render using the new view system
        $this->render('admin.dashboard', [], 'admin');
    }

    /**
     * Get dashboard data (API endpoint)
     */
    public function getDashboardData(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        try {
            $statistics = $this->adminService->getDashboardStatistics();
            $systemHealth = $this->adminService->getSystemHealth();
            $recentActivity = $this->adminService->getRecentActivity(20);
            $moderationQueue = $this->adminService->getModerationQueue();
            $systemAlerts = $this->adminService->getSystemAlerts();

            $this->successResponse([
                'dashboard' => [
                    'statistics' => $statistics,
                    'system_health' => $systemHealth,
                    'recent_activity' => $recentActivity,
                    'moderation_queue' => $moderationQueue,
                    'system_alerts' => $systemAlerts,
                    'last_updated' => (new DateTime())->format('c'),
                ]
            ]);

        } catch (Exception $e) {
            $this->errorResponse('Failed to load dashboard: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get detailed statistics
     */
    public function getStatistics(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        try {
            $input = $this->getInput();
            
            // Default to last 30 days if no date range provided
            $endDate = isset($input['end_date']) ? new DateTime($input['end_date']) : new DateTime();
            $startDate = isset($input['start_date']) ? new DateTime($input['start_date']) : (clone $endDate)->modify('-30 days');

            $dashboardStats = $this->adminService->getDashboardStatistics();
            $contentStats = $this->adminService->getContentStatsByDateRange($startDate, $endDate);
            $performanceMetrics = $this->adminService->getPerformanceMetrics();
            $userActivity = $this->adminService->getUserActivityStats();

            $this->successResponse([
                'statistics' => [
                    'overview' => $dashboardStats,
                    'content_by_date' => $contentStats,
                    'performance' => $performanceMetrics,
                    'user_activity' => $userActivity,
                    'date_range' => [
                        'start' => $startDate->format('Y-m-d'),
                        'end' => $endDate->format('Y-m-d'),
                    ]
                ]
            ]);

        } catch (Exception $e) {
            $this->errorResponse('Failed to load statistics: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get system health status
     */
    public function getSystemHealth(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        try {
            $systemHealth = $this->adminService->getSystemHealth();

            $this->successResponse([
                'system_health' => $systemHealth
            ]);

        } catch (Exception $e) {
            $this->errorResponse('Failed to load system health: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get recent activity across all domains
     */
    public function getRecentActivity(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        try {
            $input = $this->getInput();
            $limit = min(100, max(1, (int) ($input['limit'] ?? 50)));

            $recentActivity = $this->adminService->getRecentActivity($limit);

            $this->successResponse([
                'recent_activity' => $recentActivity,
                'count' => count($recentActivity),
                'limit' => $limit
            ]);

        } catch (Exception $e) {
            $this->errorResponse('Failed to load recent activity: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get performance metrics
     */
    public function getPerformanceMetrics(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        try {
            $performanceMetrics = $this->adminService->getPerformanceMetrics();

            $this->successResponse([
                'performance_metrics' => $performanceMetrics
            ]);

        } catch (Exception $e) {
            $this->errorResponse('Failed to load performance metrics: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get moderation queue
     */
    public function getModerationQueue(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        try {
            $moderationQueue = $this->adminService->getModerationQueue();

            $this->successResponse([
                'moderation_queue' => $moderationQueue,
                'total_items' => array_sum(array_column($moderationQueue, 'count'))
            ]);

        } catch (Exception $e) {
            $this->errorResponse('Failed to load moderation queue: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get user activity statistics
     */
    public function getUserActivity(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        try {
            $userActivity = $this->adminService->getUserActivityStats();

            $this->successResponse([
                'user_activity' => $userActivity
            ]);

        } catch (Exception $e) {
            $this->errorResponse('Failed to load user activity: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get top performing content
     */
    public function getTopContent(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        try {
            $topContent = $this->adminService->getTopPerformingContent();

            $this->successResponse([
                'top_content' => $topContent
            ]);

        } catch (Exception $e) {
            $this->errorResponse('Failed to load top content: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get system alerts
     */
    public function getSystemAlerts(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        try {
            $systemAlerts = $this->adminService->getSystemAlerts();

            $this->successResponse([
                'system_alerts' => $systemAlerts,
                'alert_count' => count($systemAlerts),
                'priority_breakdown' => [
                    'high' => count(array_filter($systemAlerts, fn($alert) => $alert['priority'] === 'high')),
                    'medium' => count(array_filter($systemAlerts, fn($alert) => $alert['priority'] === 'medium')),
                    'low' => count(array_filter($systemAlerts, fn($alert) => $alert['priority'] === 'low')),
                ]
            ]);

        } catch (Exception $e) {
            $this->errorResponse('Failed to load system alerts: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Export system data
     */
    public function exportData(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        try {
            $input = $this->getInput();
            $format = $input['format'] ?? 'json';
            $filters = $input['filters'] ?? [];

            // Validate format
            $allowedFormats = ['json', 'csv', 'xml'];
            if (!in_array($format, $allowedFormats)) {
                $this->errorResponse('Invalid export format. Allowed: ' . implode(', ', $allowedFormats));
                return;
            }

            $exportData = $this->adminService->exportSystemData($format, $filters);

            // Set appropriate headers based on format
            switch ($format) {
                case 'csv':
                    header('Content-Type: text/csv');
                    header('Content-Disposition: attachment; filename="system_data_' . date('Y-m-d') . '.csv"');
                    echo $this->convertToCsv($exportData);
                    break;
                
                case 'xml':
                    header('Content-Type: text/xml');
                    header('Content-Disposition: attachment; filename="system_data_' . date('Y-m-d') . '.xml"');
                    echo $this->convertToXml($exportData);
                    break;
                
                default: // json
                    header('Content-Type: application/json');
                    header('Content-Disposition: attachment; filename="system_data_' . date('Y-m-d') . '.json"');
                    echo json_encode($exportData, JSON_PRETTY_PRINT);
                    break;
            }

        } catch (Exception $e) {
            $this->errorResponse('Failed to export data: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get analytics summary
     */
    public function getAnalytics(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        try {
            $input = $this->getInput();
            $period = $input['period'] ?? '30days';

            // Calculate date range based on period
            $endDate = new DateTime();
            $startDate = match($period) {
                '7days' => (clone $endDate)->modify('-7 days'),
                '30days' => (clone $endDate)->modify('-30 days'),
                '90days' => (clone $endDate)->modify('-90 days'),
                '1year' => (clone $endDate)->modify('-1 year'),
                default => (clone $endDate)->modify('-30 days'),
            };

            $analytics = [
                'period' => $period,
                'date_range' => [
                    'start' => $startDate->format('Y-m-d'),
                    'end' => $endDate->format('Y-m-d'),
                ],
                'overview' => $this->adminService->getDashboardStatistics(),
                'content_trends' => $this->adminService->getContentStatsByDateRange($startDate, $endDate),
                'performance' => $this->adminService->getPerformanceMetrics(),
                'user_engagement' => $this->adminService->getUserActivityStats(),
                'top_content' => $this->adminService->getTopPerformingContent(),
            ];

            $this->successResponse([
                'analytics' => $analytics
            ]);

        } catch (Exception $e) {
            $this->errorResponse('Failed to load analytics: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Convert data to CSV format
     */
    private function convertToCsv(array $data): string
    {
        $output = '';
        
        // Simple CSV conversion for statistics
        if (isset($data['statistics'])) {
            $output .= "Category,Metric,Value\n";
            
            foreach ($data['statistics'] as $category => $metrics) {
                if (is_array($metrics)) {
                    foreach ($metrics as $metric => $value) {
                        $output .= "\"{$category}\",\"{$metric}\",\"{$value}\"\n";
                    }
                }
            }
        }
        
        return $output;
    }

    /**
     * Convert data to XML format
     */
    private function convertToXml(array $data): string
    {
        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<system_export>\n";
        $xml .= $this->arrayToXml($data, 1);
        $xml .= "</system_export>\n";
        
        return $xml;
    }

    /**
     * Helper method to convert array to XML
     */
    private function arrayToXml(array $data, int $indent = 0): string
    {
        $xml = '';
        $spaces = str_repeat('  ', $indent);
        
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $xml .= "{$spaces}<{$key}>\n";
                $xml .= $this->arrayToXml($value, $indent + 1);
                $xml .= "{$spaces}</{$key}>\n";
            } else {
                $xml .= "{$spaces}<{$key}>" . htmlspecialchars((string) $value) . "</{$key}>\n";
            }
        }
        
        return $xml;
    }
}