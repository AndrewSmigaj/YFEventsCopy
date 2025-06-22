<?php

declare(strict_types=1);

namespace YFEvents\Domain\Shops;

use YFEvents\Domain\Common\RepositoryInterface;

/**
 * Repository interface for shop data access
 */
interface ShopRepositoryInterface extends RepositoryInterface
{
    /**
     * Find shops by category
     */
    public function findByCategory(int $categoryId): array;

    /**
     * Find shops by status
     */
    public function findByStatus(string $status): array;

    /**
     * Find featured shops
     */
    public function findFeatured(int $limit = 10): array;

    /**
     * Find verified shops
     */
    public function findVerified(int $limit = 100): array;

    /**
     * Find shops near location
     */
    public function findNearLocation(float $latitude, float $longitude, float $radiusMiles = 10): array;

    /**
     * Search shops by text
     */
    public function search(string $query, array $filters = []): array;

    /**
     * Find shops by owner
     */
    public function findByOwner(int $ownerId): array;

    /**
     * Find shops by payment methods
     */
    public function findByPaymentMethods(array $methods): array;

    /**
     * Find shops by amenities
     */
    public function findByAmenities(array $amenities): array;

    /**
     * Get shops for map display
     */
    public function getShopsForMap(array $filters = []): array;

    /**
     * Count shops by status
     */
    public function countByStatus(): array;

    /**
     * Get shop statistics
     */
    public function getStatistics(): array;
}