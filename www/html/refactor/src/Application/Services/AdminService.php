<?php

declare(strict_types=1);

namespace YakimaFinds\Application\Services;

use YakimaFinds\Domain\Events\EventRepositoryInterface;
use YakimaFinds\Domain\Shops\ShopRepositoryInterface;
use YakimaFinds\Infrastructure\Database\Connection;
use DateTimeInterface;
use DateTime;

class AdminService
{
    public function __construct(
        private readonly EventRepositoryInterface $eventRepository,
        private readonly ShopRepositoryInterface $shopRepository,
        private readonly Connection $connection
    ) {}

    /**
     * Get comprehensive dashboard statistics
     */
    public function getDashboardStatistics(): array
    {
        return [
            'events' => $this->getEventStatistics(),
            'shops' => $this->getShopStatistics(),
            'activity' => $this->getRecentActivity(),
            'trends' => $this->getTrendData(),
            'system' => $this->getSystemHealth()
        ];
    }

    /**
     * Get detailed event statistics
     */
    public function getEventStatistics(): array
    {
        $pdo = $this->connection->getPdo();
        
        // Total events
        $totalEvents = $pdo->query("SELECT COUNT(*) FROM events")->fetchColumn();
        
        // Events by status
        $statusCounts = $pdo->query(
            "SELECT status, COUNT(*) as count FROM events GROUP BY status"
        )->fetchAll(\PDO::FETCH_KEY_PAIR);
        
        // Upcoming events (next 30 days)
        $upcomingEvents = $pdo->prepare(
            "SELECT COUNT(*) FROM events 
             WHERE start_datetime BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 30 DAY)
             AND status = 'approved'"
        );
        $upcomingEvents->execute();
        $upcomingCount = $upcomingEvents->fetchColumn();
        
        // Events by category
        $categoryStats = $pdo->query(
            "SELECT ec.category_id, c.name, COUNT(*) as count 
             FROM event_categories ec
             JOIN categories c ON ec.category_id = c.id
             GROUP BY ec.category_id, c.name
             ORDER BY count DESC
             LIMIT 10"
        )->fetchAll(\PDO::FETCH_ASSOC);
        
        // Recent event submissions
        $recentSubmissions = $pdo->prepare(
            "SELECT COUNT(*) FROM events 
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
        );
        $recentSubmissions->execute();
        $recentCount = $recentSubmissions->fetchColumn();
        
        return [
            'total' => (int)$totalEvents,
            'by_status' => $statusCounts,
            'upcoming_30_days' => (int)$upcomingCount,
            'by_category' => $categoryStats,
            'recent_submissions' => (int)$recentCount,
            'approval_rate' => $this->calculateApprovalRate()
        ];
    }

    /**
     * Get detailed shop statistics
     */
    public function getShopStatistics(): array
    {
        $pdo = $this->connection->getPdo();
        
        // Total shops
        $totalShops = $pdo->query("SELECT COUNT(*) FROM shops")->fetchColumn();
        
        // Active shops
        $activeShops = $pdo->query(
            "SELECT COUNT(*) FROM shops WHERE active = 1"
        )->fetchColumn();
        
        // Shops by category
        $categoryStats = $pdo->query(
            "SELECT sc.category_id, c.name, COUNT(DISTINCT sc.shop_id) as count 
             FROM shop_categories sc
             JOIN shop_category_types c ON sc.category_id = c.id
             GROUP BY sc.category_id, c.name
             ORDER BY count DESC
             LIMIT 10"
        )->fetchAll(\PDO::FETCH_ASSOC);
        
        // Featured shops
        $featuredCount = $pdo->query(
            "SELECT COUNT(*) FROM shops WHERE featured = 1"
        )->fetchColumn();
        
        // Recent shop updates
        $recentUpdates = $pdo->prepare(
            "SELECT COUNT(*) FROM shops 
             WHERE updated_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
        );
        $recentUpdates->execute();
        $updatedCount = $recentUpdates->fetchColumn();
        
        return [
            'total' => (int)$totalShops,
            'active' => (int)$activeShops,
            'featured' => (int)$featuredCount,
            'by_category' => $categoryStats,
            'recent_updates' => (int)$updatedCount,
            'activity_rate' => $this->calculateShopActivityRate()
        ];
    }

    /**
     * Get recent activity across the platform
     */
    public function getRecentActivity(int $limit = 20): array
    {
        $pdo = $this->connection->getPdo();
        
        $activities = [];
        
        // Recent events
        $recentEvents = $pdo->prepare(
            "SELECT 'event' as type, id, title as description, created_at, status
             FROM events 
             ORDER BY created_at DESC 
             LIMIT :limit"
        );
        $recentEvents->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $recentEvents->execute();
        $activities = array_merge($activities, $recentEvents->fetchAll(\PDO::FETCH_ASSOC));
        
        // Recent shop updates
        $recentShops = $pdo->prepare(
            "SELECT 'shop' as type, id, name as description, updated_at as created_at, 
                    IF(active = 1, 'active', 'inactive') as status
             FROM shops 
             WHERE updated_at IS NOT NULL
             ORDER BY updated_at DESC 
             LIMIT :limit"
        );
        $recentShops->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $recentShops->execute();
        $activities = array_merge($activities, $recentShops->fetchAll(\PDO::FETCH_ASSOC));
        
        // Sort by timestamp
        usort($activities, function($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });
        
        return array_slice($activities, 0, $limit);
    }

    /**
     * Get trend data for charts
     */
    public function getTrendData(int $days = 30): array
    {
        $pdo = $this->connection->getPdo();
        
        // Events per day
        $eventTrends = $pdo->prepare(
            "SELECT DATE(created_at) as date, COUNT(*) as count 
             FROM events 
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
             GROUP BY DATE(created_at)
             ORDER BY date"
        );
        $eventTrends->bindValue(':days', $days, \PDO::PARAM_INT);
        $eventTrends->execute();
        
        // Page views (if tracking exists)
        $pageViews = $this->getPageViewTrends($days);
        
        return [
            'events_per_day' => $eventTrends->fetchAll(\PDO::FETCH_ASSOC),
            'page_views' => $pageViews,
            'period_days' => $days
        ];
    }

    /**
     * Get system health metrics
     */
    public function getSystemHealth(): array
    {
        $pdo = $this->connection->getPdo();
        
        // Database size
        $dbSize = $pdo->query(
            "SELECT SUM(data_length + index_length) / 1024 / 1024 as size_mb
             FROM information_schema.tables 
             WHERE table_schema = DATABASE()"
        )->fetchColumn();
        
        // Scraper status (check last run)
        $scraperStatus = $this->getScraperStatus();
        
        // Cache status
        $cacheStatus = $this->getCacheStatus();
        
        return [
            'database_size_mb' => round((float)$dbSize, 2),
            'scraper_status' => $scraperStatus,
            'cache_status' => $cacheStatus,
            'php_version' => PHP_VERSION,
            'server_time' => (new DateTime())->format('Y-m-d H:i:s')
        ];
    }

    /**
     * Get user activity statistics
     */
    public function getUserStatistics(): array
    {
        $pdo = $this->connection->getPdo();
        
        // Total users
        $totalUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
        
        // Active users (logged in last 30 days)
        $activeUsers = $pdo->prepare(
            "SELECT COUNT(*) FROM users 
             WHERE last_login >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );
        $activeUsers->execute();
        $activeCount = $activeUsers->fetchColumn();
        
        // Users by role
        $roleStats = $pdo->query(
            "SELECT role, COUNT(*) as count 
             FROM users 
             GROUP BY role"
        )->fetchAll(\PDO::FETCH_KEY_PAIR);
        
        return [
            'total' => (int)$totalUsers,
            'active_30_days' => (int)$activeCount,
            'by_role' => $roleStats
        ];
    }

    /**
     * Search across all admin-manageable content
     */
    public function searchContent(string $query, array $types = ['events', 'shops'], int $limit = 50): array
    {
        $results = [];
        $searchTerm = '%' . $query . '%';
        
        if (in_array('events', $types)) {
            $events = $this->searchEvents($searchTerm, $limit);
            $results['events'] = $events;
        }
        
        if (in_array('shops', $types)) {
            $shops = $this->searchShops($searchTerm, $limit);
            $results['shops'] = $shops;
        }
        
        return $results;
    }

    /**
     * Get content awaiting moderation
     */
    public function getPendingModeration(): array
    {
        $pdo = $this->connection->getPdo();
        
        // Pending events
        $pendingEvents = $pdo->query(
            "SELECT id, title, created_at, source 
             FROM events 
             WHERE status = 'pending'
             ORDER BY created_at ASC"
        )->fetchAll(\PDO::FETCH_ASSOC);
        
        // Flagged content
        $flaggedContent = $this->getFlaggedContent();
        
        return [
            'pending_events' => $pendingEvents,
            'flagged_content' => $flaggedContent,
            'total_pending' => count($pendingEvents) + count($flaggedContent)
        ];
    }

    /**
     * Export data for reporting
     */
    public function exportData(string $type, array $filters = []): array
    {
        switch ($type) {
            case 'events':
                return $this->exportEvents($filters);
            case 'shops':
                return $this->exportShops($filters);
            case 'analytics':
                return $this->exportAnalytics($filters);
            default:
                throw new \InvalidArgumentException("Unknown export type: $type");
        }
    }

    private function calculateApprovalRate(): float
    {
        $pdo = $this->connection->getPdo();
        
        $stats = $pdo->query(
            "SELECT 
                COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved,
                COUNT(CASE WHEN status IN ('approved', 'rejected') THEN 1 END) as total
             FROM events
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
        )->fetch(\PDO::FETCH_ASSOC);
        
        return $stats['total'] > 0 
            ? round(($stats['approved'] / $stats['total']) * 100, 2) 
            : 0.0;
    }

    private function calculateShopActivityRate(): float
    {
        $pdo = $this->connection->getPdo();
        
        $stats = $pdo->query(
            "SELECT 
                COUNT(CASE WHEN updated_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as recent,
                COUNT(*) as total
             FROM shops
             WHERE active = 1"
        )->fetch(\PDO::FETCH_ASSOC);
        
        return $stats['total'] > 0 
            ? round(($stats['recent'] / $stats['total']) * 100, 2) 
            : 0.0;
    }

    private function getPageViewTrends(int $days): array
    {
        // Placeholder - would integrate with analytics tracking
        return [];
    }

    private function getScraperStatus(): array
    {
        $pdo = $this->connection->getPdo();
        
        // Check scraper logs if table exists
        try {
            $lastRun = $pdo->query(
                "SELECT MAX(created_at) as last_run FROM scraper_logs"
            )->fetchColumn();
            
            return [
                'last_run' => $lastRun ?: 'Never',
                'status' => $lastRun && strtotime($lastRun) > strtotime('-1 day') ? 'healthy' : 'warning'
            ];
        } catch (\Exception $e) {
            return ['status' => 'unknown', 'last_run' => 'N/A'];
        }
    }

    private function getCacheStatus(): array
    {
        // Placeholder - would check actual cache system
        return [
            'type' => 'file',
            'status' => 'active',
            'hit_rate' => 'N/A'
        ];
    }

    private function searchEvents(string $searchTerm, int $limit): array
    {
        $pdo = $this->connection->getPdo();
        
        $stmt = $pdo->prepare(
            "SELECT id, title, start_datetime, status
             FROM events
             WHERE title LIKE :search 
                OR description LIKE :search
                OR location LIKE :search
             ORDER BY start_datetime DESC
             LIMIT :limit"
        );
        
        $stmt->bindValue(':search', $searchTerm);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function searchShops(string $searchTerm, int $limit): array
    {
        $pdo = $this->connection->getPdo();
        
        $stmt = $pdo->prepare(
            "SELECT id, name, active, featured
             FROM shops
             WHERE name LIKE :search 
                OR description LIKE :search
                OR address LIKE :search
             ORDER BY featured DESC, name ASC
             LIMIT :limit"
        );
        
        $stmt->bindValue(':search', $searchTerm);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function getFlaggedContent(): array
    {
        // Placeholder - would check flagged content table
        return [];
    }

    private function exportEvents(array $filters): array
    {
        $query = "SELECT * FROM events WHERE 1=1";
        $params = [];
        
        if (isset($filters['start_date'])) {
            $query .= " AND start_datetime >= :start_date";
            $params[':start_date'] = $filters['start_date'];
        }
        
        if (isset($filters['end_date'])) {
            $query .= " AND start_datetime <= :end_date";
            $params[':end_date'] = $filters['end_date'];
        }
        
        if (isset($filters['status'])) {
            $query .= " AND status = :status";
            $params[':status'] = $filters['status'];
        }
        
        $pdo = $this->connection->getPdo();
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function exportShops(array $filters): array
    {
        $query = "SELECT * FROM shops WHERE 1=1";
        $params = [];
        
        if (isset($filters['active'])) {
            $query .= " AND active = :active";
            $params[':active'] = $filters['active'];
        }
        
        if (isset($filters['featured'])) {
            $query .= " AND featured = :featured";
            $params[':featured'] = $filters['featured'];
        }
        
        $pdo = $this->connection->getPdo();
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function exportAnalytics(array $filters): array
    {
        return [
            'events' => $this->getEventStatistics(),
            'shops' => $this->getShopStatistics(),
            'trends' => $this->getTrendData($filters['days'] ?? 30),
            'exported_at' => (new DateTime())->format('Y-m-d H:i:s')
        ];
    }
}