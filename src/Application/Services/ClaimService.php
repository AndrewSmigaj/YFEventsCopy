<?php

declare(strict_types=1);

namespace YFEvents\Application\Services;

use YFEvents\Domain\Claims\Sale;
use YFEvents\Domain\Claims\Item;
use YFEvents\Domain\Claims\SaleRepositoryInterface;
use YFEvents\Domain\Claims\ItemRepositoryInterface;
use YFEvents\Application\DTOs\PaginatedResult;
use YFEvents\Infrastructure\Services\QRCodeService;
use DateTime;

/**
 * Service for managing estate sales (claims)
 * Note: Offer/bidding functionality has been removed
 */
class ClaimService
{
    public function __construct(
        private readonly SaleRepositoryInterface $saleRepository,
        private readonly ItemRepositoryInterface $itemRepository,
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
     * Get sale items
     */
    public function getSaleItems(int $saleId): array
    {
        return $this->itemRepository->findBySaleId($saleId);
    }

    /**
     * Search items across all sales
     */
    public function searchItems(string $query, int $limit = 20): array
    {
        return $this->itemRepository->search($query, null, $limit);
    }

    /**
     * Mark item as claimed
     */
    public function claimItem(int $itemId, string $buyerInfo): Item
    {
        $item = $this->itemRepository->findById($itemId);
        
        if (!$item) {
            throw new \RuntimeException("Item not found: $itemId");
        }
        
        if (!$item->isAvailable()) {
            throw new \RuntimeException("Item is not available");
        }
        
        $claimedItem = $item->update([
            'status' => 'claimed',
            'buyer_info' => $buyerInfo,
            'claimed_at' => new DateTime()
        ]);
        
        $saved = $this->itemRepository->save($claimedItem);
        
        // Update sale statistics
        $this->saleRepository->updateStatistics($item->getSaleId());
        
        return $saved;
    }

    /**
     * Get upcoming sales
     */
    public function getUpcomingSales(int $days = 7): array
    {
        return $this->saleRepository->findUpcoming($days);
    }

    /**
     * Get popular items across all active sales
     */
    public function getPopularItems(int $limit = 10): array
    {
        $activeSales = $this->saleRepository->findActive(100, 0);
        $popularItems = [];
        
        foreach ($activeSales as $sale) {
            $salePopular = $this->itemRepository->getPopular($sale->getId(), 5);
            $popularItems = array_merge($popularItems, $salePopular);
        }
        
        // Sort by newest first
        usort($popularItems, fn($a, $b) => $b->getCreatedAt()->getTimestamp() <=> $a->getCreatedAt()->getTimestamp());
        
        return array_slice($popularItems, 0, $limit);
    }

    /**
     * Delete sale and all its items
     */
    public function deleteSale(int $saleId): void
    {
        // Delete all items first
        $this->itemRepository->deleteBySale($saleId);
        
        // Then delete the sale
        $this->saleRepository->delete($saleId);
    }

    /**
     * Delete item
     */
    public function deleteItem(int $itemId): void
    {
        $item = $this->itemRepository->findById($itemId);
        
        if (!$item) {
            throw new \RuntimeException("Item not found: $itemId");
        }
        
        $this->itemRepository->delete($itemId);
        
        // Update sale statistics
        $this->saleRepository->updateStatistics($item->getSaleId());
    }
}