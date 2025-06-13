<?php

declare(strict_types=1);

namespace YakimaFinds\Tests\Unit\Application\Services;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use YakimaFinds\Application\Services\AdminService;
use YakimaFinds\Domain\Events\EventRepositoryInterface;
use YakimaFinds\Domain\Shops\ShopRepositoryInterface;
use YakimaFinds\Infrastructure\Database\Connection;

class AdminServiceTest extends TestCase
{
    private AdminService $adminService;
    private MockObject $eventRepository;
    private MockObject $shopRepository;
    private MockObject $connection;
    private MockObject $pdo;

    protected function setUp(): void
    {
        $this->eventRepository = $this->createMock(EventRepositoryInterface::class);
        $this->shopRepository = $this->createMock(ShopRepositoryInterface::class);
        $this->connection = $this->createMock(Connection::class);
        $this->pdo = $this->createMock(\PDO::class);
        
        $this->connection->method('getPdo')->willReturn($this->pdo);
        
        $this->adminService = new AdminService(
            $this->eventRepository,
            $this->shopRepository,
            $this->connection
        );
    }

    public function testGetDashboardStatistics(): void
    {
        // Mock PDO statements for event statistics
        $eventCountStmt = $this->createMock(\PDOStatement::class);
        $eventCountStmt->method('fetchColumn')->willReturn('100');
        
        $statusCountStmt = $this->createMock(\PDOStatement::class);
        $statusCountStmt->method('fetchAll')->willReturn([
            'approved' => 80,
            'pending' => 15,
            'rejected' => 5
        ]);
        
        $upcomingStmt = $this->createMock(\PDOStatement::class);
        $upcomingStmt->method('execute');
        $upcomingStmt->method('fetchColumn')->willReturn('25');
        
        $categoryStmt = $this->createMock(\PDOStatement::class);
        $categoryStmt->method('fetchAll')->willReturn([
            ['category_id' => 1, 'name' => 'Music', 'count' => 30],
            ['category_id' => 2, 'name' => 'Food', 'count' => 25]
        ]);
        
        $recentStmt = $this->createMock(\PDOStatement::class);
        $recentStmt->method('execute');
        $recentStmt->method('fetchColumn')->willReturn('10');
        
        // Mock shop statistics
        $shopCountStmt = $this->createMock(\PDOStatement::class);
        $shopCountStmt->method('fetchColumn')->willReturn('50');
        
        $activeShopsStmt = $this->createMock(\PDOStatement::class);
        $activeShopsStmt->method('fetchColumn')->willReturn('45');
        
        $shopCategoryStmt = $this->createMock(\PDOStatement::class);
        $shopCategoryStmt->method('fetchAll')->willReturn([
            ['category_id' => 1, 'name' => 'Restaurant', 'count' => 20]
        ]);
        
        $featuredStmt = $this->createMock(\PDOStatement::class);
        $featuredStmt->method('fetchColumn')->willReturn('10');
        
        $shopUpdatesStmt = $this->createMock(\PDOStatement::class);
        $shopUpdatesStmt->method('execute');
        $shopUpdatesStmt->method('fetchColumn')->willReturn('5');
        
        $this->pdo->method('query')->willReturnMap([
            ["SELECT COUNT(*) FROM events", $eventCountStmt],
            ["SELECT status, COUNT(*) as count FROM events GROUP BY status", $statusCountStmt],
            ["SELECT ec.category_id, c.name, COUNT(*) as count FROM event_categories ec JOIN categories c ON ec.category_id = c.id GROUP BY ec.category_id, c.name ORDER BY count DESC LIMIT 10", $categoryStmt],
            ["SELECT COUNT(*) FROM shops", $shopCountStmt],
            ["SELECT COUNT(*) FROM shops WHERE active = 1", $activeShopsStmt],
            ["SELECT sc.category_id, c.name, COUNT(DISTINCT sc.shop_id) as count FROM shop_categories sc JOIN shop_category_types c ON sc.category_id = c.id GROUP BY sc.category_id, c.name ORDER BY count DESC LIMIT 10", $shopCategoryStmt],
            ["SELECT COUNT(*) FROM shops WHERE featured = 1", $featuredStmt]
        ]);
        
        $this->pdo->method('prepare')->willReturnMap([
            ["SELECT COUNT(*) FROM events WHERE start_datetime BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 30 DAY) AND status = 'approved'", $upcomingStmt],
            ["SELECT COUNT(*) FROM events WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)", $recentStmt],
            ["SELECT COUNT(*) FROM shops WHERE updated_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)", $shopUpdatesStmt]
        ]);
        
        $result = $this->adminService->getDashboardStatistics();
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('events', $result);
        $this->assertArrayHasKey('shops', $result);
        $this->assertArrayHasKey('activity', $result);
        $this->assertArrayHasKey('trends', $result);
        $this->assertArrayHasKey('system', $result);
    }

    public function testGetEventStatistics(): void
    {
        $eventCountStmt = $this->createMock(\PDOStatement::class);
        $eventCountStmt->method('fetchColumn')->willReturn('100');
        
        $statusCountStmt = $this->createMock(\PDOStatement::class);
        $statusCountStmt->method('fetchAll')->willReturn([
            'approved' => 80,
            'pending' => 15,
            'rejected' => 5
        ]);
        
        $upcomingStmt = $this->createMock(\PDOStatement::class);
        $upcomingStmt->method('execute');
        $upcomingStmt->method('fetchColumn')->willReturn('25');
        
        $categoryStmt = $this->createMock(\PDOStatement::class);
        $categoryStmt->method('fetchAll')->willReturn([
            ['category_id' => 1, 'name' => 'Music', 'count' => 30]
        ]);
        
        $recentStmt = $this->createMock(\PDOStatement::class);
        $recentStmt->method('execute');
        $recentStmt->method('fetchColumn')->willReturn('10');
        
        // Mock approval rate calculation
        $approvalStmt = $this->createMock(\PDOStatement::class);
        $approvalStmt->method('fetch')->willReturn([
            'approved' => 80,
            'total' => 100
        ]);
        
        $this->pdo->method('query')->willReturnMap([
            ["SELECT COUNT(*) FROM events", $eventCountStmt],
            ["SELECT status, COUNT(*) as count FROM events GROUP BY status", $statusCountStmt],
            ["SELECT ec.category_id, c.name, COUNT(*) as count FROM event_categories ec JOIN categories c ON ec.category_id = c.id GROUP BY ec.category_id, c.name ORDER BY count DESC LIMIT 10", $categoryStmt],
            ["SELECT COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved, COUNT(CASE WHEN status IN ('approved', 'rejected') THEN 1 END) as total FROM events WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)", $approvalStmt]
        ]);
        
        $this->pdo->method('prepare')->willReturnMap([
            ["SELECT COUNT(*) FROM events WHERE start_datetime BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 30 DAY) AND status = 'approved'", $upcomingStmt],
            ["SELECT COUNT(*) FROM events WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)", $recentStmt]
        ]);
        
        $stats = $this->adminService->getEventStatistics();
        
        $this->assertIsArray($stats);
        $this->assertEquals(100, $stats['total']);
        $this->assertEquals(25, $stats['upcoming_30_days']);
        $this->assertEquals(10, $stats['recent_submissions']);
        $this->assertEquals(80.0, $stats['approval_rate']);
    }

    public function testSearchContent(): void
    {
        $eventStmt = $this->createMock(\PDOStatement::class);
        $eventStmt->method('bindValue');
        $eventStmt->method('execute');
        $eventStmt->method('fetchAll')->willReturn([
            ['id' => 1, 'title' => 'Test Event', 'start_datetime' => '2023-12-01', 'status' => 'approved']
        ]);
        
        $shopStmt = $this->createMock(\PDOStatement::class);
        $shopStmt->method('bindValue');
        $shopStmt->method('execute');
        $shopStmt->method('fetchAll')->willReturn([
            ['id' => 1, 'name' => 'Test Shop', 'active' => 1, 'featured' => 0]
        ]);
        
        $this->pdo->method('prepare')->willReturnMap([
            ["SELECT id, title, start_datetime, status FROM events WHERE title LIKE :search OR description LIKE :search OR location LIKE :search ORDER BY start_datetime DESC LIMIT :limit", $eventStmt],
            ["SELECT id, name, active, featured FROM shops WHERE name LIKE :search OR description LIKE :search OR address LIKE :search ORDER BY featured DESC, name ASC LIMIT :limit", $shopStmt]
        ]);
        
        $result = $this->adminService->searchContent('test');
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('events', $result);
        $this->assertArrayHasKey('shops', $result);
        $this->assertCount(1, $result['events']);
        $this->assertCount(1, $result['shops']);
    }

    public function testGetPendingModeration(): void
    {
        $pendingStmt = $this->createMock(\PDOStatement::class);
        $pendingStmt->method('fetchAll')->willReturn([
            ['id' => 1, 'title' => 'Pending Event', 'created_at' => '2023-12-01', 'source' => 'manual']
        ]);
        
        $this->pdo->method('query')->willReturn($pendingStmt);
        
        $result = $this->adminService->getPendingModeration();
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('pending_events', $result);
        $this->assertArrayHasKey('flagged_content', $result);
        $this->assertArrayHasKey('total_pending', $result);
        $this->assertCount(1, $result['pending_events']);
    }

    public function testExportData(): void
    {
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->method('execute');
        $stmt->method('fetchAll')->willReturn([
            ['id' => 1, 'title' => 'Test Event']
        ]);
        
        $this->pdo->method('prepare')->willReturn($stmt);
        
        $result = $this->adminService->exportData('events');
        
        $this->assertIsArray($result);
        $this->assertCount(1, $result);
    }

    public function testExportDataInvalidType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown export type: invalid');
        
        $this->adminService->exportData('invalid');
    }

    public function testGetUserStatistics(): void
    {
        $totalStmt = $this->createMock(\PDOStatement::class);
        $totalStmt->method('fetchColumn')->willReturn('500');
        
        $activeStmt = $this->createMock(\PDOStatement::class);
        $activeStmt->method('execute');
        $activeStmt->method('fetchColumn')->willReturn('450');
        
        $roleStmt = $this->createMock(\PDOStatement::class);
        $roleStmt->method('fetchAll')->willReturn([
            'admin' => 5,
            'editor' => 20,
            'user' => 475
        ]);
        
        $this->pdo->method('query')->willReturnMap([
            ["SELECT COUNT(*) FROM users", $totalStmt],
            ["SELECT role, COUNT(*) as count FROM users GROUP BY role", $roleStmt]
        ]);
        
        $this->pdo->method('prepare')->willReturn($activeStmt);
        
        $stats = $this->adminService->getUserStatistics();
        
        $this->assertIsArray($stats);
        $this->assertEquals(500, $stats['total']);
        $this->assertEquals(450, $stats['active_30_days']);
        $this->assertIsArray($stats['by_role']);
    }
}