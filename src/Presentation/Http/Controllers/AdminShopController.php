<?php

declare(strict_types=1);

namespace YFEvents\Presentation\Http\Controllers;

use YFEvents\Domain\Shops\ShopServiceInterface;
use YFEvents\Infrastructure\Container\ContainerInterface;
use YFEvents\Infrastructure\Config\ConfigInterface;
use Exception;

/**
 * Admin shop controller for shop management
 */
class AdminShopController extends BaseController
{
    private ShopServiceInterface $shopService;

    public function __construct(ContainerInterface $container, ConfigInterface $config)
    {
        parent::__construct($container, $config);
        $this->shopService = $container->resolve(ShopServiceInterface::class);
    }

    /**
     * Get all shops with admin privileges
     */
    public function getAllShops(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        try {
            $input = $this->getInput();
            $pagination = $this->getPaginationParams($input);
            
            // Build filters for admin view
            $filters = [];
            if (isset($input['status']) && $input['status'] !== 'all') {
                $filters['status'] = $input['status'];
            }
            if (isset($input['featured'])) {
                $filters['featured'] = filter_var($input['featured'], FILTER_VALIDATE_BOOLEAN);
            }
            if (isset($input['verified'])) {
                $filters['verified'] = filter_var($input['verified'], FILTER_VALIDATE_BOOLEAN);
            }
            if (isset($input['active'])) {
                $filters['active'] = filter_var($input['active'], FILTER_VALIDATE_BOOLEAN);
            }
            if (isset($input['category_id'])) {
                $filters['category_id'] = (int) $input['category_id'];
            }
            if (isset($input['owner_id'])) {
                $filters['owner_id'] = (int) $input['owner_id'];
            }

            $filters['limit'] = $pagination['limit'];
            $query = $input['search'] ?? '';

            $shops = $this->shopService->searchShops($query, $filters);

            // Format shops with admin details
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
                    'hours' => $shop->getHours(),
                    'operating_hours' => $shop->getOperatingHours(),
                    'payment_methods' => $shop->getPaymentMethods(),
                    'amenities' => $shop->getAmenities(),
                    'featured' => $shop->isFeatured(),
                    'verified' => $shop->isVerified(),
                    'owner_id' => $shop->getOwnerId(),
                    'status' => $shop->getStatus(),
                    'active' => $shop->isActive(),
                    'created_at' => $shop->getCreatedAt()?->format('Y-m-d H:i:s'),
                    'updated_at' => $shop->getUpdatedAt()?->format('Y-m-d H:i:s'),
                    'has_coordinates' => $shop->hasCoordinates(),
                ];
            }, $shops);

            $this->successResponse([
                'shops' => $formattedShops,
                'count' => count($formattedShops),
                'pagination' => $pagination,
                'filters' => $filters
            ]);

        } catch (Exception $e) {
            $this->errorResponse('Failed to load shops: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Create a new shop
     */
    public function createShop(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

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

            // Admin can set all fields including status
            $shopData = [
                'name' => $input['name'],
                'description' => $input['description'] ?? null,
                'address' => $input['address'],
                'latitude' => isset($input['latitude']) ? (float) $input['latitude'] : null,
                'longitude' => isset($input['longitude']) ? (float) $input['longitude'] : null,
                'phone' => $input['phone'] ?? null,
                'email' => $input['email'] ?? null,
                'website' => $input['website'] ?? null,
                'image_url' => $input['image_url'] ?? null,
                'category_id' => isset($input['category_id']) ? (int) $input['category_id'] : null,
                'hours' => $input['hours'] ?? [],
                'operating_hours' => $input['operating_hours'] ?? [],
                'payment_methods' => $input['payment_methods'] ?? [],
                'amenities' => $input['amenities'] ?? [],
                'featured' => filter_var($input['featured'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'verified' => filter_var($input['verified'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'owner_id' => isset($input['owner_id']) ? (int) $input['owner_id'] : null,
                'status' => $input['status'] ?? 'active',
                'active' => filter_var($input['active'] ?? true, FILTER_VALIDATE_BOOLEAN),
            ];

            $shop = $this->shopService->createShop($shopData);

            $this->successResponse([
                'shop_id' => $shop->getId(),
                'status' => $shop->getStatus()
            ], 'Shop created successfully');

        } catch (Exception $e) {
            $this->errorResponse('Failed to create shop: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Update an existing shop
     */
    public function updateShop(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        try {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                $this->errorResponse('POST method required', 405);
                return;
            }

            $input = $this->getInput();
            
            if (!isset($input['id'])) {
                $this->errorResponse('Shop ID is required');
                return;
            }

            $shopId = (int) $input['id'];
            unset($input['id']); // Remove ID from update data

            // Build update data from input
            $updateData = array_filter($input, function($value) {
                return $value !== null && $value !== '';
            });

            // Handle boolean fields
            if (isset($updateData['featured'])) {
                $updateData['featured'] = filter_var($updateData['featured'], FILTER_VALIDATE_BOOLEAN);
            }
            if (isset($updateData['verified'])) {
                $updateData['verified'] = filter_var($updateData['verified'], FILTER_VALIDATE_BOOLEAN);
            }
            if (isset($updateData['active'])) {
                $updateData['active'] = filter_var($updateData['active'], FILTER_VALIDATE_BOOLEAN);
            }

            $shop = $this->shopService->updateShop($shopId, $updateData);

            $this->successResponse([
                'shop_id' => $shop->getId(),
                'status' => $shop->getStatus()
            ], 'Shop updated successfully');

        } catch (Exception $e) {
            $this->errorResponse('Failed to update shop: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Delete a shop
     */
    public function deleteShop(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        try {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                $this->errorResponse('POST method required', 405);
                return;
            }

            $input = $this->getInput();
            
            if (!isset($input['id'])) {
                $this->errorResponse('Shop ID is required');
                return;
            }

            $shopId = (int) $input['id'];
            $success = $this->shopService->deleteShop($shopId);

            if ($success) {
                $this->successResponse([], 'Shop deleted successfully');
            } else {
                $this->errorResponse('Shop not found or could not be deleted', 404);
            }

        } catch (Exception $e) {
            $this->errorResponse('Failed to delete shop: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Approve a shop
     */
    public function approveShop(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        try {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                $this->errorResponse('POST method required', 405);
                return;
            }

            $input = $this->getInput();
            
            if (!isset($input['id'])) {
                $this->errorResponse('Shop ID is required');
                return;
            }

            $shopId = (int) $input['id'];
            $shop = $this->shopService->approveShop($shopId);

            $this->successResponse([
                'shop_id' => $shop->getId(),
                'status' => $shop->getStatus()
            ], 'Shop approved successfully');

        } catch (Exception $e) {
            $this->errorResponse('Failed to approve shop: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Reject a shop
     */
    public function rejectShop(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        try {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                $this->errorResponse('POST method required', 405);
                return;
            }

            $input = $this->getInput();
            
            if (!isset($input['id'])) {
                $this->errorResponse('Shop ID is required');
                return;
            }

            $shopId = (int) $input['id'];
            $shop = $this->shopService->rejectShop($shopId);

            $this->successResponse([
                'shop_id' => $shop->getId(),
                'status' => $shop->getStatus()
            ], 'Shop rejected successfully');

        } catch (Exception $e) {
            $this->errorResponse('Failed to reject shop: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Verify a shop
     */
    public function verifyShop(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        try {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                $this->errorResponse('POST method required', 405);
                return;
            }

            $input = $this->getInput();
            
            if (!isset($input['id'])) {
                $this->errorResponse('Shop ID is required');
                return;
            }

            $shopId = (int) $input['id'];
            $shop = $this->shopService->verifyShop($shopId);

            $this->successResponse([
                'shop_id' => $shop->getId(),
                'verified' => $shop->isVerified()
            ], 'Shop verified successfully');

        } catch (Exception $e) {
            $this->errorResponse('Failed to verify shop: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Feature a shop
     */
    public function featureShop(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        try {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                $this->errorResponse('POST method required', 405);
                return;
            }

            $input = $this->getInput();
            
            if (!isset($input['id'])) {
                $this->errorResponse('Shop ID is required');
                return;
            }

            $shopId = (int) $input['id'];
            $featured = filter_var($input['featured'] ?? true, FILTER_VALIDATE_BOOLEAN);
            
            $shop = $this->shopService->featureShop($shopId, $featured);

            $this->successResponse([
                'shop_id' => $shop->getId(),
                'featured' => $shop->isFeatured()
            ], $featured ? 'Shop featured successfully' : 'Shop unfeatured successfully');

        } catch (Exception $e) {
            $this->errorResponse('Failed to feature shop: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Bulk approve shops
     */
    public function bulkApproveShops(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        try {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                $this->errorResponse('POST method required', 405);
                return;
            }

            $input = $this->getInput();
            
            if (!isset($input['shop_ids']) || !is_array($input['shop_ids'])) {
                $this->errorResponse('Shop IDs array is required');
                return;
            }

            $shopIds = array_map('intval', $input['shop_ids']);
            $shops = $this->shopService->bulkApproveShops($shopIds);

            $this->successResponse([
                'approved_count' => count($shops),
                'shop_ids' => array_map(fn($shop) => $shop->getId(), $shops)
            ], 'Shops approved successfully');

        } catch (Exception $e) {
            $this->errorResponse('Failed to bulk approve shops: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Bulk reject shops
     */
    public function bulkRejectShops(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        try {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                $this->errorResponse('POST method required', 405);
                return;
            }

            $input = $this->getInput();
            
            if (!isset($input['shop_ids']) || !is_array($input['shop_ids'])) {
                $this->errorResponse('Shop IDs array is required');
                return;
            }

            $shopIds = array_map('intval', $input['shop_ids']);
            $shops = $this->shopService->bulkRejectShops($shopIds);

            $this->successResponse([
                'rejected_count' => count($shops),
                'shop_ids' => array_map(fn($shop) => $shop->getId(), $shops)
            ], 'Shops rejected successfully');

        } catch (Exception $e) {
            $this->errorResponse('Failed to bulk reject shops: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get shop statistics
     */
    public function getShopStatistics(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        try {
            $statistics = $this->shopService->getShopStatistics();

            $this->successResponse([
                'statistics' => $statistics
            ]);

        } catch (Exception $e) {
            $this->errorResponse('Failed to load statistics: ' . $e->getMessage(), 500);
        }
    }
}