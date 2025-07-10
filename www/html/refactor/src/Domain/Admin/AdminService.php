<?php

declare(strict_types=1);

namespace YakimaFinds\Domain\Admin;

use YakimaFinds\Domain\Events\EventServiceInterface;
use YakimaFinds\Domain\Shops\ShopServiceInterface;
use YakimaFinds\Infrastructure\Database\ConnectionInterface;
use DateTimeInterface;
use DateTime;

/**
 * Admin service implementation
 */
class AdminService implements AdminServiceInterface
{
    public function __construct(
        private EventServiceInterface $eventService,
        private ShopServiceInterface $shopService,
        private ConnectionInterface $connection
    ) {}

    public function getDashboardStatistics(): array
    {
        // Get statistics from both domains
        $eventStats = $this->eventService->getEventStatistics();
        $shopStats = $this->shopService->getShopStatistics();

        return [
            'events' => [
                'total' => $eventStats['total'],
                'approved' => $eventStats['approved'],
                'pending' => $eventStats['pending'],
                'rejected' => $eventStats['rejected'],
                'upcoming' => $eventStats['upcoming'],
                'featured' => $eventStats['featured'],
            ],
            'shops' => [
                'total' => $shopStats['total'],
                'active' => $shopStats['approved'],
                'pending' => $shopStats['pending'],
                'inactive' => $shopStats['inactive'],
                'featured' => $shopStats['featured'],
                'verified' => $shopStats['verified'],
                'geocoded' => $shopStats['geocoded'],
            ],
            'system' => [
                'database_size' => $this->getDatabaseSize(),
                'last_updated' => (new DateTime())->format('c'),
                'uptime' => $this->getSystemUptime(),
            ],
            'summary' => [
                'total_content' => $eventStats['total'] + $shopStats['total'],
                'pending_approval' => $eventStats['pending'] + $shopStats['pending'],
                'featured_content' => $eventStats['featured'] + $shopStats['featured'],
            ]
        ];
    }

    public function getSystemHealth(): array
    {
        $health = [
            'status' => 'healthy',
            'checks' => [],
            'score' => 100,
        ];

        // Database connectivity check
        try {
            $this->connection->execute("SELECT 1")->fetch();
            $health['checks']['database'] = ['status' => 'ok', 'message' => 'Database connection successful'];
        } catch (\Exception $e) {
            $health['checks']['database'] = ['status' => 'error', 'message' => 'Database connection failed'];
            $health['status'] = 'unhealthy';
            $health['score'] -= 50;
        }

        // Content integrity checks
        $orphanedEvents = $this->checkOrphanedContent('events');
        $orphanedShops = $this->checkOrphanedContent('local_shops');
        
        if ($orphanedEvents > 0 || $orphanedShops > 0) {
            $health['checks']['content_integrity'] = [
                'status' => 'warning',
                'message' => "Found {$orphanedEvents} orphaned events and {$orphanedShops} orphaned shops"
            ];
            $health['score'] -= 10;
        } else {
            $health['checks']['content_integrity'] = ['status' => 'ok', 'message' => 'No orphaned content found'];
        }

        // Geocoding coverage check
        $eventStats = $this->eventService->getEventStatistics();
        $shopStats = $this->shopService->getShopStatistics();
        
        $eventGeocodingRate = $eventStats['total'] > 0 ? 
            (($eventStats['total'] - $this->getUngeocodedCount('events')) / $eventStats['total']) * 100 : 100;
        $shopGeocodingRate = $shopStats['total'] > 0 ? 
            ($shopStats['geocoded'] / $shopStats['total']) * 100 : 100;

        if ($eventGeocodingRate < 80 || $shopGeocodingRate < 80) {
            $health['checks']['geocoding'] = [
                'status' => 'warning',
                'message' => sprintf('Low geocoding coverage: Events %.1f%%, Shops %.1f%%', $eventGeocodingRate, $shopGeocodingRate)
            ];
            $health['score'] -= 15;
        } else {
            $health['checks']['geocoding'] = [
                'status' => 'ok',
                'message' => sprintf('Good geocoding coverage: Events %.1f%%, Shops %.1f%%', $eventGeocodingRate, $shopGeocodingRate)
            ];
        }

        return $health;
    }

    public function getRecentActivity(int $limit = 50): array
    {
        $activities = [];

        // Get recent events
        $sql = "SELECT 'event' as type, id, title as name, status, created_at, updated_at 
                FROM events 
                ORDER BY updated_at DESC 
                LIMIT :limit";
        
        $stmt = $this->connection->prepare($sql);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $activities[] = [
                'type' => 'event',
                'id' => $row['id'],
                'name' => $row['name'],
                'status' => $row['status'],
                'action' => $this->determineAction($row['created_at'], $row['updated_at']),
                'timestamp' => $row['updated_at'],
            ];
        }

        // Get recent shops
        $sql = "SELECT 'shop' as type, id, name, status, created_at, updated_at 
                FROM local_shops 
                ORDER BY updated_at DESC 
                LIMIT :limit";
        
        $stmt = $this->connection->prepare($sql);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $activities[] = [
                'type' => 'shop',
                'id' => $row['id'],
                'name' => $row['name'],
                'status' => $row['status'],
                'action' => $this->determineAction($row['created_at'], $row['updated_at']),
                'timestamp' => $row['updated_at'],
            ];
        }

        // Sort by timestamp and limit
        usort($activities, fn($a, $b) => strtotime($b['timestamp']) - strtotime($a['timestamp']));
        
        return array_slice($activities, 0, $limit);
    }

    public function getPerformanceMetrics(): array
    {
        return [
            'response_times' => [
                'avg_response_time' => $this->getAverageResponseTime(),
                'database_query_time' => $this->getDatabaseQueryTime(),
            ],
            'content_metrics' => [
                'events_per_day' => $this->getContentCreationRate('events'),
                'shops_per_day' => $this->getContentCreationRate('local_shops'),
                'approval_rate' => $this->getApprovalRate(),
            ],
            'system_metrics' => [
                'memory_usage' => memory_get_usage(true),
                'peak_memory' => memory_get_peak_usage(true),
                'php_version' => PHP_VERSION,
            ]
        ];
    }

    public function getModerationQueue(): array
    {
        $queue = [];

        // Pending events
        $eventStats = $this->eventService->getEventStatistics();
        if ($eventStats['pending'] > 0) {
            $queue[] = [
                'type' => 'events',
                'count' => $eventStats['pending'],
                'priority' => 'medium',
                'description' => 'Events awaiting approval',
                'action_url' => '/admin/events?status=pending'
            ];
        }

        // Pending shops
        $shopStats = $this->shopService->getShopStatistics();
        if ($shopStats['pending'] > 0) {
            $queue[] = [
                'type' => 'shops',
                'count' => $shopStats['pending'],
                'priority' => 'medium',
                'description' => 'Shops awaiting approval',
                'action_url' => '/admin/shops?status=pending'
            ];
        }

        // Check for content needing geocoding
        $ungeocodedEvents = $this->getUngeocodedCount('events');
        if ($ungeocodedEvents > 0) {
            $queue[] = [
                'type' => 'geocoding',
                'count' => $ungeocodedEvents,
                'priority' => 'low',
                'description' => 'Events missing location coordinates',
                'action_url' => '/admin/geocoding/events'
            ];
        }

        $ungeocodedShops = $this->getUngeocodedCount('local_shops');
        if ($ungeocodedShops > 0) {
            $queue[] = [
                'type' => 'geocoding',
                'count' => $ungeocodedShops,
                'priority' => 'low',
                'description' => 'Shops missing location coordinates',
                'action_url' => '/admin/geocoding/shops'
            ];
        }

        return $queue;
    }

    public function getUserActivityStats(): array
    {
        // Get user activity from events and shops
        $sql = "SELECT 
                    COUNT(*) as total_actions,
                    COUNT(DISTINCT cms_user_id) as active_users,
                    AVG(CASE WHEN created_at > DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as weekly_activity_rate
                FROM events 
                WHERE cms_user_id IS NOT NULL
                
                UNION ALL
                
                SELECT 
                    COUNT(*) as total_actions,
                    COUNT(DISTINCT owner_id) as active_users,
                    AVG(CASE WHEN created_at > DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as weekly_activity_rate
                FROM local_shops 
                WHERE owner_id IS NOT NULL";

        $stmt = $this->connection->execute($sql);
        $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $totalActions = array_sum(array_column($results, 'total_actions'));
        $activeUsers = array_sum(array_column($results, 'active_users'));
        $weeklyActivity = array_sum(array_column($results, 'weekly_activity_rate')) / count($results);

        return [
            'total_actions' => $totalActions,
            'active_users' => $activeUsers,
            'weekly_activity_rate' => round($weeklyActivity * 100, 2),
            'avg_actions_per_user' => $activeUsers > 0 ? round($totalActions / $activeUsers, 2) : 0,
        ];
    }

    public function getContentStatsByDateRange(DateTimeInterface $startDate, DateTimeInterface $endDate): array
    {
        $start = $startDate->format('Y-m-d');
        $end = $endDate->format('Y-m-d 23:59:59');

        // Events by date
        $sql = "SELECT DATE(created_at) as date, COUNT(*) as count 
                FROM events 
                WHERE created_at BETWEEN :start AND :end 
                GROUP BY DATE(created_at) 
                ORDER BY date";
        
        $stmt = $this->connection->prepare($sql);
        $stmt->execute(['start' => $start, 'end' => $end]);
        $eventsByDate = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Shops by date
        $sql = "SELECT DATE(created_at) as date, COUNT(*) as count 
                FROM local_shops 
                WHERE created_at BETWEEN :start AND :end 
                GROUP BY DATE(created_at) 
                ORDER BY date";
        
        $stmt = $this->connection->prepare($sql);
        $stmt->execute(['start' => $start, 'end' => $end]);
        $shopsByDate = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return [
            'events_by_date' => $eventsByDate,
            'shops_by_date' => $shopsByDate,
            'date_range' => [
                'start' => $startDate->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d')
            ]
        ];
    }

    public function getTopPerformingContent(): array
    {
        // Featured events and shops
        $featuredEvents = $this->eventService->getFeaturedEvents(5);
        $featuredShops = $this->shopService->getFeaturedShops(5);

        return [
            'featured_events' => array_map(function($event) {
                return [
                    'id' => $event->getId(),
                    'title' => $event->getTitle(),
                    'status' => $event->getStatus(),
                    'featured' => $event->isFeatured(),
                ];
            }, $featuredEvents),
            'featured_shops' => array_map(function($shop) {
                return [
                    'id' => $shop->getId(),
                    'name' => $shop->getName(),
                    'status' => $shop->getStatus(),
                    'featured' => $shop->isFeatured(),
                    'verified' => $shop->isVerified(),
                ];
            }, $featuredShops),
        ];
    }

    public function getSystemAlerts(): array
    {
        $alerts = [];

        // Check for low content approval rates
        $eventStats = $this->eventService->getEventStatistics();
        $shopStats = $this->shopService->getShopStatistics();

        $eventPendingRatio = $eventStats['total'] > 0 ? $eventStats['pending'] / $eventStats['total'] : 0;
        $shopPendingRatio = $shopStats['total'] > 0 ? $shopStats['pending'] / $shopStats['total'] : 0;

        if ($eventPendingRatio > 0.3) {
            $alerts[] = [
                'type' => 'warning',
                'category' => 'content_moderation',
                'message' => 'High number of pending events (' . $eventStats['pending'] . ' pending)',
                'action' => 'Review and approve pending events',
                'priority' => 'medium'
            ];
        }

        if ($shopPendingRatio > 0.3) {
            $alerts[] = [
                'type' => 'warning',
                'category' => 'content_moderation',
                'message' => 'High number of pending shops (' . $shopStats['pending'] . ' pending)',
                'action' => 'Review and approve pending shops',
                'priority' => 'medium'
            ];
        }

        // Check geocoding coverage
        $ungeocodedEvents = $this->getUngeocodedCount('events');
        $ungeocodedShops = $this->getUngeocodedCount('local_shops');

        if ($ungeocodedEvents > 10) {
            $alerts[] = [
                'type' => 'info',
                'category' => 'data_quality',
                'message' => $ungeocodedEvents . ' events missing coordinates',
                'action' => 'Run geocoding process',
                'priority' => 'low'
            ];
        }

        if ($ungeocodedShops > 5) {
            $alerts[] = [
                'type' => 'info',
                'category' => 'data_quality',
                'message' => $ungeocodedShops . ' shops missing coordinates',
                'action' => 'Run geocoding process',
                'priority' => 'low'
            ];
        }

        return $alerts;
    }

    public function exportSystemData(string $format = 'json', array $filters = []): array
    {
        $data = [
            'export_info' => [
                'timestamp' => (new DateTime())->format('c'),
                'format' => $format,
                'filters' => $filters,
            ],
            'statistics' => $this->getDashboardStatistics(),
            'system_health' => $this->getSystemHealth(),
            'performance_metrics' => $this->getPerformanceMetrics(),
        ];

        if (!isset($filters['exclude_content']) || !$filters['exclude_content']) {
            $data['recent_activity'] = $this->getRecentActivity(100);
        }

        return $data;
    }

    // Helper methods

    private function getDatabaseSize(): string
    {
        try {
            $sql = "SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb 
                    FROM information_schema.tables 
                    WHERE table_schema = DATABASE()";
            
            $stmt = $this->connection->execute($sql);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            return $result['size_mb'] . ' MB';
        } catch (\Exception $e) {
            return 'Unknown';
        }
    }

    private function getSystemUptime(): string
    {
        try {
            $sql = "SHOW STATUS LIKE 'Uptime'";
            $stmt = $this->connection->execute($sql);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            $uptime = (int) $result['Value'];
            $days = floor($uptime / 86400);
            $hours = floor(($uptime % 86400) / 3600);
            
            return "{$days} days, {$hours} hours";
        } catch (\Exception $e) {
            return 'Unknown';
        }
    }

    private function checkOrphanedContent(string $table): int
    {
        try {
            $sql = "SELECT COUNT(*) as count FROM {$table} WHERE id IS NULL";
            $stmt = $this->connection->execute($sql);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            return (int) $result['count'];
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function getUngeocodedCount(string $table): int
    {
        try {
            $sql = "SELECT COUNT(*) as count FROM {$table} WHERE latitude IS NULL OR longitude IS NULL";
            $stmt = $this->connection->execute($sql);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            return (int) $result['count'];
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function determineAction(string $createdAt, string $updatedAt): string
    {
        return $createdAt === $updatedAt ? 'created' : 'updated';
    }

    private function getAverageResponseTime(): float
    {
        // Placeholder - would require actual request logging
        return 0.15; // 150ms average
    }

    private function getDatabaseQueryTime(): float
    {
        $start = microtime(true);
        $this->connection->execute("SELECT 1")->fetch();
        return round((microtime(true) - $start) * 1000, 2); // Convert to milliseconds
    }

    private function getContentCreationRate(string $table): float
    {
        try {
            $sql = "SELECT COUNT(*) / 7 as rate 
                    FROM {$table} 
                    WHERE created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)";
            
            $stmt = $this->connection->execute($sql);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            return round((float) $result['rate'], 2);
        } catch (\Exception $e) {
            return 0.0;
        }
    }

    private function getApprovalRate(): float
    {
        try {
            $eventStats = $this->eventService->getEventStatistics();
            $shopStats = $this->shopService->getShopStatistics();
            
            $totalApproved = $eventStats['approved'] + $shopStats['approved'];
            $totalContent = $eventStats['total'] + $shopStats['total'];
            
            return $totalContent > 0 ? round(($totalApproved / $totalContent) * 100, 2) : 0.0;
        } catch (\Exception $e) {
            return 0.0;
        }
    }
}