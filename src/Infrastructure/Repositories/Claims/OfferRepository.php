<?php

declare(strict_types=1);

namespace YFEvents\Infrastructure\Repositories\Claims;

use YFEvents\Domain\Claims\Offer;
use YFEvents\Domain\Claims\OfferRepositoryInterface;
use YFEvents\Infrastructure\Database\ConnectionInterface;

/**
 * Stub implementation of OfferRepository
 * The offer/bidding system has been removed from YFClaim
 * This exists only for backward compatibility
 */
class OfferRepository implements OfferRepositoryInterface
{
    public function __construct(ConnectionInterface $connection)
    {
        // Connection not used in stub
    }

    public function findById(int $id): ?Offer
    {
        return null;
    }

    public function findByItemId(int $itemId): array
    {
        return [];
    }

    public function findByBuyerId(int $buyerId): array
    {
        return [];
    }

    public function findBySaleId(int $saleId): array
    {
        return [];
    }

    public function getHighestOffer(int $itemId): ?Offer
    {
        return null;
    }

    public function getOfferHistory(int $itemId): array
    {
        return [];
    }

    public function save(Offer $offer): Offer
    {
        // Stub - do nothing
        return $offer;
    }

    public function delete(int $id): void
    {
        // Stub - do nothing
    }

    public function deleteByItem(int $itemId): void
    {
        // Stub - do nothing
    }

    public function countByItem(int $itemId): int
    {
        return 0;
    }

    public function countBySale(int $saleId): int
    {
        return 0;
    }

    public function acceptOffer(int $offerId): bool
    {
        return false;
    }

    public function rejectOffer(int $offerId): bool
    {
        return false;
    }

    public function getStatistics(int $saleId): array
    {
        return [
            'total_offers' => 0,
            'unique_buyers' => 0,
            'average_offer' => 0,
            'highest_offer' => 0,
            'items_with_offers' => 0
        ];
    }
}