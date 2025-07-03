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

        $basePath = dirname($_SERVER['SCRIPT_NAME']);
        if ($basePath === '/') {
            $basePath = '';
        }

        header('Content-Type: text/html; charset=utf-8');
        echo $this->renderDashboardPage($basePath);
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

    private function renderDashboardPage(string $basePath): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $username = $_SESSION['admin_username'] ?? 'admin';

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>YFEvents V2 - Admin Dashboard</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: #f8f9fa;
            min-height: 100vh;
        }
        
        .header {
            background: linear-gradient(135deg, #dc3545 0%, #6f42c1 100%);
            color: white;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header h1 {
            font-size: 1.8rem;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .logout-btn {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
        }
        
        .logout-btn:hover {
            background: rgba(255, 255, 255, 0.3);
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 30px 20px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            text-align: center;
        }
        
        .stat-icon {
            font-size: 2.5rem;
            margin-bottom: 10px;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #343a40;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .action-section {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .action-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #343a40;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .action-list {
            list-style: none;
        }
        
        .action-list li {
            margin-bottom: 10px;
        }
        
        .action-link {
            color: #007bff;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 0;
        }
        
        .action-link:hover {
            color: #0056b3;
            text-decoration: underline;
        }
        
        .recent-activity {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .activity-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #343a40;
            margin-bottom: 15px;
        }
        
        .activity-item {
            padding: 10px 0;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-text {
            color: #495057;
        }
        
        .activity-time {
            color: #6c757d;
            font-size: 0.85rem;
        }
        
        .loading {
            text-align: center;
            color: #6c757d;
            font-style: italic;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🛠️ Admin Dashboard</h1>
        <div class="user-info">
            <span>Welcome, {$username}</span>
            <button class="logout-btn" onclick="logout()">Logout</button>
        </div>
    </div>
    
    <div class="container">
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">📅</div>
                <div class="stat-number" id="total-events">-</div>
                <div class="stat-label">Total Events</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">⏳</div>
                <div class="stat-number" id="pending-events">-</div>
                <div class="stat-label">Pending Approval</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">🏪</div>
                <div class="stat-number" id="total-shops">-</div>
                <div class="stat-label">Local Shops</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">⭐</div>
                <div class="stat-number" id="featured-events">-</div>
                <div class="stat-label">Featured Events</div>
            </div>
        </div>
        
        <div class="actions-grid">
            <div class="action-section">
                <div class="action-title">📅 Event Management</div>
                <ul class="action-list">
                    <li><a href="/admin-legacy/events.php" class="action-link">📋 View All Events</a></li>
                    <li><a href="/admin-legacy/events.php#pending" class="action-link">⏳ Review Pending Events</a></li>
                    <li><a href="/admin-legacy/events.php#featured" class="action-link">⭐ Manage Featured Events</a></li>
                    <li><a href="/admin-legacy/events/statistics" class="action-link">📊 Event Statistics</a></li>
                </ul>
            </div>
            
            <div class="action-section">
                <div class="action-title">🏪 Shop Management</div>
                <ul class="action-list">
                    <li><a href="/admin-legacy/shops.php" class="action-link">🏪 View All Shops</a></li>
                    <li><a href="/admin-legacy/shops.php#pending" class="action-link">⏳ Review Pending Shops</a></li>
                    <li><a href="/admin-legacy/shops.php#verify" class="action-link">✅ Verify Shop Information</a></li>
                    <li><a href="/admin-legacy/shops/statistics" class="action-link">📊 Shop Statistics</a></li>
                </ul>
            </div>
            
            <div class="action-section">
                <div class="action-title">📧 Email Management</div>
                <ul class="action-list">
                    <li><a href="/admin-legacy/email-events.php" class="action-link">📧 Email Events</a></li>
                    <li><a href="/admin-legacy/email-config.php" class="action-link">⚙️ Email Configuration</a></li>
                    <li><a href="/admin-legacy/email-upload.php" class="action-link">📤 Upload Emails</a></li>
                    <li><a href="/admin-legacy/email-config.php#test" class="action-link">🔌 Test Connection</a></li>
                </ul>
            </div>
            
            <div class="action-section">
                <div class="action-title">🎨 Theme Management</div>
                <ul class="action-list">
                    <li><a href="/admin-legacy/theme.php" class="action-link">🎨 Theme Editor</a></li>
                    <li><a href="/admin-legacy/theme.php#seo" class="action-link">🔍 SEO Settings</a></li>
                    <li><a href="/admin-legacy/theme.php#social" class="action-link">📱 Social Media</a></li>
                    <li><a href="/admin-legacy/theme.php#presets" class="action-link">🎯 Theme Presets</a></li>
                </ul>
            </div>
            
            <div class="action-section">
                <div class="action-title">🤖 Event Scrapers</div>
                <ul class="action-list">
                    <li><a href="/admin-legacy/scrapers.php" class="action-link">🔧 Basic Scrapers</a></li>
                    <li><a href="/admin-legacy/browser-scrapers.php" class="action-link">🤖 Browser Automation</a></li>
                    <li><a href="/admin-legacy/scrapers.php#intelligent" class="action-link">🧠 AI Scraper</a></li>
                    <li><a href="/admin-legacy/scrapers.php#status" class="action-link">📊 Scraper Status</a></li>
                </ul>
            </div>
            
            <div class="action-section">
                <div class="action-title">🏛️ Estate Sales (YFClaim)</div>
                <ul class="action-list">
                    <li><a href="/admin-legacy/sellers" class="action-link">👥 Manage Sellers</a></li>
                    <li><a href="/admin-legacy/sales" class="action-link">📋 All Sales</a></li>
                    <li><a href="/claims" class="action-link">🔍 View Public Sales</a></li>
                    <li><a href="/modules/yfclaim/www/admin/" class="action-link">🛠️ YFClaim Admin</a></li>
                </ul>
            </div>
            
            <div class="action-section">
                <div class="action-title">🛍️ YF Classifieds</div>
                <ul class="action-list">
                    <li><a href="/modules/yfclassifieds/www/admin/simple-index.php" class="action-link">🛍️ Classifieds Dashboard</a></li>
                    <li><a href="/modules/yfclassifieds/www/admin/upload.php" class="action-link">📸 Upload Photos</a></li>
                    <li><a href="/modules/yfclassifieds/www/admin/create.php" class="action-link">➕ Add New Item</a></li>
                    <li><a href="/classifieds" class="action-link">👀 View Public Gallery</a></li>
                </ul>
            </div>
            
            <div class="action-section">
                <div class="action-title">🔧 System Management</div>
                <ul class="action-list">
                    <li><a href="/admin-legacy/modules.php" class="action-link">🧩 Module Management</a></li>
                    <li><a href="/admin-legacy/dashboard/health" class="action-link">💚 System Health</a></li>
                    <li><a href="/admin-legacy/dashboard/analytics" class="action-link">📈 Analytics</a></li>
                    <li><a href="/admin-legacy/dashboard/performance" class="action-link">⚡ Performance</a></li>
                    <li><a href="/admin-legacy/dashboard/activity" class="action-link">📋 Activity Log</a></li>
                </ul>
            </div>
            
            <div class="action-section">
                <div class="action-title">🔍 Quick Actions</div>
                <ul class="action-list">
                    <li><a href="/" class="action-link">🏠 View Public Site</a></li>
                    <li><a href="/events" class="action-link">📅 Public Events</a></li>
                    <li><a href="/shops" class="action-link">🏪 Public Shops</a></li>
                    <li><a href="/admin-legacy/settings.php" class="action-link">⚙️ System Settings</a></li>
                </ul>
            </div>
        </div>
        
        <div class="recent-activity">
            <div class="activity-title">📋 Recent Activity</div>
            <div id="activity-content">
                <div class="loading">Loading recent activity...</div>
            </div>
        </div>
    </div>

    <script>
        async function loadDashboardStats() {
            try {
                // Load event statistics
                const eventStatsResponse = await fetch('/admin/events/statistics');
                if (eventStatsResponse.ok) {
                    const eventStats = await eventStatsResponse.json();
                    if (eventStats.success) {
                        document.getElementById('total-events').textContent = eventStats.data.statistics.total || 0;
                        document.getElementById('pending-events').textContent = eventStats.data.statistics.pending || 0;
                        document.getElementById('featured-events').textContent = eventStats.data.statistics.featured || 0;
                    }
                }
                
                // Load shop statistics
                const shopStatsResponse = await fetch('/admin/shops/statistics');
                if (shopStatsResponse.ok) {
                    const shopStats = await shopStatsResponse.json();
                    if (shopStats.success) {
                        document.getElementById('total-shops').textContent = shopStats.data.statistics.total || 0;
                    }
                }
                
                // Load recent activity
                const activityResponse = await fetch('/admin/dashboard/activity');
                if (activityResponse.ok) {
                    const activity = await activityResponse.json();
                    if (activity.success && activity.data.activities) {
                        const activityContent = document.getElementById('activity-content');
                        if (activity.data.activities.length > 0) {
                            activityContent.innerHTML = activity.data.activities.map(item => `
                                <div class="activity-item">
                                    <div class="activity-text">\${item.description}</div>
                                    <div class="activity-time">\${item.time}</div>
                                </div>
                            `).join('');
                        } else {
                            activityContent.innerHTML = '<div class="loading">No recent activity</div>';
                        }
                    }
                }
                
            } catch (error) {
                console.error('Error loading dashboard stats:', error);
            }
        }
        
        async function logout() {
            try {
                const response = await fetch('/admin/logout', { method: 'POST' });
                const data = await response.json();
                
                if (data.success) {
                    window.location.href = '/admin/login';
                }
            } catch (error) {
                console.error('Logout error:', error);
                window.location.href = '/admin/login';
            }
        }
        
        
        // Load dashboard data on page load
        document.addEventListener('DOMContentLoaded', loadDashboardStats);
    </script>
</body>
</html>
HTML;
    }

    // ===== SELLER MANAGEMENT METHODS (YFClaim) =====

    /**
     * Get all sellers
     */
    public function getSellers(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        try {
            $input = $this->getInput();
            $pagination = $this->getPaginationParams($input);
            
            // Get database connection
            $connection = $this->container->resolve(\YFEvents\Infrastructure\Database\ConnectionInterface::class);
            $pdo = $connection->getConnection();
            
            // Build query
            $query = "SELECT * FROM yfc_sellers";
            $params = [];
            $conditions = [];
            
            if (isset($input['status'])) {
                $conditions[] = "status = :status";
                $params[':status'] = $input['status'];
            }
            
            if (!empty($conditions)) {
                $query .= " WHERE " . implode(" AND ", $conditions);
            }
            
            $query .= " ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
            
            $stmt = $pdo->prepare($query);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->bindValue(':limit', $pagination['limit'], \PDO::PARAM_INT);
            $stmt->bindValue(':offset', $pagination['offset'], \PDO::PARAM_INT);
            $stmt->execute();
            
            $sellers = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            // Get total count
            $countQuery = "SELECT COUNT(*) FROM yfc_sellers";
            if (!empty($conditions)) {
                $countQuery .= " WHERE " . implode(" AND ", $conditions);
            }
            $stmt = $pdo->prepare($countQuery);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->execute();
            $total = $stmt->fetchColumn();
            
            $this->successResponse([
                'sellers' => $sellers,
                'pagination' => [
                    'page' => $pagination['page'],
                    'limit' => $pagination['limit'],
                    'total' => (int) $total,
                    'pages' => ceil($total / $pagination['limit'])
                ]
            ]);
            
        } catch (Exception $e) {
            $this->errorResponse('Failed to load sellers: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get seller details
     */
    public function getSellerDetails(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        try {
            $input = $this->getInput();
            $sellerId = (int) ($input['id'] ?? 0);
            
            if (!$sellerId) {
                $this->errorResponse('Seller ID is required');
                return;
            }
            
            $connection = $this->container->resolve(\YFEvents\Infrastructure\Database\ConnectionInterface::class);
            $pdo = $connection->getConnection();
            
            // Get seller details
            $stmt = $pdo->prepare("SELECT * FROM yfc_sellers WHERE id = :id");
            $stmt->execute([':id' => $sellerId]);
            $seller = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$seller) {
                $this->errorResponse('Seller not found', 404);
                return;
            }
            
            // Get seller's sales count
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM yfc_sales WHERE seller_id = :id");
            $stmt->execute([':id' => $sellerId]);
            $salesCount = $stmt->fetchColumn();
            
            // Get seller's total items
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM yfc_items i
                JOIN yfc_sales s ON i.sale_id = s.id
                WHERE s.seller_id = :id
            ");
            $stmt->execute([':id' => $sellerId]);
            $itemsCount = $stmt->fetchColumn();
            
            $this->successResponse([
                'seller' => $seller,
                'stats' => [
                    'sales_count' => (int) $salesCount,
                    'items_count' => (int) $itemsCount
                ]
            ]);
            
        } catch (Exception $e) {
            $this->errorResponse('Failed to load seller details: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get seller's sales
     */
    public function getSellerSales(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        try {
            $input = $this->getInput();
            $sellerId = (int) ($input['id'] ?? 0);
            
            if (!$sellerId) {
                $this->errorResponse('Seller ID is required');
                return;
            }
            
            $connection = $this->container->resolve(\YFEvents\Infrastructure\Database\ConnectionInterface::class);
            $pdo = $connection->getConnection();
            
            $stmt = $pdo->prepare("
                SELECT s.*, 
                       COUNT(DISTINCT i.id) as item_count,
                       COUNT(DISTINCT CASE WHEN i.status = 'claimed' THEN i.id END) as claimed_count
                FROM yfc_sales s
                LEFT JOIN yfc_items i ON s.id = i.sale_id
                WHERE s.seller_id = :id
                GROUP BY s.id
                ORDER BY s.start_date DESC
            ");
            $stmt->execute([':id' => $sellerId]);
            $sales = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            $this->successResponse([
                'sales' => $sales,
                'count' => count($sales)
            ]);
            
        } catch (Exception $e) {
            $this->errorResponse('Failed to load seller sales: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Approve seller
     */
    public function approveSeller(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        try {
            $input = $this->getInput();
            $sellerId = (int) ($input['id'] ?? 0);
            
            if (!$sellerId) {
                $this->errorResponse('Seller ID is required');
                return;
            }
            
            $connection = $this->container->resolve(\YFEvents\Infrastructure\Database\ConnectionInterface::class);
            $pdo = $connection->getConnection();
            
            $stmt = $pdo->prepare("UPDATE yfc_sellers SET status = 'active' WHERE id = :id");
            $stmt->execute([':id' => $sellerId]);
            
            if ($stmt->rowCount() === 0) {
                $this->errorResponse('Seller not found', 404);
                return;
            }
            
            $this->successResponse([], 'Seller approved successfully');
            
        } catch (Exception $e) {
            $this->errorResponse('Failed to approve seller: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Suspend seller
     */
    public function suspendSeller(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        try {
            $input = $this->getInput();
            $sellerId = (int) ($input['id'] ?? 0);
            $reason = $input['reason'] ?? '';
            
            if (!$sellerId) {
                $this->errorResponse('Seller ID is required');
                return;
            }
            
            $connection = $this->container->resolve(\YFEvents\Infrastructure\Database\ConnectionInterface::class);
            $pdo = $connection->getConnection();
            
            $stmt = $pdo->prepare("UPDATE yfc_sellers SET status = 'suspended' WHERE id = :id");
            $stmt->execute([':id' => $sellerId]);
            
            if ($stmt->rowCount() === 0) {
                $this->errorResponse('Seller not found', 404);
                return;
            }
            
            // TODO: Add suspension reason to a log table if needed
            
            $this->successResponse([], 'Seller suspended successfully');
            
        } catch (Exception $e) {
            $this->errorResponse('Failed to suspend seller: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get all sales (admin view)
     */
    public function getAllSales(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        try {
            $input = $this->getInput();
            $pagination = $this->getPaginationParams($input);
            
            $connection = $this->container->resolve(\YFEvents\Infrastructure\Database\ConnectionInterface::class);
            $pdo = $connection->getConnection();
            
            $query = "
                SELECT s.*, 
                       sl.company_name as seller_name,
                       COUNT(DISTINCT i.id) as item_count
                FROM yfc_sales s
                LEFT JOIN yfc_sellers sl ON s.seller_id = sl.id
                LEFT JOIN yfc_items i ON s.id = i.sale_id
            ";
            
            $conditions = [];
            $params = [];
            
            if (isset($input['status'])) {
                $conditions[] = "s.status = :status";
                $params[':status'] = $input['status'];
            }
            
            if (isset($input['featured'])) {
                $conditions[] = "s.featured = :featured";
                $params[':featured'] = $input['featured'] ? 1 : 0;
            }
            
            if (!empty($conditions)) {
                $query .= " WHERE " . implode(" AND ", $conditions);
            }
            
            $query .= " GROUP BY s.id ORDER BY s.start_date DESC LIMIT :limit OFFSET :offset";
            
            $stmt = $pdo->prepare($query);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->bindValue(':limit', $pagination['limit'], \PDO::PARAM_INT);
            $stmt->bindValue(':offset', $pagination['offset'], \PDO::PARAM_INT);
            $stmt->execute();
            
            $sales = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            $this->successResponse([
                'sales' => $sales,
                'count' => count($sales)
            ]);
            
        } catch (Exception $e) {
            $this->errorResponse('Failed to load sales: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get sale details
     */
    public function getSaleDetails(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        try {
            $input = $this->getInput();
            $saleId = (int) ($input['id'] ?? 0);
            
            if (!$saleId) {
                $this->errorResponse('Sale ID is required');
                return;
            }
            
            $connection = $this->container->resolve(\YFEvents\Infrastructure\Database\ConnectionInterface::class);
            $pdo = $connection->getConnection();
            
            // Get sale with seller info
            $stmt = $pdo->prepare("
                SELECT s.*, sl.company_name as seller_name, sl.email as seller_email
                FROM yfc_sales s
                LEFT JOIN yfc_sellers sl ON s.seller_id = sl.id
                WHERE s.id = :id
            ");
            $stmt->execute([':id' => $saleId]);
            $sale = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$sale) {
                $this->errorResponse('Sale not found', 404);
                return;
            }
            
            // Get items count
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM yfc_items WHERE sale_id = :id");
            $stmt->execute([':id' => $saleId]);
            $itemsCount = $stmt->fetchColumn();
            
            // Get claimed items count
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM yfc_items WHERE sale_id = :id AND status = 'claimed'");
            $stmt->execute([':id' => $saleId]);
            $claimedCount = $stmt->fetchColumn();
            
            $this->successResponse([
                'sale' => $sale,
                'stats' => [
                    'total_items' => (int) $itemsCount,
                    'claimed_items' => (int) $claimedCount
                ]
            ]);
            
        } catch (Exception $e) {
            $this->errorResponse('Failed to load sale details: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Approve sale
     */
    public function approveSale(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        try {
            $input = $this->getInput();
            $saleId = (int) ($input['id'] ?? 0);
            
            if (!$saleId) {
                $this->errorResponse('Sale ID is required');
                return;
            }
            
            $connection = $this->container->resolve(\YFEvents\Infrastructure\Database\ConnectionInterface::class);
            $pdo = $connection->getConnection();
            
            $stmt = $pdo->prepare("UPDATE yfc_sales SET status = 'active' WHERE id = :id");
            $stmt->execute([':id' => $saleId]);
            
            if ($stmt->rowCount() === 0) {
                $this->errorResponse('Sale not found', 404);
                return;
            }
            
            $this->successResponse([], 'Sale approved successfully');
            
        } catch (Exception $e) {
            $this->errorResponse('Failed to approve sale: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Feature/unfeature sale
     */
    public function featureSale(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        try {
            $input = $this->getInput();
            $saleId = (int) ($input['id'] ?? 0);
            $featured = (bool) ($input['featured'] ?? true);
            
            if (!$saleId) {
                $this->errorResponse('Sale ID is required');
                return;
            }
            
            $connection = $this->container->resolve(\YFEvents\Infrastructure\Database\ConnectionInterface::class);
            $pdo = $connection->getConnection();
            
            $stmt = $pdo->prepare("UPDATE yfc_sales SET featured = :featured WHERE id = :id");
            $stmt->execute([':id' => $saleId, ':featured' => $featured ? 1 : 0]);
            
            if ($stmt->rowCount() === 0) {
                $this->errorResponse('Sale not found', 404);
                return;
            }
            
            $message = $featured ? 'Sale featured successfully' : 'Sale unfeatured successfully';
            $this->successResponse([], $message);
            
        } catch (Exception $e) {
            $this->errorResponse('Failed to update sale: ' . $e->getMessage(), 500);
        }
    }
}