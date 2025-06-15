<?php

declare(strict_types=1);

namespace YakimaFinds\Presentation\Api\Controllers;

use YakimaFinds\Presentation\Http\Controllers\BaseController;
use YakimaFinds\Domain\Shops\ShopServiceInterface;
use YakimaFinds\Infrastructure\Container\ContainerInterface;
use YakimaFinds\Infrastructure\Config\ConfigInterface;
use Exception;

/**
 * RESTful API controller for shops
 */
class ShopApiController extends BaseController
{
    private ShopServiceInterface $shopService;

    public function __construct(ContainerInterface $container, ConfigInterface $config)
    {
        parent::__construct($container, $config);
        $this->shopService = $container->resolve(ShopServiceInterface::class);
        
        // Set CORS headers for API
        $this->setCorsHeaders();
    }

    /**
     * Handle GET /api/shops
     */
    public function index(): void
    {
        try {
            $input = $this->getInput();
            $pagination = $this->getPaginationParams($input);
            
            // Default to active shops for public API
            $filters = [
                'status' => 'active',
                'active' => true
            ];
            
            // Optional filters
            if (isset($input['featured'])) {
                $filters['featured'] = filter_var($input['featured'], FILTER_VALIDATE_BOOLEAN);
            }
            if (isset($input['verified'])) {
                $filters['verified'] = filter_var($input['verified'], FILTER_VALIDATE_BOOLEAN);
            }
            if (isset($input['category_id'])) {
                $filters['category_id'] = (int) $input['category_id'];
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

            // Format for API response
            $apiShops = array_map([$this, 'formatShopForApi'], $shops);

            $this->jsonResponse([
                'data' => $apiShops,
                'meta' => [
                    'count' => count($apiShops),
                    'page' => $pagination['page'],
                    'limit' => $pagination['limit'],
                    'filters' => array_diff_key($filters, ['limit' => '']),
                ],
                'links' => $this->generatePaginationLinks($pagination, count($apiShops), '/api/shops')
            ]);

        } catch (Exception $e) {
            $this->errorResponse('Failed to load shops: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Handle GET /api/shops/{id}
     */
    public function show(): void
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

            $this->jsonResponse([
                'data' => $this->formatShopForApi($shop)
            ]);

        } catch (Exception $e) {
            $this->errorResponse('Failed to load shop: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Handle GET /api/shops/featured
     */
    public function featured(): void
    {
        try {
            $input = $this->getInput();
            $limit = min(50, max(1, (int) ($input['limit'] ?? 10)));

            $shops = $this->shopService->getFeaturedShops($limit);
            $apiShops = array_map([$this, 'formatShopForApi'], $shops);

            $this->jsonResponse([
                'data' => $apiShops,
                'meta' => [
                    'count' => count($apiShops),
                    'limit' => $limit
                ]
            ]);

        } catch (Exception $e) {
            $this->errorResponse('Failed to load featured shops: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Handle GET /api/shops/map
     */
    public function map(): void
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

            // Format for map display
            $mapShops = array_map(function ($shop) {
                return [
                    'id' => $shop->getId(),
                    'name' => $shop->getName(),
                    'address' => $shop->getAddress(),
                    'coordinates' => [
                        'lat' => $shop->getLatitude(),
                        'lng' => $shop->getLongitude()
                    ],
                    'phone' => $shop->getPhone(),
                    'website' => $shop->getWebsite(),
                    'category_id' => $shop->getCategoryId(),
                    'featured' => $shop->isFeatured(),
                    'verified' => $shop->isVerified(),
                    'hours' => $shop->getFormattedHours(),
                ];
            }, $shops);

            $this->jsonResponse([
                'data' => $mapShops,
                'meta' => [
                    'count' => count($mapShops),
                    'filters' => $filters
                ]
            ]);

        } catch (Exception $e) {
            $this->errorResponse('Failed to load map shops: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Handle GET /api/shops/nearby
     */
    public function nearby(): void
    {
        try {
            $input = $this->getInput();
            
            $missing = $this->validateRequired($input, ['lat', 'lng']);
            if (!empty($missing)) {
                $this->errorResponse('Missing required fields: ' . implode(', ', $missing));
                return;
            }

            $latitude = (float) $input['lat'];
            $longitude = (float) $input['lng'];
            $radius = (float) ($input['radius'] ?? 10);

            $shops = $this->shopService->getShopsNearLocation($latitude, $longitude, $radius);
            $apiShops = array_map([$this, 'formatShopForApi'], $shops);

            $this->jsonResponse([
                'data' => $apiShops,
                'meta' => [
                    'count' => count($apiShops),
                    'search_center' => [
                        'latitude' => $latitude,
                        'longitude' => $longitude,
                        'radius_miles' => $radius
                    ]
                ]
            ]);

        } catch (Exception $e) {
            $this->errorResponse('Location search failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Handle GET /api/shops/categories/{categoryId}
     */
    public function byCategory(): void
    {
        try {
            $input = $this->getInput();
            
            if (!isset($input['category_id'])) {
                $this->errorResponse('Category ID is required');
                return;
            }

            $categoryId = (int) $input['category_id'];
            $shops = $this->shopService->getShopsByCategory($categoryId);

            // Filter to only active shops
            $activeShops = array_filter($shops, fn($shop) => $shop->isOpen());
            $apiShops = array_map([$this, 'formatShopForApi'], $activeShops);

            $this->jsonResponse([
                'data' => $apiShops,
                'meta' => [
                    'count' => count($apiShops),
                    'category_id' => $categoryId
                ]
            ]);

        } catch (Exception $e) {
            $this->errorResponse('Failed to load shops by category: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Handle POST /api/shops (public submission)
     */
    public function store(): void
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

            // API submissions default to pending
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
                'status' => 'pending',
                'featured' => false,
                'verified' => false,
            ];

            $shop = $this->shopService->createShop($shopData);

            $this->jsonResponse([
                'data' => [
                    'id' => $shop->getId(),
                    'status' => 'pending_approval',
                    'message' => 'Shop submitted successfully and is pending approval'
                ]
            ], 201);

        } catch (Exception $e) {
            $this->errorResponse('Failed to submit shop: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Format shop for API response
     */
    private function formatShopForApi($shop): array
    {
        return [
            'id' => $shop->getId(),
            'name' => $shop->getName(),
            'description' => $shop->getDescription(),
            'address' => $shop->getAddress(),
            'coordinates' => [
                'latitude' => $shop->getLatitude(),
                'longitude' => $shop->getLongitude()
            ],
            'contact' => [
                'phone' => $shop->getPhone(),
                'email' => $shop->getEmail(),
                'website' => $shop->getWebsite()
            ],
            'image_url' => $shop->getImageUrl(),
            'category_id' => $shop->getCategoryId(),
            'hours' => $shop->getFormattedHours(),
            'features' => [
                'payment_methods' => $shop->getPaymentMethods(),
                'amenities' => $shop->getAmenities()
            ],
            'status' => [
                'featured' => $shop->isFeatured(),
                'verified' => $shop->isVerified(),
                'has_coordinates' => $shop->hasCoordinates()
            ],
            'created_at' => $shop->getCreatedAt()?->format('c'),
            'updated_at' => $shop->getUpdatedAt()?->format('c'),
        ];
    }

    /**
     * Set CORS headers for API access
     */
    private function setCorsHeaders(): void
    {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
        
        // Handle preflight requests
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }
    }

    /**
     * Generate pagination links
     */
    private function generatePaginationLinks(array $pagination, int $resultCount, string $basePath): array
    {
        $baseUrl = $this->config->get('app.url') . $basePath;
        
        $links = [
            'self' => $baseUrl . '?page=' . $pagination['page'] . '&limit=' . $pagination['limit']
        ];

        if ($pagination['page'] > 1) {
            $links['prev'] = $baseUrl . '?page=' . ($pagination['page'] - 1) . '&limit=' . $pagination['limit'];
            $links['first'] = $baseUrl . '?page=1&limit=' . $pagination['limit'];
        }

        if ($resultCount >= $pagination['limit']) {
            $links['next'] = $baseUrl . '?page=' . ($pagination['page'] + 1) . '&limit=' . $pagination['limit'];
        }

        return $links;
    }

    /**
     * Get shop statistics
     */
    public function getStatistics(): void
    {
        try {
            // Placeholder implementation - could be enhanced with real data
            $statistics = [
                'total_shops' => 0,
                'active_shops' => 0,
                'pending_shops' => 0,
                'featured_shops' => 0,
                'verified_shops' => 0
            ];

            $this->successResponse(['statistics' => $statistics], 'Shop statistics retrieved successfully');

        } catch (Exception $e) {
            $this->errorResponse('Failed to get shop statistics: ' . $e->getMessage(), 500);
        }
    }
}