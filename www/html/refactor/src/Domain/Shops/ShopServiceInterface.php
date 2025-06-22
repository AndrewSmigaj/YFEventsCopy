<?php

declare(strict_types=1);

namespace YFEvents\Domain\Shops;

/**
 * Service interface for shop business logic
 */
interface ShopServiceInterface
{
    /**
     * Create a new shop
     */
    public function createShop(array $shopData): Shop;

    /**
     * Update an existing shop
     */
    public function updateShop(int $shopId, array $updateData): Shop;

    /**
     * Delete a shop
     */
    public function deleteShop(int $shopId): bool;

    /**
     * Get shop by ID
     */
    public function getShopById(int $shopId): ?Shop;

    /**
     * Get shops for directory listing
     */
    public function getShopsForDirectory(array $filters = []): array;

    /**
     * Search shops
     */
    public function searchShops(string $query, array $filters = []): array;

    /**
     * Get featured shops
     */
    public function getFeaturedShops(int $limit = 10): array;

    /**
     * Get shops near location
     */
    public function getShopsNearLocation(float $latitude, float $longitude, float $radiusMiles = 10): array;

    /**
     * Get shops for map display
     */
    public function getShopsForMap(array $filters = []): array;

    /**
     * Approve a shop
     */
    public function approveShop(int $shopId): Shop;

    /**
     * Reject a shop
     */
    public function rejectShop(int $shopId): Shop;

    /**
     * Verify a shop
     */
    public function verifyShop(int $shopId): Shop;

    /**
     * Feature a shop
     */
    public function featureShop(int $shopId, bool $featured = true): Shop;

    /**
     * Get shops by category
     */
    public function getShopsByCategory(int $categoryId): array;

    /**
     * Get shop statistics
     */
    public function getShopStatistics(): array;

    /**
     * Validate shop data
     */
    public function validateShopData(array $shopData, bool $requireRequired = true): array;

    /**
     * Bulk approve shops
     */
    public function bulkApproveShops(array $shopIds): array;

    /**
     * Bulk reject shops
     */
    public function bulkRejectShops(array $shopIds): array;
}