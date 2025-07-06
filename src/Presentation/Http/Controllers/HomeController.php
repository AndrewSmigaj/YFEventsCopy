<?php

declare(strict_types=1);

namespace YFEvents\Presentation\Http\Controllers;

use YFEvents\Infrastructure\Container\ContainerInterface;
use YFEvents\Infrastructure\Config\ConfigInterface;
use YFEvents\Infrastructure\Database\ConnectionInterface;
use YFEvents\Domain\Events\EventServiceInterface;
use YFEvents\Domain\Shops\ShopServiceInterface;
use YFEvents\Application\Services\ClaimService;

class HomeController
{
    use HomeControllerFixed;
    private EventServiceInterface $eventService;
    private ShopServiceInterface $shopService;
    private ClaimService $claimService;
    
    public function __construct(
        private ContainerInterface $container,
        private ConfigInterface $config
    ) {
        // Inject services through container
        $this->eventService = $container->resolve(EventServiceInterface::class);
        $this->shopService = $container->resolve(ShopServiceInterface::class);
        $this->claimService = $container->resolve(ClaimService::class);
    }

    /**
     * Display the home page
     */
    public function index(): void
    {
        try {
            // Fetch all dynamic data
            $data = [
                'featuredItems' => $this->getFeaturedItems(),
                'upcomingSales' => $this->getUpcomingSales(),
                'upcomingEvents' => $this->getUpcomingEvents(),
                'currentSales' => $this->getCurrentSales(),
                'featuredShops' => $this->getFeaturedShops(),
                'stats' => $this->getDynamicStats(),
                'hotItem' => $this->getHotItem()
            ];
            
            header('Content-Type: text/html; charset=utf-8');
            echo $this->renderHomePageFixed($data);
            
        } catch (\Exception $e) {
            // Fallback to static content if services fail
            error_log("Homepage dynamic content error: " . $e->getMessage());
            error_log("Error trace: " . $e->getTraceAsString());
            header('Content-Type: text/html; charset=utf-8');
            echo $this->renderHomePageFixed();
        }
    }

    /**
     * Health check endpoint
     */
    public function health(): void
    {
        header('Content-Type: application/json');
        
        try {
            // Test database connection
            $connection = $this->container->resolve(\YFEvents\Infrastructure\Database\ConnectionInterface::class);
            $pdo = $connection->getConnection();
            $pdo->query("SELECT 1");
            
            echo json_encode([
                'status' => 'healthy',
                'timestamp' => date('Y-m-d H:i:s'),
                'version' => '2.0.0',
                'database' => 'connected',
                'php_version' => PHP_VERSION
            ]);
        } catch (\Exception $e) {
            http_response_code(503);
            echo json_encode([
                'status' => 'unhealthy',
                'timestamp' => date('Y-m-d H:i:s'),
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Combined events and shops map view
     */
    public function showCombinedMap(): void
    {
        header('Content-Type: text/html; charset=utf-8');
        echo $this->renderCombinedMapPage();
    }

    /**
     * Debug routing information
     */
    public function debug(): void
    {
        header('Content-Type: application/json');
        
        try {
            $currentPath = $_SERVER['REQUEST_URI'] ?? '/';
            if (($pos = strpos($currentPath, '?')) !== false) {
                $currentPath = substr($currentPath, 0, $pos);
            }
            
            // Strip base path if running in subdirectory
            $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
            $basePath = dirname($scriptName);
            
            if ($basePath !== '/' && strpos($currentPath, $basePath) === 0) {
                $currentPath = substr($currentPath, strlen($basePath));
            }
            
            if (empty($currentPath) || $currentPath[0] !== '/') {
                $currentPath = '/' . $currentPath;
            }
            
            // Test database configuration
            $dbConfig = [
                'host' => $this->config->get('database.host'),
                'name' => $this->config->get('database.name'),
                'username' => $this->config->get('database.username'),
                'password' => $this->config->get('database.password') ? '[PASSWORD SET]' : '[NO PASSWORD]'
            ];

            echo json_encode([
                'current_path' => $currentPath,
                'method' => $_SERVER['REQUEST_METHOD'],
                'script_name' => $_SERVER['SCRIPT_NAME'],
                'dirname_script_name' => dirname($_SERVER['SCRIPT_NAME']),
                'base_path' => $basePath,
                'database_config' => $dbConfig,
                'config_debug' => $this->config->get('app.debug'),
                'message' => 'Debug route is working! Admin routes should work now.'
            ], JSON_PRETTY_PRINT);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Get featured items from all active sales
     */
    private function getFeaturedItems(int $limit = 12): array
    {
        try {
            $items = $this->claimService->getPopularItems($limit);
            
            // Convert Item objects to arrays for the view
            $itemArrays = [];
            foreach ($items as $item) {
                $itemArrays[] = $item->toArray();
            }
            
            return $itemArrays;
        } catch (\Exception $e) {
            error_log("Failed to get featured items: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get upcoming sales for the next 7 days
     */
    private function getUpcomingSales(int $limit = 3): array
    {
        try {
            $sales = $this->claimService->getUpcomingSales(7);
            return array_slice($sales, 0, $limit);
        } catch (\Exception $e) {
            error_log("Failed to get upcoming sales: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get upcoming events
     */
    private function getUpcomingEvents(int $limit = 5): array
    {
        try {
            $events = $this->eventService->getUpcomingEvents($limit);
            // Convert Event objects to arrays for the view
            return array_map(fn($event) => $event->toArray(), $events);
        } catch (\Exception $e) {
            error_log("Failed to get upcoming events: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get current active sales
     */
    private function getCurrentSales(): array
    {
        try {
            $result = $this->claimService->getActiveSales(1, 20);
            $sales = [];
            foreach ($result->getItems() as $sale) {
                $saleArray = $sale->toArray();
                // Add some additional fields for the view
                // company_name is now in stats from the repository
                $saleArray['company_name'] = $saleArray['stats']['company_name'] ?? 'Estate Sale Company';
                $saleArray['start_date'] = $saleArray['claim_start_date'] ?? null;
                $saleArray['end_date'] = $saleArray['claim_end_date'] ?? null;
                $saleArray['hours'] = '9am-5pm'; // Default hours
                $locationParts = explode(',', $saleArray['location'] ?? '');
                $saleArray['address'] = trim($locationParts[0] ?? '');
                $saleArray['city'] = trim($locationParts[1] ?? 'Yakima');
                // item_count is in stats from repository
                $saleArray['item_count'] = $saleArray['stats']['item_count'] ?? 0;
                $sales[] = $saleArray;
            }
            
            // Enrich sales with item previews
            $sales = $this->enrichSalesWithItemPreviews($sales);
            
            return $sales;
        } catch (\Exception $e) {
            error_log("Failed to get current sales: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Enrich sales with item preview images
     */
    private function enrichSalesWithItemPreviews(array $sales): array
    {
        if (empty($sales)) {
            return $sales;
        }
        
        try {
            // Get item repository
            $itemRepo = $this->container->resolve(\YFEvents\Infrastructure\Repositories\Claims\ItemRepository::class);
            
            // Fetch previews for each sale
            foreach ($sales as &$sale) {
                if (isset($sale['id'])) {
                    $sale['item_previews'] = $itemRepo->getItemPreviews((int)$sale['id'], 4);
                } else {
                    $sale['item_previews'] = [];
                }
            }
            
            return $sales;
        } catch (\Exception $e) {
            error_log("Failed to enrich sales with item previews: " . $e->getMessage());
            // Return sales without previews rather than failing completely
            return $sales;
        }
    }
    
    /**
     * Get featured shops
     */
    private function getFeaturedShops(int $limit = 4): array
    {
        try {
            return $this->shopService->getFeaturedShops($limit);
        } catch (\Exception $e) {
            error_log("Failed to get featured shops: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get single hot item for sidebar spotlight
     */
    private function getHotItem(): ?array
    {
        $featured = $this->getFeaturedItems(1);
        return !empty($featured) ? $featured[0] : null;
    }
    
    /**
     * Get dynamic statistics for the homepage
     */
    private function getDynamicStats(): array
    {
        try {
            // Get shop statistics
            $shopStats = $this->shopService->getShopStatistics();
            
            // Get active sales count
            $activeSales = $this->claimService->getActiveSales(1, 1);
            
            // Get upcoming events count
            $upcomingEvents = $this->eventService->getUpcomingEvents(100);
            
            // Estimate total items (from active sales stats)
            $totalItems = 0;
            $fullActiveSales = $this->claimService->getActiveSales(1, 100);
            foreach ($fullActiveSales->getItems() as $sale) {
                // If sale has item_count property from repository query
                if (property_exists($sale, 'item_count')) {
                    $totalItems += $sale->item_count;
                }
            }
            
            return [
                'active_sales' => $activeSales->getTotal(),
                'upcoming_events' => count($upcomingEvents),
                'total_items' => $totalItems ?: 2341, // Fallback to static if no data
                'local_shops' => $shopStats['total'] ?? 89
            ];
        } catch (\Exception $e) {
            error_log("Failed to get dynamic stats: " . $e->getMessage());
            // Return static defaults if services fail
            return [
                'active_sales' => 47,
                'upcoming_events' => 156,
                'total_items' => 2341,
                'local_shops' => 89
            ];
        }
    }

    private function renderHomePage(array $data = []): string
    {
        error_log("DEBUG: renderHomePage called with data keys: " . implode(', ', array_keys($data)));
        
        // Extract data with defaults
        $stats = $data['stats'] ?? [
            'active_sales' => 47,
            'upcoming_events' => 156,
            'total_items' => 2341,
            'local_shops' => 89
        ];
        
        // Extract other data arrays
        $featuredItems = $data['featuredItems'] ?? [];
        $currentSales = $data['currentSales'] ?? [];
        $upcomingEvents = $data['upcomingEvents'] ?? [];
        $hotItem = $data['hotItem'] ?? null;
        
        error_log("DEBUG: Extracted featuredItems count: " . count($featuredItems));
        
        // Format numbers
        $formattedItems = number_format($stats['total_items']);
        
        error_log("DEBUG: About to start HTML generation");
        
        // Break heredoc into pieces to find issue
        $html = '';
        
        // Part 1: DOCTYPE to start of body
        $html .= <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Yakima Valley Estate Sales & Events | YFEvents</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Georgia, 'Times New Roman', serif;
            background: #FFF8DC;
            color: #1B2951;
            line-height: 1.6;
            min-height: 100vh;
        }
        
        /* Header/Hero Section */
        .hero {
            background: linear-gradient(rgba(27, 41, 81, 0.9), rgba(27, 41, 81, 0.9)), url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 400"><rect fill="%23FFF8DC" width="1200" height="400"/><path fill="%23B87333" opacity="0.1" d="M0,200 Q300,150 600,200 T1200,200 L1200,400 L0,400 Z"/></svg>');
            background-size: cover;
            color: white;
            padding: 60px 20px;
            text-align: center;
        }
        
        .hero h1 {
            font-size: 3.5rem;
            font-weight: normal;
            margin-bottom: 20px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
        
        .hero .tagline {
            font-size: 1.3rem;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            opacity: 0.95;
            margin-bottom: 40px;
        }
        
        .hero-buttons {
            display: flex;
            gap: 20px;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .hero-btn {
            background: #B87333;
            color: white;
            padding: 15px 30px;
            border-radius: 5px;
            text-decoration: none;
            font-size: 1.1rem;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }
        
        .hero-btn:hover {
            background: transparent;
            border-color: #B87333;
            transform: translateY(-2px);
        }
        
        .hero-btn.secondary {
            background: transparent;
            border: 2px solid white;
        }
        
        .hero-btn.secondary:hover {
            background: white;
            color: #1B2951;
        }
        
        /* Main Container */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 60px 20px;
        }
        
        /* Quick Stats Bar */
        .stats-bar {
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            padding: 30px;
            margin: -40px auto 60px;
            max-width: 900px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 30px;
            text-align: center;
        }
        
        .stat {
            border-right: 1px solid #e9ecef;
        }
        
        .stat:last-child {
            border-right: none;
        }
        
        .stat-number {
            font-size: 2.5rem;
            color: #B87333;
            font-weight: bold;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        
        .stat-label {
            color: #6c757d;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        /* Services Grid */
        .services {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 30px;
            margin-bottom: 60px;
        }
        
        .service-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            padding: 40px 30px;
            text-align: center;
            transition: all 0.3s ease;
            border-top: 4px solid transparent;
        }
        
        .service-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.15);
        }
        
        .service-card.estate-sales {
            border-top-color: #B87333;
        }
        
        .service-card.events {
            border-top-color: #1B2951;
        }
        
        .service-card.shops {
            border-top-color: #4A6FA5;
        }
        
        .service-card.sellers {
            border-top-color: #8B4513;
        }
        
        .service-icon {
            font-size: 3rem;
            margin-bottom: 20px;
        }
        
        .service-title {
            font-size: 1.5rem;
            margin-bottom: 15px;
            color: #1B2951;
        }
        
        .service-desc {
            color: #6c757d;
            margin-bottom: 25px;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        
        .service-links {
            list-style: none;
        }
        
        .service-links li {
            margin-bottom: 10px;
        }
        
        .service-links a {
            color: #B87333;
            text-decoration: none;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            transition: color 0.3s ease;
        }
        
        .service-links a:hover {
            color: #8B4513;
            text-decoration: underline;
        }
        
        .feature {
            padding: 20px;
            border-radius: 15px;
            background: #f8f9fa;
            border: 1px solid #e9ecef;
        }
        
        .feature-icon {
            font-size: 2rem;
            margin-bottom: 10px;
        }
        
        .feature-title {
            font-weight: 600;
            color: #343a40;
            margin-bottom: 10px;
        }
        
        .feature-desc {
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        /* Trust Section */
        .trust-section {
            background: #1B2951;
            color: white;
            padding: 40px;
            border-radius: 10px;
            margin: 60px 0;
            text-align: center;
        }
        
        .trust-title {
            font-size: 1.8rem;
            margin-bottom: 30px;
        }
        
        .trust-items {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 30px;
        }
        
        .trust-item {
            text-align: center;
        }
        
        .trust-icon {
            font-size: 2.5rem;
            margin-bottom: 10px;
        }
        
        .trust-text {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            font-size: 0.9rem;
            opacity: 0.9;
        }
        
        /* Dashboard Grid Layout */
        .dashboard-grid {
            display: grid;
            grid-template-columns: 65% 35%;
            gap: 30px;
            margin: 60px 0;
        }
        
        .lower-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin: 40px 0;
        }
        
        /* Featured Items Section */
        .featured-items {
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            padding: 30px;
        }
        
        .featured-items h2 {
            color: #1B2951;
            margin-bottom: 25px;
            font-size: 2rem;
        }
        
        .items-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .item-card {
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            cursor: pointer;
        }
        
        .item-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        .item-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
            background: #f8f9fa;
        }
        
        .no-image-placeholder {
            width: 100%;
            height: 200px;
            background: #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6c757d;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        
        .item-details {
            padding: 15px;
            background: white;
        }
        
        .item-title {
            font-size: 1rem;
            margin-bottom: 5px;
            color: #1B2951;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .item-price {
            font-size: 1.2rem;
            color: #B87333;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .item-sale {
            font-size: 0.85rem;
            color: #6c757d;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        
        /* Spotlight Sidebar */
        .spotlight-sidebar {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        
        .weekend-box {
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            padding: 25px;
        }
        
        .weekend-box h3 {
            color: #1B2951;
            margin-bottom: 15px;
            font-size: 1.5rem;
        }
        
        .weekend-stats {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            color: #6c757d;
            margin-bottom: 20px;
        }
        
        .hot-item {
            border-top: 1px solid #e9ecef;
            padding-top: 20px;
        }
        
        .hot-item h4 {
            color: #B87333;
            margin-bottom: 10px;
            font-size: 1.1rem;
        }
        
        .quick-actions {
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            padding: 25px;
        }
        
        .quick-actions a {
            display: block;
            padding: 12px 20px;
            margin-bottom: 10px;
            background: #f8f9fa;
            border-radius: 5px;
            color: #1B2951;
            text-decoration: none;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            transition: background 0.3s ease;
        }
        
        .quick-actions a:hover {
            background: #e9ecef;
        }
        
        .quick-actions a.primary {
            background: #B87333;
            color: white;
        }
        
        .quick-actions a.primary:hover {
            background: #8B4513;
        }
        
        /* Active Sales & Events Sections */
        .active-sales, .upcoming-events {
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            padding: 25px;
        }
        
        .active-sales h2, .upcoming-events h2 {
            color: #1B2951;
            margin-bottom: 20px;
            font-size: 1.5rem;
        }
        
        .sales-list, .events-list {
            list-style: none;
        }
        
        .sale-entry, .event-entry {
            padding: 15px 0;
            border-bottom: 1px solid #e9ecef;
        }
        
        .sale-entry:last-child, .event-entry:last-child {
            border-bottom: none;
        }
        
        .sale-title, .event-title {
            font-size: 1.1rem;
            color: #1B2951;
            margin-bottom: 5px;
        }
        
        .sale-details, .event-details {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        /* Tools Bar */
        .tools-bar {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin: 40px 0;
            text-align: center;
        }
        
        .tools-bar a {
            display: inline-block;
            margin: 0 15px;
            color: #6c757d;
            text-decoration: none;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            font-size: 0.9rem;
            transition: color 0.3s ease;
        }
        
        .tools-bar a:hover {
            color: #B87333;
        }
        
        /* Section Footers */
        .section-footer {
            text-align: center;
            margin-top: 20px;
        }
        
        .section-footer a {
            color: #B87333;
            text-decoration: none;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            transition: color 0.3s ease;
        }
        
        .section-footer a:hover {
            color: #8B4513;
            text-decoration: underline;
        }
        
        /* Footer */
        .footer {
            background: #f8f9fa;
            padding: 30px 20px;
            text-align: center;
            color: #6c757d;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            font-size: 0.9rem;
        }
        
        .footer a {
            color: #B87333;
            text-decoration: none;
        }
        
        /* Responsive */
        @media (max-width: 1024px) {
            .items-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 768px) {
            .hero h1 {
                font-size: 2.5rem;
            }
            
            .stats-bar {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .stat {
                border-right: none;
                border-bottom: 1px solid #e9ecef;
                padding-bottom: 20px;
            }
            
            .stat:last-child {
                border-bottom: none;
            }
            
            .dashboard-grid,
            .lower-grid {
                grid-template-columns: 1fr;
            }
            
            .items-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .tools-bar a {
                display: block;
                margin: 5px 0;
            }
        }
    </style>
</head>
<body>
    <!-- Hero Section -->
    <div class="hero">
        <h1>Yakima Valley Estate Sales & Events</h1>
        <p class="tagline">Your Hub for Estate Sales, Local Events, and Community Connections</p>
        <div class="hero-buttons">
            <a href="/claims" class="hero-btn">🏛️ Browse Estate Sales</a>
            <a href="/events" class="hero-btn secondary">📅 Find Events</a>
            <a href="/seller/login" class="hero-btn secondary">🔐 Seller Login</a>
        </div>
    </div>
    
    <!-- Container -->
    <div class="container">
        <!-- Stats Bar -->
        <div class="stats-bar">
            <div class="stat">
                <div class="stat-number">{$stats['active_sales']}</div>
                <div class="stat-label">Active Sales</div>
            </div>
            <div class="stat">
                <div class="stat-number">{$stats['upcoming_events']}</div>
                <div class="stat-label">Upcoming Events</div>
            </div>
            <div class="stat">
                <div class="stat-number">{$formattedItems}</div>
                <div class="stat-label">Items Listed</div>
            </div>
            <div class="stat">
                <div class="stat-number">{$stats['local_shops']}</div>
                <div class="stat-label">Local Shops</div>
            </div>
        </div>
        
        <!-- Dashboard Grid -->
        <div class="dashboard-grid">
            <!-- Featured Items Section -->
            <section class="featured-items">
                <h2>Featured Estate Sale Finds</h2>
                <div class="items-grid">
HTML;
        
        // Debug: Check if we reach this point
        error_log("DEBUG: About to render featured items. Count: " . count($featuredItems));
        
        // Add featured items
        if (!empty($featuredItems)) {
            foreach (array_slice($featuredItems, 0, 12) as $item) {
                $itemId = $item['id'] ?? '';
                $title = htmlspecialchars($item['title'] ?? 'Untitled Item');
                $price = isset($item['price']) ? '$' . number_format($item['price'], 2) : 'Price TBD';
                $saleId = $item['sale_id'] ?? '';
                
                // TODO: Get sale name from sale data
                $saleName = 'Estate Sale';
                
                $html .= <<<HTML
                    <div class="item-card" onclick="window.location.href='/claims/item/{$itemId}'">
HTML;
                
                if (!empty($item['primary_image'])) {
                    $imagePath = htmlspecialchars($item['primary_image']);
                    $html .= <<<HTML
                        <img src="/uploads/yfclaim/items/{$imagePath}" 
                             alt="{$title}" 
                             class="item-image">
HTML;
                } else {
                    $html .= <<<HTML
                        <div class="no-image-placeholder">No Image Available</div>
HTML;
                }
                
                $html .= <<<HTML
                        <div class="item-details">
                            <div class="item-title">{$title}</div>
                            <div class="item-price">{$price}</div>
                            <div class="item-sale">{$saleName}</div>
                        </div>
                    </div>
HTML;
            }
            error_log("DEBUG: Completed rendering " . count($featuredItems) . " items");
        } else {
            // Show placeholder if no items
            $html .= <<<HTML
                    <div style="grid-column: 1 / -1; text-align: center; padding: 40px; color: #6c757d;">
                        <p>No featured items available at this time.</p>
                        <p>Check back soon for new estate sale finds!</p>
                    </div>
HTML;
        }
        
        $html .= <<<HTML
                </div>
                <div class="section-footer">
                    <a href="/claims/items">Browse All Items →</a>
                </div>
            </section>
            
            <!-- Spotlight Sidebar -->
            <aside class="spotlight-sidebar">
                <!-- Weekend Box -->
                <div class="weekend-box">
                    <h3>This Weekend</h3>
                    <div class="weekend-stats">
                        {$stats['active_sales']} Active Sales • {$stats['upcoming_events']} Upcoming Events
                    </div>
HTML;
        
        // Add hot item if available
        if ($hotItem) {
            $hotItemId = $hotItem['id'] ?? '';
            $hotItemTitle = htmlspecialchars($hotItem['title'] ?? 'Featured Item');
            $hotItemPrice = isset($hotItem['price']) ? '$' . number_format($hotItem['price'], 2) : 'Price TBD';
            
            $html .= <<<HTML
                    <div class="hot-item">
                        <h4>Hot Item</h4>
                        <div onclick="window.location.href='/claims/item/{$hotItemId}'" style="cursor: pointer;">
HTML;
            
            if (!empty($hotItem['primary_image'])) {
                $hotImagePath = htmlspecialchars($hotItem['primary_image']);
                $html .= <<<HTML
                            <img src="/uploads/yfclaim/items/{$hotImagePath}" 
                                 alt="{$hotItemTitle}" 
                                 style="width: 100%; height: 200px; object-fit: cover; border-radius: 5px; margin-bottom: 10px;">
HTML;
            }
            
            $html .= <<<HTML
                            <div class="item-title" style="font-size: 1.1rem; margin-bottom: 5px;">{$hotItemTitle}</div>
                            <div class="item-price" style="font-size: 1.3rem;">{$hotItemPrice}</div>
                        </div>
                    </div>
HTML;
        }
        
        $html .= <<<HTML
                </div>
                
                <!-- Quick Actions -->
                <div class="quick-actions">
                    <a href="/seller/login" class="primary">Seller Portal</a>
                    <a href="/claims">View All Sales</a>
                    <a href="/shops/map">Business Directory</a>
                    <a href="#">Get Email Alerts</a>
                </div>
            </aside>
        </div>
        
        <!-- Lower Grid -->
        <div class="lower-grid">
            <!-- Active Sales Section -->
            <section class="active-sales">
                <h2>Current Estate Sales</h2>
                <div class="sales-list">
HTML;
        
        // Add current sales
        if (!empty($currentSales)) {
            $salesCount = 0;
            foreach ($currentSales as $sale) {
                if ($salesCount >= 5) break;
                
                // Handle both object and array formats
                if (is_object($sale)) {
                    $saleTitle = htmlspecialchars($sale->getTitle());
                    $saleCity = htmlspecialchars($sale->getCity());
                    $saleId = $sale->getId();
                    // Format dates if methods exist
                    $dates = 'Dates TBD';
                } else {
                    $saleTitle = htmlspecialchars($sale['title'] ?? 'Estate Sale');
                    $saleCity = htmlspecialchars($sale['city'] ?? '');
                    $saleId = $sale['id'] ?? '';
                    $dates = 'Dates TBD';
                }
                
                $html .= <<<HTML
                    <div class="sale-entry">
                        <div class="sale-title">
                            <a href="/claims/sale/{$saleId}" style="color: inherit; text-decoration: none;">
                                {$saleTitle}
                            </a>
                        </div>
                        <div class="sale-details">
                            {$dates} • {$saleCity}
                        </div>
                    </div>
HTML;
                $salesCount++;
            }
        } else {
            $html .= <<<HTML
                    <p style="color: #6c757d; text-align: center; padding: 20px;">
                        No active sales at this time.
                    </p>
HTML;
        }
        
        $html .= <<<HTML
                </div>
                <div class="section-footer">
                    <a href="/claims">View All Sales →</a>
                </div>
            </section>
            
            <!-- Upcoming Events Section -->
            <section class="upcoming-events">
                <h2>This Week's Events</h2>
                <div class="events-list">
HTML;
        
        // Add upcoming events
        if (!empty($upcomingEvents)) {
            foreach ($upcomingEvents as $event) {
                // Handle both object and array formats
                if (is_object($event)) {
                    $eventTitle = htmlspecialchars($event->getTitle());
                    $eventDate = $event->getDate() ? $event->getDate()->format('M j') : 'Date TBD';
                    $eventTime = $event->getTime() ?? '';
                } else {
                    $eventTitle = htmlspecialchars($event['title'] ?? 'Community Event');
                    $eventDate = isset($event['date']) ? date('M j', strtotime($event['date'])) : 'Date TBD';
                    $eventTime = $event['time'] ?? '';
                }
                
                $html .= <<<HTML
                    <div class="event-entry">
                        <div class="event-title">{$eventTitle}</div>
                        <div class="event-details">{$eventDate} • {$eventTime}</div>
                    </div>
HTML;
            }
        } else {
            $html .= <<<HTML
                    <p style="color: #6c757d; text-align: center; padding: 20px;">
                        No upcoming events scheduled.
                    </p>
HTML;
        }
        
        $html .= <<<HTML
                </div>
                <div class="section-footer">
                    <a href="/events">View Full Calendar →</a>
                </div>
            </section>
        </div>
        
        <!-- Tools Bar -->
        <div class="tools-bar">
            <a href="/api/events">Integration & APIs</a>
            <span style="color: #dee2e6;">•</span>
            <a href="/shops/map">Business Directory & Map</a>
            <span style="color: #dee2e6;">•</span>
            <a href="/admin/login">Seller Resources</a>
            <span style="color: #dee2e6;">•</span>
            <a href="/events/submit">Submit Event</a>
            <span style="color: #dee2e6;">•</span>
            <a href="/api/health">System Status</a>
        </div>
    </div>
    
    <!-- Footer -->
    <div class="footer">
        <p>© 2024 Yakima Valley Estate Sales & Events. All rights reserved.</p>
        <p>
            <a href="/admin/login">Admin</a> | 
            <a href="/api/health">API Status</a> | 
            <a href="/debug">Debug Info</a>
        </p>
    </div>
</body>
</html>
HTML;
    }

    private function renderCombinedMapPage(): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>YFEvents V2 - Yakima Valley Map</title>
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px 20px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
        }
        
        .back-link:hover {
            text-decoration: underline;
        }
        
        .map-controls {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
        }
        
        .control-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .control-group label {
            font-weight: 500;
            color: #343a40;
        }
        
        .control-group input[type="checkbox"] {
            margin-right: 5px;
        }
        
        .legend {
            display: flex;
            gap: 20px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 0.9rem;
        }
        
        .legend-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
        }
        
        .map-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            position: relative;
        }
        
        #map {
            height: 70vh;
            min-height: 500px;
            width: 100%;
        }
        
        .info-panel {
            position: absolute;
            top: 10px;
            left: 10px;
            background: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.3);
            z-index: 100;
            max-width: 250px;
        }
        
        .info-count {
            font-weight: 500;
            margin-bottom: 5px;
        }
        
        .loading {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 70vh;
            min-height: 500px;
            color: #6c757d;
            font-size: 1.1rem;
        }
        
        @media (max-width: 768px) {
            .map-controls {
                flex-direction: column;
                align-items: stretch;
            }
            
            .legend {
                justify-content: center;
            }
            
            #map {
                height: 60vh;
                min-height: 400px;
            }
            
            .info-panel {
                max-width: 200px;
                padding: 10px;
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🗺️ Yakima Valley Map</h1>
        <p>Interactive map of local events and businesses</p>
    </div>
    
    <div class="container">
        <a href="/" class="back-link">← Back to Home</a>
        
        <div class="map-controls">
            <div class="control-group">
                <label>
                    <input type="checkbox" id="show-events" checked> Show Events
                </label>
            </div>
            
            <div class="control-group">
                <label>
                    <input type="checkbox" id="show-shops" checked> Show Shops
                </label>
            </div>
            
            <div class="control-group">
                <label>
                    <input type="checkbox" id="featured-only"> Featured Only
                </label>
            </div>
            
            <div class="legend">
                <div class="legend-item">
                    <div class="legend-dot" style="background-color: #667eea;"></div>
                    <span>Events</span>
                </div>
                <div class="legend-item">
                    <div class="legend-dot" style="background-color: #28a745;"></div>
                    <span>Shops</span>
                </div>
                <div class="legend-item">
                    <div class="legend-dot" style="background-color: #ffc107;"></div>
                    <span>Featured</span>
                </div>
                <div class="legend-item">
                    <div class="legend-dot" style="background-color: #dc3545;"></div>
                    <span>Today's Events</span>
                </div>
            </div>
        </div>
        
        <div class="map-container">
            <div class="info-panel" id="info-panel">
                <div class="info-count" id="events-count">Loading...</div>
                <div class="info-count" id="shops-count">Loading...</div>
            </div>
            <div id="map" class="loading">
                🗺️ Initializing Yakima Valley map...
            </div>
        </div>
    </div>

    <script>
        let map;
        let eventMarkers = [];
        let shopMarkers = [];
        let eventsData = [];
        let shopsData = [];
        
        // Initialize map
        function initMap() {
            // Center on Yakima Finds: 111 S. 2nd St, Yakima, WA
            const yakimaCenter = { lat: 46.600825, lng: -120.503357 };
            
            map = new google.maps.Map(document.getElementById('map'), {
                zoom: 10,
                center: yakimaCenter,
                styles: [
                    {
                        featureType: 'poi',
                        elementType: 'labels',
                        stylers: [{ visibility: 'off' }]
                    }
                ]
            });
            
            loadData();
        }
        
        async function loadData() {
            try {
                // Load events and shops in parallel
                const [eventsResponse, shopsResponse] = await Promise.all([
                    fetch('/api/events?status=approved&limit=50'),
                    fetch('/api/shops/map')
                ]);
                
                const eventsData = await eventsResponse.json();
                const shopsData = await shopsResponse.json();
                
                if (eventsData.success) {
                    window.eventsData = eventsData.data.events.filter(event => 
                        event.latitude && event.longitude
                    );
                }
                
                if (shopsData.success) {
                    window.shopsData = shopsData.data.shops.filter(shop => 
                        shop.latitude && shop.longitude
                    );
                }
                
                renderMap();
                
            } catch (error) {
                console.error('Error loading data:', error);
                document.getElementById('info-panel').innerHTML = '<div style="color: #dc3545;">Error loading data</div>';
            }
        }
        
        function renderMap() {
            clearMarkers();
            
            const showEvents = document.getElementById('show-events').checked;
            const showShops = document.getElementById('show-shops').checked;
            const featuredOnly = document.getElementById('featured-only').checked;
            
            // Add event markers
            if (showEvents && window.eventsData) {
                let events = window.eventsData;
                if (featuredOnly) {
                    events = events.filter(event => event.featured);
                }
                
                events.forEach(event => {
                    const today = new Date().toDateString();
                    const eventDate = new Date(event.start_datetime).toDateString();
                    const isToday = eventDate === today;
                    
                    const markerColor = event.featured ? '#ffc107' : 
                                      isToday ? '#dc3545' : '#667eea';
                    
                    const marker = new google.maps.Marker({
                        position: { lat: parseFloat(event.latitude), lng: parseFloat(event.longitude) },
                        map: map,
                        title: event.title || 'Event',
                        icon: {
                            url: 'http://maps.google.com/mapfiles/ms/icons/blue-dot.png',
                            scaledSize: new google.maps.Size(32, 32)
                        }
                    });
                    
                    const infoWindow = new google.maps.InfoWindow({
                        content: `
                            <div style="padding: 10px; max-width: 280px;">
                                <h4 style="margin: 0 0 8px 0; color: #667eea;">
                                    📅 \${event.featured ? '⭐ ' : ''}\${event.title || 'Untitled Event'}
                                </h4>
                                <p style="margin: 0 0 6px 0; font-size: 0.9rem;">
                                    📅 \${new Date(event.start_datetime).toLocaleDateString('en-US', {
                                        weekday: 'short',
                                        month: 'short',
                                        day: 'numeric',
                                        hour: '2-digit',
                                        minute: '2-digit'
                                    })}
                                </p>
                                \${event.location ? `<p style="margin: 0 0 8px 0; color: #6c757d; font-size: 0.9rem;">📍 \${event.location}</p>` : ''}
                                <div style="margin-top: 8px;">
                                    <a href="/events/\${event.id}" style="color: #667eea; text-decoration: none; font-size: 0.9rem;">View Details →</a>
                                </div>
                            </div>
                        `
                    });
                    
                    marker.addListener('click', () => {
                        infoWindow.open(map, marker);
                    });
                    
                    eventMarkers.push(marker);
                });
            }
            
            // Add shop markers
            if (showShops && window.shopsData) {
                let shops = window.shopsData;
                if (featuredOnly) {
                    shops = shops.filter(shop => shop.featured);
                }
                
                shops.forEach(shop => {
                    const markerColor = shop.featured ? '#ffc107' : '#28a745';
                    
                    const marker = new google.maps.Marker({
                        position: { lat: parseFloat(shop.latitude), lng: parseFloat(shop.longitude) },
                        map: map,
                        title: shop.name,
                        icon: {
                            url: 'http://maps.google.com/mapfiles/ms/icons/green-dot.png',
                            scaledSize: new google.maps.Size(32, 32)
                        }
                    });
                    
                    const infoWindow = new google.maps.InfoWindow({
                        content: `
                            <div style="padding: 10px; max-width: 280px;">
                                <h4 style="margin: 0 0 8px 0; color: #28a745;">
                                    🏪 \${shop.featured ? '⭐ ' : ''}\${shop.name}
                                </h4>
                                <p style="margin: 0 0 6px 0; color: #6c757d; font-size: 0.9rem;">📍 \${shop.address}</p>
                                \${shop.phone ? `<p style="margin: 0 0 8px 0; color: #6c757d; font-size: 0.9rem;">📞 \${shop.phone}</p>` : ''}
                                <div style="margin-top: 8px;">
                                    \${shop.website ? `<a href="\${shop.website}" target="_blank" style="color: #007bff; text-decoration: none; font-size: 0.9rem; margin-right: 10px;">🌐 Website</a>` : ''}
                                    <a href="/shops/\${shop.id}" style="color: #28a745; text-decoration: none; font-size: 0.9rem;">View Details →</a>
                                </div>
                            </div>
                        `
                    });
                    
                    marker.addListener('click', () => {
                        infoWindow.open(map, marker);
                    });
                    
                    shopMarkers.push(marker);
                });
            }
            
            updateCounts();
            
            // Fit map to show all markers
            const allMarkers = [...eventMarkers, ...shopMarkers];
            if (allMarkers.length > 0) {
                const bounds = new google.maps.LatLngBounds();
                allMarkers.forEach(marker => {
                    bounds.extend(marker.getPosition());
                });
                map.fitBounds(bounds);
                
                // Don't zoom too close
                if (allMarkers.length === 1) {
                    map.setZoom(15);
                }
            }
        }
        
        function clearMarkers() {
            eventMarkers.forEach(marker => marker.setMap(null));
            shopMarkers.forEach(marker => marker.setMap(null));
            eventMarkers = [];
            shopMarkers = [];
        }
        
        function updateCounts() {
            document.getElementById('events-count').textContent = `📅 \${eventMarkers.length} events`;
            document.getElementById('shops-count').textContent = `🏪 \${shopMarkers.length} shops`;
        }
        
        // Event listeners
        document.getElementById('show-events').addEventListener('change', renderMap);
        document.getElementById('show-shops').addEventListener('change', renderMap);
        document.getElementById('featured-only').addEventListener('change', renderMap);
        
        // Handle map load errors
        window.gm_authFailure = function() {
            document.getElementById('map').innerHTML = '<div style="padding: 40px; text-align: center; color: #dc3545;">Google Maps failed to load. Please check the API key configuration.</div>';
        };
    </script>
    
    <!-- Load Google Maps API -->
    <script async defer 
        src="https://maps.googleapis.com/maps/api/js?key=<?= defined('GOOGLE_MAPS_API_KEY') ? GOOGLE_MAPS_API_KEY : 'YOUR_GOOGLE_MAPS_API_KEY' ?>&libraries=places&callback=initMap">
    </script>
</body>
</html>
HTML;
    }
}