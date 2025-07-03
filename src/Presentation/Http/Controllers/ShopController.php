<?php

declare(strict_types=1);

namespace YFEvents\Presentation\Http\Controllers;

use YFEvents\Domain\Shops\ShopServiceInterface;
use YFEvents\Infrastructure\Container\ContainerInterface;
use YFEvents\Infrastructure\Config\ConfigInterface;
use Exception;

/**
 * Shop controller for public shop directory
 */
class ShopController extends BaseController
{
    private ShopServiceInterface $shopService;

    public function __construct(ContainerInterface $container, ConfigInterface $config)
    {
        parent::__construct($container, $config);
        $this->shopService = $container->resolve(ShopServiceInterface::class);
    }

    /**
     * Show public shops page
     */
    public function showShopsPage(): void
    {
        header('Content-Type: text/html; charset=utf-8');
        echo $this->renderShopsPage();
    }

    /**
     * Show shop details page
     */
    public function showShopDetailsPage(): void
    {
        $basePath = dirname($_SERVER['SCRIPT_NAME']);
        if ($basePath === '/') {
            $basePath = '';
        }

        $pathInfo = $_SERVER['PATH_INFO'] ?? $_SERVER['REQUEST_URI'] ?? '';
        $id = $this->extractIdFromPath($pathInfo);

        if (!$id) {
            header('HTTP/1.1 404 Not Found');
            echo '<h1>Shop not found</h1>';
            return;
        }

        header('Content-Type: text/html; charset=utf-8');
        echo $this->renderShopDetailsPage($basePath, $id);
    }

    /**
     * Show shops map page
     */
    public function showShopsMapPage(): void
    {
        $basePath = dirname($_SERVER['SCRIPT_NAME']);
        if ($basePath === '/') {
            $basePath = '';
        }

        header('Content-Type: text/html; charset=utf-8');
        echo $this->renderShopsMapPage($basePath);
    }

    /**
     * Show featured shops page
     */
    public function showFeaturedShopsPage(): void
    {
        $basePath = dirname($_SERVER['SCRIPT_NAME']);
        if ($basePath === '/') {
            $basePath = '';
        }

        header('Content-Type: text/html; charset=utf-8');
        echo $this->renderShopsPage($basePath, 'Featured Shops', 'featured');
    }

    /**
     * Show shop submission page
     */
    public function showSubmitShopPage(): void
    {
        $basePath = dirname($_SERVER['SCRIPT_NAME']);
        if ($basePath === '/') {
            $basePath = '';
        }

        header('Content-Type: text/html; charset=utf-8');
        echo $this->renderSubmitShopPage($basePath);
    }

    /**
     * Get shops for directory listing
     */
    public function getShops(): void
    {
        try {
            $input = $this->getInput();
            $pagination = $this->getPaginationParams($input);
            
            // Build filters for public directory
            $filters = [
                'status' => 'active'
            ];
            
            if (isset($input['category_id'])) {
                $filters['category_id'] = (int) $input['category_id'];
            }
            if (isset($input['featured'])) {
                $filters['featured'] = filter_var($input['featured'], FILTER_VALIDATE_BOOLEAN);
            }
            if (isset($input['verified'])) {
                $filters['verified'] = filter_var($input['verified'], FILTER_VALIDATE_BOOLEAN);
            }
            if (isset($input['payment_methods'])) {
                $filters['payment_methods'] = is_array($input['payment_methods']) 
                    ? $input['payment_methods'] 
                    : explode(',', $input['payment_methods']);
            }
            if (isset($input['amenities'])) {
                $filters['amenities'] = is_array($input['amenities']) 
                    ? $input['amenities'] 
                    : explode(',', $input['amenities']);
            }
            
            $filters['limit'] = $pagination['limit'];
            $query = $input['search'] ?? '';

            $shops = $this->shopService->searchShops($query, $filters);

            // Format shops for response
            $formattedShops = array_map(function ($shop) {
                return [
                    'id' => $shop->getId(),
                    'name' => $shop->getName(),
                    'description' => $shop->getDescription(),
                    'address' => $shop->getAddress(),
                    'latitude' => $shop->getLatitude(),
                    'longitude' => $shop->getLongitude(),
                    'phone' => $shop->getPhone(),
                    'email' => $shop->getEmail(),
                    'website' => $shop->getWebsite(),
                    'image_url' => $shop->getImageUrl(),
                    'category_id' => $shop->getCategoryId(),
                    'hours' => $shop->getFormattedHours(),
                    'payment_methods' => $shop->getPaymentMethods(),
                    'amenities' => $shop->getAmenities(),
                    'featured' => $shop->isFeatured(),
                    'verified' => $shop->isVerified(),
                    'has_coordinates' => $shop->hasCoordinates(),
                ];
            }, $shops);

            $this->successResponse([
                'shops' => $formattedShops,
                'count' => count($formattedShops),
                'pagination' => $pagination,
                'filters' => array_diff_key($filters, ['limit' => ''])
            ]);

        } catch (Exception $e) {
            $this->errorResponse('Failed to load shops: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get single shop details
     */
    public function getShop(): void
    {
        try {
            $input = $this->getInput();
            
            if (!isset($input['id'])) {
                $this->errorResponse('Shop ID is required');
                return;
            }

            $shop = $this->shopService->getShopById((int) $input['id']);
            
            if (!$shop) {
                $this->errorResponse('Shop not found', 404);
                return;
            }

            // Only show active shops in public API
            if (!$shop->isOpen()) {
                $this->errorResponse('Shop not found', 404);
                return;
            }

            $this->successResponse([
                'shop' => [
                    'id' => $shop->getId(),
                    'name' => $shop->getName(),
                    'description' => $shop->getDescription(),
                    'address' => $shop->getAddress(),
                    'latitude' => $shop->getLatitude(),
                    'longitude' => $shop->getLongitude(),
                    'phone' => $shop->getPhone(),
                    'email' => $shop->getEmail(),
                    'website' => $shop->getWebsite(),
                    'image_url' => $shop->getImageUrl(),
                    'category_id' => $shop->getCategoryId(),
                    'hours' => $shop->getFormattedHours(),
                    'payment_methods' => $shop->getPaymentMethods(),
                    'amenities' => $shop->getAmenities(),
                    'featured' => $shop->isFeatured(),
                    'verified' => $shop->isVerified(),
                    'status' => $shop->getStatus(),
                    'created_at' => $shop->getCreatedAt()?->format('c'),
                    'updated_at' => $shop->getUpdatedAt()?->format('c'),
                ]
            ]);

        } catch (Exception $e) {
            $this->errorResponse('Failed to load shop: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get shops for map display
     */
    public function getShopsForMap(): void
    {
        try {
            $input = $this->getInput();
            
            $filters = [];
            if (isset($input['category_id'])) {
                $filters['category_id'] = (int) $input['category_id'];
            }
            if (isset($input['featured'])) {
                $filters['featured'] = filter_var($input['featured'], FILTER_VALIDATE_BOOLEAN);
            }
            if (isset($input['verified'])) {
                $filters['verified'] = filter_var($input['verified'], FILTER_VALIDATE_BOOLEAN);
            }

            $shops = $this->shopService->getShopsForMap($filters);

            // Format for map markers
            $mapShops = array_map(function ($shop) {
                return [
                    'id' => $shop->getId(),
                    'name' => $shop->getName(),
                    'address' => $shop->getAddress(),
                    'latitude' => $shop->getLatitude(),
                    'longitude' => $shop->getLongitude(),
                    'phone' => $shop->getPhone(),
                    'website' => $shop->getWebsite(),
                    'featured' => $shop->isFeatured(),
                    'verified' => $shop->isVerified(),
                    'category_id' => $shop->getCategoryId(),
                ];
            }, $shops);

            $this->successResponse([
                'shops' => $mapShops,
                'count' => count($mapShops),
                'filters' => $filters
            ]);

        } catch (Exception $e) {
            $this->errorResponse('Failed to load map shops: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get featured shops
     */
    public function getFeaturedShops(): void
    {
        try {
            $input = $this->getInput();
            $limit = min(50, max(1, (int) ($input['limit'] ?? 10)));

            $shops = $this->shopService->getFeaturedShops($limit);

            $formattedShops = array_map(function ($shop) {
                return [
                    'id' => $shop->getId(),
                    'name' => $shop->getName(),
                    'description' => $shop->getDescription(),
                    'address' => $shop->getAddress(),
                    'phone' => $shop->getPhone(),
                    'website' => $shop->getWebsite(),
                    'image_url' => $shop->getImageUrl(),
                    'featured' => $shop->isFeatured(),
                    'verified' => $shop->isVerified(),
                ];
            }, $shops);

            $this->successResponse([
                'shops' => $formattedShops,
                'count' => count($formattedShops)
            ]);

        } catch (Exception $e) {
            $this->errorResponse('Failed to load featured shops: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get shops near location
     */
    public function getShopsNearLocation(): void
    {
        try {
            $input = $this->getInput();
            
            $missing = $this->validateRequired($input, ['latitude', 'longitude']);
            if (!empty($missing)) {
                $this->errorResponse('Missing required fields: ' . implode(', ', $missing));
                return;
            }

            $latitude = (float) $input['latitude'];
            $longitude = (float) $input['longitude'];
            $radius = (float) ($input['radius'] ?? 10);

            $shops = $this->shopService->getShopsNearLocation($latitude, $longitude, $radius);

            $formattedShops = array_map(function ($shop) {
                return [
                    'id' => $shop->getId(),
                    'name' => $shop->getName(),
                    'description' => $shop->getDescription(),
                    'address' => $shop->getAddress(),
                    'latitude' => $shop->getLatitude(),
                    'longitude' => $shop->getLongitude(),
                    'phone' => $shop->getPhone(),
                    'website' => $shop->getWebsite(),
                    'featured' => $shop->isFeatured(),
                    'verified' => $shop->isVerified(),
                ];
            }, $shops);

            $this->successResponse([
                'shops' => $formattedShops,
                'count' => count($formattedShops),
                'search_center' => [
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'radius_miles' => $radius
                ]
            ]);

        } catch (Exception $e) {
            $this->errorResponse('Location search failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Submit a new shop (public submission)
     */
    public function submitShop(): void
    {
        try {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                $this->errorResponse('POST method required', 405);
                return;
            }

            $input = $this->getInput();
            
            $missing = $this->validateRequired($input, ['name', 'address']);
            if (!empty($missing)) {
                $this->errorResponse('Missing required fields: ' . implode(', ', $missing));
                return;
            }

            // Public submissions default to pending status
            $shopData = [
                'name' => $input['name'],
                'description' => $input['description'] ?? null,
                'address' => $input['address'],
                'phone' => $input['phone'] ?? null,
                'email' => $input['email'] ?? null,
                'website' => $input['website'] ?? null,
                'category_id' => isset($input['category_id']) ? (int) $input['category_id'] : null,
                'hours' => $input['hours'] ?? [],
                'operating_hours' => $input['operating_hours'] ?? [],
                'payment_methods' => $input['payment_methods'] ?? [],
                'amenities' => $input['amenities'] ?? [],
                'status' => 'pending', // All public submissions need approval
                'featured' => false,
                'verified' => false,
            ];

            $shop = $this->shopService->createShop($shopData);

            $this->successResponse([
                'shop_id' => $shop->getId(),
                'status' => 'pending_approval'
            ], 'Shop submitted successfully and is pending approval');

        } catch (Exception $e) {
            $this->errorResponse('Failed to submit shop: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Extract ID from path for detail pages
     */
    private function extractIdFromPath(string $path): ?int
    {
        if (preg_match('/\/shops\/(\d+)/', $path, $matches)) {
            return (int) $matches[1];
        }
        return null;
    }

    private function renderShopDetailsPage(string $basePath, int $shopId): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>YFEvents V2 - Shop Details</title>
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
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 40px 20px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 3rem;
            margin-bottom: 10px;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 40px 20px;
        }
        
        .shop-details {
            background: white;
            border-radius: 15px;
            padding: 40px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #28a745;
            text-decoration: none;
            font-weight: 500;
        }
        
        .back-link:hover {
            text-decoration: underline;
        }
        
        .loading {
            text-align: center;
            padding: 40px;
            color: #6c757d;
        }
        
        .shop-name {
            font-size: 2rem;
            color: #343a40;
            margin-bottom: 20px;
        }
        
        .shop-info {
            margin-bottom: 20px;
            line-height: 1.6;
        }
        
        .shop-info strong {
            color: #495057;
        }
        
        .contact-info {
            background: #e8f5e8;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
        }
        
        .features {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin: 20px 0;
        }
        
        .feature-tag {
            background: #28a745;
            color: white;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🏪 Shop Details</h1>
    </div>
    
    <div class="container">
        <a href="{$basePath}/shops" class="back-link">← Back to Shops</a>
        
        <div class="shop-details" id="shop-details">
            <div class="loading">Loading shop details...</div>
        </div>
    </div>

    <script>
        async function loadShopDetails() {
            try {
                const response = await fetch('{$basePath}/api/shops/{$shopId}');
                const data = await response.json();
                
                if (data.success && data.data.shop) {
                    const shop = data.data.shop;
                    const container = document.getElementById('shop-details');
                    
                    container.innerHTML = `
                        <div class="shop-name">\${shop.name || 'Unnamed Shop'}</div>
                        <div class="shop-info">
                            <strong>Address:</strong> \${shop.address || 'Not provided'}<br>
                            \${shop.description ? `<strong>About:</strong> \${shop.description}<br><br>` : ''}
                        </div>
                        
                        <div class="contact-info">
                            <h3>Contact Information</h3>
                            \${shop.phone ? `<p><strong>Phone:</strong> \${shop.phone}</p>` : ''}
                            \${shop.email ? `<p><strong>Email:</strong> \${shop.email}</p>` : ''}
                            \${shop.website ? `<p><strong>Website:</strong> <a href="\${shop.website}" target="_blank">\${shop.website}</a></p>` : ''}
                        </div>
                        
                        \${shop.hours && Object.keys(shop.hours).length ? `
                            <div class="contact-info">
                                <h3>Operating Hours</h3>
                                \${Object.entries(shop.hours).map(([day, hours]) => 
                                    `<p><strong>\${day}:</strong> \${hours === 'closed' ? 'Closed' : hours}</p>`
                                ).join('')}
                            </div>
                        ` : ''}
                        
                        <div class="features">
                            \${shop.featured ? '<span class="feature-tag">⭐ Featured</span>' : ''}
                            \${shop.verified ? '<span class="feature-tag">✓ Verified</span>' : ''}
                            \${shop.payment_methods ? shop.payment_methods.map(method => 
                                `<span class="feature-tag">💳 \${method}</span>`
                            ).join('') : ''}
                            \${shop.amenities ? shop.amenities.map(amenity => 
                                `<span class="feature-tag">🏪 \${amenity}</span>`
                            ).join('') : ''}
                        </div>
                    `;
                } else {
                    document.getElementById('shop-details').innerHTML = '<div class="loading">Shop not found or unavailable.</div>';
                }
                
            } catch (error) {
                console.error('Error loading shop details:', error);
                document.getElementById('shop-details').innerHTML = '<div class="loading">Error loading shop details.</div>';
            }
        }
        
        document.addEventListener('DOMContentLoaded', loadShopDetails);
    </script>
</body>
</html>
HTML;
    }

    private function renderShopsMapPage(string $basePath): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>YFEvents V2 - Shops Map</title>
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
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
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
            color: #28a745;
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
        
        .control-group select, .control-group input {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 0.9rem;
        }
        
        .legend {
            display: flex;
            gap: 20px;
            align-items: center;
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
            height: 600px;
            width: 100%;
        }
        
        .shops-count {
            position: absolute;
            top: 10px;
            left: 10px;
            background: white;
            padding: 10px 15px;
            border-radius: 6px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.3);
            z-index: 100;
            font-weight: 500;
        }
        
        .loading {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 600px;
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
                height: 400px;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🗺️ Local Shops Map</h1>
        <p>Interactive map of businesses in the Yakima Valley</p>
    </div>
    
    <div class="container">
        <a href="{$basePath}/shops" class="back-link">← Back to Shops Directory</a>
        
        <div class="map-controls">
            <div class="control-group">
                <label for="category-filter">Category:</label>
                <select id="category-filter">
                    <option value="all">All Categories</option>
                    <option value="restaurant">Restaurants</option>
                    <option value="retail">Retail</option>
                    <option value="services">Services</option>
                    <option value="entertainment">Entertainment</option>
                </select>
            </div>
            
            <div class="control-group">
                <label>
                    <input type="checkbox" id="featured-only"> Featured Only
                </label>
            </div>
            
            <div class="legend">
                <div class="legend-item">
                    <div class="legend-dot" style="background-color: #28a745;"></div>
                    <span>Shop</span>
                </div>
                <div class="legend-item">
                    <div class="legend-dot" style="background-color: #ffc107;"></div>
                    <span>Featured</span>
                </div>
                <div class="legend-item">
                    <div class="legend-dot" style="background-color: #007bff;"></div>
                    <span>Verified</span>
                </div>
            </div>
        </div>
        
        <div class="map-container">
            <div class="shops-count" id="shops-count">Loading shops...</div>
            <div id="map" class="loading">
                🗺️ Initializing interactive map...
            </div>
        </div>
    </div>

    <script>
        let map;
        let markers = [];
        let shopsData = [];
        
        // Initialize map
        function initMap() {
            // Center on Yakima Finds: 111 S. 2nd St, Yakima, WA
            const yakimaCenter = { lat: 46.600825, lng: -120.503357 };
            
            map = new google.maps.Map(document.getElementById('map'), {
                zoom: 11,
                center: yakimaCenter,
                styles: [
                    {
                        featureType: 'poi',
                        elementType: 'labels',
                        stylers: [{ visibility: 'off' }]
                    }
                ]
            });
            
            loadShopsForMap();
        }
        
        async function loadShopsForMap() {
            try {
                const response = await fetch('{$basePath}/api/shops/map');
                const data = await response.json();
                
                if (data.success && data.data.shops) {
                    shopsData = data.data.shops.filter(shop => shop.latitude && shop.longitude);
                    displayShopsOnMap(shopsData);
                    updateShopsCount(shopsData.length);
                } else {
                    console.error('Failed to load shops data');
                    document.getElementById('shops-count').textContent = 'Error loading shops';
                }
                
            } catch (error) {
                console.error('Error loading shops for map:', error);
                document.getElementById('shops-count').textContent = 'Error loading shops';
            }
        }
        
        function displayShopsOnMap(shops) {
            // Clear existing markers
            markers.forEach(marker => marker.setMap(null));
            markers = [];
            
            // Add markers for each shop
            shops.forEach(shop => {
                const markerColor = shop.featured ? '#ffc107' : 
                                  shop.verified ? '#007bff' : '#28a745';
                
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
                        <div style="padding: 10px; max-width: 250px;">
                            <h3 style="margin: 0 0 8px 0; color: #343a40;">
                                \${shop.featured ? '⭐ ' : ''}\${shop.name}
                            </h3>
                            <p style="margin: 0 0 8px 0; color: #6c757d; font-size: 0.9rem;">
                                📍 \${shop.address}
                            </p>
                            \${shop.phone ? `<p style="margin: 0 0 8px 0; color: #6c757d; font-size: 0.9rem;">📞 \${shop.phone}</p>` : ''}
                            <div style="margin-top: 10px;">
                                \${shop.website ? `<a href="\${shop.website}" target="_blank" style="color: #007bff; text-decoration: none; margin-right: 10px;">🌐 Website</a>` : ''}
                                <a href="{$basePath}/shops/\${shop.id}" style="color: #28a745; text-decoration: none;">👁️ View Details</a>
                            </div>
                            \${shop.verified ? '<div style="margin-top: 8px; color: #007bff; font-size: 0.8rem;">✓ Verified Business</div>' : ''}
                        </div>
                    `
                });
                
                marker.addListener('click', () => {
                    infoWindow.open(map, marker);
                });
                
                markers.push(marker);
            });
            
            // Fit map to show all markers
            if (shops.length > 0) {
                const bounds = new google.maps.LatLngBounds();
                shops.forEach(shop => {
                    bounds.extend({ lat: parseFloat(shop.latitude), lng: parseFloat(shop.longitude) });
                });
                map.fitBounds(bounds);
                
                // Don't zoom too close for single markers
                if (shops.length === 1) {
                    map.setZoom(15);
                }
            }
        }
        
        function updateShopsCount(count) {
            document.getElementById('shops-count').textContent = `\${count} shops on map`;
        }
        
        function filterShops() {
            const category = document.getElementById('category-filter').value;
            const featuredOnly = document.getElementById('featured-only').checked;
            
            let filteredShops = shopsData;
            
            if (category !== 'all') {
                // Note: Would need category_name field in API response for this to work
                // For now, just show all shops
            }
            
            if (featuredOnly) {
                filteredShops = filteredShops.filter(shop => shop.featured);
            }
            
            displayShopsOnMap(filteredShops);
            updateShopsCount(filteredShops.length);
        }
        
        // Event listeners
        document.getElementById('category-filter').addEventListener('change', filterShops);
        document.getElementById('featured-only').addEventListener('change', filterShops);
        
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

    private function renderSubmitShopPage(string $basePath): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>YFEvents V2 - Submit Shop</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .form-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1);
            padding: 40px;
            width: 100%;
            max-width: 600px;
        }
        
        .form-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .form-header h1 {
            font-size: 2rem;
            color: #343a40;
            margin-bottom: 10px;
        }
        
        .form-header p {
            color: #6c757d;
            font-size: 1.1rem;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            font-weight: 600;
            color: #343a40;
            margin-bottom: 5px;
        }
        
        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.2s ease;
        }
        
        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: #28a745;
        }
        
        .form-group textarea {
            height: 100px;
            resize: vertical;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        .submit-btn {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s ease;
        }
        
        .submit-btn:hover {
            transform: translateY(-2px);
        }
        
        .submit-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        
        .back-link {
            display: block;
            text-align: center;
            margin-top: 20px;
            color: #28a745;
            text-decoration: none;
            font-weight: 500;
        }
        
        .back-link:hover {
            text-decoration: underline;
        }
        
        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: none;
        }
        
        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: none;
        }
        
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="form-container">
        <div class="form-header">
            <h1>🏪 Submit Your Shop</h1>
            <p>Add your business to our local directory</p>
        </div>
        
        <div class="success-message" id="success-message"></div>
        <div class="error-message" id="error-message"></div>
        
        <form id="shop-form">
            <div class="form-group">
                <label for="name">Business Name *</label>
                <input type="text" id="name" name="name" required>
            </div>
            
            <div class="form-group">
                <label for="description">Description</label>
                <textarea id="description" name="description" placeholder="Tell us about your business..."></textarea>
            </div>
            
            <div class="form-group">
                <label for="address">Address *</label>
                <input type="text" id="address" name="address" required placeholder="Full business address">
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="phone">Phone</label>
                    <input type="tel" id="phone" name="phone" placeholder="(xxx) xxx-xxxx">
                </div>
                
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" placeholder="contact@business.com">
                </div>
            </div>
            
            <div class="form-group">
                <label for="website">Website</label>
                <input type="url" id="website" name="website" placeholder="https://yourbusiness.com">
            </div>
            
            <div class="form-group">
                <label for="category">Category</label>
                <select id="category" name="category_id">
                    <option value="">Select a category</option>
                    <option value="1">Restaurant</option>
                    <option value="2">Retail</option>
                    <option value="3">Services</option>
                    <option value="4">Entertainment</option>
                    <option value="5">Other</option>
                </select>
            </div>
            
            <button type="submit" class="submit-btn" id="submit-btn">Submit Shop for Review</button>
        </form>
        
        <a href="{$basePath}/shops" class="back-link">← Back to Shops Directory</a>
    </div>

    <script>
        document.getElementById('shop-form').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const submitBtn = document.getElementById('submit-btn');
            const successMessage = document.getElementById('success-message');
            const errorMessage = document.getElementById('error-message');
            
            // Hide previous messages
            successMessage.style.display = 'none';
            errorMessage.style.display = 'none';
            
            // Disable submit button
            submitBtn.disabled = true;
            submitBtn.textContent = 'Submitting...';
            
            try {
                const formData = new FormData(this);
                const shopData = Object.fromEntries(formData.entries());
                
                const response = await fetch('{$basePath}/api/shops/submit', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(shopData)
                });
                
                const data = await response.json();
                
                if (data.success) {
                    successMessage.textContent = data.message || 'Shop submitted successfully! It will be reviewed by our team.';
                    successMessage.style.display = 'block';
                    this.reset(); // Clear form
                } else {
                    errorMessage.textContent = data.message || 'Failed to submit shop. Please try again.';
                    errorMessage.style.display = 'block';
                }
                
            } catch (error) {
                console.error('Error submitting shop:', error);
                errorMessage.textContent = 'Network error. Please check your connection and try again.';
                errorMessage.style.display = 'block';
            } finally {
                // Re-enable submit button
                submitBtn.disabled = false;
                submitBtn.textContent = 'Submit Shop for Review';
            }
        });
    </script>
</body>
</html>
HTML;
    }

    private function renderShopsPage(string $basePath): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>YFEvents V2 - Local Shops Directory</title>
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
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 40px 20px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 3rem;
            margin-bottom: 10px;
        }
        
        .header p {
            font-size: 1.2rem;
            opacity: 0.9;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 20px;
        }
        
        .filters {
            background: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .filter-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
        }
        
        .filter-group label {
            font-weight: 500;
            color: #343a40;
            margin-bottom: 5px;
        }
        
        .filter-group input, .filter-group select {
            padding: 10px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 1rem;
        }
        
        .filter-group input:focus, .filter-group select:focus {
            outline: none;
            border-color: #28a745;
        }
        
        .search-btn {
            padding: 10px 30px;
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            cursor: pointer;
            transition: transform 0.2s ease;
        }
        
        .search-btn:hover {
            transform: translateY(-2px);
        }
        
        .shops-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 30px;
            margin-top: 30px;
        }
        
        .shop-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .shop-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
        }
        
        .shop-name {
            font-size: 1.25rem;
            font-weight: 600;
            color: #343a40;
            margin-bottom: 10px;
        }
        
        .shop-address {
            color: #6c757d;
            margin-bottom: 10px;
        }
        
        .shop-contact {
            color: #28a745;
            font-weight: 500;
            margin-bottom: 15px;
        }
        
        .shop-description {
            color: #495057;
            line-height: 1.5;
            margin-bottom: 15px;
        }
        
        .shop-features {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 15px;
        }
        
        .feature-tag {
            background: #e9f7ef;
            color: #28a745;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
        }
        
        .shop-actions {
            display: flex;
            gap: 10px;
        }
        
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            text-decoration: none;
            font-size: 0.9rem;
            cursor: pointer;
            transition: opacity 0.2s ease;
        }
        
        .btn:hover {
            opacity: 0.8;
        }
        
        .btn-primary {
            background: #28a745;
            color: white;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .loading {
            text-align: center;
            padding: 40px;
            color: #6c757d;
        }
        
        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #28a745;
            text-decoration: none;
            font-weight: 500;
        }
        
        .back-link:hover {
            text-decoration: underline;
        }
        
        .featured {
            border: 2px solid #ffc107;
            position: relative;
        }
        
        .featured::before {
            content: "⭐ Featured";
            position: absolute;
            top: -10px;
            right: 15px;
            background: #ffc107;
            color: #212529;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🏪 Local Shops Directory</h1>
        <p>Discover local businesses in the Yakima Valley</p>
    </div>
    
    <div class="container">
        <a href="{$basePath}/" class="back-link">← Back to Home</a>
        
        <div class="filters">
            <div class="filter-row">
                <div class="filter-group">
                    <label for="search">Search Shops</label>
                    <input type="text" id="search" placeholder="Search by name, description...">
                </div>
                
                <div class="filter-group">
                    <label for="category">Category</label>
                    <select id="category">
                        <option value="all">All Categories</option>
                        <option value="restaurant">Restaurants</option>
                        <option value="retail">Retail</option>
                        <option value="services">Services</option>
                        <option value="entertainment">Entertainment</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="featured">Show</label>
                    <select id="featured">
                        <option value="all">All Shops</option>
                        <option value="featured">Featured Only</option>
                        <option value="verified">Verified Only</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label>&nbsp;</label>
                    <button class="search-btn" onclick="loadShops()">🔍 Search Shops</button>
                </div>
            </div>
        </div>
        
        <div id="shops-container">
            <div class="loading">Loading shops...</div>
        </div>
    </div>

    <script>
        async function loadShops() {
            const container = document.getElementById('shops-container');
            container.innerHTML = '<div class="loading">Loading shops...</div>';
            
            try {
                const searchParams = new URLSearchParams();
                const search = document.getElementById('search').value;
                const category = document.getElementById('category').value;
                const featured = document.getElementById('featured').value;
                
                if (search) searchParams.append('search', search);
                if (category !== 'all') searchParams.append('category', category);
                if (featured !== 'all') searchParams.append('filter', featured);
                searchParams.append('limit', '20');
                
                const response = await fetch(`{$basePath}/api/shops?\${searchParams}`);
                const data = await response.json();
                
                if (data.data?.shops && data.data.shops.length > 0) {
                    container.innerHTML = `
                        <div class="shops-grid">
                            \${data.data.shops.map(shop => `
                                <div class="shop-card \${shop.featured ? 'featured' : ''}">
                                    <div class="shop-name">\${shop.name || 'Unnamed Shop'}</div>
                                    <div class="shop-address">📍 \${shop.address || 'Address not provided'}</div>
                                    \${shop.phone ? `<div class="shop-contact">📞 \${shop.phone}</div>` : ''}
                                    \${shop.description ? `<div class="shop-description">\${shop.description.substring(0, 150)}\${shop.description.length > 150 ? '...' : ''}</div>` : ''}
                                    <div class="shop-features">
                                        \${shop.verified ? '<span class="feature-tag">✓ Verified</span>' : ''}
                                        \${shop.has_coordinates ? '<span class="feature-tag">📍 On Map</span>' : ''}
                                        \${Object.keys(shop.hours || {}).some(day => shop.hours[day] !== 'closed') ? '<span class="feature-tag">⏰ Hours Available</span>' : ''}
                                    </div>
                                    <div class="shop-actions">
                                        \${shop.website ? `<a href="\${shop.website}" target="_blank" class="btn btn-primary">Visit Website</a>` : ''}
                                        <a href="{$basePath}/shops/\${shop.id}" class="btn btn-secondary">View Details</a>
                                    </div>
                                </div>
                            `).join('')}
                        </div>
                    `;
                } else {
                    container.innerHTML = '<div class="loading">No shops found matching your criteria.</div>';
                }
                
            } catch (error) {
                container.innerHTML = '<div class="loading">Error loading shops. Please try again.</div>';
                console.error('Error loading shops:', error);
            }
        }
        
        // Load shops on page load
        document.addEventListener('DOMContentLoaded', loadShops);
        
        // Add enter key support for search
        document.getElementById('search').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                loadShops();
            }
        });
    </script>
</body>
</html>
HTML;
    }
}