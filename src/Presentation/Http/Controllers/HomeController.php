<?php

declare(strict_types=1);

namespace YFEvents\Presentation\Http\Controllers;

use YFEvents\Infrastructure\Container\ContainerInterface;
use YFEvents\Infrastructure\Config\ConfigInterface;
use YFEvents\Infrastructure\Database\ConnectionInterface;

class HomeController
{
    public function __construct(
        private ContainerInterface $container,
        private ConfigInterface $config
    ) {}

    /**
     * Display the home page
     */
    public function index(): void
    {
        header('Content-Type: text/html; charset=utf-8');
        
        $basePath = dirname($_SERVER['SCRIPT_NAME']);
        if ($basePath === '/') {
            $basePath = '';
        }
        
        echo $this->renderHomePage($basePath);
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
        $basePath = dirname($_SERVER['SCRIPT_NAME']);
        if ($basePath === '/') {
            $basePath = '';
        }

        header('Content-Type: text/html; charset=utf-8');
        echo $this->renderCombinedMapPage($basePath);
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

    private function renderHomePage(string $basePath): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>YFEvents V2 - Refactored Application</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1);
            padding: 40px;
            max-width: 800px;
            width: 90%;
            text-align: center;
        }
        
        .logo {
            font-size: 3rem;
            font-weight: bold;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 10px;
        }
        
        .version {
            color: #6c757d;
            font-size: 1.1rem;
            margin-bottom: 30px;
        }
        
        .description {
            color: #495057;
            font-size: 1.1rem;
            line-height: 1.6;
            margin-bottom: 40px;
        }
        
        .features {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 30px;
            margin-bottom: 40px;
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
        
        .endpoints {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }
        
        .endpoint {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            text-align: left;
        }
        
        .endpoint-title {
            font-weight: 600;
            color: #343a40;
            margin-bottom: 15px;
        }
        
        .endpoint-list {
            list-style: none;
        }
        
        .endpoint-list li {
            margin-bottom: 8px;
        }
        
        .endpoint-list a {
            color: #007bff;
            text-decoration: none;
            font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
            font-size: 0.9rem;
        }
        
        .endpoint-list a:hover {
            text-decoration: underline;
        }
        
        .status {
            display: inline-block;
            background: #28a745;
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            margin-bottom: 20px;
        }
        
        .architecture {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 30px;
            margin-top: 30px;
            text-align: left;
        }
        
        .architecture h3 {
            color: #343a40;
            margin-bottom: 15px;
        }
        
        .architecture ul {
            color: #495057;
            padding-left: 20px;
        }
        
        .architecture li {
            margin-bottom: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">YFEvents V2</div>
        <div class="version">Refactored Application</div>
        <div class="status">✅ ONLINE</div>
        
        <p class="description">
            Welcome to the completely refactored YFEvents system! This modern, enterprise-grade application 
            features clean architecture, comprehensive APIs, and modular design.
        </p>
        
        <div class="features">
            <div class="feature">
                <div class="feature-icon">🏗️</div>
                <div class="feature-title">Clean Architecture</div>
                <div class="feature-desc">Domain-driven design with clear separation of concerns</div>
            </div>
            
            <div class="feature">
                <div class="feature-icon">🚀</div>
                <div class="feature-title">Modern PHP 8.1+</div>
                <div class="feature-desc">Strict typing, interfaces, and best practices</div>
            </div>
            
            <div class="feature">
                <div class="feature-icon">📱</div>
                <div class="feature-title">RESTful APIs</div>
                <div class="feature-desc">70+ endpoints with comprehensive coverage</div>
            </div>
            
            <div class="feature">
                <div class="feature-icon">🛡️</div>
                <div class="feature-title">Enterprise Ready</div>
                <div class="feature-desc">Security, logging, and scalability built-in</div>
            </div>
        </div>
        
        <div class="endpoints">
            <div class="endpoint">
                <div class="endpoint-title">📅 Public Events</div>
                <ul class="endpoint-list">
                    <li><a href="{$basePath}/events">Browse Events</a></li>
                    <li><a href="{$basePath}/events/featured">Featured Events</a></li>
                    <li><a href="{$basePath}/events/upcoming">Upcoming Events</a></li>
                    <li><a href="{$basePath}/events/calendar">Calendar View</a></li>
                </ul>
            </div>
            
            <div class="endpoint">
                <div class="endpoint-title">🏪 Local Shops</div>
                <ul class="endpoint-list">
                    <li><a href="{$basePath}/shops">Browse Shops</a></li>
                    <li><a href="{$basePath}/shops/featured">Featured Shops</a></li>
                    <li><a href="{$basePath}/shops/map">Shop Map</a></li>
                    <li><a href="{$basePath}/shops/submit">Add Your Shop</a></li>
                </ul>
            </div>
            
            <div class="endpoint">
                <div class="endpoint-title">🏛️ Estate Sales (YFClaim)</div>
                <ul class="endpoint-list">
                    <li><a href="{$basePath}/claims">Browse Sales</a></li>
                    <li><a href="{$basePath}/claims/upcoming">Upcoming Sales</a></li>
                    <li><a href="{$basePath}/seller/register">Seller Registration</a></li>
                    <li><a href="{$basePath}/buyer/offers">My Offers</a></li>
                </ul>
            </div>
            
            <div class="endpoint">
                <div class="endpoint-title">🛠️ Admin & API</div>
                <ul class="endpoint-list">
                    <li><a href="{$basePath}/admin/login">Admin Login</a></li>
                    <li><a href="{$basePath}/api/events">Events API</a></li>
                    <li><a href="{$basePath}/api/shops">Shops API</a></li>
                    <li><a href="{$basePath}/api/health">Health Check</a></li>
                </ul>
            </div>
        </div>
        
        <div class="architecture">
            <h3>🏛️ Architecture Highlights</h3>
            <ul>
                <li><strong>4 Complete Domains:</strong> Events, Shops, Claims (YFClaim), Scrapers</li>
                <li><strong>Repository Pattern:</strong> Clean data access abstraction</li>
                <li><strong>Service Layer:</strong> Business logic encapsulation</li>
                <li><strong>Dependency Injection:</strong> Flexible and testable components</li>
                <li><strong>PSR Compliance:</strong> Modern PHP standards throughout</li>
                <li><strong>120+ Files:</strong> 16,500+ lines of enterprise-grade code</li>
            </ul>
        </div>
    </div>
</body>
</html>
HTML;
    }

    private function renderCombinedMapPage(string $basePath): string
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
        <a href="{$basePath}/" class="back-link">← Back to Home</a>
        
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
                    fetch('{$basePath}/api/events?status=approved&limit=50'),
                    fetch('{$basePath}/api/shops/map')
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
                                    <a href="{$basePath}/events/\${event.id}" style="color: #667eea; text-decoration: none; font-size: 0.9rem;">View Details →</a>
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
                                    <a href="{$basePath}/shops/\${shop.id}" style="color: #28a745; text-decoration: none; font-size: 0.9rem;">View Details →</a>
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