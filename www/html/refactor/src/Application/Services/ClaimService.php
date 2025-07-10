<?php

declare(strict_types=1);

namespace YakimaFinds\Application\Services;

use YakimaFinds\Domain\Claims\Sale;
use YakimaFinds\Domain\Claims\Item;
use YakimaFinds\Domain\Claims\Offer;
use YakimaFinds\Domain\Claims\SaleRepositoryInterface;
use YakimaFinds\Domain\Claims\ItemRepositoryInterface;
use YakimaFinds\Domain\Claims\OfferRepositoryInterface;
use YakimaFinds\Application\DTOs\PaginatedResult;
use YakimaFinds\Infrastructure\Services\QRCodeService;
use DateTime;

class ClaimService
{
    public function __construct(
        private readonly SaleRepositoryInterface $saleRepository,
        private readonly ItemRepositoryInterface $itemRepository,
        private readonly OfferRepositoryInterface $offerRepository,
        private readonly QRCodeService $qrCodeService
    ) {}

    /**
     * Create a new sale
     */
    public function createSale(array $data): Sale
    {
        // Generate access code
        $data['access_code'] = $this->saleRepository->generateUniqueAccessCode();
        
        // Create sale
        $sale = Sale::fromArray($data);
        $sale = $this->saleRepository->save($sale);
        
        // Generate QR code
        $qrCode = $this->qrCodeService->generateForSale($sale->getId(), $sale->getAccessCode());
        $sale = $sale->update(['qr_code' => $qrCode]);
        $sale = $this->saleRepository->save($sale);
        
        return $sale;
    }

    /**
     * Update sale
     */
    public function updateSale(int $saleId, array $data): Sale
    {
        $sale = $this->saleRepository->findById($saleId);
        
        if (!$sale) {
            throw new \RuntimeException("Sale not found: $saleId");
        }
        
        $updatedSale = $sale->update($data);
        
        return $this->saleRepository->save($updatedSale);
    }

    /**
     * Get sale with items and stats
     */
    public function getSaleDetails(int $saleId): ?Sale
    {
        $sale = $this->saleRepository->findById($saleId);
        
        if (!$sale) {
            return null;
        }
        
        // Load items
        $items = $this->itemRepository->findBySaleId($saleId);
        $sale->setItems($items);
        
        // Load statistics
        $stats = $this->saleRepository->getStatistics($saleId);
        $sale->setStats($stats);
        
        return $sale;
    }

    /**
     * Get sales by seller
     */
    public function getSellerSales(int $sellerId, int $page = 1, int $perPage = 20): PaginatedResult
    {
        $offset = ($page - 1) * $perPage;
        $sales = $this->saleRepository->findBySellerId($sellerId);
        
        // Paginate manually since we loaded all sales
        $paginatedSales = array_slice($sales, $offset, $perPage);
        
        return new PaginatedResult(
            items: $paginatedSales,
            total: count($sales),
            page: $page,
            perPage: $perPage
        );
    }

    /**
     * Get active sales
     */
    public function getActiveSales(int $page = 1, int $perPage = 20): PaginatedResult
    {
        $offset = ($page - 1) * $perPage;
        $sales = $this->saleRepository->findActive($perPage, $offset);
        
        // Get total count
        $total = count($this->saleRepository->findActive(1000, 0));
        
        return new PaginatedResult(
            items: $sales,
            total: $total,
            page: $page,
            perPage: $perPage
        );
    }

    /**
     * Get sales near location
     */
    public function getSalesNearLocation(float $latitude, float $longitude, float $radiusMiles = 25): array
    {
        return $this->saleRepository->findNearLocation($latitude, $longitude, $radiusMiles);
    }

    /**
     * Access sale by code
     */
    public function accessSaleByCode(string $accessCode): ?Sale
    {
        $sale = $this->saleRepository->findByAccessCode($accessCode);
        
        if (!$sale || !$sale->isActive()) {
            return null;
        }
        
        return $this->getSaleDetails($sale->getId());
    }

    /**
     * Add item to sale
     */
    public function addItemToSale(int $saleId, array $data): Item
    {
        $sale = $this->saleRepository->findById($saleId);
        
        if (!$sale) {
            throw new \RuntimeException("Sale not found: $saleId");
        }
        
        $data['sale_id'] = $saleId;
        $item = Item::fromArray($data);
        
        return $this->itemRepository->save($item);
    }

    /**
     * Update item
     */
    public function updateItem(int $itemId, array $data): Item
    {
        $item = $this->itemRepository->findById($itemId);
        
        if (!$item) {
            throw new \RuntimeException("Item not found: $itemId");
        }
        
        $updatedItem = $item->update($data);
        
        return $this->itemRepository->save($updatedItem);
    }

    /**
     * Add multiple items to sale
     */
    public function addMultipleItems(int $saleId, array $itemsData): array
    {
        $items = [];
        
        foreach ($itemsData as $data) {
            $data['sale_id'] = $saleId;
            $items[] = Item::fromArray($data);
        }
        
        return $this->itemRepository->saveMultiple($items);
    }

    /**
     * Get sale items with offers
     */
    public function getSaleItemsWithOffers(int $saleId): array
    {
        $items = $this->itemRepository->findWithOffers($saleId);
        
        foreach ($items as $item) {
            $offers = $this->offerRepository->findByItemId($item->getId());
            $item->setOffers($offers);
        }
        
        return $items;
    }

    /**
     * Submit offer
     */
    public function submitOffer(int $itemId, int $buyerId, float $amount, ?string $message = null): Offer
    {
        $item = $this->itemRepository->findById($itemId);
        
        if (!$item) {
            throw new \RuntimeException("Item not found: $itemId");
        }
        
        if (!$item->isAvailable()) {
            throw new \RuntimeException("Item is not available for offers");
        }
        
        // Check if buyer already has an offer
        if ($this->offerRepository->buyerHasOfferOnItem($buyerId, $itemId)) {
            throw new \RuntimeException("You already have an offer on this item");
        }
        
        // Validate offer amount
        if ($amount < $item->getStartingPrice()) {
            throw new \RuntimeException("Offer must be at least the starting price");
        }
        
        $offer = Offer::fromArray([
            'item_id' => $itemId,
            'buyer_id' => $buyerId,
            'amount' => $amount,
            'message' => $message
        ]);
        
        return $this->offerRepository->save($offer);
    }

    /**
     * Accept offer
     */
    public function acceptOffer(int $offerId, ?string $sellerNotes = null): void
    {
        $offer = $this->offerRepository->findById($offerId);
        
        if (!$offer) {
            throw new \RuntimeException("Offer not found: $offerId");
        }
        
        // Accept the offer
        $acceptedOffer = $offer->accept($sellerNotes);
        $this->offerRepository->save($acceptedOffer);
        
        // Update item with winning offer
        $item = $this->itemRepository->findById($offer->getItemId());
        $soldItem = $item->acceptOffer($offerId);
        $this->itemRepository->save($soldItem);
        
        // Reject other offers
        $otherOffers = $this->offerRepository->findByItemId($offer->getItemId(), 'pending');
        foreach ($otherOffers as $otherOffer) {
            if ($otherOffer->getId() !== $offerId) {
                $rejectedOffer = $otherOffer->reject('Another offer was accepted');
                $this->offerRepository->save($rejectedOffer);
            }
        }
        
        // Update sale statistics
        $this->saleRepository->updateStatistics($item->getSaleId());
    }

    /**
     * Reject offer
     */
    public function rejectOffer(int $offerId, ?string $reason = null): void
    {
        $offer = $this->offerRepository->findById($offerId);
        
        if (!$offer) {
            throw new \RuntimeException("Offer not found: $offerId");
        }
        
        $rejectedOffer = $offer->reject($reason);
        $this->offerRepository->save($rejectedOffer);
    }

    /**
     * Get buyer offers
     */
    public function getBuyerOffers(int $buyerId): array
    {
        return $this->offerRepository->findByBuyerId($buyerId);
    }

    /**
     * Get sale report
     */
    public function getSaleReport(int $saleId): array
    {
        $sale = $this->getSaleDetails($saleId);
        
        if (!$sale) {
            throw new \RuntimeException("Sale not found: $saleId");
        }
        
        $stats = $this->offerRepository->getSaleStatistics($saleId);
        $winningOffers = $this->offerRepository->findWinningOffersBySale($saleId);
        $soldItems = $this->itemRepository->findSold($saleId);
        
        return [
            'sale' => $sale->toArray(),
            'statistics' => $stats,
            'winning_offers' => array_map(fn($o) => $o->toArray(), $winningOffers),
            'sold_items' => array_map(fn($i) => $i->toArray(), $soldItems),
            'total_sales' => array_sum(array_map(fn($o) => $o->getAmount(), $winningOffers))
        ];
    }

    /**
     * Search items
     */
    public function searchItems(string $query, ?int $saleId = null, int $limit = 20): array
    {
        return $this->itemRepository->search($query, $saleId, $limit);
    }

    /**
     * Get popular items
     */
    public function getPopularItems(int $saleId, int $limit = 10): array
    {
        return $this->itemRepository->getPopular($saleId, $limit);
    }

    /**
     * Activate sale
     */
    public function activateSale(int $saleId): Sale
    {
        $sale = $this->saleRepository->findById($saleId);
        
        if (!$sale) {
            throw new \RuntimeException("Sale not found: $saleId");
        }
        
        $activeSale = $sale->activate();
        
        return $this->saleRepository->save($activeSale);
    }

    /**
     * Pause sale
     */
    public function pauseSale(int $saleId): Sale
    {
        $sale = $this->saleRepository->findById($saleId);
        
        if (!$sale) {
            throw new \RuntimeException("Sale not found: $saleId");
        }
        
        $pausedSale = $sale->pause();
        
        return $this->saleRepository->save($pausedSale);
    }

    /**
     * Complete sale
     */
    public function completeSale(int $saleId): Sale
    {
        $sale = $this->saleRepository->findById($saleId);
        
        if (!$sale) {
            throw new \RuntimeException("Sale not found: $saleId");
        }
        
        $completedSale = $sale->complete();
        
        return $this->saleRepository->save($completedSale);
    }
}