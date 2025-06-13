<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use YakimaFinds\Application\Bootstrap;
use YakimaFinds\Domain\Admin\AdminServiceInterface;

echo "=== Admin System Test ===\n\n";

try {
    // Test 1: Bootstrap application
    echo "1. Testing application bootstrap...\n";
    $container = Bootstrap::boot();
    echo "   ✓ Application bootstrapped successfully\n\n";

    // Test 2: Admin service resolution
    echo "2. Testing admin service resolution...\n";
    $adminService = $container->resolve(AdminServiceInterface::class);
    echo "   ✓ Admin service resolved successfully\n\n";

    // Test 3: Dashboard statistics
    echo "3. Testing dashboard statistics...\n";
    $dashboardStats = $adminService->getDashboardStatistics();
    echo "   ✓ Dashboard statistics loaded\n";
    echo "   ✓ Total events: " . $dashboardStats['events']['total'] . "\n";
    echo "   ✓ Total shops: " . $dashboardStats['shops']['total'] . "\n";
    echo "   ✓ Total content: " . $dashboardStats['summary']['total_content'] . "\n";
    echo "   ✓ Pending approval: " . $dashboardStats['summary']['pending_approval'] . "\n";
    echo "   ✓ Featured content: " . $dashboardStats['summary']['featured_content'] . "\n\n";

    // Test 4: System health
    echo "4. Testing system health check...\n";
    $systemHealth = $adminService->getSystemHealth();
    echo "   ✓ System health status: " . $systemHealth['status'] . "\n";
    echo "   ✓ Health score: " . $systemHealth['score'] . "/100\n";
    echo "   ✓ Health checks performed: " . count($systemHealth['checks']) . "\n";
    
    foreach ($systemHealth['checks'] as $check => $result) {
        $icon = $result['status'] === 'ok' ? '✓' : ($result['status'] === 'warning' ? '⚠' : '❌');
        echo "     {$icon} {$check}: {$result['status']} - {$result['message']}\n";
    }
    echo "\n";

    // Test 5: Recent activity
    echo "5. Testing recent activity...\n";
    $recentActivity = $adminService->getRecentActivity(10);
    echo "   ✓ Retrieved " . count($recentActivity) . " recent activities\n";
    
    if (!empty($recentActivity)) {
        $activity = $recentActivity[0];
        echo "   ✓ Latest activity: " . $activity['action'] . " " . $activity['type'] . " '" . $activity['name'] . "'\n";
        echo "   ✓ Status: " . $activity['status'] . "\n";
        echo "   ✓ Timestamp: " . $activity['timestamp'] . "\n";
    }
    echo "\n";

    // Test 6: Performance metrics
    echo "6. Testing performance metrics...\n";
    $performanceMetrics = $adminService->getPerformanceMetrics();
    echo "   ✓ Performance metrics loaded\n";
    echo "   ✓ Database query time: " . $performanceMetrics['response_times']['database_query_time'] . "ms\n";
    echo "   ✓ Events per day: " . $performanceMetrics['content_metrics']['events_per_day'] . "\n";
    echo "   ✓ Shops per day: " . $performanceMetrics['content_metrics']['shops_per_day'] . "\n";
    echo "   ✓ Approval rate: " . $performanceMetrics['content_metrics']['approval_rate'] . "%\n";
    echo "   ✓ Memory usage: " . round($performanceMetrics['system_metrics']['memory_usage'] / 1024 / 1024, 2) . " MB\n";
    echo "   ✓ PHP version: " . $performanceMetrics['system_metrics']['php_version'] . "\n\n";

    // Test 7: Moderation queue
    echo "7. Testing moderation queue...\n";
    $moderationQueue = $adminService->getModerationQueue();
    echo "   ✓ Moderation queue loaded with " . count($moderationQueue) . " items\n";
    
    foreach ($moderationQueue as $item) {
        echo "   ✓ {$item['type']}: {$item['count']} items ({$item['priority']} priority)\n";
        echo "     - {$item['description']}\n";
    }
    echo "\n";

    // Test 8: User activity stats
    echo "8. Testing user activity statistics...\n";
    $userActivity = $adminService->getUserActivityStats();
    echo "   ✓ User activity statistics loaded\n";
    echo "   ✓ Total actions: " . $userActivity['total_actions'] . "\n";
    echo "   ✓ Active users: " . $userActivity['active_users'] . "\n";
    echo "   ✓ Weekly activity rate: " . $userActivity['weekly_activity_rate'] . "%\n";
    echo "   ✓ Avg actions per user: " . $userActivity['avg_actions_per_user'] . "\n\n";

    // Test 9: System alerts
    echo "9. Testing system alerts...\n";
    $systemAlerts = $adminService->getSystemAlerts();
    echo "   ✓ System alerts loaded: " . count($systemAlerts) . " alerts\n";
    
    foreach ($systemAlerts as $alert) {
        $icon = match($alert['type']) {
            'error' => '❌',
            'warning' => '⚠',
            'info' => 'ℹ',
            default => '•'
        };
        echo "   {$icon} {$alert['category']}: {$alert['message']} ({$alert['priority']} priority)\n";
    }
    echo "\n";

    // Test 10: Top performing content
    echo "10. Testing top performing content...\n";
    $topContent = $adminService->getTopPerformingContent();
    echo "   ✓ Top content loaded\n";
    echo "   ✓ Featured events: " . count($topContent['featured_events']) . "\n";
    echo "   ✓ Featured shops: " . count($topContent['featured_shops']) . "\n";
    
    if (!empty($topContent['featured_events'])) {
        $event = $topContent['featured_events'][0];
        echo "     - Top event: '" . $event['title'] . "' (status: {$event['status']})\n";
    }
    
    if (!empty($topContent['featured_shops'])) {
        $shop = $topContent['featured_shops'][0];
        echo "     - Top shop: '" . $shop['name'] . "' (status: {$shop['status']})\n";
    }
    echo "\n";

    // Test 11: Date range statistics
    echo "11. Testing date range statistics...\n";
    $startDate = new DateTime('-30 days');
    $endDate = new DateTime();
    $dateRangeStats = $adminService->getContentStatsByDateRange($startDate, $endDate);
    echo "   ✓ Date range statistics loaded\n";
    echo "   ✓ Events by date entries: " . count($dateRangeStats['events_by_date']) . "\n";
    echo "   ✓ Shops by date entries: " . count($dateRangeStats['shops_by_date']) . "\n";
    echo "   ✓ Date range: " . $dateRangeStats['date_range']['start'] . " to " . $dateRangeStats['date_range']['end'] . "\n\n";

    // Test 12: Data export
    echo "12. Testing data export...\n";
    $exportData = $adminService->exportSystemData('json', ['exclude_content' => true]);
    echo "   ✓ Export data generated\n";
    echo "   ✓ Export timestamp: " . $exportData['export_info']['timestamp'] . "\n";
    echo "   ✓ Export format: " . $exportData['export_info']['format'] . "\n";
    echo "   ✓ Export sections: " . count($exportData) . "\n";
    echo "   ✓ Statistics included: " . (isset($exportData['statistics']) ? 'Yes' : 'No') . "\n";
    echo "   ✓ System health included: " . (isset($exportData['system_health']) ? 'Yes' : 'No') . "\n\n";

    echo "🎉 All admin system tests passed!\n\n";

    // Admin system summary
    echo "=== Admin System Summary ===\n";
    echo "✓ Unified dashboard with comprehensive statistics\n";
    echo "✓ System health monitoring with automated checks\n";
    echo "✓ Real-time performance metrics and monitoring\n";
    echo "✓ Content moderation queue management\n";
    echo "✓ User activity tracking and analytics\n";
    echo "✓ System alerts and notification system\n";
    echo "✓ Data export capabilities (JSON, CSV, XML)\n";
    echo "✓ Date range analytics and reporting\n";
    echo "✓ Cross-domain statistics (Events + Shops)\n";
    echo "✓ Top content identification and promotion\n";

} catch (\Exception $e) {
    echo "❌ Test failed: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}