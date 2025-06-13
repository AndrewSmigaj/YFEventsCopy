<?php

declare(strict_types=1);

namespace YakimaFinds\Presentation\Http\Controllers;

use YakimaFinds\Domain\Shops\ShopServiceInterface;
use YakimaFinds\Infrastructure\Container\ContainerInterface;
use YakimaFinds\Infrastructure\Config\ConfigInterface;
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
     * Get shops for directory listing
     */
    public function getShops(): void
    {
        try {
            $input = $this->getInput();
            $pagination = $this->getPaginationParams($input);
            
            // Build filters for public directory
            $filters = [
                'status' => 'active',
                'active' => true
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
}