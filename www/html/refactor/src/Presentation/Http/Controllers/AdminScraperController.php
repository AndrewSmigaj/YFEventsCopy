<?php

declare(strict_types=1);

namespace YFEvents\Presentation\Http\Controllers;

use YFEvents\Infrastructure\Container\ContainerInterface;
use YFEvents\Infrastructure\Config\ConfigInterface;
use YFEvents\Infrastructure\Database\ConnectionInterface;
use YFEvents\Scrapers\ScraperFactory;
use Exception;
use PDO;

/**
 * Admin controller for event scraper management
 */
class AdminScraperController extends BaseController
{
    private PDO $pdo;
    private ScraperFactory $scraperFactory;

    public function __construct(ContainerInterface $container, ConfigInterface $config)
    {
        parent::__construct($container, $config);
        $connection = $container->resolve(ConnectionInterface::class);
        $this->pdo = $connection->getConnection();
        $this->scraperFactory = new ScraperFactory($this->pdo);
    }

    /**
     * Show scrapers management page
     */
    public function index(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        $basePath = dirname($_SERVER['SCRIPT_NAME']);
        if ($basePath === '/') {
            $basePath = '';
        }

        header('Content-Type: text/html; charset=utf-8');
        echo $this->renderScrapersPage($basePath);
    }

    /**
     * Get all scrapers with their status
     */
    public function getScrapers(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        try {
            $sql = "
                SELECT 
                    cs.*,
                    COUNT(DISTINCT e.id) as total_events,
                    COUNT(DISTINCT CASE WHEN e.created_at > DATE_SUB(NOW(), INTERVAL 7 DAY) THEN e.id END) as recent_events,
                    MAX(e.created_at) as last_event_date
                FROM calendar_sources cs
                LEFT JOIN events e ON e.source_id = cs.id
                GROUP BY cs.id
                ORDER BY cs.sort_order, cs.name
            ";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            $scrapers = $stmt->fetchAll();

            $this->successResponse([
                'scrapers' => $scrapers
            ]);

        } catch (Exception $e) {
            $this->errorResponse('Failed to load scrapers: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get single scraper details
     */
    public function getScraper(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        try {
            $id = (int)($_GET['id'] ?? 0);
            
            if ($id <= 0) {
                $this->errorResponse('Invalid scraper ID');
                return;
            }

            $stmt = $this->pdo->prepare("SELECT * FROM calendar_sources WHERE id = :id");
            $stmt->execute(['id' => $id]);
            $scraper = $stmt->fetch();

            if (!$scraper) {
                $this->errorResponse('Scraper not found', 404);
                return;
            }

            // Get recent events from this scraper
            $stmt = $this->pdo->prepare("
                SELECT id, title, start_datetime, created_at 
                FROM events 
                WHERE source_id = :source_id 
                ORDER BY created_at DESC 
                LIMIT 10
            ");
            $stmt->execute(['source_id' => $id]);
            $recentEvents = $stmt->fetchAll();

            $this->successResponse([
                'scraper' => $scraper,
                'recent_events' => $recentEvents
            ]);

        } catch (Exception $e) {
            $this->errorResponse('Failed to load scraper: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Create new scraper
     */
    public function createScraper(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        try {
            $input = $this->getInput();

            $sql = "
                INSERT INTO calendar_sources (
                    name, source_type, source_url, configuration,
                    is_active, sort_order, created_at
                ) VALUES (
                    :name, :source_type, :source_url, :configuration,
                    :is_active, :sort_order, NOW()
                )
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'name' => $input['name'] ?? '',
                'source_type' => $input['source_type'] ?? 'ical',
                'source_url' => $input['source_url'] ?? '',
                'configuration' => json_encode($input['configuration'] ?? []),
                'is_active' => (bool)($input['is_active'] ?? true),
                'sort_order' => (int)($input['sort_order'] ?? 0)
            ]);

            $this->successResponse([
                'message' => 'Scraper created successfully',
                'id' => $this->pdo->lastInsertId()
            ]);

        } catch (Exception $e) {
            $this->errorResponse('Failed to create scraper: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Update scraper
     */
    public function updateScraper(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        try {
            $id = (int)($_GET['id'] ?? 0);
            
            if ($id <= 0) {
                $this->errorResponse('Invalid scraper ID');
                return;
            }

            $input = $this->getInput();

            $sql = "
                UPDATE calendar_sources SET
                    name = :name,
                    source_type = :source_type,
                    source_url = :source_url,
                    configuration = :configuration,
                    is_active = :is_active,
                    sort_order = :sort_order,
                    updated_at = NOW()
                WHERE id = :id
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'id' => $id,
                'name' => $input['name'] ?? '',
                'source_type' => $input['source_type'] ?? 'ical',
                'source_url' => $input['source_url'] ?? '',
                'configuration' => json_encode($input['configuration'] ?? []),
                'is_active' => (bool)($input['is_active'] ?? true),
                'sort_order' => (int)($input['sort_order'] ?? 0)
            ]);

            $this->successResponse([
                'message' => 'Scraper updated successfully'
            ]);

        } catch (Exception $e) {
            $this->errorResponse('Failed to update scraper: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Delete scraper
     */
    public function deleteScraper(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        try {
            $id = (int)($_GET['id'] ?? 0);
            
            if ($id <= 0) {
                $this->errorResponse('Invalid scraper ID');
                return;
            }

            // Check if scraper has events
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM events WHERE source_id = :id");
            $stmt->execute(['id' => $id]);
            $eventCount = $stmt->fetchColumn();

            if ($eventCount > 0) {
                $this->errorResponse('Cannot delete scraper with existing events');
                return;
            }

            $stmt = $this->pdo->prepare("DELETE FROM calendar_sources WHERE id = :id");
            $stmt->execute(['id' => $id]);

            $this->successResponse([
                'message' => 'Scraper deleted successfully'
            ]);

        } catch (Exception $e) {
            $this->errorResponse('Failed to delete scraper: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Run scraper manually
     */
    public function runScraper(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        try {
            $id = (int)($_GET['id'] ?? 0);
            
            if ($id <= 0) {
                $this->errorResponse('Invalid scraper ID');
                return;
            }

            // Get scraper details
            $stmt = $this->pdo->prepare("SELECT * FROM calendar_sources WHERE id = :id");
            $stmt->execute(['id' => $id]);
            $source = $stmt->fetch();

            if (!$source || !$source['is_active']) {
                $this->errorResponse('Scraper not found or inactive');
                return;
            }

            // Run the scraper
            $scraper = $this->scraperFactory->createScraper($source);
            $events = $scraper->scrape();

            // Process events
            $created = 0;
            $updated = 0;
            $errors = [];

            foreach ($events as $event) {
                try {
                    // Check if event exists
                    $stmt = $this->pdo->prepare("
                        SELECT id FROM events 
                        WHERE source_id = :source_id 
                        AND source_event_id = :source_event_id
                    ");
                    $stmt->execute([
                        'source_id' => $id,
                        'source_event_id' => $event['source_event_id'] ?? md5($event['title'] . $event['start_datetime'])
                    ]);
                    
                    if ($existingId = $stmt->fetchColumn()) {
                        // Update existing event
                        $this->updateEvent($existingId, $event);
                        $updated++;
                    } else {
                        // Create new event
                        $this->createEvent($id, $event);
                        $created++;
                    }
                } catch (Exception $e) {
                    $errors[] = "Error processing '{$event['title']}': " . $e->getMessage();
                }
            }

            // Update last run time
            $stmt = $this->pdo->prepare("
                UPDATE calendar_sources 
                SET last_scraped_at = NOW() 
                WHERE id = :id
            ");
            $stmt->execute(['id' => $id]);

            $this->successResponse([
                'message' => 'Scraper run completed',
                'created' => $created,
                'updated' => $updated,
                'errors' => $errors,
                'total_processed' => count($events)
            ]);

        } catch (Exception $e) {
            $this->errorResponse('Failed to run scraper: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Test scraper configuration
     */
    public function testScraper(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        try {
            $input = $this->getInput();

            // Create temporary source for testing
            $source = [
                'id' => 0,
                'name' => 'Test',
                'source_type' => $input['source_type'] ?? 'ical',
                'source_url' => $input['source_url'] ?? '',
                'configuration' => json_encode($input['configuration'] ?? []),
                'is_active' => true
            ];

            $scraper = $this->scraperFactory->createScraper($source);
            $events = $scraper->scrape();

            $this->successResponse([
                'success' => true,
                'event_count' => count($events),
                'sample_events' => array_slice($events, 0, 5)
            ]);

        } catch (Exception $e) {
            $this->errorResponse('Test failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get scraper statistics
     */
    public function getStatistics(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        try {
            // Overall statistics
            $stats = [];

            // Total scrapers
            $stmt = $this->pdo->query("SELECT COUNT(*) as total, SUM(is_active) as active FROM calendar_sources");
            $stats['scrapers'] = $stmt->fetch();

            // Events by source
            $stmt = $this->pdo->query("
                SELECT 
                    cs.name,
                    COUNT(e.id) as event_count,
                    MAX(e.created_at) as last_event
                FROM calendar_sources cs
                LEFT JOIN events e ON e.source_id = cs.id
                GROUP BY cs.id
                ORDER BY event_count DESC
                LIMIT 10
            ");
            $stats['events_by_source'] = $stmt->fetchAll();

            // Recent scraping activity
            $stmt = $this->pdo->query("
                SELECT 
                    DATE(created_at) as date,
                    COUNT(*) as events_created
                FROM events
                WHERE created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
                GROUP BY DATE(created_at)
                ORDER BY date DESC
            ");
            $stats['recent_activity'] = $stmt->fetchAll();

            // Error log
            $stmt = $this->pdo->query("
                SELECT * FROM scraper_logs 
                WHERE level = 'error' 
                AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
                ORDER BY created_at DESC
                LIMIT 10
            ");
            $stats['recent_errors'] = $stmt->fetchAll();

            $this->successResponse([
                'statistics' => $stats
            ]);

        } catch (Exception $e) {
            $this->errorResponse('Failed to load statistics: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Create event from scraper data
     */
    private function createEvent(int $sourceId, array $eventData): void
    {
        $sql = "
            INSERT INTO events (
                source_id, source_event_id, title, description,
                start_datetime, end_datetime, location_name,
                location_address, location_latitude, location_longitude,
                organizer_name, organizer_email, category_id,
                status, created_at
            ) VALUES (
                :source_id, :source_event_id, :title, :description,
                :start_datetime, :end_datetime, :location_name,
                :location_address, :location_latitude, :location_longitude,
                :organizer_name, :organizer_email, :category_id,
                'pending', NOW()
            )
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'source_id' => $sourceId,
            'source_event_id' => $eventData['source_event_id'] ?? md5($eventData['title'] . $eventData['start_datetime']),
            'title' => $eventData['title'],
            'description' => $eventData['description'] ?? '',
            'start_datetime' => $eventData['start_datetime'],
            'end_datetime' => $eventData['end_datetime'] ?? null,
            'location_name' => $eventData['location_name'] ?? '',
            'location_address' => $eventData['location_address'] ?? '',
            'location_latitude' => $eventData['location_latitude'] ?? null,
            'location_longitude' => $eventData['location_longitude'] ?? null,
            'organizer_name' => $eventData['organizer_name'] ?? '',
            'organizer_email' => $eventData['organizer_email'] ?? '',
            'category_id' => $eventData['category_id'] ?? 1
        ]);
    }

    /**
     * Update existing event
     */
    private function updateEvent(int $eventId, array $eventData): void
    {
        $sql = "
            UPDATE events SET
                title = :title,
                description = :description,
                start_datetime = :start_datetime,
                end_datetime = :end_datetime,
                location_name = :location_name,
                location_address = :location_address,
                location_latitude = :location_latitude,
                location_longitude = :location_longitude,
                organizer_name = :organizer_name,
                organizer_email = :organizer_email,
                updated_at = NOW()
            WHERE id = :id
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'id' => $eventId,
            'title' => $eventData['title'],
            'description' => $eventData['description'] ?? '',
            'start_datetime' => $eventData['start_datetime'],
            'end_datetime' => $eventData['end_datetime'] ?? null,
            'location_name' => $eventData['location_name'] ?? '',
            'location_address' => $eventData['location_address'] ?? '',
            'location_latitude' => $eventData['location_latitude'] ?? null,
            'location_longitude' => $eventData['location_longitude'] ?? null,
            'organizer_name' => $eventData['organizer_name'] ?? '',
            'organizer_email' => $eventData['organizer_email'] ?? ''
        ]);
    }

    /**
     * Render scrapers management page
     */
    private function renderScrapersPage(string $basePath): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Scrapers - YFEvents Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .admin-header {
            background: linear-gradient(135deg, #dc3545 0%, #6f42c1 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
        }
        .scraper-card {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .scraper-active {
            border-left: 4px solid #28a745;
        }
        .scraper-inactive {
            border-left: 4px solid #dc3545;
            opacity: 0.8;
        }
        .source-type {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 500;
        }
        .type-ical { background: #e3f2fd; color: #1976d2; }
        .type-html { background: #fff3e0; color: #f57c00; }
        .type-json { background: #f3e5f5; color: #7b1fa2; }
        .type-intelligent { background: #e8f5e9; color: #388e3c; }
        .stats-badge {
            display: inline-block;
            margin-right: 1rem;
            font-size: 0.875rem;
        }
        .last-run {
            color: #6c757d;
            font-size: 0.875rem;
        }
    </style>
</head>
<body>
    <div class="admin-header">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <h1 class="h3 mb-0">Event Scrapers</h1>
                <a href="{$basePath}/admin/dashboard" class="btn btn-light">
                    <i class="bi bi-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- Controls -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <h5 class="mb-0">Manage Event Sources</h5>
                        <p class="text-muted mb-0">Configure automated event collection from various sources</p>
                    </div>
                    <div class="col-md-6 text-end">
                        <button class="btn btn-success" onclick="runAllScrapers()">
                            <i class="bi bi-play-fill"></i> Run All Active
                        </button>
                        <button class="btn btn-primary" onclick="showCreateScraperModal()">
                            <i class="bi bi-plus-circle"></i> Add Scraper
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistics -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h3 class="mb-0" id="total-scrapers">-</h3>
                        <p class="text-muted mb-0">Total Scrapers</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h3 class="mb-0 text-success" id="active-scrapers">-</h3>
                        <p class="text-muted mb-0">Active</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h3 class="mb-0" id="total-events">-</h3>
                        <p class="text-muted mb-0">Total Events</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h3 class="mb-0" id="recent-events">-</h3>
                        <p class="text-muted mb-0">This Week</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Scrapers List -->
        <div id="scrapers-container">
            <div class="text-center p-5">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Create/Edit Scraper Modal -->
    <div class="modal fade" id="scraperModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="scraperModalTitle">Create New Scraper</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="scraperForm">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="scraper-name" class="form-label">Name</label>
                            <input type="text" class="form-control" id="scraper-name" required>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="source-type" class="form-label">Source Type</label>
                                    <select class="form-select" id="source-type" required onchange="updateConfigFields()">
                                        <option value="ical">iCal Feed</option>
                                        <option value="html">HTML Scraping</option>
                                        <option value="json">JSON API</option>
                                        <option value="intelligent">AI Scraper</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="sort-order" class="form-label">Sort Order</label>
                                    <input type="number" class="form-control" id="sort-order" value="0">
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="source-url" class="form-label">Source URL</label>
                            <input type="url" class="form-control" id="source-url" required>
                        </div>

                        <!-- Configuration Fields (dynamic based on type) -->
                        <div id="config-fields">
                            <!-- Will be populated based on source type -->
                        </div>

                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="is-active" checked>
                            <label class="form-check-label" for="is-active">
                                Active
                            </label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" onclick="testConfiguration()">
                            <i class="bi bi-play"></i> Test
                        </button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Scraper</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const basePath = '{$basePath}';
        let currentScraperId = null;
        let isEditMode = false;

        document.addEventListener('DOMContentLoaded', () => {
            loadScrapers();
            loadStatistics();
            document.getElementById('scraperForm').addEventListener('submit', handleScraperSubmit);
        });

        async function loadScrapers() {
            try {
                const response = await fetch(`\${basePath}/admin/scrapers/list`);
                const data = await response.json();

                if (data.success) {
                    renderScrapers(data.scrapers);
                } else {
                    showError('Failed to load scrapers');
                }
            } catch (error) {
                showError('Network error: ' + error.message);
            }
        }

        async function loadStatistics() {
            try {
                const response = await fetch(`\${basePath}/admin/scrapers/statistics`);
                const data = await response.json();

                if (data.success) {
                    const stats = data.statistics;
                    document.getElementById('total-scrapers').textContent = stats.scrapers.total || 0;
                    document.getElementById('active-scrapers').textContent = stats.scrapers.active || 0;
                    
                    // Calculate total events
                    const totalEvents = stats.events_by_source.reduce((sum, s) => sum + parseInt(s.event_count), 0);
                    document.getElementById('total-events').textContent = totalEvents;
                    
                    // Calculate recent events
                    const recentEvents = stats.recent_activity.reduce((sum, a) => sum + parseInt(a.events_created), 0);
                    document.getElementById('recent-events').textContent = recentEvents;
                }
            } catch (error) {
                console.error('Failed to load statistics:', error);
            }
        }

        function renderScrapers(scrapers) {
            const container = document.getElementById('scrapers-container');
            
            if (scrapers.length === 0) {
                container.innerHTML = '<div class="alert alert-info">No scrapers configured yet</div>';
                return;
            }

            container.innerHTML = scrapers.map(scraper => `
                <div class="scraper-card \${scraper.is_active == '1' ? 'scraper-active' : 'scraper-inactive'}">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="flex-grow-1">
                            <h5 class="mb-2">
                                \${escapeHtml(scraper.name)}
                                <span class="source-type type-\${scraper.source_type}">\${scraper.source_type.toUpperCase()}</span>
                            </h5>
                            <p class="mb-2 text-muted">
                                <i class="bi bi-link-45deg"></i> \${escapeHtml(scraper.source_url)}
                            </p>
                            <div class="stats">
                                <span class="stats-badge">
                                    <i class="bi bi-calendar-check"></i> \${scraper.total_events || 0} total events
                                </span>
                                <span class="stats-badge">
                                    <i class="bi bi-calendar-plus"></i> \${scraper.recent_events || 0} this week
                                </span>
                                \${scraper.last_scraped_at ? `
                                    <span class="last-run">
                                        <i class="bi bi-clock"></i> Last run: \${new Date(scraper.last_scraped_at).toLocaleString()}
                                    </span>
                                ` : ''}
                            </div>
                        </div>
                        <div class="d-flex gap-2">
                            <button class="btn btn-sm btn-outline-success" onclick="runScraper(\${scraper.id})" 
                                    \${scraper.is_active != '1' ? 'disabled' : ''}>
                                <i class="bi bi-play"></i> Run
                            </button>
                            <button class="btn btn-sm btn-outline-primary" onclick="editScraper(\${scraper.id})">
                                <i class="bi bi-pencil"></i> Edit
                            </button>
                            <button class="btn btn-sm btn-outline-danger" onclick="confirmDelete(\${scraper.id})">
                                <i class="bi bi-trash"></i> Delete
                            </button>
                        </div>
                    </div>
                </div>
            `).join('');
        }

        function showCreateScraperModal() {
            isEditMode = false;
            currentScraperId = null;
            document.getElementById('scraperModalTitle').textContent = 'Create New Scraper';
            document.getElementById('scraperForm').reset();
            document.getElementById('is-active').checked = true;
            updateConfigFields();
            
            const modal = new bootstrap.Modal(document.getElementById('scraperModal'));
            modal.show();
        }

        async function editScraper(id) {
            try {
                const response = await fetch(`\${basePath}/admin/scrapers/\${id}`);
                const data = await response.json();

                if (data.success) {
                    isEditMode = true;
                    currentScraperId = id;
                    
                    const scraper = data.scraper;
                    document.getElementById('scraperModalTitle').textContent = 'Edit Scraper';
                    document.getElementById('scraper-name').value = scraper.name;
                    document.getElementById('source-type').value = scraper.source_type;
                    document.getElementById('source-url').value = scraper.source_url;
                    document.getElementById('sort-order').value = scraper.sort_order;
                    document.getElementById('is-active').checked = scraper.is_active == '1';
                    
                    updateConfigFields();
                    
                    // Load configuration values
                    const config = JSON.parse(scraper.configuration || '{}');
                    for (const [key, value] of Object.entries(config)) {
                        const field = document.getElementById(`config-\${key}`);
                        if (field) {
                            field.value = value;
                        }
                    }
                    
                    const modal = new bootstrap.Modal(document.getElementById('scraperModal'));
                    modal.show();
                } else {
                    showError(data.error || 'Failed to load scraper');
                }
            } catch (error) {
                showError('Network error: ' + error.message);
            }
        }

        function updateConfigFields() {
            const sourceType = document.getElementById('source-type').value;
            const container = document.getElementById('config-fields');
            
            let html = '<h6 class="mb-3">Configuration</h6>';
            
            switch (sourceType) {
                case 'html':
                    html += `
                        <div class="mb-3">
                            <label for="config-event_selector" class="form-label">Event Selector (CSS)</label>
                            <input type="text" class="form-control" id="config-event_selector" placeholder=".event-item">
                        </div>
                        <div class="mb-3">
                            <label for="config-title_selector" class="form-label">Title Selector</label>
                            <input type="text" class="form-control" id="config-title_selector" placeholder=".event-title">
                        </div>
                        <div class="mb-3">
                            <label for="config-date_selector" class="form-label">Date Selector</label>
                            <input type="text" class="form-control" id="config-date_selector" placeholder=".event-date">
                        </div>
                    `;
                    break;
                    
                case 'json':
                    html += `
                        <div class="mb-3">
                            <label for="config-events_path" class="form-label">Events Path</label>
                            <input type="text" class="form-control" id="config-events_path" placeholder="data.events">
                        </div>
                        <div class="mb-3">
                            <label for="config-auth_header" class="form-label">Auth Header (optional)</label>
                            <input type="text" class="form-control" id="config-auth_header" placeholder="Bearer YOUR_TOKEN">
                        </div>
                    `;
                    break;
                    
                case 'intelligent':
                    html += `
                        <div class="mb-3">
                            <label for="config-prompt" class="form-label">AI Prompt</label>
                            <textarea class="form-control" id="config-prompt" rows="3" 
                                placeholder="Extract all events with their titles, dates, times, and locations..."></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="config-api_key" class="form-label">API Key</label>
                            <input type="password" class="form-control" id="config-api_key">
                        </div>
                    `;
                    break;
                    
                case 'ical':
                default:
                    html += '<p class="text-muted">No additional configuration required for iCal feeds</p>';
                    break;
            }
            
            container.innerHTML = html;
        }

        async function handleScraperSubmit(e) {
            e.preventDefault();
            
            const sourceType = document.getElementById('source-type').value;
            const configuration = {};
            
            // Collect configuration based on source type
            const configFields = document.querySelectorAll('[id^="config-"]');
            configFields.forEach(field => {
                const key = field.id.replace('config-', '');
                configuration[key] = field.value;
            });
            
            const scraperData = {
                name: document.getElementById('scraper-name').value,
                source_type: sourceType,
                source_url: document.getElementById('source-url').value,
                sort_order: document.getElementById('sort-order').value,
                is_active: document.getElementById('is-active').checked,
                configuration: configuration
            };

            try {
                const url = isEditMode 
                    ? `\${basePath}/admin/scrapers/\${currentScraperId}/update`
                    : `\${basePath}/admin/scrapers/create`;
                    
                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(scraperData)
                });

                const data = await response.json();

                if (data.success) {
                    bootstrap.Modal.getInstance(document.getElementById('scraperModal')).hide();
                    showSuccess(data.message);
                    loadScrapers();
                    loadStatistics();
                } else {
                    showError(data.error || 'Failed to save scraper');
                }
            } catch (error) {
                showError('Network error: ' + error.message);
            }
        }

        async function runScraper(id) {
            if (!confirm('Run this scraper now? This may take a few moments.')) {
                return;
            }

            try {
                const response = await fetch(`\${basePath}/admin/scrapers/\${id}/run`, {
                    method: 'POST'
                });

                const data = await response.json();

                if (data.success) {
                    let message = `Scraper completed!\\n\\nCreated: \${data.created} events\\nUpdated: \${data.updated} events`;
                    if (data.errors.length > 0) {
                        message += `\\n\\nErrors:\\n\${data.errors.join('\\n')}`;
                    }
                    alert(message);
                    loadScrapers();
                    loadStatistics();
                } else {
                    showError(data.error || 'Failed to run scraper');
                }
            } catch (error) {
                showError('Network error: ' + error.message);
            }
        }

        async function runAllScrapers() {
            if (!confirm('Run all active scrapers? This may take several minutes.')) {
                return;
            }

            alert('Running all scrapers in the background. Check back in a few minutes for results.');
            
            // TODO: Implement background job processing
        }

        async function testConfiguration() {
            const sourceType = document.getElementById('source-type').value;
            const configuration = {};
            
            // Collect configuration
            const configFields = document.querySelectorAll('[id^="config-"]');
            configFields.forEach(field => {
                const key = field.id.replace('config-', '');
                configuration[key] = field.value;
            });
            
            const testData = {
                source_type: sourceType,
                source_url: document.getElementById('source-url').value,
                configuration: configuration
            };

            try {
                const response = await fetch(`\${basePath}/admin/scrapers/test`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(testData)
                });

                const data = await response.json();

                if (data.success) {
                    alert(`Test successful! Found \${data.event_count} events.\\n\\nSample events:\\n` + 
                          data.sample_events.map(e => `- \${e.title} (\${e.start_datetime})`).join('\\n'));
                } else {
                    showError('Test failed: ' + (data.error || 'Unknown error'));
                }
            } catch (error) {
                showError('Network error: ' + error.message);
            }
        }

        async function confirmDelete(id) {
            if (!confirm('Are you sure you want to delete this scraper?')) {
                return;
            }

            try {
                const response = await fetch(`\${basePath}/admin/scrapers/\${id}/delete`, {
                    method: 'POST'
                });

                const data = await response.json();

                if (data.success) {
                    showSuccess('Scraper deleted successfully');
                    loadScrapers();
                    loadStatistics();
                } else {
                    showError(data.error || 'Failed to delete scraper');
                }
            } catch (error) {
                showError('Network error: ' + error.message);
            }
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function showSuccess(message) {
            alert(message);
        }

        function showError(message) {
            alert('Error: ' + message);
        }
    </script>
</body>
</html>
HTML;
    }
}