<?php

declare(strict_types=1);

namespace YFEvents\Domain\Claims;

/**
 * Repository interface for offers
 * Note: The offer/bidding system has been removed from YFClaim
 * This interface exists only for backward compatibility
 */
interface OfferRepositoryInterface
{
    public function findById(int $id): ?Offer;
    public function findByItemId(int $itemId): array;
    public function findByBuyerId(int $buyerId): array;
    public function findBySaleId(int $saleId): array;
    public function getHighestOffer(int $itemId): ?Offer;
    public function getOfferHistory(int $itemId): array;
    public function save(Offer $offer): Offer;
    public function delete(int $id): void;
    public function deleteByItem(int $itemId): void;
    public function countByItem(int $itemId): int;
    public function countBySale(int $saleId): int;
    public function acceptOffer(int $offerId): bool;
    public function rejectOffer(int $offerId): bool;
    public function getStatistics(int $saleId): array;
}