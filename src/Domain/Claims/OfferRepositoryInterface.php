<?php

declare(strict_types=1);

namespace YFEvents\Domain\Claims;

interface OfferRepositoryInterface
{
    /**
     * Find offer by ID
     */
    public function findById(int $id): ?Offer;

    /**
     * Find offers by item ID
     */
    public function findByItemId(int $itemId, ?string $status = null): array;

    /**
     * Find offers by buyer ID
     */
    public function findByBuyerId(int $buyerId, ?string $status = null): array;

    /**
     * Find offers by sale ID
     */
    public function findBySaleId(int $saleId, ?string $status = null): array;

    /**
     * Find winning offers by sale
     */
    public function findWinningOffersBySale(int $saleId): array;

    /**
     * Find top offers for item
     */
    public function findTopOffersForItem(int $itemId, int $limit = 5): array;

    /**
     * Save offer (create or update)
     */
    public function save(Offer $offer): Offer;

    /**
     * Delete offer
     */
    public function delete(int $id): void;

    /**
     * Delete offers by item
     */
    public function deleteByItem(int $itemId): void;

    /**
     * Count offers by item
     */
    public function countByItem(int $itemId, ?string $status = null): int;

    /**
     * Count offers by buyer
     */
    public function countByBuyer(int $buyerId, ?string $status = null): int;

    /**
     * Get offer statistics for sale
     */
    public function getSaleStatistics(int $saleId): array;

    /**
     * Check if buyer has offer on item
     */
    public function buyerHasOfferOnItem(int $buyerId, int $itemId): bool;

    /**
     * Get highest offer for item
     */
    public function getHighestOfferForItem(int $itemId): ?Offer;
}