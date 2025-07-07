<?php

declare(strict_types=1);

namespace YFEvents\Domain\YFClaim\Repositories;

use YFEvents\Domain\YFClaim\Entities\Inquiry;

/**
 * Repository interface for Inquiry entities
 */
interface InquiryRepositoryInterface
{
    /**
     * Save an inquiry (create or update)
     */
    public function save(Inquiry $inquiry): ?Inquiry;
    
    /**
     * Find inquiry by ID
     */
    public function findById(int $id): ?Inquiry;
    
    /**
     * Find all inquiries for a seller with optional filters
     * 
     * @param int $sellerId The seller user ID
     * @param array $filters Optional filters: status, item_id, date_from, date_to
     * @param array $orderBy Order by fields
     * @param int|null $limit
     * @param int|null $offset
     * @return array Array of Inquiry entities
     */
    public function findBySellerId(
        int $sellerId, 
        array $filters = [], 
        array $orderBy = ['created_at' => 'DESC'], 
        ?int $limit = null, 
        ?int $offset = null
    ): array;
    
    /**
     * Count unread inquiries for a seller
     */
    public function countUnreadBySeller(int $sellerId): int;
    
    /**
     * Update inquiry status
     */
    public function updateStatus(int $id, string $status): bool;
    
    /**
     * Find inquiries by item ID
     */
    public function findByItemId(int $itemId): array;
    
    /**
     * Find inquiries by buyer email
     */
    public function findByBuyerEmail(string $email): array;
}