<?php

declare(strict_types=1);

namespace YakimaFinds\Domain\Claims;

interface ItemRepositoryInterface
{
    /**
     * Find item by ID
     */
    public function findById(int $id): ?Item;

    /**
     * Find items by sale ID
     */
    public function findBySaleId(int $saleId, ?string $status = null): array;

    /**
     * Find items by category
     */
    public function findByCategory(int $categoryId, int $limit = 20, int $offset = 0): array;

    /**
     * Find items with offers
     */
    public function findWithOffers(int $saleId): array;

    /**
     * Find sold items
     */
    public function findSold(int $saleId): array;

    /**
     * Search items
     */
    public function search(string $query, ?int $saleId = null, int $limit = 20): array;

    /**
     * Save item (create or update)
     */
    public function save(Item $item): Item;

    /**
     * Save multiple items
     */
    public function saveMultiple(array $items): array;

    /**
     * Delete item
     */
    public function delete(int $id): void;

    /**
     * Delete items by sale
     */
    public function deleteBySale(int $saleId): void;

    /**
     * Count items by sale
     */
    public function countBySale(int $saleId, ?string $status = null): int;

    /**
     * Get item statistics
     */
    public function getStatistics(int $itemId): array;

    /**
     * Increment view count
     */
    public function incrementViewCount(int $itemId): void;

    /**
     * Get popular items
     */
    public function getPopular(int $saleId, int $limit = 10): array;
}